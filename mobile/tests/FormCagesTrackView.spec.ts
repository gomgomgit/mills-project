/**
 * FormCagesTrackView.spec.ts — screen-012--form-cages-track /
 * usecase-012--form-cages-track (entity-catalog v3 full rewrite,
 * 2026-08-19; updated 2026-08-20 for tech-spec v3's business_logic step 5
 * change — see below).
 *
 * Component tests covering:
 *   1. Date/Tippler Start Time auto-set once for a new draft, preserved
 *      for a resumed one.
 *   2. UPDATED 2026-08-20: "Tambah baris" (`canAddTippedTimeRow`) is
 *      disabled until the local mill_setting cache has a synced,
 *      positive `jumlah_cages` for the current session's business unit
 *      (loaded once on mount via millSettingRepo.getJumlahCages()) — no
 *      longer driven by the header's Cages Tipped field. Cages Tipped
 *      itself is now a fully plain manual input, never locked/disabled,
 *      with zero coupling to the grid (the old `cagesTippedLocked`
 *      computed was removed entirely).
 *   3. UPDATED 2026-08-20: Cage checklist renders N checkboxes matching
 *      mill_setting.jumlah_cages, independent of whatever value is typed
 *      into the Cages Tipped header field.
 *   4. Time dropdown excludes hours used by other rows AND hours ≤ the
 *      most-recently-added row's hour.
 *   5. UPDATED 2026-08-20: Checking/unchecking a cage recomputes Total
 *      Cages/Cages Remain for that row only; Cages Remain is
 *      mill_setting.jumlah_cages MINUS that row's own Total Cages —
 *      neither is affected by the Cages Tipped header value.
 *   6. Removing a row frees its hour for other rows.
 *   7. Checked By toggle interactive only for supervisor.
 *   8. Acknowledged By toggle interactive only for mill_management.
 *   9. Simpan validation (missing required field, no valid detail rows)
 *      blocks save; success freezes tippler_stop_time and navigates.
 *   10. Pause skips validation and does not freeze tippler_stop_time.
 *   11. Clear confirm/cancel.
 *   12. Back dirty/clean.
 *   13. Breadcrumb + hamburger.
 *
 * Mocking strategy mirrors FormGradingView.spec.ts: 'vue-router' and
 * '@/stores/auth' mocked at module level; '@/services/cagesTrackRecordRepo'
 * mocked at module level (its own behavior already covered by
 * cagesTrackRecordRepo.spec.ts) so this file never touches localDb.ts / a
 * real SQLite connection. ADDED 2026-08-20: '@/services/millSettingRepo'
 * is now also mocked at module level (mirrors HomeView.spec.ts's own
 * `getMillSettingMock` pattern) — `getJumlahCagesMock` defaults to
 * resolving `10` in the outer `beforeEach` (matching entity-catalog's
 * `mill-setting` test_fixture, ms-001.jumlah_cages) so every pre-existing
 * test that adds a detail row keeps working unchanged; individual tests
 * override via `mockResolvedValueOnce()` where the mill_setting value
 * itself is what's under test.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import FormCagesTrackView from '@/views/FormCagesTrackView.vue'
import type { CagesTippedTimeRow, CagesTrackRecord } from '@/services/cagesTrackRecordRepo'

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

const { getDraftWithTippedTimesMock, saveDraftMock, pauseDraftWithFormDataMock, deleteDraftMock } = vi.hoisted(
  () => ({
    getDraftWithTippedTimesMock: vi.fn(),
    saveDraftMock: vi.fn(),
    pauseDraftWithFormDataMock: vi.fn(),
    deleteDraftMock: vi.fn(),
  }),
)

vi.mock('@/services/cagesTrackRecordRepo', () => {
  class CagesTippedTimeRequiredError extends Error {
    constructor() {
      super('Minimal 1 baris cages tipped time harus diisi sebelum menyimpan.')
      this.name = 'CagesTippedTimeRequiredError'
    }
  }

  return {
    default: {
      getDraftWithTippedTimes: getDraftWithTippedTimesMock,
      saveDraft: saveDraftMock,
      pauseDraftWithFormData: pauseDraftWithFormDataMock,
      deleteDraft: deleteDraftMock,
    },
    CagesTippedTimeRequiredError,
  }
})

import { CagesTippedTimeRequiredError } from '@/services/cagesTrackRecordRepo'

// business_logic step 5 (tech-spec v3) — N (the grid's shared "Cage
// 1".."Cage N" checkbox column count) now comes from the local
// mill_setting reference cache, not the Cages Tipped header field. Mocked
// at module level the same way HomeView.spec.ts mocks this repo's
// `getMillSetting`.
const { getJumlahCagesMock } = vi.hoisted(() => ({ getJumlahCagesMock: vi.fn() }))

vi.mock('@/services/millSettingRepo', () => ({
  getJumlahCages: getJumlahCagesMock,
}))

// Matches entity-catalog's `mill-setting` test_fixture (ms-001.jumlah_cages).
const DEFAULT_JUMLAH_CAGES = 10

function makeDraftRecord(overrides: Partial<CagesTrackRecord> = {}): CagesTrackRecord {
  return {
    id: 'draft-1',
    station_id: 'station-1',
    cages_track_number: null,
    date: null,
    tippler_start_time: null,
    tippler_stop_time: null,
    cages_out: null,
    cages_tipped: null,
    note: null,
    checked_by: null,
    acknowledged_by: null,
    status: 'draft_ongoing',
    created_by: 'user-1',
    created_at: '2026-08-17T07:00:00.000Z',
    updated_at: '2026-08-17T07:00:00.000Z',
    ...overrides,
  }
}

function makeTippedTimeRow(overrides: Partial<CagesTippedTimeRow> = {}): CagesTippedTimeRow {
  return {
    id: 'tipped-1',
    cages_track_record_id: 'draft-1',
    tipped_hour: 7,
    checked_cage_numbers: '1,2,3',
    total_cages: 3,
    cages_remain: 7,
    created_at: '2026-08-17T07:00:00.000Z',
    updated_at: '2026-08-17T07:00:00.000Z',
    ...overrides,
  }
}

// business_unit_id: derived by the component from
// `authStore.currentUser?.business_unit_id` (see FormCagesTrackView.vue's
// onMounted block) to look up the local mill_setting cache — added
// 2026-08-20, mirrors HomeView.spec.ts's `mockAuthUser()`'s
// `business_unit_id: 'bu-1'`.
function setCurrentUser(role: 'operator' | 'supervisor' | 'mill_management' = 'operator'): void {
  useAuthStoreMock.mockReturnValue({
    currentUser: { id: 'user-1', username: 'operator01', name: 'Operator Satu', role, business_unit_id: 'bu-1' },
    logout: vi.fn().mockResolvedValue(undefined),
  })
}

function formatDateID(value: string): string {
  const date = new Date(value)
  if (!value || Number.isNaN(date.getTime())) return ''
  return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(date)
}

function formatTimeID(value: string): string {
  const date = new Date(value)
  if (!value || Number.isNaN(date.getTime())) return ''
  return new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false }).format(date)
}

// Matches FormCagesTrackView.vue's own nowLocalDateTimeString() exactly.
function nowLocalDateTimeString(now: Date): string {
  const yyyy = now.getFullYear()
  const mm = String(now.getMonth() + 1).padStart(2, '0')
  const dd = String(now.getDate()).padStart(2, '0')
  const hh = String(now.getHours()).padStart(2, '0')
  const mi = String(now.getMinutes()).padStart(2, '0')
  const ss = String(now.getSeconds()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}T${hh}:${mi}:${ss}`
}

async function fillRequiredHeaderFields(wrapper: ReturnType<typeof mount>): Promise<void> {
  await wrapper.find('#field-cages-track-number').setValue('CT-3001')
  await wrapper.find('#field-cages-out').setValue('12')
  await wrapper.find('#field-cages-tipped').setValue('10')
}

// Matches FormCagesTrackView.vue's own hourLabel() exactly.
function hourLabel(hour: number): string {
  return `${String(hour).padStart(2, '0')}:00`
}

// --- SearchableSelect.vue interaction helpers -----------------------------
// Faithful replacements for the old `wrapper.find('[data-testid="..."]')
// .setValue(value)` / `.findAll('[data-testid="..."] option')` patterns
// used against the Time <select> — see FormGradingView.spec.ts's header
// comment for the full rationale (mirrored here).

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

const T0 = '2026-08-19T08:00:00.000Z'

describe('FormCagesTrackView', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(T0))
    vi.clearAllMocks()
    useRouteMock.mockReturnValue({ params: { id: 'draft-1' } })
    setCurrentUser('operator')
    // Default: mill_setting already synced locally with a positive
    // jumlah_cages, matching entity-catalog's test_fixture — keeps every
    // pre-existing test below (written before N came from mill_setting)
    // working unchanged. Tests that exercise the mill_setting-driven
    // enable/disable logic itself override this via
    // `mockResolvedValueOnce()`.
    getJumlahCagesMock.mockResolvedValue(DEFAULT_JUMLAH_CAGES)
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  // 1a
  it('sets date and tippler_start_time to current local time when draft is new (both empty)', async () => {
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord({ date: null, tippler_start_time: null }), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    const expectedLocal = nowLocalDateTimeString(new Date())
    expect((wrapper.find('#field-tanggal').element as HTMLInputElement).value).toBe(
      `${formatDateID(expectedLocal)}, ${formatTimeID(expectedLocal)}`,
    )
    expect((wrapper.find('#field-tippler-start-time').element as HTMLInputElement).value).toBe(
      `${formatDateID(expectedLocal)}, ${formatTimeID(expectedLocal)}`,
    )
  })

  // 1b
  it('preserves stored date and tippler_start_time when resuming a draft that already has them', async () => {
    const stored = '2026-08-15T02:30:00'
    getDraftWithTippedTimesMock.mockResolvedValueOnce({
      record: makeDraftRecord({ status: 'draft_paused', date: stored, tippler_start_time: stored }),
      tippedTimes: [],
    })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    const expected = `${formatDateID(stored)}, ${formatTimeID(stored)}`
    expect((wrapper.find('#field-tanggal').element as HTMLInputElement).value).toBe(expected)
    expect((wrapper.find('#field-tippler-start-time').element as HTMLInputElement).value).toBe(expected)
  })

  it('shows "Akan diisi otomatis saat Simpan" for Tippler Stop Time while it is still blank', async () => {
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    expect((wrapper.find('#field-tippler-stop-time').element as HTMLInputElement).value).toBe(
      'Akan diisi otomatis saat Simpan',
    )
  })

  // 2 — Cages Tipped (header) is now a fully plain manual input at all
  // times, decoupled from the grid (2026-08-20, tech-spec v3). The old
  // "locks once a row exists" tests were removed along with the
  // `cagesTippedLocked` computed itself.
  it('keeps Cages Tipped editable at all times, even after a detail row is added', async () => {
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    expect(wrapper.find('#field-cages-tipped').attributes('disabled')).toBeUndefined()

    await wrapper.find('[data-testid="add-tipped-time-row-button"]').trigger('click')
    await wrapper.find('#field-cages-tipped').setValue('42')

    expect(wrapper.find('#field-cages-tipped').attributes('disabled')).toBeUndefined()
    expect((wrapper.find('#field-cages-tipped').element as HTMLInputElement).value).toBe('42')
  })

  // 3 — "Tambah baris" (canAddTippedTimeRow) is gated on the local
  // mill_setting cache, not the Cages Tipped header field (2026-08-20,
  // tech-spec v3, business_logic step 5).
  it("disables 'Tambah baris' when mill_setting.jumlah_cages is unavailable locally (not synced)", async () => {
    getJumlahCagesMock.mockResolvedValueOnce(null)
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    expect(wrapper.find('[data-testid="add-tipped-time-row-button"]').attributes('disabled')).toBeDefined()
    expect(wrapper.find('[data-testid="add-row-jumlah-cages-hint"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="add-row-jumlah-cages-hint"]').text()).toContain(
      'Data Jumlah Cages dari Mills Setting belum tersedia.',
    )
  })

  it("disables 'Tambah baris' when mill_setting.jumlah_cages is 0", async () => {
    getJumlahCagesMock.mockResolvedValueOnce(0)
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    expect(wrapper.find('[data-testid="add-tipped-time-row-button"]').attributes('disabled')).toBeDefined()
    expect(wrapper.find('[data-testid="add-row-jumlah-cages-hint"]').exists()).toBe(true)
  })

  it("enables 'Tambah baris' once mill_setting.jumlah_cages is a positive value", async () => {
    getJumlahCagesMock.mockResolvedValueOnce(10)
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    expect(wrapper.find('[data-testid="add-tipped-time-row-button"]').attributes('disabled')).toBeUndefined()
    expect(wrapper.find('[data-testid="add-row-jumlah-cages-hint"]').exists()).toBe(false)
  })

  // 4 — replaces the pre-2026-08-20 "renders N cage checkboxes matching
  // the Cages Tipped header value" test: N now comes from mill_setting,
  // NOT the header field, which this test deliberately sets to a
  // different value to prove the decoupling.
  it('renders N checklist checkboxes per row matching mill_setting.jumlah_cages, independent of the Cages Tipped header value', async () => {
    getJumlahCagesMock.mockResolvedValueOnce(10)
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    await wrapper.find('#field-cages-tipped').setValue('5')
    await wrapper.find('[data-testid="add-tipped-time-row-button"]').trigger('click')

    expect(wrapper.find('[data-testid="cage-checkbox-grid-0"]').findAll('input[type="checkbox"]')).toHaveLength(10)
    expect(wrapper.find('[data-testid="cage-checkbox-0-1"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="cage-checkbox-0-10"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="cage-checkbox-0-11"]').exists()).toBe(false)
  })

  // 5
  it('excludes an hour already used by another row from a row\'s Time dropdown', async () => {
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    await wrapper.find('[data-testid="add-tipped-time-row-button"]').trigger('click')
    await chooseSearchableOption(wrapper, 'tipped-hour-select-0', hourLabel(7))
    await wrapper.find('[data-testid="add-tipped-time-row-button"]').trigger('click')

    await openSearchableSelect(wrapper, 'tipped-hour-select-1')
    const row1Labels = searchableSelectOptionTexts(wrapper, 'tipped-hour-select-1')

    expect(row1Labels).not.toContain(hourLabel(7))
  })

  it('excludes hours less than or equal to the most-recently-added row\'s hour', async () => {
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    await wrapper.find('[data-testid="add-tipped-time-row-button"]').trigger('click')
    await chooseSearchableOption(wrapper, 'tipped-hour-select-0', hourLabel(7))
    await wrapper.find('[data-testid="add-tipped-time-row-button"]').trigger('click')

    await openSearchableSelect(wrapper, 'tipped-hour-select-1')
    const row1Labels = searchableSelectOptionTexts(wrapper, 'tipped-hour-select-1')

    for (const label of row1Labels) {
      expect(Number(label.split(':')[0])).toBeGreaterThan(7)
    }
  })

  // 6 — computes total_cages / cages_remain for a row; both are now
  // derived from mill_setting.jumlah_cages, not the Cages Tipped header
  // field (2026-08-20, tech-spec v3).
  it('computes total_cages for a row as the count of checked cage checkboxes in that row, independent of the Cages Tipped header value', async () => {
    getJumlahCagesMock.mockResolvedValueOnce(10)
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    // Deliberately different from mill_setting's jumlah_cages, to verify
    // no lingering dependency on this field.
    await wrapper.find('#field-cages-tipped').setValue('99')
    await wrapper.find('[data-testid="add-tipped-time-row-button"]').trigger('click')
    await wrapper.find('[data-testid="cage-checkbox-0-1"]').setValue(true)
    await wrapper.find('[data-testid="cage-checkbox-0-3"]').setValue(true)
    await wrapper.find('[data-testid="cage-checkbox-0-5"]').setValue(true)

    expect(wrapper.find('[data-testid="row-total-cages-0"]').text()).toContain('3')
  })

  it("computes cages_remain for a row as mill_setting.jumlah_cages minus that row's own total_cages", async () => {
    getJumlahCagesMock.mockResolvedValueOnce(10)
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    await wrapper.find('[data-testid="add-tipped-time-row-button"]').trigger('click')
    await wrapper.find('[data-testid="cage-checkbox-0-1"]').setValue(true)
    await wrapper.find('[data-testid="cage-checkbox-0-2"]').setValue(true)
    await wrapper.find('[data-testid="cage-checkbox-0-3"]').setValue(true)

    expect(wrapper.find('[data-testid="row-total-cages-0"]').text()).toContain('3')
    expect(wrapper.find('[data-testid="row-cages-remain-0"]').text()).toContain('7')
  })

  it('recomputes Total Cages and Cages Remain for a row when its own checklist changes, without affecting other rows', async () => {
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    await wrapper.find('[data-testid="add-tipped-time-row-button"]').trigger('click')
    await wrapper.find('[data-testid="add-tipped-time-row-button"]').trigger('click')

    await wrapper.find('[data-testid="cage-checkbox-0-1"]').setValue(true)
    await wrapper.find('[data-testid="cage-checkbox-0-2"]').setValue(true)

    expect(wrapper.find('[data-testid="row-total-cages-0"]').text()).toContain('2')
    expect(wrapper.find('[data-testid="row-cages-remain-0"]').text()).toContain('8')
    // row 1 untouched
    expect(wrapper.find('[data-testid="row-total-cages-1"]').text()).toContain('0')
  })

  // 7
  it('frees a used hour for other rows once the row using it is removed', async () => {
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    await wrapper.find('[data-testid="add-tipped-time-row-button"]').trigger('click')
    await chooseSearchableOption(wrapper, 'tipped-hour-select-0', hourLabel(7))
    await wrapper.find('[data-testid="add-tipped-time-row-button"]').trigger('click')

    await wrapper.findAll('[data-testid="remove-tipped-time-row-button"]')[0].trigger('click')

    await openSearchableSelect(wrapper, 'tipped-hour-select-0')
    const row0Labels = searchableSelectOptionTexts(wrapper, 'tipped-hour-select-0')
    expect(row0Labels).toContain(hourLabel(7))
  })

  it('queues an existing (has-id) row for deletion on Hapus baris rather than deleting immediately', async () => {
    getDraftWithTippedTimesMock.mockResolvedValueOnce({
      record: makeDraftRecord({ status: 'draft_paused', cages_tipped: 10 }),
      tippedTimes: [
        makeTippedTimeRow({ id: 'tipped-remove-1', tipped_hour: 7 }),
        makeTippedTimeRow({ id: 'tipped-keep-1', tipped_hour: 9 }),
      ],
    })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    expect(wrapper.findAll('[data-testid="tipped-time-row"]')).toHaveLength(2)

    await wrapper.findAll('[data-testid="remove-tipped-time-row-button"]')[0].trigger('click')

    expect(wrapper.findAll('[data-testid="tipped-time-row"]')).toHaveLength(1)
    expect(saveDraftMock).not.toHaveBeenCalled()
  })

  // 8
  it('disables the Checked By toggle for a non-supervisor user', async () => {
    setCurrentUser('operator')
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    expect(wrapper.find('[data-testid="checked-by-toggle"]').attributes('disabled')).toBeDefined()
  })

  it('enables the Checked By toggle for a supervisor user and sets checked_by to the current user id when checked', async () => {
    setCurrentUser('supervisor')
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    expect(wrapper.find('[data-testid="checked-by-toggle"]').attributes('disabled')).toBeUndefined()

    await wrapper.find('[data-testid="checked-by-toggle"]').setValue(true)

    expect((wrapper.find('[data-testid="checked-by-toggle"]').element as HTMLInputElement).checked).toBe(true)
  })

  // 9
  it('disables the Acknowledged By toggle for a non-mill_management user (supervisor)', async () => {
    setCurrentUser('supervisor')
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    expect(wrapper.find('[data-testid="acknowledged-by-toggle"]').attributes('disabled')).toBeDefined()
  })

  it('enables the Acknowledged By toggle for a mill_management user', async () => {
    setCurrentUser('mill_management')
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    expect(wrapper.find('[data-testid="acknowledged-by-toggle"]').attributes('disabled')).toBeUndefined()
  })

  it('displays the current logged-in user\'s name as Inputted By (read-only)', async () => {
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    expect(wrapper.find('[data-testid="inputted-by-display"]').text()).toBe('Operator Satu')
  })

  // 10
  it('shows inline error and does not save when a required header field is empty on Simpan', async () => {
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('#field-cages-track-number').classes()).not.toHaveLength(0)
    expect(saveDraftMock).not.toHaveBeenCalled()
  })

  it('shows a dedicated error and does not save when no tipped-time row has both a selected hour and a checked cage', async () => {
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    await fillRequiredHeaderFields(wrapper)
    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="tipped-time-rows-error"]').exists()).toBe(true)
    expect(saveDraftMock).not.toHaveBeenCalled()
  })

  it('freezes tippler_stop_time to the exact moment Simpan is pressed and saves with status=saved', async () => {
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })
    saveDraftMock.mockResolvedValueOnce({ record: makeDraftRecord({ status: 'saved' }), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    await fillRequiredHeaderFields(wrapper)
    await wrapper.find('[data-testid="add-tipped-time-row-button"]').trigger('click')
    await chooseSearchableOption(wrapper, 'tipped-hour-select-0', hourLabel(7))
    await wrapper.find('[data-testid="cage-checkbox-0-1"]').setValue(true)

    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(saveDraftMock).toHaveBeenCalledTimes(1)
    const [, headerPayload] = saveDraftMock.mock.calls[0]
    expect(headerPayload.tippler_stop_time).toBe(nowLocalDateTimeString(new Date()))
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-cages-track' })
  })

  it('surfaces the repo-level CagesTippedTimeRequiredError as a defense-in-depth fallback', async () => {
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })
    saveDraftMock.mockRejectedValueOnce(new CagesTippedTimeRequiredError())

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    await fillRequiredHeaderFields(wrapper)
    await wrapper.find('[data-testid="add-tipped-time-row-button"]').trigger('click')
    await chooseSearchableOption(wrapper, 'tipped-hour-select-0', hourLabel(7))
    await wrapper.find('[data-testid="cage-checkbox-0-1"]').setValue(true)

    await wrapper.find('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="tipped-time-rows-error"]').exists()).toBe(true)
    expect(pushMock).not.toHaveBeenCalled()
  })

  // 11
  it('does not validate required fields and does not freeze tippler_stop_time on Pause', async () => {
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })
    pauseDraftWithFormDataMock.mockResolvedValueOnce({ record: makeDraftRecord({ status: 'draft_paused' }), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    await wrapper.find('[data-testid="pause-button"]').trigger('click')
    await flushPromises()

    expect(pauseDraftWithFormDataMock).toHaveBeenCalledTimes(1)
    const [, headerPayload] = pauseDraftWithFormDataMock.mock.calls[0]
    expect(headerPayload.tippler_stop_time).toBe('')
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-cages-track' })
  })

  // 12
  it('shows confirmation dialog before deleting on Clear, and deletes + navigates on confirm', async () => {
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })
    deleteDraftMock.mockResolvedValueOnce(undefined)

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    await wrapper.find('[data-testid="clear-button"]').trigger('click')
    await flushPromises()
    expect(deleteDraftMock).not.toHaveBeenCalled()

    await wrapper.find('.confirm-dialog-button--confirm').trigger('click')
    await flushPromises()

    expect(deleteDraftMock).toHaveBeenCalledWith('draft-1')
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-cages-track' })
  })

  it('does not delete when Clear confirmation is cancelled', async () => {
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    await wrapper.find('[data-testid="clear-button"]').trigger('click')
    await flushPromises()
    await wrapper.find('.confirm-dialog-button--cancel').trigger('click')
    await flushPromises()

    expect(deleteDraftMock).not.toHaveBeenCalled()
  })

  // 13
  it('shows confirmation dialog on Back when the form is dirty', async () => {
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    await wrapper.find('#field-cages-track-number').setValue('CT-9999')
    await wrapper.find('[data-testid="back-button"]').trigger('click')
    await flushPromises()

    expect(pushMock).not.toHaveBeenCalled()
  })

  it('navigates directly on Back when the form is not dirty', async () => {
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    await wrapper.find('[data-testid="back-button"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-cages-track' })
  })

  // 14
  it('navigates to related route when a breadcrumb segment is tapped', async () => {
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    await wrapper.find('[data-testid="breadcrumb-cages-track"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-cages-track' })
  })

  it('opens navigation menu when hamburger icon is tapped', async () => {
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeDraftRecord(), tippedTimes: [] })

    const wrapper = mount(FormCagesTrackView)
    await flushPromises()

    await wrapper.find('[data-testid="hamburger-button"]').trigger('click')

    expect(wrapper.find('[data-testid="nav-menu"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="nav-menu-change-password"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="nav-menu-logout"]').exists()).toBe(true)
  })
})
