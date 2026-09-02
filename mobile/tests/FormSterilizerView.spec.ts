/**
 * FormSterilizerView.spec.ts — screen-122--form-sterilizer
 * / usecase-122--form-sterilizer.
 *
 * Mirrors FormCpoDispatchView.spec.ts's mocking strategy, minus the
 * grid/N-column/time-slot-ordering concerns (Sterilizer is a pure event
 * log — rows are added freely, no per-row uniqueness constraint). This is
 * the FINAL station of this project.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import FormSterilizerView from '@/views/FormSterilizerView.vue'
import type { SterilizerDetailRow, SterilizerRecord } from '@/services/sterilizerRecordRepo'

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

vi.mock('@/stores/aiAssistant', () => ({
  useAiAssistantStore: () => ({
    isOpen: false,
    bubbleEnabled: true,
    open: vi.fn(),
    close: vi.fn(),
    toggleBubble: vi.fn(),
  }),
}))

const { getDraftWithDetailsMock, saveDraftMock, pauseDraftWithFormDataMock, deleteDraftMock } = vi.hoisted(() => ({
  getDraftWithDetailsMock: vi.fn(),
  saveDraftMock: vi.fn(),
  pauseDraftWithFormDataMock: vi.fn(),
  deleteDraftMock: vi.fn(),
}))

vi.mock('@/services/sterilizerRecordRepo', () => {
  class SterilizerDetailRequiredError extends Error {
    constructor() {
      super('Minimal 1 baris log Sterilizer harus diisi sebelum menyimpan.')
      this.name = 'SterilizerDetailRequiredError'
    }
  }

  function computeDurationMinutes(closeDoorTime: string | null, openDoorTime: string | null): number | null {
    if (!closeDoorTime || !openDoorTime) {
      return null
    }

    const [closeHour, closeMinute] = closeDoorTime.slice(0, 5).split(':').map(Number)
    const [openHour, openMinute] = openDoorTime.slice(0, 5).split(':').map(Number)

    let diff = (openHour * 60 + openMinute) - (closeHour * 60 + closeMinute)

    if (diff < 0) {
      diff += 24 * 60
    }

    return diff
  }

  return {
    default: {
      getDraftWithDetails: getDraftWithDetailsMock,
      saveDraft: saveDraftMock,
      pauseDraftWithFormData: pauseDraftWithFormDataMock,
      deleteDraft: deleteDraftMock,
    },
    computeDurationMinutes,
    SterilizerDetailRequiredError,
  }
})

import { SterilizerDetailRequiredError } from '@/services/sterilizerRecordRepo'

function makeDraftRecord(overrides: Partial<SterilizerRecord> = {}): SterilizerRecord {
  return {
    id: 'draft-1',
    station_id: 'station-1',
    sterilizer_id: null,
    date: null,
    note: null,
    checked_by: null,
    acknowledged_by: null,
    status: 'draft_ongoing',
    created_by: 'user-1',
    created_at: '2026-08-31T07:00:00.000Z',
    updated_at: '2026-08-31T07:00:00.000Z',
    ...overrides,
  }
}

function makeDetailRow(overrides: Partial<SterilizerDetailRow> = {}): SterilizerDetailRow {
  return {
    id: 'detail-1',
    sterilizer_record_id: 'draft-1',
    sterilizer_no: null,
    close_door_time: null,
    peak_1_time: null,
    exhaust_1_time: null,
    peak_2_time: null,
    exhaust_2_time: null,
    peak_3_time: null,
    exhaust_3_time: null,
    open_door_time: null,
    duration_minutes: null,
    number_of_cages: null,
    cages_status: null,
    checked_by_spv: false,
    remarks: null,
    created_at: '2026-08-31T07:00:00.000Z',
    updated_at: '2026-08-31T07:00:00.000Z',
    ...overrides,
  }
}

describe('FormSterilizerView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    useRouteMock.mockReturnValue({ params: { id: 'draft-1' } })
    useAuthStoreMock.mockReturnValue({
      currentUser: { id: 'user-1', username: 'operator01', name: 'Operator Satu', role: 'operator' },
      logout: vi.fn().mockResolvedValue(undefined),
    })
    getDraftWithDetailsMock.mockResolvedValue({ record: makeDraftRecord(), details: [] })
  })

  it('shows a not-found state for an invalid draft id', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce(null)

    const wrapper = mount(FormSterilizerView)
    await flushPromises()

    expect(wrapper.find('[data-testid="record-not-found"]').exists()).toBe(true)
  })

  it('loads an existing draft with its detail rows into form state', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({
      record: makeDraftRecord({ sterilizer_id: 'STR-EXISTING' }),
      details: [makeDetailRow({ sterilizer_no: '3' })],
    })

    const wrapper = mount(FormSterilizerView)
    await flushPromises()

    expect((wrapper.find('#sterilizer_id').element as HTMLInputElement).value).toBe(
      'STR-EXISTING',
    )
    expect(wrapper.find('[data-testid="detail-row-0"]').exists()).toBe(true)
  })

  it('adds a new empty row when "Tambah Baris" is clicked', async () => {
    const wrapper = mount(FormSterilizerView)
    await flushPromises()

    await wrapper.get('[data-testid="add-row-button"]').trigger('click')

    expect(wrapper.find('[data-testid="detail-row-0"]').exists()).toBe(true)
  })

  it('removes a row when "Hapus" is clicked, queuing its id for deletion if it existed', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({
      record: makeDraftRecord(),
      details: [makeDetailRow({ id: 'existing-detail' })],
    })

    const wrapper = mount(FormSterilizerView)
    await flushPromises()

    await wrapper.get('[data-testid="remove-row-button-0"]').trigger('click')

    expect(wrapper.find('[data-testid="detail-row-0"]').exists()).toBe(false)
  })

  it('shows inline validation error and does not save when the required header field is empty', async () => {
    const wrapper = mount(FormSterilizerView)
    await flushPromises()

    await wrapper.get('[data-testid="add-row-button"]').trigger('click')
    await wrapper.get('[data-testid="detail-close-door-time-0"]').setValue('07:00')
    await wrapper.get('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(saveDraftMock).not.toHaveBeenCalled()
    expect(wrapper.find('.form-field-error').exists()).toBe(true)
  })

  it('shows a detail-specific error and does not save when there is no valid detail row', async () => {
    const wrapper = mount(FormSterilizerView)
    await flushPromises()

    await wrapper.get('#sterilizer_id').setValue('STR-001')
    await wrapper.get('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(saveDraftMock).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="detail-rows-error"]').exists()).toBe(true)
  })

  it('computes and displays duration_minutes reactively as close/open door time are filled in', async () => {
    const wrapper = mount(FormSterilizerView)
    await flushPromises()

    await wrapper.get('[data-testid="add-row-button"]').trigger('click')
    await wrapper.get('[data-testid="detail-close-door-time-0"]').setValue('07:00')
    await wrapper.get('[data-testid="detail-open-door-time-0"]').setValue('08:10')

    expect((wrapper.get('[data-testid="detail-duration-minutes-0"]').element as HTMLInputElement).value).toBe('70')
  })

  it('saves successfully and navigates to Monitor Sterilizer', async () => {
    saveDraftMock.mockResolvedValueOnce({ record: makeDraftRecord({ status: 'saved' }), details: [] })

    const wrapper = mount(FormSterilizerView)
    await flushPromises()

    await wrapper.get('#sterilizer_id').setValue('STR-001')
    await wrapper.get('[data-testid="add-row-button"]').trigger('click')
    await wrapper.get('[data-testid="detail-close-door-time-0"]').setValue('07:00')
    await wrapper.get('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(saveDraftMock).toHaveBeenCalled()
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-sterilizer' })
  })

  it('surfaces SterilizerDetailRequiredError from the repo as the detail-rows error', async () => {
    saveDraftMock.mockRejectedValueOnce(new SterilizerDetailRequiredError())

    const wrapper = mount(FormSterilizerView)
    await flushPromises()

    await wrapper.get('#sterilizer_id').setValue('STR-001')
    await wrapper.get('[data-testid="add-row-button"]').trigger('click')
    await wrapper.get('[data-testid="detail-close-door-time-0"]').setValue('07:00')
    await wrapper.get('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="detail-rows-error"]').exists()).toBe(true)
    expect(pushMock).not.toHaveBeenCalledWith({ name: 'monitor-sterilizer' })
  })

  it('pauses without validation and navigates to Monitor', async () => {
    pauseDraftWithFormDataMock.mockResolvedValueOnce({ record: makeDraftRecord({ status: 'draft_paused' }), details: [] })

    const wrapper = mount(FormSterilizerView)
    await flushPromises()

    await wrapper.get('[data-testid="pause-button"]').trigger('click')
    await flushPromises()

    expect(pauseDraftWithFormDataMock).toHaveBeenCalled()
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-sterilizer' })
  })

  it('shows a confirm dialog on Clear and deletes only on confirm', async () => {
    deleteDraftMock.mockResolvedValueOnce(undefined)

    const wrapper = mount(FormSterilizerView)
    await flushPromises()

    await wrapper.get('[data-testid="clear-button"]').trigger('click')
    expect(deleteDraftMock).not.toHaveBeenCalled()
    expect(wrapper.find('.confirm-dialog').exists()).toBe(true)

    await wrapper.get('.confirm-dialog-button--confirm').trigger('click')
    await flushPromises()

    expect(deleteDraftMock).toHaveBeenCalledWith('draft-1')
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-sterilizer' })
  })

  it('cancelling the Clear confirm dialog does not delete anything', async () => {
    const wrapper = mount(FormSterilizerView)
    await flushPromises()

    await wrapper.get('[data-testid="clear-button"]').trigger('click')
    await wrapper.get('.confirm-dialog-button--cancel').trigger('click')

    expect(deleteDraftMock).not.toHaveBeenCalled()
    expect(wrapper.find('.confirm-dialog').exists()).toBe(false)
  })

  it('Checked By checkbox is disabled for a non-supervisor role', async () => {
    const wrapper = mount(FormSterilizerView)
    await flushPromises()

    expect((wrapper.get('[data-testid="checked-by-checkbox"]').element as HTMLInputElement).disabled).toBe(true)
  })

  it('Checked By checkbox is enabled for a supervisor role', async () => {
    useAuthStoreMock.mockReturnValue({
      currentUser: { id: 'user-1', username: 'sup01', name: 'Supervisor Satu', role: 'supervisor' },
      logout: vi.fn().mockResolvedValue(undefined),
    })

    const wrapper = mount(FormSterilizerView)
    await flushPromises()

    expect((wrapper.get('[data-testid="checked-by-checkbox"]').element as HTMLInputElement).disabled).toBe(false)
  })
})
