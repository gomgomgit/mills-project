<script setup lang="ts">
/**
 * LaporanWeighbridgeView — screen-144--laporan-weighbridge-mobile /
 * usecase-147--laporan-weighbridge-mobile "Lihat Laporan Periode Weighbridge
 * (Mobile)" (dipasang di /reports/weighbridge, meta.public = false).
 * Actors: operator, supervisor, mill_management, admin.
 *
 * Kembaran keenam dari pola LaporanSterilizerView (screen-135) dan keempat
 * saudaranya — struktur, penanganan galat, dan kosakata data-testid-nya
 * sengaja dibuat sama, karena keenam layar memecahkan masalah yang sama dan
 * perbedaan gaya di antaranya hanya akan menjadi beban pembaca berikutnya.
 *
 * ── LIMA HAL YANG MENENTUKAN BENTUK BERKAS INI ──────────────────────────
 *
 * 1. NOL PERHITUNGAN ULANG DI KLIEN. Setiap angka yang tampil dipetakan apa
 *    adanya dari respons /api/weighbridge-reports/summary — endpoint yang
 *    sama persis dengan laporan versi web (screen-143), sehingga kedua layar
 *    mustahil berbeda. Tidak ada penjumlahan hourly, tidak ada perata-rataan,
 *    tidak ada penurunan missing_net_weight_trip_count dari
 *    trip_count − net_weight_trip_count. Satu-satunya angka turunan adalah
 *    UKURAN BATANG grafik (persentase terhadap nilai tertinggi) — itu murni
 *    skala visual, bukan angka yang dibaca pengguna.
 *
 * 2. ARUS MASUK DAN ARUS KELUAR TIDAK PERNAH DIJUMLAHKAN. Keduanya bertumpuk
 *    sebagai dua bagian terpisah dengan judul yang menyebut artinya, dan
 *    TIDAK ADA satu pun angka di layar ini yang menggabungkan keduanya —
 *    bukan total trip, bukan total berat, dan bukan baris total pada rekap
 *    harian. "Berapa ton yang ditimbang hari ini" bukan pertanyaan yang
 *    berguna bagi siapa pun, dan jawabannya paling berbahaya justru karena
 *    terlihat masuk akal.
 *
 *    SATU-SATUNYA yang dibagi kedua arus adalah SKALA grafik sebaran per jam:
 *    kedua grafik diskalakan terhadap nilai tertinggi KEDUA arus, dan itu
 *    dinyatakan pada catatan grafiknya. Dua grafik bertumpuk dengan skala
 *    berbeda akan dibaca sebagai perbandingan tinggi batang, dan itu
 *    perbandingan yang salah. Yang dibagi adalah skala, bukan angka.
 *
 * 3. NULL BUKAN NOL, DAN ITU TERLIHAT DI LAYAR. net_weight_total,
 *    net_weight_avg, busiest_hour, dan seluruh kolom berat pada rekap harian
 *    boleh null. Null dirender sebagai keterangan ("tidak tersedia"), TIDAK
 *    PERNAH sebagai 0: "total 0 kg" berarti sesuatu ditimbang dan hasilnya
 *    nol, sedangkan null berarti tidak ada yang pernah ditimbang sampai
 *    selesai. Dua fakta yang berbeda, dan yang satu menyesatkan.
 *
 * 4. TIDAK ADA LAMA KENDARAAN DI PABRIK, dan ketiadaannya disengaja. Migrasi
 *    2026_08_19_000010 menggabungkan dua kolom waktu lama menjadi satu
 *    record_datetime, jadi satu trip membawa TEPAT SATU penanda waktu dan
 *    durasi tidak dapat dihitung sama sekali. Penggantinya adalah sebaran
 *    trip per jam. Jangan menambahkan kartu durasi apa pun di sini: angka
 *    semacam itu akan terbaca sah padahal tidak punya dasar.
 *
 * 5. GAGAL TERTUTUP PADA MILL + 401 BUKAN KEGAGALAN JARINGAN. Akun terikat
 *    mill yang business_unit_id-nya kosong TIDAK memicu satu pun permintaan
 *    HTTP — termasuk tidak memanggil endpoint options, yang justru akan
 *    membentuk daftar SELURUH mill di perangkat orang yang tidak berhak
 *    melihat satu pun di antaranya. Sesi yang berakhir (401) diarahkan ke
 *    Login, bukan ditampilkan sebagai "periksa koneksi Anda" dengan tombol
 *    Coba Lagi yang akan gagal selamanya; sebaliknya kegagalan jaringan yang
 *    sungguhan TIDAK PERNAH membuang periode yang sudah dipilih.
 *
 * SATU KOLOM, TANPA GULIRAN MENDATAR PADA HALAMAN. Ini layar ponsel: yang
 * lebar bukan halamannya, melainkan isi kartunya. Kedua grafik 24 jam dan
 * tabel rekap harian masing-masing menggulir DI DALAM kartunya sendiri
 * (.chart-scroll / .detail-table-wrap), sementara halaman tetap
 * overflow-x: hidden. Rekap harian dibungkus CollapsibleSection dan TERTUTUP
 * secara bawaan — membuka/menutupnya murni penyingkapan, tidak memicu
 * permintaan jaringan apa pun karena datanya sudah ada.
 *
 * Kosakata visual mengikuti kelima laporan mobile lain
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
import weighbridgeReportRepo, {
  type WeighbridgeReportBusinessUnitOption,
  type WeighbridgeReportPeriodOption,
  type WeighbridgeReportSummary,
} from '@/services/weighbridgeReportRepo'

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

const businessUnits = ref<WeighbridgeReportBusinessUnitOption[]>([])

/**
 * Akun terikat mill tetapi business_unit_id-nya kosong — layar berhenti total
 * di sini, tanpa satu pun permintaan HTTP (lihat catatan 5 pada docblock).
 */
