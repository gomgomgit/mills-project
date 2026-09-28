<script setup lang="ts">
/**
 * LaporanBoilerRoomView — screen-137--laporan-boiler-room-mobile /
 * usecase-137--laporan-boiler-room-mobile "Lihat Laporan Periode Boiler
 * Room (Mobile)" (dipasang di /reports/boiler-room, meta.public = false).
 * Actors: operator, supervisor, mill_management, admin.
 *
 * Saudara LaporanSterilizerView (screen-135) dan LaporanCagesTrackView
 * (screen-136) — struktur, penanganan galat, dan kosakata data-testid-nya
 * sengaja dibuat sedekat mungkin, karena ketiganya memecahkan masalah yang
 * sama dan perbedaan gaya di antaranya hanya akan menjadi beban pembaca
 * berikutnya. Yang BERBEDA di layar ini bukan gayanya melainkan isinya:
 * Cages & Tracks melaporkan KELUARAN ("berapa banyak"), Boiler Room
 * melaporkan KONDISI ("seberapa stabil"), dan itu mengubah lima hal di
 * bawah.
 *
 * ────────────────────────────────────────────────────────────────────────
 * 1. KELENGKAPAN PENCATATAN DIRENDER LEBIH DULU, DI ATAS SEGALANYA
 * ────────────────────────────────────────────────────────────────────────
 * Periode yang hanya terisi 20% tetap menghasilkan rata-rata yang rapi dan
 * meyakinkan. Karena itu kartu kelengkapan berada di ATAS seluruh blok
 * angka dalam urutan DOM — bukan sebagai catatan kaki di bawah, yang baru
 * terbaca setelah pembacanya terlanjur mempercayai angkanya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * 2. EKSTREM PADA KARTU ≠ RATA-RATA HARIAN PADA REKAP, DAN LAYAR MENGATAKANNYA
 * ────────────────────────────────────────────────────────────────────────
 * `metrics[*].min/max` berasal dari PEMBACAAN MENTAH per slot waktu,
 * sementara kolom rata-rata pada tren dan rekap harian adalah rata-rata
 * PER TANGGAL. Keduanya memang tidak dapat dicocokkan satu sama lain, dan
 * tanpa label pembaca akan menyimpulkan laporannya rusak — karena itu ada
 * blok [data-testid="raw-extremes-note"], persis seperti versi webnya.
 *
 * PERHATIAN BAGI SIAPA PUN YANG MEMBANDINGKAN DENGAN MOCK: mock ponsel
 * screen-137 (dan mock web screen-131) menampilkan min/max sebagai ekstrem
 * RATA-RATA HARIAN, karena hanya bentuk itu yang dapat dicocokkan dengan
 * tabel di layar yang sama. Implementasi BoilerRoomReportService yang sudah
 * rilis memakai ekstrem PEMBACAAN MENTAH (metricsOf() → min()/max() atas
 * baris terisi). YANG BERLAKU ADALAH PEMBACAAN MENTAH; mock BUKAN acuan
 * untuk butir ini, dan layar inilah yang wajib mengatakan bahwa keduanya
 * tidak dapat direkonsiliasi.
 *
 * ────────────────────────────────────────────────────────────────────────
 * 3. SETIAP METRIK PUNYA PENYEBUTNYA SENDIRI — DAN PENYEBUT ITU TAMPIL
 * ────────────────────────────────────────────────────────────────────────
 * Kesembilan metrik numerik dirender masing-masing DENGAN reading_count-nya
 * sendiri berdampingan dengan angkanya. Sebuah baris pengukuran dapat
 * mengisi tekanan dan mengosongkan pH, sehingga penyebutnya berbeda-beda.
 * Rata-rata dari 3 pembacaan tidak boleh terlihat sama meyakinkan dengan
 * rata-rata dari 300 pembacaan, dan satu-satunya cara mencegahnya adalah
 * menampilkan penyebutnya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * 4. PERAWATAN PUNYA TIGA KEADAAN, DAN "TIDAK TERCATAT" BERDIRI SENDIRI
 * ────────────────────────────────────────────────────────────────────────
 * blowdown/sootblowing: dilakukan, tidak dilakukan, tidak tercatat.
 * Ketiganya dirender sebagai tiga baris terpisah dan tidak pernah
 * dijumlahkan satu sama lain di layar ini. `all_unrecorded` membedakan nol
 * yang berarti "tidak pernah dilakukan" dari nol yang berarti "tidak pernah
 * dicatat" — tanpanya keduanya tampil sebagai 0 yang sama.
 *
 * ────────────────────────────────────────────────────────────────────────
 * 5. TIDAK ADA PENANDAAN AMBANG DI MANA PUN
 * ────────────────────────────────────────────────────────────────────────
 * Tidak ada kelas aman/bahaya, tidak ada ikon peringatan, tidak ada
 * pewarnaan bersyarat atas nilai metrik. Boiler Room tidak punya master
 * target operasional, dan menurunkan ambang dari data periode itu sendiri
 * akan menghasilkan angka yang TERLIHAT seperti batas keselamatan bejana
 * tekan padahal hanya statistik tentang data yang sedang dinilainya.
 * Paragraf ini ada supaya kekosongan itu tidak "dilengkapi" kelak.
 *
 * ────────────────────────────────────────────────────────────────────────
 * DAN TIGA HAL YANG SAMA PERSIS DENGAN screen-135 / screen-136
 * ────────────────────────────────────────────────────────────────────────
 * a. NOL PERHITUNGAN ULANG DI KLIEN. Setiap angka dipetakan apa adanya dari
 *    /api/boiler-room-reports/summary — endpoint yang sama persis dengan
 *    laporan web (screen-131), sehingga kedua layar mustahil berbeda. Tidak
 *    ada penjumlahan, perataan, maupun pembulatan ulang. Satu-satunya angka
 *    turunan adalah TINGGI BATANG grafik: skala visual murni, tidak pernah
 *    dibacakan sebagai angka kepada pengguna.
 * b. NULL BUKAN NOL, DAN ITU TERLIHAT DI LAYAR. Metrik yang tidak pernah
 *    diisi dirender "-" dengan 0 pembacaan, TIDAK PERNAH 0,0 — "belum
 *    pernah diukur" dan "nilainya nol" adalah dua fakta berbeda.
 * c. GAGAL TERTUTUP PADA MILL + 401 BUKAN KEGAGALAN JARINGAN. Akun terikat
 *    mill yang business_unit_id-nya kosong TIDAK memicu satu pun permintaan
 *    HTTP — termasuk tidak memanggil endpoint options, yang justru akan
 *    membentuk daftar SELURUH mill di perangkat orang yang tidak berhak
 *    melihat satu pun di antaranya. Sesi berakhir (401) diarahkan ke Login
 *    dan BUKAN kasus coba lagi: pesan galat dan tombol Coba Lagi justru
 *    tidak boleh muncul untuk 401.
 *
 * BENTUK GALAT — apiClient menolak lewat normalizeError yang mengembalikan
 * objek DATAR { message, errors?, status? } dan MEMBUANG `response`. Berkas
 * ini membaca `error.status` saja; `error.response.status` tidak pernah ada
 * di produksi dan mengeceknya hanya akan menyembunyikan kesalahan.
 *
 * SATU KOLOM, TANPA GULIRAN MENDATAR PADA HALAMAN. Yang lebar bukan
 * halamannya melainkan isi kartunya: kedua grafik tren, tabel rekap per
 * unit boiler, dan tabel rekap harian masing-masing menggulir DI DALAM
 * kartunya sendiri (.chart-scroll / .detail-table-wrap), sementara halaman
 * tetap overflow-x: hidden.
 */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { productionLineRepo, type ProductionLineOption } from '@/services/productionLineRepo'
