/**
 * FormKernelDispatchView.spec.ts — screen-073--form-kernel-dispatch
 * / usecase-074--form-kernel-dispatch.
 *
 * Mirrors FormSolidWasteDisposalView.spec.ts's mocking strategy, minus the
 * grid/N-column/time-slot-ordering concerns (Kernel Dispatch is a pure
 * event log — rows are added freely, no shared checkbox columns, no
 * per-row uniqueness constraint).
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import FormKernelDispatchView from '@/views/FormKernelDispatchView.vue'
import type { KernelDispatchDetailRow, KernelDispatchRecord } from '@/services/kernelDispatchRecordRepo'

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

vi.mock('@/services/kernelDispatchRecordRepo', () => {
  class KernelDispatchDetailRequiredError extends Error {
    constructor() {
      super('Minimal 1 baris log Kernel Dispatch harus diisi sebelum menyimpan.')
      this.name = 'KernelDispatchDetailRequiredError'
    }
  }

  return {
    default: {
      getDraftWithDetails: getDraftWithDetailsMock,
      saveDraft: saveDraftMock,
      pauseDraftWithFormData: pauseDraftWithFormDataMock,
      deleteDraft: deleteDraftMock,
    },
    KernelDispatchDetailRequiredError,
  }
})

import { KernelDispatchDetailRequiredError } from '@/services/kernelDispatchRecordRepo'

function makeDraftRecord(overrides: Partial<KernelDispatchRecord> = {}): KernelDispatchRecord {
  return {
    id: 'draft-1',
    station_id: 'station-1',
    kernel_dispatch_id: null,
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

function makeDetailRow(overrides: Partial<KernelDispatchDetailRow> = {}): KernelDispatchDetailRow {
  return {
    id: 'detail-1',
    kernel_dispatch_record_id: 'draft-1',
    event_date: '2026-08-31',
    shift: null,
    weighbridge_ticket_no: null,
    waybill_number: null,
    transporter_contractor: null,
    vehicle_plate_no: null,
    driver_name: null,
    silo_source_id: null,
    destination_buyer: null,
    gross_weight_mt: null,
    tare_weight_mt: null,
    net_weight_mt: null,
    kernel_moisture_percent: null,
    dirt_impurities_percent: null,
    ffa_percent: null,
    broken_kernel_percent: null,
    security_seal_no_top: null,
    security_seal_no_bottom: null,
    weighbridge_operator_id: null,
    remarks_gate_status: null,
    findings: null,
    created_at: '2026-08-31T07:00:00.000Z',
    updated_at: '2026-08-31T07:00:00.000Z',
    ...overrides,
  }
}

describe('FormKernelDispatchView', () => {
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

    const wrapper = mount(FormKernelDispatchView)
    await flushPromises()

    expect(wrapper.find('[data-testid="record-not-found"]').exists()).toBe(true)
  })

  it('loads an existing draft with its detail rows into form state', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({
      record: makeDraftRecord({ kernel_dispatch_id: 'KD-EXISTING' }),
      details: [makeDetailRow({ vehicle_plate_no: 'B 1234 XY' })],
    })

    const wrapper = mount(FormKernelDispatchView)
    await flushPromises()

    expect((wrapper.find('#kernel_dispatch_id').element as HTMLInputElement).value).toBe(
      'KD-EXISTING',
    )
    expect(wrapper.find('[data-testid="detail-row-0"]').exists()).toBe(true)
  })

  it('adds a new empty row when "Tambah Baris" is clicked', async () => {
    const wrapper = mount(FormKernelDispatchView)
    await flushPromises()

    await wrapper.get('[data-testid="add-row-button"]').trigger('click')

    expect(wrapper.find('[data-testid="detail-row-0"]').exists()).toBe(true)
  })

  it('removes a row when "Hapus" is clicked, queuing its id for deletion if it existed', async () => {
    getDraftWithDetailsMock.mockResolvedValueOnce({
      record: makeDraftRecord(),
      details: [makeDetailRow({ id: 'existing-detail' })],
    })

    const wrapper = mount(FormKernelDispatchView)
    await flushPromises()

    await wrapper.get('[data-testid="remove-row-button-0"]').trigger('click')

    expect(wrapper.find('[data-testid="detail-row-0"]').exists()).toBe(false)
  })

  it('shows inline validation error and does not save when the required header field is empty', async () => {
    const wrapper = mount(FormKernelDispatchView)
    await flushPromises()

    await wrapper.get('[data-testid="add-row-button"]').trigger('click')
    await wrapper.get('[data-testid="detail-event-date-0"]').setValue('2026-08-31')
    await wrapper.get('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(saveDraftMock).not.toHaveBeenCalled()
    expect(wrapper.find('.form-field-error').exists()).toBe(true)
  })

  it('shows a detail-specific error and does not save when there is no valid detail row', async () => {
    const wrapper = mount(FormKernelDispatchView)
    await flushPromises()

    await wrapper.get('#kernel_dispatch_id').setValue('KD-001')
    await wrapper.get('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(saveDraftMock).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="detail-rows-error"]').exists()).toBe(true)
  })

  it('saves successfully and navigates to Monitor Kernel Dispatch', async () => {
    saveDraftMock.mockResolvedValueOnce({ record: makeDraftRecord({ status: 'saved' }), details: [] })

    const wrapper = mount(FormKernelDispatchView)
    await flushPromises()

    await wrapper.get('#kernel_dispatch_id').setValue('KD-001')
    await wrapper.get('[data-testid="add-row-button"]').trigger('click')
    await wrapper.get('[data-testid="detail-event-date-0"]').setValue('2026-08-31')
    await wrapper.get('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(saveDraftMock).toHaveBeenCalled()
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-kernel-dispatch' })
  })

  it('surfaces KernelDispatchDetailRequiredError from the repo as the detail-rows error', async () => {
    saveDraftMock.mockRejectedValueOnce(new KernelDispatchDetailRequiredError())

    const wrapper = mount(FormKernelDispatchView)
    await flushPromises()

    await wrapper.get('#kernel_dispatch_id').setValue('KD-001')
    await wrapper.get('[data-testid="add-row-button"]').trigger('click')
    await wrapper.get('[data-testid="detail-event-date-0"]').setValue('2026-08-31')
    await wrapper.get('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="detail-rows-error"]').exists()).toBe(true)
    expect(pushMock).not.toHaveBeenCalledWith({ name: 'monitor-kernel-dispatch' })
  })

  it('pauses without validation and navigates to Monitor', async () => {
    pauseDraftWithFormDataMock.mockResolvedValueOnce({ record: makeDraftRecord({ status: 'draft_paused' }), details: [] })

    const wrapper = mount(FormKernelDispatchView)
    await flushPromises()

    await wrapper.get('[data-testid="pause-button"]').trigger('click')
    await flushPromises()

    expect(pauseDraftWithFormDataMock).toHaveBeenCalled()
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-kernel-dispatch' })
  })

  it('shows a confirm dialog on Clear and deletes only on confirm', async () => {
    deleteDraftMock.mockResolvedValueOnce(undefined)

    const wrapper = mount(FormKernelDispatchView)
    await flushPromises()

    await wrapper.get('[data-testid="clear-button"]').trigger('click')
    expect(deleteDraftMock).not.toHaveBeenCalled()
    expect(wrapper.find('.confirm-dialog').exists()).toBe(true)

    await wrapper.get('.confirm-dialog-button--confirm').trigger('click')
    await flushPromises()

    expect(deleteDraftMock).toHaveBeenCalledWith('draft-1')
    expect(pushMock).toHaveBeenCalledWith({ name: 'monitor-kernel-dispatch' })
  })

  it('cancelling the Clear confirm dialog does not delete anything', async () => {
    const wrapper = mount(FormKernelDispatchView)
    await flushPromises()

    await wrapper.get('[data-testid="clear-button"]').trigger('click')
    await wrapper.get('.confirm-dialog-button--cancel').trigger('click')

    expect(deleteDraftMock).not.toHaveBeenCalled()
    expect(wrapper.find('.confirm-dialog').exists()).toBe(false)
  })

  it('Checked By checkbox is disabled for a non-supervisor role', async () => {
    const wrapper = mount(FormKernelDispatchView)
    await flushPromises()

    expect((wrapper.get('[data-testid="checked-by-checkbox"]').element as HTMLInputElement).disabled).toBe(true)
  })

  it('Checked By checkbox is enabled for a supervisor role', async () => {
    useAuthStoreMock.mockReturnValue({
      currentUser: { id: 'user-1', username: 'sup01', name: 'Supervisor Satu', role: 'supervisor' },
      logout: vi.fn().mockResolvedValue(undefined),
    })

    const wrapper = mount(FormKernelDispatchView)
    await flushPromises()

    expect((wrapper.get('[data-testid="checked-by-checkbox"]').element as HTMLInputElement).disabled).toBe(false)
  })
})
