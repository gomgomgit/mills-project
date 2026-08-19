/**
 * DataPreviewWeighbridgeView.spec.ts — screen-013--data-preview-weighbridge
 * / usecase-013--data-preview-weighbridge business_logic steps 1-10.
 *
 * Rewritten (2026-08-18, scope expansion) to match
 * DataPreviewWeighbridgeView.vue's rewrite adding a route-driven LIST mode
 * (default, no `:id` param) alongside the pre-existing DETAIL mode (`:id`
 * param present) — see that file's own header comment for the full
 * before/after. Component tests covering this screen's 18
 * unit_test_cases:
 *   1.  empty array when the user has no local weighbridge_record rows
 *   2.  all records returned when no date/search filter applied
 *   3.  exact date-match filter
 *   4.  case-insensitive substring filter on wb_card_number / driver_name
 *   5.  combined date + search filter matching nothing
 *   6.  draft_ongoing -> 'Pause' label (list mode)
 *   7.  draft_paused -> 'Pause' label (list mode)
 *   8.  saved -> 'Tersimpan' label (list mode)
 *   9.  synced -> 'Tersinkron' label (list mode)
 *   10. tap draft_ongoing item -> navigates to Form Weighbridge, no detail
 *       switch
 *   11. tap draft_paused item -> navigates to Form Weighbridge, no detail
 *       switch
 *   12. tap saved item -> pushes to this screen's own route WITH the id
 *       param (detail-mode transition)
 *   13. tap synced item -> pushes to this screen's own route WITH the id
 *       param (detail-mode transition)
 *   14. detail mode renders the found record read-only
 *   15. detail mode shows 'tidak ditemukan' error when record not found
 *   16. Back in list mode -> Monitor Weighbridge
 *   17. Back in detail mode -> list mode (id param removed), NOT Monitor
 *       Weighbridge
 *   18. full happy path — list renders correctly with mixed statuses
 *
 * Updated again (2026-08-18, additive change) for `loadList()` now
 * defaulting `dateFilter` to TODAY's local date (via the component's own
 * `todayLocalDateString()`, local getFullYear/getMonth+1/getDate —
 * NOT UTC/toISOString) instead of `''`, on every list-mode mount.
 * `searchFilter` still defaults to `''`; `onResetFilter()` still resets
 * `dateFilter` back to `''` (Reset Filter still shows everything, not
 * back-to-today) — both unchanged. Two things follow from this:
 *   - `makeRecord()`'s default `record_datetime` below is now computed as
 *     TODAY (via a test-local `todayLocalDateString()` that mirrors the
 *     component's own algorithm) instead of a hardcoded past date, so
 *     every pre-existing test that seeds records via `makeRecord()`
 *     without overriding `record_datetime` (unit_test_cases 2, 4, 6-13,
 *     18, plus the wb_card_number-fallback test) still renders those
 *     records under the new default today-filter, exactly as it did
 *     before under the old always-empty default. Tests that explicitly
 *     override `record_datetime` to exercise date filtering
 *     (unit_test_cases 3, 5) are untouched — they already set an explicit
 *     date filter before asserting.
 *   - Three new tests were added (19-21) directly covering the new
 *     default-to-today behavior itself: the date input's initial value,
 *     the pre-filtering it performs on first render with no user
 *     interaction, and the user's ability to still change/clear it
 *     afterwards.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import DataPreviewWeighbridgeView from '@/views/DataPreviewWeighbridgeView.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import type { WeighbridgeRecord } from '@/services/weighbridgeRecordRepo'

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

const { getAllRecordsMock, getDraftByIdMock } = vi.hoisted(() => ({
  getAllRecordsMock: vi.fn(),
  getDraftByIdMock: vi.fn(),
}))

vi.mock('@/services/weighbridgeRecordRepo', () => ({
  default: {
    getAllRecords: getAllRecordsMock,
    getDraftById: getDraftByIdMock,
  },
}))

// Mirrors DataPreviewWeighbridgeView.vue's own `todayLocalDateString()`
// exactly (local getFullYear/getMonth+1/getDate, zero-padded — NOT
// toISOString/UTC), so this file can independently compute the same
// "today" value the component computes at mount time, both for building
// default-dated fixtures and for asserting the date input's default value.
function todayLocalDateString(): string {
  const today = new Date()
  const yyyy = today.getFullYear()
  const mm = String(today.getMonth() + 1).padStart(2, '0')
  const dd = String(today.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

function makeRecord(overrides: Partial<WeighbridgeRecord> & { id: string }): WeighbridgeRecord {
  return {
    station_id: 'station-1',
    wb_card_number: 'WB-1001',
    weighbridge_type: 'receive',
    // Defaults to TODAY (not a fixed past date) so every pre-existing test
    // that doesn't override this field keeps rendering under the new
    // default-to-today date filter without needing per-test date setup.
    // entity-catalog v5 merged arrival_datetime/dispatch_datetime into this
    // single record_datetime field.
    record_datetime: `${todayLocalDateString()}T08:00`,
    vehicle_number: 'B 1234 CD',
    driver_name: 'Budi Santoso',
    estate_supplier: 'Estate A',
    destination: null,
    division: 'Divisi 1',
    block: 'Blok 3',
    gross_weight: 15000,
    tare_weight: 5000,
    net_weight: 10000,
    quantity: 1,
    checked_by: 'Supervisor Satu',
    acknowledged_by: 'Mill Manager',
    status: 'saved',
    created_by: 'user-1',
    created_at: '2026-08-17T07:00:00.000Z',
    updated_at: '2026-08-17T07:30:00.000Z',
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

describe('DataPreviewWeighbridgeView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    useAuthStoreMock.mockReturnValue({
      currentUser: { id: 'user-1', username: 'operator01', name: 'Operator Satu', role: 'operator' },
    })
    useRouteMock.mockReturnValue(listRoute())
    getAllRecordsMock.mockResolvedValue([])
  })

  /* ---------------------------------------------------------------- *
   * LIST mode — data loading + filters
   * ---------------------------------------------------------------- */

  // unit_test_case 1
  it('returns an empty array when the user has no local weighbridge_record rows', async () => {
    getAllRecordsMock.mockResolvedValueOnce([])

    const wrapper = mount(DataPreviewWeighbridgeView)
    await flushPromises()

    expect(getAllRecordsMock).toHaveBeenCalledWith('user-1')
    expect(wrapper.find('[data-testid="record-list-empty"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-list"]').exists()).toBe(false)
  })

  // unit_test_case 2
  it('returns all records when no date or search filter is applied', async () => {
    // Both records rely on makeRecord()'s default record_datetime, which
    // is TODAY — matching the date filter's new default, so they both
    // still render with zero user interaction (i.e. "no filter applied"
    // from the user's point of view).
    const records = [
      makeRecord({ id: 'rec-1', wb_card_number: 'WB-100', status: 'saved' }),
      makeRecord({ id: 'rec-2', wb_card_number: 'WB-200', status: 'synced' }),
    ]
    getAllRecordsMock.mockResolvedValueOnce(records)

    const wrapper = mount(DataPreviewWeighbridgeView)
    await flushPromises()

    expect(wrapper.find('[data-testid="record-item-rec-1"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-item-rec-2"]').exists()).toBe(true)
  })

  // unit_test_case 3
  it('filters records by exact date match when a date filter is applied', async () => {
    const records = [
      makeRecord({ id: 'rec-aug10', record_datetime: '2026-08-10T08:00', wb_card_number: 'WB-AUG10' }),
      makeRecord({ id: 'rec-aug11', record_datetime: '2026-08-11T08:00', wb_card_number: 'WB-AUG11' }),
    ]
    getAllRecordsMock.mockResolvedValueOnce(records)

    const wrapper = mount(DataPreviewWeighbridgeView)
    await flushPromises()

    await wrapper.find('[data-testid="date-filter-input"]').setValue('2026-08-11')
    await flushPromises()

    expect(wrapper.find('[data-testid="record-item-rec-aug11"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-item-rec-aug10"]').exists()).toBe(false)
  })

  // unit_test_case 4
  it('filters records by a case-insensitive substring on wb_card_number or driver_name', async () => {
    // All three records use makeRecord()'s default record_datetime
    // (TODAY), so they already pass the default date filter before the
    // search filter below is applied on top — no date-filter setup needed
    // to isolate the search-filter behavior under test here.
    const records = [
      makeRecord({ id: 'rec-card', wb_card_number: 'WB-1001', driver_name: 'Andi' }),
      makeRecord({ id: 'rec-driver', wb_card_number: 'WB-2002', driver_name: 'Budi Santoso' }),
      makeRecord({ id: 'rec-nomatch', wb_card_number: 'WB-3003', driver_name: 'Citra' }),
    ]
    getAllRecordsMock.mockResolvedValueOnce(records)

    const wrapper = mount(DataPreviewWeighbridgeView)
    await flushPromises()

    // Matches by wb_card_number, case-insensitive.
    await wrapper.find('[data-testid="search-filter-input"]').setValue('wb-10')
    await flushPromises()
    expect(wrapper.find('[data-testid="record-item-rec-card"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-item-rec-driver"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="record-item-rec-nomatch"]').exists()).toBe(false)

    // Matches by driver_name, case-insensitive.
    await wrapper.find('[data-testid="search-filter-input"]').setValue('BUDI')
    await flushPromises()
    expect(wrapper.find('[data-testid="record-item-rec-driver"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-item-rec-card"]').exists()).toBe(false)
  })

  // unit_test_case 5
  it('returns an empty array when the combined date and search filter matches no record', async () => {
    const records = [
      makeRecord({ id: 'rec-1', record_datetime: '2026-08-11T08:00', wb_card_number: 'WB-1001', driver_name: 'Budi' }),
    ]
    getAllRecordsMock.mockResolvedValueOnce(records)

    const wrapper = mount(DataPreviewWeighbridgeView)
    await flushPromises()

    await wrapper.find('[data-testid="date-filter-input"]').setValue('2026-08-11')
    await wrapper.find('[data-testid="search-filter-input"]').setValue('no-such-driver')
    await flushPromises()

    expect(wrapper.find('[data-testid="record-item-rec-1"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="record-list-empty"]').exists()).toBe(true)
  })

  /* ---------------------------------------------------------------- *
   * LIST mode — default date filter (today), additive change
   * ---------------------------------------------------------------- */

  // unit_test_case 19
  it("initializes dateFilter to today's date (YYYY-MM-DD, local) when list mode mounts", async () => {
    getAllRecordsMock.mockResolvedValueOnce([])

    const wrapper = mount(DataPreviewWeighbridgeView)
    await flushPromises()

    const input = wrapper.find('[data-testid="date-filter-input"]')
    expect(input.exists()).toBe(true)
    expect((input.element as HTMLInputElement).value).toBe(todayLocalDateString())
  })

  // unit_test_case 20
  it('filters records by dateFilter\'s default (today) value on initial render', async () => {
    const records = [
      // Default record_datetime (today) — should be visible by default.
      makeRecord({ id: 'rec-today', wb_card_number: 'WB-TODAY' }),
      // Explicit non-today date — should be filtered out by default.
      makeRecord({ id: 'rec-other-day', record_datetime: '2020-01-01T08:00', wb_card_number: 'WB-OTHERDAY' }),
    ]
    getAllRecordsMock.mockResolvedValueOnce(records)

    const wrapper = mount(DataPreviewWeighbridgeView)
    await flushPromises()

    expect(wrapper.find('[data-testid="record-item-rec-today"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-item-rec-other-day"]').exists()).toBe(false)
  })

  // unit_test_case 21
  it('allows user to change or clear the date filter after the default is applied', async () => {
    const records = [
      makeRecord({ id: 'rec-today', wb_card_number: 'WB-TODAY' }),
      makeRecord({ id: 'rec-past', record_datetime: '2020-01-01T08:00', wb_card_number: 'WB-PAST' }),
    ]
    getAllRecordsMock.mockResolvedValueOnce(records)

    const wrapper = mount(DataPreviewWeighbridgeView)
    await flushPromises()

    // Default (today) filter applied on mount, no interaction yet.
    expect(wrapper.find('[data-testid="record-item-rec-today"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-item-rec-past"]').exists()).toBe(false)

    // User changes the date filter to the other record's date.
    await wrapper.find('[data-testid="date-filter-input"]').setValue('2020-01-01')
    await flushPromises()
    expect(wrapper.find('[data-testid="record-item-rec-past"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-item-rec-today"]').exists()).toBe(false)

    // User clears the date filter — both records reappear (matches
    // unit_test_case 2's "no date filter -> all records" behavior).
    await wrapper.find('[data-testid="date-filter-input"]').setValue('')
    await flushPromises()
    expect(wrapper.find('[data-testid="record-item-rec-today"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-item-rec-past"]').exists()).toBe(true)
  })

  /* ---------------------------------------------------------------- *
   * LIST mode — status badge label mapping
   * ---------------------------------------------------------------- */

  // unit_test_case 6
  it("maps status 'draft_ongoing' to the 'Pause' label in list mode", async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-ongoing', status: 'draft_ongoing' })])

    const wrapper = mount(DataPreviewWeighbridgeView)
    await flushPromises()

    const badge = wrapper.findComponent(StatusBadge)
    expect(badge.props('status')).toBe('paused')
    expect(badge.props('label')).toBe('Pause')
    expect(badge.text()).toBe('Pause')
  })

  // unit_test_case 7
  it("maps status 'draft_paused' to the 'Pause' label in list mode", async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-paused', status: 'draft_paused' })])

    const wrapper = mount(DataPreviewWeighbridgeView)
    await flushPromises()

    const badge = wrapper.findComponent(StatusBadge)
    expect(badge.props('status')).toBe('paused')
    expect(badge.props('label')).toBe('Pause')
    expect(badge.text()).toBe('Pause')
  })

  // unit_test_case 8
  it("maps status 'saved' to the 'Tersimpan' label in list mode", async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-saved', status: 'saved' })])

    const wrapper = mount(DataPreviewWeighbridgeView)
    await flushPromises()

    const badge = wrapper.findComponent(StatusBadge)
    expect(badge.props('status')).toBe('none')
    expect(badge.props('label')).toBe('Tersimpan')
    expect(badge.text()).toBe('Tersimpan')
  })

  // unit_test_case 9
  it("maps status 'synced' to the 'Tersinkron' label in list mode", async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-synced', status: 'synced' })])

    const wrapper = mount(DataPreviewWeighbridgeView)
    await flushPromises()

    const badge = wrapper.findComponent(StatusBadge)
    expect(badge.props('status')).toBe('none')
    expect(badge.props('label')).toBe('Tersinkron')
    expect(badge.text()).toBe('Tersinkron')
  })

  /* ---------------------------------------------------------------- *
   * LIST mode — row-tap navigation
   * ---------------------------------------------------------------- */

  // unit_test_case 10
  it("navigates to Form Weighbridge (no detail switch) when a tapped item's status is 'draft_ongoing'", async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-ongoing', status: 'draft_ongoing' })])

    const wrapper = mount(DataPreviewWeighbridgeView)
    await flushPromises()

    await wrapper.find('[data-testid="record-item-rec-ongoing"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'weighbridge-form', params: { id: 'rec-ongoing' } })
    expect(pushMock).not.toHaveBeenCalledWith(
      expect.objectContaining({ name: 'data-preview-weighbridge', params: { id: 'rec-ongoing' } }),
    )
  })

  // unit_test_case 11
  it("navigates to Form Weighbridge (no detail switch) when a tapped item's status is 'draft_paused'", async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-paused', status: 'draft_paused' })])

    const wrapper = mount(DataPreviewWeighbridgeView)
    await flushPromises()

    await wrapper.find('[data-testid="record-item-rec-paused"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'weighbridge-form', params: { id: 'rec-paused' } })
    expect(pushMock).not.toHaveBeenCalledWith(
      expect.objectContaining({ name: 'data-preview-weighbridge', params: { id: 'rec-paused' } }),
    )
  })

  // unit_test_case 12
  it("switches to detail mode (pushes with the id param) when a tapped item's status is 'saved'", async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-saved', status: 'saved' })])

    const wrapper = mount(DataPreviewWeighbridgeView)
    await flushPromises()

    await wrapper.find('[data-testid="record-item-rec-saved"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'data-preview-weighbridge', params: { id: 'rec-saved' } })
    expect(pushMock).not.toHaveBeenCalledWith(expect.objectContaining({ name: 'weighbridge-form' }))
  })

  // unit_test_case 13
  it("switches to detail mode (pushes with the id param) when a tapped item's status is 'synced'", async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-synced', status: 'synced' })])

    const wrapper = mount(DataPreviewWeighbridgeView)
    await flushPromises()

    await wrapper.find('[data-testid="record-item-rec-synced"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'data-preview-weighbridge', params: { id: 'rec-synced' } })
    expect(pushMock).not.toHaveBeenCalledWith(expect.objectContaining({ name: 'weighbridge-form' }))
  })

  /* ---------------------------------------------------------------- *
   * DETAIL mode
   * ---------------------------------------------------------------- */

  // unit_test_case 14
  it('renders the record read-only when a record is found by id in detail mode (receive type)', async () => {
    useRouteMock.mockReturnValue(detailRoute('record-1'))
    // Detail mode is unaffected by the list's date-filter default (it
    // fetches by id via getDraftById, never touches loadList()/dateFilter)
    // — record_datetime is pinned explicitly here since this test asserts
    // its exact rendered value below, unlike list-mode tests that rely on
    // makeRecord()'s today-based default.
    getDraftByIdMock.mockResolvedValueOnce(
      makeRecord({ id: 'record-1', weighbridge_type: 'receive', record_datetime: '2026-08-17T08:00' }),
    )

    const wrapper = mount(DataPreviewWeighbridgeView)
    await flushPromises()

    expect(getDraftByIdMock).toHaveBeenCalledWith('record-1')
    expect(getAllRecordsMock).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="record-not-found"]').exists()).toBe(false)

    const fieldChecks: Array<[string, string]> = [
      ['#field-tipe-weighbridge', 'Receive'],
      ['#field-no-wb-card', 'WB-1001'],
      ['#field-tanggal-waktu-arrival', '2026-08-17T08:00'],
      ['#field-no-kendaraan', 'B 1234 CD'],
      ['#field-nama-sopir', 'Budi Santoso'],
      ['#field-estate-supplier', 'Estate A'],
      ['#field-divisi', 'Divisi 1'],
      ['#field-blok', 'Blok 3'],
      ['#field-berat-kotor-gross-weight', '15000'],
      ['#field-berat-kosong-tare-weight', '5000'],
      ['#field-berat-bersih-net-weight', '10000'],
      ['#field-kuantitas-tandan', '1'],
      ['#field-checked-by', 'Supervisor Satu'],
      ['#field-acknowledged-by', 'Mill Manager'],
    ]

    for (const [selector, expectedValue] of fieldChecks) {
      const field = wrapper.find(selector)
      expect(field.exists()).toBe(true)
      expect((field.element as HTMLInputElement).value).toBe(expectedValue)
      expect(field.attributes('disabled')).toBeDefined()
    }

    // 'Tanggal & Waktu Dispatch' and 'Tujuan Muatan' are receive-only
    // absent: neither field is rendered for a receive-type record.
    expect(wrapper.find('#field-tanggal-waktu-dispatch').exists()).toBe(false)
    expect(wrapper.find('#field-tujuan-muatan').exists()).toBe(false)
  })

  // unit_test_case 22 (entity-catalog v5) — dispatch type: single date field
  // relabeled to 'Tanggal & Waktu Dispatch', 'Tanggal & Waktu Arrival' is
  // NOT rendered, and Tujuan Muatan (destination) appears with its value.
  it('renders the record read-only when a record is found by id in detail mode (dispatch type)', async () => {
    useRouteMock.mockReturnValue(detailRoute('record-2'))
    getDraftByIdMock.mockResolvedValueOnce(
      makeRecord({
        id: 'record-2',
        weighbridge_type: 'dispatch',
        record_datetime: '2026-08-17T09:00',
        destination: 'PKS Sukamaju',
      }),
    )

    const wrapper = mount(DataPreviewWeighbridgeView)
    await flushPromises()

    expect(wrapper.find('#field-tipe-weighbridge').exists()).toBe(true)
    expect((wrapper.find('#field-tipe-weighbridge').element as HTMLInputElement).value).toBe('Dispatch')

    const dispatchDate = wrapper.find('#field-tanggal-waktu-dispatch')
    expect(dispatchDate.exists()).toBe(true)
    expect((dispatchDate.element as HTMLInputElement).value).toBe('2026-08-17T09:00')
    expect(wrapper.find('#field-tanggal-waktu-arrival').exists()).toBe(false)

    const destination = wrapper.find('#field-tujuan-muatan')
    expect(destination.exists()).toBe(true)
    expect((destination.element as HTMLInputElement).value).toBe('PKS Sukamaju')
  })

  // unit_test_case 15
  it("shows a 'record tidak ditemukan' error when the record is not found by id in detail mode", async () => {
    useRouteMock.mockReturnValue(detailRoute('does-not-exist'))
    getDraftByIdMock.mockResolvedValueOnce(null)

    const wrapper = mount(DataPreviewWeighbridgeView)
    await flushPromises()

    expect(getDraftByIdMock).toHaveBeenCalledWith('does-not-exist')

    const notFoundEl = wrapper.find('[data-testid="record-not-found"]')
    expect(notFoundEl.exists()).toBe(true)
    expect(notFoundEl.text().toLowerCase()).toContain('tidak ditemukan')
    expect(wrapper.find('.preview-body').exists()).toBe(false)
  })

  /* ---------------------------------------------------------------- *
   * Back navigation
   * ---------------------------------------------------------------- */

  // unit_test_case 16
  it('navigates to Monitor Weighbridge when Back is pressed in list mode', async () => {
    getAllRecordsMock.mockResolvedValueOnce([])

    const wrapper = mount(DataPreviewWeighbridgeView)
    await flushPromises()

    await wrapper.find('[data-testid="back-button"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-weighbridge' })
  })

  // unit_test_case 17
  it('navigates to list mode (id param removed) — NOT Monitor Weighbridge — when Back is pressed in detail mode', async () => {
    useRouteMock.mockReturnValue(detailRoute('record-1'))
    getDraftByIdMock.mockResolvedValueOnce(makeRecord({ id: 'record-1' }))

    const wrapper = mount(DataPreviewWeighbridgeView)
    await flushPromises()

    await wrapper.find('[data-testid="back-button"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'data-preview-weighbridge' })
    expect(pushMock).not.toHaveBeenCalledWith({ name: 'monitor-weighbridge' })
  })

  /* ---------------------------------------------------------------- *
   * Full happy path
   * ---------------------------------------------------------------- */

  // unit_test_case 18
  it('returns a success result when all conditions pass — list renders correctly with mixed statuses', async () => {
    const records = [
      makeRecord({ id: 'rec-ongoing', wb_card_number: 'WB-ONGOING', status: 'draft_ongoing' }),
      makeRecord({ id: 'rec-paused', wb_card_number: 'WB-PAUSED', status: 'draft_paused' }),
      makeRecord({ id: 'rec-saved', wb_card_number: 'WB-SAVED', status: 'saved' }),
      makeRecord({ id: 'rec-synced', wb_card_number: 'WB-SYNCED', status: 'synced' }),
    ]
    getAllRecordsMock.mockResolvedValueOnce(records)

    const wrapper = mount(DataPreviewWeighbridgeView)
    await flushPromises()

    expect(wrapper.find('[data-testid="record-list"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-list-empty"]').exists()).toBe(false)

    const items = wrapper.findAll('[data-testid^="record-item-"]')
    expect(items).toHaveLength(4)

    const badges = wrapper.findAllComponents(StatusBadge)
    expect(badges).toHaveLength(4)

    const labelByRecordId: Record<string, string> = {
      'rec-ongoing': 'Pause',
      'rec-paused': 'Pause',
      'rec-saved': 'Tersimpan',
      'rec-synced': 'Tersinkron',
    }

    for (const [id, label] of Object.entries(labelByRecordId)) {
      const item = wrapper.get(`[data-testid="record-item-${id}"]`)
      expect(item.text()).toContain(label)
    }
  })

  it("falls back to a placeholder label when a record's wb_card_number is null or empty", async () => {
    const records = [
      makeRecord({ id: 'rec-null', wb_card_number: null }),
      makeRecord({ id: 'rec-empty', wb_card_number: '   ' }),
    ]
    getAllRecordsMock.mockResolvedValueOnce(records)

    const wrapper = mount(DataPreviewWeighbridgeView)
    await flushPromises()

    expect(wrapper.get('[data-testid="record-item-rec-null"]').text()).toContain('WB Card belum diisi')
    expect(wrapper.get('[data-testid="record-item-rec-empty"]').text()).toContain('WB Card belum diisi')
  })

  it('does not load the record list when the current user has no id', async () => {
    useAuthStoreMock.mockReturnValueOnce({
      currentUser: { id: undefined, username: 'op2', name: 'Op Dua', role: 'operator' },
    })

    mount(DataPreviewWeighbridgeView)
    await flushPromises()

    expect(getAllRecordsMock).not.toHaveBeenCalled()
  })
})
