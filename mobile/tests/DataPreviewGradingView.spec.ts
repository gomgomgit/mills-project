/**
 * DataPreviewGradingView.spec.ts — screen-014--data-preview-grading /
 * usecase-014--data-preview-grading.
 *
 * FULL REWRITE (2026-08-19, scope expansion) to match
 * DataPreviewGradingView.vue's own rewrite adding a route-driven LIST mode
 * (default, no `:id` param) alongside the pre-existing DETAIL mode (`:id`
 * param present) — mirrors DataPreviewWeighbridgeView.spec.ts's
 * (screen-013) own 2026-08-18 rewrite as closely as possible given this
 * screen's different detail-mode data source (getDraftWithDetails() +
 * getGradingParameterOptions(), not a single getDraftById()-style read).
 * The previous version of this file targeted the old single-record
 * read-only view shape and is now obsolete/fully replaced.
 *
 * Component tests:
 *   LIST mode
 *     1.  empty list state when the user has no local grading_record rows
 *     2.  date filter defaults to TODAY's local date on mount (local
 *         getFullYear/getMonth+1/getDate — NOT toISOString()/UTC)
 *     3.  date filter is changeable and clearable after the default,
 *         re-filtering the list each time
 *     4.  exact-date filtering behavior
 *     5.  case-insensitive search filtering on grading_number OR
 *         license_plate_no
 *     6.  combined date + search filter matching nothing -> not-found
 *         message + Reset Filter button; Reset Filter clears both filters
 *         back to the default (today's date cleared to '', search
 *         cleared to '')
 *     7-10. status label mapping: draft_ongoing/draft_paused -> 'Pause',
 *         saved -> 'Tersimpan', synced -> 'Tersinkron'
 *     11. tap a draft_ongoing/draft_paused item -> navigates to
 *         `grading-form` with that item's id, no detail-mode switch
 *     12. tap a saved/synced item -> pushes to this screen's own route
 *         WITH the id param (detail-mode transition)
 *   DETAIL mode
 *     13. renders header fields + detail grid rows with resolved Quality
 *         Parameter names (via getGradingParameterOptions()'s id-map);
 *         'Checked By'/'Acknowledged By' are NOT rendered anywhere
 *     14. shows a 'tidak ditemukan' error + Back button when the record
 *         is not found
 *   Back navigation
 *     15. list mode -> Monitor Grading (route name `monitor-grading`)
 *     16. detail mode -> list mode (id param removed, same route name
 *         `data-preview-grading`), NOT Monitor Grading
 *   Header/breadcrumb/nav-menu (verbatim pattern from
 *   MonitorGradingView.spec.ts / DataPreviewWeighbridgeView.spec.ts)
 *     17. breadcrumb segment taps navigate to Home / Production Process
 *         Activity / Grading; 'Load Data' renders as non-interactive
 *         current-page text
 *     18. hamburger opens the nav menu with Ganti Password / Logout
 *
 * Mocking strategy (mirrors DataPreviewWeighbridgeView.spec.ts exactly):
 *   - 'vue-router' is mocked at module level so `router.push` can be
 *     asserted via a hoisted `pushMock`, and the route's `:id` param is
 *     controlled per test via a hoisted `useRouteMock`, without a real
 *     router instance.
 *   - '@/stores/auth' is mocked at module level for `currentUser`.
 *   - '@/services/gradingRecordRepo' is mocked at module level
 *     (`getAllRecords`/`getDraftWithDetails`/`getGradingParameterOptions`)
 *     — the repo's own row-shape/SQL behavior is already covered by
 *     gradingRecordRepo.spec.ts; this file only exercises the view's own
 *     logic against the repo's public interface.
 *   - FormField.vue and StatusBadge.vue are NOT mocked — real rendering
 *     is asserted directly (field values/disabled state, badge
 *     status/label), same as DataPreviewWeighbridgeView.spec.ts.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import DataPreviewGradingView from '@/views/DataPreviewGradingView.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import type { GradingDetailRow, GradingParameterOption, GradingRecord } from '@/services/gradingRecordRepo'

const { pushMock, useRouteMock } = vi.hoisted(() => ({
  pushMock: vi.fn(),
  useRouteMock: vi.fn(),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
  useRoute: useRouteMock,
}))

const { useAuthStoreMock } = vi.hoisted(() => ({
  useAuthStoreMock: vi.fn(),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: useAuthStoreMock,
}))

vi.mock('@/stores/floatingClock', () => ({
  useFloatingClockStore: () => ({ enabled: false, toggle: vi.fn() }),
}))

const { getAllRecordsMock, getDraftWithDetailsMock, getGradingParameterOptionsMock } = vi.hoisted(() => ({
  getAllRecordsMock: vi.fn(),
  getDraftWithDetailsMock: vi.fn(),
  getGradingParameterOptionsMock: vi.fn(),
}))

vi.mock('@/services/gradingRecordRepo', () => ({
  default: {
    getAllRecords: getAllRecordsMock,
    getDraftWithDetails: getDraftWithDetailsMock,
    getGradingParameterOptions: getGradingParameterOptionsMock,
  },
}))

// Mirrors DataPreviewGradingView.vue's own `todayLocalDateString()`
// exactly (local getFullYear/getMonth+1/getDate, zero-padded — NOT
// toISOString/UTC), so this file can independently compute the same
// "today" value the component computes at mount time.
function todayLocalDateString(): string {
  const today = new Date()
  const yyyy = today.getFullYear()
  const mm = String(today.getMonth() + 1).padStart(2, '0')
  const dd = String(today.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

function makeRecord(overrides: Partial<GradingRecord> & { id: string }): GradingRecord {
  return {
    station_id: 'station-1',
    grading_number: 'GR-1001',
    // Defaults to TODAY (not a fixed past date) so every pre-existing test
    // that doesn't override this field keeps rendering under the default
    // today date filter without needing per-test date setup.
    date: todayLocalDateString(),
    weighbridge_record_id: 'wb-1',
    license_plate_no: 'B 1234 CD',
    vehicle_code: 'VC-001',
    estate_supplier: 'Estate A',
    division: 'Divisi 1',
    netto: 15000,
    quantity: 12,
    note: 'Catatan',
    checked_by: null,
    acknowledged_by: null,
    status: 'saved',
    created_by: 'user-1',
    created_at: '2026-08-17T07:00:00.000Z',
    updated_at: '2026-08-17T07:30:00.000Z',
    ...overrides,
  }
}

function makeDetailRow(overrides: Partial<GradingDetailRow> & { id: string }): GradingDetailRow {
  return {
    grading_record_id: 'rec-1',
    grading_parameter_id: 'param-1',
    quantity: 10,
    uom: 'kg',
    percentage: 25,
    created_at: '2026-08-17T07:00:00.000Z',
    updated_at: '2026-08-17T07:00:00.000Z',
    ...overrides,
  }
}

function makeParameterOption(overrides: Partial<GradingParameterOption> & { id: string; name: string }): GradingParameterOption {
  return {
    uom: 'kg',
    sort_order: 1,
    ...overrides,
  }
}

// List-mode route (no id param) — the default useRouteMock return value.
function listRoute() {
  return { params: {} }
}

// Detail-mode route (id param present).
function detailRoute(id: string) {
  return { params: { id } }
}

describe('DataPreviewGradingView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    useAuthStoreMock.mockReturnValue({
      currentUser: { id: 'user-1', username: 'operator01', name: 'Operator Satu', role: 'operator' },
    })
    useRouteMock.mockReturnValue(listRoute())
    getAllRecordsMock.mockResolvedValue([])
    getDraftWithDetailsMock.mockResolvedValue(null)
    getGradingParameterOptionsMock.mockResolvedValue([])
  })

  /* ---------------------------------------------------------------- *
   * LIST mode — empty state
   * ---------------------------------------------------------------- */

  it('shows the empty-list state when the user has no local grading_record rows', async () => {
    getAllRecordsMock.mockResolvedValueOnce([])

    const wrapper = mount(DataPreviewGradingView)
    await flushPromises()

    expect(getAllRecordsMock).toHaveBeenCalledWith('user-1')
    expect(wrapper.find('[data-testid="record-list-empty"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-list"]').exists()).toBe(false)
  })

  /* ---------------------------------------------------------------- *
   * LIST mode — default date filter (today)
   * ---------------------------------------------------------------- */

  it("initializes the date filter to today's date (YYYY-MM-DD, local) when list mode mounts", async () => {
    getAllRecordsMock.mockResolvedValueOnce([])

    const wrapper = mount(DataPreviewGradingView)
    await flushPromises()

    const input = wrapper.find('[data-testid="date-filter-input"]')
    expect(input.exists()).toBe(true)
    expect((input.element as HTMLInputElement).value).toBe(todayLocalDateString())
  })

  it('filters the list by the default (today) date value on initial render, with no user interaction', async () => {
    const records = [
      makeRecord({ id: 'rec-today', grading_number: 'GR-TODAY' }),
      makeRecord({ id: 'rec-other-day', date: '2020-01-01', grading_number: 'GR-OTHERDAY' }),
    ]
    getAllRecordsMock.mockResolvedValueOnce(records)

    const wrapper = mount(DataPreviewGradingView)
    await flushPromises()

    expect(wrapper.find('[data-testid="record-item-rec-today"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-item-rec-other-day"]').exists()).toBe(false)
  })

  it('allows the user to change or clear the date filter after the default is applied', async () => {
    const records = [
      makeRecord({ id: 'rec-today', grading_number: 'GR-TODAY' }),
      makeRecord({ id: 'rec-past', date: '2020-01-01', grading_number: 'GR-PAST' }),
    ]
    getAllRecordsMock.mockResolvedValueOnce(records)

    const wrapper = mount(DataPreviewGradingView)
    await flushPromises()

    // Default (today) filter applied on mount, no interaction yet.
    expect(wrapper.find('[data-testid="record-item-rec-today"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-item-rec-past"]').exists()).toBe(false)

    // User changes the date filter to the other record's date.
    await wrapper.find('[data-testid="date-filter-input"]').setValue('2020-01-01')
    await flushPromises()
    expect(wrapper.find('[data-testid="record-item-rec-past"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-item-rec-today"]').exists()).toBe(false)

    // User clears the date filter — both records reappear.
    await wrapper.find('[data-testid="date-filter-input"]').setValue('')
    await flushPromises()
    expect(wrapper.find('[data-testid="record-item-rec-today"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-item-rec-past"]').exists()).toBe(true)
  })

  /* ---------------------------------------------------------------- *
   * LIST mode — exact date filtering
   * ---------------------------------------------------------------- */

  it('filters records by exact date match when a date filter is applied', async () => {
    const records = [
      makeRecord({ id: 'rec-aug10', date: '2026-08-10', grading_number: 'GR-AUG10' }),
      makeRecord({ id: 'rec-aug11', date: '2026-08-11', grading_number: 'GR-AUG11' }),
    ]
    getAllRecordsMock.mockResolvedValueOnce(records)

    const wrapper = mount(DataPreviewGradingView)
    await flushPromises()

    await wrapper.find('[data-testid="date-filter-input"]').setValue('2026-08-11')
    await flushPromises()

    expect(wrapper.find('[data-testid="record-item-rec-aug11"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-item-rec-aug10"]').exists()).toBe(false)
  })

  /* ---------------------------------------------------------------- *
   * LIST mode — search filtering
   * ---------------------------------------------------------------- */

  it('filters records by a case-insensitive substring on grading_number or license_plate_no', async () => {
    const records = [
      makeRecord({ id: 'rec-number', grading_number: 'GR-1001', license_plate_no: 'B 1111 AA' }),
      makeRecord({ id: 'rec-plate', grading_number: 'GR-2002', license_plate_no: 'B 2222 BUDI' }),
      makeRecord({ id: 'rec-nomatch', grading_number: 'GR-3003', license_plate_no: 'B 3333 CC' }),
    ]
    getAllRecordsMock.mockResolvedValueOnce(records)

    const wrapper = mount(DataPreviewGradingView)
    await flushPromises()

    // Matches by grading_number, case-insensitive.
    await wrapper.find('[data-testid="search-filter-input"]').setValue('gr-10')
    await flushPromises()
    expect(wrapper.find('[data-testid="record-item-rec-number"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-item-rec-plate"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="record-item-rec-nomatch"]').exists()).toBe(false)

    // Matches by license_plate_no, case-insensitive.
    await wrapper.find('[data-testid="search-filter-input"]').setValue('BUDI')
    await flushPromises()
    expect(wrapper.find('[data-testid="record-item-rec-plate"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-item-rec-number"]').exists()).toBe(false)
  })

  /* ---------------------------------------------------------------- *
   * LIST mode — combined filters matching nothing, Reset Filter
   * ---------------------------------------------------------------- */

  it('shows a not-found message + Reset Filter button when the combined date + search filter matches nothing, and Reset Filter clears both filters', async () => {
    const records = [makeRecord({ id: 'rec-1', date: '2026-08-11', grading_number: 'GR-1001' })]
    getAllRecordsMock.mockResolvedValueOnce(records)

    const wrapper = mount(DataPreviewGradingView)
    await flushPromises()

    await wrapper.find('[data-testid="date-filter-input"]').setValue('2026-08-11')
    await wrapper.find('[data-testid="search-filter-input"]').setValue('no-such-grading-number')
    await flushPromises()

    expect(wrapper.find('[data-testid="record-item-rec-1"]').exists()).toBe(false)
    const emptyState = wrapper.find('[data-testid="record-list-empty"]')
    expect(emptyState.exists()).toBe(true)
    expect(emptyState.text().toLowerCase()).toContain('tidak ada')

    const resetButton = wrapper.find('[data-testid="reset-filter-button"]')
    expect(resetButton.exists()).toBe(true)

    await resetButton.trigger('click')
    await flushPromises()

    expect((wrapper.find('[data-testid="date-filter-input"]').element as HTMLInputElement).value).toBe('')
    expect((wrapper.find('[data-testid="search-filter-input"]').element as HTMLInputElement).value).toBe('')
    expect(wrapper.find('[data-testid="record-item-rec-1"]').exists()).toBe(true)
  })

  it('does not show the Reset Filter button when no filter is active and the list is genuinely empty', async () => {
    getAllRecordsMock.mockResolvedValueOnce([])

    const wrapper = mount(DataPreviewGradingView)
    await flushPromises()

    // Default date filter (today) counts as an active filter — clear it
    // explicitly to reach the "no filter at all" empty state.
    await wrapper.find('[data-testid="date-filter-input"]').setValue('')
    await flushPromises()

    expect(wrapper.find('[data-testid="reset-filter-button"]').exists()).toBe(false)
  })

  /* ---------------------------------------------------------------- *
   * LIST mode — status badge label mapping
   * ---------------------------------------------------------------- */

  it("maps status 'draft_ongoing' to the 'Pause' label in list mode", async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-ongoing', status: 'draft_ongoing' })])

    const wrapper = mount(DataPreviewGradingView)
    await flushPromises()

    const badge = wrapper.findComponent(StatusBadge)
    expect(badge.props('status')).toBe('paused')
    expect(badge.props('label')).toBe('Pause')
    expect(badge.text()).toBe('Pause')
  })

  it("maps status 'draft_paused' to the 'Pause' label in list mode", async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-paused', status: 'draft_paused' })])

    const wrapper = mount(DataPreviewGradingView)
    await flushPromises()

    const badge = wrapper.findComponent(StatusBadge)
    expect(badge.props('status')).toBe('paused')
    expect(badge.props('label')).toBe('Pause')
    expect(badge.text()).toBe('Pause')
  })

  it("maps status 'saved' to the 'Tersimpan' label in list mode", async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-saved', status: 'saved' })])

    const wrapper = mount(DataPreviewGradingView)
    await flushPromises()

    const badge = wrapper.findComponent(StatusBadge)
    expect(badge.props('status')).toBe('none')
    expect(badge.props('label')).toBe('Tersimpan')
    expect(badge.text()).toBe('Tersimpan')
  })

  it("maps status 'synced' to the 'Tersinkron' label in list mode", async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-synced', status: 'synced' })])

    const wrapper = mount(DataPreviewGradingView)
    await flushPromises()

    const badge = wrapper.findComponent(StatusBadge)
    expect(badge.props('status')).toBe('none')
    expect(badge.props('label')).toBe('Tersinkron')
    expect(badge.text()).toBe('Tersinkron')
  })

  /* ---------------------------------------------------------------- *
   * LIST mode — row-tap navigation
   * ---------------------------------------------------------------- */

  it("navigates to Form Grading (no detail-mode switch) when a tapped item's status is 'draft_ongoing'", async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-ongoing', status: 'draft_ongoing' })])

    const wrapper = mount(DataPreviewGradingView)
    await flushPromises()

    await wrapper.find('[data-testid="record-item-rec-ongoing"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'grading-form', params: { id: 'rec-ongoing' } })
    expect(pushMock).not.toHaveBeenCalledWith(
      expect.objectContaining({ name: 'data-preview-grading', params: { id: 'rec-ongoing' } }),
    )
  })

  it("navigates to Form Grading (no detail-mode switch) when a tapped item's status is 'draft_paused'", async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-paused', status: 'draft_paused' })])

    const wrapper = mount(DataPreviewGradingView)
    await flushPromises()

    await wrapper.find('[data-testid="record-item-rec-paused"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'grading-form', params: { id: 'rec-paused' } })
    expect(pushMock).not.toHaveBeenCalledWith(
      expect.objectContaining({ name: 'data-preview-grading', params: { id: 'rec-paused' } }),
    )
  })

  it("switches to detail mode (pushes with the id param) when a tapped item's status is 'saved'", async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-saved', status: 'saved' })])

    const wrapper = mount(DataPreviewGradingView)
    await flushPromises()

    await wrapper.find('[data-testid="record-item-rec-saved"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'data-preview-grading', params: { id: 'rec-saved' } })
    expect(pushMock).not.toHaveBeenCalledWith(expect.objectContaining({ name: 'grading-form' }))
  })

  it("switches to detail mode (pushes with the id param) when a tapped item's status is 'synced'", async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-synced', status: 'synced' })])

    const wrapper = mount(DataPreviewGradingView)
    await flushPromises()

    await wrapper.find('[data-testid="record-item-rec-synced"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'data-preview-grading', params: { id: 'rec-synced' } })
    expect(pushMock).not.toHaveBeenCalledWith(expect.objectContaining({ name: 'grading-form' }))
  })

  /* ---------------------------------------------------------------- *
   * DETAIL mode
   * ---------------------------------------------------------------- */

  it('renders header fields + detail grid rows with resolved Quality Parameter names in detail mode, and never renders Checked By / Acknowledged By', async () => {
    useRouteMock.mockReturnValue(detailRoute('record-1'))
    const record = makeRecord({
      id: 'record-1',
      grading_number: 'GR-5001',
      date: '2026-08-17',
      license_plate_no: 'B 1234 CD',
      vehicle_code: 'VC-009',
      estate_supplier: 'Estate C',
      division: 'Divisi 3',
      netto: 18000,
      quantity: 9,
      note: 'Catatan detail',
      status: 'saved',
    })
    const details = [
      makeDetailRow({ id: 'detail-1', grading_record_id: 'record-1', grading_parameter_id: 'param-1', quantity: 12, uom: 'kg' }),
      makeDetailRow({ id: 'detail-2', grading_record_id: 'record-1', grading_parameter_id: 'param-2', quantity: 5, uom: 'bunch' }),
    ]
    const parameterOptions = [
      makeParameterOption({ id: 'param-1', name: 'Brondolan Segar' }),
      makeParameterOption({ id: 'param-2', name: 'Masak' }),
    ]
    getDraftWithDetailsMock.mockResolvedValueOnce({ record, details })
    getGradingParameterOptionsMock.mockResolvedValueOnce(parameterOptions)

    const wrapper = mount(DataPreviewGradingView)
    await flushPromises()

    expect(getDraftWithDetailsMock).toHaveBeenCalledWith('record-1')
    expect(getGradingParameterOptionsMock).toHaveBeenCalledTimes(1)
    expect(getAllRecordsMock).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="record-not-found"]').exists()).toBe(false)

    const fieldChecks: Array<[string, string]> = [
      ['#field-no-grading', 'GR-5001'],
      ['#field-tanggal', '2026-08-17'],
      ['#field-no-polisi', 'B 1234 CD'],
      ['#field-kode-kendaraan', 'VC-009'],
      ['#field-estate-supplier', 'Estate C'],
      ['#field-divisi', 'Divisi 3'],
      ['#field-netto', '18000'],
      ['#field-kuantitas', '9'],
      ['#field-catatan', 'Catatan detail'],
    ]

    for (const [selector, expectedValue] of fieldChecks) {
      const field = wrapper.find(selector)
      expect(field.exists()).toBe(true)
      expect((field.element as HTMLInputElement).value).toBe(expectedValue)
      expect(field.attributes('disabled')).toBeDefined()
    }

    const detailRowsList = wrapper.find('[data-testid="detail-rows-list"]')
    expect(detailRowsList.exists()).toBe(true)
    expect(detailRowsList.text()).toContain('Brondolan Segar')
    expect(detailRowsList.text()).toContain('Masak')

    // 'Checked By' / 'Acknowledged By' are deliberately never rendered on
    // this screen (Form Grading collects neither field) — assert their
    // absence across the whole rendered output, not just as FormField
    // selectors.
    expect(wrapper.find('#field-checked-by').exists()).toBe(false)
    expect(wrapper.find('#field-acknowledged-by').exists()).toBe(false)
    expect(wrapper.text().toLowerCase()).not.toContain('checked by')
    expect(wrapper.text().toLowerCase()).not.toContain('acknowledged by')
  })

  it("shows a 'record tidak ditemukan' error + Back button when the record is not found by id in detail mode", async () => {
    useRouteMock.mockReturnValue(detailRoute('does-not-exist'))
    getDraftWithDetailsMock.mockResolvedValueOnce(null)
    getGradingParameterOptionsMock.mockResolvedValueOnce([])

    const wrapper = mount(DataPreviewGradingView)
    await flushPromises()

    expect(getDraftWithDetailsMock).toHaveBeenCalledWith('does-not-exist')

    const notFoundEl = wrapper.find('[data-testid="record-not-found"]')
    expect(notFoundEl.exists()).toBe(true)
    expect(notFoundEl.text().toLowerCase()).toContain('tidak ditemukan')
    expect(wrapper.find('.preview-body').exists()).toBe(false)

    const backButton = wrapper.find('[data-testid="back-button"]')
    expect(backButton.exists()).toBe(true)
  })

  /* ---------------------------------------------------------------- *
   * Back navigation
   * ---------------------------------------------------------------- */

  it('navigates to Monitor Grading when Back is pressed in list mode', async () => {
    getAllRecordsMock.mockResolvedValueOnce([])

    const wrapper = mount(DataPreviewGradingView)
    await flushPromises()

    await wrapper.find('[data-testid="back-button"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-grading' })
  })

  it('navigates to list mode (id param removed) — NOT Monitor Grading — when Back is pressed in detail mode', async () => {
    useRouteMock.mockReturnValue(detailRoute('record-1'))
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeRecord({ id: 'record-1' }), details: [] })
    getGradingParameterOptionsMock.mockResolvedValueOnce([])

    const wrapper = mount(DataPreviewGradingView)
    await flushPromises()

    await wrapper.find('[data-testid="back-button"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'data-preview-grading' })
    expect(pushMock).not.toHaveBeenCalledWith({ name: 'monitor-grading' })
  })

  /* ---------------------------------------------------------------- *
   * Breadcrumb
   * ---------------------------------------------------------------- */

  describe('breadcrumb', () => {
    beforeEach(() => {
      getAllRecordsMock.mockResolvedValue([])
    })

    it("navigates to 'home' when the 'Home' breadcrumb segment is tapped", async () => {
      const wrapper = mount(DataPreviewGradingView)
      await flushPromises()

      await wrapper.get('[data-testid="breadcrumb-home"]').trigger('click')

      expect(pushMock).toHaveBeenCalledWith({ name: 'home' })
    })

    it("navigates to 'station-list' when the 'Production Process Activity' breadcrumb segment is tapped", async () => {
      const wrapper = mount(DataPreviewGradingView)
      await flushPromises()

      await wrapper.get('[data-testid="breadcrumb-production-process-activity"]').trigger('click')

      expect(pushMock).toHaveBeenCalledWith({ name: 'station-list' })
    })

    it("navigates to 'monitor-grading' when the 'Grading' breadcrumb segment is tapped", async () => {
      const wrapper = mount(DataPreviewGradingView)
      await flushPromises()

      await wrapper.get('[data-testid="breadcrumb-grading"]').trigger('click')

      expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-grading' })
    })

    it("renders the 'Load Data' segment as non-interactive text with aria-current=\"page\", not a link/button", async () => {
      const wrapper = mount(DataPreviewGradingView)
      await flushPromises()

      const current = wrapper.get('.breadcrumb-current')
      expect(current.text()).toBe('Load Data')
      expect(current.attributes('aria-current')).toBe('page')
      expect(current.element.tagName).not.toBe('BUTTON')
      expect(current.element.tagName).not.toBe('A')
    })
  })

  /* ---------------------------------------------------------------- *
   * Hamburger nav menu
   * ---------------------------------------------------------------- */

  describe('menu navigasi (hamburger)', () => {
    beforeEach(() => {
      getAllRecordsMock.mockResolvedValue([])
    })

    it('opens the nav menu (Ganti Password, Logout) when the hamburger icon is tapped', async () => {
      const wrapper = mount(DataPreviewGradingView)
      await flushPromises()

      expect(wrapper.find('[data-testid="nav-menu"]').exists()).toBe(false)

      await wrapper.get('[data-testid="hamburger-button"]').trigger('click')

      const navMenu = wrapper.get('[data-testid="nav-menu"]')
      expect(navMenu.text()).toContain('Ganti Password')
      expect(navMenu.text()).toContain('Logout')
    })

    it("navigates to 'change-password' when the 'Ganti Password' nav menu item is tapped", async () => {
      const wrapper = mount(DataPreviewGradingView)
      await flushPromises()

      await wrapper.get('[data-testid="hamburger-button"]').trigger('click')
      await wrapper.get('[data-testid="nav-menu-change-password"]').trigger('click')

      expect(pushMock).toHaveBeenCalledWith({ name: 'change-password' })
    })

    it("logs out and navigates to 'login' when the 'Logout' nav menu item is tapped", async () => {
      const logoutMock = vi.fn().mockResolvedValue(undefined)
      useAuthStoreMock.mockReturnValue({
        currentUser: { id: 'user-1', username: 'operator01', name: 'Operator Satu', role: 'operator' },
        logout: logoutMock,
      })

      const wrapper = mount(DataPreviewGradingView)
      await flushPromises()

      await wrapper.get('[data-testid="hamburger-button"]').trigger('click')
      await wrapper.get('[data-testid="nav-menu-logout"]').trigger('click')
      await flushPromises()

      expect(logoutMock).toHaveBeenCalledTimes(1)
      expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
    })
  })

  /* ---------------------------------------------------------------- *
   * Misc — grading_number fallback label, no-user guard
   * ---------------------------------------------------------------- */

  it("falls back to a placeholder label when a record's grading_number is null or empty", async () => {
    const records = [
      makeRecord({ id: 'rec-null', grading_number: null }),
      makeRecord({ id: 'rec-empty', grading_number: '   ' }),
    ]
    getAllRecordsMock.mockResolvedValueOnce(records)

    const wrapper = mount(DataPreviewGradingView)
    await flushPromises()

    expect(wrapper.get('[data-testid="record-item-rec-null"]').text()).toContain('No. Grading belum diisi')
    expect(wrapper.get('[data-testid="record-item-rec-empty"]').text()).toContain('No. Grading belum diisi')
  })

  it('does not load the record list when the current user has no id', async () => {
    useAuthStoreMock.mockReturnValueOnce({
      currentUser: { id: undefined, username: 'op2', name: 'Op Dua', role: 'operator' },
    })

    mount(DataPreviewGradingView)
    await flushPromises()

    expect(getAllRecordsMock).not.toHaveBeenCalled()
  })
})
