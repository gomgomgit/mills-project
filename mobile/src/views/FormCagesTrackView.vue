<script setup lang="ts">
/**
 * FormCagesTrackView — screen-012--form-cages-track /
 * usecase-012--form-cages-track (mounted at /stations/cages-track/form/:id,
 * meta.public = false — requires an authenticated session, enforced by the
 * router's global auth guard; see router/index.ts). Actors: operator,
 * supervisor, mill_management, admin (Checked By interactive only for
 * supervisor, Acknowledged By interactive only for mill_management — see
 * business_logic step 10 below).
 *
 * Mobile-only screen — no backend API endpoint; all data comes from the
 * local (offline) `cages_track_record` + `cages_tipped_time` tables via
 * cagesTrackRecordRepo.ts.
 *
 * FULL REWRITE (2026-08-19, entity-catalog v3): replaces the previous
 * placeholder built against cages_track_record/cages_tipped_time's pre-v3
 * shape (a flat header with no Tippler/Cages fields, a "one row per cage"
 * cage_number/tipped_time grid via the now-orphaned CagesTippedTimeGrid.vue
 * component). Structural pattern (breadcrumb + hamburger header, sectioned
 * form body, dirty-tracking Back confirm, Pause/Clear/Simpan footer
 * actions) mirrors FormGradingView.vue's (screen-011) own 2026-08-19
 * entity-catalog-v2 rewrite as closely as sensible, adapted to cages
 * track's very different detail grid:
 *
 *  - CagesTippedTimeGrid.vue (the old cage_number/tipped_time-shaped
 *    shared grid component) is deliberately NOT reused here — its
 *    props/emit contract is built around the removed pre-v3 columns and
 *    cannot represent an "one row = one hour slot with an N-checkbox
 *    checklist" grid at all. The Cages Tipped Time grid is implemented
 *    inline in this file instead (same reasoning FormGradingView.vue gave
 *    for not reusing GradingDetailGrid.vue). CagesTippedTimeGrid.vue
 *    itself is left untouched/unused — out of this rewrite's exact scope
 *    — see known_issues.
 *
 *  - "Identitas Cages Track" section: `cages_track_number` (required,
 *    freely editable), `date` (auto-set once, in memory, ONLY for a
 *    brand-new draft — no stored value yet; a resumed draft's stored
 *    value is kept as-is; always rendered disabled). Built from LOCAL
 *    Date getters (`nowLocalDateTimeString()` below), never
 *    `toISOString()` — that's UTC and can render a different calendar day
 *    than the device's local day.
 *
 *  - "Tippler & Cages" section: `tippler_start_time` (same auto-set-once
 *    rule/disabled rendering as `date` — a defensive fallback here, since
 *    cagesTrackRecordRepo.ts's `createDraft()` already auto-fills it at
 *    draft-creation time; this screen's own populateForm() re-applies the
 *    same "only if still empty" rule in case that ever isn't true).
 *    `tippler_stop_time` stays BLANK while editing — deliberately NOT a
 *    live-ticking clock (unlike weighbridgeRecordRepo.ts's
 *    `dispatch_datetime`) — set to "now" ONLY inside `onSimpan()`, right
 *    before the `saveDraft()` call that persists it; explicitly left
 *    untouched by `onPause()` (see that function's own comment). `cages_out`
 *    (required, freely editable at all times, never locked). `cages_tipped`
 *    (required integer > 0; freely editable until the FIRST
 *    cages_tipped_time row exists — loaded from a resumed draft OR added
 *    this session — then rendered disabled; this is what determines the
 *    grid's shared N-checkbox column count).
 *
 *  - "Verifikasi" section: "Inputted By" is a plain read-only display of
 *    `authStore.currentUser?.name` (NOT an editable field, NOT part of
 *    `form` — `created_by` itself is set once by
 *    cagesTrackRecordRepo.ts's `createDraft()` at draft-creation time,
 *    this is purely a display per the mock). "Saya verifikasi data ini"
 *    (Checked By) is a checkbox toggle, interactive ONLY for `supervisor`
 *    — checking it sets `form.checked_by = currentUser.id`, unchecking
 *    clears it back to `''`; disabled (but still visible, reflecting
 *    whatever value is already stored) for every other role. "Saya
 *    menyetujui data ini" (Acknowledged By) is the same toggle pattern,
 *    interactive ONLY for `mill_management`. Both are re-enforced at the
 *    repo level (cagesTrackRecordRepo.ts's `saveDraft()`/
 *    `pauseDraftWithFormData()` role-stripping) as defense-in-depth, same
 *    as every other role-gated field in this app. `note` (optional free
 *    text) also lives in this section per the mock.
 *
 *  - "Cages Tipped Time" section — the dynamic grid. `cageColumns`
 *    (computed from the header's `cages_tipped`) drives the shared
 *    "Cage 1".."Cage N" checkbox columns rendered identically in every
 *    row. Each row's Time <select> options are computed reactively by
 *    `availableHourOptions()` — see that function's own comment for the
 *    exact exclusion rule (already-used-by-another-row, plus a floor at
 *    the HIGHEST hour among all other rows, both exempting the row's own
 *    current selection). Checking/unchecking a cage checkbox updates that
 *    row's `checked_cage_numbers` (comma-separated, sorted ascending, via
 *    `toggleCage()`); `rowTotalCages()`/`rowCagesRemain()` are pure
 *    per-row display functions, never accumulated across rows.
 *    "Tambah baris" is disabled until `cages_tipped > 0`; "Hapus baris" on
 *    a row with an `id` (loaded from an existing draft) queues it in
 *    `pendingDeletionIds` (deleted only at the next Simpan/Pause) rather
 *    than deleting immediately, exact same pattern as
 *    FormGradingView.vue's Grading Detail rows; a row with no `id` (added
 *    this session) is just dropped from local state. Removing a row
 *    immediately frees its hour for every other row's Time dropdown,
 *    falling out automatically from `availableHourOptions()` recomputing
 *    over the smaller `tippedTimeRows` array.
 *
 *  - 'Simpan': validates required header fields (Cages Track No, Cages
 *    Out, Cages Tipped — Note/Checked By/Acknowledged By are NOT
 *    required) — inline error per field — AND at least one valid tipped
 *    time row (a selected Time AND at least one checked cage), a
 *    dedicated error message shown on the grid (not mixed into the
 *    per-field header errors), re-enforced at the repo level
 *    (`CagesTippedTimeRequiredError`) so the rule holds even if this
 *    client-side check is bypassed. On success: freezes
 *    `tippler_stop_time` to "now" (only in the payload sent to
 *    `saveDraft()` — never mutates `form` itself, so a FAILED save leaves
 *    the field genuinely untouched for a retry), calls
 *    cagesTrackRecordRepo.saveDraft() (status='saved'), navigates to
 *    `monitor-cages-track`.
 *  - 'Pause': no validation at all, persists current field values as-is
 *    (including `tippler_stop_time` exactly as currently held in `form` —
 *    i.e. still blank on a fresh draft) via
 *    cagesTrackRecordRepo.pauseDraftWithFormData() (status=
 *    'draft_paused'), navigates to `monitor-cages-track`.
 *  - 'Clear': ConfirmDialog.vue first; on confirm, calls the
 *    already-shared cagesTrackRecordRepo.deleteDraft() (screen-009's own
 *    block — cascades the delete to every cages_tipped_time row for the
 *    record, then deletes the cages_track_record row itself), then
 *    navigates to `monitor-cages-track`; on cancel, no change.
 *  - 'Back' with unsaved changes (header fields, tipped-time rows, or
 *    queued row deletions differ from the loaded-draft snapshot) shows
 *    ConfirmDialog.vue before leaving; if not dirty, navigates directly.
 *
 * Header/breadcrumb/nav-menu: copied verbatim (structure, class names,
 * composable/method naming — isNavMenuOpen/toggleNavMenu/closeNavMenu/
 * goToChangePassword/onLogout) from FormGradingView.vue /
 * FormWeighbridgeView.vue so all mobile station screens stay visually/
 * behaviorally consistent. Breadcrumb is 4 segments deep (Home >
 * Production Process Activity > Cages Track > Form), the first 3 tappable.
 */
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import cagesTrackRecordRepo, {
  CagesTippedTimeRequiredError,
  type CagesTippedTimeFormRow,
  type CagesTippedTimeRow,
  type CagesTrackDraftWithTippedTimes,
  type CagesTrackHeaderFormData,
  type CagesTrackRecord,
} from '@/services/cagesTrackRecordRepo'
import FormField from '@/components/FormField.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import SearchableSelect, { type SearchableSelectOption } from '@/components/SearchableSelect.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const recordId = String(route.params.id ?? '')

