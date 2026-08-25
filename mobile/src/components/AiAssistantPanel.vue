<script setup lang="ts">
/**
 * Bottom-sheet "Mills AI" chat panel. Mounted once, globally, in App.vue
 * (same "mount once in the shared shell" principle as FloatingClock.vue)
 * and opened via the aiAssistant store from any screen's hamburger
 * nav-menu ("Bantuan AI").
 *
 * Bottom sheet (margin on top/left/right, flush + rounded top corners on
 * the bottom edge, slides up on open) rather than truly full-screen or a
 * small floating bubble (unlike the web chatbot widget in
 * components/chatbot-widget.blade.php) — mobile screens are small and a
 * floating bubble would collide with FloatingClock.vue (bottom-right) and
 * risk accidental taps for gloved operators mid-form; per user feedback
 * 2026-08-24, plain CSS animation (not Vue's <Transition>) so the sheet's
 * presence stays tied 1:1 to v-if with no leave-transition DOM-removal
 * delay to account for in tests.
 *
 * WIRED (2026-08-25) to the same dedicated Aivena RAG backend project as
 * the web widget (millsAiChatApi.ts) — auth token flow, send/receive
 * inference, conversation history, reset. Chat state (messages/loading/
 * error/input) is local component state, not the aiAssistant store — the
 * store stays scoped to open/closed only (same separation as before);
 * this component itself is only ever mounted once (App.vue), so local
 * refs already persist across open/close cycles exactly like the web
 * widget's Alpine x-data scope does.
 */
import { computed, nextTick, ref } from 'vue'
import { useAiAssistantStore } from '@/stores/aiAssistant'
import {
  ensureToken,
  getConversationId,
  getConversationHistory,
  removeConversationId,
  sendInference,
  setConversationId,
  type ChatHistoryMessage,
} from '@/services/millsAiChatApi'
import { renderMarkdownToSafeHtml } from '@/utils/markdown'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

interface ChatMessage {
  id: string
  role: 'user' | 'ai'
  text?: string
  html?: string
}

const aiAssistantStore = useAiAssistantStore()

const messages = ref<ChatMessage[]>([])
const input = ref('')
const loading = ref(false)
const error = ref<string | null>(null)
const showResetConfirm = ref(false)

const messageListEl = ref<HTMLElement | null>(null)

const canSend = computed(() => !loading.value && input.value.trim().length > 0)

let idCounter = 0
function makeId(prefix: string): string {
  idCounter += 1
  return `${prefix}-${Date.now()}-${idCounter}`
}

function scrollToBottom(): void {
  nextTick(() => {
    if (messageListEl.value) {
      messageListEl.value.scrollTop = messageListEl.value.scrollHeight
    }
  })
}

async function send(): Promise<void> {
  const text = input.value.trim()
  if (!text || loading.value) return

  messages.value.push({ id: makeId('u'), role: 'user', text })
  input.value = ''
  loading.value = true
  error.value = null
  scrollToBottom()

  try {
    await ensureToken()
    const res = await sendInference(text)
    if (res.success && res.result) {
      if (res.result.conversation_id) {
        setConversationId(res.result.conversation_id)
      }
      messages.value.push({ id: makeId('a'), role: 'ai', html: renderMarkdownToSafeHtml(res.result.ai_message) })
    } else {
      error.value = 'Gagal menerima respon.'
    }
  } catch {
    error.value = 'Terjadi kesalahan. Coba lagi.'
  } finally {
    loading.value = false
    scrollToBottom()
  }
}

function messageText(message: ChatHistoryMessage): string {
  return message.text ?? message.ai_message ?? message.message ?? ''
}

async function loadHistory(): Promise<void> {
  const conversationId = getConversationId()
  if (!conversationId) {
    error.value = 'Tidak ada percakapan yang tersimpan.'
    return
  }

  loading.value = true
  error.value = null

  try {
    await ensureToken()
    const res = await getConversationHistory(conversationId)
    const loaded = res.success && res.result ? ('messages' in res.result ? res.result.messages : res.result) : []

    messages.value = (Array.isArray(loaded) ? loaded : []).map((message) => {
      const role = String(message.role ?? '').toLowerCase() === 'human' ? 'user' : 'ai'
      const text = messageText(message)
      return role === 'user'
        ? { id: makeId('h'), role: 'user' as const, text }
        : { id: makeId('h'), role: 'ai' as const, html: renderMarkdownToSafeHtml(text) }
    })
  } catch {
    error.value = 'Gagal memuat riwayat.'
  } finally {
    loading.value = false
    scrollToBottom()
  }
}

