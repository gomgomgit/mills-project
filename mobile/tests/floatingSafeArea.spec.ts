/**
 * floatingSafeArea.spec.ts — ruang aman bawah untuk bubble AI / jam
 * mengambang (audit 2026-10-04: bubble menutupi Load Data, Clear/Simpan,
 * pesan error, kartu cakupan laporan di 390px).
 *
 * Menguji App.vue sungguhan (router + store asli): root <main> layar
 * mendapat kelas `app-route-view`, dan <html> mendapat variabel
 * `--floating-safe-bottom` + atribut pengaktif yang mengikuti toggle
 * bubble/jam. Geometri sebenarnya dibuktikan di browser (Playwright).
 */
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { defineComponent, h, nextTick } from 'vue'
import App from '@/App.vue'
import { useAiAssistantStore } from '@/stores/aiAssistant'
import { useFloatingClockStore } from '@/stores/floatingClock'
import {
  AI_BUBBLE_TOP_FROM_BOTTOM_PX,
  FLOATING_CLOCK_TOP_FROM_BOTTOM_PX,
  floatingSafeBottomPx,
} from '@/utils/floatingSafeArea'

const Screen = defineComponent({ render: () => h('main', { class: 'some-screen' }, 'isi') })

describe('floatingSafeBottomPx', () => {
  it('bubble aktif: ruang aman melewati tepi atas bubble', () => {
    expect(floatingSafeBottomPx({ bubbleEnabled: true, clockEnabled: false })).toBeGreaterThan(AI_BUBBLE_TOP_FROM_BOTTOM_PX)
    expect(floatingSafeBottomPx({ bubbleEnabled: true, clockEnabled: true })).toBeGreaterThan(AI_BUBBLE_TOP_FROM_BOTTOM_PX)
  })

  it('hanya jam aktif: ruang aman melewati tepi atas jam', () => {
    expect(floatingSafeBottomPx({ bubbleEnabled: false, clockEnabled: true })).toBeGreaterThan(FLOATING_CLOCK_TOP_FROM_BOTTOM_PX)
  })

  it('tidak ada elemen mengambang: 0', () => {
    expect(floatingSafeBottomPx({ bubbleEnabled: false, clockEnabled: false })).toBe(0)
  })
})

describe('App.vue — ruang aman bawah', () => {
  let wrapper: VueWrapper | undefined

  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
  })

  afterEach(() => {
    wrapper?.unmount()
    document.documentElement.removeAttribute('style')
    delete document.documentElement.dataset.floatingSafeArea
  })

  async function mountApp(): Promise<VueWrapper> {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/', component: Screen }] })
    router.push('/')
    await router.isReady()
    const w = mount(App, { global: { plugins: [router], stubs: { AiAssistantPanel: true } } })
    await flushPromises()
    return w
  }

  it('root layar diberi kelas app-route-view dan <html> diberi ruang aman sesuai bubble', async () => {
    wrapper = await mountApp()

    expect(wrapper.find('main.some-screen').classes()).toContain('app-route-view')
    const root = document.documentElement
    expect(root.dataset.floatingSafeArea).toBe('')
    expect(root.style.getPropertyValue('--floating-safe-bottom')).toBe(
      `${floatingSafeBottomPx({ bubbleEnabled: true, clockEnabled: false })}px`,
    )
  })

  it('mengikuti toggle bubble/jam dan dilepas saat keduanya nonaktif', async () => {
    wrapper = await mountApp()
    const root = document.documentElement

    useAiAssistantStore().toggleBubble()
    useFloatingClockStore().toggle()
    await nextTick()
    expect(root.style.getPropertyValue('--floating-safe-bottom')).toBe(
      `${floatingSafeBottomPx({ bubbleEnabled: false, clockEnabled: true })}px`,
    )

    useFloatingClockStore().toggle()
    await nextTick()
    expect(root.style.getPropertyValue('--floating-safe-bottom')).toBe('')
    expect(root.dataset.floatingSafeArea).toBeUndefined()
  })
})
