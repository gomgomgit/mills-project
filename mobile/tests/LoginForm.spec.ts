/**
 * LoginForm.spec.ts — screen-002--login-mobile / usecase-002--login-mobile.
 *
 * Component tests for mobile/src/components/LoginForm.vue, covering the
 * component_test scenarios (component: "LoginForm") for the
 * POST /api/login (mobile, device_name) use case:
 *   1. Login Mobile — berhasil
 *   2. Tidak Ada Koneksi Saat Login Pertama
 *   3. Login Mobile — Kredensial Salah
 *   4. Login Mobile — Akun Dinonaktifkan
 *   5. (Token Sesi Lokal Kadaluarsa — NOT a LoginForm submit-flow scenario;
 *      covered separately in mobile/tests/auth.store.spec.ts against
 *      stores/auth.ts's restoreSession(), per the app-boot/offline nature
 *      of that edge case.)
 *   6. Login Mobile — Password Tidak Memenuhi Format Minimum
 *   7. Login Mobile — Akun Tanpa Business Unit (server-side auto-derive
 *      failure — replaces the old "Business Area Tidak Sesuai Penugasan"
 *      scenario, which required a client-side Business Area picker that no
 *      longer exists: business_unit_id is no longer collected in this form
 *      at all — AuthService::login() now auto-derives it from the account
 *      when the request omits it. This scenario now covers the one
 *      remaining reachable server-side rejection: an account with no
 *      business_unit_id assigned has nothing to auto-derive.)
 *
 * Mocking strategy:
 *   - '@/services/apiClient' is mocked at module level. stores/auth.ts's
 *     real login() action runs (not mocked) and calls the mocked
 *     apiClient.post('/api/login', ...) — so these tests exercise the real
 *     LoginForm -> real Pinia auth store -> mocked HTTP boundary, matching
 *     "mock API calls, don't make real HTTP requests" while still covering
 *     the store wiring LoginForm depends on (emitted navigation, token
 *     persistence, error display).
 *   - Rejections are given already in the app's NormalizedApiError shape
 *     ({ message, status }) — the shape apiClient's real Axios response
 *     interceptor produces — since the interceptor itself is bypassed by
 *     mocking the whole apiClient module.
 *   - '@/services/tokenStorage' is mocked at module level so assertions can
 *     verify persistence calls without touching real localStorage, and so
 *     useConnectivityGuard's hasStoredToken() can be controlled per test.
 *   - vue-router's useRouter/useRoute are mocked at module level (LoginForm
 *     is rendered standalone, not through a mounted router).
 *
 * '@/services/localSchema' is mocked at module level too: stores/auth.ts's
 * real login() (exercised here, see above) now calls
 * seedDefaultStationsIfNeeded() after a successful login (screen-006
 * Station List empty-grid fix, 2026-08-18). The real implementation opens
 * a live SQLite connection via localDb.ts, which has no backing bridge in
 * this jsdom test environment (`customElements.whenDefined('jeep-sqlite')`
 * would hang forever, since jeep-sqlite is only registered by main.ts's
 * real app bootstrap) — mocked here to a no-op so these tests keep
 * covering LoginForm/auth-store wiring without needing a real local DB.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import LoginForm from '@/components/LoginForm.vue'
import apiClient from '@/services/apiClient'
import { tokenStorage } from '@/services/tokenStorage'

const { pushMock } = vi.hoisted(() => ({ pushMock: vi.fn() }))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
  useRoute: () => ({ query: {} }),
}))

vi.mock('@/services/apiClient', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
}))

vi.mock('@/services/localSchema', () => ({
  seedDefaultStationsIfNeeded: vi.fn(() => Promise.resolve()),
  fetchAndCacheMillSetting: vi.fn(() => Promise.resolve()),
  initLocalSchema: vi.fn(() => Promise.resolve()),
}))

vi.mock('@/services/tokenStorage', () => ({
  tokenStorage: {
    getToken: vi.fn(() => null),
    setToken: vi.fn(),
    hasToken: vi.fn(() => true),
    getTokenIssuedAt: vi.fn(() => null),
    setTokenIssuedAt: vi.fn(),
    getUser: vi.fn(() => null),
    setUser: vi.fn(),
    getBusinessUnit: vi.fn(() => null),
    setBusinessUnit: vi.fn(),
    clear: vi.fn(),
  },
}))

/**
 * Small controllable-promise helper so "loading state shown" can be
 * asserted while the mocked apiClient.post call is still pending.
 */
function deferred<T>() {
  let resolve!: (value: T) => void
  let reject!: (reason?: unknown) => void
  const promise = new Promise<T>((res, rej) => {
    resolve = res
    reject = rej
  })

  return { promise, resolve, reject }
}

async function mountLoginForm() {
  const wrapper = mount(LoginForm, {
    global: {
      plugins: [createPinia()],
    },
  })

  await flushPromises()

  return wrapper
}

async function fillValidForm(wrapper: Awaited<ReturnType<typeof mountLoginForm>>) {
  await wrapper.find('#username').setValue('operator01')
  await wrapper.find('#password').setValue('Passw0rd!')
}

