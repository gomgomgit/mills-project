<script setup lang="ts">
/**
 * LaporanClarificationView — screen-138--laporan-clarification-mobile /
 * usecase-138--laporan-clarification-mobile "Lihat Laporan Periode
 * Clarification (Mobile)" (dipasang di /reports/clarification,
 * meta.public = false). Actors: operator, supervisor, mill_management,
 * admin.
 *
 * Saudara LaporanSterilizerView (screen-135), LaporanCagesTrackView
 * (screen-136), dan LaporanBoilerRoomView (screen-137) — struktur,
 * penanganan galat, dan kosakata data-testid-nya sengaja dibuat sedekat
 * mungkin, karena keempatnya memecahkan masalah yang sama dan perbedaan
 * gaya di antaranya hanya akan menjadi beban pembaca berikutnya.
 *
 * SUSUNANNYA SEJAJAR DENGAN LAYAR WEB screen-132 — tujuh kartu angka yang
 * sama (kelengkapan, produksi, downtime, laju produksi, lalu keempat
 * metrik: suhu clarification, suhu minyak, suhu sludge, level buffer
 * tank), istilah yang sama, dan urutan yang sama. Kalau kedua layar
 * menampilkan jumlah kartu yang berbeda, itu sendiri sudah kebohongan
 * tentang produknya. (Bandingkan screen-137, yang tercatat sebagai
 * known_issue justru karena ponselnya menampilkan 9 kartu sementara
 * webnya 5.)
 *
 * Yang BERBEDA dari Boiler Room bukan gayanya melainkan isinya: Boiler
 * Room melaporkan KONDISI ("seberapa stabil"), Clarification melaporkan
 * kondisi DAN satu angka yang dipakai menilai seluruh mill — produksi —
 * kecuali bahwa produksi itu TIDAK DICATAT DI MANA PUN. Itu mengubah lima
 * hal di bawah.
 *
 * ────────────────────────────────────────────────────────────────────────
 * 1. PRODUKSI DITURUNKAN, BUKAN DICATAT — DAN PENYEBUTNYA IKUT TAMPIL
 * ────────────────────────────────────────────────────────────────────────
 * `clarification_details` tidak punya kolom produksi sama sekali. Yang ada
 * adalah laju dalam ton/JAM, dan kisi jam kanonis membuat satu pembacaan
 * berlaku untuk satu jam, sehingga production.total_ton = SUM(laju) yang
 * dihitung SERVER. Karena DITURUNKAN, jumlah pembacaan laju dirender
 * berdampingan dengan angkanya: produksi dari 40 pembacaan dan dari 400
 * pembacaan tidak boleh terlihat sama meyakinkan.
 *
 * ────────────────────────────────────────────────────────────────────────
 * 2. JAM TANPA CATATAN LAJU BUKAN JAM BERPRODUKSI NOL
 * ────────────────────────────────────────────────────────────────────────
 * Perangkap paling berbahaya di layar ini, karena kedua pembacaan
 * menghasilkan TOTAL YANG SAMA — hanya rata-ratanya yang berbeda. Layar
 * ini tidak menghitung apa pun sendiri, jadi perangkapnya sudah ditutup di
 * server; yang menjadi tugas layar adalah MENGATAKANNYA, lewat kartu
 * kelengkapan yang dirender di ATAS segalanya dan lewat penyebut di tiap
 * kartu.
 *
 * ────────────────────────────────────────────────────────────────────────
 * 3. DOWNTIME ADALAH KONTEKS, BUKAN PENGURANG PRODUKSI
 * ────────────────────────────────────────────────────────────────────────
 * Diputuskan 2026-09-25 dan sudah final: laju yang diinput Operator dibaca
 * sebagai laju RATA-RATA sepanjang jam itu, sehingga downtime sudah
 * tercermin di dalamnya dan mengurangkannya lagi menghitung ganda. Kartu
 * produksi merender production.total_ton apa adanya; kartu downtime
 * berdiri sendiri di sebelahnya, dan alasannya ditulis di layar
 * ([data-testid="downtime-production-note"]) supaya tidak "diperbaiki"
 * kelak oleh pembaca yang mengira ada yang terlupa.
 *
 * Downtime juga punya DUA KEADAAN NOL: 0 dengan reading_count > 0 berarti
 * stasiun tidak pernah berhenti; null dengan reading_count 0 berarti tidak
 * pernah tercatat. Yang kedua dirender "tidak tercatat", bukan 0.
 *
 * ────────────────────────────────────────────────────────────────────────
 * 4. KETIGA SUHU TANGKI PADA SATU GRAFIK, DENGAN SATU SUMBU
 * ────────────────────────────────────────────────────────────────────────
 * Yang dibaca adalah SELISIH antar tangki — itulah tanda proses pemisahan
 * berjalan sebagaimana mestinya, bukan nilai masing-masing tangki secara
 * terpisah. Tiga grafik terpisah akan memenuhi kalimat "tren suhu antar
 * tangki" secara harfiah sambil menghancurkan maksudnya. Karena itu
 * ketiganya adalah tiga seri pada SATU elemen
 * [data-testid="tank-temperature-chart"], dengan legenda yang menamai
 * ketiganya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * 5. TIDAK ADA PENANDAAN AMBANG DI MANA PUN
 * ────────────────────────────────────────────────────────────────────────
 * Tidak ada kelas aman/bahaya, tidak ada ikon peringatan, tidak ada
 * pewarnaan bersyarat atas nilai metrik. Clarification tidak punya master
 * target operasional (tidak ada ClarificationOperationalTarget), dan
 * menurunkan ambang dari data periode itu sendiri akan menghasilkan angka
 * yang TERLIHAT seperti batas proses padahal hanya statistik tentang data
 * yang sedang dinilainya. Paragraf ini ada supaya kekosongan itu tidak
 * "dilengkapi" kelak.
 *
 * ────────────────────────────────────────────────────────────────────────
 * DAN TIGA HAL YANG SAMA PERSIS DENGAN screen-135 / 136 / 137
 * ────────────────────────────────────────────────────────────────────────
 * a. NOL PERHITUNGAN ULANG DI KLIEN. Setiap angka dipetakan apa adanya
 *    dari /api/clarification-reports/summary — endpoint yang sama persis
 *    dengan laporan web (screen-132), sehingga kedua layar mustahil
 *    berbeda. Satu-satunya angka turunan adalah TINGGI BATANG grafik:
 *    skala visual murni, tidak pernah dibacakan sebagai angka kepada
 *    pengguna.
 * b. NULL BUKAN NOL, DAN ITU TERLIHAT DI LAYAR. Metrik yang tidak pernah
 *    diisi dirender "-" dengan 0 pembacaan, TIDAK PERNAH 0,0.
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
 * (LaporanCagesTrackView dan LaporanSterilizerView dulu masih membaca
 * `candidate?.response?.status`; cacat itu dibereskan 2026-09-28 sehingga
 * kelima layar laporan kini seragam.)
 *
 * SATU KOLOM, TANPA GULIRAN MENDATAR PADA HALAMAN. Yang lebar bukan
 * halamannya melainkan isi kartunya: kedua grafik tren, tabel rekap per
 * unit, dan tabel rekap harian masing-masing menggulir DI DALAM kartunya
 * sendiri (.chart-scroll / .detail-table-wrap), sementara halaman tetap
 * overflow-x: hidden.
 */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { productionLineRepo, type ProductionLineOption } from '@/services/productionLineRepo'
