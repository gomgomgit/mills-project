<script setup lang="ts">
/**
 * MonitorCagesTrackView — screen-009--monitor-cages-track /
 * usecase-009--monitor-cages-track (mounted at /stations/cages-track/monitor,
 * meta.public = false — requires an authenticated session, enforced by the
 * router's global auth guard; see router/index.ts). Actors: operator,
 * supervisor.
 *
 * Mobile-only screen — no backend API endpoint; all data comes from the
 * local (offline) `cages_track_record` table (and a scoped SUM over the
 * child `cages_tipped_time` table) via cagesTrackRecordRepo.ts.
 * api_contracts.endpoints is empty for this screen.
 *
 * Rewritten (2026-08-19, entity-catalog v3): the screen's purpose changed
 * from a "single current draft + summary + Pause/Clear actions" view to a
 * full scrollable LIST of the current user's ongoing/paused drafts
 * (business_logic step 2, via the new cagesTrackRecordRepo.getDrafts()),
 * plus a "Hari Ini" 2-card counter row (business_logic step 1, via the new
 * cagesTrackRecordRepo.getTodaySummary()) — mirrors
 * MonitorGradingView.vue's (screen-008) own entity-catalog v2 rewrite
 * pattern exactly, adapted to cages-track's entity-catalog v3 fields.
 * Every row in the list is labeled uniformly "Pause" (reusing
 * StatusBadge.vue with a fixed status="paused" + label="Pause" override)
 * — no ongoing-vs-paused distinction is surfaced. The previous summary
 * section (single "Jumlah Cage/Lori Tercatat" count scoped to the current
 * draft only), primary action button ('Mulai Input Baru' / 'Lanjutkan'),
 * Pause/Clear actions, and ConfirmDialog usage are removed entirely —
 * this screen no longer mutates draft status (no resumeDraft()/
 * pauseDraft()/deleteDraft() calls from here); cagesTrackRecordRepo.ts
 * keeps those functions in place for other callers (StationListView.vue's
 * getProgressSummary() draft-status lookup; resumeDraft()/pauseDraft()/
 * deleteDraft() kept for parity/future use — see that file's header
 * comment).
 *
 * Counter card labels per this rewrite's tech spec: "Jumlah Cages Track"
 * (COUNT of today's cages_track_record rows, any status) / "Jumlah
 * Cage/Lori Tercatat" (SUM of cages_tipped_time.total_cages across those
 * records' child rows — a sum, not a row count, since one row can
 * represent multiple cages tipped at once). Only 2 cards (vs.
 * MonitorGradingView.vue's 3) — cages-track's "Hari Ini" section has no
 * third numeric field.
 *
 * Header/breadcrumb/nav-menu: copied verbatim from
 * MonitorGradingView.vue's isNavMenuOpen/toggleNavMenu/closeNavMenu/
 * goToChangePassword/onLogout pattern (brand icon + name, hamburger
 * button, nav-menu dropdown with "Ganti Password" + "Logout") so all
 * mobile station screens stay visually and behaviorally consistent.
 * Breadcrumb is 3 segments deep (Home > Production Process Activity >
 * Cages Track, the first two tappable, matching
 * MonitorGradingView.vue's/StationListView.vue's `breadcrumb-link`/
 * `data-testid="breadcrumb-home"` convention), since this screen sits one
 * level below Station List.
 *
 * Navigation:
 *  - 'New Data' → cagesTrackRecordRepo.createDraft(userId) (INSERTs a new
 *    status='draft_ongoing' row, with tippler_start_time auto-filled at
 *    creation — see that function's doc comment), then → route name
 *    `cages-track-form` with the new draft's id.
 *  - Tapping a list item → route name `cages-track-form` with that item's
 *    EXISTING id, directly — no status-transition call and no status
 *    change, per this rewrite's spec (tapping only navigates).
 *  - 'Load Data' → route name `data-preview-cages-track` with NO id param
 *    (list mode).
 *  - 'Back' → route name `station-list`, already registered
 *    (router/index.ts).
 */
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useFloatingClockStore } from '@/stores/floatingClock'
import {
  cagesTrackRecordRepo,
  type CagesTrackDraftListItem,
  type CagesTrackTodaySummary,
} from '@/services/cagesTrackRecordRepo'
import StatusBadge from '@/components/StatusBadge.vue'

const router = useRouter()
const authStore = useAuthStore()
const floatingClockStore = useFloatingClockStore()

