<script setup lang="ts">
/**
 * FormKernelPlantView — screen-044--form-kernel-plant /
 * usecase-044--form-kernel-plant (mounted at
 * /stations/kernel-plant/form/:id, meta.public = false — requires an
 * authenticated session, enforced by the router's global auth guard; see
 * router/index.ts). Actors: operator, supervisor (Checked By interactive
 * only for supervisor, Acknowledged By interactive only for
 * mill_management).
 *
 * Mobile-only screen — no backend API endpoint; all data comes from the
 * local (offline) `kernel_plant_record` + `kernel_plant_detail` tables via
 * kernelPlantRecordRepo.ts.
 *
 * Structural pattern (breadcrumb + hamburger header, sectioned form body,
 * dirty-tracking Back confirm, Pause/Clear/Simpan footer actions) copied
 * verbatim from FormDepricarpingView.vue (screen-043), Kernel Plant's exact
 * structural sibling, adapted for Kernel Plant's own header field and 9
 * reading columns:
 *
 *  - "Identitas Kernel Plant" section: `kernel_plant_id` (required, freely
 *    editable), `date` (auto-set once, in memory, ONLY for a brand-new
 *    draft — kernelPlantRecordRepo.ts's createDraft() already auto-fills it
 *    at draft-creation time; this screen's populateForm() re-applies the
 *    same "only if still empty" rule defensively). Always rendered
 *    disabled.
 *
 *  - "Verifikasi" section: "Inputted By" is a plain read-only display of
 *    authStore.currentUser?.name. "Saya verifikasi data ini" (Checked By)
 *    is a checkbox toggle shown ONLY to supervisor. "Saya menyetujui data
 *    ini" (Acknowledged By) is the same toggle pattern, shown ONLY to
 *    mill_management. UPDATED 2026-09-14: both were previously
 *    rendered-but-disabled for every other role, which put two permanently
 *    dead checkboxes in front of the Operator doing the data entry; they
 *    are now hidden outright for roles that cannot write them, and the
 *    saved state is shown on Data Preview instead. Both re-enforced at the repo
 *    level (kernelPlantRecordRepo.ts's saveDraft()/
 *    pauseDraftWithFormData() role-stripping) as defense-in-depth. `note`
 *    (optional free text) also lives in this section.
 *
 *  - "Kernel Plant Detail" section — the dynamic grid. REVISED 2026-08-24
 *    (entity-catalog v12): replaces the original FIXED 24-row design (all
 *    24 rows pre-created by createDraft(), no add/remove-row UI at all —
 *    explicitly rejected by the user for wasting screen space) with the
 *    EXACT pattern FormDepricarpingView.vue's Depricarping Detail grid
 *    already uses (itself mirroring FormPressingView.vue /
 *    FormThreshingView.vue / FormCagesTrackView.vue): `detailRows`
 *    (in-memory, rows without a saved `id` are new), `addDetailRow()`
 *    (pushes `{ time_slot: null, ... }`), `removeDetailRow(index)` (splices
 *    the array; if the row had an `id`, its id is queued into
 *    `pendingDeletionIds` for the next save/pause — not deleted
 *    immediately), `availableTimeSlotOptions(index)` (dropdown options =
 *    all 24 canonical slots MINUS ones used by OTHER rows MINUS ones
 *    at-or-before the highest slot already picked by another row, always
 *    including the row's own current value — same floor/exclusion logic as
 *    FormDepricarpingView.vue's `availableTimeSlotOptions()`). Each row's
 *    Time-Slot is now a SearchableSelect dropdown (was a read-only label);
 *    the 7 numeric reading fields, Downtime (Mins), and Findings stay
 *    freely editable at all times, unchanged. "Tambah baris" is disabled
 *    once all 24 canonical slots are already used (`detailRows.length >=
 *    24`) or while an action is in progress. STRUCTURAL SHAPE MATCHES
 *    DEPRICARPING (not Threshing/Pressing): Kernel Plant has TWO separate
 *    columns instead of one free-text "Downtime Reason" column —
 *    "Downtime (Mins)" (numeric) and "Findings" (free text) — matching its
 *    own source log sheet exactly, kept distinct end-to-end and unaffected
 *    by this dynamic-row revision (a row counts as filled when EITHER is
 *    set, same as any other reading column).
 *
 *  - "Target Operasional" section — read-only reference table (from the
 *    static KERNEL_PLANT_OPERATIONAL_TARGETS constant — see that file's own
 *    doc comment for why this is static rather than synced/local-DB-backed),
 *    rendered below the grid, never part of the save/pause payload.
 *    UNTOUCHED by this revision. STRUCTURAL SHAPE MATCHES THRESHING/PRESSING
 *    (not Depricarping): this table has 3 columns (Equipment / Parameter,
 *    Target Benchmark, Corrective Action Plan).
 *
 *  - 'Simpan': validates kernel_plant_id (required) — inline error — AND at
 *    least one valid Kernel Plant Detail row (a selected Time-Slot AND at
 *    least one reading column filled), a dedicated error message shown on
 *    the grid (not mixed into per-field header errors), re-enforced at the
 *    repo level (KernelPlantDetailRequiredError) — mirrors
 *    FormDepricarpingView.vue's grid validation exactly. On success: calls
 *    kernelPlantRecordRepo.saveDraft() (status='saved'), navigates to
 *    `monitor-kernel-plant`.
 *  - 'Pause': no validation at all, persists current field values as-is via
 *    kernelPlantRecordRepo.pauseDraftWithFormData() (status='draft_paused'),
 *    navigates to `monitor-kernel-plant`.
 *  - 'Clear': ConfirmDialog.vue first; on confirm, calls
 *    kernelPlantRecordRepo.deleteDraft() (cascades delete to every
 *    kernel_plant_detail row for the record, however many exist), then
 *    navigates to `monitor-kernel-plant`; on cancel, no change.
 *  - 'Back' with unsaved changes (header fields, detail rows, or queued row
 *    deletions differ from the loaded-draft snapshot) shows
 *    ConfirmDialog.vue before leaving; if not dirty, navigates directly.
 *
 * Header/breadcrumb/nav-menu: copied verbatim from FormDepricarpingView.vue.
 * Breadcrumb is 4 segments deep (Home > Production Process Activity >
 * Kernel Plant > Form), the first 3 tappable.
 */
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useFloatingClockStore } from '@/stores/floatingClock'
import { useAiAssistantStore } from '@/stores/aiAssistant'
import kernelPlantRecordRepo, {
  KernelPlantDetailRequiredError,
  type KernelPlantDetailFormRow,
  type KernelPlantDraftWithDetails,
  type KernelPlantHeaderFormData,
  type KernelPlantRecord,
} from '@/services/kernelPlantRecordRepo'
import { KERNEL_PLANT_OPERATIONAL_TARGETS } from '@/data/kernelPlantOperationalTargets'
import FormField from '@/components/FormField.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import SearchableSelect, { type SearchableSelectOption } from '@/components/SearchableSelect.vue'
import CollapsibleSection from '@/components/CollapsibleSection.vue'
import { syncAfterSave } from '@/services/writeThroughSync'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const floatingClockStore = useFloatingClockStore()
const aiAssistantStore = useAiAssistantStore()

