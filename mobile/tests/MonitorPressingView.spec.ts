/**
 * MonitorPressingView.spec.ts — screen-038--monitor-pressing /
 * usecase-038--monitor-pressing.
 *
 * Mirrors MonitorThreshingView.spec.ts's structure/pattern.
 *
 * Component tests covering:
 *   1. loads screen data successfully with a populated list
 *   2. empty-state list when no draft/pause records exist, New Data still
 *      enabled
 *   3. uniform 'Pause' label regardless of underlying status
 *   4. 'New Data' creates a new draft and navigates to Form Pressing
 *   5. tapping a list item navigates with the EXISTING draft id
 *   6. 'Load Data' navigates to Data Preview Pressing (list mode, no id)
 *   7. breadcrumb segment taps navigate
 *   8. hamburger opens nav-menu with Ganti Password / Logout
 *   9. 'Back' navigates to Station List
 *   10-11. "Hari Ini" counter renders whatever getTodaySummary() resolves,
 *      including all-zero
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import MonitorPressingView from '@/views/MonitorPressingView.vue'
import type { PressingDraftListItem } from '@/services/pressingRecordRepo'

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

const { getDraftsMock, createDraftMock, getTodaySummaryMock } = vi.hoisted(() => ({
  getDraftsMock: vi.fn(),
  createDraftMock: vi.fn(),
  getTodaySummaryMock: vi.fn(),
}))

vi.mock('@/services/pressingRecordRepo', () => ({
  pressingRecordRepo: {
    getDrafts: getDraftsMock,
    createDraft: createDraftMock,
    getTodaySummary: getTodaySummaryMock,
  },
}))

function makeDraft(overrides: Partial<PressingDraftListItem> & { id: string }): PressingDraftListItem {
  return {
    status: 'draft_ongoing',
    presser_id: 'PR-001',
    updated_at: '2026-08-24T08:00:00.000Z',
    ...overrides,
  }
}

beforeEach(() => {
  vi.clearAllMocks()
  useAuthStoreMock.mockReturnValue({
    currentUser: { id: 'user-1', name: 'Operator Satu', role: 'operator' },
    logout: logoutMock,
  })
  getDraftsMock.mockResolvedValue([])
  getTodaySummaryMock.mockResolvedValue({ countPressingRecord: 0, detailRowCount: 0 })
})

describe('MonitorPressingView', () => {
  it('loads and renders a populated draft/pause list', async () => {
    getDraftsMock.mockResolvedValue([
      makeDraft({ id: 'draft-1', presser_id: 'PR-001' }),
      makeDraft({ id: 'draft-2', presser_id: 'PR-002' }),
    ])

    const wrapper = mount(MonitorPressingView)
    await flushPromises()

    expect(wrapper.find('[data-testid="draft-list"]').exists()).toBe(true)
    expect(wrapper.findAll('[data-testid^="draft-item-"]')).toHaveLength(2)
    expect(wrapper.find('[data-testid="draft-list-empty"]').exists()).toBe(false)
  })

  it('shows empty state and keeps New Data enabled when there are no drafts', async () => {
    getDraftsMock.mockResolvedValue([])

    const wrapper = mount(MonitorPressingView)
    await flushPromises()

    expect(wrapper.find('[data-testid="draft-list-empty"]').exists()).toBe(true)
    const newDataButton = wrapper.find('[data-testid="new-data-button"]')
    expect(newDataButton.exists()).toBe(true)
    expect(newDataButton.attributes('disabled')).toBeUndefined()
  })

  it('labels every draft item uniformly "Pause" regardless of draft_ongoing/draft_paused status', async () => {
    getDraftsMock.mockResolvedValue([
      makeDraft({ id: 'draft-ongoing', status: 'draft_ongoing' }),
      makeDraft({ id: 'draft-paused', status: 'draft_paused' }),
    ])

    const wrapper = mount(MonitorPressingView)
    await flushPromises()

    const items = wrapper.findAll('[data-testid^="draft-item-"]')
    for (const item of items) {
      expect(item.text()).toContain('Pause')
    }
  })

  it('creates a new draft and navigates to Form Pressing when New Data is pressed', async () => {
    createDraftMock.mockResolvedValue('new-draft-id')

    const wrapper = mount(MonitorPressingView)
    await flushPromises()

    await wrapper.find('[data-testid="new-data-button"]').trigger('click')
    await flushPromises()

    expect(createDraftMock).toHaveBeenCalledWith('user-1')
    expect(pushMock).toHaveBeenCalledWith({ name: 'pressing-form', params: { id: 'new-draft-id' } })
  })

  it('navigates to Form Pressing with the existing draft id when a list item is tapped, without any status-update call', async () => {
    getDraftsMock.mockResolvedValue([makeDraft({ id: 'draft-existing', status: 'draft_paused' })])

    const wrapper = mount(MonitorPressingView)
    await flushPromises()

    await wrapper.find('[data-testid="draft-item-draft-existing"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'pressing-form', params: { id: 'draft-existing' } })
    expect(createDraftMock).not.toHaveBeenCalled()
  })

  it('navigates to Data Preview Pressing in list mode with no id when Load Data is pressed', async () => {
    const wrapper = mount(MonitorPressingView)
    await flushPromises()

    await wrapper.find('[data-testid="load-data-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'data-preview-pressing' })
  })

  it('navigates when a breadcrumb segment is tapped', async () => {
    const wrapper = mount(MonitorPressingView)
    await flushPromises()

    await wrapper.find('[data-testid="breadcrumb-home"]').trigger('click')
    expect(pushMock).toHaveBeenCalledWith({ name: 'home' })

    await wrapper.find('[data-testid="breadcrumb-production-process-activity"]').trigger('click')
    expect(pushMock).toHaveBeenCalledWith({ name: 'station-list' })
  })

  it('opens the nav menu with Ganti Password and Logout when hamburger is tapped', async () => {
    const wrapper = mount(MonitorPressingView)
    await flushPromises()

    await wrapper.find('[data-testid="hamburger-button"]').trigger('click')

    const menu = wrapper.find('[data-testid="nav-menu"]')
    expect(menu.exists()).toBe(true)
    expect(menu.text()).toContain('Ganti Password')
    expect(menu.text()).toContain('Logout')
  })

  it('navigates to Station List when Back is pressed', async () => {
    const wrapper = mount(MonitorPressingView)
    await flushPromises()

    await wrapper.find('[data-testid="back-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'station-list' })
  })

  it("renders the Hari Ini counter cards from getTodaySummary()'s resolved values", async () => {
    getTodaySummaryMock.mockResolvedValue({ countPressingRecord: 3, detailRowCount: 7 })

    const wrapper = mount(MonitorPressingView)
    await flushPromises()

    expect(wrapper.find('[data-testid="counter-count-pressing-record"]').text()).toBe('3')
    expect(wrapper.find('[data-testid="counter-detail-row-count"]').text()).toBe('7')
  })

  it('renders zero counters when there is no data today', async () => {
    const wrapper = mount(MonitorPressingView)
    await flushPromises()

    expect(wrapper.find('[data-testid="counter-count-pressing-record"]').text()).toBe('0')
    expect(wrapper.find('[data-testid="counter-detail-row-count"]').text()).toBe('0')
  })
})
