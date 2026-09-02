/**
 * DataPreviewKernelDispatchView.spec.ts —
 * screen-083--data-preview-kernel-dispatch /
 * usecase-075--data-preview-kernel-dispatch.
 *
 * Mirrors DataPreviewSolidWasteDisposalView.spec.ts's mocking strategy —
 * `useRoute()` returns a real Vue `reactive()` proxy so the component's own
 * `watch(recordIdParam, ...)` reacts to in-place `params.id` mutation
 * (list <-> detail transitions within one mounted instance).
 */
import { reactive } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import DataPreviewKernelDispatchView from '@/views/DataPreviewKernelDispatchView.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import type { KernelDispatchDetailRow, KernelDispatchRecord } from '@/services/kernelDispatchRecordRepo'

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

vi.mock('@/services/kernelDispatchRecordRepo', () => ({
  default: {
    getAllRecords: getAllRecordsMock,
    getDraftWithDetails: getDraftWithDetailsMock,
  },
}))

function makeRecord(overrides: Partial<KernelDispatchRecord> & { id: string }): KernelDispatchRecord {
  return {
    station_id: 'station-1',
    kernel_dispatch_id: 'KD-001',
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

function makeDetailRow(overrides: Partial<KernelDispatchDetailRow> = {}): KernelDispatchDetailRow {
  return {
    id: 'detail-1',
    kernel_dispatch_record_id: 'rec-1',
    event_date: '2026-08-31',
    shift: null,
    weighbridge_ticket_no: null,
    waybill_number: null,
    transporter_contractor: null,
    vehicle_plate_no: 'B 1234 XY',
    driver_name: null,
    silo_source_id: null,
    destination_buyer: 'PT Kernel Buyer',
    gross_weight_mt: 10,
    tare_weight_mt: 2,
    net_weight_mt: 8,
    kernel_moisture_percent: null,
    dirt_impurities_percent: null,
    ffa_percent: null,
    broken_kernel_percent: null,
    security_seal_no_top: null,
    security_seal_no_bottom: null,
    weighbridge_operator_id: null,
    remarks_gate_status: 'released',
    findings: null,
    created_at: '2026-08-31T07:00:00.000Z',
    updated_at: '2026-08-31T07:00:00.000Z',
    ...overrides,
  }
}

describe('DataPreviewKernelDispatchView', () => {
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
    const wrapper = mount(DataPreviewKernelDispatchView)
    await flushPromises()

    expect(wrapper.find('[data-testid="list-empty"]').exists()).toBe(true)
  })

  it('lists records and filters by search keyword (case-insensitive)', async () => {
    getAllRecordsMock.mockResolvedValueOnce([
      makeRecord({ id: 'rec-1', kernel_dispatch_id: 'KD-100' }),
      makeRecord({ id: 'rec-2', kernel_dispatch_id: 'KD-200' }),
    ])

    const wrapper = mount(DataPreviewKernelDispatchView)
    await flushPromises()

    await wrapper.get('[data-testid="date-filter"]').setValue('')
    await wrapper.get('[data-testid="search-filter"]').setValue('kd-200')

    expect(wrapper.find('[data-testid="record-item-rec-1"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="record-item-rec-2"]').exists()).toBe(true)
  })

  it('shows a not-found message with a reset-filter option when filters match nothing', async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-1' })])

    const wrapper = mount(DataPreviewKernelDispatchView)
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

    const wrapper = mount(DataPreviewKernelDispatchView)
    await flushPromises()
    await wrapper.get('[data-testid="date-filter"]').setValue('')

    const badges = wrapper.findAllComponents(StatusBadge)
    const labels = badges.map((b) => b.props('label'))
    expect(labels).toEqual(['Pause', 'Tersimpan', 'Tersinkron'])
  })

  it('tapping a draft/pause item navigates to the Form without switching to detail mode', async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-1', status: 'draft_paused' })])

    const wrapper = mount(DataPreviewKernelDispatchView)
    await flushPromises()
    await wrapper.get('[data-testid="date-filter"]').setValue('')

    await wrapper.get('[data-testid="record-item-rec-1"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'kernel-dispatch-form', params: { id: 'rec-1' } })
  })

  it('tapping a saved/synced item switches to detail mode via this screen\'s own route', async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-1', status: 'saved' })])
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeRecord({ id: 'rec-1' }), details: [] })

    const wrapper = mount(DataPreviewKernelDispatchView)
    await flushPromises()
    await wrapper.get('[data-testid="date-filter"]').setValue('')

    await wrapper.get('[data-testid="record-item-rec-1"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'data-preview-kernel-dispatch', params: { id: 'rec-1' } })
  })

  it('detail mode renders the header fields and the event-log table read-only', async () => {
    routeRef.current.params = { id: 'rec-1' }
    getDraftWithDetailsMock.mockResolvedValueOnce({
      record: makeRecord({ id: 'rec-1', kernel_dispatch_id: 'KD-XYZ' }),
      details: [makeDetailRow()],
    })

    const wrapper = mount(DataPreviewKernelDispatchView)
    await flushPromises()

    expect(wrapper.find('[data-testid="detail-kernel-dispatch-id"]').text()).toContain('KD-XYZ')
    expect(wrapper.find('[data-testid="kernel-dispatch-detail-log"]').text()).toContain('PT Kernel Buyer')
    expect(wrapper.find('[data-testid="kernel-dispatch-detail-log"]').text()).toContain('B 1234 XY')
  })

  it('shows a not-found error when the record does not exist in detail mode', async () => {
    routeRef.current.params = { id: 'missing-id' }
    getDraftWithDetailsMock.mockResolvedValueOnce(null)

    const wrapper = mount(DataPreviewKernelDispatchView)
    await flushPromises()

    expect(wrapper.find('[data-testid="record-not-found"]').exists()).toBe(true)
  })

  it('Back in list mode navigates to Monitor Kernel Dispatch', async () => {
    const wrapper = mount(DataPreviewKernelDispatchView)
    await flushPromises()

    await wrapper.get('[data-testid="back-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-kernel-dispatch' })
  })

  it('Back in detail mode returns to list mode (id param removed), not Monitor', async () => {
    routeRef.current.params = { id: 'rec-1' }
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeRecord({ id: 'rec-1' }), details: [] })

    const wrapper = mount(DataPreviewKernelDispatchView)
    await flushPromises()

    await wrapper.get('[data-testid="back-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'data-preview-kernel-dispatch' })
    expect(pushMock).not.toHaveBeenCalledWith({ name: 'monitor-kernel-dispatch' })
  })

  it('reacts to route param changes within one mounted instance (list -> detail)', async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-1' })])
    mount(DataPreviewKernelDispatchView)
    await flushPromises()

    expect(getAllRecordsMock).toHaveBeenCalledTimes(1)

    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeRecord({ id: 'rec-1' }), details: [] })
    routeRef.current.params = { id: 'rec-1' }
    await flushPromises()

    expect(getDraftWithDetailsMock).toHaveBeenCalledWith('rec-1')
  })
})
