/**
 * StationListView.spec.ts — screen-006--station-list /
 * usecase-006--station-list "Pilih Stasiun" business_logic steps 1 and 3.
 *
 * Covers unit_test_case 3 ("navigates to Monitor screen when an active
 * station is tapped") — tested at this level since StationGrid.vue itself
 * only emits `navigate` (its own emit behavior is already covered by
 * StationGrid.spec.ts); StationListView.vue owns the actual
 * `router.push`, which is what this file asserts.
 *
 * Mocking strategy (mirrors HomeView.spec.ts):
 *   - 'vue-router' is mocked at module level so `router.push` can be
 *     asserted via a hoisted `pushMock`, without a real router instance.
 *     The target route names ('monitor-weighbridge' / 'monitor-grading' /
 *     'monitor-cages-track') are not yet registered in router/index.ts
 *     (screens 007-009 not implemented yet) — irrelevant here since the
 *     router itself is mocked, not mounted. This test asserts the
 *     *intended* router.push call, not an actual successful navigation.
 *   - '@/stores/auth' is mocked at module level via a hoisted
 *     `useAuthStoreMock` (a real `vi.fn()`, not a plain arrow function),
 *     so this test never pulls in the real Pinia auth store / apiClient /
 *     tokenStorage chain, and individual tests can still override the
 *     returned `currentUser` with `mockReturnValueOnce`.
 *   - '@/services/stationRepo' is mocked at module level so this file
 *     never touches localDb.ts / a real SQLite connection (already
 *     covered by stationRepo.spec.ts).
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import StationListView from '@/views/StationListView.vue'
import StationGrid from '@/components/StationGrid.vue'
import type { StationSlot } from '@/services/stationRepo'

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

const { getActiveAndPlaceholderStationsMock } = vi.hoisted(() => ({
  getActiveAndPlaceholderStationsMock: vi.fn(),
}))

vi.mock('@/services/stationRepo', () => ({
  stationRepo: { getActiveAndPlaceholderStations: getActiveAndPlaceholderStationsMock },
  getActiveAndPlaceholderStations: getActiveAndPlaceholderStationsMock,
}))

// 2026-08-18 update — draft-status-by-type detection (business_logic
// step 2) calls these three record repos' summary functions in parallel
// on mount. Mocked at module level, same pattern as stationRepo above,
// so StationListView.vue's real `import { weighbridgeRecordRepo } from
// '@/services/weighbridgeRecordRepo'` (etc.) resolves to these mocks
// instead of touching localDb.ts / a real SQLite connection.
const { getSummaryMock, getGradingProgressSummaryMock, getCagesTrackProgressSummaryMock } = vi.hoisted(() => ({
  getSummaryMock: vi.fn(),
  getGradingProgressSummaryMock: vi.fn(),
  getCagesTrackProgressSummaryMock: vi.fn(),
}))

vi.mock('@/services/weighbridgeRecordRepo', () => ({
  weighbridgeRecordRepo: { getSummary: getSummaryMock },
}))

vi.mock('@/services/gradingRecordRepo', () => ({
  gradingRecordRepo: { getProgressSummary: getGradingProgressSummaryMock },
}))

vi.mock('@/services/cagesTrackRecordRepo', () => ({
  cagesTrackRecordRepo: { getProgressSummary: getCagesTrackProgressSummaryMock },
}))

function makeStation(overrides: Partial<StationSlot> & { id: string }): StationSlot {
  return {
    businessUnitId: 'bu-1',
    name: `Stasiun ${overrides.id}`,
    type: 'other',
    isActive: false,
    ...overrides,
  }
}

describe('StationListView — "Pilih Stasiun"', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    logoutMock.mockResolvedValue(undefined)
    useAuthStoreMock.mockReturnValue({
      currentUser: {
        id: 'user-1',
        username: 'operator01',
        name: 'Operator Satu',
        role: 'operator',
        business_unit_id: 'bu-1',
      },
      logout: logoutMock,
    })
    getSummaryMock.mockResolvedValue({ sumWbCard: 0, sumNetWeight: 0, sumQuantity: 0, currentDraft: null })
    getGradingProgressSummaryMock.mockResolvedValue({ currentDraft: null })
    getCagesTrackProgressSummaryMock.mockResolvedValue({ currentDraft: null })
  })

  it('loads stations for the current user\'s business unit on mount and renders StationGrid with them', async () => {
    const stations = [makeStation({ id: 'station-1', name: 'Timbangan', type: 'weighbridge', isActive: true })]
    getActiveAndPlaceholderStationsMock.mockResolvedValue(stations)

    const wrapper = mount(StationListView)
    await flushPromises()

    expect(getActiveAndPlaceholderStationsMock).toHaveBeenCalledWith('bu-1')
    expect(wrapper.findComponent(StationGrid).props('stations')).toEqual(stations)
  })

  // unit_test_case 3
  it.each([
    ['weighbridge', 'monitor-weighbridge'],
    ['grading', 'monitor-grading'],
    ['cages-track', 'monitor-cages-track'],
  ] as const)(
    'navigates to the Monitor screen for %s when the grid emits navigate',
    async (stationType, expectedRouteName) => {
      getActiveAndPlaceholderStationsMock.mockResolvedValue([])

      const wrapper = mount(StationListView)
      await flushPromises()

      await wrapper.findComponent(StationGrid).vm.$emit('navigate', stationType)

      expect(pushMock).toHaveBeenCalledTimes(1)
      expect(pushMock).toHaveBeenCalledWith({ name: expectedRouteName })
    },
  )

  it('does not navigate when the grid emits navigate for a type with no mapped Monitor route', async () => {
    getActiveAndPlaceholderStationsMock.mockResolvedValue([])

    const wrapper = mount(StationListView)
    await flushPromises()

    await wrapper.findComponent(StationGrid).vm.$emit('navigate', 'other')

    expect(pushMock).not.toHaveBeenCalled()
  })

  it('does not load stations when the current user has no business_unit_id', async () => {
    getActiveAndPlaceholderStationsMock.mockResolvedValue([])
    useAuthStoreMock.mockReturnValueOnce({
      currentUser: { id: 'user-2', username: 'op2', name: 'Op Dua', role: 'operator', business_unit_id: null },
    })

    mount(StationListView)
    await flushPromises()

    expect(getActiveAndPlaceholderStationsMock).not.toHaveBeenCalled()
  })
})

// 2026-08-18 update — tappable breadcrumb (business_logic steps 4/8).
// "Home" is an interactive <button> that navigates via router.push; the
// current-page segment ("Production Process Activity") is plain,
// non-interactive text with aria-current="page".
describe('StationListView — breadcrumb', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    logoutMock.mockResolvedValue(undefined)
    useAuthStoreMock.mockReturnValue({
      currentUser: {
        id: 'user-1',
        username: 'operator01',
        name: 'Operator Satu',
        role: 'operator',
        business_unit_id: 'bu-1',
      },
      logout: logoutMock,
    })
    getActiveAndPlaceholderStationsMock.mockResolvedValue([])
    getSummaryMock.mockResolvedValue({ sumWbCard: 0, sumNetWeight: 0, sumQuantity: 0, currentDraft: null })
    getGradingProgressSummaryMock.mockResolvedValue({ currentDraft: null })
    getCagesTrackProgressSummaryMock.mockResolvedValue({ currentDraft: null })
  })

  it("navigates to 'home' when the 'Home' breadcrumb segment is tapped", async () => {
    const wrapper = mount(StationListView)
    await flushPromises()

    await wrapper.get('[data-testid="breadcrumb-home"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'home' })
  })

  it("renders the 'Production Process Activity' segment as non-interactive text with aria-current=\"page\", not a link/button", async () => {
    const wrapper = mount(StationListView)
    await flushPromises()

    const current = wrapper.get('.breadcrumb-current')
    expect(current.text()).toBe('Production Process Activity')
    expect(current.attributes('aria-current')).toBe('page')
    expect(current.element.tagName).not.toBe('BUTTON')
    expect(current.element.tagName).not.toBe('A')
  })
})

// 2026-08-18 update — header hamburger nav menu, copied verbatim from
// HomeView.vue's pattern (mirrors HomeView.spec.ts's "menu navigasi
// (hamburger)" describe block below).
describe('StationListView — menu navigasi (hamburger)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    logoutMock.mockResolvedValue(undefined)
    useAuthStoreMock.mockReturnValue({
      currentUser: {
        id: 'user-1',
        username: 'operator01',
        name: 'Operator Satu',
        role: 'operator',
        business_unit_id: 'bu-1',
      },
      logout: logoutMock,
    })
    getActiveAndPlaceholderStationsMock.mockResolvedValue([])
    getSummaryMock.mockResolvedValue({ sumWbCard: 0, sumNetWeight: 0, sumQuantity: 0, currentDraft: null })
    getGradingProgressSummaryMock.mockResolvedValue({ currentDraft: null })
    getCagesTrackProgressSummaryMock.mockResolvedValue({ currentDraft: null })
  })

  it('opens the nav menu (Ganti Password, Logout) when the hamburger icon is tapped', async () => {
    const wrapper = mount(StationListView)
    await flushPromises()

    expect(wrapper.find('[data-testid="nav-menu"]').exists()).toBe(false)

    await wrapper.get('[data-testid="hamburger-button"]').trigger('click')

    const navMenu = wrapper.get('[data-testid="nav-menu"]')
    expect(navMenu.text()).toContain('Ganti Password')
    expect(navMenu.text()).toContain('Logout')
  })

  it("navigates to 'change-password' when the 'Ganti Password' nav menu item is tapped", async () => {
    const wrapper = mount(StationListView)
    await flushPromises()

    await wrapper.get('[data-testid="hamburger-button"]').trigger('click')
    await wrapper.get('[data-testid="nav-menu-change-password"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'change-password' })
  })

  it("logs out and navigates to 'login' when the 'Logout' nav menu item is tapped", async () => {
    const wrapper = mount(StationListView)
    await flushPromises()

    await wrapper.get('[data-testid="hamburger-button"]').trigger('click')
    await wrapper.get('[data-testid="nav-menu-logout"]').trigger('click')
    await flushPromises()

    expect(logoutMock).toHaveBeenCalledTimes(1)
    expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
  })
})

// 2026-08-18 update — business_logic step 2: on mount, per-active-
// station-type draft detection via the three record repos' summary
// functions (weighbridgeRecordRepo.getSummary /
// gradingRecordRepo.getProgressSummary /
// cagesTrackRecordRepo.getProgressSummary), each called with the current
// user's id, feeding StationGrid's `draft-status-by-type` prop.
describe('StationListView — draft-status-by-type detection', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    logoutMock.mockResolvedValue(undefined)
    useAuthStoreMock.mockReturnValue({
      currentUser: {
        id: 'user-1',
        username: 'operator01',
        name: 'Operator Satu',
        role: 'operator',
        business_unit_id: 'bu-1',
      },
      logout: logoutMock,
    })
    getActiveAndPlaceholderStationsMock.mockResolvedValue([])
  })

  it("calls the three record repos' summary functions with the current user's id on mount", async () => {
    getSummaryMock.mockResolvedValue({ sumWbCard: 0, sumNetWeight: 0, sumQuantity: 0, currentDraft: null })
    getGradingProgressSummaryMock.mockResolvedValue({ currentDraft: null })
    getCagesTrackProgressSummaryMock.mockResolvedValue({ currentDraft: null })

    mount(StationListView)
    await flushPromises()

    expect(getSummaryMock).toHaveBeenCalledWith('user-1')
    expect(getGradingProgressSummaryMock).toHaveBeenCalledWith('user-1')
    expect(getCagesTrackProgressSummaryMock).toHaveBeenCalledWith('user-1')
  })

  it('passes the resulting draftStatusByType (hasDraft per type) to StationGrid as draft-status-by-type', async () => {
    getSummaryMock.mockResolvedValue({
      sumWbCard: 1,
      sumNetWeight: 100,
      sumQuantity: 1,
      currentDraft: { id: 'wb-1', status: 'draft_ongoing' },
    })
    getGradingProgressSummaryMock.mockResolvedValue({ currentDraft: null })
    getCagesTrackProgressSummaryMock.mockResolvedValue({ currentDraft: { id: 'ct-1', status: 'draft_paused' } })

    const wrapper = mount(StationListView)
    await flushPromises()

    expect(wrapper.findComponent(StationGrid).props('draftStatusByType')).toEqual({
      weighbridge: true,
      grading: false,
      'cages-track': true,
    })
  })

  it('treats a failing repo call as no-draft for that type (best-effort, does not blank out the others)', async () => {
    getSummaryMock.mockRejectedValue(new Error('local db error'))
    getGradingProgressSummaryMock.mockResolvedValue({ currentDraft: { id: 'gr-1', status: 'draft_ongoing' } })
    getCagesTrackProgressSummaryMock.mockResolvedValue({ currentDraft: null })

    const wrapper = mount(StationListView)
    await flushPromises()

    expect(wrapper.findComponent(StationGrid).props('draftStatusByType')).toEqual({
      weighbridge: false,
      grading: true,
      'cages-track': false,
    })
  })

  it('does not call the record repos when the current user has no id', async () => {
    useAuthStoreMock.mockReturnValueOnce({
      currentUser: { id: undefined, username: 'op2', name: 'Op Dua', role: 'operator', business_unit_id: 'bu-1' },
      logout: logoutMock,
    })

    mount(StationListView)
    await flushPromises()

    expect(getSummaryMock).not.toHaveBeenCalled()
    expect(getGradingProgressSummaryMock).not.toHaveBeenCalled()
    expect(getCagesTrackProgressSummaryMock).not.toHaveBeenCalled()
  })
})
