<script setup lang="ts">
/**
 * FormWeighbridgeView — screen-010--form-weighbridge /
 * usecase-010--form-weighbridge (mounted at
 * /stations/weighbridge/form/:id, meta.public = false — requires an
 * authenticated session, enforced by the router's global auth guard; see
 * router/index.ts). Actors: operator, supervisor.
 *
 * Mobile-only screen — no backend API endpoint; all data comes from the
 * local (offline) `weighbridge_record` table via weighbridgeRecordRepo.ts.
 *
 * MAJOR REWRITE (2026-08-18):
 *  - Checked By is REMOVED entirely from this screen (no field, no
 *    isSupervisor computed, no role-gated rendering). The `checked_by`/
 *    `acknowledged_by` columns still exist on `weighbridge_record`
 *    (DataPreviewWeighbridgeView.vue still reads them) and
 *    WeighbridgeFormData still requires both keys, so `buildPayload()`
 *    below always sends them as empty strings — this view simply never
 *    collects or forwards a real value for either.
 *  - Fields are now grouped into titled sections (Identitas Weighbridge /
 *    Kendaraan & Supir / Asal Muatan / Data Timbangan) matching this
 *    screen's mock (.asdlc/generated/2-business-spec/screens/html/
 *    screen-010--form-weighbridge.html) minus its Verifikasi/Checked By
 *    section.
 *  - Net Weight is now a pure `computed()` (Gross − Tare), never a plain
 *    editable field — displayed disabled.
 *  - 'Pause' and 'Clear' footer actions (previously owned by Monitor
 *    Weighbridge, screen-007, before its own list-view rewrite removed
 *    them): 'Pause' persists the current field values as a checkpoint
 *    (no required-field validation) via
 *    weighbridgeRecordRepo.pauseDraftWithFormData() and sets
 *    status='draft_paused'; 'Clear' shows ConfirmDialog.vue then
 *    permanently deletes the draft via deleteDraft().
 *
 * SCHEMA REVISION (2026-08-19, entity-catalog v5 / tech spec v6):
 *  - `arrival_datetime`/`dispatch_datetime` are merged into a single
 *    `record_datetime` column on `weighbridge_record` (see
 *    localSchema.ts/weighbridgeRecordRepo.ts). A new `weighbridge_type`
 *    ('receive' | 'dispatch') column decides what `record_datetime` means:
 *    arrival time for 'receive', dispatch time for 'dispatch'.
 *  - Identitas Weighbridge now has a two-button type selector
 *    (Receive/Dispatch) — brand-new drafts default to 'receive'; resuming a
 *    draft preserves its stored `weighbridge_type`.
 *  - `record_datetime` is auto-set-once IDENTICALLY for both types — set
 *    from `new Date()` only when currently empty (brand-new draft, or right
 *    after a type switch discarded it); a stored value is otherwise never
 *    overwritten. Unlike the old dispatch field, there is NO live ticking
 *    for either type — the previous `setInterval`-driven "Dispatch" section
 *    is removed entirely, along with the onSimpan-time "freeze" hack that
 *    existed only to stop that ticker.
 *  - Switching the type tab discards the current `record_datetime` and
 *    `destination`, then immediately re-applies the auto-set-once rule for
 *    the newly selected type.
 *  - A new `destination` field ("Tujuan Muatan") renders (and is required)
 *    only when `weighbridge_type === 'dispatch'`; hidden and unvalidated
 *    for 'receive'.
 *  - Quantity's label gains a unit: "Kuantitas (tandan)".
 *  - 'Back' dirty-check (business_logic step 8) now also tracks
 *    `destination` (a real user-editable field for dispatch records) in
 *    addition to the previously-tracked fields; `record_datetime` stays
 *    excluded (still automatic, never user-edited) and `weighbridge_type`
 *    stays excluded (switching type resets/re-derives the auto fields
 *    rather than being treated as its own dirty signal).
 *
 * Header/breadcrumb/nav-menu: copied verbatim (structure, class names,
 * composable/method naming — isNavMenuOpen/toggleNavMenu/closeNavMenu/
 * goToChangePassword/onLogout) from MonitorWeighbridgeView.vue (screen-007)
 * so all weighbridge screens stay visually/behaviorally consistent.
 * Breadcrumb is 4 segments deep here (Home > Production Process Activity >
 * Weighbridge > Form), the first 3 tappable, since this screen sits one
 * level below Monitor Weighbridge.
 *
 * business_logic:
 *  1. Receives the draft `id` via route param (already INSERTed by Monitor
 *     Weighbridge's 'New Data' / by tapping an existing draft, screen-007)
 *     and loads it via getDraftById() on mount.
 *  2. `weighbridge_type` defaults to 'receive' for a brand-new draft (no
 *     stored value), or is preserved as-is when resuming. Tapping a type
 *     tab switches it, discarding + re-auto-setting `record_datetime` and
 *     clearing `destination`.
 *  3. Tanggal/Waktu (Arrival or Dispatch, per the active type) auto-set
 *     once, in memory, only when `record_datetime` is currently empty;
 *     otherwise the stored value is kept as-is. Always disabled in the UI.
 *  4. Net Weight = Gross − Tare, computed reactively, always disabled.
 *  5. 'Simpan' validates required fields client-side (wb_card_number,
 *     vehicle_number, driver_name, estate_supplier, gross_weight, plus
 *     destination when type='dispatch') — inline error per field, no save
 *     on failure. On success: calls saveDraft() (status='saved'), navigates
 *     to `monitor-weighbridge`.
 *  6. 'Pause' persists current field values as-is (no required-field
 *     validation) via pauseDraftWithFormData() (status='draft_paused'),
 *     navigates to `monitor-weighbridge`.
 *  7. 'Clear' shows ConfirmDialog.vue; on confirm, permanently deletes the
 *     draft via deleteDraft() and navigates to `monitor-weighbridge`; on
 *     cancel, no change.
 *  8. 'Back' with unsaved changes (editable fields differ from the
 *     loaded-draft snapshot) shows ConfirmDialog.vue before leaving; if not
 *     dirty, navigates directly to `monitor-weighbridge`.
 */
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import weighbridgeRecordRepo, {
  type WeighbridgeFormData,
  type WeighbridgeRecord,
  type WeighbridgeType,
} from '@/services/weighbridgeRecordRepo'
import FormField from '@/components/FormField.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const recordId = String(route.params.id ?? '')

