/**
 * LoadingComponents.spec.ts — komponen loading bersama (audit loading state
 * 2026-10-05): LoadingState, BusyLabel, LoadingOverlay, LoadingSpinner, dan
 * composable useBusyAction.
 */
import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import LoadingState from '@/components/loading/LoadingState.vue'
import BusyLabel from '@/components/loading/BusyLabel.vue'
import LoadingOverlay from '@/components/loading/LoadingOverlay.vue'
import LoadingSpinner from '@/components/loading/LoadingSpinner.vue'
import { useBusyAction } from '@/composables/useBusyAction'

describe('LoadingSpinner', () => {
  it('dekoratif: aria-hidden, ukuran dari prop, warna currentColor', () => {
    const wrapper = mount(LoadingSpinner, { props: { size: 24 } })
    const svg = wrapper.get('svg')

    expect(svg.attributes('aria-hidden')).toBe('true')
    expect(svg.attributes('width')).toBe('24')
    expect(svg.html()).toContain('stroke="currentColor"')
  })
})

describe('LoadingState', () => {
  it('mengumumkan status: role=status, aria-live=polite, aria-busy, teks slot', () => {
    const wrapper = mount(LoadingState, { slots: { default: 'Memuat daftar draft…' } })
    const root = wrapper.get('[data-testid="loading-state"]')

    expect(root.attributes('role')).toBe('status')
    expect(root.attributes('aria-live')).toBe('polite')
    expect(root.attributes('aria-busy')).toBe('true')
    expect(root.text()).toBe('Memuat daftar draft…')
    expect(root.find('[data-testid="loading-spinner"]').exists()).toBe(true)
  })

  it('memakai prop label bila tidak ada slot, dan test-id dapat diganti', () => {
    const wrapper = mount(LoadingState, { props: { label: 'Memuat…', testId: 'x-loading' } })

    expect(wrapper.get('[data-testid="x-loading"]').text()).toBe('Memuat…')
  })

  it('jeda anti-kedip diteruskan sebagai CSS var (default 150ms)', () => {
    expect(mount(LoadingState).get('.loading-state').attributes('style')).toContain('--loading-delay: 150ms')
    expect(mount(LoadingState, { props: { delay: 0 } }).get('.loading-state').attributes('style')).toContain(
      '--loading-delay: 0ms',
    )
  })

  it('variant text tidak merender kerangka', () => {
    expect(mount(LoadingState).find('.loading-skeleton').exists()).toBe(false)
  })

  it.each([
    ['list', 4, '.loading-skeleton-block--list'],
    ['grid', 6, '.loading-skeleton-block--grid'],
    ['form', 3, '.loading-skeleton-field'],
  ] as const)('variant %s merender %i butir kerangka aria-hidden', (variant, rows, selector) => {
    const wrapper = mount(LoadingState, { props: { variant, rows } })
    const skeleton = wrapper.get('.loading-skeleton')

    expect(skeleton.attributes('aria-hidden')).toBe('true')
    expect(skeleton.findAll(selector)).toHaveLength(rows)
  })

  it('variant card merender satu kartu kerangka', () => {
    const wrapper = mount(LoadingState, { props: { variant: 'card' } })

    expect(wrapper.findAll('.loading-skeleton-block--card')).toHaveLength(1)
  })
})

describe('BusyLabel', () => {
  it('tidak sibuk: hanya label, tanpa spinner', () => {
    const wrapper = mount(BusyLabel, { props: { busy: false, label: 'Simpan', busyLabel: 'Menyimpan…' } })

    expect(wrapper.text()).toBe('Simpan')
    expect(wrapper.find('[data-testid="loading-spinner"]').exists()).toBe(false)
  })

  it('sibuk: spinner + label sibuk, textContent persis tanpa spasi tambahan', () => {
    const wrapper = mount(BusyLabel, { props: { busy: true, label: 'Simpan', busyLabel: 'Menyimpan…' } })

    expect(wrapper.element.textContent).toBe('Menyimpan…')
    expect(wrapper.find('[data-testid="loading-spinner"]').exists()).toBe(true)
  })

  it('label sibuk default "Memproses…"', () => {
    const wrapper = mount(BusyLabel, { props: { busy: true, label: 'Login' } })

    expect(wrapper.text()).toBe('Memproses…')
  })
})

describe('BusyLabel iconOnly (footer Form yang sempit)', () => {
  it('sibuk: spinner di tengah, label biasa memegang lebar lewat data-label, label sibuk sebagai teks pembaca layar', () => {
    const wrapper = mount(BusyLabel, { props: { busy: true, label: 'Pause', busyLabel: 'Menyimpan…', iconOnly: true } })
    const root = wrapper.get('.busy-label--icon-only')

    expect(root.attributes('data-label')).toBe('Pause')
    expect(wrapper.element.textContent).toBe('Menyimpan…')
    expect(wrapper.get('.busy-label-sr').text()).toBe('Menyimpan…')
    expect(wrapper.find('[data-testid="loading-spinner"]').exists()).toBe(true)
  })

  it('tidak sibuk: sama dengan mode biasa', () => {
    const wrapper = mount(BusyLabel, { props: { busy: false, label: 'Pause', busyLabel: 'Menyimpan…', iconOnly: true } })

    expect(wrapper.element.textContent).toBe('Pause')
    expect(wrapper.find('.busy-label--icon-only').exists()).toBe(false)
  })
})

describe('LoadingOverlay', () => {
  it('merender kartu status di atas lapisan penuh layar', () => {
    const wrapper = mount(LoadingOverlay, { props: { label: 'Keluar…' } })
    const overlay = wrapper.get('[data-testid="loading-overlay"]')

    expect(overlay.get('[role="status"]').attributes('aria-busy')).toBe('true')
    expect(overlay.text()).toBe('Keluar…')
  })
})

describe('useBusyAction', () => {
  it('menolak aksi kedua selama yang pertama berjalan, lalu terbuka lagi', async () => {
    const { busy, run } = useBusyAction()
    let release!: () => void
    let calls = 0
    const action = () => {
      calls += 1
      return new Promise<string>((resolve) => {
        release = () => resolve('selesai')
      })
    }

    const first = run(action)
    const second = run(action)

    expect(busy.value).toBe(true)
    expect(calls).toBe(1)
    await expect(second).resolves.toBeUndefined()

    release()
    await expect(first).resolves.toBe('selesai')
    expect(busy.value).toBe(false)

    void run(action)
    expect(calls).toBe(2)
  })

  it('busy kembali false walau aksi gagal', async () => {
    const { busy, run } = useBusyAction()

    await expect(run(() => Promise.reject(new Error('gagal')))).rejects.toThrow('gagal')
    expect(busy.value).toBe(false)
  })
})