const recordId = String(route.params.id ?? '')

// business_logic step 9 — required header fields. Note/Checked By/
// Acknowledged By are deliberately NOT required.
const REQUIRED_FIELDS: (keyof KernelPlantHeaderFormData)[] = ['kernel_plant_id']

const REQUIRED_FIELD_LABELS: Record<string, string> = {
  kernel_plant_id: 'Kernel Plant ID',
}

// business_logic — fields compared for the Back dirty-check. `date` is
// excluded (fully automatic/disabled, never freely user-editable — same
// reasoning as FormDepricarpingView.vue excluding date).
const DIRTY_CHECK_FIELDS: (keyof KernelPlantHeaderFormData)[] = [
  'kernel_plant_id',
  'note',
  'checked_by',
  'acknowledged_by',
]

function emptyFormData(): KernelPlantHeaderFormData {
  return {
    kernel_plant_id: '',
    date: '',
    note: '',
    checked_by: '',
    acknowledged_by: '',
  }
}

const form = reactive<KernelPlantHeaderFormData>(emptyFormData())
const errors = reactive<Partial<Record<keyof KernelPlantHeaderFormData, string>>>({})
const detailRows = ref<KernelPlantDetailFormRow[]>([])
// business_logic — ids of existing (has-`id`) detail rows removed via
// "Hapus baris" this session; only actually DELETEd by the repo on the
// next Simpan/Pause call (mirrors FormDepricarpingView.vue's
// pendingDeletionIds exactly).
const pendingDeletionIds = ref<string[]>([])

