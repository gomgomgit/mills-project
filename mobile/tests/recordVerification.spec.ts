/**
 * recordVerification.spec.ts — the direct approve/un-approve action added to
 * Data Preview on 2026-09-14 (src/services/recordVerificationApi.ts +
 * src/components/RecordVerificationActions.vue).
 *
 * Covers the role rule (must match the backend's RecordVerificationService
 * exactly), the "never synced yet" guard, and the online-only failure mode —
 * this app's syncService has no outbox to queue an offline verification, so
 * a network failure must surface rather than look like a success.
 *
 * '@/services/localDb' and '@/services/apiClient' are mocked at module level
 * per this suite's existing convention — no SQLite and no HTTP is touched.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('@/services/localDb', () => ({ run: vi.fn() }))
vi.mock('@/services/apiClient', () => ({ default: { patch: vi.fn() } }))

import apiClient from '@/services/apiClient'
import { run } from '@/services/localDb'
import { setVerification, serverIdOf, isNetworkError } from '@/services/recordVerificationApi'
import RecordVerificationActions from '@/components/RecordVerificationActions.vue'
import { useAuthStore } from '@/stores/auth'

const SYNCED_RECORD = {
  id: 'local-1',
  server_id: 'server-1',
  checked_by: null,
  acknowledged_by: null,
}

function mountWith(role: string, record: Record<string, unknown> | null = SYNCED_RECORD, props = {}) {
  const auth = useAuthStore()
  // `currentUser` is a getter over state.user — set the state, not the getter.
  auth.user = {
    id: 'u-1',
    username: 'u',
    name: 'User',
    role: role as 'operator' | 'supervisor' | 'mill_management' | 'admin',
    business_unit_id: 'bu-1',
  }

  return mount(RecordVerificationActions, {
    props: { stationType: 'cages-track', localTable: 'cages_track_record', record, ...props },
  })
}

describe('recordVerificationApi.setVerification()', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    setActivePinia(createPinia())
  })

  it('PATCHes the generic endpoint addressed by server_id, then mirrors the result locally', async () => {
    vi.mocked(apiClient.patch).mockResolvedValue({
      data: { id: 'server-1', checked_by: 'u-1', checked_by_name: 'User', acknowledged_by: null, acknowledged_by_name: null },
    })

    await setVerification('cages-track', 'cages_track_record', SYNCED_RECORD, 'checked', true)

    expect(apiClient.patch).toHaveBeenCalledWith('/records/cages-track/server-1/verification', {
      level: 'checked',
      value: true,
    })
    expect(run).toHaveBeenCalledWith('UPDATE cages_track_record SET checked_by = ? WHERE id = ?', ['u-1', 'local-1'])
  })

  it('refuses a record that has never been synced — there is no server row to verify', async () => {
    const localOnly = { id: 'local-2', server_id: null, checked_by: null, acknowledged_by: null }

    await expect(setVerification('cages-track', 'cages_track_record', localOnly, 'checked', true)).rejects.toThrow(
      /belum tersinkron/i,
    )
    expect(apiClient.patch).not.toHaveBeenCalled()
    expect(run).not.toHaveBeenCalled()
  })

  it('serverIdOf()/isNetworkError() classify the two blocking conditions', () => {
    expect(serverIdOf({ id: 'a', server_id: null })).toBeNull()
    expect(serverIdOf({ id: 'a', server_id: 's' })).toBe('s')
    // apiClient's normalizer omits `status` only when no response arrived
    expect(isNetworkError({ message: 'Tidak dapat terhubung ke server.' })).toBe(true)
    expect(isNetworkError({ message: 'Forbidden', status: 403 })).toBe(false)
  })
})

describe('RecordVerificationActions — role rule mirrors the backend', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    setActivePinia(createPinia())
  })

  it('supervisor sees only the Checked action', () => {
    const w = mountWith('supervisor')
    expect(w.find('[data-testid="toggle-checked-button"]').exists()).toBe(true)
    expect(w.find('[data-testid="toggle-acknowledged-button"]').exists()).toBe(false)
  })

  it('mill management sees only the Acknowledged action', () => {
    const w = mountWith('mill_management')
    expect(w.find('[data-testid="toggle-checked-button"]').exists()).toBe(false)
    expect(w.find('[data-testid="toggle-acknowledged-button"]').exists()).toBe(true)
  })

  it('admin sees both (product decision 2026-09-14)', () => {
    const w = mountWith('admin')
    expect(w.find('[data-testid="toggle-checked-button"]').exists()).toBe(true)
    expect(w.find('[data-testid="toggle-acknowledged-button"]').exists()).toBe(true)
  })

  it('operator sees no verification actions at all', () => {
    const w = mountWith('operator')
    expect(w.find('[data-testid="verification-actions"]').exists()).toBe(false)
  })

  it('grading passes supports-checked=false, so even an admin gets no Checked action', () => {
    const w = mountWith('admin', SYNCED_RECORD, { supportsChecked: false })
    expect(w.find('[data-testid="toggle-checked-button"]').exists()).toBe(false)
    expect(w.find('[data-testid="toggle-acknowledged-button"]').exists()).toBe(true)
  })

  it('labels flip to the un-approve wording once the record is verified', () => {
    const w = mountWith('supervisor', { ...SYNCED_RECORD, checked_by: 'someone' })
    expect(w.find('[data-testid="toggle-checked-button"]').text()).toContain('Batalkan')
  })

  it('disables the action and explains why when the record has never synced', () => {
    const w = mountWith('supervisor', { id: 'l', server_id: null, checked_by: null, acknowledged_by: null })
    expect(w.find('[data-testid="verification-not-synced"]').exists()).toBe(true)
    expect(w.find('[data-testid="toggle-checked-button"]').attributes('disabled')).toBeDefined()
  })

  it('surfaces an offline failure instead of pretending it was saved', async () => {
    vi.mocked(apiClient.patch).mockRejectedValue({ message: 'Tidak dapat terhubung ke server.' })

    const w = mountWith('supervisor')
    await w.find('[data-testid="toggle-checked-button"]').trigger('click')
    await new Promise((r) => setTimeout(r, 0))

    expect(w.find('[data-testid="verification-message"]').text()).toMatch(/butuh koneksi/i)
    expect(w.emitted('updated')).toBeUndefined()
  })

  it('emits updated so the view reloads after a successful verification', async () => {
    vi.mocked(apiClient.patch).mockResolvedValue({
      data: { id: 'server-1', checked_by: 'u-1', checked_by_name: 'User', acknowledged_by: null, acknowledged_by_name: null },
    })

    const w = mountWith('supervisor')
    await w.find('[data-testid="toggle-checked-button"]').trigger('click')
    await new Promise((r) => setTimeout(r, 0))

    expect(w.emitted('updated')).toHaveLength(1)
  })
})
