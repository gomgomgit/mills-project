<script setup lang="ts">
/**
 * FormGradingView — screen-011--form-grading / usecase-011--form-grading
 * (mounted at /stations/grading/form/:id, meta.public = false — requires
 * an authenticated session, enforced by the router's global auth guard;
 * see router/index.ts). Actors: operator, supervisor.
 *
 * Mobile-only screen — no backend API endpoint (api_contracts.endpoints
 * is empty); all data comes from the local (offline) `grading_record` +
 * `grading_detail` tables via gradingRecordRepo.ts, plus two read-only
 * local reference lists (`weighbridge_record`, `grading_parameter`) for
 * this screen's two dropdowns.
 *
 * FULL REWRITE (2026-08-19, entity-catalog v2): replaces the previous
 * placeholder built against grading_record/grading_detail's pre-v2 shape
 * (vehicle_number/driver_name/block + free-text detail `category`, no WB
 * Card No reference, no Quality Parameter dropdown, no Pause/Clear — see
 * gradingRecordRepo.ts's own "entity-catalog v2 rewrite" update note for
 * the full column-level rundown). Structural pattern (breadcrumb +
 * hamburger header, sectioned form body, dirty-tracking Back confirm,
 * Pause/Clear/Simpan footer actions) mirrors FormWeighbridgeView.vue's
 * own 2026-08-18 rewrite as closely as sensible, adapted to grading's
 * different fields/detail grid:
 *  - No Checked By/Acknowledged By section at all (mirrors
 *    FormWeighbridgeView.vue's removal of Checked By) — grading_record
 *    still has both columns, this screen simply never collects/writes
 *    either (see gradingRecordRepo.ts's saveDraft()/
 *    pauseDraftWithFormData(), which leave them untouched).
 *  - "Date" (grading_record.date) is auto-set ONCE, in memory, only for a
 *    brand-new draft (no stored `date` yet) — a resumed draft's stored
 *    value is kept as-is, same "auto-fill-once" shape as
 *    FormWeighbridgeView.vue's Arrival. Built from LOCAL Date getters
 *    (`nowLocalDateTimeString()` below), never `toISOString()` — that's
 *    UTC and can render a different calendar day than the device's local
 *    day. Always rendered disabled, never user-editable.
 *  - "WB Card No" is a <select> over every local weighbridge_record (any
 *    status, most-recently-arrived first, via
 *    gradingRecordRepo.getWeighbridgeRecordOptions()). Selecting an
 *    option auto-fills License Plate No / Estate / Divisi from that
 *    weighbridge_record's vehicle_number/estate_supplier/division — but
 *    ONLY at the moment of selection (an @change handler, not a
 *    reactive/computed binding), so those three fields stay freely
 *    editable afterward and are never silently reset by anything else
 *    (including re-rendering, or the same option remaining selected).
 *  - Each Grading Detail row's "Quality Parameter" is a <select> over
 *    every local grading_parameter (ordered by sort_order, via
 *    gradingRecordRepo.getGradingParameterOptions()). Selecting one
 *    snapshots that parameter's `uom` onto the row (disabled/read-only
 *    display for that row from then on — not a live join). Percentage
 *    per row is a pure computed (`rowPercentage()` below): qty/header
 *    netto*100 when uom='kg', qty/header quantity*100 when uom='bunch';
 *    recomputes whenever that row's own qty changes OR the header's
 *    netto/quantity changes (header-level denominator affects every row).
 *  - "Hapus baris" on a row that has an `id` (loaded from an existing
 *    draft) does NOT delete immediately — it's queued in
 *    `pendingDeletionIds` and only actually DELETEd by the repo on the
 *    next Simpan/Pause call. A row with no `id` (added this session) is
 *    just dropped from local state, nothing to queue.
 *  - 'Simpan': validates required header fields (Grading No, WB Card No,
 *    Vehicle Code, Estate, Netto, Quantity — License Plate No/Divisi/Note
 *    are NOT required) — inline error per field — AND at least one valid
 *    detail row (Quality Parameter selected + Qty filled), a dedicated
 *    error message shown on the grid (not mixed into the per-field header
 *    errors), re-enforced at the repo level
 *    (`GradingDetailRequiredError`) so the rule holds even if this
 *    client-side check is bypassed. On success: calls
 *    gradingRecordRepo.saveDraft() (status='saved'), navigates to
 *    `monitor-grading`.
 *  - 'Pause': no validation at all, persists current field values as-is
 *    via gradingRecordRepo.pauseDraftWithFormData() (status=
 *    'draft_paused'), navigates to `monitor-grading`.
 *  - 'Clear': ConfirmDialog.vue first; on confirm, calls the screen-008
 *    block's already-shared gradingRecordRepo.deleteDraft() (deletes
 *    every grading_detail row for the record, then the grading_record row
 *    itself — exactly what Clear needs, no new repo function required),
 *    then navigates to `monitor-grading`; on cancel, no change.
 *  - 'Back' with unsaved changes (header fields, detail rows, or queued
 *    row deletions differ from the loaded-draft snapshot) shows
 *    ConfirmDialog.vue before leaving; if not dirty, navigates directly.
 *
 * Header/breadcrumb/nav-menu: copied verbatim (structure, class names,
 * composable/method naming — isNavMenuOpen/toggleNavMenu/closeNavMenu/
 * goToChangePassword/onLogout) from FormWeighbridgeView.vue /
 * MonitorGradingView.vue so all mobile station screens stay visually/
 * behaviorally consistent. Breadcrumb is 4 segments deep (Home >
 * Production Process Activity > Grading > Form), the first 3 tappable —
 * same "N-1 tappable, current page plain text" convention
 * FormWeighbridgeView.vue uses for its own 4-segment breadcrumb one level
 * below its Monitor screen (MonitorGradingView.vue's breadcrumb is 3
 * segments deep, 2 tappable, one level above this screen).
 *
 * GradingDetailGrid.vue (the old category/quantity-shaped shared grid
 * component) is deliberately NOT reused here — its props/emit contract is
 * built around the pre-v2 `category` free-text shape and does not support
 * a per-row Quality Parameter dropdown + disabled UOM + computed
 * Percentage. It remains untouched and in use by DataPreviewGradingView.vue
 * (screen-014, out of scope for this rewrite) — see known_issues.
 */
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useFloatingClockStore } from '@/stores/floatingClock'
import gradingRecordRepo, {
  GradingDetailRequiredError,
  type GradingDetailFormRow,
  type GradingDraftWithDetails,
  type GradingHeaderFormData,
  type GradingParameterOption,
  type WeighbridgeRecordOption,
} from '@/services/gradingRecordRepo'
import FormField from '@/components/FormField.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import SearchableSelect, { type SearchableSelectOption } from '@/components/SearchableSelect.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const floatingClockStore = useFloatingClockStore()

