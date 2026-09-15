<script setup lang="ts">
/**
 * DataPreviewProcessWaterView — screen-082--data-preview-process-water /
 * usecase-069--data-preview-process-water (mounted at
 * /stations/process-water/preview/:id? — `:id` is OPTIONAL; meta.public =
 * false — requires an authenticated session, enforced by the router's
 * global auth guard). Actors: operator, supervisor.
 *
 * Mobile-only screen — no backend API endpoint. All data comes from the
 * local (offline) `process_water_record` + `process_water_detail` tables
 * via processWaterRecordRepo.ts.
 *
 * Dual-mode, mirroring DataPreviewThreshingView.vue exactly:
 *
 *  - LIST mode — route has NO `:id` param. Shows every local
 *    process_water_record row for the current user, ANY status, with a
 *    client-side date filter (defaults to today) + search filter (on
 *    process_water_id). Tapping a row navigates onward:
 *      - draft_ongoing/draft_paused rows -> `process-water-form`.
 *      - saved/synced rows -> THIS screen's own route, WITH the row's id
 *        added as the `:id` param, transitioning into DETAIL mode.
 *
 *  - DETAIL mode — route HAS a `:id` param. Read-only single-record view
 *    (full header + Process Water Detail grid, however many rows the
 *    user added), reusing `getDraftWithDetails()` (already exported by
 *    processWaterRecordRepo.ts for screen-072). Renders Checked By and
 *    Acknowledged By read-only — no role restriction on VIEWING them
 *    here. Detail rows are rendered straight from their stored columns —
 *    no recomputation, this is historical data. Handles zero rows
 *    gracefully (an empty-state message,
 *    `process-water-detail-rows-empty`).
 *
 * UNLIKE DataPreviewThreshingView.vue: this station has NO
 * operational-target reference table — no section for it in detail mode.
 *
 * Route-driven mode (not a one-time onMounted read) via a `watch()` on the
 * id param (immediate: true) — same reasoning as
 * DataPreviewThreshingView.vue's header comment (Vue Router reuses this
 * component instance for param-only navigations on the same route
 * record).
 *
 * Header/breadcrumb/nav-menu: copied verbatim from
 * DataPreviewThreshingView.vue. Breadcrumb is 4 segments deep (Home >
 * Production Process Activity > Process Water > Load Data).
 *
 * List-row status badge (StatusBadge.vue):
 *  - draft_ongoing / draft_paused -> status="paused" label="Pause" (matches
 *    Monitor Process Water's list exactly).
 *  - saved  -> status="none" label="Tersimpan"
 *  - synced -> status="none" label="Tersinkron"
 * Detail mode's own badge mapping DOES distinguish ongoing/paused visually
 * (same as DataPreviewThreshingView.vue's DETAIL_STATUS_BADGE_MAP).
 *
 * Date filter: `process_water_record.date` is a full local datetime
 * string (`YYYY-MM-DDTHH:mm:ss`, set by FormProcessWaterView.vue's
 * `nowLocalDateTimeString()`), so this filter compares against
 * `item.date.slice(0, 10)`.
 *
 * Search filter: case-insensitive substring match against
 * `process_water_id`.
 */
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useFloatingClockStore } from '@/stores/floatingClock'
import { useAiAssistantStore } from '@/stores/aiAssistant'
import processWaterRecordRepo, {
  type ProcessWaterDetailRow,
  type ProcessWaterRecord,
} from '@/services/processWaterRecordRepo'
import StatusBadge, { type BadgeStatus } from '@/components/StatusBadge.vue'
import FormField from '@/components/FormField.vue'
import RecordVerificationActions from '@/components/RecordVerificationActions.vue'
import RecordVerificationStatus from '@/components/RecordVerificationStatus.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const floatingClockStore = useFloatingClockStore()
const aiAssistantStore = useAiAssistantStore()

function currentUserId(): string | null {
  return authStore.currentUser?.id ?? null
}

// Route-driven mode.
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
const allRecords = ref<ProcessWaterRecord[]>([])
const dateFilter = ref('')
const searchFilter = ref('')

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
    allRecords.value = await processWaterRecordRepo.getAllRecords(userId)
  } catch (err) {
    listError.value = err instanceof Error ? err.message : 'Gagal memuat daftar data process water lokal.'
  } finally {
    listLoading.value = false
  }
}