import { useFloatingClockStore } from '@/stores/floatingClock'
import { useAiAssistantStore } from '@/stores/aiAssistant'
import StatusBadge from '@/components/StatusBadge.vue'
import clarificationReportRepo, {
  type ClarificationReportBusinessUnitOption,
  type ClarificationReportDailyRow,
  type ClarificationReportMetric,
  type ClarificationReportMetricKey,
  type ClarificationReportPeriodOption,
  type ClarificationReportSummary,
} from '@/services/clarificationReportRepo'

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

const businessUnits = ref<ClarificationReportBusinessUnitOption[]>([])

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

const periods = ref<ClarificationReportPeriodOption[]>([])
const selectedPeriodId = ref<string | null>(null)
const summary = ref<ClarificationReportSummary | null>(null)

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
    businessUnits.value = await clarificationReportRepo.fetchBusinessUnits()
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
    periods.value = await clarificationReportRepo.fetchPeriods(scope.value)
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
    summary.value = await clarificationReportRepo.fetchSummary(periodId, scope.value)
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

  return `laporan-clarification_${slug || 'periode'}.csv`
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
    // record diulang, kolom findings verbatim). Layar ini tidak pernah
    // menyusun berkasnya dari angka yang sedang tampil.
    const blob = await clarificationReportRepo.exportCsv(periodId, scope.value)
    clarificationReportRepo.saveCsvFile(blob, exportFilename())
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
const production = computed(() => summary.value?.production ?? null)
const downtime = computed(() => summary.value?.downtime ?? null)
const metrics = computed(() => summary.value?.metrics ?? null)
const daily = computed<ClarificationReportDailyRow[]>(() => summary.value?.daily ?? [])
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
 * DOWNTIME PUNYA DUA KEADAAN NOL. reading_count 0 berarti downtime tidak
 * pernah tercatat sepanjang periode — dirender sebagai kata, bukan angka.
 * reading_count > 0 dengan total_mins 0 berarti stasiun tidak pernah
 * berhenti, dan itu angka 0 yang sungguh-sungguh diukur.
 */
