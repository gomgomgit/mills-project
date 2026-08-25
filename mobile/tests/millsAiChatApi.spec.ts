/**
 * millsAiChatApi.spec.ts — mobile/src/services/millsAiChatApi.ts.
 *
 * Ported from the reference aivena-widget project's src/widget/api.js +
 * store.js (no existing unit tests there to mirror — this establishes the
 * pattern for this app: `axios` itself is mocked at module level (rather
 * than '@/services/apiClient', which this file deliberately does NOT
 * use — see the module's own header comment) since millsAiChatApi.ts
 * creates its own axios.create() instance.
 *
 * localStorage is the REAL jsdom implementation (not mocked) — cleared in
 * beforeEach — since these functions are thin wrappers over it and a real
 * roundtrip is simpler and more trustworthy than mocking get/set/remove
 * individually.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { getMock, postMock, isAxiosErrorMock } = vi.hoisted(() => ({
  getMock: vi.fn(),
  postMock: vi.fn(),
  isAxiosErrorMock: vi.fn(),
}))

vi.mock('axios', () => ({
  default: {
    create: () => ({ get: getMock, post: postMock }),
    isAxiosError: isAxiosErrorMock,
  },
}))

import {
  ensureToken,
  getConversationHistory,
  getConversationId,
  getStoredToken,
  removeConversationId,
  removeStoredToken,
  sendInference,
  setConversationId,
  setStoredToken,
} from '@/services/millsAiChatApi'

function base64Url(input: string): string {
  return btoa(input).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
}

function makeJwt(expSecondsFromNow: number): string {
  const header = base64Url(JSON.stringify({ alg: 'none', typ: 'JWT' }))
  const payload = base64Url(JSON.stringify({ exp: Math.floor(Date.now() / 1000) + expSecondsFromNow }))
  return `${header}.${payload}.signature`
}

describe('millsAiChatApi', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
    isAxiosErrorMock.mockReturnValue(false)
  })

  describe('localStorage helpers', () => {
    it('stores, reads and removes the access token', () => {
      expect(getStoredToken()).toBeNull()
      setStoredToken('tok-1')
      expect(getStoredToken()).toBe('tok-1')
      removeStoredToken()
      expect(getStoredToken()).toBeNull()
    })

    it('stores, reads and removes the conversation id', () => {
      expect(getConversationId()).toBeNull()
      setConversationId('conv-1')
      expect(getConversationId()).toBe('conv-1')
      removeConversationId()
      expect(getConversationId()).toBeNull()
    })
  })

  describe('ensureToken()', () => {
    it('logs in and stores a fresh token when none is stored', async () => {
      postMock.mockResolvedValue({ data: { success: true, result: { access_token: makeJwt(3600) } } })

      const token = await ensureToken()

      expect(postMock).toHaveBeenCalledWith('/token', {
        email: expect.any(String),
        password: expect.any(String),
      })
      expect(getStoredToken()).toBe(token)
    })

    it('reuses a stored, non-expired token without logging in again', async () => {
      const token = makeJwt(3600)
      setStoredToken(token)

      const result = await ensureToken()

      expect(result).toBe(token)
      expect(postMock).not.toHaveBeenCalled()
    })

    it('removes an expired stored token and logs in again', async () => {
      setStoredToken(makeJwt(-10))
      postMock.mockResolvedValue({ data: { success: true, result: { access_token: makeJwt(3600) } } })

      await ensureToken()

      expect(postMock).toHaveBeenCalledTimes(1)
    })

    it('throws when the login response is missing an access_token', async () => {
      postMock.mockResolvedValue({ data: { success: false } })

      await expect(ensureToken()).rejects.toThrow('Auth failed')
    })
  })

  describe('sendInference()', () => {
    it('sends human_message (and conversation_id when present) with a Bearer token', async () => {
      setStoredToken(makeJwt(3600))
      setConversationId('conv-1')
      getMock.mockResolvedValue({ data: { success: true, result: { conversation_id: 'conv-1', ai_message: 'Halo' } } })

      const res = await sendInference('Berapa target FFB hari ini?')

      const [path, options] = getMock.mock.calls[0]
      expect(path).toContain('human_message=Berapa')
      expect(path).toContain('conversation_id=conv-1')
      expect(options.headers.Authorization).toMatch(/^Bearer /)
      expect(res.result?.ai_message).toBe('Halo')
    })

    it('omits conversation_id from the request when there is none stored yet', async () => {
      setStoredToken(makeJwt(3600))
      getMock.mockResolvedValue({ data: { success: true, result: { conversation_id: 'conv-new', ai_message: 'Hi' } } })

      await sendInference('Halo')

      const [path] = getMock.mock.calls[0]
      expect(path).not.toContain('conversation_id')
    })

    it('re-logs in and retries once on a 401 response', async () => {
      setStoredToken(makeJwt(3600))
      isAxiosErrorMock.mockReturnValue(true)
      getMock
        .mockRejectedValueOnce({ response: { status: 401 } })
        .mockResolvedValueOnce({ data: { success: true, result: { ai_message: 'Retried' } } })
      postMock.mockResolvedValue({ data: { success: true, result: { access_token: makeJwt(3600) } } })

      const res = await sendInference('Halo')

      expect(postMock).toHaveBeenCalledTimes(1)
      expect(getMock).toHaveBeenCalledTimes(2)
      expect(res.result?.ai_message).toBe('Retried')
    })
  })

  describe('getConversationHistory()', () => {
    it('requests the history endpoint for the given conversation id', async () => {
      setStoredToken(makeJwt(3600))
      getMock.mockResolvedValue({ data: { success: true, result: { messages: [] } } })

      await getConversationHistory('conv-1')

      const [path] = getMock.mock.calls[0]
      expect(path).toContain('/conversations/conv-1/history')
    })
  })
})
