<script setup lang="ts">
/**
 * LaporanStorageTankView — screen-139--laporan-storage-tank-mobile /
 * usecase-139--laporan-storage-tank-mobile "Lihat Laporan Periode Storage
 * Tank (Mobile)" (dipasang di /reports/storage-tank, meta.public = false).
 * Actors: operator, supervisor, mill_management, admin.
 *
 * Saudara LaporanSterilizerView (screen-135), LaporanCagesTrackView
 * (screen-136), LaporanBoilerRoomView (screen-137), dan
 * LaporanClarificationView (screen-138) — struktur, penanganan galat, dan
 * kosakata data-testid-nya sengaja dibuat sedekat mungkin, karena kelimanya
 * memecahkan masalah yang sama dan perbedaan gaya di antaranya hanya akan
 * menjadi beban pembaca berikutnya.
 *
 * SUSUNANNYA SEJAJAR DENGAN LAYAR WEB screen-133 — jumlah kartu angka yang
 * SAMA (kelengkapan, stok awal, stok akhir, pergerakan bersih, lalu keempat
 * kartu mutu FFA / kadar air / kotoran / DOBI, lalu satu kartu suhu
 * rata-rata), istilah yang sama, dan urutan yang sama, diikuti kedua grafik,
 * rekap per tangki, dan rekap harian. Kalau kedua layar menampilkan jumlah
 * kartu yang berbeda, itu sendiri sudah kebohongan tentang produknya.
 * (Bandingkan screen-137, yang tercatat sebagai known_issue justru karena
 * ponselnya menampilkan 9 kartu sementara webnya 5.)
 *
 * KONSEKUENSINYA, DAN ITU DISENGAJA: calculated_volume_m3,
 * calculated_weight_mt, dan ketiga suhu posisi
 * (oil_temperature_top/middle/bottom_c) TIDAK mendapat kartu metriknya
 * sendiri di sini — persis seperti pada laporan web. calculated_weight_mt
 * sudah punya tempatnya sebagai angka stok di atas, dan merendernya lagi
 * sebagai kartu metrik akan membuat satu angka terbaca sebagai dua temuan
 * yang berbeda. Ketiga suhu posisi sengaja tidak ditampilkan berdampingan
 * dengan kartu Suhu Rata-rata agar tidak terbaca sebagai pembandingnya —
 * alasan yang sama yang ditulis laporan web di kartunya sendiri. Seluruh
 * kesepuluh metrik tetap diterima repo apa adanya; yang dipilih di sini
 * hanyalah apa yang DIRENDER.
 *
 * Yang BERBEDA dari empat laporan sebelumnya bukan gayanya melainkan
 * pertanyaannya. Keempatnya MERINGKAS PERISTIWA. Layar ini menjawab
 * pertanyaan tentang KEADAAN DAN PERUBAHANNYA, dan jawaban itu adalah
 * PERBANDINGAN DUA PEMBACAAN. Empat hal di bawah lahir dari perbedaan itu.
 *
 * ────────────────────────────────────────────────────────────────────────
 * 1. PERGERAKAN BUKAN SELISIH STOK GABUNGAN
 * ────────────────────────────────────────────────────────────────────────
 * stock.movement_mt dihitung SERVER per tangki lalu dijumlahkan.
 * closing_mt − opening_mt adalah angka yang BERBEDA, dan berbeda paling jauh
 * persis ketika jumlah tangki bepembacaan berubah di tengah periode — yaitu
 * saat laporannya paling dibutuhkan, karena selisih gabungan mencampurkan
 * perubahan stok dengan perubahan cakupan pencatatan. Layar ini tidak
 * mengurangkan keduanya di mana pun, dan alasannya ditulis di layar
 * ([data-testid="movement-not-difference-note"]) supaya tidak
 * "diperbaiki" kelak oleh pembaca yang mengira ada yang terlupa.
 *
 * ────────────────────────────────────────────────────────────────────────
 * 2. SATU PEMBACAAN BUKAN PERGERAKAN NOL
 * ────────────────────────────────────────────────────────────────────────
 * Tangki dengan satu pembacaan stok berbunyi "tidak dapat dihitung", bukan
 * 0 — nol adalah klaim bahwa stoknya tidak berubah, dan klaim itu tidak
 * pernah diukur. Yang memutuskan tampilan itu adalah medan
 * movement_computable, BUKAN nilai movement_mt: sebuah tangki yang memang
 * bergerak nol juga mengirim 0, dan kedua keadaan itu harus tetap dapat
 * dibedakan. Tangki tanpa satu pun pembacaan stok berbunyi "tidak tersedia"
 * dan TETAP dirender pada rekap per tangki — tangki yang tidak pernah diukur
 * adalah temuan, bukan baris yang layak dibuang.
 *
 * ────────────────────────────────────────────────────────────────────────
 * 3. TANGGAL PEMBACAAN IKUT, DI SEBELAH ANGKANYA
 * ────────────────────────────────────────────────────────────────────────
 * opening_at dan closing_at dirender berdampingan dengan stok awal dan stok
 * akhir, dan tidak pernah diganti dengan tanggal mulai/akhir periode. Stok
 * awal yang baru terambil pada hari keempat berarti tiga hari pertama tidak
 * tercatat, dan pergerakannya menutupi kurun yang lebih pendek daripada
 * periodenya. Tanpa tanggal itu di layar, angkanya terbaca lebih meyakinkan
 * daripada yang sebenarnya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * 4. SUHU RATA-RATA DIBACA, BUKAN DITURUNKAN — DAN STOK DARI BERAT
 * ────────────────────────────────────────────────────────────────────────
 * metrics.average_temperature_c adalah kolom yang dicatat Operator sendiri.
 * Layar ini TIDAK PERNAH merata-ratakan ketiga suhu posisi untuk mengisinya;
 * bila kolomnya kosong, kartunya berbunyi tidak tersedia. Dan stok memakai
 * BERAT (MT) — diputuskan 2026-09-25 dan sudah tertanam di
 * StorageTankReportService::STOCK_COLUMN. calculated_volume_m3 serta
 * kedalaman sounding tidak pernah ikut membentuk satu pun angka stok atau
 * pergerakan di layar ini.
 *
 * ────────────────────────────────────────────────────────────────────────
 * 5. TIDAK ADA PENANDAAN AMBANG DI MANA PUN
 * ────────────────────────────────────────────────────────────────────────
 * Tidak ada kelas aman/bahaya, tidak ada ikon peringatan, tidak ada
 * pewarnaan bersyarat atas nilai metrik — termasuk pada pergerakan bernilai
 * negatif, yang adalah keadaan wajar dan bukan kesalahan. Storage Tank tidak
 * punya master target operasional, dan menurunkan ambang dari data periode
 * itu sendiri akan menghasilkan angka yang TERLIHAT seperti batas proses
 * padahal hanya statistik tentang data yang sedang dinilainya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * DAN TIGA HAL YANG SAMA PERSIS DENGAN screen-135 / 136 / 137 / 138
 * ────────────────────────────────────────────────────────────────────────
 * a. NOL PERHITUNGAN ULANG DI KLIEN. Setiap angka dipetakan apa adanya dari
 *    /api/storage-tank-reports/summary — endpoint yang sama persis dengan
 *    laporan web (screen-133), sehingga kedua layar mustahil berbeda.
 *    Satu-satunya angka turunan adalah TINGGI BATANG grafik: skala visual
 *    murni, tidak pernah dibacakan sebagai angka kepada pengguna. Nilai yang
 *    tertulis di atas tiap batang selalu nilai server apa adanya.
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
 * tangki, dan tabel rekap harian masing-masing menggulir DI DALAM kartunya
 * sendiri (.chart-scroll / .detail-table-wrap), sementara halaman tetap
 * overflow-x: hidden.
 */