function recordLabel(item: ProcessWaterRecord): string {
  return item.process_water_id?.trim() ? item.process_water_id : 'Process Water ID belum diisi'
}

const LIST_STATUS_BADGE_MAP: Record<ProcessWaterRecord['status'], { status: BadgeStatus; label: string }> = {
  draft_ongoing: { status: 'paused', label: 'Pause' },
  draft_paused: { status: 'paused', label: 'Pause' },
  saved: { status: 'none', label: 'Tersimpan' },
  synced: { status: 'none', label: 'Tersinkron' },
}

function listBadgeInfo(item: ProcessWaterRecord) {
  return LIST_STATUS_BADGE_MAP[item.status]
}

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
      const processWaterIdMatch = (item.process_water_id ?? '').toLowerCase().includes(keyword)
      if (!processWaterIdMatch) {
        return false
      }
    }

    return true
  })
})

const hasActiveFilter = computed(() => dateFilter.value !== '' || searchFilter.value !== '')

function onResetFilter(): void {
  dateFilter.value = ''
  searchFilter.value = ''
}

function onItemClick(item: ProcessWaterRecord): void {
  if (item.status === 'draft_ongoing' || item.status === 'draft_paused') {
    router.push({ name: 'process-water-form', params: { id: item.id } })
    return
  }

  router.push({ name: 'data-preview-process-water', params: { id: item.id } })
}

/* ---------------------------------------------------------------------- *
 * DETAIL mode
 * ---------------------------------------------------------------------- */

const detailLoading = ref(true)
const detailNotFound = ref(false)
const detailLoadErrorMessage = ref<string | null>(null)
const detailRecord = ref<ProcessWaterRecord | null>(null)
const detailRows = ref<ProcessWaterDetailRow[]>([])

async function loadDetail(recordId: string): Promise<void> {
  detailLoading.value = true
  detailLoadErrorMessage.value = null
  detailNotFound.value = false
  detailRecord.value = null
  detailRows.value = []

  try {
    const draft = await processWaterRecordRepo.getDraftWithDetails(recordId)

    if (!draft) {
      detailNotFound.value = true
      return
    }

    detailRecord.value = draft.record
    detailRows.value = draft.details
  } catch (err) {
    detailLoadErrorMessage.value = err instanceof Error ? err.message : 'Gagal memuat data process water.'
  } finally {
    detailLoading.value = false
  }
}

const inputtedByDisplay = computed(() => authStore.currentUser?.name ?? '-')

const DETAIL_STATUS_BADGE_MAP: Record<ProcessWaterRecord['status'], { status: BadgeStatus; label: string }> = {
  draft_ongoing: { status: 'ongoing', label: 'Sedang Berlangsung' },
  draft_paused: { status: 'paused', label: 'Dijeda' },
  saved: { status: 'none', label: 'Tersimpan' },
  synced: { status: 'none', label: 'Tersinkron' },
}

const detailBadgeInfo = computed(() => (detailRecord.value ? DETAIL_STATUS_BADGE_MAP[detailRecord.value.status] : null))

/* ---------------------------------------------------------------------- *
 * Shared — mode-driven loading, title, back navigation.
 * ---------------------------------------------------------------------- */

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

  return detailRecord.value?.process_water_id ? `Process Water ${detailRecord.value.process_water_id}` : 'Detail Process Water'
})

function onBack(): void {
  if (isDetailMode.value) {
    router.push({ name: 'data-preview-process-water' })
    return
  }

  router.push({ name: 'monitor-process-water' })
}

/* ---------------------------------------------------------------------- *
 * Header / breadcrumb / nav-menu — copied verbatim from
 * DataPreviewThreshingView.vue.
 * ---------------------------------------------------------------------- */

const isNavMenuOpen = ref(false)

function toggleNavMenu(): void {
  isNavMenuOpen.value = !isNavMenuOpen.value
}

function closeNavMenu(): void {
  isNavMenuOpen.value = false
}