const recordId = String(route.params.id ?? '')

// business_logic — required header fields per this screen's tech spec.
// License Plate No / Divisi / Note are deliberately NOT required (License
// Plate No is auto-filled but still editable/optional; Divisi/Note are
// plain optional fields, same "some fields stay optional" shape as
// FormWeighbridgeView.vue's division/block).
const REQUIRED_FIELDS: (keyof GradingHeaderFormData)[] = [
  'grading_number',
  'weighbridge_record_id',
  'estate_supplier',
  'netto',
  'quantity',
]

const REQUIRED_FIELD_LABELS: Record<string, string> = {
  grading_number: 'Grading No',
  weighbridge_record_id: 'WB Card No',
  estate_supplier: 'Estate',
  netto: 'Netto',
  quantity: 'Quantity',
}

// business_logic — fields compared for the Back dirty-check. `date` is
// deliberately excluded (fully automatic/disabled, never user-editable —
// same reasoning as FormWeighbridgeView.vue excluding arrival_datetime/
// dispatch_datetime from its own dirty-check).
const DIRTY_CHECK_FIELDS: (keyof GradingHeaderFormData)[] = [
  'grading_number',
  'weighbridge_record_id',
  'license_plate_no',
  'vehicle_code',
  'estate_supplier',
  'division',
  'netto',
  'quantity',
  'note',
]

function emptyFormData(): GradingHeaderFormData {
  return {
    grading_number: '',
    date: '',
    weighbridge_record_id: '',
    license_plate_no: '',
    vehicle_code: '',
    estate_supplier: '',
    division: '',
    netto: null,
    quantity: null,
    note: '',
  }
}

