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

vi.mock('@/stores/floatingClock', () => ({
  useFloatingClockStore: () => ({ enabled: false, toggle: vi.fn() }),
}))

const { getActiveAndPlaceholderStationsMock, getActiveAndPlaceholderStationsForProductionLineMock } = vi.hoisted(() => ({
  getActiveAndPlaceholderStationsMock: vi.fn(),
  getActiveAndPlaceholderStationsForProductionLineMock: vi.fn(),
}))

vi.mock('@/services/stationRepo', () => ({
  stationRepo: {
    getActiveAndPlaceholderStations: getActiveAndPlaceholderStationsMock,
    getActiveAndPlaceholderStationsForProductionLine: getActiveAndPlaceholderStationsForProductionLineMock,
  },
  getActiveAndPlaceholderStations: getActiveAndPlaceholderStationsMock,
}))

// Production Line picker step (2026-08-20, entity-catalog v9). Defaults to
// "no production lines known" (an empty array) in every pre-existing test
// in this file below, so `loadProductionLinesAndStations()` falls straight
// through to the legacy business-unit-scoped `getActiveAndPlaceholderStations()`
// path — i.e. every test written before this feature continues to exercise
// EXACTLY the same code path/assertions as before, unchanged. The picker
// itself is covered by its own dedicated describe block further down,
// which overrides this mock's return value per test.
const {
  fetchCurrentProductionLinesMock,
  fetchAndCacheStationsForProductionLineMock,
} = vi.hoisted(() => ({
  fetchCurrentProductionLinesMock: vi.fn(),
  fetchAndCacheStationsForProductionLineMock: vi.fn(),
}))

