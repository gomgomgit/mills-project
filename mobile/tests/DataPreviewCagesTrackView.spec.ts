/**
 * DataPreviewCagesTrackView.spec.ts — screen-015--data-preview-cages-track
 * / usecase-015--data-preview-cages-track.
 *
 * FULL REWRITE (2026-08-19, scope expansion — mirrors
 * DataPreviewGradingView.spec.ts's (screen-014) / DataPreviewWeighbridgeView
 * .spec.ts's (screen-013) own dual-mode rewrites) to match
 * DataPreviewCagesTrackView.vue's own rewrite adding a route-driven LIST
 * mode (default, no `:id` param, via the new cagesTrackRecordRepo.
 * getAllRecords()) alongside the pre-existing DETAIL mode (`:id` param
 * present, unchanged — still reuses getDraftWithTippedTimes()). The
 * previous version of this file targeted the old single-record-only
 * read-only view shape and is now obsolete/fully replaced below.
 *
 * Component tests:
 *   LIST mode
 *     1.  empty list state when the user has no local cages_track_record
 *         rows
 *     2.  date filter defaults to TODAY's local date on mount (local
 *         getFullYear/getMonth+1/getDate — NOT toISOString()/UTC)
 *     3.  date filter is changeable and clearable after the default,
 *         re-filtering the list each time
 *     4.  date filtering against the `date.slice(0, 10)` comparison
 *         (`cages_track_record.date` is a full local datetime string, NOT
 *         a plain date column — same pattern as
 *         DataPreviewWeighbridgeView.vue's `arrival_datetime` filter, NOT
 *         DataPreviewGradingView.vue's exact-match `date` filter)
 *     5.  case-insensitive search filtering on `cages_track_number`
 *     6.  combined date + search filter matching nothing -> not-found
 *         message + Reset Filter button; Reset Filter clears both filters
 *     7-10. status label mapping: draft_ongoing/draft_paused -> 'Pause',
 *         saved -> 'Tersimpan', synced -> 'Tersinkron'
 *     11. tap a draft_ongoing/draft_paused item -> navigates to
 *         `cages-track-form` with that item's id, no detail-mode switch
 *     12. tap a saved/synced item -> pushes to this screen's own route
 *         WITH the id param (detail-mode transition)
 *   DETAIL mode
 *     13. renders full header (including Checked By / Acknowledged By,
 *         regardless of viewer role) + cages_tipped_time rows parsed via
 *         hourLabel()/checkedCagesDisplay(), Total Cages/Cages Remain
 *         rendered as stored
 *     14. shows a 'tidak ditemukan' error + Back button when the record
 *         is not found
 *   Back navigation
 *     15. list mode -> Monitor Cages Track (route name
 *         `monitor-cages-track`)
 *     16. detail mode -> list mode (id param removed, same route name
 *         `data-preview-cages-track`), NOT Monitor Cages Track
 *   Route param reactivity
 *     17. a `watch()` on the id route param (not a one-time `onMounted`)
 *         re-triggers data loading on every list -> detail -> list
 *         transition within ONE mounted component instance — since Vue
 *         Router reuses the component instance across param-only
 *         navigations on the same route record.
 *   Header/breadcrumb/nav-menu (verbatim pattern from
 *   DataPreviewGradingView.spec.ts / MonitorCagesTrackView.spec.ts)
 *     18. breadcrumb segment taps navigate to Home / Production Process
 *         Activity / Cages Track; 'Load Data' renders as non-interactive
 *         current-page text
 *     19. hamburger opens the nav menu with Ganti Password / Logout
 *
 * unit_test_cases note: `getAllRecords()`'s own row-shape/SQL behavior is
 * already covered by cagesTrackRecordRepo.spec.ts's `getAllRecords()`
 * describe block (added alongside this rewrite); `getDraftWithTippedTimes
 * ()`'s found/not-found behavior is already covered by that same file's
 * pre-existing describe block (added for screen-012--form-cages-track,
 * reused as-is here) — neither is duplicated in this file.
 *
 * Mocking strategy (mirrors DataPreviewGradingView.spec.ts, with one
 * deliberate addition):
 *   - 'vue-router' is mocked at module level so `router.push` can be
 *     asserted via a hoisted `pushMock`. Unlike DataPreviewGradingView.
 *     spec.ts's plain `useRouteMock.mockReturnValue(...)` (which is
 *     re-assigned per test but never mutated *during* a test — none of
 *     that file's tests need to observe a live param change within one
 *     mounted instance), `useRoute()` here returns a single Vue
 *     `reactive()` object (`routeRef.current`, held across the whole file) whose
 *     `params.id` is mutated in place by `setListRoute()`/
 *     `setDetailRoute()` — this is required so scenario 17 (route param
 *     reactivity, see above) can flip the SAME mounted wrapper between
 *     list/detail mode and observe the component's own `watch()` react,
 *     exactly like the real `vue-router` (which also returns a reactive
 *     route object reused across param-only navigations on one route
 *     record) does. Every other test still only ever sets the route
 *     once before `mount()`, same as DataPreviewGradingView.spec.ts.
 *   - '@/stores/auth' is mocked at module level for `currentUser` (also
 *     drives "Inputted By", which this screen renders from
 *     `authStore.currentUser?.name` — see DataPreviewCagesTrackView.vue's
 *     implementation_notes).
 *   - '@/services/cagesTrackRecordRepo' is mocked at module level
 *     (`getAllRecords`/`getDraftWithTippedTimes`) — the repo's own row-
 *     shape/SQL behavior is already covered by cagesTrackRecordRepo.spec
 *     .ts; this file only exercises the view's own logic against the
 *     repo's public interface. The default export is mocked
 *     (DataPreviewCagesTrackView.vue imports it as `cagesTrackRecordRepo`,
 *     the default export).
 *   - FormField.vue and StatusBadge.vue are NOT mocked — real rendering
 *     is asserted directly (field values/disabled state, badge
 *     status/label), same as DataPreviewGradingView.spec.ts.
 *   - Inputs are targeted via FormField.vue's deterministic slugified
 *     `field-<label-slug>` ids (e.g. `#field-no-cages-track` for "No.
 *     Cages Track"), matching how FormField.vue itself derives `fieldId`
 *     from `label` when no explicit `id` prop is passed.
 */
