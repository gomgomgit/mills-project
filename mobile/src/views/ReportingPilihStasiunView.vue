<script setup lang="ts">
/**
 * ReportingPilihStasiunView — screen-141--reporting-pilih-stasiun-mobile /
 * usecase-143--reporting-pilih-stasiun-mobile "Pilih Stasiun untuk Laporan
 * (Mobile)" (dipasang di /reports, meta.public = false — penjagaan sesi
 * global di router/index.ts yang mengalihkan pengguna tanpa sesi ke
 * 'login', sehingga komponen ini tidak pernah dipasang tanpa sesi dan
 * tidak punya penanganan auth di dalamnya sama sekali).
 *
 * NOL pemanggilan jaringan. Daftar stasiun dibaca dari tabel lokal
 * (offline) lewat stationRepo.ts — persis sumber yang dipakai layar
 * Daftar Stasiun (StationListView.vue), sehingga nama, ikon, dan urutan
 * stasiun di sini dijamin sama dan tidak ada cara kedua untuk menyebut
 * stasiun yang sama. Konsekuensinya layar ini terbuka penuh saat offline;
 * itu fitur, bukan kebetulan.
 *
 * ────────────────────────────────────────────────────────────────────────
 * KETERSEDIAAN LAPORAN ≠ StationSlot.isActive
 * ────────────────────────────────────────────────────────────────────────
 * `isActive` berarti stasiunnya BEROPERASI di mill itu.
 * "tersedia" di layar ini berarti layar laporan MOBILE stasiun itu SUDAH
 * DIBANGUN. Dua hal yang berbeda, dan satu-satunya penentunya adalah
 * konstanta REPORT_ROUTES di bawah — `isActive` tidak pernah ikut
 * dievaluasi dalam keputusan ketersediaan (lihat isReportAvailable()).
 *
 * Kenapa ini ditulis sepanjang ini: Sterilizer, Cages & Tracks, dan Boiler
 * Room kebetulan isActive = true DAN punya laporan, jadi implementasi
 * keliru `isActive && REPORT_ROUTES[type]` akan tetap hijau di seluruh
 * test. Cacatnya baru muncul pada stasiun yang aktif di mill tetapi
 * laporannya belum dibuat — yaitu 15 dari 18 stasiun, sekarang juga.
 * Menambah laporan stasiun kelak = menambah SATU baris di REPORT_ROUTES,
 * bukan menyebar pengecekan ke template (screen-136 dan screen-137
 * membuktikannya: satu baris masing-masing, tanpa satu pun perubahan di
 * template).
 *
 * Tile tanpa laporan memakai aria-disabled="true", BUKAN atribut
 * `disabled` native — alasannya tertulis di components/StationGrid.vue:
 * `disabled` menelan ketukan sehingga tidak ada pesan yang muncul, dan di
 * layar sentuh diam tidak dapat dibedakan dari aplikasi yang macet.
 * Gayanya pun sengaja BUKAN gaya placeholder abu-abu milik StationGrid —
 * melainkan gaya tile aktif yang diredupkan (tetap keluarga hijau
 * LAPORAN), karena maknanya berbeda: abu-abu = stasiunnya tidak ada;
 * redup = stasiunnya ada, laporannya yang belum dibangun.
 *
 * Pesan info memakai SATU ref tunggal (bukan daftar) — itu yang membuat
 * aturan "pesan tidak menumpuk" benar secara konstruksi, bukan secara
 * konvensi.
 */
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useFloatingClockStore } from '@/stores/floatingClock'
import { useAiAssistantStore } from '@/stores/aiAssistant'
import { stationRepo, type StationSlot, type StationType } from '@/services/stationRepo'

const router = useRouter()
const authStore = useAuthStore()
const floatingClockStore = useFloatingClockStore()
const aiAssistantStore = useAiAssistantStore()

const stations = ref<StationSlot[]>([])
const loading = ref(true)

/**
 * business_logic — SATU variabel reaktif tunggal (bukan array). Menekan
 * tile tanpa laporan berkali-kali hanya menimpa nilai ini, sehingga
 * jumlah elemen [data-testid="info-message"] di DOM selalu tetap satu.
 */
const infoMessage = ref<string | null>(null)

