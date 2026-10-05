/**
 * ReportingPilihStasiunView.spec.ts — screen-141--reporting-pilih-stasiun-mobile /
 * usecase-143--reporting-pilih-stasiun-mobile "Pilih Stasiun untuk Laporan
 * (Mobile)".
 *
 * Meliput seluruh 13 unit_test_cases dan seluruh component_test pada 11
 * test_scenarios tech spec screen-141. Beberapa test sengaja memenuhi lebih
 * dari satu unit_test_case / scenario sekaligus (ditandai di komentar
 * masing-masing) — konvensi yang sama dengan DashboardReportingView.spec.ts
 * dan StationListView.spec.ts.
 *
 * ────────────────────────────────────────────────────────────────────────
 * JEBAKAN YANG DIJAGA BERKAS INI: ketersediaan laporan ≠ StationSlot.isActive
 * ────────────────────────────────────────────────────────────────────────
 * Hari ini Sterilizer kebetulan isActive = true DAN punya layar laporan,
 * sehingga implementasi keliru `isActive && REPORT_ROUTES[type]` akan tetap
 * hijau di hampir semua test yang memakai data realistis. Dua test di bawah
 * ada khusus untuk menjatuhkannya, dengan fixture yang sengaja dibuat
 * "tidak realistis":
 *
 *   - 'threshing' dengan isActive = true → tile WAJIB nonaktif (kodenya
 *     tidak ada di REPORT_ROUTES).
 *   - 'sterilizer' dengan isActive = false → tile WAJIB tetap tersedia dan
 *     tetap bernavigasi (kodenya ada di REPORT_ROUTES).
 *
 * Jangan "merapikan" kedua fixture itu agar isActive-nya masuk akal — justru
 * ketidakwajarannya yang menjadi alat ujinya.
 *
 * Strategi mocking (mengikuti StationListView.spec.ts / DashboardReportingView.spec.ts):
 *   - 'vue-router' dimock di tingkat modul sehingga `router.push` dapat
 *     diasersi lewat `pushMock` yang di-hoist, tanpa instance router nyata.
 *     (Skenario "Belum masuk" adalah satu-satunya pengecualian — describe
 *     block tersendiri di bawah memakai router/index.ts SUNGGUHAN, sesuai
 *     instruksi tech spec bahwa skenario itu menguji lapisan router, bukan
 *     komponen yang terpasang.)
 *   - '@/stores/auth' dimock lewat `useAuthStoreMock` yang di-hoist, agar
 *     `currentUser` dapat ditimpa per test (id → kunci localStorage
 *     production line; business_unit_id → jalur baca per business unit).
 *   - '@/stores/floatingClock' / '@/stores/aiAssistant' dimock semata agar
 *     header yang disalin verbatim dapat dipasang tanpa Pinia sungguhan.
 *   - '@/services/stationRepo' dimock di tingkat modul sehingga berkas ini
 *     tidak pernah menyentuh localDb.ts / koneksi SQLite nyata.
 *   - '@/services/apiClient' dan '@/services/localDb' dimock dengan spy —
 *     komponen ini tidak mengimpor keduanya, dan justru itulah yang
 *     diasersi test "nol pemanggilan jaringan".
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import ReportingPilihStasiunView from '@/views/ReportingPilihStasiunView.vue'
import type { StationSlot } from '@/services/stationRepo'

const { pushMock } = vi.hoisted(() => ({ pushMock: vi.fn() }))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
  useRoute: () => ({ query: {} }),
}))

const { useAuthStoreMock, logoutMock } = vi.hoisted(() => ({
  useAuthStoreMock: vi.fn(),
  logoutMock: vi.fn().mockResolvedValue(undefined),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: useAuthStoreMock,
}))

vi.mock('@/stores/floatingClock', () => ({
  useFloatingClockStore: () => ({ enabled: false, toggle: vi.fn() }),
}))

vi.mock('@/stores/aiAssistant', () => ({
  useAiAssistantStore: () => ({
    isOpen: false,
    bubbleEnabled: true,
    open: vi.fn(),
    close: vi.fn(),
    toggleBubble: vi.fn(),
  }),
}))

const { getActiveAndPlaceholderStationsMock, getActiveAndPlaceholderStationsForProductionLineMock } = vi.hoisted(
  () => ({
    getActiveAndPlaceholderStationsMock: vi.fn(),
    getActiveAndPlaceholderStationsForProductionLineMock: vi.fn(),
  }),
)

vi.mock('@/services/stationRepo', () => ({
  stationRepo: {
    getActiveAndPlaceholderStations: getActiveAndPlaceholderStationsMock,
    getActiveAndPlaceholderStationsForProductionLine: getActiveAndPlaceholderStationsForProductionLineMock,
  },
  getActiveAndPlaceholderStations: getActiveAndPlaceholderStationsMock,
}))

const { apiClientGetMock, apiClientPostMock } = vi.hoisted(() => ({
  apiClientGetMock: vi.fn(),
  apiClientPostMock: vi.fn(),
}))

vi.mock('@/services/apiClient', () => ({
  default: { get: apiClientGetMock, post: apiClientPostMock },
}))

const { localDbQueryMock, localDbRunMock } = vi.hoisted(() => ({
  localDbQueryMock: vi.fn(),
  localDbRunMock: vi.fn(),
}))

vi.mock('@/services/localDb', () => ({
  localDb: { query: localDbQueryMock, run: localDbRunMock },
  query: localDbQueryMock,
  run: localDbRunMock,
  default: { query: localDbQueryMock, run: localDbRunMock },
}))

function makeStation(overrides: Partial<StationSlot> & { id: string }): StationSlot {
  return {
    businessUnitId: 'bu-1',
    name: `Stasiun ${overrides.id}`,
    type: 'other',
    isActive: false,
    icon: null,
    ...overrides,
  }
}

/**
 * Tiga stasiun berurutan, dengan nama yang SENGAJA bukan turunan dari
 * type-nya — kalau layar ini diam-diam menamai ulang tile dari `type`
 * (alih-alih memakai `name` apa adanya), test urutan/nama di bawah gagal.
 */
