/**
 * DashboardReportingView.spec.ts — screen-134--dashboard-reporting-mobile
 * / usecase-134--dashboard-reporting-mobile "Buka Dashboard & Reporting
 * (Mobile)".
 *
 * Covers all 10 unit_test_cases and all 8 test_scenarios' component_test
 * entries from the tech spec (screen-134). Several tests deliberately
 * satisfy more than one unit_test_case / scenario at once (comments note
 * which), same convention as HomeView.spec.ts / StationListView.spec.ts.
 *
 * 2026-09-23 (screen-141) — kartu 'Reporting' kini HIDUP: routeName-nya
 * terisi 'report-stations' (rute /reports, ReportingPilihStasiunView.vue),
 * sehingga menekannya bernavigasi alih-alih memunculkan pesan "belum
 * tersedia". Kartu 'Dashboard' TETAP nonaktif (routeName: null) — layar
 * tujuannya memang belum dibangun — dan seluruh asersi kartu Dashboard di
 * berkas ini sengaja dipertahankan utuh, tidak dilemahkan.
 *
 * MENU_OPTIONS is a fixed, unexported `<script setup>` constant — not a
 * prop. unit_test_cases 3 and 5 require exercising the "routeName is not
 * null" (active card) branch on the card that is still disabled, and
 * unit_test_case 7 requires a SECOND disabled card; neither is reachable
 * through any prop/public API on the real component as implemented. Rather than re-implementing that branch's logic in the
 * test (which would stop testing the real component) or skipping it, the
 * array is mutated in place via `wrapper.vm.MENU_OPTIONS` (Vue Test
 * Utils exposes a `<script setup>` component's top-level bindings on
 * `wrapper.vm` even without `defineExpose`) and a rerender is forced with
 * `$forceUpdate()` + `nextTick()` — confirmed empirically that both the
 * template (aria-disabled/class/badge) and `onSelectOption`'s branching
 * (which reads `option.routeName` directly off the same object) react to
 * this exactly as they would to a real future non-null `routeName` entry.
 * This is the ONLY place in this file that reaches into component
 * internals; every other test drives the component purely through its
 * public DOM/template surface.
 *
 * Mocking strategy (mirrors HomeView.spec.ts / StationListView.spec.ts):
 *   - 'vue-router' is mocked at module level so `router.push` can be
 *     asserted via a hoisted `pushMock`, without a real router instance.
 *     (The "Belum masuk" scenario is the one exception — see its own
 *     describe block below, which uses the REAL router/index.ts guard
 *     instead of this mock, per the tech spec's explicit instruction that
 *     that scenario tests the router layer, not the mounted component.)
 *   - '@/stores/auth' is mocked at module level via a hoisted
 *     `useAuthStoreMock`, exposing a `logout` spy (the header nav menu's
 *     Logout item calls it) and an overridable `currentUser` (used only
 *     by the "layar terbuka untuk semua peran mobile" scenario below —
 *     this component itself never reads `currentUser`, which is exactly
 *     what that scenario asserts: identical render across roles).
 *   - '@/stores/floatingClock' / '@/stores/aiAssistant' are mocked at
 *     module level, same as HomeView.spec.ts, purely so mounting this
 *     screen's copied-verbatim header doesn't require a real Pinia
 *     instance for those two unrelated stores.
 *   - '@/services/apiClient' and '@/services/localDb' are mocked with
 *     spies (not stubs the component ever imports — it imports neither,
 *     by design) purely so the "nol pemanggilan data" test can assert
 *     zero calls into either, per business_logic step 2 / the offline-
 *     friendliness guarantee this screen is built around.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import DashboardReportingView from '@/views/DashboardReportingView.vue'

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

const { apiClientGetMock, apiClientPostMock } = vi.hoisted(() => ({
  apiClientGetMock: vi.fn(),
  apiClientPostMock: vi.fn(),
}))

vi.mock('@/services/apiClient', () => ({
  default: { get: apiClientGetMock, post: apiClientPostMock },
}))

const { localDbQueryMock, localDbRunMock } = vi.hoisted(() => ({
  localDbQueryMock: vi.fn(),
  localDbRunMock: vi.fn(),
}))

vi.mock('@/services/localDb', () => ({
  localDb: { query: localDbQueryMock, run: localDbRunMock },
  query: localDbQueryMock,
  run: localDbRunMock,
  default: { query: localDbQueryMock, run: localDbRunMock },
}))

function mockAuthUser(role: 'operator' | 'supervisor' | 'mill_management' | 'admin' = 'operator') {
  useAuthStoreMock.mockReturnValue({
    currentUser: {
      id: 'user-1',
      username: `${role}01`,
      name: 'Pengguna Uji',
      role,
      business_unit_id: 'bu-1',
    },
    logout: logoutMock,
  })
}

describe('DashboardReportingView — "Buka Dashboard & Reporting (Mobile)"', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    window.localStorage.clear()
    logoutMock.mockResolvedValue(undefined)
    mockAuthUser('operator')
  })

  // unit_test_case 10 ("Render sukses jalur normal") +
  // Scenario: "Buka Dashboard & Reporting (Mobile) — success"
  it('scenario: success — renders breadcrumb, title and both menu cards with no info-message', async () => {
    const wrapper = mount(DashboardReportingView)
    await flushPromises()

    const breadcrumb = wrapper.get('.breadcrumb')
    expect(breadcrumb.text()).toContain('Home')
    expect(breadcrumb.text()).toContain('Dashboard & Reporting')
    expect(wrapper.get('.screen-title').text()).toBe('Dashboard & Reporting')

    expect(wrapper.get('[data-testid="menu-card-dashboard"]').text()).toContain('Dashboard')
    expect(wrapper.get('[data-testid="menu-card-reporting"]').text()).toContain('Reporting')
    expect(wrapper.find('[data-testid="info-message"]').exists()).toBe(false)
    expect(wrapper.find('.loading, [data-testid="loading"]').exists()).toBe(false)
  })

  // unit_test_case 1
  it('renders exactly two menu cards in MENU_OPTIONS order: Dashboard then Reporting', async () => {
    const wrapper = mount(DashboardReportingView)
    await flushPromises()

    const cards = wrapper.findAll('[data-testid^="menu-card-"]')
    expect(cards).toHaveLength(2)
    expect(cards[0].attributes('data-testid')).toBe('menu-card-dashboard')
    expect(cards[0].text()).toContain('Dashboard')
    expect(cards[1].attributes('data-testid')).toBe('menu-card-reporting')
    expect(cards[1].text()).toContain('Reporting')
  })

  // unit_test_case 2 + Scenario: "Buka Dashboard & Reporting (Mobile) —
  // pilihan yang belum dibangun tetap ditampilkan nonaktif".
  //
  // 2026-09-23 (screen-141): hanya kartu 'Dashboard' yang masih nonaktif —
  // layar tujuannya belum dibangun. Asersinya TIDAK dilemahkan sedikit pun;
  // yang berubah hanya kartu 'Reporting', yang kini punya layar tujuan.
  it('scenario: pilihan belum dibangun tetap ditampilkan nonaktif — the Dashboard card is marked disabled with the "Belum tersedia" badge', async () => {
    const wrapper = mount(DashboardReportingView)
    await flushPromises()

    const card = wrapper.get('[data-testid="menu-card-dashboard"]')
    expect(card.attributes('aria-disabled')).toBe('true')
    expect(card.classes()).toContain('menu-card--disabled')
    expect(card.text()).toContain('Belum tersedia')
    // Sengaja BUKAN atribut `disabled` native — lihat komentar
    // StationGrid.vue: `disabled` menelan ketukan sehingga pengguna
    // tidak pernah mendapat umpan balik apa pun.
    expect(card.attributes('disabled')).toBeUndefined()
  })

  // screen-141 — kartu 'Reporting' kini hidup: ia bernavigasi ke rute
  // 'report-stations' (ReportingPilihStasiunView.vue) dan TIDAK lagi
  // memunculkan pesan "belum tersedia".
  it('the Reporting card is active: no aria-disabled state, no "Belum tersedia" badge, and it navigates to "report-stations"', async () => {
    const wrapper = mount(DashboardReportingView)
    await flushPromises()

    const card = wrapper.get('[data-testid="menu-card-reporting"]')
    expect(card.attributes('aria-disabled')).toBe('false')
    expect(card.classes()).not.toContain('menu-card--disabled')
    expect(card.text()).not.toContain('Belum tersedia')

    await card.trigger('click')

    expect(pushMock).toHaveBeenCalledTimes(1)
    expect(pushMock).toHaveBeenCalledWith({ name: 'report-stations' })
    expect(wrapper.find('[data-testid="info-message"]').exists()).toBe(false)
  })

  // unit_test_case 3 — exercises the "routeName is not null" (active
  // card) branch via the MENU_OPTIONS mutation technique documented in
  // this file's header comment (not reachable through any prop).
  it('renders a menu card as active (no aria-disabled, no disabled class, no badge) once its routeName is set', async () => {
    const wrapper = mount(DashboardReportingView)
    await flushPromises()

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const menuOptions = (wrapper.vm as any).MENU_OPTIONS as Array<{ key: string; routeName: string | null }>
    const dashboardOption = menuOptions.find((option) => option.key === 'dashboard')
    if (dashboardOption) {
      dashboardOption.routeName = 'dashboard-mobile'
    }
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    ;(wrapper.vm as any).$forceUpdate()
    await wrapper.vm.$nextTick()

    const card = wrapper.get('[data-testid="menu-card-dashboard"]')
    expect(card.attributes('aria-disabled')).toBe('false')
    expect(card.classes()).not.toContain('menu-card--disabled')
    expect(card.text()).not.toContain('Belum tersedia')
  })

  // unit_test_case 4 + Scenario: "Buka Dashboard & Reporting (Mobile) —
  // Menekan kartu yang belum tersedia"
  it('scenario: menekan kartu yang belum tersedia — fills the info message and does not navigate', async () => {
    const wrapper = mount(DashboardReportingView)
    await flushPromises()

    expect(wrapper.find('[data-testid="info-message"]').exists()).toBe(false)

    // 2026-09-23 (screen-141): kartu yang masih belum tersedia kini
    // 'Dashboard' — 'Reporting' sudah punya layar tujuan.
    await wrapper.get('[data-testid="menu-card-dashboard"]').trigger('click')

    const infoMessage = wrapper.get('[data-testid="info-message"]')
    expect(infoMessage.text()).toBe('Layar Dashboard belum tersedia.')
    expect(infoMessage.attributes('role')).toBe('status')
    expect(pushMock).not.toHaveBeenCalled()

    const card = wrapper.get('[data-testid="menu-card-dashboard"]')
    expect(card.attributes('aria-disabled')).toBe('true')
    expect(card.text()).toContain('Belum tersedia')
  })

  // unit_test_case 5 — exercises the "routeName is not null" navigate
  // branch of onSelectOption() via the MENU_OPTIONS mutation technique
  // documented in this file's header comment.
  it('calls router.push with the destination route name when a card with a non-null routeName is tapped', async () => {
    const wrapper = mount(DashboardReportingView)
    await flushPromises()

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const menuOptions = (wrapper.vm as any).MENU_OPTIONS as Array<{ key: string; routeName: string | null }>
    const dashboardOption = menuOptions.find((option) => option.key === 'dashboard')
    if (dashboardOption) {
      dashboardOption.routeName = 'dashboard-mobile'
    }

    await wrapper.get('[data-testid="menu-card-dashboard"]').trigger('click')

    expect(pushMock).toHaveBeenCalledTimes(1)
    expect(pushMock).toHaveBeenCalledWith({ name: 'dashboard-mobile' })
    expect(wrapper.find('[data-testid="info-message"]').exists()).toBe(false)
  })

  // unit_test_case 6 + Scenario: "Buka Dashboard & Reporting (Mobile) —
  // Menekan berkali-kali". Asserts the *count* of info-message elements,
  // not just its text content, per the "pesan tidak menumpuk" requirement.
  it('scenario: menekan berkali-kali — repeated taps on a disabled card never stack the info message', async () => {
    const wrapper = mount(DashboardReportingView)
    await flushPromises()

    const card = wrapper.get('[data-testid="menu-card-dashboard"]')
    await card.trigger('click')
    await wrapper.vm.$nextTick()
    await card.trigger('click')
    await wrapper.vm.$nextTick()
    await card.trigger('click')
    await wrapper.vm.$nextTick()

    const infoMessages = wrapper.findAll('[data-testid="info-message"]')
    expect(infoMessages).toHaveLength(1)
    expect(infoMessages[0].text()).toBe('Layar Dashboard belum tersedia.')
    expect(pushMock).not.toHaveBeenCalled()
  })

  // unit_test_case 7 — tapping a different disabled card overwrites the
  // single info-message element rather than appending a new one.
  //
  // 2026-09-23 (screen-141): hanya tersisa SATU kartu nonaktif di
  // MENU_OPTIONS, sehingga kartu nonaktif kedua dibuat dengan teknik mutasi
  // MENU_OPTIONS yang sama seperti unit_test_cases 3/5 (lihat komentar
  // kepala berkas) — kebalikannya: routeName 'Reporting' dikembalikan ke
  // null untuk memulihkan kondisi "dua kartu nonaktif" yang menjadi inti
  // unit_test_case ini.
  it('overwrites the previous info message (still a single element) when a different disabled card is tapped', async () => {
    const wrapper = mount(DashboardReportingView)
    await flushPromises()

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const menuOptions = (wrapper.vm as any).MENU_OPTIONS as Array<{ key: string; routeName: string | null }>
    const reportingOption = menuOptions.find((option) => option.key === 'reporting')
    if (reportingOption) {
      reportingOption.routeName = null
    }
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    ;(wrapper.vm as any).$forceUpdate()
    await wrapper.vm.$nextTick()

    await wrapper.get('[data-testid="menu-card-dashboard"]').trigger('click')
    await wrapper.get('[data-testid="menu-card-reporting"]').trigger('click')

    const infoMessages = wrapper.findAll('[data-testid="info-message"]')
    expect(infoMessages).toHaveLength(1)
    expect(infoMessages[0].text()).toBe('Layar Reporting belum tersedia.')
    expect(pushMock).not.toHaveBeenCalled()
  })

  // unit_test_case 8 + Scenario: "Buka Dashboard & Reporting (Mobile) —
  // Kembali ke Home"
  it('scenario: kembali ke Home — tapping the "Home" breadcrumb segment navigates to the home route', async () => {
    const wrapper = mount(DashboardReportingView)
    await flushPromises()

    await wrapper.get('[data-testid="breadcrumb-home"]').trigger('click')

    expect(pushMock).toHaveBeenCalledTimes(1)
    expect(pushMock).toHaveBeenCalledWith({ name: 'home' })
  })

  // unit_test_case 9 + Scenario: "Buka Dashboard & Reporting (Mobile) —
  // Perangkat offline". A single mount + a full round of the disabled-
  // card interactions must never touch apiClient or localDb — this
  // screen's offline-friendliness is a deliberate feature, not an
  // accident (implementation_notes).
  it('scenario: perangkat offline / nol pemanggilan data — mounting and interacting never calls apiClient or localDb', async () => {
    const originalOnLine = window.navigator.onLine
    Object.defineProperty(window.navigator, 'onLine', { value: false, configurable: true })
    apiClientGetMock.mockRejectedValue(new Error('should never be called'))
    apiClientPostMock.mockRejectedValue(new Error('should never be called'))
    localDbQueryMock.mockRejectedValue(new Error('should never be called'))
    localDbRunMock.mockRejectedValue(new Error('should never be called'))

    const wrapper = mount(DashboardReportingView)
    await flushPromises()

    await wrapper.get('[data-testid="menu-card-dashboard"]').trigger('click')
    // Kartu 'Reporting' kini bernavigasi (screen-141) — tetap ditekan di
    // sini justru untuk membuktikan navigasinya pun nol jaringan.
    await wrapper.get('[data-testid="menu-card-reporting"]').trigger('click')
    await wrapper.get('[data-testid="breadcrumb-home"]').trigger('click')
    await flushPromises()

    expect(apiClientGetMock).not.toHaveBeenCalled()
    expect(apiClientPostMock).not.toHaveBeenCalled()
    expect(localDbQueryMock).not.toHaveBeenCalled()
    expect(localDbRunMock).not.toHaveBeenCalled()

    // Still fully rendered — offline has no effect on this data-free screen.
    expect(wrapper.get('[data-testid="menu-card-dashboard"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="menu-card-reporting"]').exists()).toBe(true)
    expect(wrapper.text()).not.toMatch(/gagal|error|kesalahan jaringan/i)

    Object.defineProperty(window.navigator, 'onLine', { value: originalOnLine, configurable: true })
  })

  // Scenario: "Buka Dashboard & Reporting (Mobile) — layar terbuka untuk
  // semua peran mobile". This screen shows no data and applies no
  // per-role branch (actor_permissions: can_access=true, unconditional,
  // for all 4 mobile actors) — render must be identical regardless of
  // the current user's role.
  it.each(['actor-station-operator', 'actor-supervisor', 'actor-mill-management', 'actor-admin'] as const)(
    'scenario: layar terbuka untuk semua peran mobile — renders identically for %s',
    async (actorId) => {
      const roleByActor = {
        'actor-station-operator': 'operator',
        'actor-supervisor': 'supervisor',
        'actor-mill-management': 'mill_management',
        'actor-admin': 'admin',
      } as const
      mockAuthUser(roleByActor[actorId])

      const wrapper = mount(DashboardReportingView)
      await flushPromises()

      const cards = wrapper.findAll('[data-testid^="menu-card-"]')
      expect(cards).toHaveLength(2)
      expect(cards[0].text()).toContain('Dashboard')
      expect(cards[0].attributes('aria-disabled')).toBe('true')
      expect(cards[1].text()).toContain('Reporting')
      // screen-141 — kartu 'Reporting' hidup untuk SEMUA peran: layar
      // tujuannya tidak punya cabang berbasis peran sama sekali.
      expect(cards[1].attributes('aria-disabled')).toBe('false')
      expect(wrapper.text()).not.toMatch(/akses ditolak|tidak diizinkan|forbidden/i)
      expect(pushMock).not.toHaveBeenCalled()
    },
  )
})

// Scenario: "Buka Dashboard & Reporting (Mobile) — Belum masuk"
//
// Per the tech spec, this scenario's component_test explicitly targets
// the router layer, NOT the mounted component — DashboardReportingView is
// never mounted in this describe block at all, because per the auth
// guard it is never mounted in production either when there is no active
// session. This uses the REAL router/index.ts (default export), not the
// 'vue-router' mock used by every other test in this file, and a real
// (fresh) Pinia instance so stores/auth.ts's restoreSession() runs for
// real against a cleared localStorage — same "exercise the real
// guard/store, mock only the network-touching edges (apiClient, mocked at
// this file's top)" approach as auth.store.spec.ts.
describe('DashboardReportingView — router guard "Belum masuk"', () => {
  beforeEach(() => {
    window.localStorage.clear()
  })

  it('scenario: belum masuk — route "dashboard-reporting" has meta.public=false and the guard redirects to "login" before the component would ever mount', async () => {
    // This file's top-level `vi.mock('vue-router', ...)` and
    // `vi.mock('@/stores/auth', ...)` apply to every static AND dynamic
    // import for the whole file (vi.mock is hoisted/module-scoped) — both
    // are undone here + vi.resetModules() so the dynamic imports below
    // pull in the REAL vue-router (createRouter/createWebHistory) and the
    // REAL Pinia auth store, which is required for this scenario to
    // exercise the actual router.beforeEach guard end-to-end rather than
    // the mocked push() used by every other test in this file.
    vi.doUnmock('vue-router')
    vi.doUnmock('@/stores/auth')
    vi.resetModules()

    const { createPinia: createRealPinia, setActivePinia: setRealActivePinia } = await import('pinia')
    setRealActivePinia(createRealPinia())

    const { default: router } = await import('@/router')

    const targetRoute = router.getRoutes().find((route) => route.name === 'dashboard-reporting')
    expect(targetRoute).toBeDefined()
    expect(targetRoute?.meta.public).toBe(false)

    await router.push({ name: 'dashboard-reporting' })

    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/dashboard-reporting')
  })
})