/**
 * The subset of WeighbridgeFormData this screen actually renders/collects.
 * `net_weight` (computed, see `netWeight` below) and `checked_by` /
 * `acknowledged_by` (removed from this screen entirely) are added back in
 * `buildPayload()` only, right before a save/pause call, so this reactive
 * form object only ever holds fields the user (or the auto logic) actually
 * touches.
 */
interface FormState {
  wb_card_number: string
  weighbridge_type: WeighbridgeType
  record_datetime: string
  vehicle_number: string
  driver_name: string
  estate_supplier: string
  destination: string
  division: string
  block: string
  gross_weight: number | null
  tare_weight: number | null
  quantity: number | null
}

// business_logic step 5 — fields always required, regardless of
// weighbridge_type. `destination` is appended conditionally by
// `requiredFields` below (dispatch-only).
const BASE_REQUIRED_FIELDS: (keyof FormState)[] = [
  'wb_card_number',
  'vehicle_number',
  'driver_name',
  'estate_supplier',
  'gross_weight',
]

const REQUIRED_FIELD_LABELS: Record<string, string> = {
  wb_card_number: 'WB Card Number/ID',
  vehicle_number: 'No. Kendaraan',
  driver_name: 'Nama Supir',
  estate_supplier: 'Estate/Supplier Asal',
  gross_weight: 'Berat Masuk (Gross)',
  destination: 'Tujuan Muatan',
}

// business_logic step 6 — `destination` ("Tujuan Muatan") is required only
// for dispatch records; hidden and unvalidated for receive.
const requiredFields = computed<(keyof FormState)[]>(() =>
  form.weighbridge_type === 'dispatch' ? [...BASE_REQUIRED_FIELDS, 'destination'] : BASE_REQUIRED_FIELDS,
)

// business_logic step 8 — fields compared for the Back dirty-check.
// `record_datetime` is deliberately excluded (automatic/disabled, never
// user-edited) and so is `weighbridge_type` (switching type re-derives the
// auto fields rather than being its own dirty signal); `destination` is
// included since it is now a real user-editable field (for dispatch).
const DIRTY_CHECK_FIELDS: (keyof FormState)[] = [
  'wb_card_number',
  'vehicle_number',
  'driver_name',
  'estate_supplier',
  'destination',
  'division',
  'block',
  'gross_weight',
  'tare_weight',
  'quantity',
]

function emptyFormState(): FormState {
  return {
    wb_card_number: '',
    weighbridge_type: 'receive',
    record_datetime: '',
    vehicle_number: '',
    driver_name: '',
    estate_supplier: '',
    destination: '',
    division: '',
    block: '',
    gross_weight: null,
    tare_weight: null,
    quantity: null,
  }
}

