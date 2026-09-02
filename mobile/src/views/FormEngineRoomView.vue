<script setup lang="ts">
/**
 * FormEngineRoomView — screen-077--form-engine-room /
 * usecase-098--form-engine-room (mounted at
 * /stations/engine-room/form/:id, meta.public = false — requires an
 * authenticated session, enforced by the router's global auth guard; see
 * router/index.ts). Actors: operator, supervisor (Checked By interactive
 * only for supervisor, Acknowledged By interactive only for
 * mill_management).
 *
 * Mobile-only screen — no backend API endpoint; all data comes from the
 * local (offline) `engine_room_record` + `engine_room_detail` tables via
 * engineRoomRecordRepo.ts.
 *
 * Structural pattern (breadcrumb + hamburger header, sectioned form body,
 * dirty-tracking Back confirm, Pause/Clear/Simpan footer actions) mirrors
 * FormEffluentPlantView.vue exactly, including its dynamic add-row/
 * remove-row detail grid pattern:
 *
 *  - "Identitas Engine Room" section: `engine_room_id` (required, freely
 *    editable), `date` (auto-set once, in memory, ONLY for a brand-new
 *    draft — engineRoomRecordRepo.ts's createDraft() already auto-fills
 *    it at draft-creation time; this screen's populateForm() re-applies the
 *    same "only if still empty" rule defensively). Always rendered
 *    disabled.
 *
 *  - "Verifikasi" section: "Inputted By" is a plain read-only display of
 *    authStore.currentUser?.name. "Saya verifikasi data ini" (Checked By)
 *    is a checkbox toggle, interactive ONLY for supervisor. "Saya
 *    menyetujui data ini" (Acknowledged By) is the same toggle pattern,
 *    interactive ONLY for mill_management. Both re-enforced at the repo
 *    level (engineRoomRecordRepo.ts's saveDraft()/
 *    pauseDraftWithFormData() role-stripping) as defense-in-depth. `note`
 *    (optional free text) also lives in this section.
 *
 *  - "Engine Room Detail" section — the dynamic grid: `detailRows`
 *    (in-memory, rows without a saved `id` are new), `addDetailRow()`
 *    (pushes a fresh empty row), `removeDetailRow(index)` (splices the
 *    array; if the row had an `id`, its id is queued into
 *    `pendingDeletionIds` for the next save/pause — not deleted
 *    immediately), `availableTimeSlotOptions(index)` (dropdown options =
 *    all 24 canonical slots MINUS ones used by OTHER rows MINUS ones
 *    at-or-before the highest slot already picked by another row, always
 *    including the row's own current value). Each row's Time-Slot is a
 *    SearchableSelect dropdown; the 23 numeric reading fields (Steam
 *    Turbine ×6, Diesel Gen 1 ×4, Diesel Gen 2 ×4, Electrical Sync ×4, Air
 *    Compressor/Battery/Fuel/Energy ×4), 2 enum status dropdowns (Diesel
 *    Gen 1 Status, Diesel Gen 2 Status), and 2 free-text fields (Action
 *    Taken/Maintenance Remark, Findings) stay freely editable at all
 *    times — 27 non-time_slot columns total, the LARGEST field count of
 *    any station in this project (task brief originally said 26; the
 *    backend migration — ground truth — has 27; corrected here as a
 *    derived assumption). "Tambah baris" is disabled once all 24
 *    canonical slots are already used (`detailRows.length >= 24`) or while
 *    an action is in progress.
 *
 *  - UNLIKE Form Threshing: this station has NO operational-target
 *    reference table — no CollapsibleSection, no read-only reference data
 *    of any kind on this screen.
 *
 *  - 'Simpan': validates engine_room_id (required) — inline error — AND at
 *    least one valid Engine Room Detail row (a selected Time-Slot AND at
 *    least one reading column filled), a dedicated error message shown on
 *    the grid (not mixed into per-field header errors), re-enforced at the
 *    repo level (EngineRoomDetailRequiredError) — mirrors
 *    FormEffluentPlantView.vue's grid validation exactly. On success: calls
 *    engineRoomRecordRepo.saveDraft() (status='saved'), navigates to
 *    `monitor-engine-room`.
 *  - 'Pause': no validation at all, persists current field values as-is via
 *    engineRoomRecordRepo.pauseDraftWithFormData() (status='draft_paused'),
 *    navigates to `monitor-engine-room`.
 *  - 'Clear': ConfirmDialog.vue first; on confirm, calls
 *    engineRoomRecordRepo.deleteDraft() (cascades delete to every
 *    engine_room_detail row for the record, however many exist), then
 *    navigates to `monitor-engine-room`; on cancel, no change.
 *  - 'Back' with unsaved changes (header fields, detail rows, or queued row
 *    deletions differ from the loaded-draft snapshot) shows
 *    ConfirmDialog.vue before leaving; if not dirty, navigates directly.
 *
 * Header/breadcrumb/nav-menu: copied verbatim from FormEffluentPlantView.vue.
 * Breadcrumb is 4 segments deep (Home > Production Process Activity >
 * Engine Room > Form), the first 3 tappable.
 */
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useFloatingClockStore } from '@/stores/floatingClock'
import { useAiAssistantStore } from '@/stores/aiAssistant'
import engineRoomRecordRepo, {
  EngineRoomDetailRequiredError,
  type EngineRoomDetailFormRow,
  type EngineRoomDraftWithDetails,
  type EngineRoomHeaderFormData,
  type EngineRoomRecord,
} from '@/services/engineRoomRecordRepo'
import FormField from '@/components/FormField.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import SearchableSelect, { type SearchableSelectOption } from '@/components/SearchableSelect.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const floatingClockStore = useFloatingClockStore()
const aiAssistantStore = useAiAssistantStore()