import { computed, onMounted, ref } from 'vue'
import FilterPanel from '@/components/filters/FilterPanel.vue'
import FilterSelectField from '@/components/filters/FilterSelectField.vue'
import FilterChip from '@/components/filters/FilterChip.vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { productionLineRepo, type ProductionLineOption } from '@/services/productionLineRepo'
import { useFloatingClockStore } from '@/stores/floatingClock'
import { useAiAssistantStore } from '@/stores/aiAssistant'
import StatusBadge from '@/components/StatusBadge.vue'
import storageTankReportRepo, {
  type StorageTankReportBusinessUnitOption,
  type StorageTankReportDailyRow,
  type StorageTankReportMetric,
  type StorageTankReportMetricKey,
  type StorageTankReportPeriodOption,
  type StorageTankReportSummary,
  type StorageTankReportTankRow,
} from '@/services/storageTankReportRepo'

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

const businessUnits = ref<StorageTankReportBusinessUnitOption[]>([])

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

const periods = ref<StorageTankReportPeriodOption[]>([])
const selectedPeriodId = ref<string | null>(null)
const summary = ref<StorageTankReportSummary | null>(null)

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
    // boleh dapat dicapai kembali dengan tombol Back peramban. Preseden
    // screen-135/136/137/138; tech spec screen-139 menulis "router.push"
    // pada component_test skenario sesi berakhir, dan itu disimpangi di
    // sini dengan sengaja — alasannya ditulis pula pada docblock berkas
    // test-nya.
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
    businessUnits.value = await storageTankReportRepo.fetchBusinessUnits()
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
    periods.value = await storageTankReportRepo.fetchPeriods(scope.value)
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
    summary.value = await storageTankReportRepo.fetchSummary(periodId, scope.value)
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

  return `laporan-storage-tank_${slug || 'periode'}.csv`
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
    // Isi CSV dibentuk SERVER (satu baris per slot waktu, empat kolom
    // konteks record diulang, kolom teks verbatim). Layar ini tidak pernah
    // menyusun berkasnya dari angka yang sedang tampil.
    const blob = await storageTankReportRepo.exportCsv(periodId, scope.value)
    storageTankReportRepo.saveCsvFile(blob, exportFilename())
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