const form = reactive<FormState>(emptyFormState())
const errors = reactive<Partial<Record<keyof FormState, string>>>({})

const loading = ref(true)
const saving = ref(false)
const pausing = ref(false)
const clearing = ref(false)
const notFound = ref(false)
const loadErrorMessage = ref<string | null>(null)
const actionErrorMessage = ref<string | null>(null)
const backDialogOpen = ref(false)
const clearDialogOpen = ref(false)

const actionInProgress = computed(() => saving.value || pausing.value || clearing.value)

// Snapshot of the editable fields right after loading, used purely for the
// Back dirty-check (business_logic step 8) — compared as JSON so this
// doesn't need a per-field diff.
let loadedSnapshot = dirtySnapshot()

function dirtySnapshot(): string {
  const subset: Partial<Record<keyof FormState, string | number | null>> = {}

  for (const key of DIRTY_CHECK_FIELDS) {
    subset[key] = form[key]
  }

  return JSON.stringify(subset)
}

const isDirty = computed(() => dirtySnapshot() !== loadedSnapshot)

// business_logic step 4 — Net Weight = Gross − Tare, purely derived, never
// a plain editable field. Tare defaults to 0 when not yet entered so a
// filled-in Gross alone still shows a meaningful running total; stays null
// until Gross itself has a value.
const netWeight = computed<number | null>(() => {
  if (form.gross_weight === null) {
    return null
  }

  return form.gross_weight - (form.tare_weight ?? 0)
})

function nowIso(): string {
  return new Date().toISOString()
}

function formatDateID(iso: string): string {
  const date = new Date(iso)

  if (!iso || Number.isNaN(date.getTime())) {
    return ''
  }

  return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(date)
}

function formatTimeID(iso: string): string {
  const date = new Date(iso)

  if (!iso || Number.isNaN(date.getTime())) {
    return ''
  }

  return new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false }).format(date)
}

// business_logic step 2/3 — the single `record_datetime` field's label and
// display switch with the active `weighbridge_type` ("Arrival" for
// receive, "Dispatch" for dispatch), but the auto-set-once behavior and
// disabled rendering are identical for both.
const recordDateLabel = computed(() => (form.weighbridge_type === 'dispatch' ? 'Tanggal Dispatch' : 'Tanggal Arrival'))
const recordTimeLabel = computed(() => (form.weighbridge_type === 'dispatch' ? 'Waktu Dispatch' : 'Waktu Arrival'))
const recordDateDisplay = computed(() => formatDateID(form.record_datetime))
const recordTimeDisplay = computed(() => formatTimeID(form.record_datetime))

// business_logic step 3 — auto-set-once rule, identical for both types: set
// `record_datetime` from `new Date()` only when currently empty (brand-new
// draft, or right after a type switch discarded it); a stored value is
// otherwise never overwritten. No live ticking.
function applyRecordDatetimeAutoSet(): void {
  if (!form.record_datetime) {
    form.record_datetime = nowIso()
  }
}

// business_logic step 2 — tapping a type tab switches `weighbridge_type`,
// discards the current `record_datetime`/`destination`, then immediately
// re-applies the auto-set-once rule for the newly selected type. Tapping
// the already-active tab is a no-op (nothing to switch).
function onSelectType(type: WeighbridgeType): void {
  if (form.weighbridge_type === type) {
    return
  }

  form.weighbridge_type = type
  form.record_datetime = ''
  form.destination = ''
  applyRecordDatetimeAutoSet()
}

// business_logic step 1/2 — populate the form from a loaded draft.
// `weighbridge_type` defaults to 'receive' when the loaded record has none
// stored yet (brand-new draft); a resumed draft's stored value is kept
// as-is. `record_datetime` follows the same auto-set-once rule as a type
// switch (business_logic step 3).
function populateForm(record: WeighbridgeRecord): void {
  form.wb_card_number = record.wb_card_number ?? ''
  form.weighbridge_type = record.weighbridge_type === 'dispatch' ? 'dispatch' : 'receive'
  form.record_datetime = record.record_datetime && record.record_datetime.trim() !== '' ? record.record_datetime : ''
  form.vehicle_number = record.vehicle_number ?? ''
  form.driver_name = record.driver_name ?? ''
  form.estate_supplier = record.estate_supplier ?? ''
  form.destination = record.destination ?? ''
  form.division = record.division ?? ''
  form.block = record.block ?? ''
  form.gross_weight = record.gross_weight ?? null
  form.tare_weight = record.tare_weight ?? null
  form.quantity = record.quantity ?? null

  applyRecordDatetimeAutoSet()

  loadedSnapshot = dirtySnapshot()
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
    const record = await weighbridgeRecordRepo.getDraftById(recordId)

    if (!record) {
      notFound.value = true
      return
    }

    populateForm(record)
  } catch (err) {
    loadErrorMessage.value = err instanceof Error ? err.message : 'Gagal memuat draft timbangan.'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await loadDraft()
})

