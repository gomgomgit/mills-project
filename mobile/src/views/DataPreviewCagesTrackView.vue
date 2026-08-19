<script setup lang="ts">
/**
 * DataPreviewCagesTrackView — screen-015--data-preview-cages-track /
 * usecase-015--data-preview-cages-track (mounted at
 * /stations/cages-track/preview/:id? — `:id` is OPTIONAL, see
 * router/index.ts's comment on this route; meta.public = false — requires
 * an authenticated session, enforced by the router's global auth guard).
 * Actors: operator, supervisor.
 *
 * Mobile-only screen — no backend API endpoint (api_contracts.endpoints is
 * empty). All data comes from the local (offline) `cages_track_record` +
 * `cages_tipped_time` tables via cagesTrackRecordRepo.ts.
 *
 * FULL REWRITE (2026-08-19, scope expansion — mirrors
 * DataPreviewGradingView.vue's (screen-014) / DataPreviewWeighbridgeView.vue's
 * (screen-013) own dual-mode rewrites): this screen now has TWO
 * route-driven modes, both served by this same view/route
 * (`data-preview-cages-track`):
 *
 *  - LIST mode — route has NO `:id` param (the default/primary state,
 *    reached from MonitorCagesTrackView.vue's 'Load Data' button). Shows
 *    every local `cages_track_record` row for the current user, ANY
 *    status (business_logic step 1, via the new
 *    cagesTrackRecordRepo.getAllRecords() — unlike Monitor Cages Track's
 *    getDrafts(), which only returns draft_ongoing/draft_paused), with a
 *    client-side (in-memory, no re-query per filter change) date filter +
 *    search filter. Tapping a row navigates onward:
 *      - draft_ongoing/draft_paused rows → `cages-track-form` (continue
 *        editing, same as Monitor Cages Track's own row-tap behavior) —
 *        does NOT switch this screen into detail mode.
 *      - saved/synced rows → THIS screen's own route, WITH the row's id
 *        added as the `:id` param (a real router.push, not just local
 *        state), transitioning into DETAIL mode.
 *
 *  - DETAIL mode — route HAS a `:id` param. Read-only single-record view
 *    (full header + cages_tipped_time grid), reusing
 *    `getDraftWithTippedTimes()` (already exported by
 *    cagesTrackRecordRepo.ts for screen-012--form-cages-track — a second
 *    identical read accessor would just duplicate it). Unlike
 *    DataPreviewWeighbridgeView.vue / DataPreviewGradingView.vue (neither
 *    of which has a Checked By/Acknowledged By section at all — see
 *    gradingRecordRepo.ts's own header comment), this screen DOES render
 *    Checked By and Acknowledged By read-only — no role restriction on
 *    VIEWING them here, only on EDITING them on Form Cages Track. "Cages
 *    Tipped Time" rows are rendered read-only straight from their stored
 *    columns (`tipped_hour`/`checked_cage_numbers`/`total_cages`/
 *    `cages_remain`) — no recomputation, this is historical data.
 *
 * Because the mode is route-driven (not a one-time `onMounted` read of
 * `route.params.id`), a `watch()` on the id param (immediate: true) drives
 * both the initial load AND every list⇄detail transition within the same
 * component instance — Vue Router reuses this instance for param-only
 * navigations on the same route record (list → detail via a row tap,
 * detail → list via Back), so a plain `onMounted` read alone would not
 * re-run on those transitions. Mirrors DataPreviewGradingView.vue's own
 * watch()-driven mode switch exactly.
 *
 * Header/breadcrumb/nav-menu: copied verbatim from
 * DataPreviewGradingView.vue's (itself copied from
 * MonitorCagesTrackView.vue's) isNavMenuOpen/toggleNavMenu/closeNavMenu/
 * goToChangePassword/onLogout + brand/hamburger/nav-menu markup, for
 * visual/behavioral consistency. Breadcrumb is 4 segments deep (Home >
 * Production Process Activity > Cages Track > Load Data, the first three
 * tappable, 'Load Data' the current page as plain text) since this screen
 * sits one level below Monitor Cages Track — same in both modes.
 *
 * List-row status badge (StatusBadge.vue, `status` + `label` override,
 * same pattern as DataPreviewGradingView.vue's list badge / this screen's
 * own detail-mode badge below):
 *  - draft_ongoing → status="paused" label="Pause" (matches Monitor
 *    Cages Track's list exactly — no ongoing-vs-paused visual
 *    distinction there, reused identically here).
 *  - draft_paused  → status="paused" label="Pause" (same as above).
 *  - saved         → status="none" label="Tersimpan"
 *  - synced        → status="none" label="Tersinkron"
 * Detail mode's own badge mapping (draft_ongoing/draft_paused DO get a
 * distinct ongoing/paused visual there) is unchanged from this screen's
 * original implementation — see DETAIL_STATUS_BADGE_MAP below.
 *
 * Date filter: `cages_track_record.date` (per localSchema.ts's
 * CREATE_CAGES_TRACK_RECORD) is a full local datetime string
 * (`YYYY-MM-DDTHH:mm:ss`, set by FormCagesTrackView.vue's
 * `nowLocalDateTimeString()`), NOT a plain `YYYY-MM-DD` column like
 * `grading_record.date` — so this filter compares against
 * `item.date.slice(0, 10)`, same approach as
 * DataPreviewWeighbridgeView.vue's `arrival_datetime` date filter (a
 * full datetime column of the same shape), NOT
 * DataPreviewGradingView.vue's exact-match `date` filter (a plain-date
 * column). The `<input type="date">`'s value (`YYYY-MM-DD`) is compared
 * against that 10-character slice.
 *
 * Search filter: case-insensitive substring match against
 * `cages_track_number` only (this screen's tech spec field — unlike
 * DataPreviewGradingView.vue's two-field grading_number/license_plate_no
 * search, cages_track_record has no second free-text identifier column
 * in scope here).
 *
 * implementation_notes:
 *  - "Inputted By" (business_logic step 5) has no dedicated
 *    `cages_track_record` column of its own — `created_by` is a user id,
 *    not a display name, and this app has no local user-name lookup
 *    table (see stationRepo.ts's read-only reference-cache comment — no
 *    equivalent exists for users). DETAIL mode is only ever reached via
 *    THIS screen's own `getAllRecords(userId)`-scoped LIST (i.e. always
 *    the current user's own record), so "Inputted By" is rendered as
 *    `authStore.currentUser?.name` — same display source
 *    FormCagesTrackView.vue's own "Inputted By" field already uses (see
 *    that file's header comment), not a new/duplicate name-resolution
 *    mechanism.
 *  - `checked_cage_numbers` (a comma-separated TEXT column, e.g. "1,3,5")
 *    is parsed client-side into a readable "Cage 1, Cage 3, Cage 5" list
 *    by `checkedCagesDisplay()` below — mirrors the "Cage {{ n }}"
 *    checkbox label text FormCagesTrackView.vue already uses for the same
 *    column's live-editing counterpart, so the two screens read this data
 *    identically. `tipped_hour` is formatted as `HH:00` via `hourLabel()`,
 *    copied verbatim from FormCagesTrackView.vue's own helper of the same
 *    name/behavior. `total_cages`/`cages_remain` are rendered exactly as
 *    stored — this screen never recomputes either (historical data, see
 *    this file's business_logic comment above).
 *  - `uiux_spec` was consulted for design tokens/component patterns where
 *    applicable (StatusBadge.vue/FormField.vue reuse, spacing/typography
 *    mirrored from DataPreviewGradingView.vue's already-approved markup);
 *    no new visual pattern was introduced here.
 *
 * known_issues:
 *  - MonitorCagesTrackView.vue's 'Load Data' button already calls
 *    `router.push({ name: 'data-preview-cages-track' })` with no id
 *    param, which correctly lands on this screen's LIST mode (matches
 *    DataPreviewGradingView.vue's / DataPreviewWeighbridgeView.vue's own
 *    equivalent resolution — MonitorCagesTrackView.vue is out of this
 *    screen's implementation_plan scope and was not modified here).
 */
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import cagesTrackRecordRepo, {
  type CagesTippedTimeRow,
  type CagesTrackRecord,
} from '@/services/cagesTrackRecordRepo'
import StatusBadge, { type BadgeStatus } from '@/components/StatusBadge.vue'
import FormField from '@/components/FormField.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

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
const allRecords = ref<CagesTrackRecord[]>([])
const dateFilter = ref('')
const searchFilter = ref('')

