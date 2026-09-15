/**
 * DataPreviewProcessWaterView.spec.ts — screen-082--data-preview-process-water
 * / usecase-069--data-preview-process-water.
 *
 * Mirrors DataPreviewThreshingView.spec.ts's dual-mode (list/detail)
 * mocking strategy — `useRoute()` is mocked with a reactive-enough shape so
 * `route.params.id` can be swapped between tests (list vs detail mode).
 * UNLIKE DataPreviewThreshingView.spec.ts: this station has NO
 * operational-target reference table, so no such assertion appears here.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import DataPreviewProcessWaterView from '@/views/DataPreviewProcessWaterView.vue'
import { canonicalTimeSlots, type ProcessWaterDetailRow, type ProcessWaterRecord } from '@/services/processWaterRecordRepo'

const { pushMock } = vi.hoisted(() => ({ pushMock: vi.fn() }))
const routeState = vi.hoisted(() => ({ params: {} as Record<string, string> }))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
  useRoute: () => routeState,
}))

const { useAuthStoreMock, logoutMock } = vi.hoisted(() => ({
  useAuthStoreMock: vi.fn(),
  logoutMock: vi.fn().mockResolvedValue(undefined),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: useAuthStoreMock,
}))

vi.mock('@/stores/floatingClock', () => ({
  useFloatingClockStore: () => ({ enabled: false, toggle: vi.fn() }),
}))

const { aiAssistantOpenMock, aiAssistantToggleBubbleMock } = vi.hoisted(() => ({
  aiAssistantOpenMock: vi.fn(),
  aiAssistantToggleBubbleMock: vi.fn(),
}))

vi.mock('@/stores/aiAssistant', () => ({
  useAiAssistantStore: () => ({
    isOpen: false,
    bubbleEnabled: true,
    open: aiAssistantOpenMock,
    close: vi.fn(),
    toggleBubble: aiAssistantToggleBubbleMock,
  }),
}))

const { getAllRecordsMock, getDraftWithDetailsMock } = vi.hoisted(() => ({
  getAllRecordsMock: vi.fn(),
  getDraftWithDetailsMock: vi.fn(),
}))

vi.mock('@/services/processWaterRecordRepo', async () => {
  const actual = await vi.importActual<typeof import('@/services/processWaterRecordRepo')>(
    '@/services/processWaterRecordRepo',
  )
  return {
    ...actual,
    default: {
      getAllRecords: getAllRecordsMock,
      getDraftWithDetails: getDraftWithDetailsMock,
    },
  }
})

// Local (device-timezone) today, matching the component's own
// todayLocalDateString() — NOT toISOString(), which is UTC-based and can
// land on a different calendar day than local time.
function todayLocalDateString(): string {
  const today = new Date()
  const yyyy = today.getFullYear()
  const mm = String(today.getMonth() + 1).padStart(2, '0')
  const dd = String(today.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

function makeRecord(overrides: Partial<ProcessWaterRecord> = {}): ProcessWaterRecord {
  return {
    id: 'rec-1',
    station_id: 'station-1',
    process_water_id: 'PW-001',
    date: `${todayLocalDateString()}T07:00:00`,
    note: null,
    checked_by: null,
    acknowledged_by: null,
    status: 'saved',
    created_by: 'user-1',
    created_at: '2026-08-31T07:00:00.000Z',
    updated_at: '2026-08-31T07:00:00.000Z',
    ...overrides,
  }
}

function makeAllDetailRows(): ProcessWaterDetailRow[] {
  return canonicalTimeSlots().map((timeSlot, index) => ({
    id: `detail-${index}`,
    process_water_record_id: 'rec-1',
    time_slot: timeSlot,
    shift: null,
    inspector_id: null,
    raw_water_flow_m3h: null,
    clarified_water_flow_m3h: null,
    softener_inlet_ph: null,
    softener_outlet_hardness_ppm: null,
    alum_dosing_kgh: null,
    polymer_dosing_gh: null,
    boiler_feed_tank_temp_c: null,
    boiler_feed_water_ph: null,
    boiler_feed_tds_ppm: null,
    action_taken_status: null,
    findings: null,
    created_at: '2026-08-31T07:00:00.000Z',
    updated_at: '2026-08-31T07:00:00.000Z',
  }))
}

beforeEach(() => {
  vi.clearAllMocks()
  routeState.params = {}
  useAuthStoreMock.mockReturnValue({
    currentUser: { id: 'user-1', name: 'Operator Satu', role: 'operator' },
    logout: logoutMock,
  })
  getAllRecordsMock.mockResolvedValue([])
})

describe('DataPreviewProcessWaterView — list mode', () => {
  it('defaults the date filter to today and shows only today-dated records initially', async () => {
    getAllRecordsMock.mockResolvedValue([
      makeRecord({ id: 'rec-today' }),
      makeRecord({ id: 'rec-yesterday', date: '2020-01-01T07:00:00' }),
    ])

    const wrapper = mount(DataPreviewProcessWaterView)
    await flushPromises()

    const items = wrapper.findAll('[data-testid^="record-item-"]')
    expect(items).toHaveLength(1)
    expect(wrapper.find('[data-testid="record-item-rec-today"]').exists()).toBe(true)
  })

  it('shows empty state when there are no local records', async () => {
    const wrapper = mount(DataPreviewProcessWaterView)
    await flushPromises()

    expect(wrapper.find('[data-testid="record-list-empty"]').exists()).toBe(true)
  })

  it('navigates to Form Process Water when a draft/pause item is tapped', async () => {
    getAllRecordsMock.mockResolvedValue([makeRecord({ id: 'rec-draft', status: 'draft_ongoing' })])

    const wrapper = mount(DataPreviewProcessWaterView)
    await flushPromises()

    await wrapper.find('[data-testid="record-item-rec-draft"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'process-water-form', params: { id: 'rec-draft' } })
  })

  it('navigates to detail mode when a saved/synced item is tapped', async () => {
    getAllRecordsMock.mockResolvedValue([makeRecord({ id: 'rec-saved', status: 'saved' })])

    const wrapper = mount(DataPreviewProcessWaterView)
    await flushPromises()

    await wrapper.find('[data-testid="record-item-rec-saved"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'data-preview-process-water', params: { id: 'rec-saved' } })
  })

  it('navigates to Monitor Process Water when Back is pressed in list mode', async () => {
    const wrapper = mount(DataPreviewProcessWaterView)
    await flushPromises()

    await wrapper.find('[data-testid="back-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-process-water' })
  })
})

describe('DataPreviewProcessWaterView — detail mode', () => {
  it('renders the record read-only with all of its detail rows (a full 24, when all canonical slots were added)', async () => {
    routeState.params = { id: 'rec-1' }
    getDraftWithDetailsMock.mockResolvedValue({ record: makeRecord(), details: makeAllDetailRows() })

    const wrapper = mount(DataPreviewProcessWaterView)
    await flushPromises()

    expect(wrapper.find('[data-testid="process-water-detail-rows-list"]').exists()).toBe(true)
    expect(wrapper.findAll('[data-testid^="process-water-detail-row-"]')).toHaveLength(24)
  })

  it('renders only the rows that actually exist when a record has fewer than 24 detail rows', async () => {
    routeState.params = { id: 'rec-1' }
    getDraftWithDetailsMock.mockResolvedValue({
      record: makeRecord(),
      details: [makeAllDetailRows()[0], makeAllDetailRows()[1]],
    })

    const wrapper = mount(DataPreviewProcessWaterView)
    await flushPromises()

    expect(wrapper.findAll('[data-testid^="process-water-detail-row-"]')).toHaveLength(2)
  })

  it('shows the empty-state message when a record has zero detail rows', async () => {
    routeState.params = { id: 'rec-1' }
    getDraftWithDetailsMock.mockResolvedValue({ record: makeRecord(), details: [] })

    const wrapper = mount(DataPreviewProcessWaterView)
    await flushPromises()

    expect(wrapper.find('[data-testid="process-water-detail-rows-empty"]').exists()).toBe(true)
    expect(wrapper.findAll('[data-testid^="process-water-detail-row-"]')).toHaveLength(0)
  })

  it("shows 'record tidak ditemukan' when the record is not found", async () => {
    routeState.params = { id: 'missing' }
    getDraftWithDetailsMock.mockResolvedValue(null)

    const wrapper = mount(DataPreviewProcessWaterView)
    await flushPromises()

    expect(wrapper.find('[data-testid="record-not-found"]').exists()).toBe(true)
  })

  it('shows the verifier name when known and a pending notice when not (2026-09-14)', async () => {
    routeState.params = { id: 'rec-1' }
    getDraftWithDetailsMock.mockResolvedValue({
      record: makeRecord({ checked_by: 'user-spv', checked_by_name: 'Supervisor Satu', acknowledged_by: '', acknowledged_by_name: '' }),
      details: makeAllDetailRows(),
    })

    const wrapper = mount(DataPreviewProcessWaterView)
    await flushPromises()

    // RecordVerificationStatus replaced the old disabled <input> that
    // rendered the raw checked_by uuid (2026-09-14): a known verifier shows
    // as a name, an unverified one says so explicitly.
    expect(wrapper.text()).toContain('Oleh Supervisor Satu')
    expect(wrapper.text()).toContain('Belum dikonfirmasi Mill Management')
  })

  it('navigates back to list mode (not Monitor Process Water) when Back is pressed in detail mode', async () => {
    routeState.params = { id: 'rec-1' }
    getDraftWithDetailsMock.mockResolvedValue({ record: makeRecord(), details: makeAllDetailRows() })

    const wrapper = mount(DataPreviewProcessWaterView)
    await flushPromises()

    await wrapper.find('[data-testid="back-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'data-preview-process-water' })
    expect(pushMock).not.toHaveBeenCalledWith({ name: 'monitor-process-water' })
  })
})