// business_logic step 5 — required-field validation with inline
// per-field errors. `record_datetime` is never checked here — it's
// automatic and already guaranteed non-empty by the time Simpan can be
// reached. `destination` is only checked when it's actually part of
// `requiredFields` (dispatch only).
function validate(): boolean {
  for (const key of Object.keys(errors) as (keyof FormState)[]) {
    delete errors[key]
  }

  for (const field of requiredFields.value) {
    const value = form[field]
    const isEmpty = value === null || value === undefined || value === ''

    if (isEmpty) {
      errors[field] = `${REQUIRED_FIELD_LABELS[field]} wajib diisi.`
    }
  }

  return Object.keys(errors).length === 0
}

/**
 * Builds the full WeighbridgeFormData payload sent to saveDraft()/
 * pauseDraftWithFormData() — merges the editable `form` state with the
 * computed `netWeight`, plus empty `checked_by`/`acknowledged_by` (this
 * screen collects neither; see header comment). `destination` is always
 * forced to an empty string for `weighbridge_type === 'receive'`
 * regardless of whatever is currently in `form.destination`, so a
 * receive record can never persist a stray destination value.
 */
function buildPayload(): WeighbridgeFormData {
  return {
    ...form,
    destination: form.weighbridge_type === 'dispatch' ? form.destination : '',
    net_weight: netWeight.value,
    checked_by: '',
    acknowledged_by: '',
  }
}

// business_logic step 5 — 'Simpan'.
async function onSimpan(): Promise<void> {
  actionErrorMessage.value = null

  if (!validate()) {
    return
  }

  saving.value = true

  try {
    await weighbridgeRecordRepo.saveDraft(recordId, buildPayload(), authStore.currentUser?.role)
    router.push({ name: 'monitor-weighbridge' })
  } catch (err) {
    actionErrorMessage.value = err instanceof Error ? err.message : 'Gagal menyimpan data timbangan.'
  } finally {
    saving.value = false
  }
}

// business_logic step 6 — 'Pause'. Checkpoint save, no required-field
// validation.
async function onPause(): Promise<void> {
  actionErrorMessage.value = null
  pausing.value = true

  try {
    await weighbridgeRecordRepo.pauseDraftWithFormData(recordId, buildPayload())
    router.push({ name: 'monitor-weighbridge' })
  } catch (err) {
    actionErrorMessage.value = err instanceof Error ? err.message : 'Gagal menyimpan progres (Pause).'
  } finally {
    pausing.value = false
  }
}

// business_logic step 7 — 'Clear'.
function onClearClick(): void {
  clearDialogOpen.value = true
}

async function onClearConfirm(): Promise<void> {
  clearDialogOpen.value = false
  actionErrorMessage.value = null
  clearing.value = true

  try {
    await weighbridgeRecordRepo.deleteDraft(recordId)
    router.push({ name: 'monitor-weighbridge' })
  } catch (err) {
    actionErrorMessage.value = err instanceof Error ? err.message : 'Gagal menghapus draft timbangan.'
  } finally {
    clearing.value = false
  }
}

function onClearCancel(): void {
  clearDialogOpen.value = false
}

function navigateBack(): void {
  router.push({ name: 'monitor-weighbridge' })
}

// business_logic step 8 — 'Back'.
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

function goToMonitorWeighbridge(): void {
  router.push({ name: 'monitor-weighbridge' })
}
</script>

