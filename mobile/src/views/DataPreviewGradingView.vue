<script setup lang="ts">
/**
 * DataPreviewGradingView — screen-014--data-preview-grading /
 * usecase-014--data-preview-grading (mounted at
 * /stations/grading/preview/:id? — `:id` is OPTIONAL, see
 * router/index.ts's comment on this route; meta.public = false — requires
 * an authenticated session, enforced by the router's global auth guard).
 * Actors: operator, supervisor.
 *
 * Mobile-only screen — no backend API endpoint (api_contracts.endpoints is
 * empty). All data comes from the local (offline) `grading_record` +
 * `grading_detail` + `grading_parameter` tables via gradingRecordRepo.ts.
 *
 * Rewritten (2026-08-19, scope expansion — mirrors
 * DataPreviewWeighbridgeView.vue's (screen-013) 2026-08-18 rewrite): this
 * screen now has TWO route-driven modes, both served by this same
 * view/route (`data-preview-grading`):
 *
 *  - LIST mode — route has NO `:id` param (the default/primary state,
 *    reached from MonitorGradingView.vue's 'Load Data' button). Shows
 *    every local `grading_record` row for the current user, ANY status
 *    (business_logic step 1, via the new
 *    gradingRecordRepo.getAllRecords() — unlike Monitor Grading's
 *    getDrafts(), which only returns draft_ongoing/draft_paused), with a
 *    client-side (in-memory, no re-query per filter change) date filter +
 *    search filter. Tapping a row navigates onward:
 *      - draft_ongoing/draft_paused rows → `grading-form` (continue
 *        editing, same as Monitor Grading's own row-tap behavior) — does
 *        NOT switch this screen into detail mode.
 *      - saved/synced rows → THIS screen's own route, WITH the row's id
 *        added as the `:id` param (a real router.push, not just local
 *        state), transitioning into DETAIL mode.
 *
 *  - DETAIL mode — route HAS a `:id` param. Read-only single-record view
 *    (header fields + grading_detail grid), unchanged in spirit from this
 *    screen's original implementation: reuses `getDraftWithDetails()`
 *    (already exported by gradingRecordRepo.ts for screen-011--form-grading
 *    — a second identical read accessor would just duplicate it). Quality
 *    Parameter names are resolved client-side in this view by joining each
 *    detail row's `grading_parameter_id` against
 *    `getGradingParameterOptions()` (also already exported, for Form
 *    Grading's own dropdown) — `getDraftWithDetails()` itself returns raw
 *    `grading_detail` rows (id-only FK, no joined name), so this view
 *    fetches the parameter master list once per detail-mode load and
 *    builds an id→name lookup map, rather than adding a second/duplicate
 *    joined-query accessor to the repo. "Checked By" is deliberately NOT
 *    rendered anywhere in this screen (Form Grading has no Checked
 *    By/Acknowledged By section at all as of the entity-catalog v2
 *    rewrite — see gradingRecordRepo.ts's own header comment — so nothing
 *    meaningful would ever be shown there; `acknowledged_by` is likewise
 *    omitted for the same reason).
 *
 * Because the mode is route-driven (not a one-time `onMounted` read of
 * `route.params.id`), a `watch()` on the id param (immediate: true) drives
 * both the initial load AND every list⇄detail transition within the same
 * component instance — Vue Router reuses this instance for param-only
 * navigations on the same route record (list → detail via a row tap,
 * detail → list via Back), so a plain `onMounted` read alone would not
 * re-run on those transitions. Mirrors
 * DataPreviewWeighbridgeView.vue's own watch()-driven mode switch exactly.
 *
 * Header/breadcrumb/nav-menu: copied verbatim from
 * DataPreviewWeighbridgeView.vue's (itself copied from
 * MonitorGradingView.vue's) isNavMenuOpen/toggleNavMenu/closeNavMenu/
 * goToChangePassword/onLogout + brand/hamburger/nav-menu markup, for
 * visual/behavioral consistency. Breadcrumb is 4 segments deep (Home >
 * Production Process Activity > Grading > Load Data, the first three
 * tappable, 'Load Data' the current page as plain text) since this screen
 * sits one level below Monitor Grading — same in both modes.
 *
 * List-row status badge (StatusBadge.vue, `status` + `label` override,
 * same pattern as DataPreviewWeighbridgeView.vue's list badge / this
 * screen's own detail-mode badge below):
 *  - draft_ongoing → status="paused" label="Pause" (matches Monitor
 *    Grading's list exactly — no ongoing-vs-paused visual distinction
 *    there, reused identically here).
 *  - draft_paused  → status="paused" label="Pause" (same as above).
 *  - saved         → status="none" label="Tersimpan"
 *  - synced        → status="none" label="Tersinkron"
 * Detail mode's own badge mapping (draft_ongoing/draft_paused DO get a
 * distinct ongoing/paused visual there) is unchanged from this screen's
 * original implementation — see DETAIL_STATUS_BADGE_MAP below.
 *
 * Date filter: matches business_logic requirement against the
 * `GradingRecord.date` column directly (a plain `YYYY-MM-DD`-shaped TEXT
 * column per localSchema.ts's CREATE_GRADING_RECORD — unlike
 * weighbridge_record's `arrival_datetime`, which is a full datetime and
 * needs a `.slice(0, 10)`). The date filter `<input type="date">`'s value
 * (`YYYY-MM-DD`) is compared against `date` as-is.
 *
 * Search filter: case-insensitive substring match against `grading_number`
 * OR `license_plate_no` (this screen's tech spec fields — grading has no
 * `driver_name` column, unlike weighbridge_record).
 *
 * implementation_notes:
 *  - `getDraftWithDetails()` (screen-011--form-grading) returns raw
 *    `grading_detail` rows with only `grading_parameter_id` (a FK), no
 *    joined parameter name/uom. This view resolves the display name by
 *    fetching `getGradingParameterOptions()` once alongside the detail
 *    load and building an id→GradingParameterOption lookup map
 *    (`parameterMap`), used by `parameterName()` below — not hardcoded,
 *    and not a new repo method (getGradingParameterOptions() already
 *    exists for exactly this master-list read).
 *  - Back-navigation pattern mirrors DataPreviewWeighbridgeView.vue's
 *    `onBack()` exactly: DETAIL mode → `router.push({ name:
 *    'data-preview-grading' })` (id param dropped, same route — the
 *    `watch()` on `recordIdParam` then switches this same component
 *    instance back into LIST mode and re-runs `loadList()`, no full page
 *    reload); LIST mode → `router.push({ name: 'monitor-grading' })`.
 *
 * known_issues:
 *  - None — MonitorGradingView.vue's 'Load Data' button already calls
 *    `router.push({ name: 'data-preview-grading' })` with no id param,
 *    which now correctly lands on this screen's LIST mode (the previous
 *    "always renders not-found" known_issue no longer applies, same
 *    resolution as DataPreviewWeighbridgeView.vue's own equivalent
 *    rewrite).
 */
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useFloatingClockStore } from '@/stores/floatingClock'
import gradingRecordRepo, {
  type GradingDetailRow,
  type GradingParameterOption,
  type GradingRecord,
} from '@/services/gradingRecordRepo'
import StatusBadge, { type BadgeStatus } from '@/components/StatusBadge.vue'
import FormField from '@/components/FormField.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const floatingClockStore = useFloatingClockStore()