// business_logic step 1 (list mode) — load every local cages_track_record
// for the current user, any status, most-recently-updated first. Fetched
// once per list-mode entry; date/search filters below are purely
// client-side over this array (never re-queried per filter change).
// Defaults the date filter to today's local date (YYYY-MM-DD, matching
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
    allRecords.value = await cagesTrackRecordRepo.getAllRecords(userId)
  } catch (err) {
    listError.value = err instanceof Error ? err.message : 'Gagal memuat daftar data cages track lokal.'
  } finally {
    listLoading.value = false
  }
}

// business_logic step 2 — Cages Track Number fallback, mirrors
// DataPreviewGradingView.vue's recordLabel().
function recordLabel(item: CagesTrackRecord): string {
  return item.cages_track_number?.trim() ? item.cages_track_number : 'No. Cages Track belum diisi'
}

const LIST_STATUS_BADGE_MAP: Record<CagesTrackRecord['status'], { status: BadgeStatus; label: string }> = {
  draft_ongoing: { status: 'paused', label: 'Pause' },
  draft_paused: { status: 'paused', label: 'Pause' },
  saved: { status: 'none', label: 'Tersimpan' },
  synced: { status: 'none', label: 'Tersinkron' },
}

function listBadgeInfo(item: CagesTrackRecord) {
  return LIST_STATUS_BADGE_MAP[item.status]
}

