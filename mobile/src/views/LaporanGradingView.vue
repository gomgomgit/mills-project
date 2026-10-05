<script setup lang="ts">
/**
 * LaporanGradingView — screen-147--laporan-grading-mobile /
 * usecase-150--laporan-grading-mobile "Lihat Laporan Periode Grading
 * (Mobile)" (dipasang di /reports/grading, meta.public = false).
 * Actors: operator, supervisor, mill_management, admin.
 *
 * Kembaran ketujuh dari pola LaporanSterilizerView (screen-135) dan kelima
 * saudaranya — struktur, penanganan galat, dan kosakata data-testid-nya
 * sengaja dibuat sama, karena ketujuh layar memecahkan masalah yang sama dan
 * perbedaan gaya di antaranya hanya akan menjadi beban pembaca berikutnya.
 *
 * ── LIMA HAL YANG MENENTUKAN BENTUK BERKAS INI ──────────────────────────
 *
 * 1. NOL PERHITUNGAN ULANG DI KLIEN. Setiap angka yang tampil dipetakan apa
 *    adanya dari respons /api/grading-reports/summary — endpoint yang sama
 *    persis dengan laporan versi web (screen-146), sehingga kedua layar
 *    mustahil berbeda. Tidak ada penjumlahan kuantitas, tidak ada penurunan
 *    pangsa, tidak ada perata-rataan persentase.
 *
 * 2. SATUAN JANJANG DAN KILOGRAM TIDAK PERNAH DIJUMLAHKAN, dan di sini itu
 *    STRUKTURAL: payload membawa dua blok (`bunch`, `kg`) yang masing-masing
 *    menjadi penyebut pangsanya sendiri, dan layar merendernya lewat satu
 *    v-for atas DUA KELOMPOK — bukan satu tabel atas gabungan keduanya. Satu
 *    tabel berkolom "satuan" akan mengundang pembaca menjumlahkan kolom
 *    kuantitasnya, dan jumlah janjang dengan kilogram bukan bilangan apa pun.
 *    Pada layar ponsel kedua bagian BERTUMPUK, bukan berdampingan — tetapi
 *    tetap tidak pernah bertemu dalam satu angka.
 *
 * 3. DUA ANGKA PER PARAMETER, DAN KETERANGANNYA MENANGGUNG BEBAN. `Pangsa`
 *    berbobot menurut besar muatan; `Rata-rata %` memperlakukan tiap muatan
 *    sama. Keduanya berbeda jauh begitu ukuran muatan tidak seragam — satu
 *    muatan raksasa yang buruk mendominasi pangsa tanpa menggerakkan
 *    rata-rata. Menampilkan salah satunya menyembunyikan separuh kenyataan;
 *    menampilkan keduanya TANPA keterangan hanya terbaca sebagai dua angka
 *    yang saling membantah. Karena itu keterangan di bawah kedua bagian
 *    dirender UTUH, bukan dipotong demi ruang layar.
 *
 *    Dan PENYEBUT rata-rata dicetak di barisnya sendiri ("N dari M muatan"):
 *    itulah satu-satunya hal yang membedakannya dari angka periode, dan pada
 *    layar sempit ia yang paling mudah dikorbankan.
 *
 * 4. NULL BUKAN NOL, DAN ITU TERLIHAT DI LAYAR. netto_total, netto_avg,
 *    bunch_total, bunch_avg, quantity_total tiap blok, serta share_percent
 *    dan avg_percentage tiap baris boleh null. Null dirender sebagai
 *    keterangan ("tidak tersedia"), TIDAK PERNAH sebagai 0 — "total 0 kg"
 *    adalah klaim bahwa sesuatu ditimbang dan hasilnya nol.
 *
 * 5. GAGAL TERTUTUP PADA MILL + 401 BUKAN KEGAGALAN JARINGAN. Akun terikat
 *    mill yang business_unit_id-nya kosong TIDAK memicu satu pun permintaan
 *    HTTP — termasuk tidak memanggil endpoint options, yang justru akan
 *    membentuk daftar SELURUH mill di perangkat orang yang tidak berhak
 *    melihat satu pun di antaranya. Sesi yang berakhir (401) diarahkan ke
 *    Login, bukan ditampilkan sebagai "periksa koneksi Anda" dengan tombol
 *    Coba Lagi yang akan gagal selamanya.
 *
 * TIDAK ADA PENGHITUNG "TANPA TANGGAL" di sini, dan ketiadaannya disengaja:
 * tanggal muatan Grading adalah kolom wajib, jadi setiap muatan selalu dapat
 * ditempatkan pada sebuah periode — berbeda dari laporan Weighbridge, yang
 * punya penghitung itu justru karena penanda waktunya boleh kosong.
 *
 * SATU KOLOM, TANPA GULIRAN MENDATAR PADA HALAMAN. Yang lebar bukan
 * halamannya, melainkan isi kartunya: kedua tabel parameter dan tabel rekap
 * harian masing-masing menggulir DI DALAM kartunya sendiri
 * (.detail-table-wrap), sementara halaman tetap overflow-x: hidden. Rekap
 * harian dibungkus CollapsibleSection dan TERTUTUP secara bawaan.
 *
 * Kosakata visual mengikuti keenam laporan mobile lain
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
import gradingReportRepo, {
  type GradingReportBusinessUnitOption,
  type GradingReportParameterBlock,
  type GradingReportPeriodOption,
  type GradingReportSummary,
} from '@/services/gradingReportRepo'

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

const businessUnits = ref<GradingReportBusinessUnitOption[]>([])

/**
 * Akun terikat mill tetapi business_unit_id-nya kosong — layar berhenti total
 * di sini, tanpa satu pun permintaan HTTP (lihat catatan 5 pada docblock).
 */