function openAiAssistant(): void {
  closeNavMenu()
  aiAssistantStore.open()
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

function goToMonitorProcessWater(): void {
  router.push({ name: 'monitor-process-water' })
}
</script>

<template>
  <main class="data-preview-process-water-view">
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
          data-testid="breadcrumb-process-water"
          @click="goToMonitorProcessWater"
        >
          Process Water
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
            placeholder="Process Water ID"
            data-testid="search-filter-input"
          />
        </label>
      </div>

      <p v-if="listLoading" class="status-text">Memuat daftar data process water lokal…</p>
      <p v-else-if="listError" class="status-text status-text--error" role="alert">{{ listError }}</p>

      <section v-else class="record-list-section" aria-label="Daftar Data Process Water">
        <div v-if="filteredRecords.length === 0" class="empty-state" data-testid="record-list-empty">
          <p class="empty-state-text">
            {{ hasActiveFilter ? 'Tidak ada data yang cocok dengan filter.' : 'Belum ada data process water.' }}
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
      <p v-if="detailLoading" class="status-text">Memuat data process water…</p>
      <p
        v-else-if="detailNotFound"
        class="status-text status-text--error"
        role="alert"
        data-testid="record-not-found"
      >
        Data process water tidak ditemukan.
      </p>
      <p v-else-if="detailLoadErrorMessage" class="status-text status-text--error" role="alert">
        {{ detailLoadErrorMessage }}
      </p>

      <div v-else-if="detailRecord" class="preview-body">
        <FormField :model-value="detailRecord.process_water_id" label="Process Water ID" disabled />
        <FormField :model-value="detailRecord.date" label="Tanggal" type="datetime-local" disabled />
        <FormField :model-value="inputtedByDisplay" label="Inputted By" disabled />
        <RecordVerificationStatus
          label="Checked By"
          :verifier-id="detailRecord.checked_by"
          :verifier-name="detailRecord.checked_by_name"
          pending-label="Belum diperiksa Supervisor"
        />
        <RecordVerificationStatus
          label="Acknowledged By"
          :verifier-id="detailRecord.acknowledged_by"
          :verifier-name="detailRecord.acknowledged_by_name"
          pending-label="Belum dikonfirmasi Mill Management"
        />
        <FormField :model-value="detailRecord.note" label="Catatan" disabled />

        <section class="detail-rows-section" aria-label="Process Water Detail">
          <h2 class="detail-rows-title">Process Water Detail</h2>

          <p v-if="detailRows.length === 0" class="status-text" data-testid="process-water-detail-rows-empty">
            Belum ada baris process water detail.
          </p>

          <ul v-else class="detail-rows-list" data-testid="process-water-detail-rows-list">
            <li v-for="row in detailRows" :key="row.id" class="detail-row-view" :data-testid="`process-water-detail-row-${row.id}`">
              <div class="detail-row-view-header">
                <span class="detail-row-view-time-slot">{{ row.time_slot }}</span>
              </div>
              <div class="detail-row-view-grid">
                <span>Shift: {{ row.shift ?? '-' }}</span>
                <span>Inspector ID: {{ row.inspector_id ?? '-' }}</span>
                <span>Raw Water Flow: {{ row.raw_water_flow_m3h ?? '-' }}</span>
                <span>Clarified Water Flow: {{ row.clarified_water_flow_m3h ?? '-' }}</span>
                <span>Softener Inlet pH: {{ row.softener_inlet_ph ?? '-' }}</span>
                <span>Softener Outlet Hardness: {{ row.softener_outlet_hardness_ppm ?? '-' }}</span>
                <span>Alum Dosing: {{ row.alum_dosing_kgh ?? '-' }}</span>
                <span>Polymer Dosing: {{ row.polymer_dosing_gh ?? '-' }}</span>
                <span>Boiler Feed Tank Temp: {{ row.boiler_feed_tank_temp_c ?? '-' }}</span>
                <span>Boiler Feed Water pH: {{ row.boiler_feed_water_ph ?? '-' }}</span>
                <span>Boiler Feed TDS: {{ row.boiler_feed_tds_ppm ?? '-' }}</span>
                <span>Action Taken/Status: {{ row.action_taken_status ?? '-' }}</span>
                <span>Findings: {{ row.findings ?? '-' }}</span>
              </div>
            </li>
          </ul>
        </section>
      </div>
    </template>

    <RecordVerificationActions
      station-type="process-water"
      local-table="process_water_record"
      :record="detailRecord"
      @updated="loadDetail(recordIdParam)"
    />

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
.data-preview-process-water-view {
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

.detail-rows-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.detail-rows-title {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: #1f2937;
}

.detail-rows-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.detail-row-view {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-sizing: border-box;
}

.detail-row-view-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.detail-row-view-time-slot {
  font-size: 14px;
  font-weight: 700;
  color: #249360;
}

.detail-row-view-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 4px 12px;
  font-size: 13px;
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
