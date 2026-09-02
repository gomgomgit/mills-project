<script setup lang="ts">
/**
 * MonitorCpoDispatchView — screen-064--monitor-cpo-dispatch /
 * usecase-079--monitor-cpo-dispatch (mounted at
 * /stations/cpo-dispatch/monitor, meta.public = false). Actors:
 * operator, supervisor.
 *
 * Mobile-only screen — no backend API endpoint; all data comes from the
 * local (offline) `cpo_dispatch_record` / `cpo_dispatch_detail`
 * tables via cpoDispatchRecordRepo.ts.
 *
 * Mirrors MonitorKernelDispatchView.vue's structural pattern
 * (breadcrumb + hamburger header, draft/pause list, New Data/Load Data/Back
 * footer) with EVENT-LOG summary semantics instead of a tipping-progress
 * metric: "Hari Ini" shows Jumlah CPO Dispatch (COUNT of today's
 * records, any status) and Jumlah Kejadian Tercatat (COUNT of detail/event
 * rows belonging to those records), plus a "Kejadian Terakhir" card
 * summarizing the single most-recently-logged event across all of the
 * user's records (any date).
 */
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useFloatingClockStore } from '@/stores/floatingClock'
import { useAiAssistantStore } from '@/stores/aiAssistant'
import {
  cpoDispatchRecordRepo,
  type CpoDispatchDraftListItem,
  type CpoDispatchLastEvent,
  type CpoDispatchTodaySummary,
} from '@/services/cpoDispatchRecordRepo'
import StatusBadge from '@/components/StatusBadge.vue'

const router = useRouter()
const authStore = useAuthStore()
const floatingClockStore = useFloatingClockStore()
const aiAssistantStore = useAiAssistantStore()

