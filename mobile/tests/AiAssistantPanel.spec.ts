/**
 * AiAssistantPanel.spec.ts — mobile/src/components/AiAssistantPanel.vue.
 *
 * WIRED (2026-08-25) to millsAiChatApi.ts — mocked at module level here
 * (its own dedicated spec, millsAiChatApi.spec.ts, covers the HTTP/token
 * logic itself). aiAssistant store (open/close) is exercised for real, no
 * mocking — same as before this component had real chat logic —
 * mirroring aiAssistant.store.spec.ts. '@/utils/markdown' is NOT mocked
 * (pure, safe to run for real — its own spec covers correctness).
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import AiAssistantPanel from '@/components/AiAssistantPanel.vue'
import { useAiAssistantStore } from '@/stores/aiAssistant'

const {
  ensureTokenMock,
  sendInferenceMock,
  getConversationHistoryMock,
  getConversationIdMock,
  setConversationIdMock,
  removeConversationIdMock,
} = vi.hoisted(() => ({
  ensureTokenMock: vi.fn(),
  sendInferenceMock: vi.fn(),
  getConversationHistoryMock: vi.fn(),
  getConversationIdMock: vi.fn(),
  setConversationIdMock: vi.fn(),
  removeConversationIdMock: vi.fn(),
}))

vi.mock('@/services/millsAiChatApi', () => ({
  ensureToken: ensureTokenMock,
  sendInference: sendInferenceMock,
  getConversationHistory: getConversationHistoryMock,
  getConversationId: getConversationIdMock,
  setConversationId: setConversationIdMock,
  removeConversationId: removeConversationIdMock,
}))

describe('AiAssistantPanel', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    ensureTokenMock.mockResolvedValue('token')
    getConversationIdMock.mockReturnValue(null)
  })

  it('renders nothing when the store is closed', () => {
    const wrapper = mount(AiAssistantPanel)

    expect(wrapper.find('[data-testid="ai-assistant-panel"]').exists()).toBe(false)
  })

  it('renders the "Mills AI" panel with the empty state when opened', () => {
    const store = useAiAssistantStore()
    store.open()

    const wrapper = mount(AiAssistantPanel)
    const panel = wrapper.get('[data-testid="ai-assistant-panel"]')

    expect(panel.text()).toContain('Mills AI')
    expect(panel.text()).toContain('Ada yang bisa saya bantu?')
    expect(wrapper.find('[data-testid="ai-assistant-message"]').exists()).toBe(false)
  })

  it('closes the panel when the close button is tapped', async () => {
    const store = useAiAssistantStore()
    store.open()

    const wrapper = mount(AiAssistantPanel)
    expect(wrapper.find('[data-testid="ai-assistant-panel"]').exists()).toBe(true)

    await wrapper.get('[data-testid="ai-assistant-close"]').trigger('click')

    expect(store.isOpen).toBe(false)
    expect(wrapper.find('[data-testid="ai-assistant-panel"]').exists()).toBe(false)
  })

  it('closes the panel when the backdrop (outside the sheet) is tapped', async () => {
    const store = useAiAssistantStore()
    store.open()

    const wrapper = mount(AiAssistantPanel)

    await wrapper.get('[data-testid="ai-assistant-backdrop"]').trigger('click')

    expect(store.isOpen).toBe(false)
  })

  it('does not close when tapping inside the sheet itself', async () => {
    const store = useAiAssistantStore()
    store.open()

    const wrapper = mount(AiAssistantPanel)

    await wrapper.get('[data-testid="ai-assistant-panel"]').trigger('click')

    expect(store.isOpen).toBe(true)
  })

  describe('sending a message', () => {
    it('disables the send button until the input has non-whitespace text', async () => {
      useAiAssistantStore().open()
      const wrapper = mount(AiAssistantPanel)

      expect(wrapper.get('[data-testid="ai-assistant-send"]').attributes('disabled')).toBeDefined()

      await wrapper.get('[data-testid="ai-assistant-input"]').setValue('   ')
      expect(wrapper.get('[data-testid="ai-assistant-send"]').attributes('disabled')).toBeDefined()

      await wrapper.get('[data-testid="ai-assistant-input"]').setValue('Halo')
      expect(wrapper.get('[data-testid="ai-assistant-send"]').attributes('disabled')).toBeUndefined()
    })

    it('sends the message, shows a loading indicator, then renders the AI reply and stores the conversation id', async () => {
      useAiAssistantStore().open()
      let resolveSend!: (value: unknown) => void
      sendInferenceMock.mockReturnValue(
        new Promise((resolve) => {
          resolveSend = resolve
        }),
      )

      const wrapper = mount(AiAssistantPanel)

      await wrapper.get('[data-testid="ai-assistant-input"]').setValue('Berapa target FFB hari ini?')
      await wrapper.get('[data-testid="ai-assistant-input"]').trigger('keyup.enter')
      await wrapper.find('form').trigger('submit')
      await flushPromises()

      expect(ensureTokenMock).toHaveBeenCalled()
      expect(sendInferenceMock).toHaveBeenCalledWith('Berapa target FFB hari ini?')
      expect(wrapper.get('[data-testid="ai-assistant-input"]').element.value).toBe('')
      expect(wrapper.get('[data-testid="ai-assistant-loading"]').exists()).toBe(true)

      const userMessages = wrapper.findAll('[data-testid="ai-assistant-message"]')
      expect(userMessages[0].text()).toBe('Berapa target FFB hari ini?')

      resolveSend({
        success: true,
        result: { conversation_id: 'conv-1', ai_message: '**Target** 25 ton/jam' },
      })
      await flushPromises()

      expect(setConversationIdMock).toHaveBeenCalledWith('conv-1')
      expect(wrapper.find('[data-testid="ai-assistant-loading"]').exists()).toBe(false)

      const messages = wrapper.findAll('[data-testid="ai-assistant-message"]')
      expect(messages).toHaveLength(2)
      expect(messages[1].html()).toContain('<strong>Target</strong>')
    })

    it('shows an error message when sendInference rejects', async () => {
      useAiAssistantStore().open()
      sendInferenceMock.mockRejectedValue(new Error('network down'))

      const wrapper = mount(AiAssistantPanel)
      await wrapper.get('[data-testid="ai-assistant-input"]').setValue('Halo')
      await wrapper.find('form').trigger('submit')
      await flushPromises()

      expect(wrapper.get('[data-testid="ai-assistant-error"]').text()).toBe('Terjadi kesalahan. Coba lagi.')
    })

    it('does nothing when submitted with only whitespace input', async () => {
      useAiAssistantStore().open()
      const wrapper = mount(AiAssistantPanel)

      await wrapper.get('[data-testid="ai-assistant-input"]').setValue('   ')
      await wrapper.find('form').trigger('submit')
      await flushPromises()

      expect(sendInferenceMock).not.toHaveBeenCalled()
      expect(wrapper.find('[data-testid="ai-assistant-message"]').exists()).toBe(false)
    })
  })

  describe('loadHistory (Muat riwayat)', () => {
    it('shows an error and does not call the API when there is no stored conversation id', async () => {
      getConversationIdMock.mockReturnValue(null)
      useAiAssistantStore().open()
      const wrapper = mount(AiAssistantPanel)

      await wrapper.get('[data-testid="ai-assistant-load-history"]').trigger('click')
      await flushPromises()

      expect(getConversationHistoryMock).not.toHaveBeenCalled()
      expect(wrapper.get('[data-testid="ai-assistant-error"]').text()).toBe('Tidak ada percakapan yang tersimpan.')
    })

    it('loads history, mapping "human" role to user and everything else to ai (markdown-rendered)', async () => {
      getConversationIdMock.mockReturnValue('conv-1')
      getConversationHistoryMock.mockResolvedValue({
        success: true,
        result: {
          messages: [
            { role: 'Human', text: 'Halo' },
            { role: 'AI', ai_message: '**Halo juga**' },
          ],
        },
      })
      useAiAssistantStore().open()
      const wrapper = mount(AiAssistantPanel)

      await wrapper.get('[data-testid="ai-assistant-load-history"]').trigger('click')
      await flushPromises()

      expect(getConversationHistoryMock).toHaveBeenCalledWith('conv-1')
      const messages = wrapper.findAll('[data-testid="ai-assistant-message"]')
      expect(messages).toHaveLength(2)
      expect(messages[0].text()).toBe('Halo')
      expect(messages[1].html()).toContain('<strong>Halo juga</strong>')
    })

    it('shows an error message when getConversationHistory rejects', async () => {
      getConversationIdMock.mockReturnValue('conv-1')
      getConversationHistoryMock.mockRejectedValue(new Error('network down'))
      useAiAssistantStore().open()
      const wrapper = mount(AiAssistantPanel)

      await wrapper.get('[data-testid="ai-assistant-load-history"]').trigger('click')
      await flushPromises()

      expect(wrapper.get('[data-testid="ai-assistant-error"]').text()).toBe('Gagal memuat riwayat.')
    })
  })

  describe('resetChat (Reset percakapan)', () => {
    it('asks for confirmation, and does nothing until confirmed', async () => {
      useAiAssistantStore().open()
      const wrapper = mount(AiAssistantPanel)

      await wrapper.get('[data-testid="ai-assistant-reset"]').trigger('click')

      expect(wrapper.find('.confirm-dialog-overlay').exists()).toBe(true)
      expect(removeConversationIdMock).not.toHaveBeenCalled()

      await wrapper.find('.confirm-dialog-button--cancel').trigger('click')

      expect(removeConversationIdMock).not.toHaveBeenCalled()
      expect(wrapper.find('.confirm-dialog-overlay').exists()).toBe(false)
    })

    it('clears the conversation id and local messages once confirmed', async () => {
      getConversationIdMock.mockReturnValue('conv-1')
      getConversationHistoryMock.mockResolvedValue({ success: true, result: { messages: [{ role: 'Human', text: 'Halo' }] } })
      useAiAssistantStore().open()
      const wrapper = mount(AiAssistantPanel)

      await wrapper.get('[data-testid="ai-assistant-load-history"]').trigger('click')
      await flushPromises()
      expect(wrapper.findAll('[data-testid="ai-assistant-message"]')).toHaveLength(1)

      await wrapper.get('[data-testid="ai-assistant-reset"]').trigger('click')
      await wrapper.find('.confirm-dialog-button--confirm').trigger('click')

      expect(removeConversationIdMock).toHaveBeenCalledTimes(1)
      expect(wrapper.find('[data-testid="ai-assistant-message"]').exists()).toBe(false)
      expect(wrapper.find('.confirm-dialog-overlay').exists()).toBe(false)
    })
  })
})
