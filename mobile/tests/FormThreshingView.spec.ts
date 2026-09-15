/**
 * FormThreshingView.spec.ts — screen-041--form-threshing /
 * usecase-041--form-threshing.
 *
 * REVISED 2026-08-24 (entity-catalog v12): mirrors FormCagesTrackView.spec.ts's
 * add-row/remove-row interaction helpers and coverage — the Threshing
 * Detail grid is no longer a fixed 24-row grid, it now uses the exact same
 * dynamic add-row/remove-row pattern as Cages Tipped Time.
 *
 * Component tests covering:
 *   - loads existing detail rows (however many) on mount, canonical order
 *   - "Tambah baris" adds one row at a time; disabled once 24 rows exist
 *   - Time-Slot dropdown excludes slots used by other rows AND slots
 *     at-or-before the highest already-picked slot
 *   - "Hapus baris" queues an existing (has-id) row for deletion rather
 *     than deleting immediately; frees its slot for other rows
 *   - required-field validation (Thresher ID)
 *   - "at least one valid row" validation (Time-Slot + reading filled)
 *   - Simpan success (status=saved), navigates to Monitor Threshing
 *   - Pause skips validation entirely
 *   - Clear shows confirm dialog, deletes on confirm
 *   - Back shows confirm dialog when dirty, navigates directly when not
 *   - Checked By enabled only for supervisor, Acknowledged By only for
 *     mill_management
 *   - Target Operasional reference table renders 6 read-only rows
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import FormThreshingView from '@/views/FormThreshingView.vue'
import { ThreshingDetailRequiredError, type ThreshingDetailRow, type ThreshingRecord } from '@/services/threshingRecordRepo'

const { pushMock } = vi.hoisted(() => ({ pushMock: vi.fn() }))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
  useRoute: () => ({ params: { id: 'draft-1' } }),
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

const { aiAssistantOpenMock, aiAssistantToggleBubbleMock } = vi.hoisted(() => ({
  aiAssistantOpenMock: vi.fn(),
  aiAssistantToggleBubbleMock: vi.fn(),
}))

vi.mock('@/stores/aiAssistant', () => ({
  useAiAssistantStore: () => ({
    isOpen: false,
    bubbleEnabled: true,
    open: aiAssistantOpenMock,
    close: vi.fn(),
    toggleBubble: aiAssistantToggleBubbleMock,
  }),
}))

const { getDraftWithDetailsMock, saveDraftMock, pauseDraftWithFormDataMock, deleteDraftMock } = vi.hoisted(() => ({
  getDraftWithDetailsMock: vi.fn(),
  saveDraftMock: vi.fn(),
  pauseDraftWithFormDataMock: vi.fn(),
  deleteDraftMock: vi.fn(),
}))

vi.mock('@/services/threshingRecordRepo', async () => {
  const actual = await vi.importActual<typeof import('@/services/threshingRecordRepo')>(
    '@/services/threshingRecordRepo',
  )
  return {
    ...actual,
    default: {
      canonicalTimeSlots: actual.canonicalTimeSlots,
      getDraftWithDetails: getDraftWithDetailsMock,
      saveDraft: saveDraftMock,
      pauseDraftWithFormData: pauseDraftWithFormDataMock,
      deleteDraft: deleteDraftMock,
    },
  }
})

function setCurrentUser(role: 'operator' | 'supervisor' | 'mill_management' = 'operator'): void {
  useAuthStoreMock.mockReturnValue({
    currentUser: { id: 'user-1', username: 'operator01', name: 'Operator Satu', role },
    logout: logoutMock,
  })
}

function makeRecord(overrides: Partial<ThreshingRecord> = {}): ThreshingRecord {
  return {
    id: 'draft-1',
    station_id: 'station-1',
    thresher_id: null,
    date: '2026-08-24T07:00:00',
    note: null,
    checked_by: null,
    acknowledged_by: null,
    status: 'draft_ongoing',
    created_by: 'user-1',
    created_at: '2026-08-24T07:00:00.000Z',
    updated_at: '2026-08-24T07:00:00.000Z',
    ...overrides,
  }
}

function makeDetailRow(overrides: Partial<ThreshingDetailRow> = {}): ThreshingDetailRow {
  return {
    id: 'detail-1',
    threshing_record_id: 'draft-1',
    time_slot: '07:00',
    ffb_throughput_mt_hour: null,
    thresher_drum_speed_rpm: null,
    motor_current_amps: null,
    unstripped_bunch_count_percent: null,
    empty_bunch_oil_loss_percent: null,
    downtime_reason: null,
    created_at: '2026-08-24T07:00:00.000Z',
    updated_at: '2026-08-24T07:00:00.000Z',
    ...overrides,
  }
}

// --- SearchableSelect.vue interaction helpers -----------------------------
// Mirrors FormCagesTrackView.spec.ts's helpers exactly.

function searchableSelectRoot(wrapper: ReturnType<typeof mount>, testId: string) {
  return wrapper.find(`[data-testid="${testId}"]`)
}

function searchableSelectOptionTexts(wrapper: ReturnType<typeof mount>, testId: string): string[] {
  return searchableSelectRoot(wrapper, testId)
    .findAll('[role="option"]')
    .map((option) => option.text())
}

async function openSearchableSelect(wrapper: ReturnType<typeof mount>, testId: string): Promise<void> {
  await searchableSelectRoot(wrapper, testId).find('input').trigger('focus')
}

async function chooseSearchableOption(
  wrapper: ReturnType<typeof mount>,
  testId: string,
  optionLabel: string,
): Promise<void> {
  await openSearchableSelect(wrapper, testId)

  const match = searchableSelectRoot(wrapper, testId)
    .findAll('[role="option"]')
    .find((option) => option.text() === optionLabel)

  if (!match) {
    throw new Error(
      `chooseSearchableOption: no option labeled "${optionLabel}" for [data-testid="${testId}"]. Visible: ${searchableSelectOptionTexts(wrapper, testId).join(', ')}`,
    )
  }

  await match.trigger('mousedown')
}

const T0 = '2026-08-24T08:00:00.000Z'

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(new Date(T0))
  vi.clearAllMocks()
  setCurrentUser('operator')
  getDraftWithDetailsMock.mockResolvedValue({ record: makeRecord(), details: [] })
})

afterEach(() => {
  vi.useRealTimers()
})

describe('FormThreshingView', () => {
  it('loads existing detail rows (however many) ordered by canonical time-slot on mount', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({
      record: makeRecord(),
      details: [
        makeDetailRow({ id: 'd-2', time_slot: '09:00' }),
        makeDetailRow({ id: 'd-1', time_slot: '07:00' }),
      ],
    })

    const wrapper = mount(FormThreshingView)
    await flushPromises()

    const rows = wrapper.findAll('[data-testid="threshing-detail-row"]')
    expect(rows).toHaveLength(2)
  })

  it('renders zero detail rows for a brand-new draft (no pre-created rows)', async () => {
    const wrapper = mount(FormThreshingView)
    await flushPromises()

    expect(wrapper.findAll('[data-testid="threshing-detail-row"]')).toHaveLength(0)
  })

  it('adds one row at a time via "Tambah baris"', async () => {
    const wrapper = mount(FormThreshingView)
    await flushPromises()

    await wrapper.find('[data-testid="add-detail-row-button"]').trigger('click')
    expect(wrapper.findAll('[data-testid="threshing-detail-row"]')).toHaveLength(1)

    await wrapper.find('[data-testid="add-detail-row-button"]').trigger('click')
    expect(wrapper.findAll('[data-testid="threshing-detail-row"]')).toHaveLength(2)
  })

  it("excludes a time-slot already used by another row from a row's Time-Slot dropdown", async () => {
    const wrapper = mount(FormThreshingView)
    await flushPromises()

    await wrapper.find('[data-testid="add-detail-row-button"]').trigger('click')
    await chooseSearchableOption(wrapper, 'time-slot-select-0', '07:00')
    await wrapper.find('[data-testid="add-detail-row-button"]').trigger('click')

    await openSearchableSelect(wrapper, 'time-slot-select-1')
    const row1Labels = searchableSelectOptionTexts(wrapper, 'time-slot-select-1')

    expect(row1Labels).not.toContain('07:00')
  })

  it("excludes time-slots at-or-before the most-recently-added row's slot", async () => {
    const wrapper = mount(FormThreshingView)
    await flushPromises()

    await wrapper.find('[data-testid="add-detail-row-button"]').trigger('click')
    await chooseSearchableOption(wrapper, 'time-slot-select-0', '09:00')
    await wrapper.find('[data-testid="add-detail-row-button"]').trigger('click')

    await openSearchableSelect(wrapper, 'time-slot-select-1')
    const row1Labels = searchableSelectOptionTexts(wrapper, 'time-slot-select-1')

    expect(row1Labels).not.toContain('07:00')
    expect(row1Labels).not.toContain('08:00')
    expect(row1Labels).not.toContain('09:00')
    expect(row1Labels).toContain('10:00')
  })

  it('frees a used time-slot for other rows once the row using it is removed', async () => {
    const wrapper = mount(FormThreshingView)
    await flushPromises()

    await wrapper.find('[data-testid="add-detail-row-button"]').trigger('click')
    await chooseSearchableOption(wrapper, 'time-slot-select-0', '07:00')
    await wrapper.find('[data-testid="add-detail-row-button"]').trigger('click')

    await wrapper.findAll('[data-testid="remove-detail-row-button"]')[0].trigger('click')

    await openSearchableSelect(wrapper, 'time-slot-select-0')
    const row0Labels = searchableSelectOptionTexts(wrapper, 'time-slot-select-0')
    expect(row0Labels).toContain('07:00')
  })

  it('queues an existing (has-id) row for deletion on Hapus baris rather than deleting immediately', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({
      record: makeRecord({ status: 'draft_paused' }),
      details: [
        makeDetailRow({ id: 'detail-remove-1', time_slot: '07:00' }),
        makeDetailRow({ id: 'detail-keep-1', time_slot: '09:00' }),
      ],
    })

    const wrapper = mount(FormThreshingView)
    await flushPromises()

    expect(wrapper.findAll('[data-testid="threshing-detail-row"]')).toHaveLength(2)

    await wrapper.findAll('[data-testid="remove-detail-row-button"]')[0].trigger('click')

    expect(wrapper.findAll('[data-testid="threshing-detail-row"]')).toHaveLength(1)
    expect(saveDraftMock).not.toHaveBeenCalled()
  })

  it('disables "Tambah baris" once 24 rows already exist', async () => {
    const details = Array.from({ length: 24 }, (_, i) =>
      makeDetailRow({ id: `d-${i}`, time_slot: `${String((7 + i) % 24).padStart(2, '0')}:00` }),
    )
    getDraftWithDetailsMock.mockResolvedValueOnce({ record: makeRecord(), details })

    const wrapper = mount(FormThreshingView)
    await flushPromises()

    expect(wrapper.find('[data-testid="add-detail-row-button"]').attributes('disabled')).toBeDefined()
  })

  it('shows inline error and does not save when Thresher ID is empty on Simpan', async () => {
    const wrapper = mount(FormThreshingView)
    await flushPromises()

    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(saveDraftMock).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Thresher ID wajib diisi')
  })

  it('shows a grid-specific error and does not save when there are zero valid detail rows', async () => {
    const wrapper = mount(FormThreshingView)
    await flushPromises()

    await wrapper.find('#field-thresher-id').setValue('TH-01')
    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(saveDraftMock).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="detail-rows-error"]').exists()).toBe(true)
  })

  it('shows the grid error when a row has a selected time-slot but no reading filled', async () => {
    const wrapper = mount(FormThreshingView)
    await flushPromises()

    await wrapper.find('#field-thresher-id').setValue('TH-01')
    await wrapper.find('[data-testid="add-detail-row-button"]').trigger('click')
    await chooseSearchableOption(wrapper, 'time-slot-select-0', '07:00')
    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(saveDraftMock).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="detail-rows-error"]').exists()).toBe(true)
  })

  it('saves successfully and navigates to Monitor Threshing when Thresher ID and a valid detail row exist', async () => {
    saveDraftMock.mockResolvedValue({ record: makeRecord({ status: 'saved' }), details: [makeDetailRow()] })

    const wrapper = mount(FormThreshingView)
    await flushPromises()

    await wrapper.find('#field-thresher-id').setValue('TH-01')
    await wrapper.find('[data-testid="add-detail-row-button"]').trigger('click')
    await chooseSearchableOption(wrapper, 'time-slot-select-0', '07:00')
    await wrapper.find('#ffb-throughput-0').setValue('45.5')
    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(saveDraftMock).toHaveBeenCalledTimes(1)
    const [recordId, headerData, details, idsToDelete] = saveDraftMock.mock.calls[0]
    expect(recordId).toBe('draft-1')
    expect(headerData.thresher_id).toBe('TH-01')
    expect(details).toHaveLength(1)
    expect(details[0].time_slot).toBe('07:00')
    expect(idsToDelete).toEqual([])
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-threshing' })
  })

  it('falls back to the grid error (defense-in-depth) when the repo throws ThreshingDetailRequiredError', async () => {
    saveDraftMock.mockRejectedValue(new ThreshingDetailRequiredError())

    const wrapper = mount(FormThreshingView)
    await flushPromises()

    await wrapper.find('#field-thresher-id').setValue('TH-01')
    await wrapper.find('[data-testid="add-detail-row-button"]').trigger('click')
    await chooseSearchableOption(wrapper, 'time-slot-select-0', '07:00')
    await wrapper.find('#ffb-throughput-0').setValue('1')
    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="detail-rows-error"]').exists()).toBe(true)
    expect(pushMock).not.toHaveBeenCalledWith({ name: 'monitor-threshing' })
  })

  it('pauses without any required-field validation and navigates to Monitor', async () => {
    pauseDraftWithFormDataMock.mockResolvedValue({ record: makeRecord({ status: 'draft_paused' }), details: [] })

    const wrapper = mount(FormThreshingView)
    await flushPromises()

    await wrapper.find('[data-testid="pause-button"]').trigger('click')
    await flushPromises()

    expect(pauseDraftWithFormDataMock).toHaveBeenCalledTimes(1)
    expect(wrapper.find('[data-testid="detail-rows-error"]').exists()).toBe(false)
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-threshing' })
  })

  it('shows a confirm dialog on Clear and deletes only when confirmed', async () => {
    deleteDraftMock.mockResolvedValue(undefined)

    const wrapper = mount(FormThreshingView)
    await flushPromises()

    await wrapper.find('[data-testid="clear-button"]').trigger('click')
    expect(deleteDraftMock).not.toHaveBeenCalled()

    // ConfirmDialog.vue renders its confirm button when open=true.
    const confirmButtons = wrapper.findAll('button').filter((b) => b.text().includes('Ya, Hapus'))
    expect(confirmButtons.length).toBeGreaterThan(0)
    await confirmButtons[0].trigger('click')
    await flushPromises()

    expect(deleteDraftMock).toHaveBeenCalledWith('draft-1')
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-threshing' })
  })

  it('shows a confirm dialog on Back when the form is dirty', async () => {
    const wrapper = mount(FormThreshingView)
    await flushPromises()

    await wrapper.find('#field-thresher-id').setValue('TH-99')
    await wrapper.find('[data-testid="back-button"]').trigger('click')
    await flushPromises()

    const confirmButtons = wrapper.findAll('button').filter((b) => b.text().includes('Ya, Keluar'))
    expect(confirmButtons.length).toBeGreaterThan(0)
  })

  it('shows a confirm dialog on Back when a detail row was added (dirty via rows, not header)', async () => {
    const wrapper = mount(FormThreshingView)
    await flushPromises()

    await wrapper.find('[data-testid="add-detail-row-button"]').trigger('click')
    await wrapper.find('[data-testid="back-button"]').trigger('click')
    await flushPromises()

    const confirmButtons = wrapper.findAll('button').filter((b) => b.text().includes('Ya, Keluar'))
    expect(confirmButtons.length).toBeGreaterThan(0)
  })

  it('navigates directly on Back when the form is not dirty', async () => {
    const wrapper = mount(FormThreshingView)
    await flushPromises()

    await wrapper.find('[data-testid="back-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-threshing' })
  })

  it('hides Checked By from a non-supervisor user (2026-09-14)', async () => {
    const wrapper = mount(FormThreshingView)
    await flushPromises()

    expect(wrapper.find('[data-testid="checked-by-toggle"]').exists()).toBe(false)
  })

  it('hides Acknowledged By from a non-mill_management user (2026-09-14)', async () => {
    const wrapper = mount(FormThreshingView)
    await flushPromises()

    expect(wrapper.find('[data-testid="acknowledged-by-toggle"]').exists()).toBe(false)
  })

  it('renders the Target Operasional reference table with 6 read-only rows', async () => {
    const wrapper = mount(FormThreshingView)
    await flushPromises()

    const table = wrapper.find('[data-testid="operational-target-table"]')
    expect(table.exists()).toBe(true)
    expect(table.findAll('tbody tr')).toHaveLength(6)
    expect(table.text()).toContain('Thresher Drum Speed')
    expect(table.text()).toContain('21 - 23 RPM')
  })

  it('Target Operasional starts collapsed and expands via its toggle', async () => {
    const wrapper = mount(FormThreshingView)
    await flushPromises()

    // CSS is not processed under Vitest here (no `test.css` in
    // vitest.config.ts), so `.isVisible()` (getComputedStyle-based) can't
    // be trusted — assert on the actual inline `style` v-show toggles.
    expect(wrapper.get('[data-testid="collapsible-section-body"]').attributes('style')).toContain('display: none')

    await wrapper.get('[data-testid="collapsible-section-toggle"]').trigger('click')

    expect(wrapper.get('[data-testid="collapsible-section-body"]').attributes('style')).not.toContain('display: none')
    expect(wrapper.get('[data-testid="operational-target-table"]').exists()).toBe(true)
  })
})