import { reactive } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import DataPreviewCagesTrackView from '@/views/DataPreviewCagesTrackView.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import type { CagesTippedTimeRow, CagesTrackRecord } from '@/services/cagesTrackRecordRepo'

// `useRoute()` must return a real Vue `reactive()` proxy (not a plain
// object) so the component's own `watch(recordIdParam, ...)` (a
// `computed()` reading `route.params.id`) can actually react when this
// file mutates `.params.id` mid-test (see the "Route param reactivity"
// describe block below) — a plain mocked object would never trigger Vue's
// dependency tracking on mutation. `vi.hoisted()` itself runs BEFORE this
// file's own imports are bound (per Vitest's documented hoisting
// behavior), so `reactive` cannot be imported/called there directly — it
// is instead loaded via a dynamic `import('vue')` inside the (lazily
// invoked) `vi.mock('vue-router', ...)` factory, and the resulting proxy
// is stashed into a hoisted plain holder (`routeRef`) so both the mock
// and the test bodies below can reach the SAME proxy instance.
const { pushMock, routeRef } = vi.hoisted(() => ({
  pushMock: vi.fn(),
  routeRef: { current: null as unknown as { params: { id?: string } } },
}))

vi.mock('vue-router', async () => {
  const { reactive } = await import('vue')
  routeRef.current = reactive({ params: {} as { id?: string } })

  return {
    useRouter: () => ({ push: pushMock }),
    useRoute: () => routeRef.current,
  }
})

const { useAuthStoreMock } = vi.hoisted(() => ({
  useAuthStoreMock: vi.fn(),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: useAuthStoreMock,
}))

const { getAllRecordsMock, getDraftWithTippedTimesMock } = vi.hoisted(() => ({
  getAllRecordsMock: vi.fn(),
  getDraftWithTippedTimesMock: vi.fn(),
}))

vi.mock('@/services/cagesTrackRecordRepo', () => ({
  default: {
    getAllRecords: getAllRecordsMock,
    getDraftWithTippedTimes: getDraftWithTippedTimesMock,
  },
}))