const form = reactive<GradingHeaderFormData>(emptyFormData())
const errors = reactive<Partial<Record<keyof GradingHeaderFormData, string>>>({})
const detailRows = ref<GradingDetailFormRow[]>([])
// business_logic — ids of existing (has-`id`) detail rows removed via
// "Hapus baris" this session; only actually DELETEd by the repo on the
// next Simpan/Pause call (see this file's header comment).
const pendingDeletionIds = ref<string[]>([])

const loading = ref(true)
const saving = ref(false)
const pausing = ref(false)
const clearing = ref(false)
const notFound = ref(false)
const loadErrorMessage = ref<string | null>(null)
const actionErrorMessage = ref<string | null>(null)
// business_logic — distinct/special error for "zero valid detail rows",
// surfaced on the Grading Detail section rather than mixed into the
// per-field header `errors` object above (same split as the old
// pre-rewrite version of this screen).
const detailRowsError = ref<string | null>(null)
const backDialogOpen = ref(false)
const clearDialogOpen = ref(false)

const actionInProgress = computed(() => saving.value || pausing.value || clearing.value)

const wbOptions = ref<WeighbridgeRecordOption[]>([])
const wbOptionsError = ref<string | null>(null)
const parameterOptions = ref<GradingParameterOption[]>([])
const parameterOptionsError = ref<string | null>(null)

// Snapshot of {header, rows, pendingDeletions} right after loading, used
// purely for the Back dirty-check — compared as JSON, same approach as
// FormWeighbridgeView.vue's `isDirty` / this screen's own pre-rewrite
// `isDirty`.
let loadedSnapshot = dirtySnapshot()

function dirtySnapshot(): string {
  const header: Partial<Record<keyof GradingHeaderFormData, string | number | null>> = {}

  for (const key of DIRTY_CHECK_FIELDS) {
    header[key] = form[key]
  }

  const rows = detailRows.value.map((row) => ({
    id: row.id ?? null,
    grading_parameter_id: row.grading_parameter_id,
    quantity: row.quantity,
  }))

  return JSON.stringify({ header, rows, pendingDeletions: [...pendingDeletionIds.value] })
}

const isDirty = computed(() => dirtySnapshot() !== loadedSnapshot)

