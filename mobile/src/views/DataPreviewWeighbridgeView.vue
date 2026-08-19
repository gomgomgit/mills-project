<script setup lang="ts">
/**
 * DataPreviewWeighbridgeView — screen-013--data-preview-weighbridge /
 * usecase-013--data-preview-weighbridge (mounted at
 * /stations/weighbridge/preview/:id? — `:id` is OPTIONAL, see
 * router/index.ts's comment on this route; meta.public = false — requires
 * an authenticated session, enforced by the router's global auth guard).
 * Actors: operator, supervisor.
 *
 * Mobile-only screen — no backend API endpoint (api_contracts.endpoints is
 * empty). All data comes from the local (offline) `weighbridge_record`
 * table via weighbridgeRecordRepo.ts.
 *
 * Rewritten (2026-08-18, scope expansion): this screen now has TWO
 * route-driven modes, both served by this same view/route
 * (`data-preview-weighbridge`):
 *
 *  - LIST mode — route has NO `:id` param (the default/primary state,
 *    reached from MonitorWeighbridgeView.vue's 'Load Data' button). Shows
 *    every local `weighbridge_record` row for the current user, ANY
 *    status (business_logic step 1, via the new
 *    weighbridgeRecordRepo.getAllRecords() — unlike Monitor Weighbridge's
 *    getDrafts(), which only returns draft_ongoing/draft_paused), with a
 *    client-side (in-memory, no re-query per filter change) date filter +
 *    search filter. Tapping a row navigates onward:
 *      - draft_ongoing/draft_paused rows → `weighbridge-form` (continue
 *        editing, same as Monitor Weighbridge's own row-tap behavior) —
 *        does NOT switch this screen into detail mode.
 *      - saved/synced rows → THIS screen's own route, WITH the row's id
 *        added as the `:id` param (a real router.push, not just local
 *        state), transitioning into DETAIL mode.
 *
 *  - DETAIL mode — route HAS a `:id` param. Read-only single-record view,
 *    unchanged from this screen's original implementation: reuses
 *    `getDraftById()` (already exported by weighbridgeRecordRepo.ts for
 *    screen-010--form-weighbridge) — a second identical read-accessor
 *    would just duplicate it — renders every field disabled via
 *    FormField.vue, or "Data timbangan tidak ditemukan." if not found.
 *
 * Because the mode is route-driven (not a one-time `onMounted` read of
 * `route.params.id`), a `watch()` on the id param (immediate: true) drives
 * both the initial load AND every list⇄detail transition within the same
 * component instance — Vue Router reuses this instance for param-only
 * navigations on the same route record (list → detail via a row tap,
 * detail → list via Back), so a plain `onMounted` read alone would not
 * re-run on those transitions.
 *
 * Header/breadcrumb/nav-menu: copied verbatim from
 * MonitorWeighbridgeView.vue's (screen-007) isNavMenuOpen/toggleNavMenu/
 * closeNavMenu/goToChangePassword/onLogout + brand/hamburger/nav-menu
 * markup, for visual/behavioral consistency. Breadcrumb is 4 segments deep
 * (Home > Production Process Activity > Weighbridge > Load Data, the
 * first three tappable, 'Load Data' the current page as plain text) since
 * this screen sits one level below Monitor Weighbridge — same in both
 * modes.
 *
 * List-row status badge (StatusBadge.vue, `status` + `label` override,
 * same pattern as this screen's existing detail-mode badge / Monitor
 * Weighbridge's draft-list badge — see StatusBadge.vue's own doc comment
 * on why `status`/`label` are used together rather than widening
 * StatusBadge's 3-state type):
 *  - draft_ongoing → status="paused" label="Pause" (matches Monitor
 *    Weighbridge's list exactly — no ongoing-vs-paused visual
 *    distinction there, reused identically here).
 *  - draft_paused  → status="paused" label="Pause" (same as above).
 *  - saved         → status="none" label="Tersimpan"
 *  - synced        → status="none" label="Tersinkron"
 * Detail mode's own badge mapping (draft_ongoing/draft_paused DO get a
 * distinct ongoing/paused visual there) is unchanged from this screen's
 * original implementation — see DETAIL_STATUS_BADGE_MAP below.
 *
 * Date filter: matches business_logic requirement against
 * `record_datetime`'s date portion (the single unified WeighbridgeRecord
 * date/time field — entity-catalog v5 merged the previous
 * `arrival_datetime`/`dispatch_datetime` pair into this one field,
 * confirmed against weighbridgeRecordRepo.ts's WeighbridgeRecord shape).
 * The date filter `<input type="date">`'s value (`YYYY-MM-DD`) is compared
 * against the first 10 characters of `record_datetime` (an ISO-ish
 * datetime-local string, per FormWeighbridgeView.vue's
 * `type="datetime-local"` field — see this screen's own original
 * detail-mode rendering below), so no separate date-parsing library is
 * needed.
 *
 * Update (entity-catalog v5, Weighbridge receive/dispatch): detail mode now
 * renders `weighbridge_type` (Receive/Dispatch), a single `record_datetime`
 * field labeled 'Tanggal & Waktu Arrival' or 'Tanggal & Waktu Dispatch'
 * depending on `weighbridge_type` (mirrors FormWeighbridgeView.vue's same
 * type-dependent label), and `destination` (Tujuan Muatan) — rendered ONLY
 * when `weighbridge_type === 'dispatch'`, per tech spec v4 business_logic
 * step 8. Quantity's label now shows its unit ('(tandan)') for clarity —
 * static label text only, not a data/type change.
 *
 * known_issues:
 *  - None — MonitorWeighbridgeView.vue's 'Load Data' button already calls
 *    `router.push({ name: 'data-preview-weighbridge' })` with no id
 *    param, which now correctly lands on this screen's LIST mode (the
 *    previous "always renders not-found" known_issue no longer applies).
 */
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useFloatingClockStore } from '@/stores/floatingClock'
import weighbridgeRecordRepo, { type WeighbridgeRecord } from '@/services/weighbridgeRecordRepo'
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
const allRecords = ref<WeighbridgeRecord[]>([])
const dateFilter = ref('')
const searchFilter = ref('')