function resetChat(): void {
  showResetConfirm.value = true
}

function confirmResetChat(): void {
  removeConversationId()
  messages.value = []
  error.value = null
  showResetConfirm.value = false
}
</script>

<template>
  <div v-if="aiAssistantStore.isOpen" class="ai-assistant-backdrop" data-testid="ai-assistant-backdrop" @click.self="aiAssistantStore.close()">
    <div
      class="ai-assistant-panel"
      data-testid="ai-assistant-panel"
      role="dialog"
      aria-label="Mills AI"
    >
      <div class="ai-assistant-panel__header">
        <p class="ai-assistant-panel__title">Mills AI</p>
        <div class="ai-assistant-panel__header-actions">
          <button
            type="button"
            class="ai-assistant-panel__icon-button"
            data-testid="ai-assistant-load-history"
            aria-label="Muat riwayat"
            @click="loadHistory"
          >
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M21 12a9 9 0 1 1-2.64-6.36" />
              <polyline points="21 3 21 9 15 9" />
            </svg>
          </button>
          <button
            type="button"
            class="ai-assistant-panel__icon-button"
            data-testid="ai-assistant-reset"
            aria-label="Reset percakapan"
            @click="resetChat"
          >
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <polyline points="3 6 5 6 21 6" />
              <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
            </svg>
          </button>
          <button
            type="button"
            class="ai-assistant-panel__icon-button"
            data-testid="ai-assistant-close"
            aria-label="Tutup"
            @click="aiAssistantStore.close()"
          >
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
        </div>
      </div>

      <div ref="messageListEl" class="ai-assistant-panel__body" data-testid="ai-assistant-message-list">
        <div v-if="messages.length === 0" class="ai-assistant-panel__empty">
          <svg class="ai-assistant-panel__empty-icon" viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
          </svg>
          Ada yang bisa saya bantu?
        </div>

        <div
          v-for="message in messages"
          :key="message.id"
          class="ai-assistant-panel__message"
          :class="message.role === 'user' ? 'ai-assistant-panel__message--user' : 'ai-assistant-panel__message--ai'"
          data-testid="ai-assistant-message"
        >
          <span v-if="message.role === 'user'">{{ message.text }}</span>
          <span v-else v-html="message.html"></span>
        </div>

        <div v-if="loading" class="ai-assistant-panel__loading" data-testid="ai-assistant-loading">
          <span class="ai-assistant-panel__dot"></span>
          <span class="ai-assistant-panel__dot" style="animation-delay: 0.15s"></span>
          <span class="ai-assistant-panel__dot" style="animation-delay: 0.3s"></span>
          <span>Mills AI sedang berpikir...</span>
        </div>

        <p v-if="error" class="ai-assistant-panel__error" data-testid="ai-assistant-error">{{ error }}</p>
      </div>

      <form class="ai-assistant-panel__footer" @submit.prevent="send">
        <input
          v-model="input"
          type="text"
          class="ai-assistant-panel__input"
          placeholder="Tulis pesan..."
          :disabled="loading"
          aria-label="Tulis pesan"
          data-testid="ai-assistant-input"
        >
        <button type="submit" class="ai-assistant-panel__send" :disabled="!canSend" aria-label="Kirim" data-testid="ai-assistant-send">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="22" y1="2" x2="11" y2="13" />
            <polygon points="22 2 15 22 11 13 2 9 22 2" />
          </svg>
        </button>
      </form>
    </div>

    <ConfirmDialog
      :open="showResetConfirm"
      title="Reset Percakapan"
      message="Reset percakapan? Semua percakapan lokal akan dihapus."
      confirm-label="Ya, Reset"
      @confirm="confirmResetChat"
      @cancel="showResetConfirm = false"
    />
  </div>