/**
 * Builds a local (device timezone) date-time string, e.g.
 * "2026-08-19T14:30:00" — deliberately NOT `new Date().toISOString()`,
 * which is UTC and can land on a different calendar day/hour than the
 * device's local time. Mirrors DataPreviewWeighbridgeView.vue's
 * `todayLocalDateString()` (built manually from local Date getters, not
 * a UTC-based string slice), extended here with time-of-day since
 * `grading_record.date` stores a full timestamp (see localSchema.ts's
 * CREATE_GRADING_RECORD comment). A string in this exact shape (no
 * trailing 'Z'/offset) is parsed back by `new Date(...)` as local time
 * per the ECMAScript date-time string spec, so `formatDateID()`/
 * `formatTimeID()` below display the same wall-clock value that was
 * stored.
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

function formatTimeID(value: string): string {
  const date = new Date(value)

  if (!value || Number.isNaN(date.getTime())) {
    return ''
  }

  return new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false }).format(date)
}

const dateDisplay = computed(() => {
  const datePart = formatDateID(form.date)
  const timePart = formatTimeID(form.date)

  if (!datePart) {
    return ''
  }

  return timePart ? `${datePart}, ${timePart}` : datePart
})

// business_logic — Date auto-set once (in memory) only for a brand-new
// draft (no stored `date` yet); a resumed draft's stored value is kept
// untouched.
function populateForm(record: GradingDraftWithDetails['record']): void {
  form.grading_number = record.grading_number ?? ''
  form.date = record.date && record.date.trim() !== '' ? record.date : nowLocalDateTimeString()
  form.weighbridge_record_id = record.weighbridge_record_id ?? ''
  form.license_plate_no = record.license_plate_no ?? ''
  form.vehicle_code = record.vehicle_code ?? ''
  form.estate_supplier = record.estate_supplier ?? ''
  form.division = record.division ?? ''
  form.netto = record.netto ?? null
  form.quantity = record.quantity ?? null
  form.note = record.note ?? ''
}

function populateDetailRows(details: GradingDraftWithDetails['details']): void {
  detailRows.value = details.map((detail) => ({
    id: detail.id,
    grading_parameter_id: detail.grading_parameter_id ?? '',
    quantity: detail.quantity ?? null,
    uom: detail.uom ?? '',
    percentage: detail.percentage ?? null,
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
    const draft = await gradingRecordRepo.getDraftWithDetails(recordId)

    if (!draft) {
      notFound.value = true
      return
    }

    populateForm(draft.record)
    populateDetailRows(draft.details)
    pendingDeletionIds.value = []
    loadedSnapshot = dirtySnapshot()
  } catch (err) {
    loadErrorMessage.value = err instanceof Error ? err.message : 'Gagal memuat draft grading.'
  } finally {
    loading.value = false
  }
}

// Edge case — no local weighbridge_record data at all: wbOptions simply
// stays an empty array, the <select> below renders only its placeholder
// option, and nothing crashes.
async function loadWbOptions(): Promise<void> {
  try {
    wbOptions.value = await gradingRecordRepo.getWeighbridgeRecordOptions()
  } catch (err) {
    wbOptionsError.value = err instanceof Error ? err.message : 'Gagal memuat daftar WB Card No.'
  }
}

async function loadParameterOptions(): Promise<void> {
  try {
    parameterOptions.value = await gradingRecordRepo.getGradingParameterOptions()
  } catch (err) {
    parameterOptionsError.value = err instanceof Error ? err.message : 'Gagal memuat daftar Quality Parameter.'
  }
}

onMounted(async () => {
  await Promise.all([loadDraft(), loadWbOptions(), loadParameterOptions()])
})

function wbOptionLabel(option: WeighbridgeRecordOption): string {
  return option.wb_card_number?.trim() ? option.wb_card_number : `(Tanpa WB Card No) ${option.id}`
}

// SearchableSelect's fixed { value, label } option shape, extended with the
// raw record fields onWbCardSelect() below needs — the FULL matched option
// object SearchableSelect's `select` event carries back is exactly one of
// these entries, so those extra fields ride along for free.
interface WbCardSelectOption extends SearchableSelectOption {
  vehicle_number: string | null
  estate_supplier: string | null
  division: string | null
}

const wbSelectOptions = computed<WbCardSelectOption[]>(() =>
  wbOptions.value.map((option) => ({
    value: option.id,
    label: wbOptionLabel(option),
    vehicle_number: option.vehicle_number,
    estate_supplier: option.estate_supplier,
    division: option.division,
  })),
)

// business_logic — WB Card No auto-fill. Runs ONLY at selection time (this
// SearchableSelect `select` handler, carrying the full matched option
// object), never as a reactive/computed binding — so license_plate_no/
// estate_supplier/division stay freely editable afterward and are never
// silently reset by anything else.
function onWbCardSelect(option: SearchableSelectOption): void {
  const selected = option as WbCardSelectOption

  form.license_plate_no = selected.vehicle_number ?? ''
  form.estate_supplier = selected.estate_supplier ?? ''
  form.division = selected.division ?? ''
}

function addDetailRow(): void {
  detailRows.value.push({ grading_parameter_id: '', quantity: null, uom: '', percentage: null })
}

// business_logic — "Hapus baris". A row with an `id` (loaded from an
// existing draft) is queued for deletion, not deleted immediately; a row
// with no `id` (added this session) is just dropped from local state.
function removeDetailRow(index: number): void {
  const row = detailRows.value[index]

  if (row?.id) {
    pendingDeletionIds.value.push(row.id)
  }

  detailRows.value.splice(index, 1)
}

// SearchableSelect's fixed { value, label } option shape, extended with
// `uom` — onParameterSelect() below reads it straight off the emitted
// option instead of re-looking it up in parameterOptions.
interface ParameterSelectOption extends SearchableSelectOption {
  uom: string
}

// business_logic — Quality Parameter selection snapshots that parameter's
// uom onto the row (not a live join — see this file's header comment). Runs
// off the FULL matched option SearchableSelect's `select` event carries,
// same "select event carries the record, not just the id" pattern as
// onWbCardSelect() above.
function onParameterSelect(index: number, option: SearchableSelectOption): void {
  const row = detailRows.value[index]

  if (!row) {
    return
  }

  row.uom = (option as ParameterSelectOption).uom ?? ''
}

// business_logic — each Quality Parameter can only be used in ONE detail
// row: a row's dropdown excludes any parameter already selected in a
// DIFFERENT row, but still includes its OWN current selection (so the
// selected option doesn't disappear from its own dropdown). Reactive over
// every row's grading_parameter_id (not just this row's own), so adding,
// changing, or removing a row's selection elsewhere immediately frees/
// reserves the parameter across the whole grid — no separate "recompute"
// call needed, this recomputes naturally whenever detailRows.value changes.
// SearchableSelect never computes this availability itself — it's always
// handed the already-filtered list to show right now.
function availableParameterOptions(index: number): GradingParameterOption[] {
  const usedElsewhere = new Set(
    detailRows.value
      .filter((_, otherIndex) => otherIndex !== index)
      .map((row) => row.grading_parameter_id)
      .filter((id): id is string => Boolean(id)),
  )

  return parameterOptions.value.filter((option) => !usedElsewhere.has(option.id))
}

function parameterSelectOptions(index: number): ParameterSelectOption[] {
  return availableParameterOptions(index).map((option) => ({
    value: option.id,
    label: option.name,
    uom: option.uom,
  }))
}

// business_logic — Percentage per row: qty/header.netto*100 when
// uom='kg', qty/header.quantity*100 when uom='bunch'. Recomputes whenever
// this row's own qty changes OR the header's netto/quantity changes
// (header-level denominator affects every row) since it's read inside a
// `computed()` over `detailRows.value`/`form.netto`/`form.quantity`.
function rowPercentage(row: GradingDetailFormRow): number | null {
  if (row.quantity === null || row.quantity === undefined) {
    return null
  }

  if (row.uom === 'kg') {
    return form.netto ? (row.quantity / form.netto) * 100 : null
  }

  if (row.uom === 'bunch') {
    return form.quantity ? (row.quantity / form.quantity) * 100 : null
  }

  return null
}

const rowPercentages = computed(() => detailRows.value.map((row) => rowPercentage(row)))

function formatPercentage(value: number | null): string {
  return value === null ? '-' : `${value.toFixed(2)}%`
}

// business_logic — required-field validation with inline per-field
// errors, plus the distinct/special "zero valid detail rows" error.
function validate(): boolean {
  for (const key of Object.keys(errors) as (keyof GradingHeaderFormData)[]) {
    delete errors[key]
  }

  for (const field of REQUIRED_FIELDS) {
    const value = form[field]
    const isEmpty = value === null || value === undefined || value === ''

    if (isEmpty) {
      errors[field] = `${REQUIRED_FIELD_LABELS[field]} wajib diisi.`
    }
  }

  const hasValidDetailRow = detailRows.value.some(
    (row) => Boolean(row.grading_parameter_id) && row.quantity !== null && row.quantity !== undefined,
  )

  if (hasValidDetailRow) {
    detailRowsError.value = null
  } else {
    detailRowsError.value = 'Minimal 1 baris grading detail (Quality Parameter + Qty) harus diisi sebelum menyimpan.'
  }

  return Object.keys(errors).length === 0 && hasValidDetailRow
}

/**
 * Builds the `detailRows` payload sent to saveDraft()/
 * pauseDraftWithFormData() — each row's `percentage` is (re)computed
 * fresh from the current header netto/quantity right before the call,
 * rather than tracked live on the row object between renders.
 */
