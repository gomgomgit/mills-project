<script setup lang="ts">
/**
 * LaporanCagesTrackView — screen-136--laporan-cages-track-mobile /
 * usecase-136--laporan-cages-track-mobile "Lihat Laporan Periode Cages &
 * Tracks (Mobile)" (dipasang di /reports/cages-track, meta.public = false).
 * Actors: operator, supervisor, mill_management, admin.
 *
 * Kembaran LaporanSterilizerView (screen-135) — struktur, penanganan
 * galat, dan kosakata data-testid-nya sengaja dibuat sama, karena kedua
 * layar memecahkan masalah yang sama dan perbedaan gaya di antara keduanya
 * hanya akan menjadi beban pembaca berikutnya.
 *
 * TIGA HAL YANG MENENTUKAN BENTUK BERKAS INI
 *
 * 1. NOL PERHITUNGAN ULANG DI KLIEN. Setiap angka yang tampil dipetakan
 *    apa adanya dari respons /api/cages-track-reports/summary — endpoint
 *    yang sama persis dengan laporan versi web (screen-130), sehingga
 *    kedua layar mustahil berbeda. Tidak ada penjumlahan hourly, tidak ada
 *    perata-rataan, tidak ada penurunan jeda dari daftar jam. Satu-satunya
 *    angka turunan adalah UKURAN BATANG grafik (persentase terhadap nilai
 *    tertinggi) — itu murni skala visual, bukan angka yang dibaca
 *    pengguna; angka yang dibaca tetap nilai server apa adanya.
 *
 * 2. NULL BUKAN NOL, DAN ITU TERLIHAT DI LAYAR. peak_hour,
 *    longest_gap_hours, longest_gap_date, dan avg_tippler_duration_hours
 *    boleh null. Null dirender sebagai keterangan ("tidak tersedia" /
 *    "tidak dapat dihitung"), TIDAK PERNAH sebagai 0: "jeda 0 jam" berarti
 *    stasiun tidak pernah berhenti, sedangkan null berarti jedanya tidak
 *    dapat dihitung. Dua fakta yang berbeda, dan yang satu menyesatkan.
 *
 * 3. GAGAL TERTUTUP PADA MILL + 401 BUKAN KEGAGALAN JARINGAN. Akun terikat
 *    mill yang business_unit_id-nya kosong TIDAK memicu satu pun permintaan
 *    HTTP — termasuk tidak memanggil endpoint options, yang justru akan
 *    membentuk daftar SELURUH mill di perangkat orang yang tidak berhak
 *    melihat satu pun di antaranya. Sesi yang berakhir (401) diarahkan ke
 *    Login, bukan ditampilkan sebagai "periksa koneksi Anda" dengan tombol
 *    Coba Lagi yang akan gagal selamanya; sebaliknya kegagalan jaringan
 *    yang sungguhan TIDAK PERNAH membuang periode yang sudah dipilih.
 *
 * SATU KOLOM, TANPA GULIRAN MENDATAR PADA HALAMAN. Ini layar ponsel: yang
 * lebar bukan halamannya, melainkan isi kartunya. Grafik 24 jam, grafik 14
 * tanggal, dan tabel rekap masing-masing menggulir DI DALAM kartunya
 * sendiri (.chart-scroll / .detail-table-wrap), sementara halaman tetap
 * overflow-x: hidden. Rekap harian dibungkus CollapsibleSection dan
 * TERTUTUP secara bawaan — membuka/menutupnya murni penyingkapan, tidak
 * memicu permintaan jaringan apa pun karena datanya sudah ada.
 *
 * Kosakata visual mengikuti LaporanSterilizerView dan layar mobile lain
 * (.detail-section/.filter-bar/.action-footer), BUKAN kosakata `md-*`
 * laporan web — itu bahasa grid lebar yang tidak punya arti pada satu
 * kolom selebar 360px.
 */
import { computed, onMounted, ref } from 'vue'
import FilterPanel from '@/components/filters/FilterPanel.vue'
import LoadingState from '@/components/loading/LoadingState.vue'
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
import cagesTrackReportRepo, {
  type CagesTrackReportBusinessUnitOption,
  type CagesTrackReportPeriodOption,
  type CagesTrackReportSummary,
} from '@/services/cagesTrackReportRepo'

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

const businessUnits = ref<CagesTrackReportBusinessUnitOption[]>([])