/**
 * Penyebut kelengkapan BERHENTI DI HARI INI untuk periode yang masih
 * berjalan (temuan audit 2026-10-04 #3): server mengirim days_counted di
 * samping days_in_period, dan expected_slots sudah memakai days_counted.
 * Rumus "× N hari" karenanya memakai days_counted — memakai days_in_period
 * akan membuat rumusnya tidak cocok dengan expected_slots yang tampil.
 * Penyebut 0 (belum ada unit tercatat / periode belum mulai) → "-", bukan
 * "0,0%": null bukan 0 (#9). Persen 1 desimal di semua laporan (#10).
 * Semuanya sama dengan laporan web.
 */
const coverageDaysCounted = computed(() => coverage.value?.days_counted ?? coverage.value?.days_in_period ?? 0)
const coverageHasExpected = computed(() => (coverage.value?.expected_slots ?? 0) > 0)
const coveragePercentText = computed(() =>
  coverageHasExpected.value ? `${formatNumber(coverage.value?.coverage_percent, 1)}%` : NOT_AVAILABLE,
)
const stock = computed(() => summary.value?.stock ?? null)
const metrics = computed(() => summary.value?.metrics ?? null)
const daily = computed<StorageTankReportDailyRow[]>(() => summary.value?.daily ?? [])
const byTank = computed<StorageTankReportTankRow[]>(() => summary.value?.by_tank ?? [])
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
 * Keempat kartu mutu, dalam urutan dan dengan label yang SAMA PERSIS dengan
 * laporan web screen-133 (blade `md-kpis--4`), supaya pembaca yang berpindah
 * antar kedua layar menemukan kata yang sama.
 *
 * average_temperature_c sengaja TIDAK ada di daftar ini walaupun ia salah
 * satu dari kesepuluh metrik: ia sudah punya kartunya sendiri di bawah,
 * dengan keterangan asal-usulnya. calculated_weight_mt juga tidak, karena ia
 * sudah menjadi angka stok di atas. Merender salah satunya dua kali akan
 * membuat satu angka terbaca sebagai dua temuan yang berbeda.
 */
const METRIC_CARDS: Array<{
  key: StorageTankReportMetricKey
  testid: string
  label: string
  unit: string
  digits: number
}> = [
  { key: 'ffa_percent', testid: 'ffa', label: 'FFA', unit: '%', digits: 2 },
  { key: 'moisture_content_percent', testid: 'moisture', label: 'Kadar Air', unit: '%', digits: 3 },
  { key: 'impurities_dirt_percent', testid: 'impurities', label: 'Kotoran', unit: '%', digits: 3 },
  { key: 'dobi_index', testid: 'dobi', label: 'DOBI', unit: '', digits: 2 },
]

function metricOf(key: StorageTankReportMetricKey): StorageTankReportMetric | null {
  return metrics.value?.[key] ?? null
}

/** Kartu suhu rata-rata, dibaca dari kolom Operator dan tidak pernah diturunkan. */
const temperatureMetric = computed(() => metricOf('average_temperature_c'))

/**
 * Rekap harian TERTUTUP secara bawaan: pada periode sebulan daftarnya
 * puluhan baris dan akan mendorong angka utama serta grafik jauh ke bawah
 * layar ponsel.
 *
 * Sengaja TIDAK memakai components/CollapsibleSection.vue, yang
 * menyembunyikan isinya dengan v-show sehingga barisnya tetap ada di DOM.
 * Di sini barisnya harus benar-benar tidak dirender saat tertutup (v-if),
 * sementara SUMBER datanya tidak berubah sedikit pun — membuka dan menutup
 * adalah penyingkapan murni atas `daily` yang sudah ada di memori, dan TIDAK
 * memicu satu pun permintaan jaringan baru. fetchSummary tetap dipanggil
 * tepat sekali per periode yang dipilih.
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
 * selalu nilai server apa adanya, dan nilai itu tertulis di atas tiap
 * batang. Tidak ada pewarnaan aman/bahaya yang diturunkan darinya — lihat
 * catatan 5.
 */
function scaledHeight(value: number | null | undefined, values: number[]): string {
  if (value === null || value === undefined || values.length === 0) {
    return '0%'
  }

  const lowest = Math.min(...values)
  const highest = Math.max(...values)

  if (highest <= lowest) {
    return '100%'
  }

  return `${28 + (72 * (value - lowest)) / (highest - lowest)}%`
}

/** Seluruh nilai stok harian yang bukan null — skala grafik tren stok. */
const stockTrendValues = computed(() =>
  daily.value
    .map((row) => row.stock_total_mt)
    .filter((value): value is number => value !== null && value !== undefined),
)

function stockBarHeight(value: number | null | undefined): string {
  return scaledHeight(value, stockTrendValues.value)
}

const hasStockTrend = computed(() => stockTrendValues.value.length > 0)

