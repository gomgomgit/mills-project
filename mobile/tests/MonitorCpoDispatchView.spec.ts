/**
 * MonitorCpoDispatchView.spec.ts — screen-064--monitor-cpo-dispatch
 * / usecase-079--monitor-cpo-dispatch.
 *
 * Mirrors MonitorKernelDispatchView.spec.ts's mocking strategy and
 * coverage, adapted for this station's event-log summary semantics
 * (countRecords/countEvents + a "last event" card, instead of a
 * tipping-progress counter).
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import MonitorCpoDispatchView from '@/views/MonitorCpoDispatchView.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import type { CpoDispatchDraftListItem } from '@/services/cpoDispatchRecordRepo'

const { pushMock } = vi.hoisted(() => ({ pushMock: vi.fn() }))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
  useRoute: () => ({ query: {} }),
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

vi.mock('@/stores/aiAssistant', () => ({
  useAiAssistantStore: () => ({
    isOpen: false,
    bubbleEnabled: true,
    open: vi.fn(),
    close: vi.fn(),
    toggleBubble: vi.fn(),
  }),
}))

const { getDraftsMock, createDraftMock, getTodaySummaryMock, getLastEventSummaryMock } = vi.hoisted(() => ({
  getDraftsMock: vi.fn(),
  createDraftMock: vi.fn(),
  getTodaySummaryMock: vi.fn(),
  getLastEventSummaryMock: vi.fn(),
}))

vi.mock('@/services/cpoDispatchRecordRepo', () => ({
  cpoDispatchRecordRepo: {
    getDrafts: getDraftsMock,
    createDraft: createDraftMock,
    getTodaySummary: getTodaySummaryMock,
    getLastEventSummary: getLastEventSummaryMock,
  },
}))

function makeDraft(overrides: Partial<CpoDispatchDraftListItem> & { id: string }): CpoDispatchDraftListItem {
  return {
    status: 'draft_ongoing',
    cpo_dispatch_id: 'CD-001',
    updated_at: '2026-08-31T08:00:00.000Z',
    ...overrides,
  }
}

describe('MonitorCpoDispatchView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    logoutMock.mockResolvedValue(undefined)
    useAuthStoreMock.mockReturnValue({
      currentUser: { id: 'user-1', username: 'operator01', name: 'Operator Satu', role: 'operator' },
      logout: logoutMock,
    })
    getDraftsMock.mockResolvedValue([])
    getTodaySummaryMock.mockResolvedValue({ countRecords: 0, countEvents: 0 })
    getLastEventSummaryMock.mockResolvedValue(null)
  })

  it('loads screen data successfully with a populated list when local records exist', async () => {
    const drafts = [
      makeDraft({ id: 'draft-1', cpo_dispatch_id: 'CD-100' }),
      makeDraft({ id: 'draft-2', cpo_dispatch_id: 'CD-200' }),
    ]
    getDraftsMock.mockResolvedValueOnce(drafts)

    const wrapper = mount(MonitorCpoDispatchView)
    await flushPromises()

    expect(getDraftsMock).toHaveBeenCalledWith('user-1')
    expect(wrapper.find('[data-testid="draft-list"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="draft-item-draft-1"]').text()).toContain('CD-100')
    expect(wrapper.find('[data-testid="draft-item-draft-2"]').text()).toContain('CD-200')
  })

  it('returns an empty-state list when no draft/pause records exist, with New Data still enabled', async () => {
    const wrapper = mount(MonitorCpoDispatchView)
    await flushPromises()

    expect(wrapper.find('[data-testid="draft-list-empty"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="new-data-button"]').attributes('disabled')).toBeUndefined()
  })

  it('renders every row with a uniform "Pause" label regardless of underlying status', async () => {
    const drafts = [
      makeDraft({ id: 'draft-a', status: 'draft_paused' }),
      makeDraft({ id: 'draft-b', status: 'draft_ongoing' }),
    ]
    getDraftsMock.mockResolvedValueOnce(drafts)

    const wrapper = mount(MonitorCpoDispatchView)
    await flushPromises()

    const badges = wrapper.findAllComponents(StatusBadge)
    expect(badges).toHaveLength(2)
    for (const badge of badges) {
      expect(badge.props('label')).toBe('Pause')
    }
  })

  it('creates a new draft record and navigates to Form CPO Dispatch when New Data is pressed', async () => {
    createDraftMock.mockResolvedValueOnce('new-draft-id')

    const wrapper = mount(MonitorCpoDispatchView)
    await flushPromises()

    await wrapper.get('[data-testid="new-data-button"]').trigger('click')
    await flushPromises()

    expect(createDraftMock).toHaveBeenCalledWith('user-1')
    expect(pushMock).toHaveBeenCalledWith({ name: 'cpo-dispatch-form', params: { id: 'new-draft-id' } })
  })

  it('navigates to Form CPO Dispatch with the tapped draft id, without mutating anything', async () => {
    getDraftsMock.mockResolvedValueOnce([makeDraft({ id: 'draft-existing' })])

    const wrapper = mount(MonitorCpoDispatchView)
    await flushPromises()

    await wrapper.get('[data-testid="draft-item-draft-existing"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'cpo-dispatch-form', params: { id: 'draft-existing' } })
    expect(createDraftMock).not.toHaveBeenCalled()
  })

  it('navigates to Data Preview CPO Dispatch (list mode) when Load Data is pressed', async () => {
    const wrapper = mount(MonitorCpoDispatchView)
    await flushPromises()

    await wrapper.get('[data-testid="load-data-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'data-preview-cpo-dispatch' })
  })

  it('navigates to Station List when Back is pressed', async () => {
    const wrapper = mount(MonitorCpoDispatchView)
    await flushPromises()

    await wrapper.get('[data-testid="back-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'station-list' })
  })

  it('renders the "Hari Ini" counters from getTodaySummary()', async () => {
    getTodaySummaryMock.mockResolvedValueOnce({ countRecords: 3, countEvents: 9 })

    const wrapper = mount(MonitorCpoDispatchView)
    await flushPromises()

    expect(wrapper.find('[data-testid="counter-count-records"]').text()).toBe('3')
    expect(wrapper.find('[data-testid="counter-count-events"]').text()).toBe('9')
  })

  it('renders a "Kejadian Terakhir" card when getLastEventSummary() resolves an event', async () => {
    getLastEventSummaryMock.mockResolvedValueOnce({
      recordId: 'rec-1',
      eventDate: '2026-08-31',
      destinationBuyer: 'PT CPO Buyer',
      tankerPlateNo: 'B 999 ZZ',
    })

    const wrapper = mount(MonitorCpoDispatchView)
    await flushPromises()

    const card = wrapper.get('[data-testid="last-event-card"]')
    expect(card.text()).toContain('PT CPO Buyer')
    expect(card.text()).toContain('B 999 ZZ')
  })

  it('renders an empty message in the last-event card when there is no event yet', async () => {
    const wrapper = mount(MonitorCpoDispatchView)
    await flushPromises()

    expect(wrapper.get('[data-testid="last-event-card"]').text()).toContain('Belum ada kejadian tercatat.')
  })

  it('opens the nav menu (Ganti Password, Logout) when the hamburger icon is tapped', async () => {
    const wrapper = mount(MonitorCpoDispatchView)
    await flushPromises()

    await wrapper.get('[data-testid="hamburger-button"]').trigger('click')

    expect(wrapper.find('[data-testid="nav-menu"]').exists()).toBe(true)
  })

  it('shows an error state when getDrafts() rejects', async () => {
    getDraftsMock.mockRejectedValueOnce(new Error('boom'))

    const wrapper = mount(MonitorCpoDispatchView)
    await flushPromises()

    expect(wrapper.text()).toContain('boom')
  })
})
