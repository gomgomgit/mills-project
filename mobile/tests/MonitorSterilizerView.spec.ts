/**
 * MonitorSterilizerView.spec.ts — screen-121--monitor-sterilizer
 * / usecase-121--monitor-sterilizer.
 *
 * Mirrors MonitorCpoDispatchView.spec.ts's mocking strategy and coverage,
 * adapted for this station's event-log summary semantics
 * (countRecords/countCycles + a "last cycle" card, instead of a
 * tipping-progress counter). This is the FINAL station of this project.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import MonitorSterilizerView from '@/views/MonitorSterilizerView.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import type { SterilizerDraftListItem } from '@/services/sterilizerRecordRepo'

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

const { getDraftsMock, createDraftMock, getTodaySummaryMock, getLastCycleSummaryMock } = vi.hoisted(() => ({
  getDraftsMock: vi.fn(),
  createDraftMock: vi.fn(),
  getTodaySummaryMock: vi.fn(),
  getLastCycleSummaryMock: vi.fn(),
}))

vi.mock('@/services/sterilizerRecordRepo', () => ({
  sterilizerRecordRepo: {
    getDrafts: getDraftsMock,
    createDraft: createDraftMock,
    getTodaySummary: getTodaySummaryMock,
    getLastCycleSummary: getLastCycleSummaryMock,
  },
}))

function makeDraft(overrides: Partial<SterilizerDraftListItem> & { id: string }): SterilizerDraftListItem {
  return {
    status: 'draft_ongoing',
    sterilizer_id: 'STR-001',
    updated_at: '2026-08-31T08:00:00.000Z',
    ...overrides,
  }
}

describe('MonitorSterilizerView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    logoutMock.mockResolvedValue(undefined)
    useAuthStoreMock.mockReturnValue({
      currentUser: { id: 'user-1', username: 'operator01', name: 'Operator Satu', role: 'operator' },
      logout: logoutMock,
    })
    getDraftsMock.mockResolvedValue([])
    getTodaySummaryMock.mockResolvedValue({ countRecords: 0, countCycles: 0 })
    getLastCycleSummaryMock.mockResolvedValue(null)
  })

  it('loads screen data successfully with a populated list when local records exist', async () => {
    const drafts = [
      makeDraft({ id: 'draft-1', sterilizer_id: 'STR-100' }),
      makeDraft({ id: 'draft-2', sterilizer_id: 'STR-200' }),
    ]
    getDraftsMock.mockResolvedValueOnce(drafts)

    const wrapper = mount(MonitorSterilizerView)
    await flushPromises()

    expect(getDraftsMock).toHaveBeenCalledWith('user-1')
    expect(wrapper.find('[data-testid="draft-list"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="draft-item-draft-1"]').text()).toContain('STR-100')
    expect(wrapper.find('[data-testid="draft-item-draft-2"]').text()).toContain('STR-200')
  })

  it('returns an empty-state list when no draft/pause records exist, with New Data still enabled', async () => {
    const wrapper = mount(MonitorSterilizerView)
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

    const wrapper = mount(MonitorSterilizerView)
    await flushPromises()

    const badges = wrapper.findAllComponents(StatusBadge)
    expect(badges).toHaveLength(2)
    for (const badge of badges) {
      expect(badge.props('label')).toBe('Pause')
    }
  })

  it('creates a new draft record and navigates to Form Sterilizer when New Data is pressed', async () => {
    createDraftMock.mockResolvedValueOnce('new-draft-id')

    const wrapper = mount(MonitorSterilizerView)
    await flushPromises()

    await wrapper.get('[data-testid="new-data-button"]').trigger('click')
    await flushPromises()

    expect(createDraftMock).toHaveBeenCalledWith('user-1')
    expect(pushMock).toHaveBeenCalledWith({ name: 'sterilizer-form', params: { id: 'new-draft-id' } })
  })

  it('navigates to Form Sterilizer with the tapped draft id, without mutating anything', async () => {
    getDraftsMock.mockResolvedValueOnce([makeDraft({ id: 'draft-existing' })])

    const wrapper = mount(MonitorSterilizerView)
    await flushPromises()

    await wrapper.get('[data-testid="draft-item-draft-existing"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'sterilizer-form', params: { id: 'draft-existing' } })
    expect(createDraftMock).not.toHaveBeenCalled()
  })

  it('navigates to Data Preview Sterilizer (list mode) when Load Data is pressed', async () => {
    const wrapper = mount(MonitorSterilizerView)
    await flushPromises()

    await wrapper.get('[data-testid="load-data-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'data-preview-sterilizer' })
  })

  it('navigates to Station List when Back is pressed', async () => {
    const wrapper = mount(MonitorSterilizerView)
    await flushPromises()

    await wrapper.get('[data-testid="back-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'station-list' })
  })

  it('renders the "Hari Ini" counters from getTodaySummary()', async () => {
    getTodaySummaryMock.mockResolvedValueOnce({ countRecords: 3, countCycles: 9 })

    const wrapper = mount(MonitorSterilizerView)
    await flushPromises()

    expect(wrapper.find('[data-testid="counter-count-records"]').text()).toBe('3')
    expect(wrapper.find('[data-testid="counter-count-cycles"]').text()).toBe('9')
  })

  it('renders a "Siklus Terakhir" card when getLastCycleSummary() resolves a cycle', async () => {
    getLastCycleSummaryMock.mockResolvedValueOnce({
      recordId: 'rec-1',
      sterilizerNo: '2',
      closeDoorTime: '07:00',
      durationMinutes: 70,
    })

    const wrapper = mount(MonitorSterilizerView)
    await flushPromises()

    const card = wrapper.get('[data-testid="last-cycle-card"]')
    expect(card.text()).toContain('Sterilizer No 2')
    expect(card.text()).toContain('07:00')
    expect(card.text()).toContain('70 menit')
  })

  it('renders an empty message in the last-cycle card when there is no cycle yet', async () => {
    const wrapper = mount(MonitorSterilizerView)
    await flushPromises()

    expect(wrapper.get('[data-testid="last-cycle-card"]').text()).toContain('Belum ada siklus tercatat.')
  })

  it('opens the nav menu (Ganti Password, Logout) when the hamburger icon is tapped', async () => {
    const wrapper = mount(MonitorSterilizerView)
    await flushPromises()

    await wrapper.get('[data-testid="hamburger-button"]').trigger('click')

    expect(wrapper.find('[data-testid="nav-menu"]').exists()).toBe(true)
  })

  it('shows an error state when getDrafts() rejects', async () => {
    getDraftsMock.mockRejectedValueOnce(new Error('boom'))

    const wrapper = mount(MonitorSterilizerView)
    await flushPromises()

    expect(wrapper.text()).toContain('boom')
  })
})