// business_logic step 11 — required header fields per this screen's tech
// spec. Note/Checked By/Acknowledged By are deliberately NOT required.
const REQUIRED_FIELDS: (keyof CagesTrackHeaderFormData)[] = ['cages_track_number', 'cages_out', 'cages_tipped']

const REQUIRED_FIELD_LABELS: Record<string, string> = {
  cages_track_number: 'Cages Track Number',
  cages_out: 'Cages Out',
  cages_tipped: 'Cages Tipped',
}

// business_logic — fields compared for the Back dirty-check. `date` /
// `tippler_start_time` / `tippler_stop_time` are deliberately excluded
// (fully automatic/disabled or frozen-only-on-save, never freely
// user-editable — same reasoning as FormWeighbridgeView.vue excluding
// arrival_datetime/dispatch_datetime from its own dirty-check).
const DIRTY_CHECK_FIELDS: (keyof CagesTrackHeaderFormData)[] = [
  'cages_track_number',
  'cages_out',
  'cages_tipped',
  'note',
  'checked_by',
  'acknowledged_by',
]

function emptyFormData(): CagesTrackHeaderFormData {
  return {
    cages_track_number: '',
    date: '',
    tippler_start_time: '',
    tippler_stop_time: '',
    cages_out: null,
    cages_tipped: null,
    note: '',
    checked_by: '',
    acknowledged_by: '',
  }
}

