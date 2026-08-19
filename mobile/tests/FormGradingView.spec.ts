/**
 * FormGradingView.spec.ts — screen-011--form-grading /
 * usecase-011--form-grading (entity-catalog v2 rewrite, 2026-08-19).
 *
 * FULL REWRITE, replacing every test in the previous version of this file
 * (built against grading_record/grading_detail's pre-v2 shape —
 * vehicle_number/driver_name/block header fields, free-text detail
 * `category`, Checked-By-visible-to-supervisor, no WB Card No / Quality
 * Parameter dropdowns, no Pause/Clear). Mirrors FormGradingView.vue's own
 * "FULL REWRITE" header comment and gradingRecordRepo.spec.ts's parallel
 * rewrite for the repo layer.
 *
 * Component tests covering:
 *   1. Date auto-set to current local time only for a brand-new draft
 *      (date null/empty on load); preserved as-is when resuming a draft
 *      that already has a stored date.
 *   2. WB Card No dropdown populated from ALL local weighbridge_record
 *      rows (any status).
 *   3. Selecting a WB Card No option auto-fills license_plate_no /
 *      estate_supplier / division from that record.
 *   4. Those three auto-filled fields are NOT reset by further form
 *      activity/re-renders once manually edited — the auto-fill only
 *      ever runs inside the WB Card No <select>'s own @change handler,
 *      never as a reactive/computed binding (see FormGradingView.vue's
 *      header comment). Re-selecting the SAME WB Card No option again
 *      would legitimately re-run the handler and re-overwrite — that is
 *      not what this test exercises; it exercises everything ELSE
 *      (typing in other fields, adding detail rows) NOT silently
 *      clobbering a manual edit.
 *   5. Selecting a Quality Parameter for a detail row snapshots that
 *      row's uom from grading_parameter and renders the UOM field
 *      disabled with the snapshotted value.
 *   6. Percentage computed correctly per uom (qty/netto*100 for 'kg',
 *      qty/quantity*100 for 'bunch').
 *   7. Percentage recomputed when a row's own qty changes, and when the
 *      header's netto/quantity changes (denominator shared by every
 *      row).
 *   8. "Tambah baris" appends a new empty detail row.
 *   9. "Hapus baris" queues an existing (has-id) row for deletion
 *      (applied only on the next Simpan/Pause, not immediately) vs. just
 *      drops a newly-added (no-id) row from local state.
 *   10. Simpan: required-header-field inline errors block save; a
 *       dedicated zero-valid-detail-row error blocks save; success calls
 *       saveDraft() with the correct payload and navigates to
 *       monitor-grading; the repo's GradingDetailRequiredError is
 *       surfaced on the grid as a defense-in-depth fallback.
 *   11. Pause: no validation at all, persists as-is via
 *       pauseDraftWithFormData(), navigates to monitor-grading.
 *   12. Clear: ConfirmDialog shown; confirm deletes + navigates; cancel
 *       makes no change.
 *   13. Back: dirty form shows ConfirmDialog before navigating; clean
 *       form navigates directly.
 *   14. Breadcrumb segments navigate to their target routes.
 *   15. Hamburger opens the nav menu (Ganti Password / Logout).
 *   16. Edge case: no local weighbridge_record data at all -> the WB
 *       Card No dropdown shows only its placeholder option plus a hint,
 *       without crashing.
 *
 * Mocking strategy (mirrors FormWeighbridgeView.spec.ts /
 * gradingRecordRepo.spec.ts):
 *   - 'vue-router' is mocked at module level so `router.push` can be
 *     asserted via a hoisted `pushMock`, and the route's `:id` param can
 *     be controlled per test via a hoisted `useRouteMock`.
 *   - '@/stores/auth' is mocked at module level via a hoisted
 *     `useAuthStoreMock` so this file never pulls in the real Pinia auth
 *     store / apiClient / tokenStorage chain.
 *   - '@/services/gradingRecordRepo' is mocked at module level (its own
 *     behavior is already covered by gradingRecordRepo.spec.ts) so this
 *     file never touches localDb.ts / a real SQLite connection. Both the
 *     default export and the named `GradingDetailRequiredError` export
 *     are mocked — the latter as a locally-defined class inside the
 *     `vi.mock` factory (not `vi.importActual`, to avoid ever loading the
 *     real localDb-touching module), matching the exact
 *     name/message/`instanceof` contract FormGradingView.vue relies on.
 *   - FormField.vue and ConfirmDialog.vue are NOT mocked — rendered for
 *     real so field input/validation-error rendering and the Back/Clear
 *     confirm-cancel interactions are exercised end-to-end through their
 *     actual markup.
 *   - Inputs are targeted via FormField.vue's deterministic slugified
 *     `field-<label-slug>` ids (e.g. `#field-grading-no` for "Grading
 *     No", `#field-netto-kg` for "Netto (kg)") for header fields, and
 *     per-row explicit `#detail-qty-<index>` / `#detail-uom-<index>` ids +
 *     `detail-percentage-<index>` data-testid for the Grading Detail
 *     grid. Footer actions / header / breadcrumb / nav-menu elements are
 *     targeted via their `data-testid` attributes.
 *   - WB Card No and per-row Quality Parameter are now SearchableSelect.vue
 *     instances (`data-testid="wb-card-no-select"` /
 *     `data-testid="detail-parameter-select-<index>"` on the component's
 *     root element, per SearchableSelect.vue's own attribute-fallthrough
 *     convention) rather than plain `<select>`s — selection is driven via
 *     the `chooseSearchableOption()` helper below (focus -> find the
 *     `role="option"` list item by its exact label text -> mousedown, the
 *     same open-then-click a real user does), the currently-selected value
 *     is read via `searchableSelectValue()` (the root's `data-value`
 *     attribute, SearchableSelect.vue's own `<select>.value`-equivalent
 *     testability hook) instead of `(el as HTMLSelectElement).value`, and
 *     the rendered option list is read via `searchableSelectOptionTexts()`
 *     (after `openSearchableSelect()`) instead of a plain `option` CSS
 *     selector.
 *   - Only the 'Date' timer is faked (`vi.useFakeTimers({ toFake: ['Date'] })`
 *     + `vi.setSystemTime(...)`) — Form Grading's Date field is
 *     auto-set-ONCE on load (not a live ticker, unlike Form Weighbridge's
 *     Dispatch), so no setInterval/clearInterval faking is needed. The
 *     expected local date-time string is computed in-test via the SAME
 *     local-Date-getters logic FormGradingView.vue's own
 *     `nowLocalDateTimeString()` uses (not `toISOString()`), so the
 *     assertion is correct regardless of the host machine's timezone.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import FormGradingView from '@/views/FormGradingView.vue'
import type { GradingDetailRow, GradingParameterOption, GradingRecord, WeighbridgeRecordOption } from '@/services/gradingRecordRepo'

const { pushMock, useRouteMock } = vi.hoisted(() => ({
  pushMock: vi.fn(),
  useRouteMock: vi.fn(),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
  useRoute: useRouteMock,
}))

const { useAuthStoreMock } = vi.hoisted(() => ({ useAuthStoreMock: vi.fn() }))

vi.mock('@/stores/auth', () => ({
  useAuthStore: useAuthStoreMock,
}))

vi.mock('@/stores/floatingClock', () => ({
  useFloatingClockStore: () => ({ enabled: false, toggle: vi.fn() }),
}))

const {
  getDraftWithDetailsMock,
  getWeighbridgeRecordOptionsMock,
  getGradingParameterOptionsMock,
  saveDraftMock,
  pauseDraftWithFormDataMock,
  deleteDraftMock,
} = vi.hoisted(() => ({
  getDraftWithDetailsMock: vi.fn(),
  getWeighbridgeRecordOptionsMock: vi.fn(),
  getGradingParameterOptionsMock: vi.fn(),
  saveDraftMock: vi.fn(),
  pauseDraftWithFormDataMock: vi.fn(),
  deleteDraftMock: vi.fn(),
}))

vi.mock('@/services/gradingRecordRepo', () => {
  class GradingDetailRequiredError extends Error {
    constructor() {
      super('Minimal 1 baris grading detail harus diisi sebelum menyimpan.')
      this.name = 'GradingDetailRequiredError'
    }
  }

  return {
    default: {
      getDraftWithDetails: getDraftWithDetailsMock,
      getWeighbridgeRecordOptions: getWeighbridgeRecordOptionsMock,
      getGradingParameterOptions: getGradingParameterOptionsMock,
      saveDraft: saveDraftMock,
      pauseDraftWithFormData: pauseDraftWithFormDataMock,
      deleteDraft: deleteDraftMock,
    },
    GradingDetailRequiredError,
  }
})

// Imports the SAME mocked class the component itself sees (module
// resolution is hoisted/cached), so `instanceof` checks line up exactly.
import { GradingDetailRequiredError } from '@/services/gradingRecordRepo'

function makeDraftRecord(overrides: Partial<GradingRecord> = {}): GradingRecord {
  return {
    id: 'draft-1',
    station_id: 'station-1',
    grading_number: null,
    date: null,
    weighbridge_record_id: null,
    license_plate_no: null,
    vehicle_code: null,
    estate_supplier: null,
    division: null,
    netto: null,
    quantity: null,
    note: null,
    checked_by: null,
    acknowledged_by: null,
    status: 'draft_ongoing',
    created_by: 'user-1',
    created_at: '2026-08-17T07:00:00.000Z',
    updated_at: '2026-08-17T07:00:00.000Z',
    ...overrides,
  }
}

function makeDetailRow(overrides: Partial<GradingDetailRow> = {}): GradingDetailRow {
  return {
    id: 'detail-1',
    grading_record_id: 'draft-1',
    grading_parameter_id: 'param-kg',
    quantity: 10,
    uom: 'kg',
    percentage: 25,
    created_at: '2026-08-17T07:00:00.000Z',
    updated_at: '2026-08-17T07:00:00.000Z',
    ...overrides,
  }
}

const WB_OPTION_1: WeighbridgeRecordOption = {
  id: 'wb-1',
  wb_card_number: 'WB-2001',
  arrival_datetime: '2026-08-18T07:00:00.000Z',
  vehicle_number: 'B 1234 CD',
  estate_supplier: 'Estate A',
  division: 'Divisi 1',
}

const WB_OPTION_2: WeighbridgeRecordOption = {
  id: 'wb-2',
  wb_card_number: 'WB-2002',
  arrival_datetime: '2026-08-17T07:00:00.000Z',
  vehicle_number: 'B 5678 EF',
  estate_supplier: 'Estate B',
  division: 'Divisi 2',
}

const PARAM_KG: GradingParameterOption = { id: 'param-kg', name: 'Fraksi Matang', uom: 'kg', sort_order: 1 }
const PARAM_BUNCH: GradingParameterOption = { id: 'param-bunch', name: 'Jumlah Janjang', uom: 'bunch', sort_order: 2 }

function setCurrentUser(role: 'operator' | 'supervisor' = 'operator'): void {
  useAuthStoreMock.mockReturnValue({
    currentUser: { id: 'user-1', username: 'operator01', name: 'Operator Satu', role },
    logout: vi.fn().mockResolvedValue(undefined),
  })
}

// Matches FormGradingView.vue's own formatDateID()/formatTimeID() exactly.
function formatDateID(value: string): string {
  const date = new Date(value)
  if (!value || Number.isNaN(date.getTime())) return ''
  return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(date)
}

function formatTimeID(value: string): string {
  const date = new Date(value)
  if (!value || Number.isNaN(date.getTime())) return ''
  return new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false }).format(date)
}

// Matches FormGradingView.vue's own nowLocalDateTimeString() exactly, so
// the expected value is derived the same way the component derives it —
// timezone-agnostic (both read from the same faked "now").
function nowLocalDateTimeString(now: Date): string {
  const yyyy = now.getFullYear()
  const mm = String(now.getMonth() + 1).padStart(2, '0')
  const dd = String(now.getDate()).padStart(2, '0')
  const hh = String(now.getHours()).padStart(2, '0')
  const mi = String(now.getMinutes()).padStart(2, '0')
  const ss = String(now.getSeconds()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}T${hh}:${mi}:${ss}`
}

// --- SearchableSelect.vue interaction helpers -----------------------------
// Faithful replacements for the old `wrapper.find('[data-testid="..."]')
// .setValue(value)` / `.findAll('[data-testid="..."] option')` patterns
// used against a plain <select> — see this file's header comment for the
// full rationale.

function searchableSelectRoot(wrapper: ReturnType<typeof mount>, testId: string) {
  return wrapper.find(`[data-testid="${testId}"]`)
}

// The <select>.value equivalent — SearchableSelect.vue's root element
// carries `data-value`, mirroring the current modelValue.
function searchableSelectValue(wrapper: ReturnType<typeof mount>, testId: string): string {
  return searchableSelectRoot(wrapper, testId).attributes('data-value') ?? ''
}

async function openSearchableSelect(wrapper: ReturnType<typeof mount>, testId: string): Promise<void> {
  await searchableSelectRoot(wrapper, testId).find('input').trigger('focus')
}

function searchableSelectOptionTexts(wrapper: ReturnType<typeof mount>, testId: string): string[] {
  return searchableSelectRoot(wrapper, testId)
    .findAll('[role="option"]')
    .map((option) => option.text())
}

// Opens the popup, finds the `role="option"` item with the exact given
// label, and selects it via mousedown (SearchableSelect.vue commits the
// selection on mousedown, before any blur would close the popup) — the
// same open-then-click sequence a real user drives.
async function chooseSearchableOption(
  wrapper: ReturnType<typeof mount>,
  testId: string,
  optionLabel: string,
): Promise<void> {
  await openSearchableSelect(wrapper, testId)

  const match = searchableSelectRoot(wrapper, testId)
    .findAll('[role="option"]')
    .find((option) => option.text() === optionLabel)

  if (!match) {
    throw new Error(
      `chooseSearchableOption: no option labeled "${optionLabel}" for [data-testid="${testId}"]. Visible: ${searchableSelectOptionTexts(wrapper, testId).join(', ')}`,
    )
  }

  await match.trigger('mousedown')
}

// PARAM_KG/PARAM_BUNCH's ids -> labels, so every call site below can keep
// passing the same ids ('param-kg'/'param-bunch') it always has, with the
// id -> label lookup for the SearchableSelect interaction isolated here.
function parameterLabelById(parameterId: string): string {
  if (parameterId === PARAM_KG.id) return PARAM_KG.name
  if (parameterId === PARAM_BUNCH.id) return PARAM_BUNCH.name
  throw new Error(`parameterLabelById: unknown parameterId "${parameterId}"`)
}

async function fillRequiredHeaderFields(wrapper: ReturnType<typeof mount>): Promise<void> {
  await wrapper.find('#field-grading-no').setValue('GR-3001')
  await chooseSearchableOption(wrapper, 'wb-card-no-select', WB_OPTION_1.wb_card_number!)
  await wrapper.find('#field-vehicle-code').setValue('VC-001')
  await wrapper.find('#field-estate').setValue('Estate A')
  await wrapper.find('#field-netto-kg').setValue('1000')
  await wrapper.find('#field-quantity-bunch').setValue('50')
}

async function addValidDetailRow(
  wrapper: ReturnType<typeof mount>,
  index: number,
  parameterId: string,
  quantity: number,
): Promise<void> {
  await wrapper.find('[data-testid="add-detail-row-button"]').trigger('click')
  await chooseSearchableOption(wrapper, `detail-parameter-select-${index}`, parameterLabelById(parameterId))
  await wrapper.find(`#detail-qty-${index}`).setValue(String(quantity))
}

const T0 = '2026-08-18T08:00:00.000Z'

describe('FormGradingView', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(T0))
    vi.clearAllMocks()
    useRouteMock.mockReturnValue({ params: { id: 'draft-1' } })
    setCurrentUser('operator')
    getWeighbridgeRecordOptionsMock.mockResolvedValue([WB_OPTION_1, WB_OPTION_2])
    getGradingParameterOptionsMock.mockResolvedValue([PARAM_KG, PARAM_BUNCH])
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  // 1a
  it('sets date to current local time when draft is new (date empty)', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord({ date: null }), details: [] })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    const expectedLocal = nowLocalDateTimeString(new Date())
    expect((wrapper.find('#field-tanggal').element as HTMLInputElement).value).toBe(
      `${formatDateID(expectedLocal)}, ${formatTimeID(expectedLocal)}`,
    )
  })

  // 1b
  it('preserves the stored date when resuming a draft that already has one', async () => {
    const storedDate = '2026-08-15T02:30:00'
    getDraftWithDetailsMock.mockResolvedValueOnce({
      record: makeDraftRecord({ status: 'draft_paused', date: storedDate }),
      details: [],
    })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    expect((wrapper.find('#field-tanggal').element as HTMLInputElement).value).toBe(
      `${formatDateID(storedDate)}, ${formatTimeID(storedDate)}`,
    )
    const expectedLocal = nowLocalDateTimeString(new Date())
    expect((wrapper.find('#field-tanggal').element as HTMLInputElement).value).not.toBe(
      `${formatDateID(expectedLocal)}, ${formatTimeID(expectedLocal)}`,
    )
  })

  // 2
  it('populates the WB Card No dropdown from ALL local weighbridge_record rows', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord(), details: [] })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    await openSearchableSelect(wrapper, 'wb-card-no-select')
    const optionTexts = searchableSelectOptionTexts(wrapper, 'wb-card-no-select')
    expect(optionTexts).toHaveLength(2)
    expect(optionTexts[0]).toContain('WB-2001')
    expect(optionTexts[1]).toContain('WB-2002')
  })

  // 3
  it('auto-fills license_plate_no/estate_supplier/division when a WB Card No option is selected', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord(), details: [] })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    await chooseSearchableOption(wrapper, 'wb-card-no-select', 'WB-2001')

    expect((wrapper.find('#field-license-plate-no').element as HTMLInputElement).value).toBe('B 1234 CD')
    expect((wrapper.find('#field-estate').element as HTMLInputElement).value).toBe('Estate A')
    expect((wrapper.find('#field-divisi').element as HTMLInputElement).value).toBe('Divisi 1')
  })

  // 4
  it('keeps a manually-edited auto-filled field as-is across further form activity/re-renders', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord(), details: [] })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    await chooseSearchableOption(wrapper, 'wb-card-no-select', 'WB-2001')
    await wrapper.find('#field-license-plate-no').setValue('MANUAL-EDIT-001')

    // Unrelated form activity that causes re-renders but is not the WB
    // Card No select's own @select handler.
    await wrapper.find('#field-netto-kg').setValue('1000')
    await wrapper.find('#field-note').setValue('Sebuah catatan')
    await wrapper.find('[data-testid="add-detail-row-button"]').trigger('click')

    expect((wrapper.find('#field-license-plate-no').element as HTMLInputElement).value).toBe('MANUAL-EDIT-001')
  })

  // 5
  it('snapshots the selected Quality Parameter uom onto the row and renders UOM disabled', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord(), details: [] })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    await wrapper.find('[data-testid="add-detail-row-button"]').trigger('click')
    expect((wrapper.find('#detail-uom-0').element as HTMLInputElement).value).toBe('')

    await chooseSearchableOption(wrapper, 'detail-parameter-select-0', PARAM_BUNCH.name)

    expect((wrapper.find('#detail-uom-0').element as HTMLInputElement).value).toBe('bunch')
    expect(wrapper.find('#detail-uom-0').attributes('disabled')).toBeDefined()
  })

  // 6
  it('computes Percentage per row per its uom (kg -> /netto, bunch -> /quantity)', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord(), details: [] })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    await wrapper.find('#field-netto-kg').setValue('1000')
    await wrapper.find('#field-quantity-bunch').setValue('50')

    await addValidDetailRow(wrapper, 0, 'param-kg', 250)
    await addValidDetailRow(wrapper, 1, 'param-bunch', 10)

    expect(wrapper.find('[data-testid="detail-percentage-0"]').text()).toBe('25.00%')
    expect(wrapper.find('[data-testid="detail-percentage-1"]').text()).toBe('20.00%')
  })

  // 7
  it('recomputes Percentage when a row qty changes and when the header netto/quantity changes', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord(), details: [] })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    await wrapper.find('#field-netto-kg').setValue('1000')
    await addValidDetailRow(wrapper, 0, 'param-kg', 250)
    expect(wrapper.find('[data-testid="detail-percentage-0"]').text()).toBe('25.00%')

    // Row's own qty changes.
    await wrapper.find('#detail-qty-0').setValue('500')
    expect(wrapper.find('[data-testid="detail-percentage-0"]').text()).toBe('50.00%')

    // Header netto changes — affects every row using uom='kg'.
    await wrapper.find('#field-netto-kg').setValue('2000')
    expect(wrapper.find('[data-testid="detail-percentage-0"]').text()).toBe('25.00%')
  })

  // 8
  it('appends a new empty detail row on "Tambah baris"', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord(), details: [] })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    expect(wrapper.findAll('[data-testid="grading-detail-row"]')).toHaveLength(0)

    await wrapper.find('[data-testid="add-detail-row-button"]').trigger('click')

    const rows = wrapper.findAll('[data-testid="grading-detail-row"]')
    expect(rows).toHaveLength(1)
    expect(searchableSelectValue(wrapper, 'detail-parameter-select-0')).toBe('')
    expect((wrapper.find('#detail-qty-0').element as HTMLInputElement).value).toBe('')
  })

  // 8b — Quality Parameter uniqueness across rows
  it('excludes a Quality Parameter already selected in another row from that other row\'s dropdown, but keeps it in its own', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord(), details: [] })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    await wrapper.find('[data-testid="add-detail-row-button"]').trigger('click')
    await wrapper.find('[data-testid="add-detail-row-button"]').trigger('click')
    await chooseSearchableOption(wrapper, 'detail-parameter-select-0', PARAM_KG.name)

    await openSearchableSelect(wrapper, 'detail-parameter-select-0')
    const row0Labels = searchableSelectOptionTexts(wrapper, 'detail-parameter-select-0')
    await openSearchableSelect(wrapper, 'detail-parameter-select-1')
    const row1Labels = searchableSelectOptionTexts(wrapper, 'detail-parameter-select-1')

    expect(row0Labels).toContain(PARAM_KG.name) // still visible in its own dropdown
    expect(row1Labels).not.toContain(PARAM_KG.name) // hidden from the other row
    expect(row1Labels).toContain(PARAM_BUNCH.name) // unaffected parameter still available everywhere
  })

  it('makes a Quality Parameter available again in other rows once the row using it is removed', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord(), details: [] })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    await wrapper.find('[data-testid="add-detail-row-button"]').trigger('click')
    await wrapper.find('[data-testid="add-detail-row-button"]').trigger('click')
    await chooseSearchableOption(wrapper, 'detail-parameter-select-0', PARAM_KG.name)

    await openSearchableSelect(wrapper, 'detail-parameter-select-1')
    expect(searchableSelectOptionTexts(wrapper, 'detail-parameter-select-1')).not.toContain(PARAM_KG.name)

    await wrapper.findAll('[data-testid="remove-detail-row-button"]')[0].trigger('click')

    // The remaining row shifts down to index 0 once row 0 is removed.
    await openSearchableSelect(wrapper, 'detail-parameter-select-0')
    expect(searchableSelectOptionTexts(wrapper, 'detail-parameter-select-0')).toContain(PARAM_KG.name)
  })

  it('makes a Quality Parameter available again in other rows once the row\'s selection is changed to a different parameter', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord(), details: [] })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    await wrapper.find('[data-testid="add-detail-row-button"]').trigger('click')
    await wrapper.find('[data-testid="add-detail-row-button"]').trigger('click')
    await chooseSearchableOption(wrapper, 'detail-parameter-select-0', PARAM_KG.name)

    await openSearchableSelect(wrapper, 'detail-parameter-select-1')
    expect(searchableSelectOptionTexts(wrapper, 'detail-parameter-select-1')).not.toContain(PARAM_KG.name)

    await chooseSearchableOption(wrapper, 'detail-parameter-select-0', PARAM_BUNCH.name)

    await openSearchableSelect(wrapper, 'detail-parameter-select-1')
    const row1Labels = searchableSelectOptionTexts(wrapper, 'detail-parameter-select-1')
    expect(row1Labels).toContain(PARAM_KG.name)
    expect(row1Labels).not.toContain(PARAM_BUNCH.name)
  })

  // 9a
  it('queues an existing (has-id) row for deletion on Hapus baris rather than deleting immediately', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({
      record: makeDraftRecord({
        status: 'draft_paused',
        grading_number: 'GR-9001',
        weighbridge_record_id: 'wb-1',
        vehicle_code: 'VC-1',
        estate_supplier: 'Estate A',
        netto: 1000,
        quantity: 50,
      }),
      details: [
        makeDetailRow({ id: 'detail-remove-1', grading_parameter_id: 'param-kg', quantity: 100, uom: 'kg', percentage: 10 }),
        makeDetailRow({ id: 'detail-keep-1', grading_parameter_id: 'param-bunch', quantity: 5, uom: 'bunch', percentage: 10 }),
      ],
    })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    expect(wrapper.findAll('[data-testid="grading-detail-row"]')).toHaveLength(2)

    await wrapper.findAll('[data-testid="remove-detail-row-button"]')[0].trigger('click')

    // Removed from the UI immediately...
    expect(wrapper.findAll('[data-testid="grading-detail-row"]')).toHaveLength(1)
    // ...but not yet actually deleted anywhere (no repo call at all).
    expect(saveDraftMock).not.toHaveBeenCalled()
    expect(pauseDraftWithFormDataMock).not.toHaveBeenCalled()

    saveDraftMock.mockResolvedValueOnce({ record: makeDraftRecord({ status: 'saved' }), details: [] })
    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    // Only actually applied (as a queued deletion id) on the next Simpan.
    expect(saveDraftMock).toHaveBeenCalledWith(
      'draft-1',
      expect.any(Object),
      expect.arrayContaining([expect.objectContaining({ id: 'detail-keep-1' })]),
      ['detail-remove-1'],
    )
  })

  // 9b
  it('drops a newly-added (no-id) row from state without queuing any deletion on Hapus baris', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord(), details: [] })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    await fillRequiredHeaderFields(wrapper)
    await addValidDetailRow(wrapper, 0, 'param-kg', 100)
    await wrapper.find('[data-testid="add-detail-row-button"]').trigger('click') // row 1, empty, no id

    expect(wrapper.findAll('[data-testid="grading-detail-row"]')).toHaveLength(2)

    await wrapper.findAll('[data-testid="remove-detail-row-button"]')[1].trigger('click')

    expect(wrapper.findAll('[data-testid="grading-detail-row"]')).toHaveLength(1)

    saveDraftMock.mockResolvedValueOnce({ record: makeDraftRecord({ status: 'saved' }), details: [] })
    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(saveDraftMock).toHaveBeenCalledWith('draft-1', expect.any(Object), expect.any(Array), [])
  })

  // 10a
  it('blocks Simpan with inline per-field errors when required header fields are missing', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord(), details: [] })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('#field-grading-no-error').text()).toContain('wajib diisi')
    expect(wrapper.text()).toContain('WB Card No wajib diisi.')
    expect(wrapper.find('#field-vehicle-code-error').exists()).toBe(false)
    expect(wrapper.find('#field-estate-error').text()).toContain('wajib diisi')
    expect(wrapper.find('#field-netto-kg-error').text()).toContain('wajib diisi')
    expect(wrapper.find('#field-quantity-bunch-error').text()).toContain('wajib diisi')

    expect(saveDraftMock).not.toHaveBeenCalled()
    expect(pushMock).not.toHaveBeenCalled()
  })

  // 10b
  it('blocks Simpan with a dedicated error when there are zero valid detail rows', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord(), details: [] })

    const wrapper = mount(FormGradingView)
    await flushPromises()
    await fillRequiredHeaderFields(wrapper)

    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="detail-rows-error"]').text()).toContain('Minimal 1 baris')
    expect(saveDraftMock).not.toHaveBeenCalled()
    expect(pushMock).not.toHaveBeenCalled()
  })

  // 10c
  it('saves with the correctly computed payload and navigates to monitor-grading on success', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord({ date: null }), details: [] })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    await fillRequiredHeaderFields(wrapper)
    await wrapper.find('#field-note').setValue('Catatan uji')
    await addValidDetailRow(wrapper, 0, 'param-kg', 250)

    saveDraftMock.mockResolvedValueOnce({ record: makeDraftRecord({ status: 'saved' }), details: [] })

    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    const expectedLocal = nowLocalDateTimeString(new Date())

    expect(saveDraftMock).toHaveBeenCalledWith(
      'draft-1',
      {
        grading_number: 'GR-3001',
        date: expectedLocal,
        weighbridge_record_id: 'wb-1',
        license_plate_no: 'B 1234 CD',
        vehicle_code: 'VC-001',
        estate_supplier: 'Estate A',
        division: 'Divisi 1',
        netto: 1000,
        quantity: 50,
        note: 'Catatan uji',
      },
      [{ grading_parameter_id: 'param-kg', quantity: 250, uom: 'kg', percentage: 25 }],
      [],
    )
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-grading' })
    expect(wrapper.findAll('.form-field-error')).toHaveLength(0)
  })

  // 10d — defense-in-depth: the repo's GradingDetailRequiredError is
  // surfaced on the grid even if client-side validate() already passed.
  it('surfaces the repo GradingDetailRequiredError on the grid if the save call rejects with it', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord(), details: [] })

    const wrapper = mount(FormGradingView)
    await flushPromises()
    await fillRequiredHeaderFields(wrapper)
    await addValidDetailRow(wrapper, 0, 'param-kg', 250)

    saveDraftMock.mockRejectedValueOnce(new GradingDetailRequiredError())

    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="detail-rows-error"]').text()).toContain('Minimal 1 baris')
    expect(wrapper.find('.banner-error').exists()).toBe(false)
    expect(pushMock).not.toHaveBeenCalled()
  })

  // Resuming an existing paused draft populates header + detail rows.
  it('loads an existing paused draft header and detail rows into form state', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({
      record: makeDraftRecord({
        status: 'draft_paused',
        grading_number: 'GR-9002',
        weighbridge_record_id: 'wb-2',
        license_plate_no: 'B 5678 EF',
        vehicle_code: 'VC-002',
        estate_supplier: 'Estate B',
        division: 'Divisi 2',
        netto: 2000,
        quantity: 30,
        note: 'Catatan lama',
      }),
      details: [
        makeDetailRow({ id: 'detail-1', grading_parameter_id: 'param-kg', quantity: 400, uom: 'kg', percentage: 20 }),
      ],
    })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    expect((wrapper.find('#field-grading-no').element as HTMLInputElement).value).toBe('GR-9002')
    expect(searchableSelectValue(wrapper, 'wb-card-no-select')).toBe('wb-2')
    expect((wrapper.find('#field-license-plate-no').element as HTMLInputElement).value).toBe('B 5678 EF')
    expect((wrapper.find('#field-vehicle-code').element as HTMLInputElement).value).toBe('VC-002')
    expect((wrapper.find('#field-estate').element as HTMLInputElement).value).toBe('Estate B')
    expect((wrapper.find('#field-divisi').element as HTMLInputElement).value).toBe('Divisi 2')
    expect((wrapper.find('#field-netto-kg').element as HTMLInputElement).value).toBe('2000')
    expect((wrapper.find('#field-quantity-bunch').element as HTMLInputElement).value).toBe('30')
    expect((wrapper.find('#field-note').element as HTMLInputElement).value).toBe('Catatan lama')

    expect(wrapper.findAll('[data-testid="grading-detail-row"]')).toHaveLength(1)
    expect(searchableSelectValue(wrapper, 'detail-parameter-select-0')).toBe('param-kg')
    expect((wrapper.find('#detail-qty-0').element as HTMLInputElement).value).toBe('400')
  })

  // 11
  it('persists as-is via pauseDraftWithFormData with NO validation, even with incomplete required fields', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord(), details: [] })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    // Only a non-required field is touched — every required field is left
    // empty, and Pause must still succeed with no inline errors.
    await wrapper.find('#field-note').setValue('Progress checkpoint')

    pauseDraftWithFormDataMock.mockResolvedValueOnce({ record: makeDraftRecord({ status: 'draft_paused' }), details: [] })

    await wrapper.find('[data-testid="pause-button"]').trigger('click')
    await flushPromises()

    expect(pauseDraftWithFormDataMock).toHaveBeenCalledWith(
      'draft-1',
      expect.objectContaining({ note: 'Progress checkpoint', grading_number: '' }),
      expect.any(Array),
      [],
    )
    expect(wrapper.findAll('.form-field-error')).toHaveLength(0)
    expect(saveDraftMock).not.toHaveBeenCalled()
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-grading' })
  })

  // 12a
  it('shows a confirmation dialog before deleting on Clear', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord(), details: [] })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    await wrapper.find('[data-testid="clear-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('.confirm-dialog-overlay').exists()).toBe(true)
    expect(wrapper.text()).toContain('Hapus Draft')
    expect(deleteDraftMock).not.toHaveBeenCalled()
  })

  // 12b
  it('deletes the draft and navigates to Monitor Grading when Clear is confirmed', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord(), details: [] })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    await wrapper.find('[data-testid="clear-button"]').trigger('click')
    await flushPromises()

    deleteDraftMock.mockResolvedValueOnce(undefined)

    await wrapper.find('.confirm-dialog-button--confirm').trigger('click')
    await flushPromises()

    expect(deleteDraftMock).toHaveBeenCalledWith('draft-1')
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-grading' })
  })

  // 12c
  it('does not delete the draft when Clear confirmation is cancelled', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord(), details: [] })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    await wrapper.find('#field-grading-no').setValue('GR-KEEP')
    await wrapper.find('[data-testid="clear-button"]').trigger('click')
    await flushPromises()

    await wrapper.find('.confirm-dialog-button--cancel').trigger('click')
    await flushPromises()

    expect(wrapper.find('.confirm-dialog-overlay').exists()).toBe(false)
    expect(deleteDraftMock).not.toHaveBeenCalled()
    expect(pushMock).not.toHaveBeenCalled()
    expect((wrapper.find('#field-grading-no').element as HTMLInputElement).value).toBe('GR-KEEP')
  })

  // 13a
  it('shows a confirmation dialog on Back when the form is dirty', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord(), details: [] })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    await wrapper.find('#field-grading-no').setValue('GR-DIRTY')
    await wrapper.find('[data-testid="back-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('.confirm-dialog-overlay').exists()).toBe(true)
    expect(pushMock).not.toHaveBeenCalled()
  })

  // 13b
  it('navigates directly on Back when the form is not dirty', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord(), details: [] })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    await wrapper.find('[data-testid="back-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('.confirm-dialog-overlay').exists()).toBe(false)
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-grading' })
  })

  // 14
  it('navigates to the related route when a breadcrumb segment is tapped', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord(), details: [] })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    await wrapper.find('[data-testid="breadcrumb-home"]').trigger('click')
    expect(pushMock).toHaveBeenCalledWith({ name: 'home' })

    await wrapper.find('[data-testid="breadcrumb-production-process-activity"]').trigger('click')
    expect(pushMock).toHaveBeenCalledWith({ name: 'station-list' })

    await wrapper.find('[data-testid="breadcrumb-grading"]').trigger('click')
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-grading' })

    const current = wrapper.get('.breadcrumb-current')
    expect(current.text()).toBe('Form')
    expect(current.attributes('aria-current')).toBe('page')
    expect(current.element.tagName).not.toBe('BUTTON')
  })

  // 15
  it('opens the navigation menu with Ganti Password/Logout when the hamburger is tapped', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord(), details: [] })

    const wrapper = mount(FormGradingView)
    await flushPromises()

    expect(wrapper.find('[data-testid="nav-menu"]').exists()).toBe(false)

    await wrapper.find('[data-testid="hamburger-button"]').trigger('click')
    await flushPromises()

    const navMenu = wrapper.get('[data-testid="nav-menu"]')
    expect(navMenu.text()).toContain('Ganti Password')
    expect(navMenu.text()).toContain('Logout')

    await wrapper.find('[data-testid="nav-menu-change-password"]').trigger('click')
    expect(pushMock).toHaveBeenCalledWith({ name: 'change-password' })
  })

  // 16
  it('renders only the placeholder (plus a hint) with no crash when there is no local weighbridge_record data at all', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeDraftRecord(), details: [] })
    getWeighbridgeRecordOptionsMock.mockResolvedValueOnce([])

    const wrapper = mount(FormGradingView)
    await flushPromises()

    await openSearchableSelect(wrapper, 'wb-card-no-select')
    expect(searchableSelectOptionTexts(wrapper, 'wb-card-no-select')).toHaveLength(0)
    expect(wrapper.find('.searchable-select-empty').text()).toBe('Tidak ada data tersedia.')
    expect(wrapper.text()).toContain('Tidak ada data weighbridge lokal.')
  })
})