const THREE_STATIONS: StationSlot[] = [
  makeStation({ id: 'st-1', name: 'Sterilizer Lini A', type: 'sterilizer', isActive: true }),
  makeStation({ id: 'st-2', name: 'Threshing Lini A', type: 'threshing', isActive: true }),
  makeStation({ id: 'st-3', name: 'Pressing Lini A', type: 'pressing', isActive: true }),
]

function mockAuthUser(role: 'operator' | 'supervisor' | 'mill_management' | 'admin' = 'operator') {
  useAuthStoreMock.mockReturnValue({
    currentUser: {
      id: 'user-1',
      username: `${role}01`,
      name: 'Pengguna Uji',
      role,
      business_unit_id: 'bu-1',
    },
    logout: logoutMock,
  })
}

async function mountWithStations(stations: StationSlot[]) {
  getActiveAndPlaceholderStationsMock.mockResolvedValue(stations)
  const wrapper = mount(ReportingPilihStasiunView)
  await flushPromises()

  return wrapper
}

describe('ReportingPilihStasiunView — "Pilih Stasiun untuk Laporan (Mobile)"', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    // implementation_notes: layar ini membaca production line aktif dari
    // localStorage, jadi tanpa ini sebuah test mewarisi pilihan test
    // sebelumnya.
    window.localStorage.clear()
    logoutMock.mockResolvedValue(undefined)
    mockAuthUser('operator')
    getActiveAndPlaceholderStationsMock.mockResolvedValue([])
    getActiveAndPlaceholderStationsForProductionLineMock.mockResolvedValue([])
  })

  // unit_test_case 1 — jumlah, urutan, dan nama tile sama persis dengan
  // StationSlot[] yang dikembalikan repo; tidak ada penyusunan ulang
  // maupun penamaan ulang di layar ini.
  it('memetakan setiap StationSlot menjadi satu tile dengan nama dan urutan yang sama persis', async () => {
    const wrapper = await mountWithStations(THREE_STATIONS)

    const tiles = wrapper.findAll('[data-testid^="station-tile-"]')
    expect(tiles).toHaveLength(3)
    expect(tiles[0].attributes('data-testid')).toBe('station-tile-sterilizer')
    expect(tiles[0].text()).toContain('Sterilizer Lini A')
    expect(tiles[1].attributes('data-testid')).toBe('station-tile-threshing')
    expect(tiles[1].text()).toContain('Threshing Lini A')
    expect(tiles[2].attributes('data-testid')).toBe('station-tile-pressing')
    expect(tiles[2].text()).toContain('Pressing Lini A')
  })

  // unit_test_case 1 (bagian ikon) — tiap tile membawa ikon SVG per jenis
  // stasiun, dan ikon per jenis itu berbeda satu sama lain (bukan satu
  // ikon cadangan yang sama untuk semuanya).
  it('merender ikon SVG per jenis stasiun, berbeda antar jenis', async () => {
    const wrapper = await mountWithStations(THREE_STATIONS)

    const icons = wrapper.findAll('.station-tile-icon').map((icon) => icon.html())
    expect(icons).toHaveLength(3)
    for (const icon of icons) {
      expect(icon).toContain('<svg')
      expect(icon.length).toBeGreaterThan(60)
    }
    expect(new Set(icons).size).toBe(3)
  })

  // unit_test_case 1 (bagian override ikon) — `station.icon` (override
  // Mills Setting) menang atas ikon per jenis, sama seperti StationGrid.vue.
  it('memakai override ikon dari station.icon bila ada', async () => {
    const wrapper = await mountWithStations([
      makeStation({ id: 'st-1', name: 'Sterilizer Lini A', type: 'sterilizer', isActive: true, icon: 'truck' }),
    ])

    expect(wrapper.get('.station-tile-icon').html()).toContain('M14 10h4l3 3v4h-7')
  })

  // unit_test_case 2 + Scenario "success" (bagian ReportingPilihStasiunView)
  it('menandai tile tersedia bila kode stasiun ada di REPORT_ROUTES', async () => {
    const wrapper = await mountWithStations(THREE_STATIONS)

    const tile = wrapper.get('[data-testid="station-tile-sterilizer"]')
    expect(tile.attributes('aria-disabled')).toBe('false')
    expect(tile.text()).not.toContain('Belum tersedia')
    expect(tile.classes()).toContain('station-tile--available')
  })

  // unit_test_case 3 + Scenario "Stasiun aktif di mill namun laporannya
  // belum ada".
  //
  // JEBAKAN UTAMA (1 dari 2): isActive = true TIDAK membuat sebuah stasiun
  // tersedia. Satu-satunya penentu adalah REPORT_ROUTES, yang hari ini
  // hanya memuat 'sterilizer'. Tanpa test ini, implementasi keliru
  // `isActive && REPORT_ROUTES[type]` lolos tanpa terdeteksi.
  it('menandai tile nonaktif bila kodenya tidak ada di REPORT_ROUTES MESKIPUN isActive=true', async () => {
    const wrapper = await mountWithStations([
      makeStation({ id: 'st-2', name: 'Threshing', type: 'threshing', isActive: true }),
    ])

    const tile = wrapper.get('[data-testid="station-tile-threshing"]')
    expect(tile.attributes('aria-disabled')).toBe('true')
    expect(tile.text()).toContain('Belum tersedia')
    expect(tile.classes()).toContain('station-tile--unavailable')

    // Ditekan pun hanya mengisi pesan — tidak berpindah rute.
    await tile.trigger('click')
    expect(wrapper.get('[data-testid="info-message"]').text()).toBe('Laporan Threshing belum tersedia di aplikasi mobile.')
    expect(pushMock).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="station-tile-threshing"]').exists()).toBe(true)
  })

  // unit_test_case 4.
  //
  // JEBAKAN UTAMA (2 dari 2): kebalikannya — isActive = false TIDAK membuat
  // stasiun yang laporannya sudah dibangun menjadi tidak tersedia. Tile
  // tetap dapat ditekan dan tetap bernavigasi.
  it('menghitung tile tetap tersedia meskipun isActive=false, selama kodenya ada di REPORT_ROUTES', async () => {
    const wrapper = await mountWithStations([
      makeStation({ id: 'st-1', name: 'Sterilizer', type: 'sterilizer', isActive: false }),
    ])

    const tile = wrapper.get('[data-testid="station-tile-sterilizer"]')
    expect(tile.attributes('aria-disabled')).toBe('false')
    expect(tile.text()).not.toContain('Belum tersedia')
    expect(tile.classes()).toContain('station-tile--available')

    await tile.trigger('click')
    expect(pushMock).toHaveBeenCalledTimes(1)
    expect(pushMock).toHaveBeenCalledWith({ name: 'report-sterilizer', query: {} })
    expect(wrapper.find('[data-testid="info-message"]').exists()).toBe(false)
  })

  // unit_test_case 5 — jalur baca per production line.
  it('memakai getActiveAndPlaceholderStationsForProductionLine ketika ada production line aktif', async () => {
    window.localStorage.setItem('msl_production_line_user-1', 'pl-9')
    getActiveAndPlaceholderStationsForProductionLineMock.mockResolvedValue(THREE_STATIONS)

    const wrapper = mount(ReportingPilihStasiunView)
    await flushPromises()

    expect(getActiveAndPlaceholderStationsForProductionLineMock).toHaveBeenCalledTimes(1)
    expect(getActiveAndPlaceholderStationsForProductionLineMock).toHaveBeenCalledWith('pl-9')
    expect(getActiveAndPlaceholderStationsMock).not.toHaveBeenCalled()
    expect(wrapper.findAll('[data-testid^="station-tile-"]')).toHaveLength(3)
  })

  // unit_test_case 6 — jalur baca per business unit.
  it('memakai getActiveAndPlaceholderStations(businessUnitId) ketika tidak ada production line aktif', async () => {
    const wrapper = await mountWithStations(THREE_STATIONS)

    expect(getActiveAndPlaceholderStationsMock).toHaveBeenCalledTimes(1)
    expect(getActiveAndPlaceholderStationsMock).toHaveBeenCalledWith('bu-1')
    expect(getActiveAndPlaceholderStationsForProductionLineMock).not.toHaveBeenCalled()
    expect(wrapper.findAll('[data-testid^="station-tile-"]')).toHaveLength(3)
  })

  // Pilihan production line milik PENGGUNA LAIN tidak boleh terbaca —
  // kuncinya ber-suffix userId, sama persis dengan StationListView.vue.
  it('mengabaikan pilihan production line milik pengguna lain', async () => {
    window.localStorage.setItem('msl_production_line_user-lain', 'pl-2')

    await mountWithStations(THREE_STATIONS)

    expect(getActiveAndPlaceholderStationsForProductionLineMock).not.toHaveBeenCalled()
    expect(getActiveAndPlaceholderStationsMock).toHaveBeenCalledWith('bu-1')
  })

  // Penyimpanan lokal yang melempar (mode privat) tidak boleh mematahkan
  // layar — jatuh ke jalur business unit, bukan layar kosong/error.
  it('jatuh ke jalur business unit ketika localStorage melempar', async () => {
    const getItemSpy = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('storage disabled')
    })

    try {
      const wrapper = await mountWithStations(THREE_STATIONS)

      expect(getActiveAndPlaceholderStationsMock).toHaveBeenCalledWith('bu-1')
      expect(wrapper.findAll('[data-testid^="station-tile-"]')).toHaveLength(3)
      expect(wrapper.text()).not.toMatch(/gagal|error|kesalahan/i)
    } finally {
      getItemSpy.mockRestore()
    }
  })

  // Tanpa business_unit_id pada sesi, tidak ada yang bisa dibaca — hasilnya
  // daftar kosong dengan penanganan yang sama (arahan), bukan pesan teknis.
  it('menampilkan arahan tanpa memanggil repo ketika sesi tidak punya business_unit_id', async () => {
    useAuthStoreMock.mockReturnValue({
      currentUser: { id: 'user-1', username: 'operator01', role: 'operator', business_unit_id: null },
      logout: logoutMock,
    })

    const wrapper = mount(ReportingPilihStasiunView)
    await flushPromises()

    expect(getActiveAndPlaceholderStationsMock).not.toHaveBeenCalled()
    expect(getActiveAndPlaceholderStationsForProductionLineMock).not.toHaveBeenCalled()
    expect(wrapper.get('[data-testid="no-stations"]').exists()).toBe(true)
    expect(wrapper.findAll('[data-testid^="station-tile-"]')).toHaveLength(0)
  })

  // unit_test_case 7 + Scenario "Perangkat offline". Nol pemanggilan
  // jaringan: apiClient/localDb sengaja dibuat menolak setiap pemanggilan,
  // sehingga kalau layar ini menyentuh salah satunya, test ini gagal.
  it('scenario: perangkat offline / nol pemanggilan jaringan — hanya stationRepo yang dipanggil', async () => {
    apiClientGetMock.mockRejectedValue(new Error('should never be called'))
    apiClientPostMock.mockRejectedValue(new Error('should never be called'))
    localDbQueryMock.mockRejectedValue(new Error('should never be called'))
    localDbRunMock.mockRejectedValue(new Error('should never be called'))

    const wrapper = await mountWithStations(THREE_STATIONS)

    await wrapper.get('[data-testid="station-tile-threshing"]').trigger('click')
    await wrapper.get('[data-testid="station-tile-sterilizer"]').trigger('click')
    await flushPromises()

    expect(apiClientGetMock).not.toHaveBeenCalled()
    expect(apiClientPostMock).not.toHaveBeenCalled()
    expect(localDbQueryMock).not.toHaveBeenCalled()
    expect(localDbRunMock).not.toHaveBeenCalled()
    expect(getActiveAndPlaceholderStationsMock).toHaveBeenCalledTimes(1)

    // Seluruh tile tetap tampil lengkap; tidak ada pesan kesalahan jaringan.
    expect(wrapper.findAll('[data-testid^="station-tile-"]')).toHaveLength(3)
    expect(wrapper.text()).not.toMatch(/gagal|error|kesalahan jaringan|offline/i)
  })

  // unit_test_case 8 + Scenario "Belum ada stasiun tersimpan". Arahan, bukan
  // pesan kesalahan teknis — dan station-grid tetap ada, hanya tanpa tile.
  it('scenario: belum ada stasiun tersimpan — menampilkan arahan membuka Daftar Stasiun, tanpa satu pun tile', async () => {
    const wrapper = await mountWithStations([])

    const empty = wrapper.get('[data-testid="no-stations"]')
    expect(empty.text()).toContain('Daftar Stasiun')
    expect(empty.text()).toMatch(/belum ada stasiun tersimpan/i)
    expect(wrapper.findAll('[data-testid^="station-tile-"]')).toHaveLength(0)
    expect(wrapper.find('[data-testid="station-grid"]').exists()).toBe(true)
    expect(wrapper.text()).not.toMatch(/gagal|error|kesalahan|sqlite|jaringan/i)
    expect(wrapper.find('[data-testid="info-message"]').exists()).toBe(false)
  })

  // Audit 2026-10-04 — login baru, cache `station` lokal masih kosong: layar
  // ini dulu kosong sampai Daftar Stasiun dibuka sekali. Sekarang ia mengisi
  // cache-nya sendiri dari server (jalur yang sama dengan Daftar Stasiun).
  it('cache stasiun kosong — mengambil Production Line + stasiun dari server sendiri, tanpa harus membuka Daftar Stasiun', async () => {
    apiClientGetMock.mockImplementation(async (url: string) => {
      if (url === '/api/production-lines/current') return { data: { data: [{ id: 'pl-1', name: 'Line 1', code: 'L1' }] } }
      if (url === '/api/production-lines/current/stations') return { data: { data: [] } }
      throw new Error(`unexpected ${url}`)
    })
    localDbRunMock.mockResolvedValue({ changes: 1 })
    getActiveAndPlaceholderStationsForProductionLineMock.mockResolvedValue(THREE_STATIONS)

    const wrapper = await mountWithStations([])

    expect(apiClientGetMock).toHaveBeenCalledWith('/api/production-lines/current')
    expect(apiClientGetMock).toHaveBeenCalledWith('/api/production-lines/current/stations', {
      params: { production_line_id: 'pl-1' },
    })
    expect(getActiveAndPlaceholderStationsForProductionLineMock).toHaveBeenCalledWith('pl-1')
    expect(wrapper.findAll('[data-testid^="station-tile-"]')).toHaveLength(3)
    expect(wrapper.find('[data-testid="no-stations"]').exists()).toBe(false)
    // Line yang dipilih otomatis BUKAN pilihan pengguna — tidak diingat.
    expect(window.localStorage.getItem('msl_production_line_user-1')).toBeNull()
  })

  it('cache stasiun kosong dan offline — jatuh ke 18 stasiun bawaan, sama seperti Daftar Stasiun', async () => {
    apiClientGetMock.mockRejectedValue({ message: 'offline', network: true })
    localDbRunMock.mockResolvedValue({ changes: 1 })
    getActiveAndPlaceholderStationsMock.mockResolvedValueOnce([]).mockResolvedValueOnce(THREE_STATIONS)

    const wrapper = mount(ReportingPilihStasiunView)
    await flushPromises()

    expect(localDbRunMock).toHaveBeenCalledWith('DELETE FROM station WHERE business_unit_id = ?', ['bu-1'])
    expect(wrapper.findAll('[data-testid^="station-tile-"]')).toHaveLength(3)
  })

  // Pembacaan tabel lokal yang gagal berakhir sama dengan daftar kosong —
  // tidak ada pesan SQLite yang dilempar ke operator di lantai pabrik.
  it('memperlakukan kegagalan pembacaan tabel lokal sebagai daftar kosong, tanpa pesan teknis', async () => {
    getActiveAndPlaceholderStationsMock.mockRejectedValue(new Error('SQLITE_ERROR: no such table: station'))

    const wrapper = mount(ReportingPilihStasiunView)
    await flushPromises()

    expect(wrapper.get('[data-testid="no-stations"]').exists()).toBe(true)
    expect(wrapper.findAll('[data-testid^="station-tile-"]')).toHaveLength(0)
    expect(wrapper.text()).not.toMatch(/SQLITE|gagal|error/i)
  })

  // unit_test_case 9 + Scenario "success" (bagian penekanan tile).
  it('berpindah rute tepat sekali ketika tile bertautan ditekan', async () => {
    const wrapper = await mountWithStations(THREE_STATIONS)

    await wrapper.get('[data-testid="station-tile-sterilizer"]').trigger('click')

    expect(pushMock).toHaveBeenCalledTimes(1)
    expect(pushMock).toHaveBeenCalledWith({ name: 'report-sterilizer', query: {} })
    expect(wrapper.find('[data-testid="info-message"]').exists()).toBe(false)
  })

  // unit_test_case 10 + Scenario "Menekan stasiun yang belum tersedia".
  it('scenario: menekan stasiun yang belum tersedia — mengisi pesan dan tidak berpindah rute', async () => {
    const wrapper = await mountWithStations([
      makeStation({ id: 'st-2', name: 'Threshing', type: 'threshing', isActive: true }),
    ])

    expect(wrapper.find('[data-testid="info-message"]').exists()).toBe(false)

    await wrapper.get('[data-testid="station-tile-threshing"]').trigger('click')

    const infoMessage = wrapper.get('[data-testid="info-message"]')
    expect(infoMessage.text()).toBe('Laporan Threshing belum tersedia di aplikasi mobile.')
    expect(infoMessage.attributes('role')).toBe('status')
    expect(pushMock).not.toHaveBeenCalled()

    const tile = wrapper.get('[data-testid="station-tile-threshing"]')
    expect(tile.attributes('aria-disabled')).toBe('true')
    expect(tile.text()).toContain('Belum tersedia')
  })

  // unit_test_case 11 + Scenario "Menekan berkali-kali". Yang diasersi
  // adalah JUMLAH elemen info-message, bukan sekadar isinya — itulah
  // bentuk konkret dari aturan "pesan tidak menumpuk".
  it('scenario: menekan berkali-kali — pesan tidak pernah menumpuk, hanya tertimpa', async () => {
    const wrapper = await mountWithStations([
      makeStation({ id: 'st-2', name: 'Threshing', type: 'threshing', isActive: true }),
      makeStation({ id: 'st-3', name: 'Pressing', type: 'pressing', isActive: true }),
    ])

    const threshing = wrapper.get('[data-testid="station-tile-threshing"]')
    await threshing.trigger('click')
    await wrapper.vm.$nextTick()
    expect(wrapper.findAll('[data-testid="info-message"]')).toHaveLength(1)
    await threshing.trigger('click')
    await wrapper.vm.$nextTick()
    expect(wrapper.findAll('[data-testid="info-message"]')).toHaveLength(1)
    await threshing.trigger('click')
    await wrapper.vm.$nextTick()
    expect(wrapper.findAll('[data-testid="info-message"]')).toHaveLength(1)
    expect(wrapper.get('[data-testid="info-message"]').text()).toBe('Laporan Threshing belum tersedia di aplikasi mobile.')

    await wrapper.get('[data-testid="station-tile-pressing"]').trigger('click')
    await wrapper.vm.$nextTick()

    const infoMessages = wrapper.findAll('[data-testid="info-message"]')
    expect(infoMessages).toHaveLength(1)
    expect(infoMessages[0].text()).toBe('Laporan Pressing belum tersedia di aplikasi mobile.')
    expect(pushMock).not.toHaveBeenCalled()
  })

  // unit_test_case 12 + Scenario "Kembali" (segmen 'Dashboard & Reporting').
  it('scenario: kembali — breadcrumb-dashboard-reporting bernavigasi ke "dashboard-reporting"', async () => {
    const wrapper = await mountWithStations(THREE_STATIONS)

    await wrapper.get('[data-testid="breadcrumb-dashboard-reporting"]').trigger('click')

    expect(pushMock).toHaveBeenCalledTimes(1)
    expect(pushMock).toHaveBeenCalledWith({ name: 'dashboard-reporting' })
  })

  // unit_test_case 12 + Scenario "Kembali" (segmen 'Home').
  it('scenario: kembali — breadcrumb-home bernavigasi ke "home"', async () => {
    const wrapper = await mountWithStations(THREE_STATIONS)

    await wrapper.get('[data-testid="breadcrumb-home"]').trigger('click')

    expect(pushMock).toHaveBeenCalledTimes(1)
    expect(pushMock).toHaveBeenCalledWith({ name: 'home' })
  })

  // Breadcrumb tiga segmen: dua pertama dapat ditekan, yang terakhir
  // adalah halaman kini (bukan tautan).
  it('menampilkan breadcrumb tiga segmen "Home / Dashboard & Reporting / Reporting"', async () => {
    const wrapper = await mountWithStations(THREE_STATIONS)

    const breadcrumb = wrapper.get('.breadcrumb')
    expect(breadcrumb.text()).toContain('Home')
    expect(breadcrumb.text()).toContain('Dashboard & Reporting')
    expect(breadcrumb.text()).toContain('Reporting')
    expect(wrapper.get('[data-testid="breadcrumb-home"]').element.tagName).toBe('BUTTON')
    expect(wrapper.get('[data-testid="breadcrumb-dashboard-reporting"]').element.tagName).toBe('BUTTON')
    expect(wrapper.get('.breadcrumb-current').attributes('aria-current')).toBe('page')
    expect(wrapper.get('.screen-title').text()).toBe('Reporting')
  })

  // unit_test_case 13 + Scenario "success" — render utuh dari data lokal.
  it('scenario: success — grid tampil utuh, sterilizer dapat ditekan, sisanya nonaktif, pesan kosong, nol jaringan', async () => {
    const wrapper = await mountWithStations(THREE_STATIONS)

    expect(wrapper.get('[data-testid="station-grid"]').exists()).toBe(true)
    expect(wrapper.findAll('[data-testid^="station-tile-"]')).toHaveLength(3)
    expect(wrapper.get('[data-testid="station-tile-sterilizer"]').attributes('aria-disabled')).toBe('false')
    expect(wrapper.get('[data-testid="station-tile-threshing"]').attributes('aria-disabled')).toBe('true')
    expect(wrapper.get('[data-testid="station-tile-pressing"]').attributes('aria-disabled')).toBe('true')
    expect(wrapper.find('[data-testid="info-message"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="no-stations"]').exists()).toBe(false)
    expect(apiClientGetMock).not.toHaveBeenCalled()
    expect(apiClientPostMock).not.toHaveBeenCalled()
  })

  // Scenario "stasiun yang belum tersedia tidak boleh disembunyikan" —
  // 5 StationSlot masuk, 5 tile keluar; 4 di antaranya nonaktif dan tidak
  // satu pun dihilangkan dari grid.
  it('scenario: stasiun belum tersedia tidak disembunyikan — 5 StationSlot menghasilkan 5 tile, 4 nonaktif', async () => {
    const wrapper = await mountWithStations([
      makeStation({ id: 'st-1', name: 'Sterilizer', type: 'sterilizer', isActive: true }),
      makeStation({ id: 'st-2', name: 'Threshing', type: 'threshing', isActive: true }),
      makeStation({ id: 'st-3', name: 'Pressing', type: 'pressing', isActive: true }),
      makeStation({ id: 'st-4', name: 'Depricarping', type: 'depricarping', isActive: true }),
      makeStation({ id: 'st-5', name: 'Kernel Plant', type: 'kernel-plant', isActive: true }),
    ])

    const tiles = wrapper.findAll('[data-testid^="station-tile-"]')
    expect(tiles).toHaveLength(5)

    const disabled = tiles.filter((tile) => tile.attributes('aria-disabled') === 'true')
    expect(disabled).toHaveLength(4)
    for (const tile of disabled) {
      expect(tile.text()).toContain('Belum tersedia')
    }
    expect(wrapper.get('[data-testid="station-tile-sterilizer"]').attributes('aria-disabled')).toBe('false')
  })

  // edge_case "Menekan tile tanpa laporan" — sengaja BUKAN atribut
  // `disabled` native: `disabled` menelan ketukan sehingga tidak ada pesan
  // yang muncul (lihat StationGrid.vue). Diasersi untuk SELURUH grid.
  it('tidak memakai atribut disabled native di mana pun pada grid', async () => {
    const wrapper = await mountWithStations([
      makeStation({ id: 'st-1', name: 'Sterilizer', type: 'sterilizer', isActive: true }),
      makeStation({ id: 'st-2', name: 'Threshing', type: 'threshing', isActive: true }),
      makeStation({ id: 'st-3', name: 'Pressing', type: 'pressing', isActive: false }),
    ])

    const tiles = wrapper.findAll('[data-testid^="station-tile-"]')
    expect(tiles).toHaveLength(3)
    for (const tile of tiles) {
      expect(tile.attributes('disabled')).toBeUndefined()
      expect(tile.element.hasAttribute('disabled')).toBe(false)
    }
  })

  // Scenario "layar tidak meminta pengguna memilih mill" — grid langsung
  // tampil pada render pertama; tidak ada pemilih mill/dropdown/dialog.
  it('scenario: layar tidak meminta memilih mill — grid langsung tampil tanpa pemilih atau dialog', async () => {
    const wrapper = await mountWithStations(THREE_STATIONS)

    expect(wrapper.get('[data-testid="station-grid"]').isVisible()).toBe(true)
    expect(wrapper.findAll('select')).toHaveLength(0)
    expect(wrapper.findAll('dialog')).toHaveLength(0)
    expect(wrapper.findAll('[role="dialog"]')).toHaveLength(0)
    expect(wrapper.text()).not.toMatch(/pilih mill|pilih business unit|pilih unit/i)

    await wrapper.get('[data-testid="station-tile-sterilizer"]').trigger('click')
    expect(pushMock).toHaveBeenCalledWith({ name: 'report-sterilizer', query: {} })
  })

  // Scenario "layar tetap terbuka untuk semua peran mobile" — layar ini
  // tidak punya satu pun cabang berbasis peran; render harus identik.
  it.each(['actor-station-operator', 'actor-supervisor', 'actor-mill-management', 'actor-admin'] as const)(
    'scenario: layar terbuka untuk semua peran mobile — render identik untuk %s',
    async (actorId) => {
      const roleByActor = {
        'actor-station-operator': 'operator',
        'actor-supervisor': 'supervisor',
        'actor-mill-management': 'mill_management',
        'actor-admin': 'admin',
      } as const
      mockAuthUser(roleByActor[actorId])

      const wrapper = await mountWithStations(THREE_STATIONS)

      const tiles = wrapper.findAll('[data-testid^="station-tile-"]')
      expect(tiles).toHaveLength(3)
      expect(tiles.map((tile) => tile.attributes('aria-disabled'))).toEqual(['false', 'true', 'true'])
      expect(tiles.map((tile) => tile.attributes('data-testid'))).toEqual([
        'station-tile-sterilizer',
        'station-tile-threshing',
        'station-tile-pressing',
      ])
      expect(wrapper.text()).not.toMatch(/akses ditolak|tidak diizinkan|forbidden/i)
      expect(pushMock).not.toHaveBeenCalled()
    },
  )

  // Seluruh teks UI Bahasa Indonesia (implementation_notes) — tidak ada
  // sisa teks Inggris pada label yang ditulis layar ini sendiri.
  it('menulis seluruh teks layar dalam Bahasa Indonesia', async () => {
    const wrapper = await mountWithStations([
      makeStation({ id: 'st-2', name: 'Threshing', type: 'threshing', isActive: true }),
    ])

    await wrapper.get('[data-testid="station-tile-threshing"]').trigger('click')

    expect(wrapper.get('[data-testid="station-tile-threshing"]').text()).toContain('Belum tersedia')
    expect(wrapper.get('[data-testid="info-message"]').text()).toBe('Laporan Threshing belum tersedia di aplikasi mobile.')
    expect(wrapper.text()).not.toMatch(/not available|no stations|loading\b/i)
  })
})