const recordId = String(route.params.id ?? '')

// business_logic step 12 — required header fields. Note/Checked By/
// Acknowledged By are deliberately NOT required.
const REQUIRED_FIELDS: (keyof EngineRoomHeaderFormData)[] = ['engine_room_id']

const REQUIRED_FIELD_LABELS: Record<string, string> = {
  engine_room_id: 'Engine Room ID',
}

// business_logic — fields compared for the Back dirty-check. `date` is
// excluded (fully automatic/disabled, never freely user-editable — same
// reasoning as FormEffluentPlantView.vue excluding date).
const DIRTY_CHECK_FIELDS: (keyof EngineRoomHeaderFormData)[] = [
  'engine_room_id',
  'note',
  'checked_by',
  'acknowledged_by',
]

function emptyFormData(): EngineRoomHeaderFormData {
  return {
    engine_room_id: '',
    date: '',
    note: '',
    checked_by: '',
    acknowledged_by: '',
  }
}

function emptyDetailRow(): EngineRoomDetailFormRow {
  return {
    time_slot: null,
    steam_turbine_inlet_pressure_bar: null,
    steam_turbine_inlet_temp_c: null,
    steam_turbine_exhaust_pressure_bar: null,
    steam_turbine_rpm: null,
    steam_turbine_alternator_bearing_temp_1_c: null,
    steam_turbine_alternator_bearing_temp_2_c: null,
    diesel_gen_1_status: null,
    diesel_gen_1_load_kw: null,
    diesel_gen_1_amperage_a: null,
    diesel_gen_1_jacket_water_temp_c: null,
    diesel_gen_1_lube_oil_pressure_bar: null,
    diesel_gen_2_status: null,
    diesel_gen_2_load_kw: null,
    diesel_gen_2_amperage_a: null,
    diesel_gen_2_jacket_water_temp_c: null,
    diesel_gen_2_lube_oil_pressure_bar: null,
    electrical_sync_total_factory_load_kw: null,
    electrical_sync_system_frequency_hz: null,
    electrical_sync_power_factor: null,
    electrical_sync_busbar_voltage_v: null,
    air_compressor_1_pressure_bar: null,
    compressor_2_pressure_bar: null,
    battery_charger_ups_voltage_v: null,
    fuel_tank_level: null,
    daily_energy_export_kwh: null,
    action_taken_maintenance_remark: null,
    findings: null,
  }
}

const form = reactive<EngineRoomHeaderFormData>(emptyFormData())
const errors = reactive<Partial<Record<keyof EngineRoomHeaderFormData, string>>>({})
const detailRows = ref<EngineRoomDetailFormRow[]>([])
// business_logic — ids of existing (has-`id`) detail rows removed via
// "Hapus baris" this session; only actually DELETEd by the repo on the
// next Simpan/Pause call (mirrors FormEffluentPlantView.vue's
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
// surfaced on the Engine Room Detail section rather than mixed into the
// per-field header `errors` object above.
const detailRowsError = ref<string | null>(null)
const backDialogOpen = ref(false)
const clearDialogOpen = ref(false)

