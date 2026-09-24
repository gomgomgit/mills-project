<script setup lang="ts">
/**
 * DashboardReportingView — screen-134--dashboard-reporting-mobile /
 * usecase-134--dashboard-reporting-mobile "Buka Dashboard & Reporting
 * (Mobile)" (mounted at /dashboard-reporting, meta.public = false, per
 * router/index.ts — requires an authenticated session, enforced by the
 * router's global auth guard; unauthenticated visitors are redirected to
 * 'login' before this component is ever mounted, so there is no
 * in-component auth handling here at all).
 *
 * Second-level container screen reached from the Home menu card
 * 'dashboard-reporting' — offers exactly two placeholder choices,
 * Dashboard and Reporting, per business_logic. This screen performs NO
 * data loading of any kind: no repo, no apiClient, no localDb call, and
 * no loading state — one of the unit tests specifically asserts zero
 * calls into apiClient/localDb on mount, which is deliberate: the
 * offline-friendliness of this screen is a feature, not an accident.
 *
 * MENU_OPTIONS is a single constant driving a v-for; a null `routeName`
 * means the destination screen has not been built yet. Bringing a card to
 * life later means filling in its routeName — NOT touching the template.
 * Disabled cards deliberately use `aria-disabled="true"`, never the
 * native `disabled` attribute — see components/StationGrid.vue's doc
 * comment for why: `disabled` swallows the tap entirely, so the user
 * never gets any feedback and a touch screen's silence is
 * indistinguishable from a hung app. The info message on tap is a single
 * ref<string | null> (not a list) — pressing repeatedly just overwrites
 * that one value, which is what keeps the "message never stacks" rule
 * true by construction rather than by convention.
 *
 * Header (brand + hamburger nav menu) and breadcrumb + title are copied
 * verbatim from HomeView.vue / StationListView.vue respectively, per
 * implementation_notes, so this screen stays visually and behaviorally
 * consistent with the rest of the mobile app.
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useFloatingClockStore } from '@/stores/floatingClock'
import { useAiAssistantStore } from '@/stores/aiAssistant'

const router = useRouter()
const authStore = useAuthStore()
const floatingClockStore = useFloatingClockStore()
const aiAssistantStore = useAiAssistantStore()

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

/**
 * business_logic step 7 — tapping the 'Home' breadcrumb segment navigates
 * there. 'Dashboard & Reporting' is the current page, so it is not a
 * link (rendered as plain text with aria-current="page" in the template).
 */
function goToHome() {
  router.push({ name: 'home' })
}

interface MenuOption {
  key: string
  label: string
  routeName: string | null
}

/**
 * business_logic step 3 — single source of truth for this screen's
 * choices. A null routeName means "not built yet"; the template renders
 * that state generically (disabled class, aria-disabled, "Belum
 * tersedia" label) rather than special-casing each key.
 */
const MENU_OPTIONS: MenuOption[] = [
  { key: 'dashboard', label: 'Dashboard', routeName: null },
  // 2026-09-23 — kartu 'Reporting' dihidupkan oleh
  // screen-141--reporting-pilih-stasiun-mobile: rute 'report-stations'
  // (/reports) kini ada, jadi kartu ini menavigasi ke pemilih stasiun untuk
  // laporan. Persis seperti yang dijanjikan komentar MENU_OPTIONS di atas,
  // menghidupkan sebuah kartu hanya berarti mengisi routeName-nya — template
  // tidak disentuh sama sekali.
  //
  // Kartu 'Dashboard' TETAP null: layar Dashboard mobile memang belum
  // dibangun, dan menautkannya ke mana pun hanya akan berbohong.
  { key: 'reporting', label: 'Reporting', routeName: 'report-stations' },
]

