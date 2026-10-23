<script setup lang="ts">
/**
 * LaporanProcessQualityControlView — screen-169--laporan-process-quality-control-mobile /
 * usecase-175--laporan-process-quality-control-mobile "Lihat Laporan Periode
 * Process Quality Control (Mobile)".
 *
 * Kembaran mobile KELIMA BELAS DAN TERAKHIR — sesudah layar ini seluruh delapan
 * belas jenis stasiun punya laporan periodenya sendiri, di web maupun di
 * mobile. BACAAN SAJA: layar ini tidak punya satu pun jalur yang mengubah data
 * stasiun, dan tidak menulis ke localDb.
 *
 * ────────────────────────────────────────────────────────────────────────
 * NOL PERUBAHAN BACKEND — DIBAYAR LEBIH DULU, DENGAN SENGAJA
 * ────────────────────────────────────────────────────────────────────────
 * Ketiga rute data /api/process-quality-control-reports/* SUDAH menerima
 * Operator sejak commit pertama screen-168 — justru supaya layar ini tidak perlu melebarkan
 * akses apa pun — dan /api/production-lines/options-for-report dipakai ulang
 * VERBATIM dari screen-135. Tidak satu berkas di backend/ disentuh untuk layar
 * ini, dan tidak ada entri api-index baru. Bila sebuah test backend memerah,
 * sebabnya ada di tempat lain.
 *
 * ────────────────────────────────────────────────────────────────────────
 * DUA BELAS KARTU DALAM LIMA BLOK YANG DAPAT DILIPAT — BEBAN TATA LETAK
 * TERBESAR DI ANTARA SELURUH LAPORAN MOBILE
 * ────────────────────────────────────────────────────────────────────────
 * Dua belas ukuran, masing-masing dengan terendah/rata-rata/tertinggi beserta
 * penyebut dan satuannya sendiri, adalah TIGA PULUH ENAM ANGKA ditambah dua
 * belas penyebut dan sebelas satuan — pada lebar ponsel. Engine Room mobile
 * punya dua puluh tiga kartu dan Effluent Plant empat belas, tetapi keduanya
 * kartu datar; di sini jumlah angka per kartu yang menumpuk.
 *
 * JAWABANNYA BUKAN MEMPERKECIL SEMUANYA MELAINKAN MENGHORMATI LIMA BLOKNYA.
 * Kelima blok mengikuti JALUR FISIK MINYAK — Fruit Press, Purifier &
 * Clarification Balance, Vacuum Drying Station, Decanter/Centrifuge, Final
 * Storage — dan masing-masing menjawab SATU pertanyaan tentang SATU tahap.
 * Karena itu kelimanya KARTU YANG DAPAT DILIPAT, dibuka satu per satu: pembaca
 * di lapangan datang dengan satu pertanyaan tentang satu tahap, bukan dengan
 * dua belas pertanyaan sekaligus. Judul dan urutannya SAMA PERSIS seperti versi
 * web (screen-168), sehingga pembaca yang berpindah dari ponsel ke web tidak
 * perlu mempelajari ulang peta halamannya.
 *
 * INI PENYIMPANGAN SADAR DARI SERI. Keempat laporan bergrid mobile sebelumnya
 * merender blok kartunya TERBUKA tanpa kontrol lipat, dengan alasan bahwa
 * melipat menyembunyikan penyebut tiap kartu. Alasan itu tetap benar, dan di
 * sini ia DIKALAHKAN oleh jumlah: dua belas kartu terbuka sekaligus membuat
 * blok kelengkapan — satu-satunya bagian yang menentukan apakah angkanya layak
 * dipercaya — tergulir jauh di atas layar sebelum satu angka pun dibaca.
 * Kompensasinya: JUDUL TIAP KARTU BLOK MEMBAWA PENANDA ADA-TIDAKNYA DATA,
 * sehingga penyebut nol tetap terbaca TANPA membuka kartunya.
 *
 * PENANDANYA `some`, BUKAN `every` (lihat blockHasData): satu metrik berdata
 * sudah membuat blok itu layak dibuka. `every` akan menandai blok Final Storage
 * sebagai kosong hanya karena Indeks DOBI tidak pernah diisi — kebalikan dari
 * maksudnya.
 *
 * KELIMA BLOK TETAP ADA DI LAYAR meski seluruh ukurannya kosong: blok yang
 * hilang akan dibaca sebagai TAHAP YANG TIDAK ADA.
 *
 * MARKUP KARTUNYA DITULIS SEKALI DAN DI-LOOP atas `metrics` yang
 * DIKELOMPOKKAN MENURUT FIELD `group` DARI RESPONS — bukan atas daftar nama
 * kolom yang dipaku di berkas ini. Dua belas kartu yang ditulis manual adalah
 * dua belas kesempatan kehilangan penyebut atau MEWARISI penyebut kartu lain,
 * dan kolom baru dari migrasi kelak masuk ke blok yang benar tanpa menyunting
 * berkas ini.
 *
 * MELIPAT DAN MEMBUKA MURNI PENYINGKAPAN: CollapsibleSection memakai `v-show`,
 * jadi isinya tetap ada di DOM, tidak satu permintaan pun dipicu, tidak satu
 * angka pun berubah, dan keadaan lipatnya TIDAK disimpan ke penyimpanan
 * perangkat.
 *
 * ────────────────────────────────────────────────────────────────────────
 * TITIK PALING RAWAN DI LAYAR INI: INDEKS DOBI
 * ────────────────────────────────────────────────────────────────────────
 * Blok Final Storage memuat EMPAT kartu, dan TIGA di antaranya berpersen: ALB,
 * kadar air, kadar kotoran. Yang KEEMPAT adalah INDEKS DOBI — indeks TANPA
 * DIMENSI, bukan persentase. Mencetaknya bertanda persen adalah kesalahan yang
 * akan TERLIHAT BENAR di layar justru karena ketiga tetangganya berpersen, dan
 * di ponsel keempat kartu itu tidak pernah terlihat bersamaan sehingga
 * kesalahannya lebih sulit tertangkap daripada di web.
 *
 * Yang mencegahnya hanya DUA hal, dan keduanya harus utuh: `unit: null` pada
 * FS_DOBI_META, dan pemeriksaan `v-if="card.unit"` di template. JANGAN PERNAH
 * mengubahnya menjadi `card.unit ?? ''` — string kosong tetap merender elemen
 * pil satuan yang hampa, dan dari situ satuan apa pun dapat mendarat di sana.
 *
 * SATUAN DICETAK PADA KARTUNYA MASING-MASING, di dalam elemen
 * `pqc-metric-unit-{kolom}` kartu itu sendiri — bukan sekali pada judul
 * bloknya. Di web kartu-kartu satu blok berdampingan sehingga satuannya terbaca
 * sekaligus; di ponsel pembaca melihat SATU KARTU PADA SATU WAKTU dan kedekatan
 * itu hilang, sehingga angka tanpa satuan akan dibaca dengan skala kartu
 * sebelumnya. Besaran yang berbeda juga TIDAK disamakan demi kerapian dan TIDAK
 * dijumlahkan: 'total kondisi proses' bukan besaran yang punya arti, dan dua
 * kartu dalam satu blok pun dapat bersatuan berbeda (Decanter/Centrifuge:
 * MT/jam bersebelahan dengan persen).
 *
 * ────────────────────────────────────────────────────────────────────────
 * NOL SEBARAN KATEGORIS — PEMBEDA TERPENTING DARI LAPORAN SEBELUMNYA
 * ────────────────────────────────────────────────────────────────────────
 * Stasiun ini TIDAK PUNYA SATU PUN KOLOM BERPILIHAN TERTUTUP — tak satu pun
 * dari keenam belas kolom barisnya, dan kedua kolom identitasnya (`shift` dan
 * `qc_inspector_id`) pun teks bebas. Kedua kolom catatan
 * (`qc_engineering_corrective_actions` dan `findings`) diterbitkan sebagai DUA
 * REKAP TEKS TERPISAH — satu entri per slot — dan isi yang berulang sama persis
 * TIDAK dikelompokkan: sebaran atas teks bebas menghasilkan satu 'nilai' per
 * kalimat yang berbeda, dan daftar kalimat berjumlah satu-satu bukan sebaran —
 * ia daftar yang sama, disusun ulang sampai terlihat seperti data kategoris.
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
 * ProcessQualityControlReportService menerbitkan `coverage.coverage_percent`,
 * `coverage.process_qc_unit_count`, `metrics[k].reading_count`,
 * `inspector_recap` sebagai OBJEK {entries, not_recorded, attributed_slots},
 * dan `daily[].metrics_avg`. Tech spec layar ini menamai medan yang sama
 * `percent`, `unit_count`, `count`, dan `inspector_recap` sebagai DAFTAR.
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
 *       (number_format(100 * count / attributedSlots, 1)), supaya ponsel tidak
 *       mungkin mencetak porsi yang berbeda dari web untuk payload yang sama.
 *       PENYEBUTNYA slot TERATRIBUSI DARI RESPONS — tidak pernah expected_slots
 *       dan tidak pernah coverage.filled_slots, karena rekap itu menghitung
 *       ATRIBUSI sementara kelengkapan menghitung PEMBACAAN.
 *   (c) PENANDA DATA PADA JUDUL BLOK: `some(reading_count > 0)` atas kolom blok
 *       itu. Sebuah boolean, bukan bilangan — dan bukan nilai laporan.
 *
 * ────────────────────────────────────────────────────────────────────────
 * YANG DIWARISI DARI SERI, DINYATAKAN KARENA MENANGGUNG BEBAN
 * ────────────────────────────────────────────────────────────────────────
 * KELENGKAPAN PENCATATAN DIRENDER PALING ATAS, sebelum satu kartu pun. Di
 * layar sempit ia satu-satunya bagian yang pasti terlihat tanpa menggulir, dan
 * ia menentukan apakah dua belas angka di bawahnya layak dipercaya. Ia juga
 * SATU-SATUNYA kartu yang TIDAK dapat dilipat di layar ini.
 *
 * SLOT TERISI DIHITUNG ATAS EMPAT BELAS KOLOM, BUKAN ENAM BELAS: `shift` dan
 * `qc_inspector_id` adalah kolom IDENTITAS dan dikecualikan. Konsekuensinya
 * BERPASANGAN DAN BERLAWANAN, dan keduanya harus terbaca: slot yang HANYA
 * mengisi kolom teks bebas TETAP terhitung terisi (kedua kolom teks termasuk
 * dalam keempat belas kolom), sementara slot yang HANYA mengisi
 * shift/qc_inspector_id TIDAK — walau kode inspekturnya TETAP masuk rekap
 * per-inspektur, karena rekap menghitung ATRIBUSI sementara kelengkapan
 * menghitung PEMBACAAN. Karena itu coverage.filled_slots DAPAT MELEBIHI
 * penyebut setiap kartu — dirender APA ADANYA, tidak di-clamp, tidak
 * disembunyikan, dan tanpa peringatan 'data tidak konsisten'; yang
 * menerangkannya adalah kalimat server di `_note_filled_rule`, dan
 * `coverage.max_metric_reading_count` dicetak BERDAMPINGAN dengan filled_slots
 * supaya selisihnya terbaca alih-alih dicurigai sebagai cacat.
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
 * HALAMAN TIDAK PERNAH MENGGULIR MENDATAR PADA 390px, TERMASUK DENGAN KELIMA
 * BLOK TERBUKA SEKALIGUS. Kedua tabel rekap BERTUMPUK menjadi kartu lewat
 * v-stacked-table + src/assets/stacked-table.css (temuan QA LP-M-02/LP-M-03),
 * dan tata letak per metrik BERTUMPUK (label di atas, tiga angka di bawah)
 * alih-alih tiga kolom sejajar — tiga puluh enam angka dalam kolom sejajar akan
 * memaksa halaman bergeser.
 *
 * DAN SATU PERTANYAAN GEOMETRI YANG WAJIB DITANYAKAN SETIAP UJI: adakah elemen
 * yang digambar DI LUAR kotak induknya? `overflow` pada kartu memotong anak
 * TANPA jejak apa pun di DOM — elemennya tetap ada, tetap terlihat, dan tepinya
 * justru persis menempel tepi induknya karena di situ ia dipotong. Asersi 'ada
 * / terlihat / menempel tepi' karena itu HIJAU justru pada halaman yang cacat.
 *
 * DISIPLIN KELAS CSS: tiap kelas yang dipakai markup punya definisi di blok
 * <style> di bawah atau di stylesheet bersama yang dirujuknya
 * (src/assets/stacked-table.css untuk .detail-table / .num). Dua defek lama
 * SENGAJA TIDAK disalin: LaporanDepricarpingView.vue membungkus angka dengan
 * kelas .metric-values yang tidak terdefinisi di satu berkas pun di src/, dan
 * keterangan cakupan Depricarping mobile menyebut jumlah kolom bacaan yang
 * salah. Di sini dipakai .metric-row/.metric-row-item yang TERDEFINISI, dan
 * jumlah kolom bacaannya EMPAT BELAS — tidak diketik sebagai angka di dalam
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
import processQualityControlReportRepo, {
  type ProcessQualityControlReportCoverage,
  type ProcessQualityControlReportDailyRow,
  type ProcessQualityControlReportInspectorEntry,
  type ProcessQualityControlReportMetric,
  type ProcessQualityControlReportPeriodOption,
  type ProcessQualityControlReportSummary,
  type ProcessQualityControlReportTextEntry,
} from '@/services/processQualityControlReportRepo'

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
 * Daftar line diambil lewat processQualityControlReportRepo.fetchLines(), yang
 * MENDELEGASIKAN ke productionLineRepo.fetchProductionLinesForReport(null) —
 * endpoint screen-135 yang dipakai ulang verbatim, TANPA business_unit_id, dan
 * tidak diduplikasi di bawah prefix process-quality-control-reports.
 */