const actionInProgress = computed(() => saving.value || pausing.value || clearing.value)

const isSupervisor = computed(() => authStore.currentUser?.role === 'supervisor')
const isMillManagement = computed(() => authStore.currentUser?.role === 'mill_management')

// Snapshot of {header, rows, pendingDeletions} right after loading, used
// purely for the Back dirty-check — compared as JSON, same approach as
// FormEffluentPlantView.vue's `isDirty`.
let loadedSnapshot = dirtySnapshot()

function dirtySnapshot(): string {
  const header: Partial<Record<keyof EngineRoomHeaderFormData, string>> = {}

  for (const key of DIRTY_CHECK_FIELDS) {
    header[key] = form[key]
  }

  const rows = detailRows.value.map((row) => ({ ...row, id: row.id ?? null }))

  return JSON.stringify({ header, rows, pendingDeletions: [...pendingDeletionIds.value] })
}

const isDirty = computed(() => dirtySnapshot() !== loadedSnapshot)

/**
 * Builds a local (device timezone) date-time string, e.g.
 * "2026-08-31T14:30:00" — deliberately NOT `new Date().toISOString()`,
 * which is UTC and can land on a different calendar day/hour than the
 * device's local time. Mirrors FormEffluentPlantView.vue's
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
function populateForm(record: EngineRoomRecord): void {
  form.engine_room_id = record.engine_room_id ?? ''
  form.date = record.date && record.date.trim() !== '' ? record.date : nowLocalDateTimeString()
  form.note = record.note ?? ''
  form.checked_by = record.checked_by ?? ''
  form.acknowledged_by = record.acknowledged_by ?? ''
}

function populateDetailRows(rows: EngineRoomDraftWithDetails['details']): void {
  detailRows.value = rows.map((row) => ({
    id: row.id,
    time_slot: row.time_slot ?? null,
    steam_turbine_inlet_pressure_bar: row.steam_turbine_inlet_pressure_bar ?? null,
    steam_turbine_inlet_temp_c: row.steam_turbine_inlet_temp_c ?? null,
    steam_turbine_exhaust_pressure_bar: row.steam_turbine_exhaust_pressure_bar ?? null,
    steam_turbine_rpm: row.steam_turbine_rpm ?? null,
    steam_turbine_alternator_bearing_temp_1_c: row.steam_turbine_alternator_bearing_temp_1_c ?? null,
    steam_turbine_alternator_bearing_temp_2_c: row.steam_turbine_alternator_bearing_temp_2_c ?? null,
    diesel_gen_1_status: row.diesel_gen_1_status ?? '',
    diesel_gen_1_load_kw: row.diesel_gen_1_load_kw ?? null,
    diesel_gen_1_amperage_a: row.diesel_gen_1_amperage_a ?? null,
    diesel_gen_1_jacket_water_temp_c: row.diesel_gen_1_jacket_water_temp_c ?? null,
    diesel_gen_1_lube_oil_pressure_bar: row.diesel_gen_1_lube_oil_pressure_bar ?? null,
    diesel_gen_2_status: row.diesel_gen_2_status ?? '',
    diesel_gen_2_load_kw: row.diesel_gen_2_load_kw ?? null,
    diesel_gen_2_amperage_a: row.diesel_gen_2_amperage_a ?? null,
    diesel_gen_2_jacket_water_temp_c: row.diesel_gen_2_jacket_water_temp_c ?? null,
    diesel_gen_2_lube_oil_pressure_bar: row.diesel_gen_2_lube_oil_pressure_bar ?? null,
    electrical_sync_total_factory_load_kw: row.electrical_sync_total_factory_load_kw ?? null,
    electrical_sync_system_frequency_hz: row.electrical_sync_system_frequency_hz ?? null,
    electrical_sync_power_factor: row.electrical_sync_power_factor ?? null,
    electrical_sync_busbar_voltage_v: row.electrical_sync_busbar_voltage_v ?? null,
    air_compressor_1_pressure_bar: row.air_compressor_1_pressure_bar ?? null,
    compressor_2_pressure_bar: row.compressor_2_pressure_bar ?? null,
    battery_charger_ups_voltage_v: row.battery_charger_ups_voltage_v ?? null,
    fuel_tank_level: row.fuel_tank_level ?? null,
    daily_energy_export_kwh: row.daily_energy_export_kwh ?? null,
    action_taken_maintenance_remark: row.action_taken_maintenance_remark ?? '',
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
    const draft = await engineRoomRecordRepo.getDraftWithDetails(recordId)

    if (!draft) {
      notFound.value = true
      return
    }

    populateForm(draft.record)
    populateDetailRows(draft.details)
    pendingDeletionIds.value = []
    loadedSnapshot = dirtySnapshot()
  } catch (err) {
    loadErrorMessage.value = err instanceof Error ? err.message : 'Gagal memuat draft engine room.'
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
 * 07:00) — slots must be added in strictly ascending canonical order,
 * mirrors FormEffluentPlantView.vue's `availableTimeSlotOptions()` exactly.
 * Both exclusions exempt the row's OWN current selection. Reactive over
 * the entire `detailRows.value` array.
 */