/**
 * SATU-SATUNYA penentu ketersediaan laporan di layar ini: kode stasiun
 * ada di peta ini atau tidak. Tidak ada hubungannya dengan
 * StationSlot.isActive (lihat komentar kepala berkas).
 *
 * Hari ini baru Sterilizer (screen-135--laporan-sterilizer-mobile),
 * Cages & Tracks (screen-136--laporan-cages-track-mobile), Boiler Room
 * (screen-137--laporan-boiler-room-mobile), dan Clarification
 * (screen-138--laporan-clarification-mobile) yang layar laporan mobile-nya
 * sudah dibangun; 14 stasiun lain menunggu layar laporannya masing-masing
 * dan tetap ditampilkan dalam keadaan nonaktif, bukan disembunyikan.
 *
 * Kunci 'cages-track' dan 'boiler-room' ditulis dalam tanda kutip karena
 * StationType memakai tanda hubung; itu kode stasiun yang sama dengan yang
 * dipakai stationRepo dan periode pelaporan.
 */
const REPORT_ROUTES: Partial<Record<StationType, string>> = {
  sterilizer: 'report-sterilizer',
  'cages-track': 'report-cages-track',
  'boiler-room': 'report-boiler-room',
  clarification: 'report-clarification',
  'storage-tank': 'report-storage-tank',
}

function isReportAvailable(station: StationSlot): boolean {
  // Sengaja HANYA REPORT_ROUTES. Jangan tambahkan `station.isActive &&` di
  // sini — lihat komentar kepala berkas.
  return REPORT_ROUTES[station.type] != null
}

/**
 * Production Line yang terakhir dipilih pengguna di layar Daftar Stasiun,
 * dibaca dari localStorage dengan kunci yang SAMA PERSIS seperti yang
 * ditulis StationListView.vue (`msl_production_line_{userId}`).
 *
 * Kenapa localStorage dan bukan productionLineRepo.fetchCurrentProductionLines():
 * fungsi itu memanggil jaringan, dan layar ini wajib nol pemanggilan
 * jaringan. Membaca pilihan yang sudah tersimpan di perangkat memberi
 * jalur baca yang sama dengan layar Daftar Stasiun tanpa menyentuh
 * jaringan sama sekali.
 *
 * Dibungkus try/catch mengikuti pola floatingClock.ts / aiAssistant.ts:
 * pada mode privat penyimpanan bisa melempar, dan itu tidak boleh
 * mematahkan layar — cukup jatuh ke jalur business unit.
 */
function readActiveProductionLineId(): string | null {
  const userId = authStore.currentUser?.id

  if (!userId) {
    return null
  }

  try {
    return window.localStorage.getItem(`msl_production_line_${userId}`)
  } catch {
    return null
  }
}

/**
 * business_logic — jalur baca daftar stasiun, mengikuti StationListView.vue
 * PERSIS: ada production line aktif → per production line; tidak ada →
 * per business unit. Jalur yang berbeda akan menghasilkan daftar yang
 * berbeda dari layar Daftar Stasiun, yaitu tepat hal yang ingin dihindari.
 */
async function loadStations(): Promise<StationSlot[]> {
  const productionLineId = readActiveProductionLineId()

  if (productionLineId) {
    return stationRepo.getActiveAndPlaceholderStationsForProductionLine(productionLineId)
  }

  const businessUnitId = authStore.currentUser?.business_unit_id

  if (!businessUnitId) {
    return []
  }

  return stationRepo.getActiveAndPlaceholderStations(businessUnitId)
}

onMounted(async () => {
  try {
    stations.value = await loadStations()
  } catch {
    // Pembacaan tabel lokal gagal. Tidak ada pesan kesalahan teknis di
    // layar ini — hasilnya daftar kosong, yang sudah punya penanganannya
    // sendiri: arahan membuka Daftar Stasiun lebih dulu (edge_case
    // "Daftar stasiun lokal kosong"). Menampilkan pesan SQLite kepada
    // operator di lantai pabrik tidak menolong siapa pun.
    stations.value = []
  } finally {
    loading.value = false
  }
})

/**
 * business_logic — tile bertautan → pindah rute; tile tanpa laporan →
 * isi pesan, JANGAN pindah rute. Tidak ada cabang berbasis peran di sini:
 * layar ini terbuka untuk semua peran mobile, pembatasan sesungguhnya ada
 * di layar laporan tujuan.
 */