const loading = ref(true)
const saving = ref(false)
const pausing = ref(false)
const clearing = ref(false)
const notFound = ref(false)
const loadErrorMessage = ref<string | null>(null)
const actionErrorMessage = ref<string | null>(null)
// business_logic — distinct/special error for "zero filled rows",
// surfaced on the Kernel Plant Detail section rather than mixed into the
// per-field header `errors` object above.
const detailRowsError = ref<string | null>(null)
const backDialogOpen = ref(false)
const clearDialogOpen = ref(false)

const actionInProgress = computed(() => saving.value || pausing.value || clearing.value)

const isSupervisor = computed(() => authStore.currentUser?.role === 'supervisor')
const isMillManagement = computed(() => authStore.currentUser?.role === 'mill_management')

// Snapshot of {header, rows, pendingDeletions} right after loading, used
// purely for the Back dirty-check — compared as JSON, same approach as
// FormDepricarpingView.vue's `isDirty`.
let loadedSnapshot = dirtySnapshot()

function dirtySnapshot(): string {
  const header: Partial<Record<keyof KernelPlantHeaderFormData, string>> = {}

  for (const key of DIRTY_CHECK_FIELDS) {
    header[key] = form[key]
  }

  const rows = detailRows.value.map((row) => ({
    id: row.id ?? null,
    time_slot: row.time_slot,
    ripple_mill_1_amps: row.ripple_mill_1_amps,
    ripple_mill_2_amps: row.ripple_mill_2_amps,
    claybath_hydro_sg: row.claybath_hydro_sg,
    kernel_silo_1_temp_c: row.kernel_silo_1_temp_c,
    kernel_silo_2_temp_c: row.kernel_silo_2_temp_c,
    kernel_moisture_percent: row.kernel_moisture_percent,
    shell_loss_percent: row.shell_loss_percent,
    downtime_minutes: row.downtime_minutes,
    findings: row.findings,
  }))

  return JSON.stringify({ header, rows, pendingDeletions: [...pendingDeletionIds.value] })
}

const isDirty = computed(() => dirtySnapshot() !== loadedSnapshot)

/**
 * Builds a local (device timezone) date-time string, e.g.
 * "2026-08-24T14:30:00" — deliberately NOT `new Date().toISOString()`,
 * which is UTC and can land on a different calendar day/hour than the
 * device's local time. Mirrors FormDepricarpingView.vue's
 * `nowLocalDateTimeString()`.
 */