/**
 * Akun terikat mill tetapi business_unit_id-nya kosong — layar berhenti
 * total di sini, tanpa satu pun permintaan HTTP (lihat catatan 3 pada
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
 * tempat, sengaja). Operator/Supervisor/Mill Management mendapat mill-nya
 * dari akun di sisi server, jadi layar ini memang tidak punya cara untuk
 * menyebut mill lain.
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
  summary.value = null

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

const periods = ref<CagesTrackReportPeriodOption[]>([])
const selectedPeriodId = ref<string | null>(null)
const summary = ref<CagesTrackReportSummary | null>(null)

const loadingPeriods = ref(false)
/**
 * Daftar periode sudah pernah selesai dimuat untuk mill ini (audit loading
 * state 2026-10-05). Tanpa ini "Mill ini belum memiliki periode" tampil
 * selama daftar line/periode MASIH dimuat — daftar kosong saat memuat
 * terbaca sebagai "tidak ada periode".
 */
const periodsLoaded = ref(false)
const loadingSummary = ref(false)
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
    // selectedPeriodId SENGAJA tidak direset di sini: pengguna di area
    // tanpa sinyal tidak boleh dipaksa memilih periodenya dari awal, dan
    // Coba Lagi harus memuat ulang periode yang SAMA.
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
    businessUnits.value = await cagesTrackReportRepo.fetchBusinessUnits()
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
    periods.value = await cagesTrackReportRepo.fetchPeriods(scope.value)
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

  loadingSummary.value = true

  try {
    summary.value = await cagesTrackReportRepo.fetchSummary(periodId, scope.value)
  } catch (error) {
    handleError(error, loadSummary)
  } finally {
    loadingSummary.value = false
  }
}

async function onPeriodChange(): Promise<void> {
  clearErrors()
  summary.value = null

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
  summary.value = null
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
    // Berhenti di sini. TIDAK ada permintaan apa pun — lihat catatan 3.
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

  return `laporan-cages-track_${slug || 'periode'}.csv`
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
    // Isi CSV dibentuk SERVER (satu baris per rincian penumpahan per jam).
    // Layar ini tidak pernah menyusun berkasnya dari angka yang tampil.
    const blob = await cagesTrackReportRepo.exportCsv(periodId, scope.value)
    cagesTrackReportRepo.saveCsvFile(blob, exportFilename())
  } catch (error) {
    // Kegagalan ekspor tidak dapat "diulang" secara bermakna oleh tombol
    // Coba Lagi yang sama, jadi retry-nya null.
    handleError(error, null)
  } finally {
    exporting.value = false
  }
}

/* ------------------------------------------------------------------ */
/* Turunan tampilan (tanpa perhitungan angka laporan)                  */
/* ------------------------------------------------------------------ */

const kpi = computed(() => summary.value?.kpi ?? null)
const hourly = computed(() => summary.value?.hourly ?? [])
const daily = computed(() => summary.value?.daily ?? [])
const queue = computed(() => summary.value?.queue ?? null)
const total = computed(() => summary.value?.total ?? null)

/** Periode tanpa satu pun penumpahan — grafik TIDAK dirender sebagai kotak kosong. */
const emptyPeriod = computed(
  () => Boolean(summary.value) && (kpi.value?.total_cages_tipped ?? 0) === 0,
)

const hasReport = computed(
  () => Boolean(summary.value) && !networkError.value && !errorMessage.value,
)
const showCharts = computed(() => hasReport.value && !emptyPeriod.value)

/**
 * Nilai tertinggi — HANYA untuk ukuran batang grafik (skala visual).
 * Tidak pernah ditampilkan sebagai angka kepada pengguna, dan tidak pernah
 * menggantikan satu pun nilai dari server.
 */
const maxHourlyCages = computed(() =>
  hourly.value.reduce((max, row) => (row.cages > max ? row.cages : max), 0),
)

const maxDailyTipped = computed(() =>
  daily.value.reduce((max, row) => (row.cages_tipped > max ? row.cages_tipped : max), 0),
)

function hourlyBarHeight(cages: number): string {
  if (maxHourlyCages.value <= 0) {
    return '0%'
  }

  return `${Math.round((cages / maxHourlyCages.value) * 100)}%`
}