const downtimeUnrecorded = computed(() => (downtime.value?.reading_count ?? 0) === 0)

/**
 * Keempat kartu metrik, dalam urutan dan dengan label yang SAMA PERSIS
 * dengan laporan web screen-132 (blade `md-kpis--4`), supaya pembaca yang
 * berpindah antar kedua layar menemukan kata yang sama.
 *
 * pure_oil_production_rate_ton_hour dan downtime_mins sengaja TIDAK ada di
 * daftar ini walaupun keduanya ada pada `metrics`: keduanya sudah punya
 * kartunya sendiri di atas (kartu Laju Produksi dan kartu Total Downtime),
 * dan merendernya dua kali akan membuat satu angka terbaca sebagai dua
 * temuan yang berbeda.
 */
const METRIC_CARDS: Array<{
  key: ClarificationReportMetricKey
  testid: string
  label: string
  unit: string
  digits: number
}> = [
  {
    key: 'clarification_tank_temp_c',
    testid: 'clarification-temp',
    label: 'Suhu Tangki Clarification',
    unit: '°C',
    digits: 1,
  },
  {
    key: 'oil_tank_temperature_c',
    testid: 'oil-temp',
    label: 'Suhu Tangki Minyak',
    unit: '°C',
    digits: 1,
  },
  {
    key: 'sludge_tank_temp_c',
    testid: 'sludge-temp',
    label: 'Suhu Tangki Sludge',
    unit: '°C',
    digits: 1,
  },
  {
    key: 'buffer_tank_level_percent',
    testid: 'buffer-level',
    label: 'Level Buffer Tank',
    unit: '%',
    digits: 1,
  },
]

function metricOf(key: ClarificationReportMetricKey): ClarificationReportMetric | null {
  return metrics.value?.[key] ?? null
}

/**
 * Ketiga seri suhu tangki — SATU grafik, satu sumbu. Urutan dan warnanya
 * tetap, supaya legendanya dapat dibaca tanpa menghitung.
 */
