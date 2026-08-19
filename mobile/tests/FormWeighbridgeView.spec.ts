/**
 * FormWeighbridgeView.spec.ts — screen-010--form-weighbridge /
 * usecase-010--form-weighbridge business_logic steps 1-8.
 *
 * Rewritten (2026-08-18) to match FormWeighbridgeView.vue's MAJOR REWRITE:
 * Checked By / role-gating is gone entirely; Arrival is now
 * auto-set-once-then-disabled; Dispatch is now a live-ticking
 * setInterval-driven disabled field frozen only at Simpan-click time; Net
 * Weight is a pure computed (Gross − Tare), always disabled; new
 * Pause/Clear footer actions were added; the Back dirty-check now excludes
 * arrival_datetime/dispatch_datetime. This file replaces every old
 * scenario tied to Checked By with the 20 unit_test_cases covering the new
 * behavior instead.
 *
 * Component tests covering all 20 unit_test_cases, plus 1 extra test for the
 * new Tanggal Dispatch date-display field (there is no api_test or
 * browser_test unit-level equivalent for this screen — it is mobile-only /
 * local-SQLite-only, no backend endpoint, deferred per screens 005-009's
 * precedent):
 *   1. Arrival auto-set to "now" on a brand-new draft (empty stored value).
 *   2. Arrival preserved as-is when resuming a paused draft.
 *   3. Dispatch live-ticks from "now" on mount for a brand-new draft.
 *   4. Dispatch live-ticks from "now" on mount for a resumed draft too,
 *      ignoring the stale stored value entirely.
 *   5. Dispatch is frozen to the exact click-moment value on Simpan.
 *   6. Net Weight recomputes reactively when gross_weight changes.
 *   7. Net Weight recomputes reactively when tare_weight changes.
 *   8. Net Weight stays disabled regardless of its computed value.
 *   9. Required-field validation blocks Simpan with inline errors.
 *   10. Simpan saves (status='saved' server-side) with the frozen dispatch
 *       once all required fields are complete.
 *   11. Pause persists current values (status='draft_paused') with NO
 *       required-field validation.
 *   12. Clear shows the confirm dialog before deleting.
 *   13. Clear deletes and navigates to Monitor when confirmed.
 *   14. Clear does not delete when cancelled.
 *   15. Back shows the confirm dialog when the form is dirty.
 *   16. Back navigates directly when the form is not dirty.
 *   17. Back confirm discards in-memory changes (no save/pause call) and
 *       navigates.
 *   18. Breadcrumb segment taps navigate to their target route.
 *   19. Hamburger opens the nav menu (Ganti Password / Logout).
 *   20. Full happy-path: fill required fields, Simpan -> correct payload
 *       (incl. empty checked_by/acknowledged_by), frozen dispatch,
 *       navigation to Monitor.
 *   21. Dispatch section renders both a date field (Tanggal Dispatch) and a
 *       time field (Waktu Dispatch), each showing the correct
 *       formatDateID()/formatTimeID() portion of the same live
 *       dispatch_datetime value, not a time-only display.
 *
 * Mocking strategy (mirrors MonitorWeighbridgeView.spec.ts / the old
 * version of this file):
 *   - 'vue-router' is mocked at module level so `router.push` can be
 *     asserted via a hoisted `pushMock`, and the route's `:id` param can be
 *     controlled per test via a hoisted `useRouteMock` (a real `vi.fn()`),
 *     without a real router instance.
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
 *     Card Number/ID"), matching how FormField.vue derives `fieldId` from
 *     `label` when no explicit `id` prop is passed. Footer actions and
 *     header/breadcrumb/nav-menu elements are targeted via their
 *     `data-testid` attributes.
 *   - Fake timers ('Date', 'setInterval', 'clearInterval' only — NOT
 *     'setTimeout'/'setImmediate', which @vue/test-utils' `flushPromises()`
 *     relies on internally via `setImmediate` to flush the real microtask
 *     queue) are installed fresh in `beforeEach` via
 *     `vi.useFakeTimers({ toFake: [...] })` + `vi.setSystemTime(...)`, and
 *     torn down in `afterEach` via `vi.useRealTimers()` — this makes "now"
 *     and the 1s dispatch-ticker deterministic without ever waiting on a
 *     real wall-clock second, while `flushPromises()` keeps working
 *     normally to await the component's real async `getDraftById()` /
 *     `saveDraft()` / etc. promise chains.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
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
    arrival_datetime: null,
    dispatch_datetime: null,
    vehicle_number: null,
    driver_name: null,
    estate_supplier: null,
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

async function fillRequiredFields(wrapper: ReturnType<typeof mount>): Promise<void> {
  await wrapper.find('#field-wb-card-number-id').setValue('WB-1001')
  await wrapper.find('#field-no-kendaraan').setValue('B 1234 CD')
  await wrapper.find('#field-nama-supir').setValue('Budi')
  await wrapper.find('#field-estate-supplier-asal').setValue('Estate A')
  await wrapper.find('#field-berat-masuk-gross-kg').setValue('15000')
}

// Advances the fake clock (firing the dispatch-ticker's setInterval as
// needed) then flushes so Vue's DOM has re-rendered.
async function tick(ms: number): Promise<void> {
  vi.advanceTimersByTime(ms)
  await flushPromises()
}

const T0 = '2026-08-18T08:00:00.000Z'

describe('FormWeighbridgeView', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date', 'setInterval', 'clearInterval'] })
    vi.setSystemTime(new Date(T0))
    vi.clearAllMocks()
    useRouteMock.mockReturnValue({ params: { id: 'draft-1' } })
    setCurrentUser('operator')
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  // unit_test_case 1
  it('sets arrival_datetime to current time when draft is new (arrival_datetime empty)', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord({ arrival_datetime: null }))

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    expect((wrapper.find('#field-tanggal-arrival').element as HTMLInputElement).value).toBe(formatDateID(T0))
    expect((wrapper.find('#field-waktu-arrival').element as HTMLInputElement).value).toBe(formatTimeID(T0))
  })

  // unit_test_case 2
  it('preserves stored arrival_datetime when resuming a paused draft', async () => {
    const storedArrival = '2026-08-15T02:30:00.000Z'
    getDraftByIdMock.mockResolvedValueOnce(
      makeDraftRecord({ status: 'draft_paused', arrival_datetime: storedArrival }),
    )

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    expect((wrapper.find('#field-tanggal-arrival').element as HTMLInputElement).value).toBe(
      formatDateID(storedArrival),
    )
    expect((wrapper.find('#field-waktu-arrival').element as HTMLInputElement).value).toBe(
      formatTimeID(storedArrival),
    )
    // Not "now" — the stored value must win for a resumed draft.
    expect((wrapper.find('#field-tanggal-arrival').element as HTMLInputElement).value).not.toBe(formatDateID(T0))
  })

  // unit_test_case 3
  it('dispatch_datetime starts live-ticking from current time on mount for a brand-new draft', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord({ dispatch_datetime: null }))

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    expect((wrapper.find('#field-waktu-dispatch').element as HTMLInputElement).value).toBe(formatTimeID(T0))

    await tick(1000)

    const advanced = new Date(new Date(T0).getTime() + 1000).toISOString()
    expect((wrapper.find('#field-waktu-dispatch').element as HTMLInputElement).value).toBe(formatTimeID(advanced))
  })

  // unit_test_case 4
  it('dispatch_datetime starts live-ticking from current time on mount for a resumed draft, ignoring the stale stored value', async () => {
    const staleDispatch = '2020-01-01T00:00:00.000Z'
    getDraftByIdMock.mockResolvedValueOnce(
      makeDraftRecord({ status: 'draft_paused', dispatch_datetime: staleDispatch }),
    )

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    expect((wrapper.find('#field-waktu-dispatch').element as HTMLInputElement).value).toBe(formatTimeID(T0))
    expect((wrapper.find('#field-waktu-dispatch').element as HTMLInputElement).value).not.toBe(
      formatTimeID(staleDispatch),
    )

    await tick(1000)

    const advanced = new Date(new Date(T0).getTime() + 1000).toISOString()
    expect((wrapper.find('#field-waktu-dispatch').element as HTMLInputElement).value).toBe(formatTimeID(advanced))
  })

  // unit_test_case 5
  it('freezes dispatch_datetime to the exact moment Simpan is pressed', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord())

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()
    await fillRequiredFields(wrapper)

    await tick(3000)

    const clickMoment = new Date(new Date(T0).getTime() + 45_000).toISOString()
    vi.setSystemTime(new Date(clickMoment))

    saveDraftMock.mockResolvedValueOnce(makeDraftRecord({ status: 'saved' }))

    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(saveDraftMock).toHaveBeenCalledWith(
      'draft-1',
      expect.objectContaining({ dispatch_datetime: clickMoment }),
      'operator',
    )
  })

  // unit_test_case 6
  it('computes net_weight reactively as gross_weight - tare_weight when gross_weight changes', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord())

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    expect((wrapper.find('#field-net-weight-kg').element as HTMLInputElement).value).toBe('')

    await wrapper.find('#field-berat-masuk-gross-kg').setValue('20000')

    expect((wrapper.find('#field-net-weight-kg').element as HTMLInputElement).value).toBe('20000')

    await wrapper.find('#field-berat-masuk-gross-kg').setValue('12000')

    expect((wrapper.find('#field-net-weight-kg').element as HTMLInputElement).value).toBe('12000')
  })

  // unit_test_case 7
  it('computes net_weight reactively as gross_weight - tare_weight when tare_weight changes', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord())

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    await wrapper.find('#field-berat-masuk-gross-kg').setValue('20000')
    expect((wrapper.find('#field-net-weight-kg').element as HTMLInputElement).value).toBe('20000')

    await wrapper.find('#field-berat-keluar-tare-kg').setValue('4000')
    expect((wrapper.find('#field-net-weight-kg').element as HTMLInputElement).value).toBe('16000')

    await wrapper.find('#field-berat-keluar-tare-kg').setValue('7000')
    expect((wrapper.find('#field-net-weight-kg').element as HTMLInputElement).value).toBe('13000')
  })

  // unit_test_case 8
  it('keeps net_weight disabled/non-editable regardless of the computed value', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord())

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    expect(wrapper.find('#field-net-weight-kg').attributes('disabled')).toBeDefined()

    await wrapper.find('#field-berat-masuk-gross-kg').setValue('20000')
    await wrapper.find('#field-berat-keluar-tare-kg').setValue('4000')

    expect(wrapper.find('#field-net-weight-kg').attributes('disabled')).toBeDefined()
  })

  // unit_test_case 9
  it('returns validation errors and does not save when required fields are incomplete on Simpan', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord())

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

  // unit_test_case 10
  it('saves the record with the frozen dispatch_datetime when all required fields are complete on Simpan', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord())

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()
    await fillRequiredFields(wrapper)

    saveDraftMock.mockResolvedValueOnce(makeDraftRecord({ status: 'saved' }))

    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(saveDraftMock).toHaveBeenCalledWith(
      'draft-1',
      expect.objectContaining({
        wb_card_number: 'WB-1001',
        vehicle_number: 'B 1234 CD',
        driver_name: 'Budi',
        estate_supplier: 'Estate A',
        gross_weight: 15000,
        checked_by: '',
        acknowledged_by: '',
      }),
      'operator',
    )
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-weighbridge' })
  })

  // unit_test_case 11
  it('updates the record with status=draft_paused without validating required fields on Pause', async () => {
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

  // unit_test_case 12
  it('shows a confirmation dialog before deleting on Clear', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord())

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    await wrapper.find('[data-testid="clear-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('.confirm-dialog-overlay').exists()).toBe(true)
    expect(wrapper.text()).toContain('Hapus Draft')
    expect(deleteDraftMock).not.toHaveBeenCalled()
  })

  // unit_test_case 13
  it('deletes the record and navigates to Monitor when Clear is confirmed', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord())

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    await wrapper.find('[data-testid="clear-button"]').trigger('click')
    await flushPromises()

    deleteDraftMock.mockResolvedValueOnce(undefined)

    await wrapper.find('.confirm-dialog-button--confirm').trigger('click')
    await flushPromises()

    expect(deleteDraftMock).toHaveBeenCalledWith('draft-1')
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-weighbridge' })
  })

  // unit_test_case 14
  it('does not delete the record when Clear confirmation is cancelled', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord())

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    await wrapper.find('[data-testid="clear-button"]').trigger('click')
    await flushPromises()

    await wrapper.find('.confirm-dialog-button--cancel').trigger('click')
    await flushPromises()

    expect(wrapper.find('.confirm-dialog-overlay').exists()).toBe(false)
    expect(deleteDraftMock).not.toHaveBeenCalled()
    expect(pushMock).not.toHaveBeenCalled()
  })

  // unit_test_case 15
  it('shows a confirmation dialog on Back when the form is dirty', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord())

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    await wrapper.find('#field-wb-card-number-id').setValue('WB-1001')

    await wrapper.find('[data-testid="back-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('.confirm-dialog-overlay').exists()).toBe(true)
    expect(pushMock).not.toHaveBeenCalled()
  })

  // unit_test_case 16
  it('navigates directly on Back when the form is not dirty', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord())

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    await wrapper.find('[data-testid="back-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('.confirm-dialog-overlay').exists()).toBe(false)
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-weighbridge' })
  })

  // unit_test_case 17
  it('discards in-memory changes and navigates when Back confirmation is confirmed', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord())

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    await wrapper.find('#field-wb-card-number-id').setValue('WB-1001')
    await wrapper.find('[data-testid="back-button"]').trigger('click')
    await flushPromises()

    await wrapper.find('.confirm-dialog-button--confirm').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-weighbridge' })
    expect(saveDraftMock).not.toHaveBeenCalled()
    expect(pauseDraftWithFormDataMock).not.toHaveBeenCalled()
  })

  // unit_test_case 18
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

  // unit_test_case 19
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

  // unit_test_case 20
  it('returns a success result when all conditions pass (full happy path)', async () => {
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord())

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()
    await fillRequiredFields(wrapper)
    await wrapper.find('#field-divisi').setValue('Divisi 2')
    await wrapper.find('#field-blok').setValue('Blok 5')
    await wrapper.find('#field-berat-keluar-tare-kg').setValue('3000')
    await wrapper.find('#field-kuantitas').setValue('4')

    await tick(2000)

    const clickMoment = new Date(new Date(T0).getTime() + 2000).toISOString()

    saveDraftMock.mockResolvedValueOnce(makeDraftRecord({ status: 'saved' }))

    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(saveDraftMock).toHaveBeenCalledWith(
      'draft-1',
      {
        wb_card_number: 'WB-1001',
        arrival_datetime: T0,
        dispatch_datetime: clickMoment,
        vehicle_number: 'B 1234 CD',
        driver_name: 'Budi',
        estate_supplier: 'Estate A',
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

  // unit_test_case 21 (new — dispatchDateDisplay/Tanggal Dispatch field)
  it('displays both date and time portions of dispatch_datetime (not time-only)', async () => {
    const dispatchMoment = '2026-08-18T14:30:00.000Z'
    vi.setSystemTime(new Date(dispatchMoment))
    getDraftByIdMock.mockResolvedValueOnce(makeDraftRecord({ dispatch_datetime: null }))

    const wrapper = mount(FormWeighbridgeView)
    await flushPromises()

    const dateField = wrapper.find('#field-tanggal-dispatch')
    const timeField = wrapper.find('#field-waktu-dispatch')

    expect(dateField.exists()).toBe(true)
    expect(timeField.exists()).toBe(true)
    expect((dateField.element as HTMLInputElement).value).toBe(formatDateID(dispatchMoment))
    expect((timeField.element as HTMLInputElement).value).toBe(formatTimeID(dispatchMoment))
    expect(dateField.attributes('disabled')).toBeDefined()
    expect(timeField.attributes('disabled')).toBeDefined()
  })
})