function buildDetailRowsPayload(): GradingDetailFormRow[] {
  return detailRows.value.map((row) => ({
    ...row,
    percentage: rowPercentage(row),
  }))
}

// business_logic — 'Simpan'.
async function onSimpan(): Promise<void> {
  actionErrorMessage.value = null

  if (!validate()) {
    return
  }

  saving.value = true

  try {
    await gradingRecordRepo.saveDraft(recordId, { ...form }, buildDetailRowsPayload(), [...pendingDeletionIds.value])
    router.push({ name: 'monitor-grading' })
  } catch (err) {
    if (err instanceof GradingDetailRequiredError) {
      // Defense-in-depth fallback — validate() above should already have
      // caught this client-side, but the repo enforces it too.
      detailRowsError.value = err.message
      return
    }

    actionErrorMessage.value = err instanceof Error ? err.message : 'Gagal menyimpan data grading.'
  } finally {
    saving.value = false
  }
}

// business_logic — 'Pause'. Checkpoint save, no required-field
// validation.
async function onPause(): Promise<void> {
  actionErrorMessage.value = null
  pausing.value = true

  try {
    await gradingRecordRepo.pauseDraftWithFormData(
      recordId,
      { ...form },
      buildDetailRowsPayload(),
      [...pendingDeletionIds.value],
    )
    router.push({ name: 'monitor-grading' })
  } catch (err) {
    actionErrorMessage.value = err instanceof Error ? err.message : 'Gagal menyimpan progres (Pause).'
  } finally {
    pausing.value = false
  }
}

