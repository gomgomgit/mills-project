<script setup lang="ts">
/**
 * StationListView — screen-006--station-list / usecase-006--station-list
 * "Pilih Stasiun" (mounted at /stations, meta.public = false — requires an
 * authenticated session, enforced by the router's global auth guard; see
 * router/index.ts).
 *
 * Mobile-only screen — no backend API endpoint; all data comes from the
 * local (offline) `station` table via stationRepo.ts / localDb.ts.
 *
 * Layout follows the same split pattern as HomeView.vue (view = layout +
 * data wiring, presentational/interaction logic in StationGrid.vue), plus
 * a mobile breadcrumb chip + title per uiux-spec's mobile "list"
 * screen_type header pattern (ver 2: header_area is breadcrumb chip +
 * title ONLY — "TANPA placeholder gambar generik tanpa makna"; a
 * reference image is only added if/when a final design asset exists,
 * which it does not today, so no reference-image element is rendered).
 *
 * Navigation on tapping an ACTIVE station tile (business_logic step 3)
 * targets route names `monitor-weighbridge` / `monitor-grading` /
 * `monitor-cages-track` — screens 007/008/009, not yet built, so these
 * route names are not yet registered in router/index.ts. The navigation
 * call itself is correct and will resolve once those screens land;
 * documented as a known_issue rather than worked around here (same
 * pattern HomeView.vue used for `station-list` before this screen
 * registered it).
 *
 * Update (2026-08-18): adds the header (brand + hamburger nav menu,
 * copied verbatim from HomeView.vue's isNavMenuOpen/toggleNavMenu/
 * closeNavMenu/goToChangePassword/onLogout pattern so both screens stay
 * visually and behaviorally consistent), a tappable breadcrumb (business_
 * logic steps 4/8), and per-active-station-type draft-status detection
 * (business_logic step 2) so StationGrid.vue can render red-vs-black
 * tiles. Draft detection reuses the EXISTING record repos' summary
 * functions exactly as-is — weighbridgeRecordRepo.getSummary(userId),
 * gradingRecordRepo.getProgressSummary(userId),
 * cagesTrackRecordRepo.getProgressSummary(userId) — each already returns
 * `currentDraft: CurrentDraft | null` scoped to
 * `created_by = userId AND status IN ('draft_ongoing', 'draft_paused')`,
 * so no new draft-query service is introduced. The three calls run in
 * parallel (Promise.all) and are best-effort: a failure from any one of
 * them is swallowed (station stays black/no-draft) rather than blocking
 * the whole station list on a draft-status lookup failure, since draft
 * color-coding is a secondary affordance, not the primary data this
 * screen needs to render.
 */
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { stationRepo, type StationSlot, type StationType } from '@/services/stationRepo'
import { weighbridgeRecordRepo } from '@/services/weighbridgeRecordRepo'
import { gradingRecordRepo } from '@/services/gradingRecordRepo'
import { cagesTrackRecordRepo } from '@/services/cagesTrackRecordRepo'
import StationGrid from '@/components/StationGrid.vue'

const router = useRouter()
const authStore = useAuthStore()

const stations = ref<StationSlot[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const draftStatusByType = ref<Partial<Record<StationType, boolean>>>({})

/**
 * Maps an active station's type to the (not-yet-registered) monitor
 * screen route name it should navigate to — business_logic step 3.
 * `other` never appears as an active tile in practice (only weighbridge /
 * grading / cages-track are real, implemented station types per this
 * screen's spec) but is handled defensively rather than assumed away.
 */
const MONITOR_ROUTE_NAMES: Partial<Record<StationType, string>> = {
  weighbridge: 'monitor-weighbridge',
  grading: 'monitor-grading',
  'cages-track': 'monitor-cages-track',
}

onMounted(async () => {
  const businessUnitId = authStore.currentUser?.business_unit_id

  if (!businessUnitId) {
    return
  }

  loading.value = true
  error.value = null

  try {
    stations.value = await stationRepo.getActiveAndPlaceholderStations(businessUnitId)
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Gagal memuat daftar stasiun lokal.'
  } finally {
    loading.value = false
  }

  await loadDraftStatusByType()
})

/**
 * business_logic step 2 — hasDraft per active station type, for the
 * current user. Best-effort per repo call: any single repo's rejection is
 * caught individually so one failing lookup does not blank out the other
 * two already-successful ones.
 */
async function loadDraftStatusByType() {
  const userId = authStore.currentUser?.id

  if (!userId) {
    return
  }

  const [weighbridge, grading, cagesTrack] = await Promise.all([
    weighbridgeRecordRepo.getSummary(userId).catch(() => null),
    gradingRecordRepo.getProgressSummary(userId).catch(() => null),
    cagesTrackRecordRepo.getProgressSummary(userId).catch(() => null),
  ])

  draftStatusByType.value = {
    weighbridge: weighbridge?.currentDraft !== null && weighbridge?.currentDraft !== undefined,
    grading: grading?.currentDraft !== null && grading?.currentDraft !== undefined,
    'cages-track': cagesTrack?.currentDraft !== null && cagesTrack?.currentDraft !== undefined,
  }
}

function onNavigate(type: StationType) {
  const routeName = MONITOR_ROUTE_NAMES[type]

  if (!routeName) {
    return
  }

  router.push({ name: routeName })
}

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
 * business_logic step 8 — tapping the 'Home' breadcrumb segment navigates
 * there. 'Production Process Activity' is the current page, so it is not
 * a link (rendered as plain text with aria-current="page" in the
 * template).
 */
function goToHome() {
  router.push({ name: 'home' })
}
</script>

<template>
  <main class="station-list-view">
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

    <div class="station-list-header">
      <nav class="breadcrumb" aria-label="Breadcrumb">
        <button type="button" class="breadcrumb-link" data-testid="breadcrumb-home" @click="goToHome">Home</button>
        <span class="breadcrumb-separator" aria-hidden="true">/</span>
        <span class="breadcrumb-current" aria-current="page">Production Process Activity</span>
      </nav>
      <h1 class="screen-title">Daftar Stasiun</h1>
    </div>

    <p v-if="loading" class="status-text">Memuat daftar stasiun…</p>
    <p v-else-if="error" class="status-text status-text--error" role="alert">{{ error }}</p>

    <StationGrid v-else :stations="stations" :draft-status-by-type="draftStatusByType" @navigate="onNavigate" />
  </main>
</template>

<style scoped>
.station-list-view {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 20px;
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

.station-list-header {
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

.status-text {
  font-size: 14px;
  color: #6b7280;
}

.status-text--error {
  color: #dc2626;
}
</style>
