/**
 * loadingStates.screens.spec.ts — loading state + penjaga aksi ganda di
 * SEMUA layar stasiun mobile (audit loading state 2026-10-05):
 *   - 18 Monitor  : daftar draft memakai LoadingState; ketukan ganda New
 *                   Data hanya membuat SATU draft (dulu dua — terbukti di
 *                   browser: 0 -> 2 draft) dan tombol menampilkan "Membuat…".
 *   - 18 Form     : pemuatan draft memakai LoadingState; ketukan ganda Pause
 *                   / Simpan hanya menulis sekali; tombol "Menyimpan…".
 *   - 18 Preview  : daftar & detail memakai LoadingState; tarikan status
 *                   verifikasi latar belakang menampilkan penanda kecil.
 *
 * Satu repo Proxy generik menggantikan objek repo di ke-18 modul
 * `*RecordRepo` (ekspor lain — kelas error, konstanta — tetap asli), jadi
 * setiap layar diuji dengan jalur kode aslinya. "Ketukan ganda" dipicu
 * dengan dua `trigger('click')` TANPA menunggu render ulang di antaranya:
 * itu meniru dua ketukan yang tiba sebelum Vue sempat memasang `disabled`,
 * sehingga yang diuji adalah penjaga di handler, bukan atribut tombol.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import type { Component } from 'vue'

const h = vi.hoisted(() => {
  type Impl = (name: string, args: unknown[]) => unknown
  const calls: Array<{ name: string; args: unknown[] }> = []
  const state: { impl: Impl } = { impl: () => undefined }
  const repo = new Proxy(
    {},
    {
      get(_target, key) {
        if (typeof key === 'symbol' || key === 'then') return undefined
        return (...args: unknown[]) => {
          calls.push({ name: key, args })
          return state.impl(key, args)
        }
      },
    },
  )
  async function mockRepoModule(importOriginal: () => Promise<Record<string, unknown>>) {
    const original = await importOriginal()
    const out: Record<string, unknown> = { ...original }
    for (const key of Object.keys(original)) {
      if (/Repo$/.test(key) || key === 'default') out[key] = repo
    }
    return out
  }
  const route: { params: Record<string, string>; query: Record<string, string> } = { params: {}, query: {} }
  return {
    calls,
    state,
    mockRepoModule,
    route,
    pushMock: vi.fn(),
    pullMock: vi.fn(),
    syncAfterSaveMock: vi.fn(),
  }
})

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: h.pushMock }),
  useRoute: () => h.route,
}))
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    currentUser: { id: 'user-1', name: 'Operator Satu', role: 'operator', business_unit_id: 'bu-1' },
    businessUnit: { id: 'bu-1', name: 'Mill A' },
    logout: vi.fn().mockResolvedValue(undefined),
  }),
}))
vi.mock('@/stores/floatingClock', () => ({ useFloatingClockStore: () => ({ enabled: false, toggle: vi.fn() }) }))
vi.mock('@/stores/aiAssistant', () => ({
  useAiAssistantStore: () => ({ bubbleEnabled: false, open: vi.fn(), toggleBubble: vi.fn() }),
}))
vi.mock('@/services/writeThroughSync', () => ({ syncAfterSave: h.syncAfterSaveMock }))
vi.mock('@/services/recordVerificationApi', async (importOriginal) => ({
  ...(await importOriginal<Record<string, unknown>>()),
  pullVerificationStatus: h.pullMock,
}))
vi.mock('@/services/weighbridgeRecordRepo', h.mockRepoModule)
vi.mock('@/services/gradingRecordRepo', h.mockRepoModule)
vi.mock('@/services/cagesTrackRecordRepo', h.mockRepoModule)
vi.mock('@/services/sterilizerRecordRepo', h.mockRepoModule)
vi.mock('@/services/threshingRecordRepo', h.mockRepoModule)
vi.mock('@/services/pressingRecordRepo', h.mockRepoModule)
vi.mock('@/services/depricarpingRecordRepo', h.mockRepoModule)
vi.mock('@/services/kernelPlantRecordRepo', h.mockRepoModule)
vi.mock('@/services/solidWasteDisposalRecordRepo', h.mockRepoModule)
vi.mock('@/services/processWaterRecordRepo', h.mockRepoModule)
vi.mock('@/services/kernelDispatchRecordRepo', h.mockRepoModule)
vi.mock('@/services/cpoDispatchRecordRepo', h.mockRepoModule)
vi.mock('@/services/effluentPlantRecordRepo', h.mockRepoModule)
vi.mock('@/services/storageTankRecordRepo', h.mockRepoModule)
vi.mock('@/services/engineRoomRecordRepo', h.mockRepoModule)
vi.mock('@/services/boilerRoomRecordRepo', h.mockRepoModule)
vi.mock('@/services/clarificationRecordRepo', h.mockRepoModule)
vi.mock('@/services/processQualityControlRecordRepo', h.mockRepoModule)
vi.mock('@/services/stationRepo', h.mockRepoModule)

const monitorViews = import.meta.glob<{ default: Component }>('../src/views/Monitor*View.vue', { eager: true })
const formViews = import.meta.glob<{ default: Component }>('../src/views/Form*View.vue', { eager: true })
const previewViews = import.meta.glob<{ default: Component }>('../src/views/DataPreview*View.vue', { eager: true })

const entries = (views: Record<string, { default: Component }>) =>
  Object.entries(views).map(([path, mod]) => [path.replace(/^.*\//, '').replace('.vue', ''), mod.default] as const)

interface Deferred<T> {
  promise: Promise<T>
  resolve: (value: T) => void
}

function deferred<T>(): Deferred<T> {
  let resolve!: (value: T) => void
  const promise = new Promise<T>((r) => {
    resolve = r
  })
  return { promise, resolve }
}

const RECORD = {
  id: 'rec-1',
  user_id: 'user-1',
  status: 'draft_ongoing',
  date: '2026-10-05',
  created_at: '2026-10-05T01:00:00.000Z',
  updated_at: '2026-10-05T01:00:00.000Z',
}

/**
 * Pemuat satu draft/record — tiga bentuk di 18 repo: getDraftWithDetails
 * ({record, details}), getDraftWithTippedTimes ({record, tippedTimes} —
 * Cages Track), getDraftById (record langsung — Weighbridge).
 */