function dailyBarHeight(cagesTipped: number): string {
  if (maxDailyTipped.value <= 0) {
    return '0%'
  }

  return `${Math.round((cagesTipped / maxDailyTipped.value) * 100)}%`
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
 * Bilangan berdesimal (rata-rata, jam operasi). null WAJIB menjadi "tidak
 * tersedia", TIDAK PERNAH 0 — 0 akan terbaca sebagai fakta yang salah
 * ("tippler tidak pernah jalan"), padahal yang benar adalah nilainya tidak
 * dapat dihitung.
 */
const NOT_AVAILABLE = 'tidak tersedia'

function formatDecimal(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return NOT_AVAILABLE
  }

  const digits = Number.isInteger(value) ? 0 : 1

  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  }).format(value)
}

/** Varian untuk sel tabel rekap, yang ruangnya sempit. */
function formatCell(value: number | null | undefined, suffix = ''): string {
  if (value === null || value === undefined) {
    return 'tidak tercatat'
  }

  return suffix ? `${formatDecimal(value)} ${suffix}` : formatDecimal(value)
}

/** Jam 0..23 menjadi "09.00". null = tidak ada penumpahan sama sekali. */
function formatHour(hour: number | null | undefined): string {
  if (hour === null || hour === undefined) {
    return NOT_AVAILABLE
  }

  return `${String(hour).padStart(2, '0')}.00`
}