const TANK_SERIES: Array<{
  column: 'clarification_tank_temp_avg' | 'oil_tank_temperature_avg' | 'sludge_tank_temp_avg'
  testid: string
  label: string
}> = [
  { column: 'clarification_tank_temp_avg', testid: 'clarification', label: 'Tangki Clarification' },
  { column: 'oil_tank_temperature_avg', testid: 'oil', label: 'Tangki Minyak' },
  { column: 'sludge_tank_temp_avg', testid: 'sludge', label: 'Tangki Sludge' },
]

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
type ChartColumn =
  | 'production_ton'
  | 'clarification_tank_temp_avg'
  | 'oil_tank_temperature_avg'
  | 'sludge_tank_temp_avg'

/**
 * SKALA BERSAMA untuk ketiga suhu tangki. Dihitung atas gabungan ketiga
 * kolom, bukan per kolom: kalau tiap seri diskalakan sendiri-sendiri, tiga
 * tangki yang berselisih 10 °C akan tergambar sama tinggi dan selisih
 * itulah — satu-satunya hal yang dibaca dari grafik ini — yang hilang.
 */
function valuesOfColumns(columns: ChartColumn[]): number[] {
  const values: number[] = []

  for (const row of daily.value) {
    for (const column of columns) {
      const value = row[column]

      if (value !== null && value !== undefined) {
        values.push(value)
      }
    }
  }

  return values
}

function barHeight(value: number | null | undefined, columns: ChartColumn[]): string {
  if (value === null || value === undefined) {
    return '0%'
  }

  const values = valuesOfColumns(columns)

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

const TANK_COLUMNS: ChartColumn[] = [
  'clarification_tank_temp_avg',
  'oil_tank_temperature_avg',
  'sludge_tank_temp_avg',
]

/**
 * Apakah ada satu pun nilai suhu sepanjang periode. Grafik suhu tidak
 * digambar bila tidak ada: grafik kosong terbaca sebagai garis datar hasil
 * pengukuran.
 */
const hasTankTemperatures = computed(() => valuesOfColumns(TANK_COLUMNS).length > 0)

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

/** Titik ribuan, tanpa desimal — untuk cacah (slot, pembacaan, menit). */
function formatCount(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return NOT_AVAILABLE
  }

  return new Intl.NumberFormat('id-ID').format(value)
}