const drafts = ref<CpoDispatchDraftListItem[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

const todaySummary = ref<CpoDispatchTodaySummary>({ countRecords: 0, countEvents: 0 })
const lastEvent = ref<CpoDispatchLastEvent | null>(null)

function currentUserId(): string | null {
  return authStore.currentUser?.id ?? null
}

async function loadDrafts() {
  const userId = currentUserId()

  if (!userId) {
    return
  }

  loading.value = true
  error.value = null

  try {
    drafts.value = await cpoDispatchRecordRepo.getDrafts(userId)
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Gagal memuat daftar draft cpo dispatch lokal.'
  } finally {
    loading.value = false
  }
}

async function loadTodaySummary() {
  const userId = currentUserId()

  if (!userId) {
    return
  }

  try {
    todaySummary.value = await cpoDispatchRecordRepo.getTodaySummary(userId)
  } catch {
    // silent — secondary/supplementary section, must not block the draft list.
  }
}

async function loadLastEvent() {
  const userId = currentUserId()

  if (!userId) {
    return
  }

  try {
    lastEvent.value = await cpoDispatchRecordRepo.getLastEventSummary(userId)
  } catch {
    // silent — see loadTodaySummary()'s comment.
  }
}

async function loadAll() {
  await Promise.all([loadDrafts(), loadTodaySummary(), loadLastEvent()])
}

onMounted(loadAll)

function draftLabel(draft: CpoDispatchDraftListItem): string {
  return draft.cpo_dispatch_id?.trim() ? draft.cpo_dispatch_id : 'CPO Dispatch ID belum diisi'
}

async function onNewData() {
  const userId = currentUserId()

  if (!userId) {
    return
  }

  try {
    const id = await cpoDispatchRecordRepo.createDraft(userId)
    router.push({ name: 'cpo-dispatch-form', params: { id } })
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Gagal membuat draft cpo dispatch baru.'
  }
}

function onDraftClick(draft: CpoDispatchDraftListItem) {
  router.push({ name: 'cpo-dispatch-form', params: { id: draft.id } })
}

function onLoadData() {
  router.push({ name: 'data-preview-cpo-dispatch' })
}

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

function goToHome() {
  router.push({ name: 'home' })
}

function goToStationList() {
  router.push({ name: 'station-list' })
}
</script>

<template>
  <main class="monitor-cd-view">
    <header class="app-header">
      <div class="app-header-brand">
        <span class="brand-name">Mills Smart Log</span>
      </div>

      <button type="button" class="hamburger-button" data-testid="hamburger-button" @click="toggleNavMenu">
        &#9776;
      </button>

      <div v-if="isNavMenuOpen" class="nav-menu" data-testid="nav-menu">
        <button type="button" class="nav-menu-item" data-testid="nav-menu-change-password" @click="goToChangePassword">
          Ganti Password
        </button>
        <button type="button" class="nav-menu-item" @click="floatingClockStore.toggle()">{{ floatingClockStore.enabled ? 'Nonaktifkan Jam Mengambang' : 'Aktifkan Jam Mengambang' }}</button>
        <button type="button" class="nav-menu-item" @click="aiAssistantStore.toggleBubble()">{{ aiAssistantStore.bubbleEnabled ? 'Nonaktifkan Bubble Chat AI' : 'Aktifkan Bubble Chat AI' }}</button>
        <button type="button" class="nav-menu-item" @click="openAiAssistant">Bantuan AI</button>
        <button type="button" class="nav-menu-item" data-testid="nav-menu-logout" @click="onLogout">Logout</button>
      </div>
    </header>

    <div class="monitor-cd-header">
      <nav class="breadcrumb" aria-label="Breadcrumb">
        <button type="button" class="breadcrumb-link" data-testid="breadcrumb-home" @click="goToHome">Home</button>
        <span aria-hidden="true">/</span>
        <button type="button" class="breadcrumb-link" data-testid="breadcrumb-production-process-activity" @click="goToStationList">
          Production Process Activity
        </button>
        <span aria-hidden="true">/</span>
        <span aria-current="page">CPO Dispatch</span>
      </nav>
      <h1 class="screen-title">Monitor CPO Dispatch</h1>
    </div>

    <section class="today-summary-section" aria-label="Hari Ini">
      <h2 class="today-summary-title">Hari Ini</h2>
      <div class="today-summary-grid">
        <div class="today-summary-card">
          <span class="today-summary-label">Jumlah CPO Dispatch</span>
          <span class="today-summary-value" data-testid="counter-count-records">{{ todaySummary.countRecords }}</span>
        </div>
        <div class="today-summary-card">
          <span class="today-summary-label">Jumlah Kejadian Tercatat</span>
          <span class="today-summary-value" data-testid="counter-count-events">{{ todaySummary.countEvents }}</span>
        </div>
      </div>
    </section>

    <section class="last-event-section" aria-label="Kejadian Terakhir" data-testid="last-event-card">
      <h2 class="today-summary-title">Kejadian Terakhir</h2>
      <p v-if="!lastEvent" class="empty-state">Belum ada kejadian tercatat.</p>
      <div v-else class="last-event-body">
        <span>{{ lastEvent.destinationBuyer ?? 'Tujuan tidak tercatat' }}</span>
        <span>{{ lastEvent.eventDate ?? '-' }}</span>
        <span>{{ lastEvent.tankerPlateNo ?? '-' }}</span>
      </div>
    </section>

    <p v-if="loading" class="status-text">Memuat daftar draft cpo dispatch lokal…</p>
    <p v-else-if="error" class="status-text status-text--error" role="alert">{{ error }}</p>

    <section v-else class="draft-list-section" aria-label="Daftar Draft CPO Dispatch">
      <p v-if="drafts.length === 0" class="empty-state" data-testid="draft-list-empty">
        Belum ada draft cpo dispatch tersimpan.
      </p>

      <ul v-else class="draft-list" role="list" data-testid="draft-list">
        <li v-for="draft in drafts" :key="draft.id" role="listitem">
          <button type="button" class="draft-item" :data-testid="`draft-item-${draft.id}`" @click="onDraftClick(draft)">
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
        <button type="button" class="action-button action-button--secondary" data-testid="load-data-button" @click="onLoadData">
          Load Data
        </button>
      </div>

      <button type="button" class="action-button action-button--primary" data-testid="new-data-button" @click="onNewData">
        New Data
      </button>
    </footer>
  </main>
</template>

<style scoped>
.monitor-cd-view { min-height: 100vh; display: flex; flex-direction: column; gap: 20px; padding: 0 20px 20px; background: #ffffff; font-family: 'Inter', sans-serif; box-sizing: border-box; }
.app-header { position: relative; display: flex; align-items: center; justify-content: space-between; min-height: 64px; margin: 0 -20px; padding: 0 20px; background: #ffffff; }
.brand-name { font-size: 16px; font-weight: 700; color: #1f2937; }
.hamburger-button { border: none; background: transparent; font-size: 20px; cursor: pointer; }
.nav-menu { position: absolute; top: 64px; right: 0; z-index: 10; display: flex; flex-direction: column; min-width: 180px; padding: 6px; border-radius: 12px; background: #ffffff; box-shadow: 0 1px 2px rgba(0,0,0,0.06); border: 1px solid #f7f7f7; }
.nav-menu-item { min-height: 44px; padding: 0 12px; border: none; border-radius: 8px; background: transparent; font-size: 14px; font-weight: 500; text-align: left; cursor: pointer; }
.monitor-cd-header { display: flex; flex-direction: column; gap: 6px; }
.breadcrumb { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; font-size: 12px; color: #6b7280; }
.breadcrumb-link { border: none; background: transparent; color: #6b7280; font-size: 12px; cursor: pointer; padding: 2px 4px; }
.screen-title { margin: 0; font-size: 20px; font-weight: 600; color: #1f2937; }
.today-summary-section, .last-event-section { display: flex; flex-direction: column; gap: 8px; }
.today-summary-title { margin: 0; font-size: 14px; font-weight: 600; color: #1f2937; }
.today-summary-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
.today-summary-card { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 12px; border-radius: 8px; background: #249360; text-align: center; }
.today-summary-label { font-size: 11px; font-weight: 500; color: #eef6f1; }
.today-summary-value { font-size: 20px; font-weight: 700; color: #ffffff; }
.last-event-body { display: flex; flex-direction: column; gap: 4px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; color: #1f2937; }
.status-text { font-size: 14px; color: #6b7280; }
.status-text--error { color: #dc2626; }
.draft-list-section { display: flex; flex-direction: column; flex: 1; min-height: 0; }
.empty-state { margin: 0; padding: 16px; border: 1px solid #e5e7eb; border-radius: 8px; color: #6b7280; font-size: 14px; text-align: center; }
.draft-list { display: flex; flex-direction: column; gap: 10px; margin: 0; padding: 0; list-style: none; }
.draft-item { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; min-height: 44px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: #ffffff; box-sizing: border-box; text-align: left; font-family: inherit; cursor: pointer; }
.draft-item-label { font-size: 14px; font-weight: 500; color: #1f2937; }
.action-footer { display: flex; flex-direction: column; gap: 12px; margin-top: auto; }
.action-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
.action-button { min-height: 44px; border-radius: 8px; font-size: 14px; font-weight: 600; font-family: inherit; cursor: pointer; box-sizing: border-box; }
.action-button--secondary { border: 1px solid #e5e7eb; background: #ffffff; color: #1f2937; }
.action-button--primary { border: none; background: #249360; color: #ffffff; font-size: 16px; }
</style>