/**
 * TREN MUTU — TIGA SERI PADA SATU GRAFIK, DINORMALKAN, DAN DINYATAKAN.
 *
 * Skala ketiganya tidak sebanding: DOBI berkisar 2-4 tanpa satuan, FFA 3-5
 * persen, kadar air 0,1-0,3 persen. Menempelkan ketiganya pada satu sumbu
 * nilai akan membuat dua dari tiga tergambar sebagai garis datar di dasar
 * dan perubahannya hilang — padahal yang menyatakan mutu memburuk justru
 * ARAH KETIGANYA BERSAMAAN, bukan salah satunya sendirian.
 *
 * Karena itu tiap seri dinormalkan menjadi INDEKS terhadap rata-rata
 * periodenya sendiri (indeks 100 = rata-rata periode metrik itu), persis
 * seperti laporan web. Indeks itu MURNI SKALA TAMPILAN: ia hanya menentukan
 * tinggi batang dan tidak pernah dibacakan sebagai angka. Yang tertulis di
 * atas tiap batang adalah nilai server apa adanya, dan nilai lengkapnya ada
 * pada Rekap Harian di bawah — itulah yang membuat normalisasi ini tidak
 * menyembunyikan apa pun. Cara penskalaannya dinyatakan pada legenda
 * ([data-testid="quality-trend-legend"]) dan keterangan grafiknya.
 */
const QUALITY_SERIES: Array<{
  column: 'ffa_avg' | 'moisture_avg' | 'dobi_avg'
  key: StorageTankReportMetricKey
  testid: string
  label: string
  unit: string
  digits: number
}> = [
  { column: 'ffa_avg', key: 'ffa_percent', testid: 'ffa', label: 'FFA', unit: '%', digits: 2 },
  {
    column: 'moisture_avg',
    key: 'moisture_content_percent',
    testid: 'moisture',
    label: 'Kadar Air',
    unit: '%',
    digits: 3,
  },
  { column: 'dobi_avg', key: 'dobi_index', testid: 'dobi', label: 'DOBI', unit: '', digits: 2 },
]

/**
 * Indeks satu nilai terhadap rata-rata periode metriknya. null bila nilainya
 * kosong atau rata-rata periodenya tidak ada / nol — dalam kedua hal itu
 * tidak ada batang yang digambar, dan tidak ada angka yang dikarang.
 */
function qualityIndex(
  value: number | null | undefined,
  key: StorageTankReportMetricKey,
): number | null {
  const average = metricOf(key)?.avg

  if (value === null || value === undefined || !average) {
    return null
  }

  return (100 * value) / average
}

/** Seluruh indeks ketiga seri — SATU skala bersama, bukan tiga skala terpisah. */
const qualityIndexValues = computed(() => {
  const values: number[] = []

  for (const row of daily.value) {
    for (const series of QUALITY_SERIES) {
      const index = qualityIndex(row[series.column], series.key)

      if (index !== null) {
        values.push(index)
      }
    }
  }

  return values
})

function qualityBarHeight(
  value: number | null | undefined,
  key: StorageTankReportMetricKey,
): string {
  return scaledHeight(qualityIndex(value, key), qualityIndexValues.value)
}

const hasQualityTrend = computed(() => qualityIndexValues.value.length > 0)

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

/** Pergerakan yang tidak dapat dihitung, dan itu BUKAN nol. */
const NOT_COMPUTABLE = 'tidak dapat dihitung'

/** Titik ribuan, tanpa desimal — untuk cacah (slot, tangki, pembacaan). */
function formatCount(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return NOT_AVAILABLE
  }

  return new Intl.NumberFormat('id-ID').format(value)
}

/**
 * Bilangan berdesimal. Jumlah desimalnya adalah keputusan TAMPILAN per
 * metrik (stok 1 desimal, FFA 2, kadar air 3) — nilainya sendiri datang
 * sudah dibulatkan dari server dan tidak dihitung ulang di sini.
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

/**
 * Pergerakan stok. Tandanya ditulis apa adanya — nilai negatif berarti stok
 * berkurang, keadaan yang wajar dan bukan kesalahan: tidak ada Math.abs,
 * tidak ada pembulatan ke nol, dan tidak ada kelas peringatan.
 *
 * `computable` adalah medan movement_computable dari server, BUKAN
 * pemeriksaan atas nilainya: sebuah tangki yang benar-benar bergerak 0 juga
 * mengirim 0, dan menyatukan keduanya akan mengaku bahwa stok sebuah tangki
 * berpembacaan tunggal tidak berubah — klaim yang tidak pernah diukur.
 */
function formatMovement(
  value: number | null | undefined,
  computable: boolean,
  digits = 1,
): string {
  if (!computable) {
    return NOT_COMPUTABLE
  }

  if (value === null || value === undefined) {
    return NOT_AVAILABLE
  }

  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
    signDisplay: 'exceptZero',
  }).format(value)
}

/**
 * Kolom pergerakan pada rekap per tangki, dengan ketiga keadaannya yang
 * memang berbeda: tangki tanpa satu pun pembacaan stok berbunyi "tidak
 * tersedia", tangki berpembacaan tunggal "tidak dapat dihitung", dan
 * sisanya angka bertanda.
 */