async function loadProductionLines(): Promise<void> {
  loadingProductionLines.value = true
  productionLineFetchFailed.value = false

  try {
    const lines = (await processQualityControlReportRepo.fetchLines()) ?? []

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

const periods = ref<ProcessQualityControlReportPeriodOption[]>([])
const selectedPeriodId = ref<string | null>(null)
const summary = ref<ProcessQualityControlReportSummary | null>(null)

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
    periods.value = await processQualityControlReportRepo.fetchPeriods()
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
    const result = await processQualityControlReportRepo.fetchSummary(periodId, scope.value)
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

  return `laporan-process-quality-control_${slug || 'periode'}_${lineSlug || 'line'}.csv`
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
    // termasuk `shift` dan `qc_inspector_id`). Layar ini tidak pernah menyusun
    // berkasnya dari angka yang tampil. Nama berkas diambil SAAT ekspor
    // diminta: bila pengguna mengganti periode selama berkas diunduh, isinya
    // tetap milik periode yang diekspor, jadi namanya pun harus milik periode
    // itu. format=csv SELALU dan EKSPLISIT.
    const filename = exportFilename()
    const blob = await processQualityControlReportRepo.exportCsv(periodId, scope.value)
    processQualityControlReportRepo.saveCsvFile(blob, filename)
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

const coverage = computed<ProcessQualityControlReportCoverage | null>(() => summary.value?.coverage ?? null)
const metrics = computed<Record<string, ProcessQualityControlReportMetric>>(
  () => summary.value?.metrics ?? {},
)

/**
 * KEDUA REKAP TEKS, dibaca sebagai DUA variabel TERPISAH dan TIDAK PERNAH
 * disatukan menjadi satu daftar: satu slot yang mengisi kedua kolom muncul
 * pada KEDUA rekap, dan itu benar — menyatukannya akan menghitung slot itu dua
 * kali di dalam satu daftar dan mencampur dua pertanyaan yang berbeda (apa
 * yang dikerjakan vs apa yang ditemukan).
 */
const actionTakenEntries = computed<ProcessQualityControlReportTextEntry[]>(
  () => summary.value?.text_entries?.qc_engineering_corrective_actions ?? [],
)
const findingEntries = computed<ProcessQualityControlReportTextEntry[]>(
  () => summary.value?.text_entries?.findings ?? [],
)

/**
 * Isi satu entri teks bebas. `text` nama server, `value` nama tech spec —
 * dibaca, bukan diubah: tidak ada pemotongan, tidak ada perapian, tidak ada
 * pengelompokan menurut isinya.
 */
function entryText(entry: ProcessQualityControlReportTextEntry): string {
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
 * ProcessQualityControlReportService::metricsOf — tiap entri hanya min/avg/max/
 * reading_count/group). Isinya karena itu disalin PERSIS dari $metrikMeta pada
 * blade screen-160 supaya ponsel dan web menuliskan satuan yang sama untuk
 * angka yang sama.
 *
 * NILAI SERVER TETAP MENANG bila kelak diterbitkan (lihat metricCardOf): tabel
 * ini CADANGAN, bukan penimpa — dan TIDAK ADA PETA SATUAN DI SINI yang
 * mengubah 'C' menjadi '°C'. Satuan '°C' dan 'm³/jam' di bawah adalah LABEL
 * STATIS yang ditulis tangan, bukan hasil transformasi nilai dari server.
 *
 * TEPAT SATU KOLOM BERSATUAN null, DAN ITU BUKAN KELALAIAN: Indeks DOBI
 * memang indeks tanpa dimensi, dan kartunya tidak mencetak apa pun di tempat
 * satuan — bukan '-', bukan '—', bukan satuan karangan demi keseragaman dengan
 * sebelas kartu lain, dan JANGAN PERNAH tanda persen.
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

const FP_SLUDGE_META: MetricMeta = {
  label: 'Oil Loss di Sludge',
  short: 'Oil Loss di Sludge (%)',
  unit: '%',
  digits: 2,
}
const FP_FIBRE_META: MetricMeta = {
  label: 'Oil Loss di Fibre',
  short: 'Oil Loss di Fibre (%)',
  unit: '%',
  digits: 2,
}
const PURIFIER_TEMP_META: MetricMeta = {
  label: 'Suhu Masuk Purifier',
  short: 'Suhu Masuk Purifier (°C)',
  unit: '°C',
  digits: 1,
}
const PURIFIER_BACKPRESSURE_META: MetricMeta = {
  label: 'Tekanan Balik Purifier',
  short: 'Tekanan Balik Purifier (Bar)',
  unit: 'Bar',
  digits: 2,
}
const VDS_DRIER_TEMP_META: MetricMeta = {
  label: 'Suhu Pengering',
  short: 'Suhu Pengering (°C)',
  unit: '°C',
  digits: 1,
}
const VDS_VACUUM_META: MetricMeta = {
  label: 'Tekanan Vakum',
  short: 'Tekanan Vakum (Bar)',
  unit: 'Bar',
  digits: 2,
}
const DECANTER_FEED_META: MetricMeta = {
  label: 'Laju Umpan Decanter',
  short: 'Laju Umpan Decanter (MT/jam)',
  unit: 'MT/jam',
  digits: 2,
}
const DECANTER_CAKE_META: MetricMeta = {
  label: 'Oil Loss di Cake',
  short: 'Oil Loss di Cake (%)',
  unit: '%',
  digits: 2,
}
const FS_FFA_META: MetricMeta = {
  label: 'ALB (FFA)',
  short: 'ALB / FFA (%)',
  unit: '%',
  digits: 2,
}
const FS_MOISTURE_META: MetricMeta = {
  label: 'Kadar Air',
  short: 'Kadar Air (%)',
  unit: '%',
  digits: 2,
}
const FS_IMPURITIES_META: MetricMeta = {
  label: 'Kadar Kotoran',
  short: 'Kadar Kotoran (%)',
  unit: '%',
  digits: 2,
}

/**
 * INDEKS TANPA SATUAN, DAN ITU BUKAN KELALAIAN.
 *
 * DOBI (Deterioration of Bleachability Index) adalah indeks tanpa dimensi,
 * BUKAN persentase — ia satu-satunya dari kedua belas ukuran layar ini yang
 * bukan besaran bersatuan. Mencetaknya bertanda persen adalah kesalahan yang
 * akan TERLIHAT BENAR di layar, karena ketiga kartu tetangganya di blok Final
 * Storage memang berpersen. `unit: null` di sini dan pemeriksaan
 * `v-if="...unit"` di template adalah satu-satunya yang mencegahnya.
 */
const FS_DOBI_META: MetricMeta = {
  label: 'Indeks DOBI',
  short: 'Indeks DOBI',
  unit: null,
  digits: 2,
}

/**
 * Kunci kanonis adalah nama kolom process_quality_control_details seperti yang
 * diterbitkan server.
 */
const METRIC_META: Record<string, MetricMeta> = {
  fruit_press_oil_loss_in_sludge_percent: FP_SLUDGE_META,
  fruit_press_oil_loss_in_fibre_percent: FP_FIBRE_META,
  purifier_clarification_balance_inlet_temp_c: PURIFIER_TEMP_META,
  purifier_clarification_balance_backpressure_bar: PURIFIER_BACKPRESSURE_META,
  vacuum_drying_station_drier_temp_c: VDS_DRIER_TEMP_META,
  vacuum_drying_station_vacuum_pressure_bar: VDS_VACUUM_META,
  decanter_centrifuge_feed_rate_mth: DECANTER_FEED_META,
  decanter_centrifuge_oil_loss_in_cake_percent: DECANTER_CAKE_META,
  final_storage_ffa_percent: FS_FFA_META,
  final_storage_moisture_content_percent: FS_MOISTURE_META,
  final_storage_impurities_dirt_percent: FS_IMPURITIES_META,
  final_storage_dobi_index: FS_DOBI_META,
}

/**
 * LIMA BLOK BERJUDUL, dalam urutan JALUR FISIK MINYAK dan dengan judul SAMA
 * PERSIS seperti versi web. Judul, urutan, dan keterangan tiap blok ditetapkan
 * di sini; KOLOMNYA TIDAK — kolom tiap blok DITURUNKAN DARI FIELD `group` PADA
 * RESPONS (lihat columnsOfBlock), supaya kolom baru dari migrasi kelak masuk ke
 * blok yang benar tanpa menyunting berkas ini.
 *
 * KELIMA BLOK ADALAH UNIT TAMPILAN LAYAR INI, bukan dua belas ukuran. Itu
 * pembeda terbesarnya dari keempat laporan bergrid mobile sebelumnya: dua belas
 * ukuran, masing-masing tiga angka dengan penyebut dan satuannya sendiri,
 * adalah tiga puluh enam angka pada lebar ponsel. Jawabannya bukan memperkecil
 * semuanya melainkan MENGHORMATI LIMA BLOKNYA — kelimanya kartu yang DAPAT
 * DILIPAT, dibuka satu per satu. Pembaca di lapangan datang dengan satu
 * pertanyaan tentang satu tahap, bukan dengan dua belas pertanyaan sekaligus.
 *
 * KELIMA BLOK TETAP ADA DI LAYAR meski seluruh ukurannya kosong: blok yang
 * hilang akan dibaca sebagai TAHAP YANG TIDAK ADA. Judulnya membawa penanda
 * ada-tidaknya data supaya pembaca tahu kartu mana yang berguna dibuka tanpa
 * harus membuka kelimanya.
 *
 * `groups` berisi LEBIH DARI SATU nilai karena satu blok yang sama dapat
 * dinamai dua cara (nama server dan nama tech spec). Keduanya diterima; yang
 * TIDAK dilakukan adalah menebak blok dari nama kolom.
 *
 * PENGELOMPOKANNYA MURNI TATA LETAK: tidak satu pun angka dihitung per blok,
 * dan TIDAK ADA TOTAL PER BLOK — satuan di dalam satu blok pun berbeda (lihat
 * Decanter/Centrifuge: MT/jam bersebelahan dengan persen).
 */
interface MetricBlock {
  groups: string[]
  testid: string
  title: string
  hint: string
  note: 'jalur' | 'satuan' | 'mutu' | null
}

const METRIC_BLOCKS: MetricBlock[] = [
  {
    groups: ['fruit_press', 'fruit-press'],
    testid: 'pqc-metric-block-fruit-press',
    title: 'Fruit Press',
    hint: 'kerugian minyak — alasan utama laporan ini dibuka',
    note: 'jalur',
  },
  {
    groups: ['purifier_clarification_balance', 'purifier'],
    testid: 'pqc-metric-block-purifier',
    title: 'Purifier & Clarification Balance',
    hint: 'kondisi proses: suhu dan tekanan',
    note: 'satuan',
  },
  {
    groups: ['vacuum_drying_station', 'vacuum_drying'],
    testid: 'pqc-metric-block-vacuum-drying',
    title: 'Vacuum Drying Station',
    hint: 'suhu pengering dan tekanan vakum',
    note: null,
  },
  {
    groups: ['decanter_centrifuge', 'decanter'],
    testid: 'pqc-metric-block-decanter',
    title: 'Decanter/Centrifuge',
    hint: 'laju umpan dan kerugian minyak ketiga',
    note: null,
  },
  {
    groups: ['final_storage', 'final-storage'],
    testid: 'pqc-metric-block-final-storage',
    title: 'Final Storage',
    hint: 'mutu akhir — angka yang masuk kontrak penjualan',
    note: 'mutu',
  },
]

/**
 * Kolom-kolom sebuah blok, DITURUNKAN DARI RESPONS: setiap entri `metrics`
 * membawa `group`-nya sendiri. Inilah yang membuat kartunya satu markup dan
 * bukan dua belas salinan. Urutan di dalam blok adalah urutan kunci pada
 * respons, yakni urutan ProcessQualityControlReportService::NUMERIC_METRICS.
 */
function columnsOfBlock(block: MetricBlock): string[] {
  return Object.keys(metrics.value).filter((column) =>
    block.groups.includes(metrics.value[column]?.group ?? ''),
  )
}

/**
 * Kedua belas kolom dalam urutan kanonis — judul kolom kedua tabel rekap. Juga
 * diturunkan dari respons, lewat kelima blok, bukan dari daftar yang dipaku.
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
function readingCountOf(metric: ProcessQualityControlReportMetric | undefined): number {
  return metric?.reading_count ?? metric?.count ?? 0
}

function metricCardOf(column: string): MetricCard {
  const meta = metricMetaOf(column)
  const metric: ProcessQualityControlReportMetric | undefined = metrics.value[column]

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

/** Jumlah unit Process Quality Control: nama server, lalu nama tech spec. */
const coverageUnitCount = computed<number | null>(() => {
  const value = coverage.value

  if (!value) {
    return null
  }

  return value.process_qc_unit_count ?? value.unit_count ?? null
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
 * pada kelengkapan karena `qc_inspector_id` kolom identitas.
 */
interface InspectorRow {
  code: string | null
  slots: number
}

function inspectorSlotsOf(entry: ProcessQualityControlReportInspectorEntry): number {
  return entry.slot_count ?? entry.filled_slots ?? entry.slots ?? 0
}

/**
 * Kedua bentuk payload dinormalkan menjadi satu daftar baris:
 *
 *   - BENTUK SERVER (objek): `entries` berkode, lalu SATU baris 'tidak
 *     tercatat' dari kunci `not_recorded` yang terpisah itu.
 *   - BENTUK TECH SPEC (daftar): baris ber-qc_inspector_id null SUDAH menjadi
 *     baris 'tidak tercatat'-nya sendiri, jadi daftarnya dipakai apa adanya —
 *     dan jumlah barisnya sama dengan panjang payload, tidak bertambah satu.
 *
 * Urutannya adalah urutan payload. TIDAK ADA pengurutan menurut jumlah slot:
 * rekap ini ada untuk menelusuri slot yang belum terisi, bukan untuk menilai
 * orang, jadi ia tidak boleh terbaca sebagai tabel peringkat.
 *
 * NOL PEMETAAN NAMA. `qc_inspector_id` tidak punya relasi ke tabel User —
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
      code: entry.qc_inspector_id ?? null,
      slots: inspectorSlotsOf(entry),
    }))
  }

  const rows: InspectorRow[] = (recap.entries ?? []).map((entry) => ({
    code: entry.qc_inspector_id ?? null,
    slots: inspectorSlotsOf(entry),
  }))

  rows.push({ code: null, slots: recap.not_recorded ?? 0 })

  return rows
})

/**
 * PENYEBUT porsi tiap baris: slot TERATRIBUSI dari respons.
 * `attributed_slots` diterbitkan server di dalam blok rekap (invarian server:
 * sum(entries) + not_recorded === attributed_slots).
 *
 * BUKAN coverage.filled_slots, DAN ITU PEMBEDA PENTING: rekap ini dihitung atas
 * SELURUH baris, bukan hanya yang terisi, sehingga slot yang HANYA mengisi
 * giliran kerja dan kode inspektur MASUK di sini meski dihitung KOSONG oleh
 * kelengkapan. Rekap menghitung ATRIBUSI, kelengkapan menghitung PEMBACAAN, dan
 * kedua angka itu berbeda nilainya pada keadaan itu. Memakai filled_slots
 * sebagai penyebut akan membuat jumlah porsinya melebihi 100%.
 *
 * `recorded_slots` adalah nama lama medan ini, tetap dibaca supaya payload lama
 * tidak mematahkan layar. TIDAK PERNAH expected_slots, yang akan mencampur slot
 * tak tersentuh ke dalam porsi baris yang justru teratribusi.
 */
const inspectorRecordedSlots = computed<number>(() => {
  const recap = summary.value?.inspector_recap

  if (recap && !Array.isArray(recap)) {
    if (recap.attributed_slots !== undefined) {
      return recap.attributed_slots
    }

    if (recap.recorded_slots !== undefined) {
      return recap.recorded_slots
    }
  }

  return coverage.value?.filled_slots ?? 0
})

/**
 * PENYEBUT TERBESAR di antara kedua belas metrik, DIBACA dari respons
 * (coverage.max_metric_reading_count). Dicetak BERDAMPINGAN dengan slot terisi,
 * supaya selisih keduanya terbaca alih-alih dicurigai sebagai cacat.
 *
 * Cadangannya menghitung maksimum dari `metrics` HANYA bila payload lama belum
 * membawa kuncinya. Itu `Math.max` atas angka yang sudah ada — bukan rata-rata
 * baru, bukan penyebut baru, dan bukan nilai laporan yang lahir di klien.
 */
const maxMetricReadingCount = computed<number>(() => {
  const published = coverage.value?.max_metric_reading_count

  if (published !== undefined) {
    return published
  }

  const counts = Object.keys(metrics.value).map((column) => metricCardOf(column).readingCount ?? 0)

  return counts.length === 0 ? 0 : Math.max(...counts)
})

/** Slot milik catatan draft, DIBACA dari respons. Draft ikut di seluruh angka. */
const draftSlotCount = computed<number>(() => summary.value?.draft_slot_count ?? 0)

/* ------------------------------------------------------------------ */
/* Rekap harian                                                        */
/* ------------------------------------------------------------------ */

/*
 * TIDAK ADA REKAP PER UNIT di layar ini. Keempat laporan bergrid mobile
 * sebelumnya punya; business spec screen-169 menerbitkan LIMA BLOK TAHAP
 * sebagai penggantinya dan tidak menyebutnya. Jumlah unitnya tetap terbaca di
 * blok kelengkapan, lewat coverage.process_qc_unit_count, karena slot yang
 * diharapkan dihitung darinya.
 */

const dailyRows = computed<ProcessQualityControlReportDailyRow[]>(() => summary.value?.daily ?? [])

/** Slot terisi satu baris rekap: `reading_rows` server, `filled_slots` tech spec. */
function dailyFilledSlots(row: ProcessQualityControlReportDailyRow): number | null {
  return row.reading_rows ?? row.filled_slots ?? null
}

/**
 * Rata-rata satu kolom pada satu baris rekap harian, DIBACA dari `metrics_avg`
 * — bukan dihitung. Kolom yang tidak diterbitkan pada tanggal itu menghasilkan
 * null, BUKAN nol: nol berarti terukur dan hasilnya nol.
 */
function dailyAvgOf(row: ProcessQualityControlReportDailyRow, column: string): number | null {
  return row.metrics_avg?.[column]?.avg ?? null
}

/* ------------------------------------------------------------------ */
/* TIDAK ADA TREN HARIAN BERGAMBAR DI LAYAR INI                        */
/* ------------------------------------------------------------------ */

/*
 * Keempat laporan bergrid mobile sebelumnya menggambar satu seri batang per
 * tanggal. Business spec screen-169 tidak memintanya, dan rekap harian di bawah
 * sudah membawa rata-rata per metrik per tanggal — angka yang sama, tanpa skala
 * tegak yang harus dijelaskan karena tidak dimulai dari nol.
 *
 * Dihilangkan alih-alih diwarisi: komputasi yang tidak dirender siapa pun
 * adalah tempat penyimpangan tumbuh, dan grafik dengan dua belas kandidat seri
 * menuntut keputusan "seri mana" yang business spec tidak ambil.
 */

/* ------------------------------------------------------------------ */
/* Penanda data pada judul kartu blok                                  */
/* ------------------------------------------------------------------ */

/**
 * Apakah blok ini punya DATA — dipakai penanda di judul kartunya, supaya
 * pembaca tahu kartu mana yang berguna dibuka tanpa membuka kelimanya.
 *
 * `some`, BUKAN `every`: satu metrik berdata sudah membuat blok itu layak
 * dibuka. `every` akan menandai blok Final Storage sebagai kosong hanya karena
 * Indeks DOBI tidak pernah diisi, yang justru kebalikan dari maksudnya.
 */
function blockHasData(block: MetricBlock): boolean {
  return columnsOfBlock(block).some((column) => (metricCardOf(column).readingCount ?? 0) > 0)
}

/**
 * Judul kartu blok, LENGKAP DENGAN PENANDA DATA-nya. Disusun di sini dan bukan
 * di template karena CollapsibleSection menerima judulnya sebagai prop teks —
 * jadi penandanya ikut terbaca pembaca layar dan ikut teruji tanpa membuka
 * kartunya.
 */
function blockTitle(block: MetricBlock): string {
  return `${block.title} — ${blockHasData(block) ? 'ada data' : 'belum ada data'}`
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
 * blok kelengkapan menjawab pertanyaan yang sama. KEDUA BELAS KARTU, KELIMA
 * BLOK, dan BLOK
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
 * Penamaan unit Process Quality Control yang kosong. `process_qc_id` adalah kolom
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

function periodOptionLabel(period: ProcessQualityControlReportPeriodOption): string {
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
  <main class="laporan-pr-view" data-testid="laporan-process-quality-control-mobile">
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
        <span aria-current="page">Laporan Process Quality Control</span>
      </nav>
      <h1 class="screen-title">Laporan Process Quality Control</h1>
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
        Mill ini belum memiliki periode pelaporan yang mencakup stasiun Process Quality Control. Silakan
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
          Belum ada slot Process Quality Control yang terisi pada periode ini untuk Production Line yang
          dipilih. Kelima blok dan kedua belas kartu di bawah tetap ditampilkan dengan penyebut nol, supaya terlihat
          bahwa parameternya ada dan tidak terukur — bukan bahwa hasilnya nol.
        </p>

        <!-- ===== KELENGKAPAN PENCATATAN — PALING ATAS, SEBELUM SATU KARTU PUN
             Bukan catatan kaki: kedua belas kolom ukur seluruhnya nullable, jadi
             periode yang terisi kurang dari separuh pun menghasilkan rata-rata
             yang terlihat rapi. Di layar sempit ia satu-satunya bagian yang
             pasti terlihat tanpa menggulir, dan ia menentukan apakah angka di
             bawahnya layak dipercaya. TETAP digambar meski periode kosong —
             justru itu yang menjelaskan kekosongannya. -->
        <section class="detail-section" data-testid="pqc-coverage">
          <h2 class="section-title">Kelengkapan Pencatatan</h2>
          <p class="section-note">
            Baca ini lebih dulu — dua belas kartu di bawah bersandar padanya.
          </p>

          <div class="metric-stack">
            <div class="metric-card">
              <span class="metric-label">Slot Waktu Terisi Sepanjang Periode</span>
              <!-- DIRENDER APA ADANYA: jumlah slot terisi dapat MELEBIHI
                   penyebut setiap kartu, dan itu benar — tidak di-clamp, tidak
                   disembunyikan, dan tanpa peringatan apa pun. -->
              <span class="metric-value" data-testid="pqc-coverage-slots">
                {{ formatCount(coverage?.filled_slots) }} dari
                {{ formatCount(coverage?.expected_slots) }} slot
              </span>
              <!-- KEDUA ANGKA BERDAMPINGAN, TANPA SYARAT: slot terisi di
                   sebelah penyebut TERBESAR di antara dua belas metrik.
                   Selisihnya selalu dicurigai sebagai cacat bila tidak
                   dijelaskan, dan kalimat yang muncul bersyarat tidak pernah
                   terbaca oleh orang yang paling membutuhkannya. Dibaca dari
                   respons — klien tidak menghitung ulang maksimumnya. -->
              <span class="metric-note" data-testid="pqc-coverage-max-denominator">
                Penyebut metrik terbesar
                <strong>{{ formatCount(maxMetricReadingCount) }}</strong> pembacaan
              </span>
              <!-- Tanda pisah HANYA ketika penyebutnya belum terbentuk. Elemen
                   ini memuat PERSENNYA SAJA, supaya ia dapat diasersi sendiri
                   tanpa ikut memindai keterangan di sekitarnya. -->
              <span
                class="metric-value"
                :class="{ 'metric-value--na': coverageDenominatorMissing }"
                data-testid="pqc-coverage-percent"
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
                data-testid="pqc-coverage-no-expected-note"
              >
                Belum ada slot yang diharapkan: tidak ada unit Process Quality Control ber-record pada
                periode dan line ini, atau periodenya belum mulai. Karena penyebutnya belum
                terbentuk, persennya &ldquo;&mdash;&rdquo; dan bukan angka nol berpersen — nol
                persen akan terbaca seolah pencatatan gagal, padahal yang belum ada adalah
                penyebutnya.
              </span>
            </div>

            <div class="metric-card">
              <span class="metric-label">Pembentuk Penyebut</span>
              <span class="metric-value" data-testid="pqc-coverage-denominator">
                {{ formatCount(coverageUnitCount) }} unit &times;
                {{ formatCount(coverage?.days_counted) }} hari &times;
                {{ formatCount(coverage?.slots_per_unit_per_day) }} slot
              </span>
              <span class="metric-note" data-testid="pqc-coverage-formula">
                Slot yang diharapkan = {{ formatCount(coverageUnitCount) }} unit Process Quality Control
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
              <span class="metric-value" data-testid="pqc-coverage-days">
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
                v-if="coverage?.period_not_started || !coverage?.days_counted"
                class="metric-note"
                data-testid="pqc-coverage-not-started-note"
              >
                Periode belum mulai, sehingga belum ada hari yang dapat dijadikan pembagi —
                persennya &ldquo;&mdash;&rdquo;.
              </span>
              <span
                v-else-if="periodRunning"
                class="metric-note"
                data-testid="pqc-coverage-running-note"
              >
                <strong>Dihitung sampai HARI INI, karena periode masih berjalan</strong> —
                {{ formatCount(coverage?.days_counted) }} hari periode sudah lewat. Hari yang belum
                terjadi tidak mungkin tercatat, jadi tidak ikut menjadi penyebut. Seluruh angka di
                bawah dihitung dari <strong>pembacaan yang ada</strong>, bukan dari periode penuh:
                slot yang tidak terisi tidak diperlakukan sebagai nol, melainkan tidak ikut
                dihitung sama sekali.
              </span>
              <span class="metric-note" data-testid="pqc-draft-note">
                Record berstatus draft IKUT terhitung pada seluruh angka laporan ini —
                <strong data-testid="pqc-draft-slot-count">{{ formatCount(draftSlotCount) }}</strong>
                slot di antaranya berasal dari catatan yang belum final.
              </span>
            </div>
          </div>

          <!-- ATURAN EMPAT BELAS KOLOM, DIRENDER VERBATIM DARI SERVER. Ponsel tidak
               menyusun kalimatnya sendiri — kalimat yang disusun dua kali di
               dua platform adalah kalimat yang akan berselisih. Elemen ini
               karena itu memuat KALIMAT SERVER SAJA, tanpa satu kata tambahan
               di dalamnya. -->
          <p
            v-if="noteFilledRule"
            class="section-note"
            data-testid="pqc-note-filled-rule"
          >{{ noteFilledRule }}</p>

          <p class="section-note" data-testid="pqc-filled-rule-consequence">
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

          <p class="section-note" data-testid="pqc-empty-period-note">
            Bila periode ternyata tidak memuat <strong>satu pun</strong> pembacaan, kedua belas
            kartu di bawah menampilkan <strong>tidak tersedia</strong> dengan penyebut nol —
            <strong>bukan nol</strong> — dan <strong>blok kelengkapan ini tetap ditampilkan</strong>.
            Kelengkapan pencatatan adalah bagian dari laporan, bukan catatan kaki, karena itu ia
            dirender <strong>paling atas</strong>.
          </p>
        </section>

        <!-- ===== DUA BELAS KARTU DALAM LIMA BLOK YANG DAPAT DILIPAT =====
             Markup kartunya ditulis SEKALI dan di-loop atas `metrics` yang
             dikelompokkan menurut field `group` DARI RESPONS — bukan atas
             daftar nama kolom yang dipaku, dan bukan dua belas salinan markup:
             pengulangan markup berarti dua belas kesempatan kehilangan penyebut
             atau MEWARISI penyebut kartu lain.

             KELIMA BLOK DAPAT DILIPAT, dan itu pembeda terbesar layar ini dari
             keempat laporan bergrid mobile sebelumnya. Dua belas ukuran dengan
             tiga angka, penyebut, dan satuan masing-masing adalah tiga puluh
             enam angka pada lebar ponsel; jawabannya bukan memperkecil semuanya
             melainkan MENGHORMATI LIMA BLOKNYA. Pembaca di lapangan datang
             dengan satu pertanyaan tentang satu tahap.

             JUDUL TIAP KARTU MEMBAWA PENANDA ADA-TIDAKNYA DATA, supaya pembaca
             tahu kartu mana yang berguna dibuka tanpa harus membuka kelimanya.
             Penandanya `some`, BUKAN `every` — lihat blockHasData().

             KELIMA BLOK TETAP ADA meski seluruh ukurannya kosong: blok yang
             hilang akan dibaca sebagai TAHAP YANG TIDAK ADA.

             MELIPAT DAN MEMBUKA MURNI PENYINGKAPAN: CollapsibleSection memakai
             `v-show`, jadi isinya tetap ada di DOM, tidak satu permintaan pun
             dipicu, dan tidak satu angka pun berubah. Keadaan lipatnya TIDAK
             disimpan ke penyimpanan perangkat. -->
        <CollapsibleSection
          v-for="(block, blockIndex) in METRIC_BLOCKS"
          :key="block.testid"
          :title="blockTitle(block)"
          :data-testid="block.testid"
          :data-metric-group="block.groups[0]"
          :data-has-data="blockHasData(block) ? 'true' : 'false'"
        >
          <p class="section-note">
            Blok {{ blockIndex + 1 }} dari {{ METRIC_BLOCKS.length }} &middot;
            {{ formatCount(metricCardsOf(block).length) }} kartu &middot; {{ block.hint }}
          </p>

          <div class="metric-stack">
            <article
              v-for="card in metricCardsOf(block)"
              :key="card.column"
              class="metric-card"
              :data-testid="`pqc-metric-card-${card.column}`"
              :data-metric="card.column"
            >
              <div class="metric-label-row">
                <span class="metric-label" :data-testid="`pqc-metric-label-${card.column}`">
                  {{ card.label }}
                </span>
                <!-- SATUAN PADA KARTUNYA SENDIRI, dan ini yang paling mengikat
                     di blok Final Storage: tiga kartu berpersen bersebelahan
                     dengan Indeks DOBI yang TANPA satuan, dan di ponsel keempat
                     kartu tidak pernah terlihat bersamaan. Kartu ber-satuan
                     null TIDAK MERENDER ELEMEN INI SAMA SEKALI — bukan '-',
                     bukan '—', dan JANGAN PERNAH tanda persen. JANGAN ubah
                     `card.unit` menjadi `card.unit ?? ''`: string kosong tetap
                     merender pil yang hampa. -->
                <span
                  v-if="card.unit"
                  class="metric-unit-pill"
                  :data-testid="`pqc-metric-unit-${card.column}`"
                  >{{ card.unit }}</span
                >
              </div>

              <div class="metric-row">
                <span class="metric-row-item">
                  <small>terendah</small>
                  <b
                    :class="{ 'metric-value--na': card.min === null }"
                    :data-testid="`pqc-metric-min-${card.column}`"
                    >{{ formatDecimal(card.min, card.digits) }}</b
                  >
                </span>
                <span class="metric-row-item">
                  <small>rata-rata</small>
                  <b
                    :class="{ 'metric-value--na': card.avg === null }"
                    :data-testid="`pqc-metric-avg-${card.column}`"
                    >{{ formatDecimal(card.avg, card.digits) }}</b
                  >
                </span>
                <span class="metric-row-item">
                  <small>tertinggi</small>
                  <b
                    :class="{ 'metric-value--na': card.max === null }"
                    :data-testid="`pqc-metric-max-${card.column}`"
                    >{{ formatDecimal(card.max, card.digits) }}</b
                  >
                </span>
              </div>

              <!-- PENYEBUT MILIK KARTU INI SENDIRI. Kedua belas kolom nullable
                   dan terisi saling bebas, jadi satu penyebut bersama akan
                   salah untuk setidaknya sebelas di antaranya — dan di ponsel
                   cacat itu lebih sulit terlihat karena kartunya tidak pernah
                   berdampingan. Elemen penyebut memuat ANGKANYA SAJA supaya ia
                   dapat diasersi per kartu. -->
              <span class="metric-note">
                dari
                <b :data-testid="`pqc-metric-count-${card.column}`">{{
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
                :data-testid="`pqc-metric-unavailable-${card.column}`"
              >
                tidak tersedia — kolom ini tidak terisi pada satu slot pun sepanjang periode dan
                line ini. Kartunya <strong>tetap ada</strong> dengan penyebut nol, bukan dihapus
                dari bloknya: kartu yang hilang tidak dapat dibedakan dari kartu yang belum pernah
                ada.
              </span>
            </article>
          </div>

          <p v-if="block.note === 'jalur'" class="section-note" data-testid="pqc-oil-path-note">
            Kedua belas kartu dikelompokkan menjadi
            <strong>lima blok berjudul yang mengikuti jalur fisik minyak</strong> —
            <strong>1 Fruit Press &rarr; 2 Purifier &amp; Clarification Balance &rarr; 3 Vacuum
            Drying Station &rarr; 4 Decanter/Centrifuge &rarr; 5 Final Storage</strong> — bukan
            mengikuti abjad dan bukan urutan kolom di basis data.
            <small>
              Dua belas angka yang berdiri sendiri tidak dapat dibaca; lima blok yang
              masing-masing menjawab <strong>satu pertanyaan tentang satu tahap</strong> dapat.
              <strong>Ketiga angka oil loss</strong> — di sludge, di fibre, dan di cake decanter —
              adalah yang paling dicari manajemen, karena setiap persen adalah uang yang keluar
              dari pabrik; dua di antaranya ada di blok ini dan yang ketiga di blok
              Decanter/Centrifuge. Urutannya bekerja ke dua arah: pembaca yang menemukan angka
              mutu akhir yang tidak ia harapkan dapat
              <strong>bergerak mundur satu tahap demi satu tahap</strong> tanpa perlu tahu nama
              kolom mana pun. Judul dan urutannya <strong>sama persis seperti versi web</strong>,
              jadi pembaca yang berpindah dari ponsel ke web tidak perlu mempelajari ulang peta
              halamannya.
            </small>
          </p>

          <!-- KETERANGAN SATUAN DIRENDER DI DALAM BLOKNYA, di tempat yang
               diterangkannya — keterangan sekali di atas halaman sudah tergulir
               jauh ketika blok yang diterangkannya akhirnya terlihat di layar
               sempit. -->
          <p v-if="block.note === 'satuan'" class="section-note" data-testid="pqc-unit-per-card-note">
            <strong>Satuan tercetak pada setiap kartu, dan di ponsel tuntutan itu lebih keras
            daripada di web.</strong>
            Suhu dalam <strong>&deg;C</strong>, tekanan dalam <strong>Bar</strong>, laju umpan
            dalam <strong>MT/jam</strong>, dan kerugian minyak serta mutu akhir dalam
            <strong>persen</strong>.
            <small>
              Di web kartu-kartu satu blok <strong>berdampingan</strong> sehingga satuannya
              terbaca sekaligus; di ponsel pembaca melihat <strong>satu kartu pada satu
              waktu</strong> dan kedekatan itu hilang. Angka tanpa satuan karena itu akan dibaca
              dengan <strong>skala kartu sebelumnya</strong>, dan dua kartu dalam satu blok pun
              dapat bersatuan berbeda. Besaran yang berbeda <strong>tidak</strong> disamakan demi
              kerapian dan <strong>tidak dijumlahkan</strong> — &lsquo;total kondisi proses&rsquo;
              bukan besaran yang punya arti. Satuannya <strong>datang dari respons</strong>, bukan
              peta di sisi klien.
            </small>
          </p>

          <!-- DUA PERNYATAAN SEKALIGUS, MENEMPEL PADA BLOK MUTU AKHIR dan bukan
               sebagai catatan kaki halaman: (a) Indeks DOBI tanpa satuan, dan
               (b) nol penandaan nilai di luar batas. Justru di blok inilah
               godaan menandai paling besar, karena keempat angka ini ADALAH
               ukuran mutu yang gunanya dibandingkan dengan batas. TANPA
               text-overflow, TANPA line-clamp, dan TANPA "baca selengkapnya":
               keterangan yang terpotong di tengah berhenti menerangkan apa pun,
               dan di layar sempit itulah godaan terbesarnya. -->
          <template v-if="block.note === 'mutu'">
            <p class="section-note" data-testid="pqc-dobi-no-unit-note">
              <strong>Indeks DOBI sengaja TANPA satuan.</strong> DOBI (Deterioration of
              Bleachability Index) adalah <strong>indeks tanpa dimensi</strong>, bukan persentase
              — ia satu-satunya dari dua belas ukuran layar ini yang bukan besaran bersatuan.
              <small>
                Menampilkannya dengan tanda persen adalah kesalahan yang akan
                <strong>terlihat benar</strong>, karena ketiga kartu tetangganya di blok ini memang
                berpersen — dan di ponsel kartu-kartu itu tidak pernah terlihat bersamaan, jadi
                kesalahannya lebih sulit tertangkap daripada di web. Karena itu pil satuan dirender
                <strong>hanya ketika respons memang membawa satuan</strong>, dan kartu ini tidak
                membawanya.
              </small>
            </p>
            <p class="section-note" data-testid="pqc-no-threshold-note">
              <strong>
                TIDAK ADA satu pun nilai di layar ini yang ditandai di luar batas, dan pernyataan
                ini dirender UTUH — tidak dipendekkan, tidak dilipat, dan tidak dipotong dengan
                elipsis demi lebar layar, karena keterangan yang dipotong di tengah berhenti
                menerangkan apa pun.
              </strong>
              Stasiun Process Quality Control
              <strong>tidak punya tabel master ambang mutu</strong> — tidak ada satu baris acuan
              yang bisa dibandingkan dengan satu pun dari dua belas kartu di atas, baik per unit
              maupun per line. Dan justru di sinilah godaan menandai paling besar di antara seluruh
              laporan stasiun: <strong>keempat angka blok ini ADALAH parameter mutu CPO yang
              gunanya dibandingkan dengan batas</strong>, dan batasnya hidup
              <strong>DI LUAR aplikasi ini</strong> — spesifikasi pembeli dan standar mutu CPO.
              Laporan ini karena itu <strong>menerbitkan angkanya tanpa menyatakan memenuhi
              spesifikasi atau tidak</strong>, aturan yang sama seperti laporan Effluent Plant,
              Kernel Dispatch, dan CPO Dispatch. Menurunkan ambang dari
              <strong>data periode ini sendiri</strong> — rata-rata &plusmn; dua simpangan baku,
              atau persentil atas pembacaan yang ada — akan menghasilkan garis yang terlihat
              berwibawa, lalu <strong>dibaca sebagai batas kontrak penjualan</strong>, padahal ia
              hanya <strong>turunan statistik</strong> dari pencatatan yang belum tentu lengkap.
              Ambang semacam itu akan ikut bergeser setiap kali kelengkapan pencatatan berubah, dan
              itu bukan sifat yang boleh dimiliki sebuah batas kontrak. Karena itu kedua belas
              angka ditampilkan <strong>apa adanya</strong>: tanpa warna peringatan, tanpa ikon,
              tanpa kata &lsquo;tinggi&rsquo; atau &lsquo;rendah&rsquo;, dan tanpa satu pun sel
              yang ditandai — <strong>termasuk nilai yang jauh di luar nominal yang dikenal
              umum</strong>, yang tetap tampil polos di layar, di Rekap Temuan, dan di berkas
              ekspor. Penilaian memenuhi atau tidak memenuhi tetap di tangan pembacanya, dan
              laporan ini tidak berpura-pura memilikinya. Bila kelak ambang mutu dimasukkan sebagai
              master, laporan ini perlu ditinjau ulang — dan peninjauan itu akan berlaku untuk
              <strong>tiga stasiun sekaligus</strong>, bukan hanya yang ini.
            </p>
          </template>
        </CollapsibleSection>

        <!-- Keterangan yang membuat pengelompokan lima blok dan kartu yang
             selalu ada dapat dibaca. Ketiadaan yang tidak dijelaskan terbaca
             sebagai fitur yang belum selesai. -->
        <section class="detail-section" data-testid="pqc-metric-blocks-note">
          <h2 class="section-title">Cara Membaca Kedua Belas Kartu</h2>
          <p class="section-note">
            Kedua belas kartu di atas dikelompokkan menjadi
            <strong>lima blok berjudul yang mengikuti jalur fisik minyak</strong> —
            <strong>1 Fruit Press &rarr; 2 Purifier &amp; Clarification Balance &rarr; 3 Vacuum
            Drying Station &rarr; 4 Decanter/Centrifuge &rarr; 5 Final Storage</strong> — dengan
            <strong>judul dan urutan yang sama persis seperti versi web</strong>, bukan mengikuti
            abjad dan bukan urutan kolom di basis data. Pembaca yang berpindah dari ponsel ke web
            karena itu tidak perlu mempelajari ulang peta halamannya, dan pembaca yang menemukan
            angka mutu akhir yang tidak ia harapkan dapat
            <strong>bergerak mundur satu tahap demi satu tahap</strong> tanpa perlu tahu nama
            kolom mana pun. Pengelompokan itu <strong>murni tata letak</strong>: tidak satu pun
            angka dihitung per blok, dan <strong>tidak ada total per blok</strong>, karena satuan
            di dalam satu blok pun berbeda.
          </p>
          <p class="section-note" data-testid="pqc-collapsible-blocks-note">
            <strong>Kelima blok DAPAT DILIPAT, dan itu penyimpangan sadar dari keempat laporan
            bergrid mobile sebelumnya</strong> — yang merender blok kartunya terbuka tanpa kontrol
            lipat, dengan alasan bahwa melipat menyembunyikan penyebut tiap kartu.
            <small>
              Alasan itu tetap benar, dan di sini ia dikalahkan oleh <strong>jumlah</strong>: dua
              belas ukuran dengan tiga angka, penyebut, dan satuan masing-masing adalah
              <strong>tiga puluh enam angka</strong> pada lebar ponsel, dan dua belas kartu terbuka
              sekaligus membuat blok kelengkapan — satu-satunya bagian yang menentukan apakah
              angkanya layak dipercaya — tergulir jauh di atas layar sebelum satu angka pun
              dibaca. Kompensasinya: <strong>judul tiap kartu blok membawa penanda ada-tidaknya
              data</strong>, sehingga penyebut nol tetap terbaca <strong>tanpa</strong> membuka
              kartunya, dan pembaca tahu kartu mana yang berguna dibuka tanpa membuka kelimanya.
              Melipat dan membuka <strong>murni penyingkapan</strong>: tidak satu permintaan pun
              dipicu, tidak satu angka pun berubah, dan keadaan lipatnya tidak disimpan ke
              perangkat. <strong>Kartu kelengkapan sendiri TIDAK dapat dilipat.</strong> Dan kelima
              blok <strong>tetap ada</strong> meski seluruh ukurannya kosong — blok yang hilang
              akan dibaca sebagai tahap yang tidak ada.
            </small>
          </p>
          <p class="section-note" data-testid="pqc-own-denominator-note">
            <strong>Setiap kartu membawa penyebutnya sendiri.</strong> Kedua belas kolom ukur
            nullable dan terisi saling bebas, jadi penyebut yang sama pada kedua belas kartu adalah
            cacat — dan di ponsel cacat itu <strong>lebih sulit terlihat daripada di web</strong>,
            karena kartunya tidak pernah berdampingan. Kartu yang tidak pernah terisi
            <strong>tetap dirender</strong> dengan keterangan &lsquo;tidak tersedia&rsquo; dan
            penyebut nol pembacaan.
          </p>
        </section>

        <!-- TIDAK ADA TREN HARIAN BERGAMBAR di layar ini — lihat catatan di
             script setup. Rekap harian di bawah membawa rata-rata per metrik
             per tanggal, angka yang sama tanpa sumbu tegak yang harus
             dijelaskan karena tidak dimulai dari nol. -->

        <!-- ===== DUA REKAP TEKS BEBAS, DUA BAGIAN TERPISAH =====
             Masing-masing daftar KEJADIAN {tanggal, slot waktu, teks}, dan
             BUKAN sebaran jumlah slot per nilai. Keduanya tidak pernah
             disatukan: satu slot yang mengisi kedua kolom muncul pada KEDUA
             daftar, dan itu BUKAN penghitungan ganda. -->
        <section class="detail-section" data-testid="pqc-recap-action-taken">
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
            data-testid="pqc-recap-action-taken-empty"
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
              :data-testid="`pqc-action-taken-row-${index}`"
              :data-date="entry.date"
              :data-time-slot="entry.time_slot"
            >
              <span class="note-meta">
                {{ formatShortDate(entry.date) }} &middot; {{ formatTimeSlot(entry.time_slot) }}
              </span>
              <p class="note-text">{{ entryText(entry) }}</p>
            </article>
          </div>

          <p class="section-note" data-testid="pqc-action-taken-note">
            <strong>Kolom tindakan koreksi teknis diterbitkan sebagai REKAP TEKS, bukan sebagai
            sebaran jumlah slot per nilai.</strong> Kolomnya
            <strong>teks bebas tanpa himpunan nilai tertutup</strong> — dan stasiun ini tidak punya
            satu pun kolom berpilihan tertutup, jadi tidak ada apa pun di layar ini yang dapat
            dihitung per label. Sebaran atas teks bebas
            menghasilkan <strong>satu &lsquo;nilai&rsquo; per kalimat yang berbeda</strong>, dan
            daftar kalimat berjumlah satu-satu bukan sebaran. Dua slot yang berbunyi sama persis
            <strong>tetap dua baris</strong> di sini, bukan satu baris berjumlah dua — karena yang
            sama persis hari ini belum tentu sama persis besok. Isinya ditampilkan apa adanya
            beserta tanggal dan slot waktunya, <strong>dibungkus utuh tanpa pemotongan</strong>,
            dan tidak pernah dirata-rata maupun digrafikkan.
          </p>
        </section>

        <section class="detail-section" data-testid="pqc-recap-findings">
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
            data-testid="pqc-recap-findings-empty"
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
              :data-testid="`pqc-finding-row-${index}`"
              :data-date="entry.date"
              :data-time-slot="entry.time_slot"
            >
              <span class="note-meta">
                {{ formatShortDate(entry.date) }} &middot; {{ formatTimeSlot(entry.time_slot) }}
              </span>
              <p class="note-text">{{ entryText(entry) }}</p>
            </article>
          </div>

          <p class="section-note" data-testid="pqc-findings-note">
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
          data-testid="pqc-inspector-recap"
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
            data-testid="pqc-inspector-recap-empty"
          >
            Belum ada satu slot terisi pun pada periode dan line ini, jadi rekap per inspector
            belum punya baris untuk ditampilkan.
          </p>

          <div v-else class="detail-table-wrap">
            <table v-stacked-table class="detail-table" data-testid="pqc-inspector-table">
              <thead>
                <tr>
                  <th scope="col">qc_inspector_id</th>
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
                  :data-testid="`pqc-inspector-row-${index}`"
                >
                  <td>
                    <code v-if="row.code !== null" :data-testid="`pqc-inspector-code-${index}`">{{
                      row.code
                    }}</code>
                    <span
                      v-else
                      class="is-muted"
                      :data-testid="`pqc-inspector-code-${index}`"
                      >tidak tercatat</span
                    >
                  </td>
                  <td class="num" :data-testid="`pqc-inspector-slots-${index}`">
                    {{ formatCount(row.slots) }}
                  </td>
                  <td class="num" :data-testid="`pqc-inspector-share-${index}`">
                    {{ formatShare(row.slots, inspectorRecordedSlots) }}
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr data-testid="pqc-inspector-total">
                  <td>Seluruh slot terisi</td>
                  <td class="num">{{ formatCount(inspectorRecordedSlots) }}</td>
                  <td class="num">{{ inspectorRecordedSlots === 0 ? '—' : '100,0%' }}</td>
                </tr>
              </tfoot>
            </table>
          </div>

          <p class="section-note" data-testid="pqc-inspector-purpose-note">
            <strong>Rekap ini ada untuk menelusuri slot yang BELUM terisi — bukan untuk menilai
            orang.</strong>
            Kelengkapan pencatatan adalah pokok layar ini, dan slot yang kosong selalu punya pemilik
            yang dapat ditanyai; itu seluruh gunanya. Angka di kolom &lsquo;slot terisi&rsquo;
            <strong>bukan nilai kinerja</strong>: ia tidak memperhitungkan panjang penugasan,
            jumlah unit yang dijaga, hari libur, maupun slot yang memang bukan jatahnya — jadi
            membandingkan dua inspector lewat angka ini akan salah, dan laporan ini
            <strong>tidak</strong> mengurutkan, memberi peringkat, atau menandai siapa pun.
          </p>
          <p class="section-note" data-testid="pqc-inspector-raw-code-note">
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

        <!-- TIDAK ADA REKAP PER UNIT di layar ini — lihat catatan di script
             setup. Jumlah unitnya terbaca di blok kelengkapan. -->

        <!-- ===== REKAP HARIAN =====
             Panjang pada periode sebulan, jadi dibungkus CollapsibleSection dan
             TERTUTUP secara bawaan supaya gulirnya tetap terkendali. Membuka
             atau menutupnya MURNI PENYINGKAPAN — datanya sudah ada di memori,
             tidak satu permintaan pun dipicu — dan tidak mengubah satu angka
             pun, termasuk baris total periode. MENUMPUK MENJADI KARTU di lebar
             ponsel. -->
        <CollapsibleSection
          :title="`Rekap Harian (${dailyRows.length} tanggal)`"
          data-testid="pqc-daily-recap"
        >
          <p class="section-note">
            Satu kartu per tanggal beserta kartu <strong>Total periode</strong>. Tanggal tanpa satu
            pun slot terisi <strong>tidak mendapat baris</strong>: baris nol akan terbaca sebagai
            &lsquo;terukur nol&rsquo;, padahal tidak ada yang diukur. Sel kosong dirender
            &lsquo;&mdash;&rsquo; — <strong>bukan 0</strong>, karena nol adalah pembacaan dan
            kosong bukan.
          </p>

          <p v-if="dailyRows.length === 0" class="section-note" data-testid="pqc-daily-recap-empty">
            Belum ada satu tanggal pun dengan slot terisi pada periode dan line ini.
          </p>

          <div v-else class="detail-table-wrap">
            <table v-stacked-table class="detail-table" data-testid="pqc-daily-table">
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
                  :data-testid="`pqc-daily-row-${row.date}`"
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
                <tr data-testid="pqc-daily-total">
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
        <section class="detail-section" data-testid="pqc-absences">
          <h2 class="section-title">Yang Tidak Diterbitkan di Layar Ini</h2>

          <p
            v-if="noteAbsences"
            class="section-note"
            data-testid="pqc-note-absences"
          >{{ noteAbsences }}</p>

          <p class="section-note" data-testid="pqc-no-downtime-note">
            <strong>Tidak ada blok downtime, dan ketiadaannya disengaja.</strong> Stasiun Process
            Quality Control <strong>tidak mencatat lama berhenti sama sekali</strong> — tidak ada
            satu kolom pun yang bisa diterbitkan, jadi tidak ada blok yang dikosongkan maupun diisi
            nol.
          </p>
          <p class="section-note" data-testid="pqc-no-per-unit-note">
            <strong>Tidak ada rekap per unit dan tidak ada tren harian bergambar.</strong> Keempat
            laporan bergrid mobile sebelumnya menerbitkan keduanya; business spec layar ini meminta
            <strong>blok TAHAP</strong> dan tidak menyebut keduanya sama sekali, jadi keduanya
            <strong>dihilangkan alih-alih diwarisi</strong>.
            <small>
              Jumlah unitnya tetap terbaca di blok kelengkapan, karena slot yang diharapkan
              dihitung darinya; dan rekap harian di bawah sudah membawa rata-rata per metrik per
              tanggal — angka yang sama dengan yang akan digambar sebuah grafik, tanpa sumbu tegak
              yang harus dijelaskan karena tidak dimulai dari nol.
            </small>
          </p>
          <p class="section-note" data-testid="pqc-no-shift-note">
            <strong>Kolom giliran kerja tidak diterbitkan di layar ini, hanya di ekspor — dan
            ketiadaannya disengaja.</strong> Ia tidak ada di payload laporan sama sekali, jadi
            ketiadaannya di layar adalah konsekuensi, bukan penyaringan di klien.
            <strong>Pengelompokan laporan periode menurut giliran kerja belum ada, baik di layar
            ini maupun di versi web, dan keduanya akan ditinjau bersama.</strong>
            <small>
              Konsekuensinya sudah tampak di blok kelengkapan: kolom itu dan kode inspektur adalah
              dua kolom identitas yang dikecualikan dari pemeriksaan slot terisi, sehingga baris
              detail membawa <strong>enam belas</strong> kolom tetapi yang dihitung hanya
              <strong>empat belas</strong>. Keduanya <strong>tetap ikut</strong> di berkas ekspor
              CSV, karena ekspor adalah rincian mentah. Dari keduanya, kode inspektur
              <strong>memang</strong> diterbitkan di layar — pada rekap per-inspektur — karena ia
              menjawab &lsquo;siapa yang dapat ditanyai tentang slot yang kosong&rsquo;, pertanyaan
              pokok layar ini; giliran kerja tidak menjawab pertanyaan itu.
            </small>
          </p>
          <p class="section-note" data-testid="pqc-no-distribution-note">
            <strong>Tidak ada satu pun daftar sebaran kategoris di layar ini</strong>, karena
            stasiun ini <strong>tidak punya SATU PUN kolom berpilihan tertutup</strong> — tak satu
            pun dari keenam belas kolom barisnya, dan kedua kolom identitasnya pun teks bebas.
            Kedua kolom catatan karena itu diterbitkan sebagai <strong>dua rekap teks
            terpisah</strong> di atas. Menyalin bentuk sebaran dari laporan stasiun sebelumnya akan
            menghasilkan satu &lsquo;nilai&rsquo; per kalimat yang berbeda — daftar kalimat yang
            masing-masing dihitung sekali bukan sebuah sebaran.
          </p>
          <p class="section-note" data-testid="pqc-no-mill-picker-note">
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
          data-testid="pqc-export-csv"
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
   tabel sungguhan dan dua belas kolom angka bisa melebihi kotaknya: di sana isi
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