function onTileTap(station: StationSlot) {
  const routeName = REPORT_ROUTES[station.type]

  if (routeName) {
    router.push({ name: routeName })

    return
  }

  infoMessage.value = `Laporan ${station.name} belum tersedia.`
}

/**
 * Ikon per stasiun — disalin apa adanya dari components/StationGrid.vue
 * agar ikon di layar ini identik dengan ikon di layar Daftar Stasiun
 * (aturan bisnis: nama, ikon, dan urutan sama persis). Konvensi yang sama:
 * Lucide-style, viewBox 24, stroke 1.5, `currentColor`.
 *
 * StationGrid tidak mengekspor petanya (ia komponen presentasional, bukan
 * modul ikon), dan berkas itu di luar cakupan perubahan run ini, jadi peta
 * ini adalah salinan — bukan hasil refactor. Bila kelak ada ikon stasiun
 * baru, keduanya perlu diperbarui.
 *
 * Urutan pemilihan: override per-stasiun (`station.icon`, Mills Setting)
 * → ikon per jenis → ikon per nama (untuk baris lama ber-type 'other') →
 * ikon cadangan. `isActive` TIDAK ikut dalam pemilihan ini — di layar ini
 * ia tidak menentukan apa pun (lihat komentar kepala berkas); StationGrid
 * membatasi override ke tile aktif karena di sana tile nonaktif berarti
 * "stasiun tidak ada", makna yang tidak berlaku di layar ini.
 *
 * Markup ikon bersifat statis/ditulis pengembang (tidak pernah memuat
 * data pengguna), sehingga `v-html` di sini hanya menyuntikkan SVG tetap —
 * bukan celah XSS.
 */
const TYPE_ICONS: Partial<Record<StationType, string>> = {
  weighbridge:
    '<rect x="2" y="7" width="20" height="10" rx="1"/><path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/><line x1="6" y1="17" x2="6" y2="20"/><line x1="18" y1="17" x2="18" y2="20"/>',
  grading:
    '<circle cx="12" cy="12" r="9"/><line x1="12" y1="7" x2="12" y2="12"/><line x1="12" y1="12" x2="16" y2="14"/>',
  'cages-track':
    '<rect x="3" y="4" width="7" height="16" rx="1"/><rect x="14" y="4" width="7" height="16" rx="1"/><line x1="10" y1="12" x2="14" y2="12"/>',
  threshing: '<circle cx="12" cy="12" r="9"/><line x1="8" y1="12" x2="16" y2="12"/>',
  pressing: '<path d="M4 20V10l8-6 8 6v10"/><line x1="12" y1="14" x2="12" y2="20"/>',
  depricarping:
    '<rect x="4" y="4" width="16" height="16" rx="8"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>',
  'kernel-plant':
    '<rect x="5" y="3" width="14" height="18" rx="1"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="9" y1="12" x2="15" y2="12"/>',
  'solid-waste-disposal':
    '<path d="M4 7h16"/><path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/><path d="M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>',
  'process-water': '<path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"/>',
  'kernel-dispatch': '<path d="M21 8 12 3 3 8v8l9 5 9-5V8z"/><path d="M3 8l9 5 9-5"/><path d="m9 12 2 2 4-4"/>',
  'cpo-dispatch':
    '<rect x="3" y="7" width="10" height="14" rx="1"/><path d="M13 10h2a2 2 0 0 1 2 2v4a1.5 1.5 0 0 0 3 0v-6l-3-3"/><line x1="6" y1="11" x2="10" y2="11"/>',
  'effluent-plant': '<circle cx="12" cy="12" r="9"/><path d="M8 12h8M12 8v8"/>',
  'storage-tank': '<rect x="4" y="6" width="16" height="14" rx="1"/><path d="M8 6V4h8v2"/>',
  'engine-room':
    '<rect x="6" y="2" width="12" height="20" rx="1"/><line x1="6" y1="8" x2="18" y2="8"/><line x1="6" y1="14" x2="18" y2="14"/>',
  'boiler-room': '<path d="M6 21V9a6 6 0 0 1 12 0v12"/><line x1="6" y1="15" x2="18" y2="15"/>',
  clarification: '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/>',
  'process-quality-control':
    '<rect x="6" y="3" width="12" height="18" rx="2"/><path d="M9 3v2a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1V3"/><path d="m9 14 2 2 4-4"/>',
  sterilizer:
    '<rect x="4" y="4" width="16" height="16" rx="2"/><line x1="8" y1="9" x2="16" y2="9"/><line x1="8" y1="13" x2="16" y2="13"/>',
}

