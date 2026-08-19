/**
 * HomeView.spec.ts — screen-005--home / usecase-005--home "Navigasi Home".
 *
 * REWRITTEN (2026-08-18): screen-005's purpose changed completely — it is
 * no longer a per-station draft-status dashboard (old scenario "Lihat
 * Status Draft & Navigasi"), it is now a pure welcome + navigation
 * launcher (header + hero image + personal greeting + 3 menu cards, no
 * API/SQLite call of any kind — api_contracts[].endpoints = [] for this
 * screen). All previous draft-status / empty-state / pagination test
 * cases (which exercised '@/composables/useHomeSummary' and
 * PausedDraftsList) have been dropped — that composable and component are
 * no longer wired into HomeView.vue per the new spec (out of scope for
 * this rewrite; see useHomeSummary.spec.ts, which still separately covers
 * that composable's own logic on its own terms).
 *
 * Covers all 4 test_scenarios' component_test entries for
 * usecase-005--home:
 *   1. "Navigasi Home — success"
 *   2. "Navigasi Home — Menu Placeholder Dipilih"
 *   3. "Navigasi Home — Nama User Tidak Tersedia"
 *   4. "Navigasi Home — Tidak Menampilkan Status Draft"
 * ...plus the remaining unit_test_cases from the tech-spec (hamburger nav
 * menu) that aren't already subsumed by one of the 4 scenarios above.
 *
 * Selectors mirror HomeView.vue's actual markup (class names for purely
 * structural elements, data-testid for interactive/dynamic ones — same
 * convention already used by StationGrid.vue / StationGrid.spec.ts):
 *   - .home-header                                — header area
 *   - .hero-image                                  — hero <img>
 *   - .welcome-text                                — personal welcome text
 *   - [data-testid="menu-card-production-process-activity"] — functional
 *                                                     menu card (navigates)
 *   - [data-testid="menu-card-estimates-baselines"]          — placeholder
 *   - [data-testid="menu-card-dashboard-reporting"]          — placeholder
 *   - [data-testid="info-message"]                 — "Segera Hadir" info,
 *                                                     role="status"
 *   - [data-testid="hamburger-button"]             — hamburger icon button
 *   - [data-testid="nav-menu"]                     — nav menu panel
 *   - [data-testid="nav-menu-change-password"] / [data-testid="nav-menu-logout"]
 *
 * Mocking strategy (mirrors StationListView.spec.ts / old
 * HomeView.spec.ts):
 *   - 'vue-router' is mocked at module level so `router.push` can be
 *     asserted via a hoisted `pushMock`, without a real router instance.
 *   - '@/stores/auth' is mocked at module level via a hoisted
 *     `useAuthStoreMock`, exposing `currentUser` (HomeView.vue reads
 *     `authStore.currentUser?.name`) and a `logout` spy (the nav menu's
 *     Logout item calls `authStore.logout()`).
 *   - No '@/composables/useHomeSummary' / '@/services/draftRecordsRepo'
 *     mock is set up at all (deliberately) — the new HomeView makes no
 *     such call per this screen's spec; scenario 4 below asserts exactly
 *     that (no draft/status markup rendered, and nothing at all was
 *     mocked to produce it).
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import HomeView from '@/views/HomeView.vue'

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

const { getMillSettingMock } = vi.hoisted(() => ({ getMillSettingMock: vi.fn() }))

vi.mock('@/services/millSettingRepo', () => ({
  getMillSetting: getMillSettingMock,
}))

function mockAuthUser(name: string | null | undefined): void {
  const user = {
    id: 'user-1',
    username: 'operator01',
    name,
    role: 'operator',
    business_unit_id: 'bu-1',
  }

  useAuthStoreMock.mockReturnValue({
    user,
    currentUser: user,
    businessUnit: { id: 'bu-1', name: 'Business Unit A' },
    logout: logoutMock,
  })
}

describe('HomeView — "Navigasi Home"', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    logoutMock.mockResolvedValue(undefined)
    getMillSettingMock.mockResolvedValue(null)
    mockAuthUser('Budi')
  })

  // Scenario: "Navigasi Home — success"
  // Also satisfies unit_test_cases "menampilkan teks sambutan dengan nama
  // user ketika nama tersedia di auth store", "menavigasi ke Station List
  // ketika menu 'Production Process Activity' ditekan" and "returns
  // success result when all conditions pass".
  it('scenario: success — renders header, hero, personal welcome text and 3 menu cards, navigates to Station List on tap', async () => {
    const wrapper = mount(HomeView)
    await flushPromises()

    expect(wrapper.find('.home-header').exists()).toBe(true)
    expect(wrapper.find('.hero-image').exists()).toBe(true)

    const welcomeText = wrapper.get('.welcome-text').text()
    expect(welcomeText).toContain('Budi')
    expect(welcomeText).toMatch(/Selamat datang/i)

    const menuCards = wrapper.findAll('[data-testid^="menu-card-"]')
    expect(menuCards).toHaveLength(3)
    expect(wrapper.get('[data-testid="menu-card-production-process-activity"]').text()).toContain(
      'Production Process Activity',
    )
    expect(wrapper.get('[data-testid="menu-card-estimates-baselines"]').text()).toContain('Estimates & Baselines')
    expect(wrapper.get('[data-testid="menu-card-dashboard-reporting"]').text()).toContain('Dashboard & Reporting')

    await wrapper.get('[data-testid="menu-card-production-process-activity"]').trigger('click')

    expect(pushMock).toHaveBeenCalledTimes(1)
    expect(pushMock).toHaveBeenCalledWith({ name: 'station-list' })
  })

  // Scenario: "Navigasi Home — Menu Placeholder Dipilih"
  // Also satisfies unit_test_cases "menampilkan info 'Segera Hadir' dan
  // tidak menavigasi ketika menu 'Estimates & Baselines'/'Dashboard &
  // Reporting' ditekan".
  it.each([
    ['menu-card-estimates-baselines', 'Estimates & Baselines'],
    ['menu-card-dashboard-reporting', 'Dashboard & Reporting'],
  ] as const)(
    "scenario: menu placeholder dipilih — tapping '%s' shows 'Segera Hadir' and does not navigate",
    async (testId) => {
      const wrapper = mount(HomeView)
      await flushPromises()

      expect(wrapper.find('[data-testid="info-message"]').exists()).toBe(false)

      await wrapper.get(`[data-testid="${testId}"]`).trigger('click')

      const infoMessage = wrapper.get('[data-testid="info-message"]')
      expect(infoMessage.text()).toContain('Segera Hadir')
      expect(pushMock).not.toHaveBeenCalled()
      // Component stays showing Home — no navigation triggered.
      expect(wrapper.findComponent(HomeView).exists()).toBe(true)
    },
  )

  // Scenario: "Navigasi Home — Nama User Tidak Tersedia"
  // Also satisfies unit_test_case "menampilkan teks sambutan fallback
  // generik ketika nama user kosong/tidak tersedia".
  it.each([null, undefined, ''])(
    'scenario: nama user tidak tersedia (%s) — shows a generic fallback welcome with no user name',
    async (name) => {
      mockAuthUser(name)

      const wrapper = mount(HomeView)
      await flushPromises()

      const welcomeText = wrapper.get('.welcome-text').text()
      expect(welcomeText).toMatch(/Selamat datang/i)
      expect(welcomeText).not.toContain('null')
      expect(welcomeText).not.toContain('undefined')
      // No personalized ", <name>" suffix — a comma would indicate a name
      // slot was rendered anyway with an empty/garbage value.
      expect(welcomeText).not.toMatch(/,/)
    },
  )

  // Scenario: "Navigasi Home — Tidak Menampilkan Status Draft"
  // Also satisfies unit_test_case "returns success result when all
  // conditions pass" (no draft/record status of any kind).
  it('scenario: tidak menampilkan status draft — renders only header/hero/welcome + 3 menus, no draft/station status markup', async () => {
    const wrapper = mount(HomeView)
    await flushPromises()

    // Only header, hero, welcome text and 3 navigation menus are shown —
    // no leftover draft/record status element from the old per-station
    // dashboard.
    const testIds = wrapper.findAll('[data-testid]').map((el) => el.attributes('data-testid') ?? '')
    expect(testIds.some((id) => /draft|station-badge|station-status|status-badge/i.test(id))).toBe(false)

    expect(wrapper.text()).not.toContain('Sedang berlangsung')
    expect(wrapper.text()).not.toContain('Dijeda')
    expect(wrapper.text()).not.toContain('Tidak ada input')
    expect(wrapper.text()).not.toContain('Belum ada data draft')
    expect(wrapper.find('.paused-drafts-list').exists()).toBe(false)
    expect(wrapper.find('.station-row').exists()).toBe(false)
    expect(wrapper.find('.station-summary').exists()).toBe(false)

    expect(wrapper.find('.home-header').exists()).toBe(true)
    expect(wrapper.find('.hero-image').exists()).toBe(true)
    expect(wrapper.find('.welcome-text').exists()).toBe(true)
    expect(wrapper.findAll('[data-testid^="menu-card-"]')).toHaveLength(3)
  })
})

// unit_test_case: "membuka menu navigasi ketika ikon hamburger ditekan"
describe('HomeView — menu navigasi (hamburger)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    logoutMock.mockResolvedValue(undefined)
    mockAuthUser('Budi')
  })

  it('opens the nav menu (Ganti Password, Logout) when the hamburger icon is tapped', async () => {
    const wrapper = mount(HomeView)
    await flushPromises()

    expect(wrapper.find('[data-testid="nav-menu"]').exists()).toBe(false)

    await wrapper.get('[data-testid="hamburger-button"]').trigger('click')

    const navMenu = wrapper.get('[data-testid="nav-menu"]')
    expect(navMenu.text()).toContain('Ganti Password')
    expect(navMenu.text()).toContain('Logout')
  })

  it("navigates to 'change-password' when the 'Ganti Password' nav menu item is tapped", async () => {
    const wrapper = mount(HomeView)
    await flushPromises()

    await wrapper.get('[data-testid="hamburger-button"]').trigger('click')
    await wrapper.get('[data-testid="nav-menu-change-password"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'change-password' })
  })

  it("logs out and navigates to 'login' when the 'Logout' nav menu item is tapped", async () => {
    const wrapper = mount(HomeView)
    await flushPromises()

    await wrapper.get('[data-testid="hamburger-button"]').trigger('click')
    await wrapper.get('[data-testid="nav-menu-logout"]').trigger('click')
    await flushPromises()

    expect(logoutMock).toHaveBeenCalledTimes(1)
    expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
  })
})

// Scenario: "Navigasi Home — Hero Image Fallback Statis" (tech spec ver 3/4)
// Also satisfies unit_test_cases: "menampilkan hero image dari
// millSettingRepo.getMillSetting() saat data tersedia", "tidak memanggil API
// mill-settings dari Home saat mount" (v3 wording — mechanism corrected in
// v4 to millSettingRepo/SQLite, still asserted as "never fetches remotely"
// here), "fallback ke gambar statis bawaan ketika getMillSetting()
// mengembalikan null", "fallback ke gambar statis bawaan ketika
// home_page_image bernilai null".
describe('HomeView — hero image dari Mills Setting', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    logoutMock.mockResolvedValue(undefined)
    mockAuthUser('Budi')
  })

  it('renders the mill-setting home_page_image as hero image when cached data is available', async () => {
    getMillSettingMock.mockResolvedValue({
      id: 'ms-1',
      businessUnitId: 'bu-1',
      appName: 'Mill A App',
      logo: null,
      homePageImage: 'https://cdn.example.com/mill-a-hero.jpg',
      jumlahCages: 10,
    })

    const wrapper = mount(HomeView)
    await flushPromises()

    expect(getMillSettingMock).toHaveBeenCalledWith('bu-1')
    expect(wrapper.get('.hero-image').attributes('src')).toBe('https://cdn.example.com/mill-a-hero.jpg')
  })

  it('falls back to the bundled static hero image when no mill-setting is cached yet', async () => {
    getMillSettingMock.mockResolvedValue(null)

    const wrapper = mount(HomeView)
    await flushPromises()

    const src = wrapper.get('.hero-image').attributes('src')
    expect(src).toBeTruthy()
    expect(src).not.toBe('https://cdn.example.com/mill-a-hero.jpg')
  })

  it('falls back to the bundled static hero image when the cached mill-setting has a null home_page_image', async () => {
    getMillSettingMock.mockResolvedValue({
      id: 'ms-1',
      businessUnitId: 'bu-1',
      appName: 'Mill A App',
      logo: null,
      homePageImage: null,
      jumlahCages: 10,
    })

    const wrapper = mount(HomeView)
    await flushPromises()

    const src = wrapper.get('.hero-image').attributes('src')
    expect(src).toBeTruthy()
    expect(src).not.toBe('https://cdn.example.com/mill-a-hero.jpg')
  })

  it('does not call getMillSetting when the current user has no business unit', async () => {
    useAuthStoreMock.mockReturnValue({
      user: { id: 'user-1', username: 'operator01', name: 'Budi', role: 'operator', business_unit_id: null },
      currentUser: { id: 'user-1', username: 'operator01', name: 'Budi', role: 'operator', business_unit_id: null },
      businessUnit: null,
      logout: logoutMock,
    })

    const wrapper = mount(HomeView)
    await flushPromises()

    expect(getMillSettingMock).not.toHaveBeenCalled()
    expect(wrapper.get('.hero-image').attributes('src')).toBeTruthy()
  })
})
