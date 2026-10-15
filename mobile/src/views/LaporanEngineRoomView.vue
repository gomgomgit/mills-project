<script setup lang="ts">
/**
 * LaporanEngineRoomView — screen-159--laporan-engine-room-mobile /
 * usecase-165--laporan-engine-room-mobile "Lihat Laporan Periode Engine Room
 * (Mobile)".
 *
 * Kembaran mobile KETIGA BELAS, dan BACAAN SAJA: layar ini tidak punya satu
 * pun jalur yang mengubah data stasiun.
 *
 * ────────────────────────────────────────────────────────────────────────
 * NOL PERUBAHAN BACKEND — DIBAYAR LEBIH DULU, DENGAN SENGAJA
 * ────────────────────────────────────────────────────────────────────────
 * BERBEDA DARI screen-157, yang pelebaran akses Operator-nya harus dikerjakan
 * di putaran mobile-nya sendiri. Ketiga rute data /api/engine-room-reports/*
 * SUDAH menerima Operator sejak commit pertama screen-158 — justru supaya
 * layar ini tidak perlu melebarkan akses apa pun — dan
 * /api/production-lines/options-for-report dipakai ulang VERBATIM dari
 * screen-135. Tidak satu berkas di backend/ disentuh untuk layar ini, dan
 * tidak ada entri api-index baru.
 *
 * ────────────────────────────────────────────────────────────────────────
 * TANPA PEMILIH MILL, DAN ITU BUKAN PEMANGKASAN
 * ────────────────────────────────────────────────────────────────────────
 * Kedua aktor layar ini (Operator dan Supervisor) TERIKAT SATU MILL, jadi
 * business_unit_id tidak pernah dikirim: server menyelesaikan mill dari akun,
 * dan repo laporan ini pun tidak punya jalur untuk mengirimkannya —
 * gagal-tertutup secara KONSTRUKSI. Konsekuensinya disengaja:
 * /api/engine-room-reports/business-units/options tidak dipanggil dari mana
 * pun. Admin dan Mill Management memakai layar WEB screen-158, yang
 * satu-satunya punya pemilih mill. Nama mill tetap DITAMPILKAN sebagai
 * keterangan — yang tidak ada hanyalah kemampuan MENGGANTINYA.
 *
 * ────────────────────────────────────────────────────────────────────────
 * DUA PULUH TIGA KARTU — GULIR TERPANJANG DI SELURUH LAPORAN MOBILE
 * ────────────────────────────────────────────────────────────────────────
 * Effluent Plant mobile punya empat belas; di sini dua puluh tiga, lebih dari
 * tiga kali Kernel Plant yang tujuh. MARKUP KARTUNYA DITULIS SEKALI DAN
 * DI-LOOP atas `metrics` yang DIKELOMPOKKAN MENURUT FIELD `group` DARI
 * RESPONS — bukan atas daftar nama kolom yang dipaku di berkas ini. Dua puluh
 * tiga kartu yang ditulis manual adalah dua puluh tiga kesempatan kehilangan
 * penyebut atau MEWARISI penyebut blok lain, dan kolom baru dari migrasi kelak
 * masuk ke blok yang benar tanpa menyunting berkas ini. HANYA kartu ekspor
 * energi yang ditulis terpisah, karena bentuknya memang berbeda (nilai
 * terakhir + slot asalnya, bukan terendah/rata-rata/tertinggi).
 *
 * KEENAM BLOK DIRENDER TERBUKA, TANPA KONTROL LIPAT. Pengelompokan enam blok
 * (Turbin Uap, Genset 1, Genset 2, Kelistrikan Tersinkron, Kompresor,
 * Penunjang) murni PENANDA TEMPAT bagi gulir yang panjang — tidak satu pun
 * angka dihitung per blok dan TIDAK ADA TOTAL PER BLOK, karena satuan di dalam
 * satu blok pun berbeda. Melipatnya memperpendek gulir tetapi menyembunyikan
 * penyebut tiap kartu, dan penyebut itulah yang paling perlu dilihat di layar
 * ini.
 *
 * ────────────────────────────────────────────────────────────────────────
 * RISIKO PALING TAJAM DI LAYAR INI LAHIR DARI LAYAR SEMPIT ITU SENDIRI
 * ────────────────────────────────────────────────────────────────────────
 * Genset 1 dan Genset 2 membawa empat kolom yang sama persis. Di web keduanya
 * BERDAMPINGAN, sehingga ketidakseimbangan beban — satu genset 800 kW
 * sementara yang lain 40 kW — terbaca seketika dari kedekatannya. Di ponsel
 * pembaca melihat satu kartu pada satu waktu dan delapan kartu genset terpisah
 * oleh gulir; kedekatan yang membuat ketidakseimbangan itu terlihat HILANG.
 *
 * Jawabannya tetap BUKAN merata-ratakan keduanya dan BUKAN menghitung
 * selisihnya. Dua alasan, masing-masing cukup sendiri: rata-rata atau selisih
 * gabungan menyembunyikan persis apa yang dicari (ketidakseimbangan beban
 * adalah TEMUAN, bukan galat), DAN menghitungnya di ponsel melanggar aturan
 * nol-aritmetika. Keduanya dicatat supaya tidak ada yang "memperbaiki" layar
 * ini dengan menambahkan badge selisih atas nama keterbacaan layar sempit.
 *
 * JAWABANNYA: KETERANGAN KESEJAJARAN FISIK DIRENDER DI DALAM KEDUA BLOK
 * GENSET — dua kali, sekali per blok, dan itu disengaja. Keterangan sekali di
 * atas halaman sudah tergulir jauh ketika blok yang diterangkannya akhirnya
 * terlihat, sehingga ia tidak menerangkan apa pun di tempat ia dibutuhkan.
 * Keterangan itu menyebut kesejajaran FISIK kedua mesin dan TIDAK menyebut
 * standar bersama: stasiun ini tidak punya tabel master target sama sekali,
 * berbeda dari pasangan ripple mill / kernel silo di Kernel Plant yang memang
 * berbagi satu baris master dan punya `shares_standard_with` pada responsnya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * DUA SEBARAN STATUS BERNILAI TIGA
 * ────────────────────────────────────────────────────────────────────────
 * ITERASINYA ATAS `known_values` DARI RESPONS — BUKAN atas kunci `counts` yang
 * ada. Mengiterasi counts menghilangkan baris bernilai 0, dan baris yang
 * hilang terbaca sebagai "nilai ini tidak ada" padahal yang benar adalah
 * "nilai ini tidak pernah terjadi". Baris `not_recorded` TERPISAH dan TIDAK
 * PERNAH dilipat ke Off: 'STANDBY' BUKAN 'OFF' pula — genset siaga siap
 * menanggung beban, genset mati tidak, dan melipat keduanya akan melaporkan
 * pembangkit mati yang tidak pernah terjadi. Penyebut porsinya
 * `recorded_slots`, BUKAN `expected_slots`.
 *
 * LABELNYA DIDELEGASIKAN ke statusValueLabel() pada engineRoomReportRepo —
 * satu salinan, bukan tersebar di template — dan salinan itu membawa catatan
 * wajib-selaras dengan Display::OPTION_LABELS beserta jalur mundur
 * `map[v] ?? v`. Run / Standby / Off, TIDAK diterjemahkan ke Bahasa Indonesia.
 *
 * ────────────────────────────────────────────────────────────────────────
 * SATU KARTU YANG BUKAN RATA-RATA
 * ────────────────────────────────────────────────────────────────────────
 * Ekspor energi harian diterbitkan server sebagai nilai TERAKHIR per tanggal,
 * bukan rata-rata periode, karena semantik kolomnya belum terjelaskan. PONSEL
 * TIDAK MENENTUKAN SLOT MANA YANG TERAKHIR: server menentukannya menurut
 * POSISI pada kisi 24 slot kanonis (07:00..06:00), dan menurunkannya ulang di
 * klien akan mengulang persis cacat yang sudah diperbaiki di screen-158 —
 * 06:00 ada di posisi TERAKHIR, sementara urutan leksikal memilihnya sebagai
 * yang paling AWAL dan hasilnya terlihat sepenuhnya masuk akal. Tidak ada
 * sort, max, maupun perbandingan jam di berkas ini; `last_value` dirender
 * beserta `time_slot`-nya apa adanya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * NOL ARITMETIKA ATAS ANGKA LAPORAN
 * ────────────────────────────────────────────────────────────────────────
 * Termasuk TIDAK memetakan satuan 'C' menjadi '°C' — satuan datang dari server
 * atau dari label statis di tabel METRIC_META di bawah, yang disalin PERSIS
 * dari $metrikMeta pada blade screen-158. Yang "dihitung" di berkas ini hanya
 * TIGA hal, ketiganya TAMPILAN dan bukan angka laporan:
 *
 *   (a) PEMETAAN NOL-KE-TANDA-HUBUNG: expected_slots === 0 dirender '—'
 *       beserta 'belum ada slot yang diharapkan'. Ini PRESENTASI, bukan
 *       aritmetika — tidak ada bilangan baru yang lahir darinya.
 *   (b) PORSI SEBARAN STATUS: payload TIDAK menerbitkan `share`, jadi
 *       porsinya dibentuk di sini dengan rumus, pembulatan, dan pemisah
 *       desimal yang DISALIN PERSIS dari helper $porsi pada blade screen-158
 *       (number_format(100 * count / recordedSlots, 1)), supaya ponsel tidak
 *       mungkin mencetak porsi yang berbeda dari web untuk payload yang sama.
 *       PENYEBUTNYA `recorded_slots` DARI RESPONS — tidak pernah
 *       expected_slots, dan tidak pernah diturunkan sendiri.
 *   (c) TINGGI BATANG TREN: skala tampilan yang sama dengan $tinggiBatang pada
 *       blade screen-158. Tidak ada nilai yang ditandai aman/bahaya
 *       karenanya, dan terendah/tertinggi series-nya DILABELI "dari rata-rata
 *       harian" seperti di web.
 *
 * Tidak ada angka lain yang diturunkan, dibulatkan ulang, atau dinormalkan di
 * sini. Aturan ini bukan gaya: begitu klien menghitung, ponsel dan web dapat
 * berselisih tanpa satu test pun memerah.
 *
 * ────────────────────────────────────────────────────────────────────────
 * YANG DIWARISI DARI SERI, DINYATAKAN KARENA MENANGGUNG BEBAN
 * ────────────────────────────────────────────────────────────────────────
 * KELENGKAPAN PENCATATAN DIRENDER PALING ATAS, sebelum satu kartu pun. Di
 * layar sempit ia satu-satunya bagian yang pasti terlihat tanpa menggulir, dan
 * ia menentukan apakah dua puluh tiga angka di bawahnya layak dipercaya.
 *
 * URUTAN CABANG KETERANGAN CAKUPAN: coverage.expected_slots === 0 dan
 * coverage.days_counted === 0 DIPERIKSA SEBELUM coverage.period_running.
 * Server menandai periode yang BELUM MULAI sebagai masih berjalan juga,
 * sehingga memeriksa period_running lebih dulu membuat keterangan "belum
 * mulai" tidak pernah terender — cacat yang nyata terjadi pada tiga layar dan
 * diperbaiki pada commit screen-148.
 *
 * SLOT TERISI DIHITUNG ATAS DUA PULUH TUJUH KOLOM — kedua puluh tiga kolom
 * ukur, kedua kolom status genset, dan KEDUA kolom teks bebas — sehingga
 * coverage.filled_slots DAPAT MELEBIHI reading_count setiap kartu metrik. Yang
 * dilampaui adalah penyebut KARTU, bukan penyebut CAKUPAN, dan keterangannya
 * dirender di blok kelengkapan supaya pembaca berikutnya tidak "memperbaiki"
 * selisih yang memang benar.
 *
 * PRODUCTION LINE WAJIB, dijaga di FUNGSI loadSummary() dan bukan di template
 * — supaya jalur coba-lagi, jalur ganti-periode, dan pemulihan dari ingatan
 * perangkat tidak melewatinya. Template yang hanya menyembunyikan angka tetap
 * membiarkan permintaannya terkirim, dan ketiga jalur itu memanggil fungsi
 * yang sama, jadi satu penjagaan melindungi ketiganya.
 *
 * URUTAN PENENTUAN LINE DI LAYAR INI TIGA LANGKAH, BUKAN EMPAT: rute ->
 * ingatan perangkat -> minta pengguna memilih. TIDAK ADA langkah "pilih line
 * pertama" DAN TIDAK ADA langkah "pilih satu-satunya line" — berbeda dari
 * LaporanEffluentPlantView.vue, yang memilih line secara otomatis bila mill
 * hanya punya satu. business_logic #5 layar ini menuliskan ketiga langkah itu
 * saja, dan alasannya: memilihkan line secara diam-diam menerbitkan angka yang
 * pengguna tidak pernah minta, dan pada laporan per-line itu berarti pembaca
 * menyangka sedang melihat seluruh mill. Akibat teknisnya: pemilih line
 * DIRENDER walau daftarnya hanya berisi satu entri.
 *
 * Ingatan line (msl_production_line_{userId}) dibagi dengan StationListView —
 * KUNCI YANG SAMA PERSIS; kunci yang berbeda akan terkompilasi, terlihat
 * benar, dan tidak pernah cocok. Pembacaannya dibungkus try/catch mengikuti
 * pola floatingClock.ts: pada mode privat penyimpanan dapat melempar, dan itu
 * tidak boleh mematahkan layar — cukup jatuh ke keadaan "belum ada line
 * terpilih", tanpa satu pesan galat pun.
 *
 * KLIEN TIDAK MEMVALIDASI KEPEMILIKAN LINE. Line basi yang masih diingat
 * perangkat tetap dikirim, dan 403 dari server diperlakukan sebagai "pilih
 * line lagi" — bukan sebagai galat jaringan. Memeriksanya sendiri akan
 * menduplikasi aturan otorisasi di dua tempat yang dapat berselisih.
 *
 * DUA PERMINTAAN /summary YANG SALING MENDAHULUI diselesaikan dengan penanda
 * permintaan terakhir, BUKAN dengan membatalkan yang lama.
 *
 * 401 MENGARAHKAN KE LOGIN TANPA PESAN JARINGAN — "periksa koneksi Anda" pada
 * sesi yang berakhir membuat pembaca memeriksa sinyal alih-alih masuk kembali.
 * 422 karena akun tanpa mill menampilkan arahan menghubungi Admin, BUKAN galat
 * jaringan. Keduanya mudah tertukar dan keduanya salah bila tertukar.
 *
 * EKSPOR HANYA CSV, dan tombol Excel TIDAK DIRENDER SAMA SEKALI — bukan karena
 * endpoint tidak mendukungnya (ia mendukung), melainkan karena WebView ponsel
 * tidak membuka .xlsx. Menyediakan tombol yang menghasilkan berkas yang tidak
 * dapat dibuka lebih buruk daripada tidak menyediakannya. Tidak ada jalur kode
 * di layar ini yang dapat menyusun format lain.
 *
 * LAYAR INI TIDAK MENULIS KE localDb MAUPUN localStorage untuk hasil laporan,
 * dan TIDAK punya mode luring. Keadaan luring dinyatakan apa adanya — angka
 * basi tanpa penanda adalah kegagalan yang lebih buruk daripada layar yang
 * berterus terang kosong.
 *
 * HALAMAN TIDAK PERNAH MENGGULIR MENDATAR PADA 390px. Rekap per unit dan rekap
 * harian membawa dua puluh tiga kolom angka — terlebar di laporan mobile mana
 * pun — dan keduanya BERTUMPUK menjadi kartu di dalam kartunya lewat
 * v-stacked-table + src/assets/stacked-table.css (temuan QA LP-M-02/LP-M-03,
 * bentuk yang sama dipakai 25 tabel di 12 layar laporan mobile lain). Grafik
 * tren beban total pabrik-lah yang MENGGULIR MENDATAR DI DALAM KARTUNYA
 * SENDIRI lewat .chart-scroll { overflow-x: auto } — tren per hari memang
 * lebih lebar daripada 390px, dan memadatkannya membuat sumbunya tidak
 * terbaca.
 *
 * DISIPLIN KELAS CSS: tiap kelas yang dipakai markup punya definisi di blok
 * <style> di bawah atau di stylesheet bersama yang dirujuknya
 * (src/assets/stacked-table.css untuk .detail-table-wrap / .detail-table /
 * .num). Dua defek lama SENGAJA TIDAK disalin: LaporanDepricarpingView.vue
 * membungkus angka dengan kelas .metric-values yang tidak terdefinisi di satu
 * berkas pun di src/, dan keterangan cakupan Depricarping mobile menyebut
 * jumlah kolom bacaan yang salah. Di sini dipakai .metric-row/.metric-row-item
 * yang terdefinisi, dan jumlah kolom bacaannya DUA PULUH TUJUH — angka yang
 * sama dengan EngineRoomRecordService::READING_FIELDS.
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
import { productionLineRepo, type ProductionLineOption } from '@/services/productionLineRepo'
import { useFloatingClockStore } from '@/stores/floatingClock'
import { useAiAssistantStore } from '@/stores/aiAssistant'
import CollapsibleSection from '@/components/CollapsibleSection.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import engineRoomReportRepo, {
  statusValueLabel,
  type EngineRoomReportMetric,
  type EngineRoomReportPeriodOption,
  type EngineRoomReportSummary,
  type EngineRoomReportTrendPoint,
} from '@/services/engineRoomReportRepo'

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

/**
 * MEMILIH LINE ITU WAJIB DI LAYAR INI, DAN TIDAK ADA OPSI "SEMUA LINE".
 *
 * Laporan memulangkan angka GABUNGAN satu periode pada satu line.
 * Menjumlahkan beberapa Production Line ke dalam satu angka menghasilkan
 * bilangan yang tidak dapat ditindaklanjuti siapa pun. Karena itu tidak ada
 * angka di layar ini sebelum ada satu line yang berlaku — dan server pun
 * menjawab 422 bila production_line_id absen, bukan memulangkan angka seluruh
 * mill.
 *
 * ── MEMAKAI ULANG INGATAN LINE MILIK LAYAR DAFTAR STASIUN ───────────────
 *
 * Kunci localStorage-nya SAMA PERSIS dengan yang ditulis StationListView.vue
 * (`msl_production_line_{userId}`) dan yang sudah dibaca
 * ReportingPilihStasiunView.vue. Line adalah konteks kerja satu orang pada
 * satu shift, bukan pengaturan per layar.
 *
 * Urutan penentuan line yang berlaku (resolveProductionLine) — TIGA LANGKAH:
 *   1. `?production_line_id=` pada rute — dibawa screen-141 saat menekan tile.
 *   2. Ingatan localStorage — pilihan terakhir pengguna ini di perangkat ini.
 *   3. Selain itu: pemilih ditampilkan, dan TIDAK ADA ANGKA.
 *
 * TIDAK ADA LANGKAH KEEMPAT. Tidak "pilih line pertama", dan — berbeda dari
 * LaporanEffluentPlantView.vue — juga tidak "pilih satu-satunya line".
 * business_logic #5 layar ini menuliskan ketiga langkah itu saja: memilihkan
 * line secara diam-diam menerbitkan angka yang pengguna tidak pernah minta.
 *
 * Nilai dari (1) dan (2) TIDAK PERNAH dipercaya begitu saja — keduanya hanya
 * dipakai bila masih ada di daftar line yang baru diambil dari server. Line
 * yang dihapus, atau pengguna yang dipindah mill, kembali memunculkan pemilih
 * dan TIDAK ada line lain yang ditebak sebagai penggantinya. Kepemilikan line
 * tidak pernah diperiksa di klien: itu urusan server, yang menjawab 403.
 *
 * Daftar line diambil dari GET /api/production-lines/options-for-report TANPA
 * business_unit_id — endpoint screen-135 yang DIPAKAI ULANG VERBATIM, tidak
 * diduplikasi di bawah prefix engine-room-reports.
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
    !noMillForAccount.value && productionLines.value.length > 0 && !selectedProductionLineId.value,
)

/** Tidak ada satu pun line yang dapat dipilih di layar ini. */
const productionLineBlocked = computed(
  () =>
    !noMillForAccount.value &&
    !loadingProductionLines.value &&
    productionLines.value.length === 0,
)

