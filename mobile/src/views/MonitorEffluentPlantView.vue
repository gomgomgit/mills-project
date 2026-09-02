<script setup lang="ts">
/**
 * MonitorEffluentPlantView — screen-065--monitor-effluent-plant /
 * usecase-085--monitor-effluent-plant (mounted at
 * /stations/effluent-plant/monitor, meta.public = false — requires an
 * authenticated session, enforced by the router's global auth guard; see
 * router/index.ts). Actors: operator, supervisor.
 *
 * Mobile-only screen — no backend API endpoint; all data comes from the
 * local (offline) `effluent_plant_record` table (and its child
 * `effluent_plant_detail` table, for the "filled slot" counter) via
 * effluentPlantRecordRepo.ts. api_contracts.endpoints is empty for this
 * screen.
 *
 * Structural pattern copied from MonitorThreshingView.vue exactly: a "Hari
 * Ini" 2-card counter row plus a full scrollable LIST of the current
 * user's ongoing/paused drafts, every row labeled uniformly "Pause"
 * (StatusBadge.vue with a fixed status="paused" + label="Pause" override)
 * — no ongoing-vs-paused distinction is surfaced. This screen does not
 * mutate draft status from here (no resumeDraft()/pauseDraft()/
 * deleteDraft() calls) — those actions live in Form Effluent Plant
 * (screen-075).
 *
 * Counter card labels per this screen's tech spec: "Jumlah Effluent Plant
 * Record" (COUNT of today's effluent_plant_record rows, any status) /
 * "Jumlah Baris Time-Slot Tercatat" (COUNT of effluent_plant_detail rows
 * belonging to those records — row EXISTENCE itself is the meaningful
 * signal, a plain count of rows the user has added, with no fixed-24
 * denominator).
 *
 * Header/breadcrumb/nav-menu: copied verbatim from
 * MonitorThreshingView.vue's isNavMenuOpen/toggleNavMenu/closeNavMenu/
 * goToChangePassword/onLogout pattern so all mobile station screens stay
 * visually and behaviorally consistent. Breadcrumb is 3 segments deep
 * (Home > Production Process Activity > Effluent Plant, the first two
 * tappable).
 *
 * Navigation:
 *  - 'New Data' -> effluentPlantRecordRepo.createDraft(userId) (INSERTs a
 *    new status='draft_ongoing' header row ONLY — no effluent_plant_detail
 *    rows are pre-created, see that function's doc comment), then ->
 *    route name `monitor-effluent-plant`'s sibling `effluent-plant-form`
 *    with the new draft's id.
 *  - Tapping a list item -> route name `effluent-plant-form` with that
 *    item's EXISTING id, directly — no status-transition call.
 *  - 'Load Data' -> route name `data-preview-effluent-plant` with NO id
 *    param (list mode).
 *  - 'Back' -> route name `station-list`.
 */
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useFloatingClockStore } from '@/stores/floatingClock'
import { useAiAssistantStore } from '@/stores/aiAssistant'
import {
  effluentPlantRecordRepo,
  type EffluentPlantDraftListItem,
  type EffluentPlantTodaySummary,
} from '@/services/effluentPlantRecordRepo'
import StatusBadge from '@/components/StatusBadge.vue'

const router = useRouter()
const authStore = useAuthStore()
const floatingClockStore = useFloatingClockStore()
const aiAssistantStore = useAiAssistantStore()