const noMillForAccount = ref(false)

/**
 * Endpoint options menjawab 403 — pemilih Mill tidak dirender sama sekali.
 *
 * Ini keadaan yang NORMAL bagi Operator, Supervisor, dan Mill Management:
 * perluasan akses screen-144 sengaja TIDAK menyentuh
 * /business-units/options, karena peran yang terikat satu mill tidak punya
 * pemilih dan menyerahkan daftar seluruh mill kepadanya adalah justru
 * kebocoran yang dihindari perluasan itu. Layar ini memang tidak memanggil
 * endpoint itu untuk mereka; penanda ini ada untuk Admin yang kewenangannya
 * berubah di tengah sesi.
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
 * ditindaklanjuti siapa pun: tidak ada satu orang pun yang bertanggung jawab
 * atasnya. Karena itu tidak ada angka di layar ini sebelum ada satu line yang
 * berlaku — bukan sekadar pilihan bawaan yang "kebetulan" line pertama.
 *
 * DAN DI SINI SERVER IKUT MENJAGANYA, berbeda dari lima laporan sebelumnya:
 * /summary dan /export menjawab 422 bila production_line_id absen, bukan
 * memulangkan angka seluruh mill. Pada kelima saudaranya parameter itu tetap
 * opsional supaya layar mobile mereka yang sudah terkirim tidak pecah;
 * Weighbridge tidak punya pembaca lama, jadi kontraknya ketat sejak hari
 * pertama dan layar ini dibangun untuk kontrak itu.
 *
 * (Data Browser versi web sengaja BERBEDA: di sana barisnya tetap terpisah
 * per record, jadi "Semua Line" di sana tetap dapat dibaca. Perbedaan itu
 * disengaja, bukan ketidakkonsistenan.)
 *
 * ── MEMAKAI ULANG INGATAN LINE MILIK LAYAR DAFTAR STASIUN ───────────────
 *
 * Kunci localStorage-nya SAMA PERSIS dengan yang ditulis StationListView.vue
 * (`msl_production_line_{userId}`) dan yang sudah dibaca
 * ReportingPilihStasiunView.vue. Alasannya: line adalah konteks kerja satu
 * orang pada satu shift, bukan pengaturan per layar. Pengguna yang sudah
 * memilih Line 2 di Daftar Stasiun tidak boleh diminta memilih lagi begitu ia
 * membuka laporan.
 *
 * Tetapi ingatan itu TIDAK CUKUP dijadikan satu-satunya sumber: laporan
 * dicapai lewat cabang navigasi yang lain (Home → Dashboard & Reporting →
 * Reporting → tile stasiun), sedangkan ingatannya ditulis di cabang
 * Home → Daftar Stasiun. Karena itu layar ini punya PEMILIHNYA SENDIRI, dan
 * ingatan hanyalah nilai awalnya.
 *
 * Urutan penentuan line yang berlaku (resolveProductionLine):
 *   1. `?production_line_id=` pada rute — dibawa screen-141 saat menekan tile
 *      stasiun, sejajar dengan `report_path` milik screen-140 web.
 *   2. Ingatan localStorage — pilihan terakhir pengguna ini di perangkat ini.
 *   3. Tepat satu line di mill ini — tidak ada yang perlu dipilih.
 *   4. Selain itu: pemilih ditampilkan, dan TIDAK ADA ANGKA.
 *
 * Nilai dari (1) dan (2) TIDAK PERNAH dipercaya begitu saja — keduanya hanya
 * dipakai bila masih ada di daftar line yang baru diambil dari server, persis
 * kehati-hatian yang sama yang dipakai StationListView.vue. Line yang
 * dihapus, atau pengguna yang dipindah mill, kembali memunculkan pemilih
 * seperti seharusnya.
 *
 * ADMIN: daftar line diambil dari GET /api/production-lines/options-for-report
 * ?business_unit_id=<mill terpilih>, BUKAN dari /api/production-lines/current.
 * Yang terakhir itu swa-cakup — ia memulangkan line milik mill AKUN
 * PEMANGGIL, sedangkan Admin tidak terikat mill sama sekali, sehingga
 * memanggilnya atas nama Admin akan memulangkan line dari mill yang BUKAN
 * mill terpilih: bentuk kesalahan paling berbahaya di layar laporan, yaitu
 * angka yang terlihat sah untuk line yang salah.
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
 * BERBEDA DARI LIMA LAPORAN SEBELUMNYA, dan ini mudah salah disalin: payload
 * Weighbridge membawa nama mill di blok `business_unit`, BUKAN di
 * `period.business_unit_name`. Menyalin pola lama menghasilkan keterangan
 * mill yang kosong tanpa satu pun galat.
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

const periods = ref<WeighbridgeReportPeriodOption[]>([])
const selectedPeriodId = ref<string | null>(null)
const summary = ref<WeighbridgeReportSummary | null>(null)

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
 * BENTUK GALAT DATAR. apiClient.normalizeError (src/services/apiClient.ts)
 * menolak dengan objek DATAR `{ message, errors?, status? }` dan sudah
 * MEMBUANG `response` di sana — jadi `.response` tidak pernah ada pada galat
 * yang sampai ke sini. Medan itu karena itu tidak ditulis pada antarmuka ini:
 * yang tidak dapat ditulis tidak dapat salah.
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
 * Penanganan galat terpusat. Urutan cabangnya menentukan benar/salahnya layar
 * ini:
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
    // selectedPeriodId SENGAJA tidak direset di sini: pengguna di area tanpa
    // sinyal tidak boleh dipaksa memilih periodenya dari awal, dan Coba Lagi
    // harus memuat ulang periode yang SAMA.
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
    businessUnits.value = await weighbridgeReportRepo.fetchBusinessUnits()
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
    periods.value = await weighbridgeReportRepo.fetchPeriods(scope.value)
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
    const result = await weighbridgeReportRepo.fetchSummary(periodId, scope.value)
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

  // Daftar line lebih dulu: pemilih periode boleh terisi tanpa line, tetapi
  // angkanya tidak — dan /periods memang TIDAK tersaring line.
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

  return `laporan-weighbridge_${slug || 'periode'}.csv`
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
    // Isi CSV dibentuk SERVER (satu baris per trip). Layar ini tidak pernah
    // menyusun berkasnya dari angka yang tampil. Nama berkas diambil SAAT
    // ekspor diminta: bila pengguna mengganti periode selama berkas diunduh,
    // isinya tetap milik periode yang diekspor, jadi namanya pun harus milik
    // periode itu (audit 2026-10-05).
    const filename = exportFilename()
    const blob = await weighbridgeReportRepo.exportCsv(periodId, scope.value)
    weighbridgeReportRepo.saveCsvFile(blob, filename)
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

const receive = computed(() => summary.value?.receive ?? null)
const dispatch = computed(() => summary.value?.dispatch ?? null)
const byOrigin = computed(() => summary.value?.receive?.by_origin ?? [])
const byDestination = computed(() => summary.value?.dispatch?.by_destination ?? [])
const daily = computed(() => summary.value?.daily ?? [])
const dailyTotal = computed(() => summary.value?.daily_total ?? null)
const completeness = computed(() => summary.value?.completeness ?? null)

const hasReport = computed(
  () => Boolean(summary.value) && !networkError.value && !errorMessage.value,
)

/**
 * Periode tanpa satu trip pun — grafik TIDAK dirender sebagai kotak kosong.
 *
 * DITURUNKAN DI SINI, dan itu perbedaan nyata dari lima laporan sebelumnya:
 * payload Weighbridge tidak punya kunci `has_data`. Yang dipakai adalah kedua
 * trip_count, bukan panjang `daily` — sebuah periode bisa punya trip yang
 * seluruhnya tak bertanggal, dan itu bukan "ada data pada periode ini".
 */
