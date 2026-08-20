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
import { useFloatingClockStore } from '@/stores/floatingClock'
import { stationRepo, type StationSlot, type StationType } from '@/services/stationRepo'
import { productionLineRepo, type ProductionLineOption } from '@/services/productionLineRepo'
import { seedDefaultStationsIfNeeded } from '@/services/localSchema'
import { weighbridgeRecordRepo } from '@/services/weighbridgeRecordRepo'
import { gradingRecordRepo } from '@/services/gradingRecordRepo'
import { cagesTrackRecordRepo } from '@/services/cagesTrackRecordRepo'
import { syncAllRecords, type SyncSummary } from '@/services/syncService'
import StationGrid from '@/components/StationGrid.vue'
import SyncResultDialog from '@/components/SyncResultDialog.vue'

const router = useRouter()
const authStore = useAuthStore()
const floatingClockStore = useFloatingClockStore()

const stations = ref<StationSlot[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const draftStatusByType = ref<Partial<Record<StationType, boolean>>>({})
const syncing = ref(false)
const syncDialogOpen = ref(false)
const syncSummary = ref<SyncSummary | null>(null)
const syncErrorMessage = ref<string | null>(null)

/**
 * Production Line picker step (2026-08-20, entity-catalog v9 — Business
 * Unit → Production Line → Station: a Business Unit/mill can now have
 * several Production Lines, each with its own full set of stations),
 * inserted before the station-tile grid per explicit product decision.
 *
 * `productionLines.length > 1` gates whether the picker UI actually shows
 * — exactly one Production Line (by far the common case today) or zero
 * (fetch failed, offline, or the business unit has none yet — legacy
 * fallback) both skip straight to the grid, per
 * `loadProductionLinesAndStations()` below.
 */
const productionLines = ref<ProductionLineOption[]>([])
const selectedProductionLineId = ref<string | null>(null)
const showProductionLinePicker = ref(false)

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

  await loadProductionLinesAndStations(businessUnitId)

  loading.value = false

  await loadDraftStatusByType()
})

/**
 * business_logic step 1, extended for the Production Line picker step:
 *   - >1 Production Line -> show the picker (grid stays hidden until the
 *     user taps one, see selectProductionLine() below).
 *   - exactly 1 -> auto-select it (no picker shown at all), best-effort
 *     sync its real stations from the backend, then load the grid scoped
 *     to it.
 *   - 0 (fetch failed, offline, or the business unit genuinely has none
 *     yet) -> legacy fallback: load the grid the OLD way, scoped by
 *     business_unit_id against whatever `station` rows are already cached
 *     locally (seedDefaultStationsIfNeeded()'s synthetic seed, and/or any
 *     previously-synced real rows) — never leaves the user with a
 *     permanently empty screen just because the Production Line feature
 *     itself is unreachable.
 */
async function loadProductionLinesAndStations(businessUnitId: string): Promise<void> {
  const lines = await productionLineRepo.fetchCurrentProductionLines().catch(() => [])

  if (lines.length > 1) {
    productionLines.value = lines
    showProductionLinePicker.value = true

    return
  }

  showProductionLinePicker.value = false

  if (lines.length === 1) {
    await selectProductionLine(lines[0], businessUnitId)

    return
  }

  // Legacy fallback (2026-08-20) — the Production Line fetch returned zero
  // lines (offline, or the business unit genuinely has none yet). The
  // synthetic 15-station seed used to run unconditionally at login
  // instead — moved here so it only ever runs when the real sync path is
  // unreachable, never alongside it (that combination was what caused
  // stations to render doubled, see seedDefaultStationsIfNeeded()'s own
  // updated doc comment).
  try {
    await seedDefaultStationsIfNeeded(businessUnitId)
  } catch {
    // Local SQLite write failed — non-fatal, fall through to whatever is
    // already cached (possibly nothing, possibly a prior successful seed).
  }

  try {
    stations.value = await stationRepo.getActiveAndPlaceholderStations(businessUnitId)
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Gagal memuat daftar stasiun lokal.'
  }
}