function currentUserId(): string | null {
  return authStore.currentUser?.id ?? null
}

// Route-driven mode — a computed (not a one-time onMounted read) so it
// stays reactive across param-only navigations on this same route record
// (see this file's header comment).
const recordIdParam = computed(() => {
  const raw = route.params.id
  const value = Array.isArray(raw) ? raw[0] : raw
  return value ? String(value) : ''
})
const isDetailMode = computed(() => recordIdParam.value !== '')

/* ---------------------------------------------------------------------- *
 * LIST mode
 * ---------------------------------------------------------------------- */

const listLoading = ref(false)
const listError = ref<string | null>(null)
const allRecords = ref<GradingRecord[]>([])
const dateFilter = ref('')
const searchFilter = ref('')

// business_logic step 1 (list mode) — load every local grading_record for
// the current user, any status, most-recently-updated first. Fetched once
// per list-mode entry; date/search filters below are purely client-side
// over this array (never re-queried per filter change). Defaults the date
// filter to today's local date (YYYY-MM-DD, matching
// <input type="date">). Built manually from local getters — not
// toISOString().slice(0,10), which is UTC-based and can be off by one day.
function todayLocalDateString(): string {
  const today = new Date()
  const yyyy = today.getFullYear()
  const mm = String(today.getMonth() + 1).padStart(2, '0')
  const dd = String(today.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

async function loadList(): Promise<void> {
  const userId = currentUserId()

  dateFilter.value = todayLocalDateString()
  searchFilter.value = ''

  if (!userId) {
    allRecords.value = []
    return
  }

  listLoading.value = true
  listError.value = null

  try {
    allRecords.value = await gradingRecordRepo.getAllRecords(userId)
  } catch (err) {
    listError.value = err instanceof Error ? err.message : 'Gagal memuat daftar data grading lokal.'
  } finally {
    listLoading.value = false
  }
}

// business_logic step 2 — Grading Number fallback, mirrors
// DataPreviewWeighbridgeView.vue's recordLabel()/Monitor Grading's
// draftLabel().
function recordLabel(item: GradingRecord): string {
  return item.grading_number?.trim() ? item.grading_number : 'No. Grading belum diisi'
}

const LIST_STATUS_BADGE_MAP: Record<GradingRecord['status'], { status: BadgeStatus; label: string }> = {
  draft_ongoing: { status: 'paused', label: 'Pause' },
  draft_paused: { status: 'paused', label: 'Pause' },
  saved: { status: 'none', label: 'Tersimpan' },
  synced: { status: 'none', label: 'Tersinkron' },
}

function listBadgeInfo(item: GradingRecord) {
  return LIST_STATUS_BADGE_MAP[item.status]
}

// business_logic steps 4-5 (list mode) — client-side date + search
// filters, combined with AND logic, applied over the already-fetched
// `allRecords` array.
const filteredRecords = computed(() => {
  const date = dateFilter.value
  const keyword = searchFilter.value.trim().toLowerCase()

  return allRecords.value.filter((item) => {
    if (date) {
      const recordDate = item.date ?? ''
      if (recordDate !== date) {
        return false
      }
    }

    if (keyword) {
      const gradingNumberMatch = (item.grading_number ?? '').toLowerCase().includes(keyword)
      const licensePlateMatch = (item.license_plate_no ?? '').toLowerCase().includes(keyword)
      if (!gradingNumberMatch && !licensePlateMatch) {
        return false
      }
    }

    return true
  })
})

// edge_case_handling — "Filter Tidak Menghasilkan Apapun": reset button
// clears both filters so the full list reappears.
const hasActiveFilter = computed(() => dateFilter.value !== '' || searchFilter.value !== '')

function onResetFilter(): void {
  dateFilter.value = ''
  searchFilter.value = ''
}

// business_logic steps 6-7 (list mode) — tapping a list row.
function onItemClick(item: GradingRecord): void {
  if (item.status === 'draft_ongoing' || item.status === 'draft_paused') {
    router.push({ name: 'grading-form', params: { id: item.id } })
    return
  }

  router.push({ name: 'data-preview-grading', params: { id: item.id } })
}

/* ---------------------------------------------------------------------- *
 * DETAIL mode
 * ---------------------------------------------------------------------- */

const detailLoading = ref(true)
const detailNotFound = ref(false)
const detailLoadErrorMessage = ref<string | null>(null)
const detailRecord = ref<GradingRecord | null>(null)
const detailRows = ref<GradingDetailRow[]>([])
const parameterOptions = ref<GradingParameterOption[]>([])

// Resolves each grading_detail row's grading_parameter_id → its
// grading_parameter.name, via getGradingParameterOptions() (see this
// file's header comment / implementation_notes) — never hardcoded.
const parameterMap = computed(() => {
  const map = new Map<string, GradingParameterOption>()
  for (const option of parameterOptions.value) {
    map.set(option.id, option)
  }
  return map
})

function parameterName(row: GradingDetailRow): string {
  if (!row.grading_parameter_id) {
    return '-'
  }
  return parameterMap.value.get(row.grading_parameter_id)?.name ?? '-'
}

// business_logic step 1 (detail mode) — load the record + its
// grading_detail rows read-only by id, via getDraftWithDetails() (see
// this file's header comment), plus the grading_parameter master list for
// name resolution.
async function loadDetail(recordId: string): Promise<void> {
  detailLoading.value = true
  detailLoadErrorMessage.value = null
  detailNotFound.value = false
  detailRecord.value = null
  detailRows.value = []
  parameterOptions.value = []

  try {
    const [draft, options] = await Promise.all([
      gradingRecordRepo.getDraftWithDetails(recordId),
      gradingRecordRepo.getGradingParameterOptions(),
    ])

    parameterOptions.value = options

    if (!draft) {
      detailNotFound.value = true
      return
    }

    detailRecord.value = draft.record
    detailRows.value = draft.details
  } catch (err) {
    detailLoadErrorMessage.value = err instanceof Error ? err.message : 'Gagal memuat data grading.'
  } finally {
    detailLoading.value = false
  }
}

/**
 * StatusBadge.vue's `status` prop only models the 3-state
 * none/ongoing/paused set it was originally built for (screen-005's
 * per-station badge). This screen's uiux-spec calls for a badge across the
 * fuller saved/synced/paused state set a `grading_record` row can actually
 * be in (GradingRecordStatus) — reused here via StatusBadge's `status`
 * prop (for icon/color) PLUS its `label` prop (to override the default
 * text) rather than widening StatusBadge's own type, same approach as
 * DataPreviewWeighbridgeView.vue's DETAIL_STATUS_BADGE_MAP.
 */
const DETAIL_STATUS_BADGE_MAP: Record<GradingRecord['status'], { status: BadgeStatus; label: string }> = {
  draft_ongoing: { status: 'ongoing', label: 'Sedang Berlangsung' },
  draft_paused: { status: 'paused', label: 'Dijeda' },
  saved: { status: 'none', label: 'Tersimpan' },
  synced: { status: 'none', label: 'Tersinkron' },
}

const detailBadgeInfo = computed(() => (detailRecord.value ? DETAIL_STATUS_BADGE_MAP[detailRecord.value.status] : null))

/* ---------------------------------------------------------------------- *
 * Shared — mode-driven loading, title, back navigation.
 * ---------------------------------------------------------------------- */

// Route-driven mode switch — runs on initial mount (immediate: true) AND
// on every subsequent id-param change within this same component instance
// (list → detail via a row tap, detail → list via Back). See this file's
// header comment.
watch(
  recordIdParam,
  (id) => {
    if (id) {
      loadDetail(id)
    } else {
      loadList()
    }
  },
  { immediate: true },
)

const screenTitle = computed(() => {
  if (!isDetailMode.value) {
    return 'Load Data'
  }

  if (detailLoading.value || detailNotFound.value || detailLoadErrorMessage.value) {
    return 'Data Preview'
  }

  return detailRecord.value?.grading_number ? `Grading ${detailRecord.value.grading_number}` : 'Detail Grading'
})

// business_logic step 4 — 'Back', mode-dependent target.
function onBack(): void {
  if (isDetailMode.value) {
    router.push({ name: 'data-preview-grading' })
    return
  }

  router.push({ name: 'monitor-grading' })
}

/* ---------------------------------------------------------------------- *
 * Header / breadcrumb / nav-menu — copied verbatim from
 * DataPreviewWeighbridgeView.vue / MonitorGradingView.vue.
 * ---------------------------------------------------------------------- */

const isNavMenuOpen = ref(false)

function toggleNavMenu(): void {
  isNavMenuOpen.value = !isNavMenuOpen.value
}

function closeNavMenu(): void {
  isNavMenuOpen.value = false
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

function goToStationList(): void {
  router.push({ name: 'station-list' })
}

function goToMonitorGrading(): void {
  router.push({ name: 'monitor-grading' })
}
</script>

<template>
  <main class="data-preview-grading-view">
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

    <div class="preview-header">
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
        <button
          type="button"
          class="breadcrumb-link"
          data-testid="breadcrumb-grading"
          @click="goToMonitorGrading"
        >
          Grading
        </button>
        <span class="breadcrumb-separator" aria-hidden="true">/</span>
        <span class="breadcrumb-current" aria-current="page">Load Data</span>
      </nav>
      <div class="title-row">
        <h1 class="screen-title">{{ screenTitle }}</h1>
        <StatusBadge v-if="isDetailMode && detailBadgeInfo" :status="detailBadgeInfo.status" :label="detailBadgeInfo.label" />
      </div>
    </div>

    <!-- LIST mode -->
    <template v-if="!isDetailMode">
      <div class="filter-row">
        <label class="filter-field">
          <span class="filter-label">Tanggal</span>
          <input
            v-model="dateFilter"
            type="date"
            class="filter-input"
            data-testid="date-filter-input"
          />
        </label>
        <label class="filter-field">
          <span class="filter-label">Cari</span>
          <input
            v-model="searchFilter"
            type="text"
            class="filter-input"
            placeholder="No. Grading / No. Polisi"
            data-testid="search-filter-input"
          />
        </label>
      </div>

      <p v-if="listLoading" class="status-text">Memuat daftar data grading lokal…</p>
      <p v-else-if="listError" class="status-text status-text--error" role="alert">{{ listError }}</p>

      <section v-else class="record-list-section" aria-label="Daftar Data Grading">
        <div v-if="filteredRecords.length === 0" class="empty-state" data-testid="record-list-empty">
          <p class="empty-state-text">
            {{ hasActiveFilter ? 'Tidak ada data yang cocok dengan filter.' : 'Belum ada data grading.' }}
          </p>
          <button
            v-if="hasActiveFilter"
            type="button"
            class="reset-filter-button"
            data-testid="reset-filter-button"
            @click="onResetFilter"
          >
            Reset Filter
          </button>
        </div>

        <ul v-else class="record-list" role="list" data-testid="record-list">
          <li v-for="item in filteredRecords" :key="item.id" role="listitem">
            <button
              type="button"
              class="record-item"
              :data-testid="`record-item-${item.id}`"
              @click="onItemClick(item)"
            >
              <span class="record-item-label">{{ recordLabel(item) }}</span>
              <StatusBadge :status="listBadgeInfo(item).status" :label="listBadgeInfo(item).label" />
            </button>
          </li>
        </ul>
      </section>
    </template>

    <!-- DETAIL mode -->
    <template v-else>
      <p v-if="detailLoading" class="status-text">Memuat data grading…</p>
      <p
        v-else-if="detailNotFound"
        class="status-text status-text--error"
        role="alert"
        data-testid="record-not-found"
      >
        Data grading tidak ditemukan.
      </p>
      <p v-else-if="detailLoadErrorMessage" class="status-text status-text--error" role="alert">
        {{ detailLoadErrorMessage }}
      </p>

      <div v-else-if="detailRecord" class="preview-body">
        <FormField :model-value="detailRecord.grading_number" label="No. Grading" disabled />
        <FormField :model-value="detailRecord.date" label="Tanggal" type="date" disabled />
        <FormField :model-value="detailRecord.license_plate_no" label="No. Polisi" disabled />
        <FormField :model-value="detailRecord.vehicle_code" label="Kode Kendaraan" disabled />
        <FormField :model-value="detailRecord.estate_supplier" label="Estate/Supplier" disabled />
        <FormField :model-value="detailRecord.division" label="Divisi" disabled />
        <FormField :model-value="detailRecord.netto" label="Netto" type="number" disabled />
        <FormField :model-value="detailRecord.quantity" label="Kuantitas" type="number" disabled />
        <FormField :model-value="detailRecord.note" label="Catatan" disabled />

        <!-- business_logic step 2 — grading_detail rows, read-only. Not
             GradingDetailGrid.vue (that component is edit-only and
             screen-011-specific — see its header comment) — a simple
             read-only list is enough for this screen's needs. Quality
             Parameter name resolved via parameterName() (see this file's
             header comment / implementation_notes), not hardcoded. -->
        <section class="detail-section" aria-label="Grading Detail">
          <h2 class="detail-title">Grading Detail</h2>

          <p v-if="detailRows.length === 0" class="status-text" data-testid="detail-rows-empty">
            Belum ada baris grading detail.
          </p>

          <ul v-else class="detail-list" data-testid="detail-rows-list">
            <li v-for="row in detailRows" :key="row.id" class="detail-row">
              <span class="detail-row-parameter">{{ parameterName(row) }}</span>
              <span class="detail-row-quantity">{{ row.quantity ?? '-' }}{{ row.uom ? ` ${row.uom}` : '' }}</span>
            </li>
          </ul>
        </section>
      </div>
    </template>

    <footer class="action-footer">
      <div class="action-row">
        <button type="button" class="action-button action-button--secondary" data-testid="back-button" @click="onBack">
          Back
        </button>
      </div>
    </footer>
  </main>
</template>

<style scoped>
.data-preview-grading-view {
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

.preview-header {
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

.title-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
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

.filter-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.filter-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.filter-label {
  font-size: 12px;
  font-weight: 500;
  color: #6b7280;
}

.filter-input {
  min-height: 44px;
  padding: 0 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background-color: #ffffff;
  color: #1f2937;
  font-size: 14px;
  font-family: inherit;
  box-sizing: border-box;
}

.record-list-section {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  margin: 0;
  padding: 16px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  text-align: center;
}

.empty-state-text {
  margin: 0;
  color: #6b7280;
  font-size: 14px;
}

.reset-filter-button {
  min-height: 44px;
  padding: 0 16px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background-color: #ffffff;
  color: #249360;
  font-size: 14px;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
}

.record-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.record-item {
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

.record-item-label {
  font-size: 14px;
  font-weight: 500;
  color: #1f2937;
}

.preview-body {
  display: flex;
  flex-direction: column;
  gap: 16px;
  flex: 1;
  overflow-y: auto;
}

.detail-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.detail-title {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: #1f2937;
}

.detail-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.detail-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 44px;
  padding: 8px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-sizing: border-box;
}

.detail-row-parameter {
  font-size: 14px;
  font-weight: 500;
  color: #1f2937;
}

.detail-row-quantity {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
}

.action-footer {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: auto;
  padding-top: 12px;
}

.action-row {
  display: grid;
  grid-template-columns: 1fr;
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
</style>