const emptyPeriod = computed(
  () =>
    Boolean(summary.value) &&
    (receive.value?.trip_count ?? 0) === 0 &&
    (dispatch.value?.trip_count ?? 0) === 0,
)

const showCharts = computed(() => hasReport.value && !emptyPeriod.value)

/**
 * Nilai tertinggi di antara KEDUA arus — HANYA untuk ukuran batang grafik.
 *
 * Skala sengaja DIBAGI kedua grafik: dua grafik bertumpuk dengan skala
 * masing-masing akan dibaca sebagai perbandingan tinggi batang, dan itu
 * perbandingan yang salah. Ini bukan pelanggaran aturan "arus tidak pernah
 * dijumlahkan" — yang dibagi adalah skala visual, bukan angka; tidak satu
 * nilai pun yang dibaca pengguna lahir dari sini.
 */
const maxHourlyTrips = computed(() => {
  const rows = [...(receive.value?.hourly ?? []), ...(dispatch.value?.hourly ?? [])]

  return rows.reduce((max, row) => (row.trip_count > max ? row.trip_count : max), 0)
})

function hourlyBarHeight(tripCount: number): string {
  if (maxHourlyTrips.value <= 0) {
    return '0%'
  }

  return `${Math.round((tripCount / maxHourlyTrips.value) * 100)}%`
}

/** Nilai tertinggi rekap per asal/tujuan — skala visual untuk bilah rekap. */
const maxOriginTrips = computed(() =>
  byOrigin.value.reduce((max, row) => (row.trip_count > max ? row.trip_count : max), 0),
)

const maxDestinationTrips = computed(() =>
  byDestination.value.reduce((max, row) => (row.trip_count > max ? row.trip_count : max), 0),
)

function breakdownBarWidth(tripCount: number, max: number): string {
  if (max <= 0) {
    return '0%'
  }

  return `${Math.round((tripCount / max) * 100)}%`
}

/**
 * Persen hari bertrip. Penyebutnya days_counted (berhenti di HARI INI untuk
 * periode berjalan), dan ia bisa 0 untuk periode yang belum mulai — dalam
 * keadaan itu jawabannya '—', BUKAN '0,0%': nol persen adalah klaim tentang
 * hari yang bahkan belum terjadi.
 */
