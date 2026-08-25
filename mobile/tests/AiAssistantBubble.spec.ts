/**
 * AiAssistantBubble.spec.ts — mobile/src/components/AiAssistantBubble.vue.
 *
 * Real aiAssistant store (no mocking — same convention as
 * aiAssistant.store.spec.ts / AiAssistantPanel.spec.ts) since this
 * component is a thin, pure consumer of `bubbleEnabled`/`open()`.
 */
import { beforeEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import AiAssistantBubble from '@/components/AiAssistantBubble.vue'
import { useAiAssistantStore } from '@/stores/aiAssistant'

describe('AiAssistantBubble', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
  })

  it('renders by default (bubbleEnabled defaults to true)', () => {
    const wrapper = mount(AiAssistantBubble)

    expect(wrapper.find('[data-testid="ai-assistant-bubble"]').exists()).toBe(true)
  })

  it('renders nothing when bubbleEnabled is false', () => {
    const store = useAiAssistantStore()
    store.toggleBubble()

    const wrapper = mount(AiAssistantBubble)

    expect(wrapper.find('[data-testid="ai-assistant-bubble"]').exists()).toBe(false)
  })

  it('opens the AI assistant panel when tapped', async () => {
    const store = useAiAssistantStore()
    const wrapper = mount(AiAssistantBubble)

    expect(store.isOpen).toBe(false)
    await wrapper.get('[data-testid="ai-assistant-bubble"]').trigger('click')

    expect(store.isOpen).toBe(true)
  })
})
