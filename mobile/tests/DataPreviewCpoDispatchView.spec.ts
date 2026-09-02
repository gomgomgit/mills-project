/**
 * DataPreviewCpoDispatchView.spec.ts —
 * screen-084--data-preview-cpo-dispatch /
 * usecase-081--data-preview-cpo-dispatch.
 *
 * Mirrors DataPreviewKernelDispatchView.spec.ts's mocking strategy —
 * `useRoute()` returns a real Vue `reactive()` proxy so the component's own
 * `watch(recordIdParam, ...)` reacts to in-place `params.id` mutation
 * (list <-> detail transitions within one mounted instance).
 */
import { reactive } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import DataPreviewCpoDispatchView from '@/views/DataPreviewCpoDispatchView.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import type { CpoDispatchDetailRow, CpoDispatchRecord } from '@/services/cpoDispatchRecordRepo'

const { pushMock, routeRef } = vi.hoisted(() => ({
  pushMock: vi.fn(),
  routeRef: { current: null as unknown as { params: { id?: string } } },
}))

vi.mock('vue-router', async () => {
  const { reactive } = await import('vue')
  routeRef.current = reactive({ params: {} as { id?: string } })

  return {
    useRouter: () => ({ push: pushMock }),
    useRoute: () => routeRef.current,
  }
})

const { useAuthStoreMock } = vi.hoisted(() => ({ useAuthStoreMock: vi.fn() }))

vi.mock('@/stores/auth', () => ({
  useAuthStore: useAuthStoreMock,
}))

vi.mock('@/stores/floatingClock', () => ({
  useFloatingClockStore: () => ({ enabled: false, toggle: vi.fn() }),
}))

vi.mock('@/stores/aiAssistant', () => ({
  useAiAssistantStore: () => ({
    isOpen: false,
    bubbleEnabled: true,
    open: vi.fn(),
    close: vi.fn(),
    toggleBubble: vi.fn(),
  }),
}))

const { getAllRecordsMock, getDraftWithDetailsMock } = vi.hoisted(() => ({
  getAllRecordsMock: vi.fn(),
  getDraftWithDetailsMock: vi.fn(),
}))

vi.mock('@/services/cpoDispatchRecordRepo', () => ({
  default: {
    getAllRecords: getAllRecordsMock,
    getDraftWithDetails: getDraftWithDetailsMock,
  },
}))