const DRAFT_LOADERS = ['getDraftWithDetails', 'getDraftWithTippedTimes', 'getDraftById']

function loadedDraft(name: string, record: Record<string, unknown> = RECORD): unknown {
  if (name === 'getDraftById') return { ...record }
  return { record: { ...record }, details: [], tippedTimes: [] }
}

/** Jawaban default repo Proxy — layar termuat normal tanpa data. */
function defaultImpl(name: string): unknown {
  if (DRAFT_LOADERS.includes(name)) return Promise.resolve(loadedDraft(name))
  if (/^get.*(Drafts|Records|Options|List)$/.test(name)) return Promise.resolve([])
  if (/^getLast/.test(name)) return Promise.resolve(null)
  if (/Summary$/.test(name)) return Promise.resolve({})
  if (name.startsWith('get')) return Promise.resolve(null)
  return Promise.resolve(undefined)
}

function callsOf(predicate: (name: string) => boolean): number {
  return h.calls.filter((call) => predicate(call.name)).length
}

beforeEach(() => {
  h.calls.length = 0
  h.state.impl = defaultImpl
  h.route.params = {}
  h.route.query = {}
  h.pushMock.mockReset().mockResolvedValue(undefined)
  h.pullMock.mockReset().mockResolvedValue(0)
  h.syncAfterSaveMock.mockReset().mockResolvedValue({ synced: false, rejection: null })
})

describe.each(entries(monitorViews))('%s — loading & New Data', (_name, View) => {
  it('menampilkan LoadingState (role=status) selama daftar draft lokal dimuat', async () => {
    const drafts = deferred<unknown[]>()
    h.state.impl = (name) => (name === 'getDrafts' ? drafts.promise : defaultImpl(name))

    const wrapper = mount(View)
    await flushPromises()

    const loading = wrapper.get('[data-testid="draft-list-loading"]')
    expect(loading.attributes('role')).toBe('status')
    expect(loading.text()).toMatch(/^Memuat daftar draft/)

    drafts.resolve([])
    await flushPromises()
    expect(wrapper.find('[data-testid="draft-list-loading"]').exists()).toBe(false)
  })

  it('ketukan ganda New Data membuat tepat SATU draft; tombol sibuk "Membuat…" sampai pindah layar', async () => {
    const created = deferred<string>()
    h.state.impl = (name) => (name === 'createDraft' ? created.promise : defaultImpl(name))

    const wrapper = mount(View)
    await flushPromises()

    const button = wrapper.get('[data-testid="new-data-button"]')
    void button.trigger('click')
    void button.trigger('click')
    await flushPromises()

    expect(callsOf((name) => name === 'createDraft')).toBe(1)
    expect(button.attributes('disabled')).toBeDefined()
    expect(button.attributes('aria-busy')).toBe('true')
    expect(button.text()).toBe('Membuat…')

    created.resolve('draft-baru')
    await flushPromises()

    expect(h.pushMock).toHaveBeenCalledTimes(1)
    expect(h.pushMock.mock.calls[0][0]).toMatchObject({ params: { id: 'draft-baru' } })
  })
})