// business_logic — 'Clear'.
function onClearClick(): void {
  clearDialogOpen.value = true
}

async function onClearConfirm(): Promise<void> {
  clearDialogOpen.value = false
  actionErrorMessage.value = null
  clearing.value = true

  try {
    await gradingRecordRepo.deleteDraft(recordId)
    router.push({ name: 'monitor-grading' })
  } catch (err) {
    actionErrorMessage.value = err instanceof Error ? err.message : 'Gagal menghapus draft grading.'
  } finally {
    clearing.value = false
  }
}

function onClearCancel(): void {
  clearDialogOpen.value = false
}

function navigateBack(): void {
  router.push({ name: 'monitor-grading' })
}

// business_logic — 'Back'.
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

// Breadcrumb taps — 'Form' is the current page (not a link — rendered as
// plain text with aria-current="page" in the template).
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
  <main class="form-grading-view">
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

    <div class="form-grading-header">
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
        <button type="button" class="breadcrumb-link" data-testid="breadcrumb-grading" @click="goToMonitorGrading">
          Grading
        </button>
        <span class="breadcrumb-separator" aria-hidden="true">/</span>
        <span class="breadcrumb-current" aria-current="page">Form</span>
      </nav>
      <h1 class="screen-title">Form Grading</h1>
    </div>

    <p v-if="loading" class="status-text">Memuat draft grading…</p>
    <p v-else-if="notFound" class="status-text status-text--error" role="alert">
      Draft grading tidak ditemukan.
    </p>
    <p v-else-if="loadErrorMessage" class="status-text status-text--error" role="alert">
      {{ loadErrorMessage }}
    </p>

    <form v-else class="form-body" novalidate @submit.prevent="onSimpan">
      <div v-if="actionErrorMessage" class="banner banner-error" role="alert">{{ actionErrorMessage }}</div>

      <section class="form-section" aria-label="Identitas Grading">
        <h2 class="form-section-title">Identitas Grading</h2>

        <FormField
          v-model="form.grading_number"
          label="Grading No"
          required
          :error="errors.grading_number"
          :disabled="actionInProgress"
        />
        <FormField :model-value="dateDisplay" label="Tanggal" disabled />
      </section>

      <section class="form-section" aria-label="Referensi Weighbridge & Kendaraan">
        <h2 class="form-section-title">Referensi Weighbridge &amp; Kendaraan</h2>

        <div class="form-field">
          <label for="wb-card-no-select" class="form-field-label">
            WB Card No <span class="form-field-required" aria-hidden="true">*</span>
          </label>
          <SearchableSelect
            id="wb-card-no-select"
            :class="{ 'form-field-input--error': errors.weighbridge_record_id }"
            data-testid="wb-card-no-select"
            v-model="form.weighbridge_record_id"
            :options="wbSelectOptions"
            placeholder="Pilih WB Card No"
            :disabled="actionInProgress"
            :aria-invalid="Boolean(errors.weighbridge_record_id)"
            @select="onWbCardSelect"
          />
          <p v-if="wbOptions.length === 0" class="form-field-hint">Tidak ada data weighbridge lokal.</p>
          <p v-if="wbOptionsError" class="form-field-error" role="alert">{{ wbOptionsError }}</p>
          <p v-if="errors.weighbridge_record_id" class="form-field-error" role="alert">
            {{ errors.weighbridge_record_id }}
          </p>
        </div>

        <FormField v-model="form.license_plate_no" label="License Plate No" :disabled="actionInProgress" />
        <FormField
          v-model="form.vehicle_code"
          label="Vehicle Code"
          :error="errors.vehicle_code"
          :disabled="actionInProgress"
        />
      </section>

      <section class="form-section" aria-label="Asal Muatan">
        <h2 class="form-section-title">Asal Muatan</h2>

        <FormField
          v-model="form.estate_supplier"
          label="Estate"
          required
          :error="errors.estate_supplier"
          :disabled="actionInProgress"
        />
        <FormField v-model="form.division" label="Divisi" :disabled="actionInProgress" />
      </section>

      <section class="form-section" aria-label="Data Berat & Kuantitas">
        <h2 class="form-section-title">Data Berat &amp; Kuantitas</h2>

        <FormField
          v-model="form.netto"
          label="Netto (kg)"
          type="number"
          required
          :error="errors.netto"
          :disabled="actionInProgress"
        />
        <FormField
          v-model="form.quantity"
          label="Quantity (bunch)"
          type="number"
          required
          :error="errors.quantity"
          :disabled="actionInProgress"
        />
        <FormField v-model="form.note" label="Note" :disabled="actionInProgress" />
      </section>

      <section class="form-section" aria-label="Grading Detail">
        <h2 class="form-section-title">Grading Detail</h2>

        <p v-if="parameterOptionsError" class="form-field-error" role="alert">{{ parameterOptionsError }}</p>
        <p v-if="detailRowsError" class="form-field-error" data-testid="detail-rows-error" role="alert">
          {{ detailRowsError }}
        </p>

        <div
          v-for="(row, index) in detailRows"
          :key="row.id ?? `new-${index}`"
          class="detail-row"
          data-testid="grading-detail-row"
        >
          <div class="form-field">
            <label :for="`detail-parameter-${index}`" class="form-field-label">Quality Parameter</label>
            <SearchableSelect
              :id="`detail-parameter-${index}`"
              :data-testid="`detail-parameter-select-${index}`"
              v-model="row.grading_parameter_id"
              :options="parameterSelectOptions(index)"
              placeholder="Pilih Quality Parameter"
              :disabled="actionInProgress"
              @select="(option) => onParameterSelect(index, option)"
            />
          </div>

          <div class="detail-row-fields">
            <FormField
              v-model="row.quantity"
              label="Qty"
              type="number"
              :id="`detail-qty-${index}`"
              :disabled="actionInProgress"
            />

            <FormField :model-value="row.uom" label="UOM" :id="`detail-uom-${index}`" disabled />

            <div class="form-field">
              <span class="form-field-label">Percentage</span>
              <p class="detail-row-percentage" :data-testid="`detail-percentage-${index}`">
                {{ formatPercentage(rowPercentages[index] ?? null) }}
              </p>
            </div>

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
        </div>

        <button
          type="button"
          class="action-button action-button--secondary"
          data-testid="add-detail-row-button"
          :disabled="actionInProgress"
          @click="addDetailRow"
        >
          Tambah baris
        </button>
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
      message="Draft grading ini akan dihapus secara permanen dan tidak dapat dikembalikan. Lanjutkan?"
      confirm-label="Ya, Hapus"
      cancel-label="Batal"
      @confirm="onClearConfirm"
      @cancel="onClearCancel"
    />
  </main>
</template>

<style scoped>
.form-grading-view {
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

.form-grading-header {
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

.form-field-required {
  color: #dc2626;
}

.form-field-input--error {
  outline: 1px solid #dc2626;
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

.detail-row {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}

/*
 * Row 2: Qty / UOM / Percentage / Hapus baris laid out side by side
 * instead of each stacking as its own full-width block — Quality
 * Parameter (row 1, full width above) is the only field that needs the
 * extra horizontal room. Wraps to multiple lines on narrow viewports via
 * flex-wrap rather than a fixed grid, so nothing gets clipped.
 */
.detail-row-fields {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 8px;
}

.detail-row-fields > .form-field {
  flex: 1 1 90px;
  min-width: 90px;
}

.detail-row-percentage {
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

.detail-row-remove {
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