/**
 * Tapping a Production Line on the picker (or the auto-select branch
 * above). Syncing its real stations from the backend is best-effort — a
 * failure (offline) still lets the grid render from whatever `station`
 * rows are already cached locally for this Production Line from an
 * earlier sync, consistent with every other best-effort fetch in this
 * screen/localSchema.ts.
 */
async function selectProductionLine(line: ProductionLineOption, businessUnitId: string): Promise<void> {
  selectedProductionLineId.value = line.id
  showProductionLinePicker.value = false
  loading.value = true
  error.value = null

  try {
    await productionLineRepo.fetchAndCacheStationsForProductionLine(line.id, businessUnitId).catch(() => {})
    stations.value = await stationRepo.getActiveAndPlaceholderStationsForProductionLine(line.id)
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Gagal memuat daftar stasiun lokal.'
  } finally {
    loading.value = false
  }
}

async function onSelectProductionLine(line: ProductionLineOption): Promise<void> {
  const businessUnitId = authStore.currentUser?.business_unit_id

  if (!businessUnitId) {
    return
  }

  await selectProductionLine(line, businessUnitId)
}

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

/**
 * TEMPORARY (2026-08-20) — manual "Sinkronisasi" button, see
 * syncService.ts's doc comment for the full mechanism/scope. Not tied to
 * business_logic in this screen's spec (screen-006) — a pragmatic stopgap
 * added on direct request, not through a spec revision, per the user's own
 * "sementara" framing.
 *
 * Result is shown in a popup (SyncResultDialog) rather than inline text —
 * a long failure list (each with its own reason) previously pushed the
 * rest of the screen's layout around; a dialog keeps that contained
 * without disturbing the station grid underneath.
 */
async function onSync() {
  syncing.value = true
  syncSummary.value = null
  syncErrorMessage.value = null

  try {
    syncSummary.value = await syncAllRecords(selectedProductionLineId.value)
  } catch (err) {
    syncErrorMessage.value = err instanceof Error ? err.message : 'Sinkronisasi gagal — kesalahan tidak diketahui.'
  } finally {
    syncing.value = false
    syncDialogOpen.value = true
  }
}

function closeSyncDialog() {
  syncDialogOpen.value = false
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
        <button type="button" class="nav-menu-item" data-testid="nav-menu-toggle-floating-clock" @click="floatingClockStore.toggle()">{{ floatingClockStore.enabled ? 'Nonaktifkan Jam Mengambang' : 'Aktifkan Jam Mengambang' }}</button>
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

    <div v-else-if="showProductionLinePicker" class="production-line-picker" data-testid="production-line-picker">
      <p class="production-line-picker-title">Pilih Production Line</p>
      <button
        v-for="line in productionLines"
        :key="line.id"
        type="button"
        class="production-line-option"
        :data-testid="`production-line-option-${line.id}`"
        @click="onSelectProductionLine(line)"
      >
        {{ line.name }}
      </button>
    </div>

    <StationGrid v-else :stations="stations" :draft-status-by-type="draftStatusByType" @navigate="onNavigate" />

    <footer class="action-footer">
      <button
        type="button"
        class="action-button action-button--primary"
        data-testid="sync-button"
        :disabled="syncing"
        @click="onSync"
      >
        {{ syncing ? 'Menyinkronkan…' : 'Sinkronisasi' }}
      </button>
    </footer>

    <SyncResultDialog
      :open="syncDialogOpen"
      :summary="syncSummary"
      :error-message="syncErrorMessage"
      @close="closeSyncDialog"
    />
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

.action-footer {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: auto;
}

.action-button {
  min-height: 44px;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  box-sizing: border-box;
}

.action-button--primary {
  border: none;
  background-color: #249360;
  color: #ffffff;
}

.action-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.status-text {
  font-size: 14px;
  color: #6b7280;
}

.status-text--error {
  color: #dc2626;
}

.production-line-picker {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.production-line-picker-title {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
}

.production-line-option {
  min-height: 44px;
  padding: 0 16px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background-color: #ffffff;
  color: #1f2937;
  font-size: 15px;
  font-weight: 600;
  font-family: inherit;
  text-align: left;
  cursor: pointer;
}

.production-line-option:hover {
  border-color: #249360;
  color: #249360;
}
</style>