/**
 * DUA KEADAAN YANG MENUNTUT TINDAKAN BERBEDA, jadi dua pesan berbeda — dan
 * hanya yang kedua punya tombol Coba Lagi. Menyatukan keduanya akan menyuruh
 * pengguna menelepon Admin untuk masalah jaringan, atau sebaliknya
 * menyuruhnya mencoba lagi atas sesuatu yang tidak akan pernah berubah dengan
 * mencoba lagi.
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
 * perangkat yang sama. Sama persis dengan StationListView.vue.
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
 * Lihat urutan penentuan pada docblock blok ini. TIGA langkah, dan langkah
 * ketiga adalah "tidak ada line terpilih" — bukan sebuah pilihan.
 */
function resolveProductionLine(lines: ProductionLineOption[]): void {
  const fromRoute = lines.find((line) => line.id === routeProductionLineId())
  const remembered = lines.find((line) => line.id === readRememberedProductionLineId())

  // TIDAK ADA `?? lines[0]` DAN TIDAK ADA `lines.length === 1 ? lines[0]`
  // di sini, dan ketiadaannya disengaja — lihat docblock blok ini.
  const resolved = fromRoute ?? remembered ?? null

  selectedProductionLineId.value = resolved?.id ?? null

  if (resolved) {
    // Ditulis kembali supaya pilihan yang dibawa screen-141 ikut menjadi
    // ingatan — layar Daftar Stasiun dan layar laporan berbagi satu konteks.
    rememberProductionLineId(resolved.id)
  }
}