const noMillForAccount = ref(false)

/**
 * Endpoint options menjawab 403 — pemilih Mill tidak dirender sama sekali.
 *
 * Keadaan NORMAL bagi Operator, Supervisor, dan Mill Management: endpoint itu
 * adalah satu-satunya rute pada prefix grading-reports yang tidak terbuka bagi
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
 * payload Grading (seperti Weighbridge) menaruhnya di sana. Menyalin pola
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

const periods = ref<GradingReportPeriodOption[]>([])
const selectedPeriodId = ref<string | null>(null)
const summary = ref<GradingReportSummary | null>(null)

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
    businessUnits.value = await gradingReportRepo.fetchBusinessUnits()
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
    periods.value = await gradingReportRepo.fetchPeriods(scope.value)
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
    const result = await gradingReportRepo.fetchSummary(periodId, scope.value)
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

  return `laporan-grading_${slug || 'periode'}.csv`
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
    // Isi CSV dibentuk SERVER (satu baris per parameter per muatan). Layar ini
    // tidak pernah menyusun berkasnya dari angka yang tampil. Nama berkas
    // diambil SAAT ekspor diminta: bila pengguna mengganti periode selama
    // berkas diunduh, isinya tetap milik periode yang diekspor, jadi namanya
    // pun harus milik periode itu (audit 2026-10-05).
    const filename = exportFilename()
    const blob = await gradingReportRepo.exportCsv(periodId, scope.value)
    gradingReportRepo.saveCsvFile(blob, filename)
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

const byEstateSupplier = computed(() => summary.value?.by_estate_supplier ?? [])
const daily = computed(() => summary.value?.daily ?? [])
const dailyTotal = computed(() => summary.value?.daily_total ?? null)
const completeness = computed(() => summary.value?.completeness ?? null)

/**
 * Konfigurasi kedua bagian parameter. SATU v-for ATAS DUA KELOMPOK, bukan
 * satu tabel atas gabungan keduanya: strukturnya sendiri yang mencegah
 * terbentuknya kolom kuantitas gabungan.
 */
