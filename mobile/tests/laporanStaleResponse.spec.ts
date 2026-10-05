/**
 * laporanStaleResponse.spec.ts — audit 2026-10-05: kelima layar Laporan
 * mobile (Sterilizer, Cages Track, Boiler Room, Clarification, Storage Tank)
 * memuat ringkasan tanpa urutan permintaan. Mengganti periode/line dengan
 * cepat membuat respons LAMA yang tiba belakangan menimpa ringkasan BARU —
 * angka periode A tampil di bawah pilihan periode B.
 *
 * Respons dikendalikan dengan promise tertunda (deferred) supaya urutan
 * tibanya bisa dibalik secara deterministik. Seperti spec Laporan per layar,
 * yang di-stub hanya apiClient — repo laporan berjalan sungguhan.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import type { Component } from 'vue'
import LaporanSterilizerView from '@/views/LaporanSterilizerView.vue'
import LaporanCagesTrackView from '@/views/LaporanCagesTrackView.vue'
import LaporanBoilerRoomView from '@/views/LaporanBoilerRoomView.vue'
import LaporanClarificationView from '@/views/LaporanClarificationView.vue'
import LaporanStorageTankView from '@/views/LaporanStorageTankView.vue'
import sterilizerReportRepo from '@/services/sterilizerReportRepo'
import cagesTrackReportRepo from '@/services/cagesTrackReportRepo'
import boilerRoomReportRepo from '@/services/boilerRoomReportRepo'
import clarificationReportRepo from '@/services/clarificationReportRepo'
import storageTankReportRepo from '@/services/storageTankReportRepo'

const { pushMock } = vi.hoisted(() => ({ pushMock: vi.fn() }))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
  useRoute: () => ({ params: {}, query: {} }),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    currentUser: { id: 'user-1', username: 'supervisor01', name: 'Pengguna Uji', role: 'supervisor', business_unit_id: 'bu-1' },
    businessUnit: { id: 'bu-1', name: 'Mill Utara' },
    logout: vi.fn(),
  }),
}))

vi.mock('@/stores/floatingClock', () => ({
  useFloatingClockStore: () => ({ enabled: false, toggle: vi.fn() }),
}))

vi.mock('@/stores/aiAssistant', () => ({
  useAiAssistantStore: () => ({ isOpen: false, bubbleEnabled: true, open: vi.fn(), close: vi.fn(), toggleBubble: vi.fn() }),
}))

const { apiGetMock } = vi.hoisted(() => ({ apiGetMock: vi.fn() }))

vi.mock('@/services/apiClient', () => ({
  default: { get: apiGetMock },
}))

interface Deferred<T> {
  promise: Promise<T>
  resolve: (value: T) => void
  reject: (error: unknown) => void
}

function deferred<T>(): Deferred<T> {
  let resolve!: (value: T) => void
  let reject!: (error: unknown) => void
  const promise = new Promise<T>((res, rej) => {
    resolve = res
    reject = rej
  })
  return { promise, resolve, reject }
}

const SCREENS: Array<{ name: string; view: Component; prefix: string; stationType: string; repo: { saveCsvFile: (b: Blob, f: string) => void } }> = [
  { name: 'Sterilizer', view: LaporanSterilizerView, prefix: 'sterilizer-reports', stationType: 'sterilizer', repo: sterilizerReportRepo },
  { name: 'Cages Track', view: LaporanCagesTrackView, prefix: 'cages-track-reports', stationType: 'cages-track', repo: cagesTrackReportRepo },
  { name: 'Boiler Room', view: LaporanBoilerRoomView, prefix: 'boiler-room-reports', stationType: 'boiler-room', repo: boilerRoomReportRepo },
  { name: 'Clarification', view: LaporanClarificationView, prefix: 'clarification-reports', stationType: 'clarification', repo: clarificationReportRepo },
  { name: 'Storage Tank', view: LaporanStorageTankView, prefix: 'storage-tank-reports', stationType: 'storage-tank', repo: storageTankReportRepo },
]

const LINES = [
  { id: 'pl-1', name: 'Line 1', code: 'L1' },
  { id: 'pl-2', name: 'Line 2', code: 'L2' },
]

function period(id: string, name: string, stationType: string) {
  return { id, name, start_date: '2026-08-01', end_date: '2026-08-31', status: 'open', station_type: stationType, station_type_label: stationType }
}

function summaryFor(periodId: string, periodName: string, lineId: string) {
  return {
    period: { id: periodId, name: periodName, start_date: '2026-08-01', end_date: '2026-08-31', status: 'open', business_unit_name: 'Mill Utara' },
    production_line: LINES.find((l) => l.id === lineId),
  }
}

for (const screen of SCREENS) {
  describe(`Laporan ${screen.name} — respons ringkasan basi diabaikan`, () => {
    let wrapper: VueWrapper | undefined
    /** Antrean respons ringkasan per permintaan, sesuai urutan dikirim. */
    let summaryCalls: Array<{ params: Record<string, string>; d: Deferred<{ data: unknown }> }>
    let exportCalls: Array<{ params: Record<string, string>; d: Deferred<{ data: unknown }> }>

    beforeEach(() => {
      pushMock.mockReset()
      apiGetMock.mockReset()
      summaryCalls = []
      exportCalls = []
      apiGetMock.mockImplementation((url: string, config?: { params?: Record<string, string> }) => {
        if (url.includes('/production-lines/options-for-report')) return Promise.resolve({ data: { data: LINES } })
        if (url.endsWith(`/${screen.prefix}/periods`)) {
          return Promise.resolve({
            data: { data: [period('per-a', 'Periode Agustus', screen.stationType), period('per-b', 'Periode September', screen.stationType)] },
          })
        }
        if (url.endsWith(`/${screen.prefix}/summary`)) {
          const d = deferred<{ data: unknown }>()
          summaryCalls.push({ params: config?.params ?? {}, d })
          return d.promise
        }
        if (url.endsWith(`/${screen.prefix}/export`)) {
          const d = deferred<{ data: unknown }>()
          exportCalls.push({ params: config?.params ?? {}, d })
          return d.promise
        }
        return Promise.reject(new Error(`URL tak terduga: ${url}`))
      })
    })

    afterEach(() => {
      wrapper?.unmount()
      wrapper = undefined
      vi.restoreAllMocks()
    })

    async function mountAndPickLine(): Promise<VueWrapper> {
      const w = mount(screen.view)
      await flushPromises()
      await w.get('[data-testid="production-line-select"]').setValue('pl-1')
      await flushPromises()
      return w
    }

    function shownPeriodName(w: VueWrapper): string | null {
      const el = w.find('.period-meta-name')
      return el.exists() ? el.text() : null
    }

    it('ganti periode A → B, respons A tiba SETELAH B: yang tampil tetap ringkasan B', async () => {
      wrapper = await mountAndPickLine()

      await wrapper.get('[data-testid="period-select"]').setValue('per-a')
      await flushPromises()
      await wrapper.get('[data-testid="period-select"]').setValue('per-b')
      await flushPromises()
      expect(summaryCalls.map((c) => c.params.period_id)).toEqual(['per-a', 'per-b'])

      summaryCalls[1].d.resolve({ data: summaryFor('per-b', 'Periode September', 'pl-1') })
      await flushPromises()
      expect(shownPeriodName(wrapper)).toBe('Periode September')

      summaryCalls[0].d.resolve({ data: summaryFor('per-a', 'Periode Agustus', 'pl-1') })
      await flushPromises()
      expect(shownPeriodName(wrapper)).toBe('Periode September')
      expect(wrapper.find('[data-testid="summary-loading"]').exists()).toBe(false)
    })

    it('respons lama yang GAGAL setelah respons baru berhasil tidak memunculkan galat', async () => {
      wrapper = await mountAndPickLine()

      await wrapper.get('[data-testid="period-select"]').setValue('per-a')
      await flushPromises()
      await wrapper.get('[data-testid="period-select"]').setValue('per-b')
      await flushPromises()

      summaryCalls[1].d.resolve({ data: summaryFor('per-b', 'Periode September', 'pl-1') })
      await flushPromises()
      summaryCalls[0].d.reject({ message: 'Tidak dapat terhubung ke server.' })
      await flushPromises()

      expect(shownPeriodName(wrapper)).toBe('Periode September')
      expect(wrapper.text()).not.toContain('Tidak dapat terhubung ke server.')
    })

    it('respons lama tidak mematikan indikator muat milik permintaan baru yang masih berjalan', async () => {
      wrapper = await mountAndPickLine()

      await wrapper.get('[data-testid="period-select"]').setValue('per-a')
      await flushPromises()
      await wrapper.get('[data-testid="period-select"]').setValue('per-b')
      await flushPromises()

      summaryCalls[0].d.resolve({ data: summaryFor('per-a', 'Periode Agustus', 'pl-1') })
      await flushPromises()

      expect(shownPeriodName(wrapper)).toBeNull()
      expect(wrapper.find('[data-testid="summary-loading"]').exists()).toBe(true)

      summaryCalls[1].d.resolve({ data: summaryFor('per-b', 'Periode September', 'pl-1') })
      await flushPromises()
      expect(shownPeriodName(wrapper)).toBe('Periode September')
      expect(wrapper.find('[data-testid="summary-loading"]').exists()).toBe(false)
    })

    it('ganti line saat ringkasan line lama masih dimuat: angka line lama tidak pernah tampil', async () => {
      wrapper = await mountAndPickLine()
      await wrapper.get('[data-testid="period-select"]').setValue('per-a')
      await flushPromises()

      await wrapper.get('[data-testid="production-line-select"]').setValue('pl-2')
      await flushPromises()
      expect(summaryCalls.map((c) => c.params.production_line_id)).toEqual(['pl-1', 'pl-2'])

      summaryCalls[1].d.resolve({ data: summaryFor('per-a', 'Periode Agustus (Line 2)', 'pl-2') })
      await flushPromises()
      summaryCalls[0].d.resolve({ data: summaryFor('per-a', 'Periode Agustus (Line 1)', 'pl-1') })
      await flushPromises()

      expect(shownPeriodName(wrapper)).toBe('Periode Agustus (Line 2)')
    })

    it('periode dikosongkan saat ringkasan masih dimuat: respons yang tiba kemudian tidak memunculkan angka', async () => {
      wrapper = await mountAndPickLine()
      await wrapper.get('[data-testid="period-select"]').setValue('per-a')
      await flushPromises()

      await wrapper.get('[data-testid="period-select"]').setValue('')
      await flushPromises()
      summaryCalls[0].d.resolve({ data: summaryFor('per-a', 'Periode Agustus', 'pl-1') })
      await flushPromises()

      expect(shownPeriodName(wrapper)).toBeNull()
      expect(wrapper.find('[data-testid="summary-loading"]').exists()).toBe(false)
    })

    it('nama berkas ekspor mengikuti periode yang DIEKSPOR, bukan periode yang dipilih saat berkas tiba', async () => {
      const save = vi.spyOn(screen.repo, 'saveCsvFile').mockImplementation(() => {})
      wrapper = await mountAndPickLine()
      await wrapper.get('[data-testid="period-select"]').setValue('per-a')
      await flushPromises()
      summaryCalls[0].d.resolve({ data: summaryFor('per-a', 'Periode Agustus', 'pl-1') })
      await flushPromises()

      await wrapper.get('[data-testid="export-button"]').trigger('click')
      await flushPromises()
      expect(exportCalls).toHaveLength(1)
      expect(exportCalls[0].params.period_id).toBe('per-a')

      await wrapper.get('[data-testid="period-select"]').setValue('per-b')
      await flushPromises()
      exportCalls[0].d.resolve({ data: new Blob(['a,b\n']) })
      await flushPromises()

      expect(save).toHaveBeenCalledTimes(1)
      expect(save.mock.calls[0][1]).toContain('periode-agustus')
    })
  })
}