/**
 * Ikon per pilihan, dikunci pada `key` yang sama dengan MENU_OPTIONS.
 *
 * Dipisah sebagai peta, BUKAN ditulis keras di dalam v-for, karena satu
 * SVG di dalam loop membuat setiap kartu memakai ikon yang sama — dan
 * pilihan ketiga yang ditambahkan kelak akan diam-diam mewarisi ikon
 * milik kartu pertama. Itu justru melanggar sifat "MENU_OPTIONS adalah
 * satu sumber kebenaran" yang menjadi inti layar ini.
 *
 * 'dashboard' memakai ulang ikon batang grafik milik kartu
 * 'dashboard-reporting' di HomeView.vue agar rantai Home -> layar ini
 * terasa satu keluarga. 'reporting' memakai ikon dokumen, dengan konvensi
 * inline SVG yang sama (24x24, stroke-width 1.5, currentColor).
 */
const OPTION_ICONS: Record<string, string> = {
  dashboard:
    '<rect x="3" y="10" width="4" height="11" /><rect x="10" y="6" width="4" height="15" /><rect x="17" y="3" width="4" height="18" />',
  reporting:
    '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" /><path d="M14 3v5h5" /><path d="M9 13h6" /><path d="M9 17h4" />',
}

/**
 * business_logic steps 5-6 / edge_case_handling — single reactive
 * variable (not an array), so repeated taps on a disabled card only ever
 * overwrite this one value instead of accumulating messages.
 */
const infoMessage = ref<string | null>(null)

function onSelectOption(option: MenuOption) {
  if (option.routeName !== null) {
    router.push({ name: option.routeName })
    return
  }

  infoMessage.value = `Layar ${option.label} belum tersedia.`
}
</script>

<template>
  <main class="dashboard-reporting-view">
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

    <div class="dashboard-reporting-header">
      <nav class="breadcrumb" aria-label="Breadcrumb">
        <button type="button" class="breadcrumb-link" data-testid="breadcrumb-home" @click="goToHome">Home</button>
        <span class="breadcrumb-separator" aria-hidden="true">/</span>
        <span class="breadcrumb-current" aria-current="page">Dashboard & Reporting</span>
      </nav>
      <h1 class="screen-title">Dashboard & Reporting</h1>
    </div>

    <p v-if="infoMessage" class="info-message" role="status" data-testid="info-message">{{ infoMessage }}</p>

    <section class="menu-grid" aria-label="Pilihan Dashboard & Reporting">
      <button
        v-for="option in MENU_OPTIONS"
        :key="option.key"
        type="button"
        class="menu-card"
        :class="{ 'menu-card--disabled': option.routeName === null }"
        :aria-disabled="option.routeName === null"
        :data-testid="`menu-card-${option.key}`"
        @click="onSelectOption(option)"
      >
        <svg
          class="menu-card-icon"
          viewBox="0 0 24 24"
          width="24"
          height="24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
          stroke-linecap="round"
          stroke-linejoin="round"
          aria-hidden="true"
          v-html="OPTION_ICONS[option.key]"
        />
        <span class="menu-card-text">
          <span class="menu-card-label">{{ option.label }}</span>
          <span v-if="option.routeName === null" class="menu-card-badge">Belum tersedia</span>
        </span>
      </button>
    </section>
  </main>
</template>

<style scoped>
.dashboard-reporting-view {
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

.dashboard-reporting-header {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 6px;
  align-self: flex-start;
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

.info-message {
  margin: 0;
  padding: 10px 12px;
  border-radius: 8px;
  background-color: #f7f7f7;
  color: #1f2937;
  font-size: 13px;
}

.menu-grid {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.menu-card {
  display: flex;
  align-items: center;
  gap: 14px;
  min-height: 44px;
  padding: 16px;
  box-sizing: border-box;
  border: none;
  border-radius: 12px;
  background-color: #f7f7f7;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
  text-align: left;
  cursor: pointer;
  font-family: inherit;
}

.menu-card--disabled {
  opacity: 0.6;
}

.menu-card-icon {
  flex-shrink: 0;
  color: #249360;
}

.menu-card-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.menu-card-label {
  font-size: 15px;
  font-weight: 600;
  color: #1f2937;
}

.menu-card-badge {
  font-size: 12px;
  font-weight: 500;
  color: #6b7280;
}
</style>