const parameterGroups = computed<
  Array<{ key: 'bunch' | 'kg'; title: string; unit: string; hint: string; block: GradingReportParameterBlock | null }>
>(() => [
  {
    key: 'bunch',
    title: 'Parameter Mutu — Janjang',
    unit: 'janjang',
    hint: 'tiga belas parameter kematangan, dihitung dalam janjang',
    block: summary.value?.bunch ?? null,
  },
  {
    key: 'kg',
    title: 'Parameter Mutu — Kilogram',
    unit: 'kg',
    hint: 'tiga parameter brondolan, ditimbang dalam kilogram',
    block: summary.value?.kg ?? null,
  },
])

const hasReport = computed(
  () => Boolean(summary.value) && !networkError.value && !errorMessage.value,
)

/**
 * Periode tanpa satu muatan pun.
 *
 * DITURUNKAN DI SINI, dan itu perbedaan nyata dari lima laporan pertama:
 * payload Grading tidak punya kunci `has_data`. Yang dipakai adalah
 * load_count, bukan panjang `daily` maupun total parameter — sebuah periode
 * bisa punya muatan yang seluruhnya belum dinilai, dan itu tetap "ada data".
 */
const emptyPeriod = computed(() => Boolean(summary.value) && (summary.value?.load_count ?? 0) === 0)

/**
 * Persen hari ber-muatan. Penyebutnya days_counted (berhenti di HARI INI untuk
 * periode berjalan), dan ia bisa 0 untuk periode yang belum mulai — dalam
 * keadaan itu jawabannya '—', BUKAN '0,0%': nol persen adalah klaim tentang
 * hari yang bahkan belum terjadi.
 */
const daysWithLoadPercent = computed(() => {
  const data = completeness.value

  if (!data || !data.days_counted) {
    return null
  }

  return (data.days_with_load / data.days_counted) * 100
})

/* ------------------------------------------------------------------ */
/* Format Indonesia                                                    */
/* ------------------------------------------------------------------ */

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
 * Asal yang belum diisi. Server menyatukan NULL dan string kosong menjadi satu
 * kelompok '' (kolomnya sendiri NOT NULL), dan label ini adalah keputusan
 * view — bukan sesuatu yang repo seragamkan.
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