async function loadProductionLines(): Promise<void> {
  loadingProductionLines.value = true
  productionLineFetchFailed.value = false

  try {
    // TANPA business_unit_id: mill diselesaikan server dari akun, dan repo
    // laporan ini pun tidak punya jalur untuk mengirimkannya.
    const lines = await productionLineRepo.fetchProductionLinesForReport(null)

    productionLines.value = lines
    resolveProductionLine(lines)
  } catch (error) {
    // 401 di sini tetap berarti sesi berakhir, jadi ia DITERUSKAN ke
    // handleError() yang mengarahkan ke Login TANPA pesan jaringan — pesan
    // "daftar line gagal dimuat" pada sesi yang berakhir mengirim pembaca
    // memeriksa sinyal alih-alih masuk kembali. Galat lainnya BUKAN kegagalan
    // laporan: ia tidak boleh menyamar sebagai galat ringkasan, jadi ia
    // mengisi state-nya sendiri dengan tombol Coba Lagi-nya sendiri.
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
 * Cakupan yang dikirim repo — SATU medan, dan tidak ada medan mill di
 * dalamnya untuk dapat salah terkirim.
 */
const scope = computed(() => ({
  productionLineId: selectedProductionLineId.value,
}))

/**
 * Nama mill yang sedang berlaku, sebagai keterangan.
 *
 * DIBACA DARI summary.business_unit.name SAJA. Respons /periods layar ini
 * TIDAK memuat nama mill sama sekali — membacanya dari sana menghasilkan
 * undefined yang ikut dirender, dan koreksi itu sudah ditemukan saat
 * screen-155 diimplementasikan. Cadangannya mill akun, untuk keadaan sebelum
 * ringkasan pertama tiba.
 */
const currentMillName = computed(
  () => summary.value?.business_unit?.name || authStore.businessUnit?.name || '',
)

/* ------------------------------------------------------------------ */
/* Periode & ringkasan                                                 */
/* ------------------------------------------------------------------ */

const periods = ref<EngineRoomReportPeriodOption[]>([])
const selectedPeriodId = ref<string | null>(null)
const summary = ref<EngineRoomReportSummary | null>(null)

const loadingPeriods = ref(false)
/**
 * Daftar periode sudah pernah selesai dimuat (audit loading state
 * 2026-10-05). Tanpa ini "Mill ini belum memiliki periode" tampil selama
 * daftar line/periode MASIH dimuat — daftar kosong saat memuat terbaca
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
 * Layar ini PERLU JARINGAN: ia tidak menyimpan hasil laporan ke localDb
 * maupun localStorage dan tidak punya mode luring. Keadaan luring karena itu
 * DINYATAKAN apa adanya — angka basi tanpa penanda adalah kegagalan yang
 * lebih buruk daripada layar yang berterus terang kosong, dan layar yang diam
 * lebih buruk lagi karena di layar sentuh diam tidak dapat dibedakan dari
 * aplikasi yang macet.
 */
const isOffline = ref(false)

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
 * `{ message, errors?, status? }` dan sudah MEMBUANG `response` di sana —
 * jadi `.response` tidak pernah ada pada galat yang sampai ke sini. Medan itu
 * karena itu tidak ditulis pada antarmuka ini: yang tidak dapat ditulis tidak
 * dapat salah.
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
 *                penjagaannya ada di fungsi pemuatan. Arahannya menghubungi
 *                Admin, BUKAN galat jaringan, dan daftar seluruh mill tidak
 *                pernah ditawarkan.
 *   'message' -> /export. Di sana 422 berarti EXPORT_FAILED (lebih dari
 *                50.000 baris slot), dan pesannya ditampilkan APA ADANYA —
 *                layar ini tidak mencoba memotong, memecah, atau mengulang
 *                permintaan dengan rentang lebih kecil.
 *
 * Menyeragamkan keduanya akan menampilkan arahan "hubungi Admin" untuk ekspor
 * yang terlalu besar, atau sebaliknya pesan batas baris untuk akun yang belum
 * terhubung mill — keduanya pesan yang salah.
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
 * Penanganan galat terpusat. URUTAN CABANGNYA MENENTUKAN BENAR/SALAHNYA
 * LAYAR, dan dua pasang di antaranya paling mudah tertukar:
 *
 *   401            -> Login, TANPA pesan jaringan dan tanpa tombol Coba Lagi.
 *                     "Periksa koneksi Anda" pada sesi yang berakhir mengirim
 *                     pembaca memeriksa sinyal alih-alih masuk kembali.
 *   422 tanpa mill -> arahan menghubungi Admin, BUKAN galat jaringan dan
 *                     BUKAN daftar seluruh mill.
 *   403 /summary   -> "pilih line lagi": pilihan line dikosongkan, bukan
 *                     ditampilkan sebagai galat jaringan. Inilah jalur line
 *                     basi dari ingatan perangkat.
 *   404 /summary   -> "periode tidak ditemukan", dan pilihan periode
 *                     dikosongkan.
 *   tanpa status   -> penolakan tanpa respons = jaringan; periode dan line
 *                     yang sudah terpilih SENGAJA tidak disentuh.
 *   sisanya        -> pesan yang dapat dibaca pengguna (422 validasi lain —
 *                     jalur pertahanan kedua di belakang penjaga line).
 */
function handleError(
  error: unknown,
  retry: (() => Promise<void>) | null,
  meaning422: Meaning422 = 'message',
): void {
  const status = statusOf(error)

  if (status === 401) {
    // Sesi berakhir. Diteruskan ke penjagaan sesi; layar ini TIDAK mengarang
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
    // selectedPeriodId dan line SENGAJA tidak direset: pengguna di area tanpa
    // sinyal tidak boleh dipaksa memilih dari awal, dan Coba Lagi harus
    // memuat ulang periode dan line yang SAMA.
    return
  }

  if (status === 403) {
    // Line (atau periode) milik mill lain. BUKAN galat jaringan: pilihan line
    // dikosongkan supaya pengguna memilih lagi, dan tidak satu angka pun dari
    // mill lain pernah dirender. Klien tidak memeriksa kepemilikan sendiri —
    // itu akan menduplikasi aturan otorisasi di dua tempat.
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
    // Akun terikat mill yang business_unit_id-nya NULL — keadaannya sendiri,
    // bukan galat jaringan dan bukan pesan validasi mentah.
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
    periods.value = await engineRoomReportRepo.fetchPeriods()
  } catch (error) {
    periods.value = []
    handleError(error, loadPeriods, 'mill')
  } finally {
    loadingPeriods.value = false
    periodsLoaded.value = true
  }
}

async function loadSummary(): Promise<void> {
  const periodId = selectedPeriodId.value

  // TIDAK ADA SATU ANGKA PUN SEBELUM ADA LINE YANG BERLAKU, dan penjagaan ini
  // ada DI SINI — di dalam fungsi pemuatan — bukan hanya di template, supaya
  // tidak ada jalur pemanggilan yang bisa melewatinya. KETIGA jalur itu
  // (ganti periode, ganti line, coba lagi) memanggil fungsi ini, jadi satu
  // penjagaan melindungi ketiganya; penjaga yang hanya ada di template
  // dilewati oleh ketiganya, dan melewatinya di layar ini berarti 422 dari
  // server — bukan angka seluruh mill.
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
    const result = await engineRoomReportRepo.fetchSummary(periodId, scope.value)
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

onMounted(async () => {
  syncOnlineState()
  window.addEventListener('online', syncOnlineState)
  window.addEventListener('offline', syncOnlineState)

  if (!accountBusinessUnitId.value) {
    // Berhenti di sini. TIDAK ada permintaan apa pun, dan TIDAK ada pemilih
    // mill sebagai gantinya.
    noMillForAccount.value = true

    return
  }

  // Daftar line lebih dulu, periode menyusul — /periods memang TIDAK tersaring
  // line, tetapi ringkasan tidak dapat dimuat sebelum sebuah line berlaku.
  await loadProductionLines()
  await loadPeriods()
})

onUnmounted(() => {
  window.removeEventListener('online', syncOnlineState)
  window.removeEventListener('offline', syncOnlineState)
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

  return `laporan-engine-room_${slug || 'periode'}_${lineSlug || 'line'}.csv`
}

async function onExport(): Promise<void> {
  const periodId = selectedPeriodId.value

  // Ekspor mengikuti cakupan yang sama dengan angka di layar: tanpa line,
  // tidak ada berkas — berkas yang mencampur line adalah bentuk kesalahan
  // yang paling sulit dibantah setelah terkirim. `exporting` mengunci tombol
  // sesaat selama berkas disiapkan, sehingga klik ganda hanya mengirim SATU
  // permintaan.
  if (!periodId || !selectedProductionLineId.value || exporting.value) {
    return
  }

  exporting.value = true

  try {
    // Isi CSV dibentuk SERVER (satu baris per slot waktu, 27 kolom bacaan,
    // kedua kolom status genset memakai label Run/Standby/Off yang sama
    // dengan layar Detail). Layar ini tidak pernah menyusun berkasnya dari
    // angka yang tampil. Nama berkas diambil SAAT ekspor diminta: bila
    // pengguna mengganti periode selama berkas diunduh, isinya tetap milik
    // periode yang diekspor, jadi namanya pun harus milik periode itu.
    // format=csv SELALU — tidak ada jalur kode yang dapat menyusun format
    // lain, dan tombol .xlsx tidak dirender sama sekali.
    const filename = exportFilename()
    const blob = await engineRoomReportRepo.exportCsv(periodId, scope.value)
    engineRoomReportRepo.saveCsvFile(blob, filename)
  } catch (error) {
    // Kegagalan ekspor tidak dapat "diulang" secara bermakna oleh tombol Coba
    // Lagi yang sama, jadi retry-nya null. 401 tetap mengarah ke Login tanpa
    // pesan jaringan, dan tidak ada berkas yang disimpan. 422 EXPORT_FAILED
    // (lebih dari 50.000 baris slot) ditampilkan APA ADANYA — layar ini tidak
    // mencoba memotong, memecah, atau mengulang dengan rentang lebih kecil.
    handleError(error, null)
  } finally {
    exporting.value = false
  }
}

/* ------------------------------------------------------------------ */
/* Turunan tampilan (tanpa perhitungan angka laporan)                  */
/* ------------------------------------------------------------------ */

const coverage = computed(() => summary.value?.coverage ?? null)
const metrics = computed(() => summary.value?.metrics ?? {})
const energyExport = computed(() => summary.value?.daily_energy_export ?? null)
const energySeries = computed(() => summary.value?.daily_energy_export?.series ?? [])
const statusDistribution = computed(() => summary.value?.status_distribution ?? {})
const maintenanceRemarks = computed(() => summary.value?.maintenance_remarks ?? [])
const findings = computed(() => summary.value?.findings ?? [])
const byUnit = computed(() => summary.value?.by_unit ?? [])
const daily = computed(() => summary.value?.daily ?? [])
const dailyTotal = computed(() => summary.value?.daily_total ?? null)
const totals = computed(() => summary.value?.total ?? null)

/**
 * Label, satuan, dan jumlah desimal tiap kolom ukur — SATU SUMBER, dipakai
 * kartu metrik, rekap per unit, dan rekap harian, sehingga satu perubahan
 * label tidak mungkin meninggalkan dua ejaan di layar.
 *
 * KENAPA TABEL INI ADA DI VIEW DAN BUKAN DIBACA DARI RESPONS: payload Engine
 * Room memang TIDAK menerbitkan label maupun satuan per metrik. Isinya karena
 * itu disalin PERSIS dari $metrikMeta pada blade screen-158 supaya ponsel dan
 * web menuliskan satuan yang sama untuk angka yang sama.
 *
 * NILAI SERVER TETAP MENANG bila kelak diterbitkan (lihat metricCardOf): tabel
 * ini cadangan, bukan penimpa — dan TIDAK ADA PETA SATUAN DI SINI yang
 * mengubah 'C' menjadi '°C'. Satuan '°C' di bawah adalah LABEL STATIS yang
 * ditulis tangan, bukan hasil transformasi nilai 'C' dari server. Bila
 * ejaannya perlu berubah, yang berubah adalah $metrikMeta di blade dan tabel
 * ini bersama-sama.
 *
 * LABEL KEDUA KARTU KOMPRESOR DITULIS MANUSIAWI DAN TIDAK DITURUNKAN DARI NAMA
 * KOLOM, karena pasangannya dinamai tidak konsisten di basis data
 * (air_compressor_1_pressure_bar melawan compressor_2_pressure_bar) — itu
 * pertanyaan terbuka screen-158, dan laporan ini tidak menyamakan keduanya.
 *
 * fuel_tank_level SENGAJA TANPA SATUAN: kolomnya satu-satunya kolom numerik
 * stasiun ini yang tidak membawa satuan di namanya, dan persen atau liter
 * belum jelas. Mengarang satuannya akan membuat angkanya salah dibaca.
 *
 * Kedua kolom status dan kedua kolom teks bebas TIDAK ADA di daftar ini, dan
 * memang tidak boleh ada: terendah/rata-rata/tertinggi tidak punya arti
 * untuknya. daily_energy_export_kwh pun tidak ada di sini — ia punya metanya
 * sendiri (ENERGY_META) karena tidak punya rata-rata periode sama sekali.
 */
interface MetricMeta {
  /** Judul kartu — bentuk yang sama dengan blade screen-158. */
  label: string
  /** Judul kolom tabel rekap — bentuk pendek, karena kolomnya dua puluh tiga. */
  short: string
  unit: string
  digits: number
}

const METRIC_META: Record<string, MetricMeta> = {
  steam_turbine_inlet_pressure_bar: {
    label: 'Tekanan Masuk',
    short: 'Tekanan Masuk Turbin (bar)',
    unit: 'bar',
    digits: 1,
  },
  steam_turbine_inlet_temp_c: {
    label: 'Suhu Masuk',
    short: 'Suhu Masuk Turbin (°C)',
    unit: '°C',
    digits: 0,
  },
  steam_turbine_exhaust_pressure_bar: {
    label: 'Tekanan Buang',
    short: 'Tekanan Buang (bar)',
    unit: 'bar',
    digits: 2,
  },
  steam_turbine_rpm: { label: 'Putaran', short: 'Putaran (rpm)', unit: 'rpm', digits: 0 },
  steam_turbine_alternator_bearing_temp_1_c: {
    label: 'Suhu Bearing Alternator 1',
    short: 'Bearing Alt. 1 (°C)',
    unit: '°C',
    digits: 1,
  },
  steam_turbine_alternator_bearing_temp_2_c: {
    label: 'Suhu Bearing Alternator 2',
    short: 'Bearing Alt. 2 (°C)',
    unit: '°C',
    digits: 1,
  },
  diesel_gen_1_load_kw: { label: 'Beban', short: 'G1 Beban (kW)', unit: 'kW', digits: 0 },
  diesel_gen_1_amperage_a: { label: 'Ampere', short: 'G1 Ampere (A)', unit: 'A', digits: 0 },
  diesel_gen_1_jacket_water_temp_c: {
    label: 'Suhu Jacket Water',
    short: 'G1 Jacket Water (°C)',
    unit: '°C',
    digits: 1,
  },
  diesel_gen_1_lube_oil_pressure_bar: {
    label: 'Tekanan Lube Oil',
    short: 'G1 Lube Oil (bar)',
    unit: 'bar',
    digits: 2,
  },
  diesel_gen_2_load_kw: { label: 'Beban', short: 'G2 Beban (kW)', unit: 'kW', digits: 0 },
  diesel_gen_2_amperage_a: { label: 'Ampere', short: 'G2 Ampere (A)', unit: 'A', digits: 0 },
  diesel_gen_2_jacket_water_temp_c: {
    label: 'Suhu Jacket Water',
    short: 'G2 Jacket Water (°C)',
    unit: '°C',
    digits: 1,
  },
  diesel_gen_2_lube_oil_pressure_bar: {
    label: 'Tekanan Lube Oil',
    short: 'G2 Lube Oil (bar)',
    unit: 'bar',
    digits: 2,
  },
  electrical_sync_total_factory_load_kw: {
    label: 'Beban Total Pabrik',
    short: 'Beban Total (kW)',
    unit: 'kW',
    digits: 0,
  },
  electrical_sync_system_frequency_hz: {
    label: 'Frekuensi Sistem',
    short: 'Frekuensi (Hz)',
    unit: 'Hz',
    digits: 2,
  },
  electrical_sync_power_factor: {
    label: 'Power Factor',
    short: 'Power Factor',
    unit: '',
    digits: 3,
  },
  electrical_sync_busbar_voltage_v: {
    label: 'Tegangan Busbar',
    short: 'Tegangan Busbar (V)',
    unit: 'V',
    digits: 0,
  },
  air_compressor_1_pressure_bar: {
    label: 'Tekanan Kompresor Udara 1',
    short: 'Kompresor Udara 1 (bar)',
    unit: 'bar',
    digits: 1,
  },
  compressor_2_pressure_bar: {
    label: 'Tekanan Kompresor 2',
    short: 'Kompresor 2 (bar)',
    unit: 'bar',
    digits: 1,
  },
  battery_charger_ups_voltage_v: {
    label: 'Tegangan UPS / Battery Charger',
    short: 'Tegangan UPS (V)',
    unit: 'V',
    digits: 1,
  },
  fuel_tank_level: { label: 'Level Tangki Bahan Bakar', short: 'Level Tangki', unit: '', digits: 0 },
}

/**
 * Meta kolom ukur KE-23, DI LUAR METRIC_META, karena ia memang bukan bagian
 * dari `metrics`: tidak ada rata-rata periode untuknya di mana pun pada
 * payload. Disalin dari $energyMeta pada blade screen-158.
 */
const ENERGY_META: MetricMeta = {
  label: 'Ekspor Energi Harian',
  short: 'Ekspor Energi (kWh)',
  unit: 'kWh',
  digits: 0,
}

/**
 * ENAM BLOK BERJUDUL. Judul, urutan, dan keterangan tiap blok ditetapkan di
 * sini; KOLOMNYA TIDAK — kolom tiap blok DITURUNKAN DARI FIELD `group` PADA
 * RESPONS (lihat columnsOfGroup), supaya kolom baru dari migrasi kelak masuk
 * ke blok yang benar tanpa menyunting berkas ini.
 *
 * PENGELOMPOKANNYA MURNI PENANDA TEMPAT bagi gulir yang panjang: tidak satu
 * pun angka dihitung per blok, dan TIDAK ADA TOTAL PER BLOK — satuan di dalam
 * satu blok pun berbeda (power factor tanpa satuan, suhu °C).
 *
 * `note` menentukan keterangan apa yang dirender DI DALAM blok itu, di tempat
 * yang diterangkannya. 'genset' muncul DUA KALI — sekali di tiap blok genset —
 * dan itu disengaja; lihat docblock berkas.
 */
interface MetricBlock {
  group: string
  testid: string
  title: string
  hint: string
  note: 'genset' | 'threshold' | 'compressor' | 'auxiliary' | null
}

const METRIC_BLOCKS: MetricBlock[] = [
  {
    group: 'steam_turbine',
    testid: 'metric-block-steam-turbine',
    title: 'Turbin Uap',
    hint: 'masing-masing dengan penyebutnya sendiri',
    note: null,
  },
  {
    group: 'diesel_gen_1',
    testid: 'metric-block-diesel-gen-1',
    title: 'Genset 1',
    hint: 'mesin fisik tersendiri',
    note: 'genset',
  },
  {
    group: 'diesel_gen_2',
    testid: 'metric-block-diesel-gen-2',
    title: 'Genset 2',
    hint: 'kolom yang sama persis dengan Genset 1 · mesin fisik tersendiri',
    note: 'genset',
  },
  {
    group: 'electrical_sync',
    testid: 'metric-block-electrical-sync',
    title: 'Kelistrikan Tersinkron',
    hint: 'angka sistem, bukan angka per mesin',
    note: 'threshold',
  },
  {
    group: 'compressor',
    testid: 'metric-block-compressor',
    title: 'Kompresor',
    hint: 'dua metrik tekanan',
    note: 'compressor',
  },
  {
    group: 'auxiliary',
    testid: 'metric-block-auxiliary',
    title: 'Penunjang',
    hint: 'satu di antaranya diperlakukan berbeda',
    note: 'auxiliary',
  },
]

/**
 * Kolom-kolom sebuah blok, DITURUNKAN DARI RESPONS: setiap entri `metrics`
 * membawa `group`-nya sendiri. Inilah yang membuat kartunya satu markup dan
 * bukan dua puluh dua salinan. Urutan di dalam blok adalah urutan kunci pada
 * respons, yakni urutan EngineRoomReportService::NUMERIC_METRICS.
 */
function columnsOfGroup(group: string): string[] {
  return Object.keys(metrics.value).filter((column) => metrics.value[column]?.group === group)
}

/**
 * Kedua puluh dua kolom dalam urutan kanonis — judul kolom kedua tabel rekap.
 * Juga diturunkan dari respons, lewat keenam blok, bukan dari daftar yang
 * dipaku.
 */
const METRIC_COLUMNS = computed<string[]>(() =>
  METRIC_BLOCKS.flatMap((block) => columnsOfGroup(block.group)),
)

/**
 * Satu kartu metrik siap render.
 *
 * Tidak ada satu pun angka yang diturunkan di sini: min/avg/max dan
 * reading_count diteruskan apa adanya dari `metrics[kolom]`.
 *
 * Kolom yang tidak ada pada respons tetap menghasilkan kartu (nilai null,
 * penyebut 0): kartu yang hilang terbaca sebagai "tidak ada metrik ini"
 * padahal yang benar adalah "tidak ada yang mengukurnya".
 */
interface MetricCard {
  column: string
  label: string
  unit: string
  digits: number
  min: number | null
  avg: number | null
  max: number | null
  readingCount: number
}

function metricMetaOf(column: string): MetricMeta {
  // Kolom yang belum punya label manusiawi — misalnya kolom baru dari migrasi
  // — tetap dirender memakai nama kolomnya, bukan hilang dari layar.
  return METRIC_META[column] ?? { label: column, short: column, unit: '', digits: 2 }
}

function metricCardOf(column: string): MetricCard {
  const meta = metricMetaOf(column)
  const metric: EngineRoomReportMetric | undefined = metrics.value[column]

  return {
    column,
    // Nilai SERVER menang bila ada; tabel view hanya cadangan, dan tidak ada
    // pemetaan satuan di antara keduanya.
    label: metric?.label || meta.label,
    unit: metric?.unit ?? meta.unit,
    digits: meta.digits,
    min: metric?.min ?? null,
    avg: metric?.avg ?? null,
    max: metric?.max ?? null,
    readingCount: metric?.reading_count ?? 0,
  }
}

/** Dipakai sekali per blok oleh markup kartu yang ditulis SEKALI dan di-loop. */
function metricCardsOf(block: MetricBlock): MetricCard[] {
  return columnsOfGroup(block.group).map((column) => metricCardOf(column))
}

/**
 * Jumlah kartu yang dirender sebuah blok — termasuk kartu energi pada blok
 * Penunjang, yang ditulis terpisah. Angka ini hanya keterangan tata letak,
 * bukan angka laporan.
 */
function cardCountOf(block: MetricBlock): number {
  return columnsOfGroup(block.group).length + (block.group === 'auxiliary' ? 1 : 0)
}

/** Judul kolom pendek untuk kedua tabel rekap. */
function metricShortLabel(column: string): string {
  return metricMetaOf(column).short
}

/** Jumlah desimal kolom itu, dipakai sel kedua tabel rekap. */
function metricDigits(column: string): number {
  return metricMetaOf(column).digits
}

/**
 * DUA SEBARAN STATUS. Judulnya dan urutannya ditetapkan di sini; DAFTAR
 * NILAInya TIDAK — ia diiterasi dari `known_values` pada respons.
 */
interface StatusBlock {
  column: string
  testid: string
  title: string
}

const STATUS_BLOCKS: StatusBlock[] = [
  {
    column: 'diesel_gen_1_status',
    testid: 'status-distribution-diesel-gen-1',
    title: 'Sebaran Status Genset 1',
  },
  {
    column: 'diesel_gen_2_status',
    testid: 'status-distribution-diesel-gen-2',
    title: 'Sebaran Status Genset 2',
  },
]

const EMPTY_DISTRIBUTION = {
  known_values: [] as string[],
  counts: {} as Record<string, number>,
  not_recorded: 0,
  recorded_slots: 0,
}

function distributionOf(column: string) {
  return statusDistribution.value[column] ?? EMPTY_DISTRIBUTION
}

function statusCountOf(column: string, value: string): number {
  return distributionOf(column).counts?.[value] ?? 0
}

const hasReport = computed(
  () =>
    Boolean(summary.value) &&
    !networkError.value &&
    !errorMessage.value &&
    !millNotLinked.value &&
    !productionLineRejected.value &&
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
 * Ada tidaknya rekap dan grafik yang pantas digambar. Tabel kosong akan
 * terbaca sebagai hasil pengukuran bernilai nol dan grafik kosong sebagai
 * garis datar hasil pengukuran, padahal tidak ada yang diukur — sementara
 * blok CAKUPAN, KEDUA PULUH TIGA KARTU, dan KEDUA SEBARAN tetap digambar:
 * yang pertama menjelaskan kekosongannya, dan yang lain memperlihatkan bahwa
 * parameternya ADA dan tidak terukur.
 */
const showRecaps = computed(() => Boolean(summary.value?.has_data))

/**
 * PENYEBUT CAKUPAN TIDAK TERBENTUK — tidak ada unit ber-record, atau tidak
 * ada hari terhitung. INILAH satu-satunya keadaan yang membuat persen cakupan
 * dirender '—'.
 *
 * Diputuskan dari expected_slots === 0 dan BUKAN dari coverage_percent ===
 * null: payload Engine Room mengirim 0.0 pada keadaan ini (lihat
 * EngineRoomReportService::buildSummary), jadi memutuskannya dari null akan
 * menampilkan '0,0%' justru di tempat '—' seharusnya. Sebaliknya, periode yang
 * PUNYA record tetapi tanpa satu slot terisi menghasilkan 0,0%, dan itu benar
 * — penyebutnya ada.
 *
 * PEMETAAN INI PRESENTASI, BUKAN ARITMETIKA: tidak ada bilangan baru yang
 * lahir darinya, hanya sebuah angka yang diganti tanda hubung karena
 * penyebutnya belum terbentuk.
 */
const coverageDenominatorMissing = computed(() => (coverage.value?.expected_slots ?? 0) === 0)

/* ------------------------------------------------------------------ */
/* Tren harian — SATU, dan BUKAN atas kolom energi maupun status       */
/* ------------------------------------------------------------------ */

const TREND_COLUMN = 'electrical_sync_total_factory_load_kw'

const trendPoints = computed<EngineRoomReportTrendPoint[]>(
  () => summary.value?.daily_trend?.electrical_sync_total_factory_load_kw ?? [],
)

/**
 * Nilai terendah/tertinggi SERIES untuk SKALA TAMPILAN batang, ditiru baris
 * demi baris dari blade screen-158 (yang menurunkan $minTren/$maksTren dengan
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
 * rumus yang sama dengan $tinggiBatang pada blade screen-158.
 *
 * SUMBU TEGAK SENGAJA TIDAK DIMULAI DARI NOL, dan hal itu DINYATAKAN di
 * kartunya: batang membandingkan tanggal satu sama lain, bukan besaran
 * mutlak. Ini semata skala tampilan — tidak ada nilai yang ditandai
 * aman/bahaya karenanya, dan titik tanpa pembacaan menghasilkan batang KOSONG
 * (0%), bukan batang nol yang terbaca sebagai hasil ukur.
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
 * Persen kelengkapan cakupan, satu desimal. '—' HANYA bila penyebutnya tidak
 * terbentuk — keputusannya diambil pemanggil lewat coverageDenominatorMissing,
 * bukan dari null-nya nilai ini. 0 tetap dirender '0,0%': penyebutnya ada, dan
 * yang terukur memang nol slot.
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
 * PORSI SEBUAH NILAI STATUS — satu-satunya pembagian atas angka laporan di
 * berkas ini, dan ia ada karena payload TIDAK menerbitkan `share`.
 *
 * Rumus, pembulatan, dan pemisah desimalnya DISALIN PERSIS dari helper $porsi
 * pada blade screen-158 (number_format(100 * count / recordedSlots, 1)),
 * supaya ponsel tidak mungkin mencetak porsi yang berbeda dari web untuk
 * payload yang sama.
 *
 * PENYEBUTNYA recorded_slots — slot TERCATAT, DARI RESPONS — BUKAN
 * expected_slots: penyebut kedua itu akan mencampur slot tak tersentuh ke
 * dalam porsi sebuah nilai yang justru tercatat. Penyebut nol menghasilkan
 * '—', bukan 0,0%.
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

/** Tanggal pendek untuk label sumbu tren dan judul kejadian teks bebas. */
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
 * Tidak ada penormalan di sini — penormalan jam adalah tempat lahirnya
 * selisih antara ponsel dan web, dan pada kolom energi ia juga tempat
 * lahirnya cacat 06:00 yang sudah diperbaiki di screen-158.
 */
function formatTimeSlot(value: string | null | undefined): string {
  return value ? value : '-'
}

/**
 * Penamaan unit Engine Room yang kosong. engine_room_id adalah kolom TEKS yang
 * diketik di layar input dan bukan kunci asing, jadi nilai kosong mungkin saja
 * sampai ke sini — barisnya tetap diberi label alih-alih dirender kosong,
 * karena sel tanpa label membuat seluruh baris terbaca sebagai cacat
 * tampilan. Label ini keputusan view, bukan sesuatu yang repo seragamkan.
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

function periodOptionLabel(period: EngineRoomReportPeriodOption): string {
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
  <main class="laporan-pr-view" data-testid="laporan-engine-room-mobile">
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
          {{ aiAssistantStore.bubbleEnabled ? 'Nonaktifkan Bubble Chat AI' : 'Aktifkan Bubble Chat AI' }}
        </button>
        <button type="button" class="nav-menu-item" @click="openAiAssistant">Bantuan AI</button>
        <button
          type="button"
          class="nav-menu-item"
          data-testid="nav-menu-logout"
          @click="onLogout"
        >
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
        <span aria-current="page">Laporan Engine Room</span>
      </nav>
      <h1 class="screen-title">Laporan Engine Room</h1>
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
             punya satu — memilihkannya akan membuat pembaca menyangka sedang
             melihat seluruh mill. Itu beda yang disengaja dari
             LaporanEffluentPlantView.vue. -->
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
           alih-alih ditutupi angka basi tanpa penanda. -->
      <p v-if="isOffline" class="notice notice--warning" role="alert" data-testid="offline-notice">
        Perangkat sedang tidak terhubung jaringan. Laporan periode
        <strong>memerlukan jaringan</strong> — angkanya dihitung di server setiap kali dibaca dan
        tidak disimpan di perangkat ini, jadi layar ini tidak menampilkan angka dari pemuatan
        sebelumnya tanpa penanda. Sambungkan kembali jaringan, lalu coba lagi.
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
        line yang berlaku, tidak satu angka pun diminta ke server. Pilihan itu tidak pernah dibuat
        untuk Anda, bahkan ketika mill ini hanya punya satu line.
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
        Mill ini belum memiliki periode pelaporan yang mencakup stasiun Engine Room.
        Silakan hubungi Admin untuk membuat periode pelaporan terlebih dahulu.
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

      <p v-else-if="errorMessage" class="notice notice--error" role="alert" data-testid="error-message">
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
          Belum ada slot Engine Room yang terisi pada periode ini untuk Production Line yang
          dipilih. Kedua puluh tiga kartu di bawah tetap ditampilkan dengan penyebut nol, supaya
          terlihat bahwa parameternya ada dan tidak terukur — bukan bahwa hasilnya nol.
        </p>

        <!-- ===== Kelengkapan pencatatan — PALING ATAS =====
             Bukan catatan kaki: dua puluh tiga kolom ukur seluruhnya nullable,
             jadi periode yang terisi seperlima pun menghasilkan rata-rata yang
             terlihat rapi. Di layar sempit ia satu-satunya bagian yang pasti
             terlihat tanpa menggulir, dan ia menentukan apakah angka di
             bawahnya layak dipercaya. TETAP digambar meski periode kosong —
             justru itu yang menjelaskan kekosongannya. -->
        <section class="detail-section" data-testid="coverage">
          <h2 class="section-title">Kelengkapan Pencatatan</h2>
          <p class="section-note">
            Baca ini lebih dulu — 23 kartu di bawah bersandar padanya.
          </p>

          <div class="metric-stack">
            <div class="metric-card">
              <span class="metric-label">Slot Waktu Terisi Sepanjang Periode</span>
              <span class="metric-value" data-testid="coverage-slots">
                {{ formatCount(coverage?.filled_slots) }} dari
                {{ formatCount(coverage?.expected_slots) }} slot
              </span>
              <!-- Tanda pisah HANYA ketika penyebutnya tidak terbentuk
                   (expected_slots nol). Periode yang punya record tetapi tanpa
                   satu slot terisi menghasilkan 0,0% — dan itu benar, karena
                   penyebutnya ada. -->
              <span
                class="metric-note"
                :class="{ 'metric-value--na': coverageDenominatorMissing }"
                data-testid="coverage-percent"
              >
                {{
                  coverageDenominatorMissing
                    ? '—'
                    : formatCompletenessPercent(coverage?.coverage_percent)
                }}
              </span>
              <span
                v-if="coverageDenominatorMissing"
                class="metric-note"
                data-testid="coverage-no-expected-note"
              >
                Belum ada slot yang diharapkan: tidak ada unit Engine Room ber-record pada periode
                dan line ini, atau periodenya belum mulai. Karena penyebutnya belum terbentuk,
                persennya &ldquo;&mdash;&rdquo; dan BUKAN 0,0% — nol persen akan terbaca seolah
                pencatatan gagal.
              </span>
            </div>

            <div class="metric-card">
              <span class="metric-label">Pembentuk Penyebut</span>
              <span class="metric-value" data-testid="coverage-denominator">
                {{ formatCount(coverage?.engine_room_unit_count) }} unit ×
                {{ formatCount(coverage?.days_counted) }} hari ×
                {{ formatCount(coverage?.slots_per_unit_per_day) }} slot
              </span>
              <span class="metric-note" data-testid="coverage-formula">
                Slot yang diharapkan = {{ formatCount(coverage?.engine_room_unit_count) }} unit
                Engine Room × {{ formatCount(coverage?.days_counted) }} hari ×
                {{ formatCount(coverage?.slots_per_unit_per_day) }} slot =
                {{ formatCount(coverage?.expected_slots) }}. Jumlah unit adalah unit yang
                BENAR-BENAR punya record pada periode dan line ini, bukan jumlah stasiun terdaftar;
                dan 24 slot kanonis (07:00&ndash;06:00 keesokan hari) diambil dari kisi slot layar
                input itu sendiri, bukan dikarang di sini.
              </span>
            </div>

            <div class="metric-card">
              <span class="metric-label">Hari Dihitung</span>
              <span class="metric-value" data-testid="coverage-days">
                {{ formatCount(coverage?.days_counted) }} dari
                {{ formatCount(coverage?.days_in_period) }} hari
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
                Periode belum mulai, sehingga belum ada hari yang dapat dijadikan pembagi —
                persennya &ldquo;&mdash;&rdquo;, bukan 0%.
              </span>
              <span
                v-else-if="coverage?.period_running"
                class="metric-note"
                data-testid="coverage-running-note"
              >
                Dihitung sampai hari ini, periode masih berjalan
                ({{ formatCount(coverage?.days_counted) }} dari
                {{ formatCount(coverage?.days_in_period) }} hari periode sudah lewat). Hari yang
                belum terjadi tidak mungkin tercatat, jadi tidak ikut menjadi penyebut.
              </span>
              <span class="metric-note" data-testid="draft-note">
                Record berstatus draft IKUT terhitung pada seluruh angka laporan ini.
              </span>
            </div>
          </div>

          <!-- DUA PULUH TUJUH KOLOM, dan karena itu slot terisi dapat MELEBIHI
               penyebut setiap kartu. Dinyatakan di sini supaya pembaca
               berikutnya tidak "memperbaiki" selisih yang memang benar. -->
          <p class="section-note" data-testid="coverage-twenty-seven-columns-note">
            Satu slot dianggap <strong>terisi</strong> bila
            <strong>salah satu dari 27 kolomnya</strong> terisi — kedua puluh tiga kolom ukur,
            kedua kolom status genset, atau <strong>kedua kolom teks bebas</strong> (catatan
            tindakan perawatan dan temuan). Definisinya dipinjam dari layar input itu sendiri,
            bukan diturunkan ulang di sini. Karena itu jumlah slot terisi di atas dapat
            <strong>MELEBIHI penyebut setiap kartu</strong>: sebuah slot yang hanya mengisi temuan
            tetap terhitung pada kelengkapan tanpa menambah penyebut kartu mana pun. Yang
            dilampaui adalah penyebut <strong>kartu</strong>, bukan penyebut kelengkapan — itu
            benar, bukan selisih yang perlu dicocokkan. Seluruh angka di bawah dihitung dari
            <strong>pembacaan yang ada</strong>, bukan dari periode penuh: slot yang tidak terisi
            tidak diperlakukan sebagai nol, melainkan tidak ikut dihitung sama sekali.
          </p>
        </section>

        <!-- ===== DUA PULUH TIGA KARTU DALAM ENAM BLOK BERJUDUL =====
             Markup kartunya ditulis SEKALI dan di-loop atas `metrics` yang
             dikelompokkan menurut field `group` DARI RESPONS — bukan atas
             daftar nama kolom yang dipaku, dan bukan 23 salinan markup:
             pengulangan markup berarti sebuah blok dapat diam-diam kehilangan
             penyebutnya atau MEWARISI penyebut blok lain. Pengelompokan enam
             blok murni penanda tempat — tidak ada total per blok.

             KEENAM BLOK DIRENDER TERBUKA, TANPA SATU KONTROL LIPAT: melipatnya
             menyembunyikan penyebut tiap kartu, dan penyebut itu yang paling
             perlu dilihat di layar ini. -->
        <section
          v-for="(block, blockIndex) in METRIC_BLOCKS"
          :key="block.testid"
          class="detail-section"
          :data-testid="block.testid"
          :data-metric-group="block.group"
        >
          <h2 class="section-title">{{ block.title }}</h2>
          <p class="section-note">
            Blok {{ blockIndex + 1 }} dari {{ METRIC_BLOCKS.length }} &middot;
            {{ formatCount(cardCountOf(block)) }} metrik &middot; {{ block.hint }}
          </p>

          <div class="metric-stack">
            <article
              v-for="card in metricCardsOf(block)"
              :key="card.column"
              class="metric-card"
              data-testid="metric-card"
              :data-metric="card.column"
            >
              <span class="metric-label">
                {{ card.label }}<small v-if="card.unit"> &middot; {{ card.unit }}</small>
              </span>

              <div class="metric-row">
                <span class="metric-row-item">
                  <small>terendah</small>
                  <b :class="{ 'metric-value--na': card.min === null }">
                    {{ formatDecimal(card.min, card.digits) }}
                  </b>
                </span>
                <span class="metric-row-item">
                  <small>rata-rata</small>
                  <b :class="{ 'metric-value--na': card.avg === null }" data-testid="metric-avg">
                    {{ formatDecimal(card.avg, card.digits) }}
                  </b>
                </span>
                <span class="metric-row-item">
                  <small>tertinggi</small>
                  <b :class="{ 'metric-value--na': card.max === null }">
                    {{ formatDecimal(card.max, card.digits) }}
                  </b>
                </span>
              </div>

              <!-- PENYEBUT MILIK KARTU INI SENDIRI. Kedua puluh tiga kolom
                   nullable dan terisi saling bebas, jadi satu penyebut bersama
                   akan salah untuk setidaknya dua puluh dua di antaranya — dan
                   di ponsel cacat itu lebih sulit terlihat karena kartunya
                   tidak pernah berdampingan. Kartu tanpa satu pun pembacaan
                   TETAP dirender dengan penyebut 0. -->
              <span class="metric-note" data-testid="metric-denominator">
                dari {{ formatCount(card.readingCount) }} pembacaan
                <small>
                  (slot yang mengisi kolom ini sendiri; slot yang tidak mengisinya tidak
                  mengukurnya nol — ia tidak mengukurnya sama sekali)
                </small>
              </span>
            </article>

            <!-- KARTU KE-23, DAN SATU-SATUNYA YANG TIDAK MENERBITKAN RATA-RATA
                 PERIODE. Ditulis terpisah karena bentuknya memang berbeda:
                 nilai TERAKHIR per tanggal beserta slot asalnya, bukan
                 terendah/rata-rata/tertinggi. Serinya ada di bagiannya sendiri
                 di bawah. -->
            <article
              v-if="block.group === 'auxiliary'"
              class="metric-card"
              data-testid="energy-export-card"
              data-metric="daily_energy_export_kwh"
            >
              <span class="metric-label">
                {{ ENERGY_META.label }}<small> &middot; {{ ENERGY_META.unit }}</small>
                <span class="metric-pill" data-testid="energy-export-badge">nilai terakhir</span>
              </span>
              <span class="metric-note" data-testid="energy-export-no-average-note">
                <strong>Kartu ini tidak punya rata-rata periode sama sekali</strong>, dan itu
                disengaja — lihat serinya per tanggal di bagian
                &ldquo;Seri Harian Ekspor Energi&rdquo; di bawah beserta slot waktu asal tiap
                nilainya dan alasan lengkapnya.
              </span>
              <span
                v-if="!energyExport?.has_data"
                class="metric-note metric-value--na"
                data-testid="energy-export-empty"
              >
                Kolom ekspor energi harian tidak terisi pada satu slot pun sepanjang periode dan
                line ini, jadi tidak ada data yang dapat ditampilkan — bukan angka nol, yang akan
                terbaca sebagai hasil pengukuran.
              </span>
              <span v-else class="metric-note" data-testid="energy-export-dates">
                {{ formatCount(energySeries.length) }} tanggal berdata, masing-masing dengan nilai
                terakhirnya sendiri dan slot waktu asalnya.
              </span>
            </article>
          </div>

          <!-- KETERANGAN YANG SAMA DI KEDUA BLOK GENSET, DIRENDER DI DALAM
               BLOKNYA — jadi ia muncul TEPAT DUA KALI di halaman, dan itu
               disengaja. Di web kedelapan kartu genset berdampingan sehingga
               ketidakseimbangan beban terbaca dari kedekatannya; di ponsel
               kedekatan itu hilang karena kartunya terpisah oleh gulir,
               sehingga keterangan sekali di atas halaman sudah tergulir jauh
               ketika blok yang diterangkannya akhirnya terlihat.
               Ia menyebut kesejajaran FISIK kedua mesin dan TIDAK menyebut
               standar bersama — stasiun ini tidak punya master target sama
               sekali, berbeda dari pasangan ripple mill / kernel silo di
               Kernel Plant yang memang berbagi satu baris master. -->
          <p v-if="block.note === 'genset'" class="section-note" data-testid="genset-parallel-note">
            <strong>Genset 1 dan Genset 2 adalah dua mesin fisik yang berbeda.</strong> Keempat
            kolomnya sejajar <strong>secara fisik</strong> — beban, ampere, suhu jacket water,
            tekanan lube oil — tetapi angkanya
            <strong>sengaja tidak dirata-ratakan menjadi satu</strong>, dan
            <strong>selisih di antara keduanya tidak dihitung maupun ditandai sebagai
            penyimpangan</strong>. Ketidakseimbangan beban adalah
            <strong>temuan, bukan galat</strong>: satu genset menanggung hampir seluruh beban
            sementara yang lain menganggur adalah hal yang perlu dilihat, dan rata-rata gabungan
            justru menyembunyikannya sebagai &ldquo;normal&rdquo;. Kesejajaran yang dinyatakan di
            sini adalah kesejajaran <strong>fisik</strong> kedua mesin —
            <strong>bukan standar bersama</strong>: stasiun ini tidak punya tabel master target
            sama sekali, jadi tidak ada satu baris acuan yang keduanya bagi bersama. Genset yang
            tidak pernah menyala sepanjang periode menghasilkan empat kartu tidak tersedia dengan
            penyebut nol, dan itu keadaan operasional yang sah — bukan galat. Keterangan ini
            dirender di <strong>dalam kedua blok genset</strong>, sekali di masing-masing, karena
            di layar sempit keterangan di atas halaman sudah tergulir jauh ketika blok yang
            diterangkannya akhirnya terlihat.
          </p>

          <!-- PERNYATAAN KETIADAAN PENANDAAN DI LUAR BATAS, DIRENDER UTUH dan
               MENEMPEL PADA BLOK INI — bukan sebagai catatan kaki halaman.
               Justru di blok inilah godaan menandai paling besar, karena
               frekuensi 50 Hz, power factor, dan tegangan busbar nominal
               dikenal sangat luas di luar aplikasi ini. TANPA text-overflow,
               TANPA line-clamp, dan TANPA "baca selengkapnya": keterangan yang
               terpotong di tengah berhenti menerangkan apa pun, dan di layar
               sempit itulah godaan terbesarnya. -->
          <p v-if="block.note === 'threshold'" class="section-note" data-testid="no-threshold-note">
            <strong>
              TIDAK ADA penandaan nilai di luar batas pada metrik mana pun di layar ini, dan
              pernyataan ini dirender utuh tanpa diringkas.
            </strong>
            Stasiun Engine Room tidak punya tabel master target. Yang membuat pernyataan ini lebih
            keras daripada di laporan mana pun sebelumnya: beberapa metrik di blok ini punya batas
            yang <strong>dikenal sangat luas di luar aplikasi</strong> — frekuensi sistem 50 Hz,
            power factor, dan tegangan busbar nominal — sehingga godaan menandai penyimpangan
            paling besar justru di sini. Laporan ini tetap
            <strong>tidak menurunkan satu pun ambang dari data periode itu sendiri</strong>, dan
            tidak menyatakan satu nilai pun berada di luar batas. Alasannya bukan selera tampilan:
            ambang yang diturunkan dari sebaran periode hanyalah
            <strong>turunan statistik</strong> atas data yang justru sedang dinilainya, sementara
            pembaca akan membacanya sebagai <strong>batas keselamatan kelistrikan</strong>. Nilai
            yang jauh dari nominal ditampilkan apa adanya, tanpa satu kelas, ikon, maupun warna
            peringatan. Bila kelak batas operasional kelistrikan dimasukkan sebagai master target,
            laporan ini perlu ditinjau ulang — dan peninjauan itu menyangkut keselamatan peralatan,
            bukan sekadar tampilan.
          </p>

          <p
            v-if="block.note === 'compressor'"
            class="section-note"
            data-testid="compressor-label-note"
          >
            Label kedua kartu ini
            <strong>ditulis manusiawi dan tidak diturunkan mekanis dari nama kolom</strong>, karena
            pasangannya dinamai tidak konsisten di basis data:
            <code>air_compressor_1_pressure_bar</code> melawan
            <code>compressor_2_pressure_bar</code>. Apakah &lsquo;compressor 2&rsquo; juga
            kompresor udara atau kompresor jenis lain masih pertanyaan terbuka, jadi laporan ini
            tidak menyamakan keduanya, tidak menggabungkan angkanya, dan tidak membandingkannya.
          </p>

          <p
            v-if="block.note === 'auxiliary'"
            class="section-note"
            data-testid="auxiliary-label-note"
          >
            <strong>Level tangki bahan bakar ditulis tanpa satuan</strong>, dan itu disengaja:
            kolomnya satu-satunya kolom numerik stasiun ini yang tidak membawa satuan di namanya —
            dua puluh dua lainnya membawa <code>_bar</code>, <code>_c</code>, <code>_kw</code>,
            <code>_a</code>, <code>_hz</code>, <code>_v</code>, atau <code>_kwh</code>. Persen atau
            liter belum jelas, dan keduanya menghasilkan pembacaan yang sangat berbeda: level 90
            berarti hampir penuh bila persen, dan hampir kosong bila liter pada tangki besar.
            Laporan ini tidak mengarang satuannya sampai pertanyaan itu dijawab.
            <strong>Ekspor energi harian pun diperlakukan berbeda</strong> dari kedua kartu di
            sebelahnya — ia tidak punya rata-rata periode sama sekali.
          </p>
        </section>

        <!-- Keterangan yang membuat pengelompokan enam blok, kartu yang selalu
             ada, dan KETIADAAN downtime dapat dibaca. Ketiadaan yang tidak
             dijelaskan terbaca sebagai fitur yang belum selesai. -->
        <section class="detail-section" data-testid="metric-blocks-note">
          <h2 class="section-title">Cara Membaca Kedua Puluh Tiga Kartu</h2>
          <p class="section-note">
            Kedua puluh tiga kartu di atas dikelompokkan menjadi
            <strong>enam blok berjudul</strong> yang sama dengan versi web. Pengelompokan itu
            <strong>murni penanda tempat</strong> bagi gulir yang panjang — tidak satu pun angka
            dihitung per blok, dan <strong>tidak ada total per blok</strong>, karena satuan di
            dalam satu blok pun berbeda. Keenam blok dibiarkan
            <strong>terbuka dan tidak dapat dilipat</strong>: melipatnya memperpendek gulir tetapi
            menyembunyikan penyebut tiap kartu, dan penyebut itulah yang paling perlu dilihat di
            layar ini. Kedua puluh tiga kartu selalu dirender, termasuk kolom yang tidak pernah
            terisi: kartunya tetap ada dengan keterangan &lsquo;tidak tersedia&rsquo; dan penyebut
            0 pembacaan, karena kartu yang hilang terbaca sebagai &lsquo;tidak ada metrik
            ini&rsquo; padahal yang benar adalah &lsquo;tidak ada yang mengukurnya&rsquo;.
          </p>
          <p class="section-note" data-testid="no-target-note">
            <strong>Tidak ada kolom target maupun rencana tindakan koreksi</strong> pada kartu mana
            pun di layar ini, karena stasiun ini
            <strong>tidak punya tabel master target</strong>. Kernel Plant dan Depricarping mobile
            membawa kolom target di tiap kartunya; di sini tidak ada medan yang dikarang sebagai
            keterangan selalu-kosong hanya demi sebangun dengan kedua laporan itu.
          </p>
          <p class="section-note" data-testid="no-downtime-note">
            <strong>Tidak ada pula bagian downtime</strong>, karena stasiun ini
            <strong>tidak mencatat lama berhenti sama sekali</strong> — engine_room_details tidak
            punya satu pun kolomnya, dan formulir Engine Room pun tidak. Ketiadaannya dinyatakan di
            sini supaya tidak terbaca sebagai bagian yang gagal dimuat, dan supaya tidak
            ditambahkan kelak hanya karena &lsquo;laporan stasiun lain punya&rsquo;.
          </p>
        </section>

        <!-- ===== DUA SEBARAN STATUS KATEGORIS — TIGA NILAI MASING-MASING =====
             Kategoris: terendah/rata-rata/tertinggi tidak punya arti, jadi
             keduanya diterbitkan sebagai sebaran jumlah slot per nilai, dan
             tidak satu pun muncul pada kartu metrik maupun pada tren.
             ITERASINYA ATAS known_values, bukan atas kunci counts yang ada. -->
        <section
          v-for="block in STATUS_BLOCKS"
          :key="block.testid"
          class="detail-section"
          :data-testid="block.testid"
        >
          <h2 class="section-title">{{ block.title }}</h2>
          <p class="section-note">
            {{ formatCount(distributionOf(block.column).known_values.length) }} nilai dikenal
            &middot; dasar
            <strong>{{ formatCount(distributionOf(block.column).recorded_slots) }} slot tercatat</strong>
          </p>

          <div class="detail-table-wrap">
            <table v-stacked-table class="detail-table" :data-testid="`${block.testid}-table`">
              <thead>
                <tr>
                  <th scope="col">Nilai</th>
                  <th scope="col" class="num">Slot</th>
                  <th scope="col" class="num">Porsi</th>
                </tr>
              </thead>
              <tbody>
                <!-- ITERASI known_values, BUKAN kunci counts: nilai dikenal
                     yang jumlahnya 0 TETAP tercetak. Baris yang hilang terbaca
                     sebagai "nilai ini tidak ada" padahal yang benar adalah
                     "nilai ini tidak pernah terjadi". Labelnya DIDELEGASIKAN ke
                     statusValueLabel() pada repo — satu salinan peta label,
                     dengan jalur mundur map[v] ?? v sehingga nilai baru dari
                     migrasi tampil mentah alih-alih hilang. -->
                <tr
                  v-for="value in distributionOf(block.column).known_values"
                  :key="value"
                  data-testid="status-row"
                  :data-status-value="value"
                >
                  <td data-testid="status-value-label">{{ statusValueLabel(value) }}</td>
                  <td class="num">{{ formatCount(statusCountOf(block.column, value)) }}</td>
                  <td class="num">
                    {{
                      formatShare(
                        statusCountOf(block.column, value),
                        distributionOf(block.column).recorded_slots,
                      )
                    }}
                  </td>
                </tr>
                <!-- BARIS TERPISAH, dan TIDAK PERNAH dilipat ke Off: slot yang
                     kolom statusnya kosong berarti tidak ada yang mencatatnya,
                     bukan genset yang mati. -->
                <tr data-testid="status-not-recorded">
                  <td>Tidak tercatat</td>
                  <td class="num">{{ formatCount(distributionOf(block.column).not_recorded) }}</td>
                  <td class="num">
                    {{
                      formatShare(
                        distributionOf(block.column).not_recorded,
                        distributionOf(block.column).recorded_slots,
                      )
                    }}
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr data-testid="status-recorded-total">
                  <td>Seluruh slot tercatat</td>
                  <td class="num">
                    {{ formatCount(distributionOf(block.column).recorded_slots) }}
                  </td>
                  <td class="num">
                    {{ distributionOf(block.column).recorded_slots === 0 ? '—' : '100,0%' }}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </section>

        <!-- 'STANDBY' BUKAN 'OFF' dirender sebagai pernyataannya sendiri,
             karena melipat keduanya adalah kesalahan yang akibatnya paling
             besar di layar ini: ia melaporkan pembangkit mati yang tidak
             pernah terjadi. -->
        <section class="detail-section" data-testid="standby-not-off-note">
          <h2 class="section-title">&ldquo;Standby&rdquo; Bukan &ldquo;Off&rdquo;</h2>
          <p class="section-note">
            <strong>Genset siaga siap menanggung beban; genset mati tidak.</strong> Melipat Standby
            ke Off akan melaporkan pembangkit mati yang tidak pernah terjadi — dan itu kesimpulan
            yang salah tentang kesiapan pabrik. Label <strong>Run / Standby / Off</strong> dipakai
            apa adanya dari peta label server (<code>Display::OPTION_LABELS</code>), sama dengan
            layar Detail, formulir, dan kedua berkas ekspor; layar laporan
            <strong>tidak menerjemahkannya sendiri</strong>, karena satu nilai hanya boleh punya
            satu label di seluruh produk. Nilai di luar peta itu dicetak
            <strong>apa adanya</strong>, bukan disembunyikan: nilai baru dari migrasi kelak harus
            terlihat.
          </p>
        </section>

        <section class="detail-section" data-testid="status-distribution-note">
          <h2 class="section-title">Mengapa Kedua Kolom Itu Sebaran</h2>
          <p class="section-note">
            Kedua kolom di atas <strong>kategoris</strong>: terendah, rata-rata, dan tertinggi
            tidak punya arti untuknya, jadi keduanya diterbitkan sebagai
            <strong>sebaran jumlah slot per nilai</strong> — dan tidak satu pun dari keduanya
            muncul pada kartu metrik maupun pada tren harian. Sebaran disusun dengan
            <strong>mengelompokkan nilai yang benar-benar ada</strong>, lalu melengkapi nilai yang
            dikenal tetapi tidak muncul dengan jumlah <strong>nol</strong> — itulah sebabnya
            sebuah nilai yang tidak pernah terjadi sepanjang periode tetap tampil sebagai baris
            nol, bukan hilang dari daftar. <strong>&ldquo;Tidak tercatat&rdquo; adalah keadaan
            tersendiri</strong> dan tidak pernah diperlakukan sebagai Run, Standby, maupun Off:
            &ldquo;tidak kami tulis&rdquo; dan &ldquo;memang tidak jalan&rdquo; adalah dua
            kenyataan yang berbeda akibatnya. <strong>Porsinya memakai jumlah slot tercatat sebagai
            penyebut</strong>, bukan jumlah slot yang diharapkan — yang kedua akan mencampur slot
            tak tersentuh ke dalam porsi sebuah nilai yang justru tercatat. Kedua sebaran dirender
            sebagai <strong>dua bagian terpisah</strong>; tidak ada sebaran gabungan dua genset.
          </p>
        </section>

        <!-- ===== Seri harian ekspor energi — NILAI TERAKHIR, BUKAN RATA-RATA =====
             Dirender sebagai daftar per tanggal beserta SLOT WAKTU asalnya,
             karena slot itulah yang membuat nilainya dapat diperiksa pembaca.
             PONSEL TIDAK MENENTUKAN SLOT MANA YANG TERAKHIR — server
             menentukannya menurut posisi pada kisi 24 slot kanonis. -->
        <section class="detail-section" data-testid="daily-energy-export">
          <h2 class="section-title">Seri Harian Ekspor Energi</h2>
          <p class="section-note">
            <strong>Nilai TERAKHIR hari itu</strong>, dalam {{ ENERGY_META.unit }} &middot;
            {{ formatCount(energySeries.length) }} tanggal berdata
          </p>

          <p
            v-if="!energyExport?.has_data"
            class="section-note"
            data-testid="daily-energy-export-empty"
          >
            Kolom ekspor energi harian tidak terisi pada satu slot pun sepanjang periode dan line
            ini, jadi serinya tidak menampilkan satu titik pun — bukan garis nol, yang akan
            terbaca sebagai hasil pengukuran.
          </p>

          <div v-else class="detail-table-wrap">
            <table v-stacked-table class="detail-table" data-testid="daily-energy-export-table">
              <thead>
                <tr>
                  <th scope="col">Tanggal</th>
                  <th scope="col" class="num">Nilai terakhir ({{ ENERGY_META.unit }})</th>
                  <th scope="col">Slot waktu asal</th>
                  <th scope="col" class="num">Pembacaan</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="point in energySeries"
                  :key="`energy-${point.date}`"
                  data-testid="energy-export-row"
                  :data-date="point.date"
                  :data-time-slot="point.time_slot"
                >
                  <td>{{ formatDate(point.date) }}</td>
                  <td class="num" data-testid="energy-export-last">
                    {{ formatCell(point.last_value, ENERGY_META.digits) }}
                  </td>
                  <td data-testid="energy-export-time-slot">{{ formatTimeSlot(point.time_slot) }}</td>
                  <td class="num">{{ formatCount(point.reading_count) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <p class="section-note" data-testid="daily-energy-export-note">
            <strong>
              Seri ini berisi nilai TERAKHIR hari itu, bukan rata-rata — dan tidak ada rata-rata
              periode untuk kolom ini sama sekali.
            </strong>
            Sebabnya: nama kolomnya, <code>daily_energy_export_kwh</code>, menyebut
            &lsquo;daily&rsquo; sementara ia disimpan <strong>per slot waktu</strong>, dan
            ketidakcocokan itu belum terjelaskan di sumber mana pun. Dua bacaan sama-sama masuk
            akal: (a) ia angka meter <strong>kumulatif harian</strong> yang dicatat ulang tiap
            slot, atau (b) ia energi yang diekspor <strong>pada slot itu</strong> dan kata
            &lsquo;daily&rsquo; salah nama. Di bawah bacaan (a), merata-ratakan atau
            menjumlahkannya antar 24 slot menghasilkan angka yang
            <strong>terlihat sah dan sepenuhnya salah</strong>. Nilai terakhir per tanggal aman di
            bawah <em>kedua</em> bacaan, jadi itulah yang diterbitkan.
            <strong>&ldquo;Terakhir&rdquo; ditentukan SERVER dari posisi slot pada kisi
            kanonis</strong> (07:00 sampai 06:00 keesokan hari), bukan dari perbandingan angka jam:
            06:00 adalah slot paling AKHIR, bukan paling awal — dan ponsel ini tidak menurunkannya
            ulang, melainkan merender nilai beserta slot asalnya apa adanya. Nilai yang
            <strong>turun</strong> dibanding slot sebelumnya pada hari yang sama ditampilkan apa
            adanya tanpa dikoreksi dan tanpa penanda galat.
          </p>
        </section>

        <template v-if="showRecaps">
          <!-- ===== Tren harian beban total pabrik — RATA-RATA per tanggal =====
               SATU-SATUNYA tren di layar ini, dan agregasinya SENGAJA BERBEDA
               dari seri ekspor energi di atasnya. Perbedaan itu dinyatakan DI
               SINI, di dekat tren yang diterangkannya, bukan sekali di atas
               halaman. Grafiknya menggulir mendatar DI DALAM kartunya. -->
          <section class="detail-section" data-testid="daily-trend-total-factory-load">
            <h2 class="section-title">Tren Harian Beban Total Pabrik</h2>
            <p class="section-note">
              <strong>Rata-rata per tanggal</strong>, dalam
              {{ metricCardOf(TREND_COLUMN).unit }} &middot;
              {{ formatCount(trendPoints.length) }} tanggal berdata &middot;
              {{ formatCount(metricCardOf(TREND_COLUMN).readingCount) }} pembacaan
            </p>

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
                  :data-testid="`daily-trend-total-factory-load-col-${point.date}`"
                >
                  <span class="chart-value">
                    {{ formatDecimal(point.avg, metricCardOf(TREND_COLUMN).digits) }}
                  </span>
                  <div class="chart-track">
                    <div class="chart-fill" :style="{ height: trendBarHeight(point.avg) }"></div>
                  </div>
                  <span class="chart-axis">{{ formatDayAxis(point.date) }}</span>
                </div>
              </div>
            </div>

            <p class="section-note" data-testid="daily-trend-scroll-hint">
              Geser mendatar <strong>di dalam kartu ini</strong> untuk melihat seluruh tanggal;
              halaman sendiri tidak menggulir mendatar.
            </p>
            <p class="section-note">
              Terendah
              {{ formatDecimal(trendLowest, metricCardOf(TREND_COLUMN).digits) }} &middot;
              tertinggi
              {{ formatDecimal(trendHighest, metricCardOf(TREND_COLUMN).digits) }}
              <small>(dari rata-rata harian)</small>
            </p>
            <p class="section-note">
              Rata-rata periode
              {{ formatDecimal(metricCardOf(TREND_COLUMN).avg, metricCardOf(TREND_COLUMN).digits) }}
              <small>
                (berbobot {{ formatCount(metricCardOf(TREND_COLUMN).readingCount) }} pembacaan,
                dihitung ulang server atas seluruh slot — bukan rata-rata dari rata-rata harian)
              </small>
            </p>
            <p class="section-note">
              <small>
                Sumbu tegak tidak dimulai dari nol — batang membandingkan tanggal, bukan besaran
                mutlak. Tidak ada rentang &lsquo;aman&rsquo; yang digambar di sini: stasiun ini
                tidak punya master target.
              </small>
            </p>
            <p class="section-note" data-testid="aggregation-difference-note">
              <strong>
                Agregasi tren ini BERBEDA dari kartu dan seri ekspor energi di atasnya, dan
                perbedaan itu dinyatakan supaya keduanya tidak terbaca sejenis.
              </strong>
              Seri ekspor energi memuat <strong>nilai terakhir</strong> hari itu; tren ini memuat
              <strong>rata-rata</strong> hari itu. Bentuk batangnya bisa mirip, tetapi angkanya
              menjawab dua pertanyaan yang berbeda — satu &ldquo;berapa angka meter pada akhir
              hari&rdquo;, satu &ldquo;seberapa berat pabrik hari itu&rdquo;. Rata-rata di sini sah
              karena beban total pabrik memang besaran sesaat per slot, bukan angka kumulatif.
            </p>
          </section>
        </template>

        <!-- ===== DUA REKAP TEKS BEBAS, DUA BAGIAN TERPISAH =====
             Masing-masing daftar KEJADIAN {tanggal, slot waktu, teks}. Keduanya
             tidak pernah disatukan: satu slot yang mengisi kedua kolom muncul
             pada KEDUA daftar, dan itu BUKAN penghitungan ganda. -->
        <section class="detail-section" data-testid="maintenance-remarks">
          <h2 class="section-title">
            Rekap Catatan Tindakan Perawatan ({{ formatCount(maintenanceRemarks.length) }} slot)
          </h2>
          <p class="section-note">
            Satu entri <strong>per slot</strong> yang mengisinya, membawa tanggal dan slot waktunya.
            Daftar ini menjawab <strong>apa yang dikerjakan</strong>.
          </p>

          <p
            v-if="maintenanceRemarks.length === 0"
            class="section-note"
            data-testid="maintenance-remarks-empty"
          >
            Tidak ada satu pun catatan tindakan perawatan tercatat pada periode dan line ini. Ini
            dinyatakan sebagai keterangan pada bagian ini sendiri, bukan tabel kosong tanpa
            penjelasan — tabel kosong tidak membedakan &lsquo;tidak ada perawatan&rsquo; dari
            &lsquo;tidak ada yang mencatatnya&rsquo;.
          </p>

          <div v-else class="note-list">
            <article
              v-for="(entry, index) in maintenanceRemarks"
              :key="`mr-${entry.date}-${entry.time_slot}-${index}`"
              class="note-item"
              data-testid="maintenance-remark-row"
            >
              <span class="note-meta">
                {{ formatShortDate(entry.date) }} &middot; {{ formatTimeSlot(entry.time_slot) }}
              </span>
              <p class="note-text">{{ entry.text }}</p>
            </article>
          </div>

          <p class="section-note" data-testid="maintenance-remarks-note">
            Isinya ditampilkan apa adanya beserta tanggal dan slot waktunya,
            <strong>dibungkus utuh tanpa pemotongan</strong> — tidak pernah dirata-rata, tidak
            dijadikan terendah atau tertinggi, dan tidak muncul pada tren mana pun.
          </p>
        </section>

        <section class="detail-section" data-testid="findings">
          <h2 class="section-title">Rekap Temuan ({{ formatCount(findings.length) }} slot)</h2>
          <p class="section-note">
            Bentuk yang sama — satu entri per slot dengan tanggal dan slot waktunya — pada bagian
            yang <strong>TERPISAH</strong> dari rekap catatan tindakan perawatan. Daftar ini
            menjawab <strong>apa yang ditemukan</strong>.
          </p>

          <p v-if="findings.length === 0" class="section-note" data-testid="findings-empty">
            Tidak ada satu pun temuan tercatat pada periode dan line ini. Ini dinyatakan sebagai
            keterangan pada bagian ini sendiri, bukan digabung ke bagian catatan tindakan perawatan
            dan bukan tabel kosong tanpa penjelasan.
          </p>

          <div v-else class="note-list">
            <article
              v-for="(entry, index) in findings"
              :key="`fd-${entry.date}-${entry.time_slot}-${index}`"
              class="note-item"
              data-testid="finding-row"
            >
              <span class="note-meta">
                {{ formatShortDate(entry.date) }} &middot; {{ formatTimeSlot(entry.time_slot) }}
              </span>
              <p class="note-text">{{ entry.text }}</p>
            </article>
          </div>

          <p class="section-note" data-testid="findings-note">
            Pertanyaannya <strong>berbeda</strong> dari Rekap Catatan Tindakan Perawatan, karena itu
            keduanya dua bagian terpisah dan tidak pernah disatukan. Satu slot yang mengisi kedua
            kolom sekaligus <strong>muncul pada kedua rekap</strong>; itu benar,
            <strong>bukan penghitungan ganda</strong> — menyatukan keduanya justru akan menghitung
            slot itu dua kali di dalam satu daftar dan mencampur dua pertanyaan yang berbeda.
            Jumlah pada kedua judul karena itu tidak boleh dijumlahkan.
          </p>
        </section>

        <template v-if="showRecaps">
          <!-- ===== Rekap per unit Engine Room =====
               DUA PULUH TIGA kolom angka — rekap terlebar di laporan mobile
               mana pun. Ia BERTUMPUK menjadi kartu di dalam kartunya lewat
               v-stacked-table (temuan QA LP-M-02/LP-M-03), sehingga tidak ada
               isi yang tersembunyi di balik gulir mendatar dan halaman sendiri
               tidak pernah menggulir mendatar. -->
          <section class="detail-section" data-testid="by-unit">
            <h2 class="section-title">
              Rekap per Unit Engine Room ({{ formatCount(byUnit.length) }} unit)
            </h2>
            <p class="section-note">
              Angka periode di atas menggabungkan seluruh unit — tabel ini menunjukkan sebarannya,
              dengan <strong>dua puluh tiga kolom angka</strong>. engine_room_id adalah NAMA UNIT
              yang diketik di layar input, bukan kunci baris. Kolom ekspor energi membawa
              <strong>nilai terakhir</strong> unit itu, bukan rata-ratanya — kolom itu tidak pernah
              dirata-rata, di bucket mana pun.
            </p>
            <div class="detail-table-wrap">
              <table v-stacked-table class="detail-table" data-testid="by-unit-table">
                <thead>
                  <tr>
                    <th scope="col">Unit</th>
                    <th scope="col" class="num">Hari tercatat</th>
                    <th scope="col" class="num">Slot terisi</th>
                    <th v-for="column in METRIC_COLUMNS" :key="column" scope="col" class="num">
                      {{ metricShortLabel(column) }}
                    </th>
                    <th scope="col" class="num">{{ ENERGY_META.short }} · nilai terakhir</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Unit yang punya record tetapi tidak satu pun pembacaan
                       TETAP muncul dengan slot terisi 0 — membuangnya justru
                       akan menyembunyikan unit yang tidak pernah dituliskan. -->
                  <tr v-for="row in byUnit" :key="row.engine_room_id" data-testid="by-unit-row">
                    <td>{{ formatGroupLabel(row.engine_room_id) }}</td>
                    <td class="num">{{ formatCount(row.days_with_rows) }}</td>
                    <td class="num" data-testid="by-unit-reading-rows">
                      {{ formatCount(row.reading_rows) }}
                    </td>
                    <td v-for="column in METRIC_COLUMNS" :key="column" class="num">
                      {{ formatCell(row.metrics?.[column]?.avg ?? null, metricDigits(column)) }}
                      <small>(n={{ formatCount(row.metrics?.[column]?.reading_count ?? 0) }})</small>
                    </td>
                    <td class="num">
                      {{ formatCell(row.energy_export?.last_value ?? null, ENERGY_META.digits) }}
                      <small>(n={{ formatCount(row.energy_export?.reading_count ?? 0) }})</small>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <!-- Angka gabungan DIAMBIL dari `metrics` tingkat periode,
                       yang DIHITUNG ULANG server atas seluruh slot terisi —
                       bukan dirata-ratakan dari baris per unit di atasnya. Sel
                       ekspor energi SENGAJA tanda hubung: kolom itu tidak punya
                       rata-rata periode, dan sel total berisi angka akan
                       terbaca sebagai rata-rata itu. -->
                  <tr data-testid="by-unit-row-total">
                    <td>Gabungan {{ formatCount(coverage?.engine_room_unit_count) }} unit</td>
                    <td class="num">{{ formatCount(totals?.days_with_records) }}</td>
                    <td class="num">{{ formatCount(totals?.reading_rows) }}</td>
                    <td v-for="column in METRIC_COLUMNS" :key="column" class="num">
                      {{ formatCell(metricCardOf(column).avg, metricDigits(column)) }}
                      <small>(n={{ formatCount(metricCardOf(column).readingCount) }})</small>
                    </td>
                    <td class="num" data-testid="by-unit-total-energy-export">&mdash;</td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <p class="section-note">
              Tiap unit membawa kedua puluh dua rata-rata dengan
              <strong>penyebutnya sendiri</strong>, persis seperti kartu periode.
            </p>
          </section>

          <!-- ===== Rekap per hari =====
               Panjang pada periode sebulan, jadi dibungkus CollapsibleSection
               dan TERTUTUP secara bawaan. Membuka atau menutupnya tidak
               memicu permintaan apa pun — datanya sudah ada di memori — dan
               tidak mengubah satu angka pun, termasuk baris total periode.
               Perhatikan: yang dapat ditutup adalah REKAP, bukan keenam blok
               kartu di atas. -->
          <CollapsibleSection
            :title="`Rekap per Hari (${daily.length} hari)`"
            data-testid="daily-recap"
          >
            <div class="detail-table-wrap">
              <table v-stacked-table class="detail-table" data-testid="daily-table">
                <thead>
                  <tr>
                    <th scope="col">Tanggal</th>
                    <th scope="col" class="num">Slot terisi</th>
                    <th v-for="column in METRIC_COLUMNS" :key="column" scope="col" class="num">
                      {{ metricShortLabel(column) }}
                    </th>
                    <th scope="col" class="num">{{ ENERGY_META.short }} · nilai terakhir</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Tanggal tanpa satu pun slot terisi TIDAK mendapat baris:
                       baris nol akan terbaca sebagai "terukur nol". Sel kosong
                       dirender '—', BUKAN 0 — nol adalah pembacaan, kosong
                       bukan. -->
                  <tr v-for="row in daily" :key="row.date" :data-testid="`daily-row-${row.date}`">
                    <td>{{ formatDate(row.date) }}</td>
                    <td class="num">{{ formatCount(row.reading_rows) }}</td>
                    <td v-for="column in METRIC_COLUMNS" :key="column" class="num">
                      {{ formatCell(row.metrics_avg?.[column]?.avg ?? null, metricDigits(column)) }}
                    </td>
                    <!-- KOLOM INI BUKAN RATA-RATA: nilai TERAKHIR tanggal itu,
                         dari seri yang sama dengan bagian di atas. -->
                    <td class="num">
                      {{ formatCell(row.energy_export?.last_value ?? null, ENERGY_META.digits) }}
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <!-- TOTAL PERIODE DIHITUNG ULANG SERVER atas seluruh slot
                       terisi, bukan merata-ratakan rata-rata harian:
                       rata-rata dari rata-rata memberi bobot sama pada hari
                       berisi dua slot dan hari berisi dua puluh empat. Karena
                       itu pula ia tidak berubah ketika rekap harian ditutup. -->
                  <tr data-testid="daily-row-total">
                    <td>Total periode</td>
                    <td class="num">{{ formatCount(dailyTotal?.reading_rows) }}</td>
                    <td v-for="column in METRIC_COLUMNS" :key="column" class="num">
                      {{ formatCell(metricCardOf(column).avg, metricDigits(column)) }}
                      <small>(n={{ formatCount(metricCardOf(column).readingCount) }})</small>
                    </td>
                    <td class="num" data-testid="daily-total-energy-export">&mdash;</td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <p class="section-note" data-testid="daily-total-energy-note">
              Baris total periode <strong>tidak</strong> menerbitkan rata-rata untuk kolom ekspor
              energi — kolom itu hanya punya nilai per tanggal, dan sel totalnya sengaja tanda
              hubung. Kolom per tanggalnya pun <strong>nilai terakhir hari itu</strong>, bukan
              rata-rata seperti kolom-kolom lain di baris yang sama. Sel kosong dirender
              &lsquo;&mdash;&rsquo; — <strong>bukan 0</strong>, karena nol adalah pembacaan dan
              kosong bukan.
            </p>
          </CollapsibleSection>

          <!-- ===== Kelengkapan record ===== -->
          <section class="detail-section" data-testid="completeness">
            <h2 class="section-title">Kelengkapan Record</h2>
            <p class="section-note">
              Seluruh angka di bawah IKUT terhitung pada laporan — tidak satu pun menjadi penyaring.
            </p>
            <div class="queue-figures">
              <div class="queue-figure">
                <span class="queue-label">Hari yang punya record</span>
                <span class="queue-value" data-testid="days-with-records">
                  {{ formatCount(totals?.days_with_records) }}
                </span>
                <span class="queue-note">
                  hari ber-record, bukan hari ber-slot-terisi: sebuah hari yang punya record tetapi
                  seluruh barisnya kosong tetap sebuah hari yang dicatat orang
                </span>
              </div>
              <div class="queue-figure">
                <span class="queue-label">Slot terisi</span>
                <span class="queue-value" data-testid="total-reading-rows">
                  {{ formatCount(totals?.reading_rows) }}
                </span>
                <span class="queue-note">
                  satu definisi, satu jawaban — angka ini sama dengan jumlah slot terisi pada
                  kelengkapan di atas
                </span>
              </div>
            </div>
            <p class="section-note">
              Record berstatus draft <strong>IKUT terhitung</strong> pada seluruh angka laporan ini,
              dan hal itu dinyatakan supaya pembaca tahu bagian mana yang masih dapat berubah.
              Status verifikasi juga kelengkapan, bukan penyaring — record yang belum diperiksa
              tetap terhitung penuh.
            </p>
          </section>
        </template>
      </template>

      <footer class="action-footer">
        <!-- Ekspor TIDAK PERNAH dinonaktifkan oleh status periode: kunci
             periode mengatur penulisan data, bukan pembacaan laporan. CSV
             SAJA — TIDAK ADA tombol Ekspor Excel di layar ini sama sekali,
             karena WebView ponsel tidak membuka .xlsx, dan tidak ada jalur
             kode yang dapat menyusun format lain. Tombolnya terkunci sesaat
             selama berkas disiapkan sehingga klik ganda hanya mengirim satu
             permintaan. -->
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
     grafik di dalamnya. Di stasiun ini rekap per unit dan rekap harian
     membawa dua puluh tiga kolom angka — terlebar di laporan mobile mana pun —
     dan keduanya BERTUMPUK menjadi kartu alih-alih menggulir, lewat
     v-stacked-table + src/assets/stacked-table.css. */
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

.notice { margin: 0; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; color: #6b7280; font-size: 14px; line-height: 1.5; }
.notice--warning { border-color: #fcd34d; background: #fffbeb; color: #92400e; }
.notice--error { display: flex; flex-direction: column; gap: 10px; border-color: #fecaca; background: #fef2f2; color: #b91c1c; }
.notice-text { margin: 0; }
.notice--stack { display: flex; flex-direction: column; align-items: flex-start; gap: 10px; }

.metric-stack { display: flex; flex-direction: column; gap: 10px; }
.metric-card { display: flex; flex-direction: column; gap: 4px; padding: 14px; border: 1px solid #e5e7eb; border-radius: 8px; }
.metric-label { font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
.metric-value { font-size: 26px; font-weight: 700; color: #1f2937; }
/* Nilai yang tidak dapat dihitung tampil sebagai keterangan, bukan angka
   besar — supaya tidak pernah terbaca sekilas sebagai bilangan. */
.metric-value--na { font-size: 15px; font-weight: 600; color: #6b7280; }
.metric-note { font-size: 12px; color: #6b7280; line-height: 1.5; }

/* Penanda 'nilai terakhir' DI DALAM judul kartu ekspor energi. Netral secara
   warna: ia menyatakan AGREGASI apa yang dipakai, BUKAN menilai angkanya —
   tidak ada satu pun warna peringatan di layar ini. */
.metric-pill {
  display: inline-block;
  margin-left: 6px;
  padding: 1px 6px;
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  background: #f9fafb;
  color: #4b5563;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: lowercase;
}

.detail-section { display: flex; flex-direction: column; gap: 10px; padding: 14px; border: 1px solid #e5e7eb; border-radius: 8px; }
.section-title { margin: 0; font-size: 16px; font-weight: 700; color: #1f2937; }
/* Keterangan panjang DIRENDER UTUH: tidak ada text-overflow, tidak ada
   line-clamp, dan tidak ada pemotongan karakter di layar ini — keterangan yang
   terpotong di tengah berhenti menerangkan apa pun, dan pernyataan ketiadaan
   penandaan di luar batas adalah yang paling tidak boleh terpotong. */
.section-note { margin: 0; font-size: 12px; color: #6b7280; line-height: 1.5; overflow-wrap: anywhere; }
.section-note code { font-family: inherit; font-size: 11px; color: #4b5563; }

.queue-figures { display: flex; gap: 20px; flex-wrap: wrap; }
.queue-figure { display: flex; flex-direction: column; gap: 2px; min-width: 96px; }
.queue-label { font-size: 11px; color: #6b7280; }
.queue-value { font-size: 20px; font-weight: 700; color: #1f2937; }
.queue-note { font-size: 12px; color: #6b7280; line-height: 1.5; }

/* Tiga angka berdampingan DI DALAM satu kartu metrik — ini bukan tiga kartu
   bersebelahan: terendah/rata-rata/tertinggi adalah satu pengukuran yang sama
   dilihat dari tiga sisi, jadi memisahkannya menjadi tiga kartu akan
   mengundang pembacaan bahwa ketiganya tiga hal berbeda.
   Dipakai .metric-row/.metric-row-item yang TERDEFINISI di sini — bukan
   .metric-values, yang dipakai LaporanDepricarpingView.vue dan tidak pernah
   didefinisikan di satu berkas pun di src/. */
.metric-row { display: flex; gap: 16px; flex-wrap: wrap; }
.metric-row-item { display: flex; flex-direction: column; gap: 2px; min-width: 72px; }
.metric-row-item small { font-size: 11px; color: #6b7280; }
.metric-row-item b { font-size: 18px; font-weight: 700; color: #1f2937; }

/* Kedua rekap teks bebas: daftar KEJADIAN, bukan tabel. Teksnya dibungkus
   UTUH — pemotongan akan menghilangkan isi catatan itu sendiri, dan kartunya
   tidak boleh membuat halaman menggulir mendatar. */
.note-list { display: flex; flex-direction: column; gap: 10px; }
.note-item { display: flex; flex-direction: column; gap: 2px; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; }
.note-meta { font-size: 11px; font-weight: 600; color: #6b7280; }
.note-text { margin: 0; font-size: 13px; color: #1f2937; line-height: 1.5; white-space: normal; overflow-wrap: anywhere; }

/* Tabel: BERTUMPUK menjadi kartu di lebar ponsel, bukan menggulir mendatar. */
/* Bentuk dasar + kartu bertumpuk: src/assets/stacked-table.css (global,
   dipakai bersama seluruh tabel laporan mobile — QA LP-M-02/LP-M-03). Aturan
   `.detail-table` TIDAK ditulis ulang di sini dengan sengaja: style scoped
   mengompilasi selektor dengan [data-v-hash] dan akan selalu menang atas
   stylesheet bersama itu. */
.detail-table tfoot th, .detail-table tfoot td { font-weight: 700; color: #1f2937; border-bottom: none; }
.detail-table tfoot td small { font-weight: 500; color: #6b7280; }
.detail-table td small { font-weight: 500; color: #6b7280; }

/* Grafik tren: menggulir mendatar DI DALAM kartunya sendiri — ia satu-satunya
   elemen ber-overflow-x di layar ini, dan halaman sendiri tetap tidak
   menggulir mendatar. Sumbu tegak sengaja tidak dimulai dari nol, dan hal itu
   dinyatakan di kartunya. Tidak ada modifier warna peringatan di sini — nol
   kelas peringatan di seluruh layar ini. */
.chart-scroll { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.chart-bars { display: flex; align-items: flex-end; gap: 6px; min-width: min-content; padding-bottom: 4px; }
.chart-col { display: flex; flex-direction: column; align-items: center; gap: 4px; flex: none; width: 40px; }
.chart-value { font-size: 10px; color: #6b7280; }
.chart-track { display: flex; align-items: flex-end; width: 100%; height: 96px; border-radius: 4px; background: #f3f4f6; overflow: hidden; }
.chart-fill { width: 100%; border-radius: 4px 4px 0 0; background: #249360; }
.chart-axis { font-size: 10px; color: #6b7280; }

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