const drafts = ref<EffluentPlantDraftListItem[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

// "Hari Ini" counter section — defaults to all-zero so the cards render "0"
// rather than blank before the load completes / when there is no data
// today.
const todaySummary = ref<EffluentPlantTodaySummary>({ countEffluentPlantRecord: 0, detailRowCount: 0 })

function currentUserId(): string | null {
  return authStore.currentUser?.id ?? null
}

// business_logic step 2 — load the current user's ongoing/paused drafts,
// most-recently-updated first.
async function loadDrafts() {
  const userId = currentUserId()

  if (!userId) {
    return
  }

  loading.value = true
  error.value = null

  try {
    drafts.value = await effluentPlantRecordRepo.getDrafts(userId)
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Gagal memuat daftar draft effluent plant lokal.'
  } finally {
    loading.value = false
  }
}

// "Hari Ini" counter section — loads today's count + filled-slot count.
// Failures here are deliberately silent (todaySummary simply stays at its
// all-zero default) rather than surfaced via the shared `error` state,
// since this is a secondary/supplementary section and must not block or
// replace the existing draft-list error UX.
async function loadTodaySummary() {
  const userId = currentUserId()

  if (!userId) {
    return
  }

  try {
    todaySummary.value = await effluentPlantRecordRepo.getTodaySummary(userId)
  } catch {
    // silent — see comment above.
  }
}

async function loadAll() {
  await Promise.all([loadDrafts(), loadTodaySummary()])
}

onMounted(loadAll)

/**
 * Effluent Plant ID fallback text for a draft whose `effluent_plant_id` has
 * not been filled in yet.
 */
function draftLabel(draft: EffluentPlantDraftListItem): string {
  return draft.effluent_plant_id?.trim() ? draft.effluent_plant_id : 'Effluent Plant ID belum diisi'
}

// business_logic step 5 — 'New Data'. Enabled regardless of whether the
// list is empty.
async function onNewData() {
  const userId = currentUserId()

  if (!userId) {
    return
  }

  try {
    const id = await effluentPlantRecordRepo.createDraft(userId)
    router.push({ name: 'effluent-plant-form', params: { id } })
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Gagal membuat draft effluent plant baru.'
  }
}

// business_logic step 6 — tapping a list item navigates straight into the
// existing draft. No status-transition call of any kind.
function onDraftClick(draft: EffluentPlantDraftListItem) {
  router.push({ name: 'effluent-plant-form', params: { id: draft.id } })
}

// business_logic step 7 — 'Load Data', list mode (no id param).
function onLoadData() {
  router.push({ name: 'data-preview-effluent-plant' })
}

// 'Back'.
function onBack() {
  router.push({ name: 'station-list' })
}

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

// business_logic step 8 — breadcrumb taps. 'Effluent Plant' is the current
// page (not a link — rendered as plain text with aria-current="page" in
// the template).
function goToHome() {
  router.push({ name: 'home' })
}

function goToStationList() {
  router.push({ name: 'station-list' })
}
</script>

<template>
  <main class="monitor-effluent-plant-view">
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

    <div class="monitor-effluent-plant-header">
      <nav class="breadcrumb" aria-label="Breadcrumb">
        <button type="button" class="breadcrumb-link" data-testid="breadcrumb-home" @click="goToHome">Home</button>
        <span class="breadcrumb-separator" aria-hidden="true">/</span>
        <button
          type="button"
          class="breadcrumb-link"
          data-testid="breadcrumb-production-process-activity"
          @click="goToStationList"
        >
          Production Process Activity
        </button>
        <span class="breadcrumb-separator" aria-hidden="true">/</span>
        <span class="breadcrumb-current" aria-current="page">Effluent Plant</span>
      </nav>
      <h1 class="screen-title">Monitor Effluent Plant</h1>
    </div>

    <section class="today-summary-section" aria-label="Hari Ini">
      <h2 class="today-summary-title">Hari Ini</h2>
      <div class="today-summary-grid">
        <div class="today-summary-card">
          <span class="today-summary-label">Jumlah Effluent Plant Record</span>
          <span class="today-summary-value" data-testid="counter-count-effluent-plant-record">{{ todaySummary.countEffluentPlantRecord }}</span>
        </div>
        <div class="today-summary-card">
          <span class="today-summary-label">Jumlah Baris Time-Slot Tercatat</span>
          <span class="today-summary-value" data-testid="counter-detail-row-count">{{ todaySummary.detailRowCount }}</span>
        </div>
      </div>
    </section>

    <p v-if="loading" class="status-text">Memuat daftar draft effluent plant lokal…</p>
    <p v-else-if="error" class="status-text status-text--error" role="alert">{{ error }}</p>

    <section v-else class="draft-list-section" aria-label="Daftar Draft Effluent Plant">
      <p v-if="drafts.length === 0" class="empty-state" data-testid="draft-list-empty">
        Belum ada draft effluent plant tersimpan.
      </p>

      <ul v-else class="draft-list" role="list" data-testid="draft-list">
        <li v-for="draft in drafts" :key="draft.id" role="listitem">
          <button
            type="button"
            class="draft-item"
            :data-testid="`draft-item-${draft.id}`"
            @click="onDraftClick(draft)"
          >
            <span class="draft-item-label">{{ draftLabel(draft) }}</span>
            <StatusBadge status="paused" label="Pause" />
          </button>
        </li>
      </ul>
    </section>

    <footer class="action-footer">
      <div class="action-row">
        <button type="button" class="action-button action-button--secondary" data-testid="back-button" @click="onBack">
          Back
        </button>
        <button
          type="button"
          class="action-button action-button--secondary"
          data-testid="load-data-button"
          @click="onLoadData"
        >
          Load Data
        </button>
      </div>

      <button
        type="button"
        class="action-button action-button--primary"
        data-testid="new-data-button"
        @click="onNewData"
      >
        New Data
      </button>
    </footer>
  </main>
</template>

<style scoped>
.monitor-effluent-plant-view {
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

.monitor-effluent-plant-header {
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

.today-summary-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.today-summary-title {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
}

.today-summary-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
}

.today-summary-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  min-width: 0;
  padding: 12px;
  border: none;
  border-radius: 8px;
  background-color: #249360;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
  box-sizing: border-box;
  text-align: center;
}

.today-summary-label {
  font-size: 11px;
  font-weight: 500;
  color: #eef6f1;
  overflow-wrap: break-word;
}

.today-summary-value {
  font-size: 20px;
  font-weight: 700;
  color: #ffffff;
}

.status-text {
  font-size: 14px;
  color: #6b7280;
}

.status-text--error {
  color: #dc2626;
}

.draft-list-section {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}

.empty-state {
  margin: 0;
  padding: 16px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  color: #6b7280;
  font-size: 14px;
  text-align: center;
}

.draft-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.draft-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
  min-height: 44px;
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background-color: #ffffff;
  box-sizing: border-box;
  text-align: left;
  font-family: inherit;
  cursor: pointer;
}

.draft-item-label {
  font-size: 14px;
  font-weight: 500;
  color: #1f2937;
}

.action-footer {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: auto;
}

.action-row {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
}

.action-button {
  min-height: 44px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  box-sizing: border-box;
}

.action-button--secondary {
  border: 1px solid #e5e7eb;
  background-color: #ffffff;
  color: #1f2937;
}

.action-button--primary {
  border: none;
  background-color: #249360;
  color: #ffffff;
  font-size: 16px;
}
</style>