// Scenario: "Pilih Stasiun untuk Laporan (Mobile) — Belum masuk"
//
// Sesuai tech spec, skenario ini menyasar lapisan ROUTER, bukan komponen
// yang terpasang — ReportingPilihStasiunView tidak pernah dipasang di
// describe block ini, karena di produksi pun ia tidak pernah dipasang saat
// tidak ada sesi. Memakai router/index.ts SUNGGUHAN (bukan mock
// 'vue-router' di atas) dan Pinia sungguhan yang segar, pola yang sama
// dengan DashboardReportingView.spec.ts.
describe('ReportingPilihStasiunView — penjagaan router "Belum masuk"', () => {
  beforeEach(() => {
    window.localStorage.clear()
  })

  it('scenario: belum masuk — rute "report-stations" ber-meta.public=false dan penjagaan mengalihkan ke "login"', async () => {
    vi.doUnmock('vue-router')
    vi.doUnmock('@/stores/auth')
    vi.resetModules()

    const { createPinia: createRealPinia, setActivePinia: setRealActivePinia } = await import('pinia')
    setRealActivePinia(createRealPinia())

    const { default: router } = await import('@/router')

    const targetRoute = router.getRoutes().find((route) => route.name === 'report-stations')
    expect(targetRoute).toBeDefined()
    expect(targetRoute?.path).toBe('/reports')
    expect(targetRoute?.meta.public).toBe(false)

    await router.push({ name: 'report-stations' })

    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/reports')
  })
})

