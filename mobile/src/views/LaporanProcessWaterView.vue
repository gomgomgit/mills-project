<script setup lang="ts">
/**
 * LaporanProcessWaterView — screen-161--laporan-process-water-mobile /
 * usecase-167--laporan-process-water-mobile "Lihat Laporan Periode Process
 * Water (Mobile)".
 *
 * Kembaran mobile KEEMPAT BELAS, dan BACAAN SAJA: layar ini tidak punya satu
 * pun jalur yang mengubah data stasiun, dan tidak menulis ke localDb.
 *
 * ────────────────────────────────────────────────────────────────────────
 * NOL PERUBAHAN BACKEND — DIBAYAR LEBIH DULU, DENGAN SENGAJA
 * ────────────────────────────────────────────────────────────────────────
 * Ketiga rute data /api/process-water-reports/* SUDAH menerima Operator sejak
 * commit pertama screen-160 — justru supaya layar ini tidak perlu melebarkan
 * akses apa pun — dan /api/production-lines/options-for-report dipakai ulang
 * VERBATIM dari screen-135. Tidak satu berkas di backend/ disentuh untuk layar
 * ini, dan tidak ada entri api-index baru. Bila sebuah test backend memerah,
 * sebabnya ada di tempat lain.
 *
 * ────────────────────────────────────────────────────────────────────────
 * SEMBILAN KARTU DALAM EMPAT BLOK — GULIR TERPENDEK DI ANTARA LAPORAN BERGRID
 * ────────────────────────────────────────────────────────────────────────
 * Engine Room mobile punya dua puluh tiga kartu dan Effluent Plant empat
 * belas; di sini SEMBILAN. Justru karena pendek, pengelompokannya
 * DIPERTAHANKAN: empat blok berjudul mengikuti rantai pengolahan air —
 * Aliran Air, Softener, Dosis Kimia, Air Umpan Boiler — dengan judul dan
 * urutan SAMA PERSIS seperti versi web (screen-160), sehingga pembaca yang
 * berpindah dari ponsel ke web tidak perlu mempelajari ulang peta halamannya.
 *
 * MARKUP KARTUNYA DITULIS SEKALI DAN DI-LOOP atas `metrics` yang
 * DIKELOMPOKKAN MENURUT FIELD `group` DARI RESPONS — bukan atas daftar nama
 * kolom yang dipaku di berkas ini. Sembilan kartu yang ditulis manual adalah
 * sembilan kesempatan kehilangan penyebut atau MEWARISI penyebut kartu lain,
 * dan kolom baru dari migrasi kelak masuk ke blok yang benar tanpa menyunting
 * berkas ini.
 *
 * KEEMPAT BLOK DIRENDER TERBUKA, TANPA KONTROL LIPAT. Melipatnya memperpendek
 * gulir tetapi menyembunyikan penyebut tiap kartu, dan penyebut itulah yang
 * paling perlu dilihat di layar ini. Yang dapat ditutup adalah KETIGA REKAP di
 * bawah, bukan blok kartu.
 *
 * ────────────────────────────────────────────────────────────────────────
 * TITIK PALING RAWAN DI LAYAR INI: BLOK DOSIS KIMIA
 * ────────────────────────────────────────────────────────────────────────
 * Dosis alum (kg/jam) dan dosis polimer (g/jam) berada di blok yang SAMA,
 * keduanya bernama 'dosis', dan skalanya berbeda SERIBU KALI. Di web keduanya
 * BERDAMPINGAN sehingga satuannya terbaca sekaligus; di ponsel pembaca melihat
 * SATU KARTU PADA SATU WAKTU dan kedekatan itu hilang — angka 2,5 tanpa satuan
 * pada kartu kedua akan dibaca dengan skala kartu pertama.
 *
 * Karena itu SATUAN DICETAK PADA KARTUNYA MASING-MASING, di dalam elemen
 * `pw-metric-unit-{kolom}` kartu itu sendiri — bukan sekali pada judul
 * bloknya. Keduanya juga TIDAK disamakan ke satu satuan demi kerapian
 * (menuliskan polimer sebagai 0,42 kg/jam menyembunyikan ketelitian yang
 * dicatat di lapangan) dan TIDAK dijumlahkan: 'total dosis kimia' bukan
 * besaran yang punya arti.
 *
 * KARTU BER-SATUAN null TIDAK MENCETAK APA PUN — bukan '-', bukan '—', dan
 * bukan satuan karangan. Kedua kartu pH justru yang null, dan itu bukan
 * kelalaian: pH memang besaran tanpa satuan, dan memberinya satuan karangan
 * demi keseragaman dengan tujuh kartu lain akan menyatakan sesuatu yang tidak
 * benar.
 *
 * ────────────────────────────────────────────────────────────────────────
 * NOL SEBARAN KATEGORIS — PEMBEDA TERPENTING DARI LAPORAN SEBELUMNYA
 * ────────────────────────────────────────────────────────────────────────
 * Stasiun ini TIDAK PUNYA SATU PUN KOLOM KATEGORIS. `action_taken_status`
 * bertipe TEKS BEBAS meski namanya menyebut 'status' (tiga verifikasi
 * terpisah di docblock ProcessWaterReportService), jadi ia diterbitkan sebagai
 * REKAP TEKS — satu entri per slot — dan isi yang berulang sama persis TIDAK
 * dikelompokkan: sebaran atas teks bebas menghasilkan satu 'nilai' per kalimat
 * yang berbeda, dan daftar kalimat berjumlah satu-satu bukan sebaran.
 *
 * Layar ini karena itu TIDAK punya satu pun daftar sebaran, dan ketiadaan itu
 * DISENGAJA. Layar yang persis sebelumnya (Engine Room mobile) punya DUA, jadi
 * bentuknya paling mudah tersalin dari tetangga terdekat — dan salinan itu akan
 * salah. Tidak ada satu pun testid sebaran dan tidak ada satu pun JUDUL yang
 * dimulai 'Sebaran' di berkas ini; yang ada hanyalah PROSA yang menerangkan
 * ketiadaannya, dan prosa itu memang diminta spesifikasi.
 *
 * ────────────────────────────────────────────────────────────────────────
 * DUA KOSAKATA PAYLOAD, DAN PEMILIHANNYA DIKERJAKAN DI SINI
 * ────────────────────────────────────────────────────────────────────────
 * ProcessWaterReportService menerbitkan `coverage.coverage_percent`,
 * `coverage.process_water_unit_count`, `metrics[k].reading_count`,
 * `inspector_recap` sebagai OBJEK {entries, not_recorded, recorded_slots},
 * `by_unit`, `daily[].metrics_avg`, dan `daily_trend`. Tech spec layar ini
 * menamai medan yang sama `percent`, `unit_count`, `count`, `inspector_recap`
 * sebagai DAFTAR, `unit_recap`, dan `daily[].raw_water_flow_avg`.
 *
 * REPO MENERUSKAN KEDUANYA UTUH (nol kunci dibuang), dan VIEW INI yang
 * memilih — lihat helper bernama *Of() / normalize*() di bawah. Pemilihan itu
 * MEMBACA, bukan menghitung: tidak satu bilangan baru pun lahir darinya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * NOL ARITMETIKA ATAS ANGKA LAPORAN
 * ────────────────────────────────────────────────────────────────────────
 * Termasuk TIDAK memetakan satuan dan TIDAK menyusun ulang kalimat server:
 * `coverage._note_filled_rule` dan `_note_absences` dirender VERBATIM, karena
 * kalimat yang disusun dua kali di dua platform adalah kalimat yang akan
 * berselisih. Yang "dihitung" di berkas ini hanya TIGA hal, ketiganya TAMPILAN
 * dan bukan angka laporan:
 *
 *   (a) PEMETAAN NOL-KE-TANDA-HUBUNG: penyebut cakupan yang belum terbentuk
 *       dirender '—' beserta 'belum ada slot yang diharapkan'. PRESENTASI,
 *       bukan aritmetika — tidak ada bilangan baru yang lahir darinya, dan
 *       '0,0%' tidak pernah muncul di dalam blok kelengkapan.
 *   (b) PORSI REKAP INSPECTOR: payload server TIDAK menerbitkan porsi siap
 *       cetak, jadi porsinya dibentuk dengan rumus, pembulatan, dan pemisah
 *       desimal yang DISALIN PERSIS dari helper $porsi pada blade screen-160
 *       (number_format(100 * count / recordedSlots, 1)), supaya ponsel tidak
 *       mungkin mencetak porsi yang berbeda dari web untuk payload yang sama.
 *       PENYEBUTNYA slot TERCATAT DARI RESPONS — tidak pernah expected_slots.
 *   (c) TINGGI BATANG TREN: skala tampilan yang sama dengan $tinggiBatang pada
 *       blade screen-160. Tidak ada nilai yang ditandai aman/bahaya karenanya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * YANG DIWARISI DARI SERI, DINYATAKAN KARENA MENANGGUNG BEBAN
 * ────────────────────────────────────────────────────────────────────────
 * KELENGKAPAN PENCATATAN DIRENDER PALING ATAS, sebelum satu kartu pun. Di
 * layar sempit ia satu-satunya bagian yang pasti terlihat tanpa menggulir, dan
 * ia menentukan apakah sembilan angka di bawahnya layak dipercaya.
 *
 * SLOT TERISI DIHITUNG ATAS SEBELAS KOLOM, BUKAN TIGA BELAS: `shift` dan
 * `inspector_id` adalah kolom IDENTITAS dan dikecualikan. Konsekuensinya
 * BERPASANGAN DAN BERLAWANAN, dan keduanya harus terbaca: slot yang HANYA
 * mengisi kolom teks bebas TETAP terhitung terisi (kedua kolom teks termasuk
 * dalam kesebelas kolom), sementara slot yang HANYA mengisi shift/inspector
 * TIDAK. Karena itu coverage.filled_slots DAPAT MELEBIHI penyebut setiap
 * kartu — dirender APA ADANYA, tidak di-clamp, tidak disembunyikan, dan tanpa
 * peringatan 'data tidak konsisten'; yang menerangkannya adalah kalimat
 * server di `_note_filled_rule`.
 *
 * URUTAN CABANG KETERANGAN CAKUPAN: days_counted === 0 DIPERIKSA SEBELUM
 * periode-berjalan. Server menandai periode yang BELUM MULAI sebagai masih
 * berjalan juga, sehingga memeriksa yang kedua lebih dulu membuat keterangan
 * "belum mulai" tidak pernah terender — cacat yang nyata terjadi pada tiga
 * layar dan diperbaiki pada commit screen-148.
 *
 * PRODUCTION LINE WAJIB, DAN PENJAGAANNYA ADA DI FUNGSI loadSummary() — bukan
 * di template. Template yang hanya menyembunyikan angka tetap membiarkan
 * permintaannya terkirim; ketiga jalur (muat awal, ganti periode, coba lagi)
 * memanggil fungsi yang sama, jadi satu penjagaan melindungi ketiganya.
 *
 * URUTAN PENENTUAN LINE TIGA LANGKAH, BUKAN EMPAT: rute -> ingatan perangkat
 * -> minta pengguna memilih. TIDAK ADA langkah "pilih line pertama" dan tidak
 * ada "pilih satu-satunya line": memilihkan line secara diam-diam menerbitkan
 * angka yang pengguna tidak pernah minta, dan pada laporan per-line itu
 * berarti pembaca menyangka sedang melihat seluruh mill. Akibat teknisnya:
 * pemilih line DIRENDER walau daftarnya hanya berisi satu entri.
 *
 * Ingatan line dibaca dari localStorage dengan kunci `msl_production_line_
 * {userId}` — KUNCI YANG SAMA PERSIS yang ditulis StationListView.vue dan yang
 * sudah dibaca ReportingPilihStasiunView.vue. Kunci yang berbeda akan
 * terkompilasi, terlihat benar, dan tidak pernah cocok. Pembacaannya dibungkus
 * try/catch mengikuti pola floatingClock.ts: pada mode privat penyimpanan dapat
 * melempar, dan itu tidak boleh mematahkan layar — cukup jatuh ke keadaan
 * "belum ada line terpilih", tanpa satu pesan galat pun.
 *
 * KLIEN TIDAK MEMVALIDASI KEPEMILIKAN LINE. Line basi yang masih diingat
 * perangkat tetap dikirim, dan 403 dari server diperlakukan sebagai "pilih
 * line lagi" — bukan galat jaringan, dan tidak satu angka pun dari mill lain
 * pernah dirender.
 *
 * 401 MENGARAHKAN KE LOGIN TANPA PESAN JARINGAN; 422 karena akun tanpa mill
 * menampilkan arahan menghubungi Admin, BUKAN galat jaringan. Keduanya mudah
 * tertukar dan keduanya salah bila tertukar.
 *
 * EKSPOR HANYA CSV, dan tombol Excel TIDAK DIRENDER SAMA SEKALI — bukan karena
 * endpoint tidak mendukungnya (ia mendukung), melainkan karena WebView ponsel
 * tidak membuka .xlsx. `format=csv` dikirim EKSPLISIT, dan tidak ada jalur
 * kode di layar ini yang dapat menyusun format lain.
 *
 * LAYAR INI PERLU JARINGAN. Ia tidak menyimpan hasil laporan ke perangkat dan
 * tidak punya mode luring: keadaan luring DINYATAKAN apa adanya dan NOL angka
 * dirender bersamanya — angka basi tanpa penanda adalah kegagalan yang lebih
 * buruk daripada layar yang berterus terang kosong, dan layar yang diam lebih
 * buruk lagi karena di layar sentuh diam tidak dapat dibedakan dari aplikasi
 * yang macet.
 *
 * HALAMAN TIDAK PERNAH MENGGULIR MENDATAR PADA 390px. Ketiga tabel rekap
 * BERTUMPUK menjadi kartu lewat v-stacked-table + src/assets/stacked-table.css
 * (temuan QA LP-M-02/LP-M-03). Grafik tren aliran air baku-lah satu-satunya
 * yang menggulir mendatar DI DALAM kartunya sendiri.
 *
 * DISIPLIN KELAS CSS: tiap kelas yang dipakai markup punya definisi di blok
 * <style> di bawah atau di stylesheet bersama yang dirujuknya
 * (src/assets/stacked-table.css untuk .detail-table / .num). Dua defek lama
 * SENGAJA TIDAK disalin: LaporanDepricarpingView.vue membungkus angka dengan
 * kelas .metric-values yang tidak terdefinisi di satu berkas pun di src/, dan
 * keterangan cakupan Depricarping mobile menyebut jumlah kolom bacaan yang
 * salah. Di sini dipakai .metric-row/.metric-row-item yang TERDEFINISI, dan
 * jumlah kolom bacaannya SEBELAS — tidak diketik sebagai angka di dalam
 * kalimat yang disusun klien, melainkan dibaca dari kalimat server.
 */
