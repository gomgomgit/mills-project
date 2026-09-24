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
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
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
const scope = computed(() => ({
  isAdmin: isAdmin.value,
  businessUnitId: selectedBusinessUnitId.value,
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
const loadingSummary = ref(false)
const exporting = ref(false)

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

/* ------------------------------------------------------------------ */
/* Galat                                                               */
/* ------------------------------------------------------------------ */

const networkError = ref<string | null>(null)
const errorMessage = ref<string | null>(null)

/** Aksi terakhir yang gagal — itulah yang diulang tombol Coba Lagi. */
const retryAction = ref<(() => Promise<void>) | null>(null)
const canRetry = computed(() => retryAction.value !== null)

interface ErrorLike {
  status?: number
  message?: string
  response?: { status?: number; data?: { message?: string } }
}

function statusOf(error: unknown): number | undefined {
  const candidate = error as ErrorLike | null

  return candidate?.status ?? candidate?.response?.status
}

function messageOf(error: unknown, fallback: string): string {
  const candidate = error as ErrorLike | null

  return candidate?.response?.data?.message || candidate?.message || fallback
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
  }
}

async function loadSummary(): Promise<void> {
  const periodId = selectedPeriodId.value

  if (!periodId) {
    return
  }

  loadingSummary.value = true

  try {
    summary.value = await sterilizerReportRepo.fetchSummary(periodId, scope.value)
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

  if (!selectedBusinessUnitId.value) {
    return
  }

  await loadPeriods()
}

onMounted(async () => {
  if (isAdmin.value) {
    // Admin tidak terikat mill: pilih dulu, baru periode dimuat.
    await loadBusinessUnits()

    return
  }

  if (!accountBusinessUnitId.value) {
    // Berhenti di sini. TIDAK ada permintaan apa pun — lihat catatan 2.
    noMillForAccount.value = true

    return
  }

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

  if (!periodId || exporting.value) {
    return
  }

  exporting.value = true

  try {
    const blob = await sterilizerReportRepo.exportCsv(periodId)
    sterilizerReportRepo.saveCsvFile(blob, exportFilename())
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
  closed: 'Ditutup',
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
      <div class="filter-bar">
        <!-- Pemilih Mill — hanya Admin, dan tidak dirender sama sekali
             bila server menolak endpoint options dengan 403. -->
        <label v-if="isAdmin && !millPickerForbidden" class="filter-field">
          <span>Mill</span>
          <select v-model="selectedBusinessUnitId" data-testid="mill-select" @change="onBusinessUnitChange">
            <option :value="null">Pilih Mill</option>
            <option v-for="unit in businessUnits" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
          </select>
        </label>

        <!-- Keterangan mill bagi pengguna yang terikat satu mill. -->
        <p v-else-if="!isAdmin" class="mill-current" data-testid="mill-current">
          Mill: <strong>{{ currentMillName || '-' }}</strong>
        </p>

        <!-- Pemilih Periode selalu dirender (bukan v-if millRequired):
             bagi Admin yang belum memilih mill ia tampil KOSONG, bukan
             hilang — pemilih yang lenyap dan pemilih yang kosong
             menceritakan hal berbeda kepada pengguna. -->
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

      <!-- Mill belum punya satu pun periode: arahan, bukan pesan teknis. -->
      <p v-else-if="noPeriods" class="notice" data-testid="no-periods">
        Mill ini belum memiliki periode pelaporan yang mencakup stasiun Sterilizer.
        Silakan hubungi Admin untuk membuat periode pelaporan terlebih dahulu.
      </p>

      <p v-if="loadingPeriods" class="status-text">Memuat daftar periode…</p>

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

      <p v-if="loadingSummary" class="status-text">Memuat ringkasan periode…</p>

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
          v-if="selectedPeriodId"
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

.filter-bar { display: flex; flex-direction: column; gap: 12px; }
.filter-field { display: flex; flex-direction: column; gap: 4px; font-size: 13px; color: #6b7280; }
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
.notice-text { margin: 0; }

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
.action-button--secondary { border: 1px solid #e5e7eb; background: #ffffff; color: #1f2937; }
</style>