function periodOptionLabel(period: GradingReportPeriodOption): string {
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
  <main class="laporan-gr-view" data-testid="laporan-grading-mobile">
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
        <span aria-current="page">Laporan Grading</span>
      </nav>
      <h1 class="screen-title">Laporan Grading</h1>
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
        Mill ini belum memiliki periode pelaporan yang mencakup stasiun Grading.
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
          Belum ada muatan Grading pada periode ini untuk Production Line yang dipilih.
        </p>

        <!-- Angka utama muatan. Satu kolom bertumpuk; setiap nilai berasal
             langsung dari respons. -->
        <div class="metric-stack" data-testid="headline-metrics">
          <div class="metric-card">
            <span class="metric-label">Jumlah Muatan Disortir</span>
            <div class="metric-figure">
              <span class="metric-value" data-testid="kpi-load-count">
                {{ formatCount(summary?.load_count) }}
              </span>
              <span class="metric-unit">muatan</span>
            </div>
            <span class="metric-note">
              Termasuk muatan draft dan muatan yang belum punya satu pun baris parameter.
            </span>
          </div>

          <div class="metric-card">
            <span class="metric-label">Total Netto</span>
            <div class="metric-figure">
              <span
                class="metric-value"
                :class="{ 'metric-value--na': summary?.netto_total === null || summary?.netto_total === undefined }"
                data-testid="kpi-netto-total"
              >
                {{ formatDecimal(summary?.netto_total) }}
              </span>
              <span v-if="summary?.netto_total !== null && summary?.netto_total !== undefined" class="metric-unit">kg</span>
            </div>
            <span class="metric-note" data-testid="kpi-netto-avg">
              Rata-rata {{ formatDecimal(summary?.netto_avg) }} kg per muatan — penyebutnya jumlah muatan,
              karena netto selalu terisi pada tiap muatan.
            </span>
          </div>

          <div class="metric-card">
            <span class="metric-label">Total Jumlah Janjang</span>
            <div class="metric-figure">
              <span
                class="metric-value"
                :class="{ 'metric-value--na': summary?.bunch_total === null || summary?.bunch_total === undefined }"
                data-testid="kpi-bunch-total"
              >
                {{ formatDecimal(summary?.bunch_total, 0) }}
              </span>
              <span v-if="summary?.bunch_total !== null && summary?.bunch_total !== undefined" class="metric-unit">janjang</span>
            </div>
            <span class="metric-note" data-testid="kpi-bunch-avg">
              Rata-rata {{ formatDecimal(summary?.bunch_avg) }} janjang per muatan. Angka pada header muatan —
              bukan jumlah baris parameter ber-satuan janjang di bawah.
            </span>
          </div>
        </div>

        <!-- ============================================================
             DUA BAGIAN PARAMETER, SATU PER SATUAN. Satu v-for atas DUA
             kelompok, bukan satu tabel atas gabungan keduanya: strukturnya
             sendiri yang mencegah terbentuknya kolom kuantitas gabungan.
             Bagian yang kosong TETAP dirender — bagian yang hilang terbaca
             sebagai "tidak ada bagian ini", padahal yang benar adalah
             "tidak ada isinya".
             ============================================================ -->
        <section
          v-for="group in parameterGroups"
          :key="group.key"
          class="detail-section"
          :data-testid="`parameter-${group.key}`"
        >
          <h2 class="section-title">{{ group.title }}</h2>
          <p class="section-note">
            {{ group.hint }} &middot; pangsa dihitung terhadap total bagian ini sendiri, tidak pernah terhadap
            satuan yang lain.
          </p>
          <p class="section-note" :data-testid="`parameter-${group.key}-total`">
            Total bagian ini: <strong>{{ formatDecimal(group.block?.quantity_total ?? null) }}</strong>
            {{ group.unit }} dari {{ formatCount(group.block?.parameter_count ?? 0) }} parameter.
          </p>

          <div v-if="(group.block?.rows ?? []).length > 0" class="detail-table-wrap">
            <table class="detail-table" :data-testid="`parameter-${group.key}-table`">
              <thead>
                <tr>
                  <th>Parameter</th>
                  <th class="num">Kuantitas</th>
                  <th class="num">Pangsa</th>
                  <th class="num">Rata-rata %</th>
                  <th class="num">Penyebut</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in group.block?.rows ?? []"
                  :key="`${group.key}-${row.grading_parameter_id}`"
                  :data-testid="`parameter-${group.key}-row`"
                >
                  <td>{{ row.name }}</td>
                  <td class="num">{{ formatCell(row.quantity_total) }}</td>
                  <td class="num">{{ formatPercent(row.share_percent) }}</td>
                  <td class="num">{{ formatPercent(row.avg_percentage) }}</td>
                  <!-- PENYEBUT RATA-RATA, dicetak di sebelah rata-ratanya:
                       muatan yang tidak mencantumkan parameter ini tidak
                       menilainya nol, ia tidak menilainya sama sekali. -->
                  <td class="num">{{ formatCount(row.load_count) }} dari {{ formatCount(summary?.load_count) }} muatan</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else class="section-note" :data-testid="`parameter-${group.key}-empty`">
            Tidak ada satu pun baris parameter ber-satuan {{ group.unit }} pada periode dan line ini. Bagian ini
            tetap ditampilkan — bagian yang hilang akan terbaca sebagai &ldquo;tidak ada bagian ini&rdquo;,
            padahal yang benar adalah &ldquo;tidak ada isinya&rdquo;.
          </p>
        </section>

        <!-- Keterangan yang membuat kedua angka per parameter dapat dibaca.
             Dirender UTUH, bukan dipotong demi ruang: dua angka yang membantah
             tanpa sebab yang dinyatakan lebih buruk daripada satu angka. -->
        <section v-if="hasReport" class="detail-section" data-testid="parameter-share-note">
          <h2 class="section-title">Membaca Pangsa dan Rata-rata</h2>
          <p class="section-note">
            <strong>Pangsa</strong> membandingkan kuantitas parameter terhadap total periode, jadi ia
            <strong>berbobot menurut besar muatan</strong>: satu muatan besar menentukan banyak hal.
            <strong>Rata-rata %</strong> adalah rata-rata persentase per muatan, jadi
            <strong>tiap muatan berbobot sama</strong>, sebesar apa pun ia. Keduanya memang dapat berbeda jauh
            — itu bukan ketidakcocokan.
          </p>
          <p class="section-note">
            Penyebut <strong>Rata-rata %</strong> adalah jumlah muatan yang <strong>benar-benar mencatat</strong>
            parameter itu, dicetak pada kolom terakhir — bukan jumlah seluruh muatan periode. Muatan yang tidak
            mencantumkan sebuah parameter tidak menilainya nol persen; ia tidak menilainya sama sekali.
          </p>
          <p class="section-note">
            Satuan janjang dan kilogram tidak pernah dijumlahkan: keduanya dua bagian yang berdiri sendiri,
            masing-masing dengan totalnya sendiri. Tidak ada nilai yang ditandai di luar batas di layar ini —
            Grading tidak punya master target mutu.
          </p>
        </section>

        <!-- Rekap per asal. Kelompok "Belum diisi" tidak pernah dibuang,
             sehingga jumlah muatannya tetap menjumlah ke angka utama. -->
        <section v-if="byEstateSupplier.length > 0" class="detail-section" data-testid="by-estate-supplier">
          <h2 class="section-title">Rekap per Estate / Supplier</h2>
          <p class="section-note">Diurutkan dari netto terbesar. Seluruh asal ditampilkan, tanpa pemangkasan.</p>
          <div class="detail-table-wrap">
            <table class="detail-table" data-testid="by-estate-supplier-table">
              <thead>
                <tr>
                  <th>Asal</th>
                  <th class="num">Muatan</th>
                  <th class="num">Netto (kg)</th>
                  <th class="num">Janjang</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(row, index) in byEstateSupplier"
                  :key="`origin-${index}-${row.estate_supplier || 'belum-diisi'}`"
                  data-testid="by-estate-supplier-row"
                >
                  <td>{{ formatGroupLabel(row.estate_supplier) }}</td>
                  <td class="num">{{ formatCount(row.load_count) }}</td>
                  <td class="num">{{ formatCell(row.netto_total) }}</td>
                  <td class="num">{{ formatCell(row.bunch_total, 0) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Kelengkapan pencatatan adalah BAGIAN laporan, bukan catatan kaki. -->
        <section class="detail-section" data-testid="completeness">
          <h2 class="section-title">Kelengkapan Pencatatan</h2>

          <div class="queue-figures">
            <div class="queue-figure">
              <span class="queue-label">Hari ber-muatan</span>
              <span class="queue-value" data-testid="completeness-days">
                {{ formatCount(completeness?.days_with_load) }} / {{ formatCount(completeness?.days_counted) }}
              </span>
              <span class="queue-note" data-testid="completeness-percent">
                {{ formatCompletenessPercent(daysWithLoadPercent) }}
              </span>
            </div>
            <div class="queue-figure">
              <span class="queue-label">Tanpa baris parameter</span>
              <span class="queue-value" data-testid="loads-without-detail">
                {{ formatCount(summary?.loads_without_detail) }}
              </span>
            </div>
            <div class="queue-figure">
              <span class="queue-label">Muatan draft</span>
              <span class="queue-value" data-testid="draft-load-count">
                {{ formatCount(summary?.draft_load_count) }}
              </span>
            </div>
            <div class="queue-figure">
              <span class="queue-label">Tanpa divisi</span>
              <span class="queue-value" data-testid="loads-without-division">
                {{ formatCount(summary?.loads_without_division) }}
              </span>
            </div>
            <div class="queue-figure">
              <span class="queue-label">Belum diperiksa</span>
              <span class="queue-value" data-testid="loads-not-checked">
                {{ formatCount(summary?.loads_not_checked) }}
              </span>
            </div>
            <div class="queue-figure">
              <span class="queue-label">Belum disahkan</span>
              <span class="queue-value" data-testid="loads-not-acknowledged">
                {{ formatCount(summary?.loads_not_acknowledged) }}
              </span>
            </div>
          </div>

          <!-- URUTANNYA PENTING: days_counted DIPERIKSA LEBIH DULU.
               ReportPeriodDays::isRunning() di server juga true untuk
               periode yang BELUM MULAI (tanggal akhirnya masih di masa
               depan), jadi memeriksa period_running lebih dulu membuat
               keterangan "belum mulai" tidak pernah terender. -->
          <p v-if="!completeness?.days_counted" class="section-note" data-testid="completeness-not-started-note">
            Periode belum mulai, sehingga belum ada hari yang dapat dijadikan pembagi — persennya
            &ldquo;&mdash;&rdquo;, bukan 0%.
          </p>
          <p v-else-if="completeness?.period_running" class="section-note" data-testid="completeness-running-note">
            Dihitung sampai hari ini, periode masih berjalan
            ({{ formatCount(completeness?.days_counted) }} dari
            {{ formatCount(completeness?.days_in_period) }} hari periode sudah lewat).
          </p>

          <p class="section-note" data-testid="loads-without-detail-note">
            Muatan tanpa baris parameter <strong>ikut</strong> jumlah muatan, netto, dan jumlah janjang, tetapi
            <strong>tidak menyumbang apa pun</strong> pada kedua bagian parameter — angka itu satu-satunya cara
            menjelaskan selisih antara keduanya.
          </p>
          <p class="section-note" data-testid="draft-note">
            Muatan draft <strong>IKUT terhitung</strong> pada seluruh angka di atas; jumlahnya dinyatakan supaya
            terlihat seberapa besar laporan ini berdiri di atas data yang belum selesai. Status verifikasi juga
            kelengkapan, bukan penyaring — muatan yang belum diperiksa tetap terhitung penuh.
          </p>
          <p class="section-note" data-testid="completeness-note">
            Tidak ada penghitung &ldquo;muatan tanpa tanggal&rdquo; di layar ini, dan ketiadaannya disengaja:
            tanggal muatan Grading adalah kolom wajib, jadi setiap muatan selalu dapat ditempatkan pada sebuah
            periode — berbeda dari laporan Weighbridge.
          </p>
        </section>

        <!-- Rekap harian — panjang pada periode sebulan, jadi dibungkus
             CollapsibleSection dan TERTUTUP secara bawaan. Membuka atau
             menutupnya murni penyingkapan: datanya sudah ada di memori. -->
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
                  <th class="num">Muatan</th>
                  <th class="num">Netto (kg)</th>
                  <th class="num">Janjang</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in daily" :key="`recap-${row.date}`">
                  <td>{{ formatDate(row.date) }}</td>
                  <td class="num">{{ formatCount(row.load_count) }}</td>
                  <td class="num">{{ formatCell(row.netto_total) }}</td>
                  <td class="num">{{ formatCell(row.bunch_total, 0) }}</td>
                </tr>
              </tbody>
              <tfoot v-if="dailyTotal">
                <tr data-testid="daily-recap-total">
                  <th>Total periode</th>
                  <td class="num">{{ formatCount(dailyTotal.load_count) }}</td>
                  <td class="num">{{ formatCell(dailyTotal.netto_total) }}</td>
                  <td class="num">{{ formatCell(dailyTotal.bunch_total, 0) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
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
.laporan-gr-view {
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
</style>
