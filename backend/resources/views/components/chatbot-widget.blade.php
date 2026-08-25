{{--
    Mills AI — floating chatbot widget (bubble button bottom-right that
    toggles a chat panel). Mounted once, globally, in
    components/layouts/app.blade.php (same "mount once in the shared
    shell" principle as mobile's FloatingClock.vue in App.vue) so it
    appears on every authenticated screen without being repeated per-view.

    Wired to a dedicated Aivena RAG backend project for Mills Smart Log
    (config/millsai.php, .env's MILLS_AI_*) — auth (email/password ->
    access_token), send/receive inference, conversation history, reset.
    Ported from the reference aivena-widget project (Vue) to plain
    Alpine.js (already bundled via Livewire's @livewireScripts, no
    separate build pipeline in this backend scaffold) — same API contract,
    same localStorage persistence keys' PURPOSE (token + conversation_id),
    own key names (mills_ai_*) so this widget never collides with any
    other Aivena-backed widget a browser might have visited.

    `project_id`/auth email+password ARE exposed to the browser via @js()
    below — by design, matching the reference project's spec ("Email &
    password hardcoded di script"): an embed-level credential for this one
    Aivena project, not a per-user secret. See .env's own comment.

    marked + DOMPurify loaded from CDN at runtime (this scaffold has no
    npm build step for Blade assets) — mirrors the reference project's own
    "CSS loaded via runtime script/link tag" pattern. If either fails to
    load, renderMarkdown() falls back to escaped plain text (spec: "Jika
    markdown gagal parse -> fallback ke plain text").
--}}
@once
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>
<style>
    [x-cloak] {
        display: none !important;
    }

    .chatbot-widget {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 1000;
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }

    .chatbot-widget__bubble {
        width: 56px;
        height: 56px;
        padding: 0;
        box-sizing: border-box;
        line-height: 1;
        border-radius: 999px;
        border: none;
        background: var(--color-brand, #249360);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 8px 20px rgba(17, 24, 39, 0.24);
        transition: background 0.15s ease;
    }

    .chatbot-widget__bubble:hover {
        background: var(--color-brand-hover, #1d7a4e);
    }

    .chatbot-widget__panel {
        position: absolute;
        bottom: calc(100% + 12px);
        right: 0;
        width: 360px;
        max-width: calc(100vw - 48px);
        height: 520px;
        max-height: calc(100vh - 140px);
        display: flex;
        flex-direction: column;
        background: #fff;
        border-radius: 12px;
        border: 1px solid #d1d5db;
        box-shadow: 0 16px 40px rgba(17, 24, 39, 0.2);
        overflow: hidden;
    }

    .chatbot-widget__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 16px;
        background: var(--color-brand, #249360);
        color: #fff;
        flex-shrink: 0;
    }

    .chatbot-widget__title {
        margin: 0;
        font-size: 15px;
        font-weight: 700;
    }

    .chatbot-widget__header-actions {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .chatbot-widget__icon-button {
        border: none;
        background: transparent;
        color: #fff;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 6px;
        border-radius: 6px;
        opacity: 0.9;
    }

    .chatbot-widget__icon-button:hover {
        opacity: 1;
        background: rgba(255, 255, 255, 0.15);
    }

    .chatbot-widget__body {
        flex: 1;
        overflow-y: auto;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: #f9fafb;
    }

    .chatbot-widget__empty {
        margin: auto 0;
        text-align: center;
        color: #6b7280;
        font-size: 14px;
        max-width: 240px;
        align-self: center;
    }

    .chatbot-widget__empty-icon {
        display: block;
        margin: 0 auto 10px;
        color: #9ca3af;
    }

    .chatbot-widget__message {
        width: fit-content;
        max-width: 85%;
        padding: 8px 12px;
        border-radius: 14px;
        font-size: 13px;
        line-height: 1.4;
        word-break: break-word;
    }

    .chatbot-widget__message--user {
        align-self: flex-end;
        background: var(--color-brand, #249360);
        color: #fff;
        border-bottom-right-radius: 4px;
    }

    .chatbot-widget__message--ai {
        align-self: flex-start;
        background: #fff;
        color: #1f2937;
        border: 1px solid #e5e7eb;
        border-bottom-left-radius: 4px;
    }

    .chatbot-widget__message--ai p:first-child {
        margin-top: 0;
    }

    .chatbot-widget__message--ai p:last-child {
        margin-bottom: 0;
    }

    .chatbot-widget__loading {
        align-self: flex-start;
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        color: #6b7280;
    }

    .chatbot-widget__dot {
        width: 6px;
        height: 6px;
        border-radius: 999px;
        background: var(--color-brand, #249360);
        animation: chatbot-widget-bounce 1.1s infinite ease-in-out;
    }

    @keyframes chatbot-widget-bounce {
        0%, 80%, 100% { transform: translateY(0); opacity: 0.4; }
        40% { transform: translateY(-3px); opacity: 1; }
    }

    .chatbot-widget__error {
        align-self: stretch;
        padding: 8px 10px;
        border-radius: 8px;
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #b91c1c;
        font-size: 12px;
    }

    .chatbot-widget__footer {
        display: flex;
        gap: 8px;
        padding: 12px;
        border-top: 1px solid #d1d5db;
        flex-shrink: 0;
    }

    .chatbot-widget__input {
        flex: 1;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 9px 12px;
        font-size: 14px;
        font-family: inherit;
        color: #1f2937;
    }

    .chatbot-widget__input:disabled {
        background: #f3f4f6;
        cursor: not-allowed;
    }

    .chatbot-widget__send {
        width: 38px;
        height: 38px;
        flex-shrink: 0;
        border: none;
        border-radius: 8px;
        background: var(--color-brand, #249360);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .chatbot-widget__send:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
</style>
<script>
    function millsAiWidget(config) {
        return {
            open: false,
            messages: [],
            input: '',
            loading: false,
            error: null,
            config: config,

            LS_TOKEN_KEY: 'mills_ai_access_token',
            LS_CONVERSATION_KEY: 'mills_ai_conversation_id',

            getStoredToken() {
                try { return localStorage.getItem(this.LS_TOKEN_KEY) } catch (e) { return null }
            },
            setStoredToken(token) {
                try { localStorage.setItem(this.LS_TOKEN_KEY, token) } catch (e) {}
            },
            removeStoredToken() {
                try { localStorage.removeItem(this.LS_TOKEN_KEY) } catch (e) {}
            },
            getConversationId() {
                try { return localStorage.getItem(this.LS_CONVERSATION_KEY) } catch (e) { return null }
            },
            setConversationId(id) {
                try { localStorage.setItem(this.LS_CONVERSATION_KEY, id) } catch (e) {}
            },
            removeConversationId() {
                try { localStorage.removeItem(this.LS_CONVERSATION_KEY) } catch (e) {}
            },

            decodeJwtPayload(token) {
                try {
                    const parts = String(token || '').split('.');
                    if (parts.length < 2) return null;
                    const base64 = parts[1].replace(/-/g, '+').replace(/_/g, '/');
                    const padded = base64.padEnd(Math.ceil(base64.length / 4) * 4, '=');
                    return JSON.parse(atob(padded));
                } catch (e) {
                    return null;
                }
            },
            isTokenExpired(token) {
                const payload = this.decodeJwtPayload(token);
                const exp = Number(payload && payload.exp);
                if (!exp) return true;
                const nowSeconds = Math.floor(Date.now() / 1000);
                return exp <= nowSeconds + 30;
            },

            async login() {
                const res = await fetch(this.config.baseUrl + '/token', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: this.config.authEmail, password: this.config.authPassword }),
                });
                const data = await res.json();
                if (data && data.success && data.result && data.result.access_token) {
                    this.setStoredToken(data.result.access_token);
                    return data.result.access_token;
                }
                throw new Error('Auth failed');
            },

            async ensureToken() {
                const stored = this.getStoredToken();
                if (stored && !this.isTokenExpired(stored)) return stored;
                if (stored) this.removeStoredToken();
                return await this.login();
            },

            async apiGet(path, params) {
                let token = await this.ensureToken();
                const url = new URL(this.config.baseUrl + path);
                Object.keys(params || {}).forEach((key) => {
                    if (params[key] !== undefined && params[key] !== null) url.searchParams.set(key, params[key]);
                });

                let res = await fetch(url.toString(), { headers: { Authorization: 'Bearer ' + token } });
                if (res.status === 401) {
                    token = await this.login();
                    res = await fetch(url.toString(), { headers: { Authorization: 'Bearer ' + token } });
                }

                const data = await res.json();
                if (!res.ok || data.success === false) {
                    throw new Error((data.error && data.error.message) || 'Request failed');
                }
                return data;
            },

            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text || '';
                return div.innerHTML;
            },

            renderMarkdown(text) {
                try {
                    if (window.marked && window.DOMPurify) {
                        return window.DOMPurify.sanitize(window.marked.parse(text || ''));
                    }
                } catch (e) {
                    // fall through to plain-text fallback below
                }
                return this.escapeHtml(text || '').replace(/\n/g, '<br>');
            },

            scrollToBottom() {
                this.$nextTick(() => {
                    const el = this.$refs.messageList;
                    if (el) el.scrollTop = el.scrollHeight;
                });
            },

            async send() {
                const text = (this.input || '').trim();
                if (!text || this.loading) return;

                this.messages.push({ id: 'u-' + Date.now(), role: 'user', text: text });
                this.input = '';
                this.loading = true;
                this.error = null;
                this.scrollToBottom();

                try {
                    const params = { human_message: text };
                    const conversationId = this.getConversationId();
                    if (conversationId) params.conversation_id = conversationId;

                    const data = await this.apiGet('/projects/' + this.config.projectId + '/inference', params);
                    if (data.result && data.result.conversation_id) {
                        this.setConversationId(data.result.conversation_id);
                    }
                    const aiText = (data.result && data.result.ai_message) || '';
                    this.messages.push({ id: 'a-' + Date.now(), role: 'ai', html: this.renderMarkdown(aiText) });
                } catch (e) {
                    this.error = 'Terjadi kesalahan. Coba lagi.';
                } finally {
                    this.loading = false;
                    this.scrollToBottom();
                }
            },

            async loadHistory() {
                const conversationId = this.getConversationId();
                if (!conversationId) {
                    this.error = 'Tidak ada percakapan yang tersimpan.';
                    return;
                }

                this.loading = true;
                this.error = null;

                try {
                    const data = await this.apiGet('/projects/' + this.config.projectId + '/conversations/' + conversationId + '/history');
                    const loaded = (data.result && (data.result.messages || data.result)) || [];
                    this.messages = (Array.isArray(loaded) ? loaded : []).map((message, index) => {
                        const rawRole = String(message.role || '').toLowerCase();
                        const role = rawRole === 'human' ? 'user' : 'ai';
                        const text = message.text || message.ai_message || message.message || '';
                        return {
                            id: 'h-' + index,
                            role: role,
                            text: role === 'user' ? text : undefined,
                            html: role === 'ai' ? this.renderMarkdown(text) : undefined,
                        };
                    });
                } catch (e) {
                    this.error = 'Gagal memuat riwayat.';
                } finally {
                    this.loading = false;
                    this.scrollToBottom();
                }
            },

            resetChat() {
                if (!confirm('Reset percakapan? Semua percakapan lokal akan dihapus.')) return;
                this.removeConversationId();
                this.messages = [];
                this.error = null;
            },
        };
    }
</script>
@endonce

<div
    class="chatbot-widget"
    x-data="millsAiWidget(@js([
        'baseUrl' => config('millsai.base_url'),
        'projectId' => config('millsai.project_id'),
        'authEmail' => config('millsai.auth_email'),
        'authPassword' => config('millsai.auth_password'),
    ]))"
    @keydown.escape.window="open = false"
>
    <div
        x-show="open"
        x-cloak
        x-transition
        @click.outside="open = false"
        class="chatbot-widget__panel"
        role="dialog"
        aria-label="Mills AI"
    >
        <div class="chatbot-widget__header">
            <p class="chatbot-widget__title">Mills AI</p>
            <div class="chatbot-widget__header-actions">
                <button type="button" class="chatbot-widget__icon-button" @click="loadHistory()" title="Muat riwayat" aria-label="Muat riwayat" data-testid="chatbot-load-history">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 12a9 9 0 1 1-2.64-6.36" />
                        <polyline points="21 3 21 9 15 9" />
                    </svg>
                </button>
                <button type="button" class="chatbot-widget__icon-button" @click="resetChat()" title="Reset percakapan" aria-label="Reset percakapan" data-testid="chatbot-reset">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="3 6 5 6 21 6" />
                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                    </svg>
                </button>
                <button type="button" class="chatbot-widget__icon-button" @click="open = false" aria-label="Tutup" data-testid="chatbot-close">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="chatbot-widget__body" x-ref="messageList" data-testid="chatbot-message-list">
            <div class="chatbot-widget__empty" x-show="messages.length === 0" x-cloak>
                <svg class="chatbot-widget__empty-icon" viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                </svg>
                Ada yang bisa saya bantu?
            </div>

            <template x-for="message in messages" :key="message.id">
                <div
                    class="chatbot-widget__message"
                    :class="message.role === 'user' ? 'chatbot-widget__message--user' : 'chatbot-widget__message--ai'"
                    data-testid="chatbot-message"
                >
                    <span x-show="message.role === 'user'" x-text="message.text"></span>
                    <span x-show="message.role === 'ai'" x-html="message.html"></span>
                </div>
            </template>

            <div class="chatbot-widget__loading" x-show="loading" x-cloak data-testid="chatbot-loading">
                <span class="chatbot-widget__dot"></span>
                <span class="chatbot-widget__dot" style="animation-delay: 0.15s"></span>
                <span class="chatbot-widget__dot" style="animation-delay: 0.3s"></span>
                <span>Mills AI sedang berpikir...</span>
            </div>

            <p class="chatbot-widget__error" x-show="error" x-cloak x-text="error" data-testid="chatbot-error"></p>
        </div>

        <form class="chatbot-widget__footer" @submit.prevent="send()">
            <input
                type="text"
                x-model="input"
                class="chatbot-widget__input"
                placeholder="Tulis pesan..."
                :disabled="loading"
                aria-label="Tulis pesan"
                data-testid="chatbot-input"
            >
            <button type="submit" class="chatbot-widget__send" :disabled="loading || !input.trim()" aria-label="Kirim" data-testid="chatbot-send">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="22" y1="2" x2="11" y2="13" />
                    <polygon points="22 2 15 22 11 13 2 9 22 2" />
                </svg>
            </button>
        </form>
    </div>

    <button
        type="button"
        class="chatbot-widget__bubble"
        @click="open = !open"
        :aria-expanded="open ? 'true' : 'false'"
        aria-label="Buka Mills AI"
        data-testid="chatbot-widget-bubble"
    >
        <svg x-show="!open" viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
        </svg>
        <svg x-show="open" x-cloak viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
    </button>
</div>