import { computed, onMounted, onUnmounted, ref } from 'vue'
import FilterPanel from '@/components/filters/FilterPanel.vue'
import LoadingState from '@/components/loading/LoadingState.vue'
import { createLatestRequestGuard } from '@/utils/latestRequest'
import BusyLabel from '@/components/loading/BusyLabel.vue'
import FilterSelectField from '@/components/filters/FilterSelectField.vue'
import FilterChip from '@/components/filters/FilterChip.vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { type ProductionLineOption } from '@/services/productionLineRepo'
import { useFloatingClockStore } from '@/stores/floatingClock'
import { useAiAssistantStore } from '@/stores/aiAssistant'
import CollapsibleSection from '@/components/CollapsibleSection.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import processWaterReportRepo, {
  type ProcessWaterReportCoverage,
  type ProcessWaterReportDailyRow,
  type ProcessWaterReportInspectorEntry,
  type ProcessWaterReportMetric,
  type ProcessWaterReportPeriodOption,
  type ProcessWaterReportSummary,
  type ProcessWaterReportTextEntry,
  type ProcessWaterReportTrendPoint,
  type ProcessWaterReportUnitRow,
} from '@/services/processWaterReportRepo'

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
    !noMillForAccount.value && !loadingProductionLines.value && productionLines.value.length === 0,
)

/**
 * DUA KEADAAN YANG MENUNTUT TINDAKAN BERBEDA, jadi dua pesan berbeda — dan
 * hanya yang kedua punya tombol Coba Lagi. Menyatukan keduanya akan menyuruh
 * pengguna menelepon Admin untuk masalah jaringan, atau sebaliknya menyuruhnya
 * mencoba lagi atas sesuatu yang tidak akan pernah berubah dengan mencoba
 * lagi.
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
 * perangkat yang sama. SAMA PERSIS dengan StationListView.vue — kunci yang
 * berbeda akan terkompilasi, terlihat benar, dan tidak pernah cocok.
 *
 * Tanpa pengguna yang sedang masuk kuncinya tidak dapat dibentuk, dan
 * penyimpanan TIDAK dibaca sama sekali.
 */
function rememberedProductionLineKey(): string | null {
  const userId = authStore.currentUser?.id

  return userId ? `msl_production_line_${userId}` : null
}

/**
 * localStorage dibungkus try/catch mengikuti pola floatingClock.ts /
 * aiAssistant.ts: pada mode privat penyimpanan bisa melempar, dan gagal
 * mengingat pilihan tidak boleh mematahkan layar — kegagalannya diperlakukan
 * sebagai "tidak ada ingatan", bukan sebagai galat, dan TIDAK ADA pesan galat
 * yang dirender karenanya.
 */
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

/**
 * TIGA langkah, dan langkah ketiga adalah "tidak ada line terpilih" — bukan
 * sebuah pilihan. Nilai dari rute dan dari ingatan perangkat TIDAK PERNAH
 * dipercaya begitu saja: keduanya hanya dipakai bila masih ada di daftar line
 * yang baru diambil dari server. Kepemilikan line tidak pernah diperiksa di
 * klien — itu urusan server, yang menjawab 403.
 */
function resolveProductionLine(lines: ProductionLineOption[]): void {
  const fromRoute = lines.find((line) => line.id === routeProductionLineId())
  const remembered = lines.find((line) => line.id === readRememberedProductionLineId())

  // TIDAK ADA `?? lines[0]` DAN TIDAK ADA `lines.length === 1 ? lines[0]` di
  // sini, dan ketiadaannya disengaja — lihat docblock berkas.
  const resolved = fromRoute ?? remembered ?? null

  selectedProductionLineId.value = resolved?.id ?? null

  if (resolved) {
    // Ditulis kembali supaya pilihan yang dibawa screen-141 ikut menjadi
    // ingatan — layar Daftar Stasiun dan layar laporan berbagi satu konteks.
    rememberProductionLineId(resolved.id)
  }
}

/**
 * Daftar line diambil lewat processWaterReportRepo.fetchLines(), yang
 * MENDELEGASIKAN ke productionLineRepo.fetchProductionLinesForReport(null) —
 * endpoint screen-135 yang dipakai ulang verbatim, TANPA business_unit_id, dan
 * tidak diduplikasi di bawah prefix process-water-reports.
 */
