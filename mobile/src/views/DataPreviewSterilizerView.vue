<script setup lang="ts">
/**
 * DataPreviewSterilizerView — screen-123--data-preview-sterilizer
 * / usecase-123--data-preview-sterilizer (mounted at
 * /stations/sterilizer/preview/:id? — `:id` OPTIONAL, meta.public =
 * false). Actors: operator, supervisor.
 *
 * Mobile-only screen — no backend API endpoint. All data comes from the
 * local (offline) `sterilizer_record` + `sterilizer_detail` tables via
 * sterilizerRecordRepo.ts.
 *
 * Mirrors DataPreviewCpoDispatchView.vue's dual route-driven mode pattern:
 *  - LIST mode (no `:id`) — every local record for the current user, any
 *    status, client-side date + search (sterilizer_id) filter.
 *    Tapping a draft_ongoing/draft_paused row opens the Form; tapping a
 *    saved/synced row switches to DETAIL mode (adds `:id`).
 *  - DETAIL mode (`:id` present) — read-only single-record view (header +
 *    the event-log detail table), reusing getDraftWithDetails().
 *
 * This is the FINAL station of this project (Sterilizer promoted
 * 2026-09-01, the 18th and last of the 18 canonical stations).
 */
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useFloatingClockStore } from '@/stores/floatingClock'
import { useAiAssistantStore } from '@/stores/aiAssistant'
import sterilizerRecordRepo, {
  type SterilizerDetailRow,
  type SterilizerRecord,
} from '@/services/sterilizerRecordRepo'
import StatusBadge, { type BadgeStatus } from '@/components/StatusBadge.vue'
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

const recordIdParam = computed(() => {
  const raw = route.params.id
  const value = Array.isArray(raw) ? raw[0] : raw
  return value ? String(value) : ''
})
const isDetailMode = computed(() => recordIdParam.value !== '')

/* -------------------------- LIST mode -------------------------- */

const listLoading = ref(false)
const listError = ref<string | null>(null)
const allRecords = ref<SterilizerRecord[]>([])
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
    allRecords.value = await sterilizerRecordRepo.getAllRecords(userId)
  } catch (err) {
    listError.value = err instanceof Error ? err.message : 'Gagal memuat daftar data sterilizer lokal.'
  } finally {
    listLoading.value = false
  }
}

function recordLabel(item: SterilizerRecord): string {
  return item.sterilizer_id?.trim() ? item.sterilizer_id : 'Sterilizer ID belum diisi'
}

const LIST_STATUS_BADGE_MAP: Record<SterilizerRecord['status'], { status: BadgeStatus; label: string }> = {
  draft_ongoing: { status: 'paused', label: 'Pause' },
  draft_paused: { status: 'paused', label: 'Pause' },
  saved: { status: 'none', label: 'Tersimpan' },
  synced: { status: 'none', label: 'Tersinkron' },
}

