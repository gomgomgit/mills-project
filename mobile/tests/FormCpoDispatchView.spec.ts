/**
 * FormCpoDispatchView.spec.ts — screen-074--form-cpo-dispatch
 * / usecase-080--form-cpo-dispatch.
 *
 * Mirrors FormKernelDispatchView.spec.ts's mocking strategy, minus the
 * grid/N-column/time-slot-ordering concerns (CPO Dispatch is a pure
 * event log — rows are added freely, no shared checkbox columns, no
 * per-row uniqueness constraint).
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import FormCpoDispatchView from '@/views/FormCpoDispatchView.vue'
import type { CpoDispatchDetailRow, CpoDispatchRecord } from '@/services/cpoDispatchRecordRepo'

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

vi.mock('@/services/cpoDispatchRecordRepo', () => {
  class CpoDispatchDetailRequiredError extends Error {
    constructor() {
      super('Minimal 1 baris log CPO Dispatch harus diisi sebelum menyimpan.')
      this.name = 'CpoDispatchDetailRequiredError'
    }
  }

  return {
    default: {
      getDraftWithDetails: getDraftWithDetailsMock,
      saveDraft: saveDraftMock,
      pauseDraftWithFormData: pauseDraftWithFormDataMock,
      deleteDraft: deleteDraftMock,
    },
    CpoDispatchDetailRequiredError,
  }
})

import { CpoDispatchDetailRequiredError } from '@/services/cpoDispatchRecordRepo'

function makeDraftRecord(overrides: Partial<CpoDispatchRecord> = {}): CpoDispatchRecord {
  return {
    id: 'draft-1',
    station_id: 'station-1',
    cpo_dispatch_id: null,
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

function makeDetailRow(overrides: Partial<CpoDispatchDetailRow> = {}): CpoDispatchDetailRow {
  return {
    id: 'detail-1',
    cpo_dispatch_record_id: 'draft-1',
    event_date: '2026-08-31',
    shift: null,
    time_in: null,
    time_out: null,
    waybill_number: null,
    tanker_plate_no: null,
    transport_company: null,
    driver_name: null,
    storage_tank_source: null,
    seal_no_top: null,
    seal_no_bottom: null,
    gross_weight_mt: null,
    tare_weight_mt: null,
    net_weight_mt: null,
    ffa_percent: null,
    moisture_percent: null,
    impurities_percent: null,
    dobi: null,
    destination_buyer: null,
    weighbridge_operator: null,
    findings: null,
    created_at: '2026-08-31T07:00:00.000Z',
    updated_at: '2026-08-31T07:00:00.000Z',
    ...overrides,
  }
}

describe('FormCpoDispatchView', () => {
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

    const wrapper = mount(FormCpoDispatchView)
    await flushPromises()

    expect(wrapper.find('[data-testid="record-not-found"]').exists()).toBe(true)
  })

  it('loads an existing draft with its detail rows into form state', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({
      record: makeDraftRecord({ cpo_dispatch_id: 'CD-EXISTING' }),
      details: [makeDetailRow({ tanker_plate_no: 'B 1234 XY' })],
    })

    const wrapper = mount(FormCpoDispatchView)
    await flushPromises()

    expect((wrapper.find('#cpo_dispatch_id').element as HTMLInputElement).value).toBe(
      'CD-EXISTING',
    )
    expect(wrapper.find('[data-testid="detail-row-0"]').exists()).toBe(true)
  })

  it('adds a new empty row when "Tambah Baris" is clicked', async () => {
    const wrapper = mount(FormCpoDispatchView)
    await flushPromises()

    await wrapper.get('[data-testid="add-row-button"]').trigger('click')

    expect(wrapper.find('[data-testid="detail-row-0"]').exists()).toBe(true)
  })

  it('removes a row when "Hapus" is clicked, queuing its id for deletion if it existed', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({
      record: makeDraftRecord(),
      details: [makeDetailRow({ id: 'existing-detail' })],
    })

    const wrapper = mount(FormCpoDispatchView)
    await flushPromises()

    await wrapper.get('[data-testid="remove-row-button-0"]').trigger('click')

    expect(wrapper.find('[data-testid="detail-row-0"]').exists()).toBe(false)
  })

  it('shows inline validation error and does not save when the required header field is empty', async () => {
    const wrapper = mount(FormCpoDispatchView)
    await flushPromises()

    await wrapper.get('[data-testid="add-row-button"]').trigger('click')
    await wrapper.get('[data-testid="detail-event-date-0"]').setValue('2026-08-31')
    await wrapper.get('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(saveDraftMock).not.toHaveBeenCalled()
    expect(wrapper.find('.form-field-error').exists()).toBe(true)
  })

  it('shows a detail-specific error and does not save when there is no valid detail row', async () => {
    const wrapper = mount(FormCpoDispatchView)
    await flushPromises()

    await wrapper.get('#cpo_dispatch_id').setValue('CD-001')
    await wrapper.get('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(saveDraftMock).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="detail-rows-error"]').exists()).toBe(true)
  })

  it('saves successfully and navigates to Monitor CPO Dispatch', async () => {
    saveDraftMock.mockResolvedValueOnce({ record: makeDraftRecord({ status: 'saved' }), details: [] })

    const wrapper = mount(FormCpoDispatchView)
    await flushPromises()

    await wrapper.get('#cpo_dispatch_id').setValue('CD-001')
    await wrapper.get('[data-testid="add-row-button"]').trigger('click')
    await wrapper.get('[data-testid="detail-event-date-0"]').setValue('2026-08-31')
    await wrapper.get('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(saveDraftMock).toHaveBeenCalled()
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-cpo-dispatch' })
  })

  it('surfaces CpoDispatchDetailRequiredError from the repo as the detail-rows error', async () => {
    saveDraftMock.mockRejectedValueOnce(new CpoDispatchDetailRequiredError())

    const wrapper = mount(FormCpoDispatchView)
    await flushPromises()

    await wrapper.get('#cpo_dispatch_id').setValue('CD-001')
    await wrapper.get('[data-testid="add-row-button"]').trigger('click')
    await wrapper.get('[data-testid="detail-event-date-0"]').setValue('2026-08-31')
    await wrapper.get('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="detail-rows-error"]').exists()).toBe(true)
    expect(pushMock).not.toHaveBeenCalledWith({ name: 'monitor-cpo-dispatch' })
  })

  it('pauses without validation and navigates to Monitor', async () => {
    pauseDraftWithFormDataMock.mockResolvedValueOnce({ record: makeDraftRecord({ status: 'draft_paused' }), details: [] })

    const wrapper = mount(FormCpoDispatchView)
    await flushPromises()

    await wrapper.get('[data-testid="pause-button"]').trigger('click')
    await flushPromises()

    expect(pauseDraftWithFormDataMock).toHaveBeenCalled()
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-cpo-dispatch' })
  })

  it('shows a confirm dialog on Clear and deletes only on confirm', async () => {
    deleteDraftMock.mockResolvedValueOnce(undefined)

    const wrapper = mount(FormCpoDispatchView)
    await flushPromises()

    await wrapper.get('[data-testid="clear-button"]').trigger('click')
    expect(deleteDraftMock).not.toHaveBeenCalled()
    expect(wrapper.find('.confirm-dialog').exists()).toBe(true)

    await wrapper.get('.confirm-dialog-button--confirm').trigger('click')
    await flushPromises()

    expect(deleteDraftMock).toHaveBeenCalledWith('draft-1')
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-cpo-dispatch' })
  })

  it('cancelling the Clear confirm dialog does not delete anything', async () => {
    const wrapper = mount(FormCpoDispatchView)
    await flushPromises()

    await wrapper.get('[data-testid="clear-button"]').trigger('click')
    await wrapper.get('.confirm-dialog-button--cancel').trigger('click')

    expect(deleteDraftMock).not.toHaveBeenCalled()
    expect(wrapper.find('.confirm-dialog').exists()).toBe(false)
  })

  it('Checked By checkbox is disabled for a non-supervisor role', async () => {
    const wrapper = mount(FormCpoDispatchView)
    await flushPromises()

    expect((wrapper.get('[data-testid="checked-by-checkbox"]').element as HTMLInputElement).disabled).toBe(true)
  })

  it('Checked By checkbox is enabled for a supervisor role', async () => {
    useAuthStoreMock.mockReturnValue({
      currentUser: { id: 'user-1', username: 'sup01', name: 'Supervisor Satu', role: 'supervisor' },
      logout: vi.fn().mockResolvedValue(undefined),
    })

    const wrapper = mount(FormCpoDispatchView)
    await flushPromises()

    expect((wrapper.get('[data-testid="checked-by-checkbox"]').element as HTMLInputElement).disabled).toBe(false)
  })
})