/* ================================================================== */
/* Membawa Production Line ke layar laporan (2026-09-28)               */
/* ================================================================== */

/**
 * Kelima layar laporan mobile kini menolak menampilkan angka sebelum ada
 * satu Production Line yang berlaku. Layar ini adalah pintu masuknya, jadi
 * ia wajib membawa line yang sudah dipilih pengguna — kalau tidak, setiap
 * pengguna ditanyai dua kali atas satu keputusan yang sama.
 *
 * Sumbernya localStorage, kunci yang sama persis yang dipakai
 * `loadStations()` di layar ini dan yang ditulis StationListView.vue —
 * jadi tile yang ditekan dan angka yang muncul mustahil berasal dari line
 * yang berbeda. TIDAK ADA pemanggilan jaringan yang ditambahkan.
 */
describe('ReportingPilihStasiunView — membawa Production Line ke laporan', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    window.localStorage.clear()
    logoutMock.mockResolvedValue(undefined)
    mockAuthUser('operator')
    getActiveAndPlaceholderStationsMock.mockResolvedValue([])
    getActiveAndPlaceholderStationsForProductionLineMock.mockResolvedValue([])
  })

  it('menyertakan production_line_id pada rute laporan ketika pengguna sudah punya line aktif', async () => {
    window.localStorage.setItem('msl_production_line_user-1', 'pl-2')
    getActiveAndPlaceholderStationsForProductionLineMock.mockResolvedValue(THREE_STATIONS)

    const wrapper = mount(ReportingPilihStasiunView)
    await flushPromises()

    // Daftar tile-nya pun dibaca dari line yang sama — satu sumber, bukan dua.
    expect(getActiveAndPlaceholderStationsForProductionLineMock).toHaveBeenCalledWith('pl-2')
    expect(getActiveAndPlaceholderStationsMock).not.toHaveBeenCalled()

    await wrapper.get('[data-testid="station-tile-sterilizer"]').trigger('click')

    expect(pushMock).toHaveBeenCalledTimes(1)
    expect(pushMock).toHaveBeenCalledWith({
      name: 'report-sterilizer',
      query: { production_line_id: 'pl-2' },
    })
  })

  it('membawa line yang sama ke SETIAP laporan, bukan hanya Sterilizer', async () => {
    window.localStorage.setItem('msl_production_line_user-1', 'pl-9')
    getActiveAndPlaceholderStationsForProductionLineMock.mockResolvedValue([
      makeStation({ id: 'st-a', name: 'Cages & Tracks', type: 'cages-track', isActive: true }),
      makeStation({ id: 'st-b', name: 'Boiler Room', type: 'boiler-room', isActive: true }),
      makeStation({ id: 'st-c', name: 'Clarification', type: 'clarification', isActive: true }),
      makeStation({ id: 'st-d', name: 'Storage Tank', type: 'storage-tank', isActive: true }),
    ])

    const wrapper = mount(ReportingPilihStasiunView)
    await flushPromises()

    const expected: Array<[string, string]> = [
      ['cages-track', 'report-cages-track'],
      ['boiler-room', 'report-boiler-room'],
      ['clarification', 'report-clarification'],
      ['storage-tank', 'report-storage-tank'],
    ]

    for (const [type, routeName] of expected) {
      pushMock.mockClear()
      await wrapper.get(`[data-testid="station-tile-${type}"]`).trigger('click')
      expect(pushMock).toHaveBeenCalledWith({ name: routeName, query: { production_line_id: 'pl-9' } })
    }
  })

  it('tanpa ingatan line, query dibiarkan KOSONG — layar laporan yang bertanya, bukan layar ini yang menebak', async () => {
    getActiveAndPlaceholderStationsMock.mockResolvedValue(THREE_STATIONS)

    const wrapper = mount(ReportingPilihStasiunView)
    await flushPromises()

    await wrapper.get('[data-testid="station-tile-sterilizer"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'report-sterilizer', query: {} })
    expect(JSON.stringify(pushMock.mock.calls)).not.toContain('production_line_id')
  })

  it('ingatan line milik pengguna LAIN tidak pernah terbawa', async () => {
    window.localStorage.setItem('msl_production_line_user-99', 'pl-orang-lain')
    getActiveAndPlaceholderStationsMock.mockResolvedValue(THREE_STATIONS)

    const wrapper = mount(ReportingPilihStasiunView)
    await flushPromises()

    await wrapper.get('[data-testid="station-tile-sterilizer"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'report-sterilizer', query: {} })
    expect(JSON.stringify(pushMock.mock.calls)).not.toContain('pl-orang-lain')
  })

  it('tetap NOL pemanggilan jaringan — line dibaca dari perangkat, bukan diminta ke server', async () => {
    window.localStorage.setItem('msl_production_line_user-1', 'pl-2')
    getActiveAndPlaceholderStationsForProductionLineMock.mockResolvedValue(THREE_STATIONS)

    const wrapper = mount(ReportingPilihStasiunView)
    await flushPromises()

    await wrapper.get('[data-testid="station-tile-sterilizer"]').trigger('click')

    expect(apiClientGetMock).not.toHaveBeenCalled()
    expect(apiClientPostMock).not.toHaveBeenCalled()
  })

  it('tile tanpa laporan tetap tidak berpindah rute, walau ada line aktif', async () => {
    window.localStorage.setItem('msl_production_line_user-1', 'pl-2')
    getActiveAndPlaceholderStationsForProductionLineMock.mockResolvedValue([
      makeStation({ id: 'st-x', name: 'Weighbridge', type: 'weighbridge', isActive: true }),
    ])

    const wrapper = mount(ReportingPilihStasiunView)
    await flushPromises()

    await wrapper.get('[data-testid="station-tile-weighbridge"]').trigger('click')

    expect(pushMock).not.toHaveBeenCalled()
    expect(wrapper.get('[data-testid="info-message"]').text()).toContain('belum tersedia')
  })
})

describe('ReportingPilihStasiunView — loading state (audit loading state 2026-10-05)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    window.localStorage.clear()
    logoutMock.mockResolvedValue(undefined)
    mockAuthUser('operator')
  })

  it('menampilkan LoadingState grid selama stasiun lokal dimuat, bukan layar kosong', async () => {
    let release!: (value: StationSlot[]) => void
    getActiveAndPlaceholderStationsMock.mockReturnValue(
      new Promise<StationSlot[]>((resolve) => {
        release = resolve
      }),
    )

    const wrapper = mount(ReportingPilihStasiunView)
    await flushPromises()

    const loading = wrapper.get('[data-testid="station-grid-loading"]')
    expect(loading.attributes('role')).toBe('status')
    expect(loading.text()).toBe('Memuat daftar stasiun…')
    expect(wrapper.find('[data-testid="no-stations"]').exists()).toBe(false)

    release(THREE_STATIONS)
    await flushPromises()
    expect(wrapper.find('[data-testid="station-grid-loading"]').exists()).toBe(false)
    expect(wrapper.findAll('[data-testid^="station-tile-"]')).toHaveLength(3)
  })
})