const NAME_ICONS: Record<string, string> = {
  thresher: '<circle cx="12" cy="12" r="9"/><line x1="8" y1="12" x2="16" y2="12"/>',
  press: '<path d="M4 20V10l8-6 8 6v10"/><line x1="12" y1="14" x2="12" y2="20"/>',
  clarification: '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/>',
  'kernel plant':
    '<rect x="5" y="3" width="14" height="18" rx="1"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="9" y1="12" x2="15" y2="12"/>',
  boiler: '<path d="M6 21V9a6 6 0 0 1 12 0v12"/><line x1="6" y1="15" x2="18" y2="15"/>',
  'effluent treatment': '<circle cx="12" cy="12" r="9"/><path d="M8 12h8M12 8v8"/>',
  'loading ramp':
    '<rect x="3" y="10" width="18" height="8" rx="1"/><line x1="3" y1="10" x2="8" y2="4" stroke-linejoin="round"/>',
  digester:
    '<rect x="4" y="4" width="16" height="16" rx="8"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>',
  'engine room':
    '<rect x="6" y="2" width="12" height="20" rx="1"/><line x1="6" y1="8" x2="18" y2="8"/><line x1="6" y1="14" x2="18" y2="14"/>',
  'water treatment': '<path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"/>',
  'bulking storage': '<rect x="4" y="6" width="16" height="14" rx="1"/><path d="M8 6V4h8v2"/>',
}

const ICON_OVERRIDES: Record<string, string> = {
  gauge: '<path d="M12 12l4-4"/><circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 1 8.5 6"/>',
  layers: '<path d="M12 3 3 8l9 5 9-5-9-5z"/><path d="M3 13l9 5 9-5"/>',
  package: '<path d="M21 8 12 3 3 8v8l9 5 9-5V8z"/><path d="M3 8l9 5 9-5"/><line x1="12" y1="13" x2="12" y2="21"/>',
  truck:
    '<rect x="1" y="7" width="13" height="10" rx="1"/><path d="M14 10h4l3 3v4h-7"/><circle cx="6" cy="19" r="2"/><circle cx="17" cy="19" r="2"/>',
  scale:
    '<line x1="12" y1="3" x2="12" y2="21"/><path d="M5 7h14"/><path d="M5 7 2 13a3 3 0 0 0 6 0z"/><path d="M19 7l-3 6a3 3 0 0 0 6 0z"/>',
  warehouse: '<path d="M3 10 12 4l9 6v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M8 20v-6h8v6"/>',
  factory: '<path d="M3 21V10l6 4v-4l6 4v-4l6 4v7z"/><line x1="3" y1="21" x2="21" y2="21"/>',
  container:
    '<rect x="2" y="6" width="20" height="12" rx="1"/><line x1="2" y1="10" x2="22" y2="10"/><line x1="2" y1="14" x2="22" y2="14"/>',
  box: '<rect x="4" y="4" width="16" height="16" rx="1"/>',
  boxes:
    '<rect x="2" y="10" width="8" height="10" rx="1"/><rect x="14" y="10" width="8" height="10" rx="1"/><path d="M6 10V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v4"/>',
}

const FALLBACK_ICON = '<rect x="4" y="4" width="16" height="16" rx="2"/>'

function iconInnerHtml(station: StationSlot): string {
  const override = station.icon ? ICON_OVERRIDES[station.icon.trim().toLowerCase()] : undefined

  if (override) {
    return override
  }

  return TYPE_ICONS[station.type] ?? NAME_ICONS[station.name.trim().toLowerCase()] ?? FALLBACK_ICON
}

/** business_logic — segmen breadcrumb yang dapat ditekan. */
function goToHome() {
  router.push({ name: 'home' })
}

function goToDashboardReporting() {
  router.push({ name: 'dashboard-reporting' })
}

/**
 * Header (brand + menu hamburger) disalin verbatim dari
 * DashboardReportingView.vue / HomeView.vue agar seluruh layar mobile
 * tetap konsisten secara visual maupun perilaku.
 */
const isNavMenuOpen = ref(false)

function toggleNavMenu() {
  isNavMenuOpen.value = !isNavMenuOpen.value
}

function closeNavMenu() {
  isNavMenuOpen.value = false
}