const form = reactive<CagesTrackHeaderFormData>(emptyFormData())
const errors = reactive<Partial<Record<keyof CagesTrackHeaderFormData, string>>>({})
const tippedTimeRows = ref<CagesTippedTimeFormRow[]>([])
// business_logic — ids of existing (has-`id`) tipped-time rows removed via
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
// business_logic — distinct/special error for "zero valid tipped-time
// rows", surfaced on the Cages Tipped Time section rather than mixed into
// the per-field header `errors` object above.
const tippedTimeRowsError = ref<string | null>(null)
const backDialogOpen = ref(false)
const clearDialogOpen = ref(false)

const actionInProgress = computed(() => saving.value || pausing.value || clearing.value)

const isSupervisor = computed(() => authStore.currentUser?.role === 'supervisor')
const isMillManagement = computed(() => authStore.currentUser?.role === 'mill_management')

// business_logic — cages_tipped locks (disabled) once at least one
// cages_tipped_time row exists (loaded from a resumed draft OR added this
// session) — freely editable before that.
const cagesTippedLocked = computed(() => tippedTimeRows.value.length > 0)

// business_logic — "Tambah baris" is only enabled once cages_tipped has a
// value > 0.
const canAddTippedTimeRow = computed(
  () => form.cages_tipped !== null && form.cages_tipped !== undefined && form.cages_tipped > 0,
)