async function mountLoadedForm(View: Component): Promise<VueWrapper> {
  h.route.params = { id: 'rec-1' }
  const wrapper = mount(View)
  await flushPromises()
  return wrapper
}

describe.each(entries(formViews))('%s — loading & aksi ganda', (_name, View) => {
  it('menampilkan LoadingState variant form selama draft dimuat', async () => {
    const draft = deferred<void>()
    h.state.impl = (name) =>
      DRAFT_LOADERS.includes(name) ? draft.promise.then(() => loadedDraft(name)) : defaultImpl(name)
    h.route.params = { id: 'rec-1' }

    const wrapper = mount(View)
    await flushPromises()

    const loading = wrapper.get('[data-testid="form-loading"]')
    expect(loading.attributes('aria-busy')).toBe('true')
    expect(loading.find('.loading-skeleton-field').exists()).toBe(true)

    draft.resolve()
    await flushPromises()
    expect(wrapper.find('[data-testid="form-loading"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="save-button"]').exists()).toBe(true)
  })

  it('ketukan ganda Pause menulis tepat sekali; tombol sibuk "Menyimpan…" dan aksi lain terkunci', async () => {
    const paused = deferred<void>()
    h.state.impl = (name) => (name.startsWith('pause') ? paused.promise : defaultImpl(name))

    const wrapper = await mountLoadedForm(View)
    const pause = wrapper.get('[data-testid="pause-button"]')

    void pause.trigger('click')
    void pause.trigger('click')
    await flushPromises()

    expect(callsOf((name) => name.startsWith('pause'))).toBe(1)
    expect(pause.attributes('aria-busy')).toBe('true')
    expect(pause.text()).toBe('Menyimpan…')
    expect(wrapper.get('[data-testid="save-button"]').attributes('disabled')).toBeDefined()

    // Simpan saat Pause masih berjalan juga diabaikan (satu tulis per waktu).
    void wrapper.get('[data-testid="save-button"]').trigger('click')
    await flushPromises()
    expect(callsOf((name) => name.startsWith('save'))).toBe(0)

    paused.resolve()
    await flushPromises()
    expect(h.pushMock).toHaveBeenCalledTimes(1)
  })
})

describe.each(entries(previewViews))('%s — loading daftar, detail, verifikasi', (_name, View) => {
  it('mode daftar: LoadingState variant list selama record lokal dimuat', async () => {
    const records = deferred<unknown[]>()
    h.state.impl = (name) => (name === 'getAllRecords' ? records.promise : defaultImpl(name))

    const wrapper = mount(View)
    await flushPromises()

    expect(wrapper.get('[data-testid="list-loading"]').find('.loading-skeleton-block--list').exists()).toBe(true)

    records.resolve([])
    await flushPromises()
    expect(wrapper.find('[data-testid="list-loading"]').exists()).toBe(false)
  })

  it('mode detail: LoadingState selama dimuat, lalu penanda verifikasi selama tarikan latar belakang', async () => {
    const detail = deferred<void>()
    const pull = deferred<number>()
    h.state.impl = (name) =>
      DRAFT_LOADERS.includes(name)
        ? detail.promise.then(() => loadedDraft(name, { ...RECORD, status: 'saved' }))
        : defaultImpl(name)
    h.pullMock.mockReturnValue(pull.promise)
    h.route.params = { id: 'rec-1' }

    const wrapper = mount(View)
    await flushPromises()
    expect(wrapper.find('[data-testid="detail-loading"]').exists()).toBe(true)

    detail.resolve()
    await flushPromises()

    expect(wrapper.find('[data-testid="detail-loading"]').exists()).toBe(false)
    const refreshing = wrapper.get('[data-testid="verification-refreshing"]')
    expect(refreshing.text()).toBe('Memperbarui status verifikasi…')

    pull.resolve(0)
    await flushPromises()
    expect(wrapper.find('[data-testid="verification-refreshing"]').exists()).toBe(false)
  })
})