function makeRecord(overrides: Partial<CpoDispatchRecord> & { id: string }): CpoDispatchRecord {
  return {
    station_id: 'station-1',
    cpo_dispatch_id: 'CD-001',
    date: '2026-08-31T07:00:00',
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

function makeDetailRow(overrides: Partial<CpoDispatchDetailRow> = {}): CpoDispatchDetailRow {
  return {
    id: 'detail-1',
    cpo_dispatch_record_id: 'rec-1',
    event_date: '2026-08-31',
    shift: null,
    time_in: null,
    time_out: null,
    waybill_number: null,
    tanker_plate_no: 'B 1234 XY',
    transport_company: null,
    driver_name: null,
    storage_tank_source: null,
    seal_no_top: null,
    seal_no_bottom: null,
    gross_weight_mt: 10,
    tare_weight_mt: 2,
    net_weight_mt: 8,
    ffa_percent: null,
    moisture_percent: null,
    impurities_percent: null,
    dobi: 2.5,
    destination_buyer: 'PT CPO Buyer',
    weighbridge_operator: null,
    findings: null,
    created_at: '2026-08-31T07:00:00.000Z',
    updated_at: '2026-08-31T07:00:00.000Z',
    ...overrides,
  }
}

describe('DataPreviewCpoDispatchView', () => {
  beforeEach(() => {
    // vi.resetAllMocks() (not clearAllMocks()) — several tests below queue a
    // mockResolvedValueOnce() that the component never ends up calling (e.g.
    // clicking a saved/synced list item only asserts the router.push() call,
    // it doesn't actually navigate the mocked route), so a leftover queued
    // value must not leak into the next test's own getDraftWithDetailsMock
    // expectations.
    vi.resetAllMocks()
    // Fresh reactive route object per test — @vue/test-utils does not
    // auto-unmount wrappers between tests, so reusing ONE reactive object
    // across the whole file would let earlier tests' still-active
    // watch(recordIdParam, ...) effects keep firing (and re-calling the
    // repo mocks) whenever a later test mutates it, corrupting call
    // counts. A brand-new proxy per test means any leftover watchers from
    // earlier, no-longer-relevant component instances are watching a
    // now-abandoned object and can never fire again.
    routeRef.current = reactive({ params: {} as { id?: string } })
    useAuthStoreMock.mockReturnValue({
      currentUser: { id: 'user-1', username: 'operator01', name: 'Operator Satu', role: 'operator' },
    })
    getAllRecordsMock.mockResolvedValue([])
  })

  it('shows an empty-state message when the user has no local records', async () => {
    const wrapper = mount(DataPreviewCpoDispatchView)
    await flushPromises()

    expect(wrapper.find('[data-testid="list-empty"]').exists()).toBe(true)
  })

  it('lists records and filters by search keyword (case-insensitive)', async () => {
    getAllRecordsMock.mockResolvedValueOnce([
      makeRecord({ id: 'rec-1', cpo_dispatch_id: 'CD-100' }),
      makeRecord({ id: 'rec-2', cpo_dispatch_id: 'CD-200' }),
    ])

    const wrapper = mount(DataPreviewCpoDispatchView)
    await flushPromises()

    await wrapper.get('[data-testid="date-filter"]').setValue('')
    await wrapper.get('[data-testid="search-filter"]').setValue('cd-200')

    expect(wrapper.find('[data-testid="record-item-rec-1"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="record-item-rec-2"]').exists()).toBe(true)
  })

  it('shows a not-found message with a reset-filter option when filters match nothing', async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-1' })])

    const wrapper = mount(DataPreviewCpoDispatchView)
    await flushPromises()

    await wrapper.get('[data-testid="date-filter"]').setValue('')
    await wrapper.get('[data-testid="search-filter"]').setValue('no-match')

    expect(wrapper.find('[data-testid="list-not-found"]').exists()).toBe(true)

    await wrapper.get('[data-testid="reset-filter-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="record-item-rec-1"]').exists()).toBe(true)
  })

  it('maps status to the correct badge label', async () => {
    getAllRecordsMock.mockResolvedValueOnce([
      makeRecord({ id: 'rec-1', status: 'draft_ongoing' }),
      makeRecord({ id: 'rec-2', status: 'saved' }),
      makeRecord({ id: 'rec-3', status: 'synced' }),
    ])

    const wrapper = mount(DataPreviewCpoDispatchView)
    await flushPromises()
    await wrapper.get('[data-testid="date-filter"]').setValue('')

    const badges = wrapper.findAllComponents(StatusBadge)
    const labels = badges.map((b) => b.props('label'))
    expect(labels).toEqual(['Pause', 'Tersimpan', 'Tersinkron'])
  })

  it('tapping a draft/pause item navigates to the Form without switching to detail mode', async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-1', status: 'draft_paused' })])

    const wrapper = mount(DataPreviewCpoDispatchView)
    await flushPromises()
    await wrapper.get('[data-testid="date-filter"]').setValue('')

    await wrapper.get('[data-testid="record-item-rec-1"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'cpo-dispatch-form', params: { id: 'rec-1' } })
  })

  it('tapping a saved/synced item switches to detail mode via this screen\'s own route', async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-1', status: 'saved' })])
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeRecord({ id: 'rec-1' }), details: [] })

    const wrapper = mount(DataPreviewCpoDispatchView)
    await flushPromises()
    await wrapper.get('[data-testid="date-filter"]').setValue('')

    await wrapper.get('[data-testid="record-item-rec-1"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'data-preview-cpo-dispatch', params: { id: 'rec-1' } })
  })

  it('detail mode renders the header fields and the event-log table read-only', async () => {
    routeRef.current.params = { id: 'rec-1' }
    getDraftWithDetailsMock.mockResolvedValueOnce({
      record: makeRecord({ id: 'rec-1', cpo_dispatch_id: 'CD-XYZ' }),
      details: [makeDetailRow()],
    })

    const wrapper = mount(DataPreviewCpoDispatchView)
    await flushPromises()

    expect(wrapper.find('[data-testid="detail-cpo-dispatch-id"]').text()).toContain('CD-XYZ')
    expect(wrapper.find('[data-testid="cpo-dispatch-detail-log"]').text()).toContain('PT CPO Buyer')
    expect(wrapper.find('[data-testid="cpo-dispatch-detail-log"]').text()).toContain('B 1234 XY')
  })

  it('shows a not-found error when the record does not exist in detail mode', async () => {
    routeRef.current.params = { id: 'missing-id' }
    getDraftWithDetailsMock.mockResolvedValueOnce(null)

    const wrapper = mount(DataPreviewCpoDispatchView)
    await flushPromises()

    expect(wrapper.find('[data-testid="record-not-found"]').exists()).toBe(true)
  })

  it('Back in list mode navigates to Monitor CPO Dispatch', async () => {
    const wrapper = mount(DataPreviewCpoDispatchView)
    await flushPromises()

    await wrapper.get('[data-testid="back-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-cpo-dispatch' })
  })

  it('Back in detail mode returns to list mode (id param removed), not Monitor', async () => {
    routeRef.current.params = { id: 'rec-1' }
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeRecord({ id: 'rec-1' }), details: [] })

    const wrapper = mount(DataPreviewCpoDispatchView)
    await flushPromises()

    await wrapper.get('[data-testid="back-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'data-preview-cpo-dispatch' })
    expect(pushMock).not.toHaveBeenCalledWith({ name: 'monitor-cpo-dispatch' })
  })

  it('reacts to route param changes within one mounted instance (list -> detail)', async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-1' })])
    mount(DataPreviewCpoDispatchView)
    await flushPromises()

    expect(getAllRecordsMock).toHaveBeenCalledTimes(1)

    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeRecord({ id: 'rec-1' }), details: [] })
    routeRef.current.params = { id: 'rec-1' }
    await flushPromises()

    expect(getDraftWithDetailsMock).toHaveBeenCalledWith('rec-1')
  })
})
