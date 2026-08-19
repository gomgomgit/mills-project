<script setup lang="ts">
/**
 * HomeView — screen-005--home / usecase-005--home "Home" (mounted at
 * /home, meta.public = false, per router/index.ts — requires an
 * authenticated session, enforced by the router's global auth guard).
 *
 * Rewritten (2026-08-18): the screen's purpose changed completely — it is
 * now purely a welcome + navigation launcher (header + hero image +
 * personalized greeting + 3 static menu cards), per the updated
 * screen_tech_spec (ver 2). It no longer shows per-station draft status
 * or a paused-draft list — api_contracts.endpoints is empty and there is
 * NO local SQLite read / API call of any kind on this screen anymore
 * (implementation_notes: "Home is purely presentational + static
 * navigation"). The old useHomeSummary/draftRecordsRepo/PausedDraftsList
 * wiring has been removed from this file entirely — see this call's
 * dead-code investigation notes (reported to the orchestrator, not
 * inlined here) for whether those files are still referenced elsewhere.
 *
 * User's display name: read directly from stores/auth.ts's
 * `currentUser.name` (already available from the login flow) — no
 * separate query, per implementation_notes.
 *
 * Hero image: static local asset (`@/assets/home-hero-mill.jpg`),
 * imported and bundled like any other Vite asset — never fetched from a
 * remote URL at runtime, since this app is offline-first.
 *
 * Icons: hand-authored inline SVG (Lucide-style, 24px viewBox, stroke
 * 1.5, `currentColor`), following the exact pattern established in
 * components/StationGrid.vue (static/developer-authored SVG markup only,
 * never containing user data — safe to inject as raw template markup).
 *
 * Nav menu (hamburger): no existing dropdown/nav-menu component was found
 * anywhere else in mobile/src/views or mobile/src/components (only
 * unrelated <select> "dropdown" mentions in LoginForm.vue), so a minimal
 * state-driven one (`isNavMenuOpen`) is implemented locally here rather
 * than inventing a new shared component for a single caller.
 *
 * "Segera Hadir" info message: no toast/snackbar utility exists in the
 * codebase (services/errorHandler.ts is API-error-specific and explicitly
 * defers rendering to the caller — "no toast library is part of the
 * current stack", see its header comment) — not applicable here anyway
 * since this screen makes no API calls. A minimal local transient-message
 * ref is implemented instead, following the same
 * show-then-auto-dismiss-after-a-few-seconds pattern already used by
 * StationGrid.vue's `infoMessage` for its own "belum tersedia" message.
 */
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import heroImage from '@/assets/home-hero-mill.jpg'

const router = useRouter()
const authStore = useAuthStore()

const welcomeText = computed(() => {
  const name = authStore.currentUser?.name?.trim()

  return name ? `Selamat datang, ${name}` : 'Selamat datang'
})

const isNavMenuOpen = ref(false)

function toggleNavMenu() {
  isNavMenuOpen.value = !isNavMenuOpen.value
}

function closeNavMenu() {
  isNavMenuOpen.value = false
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
 * business_logic step 4 / edge_case_handling — tapping a placeholder menu
 * card shows a brief "Segera Hadir" info message and does NOT navigate.
 * Auto-dismissed after a few seconds, same approach as StationGrid.vue's
 * `infoMessage`.
 */
const infoMessage = ref<string | null>(null)
let infoMessageTimeout: ReturnType<typeof setTimeout> | null = null

function showComingSoon() {
  infoMessage.value = 'Segera Hadir'

  if (infoMessageTimeout) {
    clearTimeout(infoMessageTimeout)
  }

  infoMessageTimeout = setTimeout(() => {
    infoMessage.value = null
  }, 3000)
}

/**
 * business_logic step 3 — the only functional menu card, navigates to the
 * Station List route (screen-006, router name 'station-list', per
 * router/index.ts).
 */
function goToStationList() {
  router.push({ name: 'station-list' })
}

interface MenuCard {
  key: string
  label: string
  action: () => void
}

const MENU_CARDS: MenuCard[] = [
  {
    key: 'estimates-baselines',
    label: 'Estimates & Baselines',
    action: showComingSoon,
  },
  {
    key: 'production-process-activity',
    label: 'Production Process Activity',
    action: goToStationList,
  },
  {
    key: 'dashboard-reporting',
    label: 'Dashboard & Reporting',
    action: showComingSoon,
  },
]

const MENU_ICONS: Record<string, string> = {
  'estimates-baselines':
    '<path d="M3 3v18h18"/><path d="M7 15l4-5 4 3 5-7"/>',
  'production-process-activity':
    '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
  'dashboard-reporting':
    '<rect x="3" y="10" width="4" height="11"/><rect x="10" y="6" width="4" height="15"/><rect x="17" y="3" width="4" height="18"/>',
}
</script>

<template>
  <main class="home-view">
    <header class="home-header">
      <div class="home-header-brand">
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
        aria-label="Buka menu navigasi"
        data-testid="hamburger-button"
        @click="toggleNavMenu"
      >
        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
          <line x1="3" y1="6" x2="21" y2="6" />
          <line x1="3" y1="12" x2="21" y2="12" />
          <line x1="3" y1="18" x2="21" y2="18" />
        </svg>
      </button>

      <div v-if="isNavMenuOpen" class="nav-menu" data-testid="nav-menu">
        <button type="button" class="nav-menu-item" data-testid="nav-menu-change-password" @click="goToChangePassword">
          Ganti Password
        </button>
        <button type="button" class="nav-menu-item" data-testid="nav-menu-logout" @click="onLogout">Logout</button>
      </div>
    </header>

    <p v-if="infoMessage" class="info-message" role="status" data-testid="info-message">{{ infoMessage }}</p>

    <img :src="heroImage" alt="" class="hero-image" />

    <p class="welcome-text">{{ welcomeText }}</p>

    <section class="menu-grid" aria-label="Menu utama">
      <button
        v-for="card in MENU_CARDS"
        :key="card.key"
        type="button"
        class="menu-card"
        :data-testid="`menu-card-${card.key}`"
        @click="card.action"
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
          v-html="MENU_ICONS[card.key]"
        />
        <span class="menu-card-label">{{ card.label }}</span>
      </button>
    </section>
  </main>
</template>

<style scoped>
.home-view {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 0 20px 20px;
  background-color: #ffffff;
  font-family: 'Inter', sans-serif;
  box-sizing: border-box;
}

.home-header {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 64px;
  margin: 0 -20px;
  padding: 0 20px;
  background-color: #ffffff;
}

.home-header-brand {
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

.info-message {
  margin: 0;
  padding: 10px 12px;
  border-radius: 8px;
  background-color: #f7f7f7;
  color: #1f2937;
  font-size: 13px;
}

.hero-image {
  width: 100%;
  height: 160px;
  object-fit: cover;
  border-radius: 12px;
}

.welcome-text {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
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

.menu-card-icon {
  flex-shrink: 0;
  color: #249360;
}

.menu-card-label {
  font-size: 15px;
  font-weight: 600;
  color: #1f2937;
}
</style>