// business_logic — the shared "Cage 1".."Cage N" checkbox columns
// rendered identically in every row, N = header's cages_tipped (0 → no
// columns at all, e.g. before it's been entered).
const cageColumns = computed<number[]>(() => {
  const n = form.cages_tipped ?? 0

  if (n <= 0) {
    return []
  }

  return Array.from({ length: n }, (_, index) => index + 1)
})

// Snapshot of {header, rows, pendingDeletions} right after loading, used
// purely for the Back dirty-check — compared as JSON, same approach as
// FormGradingView.vue's `isDirty`.
let loadedSnapshot = dirtySnapshot()

function dirtySnapshot(): string {
  const header: Partial<Record<keyof CagesTrackHeaderFormData, string | number | null>> = {}

  for (const key of DIRTY_CHECK_FIELDS) {
    header[key] = form[key]
  }

  const rows = tippedTimeRows.value.map((row) => ({
    id: row.id ?? null,
    tipped_hour: row.tipped_hour,
    checked_cage_numbers: row.checked_cage_numbers,
  }))

  return JSON.stringify({ header, rows, pendingDeletions: [...pendingDeletionIds.value] })
}

const isDirty = computed(() => dirtySnapshot() !== loadedSnapshot)

/**
 * Builds a local (device timezone) date-time string, e.g.
 * "2026-08-19T14:30:00" — deliberately NOT `new Date().toISOString()`,
 * which is UTC and can land on a different calendar day/hour than the
 * device's local time. Mirrors FormGradingView.vue's/
 * FormWeighbridgeView.vue's own `nowLocalDateTimeString()`.
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

function formatDateTimeDisplay(value: string): string {
  const datePart = formatDateID(value)
  const timePart = formatTimeID(value)

  if (!datePart) {
    return ''
  }

  return timePart ? `${datePart}, ${timePart}` : datePart
}

const dateDisplay = computed(() => formatDateTimeDisplay(form.date))
const tipplerStartDisplay = computed(() => formatDateTimeDisplay(form.tippler_start_time))
const tipplerStopDisplay = computed(() =>
  form.tippler_stop_time ? formatDateTimeDisplay(form.tippler_stop_time) : 'Akan diisi otomatis saat Simpan',
)

// business_logic — Date/Tippler Start Time auto-set once (in memory) only
// for a brand-new draft (no stored value yet); a resumed draft's stored
// value is kept untouched. Tippler Stop Time is never auto-set here —
// stays blank until onSimpan() freezes it.
function populateForm(record: CagesTrackRecord): void {
  form.cages_track_number = record.cages_track_number ?? ''
  form.date = record.date && record.date.trim() !== '' ? record.date : nowLocalDateTimeString()
  form.tippler_start_time =
    record.tippler_start_time && record.tippler_start_time.trim() !== ''
      ? record.tippler_start_time
      : nowLocalDateTimeString()
  form.tippler_stop_time = record.tippler_stop_time ?? ''
  form.cages_out = record.cages_out ?? null
  form.cages_tipped = record.cages_tipped ?? null
  form.note = record.note ?? ''
  form.checked_by = record.checked_by ?? ''
  form.acknowledged_by = record.acknowledged_by ?? ''
}

function populateTippedTimeRows(rows: CagesTippedTimeRow[]): void {
  tippedTimeRows.value = rows.map((row) => ({
    id: row.id,
    tipped_hour: row.tipped_hour ?? null,
    checked_cage_numbers: row.checked_cage_numbers ?? '',
    total_cages: row.total_cages ?? 0,
    cages_remain: row.cages_remain ?? 0,
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
    const draft = await cagesTrackRecordRepo.getDraftWithTippedTimes(recordId)

    if (!draft) {
      notFound.value = true
      return
    }

    populateForm(draft.record)
    populateTippedTimeRows(draft.tippedTimes)
    pendingDeletionIds.value = []
    loadedSnapshot = dirtySnapshot()
  } catch (err) {
    loadErrorMessage.value = err instanceof Error ? err.message : 'Gagal memuat draft cages track.'
  } finally {
    loading.value = false
  }
}

onMounted(loadDraft)

function hourLabel(hour: number): string {
  return `${String(hour).padStart(2, '0')}:00`
}

/**
 * business_logic — available Time (hour) options for a given row: all 24
 * hours (0-23), MINUS any hour already selected by ANOTHER row in the
 * grid, MINUS any hour ≤ the HIGHEST hour already selected among all OTHER
 * rows (the "floor") — hours must be added in strictly ascending order, so
 * once a later hour exists anywhere else in the grid, no row can be
 * set/kept at an earlier-or-equal hour. The floor is deliberately the max
 * across every OTHER row, not "the array's last element" — using the last
 * element breaks when computing options FOR that very last row itself
 * (its own still-unselected/its-own value would wrongly floor against
 * itself, producing no floor at all) and also breaks for a non-last row
 * once a later row already exists. Both exclusions exempt the row's OWN
 * current selection (so it never disappears from its own dropdown, same
 * "still includes its own current selection" idiom as
 * FormGradingView.vue's `availableParameterOptions()`). Reactive over the
 * entire `tippedTimeRows.value` array, so adding, changing, or removing a
 * row anywhere immediately recomputes every other row's options — no
 * separate "recompute" call needed.
 */