vi.mock('@/services/productionLineRepo', () => ({
  productionLineRepo: {
    fetchCurrentProductionLines: fetchCurrentProductionLinesMock,
    fetchAndCacheStationsForProductionLine: fetchAndCacheStationsForProductionLineMock,
  },
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

// TEMPORARY (2026-08-20) — manual "Sinkronisasi" button (syncService.ts).
// Mocked at module level, same convention as the record-repo mocks above:
// this file asserts StationListView.vue's own button-click wiring
// (loading state, result message rendering), not syncService.ts's
// internals (covered by syncService.spec.ts).
const { syncAllRecordsMock } = vi.hoisted(() => ({
  syncAllRecordsMock: vi.fn(),
}))

vi.mock('@/services/syncService', () => ({
  syncAllRecords: syncAllRecordsMock,
}))

function makeStation(overrides: Partial<StationSlot> & { id: string }): StationSlot {
  return {
    businessUnitId: 'bu-1',
    name: `Stasiun ${overrides.id}`,
    type: 'other',
    isActive: false,
    icon: null,
    ...overrides,
  }
}

describe('StationListView — "Pilih Stasiun"', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchCurrentProductionLinesMock.mockResolvedValue([])
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
    fetchCurrentProductionLinesMock.mockResolvedValue([])
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
    fetchCurrentProductionLinesMock.mockResolvedValue([])
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
    fetchCurrentProductionLinesMock.mockResolvedValue([])
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

  describe('Sinkronisasi (temporary)', () => {
    it('shows a loading state while syncing, then the result summary in a popup on success', async () => {
      getActiveAndPlaceholderStationsMock.mockResolvedValue([])
      let resolveSync!: (value: {
        weighbridge: unknown[]
        grading: unknown[]
        cagesTrack: unknown[]
        syncedCount: number
        failedCount: number
      }) => void
      syncAllRecordsMock.mockReturnValue(
        new Promise((resolve) => {
          resolveSync = resolve
        }),
      )

      const wrapper = mount(StationListView)
      await flushPromises()

      const button = wrapper.get('[data-testid="sync-button"]')
      await button.trigger('click')
      await flushPromises()

      expect(button.attributes('disabled')).toBeDefined()
      expect(button.text()).toContain('Menyinkronkan')
      expect(wrapper.find('[data-testid="sync-dialog-message"]').exists()).toBe(false)

      resolveSync({ weighbridge: [], grading: [], cagesTrack: [], syncedCount: 3, failedCount: 0 })
      await flushPromises()

      expect(button.attributes('disabled')).toBeUndefined()
      expect(wrapper.get('[data-testid="sync-dialog-message"]').text()).toBe('3 data berhasil disinkronkan.')

      await wrapper.get('[data-testid="sync-dialog-close"]').trigger('click')
      await flushPromises()
      expect(wrapper.find('[data-testid="sync-dialog-message"]').exists()).toBe(false)
    })

    it('shows a combined success/failure summary with per-item reasons in the popup', async () => {
      getActiveAndPlaceholderStationsMock.mockResolvedValue([])
      syncAllRecordsMock.mockResolvedValue({
        weighbridge: [{ id: 'wb-1', label: 'WB-001', ok: false, reason: 'Gagal terhubung ke server.' }],
        grading: [],
        cagesTrack: [],
        syncedCount: 2,
        failedCount: 1,
      })

      const wrapper = mount(StationListView)
      await flushPromises()

      await wrapper.get('[data-testid="sync-button"]').trigger('click')
      await flushPromises()

      expect(wrapper.get('[data-testid="sync-dialog-message"]').text()).toBe('2 data berhasil disinkronkan, 1 gagal.')
      expect(wrapper.get('[data-testid="sync-dialog-failed-list"]').text()).toContain('WB-001')
      expect(wrapper.get('[data-testid="sync-dialog-failed-list"]').text()).toContain('Gagal terhubung ke server.')
    })

    it('shows an error popup when syncAllRecords itself rejects', async () => {
      getActiveAndPlaceholderStationsMock.mockResolvedValue([])
      syncAllRecordsMock.mockRejectedValue(new Error('Tidak dapat sinkronisasi: business unit atau user tidak diketahui.'))

      const wrapper = mount(StationListView)
      await flushPromises()

      await wrapper.get('[data-testid="sync-button"]').trigger('click')
      await flushPromises()

      expect(wrapper.get('[data-testid="sync-dialog-message"]').text()).toBe(
        'Tidak dapat sinkronisasi: business unit atau user tidak diketahui.',
      )
    })
  })
})

// 2026-08-20 — Production Line picker step (entity-catalog v9: Business
// Unit → Production Line → Station). Every test above defaults
// fetchCurrentProductionLinesMock to resolve [] (see the mock declaration
// near the top of this file), which exercises the "0 Production Lines
// known" legacy-fallback branch — the SAME branch/assertions those tests
// already covered before this feature existed. These tests instead
// override that return value per case to cover the other two branches:
// exactly 1 (auto-select, no picker shown) and >1 (picker shown, grid
// hidden until a tap).
describe('StationListView — Production Line picker step', () => {
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
    fetchAndCacheStationsForProductionLineMock.mockResolvedValue(undefined)
  })

  it('auto-selects the single Production Line (no picker shown) and loads its stations', async () => {
    fetchCurrentProductionLinesMock.mockResolvedValue([{ id: 'pl-1', name: 'Line 01', code: null }])
    const stations = [makeStation({ id: 'station-1', name: 'Timbangan', type: 'weighbridge', isActive: true })]
    getActiveAndPlaceholderStationsForProductionLineMock.mockResolvedValue(stations)

    const wrapper = mount(StationListView)
    await flushPromises()

    expect(wrapper.find('[data-testid="production-line-picker"]').exists()).toBe(false)
    expect(fetchAndCacheStationsForProductionLineMock).toHaveBeenCalledWith('pl-1', 'bu-1')
    expect(getActiveAndPlaceholderStationsForProductionLineMock).toHaveBeenCalledWith('pl-1')
    expect(getActiveAndPlaceholderStationsMock).not.toHaveBeenCalled()
    expect(wrapper.findComponent(StationGrid).props('stations')).toEqual(stations)
  })

  it('shows a picker with one option per Production Line and hides the grid until one is tapped', async () => {
    fetchCurrentProductionLinesMock.mockResolvedValue([
      { id: 'pl-1', name: 'Line 01', code: null },
      { id: 'pl-2', name: 'Line 02', code: null },
    ])

    const wrapper = mount(StationListView)
    await flushPromises()

    const picker = wrapper.get('[data-testid="production-line-picker"]')
    expect(picker.text()).toContain('Line 01')
    expect(picker.text()).toContain('Line 02')
    expect(wrapper.findComponent(StationGrid).exists()).toBe(false)
    expect(getActiveAndPlaceholderStationsForProductionLineMock).not.toHaveBeenCalled()
    expect(getActiveAndPlaceholderStationsMock).not.toHaveBeenCalled()
  })

  it('loads that Production Line\'s stations and hides the picker once an option is tapped', async () => {
    fetchCurrentProductionLinesMock.mockResolvedValue([
      { id: 'pl-1', name: 'Line 01', code: null },
      { id: 'pl-2', name: 'Line 02', code: null },
    ])
    const stations = [makeStation({ id: 'station-2', name: 'Grading', type: 'grading', isActive: true })]
    getActiveAndPlaceholderStationsForProductionLineMock.mockResolvedValue(stations)

    const wrapper = mount(StationListView)
    await flushPromises()

    await wrapper.get('[data-testid="production-line-option-pl-2"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="production-line-picker"]').exists()).toBe(false)
    expect(fetchAndCacheStationsForProductionLineMock).toHaveBeenCalledWith('pl-2', 'bu-1')
    expect(getActiveAndPlaceholderStationsForProductionLineMock).toHaveBeenCalledWith('pl-2')
    expect(wrapper.findComponent(StationGrid).props('stations')).toEqual(stations)
  })

  it('falls back to the legacy business-unit-scoped grid when no Production Lines are known (offline/none yet)', async () => {
    fetchCurrentProductionLinesMock.mockResolvedValue([])
    const stations = [makeStation({ id: 'station-1', name: 'Timbangan', type: 'weighbridge', isActive: true })]
    getActiveAndPlaceholderStationsMock.mockResolvedValue(stations)

    const wrapper = mount(StationListView)
    await flushPromises()

    expect(wrapper.find('[data-testid="production-line-picker"]').exists()).toBe(false)
    expect(getActiveAndPlaceholderStationsMock).toHaveBeenCalledWith('bu-1')
    expect(fetchAndCacheStationsForProductionLineMock).not.toHaveBeenCalled()
    expect(wrapper.findComponent(StationGrid).props('stations')).toEqual(stations)
  })

  it('still loads the legacy grid when fetchCurrentProductionLines itself rejects (offline)', async () => {
    fetchCurrentProductionLinesMock.mockRejectedValue(new Error('network error'))
    getActiveAndPlaceholderStationsMock.mockResolvedValue([])

    const wrapper = mount(StationListView)
    await flushPromises()

    expect(wrapper.find('[data-testid="production-line-picker"]').exists()).toBe(false)
    expect(getActiveAndPlaceholderStationsMock).toHaveBeenCalledWith('bu-1')
  })

  it('renders the grid even when the real-station sync (fetchAndCacheStationsForProductionLine) fails, from whatever is already cached locally', async () => {
    fetchCurrentProductionLinesMock.mockResolvedValue([{ id: 'pl-1', name: 'Line 01', code: null }])
    fetchAndCacheStationsForProductionLineMock.mockRejectedValue(new Error('offline'))
    const stations = [makeStation({ id: 'station-1', name: 'Timbangan', type: 'weighbridge', isActive: true })]
    getActiveAndPlaceholderStationsForProductionLineMock.mockResolvedValue(stations)

    const wrapper = mount(StationListView)
    await flushPromises()

    expect(wrapper.findComponent(StationGrid).props('stations')).toEqual(stations)
  })

  it('passes the selected Production Line id through to syncAllRecords when Sinkronisasi is tapped', async () => {
    fetchCurrentProductionLinesMock.mockResolvedValue([{ id: 'pl-1', name: 'Line 01', code: null }])
    getActiveAndPlaceholderStationsForProductionLineMock.mockResolvedValue([])
    syncAllRecordsMock.mockResolvedValue({ weighbridge: [], grading: [], cagesTrack: [], syncedCount: 0, failedCount: 0 })

    const wrapper = mount(StationListView)
    await flushPromises()

    await wrapper.get('[data-testid="sync-button"]').trigger('click')
    await flushPromises()

    expect(syncAllRecordsMock).toHaveBeenCalledWith('pl-1')
  })
})
