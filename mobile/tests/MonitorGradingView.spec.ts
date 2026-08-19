/**
 * MonitorGradingView.spec.ts — screen-008--monitor-grading /
 * usecase-008--monitor-grading.
 *
 * Rewritten (2026-08-18, entity-catalog v2) to match MonitorGradingView.vue's
 * list-view rewrite — the screen no longer shows a single-draft summary +
 * Mulai Input Baru/Lanjutkan/Pause/Clear surface (that whole surface, and
 * this file's previous test cases for it, are gone; mirrors
 * MonitorWeighbridgeView.spec.ts's (screen-007) own equivalent rewrite). It
 * now renders a full scrollable list of the current user's ongoing/paused
 * drafts (gradingRecordRepo.getDrafts()), every row labeled uniformly
 * "Pause" regardless of underlying status, a "Hari Ini" 3-card counter row
 * (gradingRecordRepo.getTodaySummary()), plus 'New Data' / 'Load Data' /
 * 'Back' actions and the same header/breadcrumb/hamburger nav-menu pattern
 * as MonitorWeighbridgeView.vue / StationListView.vue / HomeView.vue.
 *
 * Component tests covering unit_test_cases 1-12 per the tech spec (+ light
 * extra edge-case coverage):
 *   1. loads screen data successfully with a populated list
 *   2. empty-state list when no draft/pause records exist, New Data still
 *      enabled
 *   3. uniform 'Pause' label, ordered by updated_at DESC, regardless of
 *      underlying status (draft_ongoing vs draft_paused)
 *   4. 'New Data' creates a new draft and navigates to Form Grading
 *   5. tapping a list item navigates with the EXISTING draft id and does
 *      NOT call any status-update/repo-write function
 *   6. 'Load Data' navigates to Data Preview Grading (list mode, no id)
 *   7. breadcrumb segment taps navigate (Home, Production Process Activity)
 *   8. hamburger opens nav-menu with Ganti Password / Logout
 *   9. 'Back' navigates to Station List
 *   10-12. "Hari Ini" counter — computes count/sum netto/sum quantity from
 *      today-dated records regardless of status, and renders zero when
 *      none match (repo-level date exclusion is covered by
 *      gradingRecordRepo.spec.ts; here we verify the view renders whatever
 *      getTodaySummary() resolves)
 *   + Grading Number fallback label when grading_number is null/empty
 *   + error state when getDrafts() rejects
 *   + does not load drafts when there is no current user id
 *
 * Mocking strategy (mirrors MonitorWeighbridgeView.spec.ts exactly):
 *   - 'vue-router' is mocked at module level so `router.push` can be
 *     asserted via a hoisted `pushMock`, without a real router instance.
 *   - '@/stores/auth' is mocked at module level via a hoisted
 *     `useAuthStoreMock` (a real `vi.fn()`), so this file never pulls in
 *     the real Pinia auth store / apiClient / tokenStorage chain, and
 *     individual tests can override the returned `currentUser`.
 *   - '@/services/gradingRecordRepo' is mocked at module level (its own
 *     behavior is already covered by gradingRecordRepo.spec.ts) so this
 *     file never touches localDb.ts / a real SQLite connection — the view
 *     is tested against the repo's public interface only. `resumeDraft` /
 *     `pauseDraft` / `deleteDraft` are mocked too (even though the current
 *     view no longer imports them) purely so test case 5 can assert they
 *     are never called without throwing on an unmocked import.
 *   - StatusBadge.vue is NOT mocked — it is rendered for real so test case
 *     3 can assert the actual status/label it renders per row.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import MonitorGradingView from '@/views/MonitorGradingView.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import type { GradingDraftListItem } from '@/services/gradingRecordRepo'

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
  // "today's counter" addition — see MonitorGradingView.vue's
  // loadTodaySummary()/loadAll() header comment.
  getTodaySummaryMock: vi.fn(),
}))

vi.mock('@/services/gradingRecordRepo', () => ({
  gradingRecordRepo: {
    getDrafts: getDraftsMock,
    createDraft: createDraftMock,
    resumeDraft: resumeDraftMock,
    pauseDraft: pauseDraftMock,
    deleteDraft: deleteDraftMock,
    getTodaySummary: getTodaySummaryMock,
  },
}))

function makeDraft(overrides: Partial<GradingDraftListItem> & { id: string }): GradingDraftListItem {
  return {
    status: 'draft_ongoing',
    grading_number: 'GR-001',
    updated_at: '2026-08-18T08:00:00.000Z',
    ...overrides,
  }
}

describe('MonitorGradingView', () => {
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
    getTodaySummaryMock.mockResolvedValue({ countGrading: 0, sumNetto: 0, sumQuantity: 0 })
  })

  // unit_test_case 1
  it('loads screen data successfully with a populated list when local records exist', async () => {
    const drafts = [
      makeDraft({ id: 'draft-1', grading_number: 'GR-100', updated_at: '2026-08-18T09:00:00.000Z' }),
      makeDraft({ id: 'draft-2', grading_number: 'GR-200', updated_at: '2026-08-18T08:00:00.000Z' }),
    ]
    getDraftsMock.mockResolvedValueOnce(drafts)

    const wrapper = mount(MonitorGradingView)
    await flushPromises()

    expect(getDraftsMock).toHaveBeenCalledWith('user-1')
    expect(wrapper.find('[data-testid="draft-list"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="draft-item-draft-1"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="draft-item-draft-2"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="draft-item-draft-1"]').text()).toContain('GR-100')
    expect(wrapper.find('[data-testid="draft-item-draft-2"]').text()).toContain('GR-200')
  })

  // unit_test_case 2
  it('returns an empty-state list when no draft/pause records exist for the current user, with New Data still enabled', async () => {
    getDraftsMock.mockResolvedValueOnce([])

    const wrapper = mount(MonitorGradingView)
    await flushPromises()

    expect(wrapper.find('[data-testid="draft-list-empty"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Belum ada draft grading tersimpan.')
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

    const wrapper = mount(MonitorGradingView)
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
  it('creates a new draft record and navigates to Form Grading when New Data is pressed', async () => {
    getDraftsMock.mockResolvedValueOnce([])
    createDraftMock.mockResolvedValueOnce('new-draft-id')

    const wrapper = mount(MonitorGradingView)
    await flushPromises()

    await wrapper.get('[data-testid="new-data-button"]').trigger('click')
    await flushPromises()

    expect(createDraftMock).toHaveBeenCalledWith('user-1')
    expect(pushMock).toHaveBeenCalledWith({ name: 'grading-form', params: { id: 'new-draft-id' } })
  })

  // unit_test_case 5
  it('navigates to Form Grading with the selected draft id and does not change its status when a list item is tapped', async () => {
    const drafts = [makeDraft({ id: 'draft-existing', status: 'draft_paused' })]
    getDraftsMock.mockResolvedValueOnce(drafts)

    const wrapper = mount(MonitorGradingView)
    await flushPromises()

    await wrapper.get('[data-testid="draft-item-draft-existing"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'grading-form', params: { id: 'draft-existing' } })
    expect(resumeDraftMock).not.toHaveBeenCalled()
    expect(pauseDraftMock).not.toHaveBeenCalled()
    expect(deleteDraftMock).not.toHaveBeenCalled()
    expect(createDraftMock).not.toHaveBeenCalled()
  })

  // unit_test_case 6
  it('navigates to Data Preview Grading in list mode with no id param when Load Data is pressed', async () => {
    getDraftsMock.mockResolvedValueOnce([])

    const wrapper = mount(MonitorGradingView)
    await flushPromises()

    await wrapper.get('[data-testid="load-data-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'data-preview-grading' })
  })

  // unit_test_case 7
  describe('breadcrumb', () => {
    beforeEach(() => {
      getDraftsMock.mockResolvedValue([])
    })

    it("navigates to 'home' when the 'Home' breadcrumb segment is tapped", async () => {
      const wrapper = mount(MonitorGradingView)
      await flushPromises()

      await wrapper.get('[data-testid="breadcrumb-home"]').trigger('click')

      expect(pushMock).toHaveBeenCalledWith({ name: 'home' })
    })

    it("navigates to 'station-list' when the 'Production Process Activity' breadcrumb segment is tapped", async () => {
      const wrapper = mount(MonitorGradingView)
      await flushPromises()

      await wrapper.get('[data-testid="breadcrumb-production-process-activity"]').trigger('click')

      expect(pushMock).toHaveBeenCalledWith({ name: 'station-list' })
    })

    it("renders the 'Grading' segment as non-interactive text with aria-current=\"page\", not a link/button", async () => {
      const wrapper = mount(MonitorGradingView)
      await flushPromises()

      const current = wrapper.get('.breadcrumb-current')
      expect(current.text()).toBe('Grading')
      expect(current.attributes('aria-current')).toBe('page')
      expect(current.element.tagName).not.toBe('BUTTON')
      expect(current.element.tagName).not.toBe('A')
    })
  })

  // unit_test_case 8
  describe('menu navigasi (hamburger)', () => {
    beforeEach(() => {
      getDraftsMock.mockResolvedValue([])
    })

    it('opens the nav menu (Ganti Password, Logout) when the hamburger icon is tapped', async () => {
      const wrapper = mount(MonitorGradingView)
      await flushPromises()

      expect(wrapper.find('[data-testid="nav-menu"]').exists()).toBe(false)

      await wrapper.get('[data-testid="hamburger-button"]').trigger('click')

      const navMenu = wrapper.get('[data-testid="nav-menu"]')
      expect(navMenu.text()).toContain('Ganti Password')
      expect(navMenu.text()).toContain('Logout')
    })

    it("navigates to 'change-password' when the 'Ganti Password' nav menu item is tapped", async () => {
      const wrapper = mount(MonitorGradingView)
      await flushPromises()

      await wrapper.get('[data-testid="hamburger-button"]').trigger('click')
      await wrapper.get('[data-testid="nav-menu-change-password"]').trigger('click')

      expect(pushMock).toHaveBeenCalledWith({ name: 'change-password' })
    })

    it("logs out and navigates to 'login' when the 'Logout' nav menu item is tapped", async () => {
      const wrapper = mount(MonitorGradingView)
      await flushPromises()

      await wrapper.get('[data-testid="hamburger-button"]').trigger('click')
      await wrapper.get('[data-testid="nav-menu-logout"]').trigger('click')
      await flushPromises()

      expect(logoutMock).toHaveBeenCalledTimes(1)
      expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
    })
  })

  // unit_test_case 9
  it('navigates to Station List when Back is pressed', async () => {
    getDraftsMock.mockResolvedValueOnce([])

    const wrapper = mount(MonitorGradingView)
    await flushPromises()

    await wrapper.get('[data-testid="back-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'station-list' })
  })

  // extra edge-case coverage
  it('falls back to a placeholder label when a draft\'s grading_number is null or empty', async () => {
    const drafts = [
      makeDraft({ id: 'draft-null', grading_number: null }),
      makeDraft({ id: 'draft-empty', grading_number: '   ' }),
    ]
    getDraftsMock.mockResolvedValueOnce(drafts)

    const wrapper = mount(MonitorGradingView)
    await flushPromises()

    expect(wrapper.get('[data-testid="draft-item-draft-null"]').text()).toContain('Grading Number belum diisi')
    expect(wrapper.get('[data-testid="draft-item-draft-empty"]').text()).toContain('Grading Number belum diisi')
  })

  it('shows an error message when loading the draft list fails, instead of the list/empty-state', async () => {
    getDraftsMock.mockRejectedValueOnce(new Error('local db error'))

    const wrapper = mount(MonitorGradingView)
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

    mount(MonitorGradingView)
    await flushPromises()

    expect(getDraftsMock).not.toHaveBeenCalled()
  })

  // unit_test_cases 10-12 — "today's counter" addition, see
  // MonitorGradingView.vue's "Hari Ini" section / loadTodaySummary()
  // header comment.
  describe('Hari Ini counter', () => {
    it('Counter Hari Ini Menampilkan Data — renders the 3 counter cards with getTodaySummary()\'s totals', async () => {
      getTodaySummaryMock.mockResolvedValueOnce({ countGrading: 4, sumNetto: 32000, sumQuantity: 8 })

      const wrapper = mount(MonitorGradingView)
      await flushPromises()

      expect(getTodaySummaryMock).toHaveBeenCalledWith('user-1')
      expect(wrapper.get('[data-testid="counter-count-grading"]').text()).toBe('4')
      expect(wrapper.get('[data-testid="counter-netto"]').text()).toBe('32000')
      expect(wrapper.get('[data-testid="counter-quantity"]').text()).toBe('8')
    })

    it('Belum Ada Data Hari Ini — renders all 3 counter cards as 0 when getTodaySummary() resolves all-zero', async () => {
      getTodaySummaryMock.mockResolvedValueOnce({ countGrading: 0, sumNetto: 0, sumQuantity: 0 })

      const wrapper = mount(MonitorGradingView)
      await flushPromises()

      expect(wrapper.get('[data-testid="counter-count-grading"]').text()).toBe('0')
      expect(wrapper.get('[data-testid="counter-netto"]').text()).toBe('0')
      expect(wrapper.get('[data-testid="counter-quantity"]').text()).toBe('0')
    })

    it('does not let a getTodaySummary() rejection surface as the shared draft-list error state (fails silently)', async () => {
      getTodaySummaryMock.mockRejectedValueOnce(new Error('local db error'))
      const drafts = [makeDraft({ id: 'draft-1' })]
      getDraftsMock.mockResolvedValueOnce(drafts)

      const wrapper = mount(MonitorGradingView)
      await flushPromises()

      // Counter cards fall back to the all-zero default.
      expect(wrapper.get('[data-testid="counter-count-grading"]').text()).toBe('0')
      expect(wrapper.get('[data-testid="counter-netto"]').text()).toBe('0')
      expect(wrapper.get('[data-testid="counter-quantity"]').text()).toBe('0')
      // The draft list itself still renders normally — no shared error
      // state was triggered by the counter's failure.
      expect(wrapper.find('[role="alert"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="draft-list"]').exists()).toBe(true)
    })
  })
})
