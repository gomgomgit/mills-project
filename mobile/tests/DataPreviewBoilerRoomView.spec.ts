/**
 * DataPreviewBoilerRoomView.spec.ts — screen-088--data-preview-boiler-room
 * / usecase-105--data-preview-boiler-room.
 *
 * Mirrors DataPreviewEngineRoomView.spec.ts's dual-mode (list/detail)
 * mocking strategy — `useRoute()` is mocked with a reactive-enough shape so
 * `route.params.id` can be swapped between tests (list vs detail mode).
 * UNLIKE DataPreviewThreshingView.spec.ts: this station has NO
 * operational-target reference table, so no such assertion appears here.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import DataPreviewBoilerRoomView from '@/views/DataPreviewBoilerRoomView.vue'
import { canonicalTimeSlots, type BoilerRoomDetailRow, type BoilerRoomRecord } from '@/services/boilerRoomRecordRepo'

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

vi.mock('@/services/boilerRoomRecordRepo', async () => {
  const actual = await vi.importActual<typeof import('@/services/boilerRoomRecordRepo')>(
    '@/services/boilerRoomRecordRepo',
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

function makeRecord(overrides: Partial<BoilerRoomRecord> = {}): BoilerRoomRecord {
  return {
    id: 'rec-1',
    station_id: 'station-1',
    boiler_room_id: 'BR-001',
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

function makeAllDetailRows(): BoilerRoomDetailRow[] {
  return canonicalTimeSlots().map((timeSlot, index) => ({
    id: `detail-${index}`,
    boiler_room_record_id: 'rec-1',
    time_slot: timeSlot,
    steam_pressure_bar: null,
    steam_temp_c: null,
    feed_water_temp_c: null,
    feed_water_tank_level_percent: null,
    boiler_water_level_percent: null,
    water_tds_ppm: null,
    water_ph: null,
    fuel_feed_rate: null,
    id_fan_load: null,
    sa_fan_load: null,
    exhaust_gas_temp_c: null,
    dust_collector_differential_pressure_mmh2o: null,
    blowdown_executed: null,
    sootblowing_executed: null,
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

describe('DataPreviewBoilerRoomView — list mode', () => {
  it('defaults the date filter to today and shows only today-dated records initially', async () => {
    getAllRecordsMock.mockResolvedValue([
      makeRecord({ id: 'rec-today' }),
      makeRecord({ id: 'rec-yesterday', date: '2020-01-01T07:00:00' }),
    ])

    const wrapper = mount(DataPreviewBoilerRoomView)
    await flushPromises()

    const items = wrapper.findAll('[data-testid^="record-item-"]')
    expect(items).toHaveLength(1)
    expect(wrapper.find('[data-testid="record-item-rec-today"]').exists()).toBe(true)
  })

  it('shows empty state when there are no local records', async () => {
    const wrapper = mount(DataPreviewBoilerRoomView)
    await flushPromises()

    expect(wrapper.find('[data-testid="record-list-empty"]').exists()).toBe(true)
  })

  it('navigates to Form Boiler Room when a draft/pause item is tapped', async () => {
    getAllRecordsMock.mockResolvedValue([makeRecord({ id: 'rec-draft', status: 'draft_ongoing' })])

    const wrapper = mount(DataPreviewBoilerRoomView)
    await flushPromises()

    await wrapper.find('[data-testid="record-item-rec-draft"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'boiler-room-form', params: { id: 'rec-draft' } })
  })

  it('navigates to detail mode when a saved/synced item is tapped', async () => {
    getAllRecordsMock.mockResolvedValue([makeRecord({ id: 'rec-saved', status: 'saved' })])

    const wrapper = mount(DataPreviewBoilerRoomView)
    await flushPromises()

    await wrapper.find('[data-testid="record-item-rec-saved"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'data-preview-boiler-room', params: { id: 'rec-saved' } })
  })

  it('navigates to Monitor Boiler Room when Back is pressed in list mode', async () => {
    const wrapper = mount(DataPreviewBoilerRoomView)
    await flushPromises()

    await wrapper.find('[data-testid="back-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-boiler-room' })
  })
})

describe('DataPreviewBoilerRoomView — detail mode', () => {
  it('renders the record read-only with all of its detail rows (a full 24, when all canonical slots were added)', async () => {
    routeState.params = { id: 'rec-1' }
    getDraftWithDetailsMock.mockResolvedValue({ record: makeRecord(), details: makeAllDetailRows() })

    const wrapper = mount(DataPreviewBoilerRoomView)
    await flushPromises()

    expect(wrapper.find('[data-testid="boiler-room-detail-rows-list"]').exists()).toBe(true)
    expect(wrapper.findAll('[data-testid^="boiler-room-detail-row-"]')).toHaveLength(24)
  })

  it('renders only the rows that actually exist when a record has fewer than 24 detail rows', async () => {
    routeState.params = { id: 'rec-1' }
    getDraftWithDetailsMock.mockResolvedValue({
      record: makeRecord(),
      details: [makeAllDetailRows()[0], makeAllDetailRows()[1]],
    })

    const wrapper = mount(DataPreviewBoilerRoomView)
    await flushPromises()

    expect(wrapper.findAll('[data-testid^="boiler-room-detail-row-"]')).toHaveLength(2)
  })

  it('shows the empty-state message when a record has zero detail rows', async () => {
    routeState.params = { id: 'rec-1' }
    getDraftWithDetailsMock.mockResolvedValue({ record: makeRecord(), details: [] })

    const wrapper = mount(DataPreviewBoilerRoomView)
    await flushPromises()

    expect(wrapper.find('[data-testid="boiler-room-detail-rows-empty"]').exists()).toBe(true)
    expect(wrapper.findAll('[data-testid^="boiler-room-detail-row-"]')).toHaveLength(0)
  })

  it("shows 'record tidak ditemukan' when the record is not found", async () => {
    routeState.params = { id: 'missing' }
    getDraftWithDetailsMock.mockResolvedValue(null)

    const wrapper = mount(DataPreviewBoilerRoomView)
    await flushPromises()

    expect(wrapper.find('[data-testid="record-not-found"]').exists()).toBe(true)
  })

  it('renders Checked By and Acknowledged By read-only regardless of the current role', async () => {
    routeState.params = { id: 'rec-1' }
    getDraftWithDetailsMock.mockResolvedValue({
      record: makeRecord({ checked_by: 'Supervisor Satu', acknowledged_by: 'Mill Mgmt Satu' }),
      details: makeAllDetailRows(),
    })

    const wrapper = mount(DataPreviewBoilerRoomView)
    await flushPromises()

    // FormField renders values as disabled <input>s, not text nodes — so
    // assert against the input elements' `value`, not wrapper.text().
    expect((wrapper.find('#field-checked-by').element as HTMLInputElement).value).toBe('Supervisor Satu')
    expect((wrapper.find('#field-acknowledged-by').element as HTMLInputElement).value).toBe('Mill Mgmt Satu')
  })

  it('navigates back to list mode (not Monitor Boiler Room) when Back is pressed in detail mode', async () => {
    routeState.params = { id: 'rec-1' }
    getDraftWithDetailsMock.mockResolvedValue({ record: makeRecord(), details: makeAllDetailRows() })

    const wrapper = mount(DataPreviewBoilerRoomView)
    await flushPromises()

    await wrapper.find('[data-testid="back-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'data-preview-boiler-room' })
    expect(pushMock).not.toHaveBeenCalledWith({ name: 'monitor-boiler-room' })
  })
})
