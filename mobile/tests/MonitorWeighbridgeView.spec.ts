/**
 * MonitorWeighbridgeView.spec.ts — screen-007--monitor-weighbridge /
 * usecase-007--monitor-weighbridge.
 *
 * Rewritten (2026-08-18) to match MonitorWeighbridgeView.vue's list-view
 * rewrite — the screen no longer shows a single-draft summary +
 * Pause/Clear actions (that whole surface, and this file's previous test
 * cases for it, are gone). It now renders a full scrollable list of the
 * current user's ongoing/paused drafts (weighbridgeRecordRepo.getDrafts()),
 * every row labeled uniformly "Pause" regardless of underlying status, plus
 * 'New Data' / 'Load Data' / 'Back' actions and the same header/breadcrumb/
 * hamburger nav-menu pattern as HomeView.vue / StationListView.vue.
 *
 * Component tests covering unit_test_cases 1-10 (+ light extra edge-case
 * coverage) per the tech spec:
 *   1. loads screen data successfully with a populated list
 *   2. empty-state list when no draft/pause records exist
 *   3. uniform 'Pause' label, ordered by updated_at DESC, regardless of
 *      underlying status (draft_ongoing vs draft_paused)
 *   4. full list without truncation for many records (50)
 *   5. 'New Data' creates a new draft and navigates to Form Weighbridge
 *   6. tapping a list item navigates with the EXISTING draft id and does
 *      NOT call any status-update/repo-write function
 *   7. 'Load Data' navigates to Data Preview Weighbridge (list mode, no id)
 *   8. breadcrumb segment taps navigate (Home, Production Process Activity)
 *   9. hamburger opens nav-menu with Ganti Password / Logout
 *   10. 'Back' navigates to Station List
 *   + WB Card fallback label when wb_card_number is null/empty
 *   + error state when getDrafts() rejects
 *   + does not load drafts when there is no current user id
 *
 * Mocking strategy (mirrors StationListView.spec.ts / HomeView.spec.ts):
 *   - 'vue-router' is mocked at module level so `router.push` can be
 *     asserted via a hoisted `pushMock`, without a real router instance.
 *   - '@/stores/auth' is mocked at module level via a hoisted
 *     `useAuthStoreMock` (a real `vi.fn()`), so this file never pulls in
 *     the real Pinia auth store / apiClient / tokenStorage chain, and
 *     individual tests can override the returned `currentUser`.
 *   - '@/services/weighbridgeRecordRepo' is mocked at module level (its own
 *     behavior is already covered by weighbridgeRecordRepo.spec.ts) so this
 *     file never touches localDb.ts / a real SQLite connection — the view
 *     is tested against the repo's public interface only. `resumeDraft` /
 *     `pauseDraft` / `deleteDraft` are mocked too (even though the current
 *     view no longer imports them) purely so test case 6 can assert they
 *     are never called without throwing on an unmocked import.
 *   - StatusBadge.vue is NOT mocked — it is rendered for real so test case
 *     3 can assert the actual status/label it renders per row.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import MonitorWeighbridgeView from '@/views/MonitorWeighbridgeView.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import type { WeighbridgeDraftListItem } from '@/services/weighbridgeRecordRepo'

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

const {
  getDraftsMock,
  createDraftMock,
  resumeDraftMock,
  pauseDraftMock,
  deleteDraftMock,
  getTodaySummaryMock,
} = vi.hoisted(() => ({
  getDraftsMock: vi.fn(),
  createDraftMock: vi.fn(),
  resumeDraftMock: vi.fn(),
  pauseDraftMock: vi.fn(),
  deleteDraftMock: vi.fn(),
  // "today's counter" addition — see MonitorWeighbridgeView.vue's
  // loadTodaySummary()/loadAll() header comment.
  getTodaySummaryMock: vi.fn(),
}))

vi.mock('@/services/weighbridgeRecordRepo', () => ({
  weighbridgeRecordRepo: {
    getDrafts: getDraftsMock,
    createDraft: createDraftMock,
    resumeDraft: resumeDraftMock,
    pauseDraft: pauseDraftMock,
    deleteDraft: deleteDraftMock,
    getTodaySummary: getTodaySummaryMock,
  },
}))

function makeDraft(overrides: Partial<WeighbridgeDraftListItem> & { id: string }): WeighbridgeDraftListItem {
  return {
    status: 'draft_ongoing',
    wb_card_number: 'WB-001',
    updated_at: '2026-08-18T08:00:00.000Z',
    ...overrides,
  }
}

describe('MonitorWeighbridgeView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    logoutMock.mockResolvedValue(undefined)
    useAuthStoreMock.mockReturnValue({
      currentUser: { id: 'user-1', username: 'operator01', name: 'Operator Satu', role: 'operator' },
      logout: logoutMock,
    })
    getDraftsMock.mockResolvedValue([])
    // default: all-zero, matching the component's own default ref value —
    // individual tests override this to assert the non-zero-render case.
    getTodaySummaryMock.mockResolvedValue({ countWb: 0, sumNetWeight: 0, sumQuantity: 0 })
  })

  // unit_test_case 1
  it('loads screen data successfully with a populated list when local records exist', async () => {
    const drafts = [
      makeDraft({ id: 'draft-1', wb_card_number: 'WB-100', updated_at: '2026-08-18T09:00:00.000Z' }),
      makeDraft({ id: 'draft-2', wb_card_number: 'WB-200', updated_at: '2026-08-18T08:00:00.000Z' }),
    ]
    getDraftsMock.mockResolvedValueOnce(drafts)

    const wrapper = mount(MonitorWeighbridgeView)
    await flushPromises()

    expect(getDraftsMock).toHaveBeenCalledWith('user-1')
    expect(wrapper.find('[data-testid="draft-list"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="draft-item-draft-1"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="draft-item-draft-2"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="draft-item-draft-1"]').text()).toContain('WB-100')
    expect(wrapper.find('[data-testid="draft-item-draft-2"]').text()).toContain('WB-200')
  })

  // unit_test_case 2
  it('returns an empty-state list when no draft/pause records exist for the current user, with New Data still enabled', async () => {
    getDraftsMock.mockResolvedValueOnce([])

    const wrapper = mount(MonitorWeighbridgeView)
    await flushPromises()

    expect(wrapper.find('[data-testid="draft-list-empty"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Belum ada draft timbangan tersimpan.')
    expect(wrapper.find('[data-testid="draft-list"]').exists()).toBe(false)

    const newDataButton = wrapper.find('[data-testid="new-data-button"]')
    expect(newDataButton.attributes('disabled')).toBeUndefined()
  })

  // unit_test_case 3
  it('renders every row with a uniform "Pause" label ordered by updated_at DESC, regardless of underlying status', async () => {
    // Deliberately returned already ordered by updated_at DESC (mirrors
    // what the real repo's SQL ORDER BY does) — the view does not
    // re-sort, it renders in list order.
    const drafts = [
      makeDraft({ id: 'draft-newest', status: 'draft_paused', updated_at: '2026-08-18T10:00:00.000Z' }),
      makeDraft({ id: 'draft-middle', status: 'draft_ongoing', updated_at: '2026-08-18T09:00:00.000Z' }),
      makeDraft({ id: 'draft-oldest', status: 'draft_paused', updated_at: '2026-08-18T08:00:00.000Z' }),
    ]
    getDraftsMock.mockResolvedValueOnce(drafts)

    const wrapper = mount(MonitorWeighbridgeView)
    await flushPromises()

    const renderedIds = wrapper
      .findAll('[data-testid^="draft-item-"]')
      .map((item) => item.attributes('data-testid'))
    expect(renderedIds).toEqual(['draft-item-draft-newest', 'draft-item-draft-middle', 'draft-item-draft-oldest'])

    const badges = wrapper.findAllComponents(StatusBadge)
    expect(badges).toHaveLength(3)
    for (const badge of badges) {
      // Every row is forced to status="paused"/label="Pause", regardless
      // of the underlying draft's actual status (ongoing vs paused).
      expect(badge.props('status')).toBe('paused')
      expect(badge.props('label')).toBe('Pause')
      expect(badge.text()).toBe('Pause')
    }
  })

  // unit_test_case 4
  it('renders the full list without truncation when many draft/pause records exist (e.g. 50)', async () => {
    const drafts = Array.from({ length: 50 }, (_, index) =>
      makeDraft({
        id: `draft-${index}`,
        wb_card_number: `WB-${index}`,
        updated_at: new Date(Date.now() - index * 1000).toISOString(),
      }),
    )
    getDraftsMock.mockResolvedValueOnce(drafts)

    const wrapper = mount(MonitorWeighbridgeView)
    await flushPromises()

    expect(wrapper.findAll('[data-testid^="draft-item-"]')).toHaveLength(50)
    expect(wrapper.findAll('li[role="listitem"]')).toHaveLength(50)
  })

  // unit_test_case 5
  it('creates a new draft record and navigates to Form Weighbridge when New Data is pressed', async () => {
    getDraftsMock.mockResolvedValueOnce([])
    createDraftMock.mockResolvedValueOnce('new-draft-id')

    const wrapper = mount(MonitorWeighbridgeView)
    await flushPromises()

    await wrapper.get('[data-testid="new-data-button"]').trigger('click')
    await flushPromises()

    expect(createDraftMock).toHaveBeenCalledWith('user-1')
    expect(pushMock).toHaveBeenCalledWith({ name: 'weighbridge-form', params: { id: 'new-draft-id' } })
  })

  // unit_test_case 6
  it('navigates to Form Weighbridge with the selected draft id and does not change its status when a list item is tapped', async () => {
    const drafts = [makeDraft({ id: 'draft-existing', status: 'draft_paused' })]
    getDraftsMock.mockResolvedValueOnce(drafts)

    const wrapper = mount(MonitorWeighbridgeView)
    await flushPromises()

    await wrapper.get('[data-testid="draft-item-draft-existing"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'weighbridge-form', params: { id: 'draft-existing' } })
    expect(resumeDraftMock).not.toHaveBeenCalled()
    expect(pauseDraftMock).not.toHaveBeenCalled()
    expect(deleteDraftMock).not.toHaveBeenCalled()
    expect(createDraftMock).not.toHaveBeenCalled()
  })

  // unit_test_case 7
  it('navigates to Data Preview Weighbridge in list mode with no id param when Load Data is pressed', async () => {
    getDraftsMock.mockResolvedValueOnce([])

    const wrapper = mount(MonitorWeighbridgeView)
    await flushPromises()

    await wrapper.get('[data-testid="load-data-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'data-preview-weighbridge' })
  })

  // unit_test_case 8
  describe('breadcrumb', () => {
    beforeEach(() => {
      getDraftsMock.mockResolvedValue([])
    })

    it("navigates to 'home' when the 'Home' breadcrumb segment is tapped", async () => {
      const wrapper = mount(MonitorWeighbridgeView)
      await flushPromises()

      await wrapper.get('[data-testid="breadcrumb-home"]').trigger('click')

      expect(pushMock).toHaveBeenCalledWith({ name: 'home' })
    })

    it("navigates to 'station-list' when the 'Production Process Activity' breadcrumb segment is tapped", async () => {
      const wrapper = mount(MonitorWeighbridgeView)
      await flushPromises()

      await wrapper.get('[data-testid="breadcrumb-production-process-activity"]').trigger('click')

      expect(pushMock).toHaveBeenCalledWith({ name: 'station-list' })
    })

    it("renders the 'Weighbridge' segment as non-interactive text with aria-current=\"page\", not a link/button", async () => {
      const wrapper = mount(MonitorWeighbridgeView)
      await flushPromises()

      const current = wrapper.get('.breadcrumb-current')
      expect(current.text()).toBe('Weighbridge')
      expect(current.attributes('aria-current')).toBe('page')
      expect(current.element.tagName).not.toBe('BUTTON')
      expect(current.element.tagName).not.toBe('A')
    })
  })

  // unit_test_case 9
  describe('menu navigasi (hamburger)', () => {
    beforeEach(() => {
      getDraftsMock.mockResolvedValue([])
    })

    it('opens the nav menu (Ganti Password, Logout) when the hamburger icon is tapped', async () => {
      const wrapper = mount(MonitorWeighbridgeView)
      await flushPromises()

      expect(wrapper.find('[data-testid="nav-menu"]').exists()).toBe(false)

      await wrapper.get('[data-testid="hamburger-button"]').trigger('click')

      const navMenu = wrapper.get('[data-testid="nav-menu"]')
      expect(navMenu.text()).toContain('Ganti Password')
      expect(navMenu.text()).toContain('Logout')
    })

    it("navigates to 'change-password' when the 'Ganti Password' nav menu item is tapped", async () => {
      const wrapper = mount(MonitorWeighbridgeView)
      await flushPromises()

      await wrapper.get('[data-testid="hamburger-button"]').trigger('click')
      await wrapper.get('[data-testid="nav-menu-change-password"]').trigger('click')

      expect(pushMock).toHaveBeenCalledWith({ name: 'change-password' })
    })

    it("logs out and navigates to 'login' when the 'Logout' nav menu item is tapped", async () => {
      const wrapper = mount(MonitorWeighbridgeView)
      await flushPromises()

      await wrapper.get('[data-testid="hamburger-button"]').trigger('click')
      await wrapper.get('[data-testid="nav-menu-logout"]').trigger('click')
      await flushPromises()

      expect(logoutMock).toHaveBeenCalledTimes(1)
      expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
    })
  })

  // unit_test_case 10
  it("navigates to Station List when Back is pressed", async () => {
    getDraftsMock.mockResolvedValueOnce([])

    const wrapper = mount(MonitorWeighbridgeView)
    await flushPromises()

    await wrapper.get('[data-testid="back-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'station-list' })
  })

  // extra edge-case coverage
  it('falls back to a placeholder label when a draft\'s wb_card_number is null or empty', async () => {
    const drafts = [
      makeDraft({ id: 'draft-null', wb_card_number: null }),
      makeDraft({ id: 'draft-empty', wb_card_number: '   ' }),
    ]
    getDraftsMock.mockResolvedValueOnce(drafts)

    const wrapper = mount(MonitorWeighbridgeView)
    await flushPromises()

    expect(wrapper.get('[data-testid="draft-item-draft-null"]').text()).toContain('WB Card belum diisi')
    expect(wrapper.get('[data-testid="draft-item-draft-empty"]').text()).toContain('WB Card belum diisi')
  })

  it('shows an error message when loading the draft list fails, instead of the list/empty-state', async () => {
    getDraftsMock.mockRejectedValueOnce(new Error('local db error'))

    const wrapper = mount(MonitorWeighbridgeView)
    await flushPromises()

    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="draft-list"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="draft-list-empty"]').exists()).toBe(false)
  })

  it('does not load drafts when the current user has no id', async () => {
    useAuthStoreMock.mockReturnValueOnce({
      currentUser: { id: undefined, username: 'op2', name: 'Op Dua', role: 'operator' },
      logout: logoutMock,
    })

    mount(MonitorWeighbridgeView)
    await flushPromises()

    expect(getDraftsMock).not.toHaveBeenCalled()
  })
  // "today's counter" addition — see MonitorWeighbridgeView.vue's
  // "Hari Ini" section / loadTodaySummary() header comment.
  describe('Hari Ini counter', () => {
    it('Counter Hari Ini Menampilkan Data — renders the 3 counter cards with getTodaySummary()\'s totals', async () => {
      getTodaySummaryMock.mockResolvedValueOnce({ countWb: 4, sumNetWeight: 32000, sumQuantity: 8 })

      const wrapper = mount(MonitorWeighbridgeView)
      await flushPromises()

      expect(getTodaySummaryMock).toHaveBeenCalledWith('user-1')
      expect(wrapper.get('[data-testid="counter-count-wb"]').text()).toBe('4')
      expect(wrapper.get('[data-testid="counter-net-weight"]').text()).toBe('32000')
      expect(wrapper.get('[data-testid="counter-quantity"]').text()).toBe('8')
    })

    it('Belum Ada Data Hari Ini — renders all 3 counter cards as 0 when getTodaySummary() resolves all-zero', async () => {
      getTodaySummaryMock.mockResolvedValueOnce({ countWb: 0, sumNetWeight: 0, sumQuantity: 0 })

      const wrapper = mount(MonitorWeighbridgeView)
      await flushPromises()

      expect(wrapper.get('[data-testid="counter-count-wb"]').text()).toBe('0')
      expect(wrapper.get('[data-testid="counter-net-weight"]').text()).toBe('0')
      expect(wrapper.get('[data-testid="counter-quantity"]').text()).toBe('0')
    })

    it('does not let a getTodaySummary() rejection surface as the shared draft-list error state (fails silently)', async () => {
      getTodaySummaryMock.mockRejectedValueOnce(new Error('local db error'))
      const drafts = [makeDraft({ id: 'draft-1' })]
      getDraftsMock.mockResolvedValueOnce(drafts)

      const wrapper = mount(MonitorWeighbridgeView)
      await flushPromises()

      // Counter cards fall back to the all-zero default.
      expect(wrapper.get('[data-testid="counter-count-wb"]').text()).toBe('0')
      expect(wrapper.get('[data-testid="counter-net-weight"]').text()).toBe('0')
      expect(wrapper.get('[data-testid="counter-quantity"]').text()).toBe('0')
      // The draft list itself still renders normally — no shared error
      // state was triggered by the counter's failure.
      expect(wrapper.find('[role="alert"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="draft-list"]').exists()).toBe(true)
    })
  })
})