function openAiAssistant() {
  closeNavMenu()
  aiAssistantStore.open()
}

function goToChangePassword() {
  closeNavMenu()
  router.push({ name: 'change-password' })
}

async function onLogout() {
  closeNavMenu()
  await authStore.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <main class="reporting-stations-view">
    <header class="app-header">
      <div class="app-header-brand">
        <svg
          class="brand-icon"
          viewBox="0 0 24 24"
          width="28"
          height="28"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
          stroke-linecap="round"
          stroke-linejoin="round"
          aria-hidden="true"
        >
          <circle cx="12" cy="12" r="9" />
          <path d="M8 12l3 3 5-6" />
        </svg>
        <span class="brand-name">Mills Smart Log</span>
      </div>

      <button
        type="button"
        class="hamburger-button"
        :aria-label="isNavMenuOpen ? 'Tutup menu navigasi' : 'Buka menu navigasi'"
        data-testid="hamburger-button"
        @click="toggleNavMenu"
      >
        <svg v-if="!isNavMenuOpen" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
          <line x1="3" y1="6" x2="21" y2="6" />
          <line x1="3" y1="12" x2="21" y2="12" />
          <line x1="3" y1="18" x2="21" y2="18" />
        </svg>
        <svg v-else viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
          <line x1="18" y1="6" x2="6" y2="18" />
          <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
      </button>

      <div v-if="isNavMenuOpen" class="nav-menu" data-testid="nav-menu">
        <button type="button" class="nav-menu-item" data-testid="nav-menu-change-password" @click="goToChangePassword">
          Ganti Password
        </button>
        <button type="button" class="nav-menu-item" data-testid="nav-menu-toggle-floating-clock" @click="floatingClockStore.toggle()">{{ floatingClockStore.enabled ? 'Nonaktifkan Jam Mengambang' : 'Aktifkan Jam Mengambang' }}</button>
        <button type="button" class="nav-menu-item" data-testid="nav-menu-toggle-ai-bubble" @click="aiAssistantStore.toggleBubble()">{{ aiAssistantStore.bubbleEnabled ? 'Nonaktifkan Bubble Chat AI' : 'Aktifkan Bubble Chat AI' }}</button>
        <button type="button" class="nav-menu-item" data-testid="nav-menu-ai-assistant" @click="openAiAssistant">Bantuan AI</button>
        <button type="button" class="nav-menu-item" data-testid="nav-menu-logout" @click="onLogout">Logout</button>
      </div>
    </header>

    <div class="screen-header">
      <nav class="breadcrumb" aria-label="Breadcrumb">
        <button type="button" class="breadcrumb-link" data-testid="breadcrumb-home" @click="goToHome">Home</button>
        <span class="breadcrumb-separator" aria-hidden="true">/</span>
        <button
          type="button"
          class="breadcrumb-link"
          data-testid="breadcrumb-dashboard-reporting"
          @click="goToDashboardReporting"
        >
          Dashboard &amp; Reporting
        </button>
        <span class="breadcrumb-separator" aria-hidden="true">/</span>
        <span class="breadcrumb-current" aria-current="page">Reporting</span>
      </nav>
      <h1 class="screen-title">Reporting</h1>
    </div>

    <div class="station-grid-wrapper">
      <p v-if="infoMessage" class="info-message" role="status" data-testid="info-message">
        {{ infoMessage }}
      </p>

      <!--
        edge_case "Daftar stasiun lokal kosong" — arahan, bukan pesan
        kesalahan teknis: pengguna yang baru masuk dan belum pernah membuka
        Daftar Stasiun memang belum punya data stasiun di perangkatnya.
      -->
      <p v-if="!loading && stations.length === 0" class="no-stations" data-testid="no-stations">
        Belum ada stasiun tersimpan di perangkat ini. Buka layar Daftar Stasiun lebih dulu agar
        daftar stasiun tersimpan, lalu kembali ke sini.
      </p>

      <div class="station-grid" role="list" aria-label="Daftar stasiun untuk laporan" data-testid="station-grid">
        <button
          v-for="station in stations"
          :key="station.id"
          type="button"
          role="listitem"
          class="station-tile"
          :class="isReportAvailable(station) ? 'station-tile--available' : 'station-tile--unavailable'"
          :aria-disabled="!isReportAvailable(station)"
          :data-testid="`station-tile-${station.type}`"
          @click="onTileTap(station)"
        >
          <svg
            class="station-tile-icon"
            viewBox="0 0 24 24"
            width="24"
            height="24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
            v-html="iconInnerHtml(station)"
          />
          <span class="station-tile-name">{{ station.name }}</span>
          <span v-if="!isReportAvailable(station)" class="station-tile-hint">Belum tersedia</span>
        </button>
      </div>
    </div>
  </main>
</template>

<style scoped>
.reporting-stations-view {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 0 20px 20px;
  background-color: #ffffff;
  font-family: 'Inter', sans-serif;
  box-sizing: border-box;
}

.app-header {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 64px;
  margin: 0 -20px;
  padding: 0 20px;
  background-color: #ffffff;
}

.app-header-brand {
  display: flex;
  align-items: center;
  gap: 10px;
}

.brand-icon {
  color: #249360;
  flex-shrink: 0;
}

.brand-name {
  font-size: 16px;
  font-weight: 700;
  color: #1f2937;
}

.hamburger-button {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border: none;
  border-radius: 8px;
  background-color: transparent;
  color: #1f2937;
  cursor: pointer;
}

.nav-menu {
  position: absolute;
  top: 64px;
  right: 0;
  z-index: 10;
  display: flex;
  flex-direction: column;
  min-width: 180px;
  padding: 6px;
  border-radius: 12px;
  background-color: #ffffff;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
  border: 1px solid #f7f7f7;
}

.nav-menu-item {
  min-height: 44px;
  padding: 0 12px;
  border: none;
  border-radius: 8px;
  background-color: transparent;
  color: #1f2937;
  font-size: 14px;
  font-weight: 500;
  font-family: inherit;
  text-align: left;
  cursor: pointer;
}

.nav-menu-item:hover {
  background-color: #f7f7f7;
}

.screen-header {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 6px;
  align-self: flex-start;
  flex-wrap: wrap;
}

.breadcrumb-link {
  padding: 2px 4px;
  border: none;
  border-radius: 4px;
  background-color: transparent;
  color: #6b7280;
  font-size: 12px;
  font-weight: 500;
  font-family: inherit;
  cursor: pointer;
}

.breadcrumb-link:hover {
  text-decoration: underline;
}

.breadcrumb-separator {
  font-size: 12px;
  color: #6b7280;
}

.breadcrumb-current {
  padding: 2px 4px;
  color: #6b7280;
  font-size: 12px;
  font-weight: 500;
}

.screen-title {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
}

.station-grid-wrapper {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.info-message {
  margin: 0;
  padding: 10px 12px;
  border-radius: 8px;
  background-color: #f7f7f7;
  color: #1f2937;
  font-size: 13px;
}

.no-stations {
  margin: 0;
  padding: 16px;
  border-radius: 8px;
  background-color: #f7f7f7;
  color: #6b7280;
  font-size: 13px;
  line-height: 1.5;
}

.station-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

.station-tile {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  min-height: 44px;
  padding: 22px 6px;
  box-sizing: border-box;
  border: none;
  border-radius: 8px;
  text-align: center;
  cursor: pointer;
  font-family: inherit;
}

.station-tile-icon {
  flex-shrink: 0;
}

.station-tile-name {
  font-size: 13px;
  font-weight: 600;
  line-height: 1.2;
}

.station-tile-hint {
  font-size: 11px;
  font-weight: 400;
  line-height: 1.2;
}

/*
 * TERSEDIA — laporan mobile stasiun ini sudah dibangun. Hijau merek
 * (keluarga LAPORAN), bukan merah #d20000 milik layar input data.
 */
.station-tile--available {
  background-color: #249360;
  color: #ffffff;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
}

.station-tile--available:focus {
  outline: 2px solid #1f2937;
  outline-offset: 2px;
}

/*
 * BELUM TERSEDIA — laporannya yang belum dibangun, BUKAN stasiunnya yang
 * tidak ada. Karena itu sengaja bukan abu-abu placeholder milik
 * StationGrid.vue, melainkan tile aktif yang diredupkan: tetap keluarga
 * hijau, tanpa bayangan, teks & ikon dimuted, tetap terbaca. Tetap dapat
 * diketuk (aria-disabled, bukan atribut disabled native) agar ketukannya
 * memunculkan pesan info.
 */
.station-tile--unavailable {
  background-color: #d6f6e5;
  color: #6b7280;
  box-shadow: none;
}
</style>