// business_logic step 1 (list mode) — load every local weighbridge_record
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
    allRecords.value = await weighbridgeRecordRepo.getAllRecords(userId)
  } catch (err) {
    listError.value = err instanceof Error ? err.message : 'Gagal memuat daftar data timbangan lokal.'
  } finally {
    listLoading.value = false
  }
}

// business_logic step 2 — WB Card number fallback, identical to Monitor
// Weighbridge's draftLabel().
function recordLabel(item: WeighbridgeRecord): string {
  return item.wb_card_number?.trim() ? item.wb_card_number : 'WB Card belum diisi'
}

const LIST_STATUS_BADGE_MAP: Record<WeighbridgeRecord['status'], { status: BadgeStatus; label: string }> = {
  draft_ongoing: { status: 'paused', label: 'Pause' },
  draft_paused: { status: 'paused', label: 'Pause' },
  saved: { status: 'none', label: 'Tersimpan' },
  synced: { status: 'none', label: 'Tersinkron' },
}

function listBadgeInfo(item: WeighbridgeRecord) {
  return LIST_STATUS_BADGE_MAP[item.status]
}

// business_logic steps 6-7 — client-side date + search filters, combined
// with AND logic, applied over the already-fetched `allRecords` array.
const filteredRecords = computed(() => {
  const date = dateFilter.value
  const keyword = searchFilter.value.trim().toLowerCase()

  return allRecords.value.filter((item) => {
    if (date) {
      const recordDate = item.record_datetime ? item.record_datetime.slice(0, 10) : ''
      if (recordDate !== date) {
        return false
      }
    }

    if (keyword) {
      const cardMatch = (item.wb_card_number ?? '').toLowerCase().includes(keyword)
      const driverMatch = (item.driver_name ?? '').toLowerCase().includes(keyword)
      if (!cardMatch && !driverMatch) {
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

// business_logic steps 4-5 — tapping a list row.
function onItemClick(item: WeighbridgeRecord): void {
  if (item.status === 'draft_ongoing' || item.status === 'draft_paused') {
    router.push({ name: 'weighbridge-form', params: { id: item.id } })
    return
  }

  router.push({ name: 'data-preview-weighbridge', params: { id: item.id } })
}

/* ---------------------------------------------------------------------- *
 * DETAIL mode — unchanged from this screen's original implementation.
 * ---------------------------------------------------------------------- */

const detailLoading = ref(true)
const detailNotFound = ref(false)
const detailLoadErrorMessage = ref<string | null>(null)
const detailRecord = ref<WeighbridgeRecord | null>(null)

// business_logic step 1 (detail mode) — load the record read-only by id
// via getDraftById() (see this file's header comment).
async function loadDetail(recordId: string): Promise<void> {
  detailLoading.value = true
  detailLoadErrorMessage.value = null
  detailNotFound.value = false
  detailRecord.value = null

  try {
    const found = await weighbridgeRecordRepo.getDraftById(recordId)

    if (!found) {
      detailNotFound.value = true
      return
    }

    detailRecord.value = found
  } catch (err) {
    detailLoadErrorMessage.value = err instanceof Error ? err.message : 'Gagal memuat data timbangan.'
  } finally {
    detailLoading.value = false
  }
}

/**
 * StatusBadge.vue's `status` prop only models the 3-state
 * none/ongoing/paused set it was originally built for (screen-005's
 * per-station badge). This screen's uiux-spec calls for a badge across the
 * fuller saved/synced/paused state set a `weighbridge_record` row can
 * actually be in (WeighbridgeRecordStatus) — reused here via StatusBadge's
 * `status` prop (for icon/color) PLUS its `label` prop (to override the
 * default text) rather than widening StatusBadge's own type, so the
 * shared component stays generic for its other callers.
 */
const DETAIL_STATUS_BADGE_MAP: Record<WeighbridgeRecord['status'], { status: BadgeStatus; label: string }> = {
  draft_ongoing: { status: 'ongoing', label: 'Sedang Berlangsung' },
  draft_paused: { status: 'paused', label: 'Dijeda' },
  saved: { status: 'none', label: 'Tersimpan' },
  synced: { status: 'none', label: 'Tersinkron' },
}

const detailBadgeInfo = computed(() => (detailRecord.value ? DETAIL_STATUS_BADGE_MAP[detailRecord.value.status] : null))

// entity-catalog v5 — weighbridge_type-dependent detail labels, mirroring
// FormWeighbridgeView.vue's own Receive/Dispatch label logic. Falls back to
// the 'receive' label when weighbridge_type is null (legacy/unset rows).
const detailTypeLabel = computed(() => (detailRecord.value?.weighbridge_type === 'dispatch' ? 'Dispatch' : 'Receive'))
const detailDatetimeLabel = computed(() =>
  detailRecord.value?.weighbridge_type === 'dispatch' ? 'Tanggal & Waktu Dispatch' : 'Tanggal & Waktu Arrival',
)
const isDetailDispatch = computed(() => detailRecord.value?.weighbridge_type === 'dispatch')

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

  return detailRecord.value?.wb_card_number ? `WB Card ${detailRecord.value.wb_card_number}` : 'Detail Timbangan'
})

// business_logic steps 9-10 — 'Back', mode-dependent target.
function onBack(): void {
  if (isDetailMode.value) {
    router.push({ name: 'data-preview-weighbridge' })
    return
  }

  router.push({ name: 'monitor-weighbridge' })
}

/* ---------------------------------------------------------------------- *
 * Header / breadcrumb / nav-menu — copied verbatim from
 * MonitorWeighbridgeView.vue.
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

function goToMonitorWeighbridge(): void {
  router.push({ name: 'monitor-weighbridge' })
}
</script>

<template>
  <main class="data-preview-weighbridge-view">
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
          data-testid="breadcrumb-weighbridge"
          @click="goToMonitorWeighbridge"
        >
          Weighbridge
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
            placeholder="No. WB Card / Nama Sopir"
            data-testid="search-filter-input"
          />
        </label>
      </div>

      <p v-if="listLoading" class="status-text">Memuat daftar data timbangan lokal…</p>
      <p v-else-if="listError" class="status-text status-text--error" role="alert">{{ listError }}</p>

      <section v-else class="record-list-section" aria-label="Daftar Data Timbangan">
        <div v-if="filteredRecords.length === 0" class="empty-state" data-testid="record-list-empty">
          <p class="empty-state-text">
            {{ hasActiveFilter ? 'Tidak ada data yang cocok dengan filter.' : 'Belum ada data timbangan.' }}
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
      <p v-if="detailLoading" class="status-text">Memuat data timbangan…</p>
      <p
        v-else-if="detailNotFound"
        class="status-text status-text--error"
        role="alert"
        data-testid="record-not-found"
      >
        Data timbangan tidak ditemukan.
      </p>
      <p v-else-if="detailLoadErrorMessage" class="status-text status-text--error" role="alert">
        {{ detailLoadErrorMessage }}
      </p>

      <div v-else-if="detailRecord" class="preview-body">
        <FormField :model-value="detailTypeLabel" label="Tipe Weighbridge" disabled data-testid="detail-weighbridge-type" />
        <FormField :model-value="detailRecord.wb_card_number" label="No. WB Card" disabled />
        <FormField
          :model-value="detailRecord.record_datetime"
          :label="detailDatetimeLabel"
          type="datetime-local"
          disabled
          data-testid="detail-record-datetime"
        />
        <FormField :model-value="detailRecord.vehicle_number" label="No. Kendaraan" disabled />
        <FormField :model-value="detailRecord.driver_name" label="Nama Sopir" disabled />
        <FormField :model-value="detailRecord.estate_supplier" label="Estate/Supplier" disabled />
        <FormField
          v-if="isDetailDispatch"
          :model-value="detailRecord.destination"
          label="Tujuan Muatan"
          disabled
          data-testid="detail-destination"
        />
        <FormField :model-value="detailRecord.division" label="Divisi" disabled />
        <FormField :model-value="detailRecord.block" label="Blok" disabled />
        <FormField :model-value="detailRecord.gross_weight" label="Berat Kotor (Gross Weight)" type="number" disabled />
        <FormField :model-value="detailRecord.tare_weight" label="Berat Kosong (Tare Weight)" type="number" disabled />
        <FormField :model-value="detailRecord.net_weight" label="Berat Bersih (Net Weight)" type="number" disabled />
        <FormField :model-value="detailRecord.quantity" label="Kuantitas (tandan)" type="number" disabled />
        <FormField :model-value="detailRecord.checked_by" label="Checked By" disabled />
        <FormField :model-value="detailRecord.acknowledged_by" label="Acknowledged By" disabled />
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
.data-preview-weighbridge-view {
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