// business_logic steps 6-7 (list mode) — client-side date + search
// filters, combined with AND logic, applied over the already-fetched
// `allRecords` array. `date` is a full local datetime string (see this
// file's header comment) — compared via its first 10 characters, same
// approach as DataPreviewWeighbridgeView.vue's arrival_datetime filter.
const filteredRecords = computed(() => {
  const date = dateFilter.value
  const keyword = searchFilter.value.trim().toLowerCase()

  return allRecords.value.filter((item) => {
    if (date) {
      const recordDate = item.date ? item.date.slice(0, 10) : ''
      if (recordDate !== date) {
        return false
      }
    }

    if (keyword) {
      const cagesTrackNumberMatch = (item.cages_track_number ?? '').toLowerCase().includes(keyword)
      if (!cagesTrackNumberMatch) {
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

// business_logic steps 4-5 (list mode) — tapping a list row.
function onItemClick(item: CagesTrackRecord): void {
  if (item.status === 'draft_ongoing' || item.status === 'draft_paused') {
    router.push({ name: 'cages-track-form', params: { id: item.id } })
    return
  }

  router.push({ name: 'data-preview-cages-track', params: { id: item.id } })
}

/* ---------------------------------------------------------------------- *
 * DETAIL mode
 * ---------------------------------------------------------------------- */

const detailLoading = ref(true)
const detailNotFound = ref(false)
const detailLoadErrorMessage = ref<string | null>(null)
const detailRecord = ref<CagesTrackRecord | null>(null)
const detailRows = ref<CagesTippedTimeRow[]>([])

// business_logic step 8 (detail mode) — load the record + its
// cages_tipped_time rows read-only by id, via getDraftWithTippedTimes()
// (see this file's header comment) — reused unchanged from
// screen-012--form-cages-track, not a new/duplicate read accessor.
async function loadDetail(recordId: string): Promise<void> {
  detailLoading.value = true
  detailLoadErrorMessage.value = null
  detailNotFound.value = false
  detailRecord.value = null
  detailRows.value = []

  try {
    const draft = await cagesTrackRecordRepo.getDraftWithTippedTimes(recordId)

    if (!draft) {
      detailNotFound.value = true
      return
    }

    detailRecord.value = draft.record
    detailRows.value = draft.tippedTimes
  } catch (err) {
    detailLoadErrorMessage.value = err instanceof Error ? err.message : 'Gagal memuat data cages track.'
  } finally {
    detailLoading.value = false
  }
}

// business_logic step 5 — Cages Tipped Time row display helpers.
// `hourLabel()` copied verbatim from FormCagesTrackView.vue's own helper
// of the same name/behavior (HH:00, whole hours only).
function hourLabel(hour: number | null): string {
  if (hour === null || hour === undefined) {
    return '-'
  }
  return `${String(hour).padStart(2, '0')}:00`
}

// Parses the stored comma-separated `checked_cage_numbers` column (e.g.
// "1,3,5") into a readable "Cage 1, Cage 3, Cage 5" list — mirrors the
// "Cage {{ n }}" checkbox label text FormCagesTrackView.vue already uses
// for the same column's live-editing counterpart (see this file's
// implementation_notes).
function checkedCagesDisplay(row: CagesTippedTimeRow): string {
  const raw = row.checked_cage_numbers ?? ''
  const numbers = raw
    .split(',')
    .map((value) => value.trim())
    .filter((value) => value !== '')

  if (numbers.length === 0) {
    return '-'
  }

  return numbers.map((number) => `Cage ${number}`).join(', ')
}

// Inputted By — see this file's implementation_notes for why this reads
// from authStore rather than a `cages_track_record` column.
const inputtedByDisplay = computed(() => authStore.currentUser?.name ?? '-')

/**
 * StatusBadge.vue's `status` prop only models the 3-state
 * none/ongoing/paused set it was originally built for (screen-005's
 * per-station badge). This screen's uiux-spec calls for a badge across
 * the fuller saved/synced/paused state set a `cages_track_record` row can
 * actually be in (CagesTrackRecordStatus) — reused here via StatusBadge's
 * `status` prop (for icon/color) PLUS its `label` prop (to override the
 * default text) rather than widening StatusBadge's own type, same
 * approach as DataPreviewGradingView.vue's DETAIL_STATUS_BADGE_MAP.
 */
const DETAIL_STATUS_BADGE_MAP: Record<CagesTrackRecord['status'], { status: BadgeStatus; label: string }> = {
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

  return detailRecord.value?.cages_track_number ? `Cages Track ${detailRecord.value.cages_track_number}` : 'Detail Cages Track'
})

// business_logic steps 9-10 — 'Back', mode-dependent target.
function onBack(): void {
  if (isDetailMode.value) {
    router.push({ name: 'data-preview-cages-track' })
    return
  }

  router.push({ name: 'monitor-cages-track' })
}

/* ---------------------------------------------------------------------- *
 * Header / breadcrumb / nav-menu — copied verbatim from
 * DataPreviewGradingView.vue / MonitorCagesTrackView.vue.
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

function goToMonitorCagesTrack(): void {
  router.push({ name: 'monitor-cages-track' })
}
</script>

<template>
  <main class="data-preview-cages-track-view">
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
          data-testid="breadcrumb-cages-track"
          @click="goToMonitorCagesTrack"
        >
          Cages Track
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
            placeholder="No. Cages Track"
            data-testid="search-filter-input"
          />
        </label>
      </div>

      <p v-if="listLoading" class="status-text">Memuat daftar data cages track lokal…</p>
      <p v-else-if="listError" class="status-text status-text--error" role="alert">{{ listError }}</p>

      <section v-else class="record-list-section" aria-label="Daftar Data Cages Track">
        <div v-if="filteredRecords.length === 0" class="empty-state" data-testid="record-list-empty">
          <p class="empty-state-text">
            {{ hasActiveFilter ? 'Tidak ada data yang cocok dengan filter.' : 'Belum ada data cages track.' }}
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
      <p v-if="detailLoading" class="status-text">Memuat data cages track…</p>
      <p
        v-else-if="detailNotFound"
        class="status-text status-text--error"
        role="alert"
        data-testid="record-not-found"
      >
        Data cages track tidak ditemukan.
      </p>
      <p v-else-if="detailLoadErrorMessage" class="status-text status-text--error" role="alert">
        {{ detailLoadErrorMessage }}
      </p>

      <div v-else-if="detailRecord" class="preview-body">
        <FormField :model-value="detailRecord.cages_track_number" label="No. Cages Track" disabled />
        <FormField :model-value="detailRecord.date" label="Tanggal" type="datetime-local" disabled />
        <FormField :model-value="detailRecord.tippler_start_time" label="Tippler Start Time" type="datetime-local" disabled />
        <FormField :model-value="detailRecord.tippler_stop_time" label="Tippler Stop Time" type="datetime-local" disabled />
        <FormField :model-value="detailRecord.cages_out" label="Cages Out" type="number" disabled />
        <FormField :model-value="detailRecord.cages_tipped" label="Cages Tipped" type="number" disabled />
        <FormField :model-value="inputtedByDisplay" label="Inputted By" disabled />
        <FormField :model-value="detailRecord.checked_by" label="Checked By" disabled />
        <FormField :model-value="detailRecord.acknowledged_by" label="Acknowledged By" disabled />
        <FormField :model-value="detailRecord.note" label="Catatan" disabled />

        <!-- business_logic step 5 — cages_tipped_time rows, read-only.
             Each row shows its stored Time (HH:00), the checked cage
             numbers parsed into a readable list, Total Cages, and Cages
             Remain — all read directly from stored columns, no
             recomputation (historical data). See this file's header
             comment / implementation_notes. -->
        <section class="tipped-time-section" aria-label="Riwayat Waktu Tip Cages">
          <h2 class="tipped-time-title">Cages Tipped Time</h2>

          <p v-if="detailRows.length === 0" class="status-text" data-testid="tipped-time-rows-empty">
            Belum ada baris cages tipped time.
          </p>

          <ul v-else class="tipped-time-list" data-testid="tipped-time-rows-list">
            <li v-for="row in detailRows" :key="row.id" class="tipped-time-row" :data-testid="`tipped-time-row-${row.id}`">
              <div class="tipped-time-row-main">
                <span class="tipped-time-row-hour">{{ hourLabel(row.tipped_hour) }}</span>
                <span class="tipped-time-row-cages">{{ checkedCagesDisplay(row) }}</span>
              </div>
              <div class="tipped-time-row-summary">
                <span>Total Cages: {{ row.total_cages ?? '-' }}</span>
                <span>Cages Remain: {{ row.cages_remain ?? '-' }}</span>
              </div>
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
.data-preview-cages-track-view {
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

.tipped-time-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.tipped-time-title {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: #1f2937;
}

.tipped-time-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.tipped-time-row {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-sizing: border-box;
}

.tipped-time-row-main {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.tipped-time-row-hour {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
  flex-shrink: 0;
}

.tipped-time-row-cages {
  font-size: 14px;
  color: #1f2937;
  text-align: right;
}

.tipped-time-row-summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  font-size: 13px;
  color: #6b7280;
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