function availableHourOptions(index: number): number[] {
  const row = tippedTimeRows.value[index]

  if (!row) {
    return []
  }

  const otherHours = tippedTimeRows.value
    .filter((_, otherIndex) => otherIndex !== index)
    .map((otherRow) => otherRow.tipped_hour)
    .filter((hour): hour is number => hour !== null && hour !== undefined)

  const usedByOtherRows = new Set(otherHours)
  const floor = otherHours.length > 0 ? Math.max(...otherHours) : null

  const options: number[] = []

  for (let hour = 0; hour < 24; hour++) {
    if (usedByOtherRows.has(hour)) {
      continue
    }

    if (floor !== null && hour <= floor && hour !== row.tipped_hour) {
      continue
    }

    options.push(hour)
  }

  return options
}

// SearchableSelect never computes availability itself — it's always handed
// the already-filtered list to show right now, same pattern
// FormGradingView.vue's parameterSelectOptions() uses for its own per-row
// dropdown.
function hourSelectOptions(index: number): SearchableSelectOption[] {
  return availableHourOptions(index).map((hour) => ({ value: hour, label: hourLabel(hour) }))
}

function parseCheckedCages(value: string): number[] {
  return value
    .split(',')
    .map((part) => part.trim())
    .filter((part) => part !== '')
    .map((part) => Number(part))
    .filter((n) => !Number.isNaN(n))
}

function serializeCheckedCages(numbers: number[]): string {
  return [...new Set(numbers)].sort((a, b) => a - b).join(',')
}

function isCageChecked(row: CagesTippedTimeFormRow, cageNumber: number): boolean {
  return parseCheckedCages(row.checked_cage_numbers).includes(cageNumber)
}

// business_logic — checking/unchecking a cage checkbox updates that row's
// `checked_cage_numbers`, scoped to that row only.
function toggleCage(index: number, cageNumber: number): void {
  const row = tippedTimeRows.value[index]

  if (!row) {
    return
  }

  const current = parseCheckedCages(row.checked_cage_numbers)
  const next = current.includes(cageNumber)
    ? current.filter((n) => n !== cageNumber)
    : [...current, cageNumber]

  row.checked_cage_numbers = serializeCheckedCages(next)
}

// business_logic — per-row, auto-computed, read-only display values.
// Never accumulated/subtracted across rows.
function rowTotalCages(row: CagesTippedTimeFormRow): number {
  return parseCheckedCages(row.checked_cage_numbers).length
}

function rowCagesRemain(row: CagesTippedTimeFormRow): number {
  return (form.cages_tipped ?? 0) - rowTotalCages(row)
}