/**
 * Bilangan berdesimal. Jumlah desimalnya adalah keputusan TAMPILAN per
 * metrik (produksi 1 desimal, laju 2) — nilainya sendiri datang sudah
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

function periodOptionLabel(period: ClarificationReportPeriodOption): string {
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
  <main class="laporan-clf-view" data-testid="laporan-clarification-mobile">
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
        <span aria-current="page">Laporan Clarification</span>
      </nav>
      <h1 class="screen-title">Laporan Clarification</h1>
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
        Mill ini belum memiliki periode pelaporan yang mencakup stasiun Clarification.
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
          Belum ada data pembacaan Clarification pada periode ini. Seluruh angka di bawah ditampilkan sebagai
          &ldquo;{{ NOT_AVAILABLE }}&rdquo; — belum diukur, bukan bernilai nol.
        </p>

        <!-- ============ KELENGKAPAN PENCATATAN — DI ATAS SEGALANYA ============
             Di layar ini kartu ini menopang beban, bukan sekadar memberi
             keterangan: produksi DITURUNKAN dari pembacaan yang ada,
             sehingga pencatatan yang bolong langsung menurunkan angka
             produksinya. coverage_percent datang dari server apa adanya. -->
        <section v-if="coverage" class="detail-section coverage-card" data-testid="coverage-card">
          <h2 class="section-title">Kelengkapan Pencatatan</h2>
          <p class="section-note">Baca ini lebih dulu — angka produksi di bawah bersandar padanya.</p>
          <div class="metric-figure">
            <span class="metric-value" data-testid="coverage-percent">
              {{ formatNumber(coverage.coverage_percent, 1) }}%
            </span>
          </div>
          <span class="metric-note" data-testid="coverage-slots">
            {{ formatCount(coverage.filled_slots) }} dari {{ formatCount(coverage.expected_slots) }} slot waktu terisi
          </span>
          <span class="metric-note" data-testid="coverage-formula">
            Slot yang diharapkan = {{ formatCount(coverage.unit_count) }} unit clarification &times;
            {{ formatCount(coverage.days_in_period) }} hari &times;
            {{ formatCount(coverage.slots_per_unit_per_day) }} slot.
          </span>
          <span class="metric-note">
            Produksi di bawah DITURUNKAN dari laju yang tercatat, bukan dari periode penuh — jam yang tidak
            mencatat laju tidak diperlakukan sebagai jam berproduksi nol, melainkan tidak ikut dihitung sama
            sekali. Karena itu tiap metrik membawa jumlah pembacaannya sendiri, dan jumlah itu berbeda-beda
            antar metrik.
          </span>
        </section>

        <!-- ============ PRODUKSI / DOWNTIME / LAJU ============
             Ketiganya adalah tiga kartu terpisah, sama seperti laporan web
             screen-132. Produksi dan downtime BERDAMPINGAN dan tidak pernah
             saling mengurangi — lihat catatan 3. -->
        <section class="detail-section" data-testid="production-card">
          <span class="metric-label">Produksi Minyak Murni</span>
          <div class="metric-figure">
            <span class="metric-value" data-testid="production-total">
              {{ formatNumber(production?.total_ton, 1) }}
            </span>
            <span class="metric-unit">ton sepanjang periode</span>
          </div>
          <!-- Penyebutnya WAJIB tampil: produksi ini diturunkan, dan
               produksi dari 40 pembacaan tidak boleh terlihat sama
               meyakinkan dengan produksi dari 400. -->
          <span class="metric-note" data-testid="production-reading-count">
            dari {{ formatCount(production?.reading_count) }} pembacaan laju
          </span>
          <span class="metric-note" data-testid="production-avg-per-day">
            rata-rata {{ formatNumber(production?.avg_per_day_ton, 1) }} ton/hari ber-record
          </span>
          <p class="section-note" data-testid="production-derived-note">
            Tidak ada kolom produksi yang diisi Operator. Angka ini DITURUNKAN dengan menjumlahkan laju per jam
            yang tercatat, karena satu pembacaan berlaku untuk satu jam. Jam yang laju-nya tidak tercatat
            dikeluarkan dari perhitungan — bukan dianggap berproduksi nol.
          </p>
        </section>

        <section class="detail-section" data-testid="downtime-card">
          <span class="metric-label">Total Downtime</span>
          <div class="metric-figure">
            <!-- DUA KEADAAN NOL. reading_count 0 berarti tidak pernah
                 tercatat, dan itu dirender sebagai kata — menampilkannya
                 sebagai 0 akan menerbitkan angka keandalan yang tidak
                 pernah diukur. -->
            <span class="metric-value" data-testid="downtime-total">
              {{ downtimeUnrecorded ? 'tidak tercatat' : formatCount(downtime?.total_mins) }}
            </span>
            <span v-if="!downtimeUnrecorded" class="metric-unit">menit</span>
          </div>
          <span class="metric-note" data-testid="downtime-reading-count">
            dari {{ formatCount(downtime?.reading_count) }} pembacaan
          </span>
          <span class="metric-note" data-testid="downtime-hours">
            tercatat pada {{ formatCount(downtime?.hours_with_downtime) }} jam &middot; rata-rata
            {{ formatNumber(downtime?.avg_per_day_mins, 1) }} menit/hari
          </span>
          <p class="section-note" data-testid="downtime-production-note">
            Downtime TIDAK dikurangkan dari produksi di atas. Laju yang diinput Operator adalah laju rata-rata
            sepanjang jam itu, sehingga downtime sudah tercermin di dalamnya — mengurangkannya lagi akan
            menghitung ganda. Angka ini berdiri di sini sebagai konteks.
          </p>
        </section>

        <section class="detail-section" data-testid="production-rate-card">
          <span class="metric-label">Laju Produksi</span>
          <div class="metric-figure">
            <span class="metric-value" data-testid="production-rate-avg">
              {{ formatNumber(production?.avg_rate_ton_hour, 2) }}
            </span>
            <span class="metric-unit">ton/jam rata-rata</span>
          </div>
          <span class="metric-note metric-extremes">
            terendah <b data-testid="production-rate-min">{{ formatNumber(production?.min_rate_ton_hour, 2) }}</b>
            &middot; tertinggi <b data-testid="production-rate-max">{{ formatNumber(production?.max_rate_ton_hour, 2) }}</b>
          </span>
          <span class="metric-note" data-testid="production-rate-reading-count">
            dari {{ formatCount(production?.reading_count) }} pembacaan laju
          </span>
        </section>

        <!-- ============ LABEL WAJIB: EKSTREM MENTAH vs RATA-RATA HARIAN ============
             Tanpa blok ini pembaca yang mencoba mencocokkan "terendah" pada
             kartu dengan kolom rata-rata pada rekap akan menyimpulkan
             laporannya rusak. -->
        <p class="notice notice--info" data-testid="raw-extremes-note">
          Nilai <strong>terendah</strong> dan <strong>tertinggi</strong> pada kartu di atas dan di bawah diambil
          dari <strong>pembacaan mentah per slot waktu</strong>, sedangkan kolom rata-rata pada tren harian dan
          Rekap Harian adalah <strong>rata-rata per tanggal</strong>. Karena itu keduanya
          <strong>tidak dapat dicocokkan satu sama lain</strong>, dan itu disengaja.
        </p>

        <!-- Kartu metrik — satu kolom bertumpuk, masing-masing dengan
             PENYEBUTNYA SENDIRI di sebelah angkanya. Satu baris pembacaan
             dapat mengisi suhu sludge dan mengosongkan laju, jadi tidak ada
             satu label jumlah pembacaan yang berlaku untuk semuanya.
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
              <span class="metric-unit">{{ card.unit }} rata-rata</span>
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

        <!-- Tren harian produksi — menggulir DI DALAM kartu ini. -->
        <section
          v-if="showCharts && daily.length > 0"
          class="detail-section"
          data-testid="daily-trend-production"
        >
          <h2 class="section-title">Tren Harian Produksi</h2>
          <p class="section-note">
            Produksi per tanggal, dalam ton, diturunkan dari laju yang tercatat pada tanggal itu. Geser mendatar
            untuk melihat seluruh tanggal.
          </p>
          <div class="chart-scroll">
            <div class="chart-bars">
              <div v-for="row in daily" :key="`prod-${row.date}`" class="chart-col">
                <span class="chart-value">{{ formatNumber(row.production_ton, 1) }}</span>
                <div class="chart-track">
                  <div class="chart-fill" :style="{ height: barHeight(row.production_ton, ['production_ton']) }"></div>
                </div>
                <span class="chart-axis">{{ formatDayAxis(row.date) }}</span>
              </div>
            </div>
          </div>
          <p class="section-note">
            Sumbu tegak tidak dimulai dari nol — batang membandingkan tanggal, bukan besaran mutlak.
          </p>
        </section>

        <!-- ============ TREN SUHU ANTAR TANGKI — SATU GRAFIK ============
             Ketiga suhu adalah tiga SERI pada SATU elemen grafik, dengan
             SATU skala bersama. Yang dibaca adalah selisih antar tangki;
             tiga grafik terpisah — atau tiga skala terpisah — akan
             menghancurkan tepat hal itu. Lihat catatan 4. -->
        <section
          v-if="showCharts && daily.length > 0 && hasTankTemperatures"
          class="detail-section"
          data-testid="tank-temperature-card"
        >
          <h2 class="section-title">Tren Suhu Antar Tangki</h2>
          <p class="section-note">
            Rata-rata per tanggal, dalam &deg;C, pada SATU sumbu bersama — yang dibaca adalah selisih antar
            tangki, bukan nilai masing-masing. Geser mendatar untuk melihat seluruh tanggal.
          </p>
          <div class="chart-legend" data-testid="tank-temperature-legend">
            <span
              v-for="(series, index) in TANK_SERIES"
              :key="series.column"
              class="legend-item"
              :data-testid="`tank-legend-${series.testid}`"
            >
              <i :class="`legend-swatch legend-swatch--${index}`"></i>{{ series.label }}
            </span>
          </div>
          <div class="chart-scroll">
            <div class="chart-bars chart-bars--grouped" data-testid="tank-temperature-chart">
              <div v-for="row in daily" :key="`tank-${row.date}`" class="chart-col chart-col--grouped">
                <div class="chart-group">
                  <div
                    v-for="(series, index) in TANK_SERIES"
                    :key="`${row.date}-${series.column}`"
                    class="chart-track chart-track--thin"
                    :data-testid="`tank-bar-${series.testid}`"
                  >
                    <div
                      :class="`chart-fill chart-fill--${index}`"
                      :style="{ height: barHeight(row[series.column], TANK_COLUMNS) }"
                    ></div>
                  </div>
                </div>
                <span class="chart-axis">{{ formatDayAxis(row.date) }}</span>
              </div>
            </div>
          </div>
          <p class="section-note" data-testid="tank-gap-note">
            Sumbu tegak tidak dimulai dari nol, dan ketiga seri memakai skala yang SAMA — hanya dengan begitu
            selisih antar tangki terbaca langsung dari tingginya.
          </p>
        </section>

        <!-- Rekap per unit Clarification — SELURUH unit, termasuk yang
             jumlah pembacaannya nol: unit yang tidak pernah terisi adalah
             temuan, bukan baris yang layak dibuang. Tabelnya menggulir di
             dalam kartunya sendiri, halaman tetap satu kolom. -->
        <section v-if="byUnit.length > 0" class="detail-section" data-testid="by-unit-card">
          <h2 class="section-title">Rekap per Unit Clarification</h2>
          <p class="section-note">
            Angka periode di atas menggabungkan seluruh unit — tabel ini menunjukkan sebarannya. Geser mendatar
            untuk melihat seluruh kolom.
          </p>
          <div class="detail-table-wrap">
            <table class="detail-table" data-testid="by-unit-table">
              <thead>
                <tr>
                  <th>Unit Clarification</th>
                  <th class="num">Produksi (ton)</th>
                  <th class="num">Laju rata-rata (ton/jam)</th>
                  <th class="num">Suhu Clarification (&deg;C)</th>
                  <th class="num">Suhu Minyak (&deg;C)</th>
                  <th class="num">Suhu Sludge (&deg;C)</th>
                  <th class="num">Downtime (menit)</th>
                  <th class="num">Jumlah pembacaan</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="unit in byUnit" :key="`unit-${unit.clarification_id}`" data-testid="by-unit-row">
                  <td>{{ unit.clarification_id || NOT_AVAILABLE }}</td>
                  <td class="num">{{ formatNumber(unit.production_ton, 1) }}</td>
                  <td class="num">{{ formatNumber(unit.rate_avg, 2) }}</td>
                  <td class="num">{{ formatNumber(unit.clarification_tank_temp_avg, 1) }}</td>
                  <td class="num">{{ formatNumber(unit.oil_tank_temperature_avg, 1) }}</td>
                  <td class="num">{{ formatNumber(unit.sludge_tank_temp_avg, 1) }}</td>
                  <td class="num">{{ formatCount(unit.downtime_mins) }}</td>
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
                    <th class="num">Produksi (ton)</th>
                    <th class="num">Laju rata-rata (ton/jam)</th>
                    <th class="num">Suhu Clarification (&deg;C)</th>
                    <th class="num">Suhu Minyak (&deg;C)</th>
                    <th class="num">Suhu Sludge (&deg;C)</th>
                    <th class="num">Level Buffer Tank (%)</th>
                    <th class="num">Downtime (menit)</th>
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
                    <td class="num">{{ formatNumber(row.production_ton, 1) }}</td>
                    <td class="num">{{ formatNumber(row.rate_avg, 2) }}</td>
                    <td class="num">{{ formatNumber(row.clarification_tank_temp_avg, 1) }}</td>
                    <td class="num">{{ formatNumber(row.oil_tank_temperature_avg, 1) }}</td>
                    <td class="num">{{ formatNumber(row.sludge_tank_temp_avg, 1) }}</td>
                    <td class="num">{{ formatNumber(row.buffer_tank_level_avg, 1) }}</td>
                    <td class="num">{{ formatCount(row.downtime_mins) }}</td>
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
.laporan-clf-view {
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
/* Sasaran sentuh minimal 44px — berlaku untuk SETIAP kendali di layar ini,
   tinggi DAN lebar. Pemilih di sini melebar penuh mengikuti satu kolomnya. */