const daysWithTripPercent = computed(() => {
  const data = completeness.value

  if (!data || !data.days_counted) {
    return null
  }

  return (data.days_with_trip / data.days_counted) * 100
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
 * Berat dalam KILOGRAM, apa adanya dari kolomnya, tanpa konversi — konvensi
 * yang sama dengan form input dan laporan versi web. Dua desimal.
 *
 * null WAJIB menjadi "tidak tersedia", TIDAK PERNAH 0: '0 kg' akan terbaca
 * sebagai fakta yang salah (sesuatu ditimbang dan hasilnya nol), padahal yang
 * benar adalah tidak ada trip yang beratnya pernah terisi.
 */
function formatWeight(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return NOT_AVAILABLE
  }

  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value)
}

/** Varian untuk sel tabel rekap, yang ruangnya sempit. */
function formatWeightCell(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return 'tidak tercatat'
  }

  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value)
}

function formatPercent(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return '—'
  }

  return `${new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  }).format(value)}%`
}

/** Jam 0..23 menjadi "09.00". null = tidak ada trip sama sekali pada arus itu. */
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

/** Label sumbu rekap harian — tanggalnya saja, agar banyak kolom tetap muat. */
function formatDayAxis(value: string | null | undefined): string {
  if (!value) {
    return '-'
  }

  return value.slice(8, 10) || value
}

/**
 * Asal atau tujuan yang belum diisi. DUA BENTUK dalam satu payload, dan
 * keduanya harus ditangani di sini: server mengirim STRING KOSONG untuk
 * estate_supplier yang belum diisi (keepNull=false) dan NULL untuk destination
 * yang belum diisi (keepNull=true). Menangani hanya salah satunya
 * menghasilkan baris tanpa label pada salah satu rekap.
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

function periodOptionLabel(period: WeighbridgeReportPeriodOption): string {
  return period.station_type_label ? `${period.station_type_label} — ${period.name}` : period.name
}

/** Status periode adalah KETERANGAN, bukan gerbang: ekspor tetap aktif. */
const periodStatus = computed(
  () => summary.value?.period?.status ?? selectedPeriod.value?.status ?? null,
)

/**
 * Keterangan penyebut rata-rata. ', bukan M' hanya ditambahkan ketika jumlah
 * trip berbobot BERBEDA dari jumlah seluruh trip — menampilkannya selalu akan
 * menjadi kebisingan yang dilatih diabaikan orang. Perilaku yang sama dengan
 * laporan versi web.
 */