import { useFloatingClockStore } from '@/stores/floatingClock'
import { useAiAssistantStore } from '@/stores/aiAssistant'
import StatusBadge from '@/components/StatusBadge.vue'
import boilerRoomReportRepo, {
  type BoilerRoomReportBusinessUnitOption,
  type BoilerRoomReportDailyRow,
  type BoilerRoomReportMetric,
  type BoilerRoomReportMetricKey,
  type BoilerRoomReportPeriodOption,
  type BoilerRoomReportSummary,
} from '@/services/boilerRoomReportRepo'

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

const businessUnits = ref<BoilerRoomReportBusinessUnitOption[]>([])

/**
 * Akun terikat mill tetapi business_unit_id-nya kosong — layar berhenti
 * total di sini, tanpa satu pun permintaan HTTP (lihat catatan c).
 */
const noMillForAccount = ref(false)

/** Endpoint options menjawab 403 — pemilih Mill tidak dirender sama sekali. */
const millPickerForbidden = ref(false)

/** Admin yang belum memilih mill: tidak ada angka, hanya arahan memilih. */
const millRequired = computed(() => isAdmin.value && !selectedBusinessUnitId.value)

/**
 * Cakupan yang dikirim ke repo. business_unit_id hanya ikut untuk Admin —
 * repo pun menolak mengirimkannya untuk peran lain (gagal tertutup di dua
 * tempat, sengaja). Operator, Supervisor, dan Mill Management mendapat
 * mill-nya dari akun di sisi server, jadi layar ini memang tidak punya cara
 * untuk menyebut mill lain.
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

/* ------------------------------------------------------------------ */
/* Periode & ringkasan                                                 */
/* ------------------------------------------------------------------ */

const periods = ref<BoilerRoomReportPeriodOption[]>([])
const selectedPeriodId = ref<string | null>(null)
const summary = ref<BoilerRoomReportSummary | null>(null)

const loadingPeriods = ref(false)
const loadingSummary = ref(false)
const exporting = ref(false)

/** Nama mill yang sedang berlaku, sebagai keterangan. */
const currentMillName = computed(() => {
  if (isAdmin.value) {
    return businessUnits.value.find((item) => item.id === selectedBusinessUnitId.value)?.name ?? ''
  }

  return (
    summary.value?.business_unit?.name ||
    summary.value?.period?.business_unit_name ||
    authStore.businessUnit?.name ||
    ''
  )
})

/* ------------------------------------------------------------------ */
/* Galat                                                               */
/* ------------------------------------------------------------------ */

const networkError = ref<string | null>(null)
const errorMessage = ref<string | null>(null)

/** Aksi terakhir yang gagal — itulah yang diulang tombol Coba Lagi. */
const retryAction = ref<(() => Promise<void>) | null>(null)
const canRetry = computed(() => retryAction.value !== null)