const drafts = ref<CagesTrackDraftListItem[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

// "Hari Ini" counter section — see file header comment. Defaults to
// all-zero so the cards render "0" rather than blank before the load
// completes / when there is no data today.
const todaySummary = ref<CagesTrackTodaySummary>({ countCagesTrack: 0, sumTotalCages: 0 })

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
    drafts.value = await cagesTrackRecordRepo.getDrafts(userId)
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Gagal memuat daftar draft cages track lokal.'
  } finally {
    loading.value = false
  }
}

// "Hari Ini" counter section — loads today's count + sum. Failures here
// are deliberately silent (todaySummary simply stays at its all-zero
// default) rather than surfaced via the shared `error` state, since this
// is a secondary/supplementary section and must not block or replace the
// existing draft-list error UX.
async function loadTodaySummary() {
  const userId = currentUserId()

  if (!userId) {
    return
  }

  try {
    todaySummary.value = await cagesTrackRecordRepo.getTodaySummary(userId)
  } catch {
    // silent — see comment above.
  }
}

async function loadAll() {
  await Promise.all([loadDrafts(), loadTodaySummary()])
}

onMounted(loadAll)

/**
 * Cages Track Number fallback text for a draft whose `cages_track_number`
 * has not been filled in yet.
 */
function draftLabel(draft: CagesTrackDraftListItem): string {
  return draft.cages_track_number?.trim() ? draft.cages_track_number : 'Cages Track Number belum diisi'
}

// business_logic step 5 — 'New Data'. Enabled regardless of whether the
// list is empty.
async function onNewData() {
  const userId = currentUserId()

  if (!userId) {
    return
  }

  try {
    const id = await cagesTrackRecordRepo.createDraft(userId)
    router.push({ name: 'cages-track-form', params: { id } })
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Gagal membuat draft cages track baru.'
  }
}

// business_logic step 6 — tapping a list item navigates straight into the
// existing draft. No status-transition call of any kind.
function onDraftClick(draft: CagesTrackDraftListItem) {
  router.push({ name: 'cages-track-form', params: { id: draft.id } })
}

// business_logic step 7 — 'Load Data', list mode (no id param).
function onLoadData() {
  router.push({ name: 'data-preview-cages-track' })
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

function goToChangePassword() {
  closeNavMenu()
  router.push({ name: 'change-password' })
}

async function onLogout() {
  closeNavMenu()
  await authStore.logout()
  router.push({ name: 'login' })
}

// business_logic step 8 — breadcrumb taps. 'Cages Track' is the current
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
  <main class="monitor-cages-track-view">
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

    <div class="monitor-cages-track-header">
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
        <span class="breadcrumb-current" aria-current="page">Cages Track</span>
      </nav>
      <h1 class="screen-title">Monitor Cages Track</h1>
    </div>

    <section class="today-summary-section" aria-label="Hari Ini">
      <h2 class="today-summary-title">Hari Ini</h2>
      <div class="today-summary-grid">
        <div class="today-summary-card">
          <span class="today-summary-label">Jumlah Cages Track</span>
          <span class="today-summary-value" data-testid="counter-count-cages-track">{{ todaySummary.countCagesTrack }}</span>
        </div>
        <div class="today-summary-card">
          <span class="today-summary-label">Jumlah Cage/Lori Tercatat</span>
          <span class="today-summary-value" data-testid="counter-total-cages">{{ todaySummary.sumTotalCages }}</span>
        </div>
      </div>
    </section>

    <p v-if="loading" class="status-text">Memuat daftar draft cages track lokal…</p>
    <p v-else-if="error" class="status-text status-text--error" role="alert">{{ error }}</p>

    <section v-else class="draft-list-section" aria-label="Daftar Draft Cages Track">
      <p v-if="drafts.length === 0" class="empty-state" data-testid="draft-list-empty">
        Belum ada draft cages track tersimpan.
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
.monitor-cages-track-view {
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

.monitor-cages-track-header {
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

/*
 * Fully scrollable, no truncation/pagination — the list is never given a
 * fixed/max height that would clip it; it simply grows and, once the page
 * is taller than the viewport, scrolls with the rest of `main` (matching
 * this app's other local-list screens, e.g. StationListView.vue /
 * MonitorGradingView.vue, which rely on normal document scroll rather
 * than an inner scroll region).
 */
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