function denominatorNote(
  netWeightTripCount: number | null | undefined,
  tripCount: number | null | undefined,
): string {
  const denominator = formatCount(netWeightTripCount)

  if (
    netWeightTripCount === null ||
    netWeightTripCount === undefined ||
    tripCount === null ||
    tripCount === undefined ||
    netWeightTripCount === tripCount
  ) {
    return `penyebutnya ${denominator} trip`
  }

  return `penyebutnya ${denominator} trip, bukan ${formatCount(tripCount)}`
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
  <main class="laporan-wb-view" data-testid="laporan-weighbridge-mobile">
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
        <span aria-current="page">Laporan Weighbridge</span>
      </nav>
      <h1 class="screen-title">Laporan Weighbridge</h1>
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
             server menolak endpoint options dengan 403. Operator, Supervisor,
             dan Mill Management memang tidak punya cara untuk menyebut mill
             lain dari layar ini. -->
        <FilterSelectField v-if="isAdmin && !millPickerForbidden" label="Mill" icon="mill">
          <select v-model="selectedBusinessUnitId" data-testid="mill-select" @change="onBusinessUnitChange">
            <option :value="null">Pilih Mill</option>
            <option v-for="unit in businessUnits" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
          </select>
        </FilterSelectField>

        <!-- Pemilih Production Line — WAJIB, dan SENGAJA tanpa opsi "semua
             line". Hanya dirender ketika mill ini memang punya lebih dari satu
             line: dengan satu line tidak ada keputusan yang perlu diminta,
             tetapi NAMANYA tetap tampil sebagai chip di bawah. -->
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

        <!-- Pemilih Periode selalu dirender (bukan v-if millRequired): bagi
             Admin yang belum memilih mill ia tampil KOSONG, bukan hilang —
             pemilih yang lenyap dan pemilih yang kosong menceritakan hal
             berbeda kepada pengguna. Urutannya persis seperti yang dikirim
             server; layar ini tidak menyaring station_type sendiri. -->
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
          <!-- Nama line yang menyertai angka yang sedang tampil — dirender di
               kedua cabang (satu line maupun banyak), supaya tidak pernah ada
               angka di layar ini yang tidak dapat ditelusuri ke satu line
               tertentu. -->
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

      <!-- Line belum berlaku: tidak ada angka sama sekali. -->
      <p v-else-if="productionLineRequired" class="notice" data-testid="production-line-required-hint">
        Pilih Production Line terlebih dahulu untuk menampilkan laporan. Angka laporan dihitung per
        Production Line, sehingga tidak ada pilihan gabungan lintas line.
      </p>

      <!-- Tidak ada satu pun line yang dapat dipilih di layar ini. Pesannya
           membedakan "mill belum punya line" dari "daftarnya gagal dimuat":
           dua keadaan yang menuntut tindakan berbeda dari pengguna. -->
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
        Mill ini belum memiliki periode pelaporan yang mencakup stasiun Weighbridge.
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
          Belum ada transaksi timbang pada periode ini untuk Production Line yang dipilih.
        </p>

        <!-- ============================================================
             ARUS MASUK. Kelompok ini dan kelompok Arus Keluar di bawahnya
             BERTUMPUK, bukan berdampingan: pada layar sempit dua kelompok
             berdampingan akan terpotong, dan kelompok yang terpotong adalah
             kelompok yang tidak terbaca. Judulnya menyebut artinya, bukan
             hanya namanya.
             ============================================================ -->
        <section class="detail-section" data-testid="flow-receive">
          <h2 class="section-title">Arus Masuk</h2>
          <p class="section-note">FFB yang datang dari estate atau supplier.</p>

          <div class="metric-stack">
            <div class="metric-card">
              <span class="metric-label">Jumlah Trip</span>
              <div class="metric-figure">
                <span class="metric-value" data-testid="kpi-receive-trip-count">
                  {{ formatCount(receive?.trip_count) }}
                </span>
                <span class="metric-unit">trip</span>
              </div>
              <span class="metric-note">
                Seluruh trip arus masuk pada periode ini, termasuk yang beratnya belum terisi.
              </span>
            </div>

            <div class="metric-card">
              <span class="metric-label">Total Berat Bersih</span>
              <div class="metric-figure">
                <span
                  class="metric-value"
                  :class="{ 'metric-value--na': receive?.net_weight_total === null || receive?.net_weight_total === undefined }"
                  data-testid="kpi-receive-net-weight-total"
                >
                  {{ formatWeight(receive?.net_weight_total) }}
                </span>
                <span
                  v-if="receive?.net_weight_total !== null && receive?.net_weight_total !== undefined"
                  class="metric-unit"
                >
                  kg
                </span>
              </div>
              <span class="metric-note" data-testid="kpi-receive-weighed-trip-count">
                Dari {{ formatCount(receive?.net_weight_trip_count) }} trip yang beratnya terisi.
              </span>
            </div>

            <div class="metric-card">
              <span class="metric-label">Berat Bersih Rata-rata</span>
              <div class="metric-figure">
                <span
                  class="metric-value"
                  :class="{ 'metric-value--na': receive?.net_weight_avg === null || receive?.net_weight_avg === undefined }"
                  data-testid="kpi-receive-net-weight-avg"
                >
                  {{ formatWeight(receive?.net_weight_avg) }}
                </span>
                <span
                  v-if="receive?.net_weight_avg !== null && receive?.net_weight_avg !== undefined"
                  class="metric-unit"
                >
                  kg/trip
                </span>
              </div>
              <!-- Penyebut ditampilkan bersama angkanya: membagi total berat
                   dengan SELURUH jumlah trip akan menurunkan rata-rata secara
                   palsu setiap kali ada penimbangan yang belum selesai. -->
              <span class="metric-note" data-testid="kpi-receive-avg-denominator">
                {{ denominatorNote(receive?.net_weight_trip_count, receive?.trip_count) }}.
              </span>
            </div>

            <!-- Trip yang beratnya belum terisi TIDAK menurunkan rata-rata, ia
                 MENGHILANG darinya — jadi satu-satunya cara pembaca tahu ada
                 yang hilang adalah bila jumlahnya dinyatakan. -->
            <div class="metric-card">
              <span class="metric-label">Penimbangan Belum Selesai</span>
              <div class="metric-figure">
                <span class="metric-value" data-testid="kpi-receive-missing">
                  {{ formatCount(receive?.missing_net_weight_trip_count) }}
                </span>
                <span class="metric-unit">trip</span>
              </div>
              <span class="metric-note">
                Berat kosong kendaraan belum ditimbang, sehingga berat bersihnya belum dapat dihitung.
                Trip ini tidak ikut dalam total maupun rata-rata di atas.
              </span>
            </div>

            <div class="metric-card">
              <span class="metric-label">Jam Tersibuk</span>
              <div class="metric-figure">
                <span
                  class="metric-value"
                  :class="{ 'metric-value--na': receive?.busiest_hour === null || receive?.busiest_hour === undefined }"
                  data-testid="kpi-receive-busiest-hour"
                >
                  {{ formatHour(receive?.busiest_hour) }}
                </span>
              </div>
              <span
                v-if="receive?.busiest_hour !== null && receive?.busiest_hour !== undefined"
                class="metric-note"
              >
                {{ formatCount(receive?.busiest_hour_trip_count) }} trip pada jam tersebut &middot;
                {{ formatCount(receive?.empty_hour_count) }} dari 24 jam tanpa satu trip pun.
              </span>
              <span v-else class="metric-note">
                Belum ada trip arus masuk pada periode ini, sehingga jam tersibuknya belum terbentuk.
              </span>
            </div>
          </div>
        </section>

        <!-- Sebaran per jam ARUS MASUK — 24 batang, menggulir DI DALAM kartu. -->
        <section v-if="showCharts" class="detail-section" data-testid="hourly-receive">
          <h2 class="section-title">Sebaran Trip per Jam — Arus Masuk</h2>
          <p class="section-note">
            Seluruh trip arus masuk dikelompokkan ke jam kejadiannya. Sumbu mendatar = jam, bukan tanggal.
            Tinggi batang memakai skala yang SAMA dengan grafik arus keluar di bawah, supaya keduanya dapat
            dibandingkan langsung. Geser mendatar untuk melihat 24 jam.
          </p>
          <div class="chart-scroll">
            <div class="chart-bars">
              <div
                v-for="row in receive?.hourly ?? []"
                :key="`receive-hour-${row.hour}`"
                class="chart-col"
                :data-testid="`hourly-receive-bar-${row.hour}`"
              >
                <span class="chart-value">{{ formatCount(row.trip_count) }}</span>
                <div class="chart-track">
                  <div class="chart-fill" :style="{ height: hourlyBarHeight(row.trip_count) }"></div>
                </div>
                <span class="chart-axis">{{ formatHourAxis(row.hour) }}</span>
              </div>
            </div>
          </div>
        </section>

        <!-- Rekap per asal. TIDAK DIPANGKAS: satu asal yang menyumbang hampir
             seluruh arus masuk tidak boleh membuat penyumbang kecil lenyap. -->
        <section v-if="hasReport && byOrigin.length > 0" class="detail-section" data-testid="by-origin">
          <h2 class="section-title">Arus Masuk per Estate / Supplier</h2>
          <p class="section-note">Diurutkan dari berat bersih terbesar. Seluruh asal ditampilkan, tanpa pemangkasan.</p>
          <ul class="breakdown-list">
            <li v-for="row in byOrigin" :key="`origin-${row.estate_supplier}`" class="breakdown-row">
              <div class="breakdown-head">
                <span class="breakdown-label">{{ formatGroupLabel(row.estate_supplier) }}</span>
                <span class="breakdown-figure">
                  {{ formatCount(row.trip_count) }} trip &middot; {{ formatWeight(row.net_weight_total) }}
                  <template v-if="row.net_weight_total !== null">kg</template>
                </span>
              </div>
              <div class="breakdown-track">
                <div
                  class="breakdown-fill"
                  :style="{ width: breakdownBarWidth(row.trip_count, maxOriginTrips) }"
                ></div>
              </div>
            </li>
          </ul>
        </section>

        <!-- ============================================================
             ARUS KELUAR. Dirender UTUH walau tidak punya satu trip pun —
             nilainya menjadi "tidak tersedia", tetapi bagiannya TIDAK
             disembunyikan: pembaca harus tahu tidak ada kiriman keluar pada
             periode itu, bukan mengira bagian ini tidak ada.
             ============================================================ -->
        <section class="detail-section" data-testid="flow-dispatch">
          <h2 class="section-title">Arus Keluar</h2>
          <p class="section-note">Kiriman yang meninggalkan pabrik menuju suatu tujuan.</p>

          <div class="metric-stack">
            <div class="metric-card">
              <span class="metric-label">Jumlah Trip</span>
              <div class="metric-figure">
                <span class="metric-value" data-testid="kpi-dispatch-trip-count">
                  {{ formatCount(dispatch?.trip_count) }}
                </span>
                <span class="metric-unit">trip</span>
              </div>
              <span class="metric-note">
                Seluruh trip arus keluar pada periode ini, termasuk yang beratnya belum terisi.
              </span>
            </div>

            <div class="metric-card">
              <span class="metric-label">Total Berat Bersih</span>
              <div class="metric-figure">
                <span
                  class="metric-value"
                  :class="{ 'metric-value--na': dispatch?.net_weight_total === null || dispatch?.net_weight_total === undefined }"
                  data-testid="kpi-dispatch-net-weight-total"
                >
                  {{ formatWeight(dispatch?.net_weight_total) }}
                </span>
                <span
                  v-if="dispatch?.net_weight_total !== null && dispatch?.net_weight_total !== undefined"
                  class="metric-unit"
                >
                  kg
                </span>
              </div>
              <span class="metric-note" data-testid="kpi-dispatch-weighed-trip-count">
                Dari {{ formatCount(dispatch?.net_weight_trip_count) }} trip yang beratnya terisi.
              </span>
            </div>

            <div class="metric-card">
              <span class="metric-label">Berat Bersih Rata-rata</span>
              <div class="metric-figure">
                <span
                  class="metric-value"
                  :class="{ 'metric-value--na': dispatch?.net_weight_avg === null || dispatch?.net_weight_avg === undefined }"
                  data-testid="kpi-dispatch-net-weight-avg"
                >
                  {{ formatWeight(dispatch?.net_weight_avg) }}
                </span>
                <span
                  v-if="dispatch?.net_weight_avg !== null && dispatch?.net_weight_avg !== undefined"
                  class="metric-unit"
                >
                  kg/trip
                </span>
              </div>
              <span class="metric-note" data-testid="kpi-dispatch-avg-denominator">
                {{ denominatorNote(dispatch?.net_weight_trip_count, dispatch?.trip_count) }}.
              </span>
            </div>

            <div class="metric-card">
              <span class="metric-label">Penimbangan Belum Selesai</span>
              <div class="metric-figure">
                <span class="metric-value" data-testid="kpi-dispatch-missing">
                  {{ formatCount(dispatch?.missing_net_weight_trip_count) }}
                </span>
                <span class="metric-unit">trip</span>
              </div>
              <span class="metric-note">
                Trip ini tidak ikut dalam total maupun rata-rata di atas.
              </span>
            </div>

            <div class="metric-card">
              <span class="metric-label">Jam Tersibuk</span>
              <div class="metric-figure">
                <span
                  class="metric-value"
                  :class="{ 'metric-value--na': dispatch?.busiest_hour === null || dispatch?.busiest_hour === undefined }"
                  data-testid="kpi-dispatch-busiest-hour"
                >
                  {{ formatHour(dispatch?.busiest_hour) }}
                </span>
              </div>
              <span
                v-if="dispatch?.busiest_hour !== null && dispatch?.busiest_hour !== undefined"
                class="metric-note"
              >
                {{ formatCount(dispatch?.busiest_hour_trip_count) }} trip pada jam tersebut &middot;
                {{ formatCount(dispatch?.empty_hour_count) }} dari 24 jam tanpa satu trip pun.
              </span>
              <span v-else class="metric-note">
                Belum ada trip arus keluar pada periode ini, sehingga jam tersibuknya belum terbentuk.
              </span>
            </div>
          </div>
        </section>

        <!-- Sebaran per jam ARUS KELUAR — skala sama dengan grafik di atas. -->
        <section v-if="showCharts" class="detail-section" data-testid="hourly-dispatch">
          <h2 class="section-title">Sebaran Trip per Jam — Arus Keluar</h2>
          <p class="section-note">
            Skalanya sama dengan grafik arus masuk di atas, sehingga tinggi batang kedua grafik dapat
            dibandingkan langsung. Geser mendatar untuk melihat 24 jam.
          </p>
          <div class="chart-scroll">
            <div class="chart-bars">
              <div
                v-for="row in dispatch?.hourly ?? []"
                :key="`dispatch-hour-${row.hour}`"
                class="chart-col"
                :data-testid="`hourly-dispatch-bar-${row.hour}`"
              >
                <span class="chart-value">{{ formatCount(row.trip_count) }}</span>
                <div class="chart-track">
                  <div
                    class="chart-fill chart-fill--dispatch"
                    :style="{ height: hourlyBarHeight(row.trip_count) }"
                  ></div>
                </div>
                <span class="chart-axis">{{ formatHourAxis(row.hour) }}</span>
              </div>
            </div>
          </div>
        </section>

        <!-- Rekap per tujuan. Trip tanpa tujuan punya KELOMPOKNYA SENDIRI dan
             tidak dibuang: membuangnya membuat jumlah trip per tujuan tidak
             lagi menjumlah ke total arus keluar. -->
        <section v-if="hasReport && byDestination.length > 0" class="detail-section" data-testid="by-destination">
          <h2 class="section-title">Arus Keluar per Tujuan</h2>
          <p class="section-note">
            Diurutkan dari berat bersih terbesar. Trip yang tujuannya belum diisi punya kelompoknya sendiri
            dan tetap ikut terhitung.
          </p>
          <ul class="breakdown-list">
            <li
              v-for="(row, index) in byDestination"
              :key="`destination-${index}-${row.destination ?? 'belum-diisi'}`"
              class="breakdown-row"
            >
              <div class="breakdown-head">
                <span class="breakdown-label">{{ formatGroupLabel(row.destination) }}</span>
                <span class="breakdown-figure">
                  {{ formatCount(row.trip_count) }} trip &middot; {{ formatWeight(row.net_weight_total) }}
                  <template v-if="row.net_weight_total !== null">kg</template>
                </span>
              </div>
              <div class="breakdown-track">
                <div
                  class="breakdown-fill breakdown-fill--dispatch"
                  :style="{ width: breakdownBarWidth(row.trip_count, maxDestinationTrips) }"
                ></div>
              </div>
            </li>
          </ul>
        </section>

        <!-- Kelengkapan pencatatan adalah BAGIAN laporan, bukan catatan kaki:
             pembaca memakainya untuk menilai seberapa jauh angka di atas dapat
             diandalkan. -->
        <section class="detail-section" data-testid="completeness">
          <h2 class="section-title">Kelengkapan Pencatatan</h2>

          <div class="queue-figures">
            <div class="queue-figure">
              <span class="queue-label">Hari bertrip</span>
              <span class="queue-value" data-testid="completeness-days-with-trip">
                {{ formatCount(completeness?.days_with_trip) }} / {{ formatCount(completeness?.days_counted) }}
              </span>
              <span class="queue-note" data-testid="completeness-percent">
                {{ formatPercent(daysWithTripPercent) }}
              </span>
            </div>
            <div class="queue-figure">
              <span class="queue-label">Trip draft</span>
              <span class="queue-value" data-testid="draft-trip-count">
                {{ formatCount(summary?.draft_trip_count) }}
              </span>
            </div>
            <div class="queue-figure">
              <span class="queue-label">Tanpa penanda waktu</span>
              <span class="queue-value" data-testid="undated-trip-count">
                {{ formatCount(summary?.undated_trip_count) }}
              </span>
            </div>
          </div>

          <!-- Periode berjalan: penyebutnya berhenti di hari ini, dan itu
               dinyatakan — hari yang belum terjadi tidak boleh menurunkan
               persen (temuan audit 2026-10-04). -->
          <p v-if="completeness?.period_running" class="section-note" data-testid="completeness-running-note">
            Dihitung sampai hari ini, periode masih berjalan
            ({{ formatCount(completeness?.days_counted) }} dari
            {{ formatCount(completeness?.days_in_period) }} hari periode sudah lewat).
          </p>
          <p v-else-if="!completeness?.days_counted" class="section-note" data-testid="completeness-not-started-note">
            Periode belum mulai, sehingga belum ada hari yang dapat dijadikan pembagi.
          </p>
          <p v-else class="section-note">
            Dari {{ formatCount(completeness?.days_in_period) }} hari periode, seluruhnya sudah lewat.
          </p>

          <p class="section-note" data-testid="draft-note">
            Trip berstatus draft IKUT terhitung pada seluruh angka di atas — jumlahnya ditampilkan supaya
            terlihat seberapa besar laporan ini berdiri di atas data yang belum selesai.
          </p>
          <p class="section-note" data-testid="undated-note">
            Trip tanpa penanda waktu penimbangan TIDAK dapat ditempatkan pada periode mana pun, sehingga
            tidak ikut terhitung di angka mana pun. Jumlahnya ditampilkan supaya selisih terhadap jumlah
            baris yang Anda ketahui dapat dijelaskan.
          </p>
        </section>

        <!-- Rekap harian — panjang pada periode sebulan, jadi dibungkus
             CollapsibleSection dan TERTUTUP secara bawaan. Membuka atau
             menutupnya murni penyingkapan: datanya sudah ada di memori, tidak
             ada permintaan jaringan baru. Tabelnya menggulir di dalam kartunya
             sendiri, halaman tetap satu kolom.

             KEDUA ARUS PUNYA KOLOMNYA SENDIRI, termasuk pada baris total —
             tidak ada satu sel pun yang menjumlahkan keduanya. -->
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
                  <th class="num">Trip Masuk</th>
                  <th class="num">Berat Masuk (kg)</th>
                  <th class="num">Trip Keluar</th>
                  <th class="num">Berat Keluar (kg)</th>
                </tr>
              </thead>
              <tbody>
                <!-- Seluruh baris dari server, dalam urutannya, TERMASUK baris
                     bernilai nol pada salah satu arus: hari yang hanya memuat
                     satu arus tetap harus terbaca. -->
                <tr v-for="row in daily" :key="`recap-${row.date}`">
                  <td>{{ formatDate(row.date) }}</td>
                  <td class="num">{{ formatCount(row.receive_trip_count) }}</td>
                  <td class="num">{{ formatWeightCell(row.receive_net_weight_total) }}</td>
                  <td class="num">{{ formatCount(row.dispatch_trip_count) }}</td>
                  <td class="num">{{ formatWeightCell(row.dispatch_net_weight_total) }}</td>
                </tr>
              </tbody>
              <tfoot v-if="dailyTotal">
                <tr data-testid="daily-recap-total">
                  <th>Total periode</th>
                  <td class="num">{{ formatCount(dailyTotal.receive_trip_count) }}</td>
                  <td class="num">{{ formatWeightCell(dailyTotal.receive_net_weight_total) }}</td>
                  <td class="num">{{ formatCount(dailyTotal.dispatch_trip_count) }}</td>
                  <td class="num">{{ formatWeightCell(dailyTotal.dispatch_net_weight_total) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
          <p class="section-note">
            Tidak ada kolom total yang menggabungkan arus masuk dengan arus keluar — keduanya menjawab
            pertanyaan yang berbeda dan menjumlahkannya tidak menghasilkan angka yang berarti.
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
.laporan-wb-view {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 0 16px 20px;
  background: #ffffff;
  font-family: 'Inter', sans-serif;
  box-sizing: border-box;
  /* Halaman TIDAK PERNAH menggulir mendatar; yang menggulir adalah isi kartu
     grafik/tabel di dalamnya. */
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
/* Warna berbeda per arus supaya kedua grafik tidak tertukar saat digulir,
   sementara SKALANYA tetap sama. */
.chart-fill--dispatch { background: #2563eb; }
.chart-axis { font-size: 10px; color: #6b7280; }

/* Rekap per asal/tujuan: bilah proporsi + angka. Bilahnya skala visual
   saja; yang dibaca pengguna adalah angkanya. */
.breakdown-list { display: flex; flex-direction: column; gap: 10px; margin: 0; padding: 0; list-style: none; }
.breakdown-row { display: flex; flex-direction: column; gap: 4px; }
.breakdown-head { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.breakdown-label { font-size: 13px; font-weight: 600; color: #1f2937; }
.breakdown-figure { font-size: 12px; color: #6b7280; }
.breakdown-track { width: 100%; height: 6px; border-radius: 3px; background: #f3f4f6; overflow: hidden; }
.breakdown-fill { height: 100%; border-radius: 3px; background: #249360; }
.breakdown-fill--dispatch { background: #2563eb; }

.queue-figures { display: flex; gap: 20px; flex-wrap: wrap; }
.queue-figure { display: flex; flex-direction: column; gap: 2px; }
.queue-label { font-size: 11px; color: #6b7280; }
.queue-value { font-size: 20px; font-weight: 700; color: #1f2937; }
.queue-note { font-size: 12px; color: #6b7280; }

/* Tabel rekap: guliran mendatar DI DALAM kartu, bukan pada halaman. */
.detail-table-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.detail-table { border-collapse: collapse; width: 100%; font-size: 13px; }
.detail-table th, .detail-table td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
.detail-table th.num, .detail-table td.num { text-align: right; }
.detail-table tfoot th, .detail-table tfoot td { font-weight: 700; color: #1f2937; border-bottom: none; }

.action-footer { display: flex; gap: 10px; margin-top: auto; }
.action-button { min-height: 44px; padding: 0 16px; border-radius: 8px; font-size: 14px; font-weight: 600; font-family: inherit; cursor: pointer; box-sizing: border-box; }
.action-button--primary { border: 1px solid #249360; background: #249360; color: #ffffff; }
.action-button:disabled { opacity: 0.5; cursor: not-allowed; }

/* Tombol yang sedang bekerja tetap berwarna penuh (spinner terbaca); tombol
   lain yang ikut terkunci tetap redup. */
.action-button[aria-busy='true']:disabled {
  opacity: 1;
  cursor: progress;
}
.action-button--secondary { border: 1px solid #e5e7eb; background: #ffffff; color: #1f2937; }
</style>