async function loadProductionLines(): Promise<void> {
  loadingProductionLines.value = true
  productionLineFetchFailed.value = false

  try {
    const lines = (await processWaterReportRepo.fetchLines()) ?? []

    productionLines.value = lines
    resolveProductionLine(lines)
  } catch (error) {
    // 401 di sini tetap berarti sesi berakhir, jadi ia DITERUSKAN ke
    // handleError() yang mengarahkan ke Login TANPA pesan jaringan. Galat
    // lainnya BUKAN kegagalan laporan: ia tidak boleh menyamar sebagai galat
    // ringkasan, jadi ia mengisi state-nya sendiri dengan tombol Coba
    // Lagi-nya sendiri.
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
 * Cakupan yang dikirim repo — SATU medan, dan tidak ada medan mill di dalamnya
 * untuk dapat salah terkirim.
 */
const scope = computed(() => ({
  productionLineId: selectedProductionLineId.value,
}))

/**
 * Nama mill yang sedang berlaku, sebagai KETERANGAN — tidak ada pemilih mill
 * di layar ini. Dibaca dari summary.business_unit.name; respons /periods TIDAK
 * memuat nama mill sama sekali, dan membacanya dari sana menghasilkan
 * undefined yang ikut dirender (koreksi yang sudah ditemukan di screen-155).
 */
const currentMillName = computed(
  () => summary.value?.business_unit?.name || authStore.businessUnit?.name || '',
)

/* ------------------------------------------------------------------ */
/* Periode & ringkasan                                                 */
/* ------------------------------------------------------------------ */

const periods = ref<ProcessWaterReportPeriodOption[]>([])
const selectedPeriodId = ref<string | null>(null)
const summary = ref<ProcessWaterReportSummary | null>(null)

const loadingPeriods = ref(false)
/**
 * Daftar periode sudah pernah selesai dimuat (audit loading state
 * 2026-10-05). Tanpa ini "Mill ini belum memiliki periode" tampil selama
 * daftar line/periode MASIH dimuat — daftar kosong saat memuat terbaca sebagai
 * "tidak ada periode".
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
    !millNotLinked.value &&
    !networkError.value &&
    !errorMessage.value &&
    periods.value.length === 0,
)

const selectedPeriod = computed(
  () => periods.value.find((item) => item.id === selectedPeriodId.value) ?? null,
)

/* ------------------------------------------------------------------ */
/* Luring — DINYATAKAN, bukan ditutupi angka basi                      */
/* ------------------------------------------------------------------ */

/**
 * Layar ini PERLU JARINGAN: ia tidak menyimpan hasil laporan ke localDb maupun
 * localStorage dan tidak punya mode luring. Keadaan luring karena itu
 * DINYATAKAN apa adanya, dan selama ia berlaku NOL angka dirender — bukan
 * angka dari pemuatan sebelumnya tanpa penanda.
 */
const isOffline = ref(false)

/** Pemuatan awal sudah pernah dijalankan — lihat onOnline(). */
const bootstrapped = ref(false)

function syncOnlineState(): void {
  isOffline.value = typeof navigator !== 'undefined' && navigator.onLine === false
}

/* ------------------------------------------------------------------ */
/* Galat                                                               */
/* ------------------------------------------------------------------ */

const networkError = ref<string | null>(null)
const errorMessage = ref<string | null>(null)

/**
 * 422 karena users.business_unit_id NULL — keadaannya SENDIRI, bukan galat
 * jaringan dan bukan pesan validasi mentah. Arahannya menghubungi Admin, dan
 * sistem TIDAK menawarkan daftar seluruh mill sebagai gantinya.
 */
const millNotLinked = ref(false)

/**
 * Line yang diminta bukan milik mill akun (403) — termasuk line basi yang
 * masih diingat perangkat. Diperlakukan sebagai "pilih line lagi", BUKAN galat
 * jaringan, dan tidak pernah menampilkan angka mill lain.
 */
const productionLineRejected = ref(false)

/** Aksi terakhir yang gagal — itulah yang diulang tombol Coba Lagi. */
const retryAction = ref<(() => Promise<void>) | null>(null)
const canRetry = computed(() => retryAction.value !== null)

/**
 * BENTUK GALAT DATAR. apiClient.normalizeError menolak dengan objek DATAR
 * `{ message, errors?, status? }` dan sudah MEMBUANG `response` di sana — jadi
 * `.response` tidak pernah ada pada galat yang sampai ke sini. Medan itu karena
 * itu tidak ditulis pada antarmuka ini: yang tidak dapat ditulis tidak dapat
 * salah.
 */
interface ErrorLike {
  status?: number
  message?: string
}

/**
 * Cara sebuah 422 ditafsirkan, dan ia WAJIB diputuskan pemanggil karena satu
 * kode HTTP yang sama membawa dua arti yang berbeda di layar ini:
 *
 *   'mill'    -> /periods dan /summary. Satu-satunya 422 yang dapat dicapai
 *                dari kedua jalur itu adalah akun terikat mill yang
 *                users.business_unit_id-nya NULL: period_id dan
 *                production_line_id tidak pernah dikirim kosong, karena
 *                penjagaannya ada di fungsi pemuatan.
 *   'message' -> /export. Di sana 422 berarti parameter wajib absen atau batas
 *                50.000 baris slot terlampaui, dan pesannya ditampilkan APA
 *                ADANYA — layar ini tidak mencoba memotong, memecah, atau
 *                mengulang permintaan dengan rentang lebih kecil.
 */
type Meaning422 = 'mill' | 'message'

function statusOf(error: unknown): number | undefined {
  const candidate = error as ErrorLike | null

  return candidate?.status
}

function messageOf(error: unknown, fallback: string): string {
  const candidate = error as ErrorLike | null

  return candidate?.message || fallback
}

/**
 * Penanganan galat terpusat. URUTAN CABANGNYA MENENTUKAN BENAR/SALAHNYA LAYAR:
 *
 *   401            -> Login, TANPA pesan jaringan dan tanpa tombol Coba Lagi.
 *                     "Periksa koneksi Anda" pada sesi yang berakhir mengirim
 *                     pembaca memeriksa sinyal alih-alih masuk kembali.
 *   tanpa status    -> penolakan tanpa respons = jaringan; periode dan line
 *                     yang sudah terpilih SENGAJA tidak disentuh.
 *   403 /summary    -> "pilih line lagi": pilihan line dikosongkan, bukan
 *                     ditampilkan sebagai galat jaringan. Inilah jalur line
 *                     basi dari ingatan perangkat.
 *   404 /summary    -> "periode tidak ditemukan", pilihan periode dikosongkan.
 *   422 tanpa mill  -> arahan menghubungi Admin, BUKAN galat jaringan dan
 *                     BUKAN daftar seluruh mill.
 *   sisanya         -> pesan yang dapat dibaca pengguna.
 */
function handleError(
  error: unknown,
  retry: (() => Promise<void>) | null,
  meaning422: Meaning422 = 'message',
): void {
  const status = statusOf(error)

  if (status === 401) {
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

    return
  }

  if (status === 403) {
    productionLineRejected.value = true
    selectedProductionLineId.value = null
    networkError.value = null
    errorMessage.value = null
    retryAction.value = null
    summary.value = null

    return
  }

  if (status === 404) {
    errorMessage.value = messageOf(
      error,
      'Periode Pelaporan yang dipilih tidak ditemukan. Silakan pilih periode lain.',
    )
    selectedPeriodId.value = null
    retryAction.value = null
    summary.value = null

    return
  }

  if (status === 422 && meaning422 === 'mill') {
    millNotLinked.value = true
    networkError.value = null
    errorMessage.value = null
    retryAction.value = null
    summary.value = null

    return
  }

  errorMessage.value = messageOf(error, 'Gagal memuat laporan.')
  retryAction.value = retry
  summary.value = null
}

function clearErrors(): void {
  networkError.value = null
  errorMessage.value = null
  millNotLinked.value = false
  productionLineRejected.value = false
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
    // TANPA business_unit_id dan TANPA production_line_id — tidak ada jalur
    // kode untuk mengirimkan keduanya. Periode milik MILL, bukan milik line.
    periods.value = await processWaterReportRepo.fetchPeriods()
  } catch (error) {
    periods.value = []
    handleError(error, loadPeriods, 'mill')
  } finally {
    loadingPeriods.value = false
    periodsLoaded.value = true
  }
}

/**
 * PENJAGAAN PRODUCTION LINE ADA DI SINI — DI DALAM FUNGSI PEMUATAN — bukan
 * hanya di template, supaya tidak ada jalur pemanggilan yang bisa
 * melewatinya. KETIGA jalur (ganti periode, ganti line, coba lagi) memanggil
 * fungsi ini, jadi satu penjagaan melindungi ketiganya; penjaga yang hanya ada
 * di template dilewati ketiganya, dan melewatinya di layar ini berarti 422 dari
 * server — bukan angka seluruh mill.
 */
async function loadSummary(): Promise<void> {
  const periodId = selectedPeriodId.value

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
    const result = await processWaterReportRepo.fetchSummary(periodId, scope.value)
    if (isLatest()) {
      summary.value = result
    }
  } catch (error) {
    if (isLatest()) {
      handleError(error, loadSummary, 'mill')
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

/**
 * Pemuatan awal. Daftar line lebih dulu, periode menyusul — /periods memang
 * TIDAK tersaring line, tetapi ringkasan tidak dapat dimuat sebelum sebuah
 * line berlaku.
 *
 * LURING MENGHENTIKANNYA SEBELUM PERMINTAAN PERTAMA: laporan ini dihitung di
 * server setiap kali dibaca, jadi tanpa jaringan tidak ada satu angka pun yang
 * dapat ditampilkan — dan menampilkan angka pemuatan sebelumnya tanpa penanda
 * adalah kegagalan yang lebih buruk.
 */
async function bootstrap(): Promise<void> {
  if (!accountBusinessUnitId.value) {
    // Berhenti di sini. TIDAK ada permintaan apa pun, dan TIDAK ada pemilih
    // mill sebagai gantinya.
    noMillForAccount.value = true

    return
  }

  if (isOffline.value) {
    return
  }

  bootstrapped.value = true

  await loadProductionLines()
  await loadPeriods()
}

function onOffline(): void {
  syncOnlineState()
}

function onOnline(): void {
  syncOnlineState()

  if (!bootstrapped.value && !noMillForAccount.value) {
    void bootstrap()
  }
}

onMounted(async () => {
  syncOnlineState()
  window.addEventListener('online', onOnline)
  window.addEventListener('offline', onOffline)

  await bootstrap()
})

onUnmounted(() => {
  window.removeEventListener('online', onOnline)
  window.removeEventListener('offline', onOffline)
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

  return `laporan-process-water_${slug || 'periode'}_${lineSlug || 'line'}.csv`
}

async function onExport(): Promise<void> {
  const periodId = selectedPeriodId.value

  // Ekspor mengikuti cakupan yang sama dengan angka di layar: tanpa line,
  // tidak ada berkas — berkas yang mencampur line adalah bentuk kesalahan yang
  // paling sulit dibantah setelah terkirim. `exporting` mengunci tombol sesaat
  // selama berkas disiapkan, sehingga klik ganda hanya mengirim SATU
  // permintaan.
  if (!periodId || !selectedProductionLineId.value || exporting.value) {
    return
  }

  exporting.value = true

  try {
    // Isi CSV dibentuk SERVER (satu baris per slot waktu, ke-13 kolom detail
    // termasuk `shift` dan `inspector_id`). Layar ini tidak pernah menyusun
    // berkasnya dari angka yang tampil. Nama berkas diambil SAAT ekspor
    // diminta: bila pengguna mengganti periode selama berkas diunduh, isinya
    // tetap milik periode yang diekspor, jadi namanya pun harus milik periode
    // itu. format=csv SELALU dan EKSPLISIT.
    const filename = exportFilename()
    const blob = await processWaterReportRepo.exportCsv(periodId, scope.value)
    processWaterReportRepo.saveCsvFile(blob, filename)
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

const coverage = computed<ProcessWaterReportCoverage | null>(() => summary.value?.coverage ?? null)
const metrics = computed<Record<string, ProcessWaterReportMetric>>(
  () => summary.value?.metrics ?? {},
)

/**
 * KEDUA REKAP TEKS, dibaca sebagai DUA variabel TERPISAH dan TIDAK PERNAH
 * disatukan menjadi satu daftar: satu slot yang mengisi kedua kolom muncul
 * pada KEDUA rekap, dan itu benar — menyatukannya akan menghitung slot itu dua
 * kali di dalam satu daftar dan mencampur dua pertanyaan yang berbeda (apa
 * yang dikerjakan vs apa yang ditemukan).
 */
const actionTakenEntries = computed<ProcessWaterReportTextEntry[]>(
  () => summary.value?.text_entries?.action_taken_status ?? [],
)
const findingEntries = computed<ProcessWaterReportTextEntry[]>(
  () => summary.value?.text_entries?.findings ?? [],
)

/**
 * Isi satu entri teks bebas. `text` nama server, `value` nama tech spec —
 * dibaca, bukan diubah: tidak ada pemotongan, tidak ada perapian, tidak ada
 * pengelompokan menurut isinya.
 */
function entryText(entry: ProcessWaterReportTextEntry): string {
  return entry.text ?? entry.value ?? ''
}

/** Prosa SERVER, dirender VERBATIM — klien tidak menyusun kalimatnya sendiri. */
const noteFilledRule = computed(() => coverage.value?._note_filled_rule ?? '')
const noteAbsences = computed(() => summary.value?._note_absences ?? '')

const totals = computed(() => summary.value?.total ?? null)
const dailyTotal = computed(() => summary.value?.daily_total ?? null)

/**
 * Label, satuan, dan jumlah desimal tiap kolom ukur — SATU SUMBER, dipakai
 * kartu metrik, rekap per unit, dan rekap harian, sehingga satu perubahan label
 * tidak mungkin meninggalkan dua ejaan di layar.
 *
 * KENAPA TABEL INI ADA DI VIEW DAN BUKAN DIBACA DARI RESPONS: payload Process
 * Water memang TIDAK menerbitkan label maupun satuan per metrik (lihat
 * ProcessWaterReportService::metricsOf — tiap entri hanya min/avg/max/
 * reading_count/group). Isinya karena itu disalin PERSIS dari $metrikMeta pada
 * blade screen-160 supaya ponsel dan web menuliskan satuan yang sama untuk
 * angka yang sama.
 *
 * NILAI SERVER TETAP MENANG bila kelak diterbitkan (lihat metricCardOf): tabel
 * ini CADANGAN, bukan penimpa — dan TIDAK ADA PETA SATUAN DI SINI yang
 * mengubah 'C' menjadi '°C'. Satuan '°C' dan 'm³/jam' di bawah adalah LABEL
 * STATIS yang ditulis tangan, bukan hasil transformasi nilai dari server.
 *
 * KEDUA KOLOM pH BERSATUAN null, DAN ITU BUKAN KELALAIAN: pH memang besaran
 * tanpa satuan, dan kartunya tidak mencetak apa pun di tempat satuan — bukan
 * '-', bukan '—', bukan satuan karangan demi keseragaman dengan tujuh kartu
 * lain.
 *
 * KEDUA KOLOM TEKS BEBAS DAN KEDUA KOLOM IDENTITAS TIDAK ADA di daftar ini dan
 * memang tidak boleh ada: terendah/rata-rata/tertinggi tidak punya arti
 * untuknya.
 */
interface MetricMeta {
  /** Judul kartu — bentuk yang sama dengan blade screen-160. */
  label: string
  /** Judul kolom tabel rekap — bentuk pendek, dengan satuannya di dalamnya. */
  short: string
  /** null = kolom ini memang TANPA satuan, dan tidak mencetak apa pun. */
  unit: string | null
  digits: number
}

const RAW_WATER_FLOW_META: MetricMeta = {
  label: 'Aliran Air Baku',
  short: 'Aliran Air Baku (m³/jam)',
  unit: 'm³/jam',
  digits: 1,
}
const CLARIFIED_WATER_FLOW_META: MetricMeta = {
  label: 'Aliran Air Jernih',
  short: 'Aliran Air Jernih (m³/jam)',
  unit: 'm³/jam',
  digits: 1,
}
const SOFTENER_INLET_PH_META: MetricMeta = {
  label: 'pH Masuk Softener',
  short: 'pH Masuk Softener',
  unit: null,
  digits: 2,
}
const SOFTENER_OUTLET_HARDNESS_META: MetricMeta = {
  label: 'Kesadahan Keluaran Softener',
  short: 'Kesadahan Keluaran Softener (ppm)',
  unit: 'ppm',
  digits: 1,
}
const ALUM_DOSE_META: MetricMeta = {
  label: 'Dosis Alum',
  short: 'Dosis Alum (kg/jam)',
  unit: 'kg/jam',
  digits: 1,
}
const POLYMER_DOSE_META: MetricMeta = {
  label: 'Dosis Polimer',
  short: 'Dosis Polimer (g/jam)',
  unit: 'g/jam',
  digits: 0,
}
const BOILER_FEED_TANK_TEMP_META: MetricMeta = {
  label: 'Suhu Tangki Air Umpan',
  short: 'Suhu Tangki Air Umpan (°C)',
  unit: '°C',
  digits: 1,
}
const BOILER_FEED_PH_META: MetricMeta = {
  label: 'pH Air Umpan Boiler',
  short: 'pH Air Umpan Boiler',
  unit: null,
  digits: 2,
}
const BOILER_FEED_TDS_META: MetricMeta = {
  label: 'TDS Air Umpan Boiler',
  short: 'TDS Air Umpan Boiler (ppm)',
  unit: 'ppm',
  digits: 0,
}

/**
 * Kunci kanonis adalah nama kolom process_water_details seperti yang
 * diterbitkan server. Kunci RINGKAS di sebelahnya adalah nama yang sama dalam
 * kosakata tech spec layar ini, dan keduanya MENUNJUK OBJEK YANG SAMA — bukan
 * salinan — sehingga mustahil keduanya menyimpan satuan yang berbeda untuk
 * satu kolom yang sama.
 */
const METRIC_META: Record<string, MetricMeta> = {
  raw_water_flow_m3h: RAW_WATER_FLOW_META,
  raw_water_flow: RAW_WATER_FLOW_META,
  clarified_water_flow_m3h: CLARIFIED_WATER_FLOW_META,
  clarified_water_flow: CLARIFIED_WATER_FLOW_META,
  softener_inlet_ph: SOFTENER_INLET_PH_META,
  softener_outlet_hardness_ppm: SOFTENER_OUTLET_HARDNESS_META,
  softener_outlet_hardness: SOFTENER_OUTLET_HARDNESS_META,
  alum_dosing_kgh: ALUM_DOSE_META,
  alum_dose: ALUM_DOSE_META,
  polymer_dosing_gh: POLYMER_DOSE_META,
  polymer_dose: POLYMER_DOSE_META,
  boiler_feed_tank_temp_c: BOILER_FEED_TANK_TEMP_META,
  boiler_feed_tank_temp: BOILER_FEED_TANK_TEMP_META,
  boiler_feed_water_ph: BOILER_FEED_PH_META,
  boiler_feed_ph: BOILER_FEED_PH_META,
  boiler_feed_tds_ppm: BOILER_FEED_TDS_META,
  boiler_feed_tds: BOILER_FEED_TDS_META,
}

/** Kolom yang digrafikkan: aliran air baku, dalam kedua kosakata. */
const RAW_WATER_FLOW_KEYS = ['raw_water_flow_m3h', 'raw_water_flow']

/**
 * EMPAT BLOK BERJUDUL, dalam urutan RANTAI PENGOLAHAN AIR dan dengan judul
 * SAMA PERSIS seperti versi web. Judul, urutan, dan keterangan tiap blok
 * ditetapkan di sini; KOLOMNYA TIDAK — kolom tiap blok DITURUNKAN DARI FIELD
 * `group` PADA RESPONS (lihat columnsOfBlock), supaya kolom baru dari migrasi
 * kelak masuk ke blok yang benar tanpa menyunting berkas ini.
 *
 * `groups` berisi LEBIH DARI SATU nilai karena satu blok yang sama dinamai
 * dua cara: ProcessWaterReportService::METRIC_GROUPS menerbitkan
 * water_flow / softener / chemical_dosing / boiler_feed, sementara tech spec
 * layar ini menamainya aliran / softener / dosis / air_umpan_boiler. Keduanya
 * diterima; yang TIDAK dilakukan adalah menebak blok dari nama kolom.
 *
 * PENGELOMPOKANNYA MURNI TATA LETAK: tidak satu pun angka dihitung per blok,
 * dan TIDAK ADA TOTAL PER BLOK — satuan di dalam satu blok pun berbeda (lihat
 * Dosis Kimia).
 */
interface MetricBlock {
  groups: string[]
  testid: string
  title: string
  hint: string
  note: 'softener' | 'dosis' | 'threshold' | null
}

const METRIC_BLOCKS: MetricBlock[] = [
  {
    groups: ['water_flow', 'aliran', 'aliran_air'],
    testid: 'pw-metric-block-aliran-air',
    title: 'Aliran Air',
    hint: 'hulu rantai pengolahan',
    note: null,
  },
  {
    groups: ['softener'],
    testid: 'pw-metric-block-softener',
    title: 'Softener',
    hint: 'pH di masukan, kesadahan di keluaran',
    note: 'softener',
  },
  {
    groups: ['chemical_dosing', 'dosis', 'dosis_kimia'],
    testid: 'pw-metric-block-dosis-kimia',
    title: 'Dosis Kimia',
    hint: 'dua satuan yang berbeda seribu kali',
    note: 'dosis',
  },
  {
    groups: ['boiler_feed', 'air_umpan_boiler'],
    testid: 'pw-metric-block-air-umpan-boiler',
    title: 'Air Umpan Boiler',
    hint: 'hilir rantai — pertanyaan pokok stasiun ini',
    note: 'threshold',
  },
]

/**
 * Kolom-kolom sebuah blok, DITURUNKAN DARI RESPONS: setiap entri `metrics`
 * membawa `group`-nya sendiri. Inilah yang membuat kartunya satu markup dan
 * bukan sembilan salinan. Urutan di dalam blok adalah urutan kunci pada
 * respons, yakni urutan ProcessWaterReportService::NUMERIC_METRICS.
 */
function columnsOfBlock(block: MetricBlock): string[] {
  return Object.keys(metrics.value).filter((column) =>
    block.groups.includes(metrics.value[column]?.group ?? ''),
  )
}

/**
 * Kesembilan kolom dalam urutan kanonis — judul kolom kedua tabel rekap. Juga
 * diturunkan dari respons, lewat keempat blok, bukan dari daftar yang dipaku.
 */
const METRIC_COLUMNS = computed<string[]>(() =>
  METRIC_BLOCKS.flatMap((block) => columnsOfBlock(block)),
)

/**
 * Satu kartu metrik siap render. Tidak ada satu pun angka yang diturunkan di
 * sini: min/avg/max dan penyebutnya diteruskan apa adanya dari
 * `metrics[kolom]`.
 *
 * Kolom yang tidak ada pada respons tetap menghasilkan kartu (nilai null,
 * penyebut 0): kartu yang hilang terbaca sebagai "tidak ada metrik ini"
 * padahal yang benar adalah "tidak ada yang mengukurnya".
 */
interface MetricCard {
  column: string
  label: string
  /** null = TIDAK mencetak satuan apa pun. */
  unit: string | null
  digits: number
  min: number | null
  avg: number | null
  max: number | null
  readingCount: number
}

function metricMetaOf(column: string): MetricMeta {
  // Kolom yang belum punya label manusiawi — misalnya kolom baru dari migrasi
  // — tetap dirender memakai nama kolomnya, bukan hilang dari layar. Satuannya
  // null, karena mengarangnya akan membuat angkanya salah dibaca.
  return METRIC_META[column] ?? { label: column, short: column, unit: null, digits: 2 }
}

/** Penyebut kartu INI SENDIRI: `reading_count` server, `count` tech spec. */
function readingCountOf(metric: ProcessWaterReportMetric | undefined): number {
  return metric?.reading_count ?? metric?.count ?? 0
}

function metricCardOf(column: string): MetricCard {
  const meta = metricMetaOf(column)
  const metric: ProcessWaterReportMetric | undefined = metrics.value[column]

  return {
    column,
    // Nilai SERVER menang bila ada; tabel view hanya cadangan, dan tidak ada
    // pemetaan satuan di antara keduanya. `unit` yang DIKIRIM null tetap null
    // — bukan jatuh ke tabel view — karena null adalah jawaban, bukan medan
    // yang hilang.
    label: metric?.label || meta.label,
    unit: metric?.unit !== undefined ? metric.unit : meta.unit,
    digits: meta.digits,
    min: metric?.min ?? null,
    avg: metric?.avg ?? null,
    max: metric?.max ?? null,
    readingCount: readingCountOf(metric),
  }
}

/** Dipakai sekali per blok oleh markup kartu yang ditulis SEKALI dan di-loop. */
function metricCardsOf(block: MetricBlock): MetricCard[] {
  return columnsOfBlock(block).map((column) => metricCardOf(column))
}

/** Judul kolom pendek untuk kedua tabel rekap. */
function metricShortLabel(column: string): string {
  return metricMetaOf(column).short
}

/** Jumlah desimal kolom itu, dipakai sel kedua tabel rekap. */
function metricDigits(column: string): number {
  return metricMetaOf(column).digits
}

/* ------------------------------------------------------------------ */
/* Kelengkapan pencatatan                                             */
/* ------------------------------------------------------------------ */

/**
 * Persen cakupan APA ADANYA dari respons. `percent` nama tech spec (null
 * ketika tidak ada slot yang diharapkan), `coverage_percent` nama server (0.0
 * pada keadaan yang sama). Keduanya dibaca; tidak satu pun diturunkan dari
 * filled_slots/expected_slots di sini.
 */
const coveragePercentValue = computed<number | null>(() => {
  const value = coverage.value

  if (!value) {
    return null
  }

  if (value.percent !== undefined) {
    return value.percent
  }

  return value.coverage_percent ?? null
})

/**
 * PENYEBUT CAKUPAN BELUM TERBENTUK — tidak ada unit ber-record, atau tidak ada
 * hari terhitung. INILAH satu-satunya keadaan yang membuat persen cakupan
 * dirender '—' beserta 'belum ada slot yang diharapkan'.
 *
 * Diputuskan dari expected_slots === 0 LEBIH DULU, bukan hanya dari null-nya
 * persen: payload server mengirim 0.0 pada keadaan ini, jadi memutuskannya
 * hanya dari null akan menampilkan nol persen justru di tempat tanda pisah
 * seharusnya. Sebaliknya, periode yang PUNYA record tetapi tanpa satu slot
 * terisi menghasilkan persen nol — dan itu benar, karena penyebutnya ada.
 *
 * PEMETAAN INI PRESENTASI, BUKAN ARITMETIKA: tidak ada bilangan baru yang
 * lahir darinya.
 */
const coverageDenominatorMissing = computed(
  () => (coverage.value?.expected_slots ?? 0) === 0 || coveragePercentValue.value === null,
)

/** Jumlah unit Process Water: nama server, lalu nama tech spec. */
const coverageUnitCount = computed<number | null>(() => {
  const value = coverage.value

  if (!value) {
    return null
  }

  return value.process_water_unit_count ?? value.unit_count ?? null
})

/** Status periode adalah KETERANGAN, bukan gerbang: ekspor tetap aktif. */
const periodStatus = computed(
  () => summary.value?.period?.status ?? selectedPeriod.value?.status ?? null,
)

/**
 * Periode masih berjalan, sehingga penyebut hari BERHENTI DI HARI INI.
 * `period_running` datang dari server; bila kosakata payload tidak
 * membawanya, status periode 'open' adalah penanda yang sama — dan ia pun DARI
 * SERVER. Jam perangkat tidak pernah ikut memutuskan, karena perangkat yang
 * jamnya bergeser akan menerbitkan keterangan yang berselisih dengan angkanya.
 */
const periodRunning = computed(() => {
  const value = coverage.value

  if (value?.period_running !== undefined) {
    return Boolean(value.period_running)
  }

  return (periodStatus.value ?? '') === 'open'
})

/* ------------------------------------------------------------------ */
/* Rekap per inspector — KODE MENTAH, nol pemetaan nama                */
/* ------------------------------------------------------------------ */

/**
 * Satu baris rekap siap render. `code` null berarti baris 'tidak tercatat',
 * dan baris itu BERDIRI SENDIRI — tidak pernah dilipat ke baris berkode mana
 * pun: slot terisi yang kolom inspector-nya kosong adalah fakta yang berbeda
 * dari slot yang diatribusikan kepada seseorang, dan slot itu TETAP terhitung
 * pada kelengkapan karena `inspector_id` kolom identitas.
 */
interface InspectorRow {
  code: string | null
  slots: number
}

function inspectorSlotsOf(entry: ProcessWaterReportInspectorEntry): number {
  return entry.filled_slots ?? entry.slots ?? 0
}

/**
 * Kedua bentuk payload dinormalkan menjadi satu daftar baris:
 *
 *   - BENTUK SERVER (objek): `entries` berkode, lalu SATU baris 'tidak
 *     tercatat' dari kunci `not_recorded` yang terpisah itu.
 *   - BENTUK TECH SPEC (daftar): baris ber-inspector_id null SUDAH menjadi
 *     baris 'tidak tercatat'-nya sendiri, jadi daftarnya dipakai apa adanya —
 *     dan jumlah barisnya sama dengan panjang payload, tidak bertambah satu.
 *
 * Urutannya adalah urutan payload. TIDAK ADA pengurutan menurut jumlah slot:
 * rekap ini ada untuk menelusuri slot yang belum terisi, bukan untuk menilai
 * orang, jadi ia tidak boleh terbaca sebagai tabel peringkat.
 *
 * NOL PEMETAAN NAMA. `inspector_id` tidak punya relasi ke tabel User —
 * isinya teks bebas yang tidak merujuk master mana pun — jadi kodenya
 * dipulangkan MENTAH apa adanya dan tidak ada satu pun pencarian nama di
 * berkas ini.
 */
const inspectorRows = computed<InspectorRow[]>(() => {
  const recap = summary.value?.inspector_recap

  if (!recap) {
    return []
  }

  if (Array.isArray(recap)) {
    return recap.map((entry) => ({
      code: entry.inspector_id ?? null,
      slots: inspectorSlotsOf(entry),
    }))
  }

  const rows: InspectorRow[] = (recap.entries ?? []).map((entry) => ({
    code: entry.inspector_id ?? null,
    slots: inspectorSlotsOf(entry),
  }))

  rows.push({ code: null, slots: recap.not_recorded ?? 0 })

  return rows
})

/**
 * PENYEBUT porsi tiap baris: slot TERCATAT dari respons. `recorded_slots`
 * diterbitkan server di dalam blok rekap; bila kosakata payload tidak
 * membawanya, jumlah slot terisi pada blok kelengkapan adalah angka yang sama
 * (invarian server: sum(entries) + not_recorded === recorded_slots ===
 * filled_slots). TIDAK PERNAH expected_slots, yang akan mencampur slot tak
 * tersentuh ke dalam porsi baris yang justru tercatat.
 */
const inspectorRecordedSlots = computed<number>(() => {
  const recap = summary.value?.inspector_recap

  if (recap && !Array.isArray(recap) && recap.recorded_slots !== undefined) {
    return recap.recorded_slots
  }

  return coverage.value?.filled_slots ?? 0
})

/* ------------------------------------------------------------------ */
/* Rekap per unit & rekap harian                                       */
/* ------------------------------------------------------------------ */

/** `by_unit` nama server, `unit_recap` nama tech spec — rekap yang sama. */
const unitRows = computed<ProcessWaterReportUnitRow[]>(
  () => summary.value?.by_unit ?? summary.value?.unit_recap ?? [],
)

const dailyRows = computed<ProcessWaterReportDailyRow[]>(() => summary.value?.daily ?? [])

/** Slot terisi satu baris rekap: `reading_rows` server, `filled_slots` tech spec. */
function unitFilledSlots(row: ProcessWaterReportUnitRow): number | null {
  return row.reading_rows ?? row.filled_slots ?? null
}

function dailyFilledSlots(row: ProcessWaterReportDailyRow): number | null {
  return row.reading_rows ?? row.filled_slots ?? null
}

/**
 * Rata-rata satu kolom pada satu baris rekap harian. `metrics_avg` adalah
 * bentuk server; `raw_water_flow_avg` adalah jalur ringkas kosakata tech spec
 * dan ia HANYA berlaku untuk kolom aliran air baku. Dibaca, bukan dihitung.
 */
function dailyAvgOf(row: ProcessWaterReportDailyRow, column: string): number | null {
  const published = row.metrics_avg?.[column]

  if (published) {
    return published.avg ?? null
  }

  if (RAW_WATER_FLOW_KEYS.includes(column)) {
    return row.raw_water_flow_avg ?? null
  }

  return null
}

/* ------------------------------------------------------------------ */
/* Tren harian — SATU, atas aliran air baku                            */
/* ------------------------------------------------------------------ */

/**
 * Kolom yang digrafikkan, DIPILIH DARI RESPONS: kunci aliran air baku yang
 * benar-benar ada pada `metrics`. Tanpa salah satu pun, nama kanonis server
 * dipakai sebagai cadangan supaya label satuannya tetap terbaca.
 */
const trendColumn = computed<string>(
  () =>
    Object.keys(metrics.value).find((column) => RAW_WATER_FLOW_KEYS.includes(column)) ??
    RAW_WATER_FLOW_KEYS[0],
)

/**
 * Titik tren DARI RESPONS. `daily_trend` adalah bentuk server (satu seri per
 * kolom, masing-masing {date, avg, reading_count}); bila payload tidak
 * membawanya, seri yang sama dibaca dari rekap harian lewat dailyAvgOf(). Yang
 * dikerjakan di sini hanya MEMBACA ULANG bentuknya — tidak satu rata-rata pun
 * dihitung, tidak ada pengurutan, dan tanggal tanpa pembacaan menghasilkan
 * null, BUKAN nol.
 */
const trendPoints = computed<ProcessWaterReportTrendPoint[]>(() => {
  const published = summary.value?.daily_trend

  if (published) {
    const key =
      Object.keys(published).find((column) => RAW_WATER_FLOW_KEYS.includes(column)) ??
      Object.keys(published)[0]

    if (key && Array.isArray(published[key])) {
      return published[key]
    }
  }

  return dailyRows.value.map((row) => ({
    date: row.date,
    avg: dailyAvgOf(row, trendColumn.value),
  }))
})

/**
 * Nilai terendah/tertinggi SERIES untuk SKALA TAMPILAN batang, ditiru baris
 * demi baris dari blade screen-160 (yang menurunkan $minTren/$maksTren dengan
 * cara yang sama persis, sehingga kedua layar menggambar batang yang sama).
 *
 * Keduanya DILABELI "dari rata-rata harian" di layar — bukan dipamerkan
 * sebagai terendah/tertinggi periode, yang punya angkanya sendiri di
 * metrics[kolom].min/max atas pembacaan mentah. Tidak ada pewarnaan
 * aman/bahaya yang diturunkan darinya.
 */
function trendSeries(): number[] {
  const values: number[] = []

  for (const point of trendPoints.value) {
    if (point.avg !== null && point.avg !== undefined) {
      values.push(point.avg)
    }
  }

  return values
}

const trendLowest = computed<number | null>(() => {
  const values = trendSeries()

  return values.length === 0 ? null : Math.min(...values)
})

const trendHighest = computed<number | null>(() => {
  const values = trendSeries()

  return values.length === 0 ? null : Math.max(...values)
})

/**
 * Tinggi batang = 28% + 72% × (nilai − terendah) / (tertinggi − terendah) —
 * rumus yang sama dengan $tinggiBatang pada blade screen-160.
 *
 * SUMBU TEGAK SENGAJA TIDAK DIMULAI DARI NOL, dan hal itu DINYATAKAN di
 * kartunya: batang membandingkan tanggal satu sama lain, bukan besaran mutlak.
 * Ini semata skala tampilan — tidak ada nilai yang ditandai aman/bahaya
 * karenanya, dan titik tanpa pembacaan menghasilkan batang KOSONG (0%), bukan
 * batang nol yang terbaca sebagai hasil ukur.
 */
function trendBarHeight(value: number | null): string {
  const lowest = trendLowest.value
  const highest = trendHighest.value

  if (value === null || value === undefined || lowest === null || highest === null) {
    return '0%'
  }

  if (highest <= lowest) {
    return '100%'
  }

  return `${28 + (72 * (value - lowest)) / (highest - lowest)}%`
}

/* ------------------------------------------------------------------ */
/* Keadaan layar                                                       */
/* ------------------------------------------------------------------ */

/**
 * LURING IKUT MENUTUP SELURUH ANGKA, dan itu penyimpangan yang disengaja dari
 * LaporanEngineRoomView.vue: layar ini menyatakan laporan perlu jaringan DAN
 * tidak merender satu angka pun bersamaan dengan pernyataan itu, karena angka
 * yang tampil di sebelah pernyataan "perlu jaringan" adalah angka yang tidak
 * dapat dipertanggungjawabkan asalnya.
 */
const hasReport = computed(
  () =>
    Boolean(summary.value) &&
    !isOffline.value &&
    !networkError.value &&
    !errorMessage.value &&
    !millNotLinked.value &&
    !productionLineRejected.value &&
    !productionLineRequired.value &&
    !noMillForAccount.value,
)

/**
 * Periode yang sah tetapi tanpa satu slot terisi. `has_data` dari server
 * didahulukan; bila kosakata payload tidak membawanya, jumlah slot terisi pada
 * blok kelengkapan menjawab pertanyaan yang sama. KESEMBILAN KARTU dan BLOK
 * KELENGKAPAN tetap dirender pada keadaan ini — justru itu yang menjelaskan
 * kekosongannya, dan yang memperlihatkan bahwa parameternya ADA dan tidak
 * terukur.
 */
const emptyPeriod = computed(() => {
  if (!summary.value) {
    return false
  }

  if (summary.value.has_data !== undefined) {
    return !summary.value.has_data
  }

  return (coverage.value?.filled_slots ?? 0) === 0
})

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
 * Persen kelengkapan cakupan, satu desimal. Tanda pisah HANYA ketika
 * penyebutnya belum terbentuk — keputusannya diambil pemanggil lewat
 * coverageDenominatorMissing, bukan dari null-nya nilai ini.
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
 * PORSI SEBUAH BARIS REKAP INSPECTOR — satu-satunya pembagian atas angka
 * laporan di berkas ini, dan ia ada karena payload TIDAK menerbitkan porsi
 * siap cetak.
 *
 * Rumus, pembulatan, dan pemisah desimalnya DISALIN PERSIS dari helper $porsi
 * pada blade screen-160 (number_format(100 * count / recordedSlots, 1)),
 * supaya ponsel tidak mungkin mencetak porsi yang berbeda dari web untuk
 * payload yang sama. PENYEBUTNYA slot TERCATAT DARI RESPONS. Penyebut nol
 * menghasilkan '—', bukan nol persen.
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

/** Tanggal pendek untuk judul kejadian teks bebas. */
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
 * Tidak ada penormalan di sini — penormalan jam adalah tempat lahirnya selisih
 * antara ponsel dan web.
 */
function formatTimeSlot(value: string | null | undefined): string {
  return value ? value : '-'
}

/**
 * Penamaan unit Process Water yang kosong. `process_water_id` adalah kolom
 * TEKS yang diketik di layar input dan bukan kunci asing, jadi nilai kosong
 * mungkin saja sampai ke sini — barisnya tetap diberi label alih-alih dirender
 * kosong, karena sel tanpa label membuat seluruh baris terbaca sebagai cacat
 * tampilan.
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

function periodOptionLabel(period: ProcessWaterReportPeriodOption): string {
  return period.station_type_label ? `${period.station_type_label} — ${period.name}` : period.name
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
  <main class="laporan-pr-view" data-testid="laporan-process-water-mobile">
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
          {{
            aiAssistantStore.bubbleEnabled ? 'Nonaktifkan Bubble Chat AI' : 'Aktifkan Bubble Chat AI'
          }}
        </button>
        <button type="button" class="nav-menu-item" @click="openAiAssistant">Bantuan AI</button>
        <button type="button" class="nav-menu-item" data-testid="nav-menu-logout" @click="onLogout">
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
        <span aria-current="page">Laporan Process Water</span>
      </nav>
      <h1 class="screen-title">Laporan Process Water</h1>
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
             line". DIRENDER WALAU DAFTARNYA HANYA BERISI SATU ENTRI: layar ini
             tidak memilihkan line secara diam-diam, bahkan ketika mill hanya
             punya satu. -->
        <FilterSelectField v-if="productionLines.length > 0" label="Production Line" icon="line">
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

      <!-- Laporan ini PERLU JARINGAN: tidak ada hasil yang disimpan ke
           perangkat dan tidak ada mode luring, jadi keadaannya dinyatakan
           alih-alih ditutupi angka basi tanpa penanda — dan selama ia berlaku
           NOL angka dirender. -->
      <p v-if="isOffline" class="notice notice--warning" role="alert" data-testid="offline-notice">
        Perangkat sedang tidak terhubung jaringan. Laporan periode
        <strong>memerlukan jaringan</strong> — angkanya dihitung di server setiap kali dibaca dan
        tidak disimpan di perangkat ini, jadi layar ini tidak menampilkan satu angka pun dari
        pemuatan sebelumnya. Sambungkan kembali jaringan, lalu coba lagi.
      </p>

      <!-- Akun terikat mill yang business_unit_id-nya NULL (422 dari server).
           BUKAN galat jaringan, dan sistem tidak menawarkan daftar mill. -->
      <p
        v-if="millNotLinked"
        class="notice notice--warning"
        role="alert"
        data-testid="mill-not-linked"
      >
        Akun Anda belum terhubung ke mill mana pun, sehingga tidak ada laporan yang dapat
        ditampilkan. Silakan hubungi Admin untuk menghubungkan akun Anda ke sebuah mill. Ini bukan
        masalah jaringan, jadi mencoba lagi tidak akan mengubah apa pun.
      </p>

      <!-- 403: line (atau periode) milik mill lain — termasuk line basi yang
           masih diingat perangkat. Diperlakukan sebagai "pilih line lagi",
           BUKAN galat jaringan. Klien tidak memeriksa kepemilikan sendiri. -->
      <p
        v-if="productionLineRejected"
        class="notice notice--warning"
        role="alert"
        data-testid="production-line-rejected"
      >
        Production Line yang terakhir dipakai perangkat ini tidak lagi tersedia untuk akun Anda —
        mungkin sudah dihapus atau milik mill lain. Silakan pilih Production Line kembali pada
        pemilih di atas. Tidak satu angka pun dari mill lain ditampilkan.
      </p>

      <p v-if="productionLineRequired" class="notice" data-testid="production-line-required-hint">
        Pilih Production Line terlebih dahulu untuk menampilkan laporan. Angka laporan dihitung per
        Production Line, sehingga tidak ada pilihan gabungan lintas line — dan selama belum ada
        line yang berlaku, <strong>tidak satu permintaan pun dikirim ke server</strong>, karena
        penjagaannya ada di fungsi pemuatan dan bukan hanya di tampilan. Pilihan itu tidak pernah
        dibuat untuk Anda, bahkan ketika mill ini hanya punya satu line.
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
        Mill ini belum memiliki periode pelaporan yang mencakup stasiun Process Water. Silakan
        hubungi Admin untuk membuat periode pelaporan terlebih dahulu.
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

      <p
        v-else-if="errorMessage"
        class="notice notice--error"
        role="alert"
        data-testid="error-message"
      >
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
          Belum ada slot Process Water yang terisi pada periode ini untuk Production Line yang
          dipilih. Kesembilan kartu di bawah tetap ditampilkan dengan penyebut nol, supaya terlihat
          bahwa parameternya ada dan tidak terukur — bukan bahwa hasilnya nol.
        </p>

        <!-- ===== KELENGKAPAN PENCATATAN — PALING ATAS, SEBELUM SATU KARTU PUN
             Bukan catatan kaki: kesembilan kolom ukur seluruhnya nullable, jadi
             periode yang terisi kurang dari separuh pun menghasilkan rata-rata
             yang terlihat rapi. Di layar sempit ia satu-satunya bagian yang
             pasti terlihat tanpa menggulir, dan ia menentukan apakah angka di
             bawahnya layak dipercaya. TETAP digambar meski periode kosong —
             justru itu yang menjelaskan kekosongannya. -->
        <section class="detail-section" data-testid="pw-coverage">
          <h2 class="section-title">Kelengkapan Pencatatan</h2>
          <p class="section-note">
            Baca ini lebih dulu — sembilan kartu di bawah bersandar padanya.
          </p>

          <div class="metric-stack">
            <div class="metric-card">
              <span class="metric-label">Slot Waktu Terisi Sepanjang Periode</span>
              <!-- DIRENDER APA ADANYA: jumlah slot terisi dapat MELEBIHI
                   penyebut setiap kartu, dan itu benar — tidak di-clamp, tidak
                   disembunyikan, dan tanpa peringatan apa pun. -->
              <span class="metric-value" data-testid="pw-coverage-slots">
                {{ formatCount(coverage?.filled_slots) }} dari
                {{ formatCount(coverage?.expected_slots) }} slot
              </span>
              <!-- Tanda pisah HANYA ketika penyebutnya belum terbentuk. Elemen
                   ini memuat PERSENNYA SAJA, supaya ia dapat diasersi sendiri
                   tanpa ikut memindai keterangan di sekitarnya. -->
              <span
                class="metric-value"
                :class="{ 'metric-value--na': coverageDenominatorMissing }"
                data-testid="pw-coverage-percent"
              >
                {{
                  coverageDenominatorMissing
                    ? '—'
                    : formatCompletenessPercent(coveragePercentValue)
                }}
              </span>
              <div
                v-if="!coverageDenominatorMissing"
                class="completeness-track"
                role="img"
                :aria-label="`Kelengkapan pencatatan ${formatCompletenessPercent(coveragePercentValue)}`"
              >
                <div
                  class="completeness-fill"
                  :style="{ width: `${coveragePercentValue ?? 0}%` }"
                ></div>
              </div>
              <span
                v-if="coverageDenominatorMissing"
                class="metric-note"
                data-testid="pw-coverage-no-expected-note"
              >
                Belum ada slot yang diharapkan: tidak ada unit Process Water ber-record pada
                periode dan line ini, atau periodenya belum mulai. Karena penyebutnya belum
                terbentuk, persennya &ldquo;&mdash;&rdquo; dan bukan angka nol berpersen — nol
                persen akan terbaca seolah pencatatan gagal, padahal yang belum ada adalah
                penyebutnya.
              </span>
            </div>

            <div class="metric-card">
              <span class="metric-label">Pembentuk Penyebut</span>
              <span class="metric-value" data-testid="pw-coverage-denominator">
                {{ formatCount(coverageUnitCount) }} unit &times;
                {{ formatCount(coverage?.days_counted) }} hari &times;
                {{ formatCount(coverage?.slots_per_unit_per_day) }} slot
              </span>
              <span class="metric-note" data-testid="pw-coverage-formula">
                Slot yang diharapkan = {{ formatCount(coverageUnitCount) }} unit Process Water
                &times; {{ formatCount(coverage?.days_counted) }} hari &times;
                {{ formatCount(coverage?.slots_per_unit_per_day) }} slot =
                {{ formatCount(coverage?.expected_slots) }}. Jumlah unit adalah unit yang
                BENAR-BENAR punya record pada periode dan line ini, bukan jumlah stasiun
                terdaftar; dan 24 slot kanonis (07:00&ndash;06:00 keesokan hari) diambil dari kisi
                slot layar input itu sendiri, bukan dikarang di sini.
              </span>
            </div>

            <div class="metric-card">
              <span class="metric-label">Hari Dihitung</span>
              <span class="metric-value" data-testid="pw-coverage-days">
                {{ formatCount(coverage?.days_counted) }} hari<template
                  v-if="coverage?.days_in_period"
                >
                  dari {{ formatCount(coverage?.days_in_period) }} hari periode</template
                >
              </span>
              <!-- URUTANNYA PENTING: days_counted DIPERIKSA LEBIH DULU. Server
                   menandai periode yang BELUM MULAI sebagai masih berjalan
                   juga, jadi memeriksa periode-berjalan lebih dulu membuat
                   keterangan "belum mulai" tidak pernah terender. -->
              <span
                v-if="!coverage?.days_counted"
                class="metric-note"
                data-testid="pw-coverage-not-started-note"
              >
                Periode belum mulai, sehingga belum ada hari yang dapat dijadikan pembagi —
                persennya &ldquo;&mdash;&rdquo;.
              </span>
              <span
                v-else-if="periodRunning"
                class="metric-note"
                data-testid="pw-coverage-running-note"
              >
                <strong>Dihitung sampai HARI INI, karena periode masih berjalan</strong> —
                {{ formatCount(coverage?.days_counted) }} hari periode sudah lewat. Hari yang belum
                terjadi tidak mungkin tercatat, jadi tidak ikut menjadi penyebut. Seluruh angka di
                bawah dihitung dari <strong>pembacaan yang ada</strong>, bukan dari periode penuh:
                slot yang tidak terisi tidak diperlakukan sebagai nol, melainkan tidak ikut
                dihitung sama sekali.
              </span>
              <span class="metric-note" data-testid="pw-draft-note">
                Record berstatus draft IKUT terhitung pada seluruh angka laporan ini.
              </span>
            </div>
          </div>

          <!-- ATURAN SEBELAS KOLOM, DIRENDER VERBATIM DARI SERVER. Ponsel tidak
               menyusun kalimatnya sendiri — kalimat yang disusun dua kali di
               dua platform adalah kalimat yang akan berselisih. Elemen ini
               karena itu memuat KALIMAT SERVER SAJA, tanpa satu kata tambahan
               di dalamnya. -->
          <p
            v-if="noteFilledRule"
            class="section-note"
            data-testid="pw-note-filled-rule"
          >{{ noteFilledRule }}</p>

          <p class="section-note" data-testid="pw-filled-rule-consequence">
            Konsekuensi aturan di atas <strong>berpasangan dan berlawanan</strong>, dan keduanya
            harus dapat dibedakan pembaca: slot yang <strong>hanya</strong> mengisi kolom teks
            bebas <strong>TETAP terhitung terisi</strong>, sementara slot yang
            <strong>hanya</strong> mengisi kolom identitas giliran kerja dan kode inspector
            <strong>TIDAK terhitung terisi</strong>. Karena kolom teks ikut terhitung,
            <strong>jumlah slot terisi di atas dapat MELEBIHI penyebut setiap kartu</strong> di
            bawah — yang dilampaui adalah penyebut <strong>kartu</strong>, bukan penyebut
            kelengkapan. Selisihnya <strong>bukan galat dan tidak dikoreksi</strong>, melainkan
            dinyatakan di sini.
          </p>

          <p class="section-note" data-testid="pw-empty-period-note">
            Bila periode ternyata tidak memuat <strong>satu pun</strong> pembacaan, kesembilan
            kartu di bawah menampilkan <strong>tidak tersedia</strong> dengan penyebut nol —
            <strong>bukan nol</strong> — dan <strong>blok kelengkapan ini tetap ditampilkan</strong>.
            Kelengkapan pencatatan adalah bagian dari laporan, bukan catatan kaki, karena itu ia
            dirender <strong>paling atas</strong>.
          </p>
        </section>

        <!-- ===== SEMBILAN KARTU DALAM EMPAT BLOK BERJUDUL =====
             Markup kartunya ditulis SEKALI dan di-loop atas `metrics` yang
             dikelompokkan menurut field `group` DARI RESPONS — bukan atas
             daftar nama kolom yang dipaku, dan bukan sembilan salinan markup:
             pengulangan markup berarti sembilan kesempatan kehilangan penyebut
             atau MEWARISI penyebut kartu lain.

             KEEMPAT BLOK DIRENDER TERBUKA, TANPA SATU KONTROL LIPAT: melipatnya
             menyembunyikan penyebut tiap kartu, dan penyebut itu yang paling
             perlu dilihat di layar ini. -->
        <section
          v-for="(block, blockIndex) in METRIC_BLOCKS"
          :key="block.testid"
          class="detail-section"
          :data-testid="block.testid"
          :data-metric-group="block.groups[0]"
        >
          <h2 class="section-title">{{ block.title }}</h2>
          <p class="section-note">
            Blok {{ blockIndex + 1 }} dari {{ METRIC_BLOCKS.length }} &middot;
            {{ formatCount(metricCardsOf(block).length) }} kartu &middot; {{ block.hint }}
          </p>

          <div class="metric-stack">
            <article
              v-for="card in metricCardsOf(block)"
              :key="card.column"
              class="metric-card"
              :data-testid="`pw-metric-card-${card.column}`"
              :data-metric="card.column"
            >
              <div class="metric-label-row">
                <span class="metric-label" :data-testid="`pw-metric-label-${card.column}`">
                  {{ card.label }}
                </span>
                <!-- SATUAN PADA KARTUNYA SENDIRI, dan ini yang paling mengikat
                     di blok Dosis Kimia: alum kg/jam melawan polimer g/jam,
                     skala berbeda SERIBU KALI, dan di ponsel kedua kartu tidak
                     pernah terlihat bersamaan. Kartu ber-satuan null TIDAK
                     MERENDER ELEMEN INI SAMA SEKALI — bukan '-', bukan '—',
                     dan bukan satuan karangan: kedua kartu pH justru yang
                     null, dan pH memang besaran tanpa satuan. -->
                <span
                  v-if="card.unit"
                  class="metric-unit-pill"
                  :data-testid="`pw-metric-unit-${card.column}`"
                  >{{ card.unit }}</span
                >
              </div>

              <div class="metric-row">
                <span class="metric-row-item">
                  <small>terendah</small>
                  <b
                    :class="{ 'metric-value--na': card.min === null }"
                    :data-testid="`pw-metric-min-${card.column}`"
                    >{{ formatDecimal(card.min, card.digits) }}</b
                  >
                </span>
                <span class="metric-row-item">
                  <small>rata-rata</small>
                  <b
                    :class="{ 'metric-value--na': card.avg === null }"
                    :data-testid="`pw-metric-avg-${card.column}`"
                    >{{ formatDecimal(card.avg, card.digits) }}</b
                  >
                </span>
                <span class="metric-row-item">
                  <small>tertinggi</small>
                  <b
                    :class="{ 'metric-value--na': card.max === null }"
                    :data-testid="`pw-metric-max-${card.column}`"
                    >{{ formatDecimal(card.max, card.digits) }}</b
                  >
                </span>
              </div>

              <!-- PENYEBUT MILIK KARTU INI SENDIRI. Kesembilan kolom nullable
                   dan terisi saling bebas, jadi satu penyebut bersama akan
                   salah untuk setidaknya delapan di antaranya — dan di ponsel
                   cacat itu lebih sulit terlihat karena kartunya tidak pernah
                   berdampingan. Elemen penyebut memuat ANGKANYA SAJA supaya ia
                   dapat diasersi per kartu. -->
              <span class="metric-note">
                dari
                <b :data-testid="`pw-metric-count-${card.column}`">{{
                  formatCount(card.readingCount)
                }}</b>
                pembacaan
                <small>
                  (slot yang mengisi kolom ini sendiri; slot yang tidak mengisinya tidak
                  mengukurnya nol — ia tidak mengukurnya sama sekali)
                </small>
              </span>
              <span
                v-if="card.readingCount === 0"
                class="metric-note metric-value--na"
                :data-testid="`pw-metric-unavailable-${card.column}`"
              >
                tidak tersedia — kolom ini tidak terisi pada satu slot pun sepanjang periode dan
                line ini. Kartunya <strong>tetap ada</strong> dengan penyebut nol, bukan dihapus
                dari bloknya: kartu yang hilang tidak dapat dibedakan dari kartu yang belum pernah
                ada.
              </span>
            </article>
          </div>

          <p v-if="block.note === 'softener'" class="section-note" data-testid="pw-softener-note">
            Kedua kartu blok ini mengukur <strong>dua hal berbeda di dua titik berbeda</strong> —
            pH di <strong>MASUKAN</strong> softener, kesadahan di <strong>KELUARAN</strong> — jadi
            keduanya tidak saling menggantikan dan penyebutnya
            <strong>tidak dipinjamkan</strong>. Kartu pH <strong>tidak punya pil satuan</strong>,
            dan itu bukan kelalaian: pH memang besaran tanpa satuan, dan memberinya satuan karangan
            demi keseragaman dengan kartu lain akan menyatakan sesuatu yang tidak benar.
          </p>

          <!-- KETERANGAN SATUAN DIRENDER DI DALAM BLOK DOSIS KIMIA, di tempat
               yang diterangkannya — keterangan sekali di atas halaman sudah
               tergulir jauh ketika blok yang diterangkannya akhirnya terlihat
               di layar sempit. -->
          <p v-if="block.note === 'dosis'" class="section-note" data-testid="pw-dosis-unit-note">
            <strong>Satuan tercetak pada KEDUA kartu blok ini, dan di ponsel tuntutan itu lebih
            keras daripada di web.</strong>
            Alum dalam <strong>kg/jam</strong>, polimer dalam <strong>g/jam</strong>. Keduanya
            bernama &lsquo;dosis&rsquo; dan berada di blok yang sama; di web keduanya
            <strong>berdampingan</strong> sehingga satuannya terbaca sekaligus, tetapi di ponsel
            pembaca melihat <strong>satu kartu pada satu waktu</strong> dan kedekatan itu hilang.
            Angka tanpa satuan pada kartu kedua akan dibaca dengan
            <strong>skala kartu pertama</strong> &mdash; dan kedua skala itu berbeda
            <strong>seribu kali</strong>. Keduanya juga <strong>tidak</strong> disamakan ke satu
            satuan demi kerapian: menuliskan polimer sebagai 0,42 kg/jam akan menyembunyikan
            ketelitian yang dicatat di lapangan. Dan keduanya <strong>tidak dijumlahkan</strong>
            maupun dibandingkan &mdash; &lsquo;total dosis kimia&rsquo; bukan besaran yang punya
            arti.
          </p>

          <!-- PERNYATAAN KETIADAAN PENANDAAN DI LUAR BATAS, DIRENDER UTUH dan
               MENEMPEL PADA BLOK INI — bukan sebagai catatan kaki halaman.
               Justru di blok inilah godaan menandai paling besar, karena pH dan
               TDS air umpan boiler punya batas yang dikenal sangat luas DI LUAR
               aplikasi ini. TANPA text-overflow, TANPA line-clamp, dan TANPA
               "baca selengkapnya": keterangan yang terpotong di tengah berhenti
               menerangkan apa pun, dan di layar sempit itulah godaan
               terbesarnya. -->
          <p
            v-if="block.note === 'threshold'"
            class="section-note"
            data-testid="pw-no-threshold-note"
          >
            <strong>
              TIDAK ADA satu pun nilai di layar ini yang ditandai di luar batas, dan pernyataan ini
              dirender UTUH — tidak dipendekkan, tidak dilipat, dan tidak dipotong dengan elipsis
              demi lebar layar, karena keterangan yang dipotong di tengah berhenti menerangkan apa
              pun.
            </strong>
            Stasiun Process Water <strong>tidak punya tabel master target</strong> — tidak ada satu
            baris acuan yang bisa dibandingkan dengan satu pun dari sembilan kartu di atas, baik
            per unit maupun per line. Dan justru di sinilah godaan menandai paling besar di antara
            seluruh laporan stasiun: <strong>pH air umpan boiler dan TDS air umpan boiler punya
            batas yang dikenal SANGAT luas DI LUAR aplikasi ini</strong> — setiap pedoman air umpan
            boiler memuat angkanya, dan hampir setiap pembaca laporan ini sudah membawa angka itu
            di kepalanya. Menurunkan ambang dari <strong>data periode ini sendiri</strong> —
            rata-rata &plusmn; dua simpangan baku, atau persentil atas pembacaan yang ada — akan
            menghasilkan garis yang terlihat berwibawa, lalu
            <strong>dibaca sebagai batas keselamatan boiler</strong>, padahal ia hanya
            <strong>turunan statistik</strong> dari pencatatan yang belum tentu lengkap. Ambang
            semacam itu akan ikut bergeser setiap kali kelengkapan pencatatan berubah, dan itu
            bukan sifat yang boleh dimiliki sebuah batas keselamatan. Karena itu kesembilan angka
            ditampilkan <strong>apa adanya</strong>: tanpa warna peringatan, tanpa ikon, tanpa kata
            &lsquo;tinggi&rsquo; atau &lsquo;rendah&rsquo;, dan tanpa satu pun sel yang ditandai —
            <strong>termasuk nilai yang jauh di luar batas yang dikenal umum</strong>, yang tetap
            tampil polos di layar, di Rekap Temuan, dan di berkas ekspor. Penilaian layak atau
            tidak layak tetap di tangan pembacanya, dan laporan ini tidak berpura-pura
            memilikinya.
          </p>
        </section>

        <!-- Keterangan yang membuat pengelompokan empat blok dan kartu yang
             selalu ada dapat dibaca. Ketiadaan yang tidak dijelaskan terbaca
             sebagai fitur yang belum selesai. -->
        <section class="detail-section" data-testid="pw-metric-blocks-note">
          <h2 class="section-title">Cara Membaca Kesembilan Kartu</h2>
          <p class="section-note">
            Kesembilan kartu di atas dikelompokkan menjadi
            <strong>empat blok berjudul yang mengikuti rantai pengolahan air</strong> —
            <strong>1 Aliran Air &rarr; 2 Softener &rarr; 3 Dosis Kimia &rarr; 4 Air Umpan
            Boiler</strong> — dengan <strong>judul dan urutan yang sama persis seperti versi
            web</strong>, bukan mengikuti urutan kolom di basis data. Pembaca yang berpindah dari
            ponsel ke web karena itu tidak perlu mempelajari ulang peta halamannya, dan pembaca
            yang menemukan angka air umpan boiler yang tidak ia harapkan dapat
            <strong>bergerak mundur ke tahap sebelumnya</strong> tanpa perlu tahu nama kolom mana
            pun. Pengelompokan itu <strong>murni tata letak</strong>: tidak satu pun angka dihitung
            per blok, dan <strong>tidak ada total per blok</strong>, karena satuan di dalam satu
            blok pun berbeda. Keempat blok dibiarkan
            <strong>terbuka dan tidak dapat dilipat</strong>: melipatnya memperpendek gulir tetapi
            menyembunyikan penyebut tiap kartu, dan penyebut itulah yang paling perlu dilihat di
            layar ini.
          </p>
          <p class="section-note" data-testid="pw-own-denominator-note">
            <strong>Setiap kartu membawa penyebutnya sendiri.</strong> Kesembilan kolom ukur
            nullable dan terisi saling bebas, jadi penyebut yang sama pada kesembilan kartu adalah
            cacat — dan di ponsel cacat itu <strong>lebih sulit terlihat daripada di web</strong>,
            karena kartunya tidak pernah berdampingan. Kartu yang tidak pernah terisi
            <strong>tetap dirender</strong> dengan keterangan &lsquo;tidak tersedia&rsquo; dan
            penyebut nol pembacaan.
          </p>
        </section>

        <!-- ===== Tren harian aliran air baku — RATA-RATA per tanggal =====
             SATU-SATUNYA tren di layar ini. Sah dirata-ratakan karena aliran
             adalah besaran SESAAT per slot, bukan angka meter kumulatif.
             Aliran air baku yang digrafikkan karena ia HULU seluruh rantai dan
             punya penyebut terbesar, jadi bentuk grafiknya paling sedikit
             dipengaruhi slot yang tidak terisi. Grafiknya menggulir mendatar DI
             DALAM kartunya — satu-satunya elemen ber-overflow di layar ini. -->
        <section class="detail-section" data-testid="pw-trend">
          <h2 class="section-title">Tren Harian Aliran Air Baku</h2>
          <p class="section-note">
            <strong>Rata-rata per tanggal</strong><template
              v-if="metricCardOf(trendColumn).unit"
            >, dalam {{ metricCardOf(trendColumn).unit }}</template> &middot;
            {{ formatCount(trendPoints.length) }} tanggal berdata &middot;
            {{ formatCount(metricCardOf(trendColumn).readingCount) }} pembacaan
          </p>

          <p v-if="trendPoints.length === 0" class="section-note" data-testid="pw-trend-empty">
            Belum ada satu tanggal pun yang mencatat aliran air baku pada periode dan line ini,
            jadi trennya tidak menampilkan satu titik pun — bukan garis nol, yang akan terbaca
            sebagai hasil pengukuran.
          </p>

          <template v-else>
            <div class="chart-scroll">
              <div class="chart-bars">
                <!-- Tanpa modifier warna apa pun: tidak ada batang yang
                     ditandai rendah, tinggi, maupun di luar batas. Titik tanpa
                     pembacaan bernilai tidak tersedia, BUKAN nol — batangnya
                     karena itu kosong. -->
                <div
                  v-for="point in trendPoints"
                  :key="`trend-${point.date}`"
                  class="chart-col"
                  :data-testid="`pw-trend-col-${point.date}`"
                >
                  <span class="chart-value">
                    {{ formatDecimal(point.avg, metricCardOf(trendColumn).digits) }}
                  </span>
                  <div class="chart-track">
                    <div class="chart-fill" :style="{ height: trendBarHeight(point.avg) }"></div>
                  </div>
                  <span class="chart-axis">{{ formatDayAxis(point.date) }}</span>
                </div>
              </div>
            </div>

            <p class="section-note" data-testid="pw-trend-scroll-hint">
              Geser mendatar <strong>di dalam kartu ini</strong> untuk melihat seluruh tanggal;
              halaman sendiri tidak menggulir mendatar.
            </p>
            <p class="section-note">
              Terendah {{ formatDecimal(trendLowest, metricCardOf(trendColumn).digits) }} &middot;
              tertinggi {{ formatDecimal(trendHighest, metricCardOf(trendColumn).digits) }}
              <small>(dari rata-rata harian)</small>
            </p>
            <p class="section-note">
              <small>
                Sumbu tegak tidak dimulai dari nol — batang membandingkan tanggal, bukan besaran
                mutlak. Tidak ada rentang &lsquo;aman&rsquo; yang digambar di sini: stasiun ini
                tidak punya master target. Tanggal yang tidak punya pembacaan sama sekali
                <strong>tidak digambar</strong> sebagai batang nol — ia tidak digambar.
              </small>
            </p>
          </template>
        </section>

        <!-- ===== DUA REKAP TEKS BEBAS, DUA BAGIAN TERPISAH =====
             Masing-masing daftar KEJADIAN {tanggal, slot waktu, teks}, dan
             BUKAN sebaran jumlah slot per nilai. Keduanya tidak pernah
             disatukan: satu slot yang mengisi kedua kolom muncul pada KEDUA
             daftar, dan itu BUKAN penghitungan ganda. -->
        <section class="detail-section" data-testid="pw-recap-action-taken">
          <h2 class="section-title">
            Rekap Catatan Tindakan ({{ formatCount(actionTakenEntries.length) }} slot)
          </h2>
          <p class="section-note">
            Satu entri <strong>per slot</strong> yang mengisinya, membawa tanggal dan slot
            waktunya. Daftar ini menjawab <strong>apa yang dikerjakan</strong>.
          </p>

          <p
            v-if="actionTakenEntries.length === 0"
            class="section-note"
            data-testid="pw-recap-action-taken-empty"
          >
            Tidak ada satu pun catatan tindakan tercatat pada periode dan line ini. Ini dinyatakan
            sebagai keterangan pada bagian ini sendiri, bukan daftar kosong tanpa penjelasan —
            daftar kosong tidak membedakan &lsquo;tidak ada tindakan&rsquo; dari &lsquo;tidak ada
            yang mencatatnya&rsquo;.
          </p>

          <div v-else class="note-list">
            <article
              v-for="(entry, index) in actionTakenEntries"
              :key="`at-${entry.date}-${entry.time_slot}-${index}`"
              class="note-item"
              :data-testid="`pw-action-taken-row-${index}`"
              :data-date="entry.date"
              :data-time-slot="entry.time_slot"
            >
              <span class="note-meta">
                {{ formatShortDate(entry.date) }} &middot; {{ formatTimeSlot(entry.time_slot) }}
              </span>
              <p class="note-text">{{ entryText(entry) }}</p>
            </article>
          </div>

          <p class="section-note" data-testid="pw-action-taken-note">
            <strong>Kolom catatan tindakan diterbitkan sebagai REKAP TEKS, bukan sebagai sebaran
            jumlah slot per nilai — meski namanya menyebut &lsquo;status&rsquo;.</strong> Kolomnya
            <strong>teks bebas tanpa himpunan nilai tertutup</strong>. Sebaran atas teks bebas
            menghasilkan <strong>satu &lsquo;nilai&rsquo; per kalimat yang berbeda</strong>, dan
            daftar kalimat berjumlah satu-satu bukan sebaran. Dua slot yang berbunyi sama persis
            <strong>tetap dua baris</strong> di sini, bukan satu baris berjumlah dua — karena yang
            sama persis hari ini belum tentu sama persis besok. Isinya ditampilkan apa adanya
            beserta tanggal dan slot waktunya, <strong>dibungkus utuh tanpa pemotongan</strong>,
            dan tidak pernah dirata-rata maupun digrafikkan.
          </p>
        </section>

        <section class="detail-section" data-testid="pw-recap-findings">
          <h2 class="section-title">
            Rekap Temuan ({{ formatCount(findingEntries.length) }} slot)
          </h2>
          <p class="section-note">
            Bentuk yang sama — satu entri per slot dengan tanggal dan slot waktunya — pada bagian
            yang <strong>TERPISAH</strong> dari Rekap Catatan Tindakan. Daftar ini menjawab
            <strong>apa yang ditemukan</strong>.
          </p>

          <p
            v-if="findingEntries.length === 0"
            class="section-note"
            data-testid="pw-recap-findings-empty"
          >
            Tidak ada satu pun temuan tercatat pada periode dan line ini. Ini dinyatakan sebagai
            keterangan pada bagian ini sendiri, bukan digabung ke bagian catatan tindakan dan bukan
            daftar kosong tanpa penjelasan.
          </p>

          <div v-else class="note-list">
            <article
              v-for="(entry, index) in findingEntries"
              :key="`fd-${entry.date}-${entry.time_slot}-${index}`"
              class="note-item"
              :data-testid="`pw-finding-row-${index}`"
              :data-date="entry.date"
              :data-time-slot="entry.time_slot"
            >
              <span class="note-meta">
                {{ formatShortDate(entry.date) }} &middot; {{ formatTimeSlot(entry.time_slot) }}
              </span>
              <p class="note-text">{{ entryText(entry) }}</p>
            </article>
          </div>

          <p class="section-note" data-testid="pw-findings-note">
            Pertanyaannya <strong>berbeda</strong> dari Rekap Catatan Tindakan, karena itu keduanya
            dua bagian terpisah dan tidak pernah disatukan. Satu slot yang mengisi kedua kolom
            sekaligus <strong>muncul pada kedua rekap</strong>; itu benar,
            <strong>bukan penghitungan ganda</strong> — menyatukan keduanya justru akan menghitung
            slot itu dua kali di dalam satu daftar dan mencampur dua pertanyaan yang berbeda.
            Jumlah pada kedua judul karena itu <strong>tidak boleh dijumlahkan</strong>.
          </p>
        </section>

        <!-- ===== REKAP PER INSPECTOR — KODE MENTAH, DI LAYAR =====
             Penyimpangan SADAR dari preseden Storage Tank, yang menerbitkan
             kolom sejenis hanya di ekspor; diminta pemilik produk 2026-10-16.
             MENUMPUK MENJADI KARTU di lebar ponsel lewat v-stacked-table,
             bukan tabel yang digulir mendatar. Dapat ditutup — dan menutupnya
             MURNI PENYINGKAPAN: tidak memicu satu permintaan data pun. -->
        <CollapsibleSection
          :title="`Rekap per Inspector (${inspectorRows.length} baris)`"
          data-testid="pw-inspector-recap"
        >
          <p class="section-note">
            Dasar <strong>{{ formatCount(inspectorRecordedSlots) }} slot terisi</strong> &middot;
            satu baris per kode inspector beserta baris &lsquo;tidak tercatat&rsquo; &middot;
            menumpuk menjadi kartu di lebar ponsel — satu kartu per baris, tiap sel berlabel, tanpa
            gulir mendatar.
          </p>

          <p
            v-if="inspectorRows.length === 0"
            class="section-note"
            data-testid="pw-inspector-recap-empty"
          >
            Belum ada satu slot terisi pun pada periode dan line ini, jadi rekap per inspector
            belum punya baris untuk ditampilkan.
          </p>

          <div v-else class="detail-table-wrap">
            <table v-stacked-table class="detail-table" data-testid="pw-inspector-table">
              <thead>
                <tr>
                  <th scope="col">inspector_id</th>
                  <th scope="col" class="num">Slot terisi</th>
                  <th scope="col" class="num">Porsi dari slot terisi</th>
                </tr>
              </thead>
              <tbody>
                <!-- SATU ELEMEN BARIS PER BARIS, masing-masing dengan testid-nya
                     sendiri, dan KODENYA punya elemen sendiri di dalamnya: kode
                     inspector pendek seperti '7' atau 'A1' akan cocok di dalam
                     angka lain pada halaman yang sama, jadi asersi harus dapat
                     menyasar SEL kodenya — bukan memindai teks kontainer.
                     Kodenya dicetak MENTAH apa adanya; tidak ada satu pun
                     pencarian nama di layar ini. -->
                <tr
                  v-for="(row, index) in inspectorRows"
                  :key="`inspector-${index}-${row.code ?? 'null'}`"
                  :data-testid="`pw-inspector-row-${index}`"
                >
                  <td>
                    <code v-if="row.code !== null" :data-testid="`pw-inspector-code-${index}`">{{
                      row.code
                    }}</code>
                    <span
                      v-else
                      class="is-muted"
                      :data-testid="`pw-inspector-code-${index}`"
                      >tidak tercatat</span
                    >
                  </td>
                  <td class="num" :data-testid="`pw-inspector-slots-${index}`">
                    {{ formatCount(row.slots) }}
                  </td>
                  <td class="num" :data-testid="`pw-inspector-share-${index}`">
                    {{ formatShare(row.slots, inspectorRecordedSlots) }}
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr data-testid="pw-inspector-total">
                  <td>Seluruh slot terisi</td>
                  <td class="num">{{ formatCount(inspectorRecordedSlots) }}</td>
                  <td class="num">{{ inspectorRecordedSlots === 0 ? '—' : '100,0%' }}</td>
                </tr>
              </tfoot>
            </table>
          </div>

          <p class="section-note" data-testid="pw-inspector-purpose-note">
            <strong>Rekap ini ada untuk menelusuri slot yang BELUM terisi — bukan untuk menilai
            orang.</strong>
            Kelengkapan pencatatan adalah pokok layar ini, dan slot yang kosong selalu punya pemilik
            yang dapat ditanyai; itu seluruh gunanya. Angka di kolom &lsquo;slot terisi&rsquo;
            <strong>bukan nilai kinerja</strong>: ia tidak memperhitungkan panjang penugasan,
            jumlah unit yang dijaga, hari libur, maupun slot yang memang bukan jatahnya — jadi
            membandingkan dua inspector lewat angka ini akan salah, dan laporan ini
            <strong>tidak</strong> mengurutkan, memberi peringkat, atau menandai siapa pun.
          </p>
          <p class="section-note" data-testid="pw-inspector-raw-code-note">
            Baris <strong>&lsquo;tidak tercatat&rsquo;</strong> memuat slot yang
            <strong>terisi</strong> tetapi kolom inspector-nya kosong. Slot itu
            <strong>tetap terhitung</strong> pada kelengkapan di atas, karena kolom itu adalah
            kolom identitas dan tidak ikut menentukan apakah sebuah slot terisi. Baris itu
            <strong>berdiri sendiri</strong> dan tidak pernah digabungkan ke salah satu inspector.
            <small>
              Kolomnya bertipe <strong>string polos, bukan relasi ke tabel User</strong>, jadi
              kodenya ditampilkan <strong>MENTAH apa adanya</strong> dan laporan ini tidak pernah
              mencoba memulangkan nama orangnya — formatnya pun belum seragam. Kode panjang atau
              yang mengandung spasi ditampilkan utuh, membungkus di dalam kartunya, tidak dipotong.
            </small>
          </p>
        </CollapsibleSection>

        <!-- ===== REKAP PER UNIT PROCESS WATER =====
             Angka periode di kartu-kartu di atas MENGGABUNGKAN seluruh unit;
             rekap ini menunjukkan sebarannya per unit. MENUMPUK MENJADI KARTU
             di lebar ponsel. -->
        <CollapsibleSection
          :title="`Rekap per Unit Process Water (${unitRows.length} unit)`"
          data-testid="pw-unit-recap"
        >
          <p class="section-note">
            Angka periode di seluruh kartu di atas <strong>menggabungkan seluruh unit</strong> —
            rekap ini menunjukkan sebarannya. Unit yang
            <strong>tidak punya pembacaan sama sekali tetap muncul</strong> dengan penyebut nol;
            membuangnya justru akan menyembunyikan unit yang tidak pernah dituliskan. Nama unit
            adalah nilai yang <strong>diketik di layar input</strong>, bukan kunci baris.
          </p>

          <p v-if="unitRows.length === 0" class="section-note" data-testid="pw-unit-recap-empty">
            Belum ada satu unit Process Water pun yang punya record pada periode dan line ini.
          </p>

          <div v-else class="detail-table-wrap">
            <table v-stacked-table class="detail-table" data-testid="pw-unit-table">
              <thead>
                <tr>
                  <th scope="col">Unit Process Water</th>
                  <th scope="col" class="num">Hari tercatat</th>
                  <th scope="col" class="num">Slot terisi</th>
                  <th v-for="column in METRIC_COLUMNS" :key="column" scope="col" class="num">
                    {{ metricShortLabel(column) }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(row, index) in unitRows"
                  :key="`unit-${row.process_water_id}-${index}`"
                  :data-testid="`pw-unit-row-${index}`"
                >
                  <td>{{ formatGroupLabel(row.process_water_id) }}</td>
                  <td class="num">{{ formatCount(row.days_with_rows ?? null) }}</td>
                  <td class="num" :data-testid="`pw-unit-filled-slots-${index}`">
                    {{ formatCount(unitFilledSlots(row)) }}
                  </td>
                  <td v-for="column in METRIC_COLUMNS" :key="column" class="num">
                    {{ formatCell(row.metrics?.[column]?.avg ?? null, metricDigits(column)) }}
                    <small>(n={{ formatCount(readingCountOf(row.metrics?.[column])) }})</small>
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <!-- Angka gabungan DIAMBIL dari `metrics` tingkat periode, yang
                     DIHITUNG ULANG server atas seluruh slot terisi — bukan
                     dirata-ratakan dari baris per unit di atasnya. -->
                <tr data-testid="pw-unit-total">
                  <td>Gabungan {{ formatCount(coverageUnitCount) }} unit</td>
                  <td class="num">{{ formatCount(totals?.days_with_records) }}</td>
                  <td class="num">{{ formatCount(totals?.reading_rows) }}</td>
                  <td v-for="column in METRIC_COLUMNS" :key="column" class="num">
                    {{ formatCell(metricCardOf(column).avg, metricDigits(column)) }}
                    <small>(n={{ formatCount(metricCardOf(column).readingCount) }})</small>
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </CollapsibleSection>

        <!-- ===== REKAP HARIAN =====
             Panjang pada periode sebulan, jadi dibungkus CollapsibleSection dan
             TERTUTUP secara bawaan supaya gulirnya tetap terkendali. Membuka
             atau menutupnya MURNI PENYINGKAPAN — datanya sudah ada di memori,
             tidak satu permintaan pun dipicu — dan tidak mengubah satu angka
             pun, termasuk baris total periode. MENUMPUK MENJADI KARTU di lebar
             ponsel. -->
        <CollapsibleSection
          :title="`Rekap Harian (${dailyRows.length} tanggal)`"
          data-testid="pw-daily-recap"
        >
          <p class="section-note">
            Satu kartu per tanggal beserta kartu <strong>Total periode</strong>. Tanggal tanpa satu
            pun slot terisi <strong>tidak mendapat baris</strong>: baris nol akan terbaca sebagai
            &lsquo;terukur nol&rsquo;, padahal tidak ada yang diukur. Sel kosong dirender
            &lsquo;&mdash;&rsquo; — <strong>bukan 0</strong>, karena nol adalah pembacaan dan
            kosong bukan.
          </p>

          <p v-if="dailyRows.length === 0" class="section-note" data-testid="pw-daily-recap-empty">
            Belum ada satu tanggal pun dengan slot terisi pada periode dan line ini.
          </p>

          <div v-else class="detail-table-wrap">
            <table v-stacked-table class="detail-table" data-testid="pw-daily-table">
              <thead>
                <tr>
                  <th scope="col">Tanggal</th>
                  <th scope="col" class="num">Slot terisi</th>
                  <th v-for="column in METRIC_COLUMNS" :key="column" scope="col" class="num">
                    {{ metricShortLabel(column) }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in dailyRows"
                  :key="`daily-${row.date}`"
                  :data-testid="`pw-daily-row-${row.date}`"
                >
                  <td>{{ formatDate(row.date) }}</td>
                  <td class="num">{{ formatCount(dailyFilledSlots(row)) }}</td>
                  <td v-for="column in METRIC_COLUMNS" :key="column" class="num">
                    {{ formatCell(dailyAvgOf(row, column), metricDigits(column)) }}
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <!-- TOTAL PERIODE DIHITUNG ULANG SERVER atas seluruh slot
                     terisi, bukan merata-ratakan rata-rata harian: rata-rata
                     dari rata-rata memberi bobot sama pada hari berisi dua slot
                     dan hari berisi dua puluh empat. Karena itu pula ia tidak
                     berubah ketika rekap harian ditutup. -->
                <tr data-testid="pw-daily-total">
                  <td>Total periode</td>
                  <td class="num">
                    {{ formatCount(dailyTotal?.reading_rows ?? coverage?.filled_slots ?? null) }}
                  </td>
                  <td v-for="column in METRIC_COLUMNS" :key="column" class="num">
                    {{ formatCell(metricCardOf(column).avg, metricDigits(column)) }}
                    <small>(n={{ formatCount(metricCardOf(column).readingCount) }})</small>
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </CollapsibleSection>

        <!-- ===== YANG TIDAK DITERBITKAN DI LAYAR INI =====
             Ketiadaan yang disengaja, dinyatakan supaya tidak terbaca sebagai
             bagian yang terlewat ketika laporan ini dibandingkan dengan laporan
             stasiun lain. Kalimat SERVER dirender VERBATIM di elemen tersendiri;
             keterangan layar ada di sebelahnya, bukan di dalamnya. -->
        <section class="detail-section" data-testid="pw-absences">
          <h2 class="section-title">Yang Tidak Diterbitkan di Layar Ini</h2>

          <p
            v-if="noteAbsences"
            class="section-note"
            data-testid="pw-note-absences"
          >{{ noteAbsences }}</p>

          <p class="section-note" data-testid="pw-no-downtime-note">
            <strong>Tidak ada blok downtime, dan ketiadaannya disengaja.</strong> Stasiun Process
            Water <strong>tidak mencatat lama berhenti sama sekali</strong> — tidak ada satu kolom
            pun yang bisa diterbitkan, jadi tidak ada blok yang dikosongkan maupun diisi nol.
          </p>
          <p class="section-note" data-testid="pw-no-shift-note">
            <strong>Kolom giliran kerja tidak diterbitkan di layar ini, hanya di ekspor — dan
            ketiadaannya disengaja.</strong> Ia tidak ada di payload laporan sama sekali, jadi
            ketiadaannya di layar adalah konsekuensi, bukan penyaringan di klien.
            <strong>Pengelompokan laporan periode menurut giliran kerja belum ada, baik di layar
            ini maupun di versi web, dan keduanya akan ditinjau bersama.</strong>
            <small>
              Konsekuensinya sudah tampak di blok kelengkapan: kolom itu dan kode inspector adalah
              dua kolom identitas yang dikecualikan dari pemeriksaan slot terisi. Keduanya
              <strong>tetap ikut</strong> di berkas ekspor CSV, karena ekspor adalah rincian
              mentah.
            </small>
          </p>
          <p class="section-note" data-testid="pw-no-distribution-note">
            <strong>Tidak ada satu pun daftar sebaran kategoris di layar ini</strong>, karena
            stasiun ini <strong>tidak punya satu pun kolom kategoris</strong>. Kolom catatan
            tindakan bertipe teks bebas meski namanya menyebut &lsquo;status&rsquo;, jadi ia
            diterbitkan sebagai <strong>rekap teks</strong> di atas. Menyalin bentuk sebaran dari
            laporan stasiun sebelumnya akan menghasilkan satu &lsquo;nilai&rsquo; per kalimat yang
            berbeda.
          </p>
          <p class="section-note" data-testid="pw-no-mill-picker-note">
            <strong>Tidak ada pemilih Mill, dan tidak ada tombol Ekspor Excel.</strong> Kedua aktor
            layar ini terikat pada mill akunnya, jadi daftar mill tidak pernah dipanggil dari sini;
            dan .xlsx tidak dirender karena WebView ponsel tidak membukanya — menyediakan tombol
            yang menghasilkan berkas tak terbuka lebih buruk daripada tidak menyediakannya.
            <small>
              Layar ini juga <strong>perlu jaringan</strong>: ia tidak menyimpan hasil laporan ke
              penyimpanan lokal dan tidak punya mode luring — keadaan luring dinyatakan apa adanya,
              bukan ditampilkan sebagai angka basi tanpa penanda.
            </small>
          </p>
        </section>
      </template>

      <footer class="action-footer">
        <!-- Ekspor TIDAK PERNAH dinonaktifkan oleh status periode: kunci
             periode mengatur penulisan data, bukan pembacaan laporan. CSV SAJA
             — TIDAK ADA tombol Ekspor Excel di layar ini sama sekali, dan tidak
             ada jalur kode yang dapat menyusun format lain. Tombolnya tidak
             dirender selama belum ada periode DAN line yang berlaku, karena
             berkas yang mencampur line adalah bentuk kesalahan yang paling
             sulit dibantah setelah terkirim. -->
        <button
          v-if="selectedPeriodId && selectedProductionLineId"
          type="button"
          class="action-button action-button--primary"
          data-testid="pw-export-csv"
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
/* SETIAP KELAS YANG DIPAKAI MARKUP DI ATAS PUNYA DEFINISI — di blok ini, atau
   di stylesheet bersama yang dirujuknya (src/assets/stacked-table.css untuk
   .detail-table dan .num). Dua defek lama SENGAJA tidak disalin:
   LaporanDepricarpingView.vue membungkus angka dengan kelas .metric-values
   yang tidak terdefinisi di satu berkas pun di src/, dan lima kelas tanpa
   definisi di kelola-machinery.blade.php lolos 25 test Livewire dan 19 spec
   browser. Kelas tanpa definisi tidak memerahkan satu test pun — ia hanya
   menjawab 'visible' ketika gaya yang diharapkan ditanyakan. */

.laporan-pr-view {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 0 16px 20px;
  background: #ffffff;
  font-family: 'Inter', sans-serif;
  box-sizing: border-box;
  /* HALAMAN TIDAK PERNAH MENGGULIR MENDATAR pada 390px; yang menggulir adalah
     isi kartu grafik di dalamnya. Ketiga tabel rekap BERTUMPUK menjadi kartu
     alih-alih menggulir, lewat v-stacked-table + src/assets/stacked-table.css
     (temuan QA LP-M-02/LP-M-03). */
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
.period-meta-note { font-size: 12px; color: #6b7280; line-height: 1.5; }

.notice { margin: 0; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; color: #6b7280; font-size: 14px; line-height: 1.5; }
.notice--warning { border-color: #fcd34d; background: #fffbeb; color: #92400e; }
.notice--error { display: flex; flex-direction: column; gap: 10px; border-color: #fecaca; background: #fef2f2; color: #b91c1c; }
.notice-text { margin: 0; }
.notice--stack { display: flex; flex-direction: column; align-items: flex-start; gap: 10px; }

.metric-stack { display: flex; flex-direction: column; gap: 10px; }
.metric-card { display: flex; flex-direction: column; gap: 4px; padding: 14px; border: 1px solid #e5e7eb; border-radius: 8px; }

/* Judul kartu dan PIL SATUANNYA pada satu baris, satuan di kanan. Barisnya
   membungkus (flex-wrap) supaya label panjang tidak pernah mendorong pil
   satuan keluar kartu — dan satuan yang terdorong keluar adalah satuan yang
   tidak terbaca, yang di blok Dosis Kimia berarti angka terbaca dengan skala
   kartu sebelumnya. */
.metric-label-row { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.metric-label { font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }

/* PIL SATUAN — netral secara warna: ia menyatakan SATUAN, bukan menilai
   angkanya. Tidak ada satu pun warna peringatan di layar ini. */
.metric-unit-pill {
  display: inline-block;
  flex: none;
  padding: 1px 8px;
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  background: #f9fafb;
  color: #4b5563;
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
}

.metric-value { font-size: 22px; font-weight: 700; color: #1f2937; }
/* Nilai yang tidak dapat dihitung tampil sebagai keterangan, bukan angka besar
   — supaya tidak pernah terbaca sekilas sebagai bilangan. */
.metric-value--na { font-size: 15px; font-weight: 600; color: #6b7280; }
.metric-note { font-size: 12px; color: #6b7280; line-height: 1.5; overflow-wrap: anywhere; }

/* Tiga angka berdampingan DI DALAM satu kartu metrik — ini bukan tiga kartu
   bersebelahan: terendah/rata-rata/tertinggi adalah satu pengukuran yang sama
   dilihat dari tiga sisi. Dipakai .metric-row/.metric-row-item yang
   TERDEFINISI di sini. */
.metric-row { display: flex; gap: 16px; flex-wrap: wrap; }
.metric-row-item { display: flex; flex-direction: column; gap: 2px; min-width: 72px; }
.metric-row-item small { font-size: 11px; color: #6b7280; }
.metric-row-item b { font-size: 18px; font-weight: 700; color: #1f2937; }

/* Bilah kelengkapan: lebarnya adalah PERSEN DARI SERVER yang dicetak apa
   adanya, bukan angka yang diturunkan di klien. Ia tidak dirender sama sekali
   ketika penyebutnya belum terbentuk. */
.completeness-track { width: 100%; height: 8px; border-radius: 999px; background: #f3f4f6; overflow: hidden; }
.completeness-fill { height: 100%; border-radius: 999px; background: #249360; }

.detail-section { display: flex; flex-direction: column; gap: 10px; padding: 14px; border: 1px solid #e5e7eb; border-radius: 8px; }
.section-title { margin: 0; font-size: 16px; font-weight: 700; color: #1f2937; }
/* KETERANGAN PANJANG DIRENDER UTUH: tidak ada text-overflow, tidak ada
   line-clamp, tidak ada -webkit-line-clamp, dan tidak ada pemotongan karakter
   di layar ini — pernyataan ketiadaan penandaan di luar batas adalah yang
   paling tidak boleh terpotong, dan keterangan yang dipotong di tengah
   berhenti menerangkan apa pun. `overflow-wrap: anywhere` membuat kata/kode
   panjang MEMBUNGKUS alih-alih melebarkan halaman. */
.section-note { margin: 0; font-size: 14px; color: #6b7280; line-height: 1.6; overflow-wrap: anywhere; }
.section-note small { font-size: 13px; }
.section-note code { font-family: ui-monospace, 'SFMono-Regular', Menlo, monospace; font-size: 13px; color: #4b5563; }

/* Kedua rekap teks bebas: daftar KEJADIAN, bukan tabel. Teksnya dibungkus
   UTUH — pemotongan akan menghilangkan isi catatan itu sendiri, dan kartunya
   tidak boleh membuat halaman menggulir mendatar. */
.note-list { display: flex; flex-direction: column; gap: 10px; }
.note-item { display: flex; flex-direction: column; gap: 2px; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; }
.note-meta { font-size: 12px; font-weight: 600; color: #6b7280; }
.note-text { margin: 0; font-size: 14px; color: #1f2937; line-height: 1.5; white-space: normal; overflow-wrap: anywhere; }

/* Tabel: BERTUMPUK menjadi kartu di lebar ponsel, bukan menggulir mendatar.
   Bentuk dasar + kartu bertumpuk ada di src/assets/stacked-table.css (global,
   dipakai bersama seluruh tabel laporan mobile — QA LP-M-02/LP-M-03), dan
   aturan `.detail-table` TIDAK ditulis ulang di sini dengan sengaja: style
   scoped mengompilasi selektor dengan [data-v-hash] dan akan selalu menang
   atas stylesheet bersama itu.

   PEMBUNGKUSNYA tetap `overflow-x: auto`: pada lebar ponsel barisnya sudah
   menjadi kartu sehingga tidak ada yang melebar dan tidak ada bilah gulir yang
   muncul — halaman tetap `scrollWidth <= clientWidth`. Yang dijaga nilai ini
   adalah lebar di ATAS 640px (tablet/jendela lebar), tempat tabelnya memang
   tabel sungguhan dan sembilan kolom angka bisa melebihi kotaknya: di sana isi
   yang melebar MENGGULIR DI DALAM KOTAKNYA SENDIRI alih-alih melebarkan
   halaman. */
.detail-table-wrap { width: 100%; overflow-x: auto; }
.detail-table tfoot th, .detail-table tfoot td { font-weight: 700; color: #1f2937; border-bottom: none; }
.detail-table tfoot td small { font-weight: 500; color: #6b7280; }
.detail-table td small { font-weight: 500; color: #6b7280; }
/* KODE INSPECTOR MENTAH: monospace supaya kode yang formatnya belum seragam
   tetap terbaca sebagai kode, dan MEMBUNGKUS supaya kode panjang atau yang
   mengandung spasi tampil utuh di dalam kartunya — tidak dipotong, dan tidak
   melebarkan halaman. */
.detail-table code { font-family: ui-monospace, 'SFMono-Regular', Menlo, monospace; font-size: 13px; color: #1f2937; overflow-wrap: anywhere; }

/* Nilai yang memang tidak tercatat — redup, TANPA warna peringatan: ia
   menyatakan ketiadaan catatan, bukan penilaian. */
.is-muted { color: #6b7280; }

/* Grafik tren: menggulir mendatar DI DALAM kartunya sendiri — ia satu-satunya
   elemen yang menggulir mendatar di layar ini, dan halaman sendiri tetap tidak
   menggulir mendatar. Sumbu tegak sengaja tidak dimulai dari nol, dan hal itu
   dinyatakan di kartunya. Tidak ada modifier warna peringatan di sini — nol
   kelas peringatan di seluruh layar ini. */
.chart-scroll { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.chart-bars { display: flex; align-items: flex-end; gap: 6px; min-width: min-content; padding-bottom: 4px; }
.chart-col { display: flex; flex-direction: column; align-items: center; gap: 4px; flex: none; width: 44px; }
.chart-value { font-size: 11px; color: #6b7280; }
.chart-track { display: flex; align-items: flex-end; width: 100%; height: 96px; border-radius: 4px; background: #f3f4f6; overflow: hidden; }
.chart-fill { width: 100%; border-radius: 4px 4px 0 0; background: #249360; }
.chart-axis { font-size: 11px; color: #6b7280; }

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