// business_logic step 6 — "Tambah baris": only enabled once cages_tipped
// > 0 (see canAddTippedTimeRow above).
function addTippedTimeRow(): void {
  tippedTimeRows.value.push({
    tipped_hour: null,
    checked_cage_numbers: '',
    total_cages: 0,
    cages_remain: form.cages_tipped ?? 0,
  })
}

// business_logic step 9 — "Hapus baris". A row with an `id` (loaded from
// an existing draft) is queued for deletion, not deleted immediately; a
// row with no `id` (added this session) is just dropped from local state.
// Either way, the row's hour is immediately freed for other rows' Time
// dropdowns (falls out of availableHourOptions() automatically once the
// row is gone from tippedTimeRows.value).
function removeTippedTimeRow(index: number): void {
  const row = tippedTimeRows.value[index]

  if (row?.id) {
    pendingDeletionIds.value.push(row.id)
  }

  tippedTimeRows.value.splice(index, 1)
}

// business_logic step 11 — required-field validation with inline
// per-field errors. cages_tipped additionally must be > 0 (not just
// non-empty).
function validateHeader(): boolean {
  for (const key of Object.keys(errors) as (keyof CagesTrackHeaderFormData)[]) {
    delete errors[key]
  }

  for (const field of REQUIRED_FIELDS) {
    const value = form[field]
    const isEmpty = value === null || value === undefined || value === ''

    if (isEmpty) {
      errors[field] = `${REQUIRED_FIELD_LABELS[field]} wajib diisi.`
    }
  }

  if (!errors.cages_tipped && (form.cages_tipped ?? 0) <= 0) {
    errors.cages_tipped = `${REQUIRED_FIELD_LABELS.cages_tipped} harus lebih besar dari 0.`
  }

  return Object.keys(errors).length === 0
}

// business_logic step 11 — at-least-one-valid-tipped-time-row validation
// (a selected Time AND at least one checked cage), kept separate from
// validateHeader() since it's a distinct/special error surfaced on the
// grid, not a per-field header error.
function validateTippedTimeRows(): boolean {
  const hasValidRow = tippedTimeRows.value.some(
    (row) => row.tipped_hour !== null && row.tipped_hour !== undefined && rowTotalCages(row) > 0,
  )

  if (!hasValidRow) {
    tippedTimeRowsError.value =
      'Minimal 1 baris Cages Tipped Time (Time terpilih + minimal 1 Cage dicentang) harus diisi sebelum menyimpan.'
    return false
  }

  tippedTimeRowsError.value = null
  return true
}

/**
 * Builds the `tippedTimeRows` payload sent to saveDraft()/
 * pauseDraftWithFormData() — each row's `total_cages`/`cages_remain` are
 * (re)computed fresh from that row's own `checked_cage_numbers` plus the
 * current header `cages_tipped`, right before the call, rather than
 * tracked live on the row object between renders.
 */
function buildTippedTimeRowsPayload(): CagesTippedTimeFormRow[] {
  return tippedTimeRows.value.map((row) => ({
    ...row,
    total_cages: rowTotalCages(row),
    cages_remain: rowCagesRemain(row),
  }))
}

// business_logic step 11 — 'Simpan'.
async function onSimpan(): Promise<void> {
  actionErrorMessage.value = null

  const headerValid = validateHeader()
  const tippedTimeValid = validateTippedTimeRows()

  if (!headerValid || !tippedTimeValid) {
    return
  }

  saving.value = true

  try {
    // business_logic — freeze tippler_stop_time to "now" only in the
    // payload sent to saveDraft(), never mutating `form` itself — so a
    // FAILED save leaves the field genuinely blank/untouched for a retry
    // (see this file's header comment).
    const headerPayload: CagesTrackHeaderFormData = { ...form, tippler_stop_time: nowLocalDateTimeString() }

    await cagesTrackRecordRepo.saveDraft(
      recordId,
      headerPayload,
      buildTippedTimeRowsPayload(),
      [...pendingDeletionIds.value],
      authStore.currentUser?.role,
    )
    router.push({ name: 'monitor-cages-track' })
  } catch (err) {
    if (err instanceof CagesTippedTimeRequiredError) {
      // Defense-in-depth fallback — validateTippedTimeRows() above should
      // already have caught this client-side, but the repo enforces it
      // too.
      tippedTimeRowsError.value = err.message
      return
    }

    actionErrorMessage.value = err instanceof Error ? err.message : 'Gagal menyimpan data cages track.'
  } finally {
    saving.value = false
  }
}