// Mirrors DataPreviewCagesTrackView.vue's own `todayLocalDateString()`
// exactly (local getFullYear/getMonth+1/getDate, zero-padded — NOT
// toISOString/UTC), so this file can independently compute the same
// "today" value the component computes at mount time.
function todayLocalDateString(): string {
  const today = new Date()
  const yyyy = today.getFullYear()
  const mm = String(today.getMonth() + 1).padStart(2, '0')
  const dd = String(today.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

function makeRecord(overrides: Partial<CagesTrackRecord> & { id: string }): CagesTrackRecord {
  return {
    station_id: 'station-1',
    cages_track_number: 'CT-1001',
    // Defaults to TODAY (full local datetime, matching the real
    // `date` column shape) so every pre-existing test that doesn't
    // override this field keeps rendering under the default today date
    // filter without needing per-test date setup.
    date: `${todayLocalDateString()}T07:00:00`,
    tippler_start_time: `${todayLocalDateString()}T07:00:00`,
    tippler_stop_time: `${todayLocalDateString()}T11:00:00`,
    cages_out: 12,
    cages_tipped: 10,
    note: 'Catatan',
    checked_by: null,
    acknowledged_by: null,
    status: 'saved',
    created_by: 'user-1',
    created_at: '2026-08-17T07:00:00.000Z',
    updated_at: '2026-08-17T07:30:00.000Z',
    ...overrides,
  }
}

function makeTippedTimeRow(overrides: Partial<CagesTippedTimeRow> & { id: string }): CagesTippedTimeRow {
  return {
    cages_track_record_id: 'rec-1',
    tipped_hour: 8,
    checked_cage_numbers: '1,3,5',
    total_cages: 3,
    cages_remain: 7,
    created_at: '2026-08-17T07:00:00.000Z',
    updated_at: '2026-08-17T07:00:00.000Z',
    ...overrides,
  }
}

// List-mode route (no id param) — the default state.
function setListRoute(): void {
  delete routeRef.current.params.id
}

// Detail-mode route (id param present).
function setDetailRoute(id: string): void {
  routeRef.current.params.id = id
}

describe('DataPreviewCagesTrackView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    // Fresh reactive route object per test — @vue/test-utils does not
    // auto-unmount wrappers between tests, so reusing ONE reactive object
    // across the whole file would let earlier tests' still-active
    // watch(recordIdParam, ...) effects keep firing (and re-calling the
    // repo mocks) whenever a later test mutates it, corrupting call
    // counts. A brand-new proxy per test means any leftover watchers from
    // earlier, no-longer-relevant component instances are watching a
    // now-abandoned object and can never fire again.
    routeRef.current = reactive({ params: {} as { id?: string } })
    useAuthStoreMock.mockReturnValue({
      currentUser: { id: 'user-1', username: 'operator01', name: 'Operator Satu', role: 'operator' },
    })
    getAllRecordsMock.mockResolvedValue([])
    getDraftWithTippedTimesMock.mockResolvedValue(null)
  })

  /* ---------------------------------------------------------------- *
   * LIST mode — empty state
   * ---------------------------------------------------------------- */

  it('shows the empty-list state when the user has no local cages_track_record rows', async () => {
    getAllRecordsMock.mockResolvedValueOnce([])

    const wrapper = mount(DataPreviewCagesTrackView)
    await flushPromises()

    expect(getAllRecordsMock).toHaveBeenCalledWith('user-1')
    expect(wrapper.find('[data-testid="record-list-empty"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-list"]').exists()).toBe(false)
  })

  /* ---------------------------------------------------------------- *
   * LIST mode — default date filter (today)
   * ---------------------------------------------------------------- */

  it("initializes the date filter to today's date (YYYY-MM-DD, local) when list mode mounts", async () => {
    getAllRecordsMock.mockResolvedValueOnce([])

    const wrapper = mount(DataPreviewCagesTrackView)
    await flushPromises()

    const input = wrapper.find('[data-testid="date-filter-input"]')
    expect(input.exists()).toBe(true)
    expect((input.element as HTMLInputElement).value).toBe(todayLocalDateString())
  })

  it('filters the list by the default (today) date value on initial render, with no user interaction', async () => {
    const records = [
      makeRecord({ id: 'rec-today', cages_track_number: 'CT-TODAY' }),
      makeRecord({ id: 'rec-other-day', date: '2020-01-01T09:00:00', cages_track_number: 'CT-OTHERDAY' }),
    ]
    getAllRecordsMock.mockResolvedValueOnce(records)

    const wrapper = mount(DataPreviewCagesTrackView)
    await flushPromises()

    expect(wrapper.find('[data-testid="record-item-rec-today"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-item-rec-other-day"]').exists()).toBe(false)
  })

  it('allows the user to change or clear the date filter after the default is applied', async () => {
    const records = [
      makeRecord({ id: 'rec-today', cages_track_number: 'CT-TODAY' }),
      makeRecord({ id: 'rec-past', date: '2020-01-01T09:00:00', cages_track_number: 'CT-PAST' }),
    ]
    getAllRecordsMock.mockResolvedValueOnce(records)

    const wrapper = mount(DataPreviewCagesTrackView)
    await flushPromises()

    // Default (today) filter applied on mount, no interaction yet.
    expect(wrapper.find('[data-testid="record-item-rec-today"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-item-rec-past"]').exists()).toBe(false)

    // User changes the date filter to the other record's date.
    await wrapper.find('[data-testid="date-filter-input"]').setValue('2020-01-01')
    await flushPromises()
    expect(wrapper.find('[data-testid="record-item-rec-past"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-item-rec-today"]').exists()).toBe(false)

    // User clears the date filter — both records reappear.
    await wrapper.find('[data-testid="date-filter-input"]').setValue('')
    await flushPromises()
    expect(wrapper.find('[data-testid="record-item-rec-today"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-item-rec-past"]').exists()).toBe(true)
  })

  /* ---------------------------------------------------------------- *
   * LIST mode — date filtering against the datetime column's
   * `.slice(0, 10)` (NOT an exact-match plain-date column).
   * ---------------------------------------------------------------- */

  it('filters records by date, comparing against the first 10 characters of the full local datetime `date` column (not an exact-string match)', async () => {
    const records = [
      makeRecord({ id: 'rec-aug10', date: '2026-08-10T14:30:00', cages_track_number: 'CT-AUG10' }),
      // Same 10-char date prefix as rec-aug10 would need, but a different
      // time component — proves the comparison is against the sliced
      // prefix, not the raw full datetime string.
      makeRecord({ id: 'rec-aug10-later', date: '2026-08-10T23:59:59', cages_track_number: 'CT-AUG10-LATER' }),
      makeRecord({ id: 'rec-aug11', date: '2026-08-11T08:00:00', cages_track_number: 'CT-AUG11' }),
    ]
    getAllRecordsMock.mockResolvedValueOnce(records)

    const wrapper = mount(DataPreviewCagesTrackView)
    await flushPromises()

    await wrapper.find('[data-testid="date-filter-input"]').setValue('2026-08-10')
    await flushPromises()

    expect(wrapper.find('[data-testid="record-item-rec-aug10"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-item-rec-aug10-later"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-item-rec-aug11"]').exists()).toBe(false)
  })

  it('excludes a record with a null `date` when a date filter is applied', async () => {
    const records = [
      makeRecord({ id: 'rec-dated', date: '2026-08-11T08:00:00', cages_track_number: 'CT-DATED' }),
      makeRecord({ id: 'rec-null-date', date: null, cages_track_number: 'CT-NULL-DATE' }),
    ]
    getAllRecordsMock.mockResolvedValueOnce(records)

    const wrapper = mount(DataPreviewCagesTrackView)
    await flushPromises()

    await wrapper.find('[data-testid="date-filter-input"]').setValue('2026-08-11')
    await flushPromises()

    expect(wrapper.find('[data-testid="record-item-rec-dated"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-item-rec-null-date"]').exists()).toBe(false)
  })

  /* ---------------------------------------------------------------- *
   * LIST mode — search filtering
   * ---------------------------------------------------------------- */

  it('filters records by a case-insensitive substring on cages_track_number', async () => {
    const records = [
      makeRecord({ id: 'rec-number', cages_track_number: 'CT-1001' }),
      makeRecord({ id: 'rec-other', cages_track_number: 'CT-2002' }),
    ]
    getAllRecordsMock.mockResolvedValueOnce(records)

    const wrapper = mount(DataPreviewCagesTrackView)
    await flushPromises()

    await wrapper.find('[data-testid="search-filter-input"]').setValue('ct-10')
    await flushPromises()
    expect(wrapper.find('[data-testid="record-item-rec-number"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="record-item-rec-other"]').exists()).toBe(false)
  })

  /* ---------------------------------------------------------------- *
   * LIST mode — combined filters matching nothing, Reset Filter
   * ---------------------------------------------------------------- */

  it('shows a not-found message + Reset Filter button when the combined date + search filter matches nothing, and Reset Filter clears both filters', async () => {
    const records = [makeRecord({ id: 'rec-1', date: '2026-08-11T08:00:00', cages_track_number: 'CT-1001' })]
    getAllRecordsMock.mockResolvedValueOnce(records)

    const wrapper = mount(DataPreviewCagesTrackView)
    await flushPromises()

    await wrapper.find('[data-testid="date-filter-input"]').setValue('2026-08-11')
    await wrapper.find('[data-testid="search-filter-input"]').setValue('no-such-cages-track-number')
    await flushPromises()

    expect(wrapper.find('[data-testid="record-item-rec-1"]').exists()).toBe(false)
    const emptyState = wrapper.find('[data-testid="record-list-empty"]')
    expect(emptyState.exists()).toBe(true)
    expect(emptyState.text().toLowerCase()).toContain('tidak ada')

    const resetButton = wrapper.find('[data-testid="reset-filter-button"]')
    expect(resetButton.exists()).toBe(true)

    await resetButton.trigger('click')
    await flushPromises()

    expect((wrapper.find('[data-testid="date-filter-input"]').element as HTMLInputElement).value).toBe('')
    expect((wrapper.find('[data-testid="search-filter-input"]').element as HTMLInputElement).value).toBe('')
    expect(wrapper.find('[data-testid="record-item-rec-1"]').exists()).toBe(true)
  })

  it('does not show the Reset Filter button when no filter is active and the list is genuinely empty', async () => {
    getAllRecordsMock.mockResolvedValueOnce([])

    const wrapper = mount(DataPreviewCagesTrackView)
    await flushPromises()

    // Default date filter (today) counts as an active filter — clear it
    // explicitly to reach the "no filter at all" empty state.
    await wrapper.find('[data-testid="date-filter-input"]').setValue('')
    await flushPromises()

    expect(wrapper.find('[data-testid="reset-filter-button"]').exists()).toBe(false)
  })

  /* ---------------------------------------------------------------- *
   * LIST mode — status badge label mapping
   * ---------------------------------------------------------------- */

  it("maps status 'draft_ongoing' to the 'Pause' label in list mode", async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-ongoing', status: 'draft_ongoing' })])

    const wrapper = mount(DataPreviewCagesTrackView)
    await flushPromises()

    const badge = wrapper.findComponent(StatusBadge)
    expect(badge.props('status')).toBe('paused')
    expect(badge.props('label')).toBe('Pause')
    expect(badge.text()).toBe('Pause')
  })

  it("maps status 'draft_paused' to the 'Pause' label in list mode", async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-paused', status: 'draft_paused' })])

    const wrapper = mount(DataPreviewCagesTrackView)
    await flushPromises()

    const badge = wrapper.findComponent(StatusBadge)
    expect(badge.props('status')).toBe('paused')
    expect(badge.props('label')).toBe('Pause')
    expect(badge.text()).toBe('Pause')
  })

  it("maps status 'saved' to the 'Tersimpan' label in list mode", async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-saved', status: 'saved' })])

    const wrapper = mount(DataPreviewCagesTrackView)
    await flushPromises()

    const badge = wrapper.findComponent(StatusBadge)
    expect(badge.props('status')).toBe('none')
    expect(badge.props('label')).toBe('Tersimpan')
    expect(badge.text()).toBe('Tersimpan')
  })

  it("maps status 'synced' to the 'Tersinkron' label in list mode", async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-synced', status: 'synced' })])

    const wrapper = mount(DataPreviewCagesTrackView)
    await flushPromises()

    const badge = wrapper.findComponent(StatusBadge)
    expect(badge.props('status')).toBe('none')
    expect(badge.props('label')).toBe('Tersinkron')
    expect(badge.text()).toBe('Tersinkron')
  })

  /* ---------------------------------------------------------------- *
   * LIST mode — row-tap navigation
   * ---------------------------------------------------------------- */

  it("navigates to Form Cages Track (no detail-mode switch) when a tapped item's status is 'draft_ongoing'", async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-ongoing', status: 'draft_ongoing' })])

    const wrapper = mount(DataPreviewCagesTrackView)
    await flushPromises()

    await wrapper.find('[data-testid="record-item-rec-ongoing"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'cages-track-form', params: { id: 'rec-ongoing' } })
    expect(pushMock).not.toHaveBeenCalledWith(
      expect.objectContaining({ name: 'data-preview-cages-track', params: { id: 'rec-ongoing' } }),
    )
  })

  it("navigates to Form Cages Track (no detail-mode switch) when a tapped item's status is 'draft_paused'", async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-paused', status: 'draft_paused' })])

    const wrapper = mount(DataPreviewCagesTrackView)
    await flushPromises()

    await wrapper.find('[data-testid="record-item-rec-paused"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'cages-track-form', params: { id: 'rec-paused' } })
    expect(pushMock).not.toHaveBeenCalledWith(
      expect.objectContaining({ name: 'data-preview-cages-track', params: { id: 'rec-paused' } }),
    )
  })

  it("switches to detail mode (pushes with the id param) when a tapped item's status is 'saved'", async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-saved', status: 'saved' })])

    const wrapper = mount(DataPreviewCagesTrackView)
    await flushPromises()

    await wrapper.find('[data-testid="record-item-rec-saved"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'data-preview-cages-track', params: { id: 'rec-saved' } })
    expect(pushMock).not.toHaveBeenCalledWith(expect.objectContaining({ name: 'cages-track-form' }))
  })

  it("switches to detail mode (pushes with the id param) when a tapped item's status is 'synced'", async () => {
    getAllRecordsMock.mockResolvedValueOnce([makeRecord({ id: 'rec-synced', status: 'synced' })])

    const wrapper = mount(DataPreviewCagesTrackView)
    await flushPromises()

    await wrapper.find('[data-testid="record-item-rec-synced"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'data-preview-cages-track', params: { id: 'rec-synced' } })
    expect(pushMock).not.toHaveBeenCalledWith(expect.objectContaining({ name: 'cages-track-form' }))
  })

  /* ---------------------------------------------------------------- *
   * DETAIL mode
   * ---------------------------------------------------------------- */

  it('renders the full header (INCLUDING Checked By / Acknowledged By, regardless of viewer role) plus cages_tipped_time rows parsed via hourLabel()/checkedCagesDisplay(), with Total Cages/Cages Remain rendered exactly as stored', async () => {
    setDetailRoute('record-1')
    const record = makeRecord({
      id: 'record-1',
      cages_track_number: 'CT-5001',
      date: '2026-08-17T07:00:00',
      tippler_start_time: '2026-08-17T07:00:00',
      tippler_stop_time: '2026-08-17T11:00:00',
      cages_out: 20,
      cages_tipped: 18,
      note: 'Catatan detail',
      checked_by: 'Supervisor Satu',
      acknowledged_by: 'Mill Management Satu',
      status: 'saved',
    })
    const tippedRows = [
      makeTippedTimeRow({ id: 'tipped-1', cages_track_record_id: 'record-1', tipped_hour: 8, checked_cage_numbers: '1,3,5', total_cages: 3, cages_remain: 17 }),
      makeTippedTimeRow({ id: 'tipped-2', cages_track_record_id: 'record-1', tipped_hour: 9, checked_cage_numbers: '2,4', total_cages: 2, cages_remain: 15 }),
    ]
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record, tippedTimes: tippedRows })

    const wrapper = mount(DataPreviewCagesTrackView)
    await flushPromises()

    expect(getDraftWithTippedTimesMock).toHaveBeenCalledWith('record-1')
    expect(getAllRecordsMock).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="record-not-found"]').exists()).toBe(false)

    const fieldChecks: Array<[string, string]> = [
      ['#field-no-cages-track', 'CT-5001'],
      // datetime-local inputs normalize away a trailing :00 seconds
      // component (default step is whole minutes) — this is browser/jsdom
      // input behavior, not a component bug.
      ['#field-tanggal', '2026-08-17T07:00'],
      ['#field-tippler-start-time', '2026-08-17T07:00'],
      ['#field-tippler-stop-time', '2026-08-17T11:00'],
      ['#field-cages-out', '20'],
      ['#field-cages-tipped', '18'],
      ['#field-inputted-by', 'Operator Satu'],
      ['#field-checked-by', 'Supervisor Satu'],
      ['#field-acknowledged-by', 'Mill Management Satu'],
      ['#field-catatan', 'Catatan detail'],
    ]

    for (const [selector, expectedValue] of fieldChecks) {
      const field = wrapper.find(selector)
      expect(field.exists()).toBe(true)
      expect((field.element as HTMLInputElement).value).toBe(expectedValue)
      expect(field.attributes('disabled')).toBeDefined()
    }

    const rowsList = wrapper.find('[data-testid="tipped-time-rows-list"]')
    expect(rowsList.exists()).toBe(true)

    const row1 = wrapper.find('[data-testid="tipped-time-row-tipped-1"]')
    expect(row1.text()).toContain('08:00')
    expect(row1.text()).toContain('Cage 1, Cage 3, Cage 5')
    expect(row1.text()).toContain('Total Cages: 3')
    expect(row1.text()).toContain('Cages Remain: 17')

    const row2 = wrapper.find('[data-testid="tipped-time-row-tipped-2"]')
    expect(row2.text()).toContain('09:00')
    expect(row2.text()).toContain('Cage 2, Cage 4')
    expect(row2.text()).toContain('Total Cages: 2')
    expect(row2.text()).toContain('Cages Remain: 15')
  })

  it('renders an empty cages_tipped_time state when the detail record has zero rows', async () => {
    setDetailRoute('record-empty-rows')
    getDraftWithTippedTimesMock.mockResolvedValueOnce({
      record: makeRecord({ id: 'record-empty-rows' }),
      tippedTimes: [],
    })

    const wrapper = mount(DataPreviewCagesTrackView)
    await flushPromises()

    expect(wrapper.find('[data-testid="tipped-time-rows-empty"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="tipped-time-rows-list"]').exists()).toBe(false)
  })

  it("shows a 'tidak ditemukan' error + Back button when the record is not found by id in detail mode", async () => {
    setDetailRoute('does-not-exist')
    getDraftWithTippedTimesMock.mockResolvedValueOnce(null)

    const wrapper = mount(DataPreviewCagesTrackView)
    await flushPromises()

    expect(getDraftWithTippedTimesMock).toHaveBeenCalledWith('does-not-exist')

    const notFoundEl = wrapper.find('[data-testid="record-not-found"]')
    expect(notFoundEl.exists()).toBe(true)
    expect(notFoundEl.text().toLowerCase()).toContain('tidak ditemukan')
    expect(wrapper.find('.preview-body').exists()).toBe(false)

    const backButton = wrapper.find('[data-testid="back-button"]')
    expect(backButton.exists()).toBe(true)
  })

  /* ---------------------------------------------------------------- *
   * Back navigation
   * ---------------------------------------------------------------- */

  it('navigates to Monitor Cages Track when Back is pressed in list mode', async () => {
    getAllRecordsMock.mockResolvedValueOnce([])

    const wrapper = mount(DataPreviewCagesTrackView)
    await flushPromises()

    await wrapper.find('[data-testid="back-button"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-cages-track' })
  })

  it('navigates to list mode (id param removed) — NOT Monitor Cages Track — when Back is pressed in detail mode', async () => {
    setDetailRoute('record-1')
    getDraftWithTippedTimesMock.mockResolvedValueOnce({ record: makeRecord({ id: 'record-1' }), tippedTimes: [] })

    const wrapper = mount(DataPreviewCagesTrackView)
    await flushPromises()

    await wrapper.find('[data-testid="back-button"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'data-preview-cages-track' })
    expect(pushMock).not.toHaveBeenCalledWith({ name: 'monitor-cages-track' })
  })

  /* ---------------------------------------------------------------- *
   * Route param reactivity — watch() re-triggers loading on every
   * list <-> detail transition within one mounted component instance
   * (Vue Router reuses the instance for param-only navigations on the
   * same route record; a one-time onMounted read would NOT re-run here).
   * ---------------------------------------------------------------- */

  it('re-triggers data loading via watch() on each list -> detail -> list -> detail transition within one mounted instance', async () => {
    const listRecord = makeRecord({ id: 'rec-cycle', status: 'saved', cages_track_number: 'CT-CYCLE' })
    getAllRecordsMock.mockResolvedValue([listRecord])
    getDraftWithTippedTimesMock.mockResolvedValue({
      record: makeRecord({ id: 'rec-cycle', status: 'saved', cages_track_number: 'CT-CYCLE' }),
      tippedTimes: [],
    })

    const wrapper = mount(DataPreviewCagesTrackView)
    await flushPromises()

    // Initial LIST load.
    expect(getAllRecordsMock).toHaveBeenCalledTimes(1)
    expect(wrapper.find('[data-testid="record-item-rec-cycle"]').exists()).toBe(true)

    // Tap the record -> pushes into detail mode; simulate vue-router
    // actually landing there by mutating the same reactive route object
    // the component's watch() observes (this mocked router doesn't
    // perform real navigation, unlike the live app).
    await wrapper.find('[data-testid="record-item-rec-cycle"]').trigger('click')
    expect(pushMock).toHaveBeenCalledWith({ name: 'data-preview-cages-track', params: { id: 'rec-cycle' } })
    setDetailRoute('rec-cycle')
    await flushPromises()

    expect(getDraftWithTippedTimesMock).toHaveBeenCalledTimes(1)
    expect(getDraftWithTippedTimesMock).toHaveBeenCalledWith('rec-cycle')
    expect(wrapper.find('[data-testid="record-not-found"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('CT-CYCLE')

    // Back to list.
    await wrapper.find('[data-testid="back-button"]').trigger('click')
    expect(pushMock).toHaveBeenCalledWith({ name: 'data-preview-cages-track' })
    setListRoute()
    await flushPromises()

    // Proves the watch() re-ran (a 2nd getAllRecords call), not a
    // one-time onMounted.
    expect(getAllRecordsMock).toHaveBeenCalledTimes(2)
    expect(wrapper.find('[data-testid="record-item-rec-cycle"]').exists()).toBe(true)

    // Tap into detail mode again — proves the cycle repeats, not just
    // once.
    await wrapper.find('[data-testid="record-item-rec-cycle"]').trigger('click')
    setDetailRoute('rec-cycle')
    await flushPromises()

    expect(getDraftWithTippedTimesMock).toHaveBeenCalledTimes(2)
    expect(wrapper.text()).toContain('CT-CYCLE')
  })

  /* ---------------------------------------------------------------- *
   * Breadcrumb
   * ---------------------------------------------------------------- */

  describe('breadcrumb', () => {
    beforeEach(() => {
      getAllRecordsMock.mockResolvedValue([])
    })

    it("navigates to 'home' when the 'Home' breadcrumb segment is tapped", async () => {
      const wrapper = mount(DataPreviewCagesTrackView)
      await flushPromises()

      await wrapper.get('[data-testid="breadcrumb-home"]').trigger('click')

      expect(pushMock).toHaveBeenCalledWith({ name: 'home' })
    })

    it("navigates to 'station-list' when the 'Production Process Activity' breadcrumb segment is tapped", async () => {
      const wrapper = mount(DataPreviewCagesTrackView)
      await flushPromises()

      await wrapper.get('[data-testid="breadcrumb-production-process-activity"]').trigger('click')

      expect(pushMock).toHaveBeenCalledWith({ name: 'station-list' })
    })

    it("navigates to 'monitor-cages-track' when the 'Cages Track' breadcrumb segment is tapped", async () => {
      const wrapper = mount(DataPreviewCagesTrackView)
      await flushPromises()

      await wrapper.get('[data-testid="breadcrumb-cages-track"]').trigger('click')

      expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-cages-track' })
    })

    it("renders the 'Load Data' segment as non-interactive text with aria-current=\"page\", not a link/button", async () => {
      const wrapper = mount(DataPreviewCagesTrackView)
      await flushPromises()

      const current = wrapper.get('.breadcrumb-current')
      expect(current.text()).toBe('Load Data')
      expect(current.attributes('aria-current')).toBe('page')
      expect(current.element.tagName).not.toBe('BUTTON')
      expect(current.element.tagName).not.toBe('A')
    })
  })

  /* ---------------------------------------------------------------- *
   * Hamburger nav menu
   * ---------------------------------------------------------------- */

  describe('menu navigasi (hamburger)', () => {
    beforeEach(() => {
      getAllRecordsMock.mockResolvedValue([])
    })

    it('opens the nav menu (Ganti Password, Logout) when the hamburger icon is tapped', async () => {
      const wrapper = mount(DataPreviewCagesTrackView)
      await flushPromises()

      expect(wrapper.find('[data-testid="nav-menu"]').exists()).toBe(false)

      await wrapper.get('[data-testid="hamburger-button"]').trigger('click')

      const navMenu = wrapper.get('[data-testid="nav-menu"]')
      expect(navMenu.text()).toContain('Ganti Password')
      expect(navMenu.text()).toContain('Logout')
    })

    it("navigates to 'change-password' when the 'Ganti Password' nav menu item is tapped", async () => {
      const wrapper = mount(DataPreviewCagesTrackView)
      await flushPromises()

      await wrapper.get('[data-testid="hamburger-button"]').trigger('click')
      await wrapper.get('[data-testid="nav-menu-change-password"]').trigger('click')

      expect(pushMock).toHaveBeenCalledWith({ name: 'change-password' })
    })

    it("logs out and navigates to 'login' when the 'Logout' nav menu item is tapped", async () => {
      const logoutMock = vi.fn().mockResolvedValue(undefined)
      useAuthStoreMock.mockReturnValue({
        currentUser: { id: 'user-1', username: 'operator01', name: 'Operator Satu', role: 'operator' },
        logout: logoutMock,
      })

      const wrapper = mount(DataPreviewCagesTrackView)
      await flushPromises()

      await wrapper.get('[data-testid="hamburger-button"]').trigger('click')
      await wrapper.get('[data-testid="nav-menu-logout"]').trigger('click')
      await flushPromises()

      expect(logoutMock).toHaveBeenCalledTimes(1)
      expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
    })
  })

  /* ---------------------------------------------------------------- *
   * Misc — cages_track_number fallback label, no-user guard
   * ---------------------------------------------------------------- */

  it("falls back to a placeholder label when a record's cages_track_number is null or empty", async () => {
    const records = [
      makeRecord({ id: 'rec-null', cages_track_number: null }),
      makeRecord({ id: 'rec-empty', cages_track_number: '   ' }),
    ]
    getAllRecordsMock.mockResolvedValueOnce(records)

    const wrapper = mount(DataPreviewCagesTrackView)
    await flushPromises()

    expect(wrapper.get('[data-testid="record-item-rec-null"]').text()).toContain('No. Cages Track belum diisi')
    expect(wrapper.get('[data-testid="record-item-rec-empty"]').text()).toContain('No. Cages Track belum diisi')
  })

  it('does not load the record list when the current user has no id', async () => {
    useAuthStoreMock.mockReturnValueOnce({
      currentUser: { id: undefined, username: 'op2', name: 'Op Dua', role: 'operator' },
    })

    mount(DataPreviewCagesTrackView)
    await flushPromises()

    expect(getAllRecordsMock).not.toHaveBeenCalled()
  })
})
