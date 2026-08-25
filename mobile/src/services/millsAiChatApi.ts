import axios, { type AxiosInstance } from 'axios'

/**
 * millsAiChatApi — ported from the reference aivena-widget project's
 * src/widget/api.js + src/widget/store.js, adapted to this app's TS/repo
 * conventions. Talks to a dedicated Aivena RAG backend project for Mills
 * Smart Log (VITE_MILLS_AI_*, unrelated to '@/services/apiClient' — a
 * completely separate external service with its own email/password/
 * access_token, never touches the Mills Smart Log backend or auth store).
 *
 * Own axios instance (not the shared apiClient) — that one attaches this
 * app's own Sanctum bearer token via an interceptor tied to the auth
 * store, which would be wrong here.
 *
 * Own localStorage keys (mills_ai_*, distinct from apiClient's/auth
 * store's) so this chat widget's token/conversation state never collides
 * with the app's own session state.
 */

const LS_TOKEN_KEY = 'mills_ai_access_token'
const LS_CONVERSATION_KEY = 'mills_ai_conversation_id'
const TOKEN_EXPIRY_SKEW_SECONDS = 30

const chatApiClient: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_MILLS_AI_BASE_URL,
  timeout: 20000,
})

interface InferenceResult {
  conversation_id: string
  message_id: string
  ai_message: string
}

interface ApiEnvelope<T> {
  success: boolean
  result?: T
}

export interface ChatHistoryMessage {
  role: string
  text?: string
  ai_message?: string
  message?: string
}

export function getStoredToken(): string | null {
  try {
    return localStorage.getItem(LS_TOKEN_KEY)
  } catch {
    return null
  }
}

export function setStoredToken(token: string): void {
  try {
    localStorage.setItem(LS_TOKEN_KEY, token)
  } catch {
    // localStorage unavailable — token still works for this request, just doesn't persist.
  }
}

export function removeStoredToken(): void {
  try {
    localStorage.removeItem(LS_TOKEN_KEY)
  } catch {
    // ignore
  }
}

export function getConversationId(): string | null {
  try {
    return localStorage.getItem(LS_CONVERSATION_KEY)
  } catch {
    return null
  }
}

export function setConversationId(id: string): void {
  try {
    localStorage.setItem(LS_CONVERSATION_KEY, id)
  } catch {
    // ignore
  }
}

export function removeConversationId(): void {
  try {
    localStorage.removeItem(LS_CONVERSATION_KEY)
  } catch {
    // ignore
  }
}

function decodeJwtPayload(token: string): { exp?: number } | null {
  try {
    const parts = token.split('.')
    if (parts.length < 2) return null
    const base64 = parts[1].replace(/-/g, '+').replace(/_/g, '/')
    const padded = base64.padEnd(Math.ceil(base64.length / 4) * 4, '=')
    return JSON.parse(atob(padded))
  } catch {
    return null
  }
}

function isTokenExpired(token: string): boolean {
  const exp = Number(decodeJwtPayload(token)?.exp)
  if (!exp) return true
  const nowSeconds = Math.floor(Date.now() / 1000)
  return exp <= nowSeconds + TOKEN_EXPIRY_SKEW_SECONDS
}

async function loginAndStoreToken(): Promise<string> {
  const res = await chatApiClient.post<ApiEnvelope<{ access_token: string }>>('/token', {
    email: import.meta.env.VITE_MILLS_AI_AUTH_EMAIL,
    password: import.meta.env.VITE_MILLS_AI_AUTH_PASSWORD,
  })

  const token = res.data?.result?.access_token
  if (res.data?.success && token) {
    setStoredToken(token)
    return token
  }
  throw new Error('Auth failed')
}

export async function ensureToken(): Promise<string> {
  const stored = getStoredToken()
  if (stored && !isTokenExpired(stored)) return stored
  if (stored) removeStoredToken()
  return await loginAndStoreToken()
}

async function requestWithAuth<T>(path: string): Promise<ApiEnvelope<T>> {
  let token = await ensureToken()

  try {
    const resp = await chatApiClient.get<ApiEnvelope<T>>(path, {
      headers: { Authorization: `Bearer ${token}` },
    })
    return resp.data
  } catch (err) {
    const status = axios.isAxiosError(err) ? err.response?.status : undefined
    if (status !== 401) throw err

    token = await loginAndStoreToken()
    const retry = await chatApiClient.get<ApiEnvelope<T>>(path, {
      headers: { Authorization: `Bearer ${token}` },
    })
    return retry.data
  }
}

export async function sendInference(humanMessage: string): Promise<ApiEnvelope<InferenceResult>> {
  const projectId = import.meta.env.VITE_MILLS_AI_PROJECT_ID
  const conversationId = getConversationId()

  const params = new URLSearchParams({ human_message: humanMessage })
  if (conversationId) params.set('conversation_id', conversationId)

  return requestWithAuth<InferenceResult>(`/projects/${projectId}/inference?${params.toString()}`)
}

export async function getConversationHistory(
  conversationId: string,
): Promise<ApiEnvelope<ChatHistoryMessage[] | { messages: ChatHistoryMessage[] }>> {
  const projectId = import.meta.env.VITE_MILLS_AI_PROJECT_ID
  return requestWithAuth(`/projects/${projectId}/conversations/${conversationId}/history`)
}