function tankMovementLabel(tank: StorageTankReportTankRow): string {
  if (tank.opening_mt === null && tank.closing_mt === null) {
    return NOT_AVAILABLE
  }

  return formatMovement(tank.movement_mt, tank.movement_computable, 1)
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

/**
 * Tanggal + slot waktu sebuah pembacaan. Slot waktunya ikut karena stok awal
 * dan stok akhir dipilih menurut (tanggal, slot waktu) — dua pembacaan pada
 * tanggal yang sama bukan hal yang sama.
 */
function formatDateTime(value: string | null | undefined): string {
  if (!value) {
    return NOT_AVAILABLE
  }

  const datePart = formatDate(value)
  const timePart = value.length > 10 ? value.slice(11, 16) : ''

  return timePart ? `${datePart} ${timePart}` : datePart
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
  closed: 'Tertutup',
}

function periodStatusLabel(status: string | null | undefined): string {
  if (!status) {
    return NOT_AVAILABLE
  }

  return PERIOD_STATUS_LABELS[status] ?? status
}

function periodOptionLabel(period: StorageTankReportPeriodOption): string {
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
  <main class="laporan-stg-view" data-testid="laporan-storage-tank-mobile">
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
        <span aria-current="page">Laporan Storage Tank</span>
      </nav>
      <h1 class="screen-title">Laporan Storage Tank</h1>
    </div>

    <!-- Akun terikat mill tetapi mill-nya kosong: berhenti total, tanpa
         satu pun permintaan HTTP dan tanpa pemilih mill sebagai pengganti. -->
    <p v-if="noMillForAccount" class="notice notice--warning" role="alert" data-testid="no-mill-for-account">
      Akun Anda belum terhubung ke mill mana pun, sehingga laporan tidak dapat ditampilkan.
      Silakan hubungi Admin untuk menghubungkan akun Anda ke sebuah mill.
    </p>

    <template v-else>
      <FilterPanel aria-label="Filter laporan">
        <!-- Pemilih Mill — hanya Admin, dan tidak dirender sama sekali bila
             server menolak endpoint options dengan 403. Operator,
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
          <FilterChip v-if="!isAdmin" label="Mill" :value="currentMillName || NOT_AVAILABLE" data-testid="mill-current" />
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
        Mill ini belum memiliki periode pelaporan yang mencakup stasiun Storage Tank.
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
          Belum ada data pembacaan Storage Tank pada periode ini. Seluruh angka di bawah ditampilkan sebagai
          &ldquo;{{ NOT_AVAILABLE }}&rdquo; — belum diukur, bukan bernilai nol, dan kedua grafik tidak digambar.
        </p>

        <!-- ============ KELENGKAPAN PENCATATAN — DI ATAS SEGALANYA ============
             Stok awal dan akhir bukan penjumlahan banyak pembacaan melainkan
             PERBANDINGAN DUA PEMBACAAN, sehingga kelengkapanlah yang
             menentukan seberapa jauh kedua pembacaan itu mewakili
             ujung-ujung periodenya. coverage_percent datang dari server apa
             adanya, ditampilkan 1 desimal seperti laporan web (temuan audit 2026-10-04 #10). -->
        <section v-if="coverage" class="detail-section coverage-card" data-testid="coverage-card">
          <h2 class="section-title">Kelengkapan Pencatatan</h2>
          <p class="section-note">
            Baca ini lebih dulu — kelengkapanlah yang menentukan seberapa jauh stok awal dan akhir mewakili
            ujung periodenya.
          </p>
          <div class="metric-figure">
            <span class="metric-value" data-testid="coverage-percent">
              {{ coveragePercentText }}
            </span>
          </div>
          <span v-if="coverageHasExpected" class="metric-note" data-testid="coverage-slots">
            {{ formatCount(coverage.filled_slots) }} dari {{ formatCount(coverage.expected_slots) }} slot waktu terisi
          </span>
          <span v-else class="metric-note" data-testid="coverage-no-expected">belum ada slot yang diharapkan</span>
          <span v-if="coverage.period_running" class="metric-note" data-testid="period-running-note">
            Dihitung sampai hari ini, periode masih berjalan ({{ formatCount(coverageDaysCounted) }} dari
            {{ formatCount(coverage.days_in_period) }} hari periode sudah lewat).
          </span>
          <span class="metric-note" data-testid="coverage-formula">
            Slot yang diharapkan = {{ formatCount(coverage.tank_count) }} tangki &times;
            {{ formatCount(coverageDaysCounted) }} hari{{ coverage.period_running ? ' (sampai hari ini)' : '' }} &times;
            {{ formatCount(coverage.slots_per_tank_per_day) }} slot.
          </span>
          <span class="metric-note">
            Slot kosong tidak pernah dihitung sebagai 0 dan tidak masuk penyebut rata-rata mana pun. Yang
            menentukan angka stok bukan berapa banyak data yang ada, melainkan KAPAN pembacaan pertama dan
            terakhirnya diambil — dan tanggal keduanya ikut ditampilkan di setiap tempat angkanya muncul.
          </span>
        </section>

        <!-- ============ STOK AWAL / STOK AKHIR / PERGERAKAN ============
             Tiga kartu terpisah, sama seperti laporan web screen-133, dengan
             TANGGAL PEMBACAAN di dalam kartu yang sama — lihat catatan 3. -->
        <section class="detail-section" data-testid="stock-opening-card">
          <span class="metric-label">Stok Awal Periode</span>
          <div class="metric-figure">
            <span class="metric-value" data-testid="stock-opening-mt">
              {{ formatNumber(stock?.opening_mt, 1) }}
            </span>
            <span class="metric-unit">MT</span>
          </div>
          <span class="metric-note" data-testid="opening-at">
            pembacaan pertama {{ formatDateTime(stock?.opening_at) }}
          </span>
          <span class="metric-note">
            jumlah stok awal {{ formatCount(coverage?.tank_count) }} tangki, masing-masing dari pembacaan stok
            pertamanya sendiri — bukan nilai terendah, dan bukan baris pertama menurut urutan penyimpanan.
          </span>
        </section>

        <section class="detail-section" data-testid="stock-closing-card">
          <span class="metric-label">Stok Akhir Periode</span>
          <div class="metric-figure">
            <span class="metric-value" data-testid="stock-closing-mt">
              {{ formatNumber(stock?.closing_mt, 1) }}
            </span>
            <span class="metric-unit">MT</span>
          </div>
          <span class="metric-note" data-testid="closing-at">
            pembacaan terakhir {{ formatDateTime(stock?.closing_at) }}
          </span>
          <span class="metric-note">
            jumlah stok akhir {{ formatCount(coverage?.tank_count) }} tangki, masing-masing dari pembacaan stok
            terakhirnya menurut tanggal lalu slot waktu.
          </span>
        </section>

        <section class="detail-section" data-testid="stock-movement-card">
          <span class="metric-label">Pergerakan Bersih</span>
          <div class="metric-figure">
            <!-- Tanda minus ditulis apa adanya dengan warna teks biasa:
                 tidak ada kelas peringatan, tidak ada ikon, tidak ada
                 pembulatan ke nol, dan tidak ada nilai absolut. -->
            <span class="metric-value" data-testid="stock-movement-mt">
              {{ formatMovement(stock?.movement_mt, true, 1) }}
            </span>
            <span class="metric-unit">MT</span>
          </div>
          <span class="metric-note">
            dijumlahkan dari pergerakan tiap tangki &middot;
            <b data-testid="tanks-with-movement">{{ formatCount(stock?.tanks_with_movement) }}</b> tangki terhitung,
            <b data-testid="tanks-without-movement">{{ formatCount(stock?.tanks_without_movement) }}</b> tangki tidak
            dapat dihitung
          </span>
          <p class="section-note" data-testid="movement-not-difference-note">
            Angka ini BUKAN selisih stok akhir dikurangi stok awal. Pergerakan dihitung PER TANGKI lalu
            dijumlahkan; menghitungnya dari stok gabungan akan salah bila jumlah tangki yang tercatat berbeda
            antara awal dan akhir periode — ia mencampurkan perubahan stok dengan perubahan cakupan pencatatan.
            Nilai negatif berarti stok berkurang, keadaan yang wajar dan bukan kesalahan. Tangki dengan satu
            pembacaan tidak menyumbang apa pun di sini karena pergerakannya tidak dapat dihitung — itu bukan nol.
          </p>
        </section>

        <!-- Kartu mutu — satu kolom bertumpuk, masing-masing dengan
             PENYEBUTNYA SENDIRI di sebelah angkanya. Satu baris pembacaan
             dapat mengisi FFA dan mengosongkan DOBI, jadi tidak ada satu
             label jumlah pembacaan yang berlaku untuk semuanya.
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

        <!-- ============ SUHU RATA-RATA MINYAK ============
             Kartunya sendiri, dengan keterangan asal-usulnya di layar supaya
             tidak "dilengkapi" kelak dari ketiga suhu posisi. Lihat
             catatan 4. -->
        <section class="detail-section" data-testid="metric-card-temperature">
          <h2 class="section-title">Suhu Rata-rata Minyak</h2>
          <p class="section-note">Sepanjang periode, dalam &deg;C</p>
          <div class="metric-figure">
            <span class="metric-value" data-testid="metric-temperature-avg">
              {{ formatNumber(temperatureMetric?.avg, 1) }}
            </span>
            <span class="metric-unit">&deg;C rata-rata</span>
          </div>
          <span class="metric-note" data-testid="metric-temperature-count">
            dari {{ formatCount(temperatureMetric?.reading_count) }} pembacaan
          </span>
          <span class="metric-note metric-extremes">
            terendah <b data-testid="metric-temperature-min">{{ formatNumber(temperatureMetric?.min, 1) }}</b>
            &middot; tertinggi <b data-testid="metric-temperature-max">{{ formatNumber(temperatureMetric?.max, 1) }}</b>
          </span>
          <p class="section-note" data-testid="temperature-source-note">
            Angka ini berasal dari KOLOM SUHU RATA-RATA YANG DICATAT OPERATOR, bukan dihitung ulang dari suhu
            atas, tengah, dan bawah. Menghitungnya ulang akan menciptakan kebenaran kedua yang menyimpang
            diam-diam dari yang dilihat Operator di layar input, dan selisihnya tidak akan pernah terlihat
            karena keduanya sama-sama masuk akal. Bila kolom itu kosong, kartu ini berbunyi
            &ldquo;{{ NOT_AVAILABLE }}&rdquo; — layar tidak menurunkan angka apa pun dari ketiga suhu posisi.
          </p>
        </section>

        <!-- Tren harian stok total — menggulir DI DALAM kartu ini. -->
        <section
          v-if="showCharts && daily.length > 0 && hasStockTrend"
          class="detail-section"
          data-testid="stock-trend-card"
        >
          <h2 class="section-title">Tren Harian Stok Total</h2>
          <p class="section-note">
            Stok total seluruh tangki per tanggal, dalam MT. Geser mendatar untuk melihat seluruh tanggal.
          </p>
          <div class="chart-scroll">
            <div class="chart-bars" data-testid="stock-trend-chart">
              <div v-for="row in daily" :key="`stock-${row.date}`" class="chart-col">
                <span class="chart-value">{{ formatNumber(row.stock_total_mt, 1) }}</span>
                <div class="chart-track">
                  <div class="chart-fill" :style="{ height: stockBarHeight(row.stock_total_mt) }"></div>
                </div>
                <span class="chart-axis">{{ formatDayAxis(row.date) }}</span>
              </div>
            </div>
          </div>
          <p class="section-note">
            Sumbu tegak tidak dimulai dari nol — batang membandingkan tanggal, bukan besaran mutlak. Angka di
            atas tiap batang adalah nilai server apa adanya.
          </p>
        </section>

        <!-- ============ TREN MUTU MINYAK — SATU GRAFIK, DINORMALKAN ============
             FFA, kadar air, dan DOBI adalah tiga SERI pada SATU elemen
             grafik. Skalanya tidak sebanding, jadi tiap seri dinormalkan
             menjadi indeks terhadap rata-rata periodenya sendiri — dan cara
             penskalaan itu DINYATAKAN pada legenda. Lihat catatan pada
             QUALITY_SERIES. -->
        <section
          v-if="showCharts && daily.length > 0 && hasQualityTrend"
          class="detail-section"
          data-testid="quality-trend-card"
        >
          <h2 class="section-title">Tren Mutu Minyak</h2>
          <p class="section-note">
            FFA, kadar air, dan DOBI pada SATU grafik — yang menyatakan mutu memburuk adalah arah ketiganya
            bersamaan, bukan salah satunya sendirian. Geser mendatar untuk melihat seluruh tanggal.
          </p>
          <div class="chart-legend" data-testid="quality-trend-legend">
            <span
              v-for="(series, index) in QUALITY_SERIES"
              :key="series.column"
              class="legend-item"
              :data-testid="`quality-legend-${series.testid}`"
            >
              <i :class="`legend-swatch legend-swatch--${index}`"></i>{{ series.label }} — dinormalkan, rata-rata
              periode {{ formatNumber(metricOf(series.key)?.avg, series.digits) }}{{ series.unit }} = indeks 100,
              {{ formatCount(metricOf(series.key)?.reading_count) }} pembacaan
            </span>
          </div>
          <div class="chart-scroll">
            <div class="chart-bars chart-bars--grouped" data-testid="quality-trend-chart">
              <div v-for="row in daily" :key="`quality-${row.date}`" class="chart-col chart-col--grouped">
                <div class="chart-group">
                  <div
                    v-for="(series, index) in QUALITY_SERIES"
                    :key="`${row.date}-${series.column}`"
                    class="chart-track chart-track--thin"
                    :data-testid="`quality-bar-${series.testid}`"
                  >
                    <div
                      :class="`chart-fill chart-fill--${index}`"
                      :style="{ height: qualityBarHeight(row[series.column], series.key) }"
                    ></div>
                  </div>
                </div>
                <span class="chart-axis">{{ formatDayAxis(row.date) }}</span>
              </div>
            </div>
          </div>
          <p class="section-note" data-testid="quality-trend-note">
            TINGGI BATANG BUKAN NILAI ASLI, melainkan indeks yang dinormalkan terhadap rata-rata periode metrik
            itu sendiri (indeks 100 = rata-rata periode metrik itu). Skala ketiganya tidak sebanding — DOBI
            berkisar 2&ndash;4 tanpa satuan, FFA 3&ndash;5%, kadar air 0,1&ndash;0,3% — dan menempelkan
            ketiganya pada satu sumbu nilai akan membuat dua di antaranya tergambar sebagai garis datar di
            dasar. Nilai aslinya ada lengkap pada Rekap Harian di bawah, jadi normalisasi ini tidak
            menyembunyikan apa pun. Tidak ada nilai yang ditandai di luar batas di layar ini — Storage Tank
            tidak punya master target operasional.
          </p>
        </section>

        <p
          v-else-if="showCharts && daily.length > 0"
          class="notice"
          data-testid="quality-trend-unavailable"
        >
          Tidak ada satu pun pembacaan FFA, kadar air, maupun DOBI pada periode ini, jadi grafiknya tidak
          digambar — grafik kosong akan terbaca sebagai garis datar yang terukur.
        </p>

        <!-- Rekap per tangki — SELURUH tangki, termasuk yang jumlah
             pembacaannya nol: tangki yang tidak pernah terisi adalah temuan,
             bukan baris yang layak dibuang. Tabelnya menggulir di dalam
             kartunya sendiri, halaman tetap satu kolom. -->
        <section v-if="byTank.length > 0" class="detail-section" data-testid="by-tank-card">
          <h2 class="section-title">Rekap per Tangki</h2>
          <p class="section-note">
            Pergerakan dihitung per tangki lalu dijumlahkan — kolom Pergerakan di bawah adalah asal angka pada
            kartu Pergerakan Bersih. Geser mendatar untuk melihat seluruh kolom.
          </p>
          <div class="detail-table-wrap">
            <table class="detail-table" data-testid="by-tank-table">
              <thead>
                <tr>
                  <th>Tangki</th>
                  <th class="num">Stok Awal (MT)</th>
                  <th>Waktu Awal</th>
                  <th class="num">Stok Akhir (MT)</th>
                  <th>Waktu Akhir</th>
                  <th class="num">Pergerakan (MT)</th>
                  <th class="num">FFA rata2 (%)</th>
                  <th class="num">Suhu rata2 (&deg;C)</th>
                  <th class="num">Jumlah pembacaan</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="tank in byTank" :key="`tank-${tank.storage_tank_id}`" data-testid="by-tank-row">
                  <td>{{ tank.storage_tank_id || NOT_AVAILABLE }}</td>
                  <td class="num">{{ formatNumber(tank.opening_mt, 1) }}</td>
                  <td>{{ formatDateTime(tank.opening_at) }}</td>
                  <td class="num">{{ formatNumber(tank.closing_mt, 1) }}</td>
                  <td>{{ formatDateTime(tank.closing_at) }}</td>
                  <!-- "tidak dapat dihitung", TIDAK PERNAH 0: nol berarti
                       "stok tidak berubah", klaim yang berbeda dan lebih
                       kuat. Sel ini tidak pernah membawa kelas peringatan,
                       termasuk saat nilainya negatif. -->
                  <td class="num">{{ tankMovementLabel(tank) }}</td>
                  <td class="num">{{ formatNumber(tank.ffa_avg, 2) }}</td>
                  <td class="num">{{ formatNumber(tank.average_temperature_avg, 1) }}</td>
                  <td class="num">{{ formatCount(tank.reading_count) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="section-note" data-testid="by-tank-note">
            Waktu Awal dan Waktu Akhir sengaja berdampingan dengan angkanya supaya terbaca rentang waktu apa
            yang sebenarnya diselisihkan: pembacaan pertama sebuah tangki yang baru terambil beberapa hari
            setelah periode dimulai berarti hari-hari sebelumnya tidak tercatat. Setiap metrik punya
            penyebutnya sendiri, jadi FFA rata2 dan Suhu rata2 pada baris yang sama dapat berasal dari jumlah
            pembacaan yang berbeda.
          </p>
        </section>

        <!-- Rekap harian — panjang pada periode sebulan, jadi TERTUTUP secara
             bawaan. Membuka atau menutupnya murni penyingkapan atas `daily`
             yang sudah ada di memori: NOL permintaan jaringan baru. Nilai
             ASLI ketiga metrik mutu ada di sini, lengkap — itulah yang
             membuat normalisasi pada grafik di atas tidak menyembunyikan apa
             pun. -->
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
                    <th class="num">Stok Total (MT)</th>
                    <th class="num">FFA rata2 (%)</th>
                    <th class="num">Kadar Air rata2 (%)</th>
                    <th class="num">Kotoran rata2 (%)</th>
                    <th class="num">DOBI rata2</th>
                    <th class="num">Suhu rata2 (&deg;C)</th>
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
                    <td class="num">{{ formatNumber(row.stock_total_mt, 1) }}</td>
                    <td class="num">{{ formatNumber(row.ffa_avg, 2) }}</td>
                    <td class="num">{{ formatNumber(row.moisture_avg, 3) }}</td>
                    <td class="num">{{ formatNumber(row.impurities_avg, 3) }}</td>
                    <td class="num">{{ formatNumber(row.dobi_avg, 2) }}</td>
                    <td class="num">{{ formatNumber(row.temperature_avg, 1) }}</td>
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
.laporan-stg-view {
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

/* Sasaran sentuh minimal 44px — berlaku untuk SETIAP kendali di layar ini,
   tinggi DAN lebar. Pemilih di sini melebar penuh mengikuti satu kolomnya. */

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
.legend-swatch { width: 10px; height: 10px; border-radius: 2px; display: inline-block; flex: none; }
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