.filter-field select { min-height: 44px; min-width: 44px; width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; font-family: inherit; background: #ffffff; box-sizing: border-box; }
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
.metric-extremes b { color: #1f2937; font-weight: 600; }
.metric-extremes small { display: block; font-size: 11px; color: #9ca3af; }

.detail-section { display: flex; flex-direction: column; gap: 10px; padding: 14px; border: 1px solid #e5e7eb; border-radius: 8px; }
.coverage-card { border-color: #249360; background: #f3fbf7; }
.section-title { margin: 0; font-size: 16px; font-weight: 700; color: #1f2937; }
.section-note { margin: 0; font-size: 12px; color: #6b7280; line-height: 1.5; }

.recap-toggle { display: flex; align-items: center; justify-content: space-between; gap: 8px; min-height: 44px; min-width: 44px; width: 100%; padding: 0; border: none; background: transparent; font-family: inherit; text-align: left; cursor: pointer; box-sizing: border-box; }
.recap-toggle-hint { font-size: 12px; font-weight: 600; color: #249360; }

/* Grafik batang: guliran mendatar DI DALAM kartu, bukan pada halaman. */
.chart-scroll { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.chart-bars { display: flex; align-items: flex-end; gap: 6px; min-width: min-content; padding-bottom: 4px; }
.chart-col { display: flex; flex-direction: column; align-items: center; gap: 4px; flex: none; width: 34px; }
.chart-col--grouped { width: 40px; }
.chart-group { display: flex; align-items: flex-end; gap: 2px; width: 100%; height: 96px; }
.chart-value { font-size: 10px; color: #6b7280; }
.chart-track { display: flex; align-items: flex-end; width: 100%; height: 96px; border-radius: 4px; background: #f3f4f6; overflow: hidden; }
.chart-track--thin { flex: 1; height: 100%; }
/*
 * Warna membedakan SERI, bukan nilai. Tidak ada pewarnaan aman/bahaya di
 * mana pun pada layar ini — lihat catatan 5.
 */
.chart-fill { width: 100%; border-radius: 4px 4px 0 0; background: #249360; }
.chart-fill--0 { background: #249360; }
.chart-fill--1 { background: #2563eb; }
.chart-fill--2 { background: #b45309; }
.chart-axis { font-size: 10px; color: #6b7280; }

.chart-legend { display: flex; flex-wrap: wrap; gap: 10px; font-size: 12px; color: #6b7280; }
.legend-item { display: inline-flex; align-items: center; gap: 5px; }
.legend-swatch { width: 10px; height: 10px; border-radius: 2px; display: inline-block; }
.legend-swatch--0 { background: #249360; }
.legend-swatch--1 { background: #2563eb; }
.legend-swatch--2 { background: #b45309; }

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