function listBadgeInfo(item: SterilizerRecord) {
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
      const idMatch = (item.sterilizer_id ?? '').toLowerCase().includes(keyword)
      if (!idMatch) {
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

function onItemClick(item: SterilizerRecord): void {
  if (item.status === 'draft_ongoing' || item.status === 'draft_paused') {
    router.push({ name: 'sterilizer-form', params: { id: item.id } })
    return
  }

  router.push({ name: 'data-preview-sterilizer', params: { id: item.id } })
}

/* -------------------------- DETAIL mode -------------------------- */

const detailLoading = ref(true)
const detailNotFound = ref(false)
const detailLoadErrorMessage = ref<string | null>(null)
const detailRecord = ref<SterilizerRecord | null>(null)
const detailRows = ref<SterilizerDetailRow[]>([])

async function loadDetail(recordId: string): Promise<void> {
  detailLoading.value = true
  detailLoadErrorMessage.value = null
  detailNotFound.value = false
  detailRecord.value = null
  detailRows.value = []

  try {
    const draft = await sterilizerRecordRepo.getDraftWithDetails(recordId)

    if (!draft) {
      detailNotFound.value = true
      return
    }

    detailRecord.value = draft.record
    detailRows.value = draft.details
  } catch (err) {
    detailLoadErrorMessage.value = err instanceof Error ? err.message : 'Gagal memuat data sterilizer.'
  } finally {
    detailLoading.value = false
  }
}

const inputtedByDisplay = computed(() => authStore.currentUser?.name ?? '-')

const DETAIL_STATUS_BADGE_MAP: Record<SterilizerRecord['status'], { status: BadgeStatus; label: string }> = {
  draft_ongoing: { status: 'ongoing', label: 'Sedang Berlangsung' },
  draft_paused: { status: 'paused', label: 'Dijeda' },
  saved: { status: 'none', label: 'Tersimpan' },
  synced: { status: 'none', label: 'Tersinkron' },
}

const detailBadgeInfo = computed(() => (detailRecord.value ? DETAIL_STATUS_BADGE_MAP[detailRecord.value.status] : null))

/* -------------------------- shared -------------------------- */

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

function onBack(): void {
  if (isDetailMode.value) {
    router.push({ name: 'data-preview-sterilizer' })
    return
  }

  router.push({ name: 'monitor-sterilizer' })
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

function goToHome() {
  router.push({ name: 'home' })
}

function goToStationList() {
  router.push({ name: 'station-list' })
}

function goToMonitor() {
  router.push({ name: 'monitor-sterilizer' })
}
</script>

<template>
  <main class="preview-ster-view">
    <header class="app-header">
      <div class="app-header-brand">
        <span class="brand-name">Mills Smart Log</span>
      </div>
      <button type="button" class="hamburger-button" data-testid="hamburger-button" @click="toggleNavMenu">
        &#9776;
      </button>
      <div v-if="isNavMenuOpen" class="nav-menu" data-testid="nav-menu">
        <button type="button" class="nav-menu-item" @click="goToChangePassword">Ganti Password</button>
        <button type="button" class="nav-menu-item" @click="floatingClockStore.toggle()">{{ floatingClockStore.enabled ? 'Nonaktifkan Jam Mengambang' : 'Aktifkan Jam Mengambang' }}</button>
        <button type="button" class="nav-menu-item" @click="aiAssistantStore.toggleBubble()">{{ aiAssistantStore.bubbleEnabled ? 'Nonaktifkan Bubble Chat AI' : 'Aktifkan Bubble Chat AI' }}</button>
        <button type="button" class="nav-menu-item" @click="openAiAssistant">Bantuan AI</button>
        <button type="button" class="nav-menu-item" data-testid="nav-menu-logout" @click="onLogout">Logout</button>
      </div>
    </header>

    <div class="preview-ster-header">
      <nav class="breadcrumb" aria-label="Breadcrumb">
        <button type="button" class="breadcrumb-link" data-testid="breadcrumb-home" @click="goToHome">Home</button>
        <span aria-hidden="true">/</span>
        <button type="button" class="breadcrumb-link" @click="goToStationList">Production Process Activity</button>
        <span aria-hidden="true">/</span>
        <button type="button" class="breadcrumb-link" @click="goToMonitor">Sterilizer</button>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Load Data</span>
      </nav>
      <h1 class="screen-title">Data Preview Sterilizer</h1>
    </div>

    <!-- LIST MODE -->
    <template v-if="!isDetailMode">
      <div class="filter-bar">
        <label class="filter-field">
          <span>Tanggal</span>
          <input type="date" v-model="dateFilter" data-testid="date-filter" />
        </label>
        <label class="filter-field">
          <span>Cari (Sterilizer ID)</span>
          <input type="text" v-model="searchFilter" data-testid="search-filter" />
        </label>
        <button v-if="hasActiveFilter" type="button" class="action-button action-button--secondary" data-testid="reset-filter-button" @click="onResetFilter">
          Reset Filter
        </button>
      </div>

      <p v-if="listLoading" class="status-text">Memuat daftar data sterilizer lokal…</p>
      <p v-else-if="listError" class="status-text status-text--error" role="alert">{{ listError }}</p>
      <p v-else-if="allRecords.length === 0" class="empty-state" data-testid="list-empty">
        Belum ada data sterilizer tersimpan.
      </p>
      <p v-else-if="filteredRecords.length === 0" class="empty-state" data-testid="list-not-found">
        Tidak ditemukan data yang sesuai filter.
      </p>

      <ul v-else class="record-list" role="list" data-testid="record-list">
        <li v-for="item in filteredRecords" :key="item.id" role="listitem">
          <button type="button" class="record-item" :data-testid="`record-item-${item.id}`" @click="onItemClick(item)">
            <span class="record-item-label">{{ recordLabel(item) }}</span>
            <StatusBadge :status="listBadgeInfo(item).status" :label="listBadgeInfo(item).label" />
          </button>
        </li>
      </ul>
    </template>

    <!-- DETAIL MODE -->
    <template v-else>
      <p v-if="detailLoading" class="status-text">Memuat data sterilizer…</p>
      <p v-else-if="detailNotFound" class="status-text status-text--error" role="alert" data-testid="record-not-found">
        Record tidak ditemukan.
      </p>
      <p v-else-if="detailLoadErrorMessage" class="status-text status-text--error" role="alert">{{ detailLoadErrorMessage }}</p>

      <div v-else-if="detailRecord" class="detail-body">
        <section class="detail-section">
          <h2 class="section-title">Identitas Sterilizer</h2>
          <p data-testid="detail-sterilizer-id"><strong>Sterilizer ID:</strong> {{ detailRecord.sterilizer_id || '-' }}</p>
          <p><strong>Tanggal:</strong> {{ detailRecord.date || '-' }}</p>
          <p v-if="detailBadgeInfo">
            <strong>Status:</strong>
            <StatusBadge :status="detailBadgeInfo.status" :label="detailBadgeInfo.label" />
          </p>
        </section>

        <section class="detail-section">
          <h2 class="section-title">Log Siklus Sterilisasi</h2>
          <p v-if="detailRows.length === 0" class="empty-state">Belum ada log siklus.</p>
          <div v-else class="detail-table-wrap" data-testid="sterilizer-detail-log">
            <table class="detail-table">
              <thead>
                <tr>
                  <th>Sterilizer No</th>
                  <th>Close Door Time</th>
                  <th>Open Door Time</th>
                  <th>Duration (Minutes)</th>
                  <th>Number of Cages</th>
                  <th>Cages Status</th>
                  <th>Checked by SPV</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in detailRows" :key="row.id">
                  <td>{{ row.sterilizer_no || '-' }}</td>
                  <td>{{ row.close_door_time || '-' }}</td>
                  <td>{{ row.open_door_time || '-' }}</td>
                  <td>{{ row.duration_minutes ?? '-' }}</td>
                  <td>{{ row.number_of_cages ?? '-' }}</td>
                  <td>{{ row.cages_status || '-' }}</td>
                  <td>{{ row.checked_by_spv ? 'Ya' : 'Tidak' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section class="detail-section">
          <h2 class="section-title">Verifikasi</h2>
          <p><strong>Inputted By:</strong> {{ inputtedByDisplay }}</p>
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
          <p><strong>Note:</strong> {{ detailRecord.note || '-' }}</p>
        </section>
      </div>
    </template>

    <RecordVerificationActions
      station-type="sterilizer"
      local-table="sterilizer_record"
      :record="detailRecord"
      @updated="loadDetail(recordIdParam)"
    />

    <footer class="action-footer">
      <button type="button" class="action-button action-button--secondary" data-testid="back-button" @click="onBack">
        Back
      </button>
    </footer>
  </main>
</template>

<style scoped>
.preview-ster-view { min-height: 100vh; display: flex; flex-direction: column; gap: 16px; padding: 0 16px 20px; background: #ffffff; font-family: 'Inter', sans-serif; box-sizing: border-box; }
.app-header { position: relative; display: flex; align-items: center; justify-content: space-between; min-height: 64px; margin: 0 -16px; padding: 0 16px; background: #ffffff; }
.brand-name { font-size: 16px; font-weight: 700; color: #1f2937; }
.hamburger-button { border: none; background: transparent; font-size: 20px; cursor: pointer; }
.nav-menu { position: absolute; top: 64px; right: 0; z-index: 10; display: flex; flex-direction: column; min-width: 180px; padding: 6px; border-radius: 12px; background: #ffffff; box-shadow: 0 1px 2px rgba(0,0,0,0.06); border: 1px solid #f7f7f7; }
.nav-menu-item { min-height: 44px; padding: 0 12px; border: none; border-radius: 8px; background: transparent; font-size: 14px; font-weight: 500; text-align: left; cursor: pointer; }
.preview-ster-header { display: flex; flex-direction: column; gap: 6px; }
.breadcrumb { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; font-size: 12px; color: #6b7280; }
.breadcrumb-link { border: none; background: transparent; color: #6b7280; font-size: 12px; cursor: pointer; padding: 0; }
.screen-title { margin: 0; font-size: 20px; font-weight: 600; color: #1f2937; }
.filter-bar { display: flex; flex-wrap: wrap; align-items: end; gap: 12px; }
.filter-field { display: flex; flex-direction: column; gap: 4px; font-size: 13px; color: #6b7280; }
.filter-field input { padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; font-family: inherit; }
.status-text { font-size: 14px; color: #6b7280; }
.status-text--error { color: #dc2626; }
.empty-state { margin: 0; padding: 16px; border: 1px solid #e5e7eb; border-radius: 8px; color: #6b7280; font-size: 14px; text-align: center; }
.record-list { display: flex; flex-direction: column; gap: 10px; margin: 0; padding: 0; list-style: none; }
.record-item { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; min-height: 44px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: #ffffff; box-sizing: border-box; text-align: left; font-family: inherit; cursor: pointer; }
.record-item-label { font-size: 14px; font-weight: 500; color: #1f2937; }
.detail-body { display: flex; flex-direction: column; gap: 16px; }
.detail-section { display: flex; flex-direction: column; gap: 8px; padding: 16px; border: 1px solid #e5e7eb; border-radius: 8px; }
.section-title { margin: 0; font-size: 16px; font-weight: 700; color: #1f2937; }
.detail-table-wrap { width: 100%; overflow-x: auto; }
.detail-table { border-collapse: collapse; min-width: 700px; font-size: 13px; }
.detail-table th, .detail-table td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
.action-footer { display: flex; margin-top: auto; }
.action-button { min-height: 44px; padding: 0 16px; border-radius: 8px; font-size: 14px; font-weight: 600; font-family: inherit; cursor: pointer; box-sizing: border-box; }
.action-button--secondary { border: 1px solid #e5e7eb; background: #ffffff; color: #1f2937; }
</style>