// business_logic step 12 — 'Pause'. Checkpoint save, no required-field
// validation, tippler_stop_time explicitly NOT touched/frozen (whatever
// `form.tippler_stop_time` currently holds — blank on a fresh draft — is
// persisted as-is).
async function onPause(): Promise<void> {
  actionErrorMessage.value = null
  pausing.value = true

  try {
    await cagesTrackRecordRepo.pauseDraftWithFormData(
      recordId,
      { ...form },
      buildTippedTimeRowsPayload(),
      [...pendingDeletionIds.value],
      authStore.currentUser?.role,
    )
    router.push({ name: 'monitor-cages-track' })
  } catch (err) {
    actionErrorMessage.value = err instanceof Error ? err.message : 'Gagal menyimpan progres (Pause).'
  } finally {
    pausing.value = false
  }
}

// business_logic step 13 — 'Clear'.
function onClearClick(): void {
  clearDialogOpen.value = true
}

async function onClearConfirm(): Promise<void> {
  clearDialogOpen.value = false
  actionErrorMessage.value = null
  clearing.value = true

  try {
    await cagesTrackRecordRepo.deleteDraft(recordId)
    router.push({ name: 'monitor-cages-track' })
  } catch (err) {
    actionErrorMessage.value = err instanceof Error ? err.message : 'Gagal menghapus draft cages track.'
  } finally {
    clearing.value = false
  }
}

function onClearCancel(): void {
  clearDialogOpen.value = false
}

function navigateBack(): void {
  router.push({ name: 'monitor-cages-track' })
}

// business_logic step 14 — 'Back'.
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

// business_logic step 10 — Checked By / Acknowledged By "Saya
// verifikasi..." toggles. Interactive only for the gated role; disabled
// (but still visible, reflecting the stored value) for every other role.
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

function goToMonitorCagesTrack(): void {
  router.push({ name: 'monitor-cages-track' })
}
</script>