/** Label sumbu grafik per jam — dua digit tanpa menit, agar muat. */
function formatHourAxis(hour: number): string {
  return String(hour).padStart(2, '0')
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

/** Label sumbu tren harian — tanggalnya saja, agar 14 kolom tetap muat. */
function formatDayAxis(value: string | null | undefined): string {
  if (!value) {
    return '-'
  }

  return value.slice(8, 10) || value
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

function periodOptionLabel(period: CagesTrackReportPeriodOption): string {
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
  <main class="laporan-ct-view" data-testid="laporan-cages-track-mobile">
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
        <span aria-current="page">Laporan Cages &amp; Tracks</span>
      </nav>
      <h1 class="screen-title">Laporan Cages &amp; Tracks</h1>
    </div>

    <!-- Akun terikat mill tetapi mill-nya kosong: berhenti total, tanpa
         satu pun permintaan HTTP dan tanpa pemilih mill sebagai pengganti. -->
    <p v-if="noMillForAccount" class="notice notice--warning" role="alert" data-testid="no-mill-for-account">
      Akun Anda belum terhubung ke mill mana pun, sehingga laporan tidak dapat ditampilkan.
      Silakan hubungi Admin untuk menghubungkan akun Anda ke sebuah mill.
    </p>

    <template v-else>
      <FilterPanel aria-label="Filter laporan">
        <!-- Pemilih Mill — hanya Admin, dan tidak dirender sama sekali
             bila server menolak endpoint options dengan 403. Operator,
             Supervisor, dan Mill Management memang tidak punya cara untuk
             menyebut mill lain dari layar ini. -->
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
             menceritakan hal berbeda kepada pengguna. Urutannya persis
             seperti yang dikirim server; layar ini tidak menyaring
             station_type sendiri. -->
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
        Mill ini belum memiliki periode pelaporan yang mencakup stasiun Cages &amp; Tracks.
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
          Belum ada data penumpahan lori pada periode ini.
        </p>

        <!-- Kartu angka utama — satu kolom bertumpuk. Setiap nilai berasal
             langsung dari respons; tidak ada yang dihitung di sini. -->
        <div class="metric-stack">
          <div class="metric-card">
            <span class="metric-label">Total Lori Ditumpahkan</span>
            <div class="metric-figure">
              <span class="metric-value" data-testid="kpi-total-cages-tipped">
                {{ formatCount(kpi?.total_cages_tipped) }}
              </span>
              <span class="metric-unit">lori</span>
            </div>
            <span class="metric-note">
              Dihitung dari rincian penumpahan per jam, bukan dari ringkasan pada header record.
            </span>
          </div>

          <div class="metric-card">
            <span class="metric-label">Total Lori Keluar</span>
            <div class="metric-figure">
              <span class="metric-value" data-testid="kpi-total-cages-out">
                {{ formatCount(kpi?.total_cages_out) }}
              </span>
              <span class="metric-unit">lori</span>
            </div>
          </div>

          <div class="metric-card">
            <span class="metric-label">Rata-rata per Hari</span>
            <!-- Penyebut 0 hari → "tidak tersedia", bukan "0 lori/hari":
                 null bukan 0 (temuan audit 2026-10-04 #9, sama dengan web). -->
            <template v-if="!kpi?.days_with_records">
              <div class="metric-figure">
                <span class="metric-value metric-value--na" data-testid="kpi-avg-cages-per-day">
                  {{ NOT_AVAILABLE }}
                </span>
              </div>
              <span class="metric-note" data-testid="kpi-avg-per-day-empty">
                Belum ada hari ber-record untuk dijadikan pembagi.
              </span>
            </template>
            <div v-else class="metric-figure">
              <span class="metric-value" data-testid="kpi-avg-cages-per-day">
                {{ formatDecimal(kpi?.avg_cages_per_day) }}
              </span>
              <span class="metric-unit">lori/hari</span>
            </div>
            <span v-if="kpi?.days_with_records" class="metric-note" data-testid="days-with-records">
              Dari {{ formatCount(kpi?.days_with_records) }} hari yang punya record — hari tanpa rincian per jam
              tetap ikut sebagai pembagi.
            </span>
          </div>

          <!-- Jam Puncak. peak_hour null = tidak ada penumpahan sama
               sekali; dirender sebagai keterangan, TIDAK sebagai 00.00. -->
          <div class="metric-card">
            <span class="metric-label">Jam Puncak</span>
            <div class="metric-figure">
              <span
                class="metric-value"
                :class="{ 'metric-value--na': kpi?.peak_hour === null || kpi?.peak_hour === undefined }"
                data-testid="kpi-peak-hour"
              >
                {{ formatHour(kpi?.peak_hour) }}
              </span>
            </div>
            <span v-if="kpi?.peak_hour !== null && kpi?.peak_hour !== undefined" class="metric-note">
              {{ formatCount(kpi?.peak_hour_cages) }} lori pada jam tersebut
            </span>
            <span v-else class="metric-note">
              Belum ada penumpahan pada periode ini, sehingga jam puncaknya belum terbentuk.
            </span>
          </div>

          <div class="metric-card">
            <span class="metric-label">Jam Operasi Tanpa Penumpahan</span>
            <div class="metric-figure">
              <span class="metric-value" data-testid="kpi-idle-hours">
                {{ formatCount(kpi?.idle_operating_hours) }}
              </span>
              <span class="metric-unit">jam</span>
            </div>
            <span class="metric-note">
              Hanya dihitung di dalam jam operasi tippler, bukan sepanjang 24 jam.
            </span>
          </div>

          <!-- Jeda Terpanjang. null = kurang dari dua jam penumpahan
               berbeda, jadi jeda tidak terdefinisi. Kartu ini TIDAK BOLEH
               menampilkan "0 jam" untuk keadaan itu. -->
          <div class="metric-card">
            <span class="metric-label">Jeda Terpanjang</span>
            <div class="metric-figure">
              <template v-if="kpi?.longest_gap_hours !== null && kpi?.longest_gap_hours !== undefined">
                <span class="metric-value" data-testid="kpi-longest-gap">
                  {{ formatCount(kpi?.longest_gap_hours) }}
                </span>
                <span class="metric-unit">jam</span>
                <span v-if="kpi?.longest_gap_date" class="metric-unit" data-testid="kpi-longest-gap-date">
                  &middot; {{ formatDate(kpi?.longest_gap_date) }}
                </span>
              </template>
              <span v-else class="metric-value metric-value--na" data-testid="kpi-longest-gap">
                tidak dapat dihitung
              </span>
            </div>
            <span class="metric-note">
              Diukur di dalam satu tanggal, tidak melintasi pergantian hari.
            </span>
          </div>

          <!-- Durasi operasi tippler rata-rata. null = tidak satu tanggal
               pun punya jendela operasi yang dapat dihitung. -->
          <div class="metric-card">
            <span class="metric-label">Durasi Operasi Tippler Rata-rata</span>
            <div class="metric-figure">
              <span
                class="metric-value"
                :class="{
                  'metric-value--na':
                    kpi?.avg_tippler_duration_hours === null || kpi?.avg_tippler_duration_hours === undefined,
                }"
                data-testid="kpi-avg-tippler-duration"
              >
                {{ formatDecimal(kpi?.avg_tippler_duration_hours) }}
              </span>
              <span
                v-if="kpi?.avg_tippler_duration_hours !== null && kpi?.avg_tippler_duration_hours !== undefined"
                class="metric-unit"
              >
                jam/hari
              </span>
            </div>
            <!-- Rata-rata tidak boleh terbaca tanpa tahu berapa hari yang
                 dikeluarkan dari perhitungannya. -->
            <span class="metric-note" data-testid="days-without-valid-window">
              {{ formatCount(kpi?.days_without_valid_window) }} hari jendela operasinya tidak dapat dihitung, tidak
              ikut dirata-ratakan.
            </span>
          </div>
        </div>

        <!-- Sebaran per jam — 24 batang, menggulir DI DALAM kartu ini.
             Jam di luar jendela operasi tippler diberi penanda visual
             tersendiri, karena jam kosong di luar jendela BUKAN jam
             menganggur. -->
        <section v-if="showCharts" class="detail-section" data-testid="hourly-distribution">
          <h2 class="section-title">Sebaran Penumpahan per Jam</h2>
          <p class="section-note">
            Seluruh lori periode ini dikelompokkan ke jam kejadiannya. Sumbu mendatar = jam, bukan tanggal.
            Geser mendatar untuk melihat 24 jam.
          </p>
          <div class="chart-scroll">
            <div class="chart-bars">
              <div
                v-for="row in hourly"
                :key="`hour-${row.hour}`"
                class="chart-col"
                :data-testid="`hourly-bar-${row.hour}`"
                :data-within-window="row.within_operating_window ? 'true' : 'false'"
              >
                <span class="chart-value">{{ formatCount(row.cages) }}</span>
                <div class="chart-track">
                  <div
                    class="chart-fill"
                    :class="{ 'chart-fill--outside': !row.within_operating_window }"
                    :style="{ height: hourlyBarHeight(row.cages) }"
                  ></div>
                </div>
                <span class="chart-axis">{{ formatHourAxis(row.hour) }}</span>
              </div>
            </div>
          </div>
          <div class="legend">
            <span class="legend-item"><i class="legend-swatch"></i> Di dalam jendela operasi tippler</span>
            <span class="legend-item"><i class="legend-swatch legend-swatch--outside"></i> Di luar jendela operasi</span>
          </div>
        </section>

        <!-- Tren harian — menggulir DI DALAM kartu ini. -->
        <section v-if="showCharts && daily.length > 0" class="detail-section" data-testid="daily-trend">
          <h2 class="section-title">Tren Harian</h2>
          <p class="section-note">Lori ditumpahkan per tanggal. Geser mendatar untuk melihat seluruh tanggal.</p>
          <div class="chart-scroll">
            <div class="chart-bars">
              <div v-for="row in daily" :key="`trend-${row.date}`" class="chart-col">
                <span class="chart-value">{{ formatCount(row.cages_tipped) }}</span>
                <div class="chart-track">
                  <div class="chart-fill" :style="{ height: dailyBarHeight(row.cages_tipped) }"></div>
                </div>
                <span class="chart-axis">{{ formatDayAxis(row.date) }}</span>
              </div>
            </div>
          </div>
        </section>

        <!-- Antrean lori tersisa — hanya terendah dan rata-rata. TIDAK ADA
             angka total: nilai ini adalah potret per jam, dan menjumlahkan
             potret lintas jam menghasilkan angka yang tidak berarti. -->
        <section v-if="queue" class="detail-section" data-testid="queue">
          <h2 class="section-title">Antrean Lori Tersisa</h2>
          <div class="queue-figures">
            <div class="queue-figure">
              <span class="queue-label">Terendah</span>
              <span class="queue-value" data-testid="queue-min-remaining">
                {{ queue.min_remaining === null ? NOT_AVAILABLE : formatCount(queue.min_remaining) }}
              </span>
            </div>
            <div class="queue-figure">
              <span class="queue-label">Rata-rata</span>
              <span class="queue-value" data-testid="queue-avg-remaining">
                {{ formatDecimal(queue.avg_remaining) }}
              </span>
            </div>
          </div>
          <p class="section-note" data-testid="queue-snapshot-note">
            Angka ini adalah potret per jam — armada lori stasiun dikurangi lori yang ditumpahkan pada jam itu —
            bukan antrean yang menumpuk dari jam ke jam, sehingga tidak pernah dijumlahkan.
          </p>
        </section>

        <!-- Rekap harian — panjang pada periode sebulan, jadi dibungkus
             CollapsibleSection dan TERTUTUP secara bawaan. Membuka atau
             menutupnya murni penyingkapan: datanya sudah ada di memori,
             tidak ada permintaan jaringan baru. Tabelnya menggulir di
             dalam kartunya sendiri, halaman tetap satu kolom. -->
        <CollapsibleSection
          v-if="daily.length > 0"
          :title="`Rekap Harian (${formatCount(daily.length)} hari)`"
          data-testid="daily-recap"
        >
          <p class="section-note">Geser mendatar untuk melihat seluruh kolom.</p>
          <div class="detail-table-wrap">
            <table class="detail-table">
              <thead>
                <tr>
                  <th>Tanggal</th>
                  <th class="num">Lori Ditumpahkan</th>
                  <th class="num">Lori Keluar</th>
                  <th class="num">Jam Operasi</th>
                  <th class="num">Jam Tanpa Penumpahan</th>
                  <th class="num">Jeda Terpanjang</th>
                  <th class="num">Antrean Terendah</th>
                </tr>
              </thead>
              <tbody>
                <!-- Seluruh baris dari server, dalam urutannya, TERMASUK
                     baris bernilai nol: hari dengan record tetapi tanpa
                     rincian per jam harus tetap terbaca, karena hari itu
                     ikut menjadi pembagi rata-rata harian. -->
                <tr v-for="row in daily" :key="`recap-${row.date}`">
                  <td>{{ formatDate(row.date) }}</td>
                  <td class="num">{{ formatCount(row.cages_tipped) }}</td>
                  <td class="num">{{ formatCount(row.cages_out) }}</td>
                  <td class="num">{{ formatCell(row.operating_hours, 'jam') }}</td>
                  <td class="num">{{ formatCell(row.idle_operating_hours, 'jam') }}</td>
                  <td class="num">{{ formatCell(row.longest_gap_hours, 'jam') }}</td>
                  <td class="num">{{ formatCell(row.min_remaining) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-if="total" class="section-note" data-testid="daily-recap-total">
            Total periode {{ formatCount(total.cages_tipped) }} lori ditumpahkan &middot;
            {{ formatCount(total.cages_out) }} lori keluar &middot; {{ formatCount(total.days) }} hari.
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
.laporan-ct-view {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 0 16px 20px;
  background: #ffffff;
  font-family: 'Inter', sans-serif;
  box-sizing: border-box;
  /* Halaman TIDAK PERNAH menggulir mendatar; yang menggulir adalah isi
     kartu grafik/tabel di dalamnya. */
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
/* Nilai yang tidak dapat dihitung tampil sebagai keterangan, bukan angka
   besar — supaya tidak pernah terbaca sekilas sebagai bilangan. */
.metric-value--na { font-size: 15px; font-weight: 600; color: #6b7280; }
.metric-unit { font-size: 13px; color: #6b7280; }
.metric-note { font-size: 12px; color: #6b7280; }

.detail-section { display: flex; flex-direction: column; gap: 10px; padding: 14px; border: 1px solid #e5e7eb; border-radius: 8px; }
.section-title { margin: 0; font-size: 16px; font-weight: 700; color: #1f2937; }
.section-note { margin: 0; font-size: 12px; color: #6b7280; }

/* Grafik batang: guliran mendatar DI DALAM kartu, bukan pada halaman. */
.chart-scroll { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.chart-bars { display: flex; align-items: flex-end; gap: 6px; min-width: min-content; padding-bottom: 4px; }
.chart-col { display: flex; flex-direction: column; align-items: center; gap: 4px; flex: none; width: 28px; }
.chart-value { font-size: 10px; color: #6b7280; }
.chart-track { display: flex; align-items: flex-end; width: 100%; height: 96px; border-radius: 4px; background: #f3f4f6; overflow: hidden; }
.chart-fill { width: 100%; border-radius: 4px 4px 0 0; background: #249360; }
.chart-fill--outside { background: #cbd5e1; }
.chart-axis { font-size: 10px; color: #6b7280; }

.legend { display: flex; flex-direction: column; gap: 4px; }
.legend-item { display: flex; align-items: center; gap: 6px; font-size: 11px; color: #6b7280; }
.legend-swatch { display: inline-block; width: 10px; height: 10px; border-radius: 2px; background: #249360; }
.legend-swatch--outside { background: #cbd5e1; }

.queue-figures { display: flex; gap: 20px; flex-wrap: wrap; }
.queue-figure { display: flex; flex-direction: column; gap: 2px; }
.queue-label { font-size: 11px; color: #6b7280; }
.queue-value { font-size: 20px; font-weight: 700; color: #1f2937; }

/* Tabel rekap: guliran mendatar DI DALAM kartu, bukan pada halaman. */
.detail-table-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
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