<template>
  <main class="form-weighbridge-view">
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

    <div class="form-weighbridge-header">
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
        <span class="breadcrumb-current" aria-current="page">Form</span>
      </nav>
      <h1 class="screen-title">Form Weighbridge</h1>
    </div>

    <p v-if="loading" class="status-text">Memuat draft timbangan…</p>
    <p v-else-if="notFound" class="status-text status-text--error" role="alert">
      Draft timbangan tidak ditemukan.
    </p>
    <p v-else-if="loadErrorMessage" class="status-text status-text--error" role="alert">
      {{ loadErrorMessage }}
    </p>

    <form v-else class="form-body" novalidate @submit.prevent="onSimpan">
      <div v-if="actionErrorMessage" class="banner banner-error" role="alert">{{ actionErrorMessage }}</div>

      <div class="content-note">
        Data tersimpan langsung saat <strong>Simpan</strong> ditekan — bukan approval berjenjang. Gunakan
        <strong>Pause</strong> untuk melanjutkan nanti.
      </div>

      <section class="form-section" aria-label="Identitas Weighbridge">
        <h2 class="form-section-title">Identitas Weighbridge</h2>

        <div class="type-selector" role="tablist" aria-label="Tipe Weighbridge">
          <button
            type="button"
            class="type-tab"
            :class="{ 'type-tab--active': form.weighbridge_type === 'receive' }"
            role="tab"
            :aria-selected="form.weighbridge_type === 'receive'"
            data-testid="weighbridge-type-receive"
            :disabled="actionInProgress"
            @click="onSelectType('receive')"
          >
            Receive
          </button>
          <button
            type="button"
            class="type-tab"
            :class="{ 'type-tab--active': form.weighbridge_type === 'dispatch' }"
            role="tab"
            :aria-selected="form.weighbridge_type === 'dispatch'"
            data-testid="weighbridge-type-dispatch"
            :disabled="actionInProgress"
            @click="onSelectType('dispatch')"
          >
            Dispatch
          </button>
        </div>

        <FormField
          v-model="form.wb_card_number"
          label="WB Card Number/ID"
          required
          :error="errors.wb_card_number"
          :disabled="actionInProgress"
        />
        <FormField :model-value="recordDateDisplay" :label="recordDateLabel" disabled />
        <FormField :model-value="recordTimeDisplay" :label="recordTimeLabel" disabled />
      </section>

      <section class="form-section" aria-label="Kendaraan & Supir">
        <h2 class="form-section-title">Kendaraan &amp; Supir</h2>

        <FormField
          v-model="form.vehicle_number"
          label="No. Kendaraan"
          required
          :error="errors.vehicle_number"
          :disabled="actionInProgress"
        />
        <FormField
          v-model="form.driver_name"
          label="Nama Supir"
          required
          :error="errors.driver_name"
          :disabled="actionInProgress"
        />
      </section>

      <section class="form-section" aria-label="Asal Muatan">
        <h2 class="form-section-title">Asal Muatan</h2>

        <FormField
          v-model="form.estate_supplier"
          label="Estate/Supplier Asal"
          required
          :error="errors.estate_supplier"
          :disabled="actionInProgress"
        />
        <FormField
          v-if="form.weighbridge_type === 'dispatch'"
          v-model="form.destination"
          label="Tujuan Muatan"
          required
          :error="errors.destination"
          :disabled="actionInProgress"
        />
        <FormField v-model="form.division" label="Divisi" :disabled="actionInProgress" />
        <FormField v-model="form.block" label="Blok" :disabled="actionInProgress" />
      </section>

      <section class="form-section" aria-label="Data Timbangan">
        <h2 class="form-section-title">Data Timbangan</h2>

        <FormField
          v-model="form.gross_weight"
          label="Berat Masuk (Gross) (kg)"
          type="number"
          required
          :error="errors.gross_weight"
          :disabled="actionInProgress"
        />
        <FormField v-model="form.tare_weight" label="Berat Keluar (Tare) (kg)" type="number" :disabled="actionInProgress" />
        <FormField :model-value="netWeight" label="Net Weight (kg)" type="number" disabled />
        <FormField v-model="form.quantity" label="Kuantitas (tandan)" type="number" :disabled="actionInProgress" />
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
      message="Draft timbangan ini akan dihapus secara permanen dan tidak dapat dikembalikan. Lanjutkan?"
      confirm-label="Ya, Hapus"
      cancel-label="Batal"
      @confirm="onClearConfirm"
      @cancel="onClearCancel"
    />
  </main>
</template>

<style scoped>
.form-weighbridge-view {
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

.form-weighbridge-header {
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

.content-note {
  padding: 10px 12px;
  border-radius: 8px;
  background-color: #eef6f1;
  color: #1f2937;
  font-size: 12px;
  line-height: 1.5;
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

.type-selector {
  display: flex;
  gap: 8px;
}

.type-tab {
  flex: 1 1 0;
  min-height: 44px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background-color: #ffffff;
  color: #6b7280;
  font-size: 14px;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
}

.type-tab:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.type-tab--active {
  border-color: #249360;
  background-color: #249360;
  color: #ffffff;
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