<template>
  <main class="form-cages-track-view">
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

    <div class="form-cages-track-header">
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
        <button type="button" class="breadcrumb-link" data-testid="breadcrumb-cages-track" @click="goToMonitorCagesTrack">
          Cages Track
        </button>
        <span class="breadcrumb-separator" aria-hidden="true">/</span>
        <span class="breadcrumb-current" aria-current="page">Form</span>
      </nav>
      <h1 class="screen-title">Form Cages Track</h1>
    </div>

    <p v-if="loading" class="status-text">Memuat draft cages track…</p>
    <p v-else-if="notFound" class="status-text status-text--error" role="alert">
      Draft cages track tidak ditemukan.
    </p>
    <p v-else-if="loadErrorMessage" class="status-text status-text--error" role="alert">
      {{ loadErrorMessage }}
    </p>

    <form v-else class="form-body" novalidate @submit.prevent="onSimpan">
      <div v-if="actionErrorMessage" class="banner banner-error" role="alert">{{ actionErrorMessage }}</div>

      <section class="form-section" aria-label="Identitas Cages Track">
        <h2 class="form-section-title">Identitas Cages Track</h2>

        <FormField
          v-model="form.cages_track_number"
          label="Cages Track Number"
          required
          :error="errors.cages_track_number"
          :disabled="actionInProgress"
        />
        <FormField :model-value="dateDisplay" label="Tanggal" disabled />
      </section>

      <section class="form-section" aria-label="Tippler &amp; Cages">
        <h2 class="form-section-title">Tippler &amp; Cages</h2>

        <FormField :model-value="tipplerStartDisplay" label="Tippler Start Time" disabled />
        <FormField :model-value="tipplerStopDisplay" label="Tippler Stop Time" disabled />
        <FormField
          v-model="form.cages_out"
          label="Cages Out"
          type="number"
          required
          :error="errors.cages_out"
          :disabled="actionInProgress"
        />
        <FormField
          v-model="form.cages_tipped"
          label="Cages Tipped"
          type="number"
          required
          :error="errors.cages_tipped"
          :disabled="actionInProgress || cagesTippedLocked"
        />
        <p v-if="cagesTippedLocked" class="form-field-hint">
          Cages Tipped terkunci karena sudah ada baris Cages Tipped Time.
        </p>
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

      <section class="form-section" aria-label="Cages Tipped Time">
        <h2 class="form-section-title">Cages Tipped Time</h2>

        <p v-if="tippedTimeRowsError" class="form-field-error" data-testid="tipped-time-rows-error" role="alert">
          {{ tippedTimeRowsError }}
        </p>

        <div
          v-for="(row, index) in tippedTimeRows"
          :key="row.id ?? `new-${index}`"
          class="tipped-time-row"
          data-testid="tipped-time-row"
        >
          <div class="form-field">
            <label :for="`tipped-hour-${index}`" class="form-field-label">Time</label>
            <SearchableSelect
              :id="`tipped-hour-${index}`"
              :data-testid="`tipped-hour-select-${index}`"
              v-model="row.tipped_hour"
              :options="hourSelectOptions(index)"
              placeholder="Pilih Jam"
              :disabled="actionInProgress"
            />
          </div>

          <div class="cage-checkbox-grid" :data-testid="`cage-checkbox-grid-${index}`">
            <label v-for="cageNumber in cageColumns" :key="cageNumber" class="cage-checkbox">
              <input
                type="checkbox"
                :data-testid="`cage-checkbox-${index}-${cageNumber}`"
                :checked="isCageChecked(row, cageNumber)"
                :disabled="actionInProgress"
                @change="toggleCage(index, cageNumber)"
              />
              Cage {{ cageNumber }}
            </label>
            <p v-if="cageColumns.length === 0" class="form-field-hint">Isi Cages Tipped pada header terlebih dahulu.</p>
          </div>

          <div class="tipped-time-row-summary">
            <span :data-testid="`row-total-cages-${index}`">Total Cages: {{ rowTotalCages(row) }}</span>
            <span :data-testid="`row-cages-remain-${index}`">Cages Remain: {{ rowCagesRemain(row) }}</span>
          </div>

          <button
            type="button"
            class="detail-row-remove"
            data-testid="remove-tipped-time-row-button"
            :disabled="actionInProgress"
            @click="removeTippedTimeRow(index)"
          >
            Hapus baris
          </button>
        </div>

        <button
          type="button"
          class="action-button action-button--secondary"
          data-testid="add-tipped-time-row-button"
          :disabled="actionInProgress || !canAddTippedTimeRow"
          @click="addTippedTimeRow"
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
      message="Draft cages track ini akan dihapus secara permanen dan tidak dapat dikembalikan. Lanjutkan?"
      confirm-label="Ya, Hapus"
      cancel-label="Batal"
      @confirm="onClearConfirm"
      @cancel="onClearCancel"
    />
  </main>
</template>

<style scoped>
.form-cages-track-view {
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

.form-cages-track-header {
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

.tipped-time-row {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}

.cage-checkbox-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 16px;
}

.cage-checkbox {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  color: #1f2937;
}

.cage-checkbox input[type='checkbox'] {
  width: 18px;
  height: 18px;
}

.tipped-time-row-summary {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  font-size: 13px;
  font-weight: 600;
  color: #249360;
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
