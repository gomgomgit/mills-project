/**
 * ChangePasswordForm.spec.ts — screen-004--ganti-password-mobile /
 * usecase-004--ganti-password-mobile.
 *
 * Component tests for mobile/src/components/ChangePasswordForm.vue,
 * covering all 5 test_scenarios' component_test entries (component:
 * "ChangePasswordForm") for the PATCH /api/me/password (mobile, Sanctum)
 * use case:
 *   1. Ganti Password Mobile — berhasil
 *   2. Tidak ada koneksi internet
 *   3. Ganti Password Mobile — Password Lama Salah
 *   4. Ganti Password Mobile — Password Baru Tidak Memenuhi Format
 *   5. Ganti Password Mobile — Konfirmasi Tidak Cocok
 *
 * Mocking strategy (mirrors mobile/tests/LoginForm.spec.ts):
 *   - '@/services/apiClient' is mocked at module level — ChangePasswordForm
 *     calls apiClient.patch() directly (no store in between), so the test
 *     asserts on that mock directly rather than through a Pinia store.
 *   - '@/composables/useConnectivityGuard' is mocked at module level so
 *     `blocksAction` / `offlineActionMessage` can be controlled per test,
 *     without depending on real `navigator.onLine` / window event wiring
 *     (unlike LoginForm.spec.ts, which toggles the real navigator.onLine
 *     property because LoginForm's blocksFirstLogin also depends on
 *     tokenStorage.hasToken()'s real composable wiring; ChangePasswordForm
 *     only needs the simpler blocksAction boolean, so mocking the
 *     composable directly is more direct and avoids reaching into
 *     tokenStorage for a scenario that doesn't concern it).
 *   - Rejections are given already in the app's NormalizedApiError shape
 *     ({ message, status, errors? }) — the shape apiClient's real Axios
 *     response interceptor produces — since the interceptor itself is
 *     bypassed by mocking the whole apiClient module.
 *
 * Scenario 4 / 5 note: ChangePasswordForm.vue validates new_password
 * format and confirmation-match CLIENT-SIDE (validate(), mirroring
 * AuthService's rules) before ever calling the API — so those two
 * scenarios assert the client-side inline field error and that
 * apiClient.patch is never called, rather than mocking a server rejection
 * (the format/mismatch case can never reach the server from this form).
 * The VALIDATION_ERROR / PASSWORD_CONFIRMATION_MISMATCH server-error
 * branches in onSubmit()'s catch block are defense-in-depth for a
 * server-side re-validation mismatch; they are covered by the BE
 * integration test instead (ChangePasswordMobileTest.php).
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import ChangePasswordForm from '@/components/ChangePasswordForm.vue'
import apiClient from '@/services/apiClient'

const OFFLINE_ACTION_MESSAGE = 'Tidak ada koneksi internet. Ganti password memerlukan koneksi internet.'

const { blocksActionRef, offlineActionMessageRef } = vi.hoisted(() => {
  // Lazily required inside the mock factory below (vi.mock is hoisted
  // above imports), so the refs themselves are created via vi.hoisted to
  // avoid a temporal-dead-zone reference.
  return {
    blocksActionRef: { value: false },
    offlineActionMessageRef: { value: 'Tidak ada koneksi internet. Ganti password memerlukan koneksi internet.' },
  }
})

vi.mock('@/composables/useConnectivityGuard', () => ({
  useConnectivityGuard: () => ({
    isOnline: { value: !blocksActionRef.value },
    hasStoredToken: { value: true },
    blocksFirstLogin: { value: false },
    blockMessage: 'Koneksi internet diperlukan untuk login pertama kali',
    blocksAction: blocksActionRef,
    offlineActionMessage: offlineActionMessageRef.value,
  }),
}))

vi.mock('@/services/apiClient', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
  },
}))

/**
 * Small controllable-promise helper so "loading state shown" can be
 * asserted while the mocked apiClient.patch call is still pending.
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

function mountForm() {
  return mount(ChangePasswordForm)
}

async function fillValidForm(wrapper: ReturnType<typeof mountForm>) {
  await wrapper.find('#old_password').setValue('OldPass123!')
  await wrapper.find('#new_password').setValue('NewPass456!')
  await wrapper.find('#new_password_confirmation').setValue('NewPass456!')
}

describe('ChangePasswordForm — PATCH /api/me/password (mobile, Sanctum)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    blocksActionRef.value = false
    offlineActionMessageRef.value = OFFLINE_ACTION_MESSAGE
  })

  afterEach(() => {
    blocksActionRef.value = false
  })

  // Scenario: "Ganti Password Mobile — berhasil"
  it('scenario: berhasil — shows loading state, submits the correct payload, shows success message', async () => {
    const patch = deferred<{ data: { message: string } }>()
    vi.mocked(apiClient.patch).mockReturnValue(patch.promise as unknown as Promise<never>)

    const wrapper = mountForm()
    await fillValidForm(wrapper)

    const submitPromise = wrapper.find('form').trigger('submit')
    await flushPromises()

    // Loading state shown while the request is pending.
    const submitButton = wrapper.find('.submit-button')
    expect(submitButton.text()).toContain('Memproses')
    expect(submitButton.attributes('disabled')).toBeDefined()

    patch.resolve({ data: { message: 'Password berhasil diubah.' } })
    await submitPromise
    await flushPromises()

    expect(apiClient.patch).toHaveBeenCalledWith('/api/me/password', {
      old_password: 'OldPass123!',
      new_password: 'NewPass456!',
      new_password_confirmation: 'NewPass456!',
    })

    expect(wrapper.text()).toContain('Password berhasil diubah.')

    // Form fields reset on success.
    expect((wrapper.find('#old_password').element as HTMLInputElement).value).toBe('')
    expect((wrapper.find('#new_password').element as HTMLInputElement).value).toBe('')
    expect((wrapper.find('#new_password_confirmation').element as HTMLInputElement).value).toBe('')
  })

  // Scenario: "Tidak ada koneksi internet"
  it('scenario: tidak ada koneksi internet — blocks submit client-side, no API call', async () => {
    blocksActionRef.value = true

    const wrapper = mountForm()
    await fillValidForm(wrapper)

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(apiClient.patch).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain(OFFLINE_ACTION_MESSAGE)
  })

  // Scenario: "Ganti Password Mobile — Password Lama Salah"
  it('scenario: password lama salah — shows error, clears old_password field', async () => {
    vi.mocked(apiClient.patch).mockRejectedValue({
      message: 'Password lama salah.',
      status: 422,
    })

    const wrapper = mountForm()
    await fillValidForm(wrapper)

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Password lama salah.')
    expect((wrapper.find('#old_password').element as HTMLInputElement).value).toBe('')
    // new_password fields are left untouched for this error.
    expect((wrapper.find('#new_password').element as HTMLInputElement).value).toBe('NewPass456!')
  })

  // Scenario: "Ganti Password Mobile — Password Baru Tidak Memenuhi Format"
  it('scenario: password baru tidak memenuhi format — client-side validation blocks submit', async () => {
    const wrapper = mountForm()

    await wrapper.find('#old_password').setValue('OldPass123!')
    await wrapper.find('#new_password').setValue('abc')
    await wrapper.find('#new_password_confirmation').setValue('abc')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    // ChangePasswordForm.vue mirrors AuthService::validatePasswordFormat()
    // client-side (isPasswordFormatValid) — submit never reaches the API
    // for this case.
    expect(apiClient.patch).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain(
      'Password baru minimal 6 karakter dan harus mengandung kombinasi huruf/angka serta simbol.',
    )
  })

  // Scenario: "Ganti Password Mobile — Konfirmasi Tidak Cocok"
  it('scenario: konfirmasi tidak cocok — client-side validation blocks submit', async () => {
    const wrapper = mountForm()

    await wrapper.find('#old_password').setValue('OldPass123!')
    await wrapper.find('#new_password').setValue('NewPass456!')
    await wrapper.find('#new_password_confirmation').setValue('Different789!')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(apiClient.patch).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Konfirmasi password tidak cocok dengan password baru.')
  })
})