/** Daftar periode kosong — arahan menghubungi Admin, bukan pesan galat. */
const noPeriods = computed(
  () =>
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

/**
 * Bentuk galat NYATA yang sampai kemari: objek DATAR dari
 * apiClient.normalizeError. `response` sengaja TIDAK ada pada antarmuka ini
 * — menuliskannya akan mengundang pengecekan `error.response.status` yang
 * tidak pernah benar di produksi dan diam-diam menelan kasus 401.
 */
interface ErrorLike {
  status?: number
  message?: string
}

function statusOf(error: unknown): number | undefined {
  return (error as ErrorLike | null)?.status
}

function messageOf(error: unknown, fallback: string): string {
  return (error as ErrorLike | null)?.message || fallback
}

/**
 * Penanganan galat terpusat. Urutan cabangnya menentukan benar/salahnya
 * layar ini:
 *   401 -> Login (bukan pesan jaringan, dan TANPA tombol Coba Lagi — sesi
 *          yang sudah mati tidak akan hidup karena dicoba lagi)
 *   tanpa status -> penolakan tanpa respons = jaringan; periode terpilih
 *                   SENGAJA tidak disentuh
 *   sisanya -> pesan yang dapat dibaca pengguna
 */
function handleError(error: unknown, retry: (() => Promise<void>) | null): void {
  const status = statusOf(error)

  if (status === 401) {
    // Sesi berakhir. Diteruskan ke penjagaan sesi; layar ini tidak
    // mengarang pesan jaringan atas sesuatu yang bukan masalah jaringan,
    // dan tidak menawarkan Coba Lagi yang pasti gagal selamanya.
    networkError.value = null
    errorMessage.value = null
    retryAction.value = null
    summary.value = null
    // replace, bukan push: layar laporan yang sesinya sudah mati tidak
    // boleh dapat dicapai kembali dengan tombol Back peramban.
    router.replace({ name: 'login' })

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
    businessUnits.value = await boilerRoomReportRepo.fetchBusinessUnits()
  } catch (error) {
    if (statusOf(error) === 403) {
      // Pemilih mill memang bukan untuk peran ini — jangan dirender sama
      // sekali; menawarkan pemilih yang pasti gagal adalah kebohongan.
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
    periods.value = await boilerRoomReportRepo.fetchPeriods(scope.value)
  } catch (error) {
    periods.value = []
    handleError(error, loadPeriods)
  } finally {
    loadingPeriods.value = false
  }
}

/**
 * Satu-satunya pemanggilan angka laporan. Dipanggil saat periode dipilih
 * dan saat Coba Lagi — tidak pernah oleh buka/tutup rekap harian, yang
 * murni penyingkapan atas data yang sudah ada di memori.
 */
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
    summary.value = await boilerRoomReportRepo.fetchSummary(periodId, scope.value)
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
    // Berhenti di sini. TIDAK ada permintaan apa pun — lihat catatan c.
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

  return `laporan-boiler-room_${slug || 'periode'}.csv`
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
    // Isi CSV dibentuk SERVER (satu baris per slot waktu, kolom konteks
    // record diulang, ketiga kolom teks bebas verbatim). Layar ini tidak
    // pernah menyusun berkasnya dari angka yang sedang tampil.
    const blob = await boilerRoomReportRepo.exportCsv(periodId, scope.value)
    boilerRoomReportRepo.saveCsvFile(blob, exportFilename())
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

const coverage = computed(() => summary.value?.coverage ?? null)
const metrics = computed(() => summary.value?.metrics ?? null)
const maintenance = computed(() => summary.value?.maintenance ?? null)
const daily = computed<BoilerRoomReportDailyRow[]>(() => summary.value?.daily ?? [])
const byUnit = computed(() => summary.value?.by_unit ?? [])
const total = computed(() => summary.value?.total ?? null)

const hasReport = computed(
  () => Boolean(summary.value) && !networkError.value && !errorMessage.value,
)

/**
 * has_data datang dari SERVER (filled_slots > 0) dan tidak diturunkan ulang
 * di sini. Periode tanpa satu pun pembacaan TIDAK menggambar grafik: garis
 * datar dari data kosong akan terbaca sebagai hasil pengukuran.
 */
const emptyPeriod = computed(() => Boolean(summary.value) && !summary.value?.has_data)
const showCharts = computed(() => hasReport.value && !emptyPeriod.value)

/**
 * Kesembilan metrik numerik Boiler Room, dalam urutan
 * BoilerRoomReportService::NUMERIC_METRICS. Label dan satuan mengikuti
 * laporan web supaya pembaca yang berpindah antar kedua layar menemukan
 * kata yang sama. Kelima teratas adalah yang disebut information_displayed
 * secara eksplisit; empat sisanya tetap dirender karena payload membawanya
 * lengkap dan test spec menuntut kesembilan penyebutnya terbaca.
 *
 * Tiga kolom teks bebas (laju bahan bakar, beban ID fan, beban SA fan)
 * SENGAJA tidak ada di daftar ini dan memang tidak boleh ada: satuannya
 * bercampur (Hz/%/ton, A/%), sehingga merata-ratakannya mustahil, bukan
 * sekadar tidak diinginkan. Ketiganya hanya muncul apa adanya pada ekspor.
 */
const METRIC_CARDS: Array<{
  key: BoilerRoomReportMetricKey
  testid: string
  label: string
  unit: string
  digits: number
}> = [
  { key: 'steam_pressure_bar', testid: 'steam-pressure', label: 'Tekanan Uap', unit: 'bar', digits: 1 },
  { key: 'steam_temp_c', testid: 'steam-temp', label: 'Suhu Uap', unit: '°C', digits: 1 },
  { key: 'water_tds_ppm', testid: 'water-tds', label: 'TDS Air', unit: 'ppm', digits: 0 },
  { key: 'water_ph', testid: 'water-ph', label: 'pH Air', unit: '', digits: 1 },
  { key: 'exhaust_gas_temp_c', testid: 'exhaust-gas-temp', label: 'Suhu Gas Buang', unit: '°C', digits: 0 },
  { key: 'feed_water_temp_c', testid: 'feed-water-temp', label: 'Suhu Air Umpan', unit: '°C', digits: 1 },
  {
    key: 'feed_water_tank_level_percent',
    testid: 'feed-water-tank-level',
    label: 'Level Tangki Air Umpan',
    unit: '%',
    digits: 1,
  },
  {
    key: 'boiler_water_level_percent',
    testid: 'boiler-water-level',
    label: 'Level Air Boiler',
    unit: '%',
    digits: 1,
  },
  {
    key: 'dust_collector_differential_pressure_mmh2o',
    testid: 'dust-collector-dp',
    label: 'Beda Tekanan Dust Collector',
    unit: 'mmH2O',
    digits: 1,
  },
]

function metricOf(key: BoilerRoomReportMetricKey): BoilerRoomReportMetric | null {
  return metrics.value?.[key] ?? null
}

/**
 * Rekap harian TERTUTUP secara bawaan: pada periode sebulan daftarnya
 * puluhan baris dan akan mendorong angka utama serta grafik jauh ke bawah
 * layar ponsel.
 *
 * Sengaja TIDAK memakai components/CollapsibleSection.vue, yang
 * menyembunyikan isinya dengan v-show sehingga barisnya tetap ada di DOM.
 * Di sini barisnya harus benar-benar tidak dirender saat tertutup (v-if),
 * sementara SUMBER datanya tidak berubah sedikit pun — membuka dan menutup
 * adalah penyingkapan murni atas `daily` yang sudah ada di memori, dan
 * TIDAK memicu satu pun permintaan jaringan baru. fetchSummary tetap
 * dipanggil tepat sekali per periode yang dipilih.
 */
const recapOpen = ref(false)

function toggleRecap(): void {
  recapOpen.value = !recapOpen.value
}

/* ------------------------------------------------------------------ */
/* Skala visual grafik — BUKAN angka yang dibaca pengguna              */
/* ------------------------------------------------------------------ */

/**
 * Tinggi batang = 28% + 72% × (nilai − terendah) / (tertinggi − terendah),
 * sama dengan laporan web. Sumbu tegak SENGAJA tidak dimulai dari nol:
 * batang membandingkan tanggal satu sama lain, bukan besaran mutlak — dan
 * hal itu dinyatakan di kartunya sendiri.
 *
 * Nilai terendah/tertinggi di sini dipakai HANYA sebagai skala tampilan dan
 * TIDAK PERNAH dibacakan sebagai angka kepada pengguna; angka yang dibaca
 * selalu nilai server apa adanya. Tidak ada pewarnaan aman/bahaya yang
 * diturunkan darinya — lihat catatan 5.
 */
function seriesOf(column: 'steam_pressure_avg' | 'steam_temp_avg'): number[] {
  const values: number[] = []

  for (const row of daily.value) {
    const value = row[column]

    if (value !== null && value !== undefined) {
      values.push(value)
    }
  }

  return values
}

function barHeight(
  value: number | null,
  column: 'steam_pressure_avg' | 'steam_temp_avg',
): string {
  if (value === null || value === undefined) {
    return '0%'
  }

  const values = seriesOf(column)

  if (values.length === 0) {
    return '0%'
  }

  const lowest = Math.min(...values)
  const highest = Math.max(...values)

  if (highest <= lowest) {
    return '100%'
  }

  return `${28 + (72 * (value - lowest)) / (highest - lowest)}%`
}

/* ------------------------------------------------------------------ */
/* Format Indonesia                                                    */
/* ------------------------------------------------------------------ */

const MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']

/**
 * "-" adalah bentuk baku untuk nilai yang TIDAK ADA di layar ini, dan
 * sengaja bukan "0": sebuah metrik yang tidak pernah diisi harus terbaca
 * sebagai belum diukur, bukan sebagai hasil pengukuran bernilai nol.
 */
const NOT_AVAILABLE = '-'

/** Titik ribuan, tanpa desimal — untuk cacah (slot, pembacaan, perawatan). */
function formatCount(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return NOT_AVAILABLE
  }

  return new Intl.NumberFormat('id-ID').format(value)
}

/**
 * Bilangan berdesimal. Jumlah desimalnya adalah keputusan TAMPILAN per
 * metrik (tekanan 1 desimal, TDS 0) — nilainya sendiri datang sudah
 * dibulatkan dari server dan tidak dihitung ulang di sini.
 */
function formatNumber(value: number | null | undefined, digits = 1): string {
  if (value === null || value === undefined) {
    return NOT_AVAILABLE
  }

  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  }).format(value)
}