describe('LoginForm — POST /api/login (mobile, device_name)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    vi.mocked(tokenStorage.hasToken).mockReturnValue(true)
    Object.defineProperty(navigator, 'onLine', { value: true, configurable: true })
  })

  afterEach(() => {
    Object.defineProperty(navigator, 'onLine', { value: true, configurable: true })
  })

  // Scenario: "Login Mobile — berhasil"
  it('scenario: berhasil — shows loading state, navigates to Home, persists the token', async () => {
    const post = deferred<{ data: { user: unknown; business_unit: unknown; token: string } }>()
    vi.mocked(apiClient.post).mockReturnValue(post.promise as unknown as Promise<never>)

    const wrapper = await mountLoginForm()
    await fillValidForm(wrapper)

    const submitPromise = wrapper.find('form').trigger('submit')
    await flushPromises()

    // Loading state shown while the request is pending.
    const submitButton = wrapper.find('.submit-button')
    expect(submitButton.text()).toContain('Memproses')
    expect(submitButton.attributes('disabled')).toBeDefined()

    post.resolve({
      data: {
        user: { id: 'user-1', username: 'operator01', name: 'Operator Satu', role: 'operator' },
        business_unit: { id: 'bu-001', name: 'Mill A' },
        token: 'sanctum-token-abc',
      },
    })
    await submitPromise
    await flushPromises()

    // business_unit_id is intentionally NOT sent — the backend auto-derives
    // it from the authenticated account (AuthService::login() step 5).
    expect(apiClient.post).toHaveBeenCalledWith(
      '/api/login',
      expect.objectContaining({
        username: 'operator01',
        password: 'Passw0rd!',
        device_name: expect.any(String),
      }),
    )
    expect(apiClient.post).not.toHaveBeenCalledWith(
      '/api/login',
      expect.objectContaining({ business_unit_id: expect.anything() }),
    )
    expect(pushMock).toHaveBeenCalledWith('/home')
    expect(tokenStorage.setToken).toHaveBeenCalledWith('sanctum-token-abc')
  })

  // Scenario: "Tidak Ada Koneksi Saat Login Pertama"
  it('scenario: tidak ada koneksi saat login pertama — blocks submit client-side, no API call', async () => {
    Object.defineProperty(navigator, 'onLine', { value: false, configurable: true })
    vi.mocked(tokenStorage.hasToken).mockReturnValue(false)

    const wrapper = await mountLoginForm()
    await fillValidForm(wrapper)

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(apiClient.post).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Koneksi internet diperlukan untuk login pertama kali')
  })

  // Scenario: "Login Mobile — Kredensial Salah"
  it('scenario: kredensial salah — shows error, clears password, allows retry', async () => {
    vi.mocked(apiClient.post).mockRejectedValue({
      message: 'Username atau password salah.',
      status: 401,
    })

    const wrapper = await mountLoginForm()
    await fillValidForm(wrapper)

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Username atau password salah.')
    expect((wrapper.find('#password').element as HTMLInputElement).value).toBe('')

    // Retry allowed — form is editable again, submit button re-enabled.
    expect(wrapper.find('.submit-button').attributes('disabled')).toBeUndefined()
    expect((wrapper.find('#username').element as HTMLInputElement).disabled).toBe(false)
  })

  // Scenario: "Login Mobile — Akun Dinonaktifkan"
  it('scenario: akun dinonaktifkan — shows inactive-account error, login rejected', async () => {
    vi.mocked(apiClient.post).mockRejectedValue({
      message: 'Akun tidak aktif, hubungi Admin.',
      status: 403,
    })

    const wrapper = await mountLoginForm()
    await fillValidForm(wrapper)

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Akun tidak aktif, hubungi Admin.')
    expect(pushMock).not.toHaveBeenCalled()
    expect(tokenStorage.setToken).not.toHaveBeenCalled()
  })

  // Scenario: "Login Mobile — Password Tidak Memenuhi Format Minimum"
  it('scenario: password tidak memenuhi format minimum — client-side validation blocks submit', async () => {
    const wrapper = await mountLoginForm()

    await wrapper.find('#username').setValue('operator01')
    await wrapper.find('#password').setValue('abc')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    // LoginForm.vue mirrors AuthService::validatePasswordFormat() client-side
    // (isPasswordFormatValid) — submit never reaches the API for this case.
    expect(apiClient.post).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain(
      'Password minimal 6 karakter dan harus mengandung kombinasi huruf/angka serta simbol.',
    )
  })

  // Scenario: "Login Mobile — Akun Tanpa Business Unit"
  it('scenario: akun tanpa business unit — server rejects auto-derive, login ditolak', async () => {
    vi.mocked(apiClient.post).mockRejectedValue({
      message: 'Business area yang dipilih tidak sesuai dengan akses Anda.',
      status: 403,
    })

    const wrapper = await mountLoginForm()
    await fillValidForm(wrapper)

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Business area yang dipilih tidak sesuai dengan akses Anda.')
    expect(pushMock).not.toHaveBeenCalled()
    expect(tokenStorage.setToken).not.toHaveBeenCalled()
  })
})