function availableTimeSlotOptions(index: number): string[] {
  const row = detailRows.value[index]

  if (!row) {
    return []
  }

  const order = engineRoomRecordRepo.canonicalTimeSlots()

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

// SearchableSelect never computes availability itself — it's always
// handed the already-filtered list to show right now, same pattern
// FormEffluentPlantView.vue's timeSlotSelectOptions() uses for its own
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
  detailRows.value.push(emptyDetailRow())
}

// business_logic step 9 — "Hapus baris". A row with an `id` (loaded from
// an existing draft) is queued for deletion, not deleted immediately; a
// row with no `id` (added this session) is just dropped from local state
// — mirrors FormEffluentPlantView.vue's removeDetailRow() exactly. Either
// way, the row's time-slot is immediately freed for other rows' dropdowns
// (falls out of availableTimeSlotOptions() automatically once the row is
// gone from detailRows.value).
function removeDetailRow(index: number): void {
  const row = detailRows.value[index]

  if (row?.id) {
    pendingDeletionIds.value.push(row.id)
  }

  detailRows.value.splice(index, 1)
}

// business_logic step 12 — required-field validation with inline
// per-field errors.
function validateHeader(): boolean {
  for (const key of Object.keys(errors) as (keyof EngineRoomHeaderFormData)[]) {
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

function isRowFilled(row: EngineRoomDetailFormRow): boolean {
  return (
    row.steam_turbine_inlet_pressure_bar !== null ||
    row.steam_turbine_inlet_temp_c !== null ||
    row.steam_turbine_exhaust_pressure_bar !== null ||
    row.steam_turbine_rpm !== null ||
    row.steam_turbine_alternator_bearing_temp_1_c !== null ||
    row.steam_turbine_alternator_bearing_temp_2_c !== null ||
    (row.diesel_gen_1_status !== null && row.diesel_gen_1_status !== '') ||
    row.diesel_gen_1_load_kw !== null ||
    row.diesel_gen_1_amperage_a !== null ||
    row.diesel_gen_1_jacket_water_temp_c !== null ||
    row.diesel_gen_1_lube_oil_pressure_bar !== null ||
    (row.diesel_gen_2_status !== null && row.diesel_gen_2_status !== '') ||
    row.diesel_gen_2_load_kw !== null ||
    row.diesel_gen_2_amperage_a !== null ||
    row.diesel_gen_2_jacket_water_temp_c !== null ||
    row.diesel_gen_2_lube_oil_pressure_bar !== null ||
    row.electrical_sync_total_factory_load_kw !== null ||
    row.electrical_sync_system_frequency_hz !== null ||
    row.electrical_sync_power_factor !== null ||
    row.electrical_sync_busbar_voltage_v !== null ||
    row.air_compressor_1_pressure_bar !== null ||
    row.compressor_2_pressure_bar !== null ||
    row.battery_charger_ups_voltage_v !== null ||
    row.fuel_tank_level !== null ||
    row.daily_energy_export_kwh !== null ||
    (row.action_taken_maintenance_remark !== null && row.action_taken_maintenance_remark !== '') ||
    (row.findings !== null && row.findings !== '')
  )
}

// business_logic step 12 — at-least-one-valid-row validation (a selected
// Time-Slot AND at least one filled reading column), kept separate from
// validateHeader() since it's a distinct/special error surfaced on the
// grid, not a per-field header error — mirrors FormEffluentPlantView.vue's
// validateDetailRows() exactly.
function isRowValid(row: EngineRoomDetailFormRow): boolean {
  return row.time_slot !== null && row.time_slot !== undefined && row.time_slot !== '' && isRowFilled(row)
}

function validateDetailRows(): boolean {
  const hasValidRow = detailRows.value.some(isRowValid)

  if (!hasValidRow) {
    detailRowsError.value =
      'Minimal 1 baris Engine Room Detail (Time-Slot terpilih + minimal 1 kolom bacaan terisi) harus diisi sebelum menyimpan.'
    return false
  }

  detailRowsError.value = null
  return true
}

// business_logic step 12 — 'Simpan'.
async function onSimpan(): Promise<void> {
  actionErrorMessage.value = null

  const headerValid = validateHeader()
  const detailValid = validateDetailRows()

  if (!headerValid || !detailValid) {
    return
  }

  saving.value = true

  try {
    await engineRoomRecordRepo.saveDraft(
      recordId,
      { ...form },
      [...detailRows.value],
      [...pendingDeletionIds.value],
      authStore.currentUser?.role,
    )
    router.push({ name: 'monitor-engine-room' })
  } catch (err) {
    if (err instanceof EngineRoomDetailRequiredError) {
      // Defense-in-depth fallback — validateDetailRows() above should
      // already have caught this client-side, but the repo enforces it
      // too.
      detailRowsError.value = err.message
      return
    }

    actionErrorMessage.value = err instanceof Error ? err.message : 'Gagal menyimpan data engine room.'
  } finally {
    saving.value = false
  }
}

// business_logic step 13 — 'Pause'. Checkpoint save, no required-field
// validation.
async function onPause(): Promise<void> {
  actionErrorMessage.value = null
  pausing.value = true

  try {
    await engineRoomRecordRepo.pauseDraftWithFormData(
      recordId,
      { ...form },
      [...detailRows.value],
      [...pendingDeletionIds.value],
      authStore.currentUser?.role,
    )
    router.push({ name: 'monitor-engine-room' })
  } catch (err) {
    actionErrorMessage.value = err instanceof Error ? err.message : 'Gagal menyimpan progres (Pause).'
  } finally {
    pausing.value = false
  }
}

// business_logic step 14 — 'Clear'.
function onClearClick(): void {
  clearDialogOpen.value = true
}

async function onClearConfirm(): Promise<void> {
  clearDialogOpen.value = false
  actionErrorMessage.value = null
  clearing.value = true

  try {
    await engineRoomRecordRepo.deleteDraft(recordId)
    router.push({ name: 'monitor-engine-room' })
  } catch (err) {
    actionErrorMessage.value = err instanceof Error ? err.message : 'Gagal menghapus draft engine room.'
  } finally {
    clearing.value = false
  }
}

function onClearCancel(): void {
  clearDialogOpen.value = false
}

function navigateBack(): void {
  router.push({ name: 'monitor-engine-room' })
}

// business_logic step 15 — 'Back'.
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

// business_logic steps 10-11 — Checked By / Acknowledged By "Saya
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

function goToMonitorEngineRoom(): void {
  router.push({ name: 'monitor-engine-room' })
}
</script>

<template>
  <main class="form-engine-room-view">
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

    <div class="form-engine-room-header">
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
        <button type="button" class="breadcrumb-link" data-testid="breadcrumb-engine-room" @click="goToMonitorEngineRoom">
          Engine Room
        </button>
        <span class="breadcrumb-separator" aria-hidden="true">/</span>
        <span class="breadcrumb-current" aria-current="page">Form</span>
      </nav>
      <h1 class="screen-title">Form Engine Room</h1>
    </div>

    <p v-if="loading" class="status-text">Memuat draft engine room…</p>
    <p v-else-if="notFound" class="status-text status-text--error" role="alert">
      Draft engine room tidak ditemukan.
    </p>
    <p v-else-if="loadErrorMessage" class="status-text status-text--error" role="alert">
      {{ loadErrorMessage }}
    </p>

    <form v-else class="form-body" novalidate @submit.prevent="onSimpan">
      <div v-if="actionErrorMessage" class="banner banner-error" role="alert">{{ actionErrorMessage }}</div>

      <section class="form-section" aria-label="Identitas Engine Room">
        <h2 class="form-section-title">Identitas Engine Room</h2>

        <FormField
          v-model="form.engine_room_id"
          label="Engine Room ID"
          required
          :error="errors.engine_room_id"
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

        <div class="verify-toggle">
          <input
            id="checked-by-toggle"
            type="checkbox"
            data-testid="checked-by-toggle"
            :checked="Boolean(form.checked_by)"
            :disabled="!isSupervisor || actionInProgress"
            @change="toggleCheckedByMe"
          />
          <label for="checked-by-toggle">Saya verifikasi data ini (Checked By)</label>
        </div>

        <div class="verify-toggle">
          <input
            id="acknowledged-by-toggle"
            type="checkbox"
            data-testid="acknowledged-by-toggle"
            :checked="Boolean(form.acknowledged_by)"
            :disabled="!isMillManagement || actionInProgress"
            @change="toggleAcknowledgedByMe"
          />
          <label for="acknowledged-by-toggle">Saya menyetujui data ini (Acknowledged By)</label>
        </div>

        <FormField v-model="form.note" label="Note" :disabled="actionInProgress" />
      </section>

      <section class="form-section" aria-label="Engine Room Detail">
        <h2 class="form-section-title">Engine Room Detail</h2>

        <p v-if="detailRowsError" class="form-field-error" data-testid="detail-rows-error" role="alert">
          {{ detailRowsError }}
        </p>

        <div
          v-for="(row, index) in detailRows"
          :key="row.id ?? `new-${index}`"
          class="detail-row"
          data-testid="engine-room-detail-row"
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
            v-model="row.steam_turbine_inlet_pressure_bar"
            label="Steam Turbine Inlet Pressure (bar)"
            type="number"
            :id="`steam-inlet-pressure-${index}`"
            :data-testid="`steam-inlet-pressure-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.steam_turbine_inlet_temp_c"
            label="Steam Turbine Inlet Temp (°C)"
            type="number"
            :id="`steam-inlet-temp-${index}`"
            :data-testid="`steam-inlet-temp-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.steam_turbine_exhaust_pressure_bar"
            label="Steam Turbine Exhaust Pressure (bar)"
            type="number"
            :id="`steam-exhaust-pressure-${index}`"
            :data-testid="`steam-exhaust-pressure-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.steam_turbine_rpm"
            label="Steam Turbine RPM"
            type="number"
            :id="`steam-rpm-${index}`"
            :data-testid="`steam-rpm-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.steam_turbine_alternator_bearing_temp_1_c"
            label="Steam Turbine Alternator Bearing Temp 1 (°C)"
            type="number"
            :id="`steam-bearing-temp-1-${index}`"
            :data-testid="`steam-bearing-temp-1-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.steam_turbine_alternator_bearing_temp_2_c"
            label="Steam Turbine Alternator Bearing Temp 2 (°C)"
            type="number"
            :id="`steam-bearing-temp-2-${index}`"
            :data-testid="`steam-bearing-temp-2-${index}`"
            :disabled="actionInProgress"
          />
          <div class="form-field">
            <label :for="`diesel-gen-1-status-${index}`" class="form-field-label">Diesel Gen 1 Status</label>
            <select
              :id="`diesel-gen-1-status-${index}`"
              :data-testid="`diesel-gen-1-status-${index}`"
              v-model="row.diesel_gen_1_status"
              class="native-select"
              :disabled="actionInProgress"
            >
              <option value="">-- Pilih --</option>
              <option value="run">Run</option>
              <option value="standby">Standby</option>
              <option value="off">Off</option>
            </select>
          </div>
          <FormField
            v-model="row.diesel_gen_1_load_kw"
            label="Diesel Gen 1 Load (kW)"
            type="number"
            :id="`diesel-gen-1-load-${index}`"
            :data-testid="`diesel-gen-1-load-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.diesel_gen_1_amperage_a"
            label="Diesel Gen 1 Amperage (A)"
            type="number"
            :id="`diesel-gen-1-amperage-${index}`"
            :data-testid="`diesel-gen-1-amperage-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.diesel_gen_1_jacket_water_temp_c"
            label="Diesel Gen 1 Jacket Water Temp (°C)"
            type="number"
            :id="`diesel-gen-1-jacket-temp-${index}`"
            :data-testid="`diesel-gen-1-jacket-temp-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.diesel_gen_1_lube_oil_pressure_bar"
            label="Diesel Gen 1 Lube Oil Pressure (bar)"
            type="number"
            :id="`diesel-gen-1-lube-oil-${index}`"
            :data-testid="`diesel-gen-1-lube-oil-${index}`"
            :disabled="actionInProgress"
          />
          <div class="form-field">
            <label :for="`diesel-gen-2-status-${index}`" class="form-field-label">Diesel Gen 2 Status</label>
            <select
              :id="`diesel-gen-2-status-${index}`"
              :data-testid="`diesel-gen-2-status-${index}`"
              v-model="row.diesel_gen_2_status"
              class="native-select"
              :disabled="actionInProgress"
            >
              <option value="">-- Pilih --</option>
              <option value="run">Run</option>
              <option value="standby">Standby</option>
              <option value="off">Off</option>
            </select>
          </div>
          <FormField
            v-model="row.diesel_gen_2_load_kw"
            label="Diesel Gen 2 Load (kW)"
            type="number"
            :id="`diesel-gen-2-load-${index}`"
            :data-testid="`diesel-gen-2-load-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.diesel_gen_2_amperage_a"
            label="Diesel Gen 2 Amperage (A)"
            type="number"
            :id="`diesel-gen-2-amperage-${index}`"
            :data-testid="`diesel-gen-2-amperage-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.diesel_gen_2_jacket_water_temp_c"
            label="Diesel Gen 2 Jacket Water Temp (°C)"
            type="number"
            :id="`diesel-gen-2-jacket-temp-${index}`"
            :data-testid="`diesel-gen-2-jacket-temp-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.diesel_gen_2_lube_oil_pressure_bar"
            label="Diesel Gen 2 Lube Oil Pressure (bar)"
            type="number"
            :id="`diesel-gen-2-lube-oil-${index}`"
            :data-testid="`diesel-gen-2-lube-oil-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.electrical_sync_total_factory_load_kw"
            label="Electrical Sync Total Factory Load (kW)"
            type="number"
            :id="`sync-total-load-${index}`"
            :data-testid="`sync-total-load-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.electrical_sync_system_frequency_hz"
            label="Electrical Sync System Frequency (Hz)"
            type="number"
            :id="`sync-frequency-${index}`"
            :data-testid="`sync-frequency-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.electrical_sync_power_factor"
            label="Electrical Sync Power Factor (PF)"
            type="number"
            :id="`sync-power-factor-${index}`"
            :data-testid="`sync-power-factor-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.electrical_sync_busbar_voltage_v"
            label="Electrical Sync Busbar Voltage (V)"
            type="number"
            :id="`sync-busbar-voltage-${index}`"
            :data-testid="`sync-busbar-voltage-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.air_compressor_1_pressure_bar"
            label="Air Compressor 1 Pressure (bar)"
            type="number"
            :id="`air-compressor-1-pressure-${index}`"
            :data-testid="`air-compressor-1-pressure-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.compressor_2_pressure_bar"
            label="Compressor 2 Pressure (bar)"
            type="number"
            :id="`compressor-2-pressure-${index}`"
            :data-testid="`compressor-2-pressure-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.battery_charger_ups_voltage_v"
            label="Battery Charger/UPS Voltage (V)"
            type="number"
            :id="`battery-charger-ups-voltage-${index}`"
            :data-testid="`battery-charger-ups-voltage-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.fuel_tank_level"
            label="Fuel Tank Level (Liters/%)"
            type="number"
            :id="`fuel-tank-level-${index}`"
            :data-testid="`fuel-tank-level-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.daily_energy_export_kwh"
            label="Daily Energy Export (kWh)"
            type="number"
            :id="`daily-energy-export-${index}`"
            :data-testid="`daily-energy-export-${index}`"
            :disabled="actionInProgress"
          />
          <FormField
            v-model="row.action_taken_maintenance_remark"
            label="Action Taken/Maintenance Remark"
            :id="`action-taken-remark-${index}`"
            :data-testid="`action-taken-remark-${index}`"
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
      message="Draft engine room ini akan dihapus secara permanen dan tidak dapat dikembalikan. Lanjutkan?"
      confirm-label="Ya, Hapus"
      cancel-label="Batal"
      @confirm="onClearConfirm"
      @cancel="onClearCancel"
    />
  </main>
</template>

<style scoped>
.form-engine-room-view {
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

.form-engine-room-header {
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

.native-select {
  min-height: 44px;
  padding: 0 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background-color: #ffffff;
  color: #1f2937;
  font-size: 16px;
  font-family: inherit;
  box-sizing: border-box;
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