function formatDate(value: string | null | undefined): string {
  if (!value) {
    return NOT_AVAILABLE
  }

  const [year, month, day] = value.slice(0, 10).split('-')

  if (!year || !month || !day) {
    return value
  }

  return `${day} ${MONTHS_SHORT[Number(month) - 1] ?? month} ${year}`
}

/** Label sumbu tren harian — tanggalnya saja, agar kolomnya tetap muat. */
function formatDayAxis(value: string | null | undefined): string {
  if (!value) {
    return NOT_AVAILABLE
  }

  return value.slice(8, 10) || value
}

const PERIOD_STATUS_LABELS: Record<string, string> = {
  draft: 'Draft',
  open: 'Terbuka',
  closed: 'Ditutup',
}

function periodStatusLabel(status: string | null | undefined): string {
  if (!status) {
    return NOT_AVAILABLE
  }

  return PERIOD_STATUS_LABELS[status] ?? status
}

function periodOptionLabel(period: BoilerRoomReportPeriodOption): string {
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
  <main class="laporan-br-view" data-testid="laporan-boiler-room-mobile">
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
        <span aria-current="page">Laporan Boiler Room</span>
      </nav>
      <h1 class="screen-title">Laporan Boiler Room</h1>
    </div>

    <!-- Akun terikat mill tetapi mill-nya kosong: berhenti total, tanpa
         satu pun permintaan HTTP dan tanpa pemilih mill sebagai pengganti. -->
    <p v-if="noMillForAccount" class="notice notice--warning" role="alert" data-testid="no-mill-for-account">
      Akun Anda belum terhubung ke mill mana pun, sehingga laporan tidak dapat ditampilkan.
      Silakan hubungi Admin untuk menghubungkan akun Anda ke sebuah mill.
    </p>

    <template v-else>
      <div class="filter-bar">
        <!-- Pemilih Mill — hanya Admin, dan tidak dirender sama sekali bila
             server menolak endpoint options dengan 403. Operator,
             Supervisor, dan Mill Management memang tidak punya cara untuk
             menyebut mill lain dari layar ini. -->
        <label v-if="isAdmin && !millPickerForbidden" class="filter-field">
          <span>Mill</span>
          <select v-model="selectedBusinessUnitId" data-testid="mill-select" @change="onBusinessUnitChange">
            <option :value="null">Pilih Mill</option>
            <option v-for="unit in businessUnits" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
          </select>
        </label>

        <!-- Keterangan mill bagi pengguna yang terikat satu mill. -->
        <p v-else-if="!isAdmin" class="mill-current" data-testid="mill-current">
          Mill: <strong>{{ currentMillName || NOT_AVAILABLE }}</strong>
        </p>

        <!-- Pemilih Production Line — WAJIB, dan SENGAJA tanpa opsi
             "semua line". Hanya dirender ketika mill ini memang punya
             lebih dari satu line: dengan satu line tidak ada keputusan
             yang perlu diminta. -->
        <label v-if="productionLines.length > 1" class="filter-field">
          <span>Production Line</span>
          <select
            v-model="selectedProductionLineId"
            data-testid="production-line-select"
            @change="onProductionLineChange"
          >
            <option :value="null">Pilih Production Line</option>
            <option v-for="line in productionLines" :key="line.id" :value="line.id">{{ line.name }}</option>
          </select>
        </label>

        <!-- Nama line yang menyertai angka yang sedang tampil — dirender
             di kedua cabang (satu line maupun banyak), supaya tidak pernah
             ada angka di layar ini yang tidak dapat ditelusuri ke satu
             line tertentu. -->
        <p v-if="activeProductionLineName" class="mill-current" data-testid="production-line-current">
          Production Line: <strong>{{ activeProductionLineName }}</strong>
        </p>

        <!-- Pemilih Periode selalu dirender (bukan v-if millRequired): bagi
             Admin yang belum memilih mill ia tampil KOSONG, bukan hilang —
             pemilih yang lenyap dan pemilih yang kosong menceritakan hal
             berbeda kepada pengguna. Urutannya persis seperti yang dikirim
             server; layar ini tidak menyaring station_type sendiri. -->
        <label class="filter-field">
          <span>Periode Pelaporan</span>
          <select v-model="selectedPeriodId" data-testid="period-select" @change="onPeriodChange">
            <option :value="null">Pilih Periode</option>
            <option v-for="period in periods" :key="period.id" :value="period.id">
              {{ periodOptionLabel(period) }}
            </option>
          </select>
        </label>
      </div>

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
        Mill ini belum memiliki periode pelaporan yang mencakup stasiun Boiler Room.
        Silakan hubungi Admin untuk membuat periode pelaporan terlebih dahulu.
      </p>

      <p v-if="loadingPeriods" class="status-text">Memuat daftar periode…</p>

      <!-- Kegagalan jaringan: dikatakan terus terang, dengan cara mencoba
           lagi. Periode yang sudah dipilih tetap terpilih, dan Coba Lagi
           memanggil ulang period_id yang SAMA. -->
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

      <p v-if="loadingSummary" class="status-text">Memuat ringkasan periode…</p>

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
          Belum ada data pembacaan Boiler Room pada periode ini. Seluruh angka di bawah ditampilkan sebagai
          &ldquo;{{ NOT_AVAILABLE }}&rdquo; — belum diukur, bukan bernilai nol.
        </p>

        <!-- ============ KELENGKAPAN PENCATATAN — DI ATAS SEGALANYA ============
             Ditempatkan mendahului seluruh blok angka dalam urutan DOM. Lihat
             catatan 1 pada docblock: periode yang terisi sebagian tetap
             menghasilkan rata-rata yang rapi, dan itu harus terbaca lebih
             dulu. coverage_percent datang dari server apa adanya. -->
        <section v-if="coverage" class="detail-section coverage-card" data-testid="coverage-card">
          <h2 class="section-title">Kelengkapan Pencatatan</h2>
          <p class="section-note">Baca ini lebih dulu — seluruh angka di bawah bersandar padanya.</p>
          <div class="metric-figure">
            <span class="metric-value" data-testid="coverage-percent">
              {{ formatNumber(coverage.coverage_percent, 1) }}%
            </span>
          </div>
          <span class="metric-note" data-testid="coverage-slots">
            {{ formatCount(coverage.filled_slots) }} dari {{ formatCount(coverage.expected_slots) }} slot waktu terisi
          </span>
          <span class="metric-note" data-testid="coverage-formula">
            Slot yang diharapkan = {{ formatCount(coverage.boiler_unit_count) }} unit boiler &times;
            {{ formatCount(coverage.days_in_period) }} hari &times;
            {{ formatCount(coverage.slots_per_unit_per_day) }} slot.
          </span>
          <span class="metric-note">
            Seluruh angka di bawah dihitung dari pembacaan yang ADA, bukan dari periode penuh — slot yang tidak
            terisi tidak diperlakukan sebagai nol, melainkan tidak ikut dihitung. Karena itu tiap metrik membawa
            jumlah pembacaannya sendiri, dan jumlah itu berbeda-beda antar metrik.
          </span>
        </section>

        <!-- ============ LABEL WAJIB: EKSTREM MENTAH vs RATA-RATA HARIAN ============
             Lihat catatan 2. Tanpa blok ini pembaca yang mencoba mencocokkan
             "terendah" pada kartu dengan kolom rata-rata pada rekap akan
             menyimpulkan laporannya rusak. -->
        <p class="notice notice--info" data-testid="raw-extremes-note">
          Nilai <strong>terendah</strong> dan <strong>tertinggi</strong> pada kartu metrik di bawah diambil dari
          <strong>pembacaan mentah per slot waktu</strong>, sedangkan kolom rata-rata pada tren harian dan Rekap
          Harian adalah <strong>rata-rata per tanggal</strong>. Karena itu keduanya
          <strong>tidak dapat dicocokkan satu sama lain</strong>, dan itu disengaja: tekanan yang anjlok pada satu
          slot tetap terlihat di kartu justru karena rata-rata harinya menyamarkannya.
        </p>

        <!-- Kartu metrik — satu kolom bertumpuk, kesembilan metrik numerik,
             masing-masing dengan PENYEBUTNYA SENDIRI di sebelah angkanya.
             Tidak ada satu pun kelas/ikon/pewarnaan ambang di sini. -->
        <div class="metric-stack" data-testid="metric-cards">
          <div
            v-for="card in METRIC_CARDS"
            :key="card.key"
            class="metric-card"
            :data-testid="`metric-${card.testid}`"
          >
            <span class="metric-label">{{ card.label }}</span>
            <div class="metric-figure">
              <span class="metric-value" :data-testid="`metric-${card.testid}-avg`">
                {{ formatNumber(metricOf(card.key)?.avg, card.digits) }}
              </span>
              <span v-if="card.unit" class="metric-unit">{{ card.unit }} rata-rata</span>
              <span v-else class="metric-unit">rata-rata</span>
            </div>
            <span class="metric-note" :data-testid="`metric-${card.testid}-count`">
              dari {{ formatCount(metricOf(card.key)?.reading_count) }} pembacaan
            </span>
            <span class="metric-note metric-extremes">
              terendah
              <b :data-testid="`metric-${card.testid}-min`">
                {{ formatNumber(metricOf(card.key)?.min, card.digits) }}
              </b>
              &middot; tertinggi
              <b :data-testid="`metric-${card.testid}-max`">
                {{ formatNumber(metricOf(card.key)?.max, card.digits) }}
              </b>
              <small>(pembacaan mentah)</small>
            </span>
          </div>
        </div>

        <!-- ============ PERAWATAN — TIGA KEADAAN, TIGA BARIS ============
             "Tidak tercatat" berdiri sendiri dan TIDAK PERNAH dijumlahkan ke
             "tidak dilakukan": kolom kosong bukan berarti perawatan tidak
             dijalankan, dan kekeliruan itu tidak dapat dibatalkan setelah
             angkanya dibaca. -->
        <section v-if="maintenance" class="detail-section" data-testid="maintenance-card">
          <h2 class="section-title">Perawatan Boiler</h2>
          <p class="section-note">
            Tiap pembacaan berakhir pada salah satu dari tiga keadaan. &ldquo;Tidak tercatat&rdquo; berdiri
            sendiri — ia bukan &ldquo;tidak dilakukan&rdquo;.
          </p>
          <div class="detail-table-wrap">
            <table class="detail-table">
              <thead>
                <tr>
                  <th>Keadaan</th>
                  <th class="num">Blowdown</th>
                  <th class="num">Sootblowing</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>Dilakukan</td>
                  <td class="num" data-testid="maintenance-blowdown-executed">
                    {{ formatCount(maintenance.blowdown?.executed) }}
                  </td>
                  <td class="num" data-testid="maintenance-sootblowing-executed">
                    {{ formatCount(maintenance.sootblowing?.executed) }}
                  </td>
                </tr>
                <tr>
                  <td>Tidak dilakukan</td>
                  <td class="num" data-testid="maintenance-blowdown-not-executed">
                    {{ formatCount(maintenance.blowdown?.not_executed) }}
                  </td>
                  <td class="num" data-testid="maintenance-sootblowing-not-executed">
                    {{ formatCount(maintenance.sootblowing?.not_executed) }}
                  </td>
                </tr>
                <tr>
                  <td>Tidak tercatat</td>
                  <td class="num" data-testid="maintenance-blowdown-not-recorded">
                    {{ formatCount(maintenance.blowdown?.not_recorded) }}
                  </td>
                  <td class="num" data-testid="maintenance-sootblowing-not-recorded">
                    {{ formatCount(maintenance.sootblowing?.not_recorded) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <span class="metric-note" data-testid="maintenance-avg-per-day">
            Blowdown dilakukan rata-rata {{ formatNumber(maintenance.blowdown?.avg_per_day, 1) }} kali/hari
            &middot; sootblowing {{ formatNumber(maintenance.sootblowing?.avg_per_day, 1) }} kali/hari
          </span>

          <!-- Nol yang berarti "tidak pernah dicatat" dibedakan dari nol yang
               berarti "tidak pernah dilakukan". Penandanya datang dari server
               (all_unrecorded), tidak diturunkan di sini. -->
          <p
            v-if="maintenance.blowdown?.all_unrecorded"
            class="metric-note metric-note--strong"
            data-testid="maintenance-blowdown-all-unrecorded"
          >
            Seluruh kolom blowdown periode ini TIDAK TERCATAT. Angka 0 di atas berarti tidak ada catatannya,
            bukan bahwa blowdown tidak pernah dilakukan.
          </p>
          <p
            v-if="maintenance.sootblowing?.all_unrecorded"
            class="metric-note metric-note--strong"
            data-testid="maintenance-sootblowing-all-unrecorded"
          >
            Seluruh kolom sootblowing periode ini TIDAK TERCATAT. Angka 0 di atas berarti tidak ada catatannya,
            bukan bahwa sootblowing tidak pernah dilakukan.
          </p>
        </section>

        <!-- Tren harian tekanan uap — menggulir DI DALAM kartu ini. -->
        <section
          v-if="showCharts && daily.length > 0"
          class="detail-section"
          data-testid="daily-trend-steam-pressure"
        >
          <h2 class="section-title">Tren Harian Tekanan Uap</h2>
          <p class="section-note">
            Rata-rata per tanggal, dalam bar. Geser mendatar untuk melihat seluruh tanggal.
          </p>
          <div class="chart-scroll">
            <div class="chart-bars">
              <div v-for="row in daily" :key="`sp-${row.date}`" class="chart-col">
                <span class="chart-value">{{ formatNumber(row.steam_pressure_avg, 1) }}</span>
                <div class="chart-track">
                  <div
                    class="chart-fill"
                    :style="{ height: barHeight(row.steam_pressure_avg, 'steam_pressure_avg') }"
                  ></div>
                </div>
                <span class="chart-axis">{{ formatDayAxis(row.date) }}</span>
              </div>
            </div>
          </div>
          <p class="section-note">
            Sumbu tegak tidak dimulai dari nol — batang membandingkan tanggal, bukan besaran mutlak.
          </p>
        </section>

        <!-- Tren harian suhu uap — menggulir DI DALAM kartu ini. -->
        <section
          v-if="showCharts && daily.length > 0"
          class="detail-section"
          data-testid="daily-trend-steam-temp"
        >
          <h2 class="section-title">Tren Harian Suhu Uap</h2>
          <p class="section-note">
            Rata-rata per tanggal, dalam &deg;C. Geser mendatar untuk melihat seluruh tanggal.
          </p>
          <div class="chart-scroll">
            <div class="chart-bars">
              <div v-for="row in daily" :key="`st-${row.date}`" class="chart-col">
                <span class="chart-value">{{ formatNumber(row.steam_temp_avg, 1) }}</span>
                <div class="chart-track">
                  <div class="chart-fill" :style="{ height: barHeight(row.steam_temp_avg, 'steam_temp_avg') }"></div>
                </div>
                <span class="chart-axis">{{ formatDayAxis(row.date) }}</span>
              </div>
            </div>
          </div>
          <p class="section-note">
            Sumbu tegak tidak dimulai dari nol — batang membandingkan tanggal, bukan besaran mutlak.
          </p>
        </section>

        <!-- Rekap per unit boiler — SELURUH unit, termasuk yang jumlah
             pembacaannya nol: unit yang tidak pernah terisi adalah temuan,
             bukan baris yang layak dibuang. Tabelnya menggulir di dalam
             kartunya sendiri, halaman tetap satu kolom. -->
        <section v-if="byUnit.length > 0" class="detail-section" data-testid="by-unit-card">
          <h2 class="section-title">Rekap per Unit Boiler</h2>
          <p class="section-note">
            Angka periode di atas menggabungkan seluruh unit — tabel ini menunjukkan sebarannya. Geser mendatar
            untuk melihat seluruh kolom.
          </p>
          <div class="detail-table-wrap">
            <table class="detail-table" data-testid="by-unit-table">
              <thead>
                <tr>
                  <th>Unit</th>
                  <th class="num">Tekanan (bar)</th>
                  <th class="num">Suhu Uap (&deg;C)</th>
                  <th class="num">TDS (ppm)</th>
                  <th class="num">pH</th>
                  <th class="num">Blowdown</th>
                  <th class="num">Sootblowing</th>
                  <th class="num">Jumlah pembacaan</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="unit in byUnit" :key="`unit-${unit.boiler_room_id}`" data-testid="by-unit-row">
                  <td>{{ unit.boiler_room_id || NOT_AVAILABLE }}</td>
                  <td class="num">{{ formatNumber(unit.steam_pressure_avg, 1) }}</td>
                  <td class="num">{{ formatNumber(unit.steam_temp_avg, 1) }}</td>
                  <td class="num">{{ formatNumber(unit.water_tds_avg, 0) }}</td>
                  <td class="num">{{ formatNumber(unit.water_ph_avg, 1) }}</td>
                  <td class="num">{{ formatCount(unit.blowdown_executed) }}</td>
                  <td class="num">{{ formatCount(unit.sootblowing_executed) }}</td>
                  <td class="num">{{ formatCount(unit.reading_count) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Rekap harian — panjang pada periode sebulan, jadi TERTUTUP secara
             bawaan. Membuka atau menutupnya murni penyingkapan atas `daily`
             yang sudah ada di memori: NOL permintaan jaringan baru. -->
        <section v-if="daily.length > 0" class="detail-section" data-testid="daily-recap">
          <button
            type="button"
            class="recap-toggle"
            :aria-expanded="recapOpen"
            data-testid="daily-recap-toggle"
            @click="toggleRecap"
          >
            <span class="section-title">Rekap Harian ({{ formatCount(daily.length) }} hari)</span>
            <span class="recap-toggle-hint">{{ recapOpen ? 'Tutup' : 'Buka' }}</span>
          </button>

          <template v-if="recapOpen">
            <p class="section-note">
              Nilai tiap kolom adalah <strong>rata-rata harian</strong> — bukan pembacaan mentah, dan karena itu
              tidak dapat dicocokkan dengan terendah/tertinggi pada kartu di atas. Geser mendatar untuk melihat
              seluruh kolom.
            </p>
            <div class="detail-table-wrap">
              <table class="detail-table">
                <thead>
                  <tr>
                    <th>Tanggal</th>
                    <th class="num">Slot Terisi</th>
                    <th class="num">Tekanan (bar)</th>
                    <th class="num">Suhu Uap (&deg;C)</th>
                    <th class="num">TDS (ppm)</th>
                    <th class="num">pH</th>
                    <th class="num">Gas Buang (&deg;C)</th>
                    <th class="num">Blowdown</th>
                    <th class="num">Sootblowing</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Seluruh baris dari server, dalam urutannya, TERMASUK
                       baris yang seluruh rata-ratanya null: hari itu tetap
                       hari yang ber-record, dan membuangnya akan membuat
                       periodenya terlihat lebih terisi daripada kenyataannya. -->
                  <tr v-for="row in daily" :key="`recap-${row.date}`" data-testid="daily-recap-row">
                    <td>{{ formatDate(row.date) }}</td>
                    <td class="num">{{ formatCount(row.filled_slots) }}</td>
                    <td class="num">{{ formatNumber(row.steam_pressure_avg, 1) }}</td>
                    <td class="num">{{ formatNumber(row.steam_temp_avg, 1) }}</td>
                    <td class="num">{{ formatNumber(row.water_tds_avg, 0) }}</td>
                    <td class="num">{{ formatNumber(row.water_ph_avg, 1) }}</td>
                    <td class="num">{{ formatNumber(row.exhaust_gas_temp_avg, 0) }}</td>
                    <td class="num">{{ formatCount(row.blowdown_executed) }}</td>
                    <td class="num">{{ formatCount(row.sootblowing_executed) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-if="total" class="section-note" data-testid="daily-recap-total">
              Total periode {{ formatCount(total.reading_rows) }} pembacaan &middot;
              {{ formatCount(total.days_with_records) }} hari ber-record.
            </p>
          </template>
        </section>
      </template>

      <footer class="action-footer">
        <!-- Ekspor TIDAK PERNAH dinonaktifkan oleh status periode: kunci
             periode mengatur penulisan data, bukan pembacaan laporan. -->
        <button
          v-if="selectedPeriodId && selectedProductionLineId"
          type="button"
          class="action-button action-button--primary"
          data-testid="export-button"
          :aria-busy="exporting"
          @click="onExport"
        >
          Ekspor CSV
        </button>
        <button type="button" class="action-button action-button--secondary" data-testid="back-button" @click="onBack">
          Back
        </button>
      </footer>
    </template>
  </main>
</template>

<style scoped>
.laporan-br-view {
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

.filter-bar { display: flex; flex-direction: column; gap: 12px; }
.filter-field { display: flex; flex-direction: column; gap: 4px; font-size: 13px; color: #6b7280; }
/* Sasaran sentuh minimal 44px — berlaku untuk SETIAP kendali di layar ini. */
.filter-field select { min-height: 44px; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; font-family: inherit; background: #ffffff; }
.mill-current { margin: 0; font-size: 13px; color: #6b7280; }
.mill-current strong { color: #1f2937; }

.period-meta { display: flex; flex-direction: column; gap: 6px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; }
.period-meta-name { font-size: 14px; font-weight: 600; color: #1f2937; }
.period-meta-line { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; font-size: 13px; color: #6b7280; }
.period-meta-note { font-size: 12px; color: #6b7280; }

.status-text { margin: 0; font-size: 14px; color: #6b7280; }
.notice { margin: 0; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; color: #6b7280; font-size: 14px; }
.notice--warning { border-color: #fcd34d; background: #fffbeb; color: #92400e; }
.notice--error { display: flex; flex-direction: column; gap: 10px; border-color: #fecaca; background: #fef2f2; color: #b91c1c; }
/*
 * Netral dan informatif — SENGAJA bukan warna peringatan. Blok ini
 * menjelaskan cara membaca angka, bukan menandai nilai di luar batas; lihat
 * catatan 5 pada docblock.
 */
.notice--info { border-color: #e5e7eb; background: #f9fafb; color: #4b5563; font-size: 13px; line-height: 1.5; }
.notice-text { margin: 0; }
.notice--stack { display: flex; flex-direction: column; align-items: flex-start; gap: 10px; }

.metric-stack { display: flex; flex-direction: column; gap: 10px; }
.metric-card { display: flex; flex-direction: column; gap: 4px; padding: 14px; border: 1px solid #e5e7eb; border-radius: 8px; }
.metric-label { font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
.metric-figure { display: flex; align-items: baseline; gap: 6px; flex-wrap: wrap; }
.metric-value { font-size: 26px; font-weight: 700; color: #1f2937; }
.metric-unit { font-size: 13px; color: #6b7280; }
.metric-note { font-size: 12px; color: #6b7280; }
.metric-note--strong { margin: 0; font-weight: 600; color: #4b5563; }
.metric-extremes b { color: #1f2937; font-weight: 600; }
.metric-extremes small { display: block; font-size: 11px; color: #9ca3af; }

.detail-section { display: flex; flex-direction: column; gap: 10px; padding: 14px; border: 1px solid #e5e7eb; border-radius: 8px; }
.coverage-card { border-color: #249360; background: #f3fbf7; }
.section-title { margin: 0; font-size: 16px; font-weight: 700; color: #1f2937; }
.section-note { margin: 0; font-size: 12px; color: #6b7280; line-height: 1.5; }

.recap-toggle { display: flex; align-items: center; justify-content: space-between; gap: 8px; min-height: 44px; padding: 0; border: none; background: transparent; font-family: inherit; text-align: left; cursor: pointer; }
.recap-toggle-hint { font-size: 12px; font-weight: 600; color: #249360; }

/* Grafik batang: guliran mendatar DI DALAM kartu, bukan pada halaman. */
.chart-scroll { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.chart-bars { display: flex; align-items: flex-end; gap: 6px; min-width: min-content; padding-bottom: 4px; }
.chart-col { display: flex; flex-direction: column; align-items: center; gap: 4px; flex: none; width: 34px; }
.chart-value { font-size: 10px; color: #6b7280; }
.chart-track { display: flex; align-items: flex-end; width: 100%; height: 96px; border-radius: 4px; background: #f3f4f6; overflow: hidden; }
/* Satu warna untuk seluruh batang — tidak ada pewarnaan aman/bahaya. */
.chart-fill { width: 100%; border-radius: 4px 4px 0 0; background: #249360; }
.chart-axis { font-size: 10px; color: #6b7280; }

/* Tabel: guliran mendatar DI DALAM kartu, bukan pada halaman. */
.detail-table-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.detail-table { border-collapse: collapse; width: 100%; font-size: 13px; }
.detail-table th, .detail-table td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
.detail-table th.num, .detail-table td.num { text-align: right; }

.action-footer { display: flex; gap: 10px; margin-top: auto; }
.action-button { min-height: 44px; min-width: 44px; padding: 0 16px; border-radius: 8px; font-size: 14px; font-weight: 600; font-family: inherit; cursor: pointer; box-sizing: border-box; }
.action-button--primary { border: 1px solid #249360; background: #249360; color: #ffffff; }
.action-button--secondary { border: 1px solid #e5e7eb; background: #ffffff; color: #1f2937; }
</style>