function nowLocalDateTimeString(): string {
  const now = new Date()
  const yyyy = now.getFullYear()
  const mm = String(now.getMonth() + 1).padStart(2, '0')
  const dd = String(now.getDate()).padStart(2, '0')
  const hh = String(now.getHours()).padStart(2, '0')
  const mi = String(now.getMinutes()).padStart(2, '0')
  const ss = String(now.getSeconds()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}T${hh}:${mi}:${ss}`
}

function formatDateID(value: string): string {
  const date = new Date(value)

  if (!value || Number.isNaN(date.getTime())) {
    return ''
  }

  return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(date)
}

const dateDisplay = computed(() => formatDateID(form.date))

// business_logic — Date auto-set once (in memory) only for a brand-new
// draft (no stored value yet); a resumed draft's stored value is kept
// untouched.
function populateForm(record: KernelPlantRecord): void {
  form.kernel_plant_id = record.kernel_plant_id ?? ''
  form.date = record.date && record.date.trim() !== '' ? record.date : nowLocalDateTimeString()
  form.note = record.note ?? ''
  form.checked_by = record.checked_by ?? ''
  form.acknowledged_by = record.acknowledged_by ?? ''
}

function populateDetailRows(rows: KernelPlantDraftWithDetails['details']): void {
  detailRows.value = rows.map((row) => ({
    id: row.id,
    time_slot: row.time_slot ?? null,
    ripple_mill_1_amps: row.ripple_mill_1_amps ?? null,
    ripple_mill_2_amps: row.ripple_mill_2_amps ?? null,
    claybath_hydro_sg: row.claybath_hydro_sg ?? null,
    kernel_silo_1_temp_c: row.kernel_silo_1_temp_c ?? null,
    kernel_silo_2_temp_c: row.kernel_silo_2_temp_c ?? null,
    kernel_moisture_percent: row.kernel_moisture_percent ?? null,
    shell_loss_percent: row.shell_loss_percent ?? null,
    downtime_minutes: row.downtime_minutes ?? null,
    findings: row.findings ?? '',
  }))
}

async function loadDraft(): Promise<void> {
  loading.value = true
  loadErrorMessage.value = null
  notFound.value = false

  if (!recordId) {
    loading.value = false
    notFound.value = true
    return
  }

  try {
    const draft = await kernelPlantRecordRepo.getDraftWithDetails(recordId)

    if (!draft) {
      notFound.value = true
      return
    }

    populateForm(draft.record)
    populateDetailRows(draft.details)
    pendingDeletionIds.value = []
    loadedSnapshot = dirtySnapshot()
  } catch (err) {
    loadErrorMessage.value = err instanceof Error ? err.message : 'Gagal memuat draft kernel plant.'
  } finally {
    loading.value = false
  }
}

onMounted(loadDraft)

/**
 * business_logic — available Time-Slot options for a given row: all 24
 * canonical slots (07:00..06:00), MINUS any slot already selected by
 * ANOTHER row in the grid, MINUS any slot at-or-before the HIGHEST slot
 * already selected among all OTHER rows (the "floor", compared by
 * canonical-order INDEX rather than raw hour, since slots wrap starting at
 * 07:00) — slots must be added in strictly ascending canonical order, same
 * "floor is the max across every OTHER row" reasoning as
 * FormDepricarpingView.vue's `availableTimeSlotOptions()`. Both exclusions
 * exempt the row's OWN current selection. Reactive over the entire
 * `detailRows.value` array.
 */
function availableTimeSlotOptions(index: number): string[] {
  const row = detailRows.value[index]

  if (!row) {
    return []
  }

  const order = kernelPlantRecordRepo.canonicalTimeSlots()

  const otherSlots = detailRows.value
    .filter((_, otherIndex) => otherIndex !== index)
    .map((otherRow) => otherRow.time_slot)
    .filter((slot): slot is string => slot !== null && slot !== undefined && slot !== '')

  const otherIndexes = otherSlots.map((slot) => order.indexOf(slot)).filter((i) => i >= 0)
  const usedByOtherRows = new Set(otherSlots)
  const floor = otherIndexes.length > 0 ? Math.max(...otherIndexes) : null

  const options: string[] = []

  order.forEach((slot, slotIndex) => {
    if (usedByOtherRows.has(slot)) {
      return
    }

    if (floor !== null && slotIndex <= floor && slot !== row.time_slot) {
      return
    }

    options.push(slot)
  })

  return options
}

// SearchableSelect never computes availability itself — it's always handed
// the already-filtered list to show right now, same pattern
// FormDepricarpingView.vue's timeSlotSelectOptions() uses for its own
// per-row dropdown.
function timeSlotSelectOptions(index: number): SearchableSelectOption[] {
  return availableTimeSlotOptions(index).map((slot) => ({ value: slot, label: slot }))
}

// business_logic step 6 — "Tambah baris": disabled once all 24 canonical
// slots are already used by existing rows, or while an action is in
// progress (see the template's :disabled binding).
const canAddDetailRow = computed(() => detailRows.value.length < 24)

// business_logic step 6 — "Tambah baris".
function addDetailRow(): void {
  detailRows.value.push({
    time_slot: null,
    ripple_mill_1_amps: null,
    ripple_mill_2_amps: null,
    claybath_hydro_sg: null,
    kernel_silo_1_temp_c: null,
    kernel_silo_2_temp_c: null,
    kernel_moisture_percent: null,
    shell_loss_percent: null,
    downtime_minutes: null,
    findings: null,
  })
}

// business_logic step 9 — "Hapus baris". A row with an `id` (loaded from an
// existing draft) is queued for deletion, not deleted immediately; a row
// with no `id` (added this session) is just dropped from local state —
// mirrors FormDepricarpingView.vue's removeDetailRow() exactly. Either way,
// the row's time-slot is immediately freed for other rows' dropdowns (falls
// out of availableTimeSlotOptions() automatically once the row is gone from
// detailRows.value).
function removeDetailRow(index: number): void {
  const row = detailRows.value[index]

  if (row?.id) {
    pendingDeletionIds.value.push(row.id)
  }

  detailRows.value.splice(index, 1)
}

// business_logic step 9 — required-field validation with inline per-field
// errors.
function validateHeader(): boolean {
  for (const key of Object.keys(errors) as (keyof KernelPlantHeaderFormData)[]) {
    delete errors[key]
  }

  for (const field of REQUIRED_FIELDS) {
    const value = form[field]
    const isEmpty = value === null || value === undefined || value === ''

    if (isEmpty) {
      errors[field] = `${REQUIRED_FIELD_LABELS[field]} wajib diisi.`
    }
  }

  return Object.keys(errors).length === 0
}

function isRowFilled(row: KernelPlantDetailFormRow): boolean {
  return (
    row.ripple_mill_1_amps !== null ||
    row.ripple_mill_2_amps !== null ||
    row.claybath_hydro_sg !== null ||
    row.kernel_silo_1_temp_c !== null ||
    row.kernel_silo_2_temp_c !== null ||
    row.kernel_moisture_percent !== null ||
    row.shell_loss_percent !== null ||
    row.downtime_minutes !== null ||
    (row.findings !== null && row.findings !== '')
  )
}

// business_logic step 9 — at-least-one-valid-row validation (a selected
// Time-Slot AND at least one filled reading column), kept separate from
// validateHeader() since it's a distinct/special error surfaced on the
// grid, not a per-field header error — mirrors FormDepricarpingView.vue's
// validateDetailRows() exactly.
function isRowValid(row: KernelPlantDetailFormRow): boolean {
  return row.time_slot !== null && row.time_slot !== undefined && row.time_slot !== '' && isRowFilled(row)
}

function validateDetailRows(): boolean {
  const hasValidRow = detailRows.value.some(isRowValid)

  if (!hasValidRow) {
    detailRowsError.value =
      'Minimal 1 baris Kernel Plant Detail (Time-Slot terpilih + minimal 1 kolom bacaan terisi) harus diisi sebelum menyimpan.'
    return false
  }

  detailRowsError.value = null
  return true
}

// business_logic step 9 — 'Simpan'.
async function onSimpan(): Promise<void> {
  actionErrorMessage.value = null

  const headerValid = validateHeader()
  const detailValid = validateDetailRows()

  if (!headerValid || !detailValid) {
    return
  }

  saving.value = true

  try {
    await kernelPlantRecordRepo.saveDraft(
      recordId,
      { ...form },
      [...detailRows.value],
      [...pendingDeletionIds.value],
      authStore.currentUser?.role,
    )
    // Write-through saving (2026-09-14): push straight to the server
    // when this mill has it enabled. Silent by design — the record is
    // already saved locally, so a failure here just means it waits for
    // the next manual sync.
    await syncAfterSave('kernel_plant_record', recordId)
    router.push({ name: 'monitor-kernel-plant' })
  } catch (err) {
    if (err instanceof KernelPlantDetailRequiredError) {
      // Defense-in-depth fallback — validateDetailRows() above should
      // already have caught this client-side, but the repo enforces it
      // too.
      detailRowsError.value = err.message
      return
    }

    actionErrorMessage.value = err instanceof Error ? err.message : 'Gagal menyimpan data kernel plant.'
  } finally {
    saving.value = false
  }
}

// business_logic step 10 — 'Pause'. Checkpoint save, no required-field
// validation.
async function onPause(): Promise<void> {
  actionErrorMessage.value = null
  pausing.value = true

  try {
    await kernelPlantRecordRepo.pauseDraftWithFormData(
      recordId,
      { ...form },
      [...detailRows.value],
      [...pendingDeletionIds.value],
      authStore.currentUser?.role,
    )
    router.push({ name: 'monitor-kernel-plant' })
  } catch (err) {
    actionErrorMessage.value = err instanceof Error ? err.message : 'Gagal menyimpan progres (Pause).'
  } finally {
    pausing.value = false
  }
}

// business_logic step 11 — 'Clear'.
function onClearClick(): void {
  clearDialogOpen.value = true
}

async function onClearConfirm(): Promise<void> {
  clearDialogOpen.value = false
  actionErrorMessage.value = null
  clearing.value = true

  try {
    await kernelPlantRecordRepo.deleteDraft(recordId)
    router.push({ name: 'monitor-kernel-plant' })
  } catch (err) {
    actionErrorMessage.value = err instanceof Error ? err.message : 'Gagal menghapus draft kernel plant.'
  } finally {
    clearing.value = false
  }
}

function onClearCancel(): void {
  clearDialogOpen.value = false
}

function navigateBack(): void {
  router.push({ name: 'monitor-kernel-plant' })
}

// business_logic step 12 — 'Back'.
function onBackClick(): void {
  if (isDirty.value) {
    backDialogOpen.value = true
    return
  }

  navigateBack()
}

function onBackConfirm(): void {
  backDialogOpen.value = false
  navigateBack()
}

function onBackCancel(): void {
  backDialogOpen.value = false
}

// business_logic steps 7-8 — Checked By / Acknowledged By "Saya
// verifikasi..." toggles.
function toggleCheckedByMe(): void {
  if (!isSupervisor.value || actionInProgress.value) {
    return
  }

  form.checked_by = form.checked_by ? '' : authStore.currentUser?.id ?? ''
}

function toggleAcknowledgedByMe(): void {
  if (!isMillManagement.value || actionInProgress.value) {
    return
  }

  form.acknowledged_by = form.acknowledged_by ? '' : authStore.currentUser?.id ?? ''
}

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

function goToMonitorKernelPlant(): void {
  router.push({ name: 'monitor-kernel-plant' })
}
</script>

<template>
  <main class="form-kernel-plant-view">
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

    <div class="form-kernel-plant-header">
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
        <button type="button" class="breadcrumb-link" data-testid="breadcrumb-kernel-plant" @click="goToMonitorKernelPlant">
          Kernel Plant
        </button>
        <span class="breadcrumb-separator" aria-hidden="true">/</span>
        <span class="breadcrumb-current" aria-current="page">Form</span>
      </nav>
      <h1 class="screen-title">Form Kernel Plant</h1>
    </div>

    <p v-if="loading" class="status-text">Memuat draft kernel plant…</p>
    <p v-else-if="notFound" class="status-text status-text--error" role="alert">
      Draft kernel plant tidak ditemukan.
    </p>
    <p v-else-if="loadErrorMessage" class="status-text status-text--error" role="alert">
      {{ loadErrorMessage }}
    </p>

    <form v-else class="form-body" novalidate @submit.prevent="onSimpan">
      <div v-if="actionErrorMessage" class="banner banner-error" role="alert">{{ actionErrorMessage }}</div>

      <section class="form-section" aria-label="Identitas Kernel Plant">
        <h2 class="form-section-title">Identitas Kernel Plant</h2>

        <FormField
          v-model="form.kernel_plant_id"
          label="Kernel Plant ID"
          required
          :error="errors.kernel_plant_id"
          :disabled="actionInProgress"
        />
        <FormField :model-value="dateDisplay" label="Tanggal" disabled />
      </section>

      <section class="form-section" aria-label="Verifikasi">
        <h2 class="form-section-title">Verifikasi</h2>

        <div class="form-field">
          <span class="form-field-label">Inputted By</span>
          <p class="readonly-display" data-testid="inputted-by-display">{{ authStore.currentUser?.name ?? '-' }}</p>
        </div>

        <div v-if="isSupervisor" class="verify-toggle">
          <input
            id="checked-by-toggle"
            type="checkbox"
            data-testid="checked-by-toggle"
            :checked="Boolean(form.checked_by)"
            :disabled="actionInProgress"
            @change="toggleCheckedByMe"
          />
          <label for="checked-by-toggle">Saya verifikasi data ini (Checked By)</label>
        </div>

        <div v-if="isMillManagement" class="verify-toggle">
          <input
            id="acknowledged-by-toggle"
            type="checkbox"
            data-testid="acknowledged-by-toggle"
            :checked="Boolean(form.acknowledged_by)"
            :disabled="actionInProgress"
            @change="toggleAcknowledgedByMe"
          />
          <label for="acknowledged-by-toggle">Saya menyetujui data ini (Acknowledged By)</label>
        </div>

        <FormField v-model="form.note" label="Note" :disabled="actionInProgress" />
      </section>

      <section class="form-section" aria-label="Kernel Plant Detail">
        <h2 class="form-section-title">Kernel Plant Detail</h2>

        <p v-if="detailRowsError" class="form-field-error" data-testid="detail-rows-error" role="alert">
          {{ detailRowsError }}
        </p>

        <div
          v-for="(row, index) in detailRows"
          :key="row.id ?? `new-${index}`"
          class="detail-row"
          data-testid="kernel-plant-detail-row"
        >
          <div class="form-field">
            <label :for="`time-slot-${index}`" class="form-field-label">Time-Slot</label>
            <SearchableSelect
              :id="`time-slot-${index}`"
              :data-testid="`time-slot-select-${index}`"
              v-model="row.time_slot"
              :options="timeSlotSelectOptions(index)"
              placeholder="Pilih Time-Slot"
              :disabled="actionInProgress"
            />
          </div>

          <FormField
            v-model="row.ripple_mill_1_amps"
            label="Ripple Mill 1 (Amps)"
            type="number"
            :id="`ripple-mill-1-${index}`"
            :data-testid="`ripple-mill-1-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.ripple_mill_2_amps"
            label="Ripple Mill 2 (Amps)"
            type="number"
            :id="`ripple-mill-2-${index}`"
            :data-testid="`ripple-mill-2-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.claybath_hydro_sg"
            label="Claybath/Hydro SG"
            type="number"
            :id="`claybath-hydro-sg-${index}`"
            :data-testid="`claybath-hydro-sg-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.kernel_silo_1_temp_c"
            label="Kernel Silo 1 Temp (°C)"
            type="number"
            :id="`kernel-silo-1-temp-${index}`"
            :data-testid="`kernel-silo-1-temp-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.kernel_silo_2_temp_c"
            label="Kernel Silo 2 Temp (°C)"
            type="number"
            :id="`kernel-silo-2-temp-${index}`"
            :data-testid="`kernel-silo-2-temp-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.kernel_moisture_percent"
            label="Kernel Moisture (%)"
            type="number"
            :id="`kernel-moisture-${index}`"
            :data-testid="`kernel-moisture-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.shell_loss_percent"
            label="Shell Loss (%)"
            type="number"
            :id="`shell-loss-${index}`"
            :data-testid="`shell-loss-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.downtime_minutes"
            label="Downtime (Mins)"
            type="number"
            :id="`downtime-minutes-${index}`"
            :data-testid="`downtime-minutes-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.findings"
            label="Findings"
            :id="`findings-${index}`"
            :data-testid="`findings-${index}`"
            :disabled="actionInProgress"
          />

          <button
            type="button"
            class="detail-row-remove"
            data-testid="remove-detail-row-button"
            :disabled="actionInProgress"
            @click="removeDetailRow(index)"
          >
            Hapus baris
          </button>
        </div>

        <button
          type="button"
          class="action-button action-button--secondary"
          data-testid="add-detail-row-button"
          :disabled="actionInProgress || !canAddDetailRow"
          @click="addDetailRow"
        >
          Tambah baris
        </button>
        <p v-if="!canAddDetailRow" class="form-field-hint" data-testid="add-row-max-slots-hint">
          Seluruh 24 Time-Slot kanonis sudah digunakan.
        </p>
      </section>

      <CollapsibleSection title="Target Operasional">
        <p class="form-field-hint">Referensi baku mutu operasional — tidak termasuk data yang disimpan.</p>

        <table class="operational-target-table" data-testid="operational-target-table">
          <thead>
            <tr>
              <th>Equipment / Parameter</th>
              <th>Target Benchmark</th>
              <th>Corrective Action Plan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="target in KERNEL_PLANT_OPERATIONAL_TARGETS" :key="target.equipment_parameter">
              <td>{{ target.equipment_parameter }}</td>
              <td>{{ target.target_benchmark }}</td>
              <td>{{ target.corrective_action_plan }}</td>
            </tr>
          </tbody>
        </table>
      </CollapsibleSection>
    </form>

    <footer v-if="!loading && !notFound && !loadErrorMessage" class="action-footer">
      <div class="footer-actions">
        <button
          type="button"
          class="action-button action-button--icon"
          data-testid="back-button"
          title="Back"
          aria-label="Kembali ke Monitor tanpa menyimpan"
          :disabled="actionInProgress"
          @click="onBackClick"
        >
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="19" y1="12" x2="5" y2="12" />
            <polyline points="12 19 5 12 12 5" />
          </svg>
        </button>
        <button
          type="button"
          class="action-button action-button--warning"
          data-testid="pause-button"
          :disabled="actionInProgress"
          @click="onPause"
        >
          {{ pausing ? 'Menyimpan…' : 'Pause' }}
        </button>
        <button
          type="button"
          class="action-button action-button--secondary"
          data-testid="clear-button"
          :disabled="actionInProgress"
          @click="onClearClick"
        >
          {{ clearing ? 'Menghapus…' : 'Clear' }}
        </button>
        <button
          type="button"
          class="action-button action-button--primary"
          data-testid="save-button"
          :disabled="actionInProgress"
          @click="onSimpan"
        >
          {{ saving ? 'Menyimpan…' : 'Simpan' }}
        </button>
      </div>
    </footer>

    <ConfirmDialog
      :open="backDialogOpen"
      title="Perubahan Belum Disimpan"
      message="Ada perubahan yang belum disimpan. Yakin ingin keluar tanpa menyimpan?"
      confirm-label="Ya, Keluar"
      cancel-label="Batal"
      @confirm="onBackConfirm"
      @cancel="onBackCancel"
    />

    <ConfirmDialog
      :open="clearDialogOpen"
      title="Hapus Draft"
      message="Draft kernel plant ini akan dihapus secara permanen dan tidak dapat dikembalikan. Lanjutkan?"
      confirm-label="Ya, Hapus"
      cancel-label="Batal"
      @confirm="onClearConfirm"
      @cancel="onClearCancel"
    />
  </main>
</template>

<style scoped>
.form-kernel-plant-view {
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

.form-kernel-plant-header {
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

.status-text {
  font-size: 14px;
  color: #6b7280;
}

.status-text--error {
  color: #dc2626;
}

.form-body {
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex: 1;
}

.banner {
  min-height: 44px;
  display: flex;
  align-items: center;
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 14px;
}

.banner-error {
  background-color: #fee2e2;
  color: #dc2626;
}

.form-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-top: 16px;
}

.form-section-title {
  margin: 0;
  padding-bottom: 6px;
  border-bottom: 1px solid #e5e7eb;
  font-size: 13px;
  font-weight: 700;
  color: #249360;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-family: 'Inter', sans-serif;
}

.form-field-label {
  font-size: 14px;
  font-weight: 500;
  color: #1f2937;
}

.form-field-hint {
  margin: 0;
  color: #6b7280;
  font-size: 12px;
}

.form-field-error {
  margin: 0;
  color: #dc2626;
  font-size: 12px;
}

.readonly-display {
  min-height: 44px;
  display: flex;
  align-items: center;
  margin: 0;
  padding: 0 12px;
  background-color: #eef6f1;
  border-radius: 6px;
  font-size: 16px;
  color: #1f2937;
  box-sizing: border-box;
}

.verify-toggle {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
}

.verify-toggle input[type='checkbox'] {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

.verify-toggle label {
  font-size: 14px;
  font-weight: 500;
  color: #1f2937;
}

.detail-row {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}

.detail-row-remove {
  align-self: flex-start;
  flex: 0 0 auto;
  min-height: 40px;
  padding: 0 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background-color: #ffffff;
  color: #dc2626;
  font-size: 13px;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
}

.detail-row-remove:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.operational-target-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.operational-target-table th,
.operational-target-table td {
  border: 1px solid #e5e7eb;
  padding: 8px;
  text-align: left;
  vertical-align: top;
}

.operational-target-table th {
  background-color: #f7f7f7;
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

.footer-actions {
  display: flex;
  align-items: stretch;
  flex-wrap: wrap;
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

.action-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.action-button--icon {
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  padding: 0;
  border: 1px solid #e5e7eb;
  background-color: #ffffff;
  color: #6b7280;
}

.action-button--secondary {
  flex: 1 1 0;
  border: 1px solid #e5e7eb;
  background-color: #ffffff;
  color: #1f2937;
}

.action-button--warning {
  flex: 1 1 0;
  border: none;
  background-color: #d97706;
  color: #ffffff;
}

.action-button--primary {
  flex: 1 1 0;
  border: none;
  background-color: #249360;
  color: #ffffff;
  font-size: 16px;
}
</style>