</template>

<style scoped>
.ai-assistant-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1100;
  background: rgba(17, 24, 39, 0.35);
  animation: ai-assistant-fade-in 0.2s ease-out;
}

.ai-assistant-panel {
  position: fixed;
  top: 56px;
  left: 12px;
  right: 12px;
  bottom: 0;
  z-index: 1101;
  display: flex;
  flex-direction: column;
  background: #fff;
  border-radius: 20px 20px 0 0;
  box-shadow: 0 -8px 24px rgba(0, 0, 0, 0.18);
  overflow: hidden;
  animation: ai-assistant-slide-up 0.25s ease-out;
  font-family: 'Inter', sans-serif;
}

@keyframes ai-assistant-fade-in {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes ai-assistant-slide-up {
  from {
    transform: translateY(100%);
  }
  to {
    transform: translateY(0);
  }
}

.ai-assistant-panel__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  background: #249360;
  color: #fff;
  flex-shrink: 0;
}

.ai-assistant-panel__title {
  margin: 0;
  font-size: 17px;
  font-weight: 700;
}

.ai-assistant-panel__header-actions {
  display: flex;
  align-items: center;
  gap: 4px;
}

.ai-assistant-panel__icon-button {
  border: none;
  background: transparent;
  color: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 6px;
  border-radius: 8px;
  min-width: 40px;
  min-height: 40px;
}

.ai-assistant-panel__icon-button:hover {
  background: rgba(255, 255, 255, 0.15);
}

.ai-assistant-panel__body {
  flex: 1;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 16px;
  background: #f9fafb;
}

.ai-assistant-panel__empty {
  margin: auto 0;
  align-self: center;
  text-align: center;
  color: #6b7280;
  font-size: 14px;
  max-width: 260px;
}

.ai-assistant-panel__empty-icon {
  display: block;
  margin: 0 auto 12px;
  color: #9ca3af;
}

.ai-assistant-panel__message {
  max-width: 85%;
  padding: 10px 12px;
  border-radius: 14px;
  font-size: 14px;
  line-height: 1.55;
  word-break: break-word;
}

.ai-assistant-panel__message--user {
  align-self: flex-end;
  background: #249360;
  color: #fff;
  border-bottom-right-radius: 4px;
  white-space: pre-wrap;
}

.ai-assistant-panel__message--ai {
  align-self: flex-start;
  background: #fff;
  color: #1f2937;
  border: 1px solid #e5e7eb;
  border-bottom-left-radius: 4px;
}

.ai-assistant-panel__message--ai :deep(p:first-child) {
  margin-top: 0;
}

.ai-assistant-panel__message--ai :deep(p:last-child) {
  margin-bottom: 0;
}

.ai-assistant-panel__loading {
  align-self: flex-start;
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #6b7280;
}

.ai-assistant-panel__dot {
  width: 6px;
  height: 6px;
  border-radius: 999px;
  background: #249360;
  animation: ai-assistant-bounce 1.1s infinite ease-in-out;
}

@keyframes ai-assistant-bounce {
  0%, 80%, 100% {
    transform: translateY(0);
    opacity: 0.4;
  }
  40% {
    transform: translateY(-3px);
    opacity: 1;
  }
}

.ai-assistant-panel__error {
  align-self: stretch;
  margin: 0;
  padding: 8px 10px;
  border-radius: 8px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #b91c1c;
  font-size: 12px;
}

.ai-assistant-panel__footer {
  display: flex;
  gap: 8px;
  padding: 12px;
  padding-bottom: max(12px, env(safe-area-inset-bottom));
  border-top: 1px solid #d1d5db;
  flex-shrink: 0;
}

.ai-assistant-panel__input {
  flex: 1;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 11px 14px;
  font-size: 14px;
  font-family: inherit;
  color: #1f2937;
}

.ai-assistant-panel__input:disabled {
  background: #f3f4f6;
  cursor: not-allowed;
}

.ai-assistant-panel__send {
  width: 44px;
  height: 44px;
  flex-shrink: 0;
  border: none;
  border-radius: 8px;
  background: #249360;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.ai-assistant-panel__send:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
