/**
 * FormWeighbridgeView.spec.ts — screen-010--form-weighbridge /
 * usecase-010--form-weighbridge business_logic steps 1-8.
 *
 * SCHEMA REVISION (2026-08-19, entity-catalog v5 / tech spec v6) — rewritten
 * to match FormWeighbridgeView.vue's schema-revision update:
 * `arrival_datetime`/`dispatch_datetime` (and the old live-ticking
 * `setInterval`-driven Dispatch field + its onSimpan-time "freeze" hack) no
 * longer exist. Both are replaced by a single `record_datetime` column, and
 * a new `weighbridge_type` ('receive' | 'dispatch') two-tab selector
 * decides what it means (Arrival vs Dispatch) — `record_datetime` is
 * auto-set-once IDENTICALLY for both types (set from `new Date()` only when
 * currently empty; a stored value is otherwise never overwritten; there is
 * NO live ticking for either type any more). Switching the type tab
 * discards `record_datetime`/`destination` and immediately re-applies the
 * auto-set-once rule for the newly selected type. A new `destination`
 * field ("Tujuan Muatan") renders/is required only when
 * `weighbridge_type === 'dispatch'`. Quantity's label gains a unit:
 * "Kuantitas (tandan)". The Back dirty-check now also tracks `destination`
 * (real user-editable, dispatch-only) in addition to the previously-tracked
 * fields; `record_datetime`/`weighbridge_type` stay excluded (automatic /
 * re-derived on switch, never their own dirty signal).
 *
 * 20 unit_test_cases below map 1:1 to this screen's authoritative
 * unit_test_cases list (there is no api_test/browser_test unit-level
 * equivalent for this screen — it is mobile-only / local-SQLite-only, no
 * backend endpoint), plus a second describe block covering this screen's
 * component_test scenarios (scenario_ref-named, per Phase 2 bdd_scenarios)
 * for traceability, even where they overlap in substance with a
 * unit_test_case above:
 *   1. Defaults weighbridge_type to 'receive' when loading a new draft
 *      (weighbridge_type absent/null).
 *   2. Preserves stored weighbridge_type when loading a continued draft.
 *   3. Auto-sets record_datetime to current time when type=receive and the
 *      stored value is empty.
 *   4. Preserves stored record_datetime when type=receive and a value is
 *      already set.
 *   5. Auto-sets record_datetime to current time when type=dispatch and the
 *      stored value is empty (same rule as receive — no live ticking).
 *   6. Preserves stored record_datetime when type=dispatch and a value is
 *      already set (resumed draft).
 *   7. Discards record_datetime and destination and reapplies
 *      auto-set-once logic when switching tab receive -> dispatch.
 *   8. Discards record_datetime and destination and reapplies
 *      auto-set-once logic when switching tab dispatch -> receive.
 *   9. Computes Net Weight reactively as Gross Weight minus Tare Weight.
 *   10. Recomputes Net Weight when Gross Weight or Tare Weight changes.
 *   11. Validation error when a required field is empty on Simpan
 *       (type=receive).
 *   12. Validation error when destination is empty on Simpan
 *       (type=dispatch).
 *   13. Saves record status=saved on Simpan with type=dispatch, all
 *       required fields valid, record_datetime unchanged (not modified at
 *       save time — the old "freeze dispatch at click moment" behavior is
 *       gone).
 *   14. Saves record status=saved on Simpan with type=receive, all required
 *       fields valid, record_datetime unchanged.
 *   15. Saves draft status=draft_paused without validating required fields
 *       on Pause.
 *   16. Deletes record and navigates to Monitor when Clear confirmed.
 *   17. No changes when Clear dialog canceled.
 *   18. Shows confirmation dialog and discards in-memory changes on Back
 *       when dirty and confirmed.
 *   19. Navigates directly on Back when not dirty.
 *   20. Full happy path returns success, type=dispatch.
 *
 * Mocking strategy (mirrors MonitorWeighbridgeView.spec.ts / the previous
 * version of this file):
 *   - 'vue-router' is mocked at module level so `router.push` can be
 *     asserted via a hoisted `pushMock`, and the route's `:id` param can be
 *     controlled per test via a hoisted `useRouteMock`, without a real
 *     router instance.
 *   - '@/stores/auth' is mocked at module level via a hoisted
 *     `useAuthStoreMock` so this file never pulls in the real Pinia auth
 *     store / apiClient / tokenStorage chain.
 *   - '@/services/weighbridgeRecordRepo' is mocked at module level (its own
 *     behavior is already covered by weighbridgeRecordRepo.spec.ts) so this
 *     file never touches localDb.ts / a real SQLite connection — the view
 *     is tested against the repo's public (default-export) interface only:
 *     getDraftById, saveDraft, pauseDraftWithFormData, deleteDraft.
 *   - FormField.vue and ConfirmDialog.vue are NOT mocked — rendered for
 *     real so field input/validation-error rendering and the
 *     Back/Clear confirm-cancel interactions are exercised end-to-end
 *     through their actual markup.
 *   - Inputs are targeted via FormField.vue's deterministic slugified
 *     `field-<label-slug>` ids (e.g. `#field-wb-card-number-id` for "WB
 *     Card Number/ID"). Since `recordDateLabel`/`recordTimeLabel` (and
 *     therefore their FormField ids) switch with `weighbridge_type`
 *     ("Tanggal/Waktu Arrival" vs "Tanggal/Waktu Dispatch"), tests that
 *     switch type re-query by the newly active label's id. Footer actions
 *     and header/breadcrumb/nav-menu elements are targeted via their
 *     `data-testid` attributes; the type tabs via
 *     `[data-testid="weighbridge-type-receive"]` /
 *     `[data-testid="weighbridge-type-dispatch"]`.
 *   - Fake timers ('Date' only — there is no more `setInterval` anywhere in
 *     this screen) are installed fresh in `beforeEach` via
 *     `vi.useFakeTimers({ toFake: ['Date'] })` + `vi.setSystemTime(...)`,
 *     and torn down in `afterEach` via `vi.useRealTimers()` — this makes
 *     "now" deterministic without ever waiting on a real wall-clock second,
 *     while `flushPromises()` keeps working normally (real `setImmediate`
 *     is never faked) to await the component's real async
 *     `getDraftById()`/`saveDraft()`/etc. promise chains.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import FormWeighbridgeView from '@/views/FormWeighbridgeView.vue'
import type { WeighbridgeRecord } from '@/services/weighbridgeRecordRepo'

const { pushMock, useRouteMock } = vi.hoisted(() => ({
  pushMock: vi.fn(),
  useRouteMock: vi.fn(),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
  useRoute: useRouteMock,
}))

const { useAuthStoreMock } = vi.hoisted(() => ({ useAuthStoreMock: vi.fn() }))

vi.mock('@/stores/auth', () => ({
  useAuthStore: useAuthStoreMock,
}))

vi.mock('@/stores/floatingClock', () => ({
  useFloatingClockStore: () => ({ enabled: false, toggle: vi.fn() }),
}))

const { getDraftByIdMock, saveDraftMock, pauseDraftWithFormDataMock, deleteDraftMock } = vi.hoisted(() => ({
  getDraftByIdMock: vi.fn(),
  saveDraftMock: vi.fn(),
  pauseDraftWithFormDataMock: vi.fn(),
  deleteDraftMock: vi.fn(),
}))

vi.mock('@/services/weighbridgeRecordRepo', () => ({
  default: {
    getDraftById: getDraftByIdMock,
    saveDraft: saveDraftMock,
    pauseDraftWithFormData: pauseDraftWithFormDataMock,
    deleteDraft: deleteDraftMock,
  },
}))

// Matches FormWeighbridgeView.vue's own formatDateID()/formatTimeID() exactly,
// so expected values are computed the same way the component computes them.
function formatDateID(iso: string): string {
  return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(iso))
}

function formatTimeID(iso: string): string {
  return new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false }).format(new Date(iso))
}

function makeDraftRecord(overrides: Partial<WeighbridgeRecord> = {}): WeighbridgeRecord {
  return {
    id: 'draft-1',
    station_id: 'station-1',
    wb_card_number: null,
    weighbridge_type: null,
    record_datetime: null,
    vehicle_number: null,
    driver_name: null,
    estate_supplier: null,
    destination: null,
    division: null,
    block: null,
    gross_weight: null,
    tare_weight: null,
    net_weight: null,
    quantity: null,
    checked_by: null,
    acknowledged_by: null,
    status: 'draft_ongoing',
    created_by: 'user-1',
    created_at: '2026-08-17T07:00:00.000Z',
    updated_at: '2026-08-17T07:00:00.000Z',
    ...overrides,
  }
}

function setCurrentUser(role: 'operator' | 'supervisor' = 'operator'): void {
  useAuthStoreMock.mockReturnValue({
    currentUser: { id: 'user-1', username: 'operator01', name: 'Operator Satu', role },
  })
}

// Fills every BASE_REQUIRED_FIELDS field (i.e. every required field common
// to both receive and dispatch); does NOT touch `destination` (dispatch-only).
async function fillBaseRequiredFields(wrapper: VueWrapper): Promise<void> {
  await wrapper.find('#field-wb-card-number-id').setValue('WB-1001')
  await wrapper.find('#field-no-kendaraan').setValue('B 1234 CD')
  await wrapper.find('#field-nama-supir').setValue('Budi')
  await wrapper.find('#field-estate-supplier-asal').setValue('Estate A')
  await wrapper.find('#field-berat-masuk-gross-kg').setValue('15000')
}

function typeTab(wrapper: VueWrapper, type: 'receive' | 'dispatch') {
  return wrapper.find(`[data-testid="weighbridge-type-${type}"]`)
}

const T0 = '2026-08-18T08:00:00.000Z'

describe('FormWeighbridgeView', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(T0))
    vi.clearAllMocks()
    useRouteMock.mockReturnValue({ params: { id: 'draft-1' } })
    setCurrentUser('operator')
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  // unit_test_case 1
  it("defaults weighbridge_type to 'receive' when loading a new draft (weighbridge_type absent)", async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord({ weighbridge_type: null }))

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    expect(typeTab(wrapper, 'receive').attributes('aria-selected')).toBe('true')
    expect(typeTab(wrapper, 'dispatch').attributes('aria-selected')).toBe('false')
    // The receive-only labels (Arrival) render, confirming the default.
    expect(wrapper.find('#field-tanggal-arrival').exists()).toBe(true)
  })

  // unit_test_case 2
  it('preserves stored weighbridge_type when loading a continued draft', async () => {
    getDraftByIdMock.mockResolvedValueOnce(
      makeDraftRecord({ status: 'draft_paused', weighbridge_type: 'dispatch' }),
    )

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    expect(typeTab(wrapper, 'dispatch').attributes('aria-selected')).toBe('true')
    expect(typeTab(wrapper, 'receive').attributes('aria-selected')).toBe('false')
    expect(wrapper.find('#field-tanggal-dispatch').exists()).toBe(true)
  })

  // unit_test_case 3
  it('auto-sets record_datetime to current time when type=receive and stored value is empty', async () => {
    getDraftByIdMock.mockResolvedValueOnce(
      makeDraftRecord({ weighbridge_type: 'receive', record_datetime: null }),
    )

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    expect((wrapper.find('#field-tanggal-arrival').element as HTMLInputElement).value).toBe(formatDateID(T0))
    expect((wrapper.find('#field-waktu-arrival').element as HTMLInputElement).value).toBe(formatTimeID(T0))
  })

  // unit_test_case 4
  it('preserves stored record_datetime when type=receive and value already set', async () => {
    const stored = '2026-08-15T02:30:00.000Z'
    getDraftByIdMock.mockResolvedValueOnce(
      makeDraftRecord({ status: 'draft_paused', weighbridge_type: 'receive', record_datetime: stored }),
    )

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    expect((wrapper.find('#field-tanggal-arrival').element as HTMLInputElement).value).toBe(formatDateID(stored))
    expect((wrapper.find('#field-waktu-arrival').element as HTMLInputElement).value).toBe(formatTimeID(stored))
    expect((wrapper.find('#field-tanggal-arrival').element as HTMLInputElement).value).not.toBe(formatDateID(T0))
  })

  // unit_test_case 5
  it('auto-sets record_datetime to current time when type=dispatch and stored value is empty (same as receive, no ticking)', async () => {
    getDraftByIdMock.mockResolvedValueOnce(
      makeDraftRecord({ weighbridge_type: 'dispatch', record_datetime: null }),
    )

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    expect((wrapper.find('#field-tanggal-dispatch').element as HTMLInputElement).value).toBe(formatDateID(T0))
    expect((wrapper.find('#field-waktu-dispatch').element as HTMLInputElement).value).toBe(formatTimeID(T0))

    // No live ticking — advancing the clock and re-flushing must NOT change
    // the already-rendered value (there is no setInterval driving it).
    vi.setSystemTime(new Date(new Date(T0).getTime() + 5000))
    await flushPromises()

    expect((wrapper.find('#field-waktu-dispatch').element as HTMLInputElement).value).toBe(formatTimeID(T0))
  })

  // unit_test_case 6
  it('preserves stored record_datetime when type=dispatch and value already set (resumed draft)', async () => {
    const stored = '2026-08-15T09:45:00.000Z'
    getDraftByIdMock.mockResolvedValueOnce(
      makeDraftRecord({ status: 'draft_paused', weighbridge_type: 'dispatch', record_datetime: stored }),
    )

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    expect((wrapper.find('#field-tanggal-dispatch').element as HTMLInputElement).value).toBe(formatDateID(stored))
    expect((wrapper.find('#field-waktu-dispatch').element as HTMLInputElement).value).toBe(formatTimeID(stored))
    expect((wrapper.find('#field-waktu-dispatch').element as HTMLInputElement).value).not.toBe(formatTimeID(T0))
  })

  // unit_test_case 7
  it('discards record_datetime and destination and reapplies auto-set-once logic when switching tab receive to dispatch', async () => {
    const storedReceive = '2026-08-10T01:00:00.000Z'
    getDraftByIdMock.mockResolvedValueOnce(
      makeDraftRecord({ weighbridge_type: 'receive', record_datetime: storedReceive, destination: null }),
    )

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    expect((wrapper.find('#field-tanggal-arrival').element as HTMLInputElement).value).toBe(
      formatDateID(storedReceive),
    )
    // No destination field while on receive.
    expect(wrapper.find('#field-tujuan-muatan').exists()).toBe(false)

    await typeTab(wrapper, 'dispatch').trigger('click')
    await flushPromises()

    // record_datetime discarded then re-auto-set to "now" (T0), not the
    // stale stored receive value.
    expect(wrapper.find('#field-tanggal-arrival').exists()).toBe(false)
    expect((wrapper.find('#field-tanggal-dispatch').element as HTMLInputElement).value).toBe(formatDateID(T0))
    expect((wrapper.find('#field-waktu-dispatch').element as HTMLInputElement).value).toBe(formatTimeID(T0))

    // destination now visible and empty (freshly discarded/re-derived).
    const destinationField = wrapper.find('#field-tujuan-muatan')
    expect(destinationField.exists()).toBe(true)
    expect((destinationField.element as HTMLInputElement).value).toBe('')
  })

  // unit_test_case 8
  it('discards record_datetime and destination and reapplies auto-set-once logic when switching tab dispatch to receive', async () => {
    const storedDispatch = '2026-08-11T03:15:00.000Z'
    getDraftByIdMock.mockResolvedValueOnce(
      makeDraftRecord({
        status: 'draft_paused',
        weighbridge_type: 'dispatch',
        record_datetime: storedDispatch,
        destination: 'PKS Awal',
      }),
    )

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    expect((wrapper.find('#field-tujuan-muatan').element as HTMLInputElement).value).toBe('PKS Awal')

    await typeTab(wrapper, 'receive').trigger('click')
    await flushPromises()

    // record_datetime discarded then re-auto-set to "now" (T0), not the
    // stale stored dispatch value.
    expect(wrapper.find('#field-tanggal-dispatch').exists()).toBe(false)
    expect((wrapper.find('#field-tanggal-arrival').element as HTMLInputElement).value).toBe(formatDateID(T0))
    expect((wrapper.find('#field-waktu-arrival').element as HTMLInputElement).value).toBe(formatTimeID(T0))

    // destination is hidden on receive.
    expect(wrapper.find('#field-tujuan-muatan').exists()).toBe(false)

    // Switching back to dispatch proves the earlier value was truly
    // discarded (not merely hidden) — it comes back empty.
    await typeTab(wrapper, 'dispatch').trigger('click')
    await flushPromises()

    expect((wrapper.find('#field-tujuan-muatan').element as HTMLInputElement).value).toBe('')
  })

  // unit_test_case 9
  it('computes Net Weight reactively as Gross Weight minus Tare Weight', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord())

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    expect((wrapper.find('#field-net-weight-kg').element as HTMLInputElement).value).toBe('')

    await wrapper.find('#field-berat-masuk-gross-kg').setValue('20000')
    expect((wrapper.find('#field-net-weight-kg').element as HTMLInputElement).value).toBe('20000')

    await wrapper.find('#field-berat-keluar-tare-kg').setValue('4000')
    expect((wrapper.find('#field-net-weight-kg').element as HTMLInputElement).value).toBe('16000')
  })

  // unit_test_case 10
  it('recomputes Net Weight when Gross Weight or Tare Weight changes', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord())

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    await wrapper.find('#field-berat-masuk-gross-kg').setValue('20000')
    await wrapper.find('#field-berat-keluar-tare-kg').setValue('4000')
    expect((wrapper.find('#field-net-weight-kg').element as HTMLInputElement).value).toBe('16000')

    await wrapper.find('#field-berat-masuk-gross-kg').setValue('12000')
    expect((wrapper.find('#field-net-weight-kg').element as HTMLInputElement).value).toBe('8000')

    await wrapper.find('#field-berat-keluar-tare-kg').setValue('7000')
    expect((wrapper.find('#field-net-weight-kg').element as HTMLInputElement).value).toBe('5000')

    // Always disabled, regardless of the computed value.
    expect(wrapper.find('#field-net-weight-kg').attributes('disabled')).toBeDefined()
  })

  // unit_test_case 11
  it('shows a validation error when a required field is empty on Simpan (type=receive)', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord({ weighbridge_type: 'receive' }))

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    // Fill everything except driver_name (Nama Supir), left empty.
    await wrapper.find('#field-wb-card-number-id').setValue('WB-1001')
    await wrapper.find('#field-no-kendaraan').setValue('B 1234 CD')
    await wrapper.find('#field-estate-supplier-asal').setValue('Estate A')
    await wrapper.find('#field-berat-masuk-gross-kg').setValue('15000')

    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    const errorEl = wrapper.find('#field-nama-supir-error')
    expect(errorEl.exists()).toBe(true)
    expect(errorEl.text()).toContain('wajib diisi')

    expect(saveDraftMock).not.toHaveBeenCalled()
    expect(pushMock).not.toHaveBeenCalled()
  })

  // unit_test_case 12
  it('shows a validation error when destination is empty on Simpan (type=dispatch)', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord({ weighbridge_type: 'dispatch' }))

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    await fillBaseRequiredFields(wrapper)
    // destination intentionally left empty.

    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    const errorEl = wrapper.find('#field-tujuan-muatan-error')
    expect(errorEl.exists()).toBe(true)
    expect(errorEl.text()).toContain('wajib diisi')

    expect(saveDraftMock).not.toHaveBeenCalled()
    expect(pushMock).not.toHaveBeenCalled()
  })

  // unit_test_case 13
  it('saves record status=saved on Simpan with type=dispatch, all required fields valid, record_datetime unchanged (not modified at save time)', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord({ weighbridge_type: 'dispatch', record_datetime: null }))

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    await fillBaseRequiredFields(wrapper)
    await wrapper.find('#field-tujuan-muatan').setValue('PKS Tujuan')

    // Advance the clock well past load time — proves Simpan no longer
    // re-derives/freezes record_datetime at click time.
    vi.setSystemTime(new Date(new Date(T0).getTime() + 60_000))

    saveDraftMock.mockResolvedValueOnce(makeDraftRecord({ status: 'saved' }))

    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(saveDraftMock).toHaveBeenCalledWith(
      'draft-1',
      expect.objectContaining({ weighbridge_type: 'dispatch', record_datetime: T0 }),
      'operator',
    )
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-weighbridge' })
  })

  // unit_test_case 14
  it('saves record status=saved on Simpan with type=receive, all required fields valid, record_datetime unchanged', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord({ weighbridge_type: 'receive', record_datetime: null }))

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    await fillBaseRequiredFields(wrapper)

    vi.setSystemTime(new Date(new Date(T0).getTime() + 60_000))

    saveDraftMock.mockResolvedValueOnce(makeDraftRecord({ status: 'saved' }))

    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(saveDraftMock).toHaveBeenCalledWith(
      'draft-1',
      expect.objectContaining({ weighbridge_type: 'receive', record_datetime: T0, destination: '' }),
      'operator',
    )
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-weighbridge' })
  })

  // unit_test_case 15
  it('saves draft status=draft_paused without validating required fields on Pause', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord())

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    // Only a non-required field is touched — every required field is left
    // empty, and Pause must still succeed with no inline errors.
    await wrapper.find('#field-divisi').setValue('Divisi 1')

    pauseDraftWithFormDataMock.mockResolvedValueOnce(makeDraftRecord({ status: 'draft_paused' }))

    await wrapper.find('[data-testid="pause-button"]').trigger('click')
    await flushPromises()

    expect(pauseDraftWithFormDataMock).toHaveBeenCalledWith(
      'draft-1',
      expect.objectContaining({ division: 'Divisi 1', wb_card_number: '', checked_by: '', acknowledged_by: '' }),
    )
    expect(wrapper.findAll('.form-field-error')).toHaveLength(0)
    expect(saveDraftMock).not.toHaveBeenCalled()
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-weighbridge' })
  })

  // unit_test_case 16
  it('deletes the record and navigates to Monitor when Clear is confirmed', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord())

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    await wrapper.find('[data-testid="clear-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('.confirm-dialog-overlay').exists()).toBe(true)

    deleteDraftMock.mockResolvedValueOnce(undefined)

    await wrapper.find('.confirm-dialog-button--confirm').trigger('click')
    await flushPromises()

    expect(deleteDraftMock).toHaveBeenCalledWith('draft-1')
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-weighbridge' })
  })

  // unit_test_case 17
  it('makes no changes when the Clear dialog is canceled', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord())

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    await wrapper.find('#field-wb-card-number-id').setValue('WB-KEEP')
    await wrapper.find('[data-testid="clear-button"]').trigger('click')
    await flushPromises()

    await wrapper.find('.confirm-dialog-button--cancel').trigger('click')
    await flushPromises()

    expect(wrapper.find('.confirm-dialog-overlay').exists()).toBe(false)
    expect(deleteDraftMock).not.toHaveBeenCalled()
    expect(pushMock).not.toHaveBeenCalled()
    expect((wrapper.find('#field-wb-card-number-id').element as HTMLInputElement).value).toBe('WB-KEEP')
  })

  // unit_test_case 18
  it('shows a confirmation dialog and discards in-memory changes on Back when dirty and confirmed', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord())

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    await wrapper.find('#field-wb-card-number-id').setValue('WB-1001')

    await wrapper.find('[data-testid="back-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('.confirm-dialog-overlay').exists()).toBe(true)
    expect(pushMock).not.toHaveBeenCalled()

    await wrapper.find('.confirm-dialog-button--confirm').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-weighbridge' })
    expect(saveDraftMock).not.toHaveBeenCalled()
    expect(pauseDraftWithFormDataMock).not.toHaveBeenCalled()
  })

  // unit_test_case 19
  it('navigates directly on Back when not dirty', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord())

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    await wrapper.find('[data-testid="back-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('.confirm-dialog-overlay').exists()).toBe(false)
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-weighbridge' })
  })

  // unit_test_case 20
  it('returns a success result on the full happy path, type=dispatch', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord({ weighbridge_type: 'dispatch', record_datetime: null }))

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    await fillBaseRequiredFields(wrapper)
    await wrapper.find('#field-tujuan-muatan').setValue('PKS Tujuan B')
    await wrapper.find('#field-divisi').setValue('Divisi 2')
    await wrapper.find('#field-blok').setValue('Blok 5')
    await wrapper.find('#field-berat-keluar-tare-kg').setValue('3000')
    await wrapper.find('#field-kuantitas-tandan').setValue('4')

    saveDraftMock.mockResolvedValueOnce(makeDraftRecord({ status: 'saved' }))

    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(saveDraftMock).toHaveBeenCalledWith(
      'draft-1',
      {
        wb_card_number: 'WB-1001',
        weighbridge_type: 'dispatch',
        record_datetime: T0,
        vehicle_number: 'B 1234 CD',
        driver_name: 'Budi',
        estate_supplier: 'Estate A',
        destination: 'PKS Tujuan B',
        division: 'Divisi 2',
        block: 'Blok 5',
        gross_weight: 15000,
        tare_weight: 3000,
        net_weight: 12000,
        quantity: 4,
        checked_by: '',
        acknowledged_by: '',
      },
      'operator',
    )
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-weighbridge' })
    expect(wrapper.findAll('.form-field-error')).toHaveLength(0)
  })

  // Non-enumerated, kept for regression coverage of navigation/nav-menu
  // chrome shared with every other mobile screen (breadcrumb / hamburger).
  it('navigates to the related route when a breadcrumb segment is tapped', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord())

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    await wrapper.find('[data-testid="breadcrumb-home"]').trigger('click')
    expect(pushMock).toHaveBeenCalledWith({ name: 'home' })

    await wrapper.find('[data-testid="breadcrumb-production-process-activity"]').trigger('click')
    expect(pushMock).toHaveBeenCalledWith({ name: 'station-list' })

    await wrapper.find('[data-testid="breadcrumb-weighbridge"]').trigger('click')
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-weighbridge' })

    const current = wrapper.get('.breadcrumb-current')
    expect(current.text()).toBe('Form')
    expect(current.attributes('aria-current')).toBe('page')
    expect(current.element.tagName).not.toBe('BUTTON')
  })

  it('opens the navigation menu with Ganti Password/Logout when the hamburger is tapped', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord())

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    expect(wrapper.find('[data-testid="nav-menu"]').exists()).toBe(false)

    await wrapper.find('[data-testid="hamburger-button"]').trigger('click')
    await flushPromises()

    const navMenu = wrapper.get('[data-testid="nav-menu"]')
    expect(navMenu.text()).toContain('Ganti Password')
    expect(navMenu.text()).toContain('Logout')

    await wrapper.find('[data-testid="nav-menu-change-password"]').trigger('click')
    expect(pushMock).toHaveBeenCalledWith({ name: 'change-password' })
  })
})

// component_test scenarios (Phase 2 bdd_scenarios, scenario_ref-named) —
// distinct assertions from the numbered unit_test_cases above, even where
// substance overlaps, for scenario-level traceability.
describe('FormWeighbridgeView — component_test scenarios', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(T0))
    vi.clearAllMocks()
    useRouteMock.mockReturnValue({ params: { id: 'draft-1' } })
    setCurrentUser('operator')
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  // scenario_ref: "success"
  it('success — fills fields incl. Destination for Dispatch, Simpan saves and navigates to Monitor', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord({ weighbridge_type: 'dispatch', record_datetime: null }))

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    // Type tab already selected per the loaded draft.
    expect(typeTab(wrapper, 'dispatch').attributes('aria-selected')).toBe('true')

    await fillBaseRequiredFields(wrapper)
    await wrapper.find('#field-tujuan-muatan').setValue('PKS Tujuan')
    await wrapper.find('#field-berat-keluar-tare-kg').setValue('5000')

    // Net Weight computed and disabled.
    expect((wrapper.find('#field-net-weight-kg').element as HTMLInputElement).value).toBe('10000')
    expect(wrapper.find('#field-net-weight-kg').attributes('disabled')).toBeDefined()

    // record_datetime unchanged by the Simpan click itself.
    vi.setSystemTime(new Date(new Date(T0).getTime() + 30_000))

    saveDraftMock.mockResolvedValueOnce(makeDraftRecord({ status: 'saved' }))

    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(saveDraftMock).toHaveBeenCalledWith(
      'draft-1',
      expect.objectContaining({ record_datetime: T0, destination: 'PKS Tujuan' }),
      'operator',
    )
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-weighbridge' })
  })

  // scenario_ref: "Ganti Tipe Setelah Field Terisi"
  it('Ganti Tipe Setelah Field Terisi — switching type clears/resets values and toggles destination visibility/required', async () => {
    getDraftByIdMock.mockResolvedValueOnce(
      makeDraftRecord({
        status: 'draft_paused',
        weighbridge_type: 'dispatch',
        record_datetime: '2026-08-12T04:00:00.000Z',
        destination: 'PKS Lama',
      }),
    )

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    expect((wrapper.find('#field-tujuan-muatan').element as HTMLInputElement).value).toBe('PKS Lama')

    vi.setSystemTime(new Date(new Date(T0).getTime() + 120_000))

    await typeTab(wrapper, 'receive').trigger('click')
    await flushPromises()

    // destination no longer rendered/required for receive.
    expect(wrapper.find('#field-tujuan-muatan').exists()).toBe(false)
    // record_datetime re-derived to the new "now", not the stale stored one.
    expect((wrapper.find('#field-waktu-arrival').element as HTMLInputElement).value).toBe(
      formatTimeID(new Date(new Date(T0).getTime() + 120_000).toISOString()),
    )

    // Simpan without filling destination now succeeds (receive doesn't
    // require it) once the other base-required fields are filled.
    await fillBaseRequiredFields(wrapper)
    saveDraftMock.mockResolvedValueOnce(makeDraftRecord({ status: 'saved' }))
    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()
    expect(saveDraftMock).toHaveBeenCalledWith(
      'draft-1',
      expect.objectContaining({ weighbridge_type: 'receive', destination: '' }),
      'operator',
    )
  })

  // scenario_ref: "Field Wajib Belum Lengkap"
  it('Field Wajib Belum Lengkap — leaves a required field empty, Simpan shows inline error and does not call the save handler', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord({ weighbridge_type: 'dispatch' }))

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    // Fill everything except Destination (dispatch-only required field).
    await fillBaseRequiredFields(wrapper)

    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('#field-tujuan-muatan-error').exists()).toBe(true)
    expect(saveDraftMock).not.toHaveBeenCalled()
    expect(pushMock).not.toHaveBeenCalled()
  })

  // scenario_ref: "Lanjutkan Draft Paused"
  it('Lanjutkan Draft Paused — mounts with an existing draft_paused record, fields populate from the saved draft with no reset/re-tick for either type', async () => {
    const storedReceive = '2026-08-05T06:00:00.000Z'
    getDraftByIdMock.mockResolvedValueOnce(
      makeDraftRecord({
        status: 'draft_paused',
        weighbridge_type: 'receive',
        record_datetime: storedReceive,
        wb_card_number: 'WB-PAUSED-01',
        vehicle_number: 'B 9999 ZZ',
        driver_name: 'Budi',
        estate_supplier: 'Estate A',
        division: 'Divisi 1',
        block: 'Blok 3',
        gross_weight: 15000,
        tare_weight: 5000,
        quantity: 1,
      }),
    )

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    expect(typeTab(wrapper, 'receive').attributes('aria-selected')).toBe('true')
    expect((wrapper.find('#field-wb-card-number-id').element as HTMLInputElement).value).toBe('WB-PAUSED-01')
    expect((wrapper.find('#field-tanggal-arrival').element as HTMLInputElement).value).toBe(
      formatDateID(storedReceive),
    )
    expect((wrapper.find('#field-waktu-arrival').element as HTMLInputElement).value).not.toBe(formatTimeID(T0))
    expect((wrapper.find('#field-divisi').element as HTMLInputElement).value).toBe('Divisi 1')
    expect((wrapper.find('#field-berat-masuk-gross-kg').element as HTMLInputElement).value).toBe('15000')
  })

  // scenario_ref: "Pause Progress"
  it('Pause Progress — fills partially (required fields empty), Pause invokes checkpoint save with no validation errors and navigates', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord({ weighbridge_type: 'dispatch' }))

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    await wrapper.find('#field-wb-card-number-id').setValue('WB-PAUSE-01')
    await wrapper.find('#field-no-kendaraan').setValue('B 9999 ZZ')
    // Every other required field (incl. destination) intentionally left
    // empty — Pause must still succeed.

    pauseDraftWithFormDataMock.mockResolvedValueOnce(makeDraftRecord({ status: 'draft_paused' }))

    await wrapper.find('[data-testid="pause-button"]').trigger('click')
    await flushPromises()

    expect(pauseDraftWithFormDataMock).toHaveBeenCalledWith(
      'draft-1',
      expect.objectContaining({ wb_card_number: 'WB-PAUSE-01', vehicle_number: 'B 9999 ZZ', destination: '' }),
    )
    expect(wrapper.findAll('.form-field-error')).toHaveLength(0)
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-weighbridge' })
  })

  // scenario_ref: "Clear Draft"
  it('Clear Draft — confirm deletes and navigates; cancel makes no change and leaves the form intact', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord())

    const confirmWrapper = mount(FormWeighbridgeView)
    await flushPromises()
    await confirmWrapper.find('[data-testid="clear-button"]').trigger('click')
    await flushPromises()
    deleteDraftMock.mockResolvedValueOnce(undefined)
    await confirmWrapper.find('.confirm-dialog-button--confirm').trigger('click')
    await flushPromises()
    expect(deleteDraftMock).toHaveBeenCalledWith('draft-1')
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-weighbridge' })

    vi.clearAllMocks()
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord())
    const cancelWrapper = mount(FormWeighbridgeView)
    await flushPromises()
    await cancelWrapper.find('#field-wb-card-number-id').setValue('WB-KEEP')
    await cancelWrapper.find('[data-testid="clear-button"]').trigger('click')
    await flushPromises()
    await cancelWrapper.find('.confirm-dialog-button--cancel').trigger('click')
    await flushPromises()
    expect(deleteDraftMock).not.toHaveBeenCalled()
    expect(pushMock).not.toHaveBeenCalled()
    expect((cancelWrapper.find('#field-wb-card-number-id').element as HTMLInputElement).value).toBe('WB-KEEP')
  })
})
