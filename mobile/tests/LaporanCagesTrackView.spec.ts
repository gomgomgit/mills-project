/**
 * LaporanCagesTrackView.spec.ts — screen-136--laporan-cages-track-mobile /
 * usecase-136--laporan-cages-track-mobile "Lihat Laporan Periode Cages &
 * Tracks (Mobile)".
 *
 * Satu test per test_scenarios yang punya component_test — seluruh 24.
 *
 * STRATEGI MOCK — yang di-mock adalah REPO, bukan apiClient.
 *
 * Berbeda dari LaporanSterilizerView.spec.ts (yang men-stub apiClient dan
 * menjalankan repo-nya sungguhan), berkas ini men-stub
 * '@/services/cagesTrackReportRepo'. Pembagiannya disengaja dan berpasangan:
 * seluruh klaim tentang QUERY YANG DIKIRIM dan PEMETAAN RESPONS —
 * scopeParams(), unwrap(), null bukan nol — sudah dikunci baris demi baris
 * pada tests/cagesTrackReportRepo.spec.ts terhadap apiClient yang sungguhan.
 * Yang tersisa untuk berkas ini, dan hanya dapat dibuktikan di sini, adalah
 * apa yang dilakukan VIEW terhadap nilai-nilai itu: null menjadi keterangan
 * yang terbaca, 401 menjadi perpindahan ke Login, jaringan putus menjadi
 * pesan + Coba Lagi TANPA membuang periode terpilih, dan buka/tutup rekap
 * yang tidak memicu permintaan baru. Mengulang asersi query di sini hanya
 * akan menduplikasi apa yang sudah dijaga di tempat yang lebih tepat.
 *
 * ANGKA FIXTURE SENGAJA TIDAK KONSISTEN SECARA ARITMETIKA.
 * kpi.total_cages_tipped (1284) bukan jumlah hourly, bukan jumlah daily,
 * avg_cages_per_day (91.7) bukan 1284/14, dan total.cages_tipped (777)
 * bertentangan dengan keduanya. Layar yang benar menampilkan ketiganya apa
 * adanya; layar yang diam-diam menghitung ulang gagal di sini. JANGAN
 * "merapikan" angka-angka ini.
 *
 * REKAP HARIAN MEMAKAI v-show, BUKAN v-if. Rekap dibungkus
 * CollapsibleSection.vue (kembaran screen-135), yang menyembunyikan isinya
 * dengan `v-show` — jadi ketika tertutup, seluruh baris TETAP ADA di DOM
 * dengan display:none. Karena itu "baris rekap tidak lagi dirender" pada
 * skenario rekap harian diasersi sebagai KETERLIHATAN (isVisible() ===
 * false), bukan sebagai ketiadaan dari DOM. Mengasersinya sebagai
 * exists() === false akan memaksa perubahan implementasi yang justru
 * menyimpang dari layar kembarannya.
 */
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import LaporanCagesTrackView from '@/views/LaporanCagesTrackView.vue'

/* ------------------------------------------------------------------ */
/* Mock modul                                                          */
/* ------------------------------------------------------------------ */

const { pushMock } = vi.hoisted(() => ({ pushMock: vi.fn() }))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
  useRoute: () => ({ params: {}, query: {} }),
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

const { repoMocks } = vi.hoisted(() => ({
  repoMocks: {
    fetchBusinessUnits: vi.fn(),
    fetchPeriods: vi.fn(),
    fetchSummary: vi.fn(),
    exportCsv: vi.fn(),
    saveCsvFile: vi.fn(),
  },
}))

vi.mock('@/services/cagesTrackReportRepo', () => ({
  default: repoMocks,
  ...repoMocks,
}))

/* ------------------------------------------------------------------ */
/* Bentuk galat NYATA (apiClient.normalizeError) — datar, tanpa         */
/* properti `response`. Ketiadaan `status` itulah yang membedakan       */
/* "jaringan putus" dari "server menjawab 401/403/422".                */
/* ------------------------------------------------------------------ */

const NETWORK_ERROR = { message: 'Tidak dapat terhubung ke server. Data akan disimpan secara lokal.' }
const UNAUTHENTICATED_ERROR = { message: 'Unauthenticated.', status: 401 }

/* ------------------------------------------------------------------ */
/* Fixture                                                             */
/* ------------------------------------------------------------------ */

const PERIOD_CT = {
  id: 'per-1',
  name: 'Periode Maret 2026',
  start_date: '2026-03-01',
  end_date: '2026-03-14',
  status: 'open',
  station_type: 'cages-track',
  station_type_label: 'Cages & Tracks',
}

const PERIOD_ALL_TYPES = {
  id: 'per-2',
  name: 'Periode Semua Stasiun Maret 2026',
  start_date: '2026-03-01',
  end_date: '2026-03-31',
  status: 'open',
  station_type: null,
  station_type_label: 'Semua Jenis Stasiun',
}

const PERIOD_CLOSED = {
  id: 'per-3',
  name: 'Periode Februari 2026',
  start_date: '2026-02-01',
  end_date: '2026-02-28',
  status: 'closed',
  station_type: 'cages-track',
  station_type_label: 'Cages & Tracks',
}

const BUSINESS_UNITS = [
  { id: 'bu-1', name: 'Mill Utara' },
  { id: 'bu-2', name: 'Mill Selatan' },
]

function makeHourly(): Array<{ hour: number; cages: number; within_operating_window: boolean }> {
  return Array.from({ length: 24 }, (_, hour) => ({
    hour,
    cages: hour >= 6 && hour <= 18 ? (hour === 9 ? 168 : 10) : 0,
    within_operating_window: hour >= 6 && hour <= 18,
  }))
}

const FULL_KPI = {
  total_cages_tipped: 1284,
  total_cages_out: 1310,
  avg_cages_per_day: 91.7,
  peak_hour: 9,
  peak_hour_cages: 168,
  idle_operating_hours: 9,
  longest_gap_hours: 4,
  longest_gap_date: '2026-03-08',
  avg_tippler_duration_hours: 11.5,
  days_with_records: 14,
  days_without_valid_window: 0,
}

function makeSummary(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    period: {
      id: 'per-1',
      name: 'Periode Maret 2026',
      start_date: '2026-03-01',
      end_date: '2026-03-14',
      status: 'open',
      business_unit_name: 'Mill Utara',
    },
    kpi: { ...FULL_KPI },
    hourly: makeHourly(),
    daily: [
      {
        date: '2026-03-01',
        cages_tipped: 96,
        cages_out: 99,
        operating_hours: 12,
        idle_operating_hours: 1,
        longest_gap_hours: 2,
        min_remaining: 4,
      },
      {
        date: '2026-03-14',
        cages_tipped: 0,
        cages_out: 0,
        operating_hours: null,
        idle_operating_hours: null,
        longest_gap_hours: null,
        min_remaining: null,
      },
    ],
    queue: { min_remaining: 4, avg_remaining: 11.3 },
    // Sengaja bertentangan dengan kpi dan dengan jumlah baris daily.
    total: { cages_tipped: 777, cages_out: 888, days: 14 },
    ...overrides,
  }
}

const EMPTY_SUMMARY = makeSummary({
  kpi: {
    total_cages_tipped: 0,
    total_cages_out: 0,
    avg_cages_per_day: 0,
    peak_hour: null,
    peak_hour_cages: 0,
    idle_operating_hours: 0,
    longest_gap_hours: null,
    longest_gap_date: null,
    avg_tippler_duration_hours: null,
    days_with_records: 0,
    days_without_valid_window: 0,
  },
  hourly: Array.from({ length: 24 }, (_, hour) => ({ hour, cages: 0, within_operating_window: false })),
  daily: [],
  queue: { min_remaining: null, avg_remaining: null },
  total: { cages_tipped: 0, cages_out: 0, days: 0 },
})

/* ------------------------------------------------------------------ */
/* Auth store palsu                                                    */
/* ------------------------------------------------------------------ */

function asRole(
  role: 'operator' | 'supervisor' | 'mill_management' | 'admin',
  businessUnitId: string | null,
): void {
  useAuthStoreMock.mockReturnValue({
    currentUser: {
      id: 'user-1',
      username: role === 'admin' ? 'admin' : 'operator01',
      name: 'Pengguna Uji',
      role,
      business_unit_id: businessUnitId,
    },
    businessUnit: businessUnitId ? { id: businessUnitId, name: 'Mill Utara' } : null,
    logout: logoutMock,
  })
}

/* ------------------------------------------------------------------ */
/* Bantuan mount / interaksi                                           */
/* ------------------------------------------------------------------ */

async function mountView(): Promise<VueWrapper> {
  const wrapper = mount(LaporanCagesTrackView)
  await flushPromises()

  return wrapper
}

async function selectPeriod(wrapper: VueWrapper, periodId: string): Promise<void> {
  await wrapper.get('[data-testid="period-select"]').setValue(periodId)
  await flushPromises()
}

async function selectMill(wrapper: VueWrapper, businessUnitId: string): Promise<void> {
  await wrapper.get('[data-testid="mill-select"]').setValue(businessUnitId)
  await flushPromises()
}

function text(wrapper: VueWrapper, testid: string): string {
  return wrapper.get(`[data-testid="${testid}"]`).text()
}

function exists(wrapper: VueWrapper, testid: string): boolean {
  return wrapper.find(`[data-testid="${testid}"]`).exists()
}

function selectValue(wrapper: VueWrapper, testid: string): string {
  return (wrapper.get(`[data-testid="${testid}"]`).element as HTMLSelectElement).value
}

/** Tombol buka/tutup rekap harian — CollapsibleSection, tertutup bawaan. */
function recapToggle(wrapper: VueWrapper) {
  return wrapper.get('[data-testid="daily-recap"] [data-testid="collapsible-section-toggle"]')
}

function recapBody(wrapper: VueWrapper) {
  return wrapper.get('[data-testid="daily-recap"] [data-testid="collapsible-section-body"]')
}

function recapRows(wrapper: VueWrapper) {
  return wrapper.findAll('[data-testid="daily-recap"] tbody tr')
}

/** Argumen `scope` pada panggilan ke-`index` fetchPeriods/fetchSummary. */
function scopeArgOf(mock: { mock: { calls: unknown[][] } }, index: number, position: number): unknown {
  return mock.mock.calls[index]?.[position]
}

beforeEach(() => {
  vi.clearAllMocks()
  // Aturan repo: setiap test yang dapat menyentuh localStorage wajib
  // membersihkannya — jsdom membagi satu objek localStorage per berkas.
  window.localStorage.clear()

  asRole('operator', 'bu-1')

  repoMocks.fetchBusinessUnits.mockResolvedValue(BUSINESS_UNITS)
  repoMocks.fetchPeriods.mockResolvedValue([PERIOD_CT])
  repoMocks.fetchSummary.mockResolvedValue(makeSummary())
  repoMocks.exportCsv.mockResolvedValue(new Blob(['csv'], { type: 'text/csv' }))
  repoMocks.saveCsvFile.mockImplementation(() => {})
})

afterEach(() => {
  vi.restoreAllMocks()
})

/* ================================================================== */
/* 24 test_scenarios — component_test                                  */
/* ================================================================== */

describe('LaporanCagesTrackView — test_scenarios / component_test (tech spec screen-136)', () => {
  // Scenario 1: "berhasil dilihat Operator, Supervisor, dan Mill Management"
  it('berhasil dilihat peran terikat mill — tanpa pemilih Mill, seluruh KPI dan bagian terender, tanpa kontrol tulis', async () => {
    for (const role of ['operator', 'supervisor', 'mill_management'] as const) {
      vi.clearAllMocks()
      asRole(role, 'bu-1')
      repoMocks.fetchPeriods.mockResolvedValue([PERIOD_CT])
      repoMocks.fetchSummary.mockResolvedValue(makeSummary())

      const wrapper = await mountView()

      // Pemilih Mill tidak dirender SAMA SEKALI untuk peran terikat mill.
      expect(exists(wrapper, 'mill-select')).toBe(false)
      expect(exists(wrapper, 'mill-current')).toBe(true)
      expect(repoMocks.fetchBusinessUnits).not.toHaveBeenCalled()

      await selectPeriod(wrapper, 'per-1')

      expect(text(wrapper, 'mill-current')).toContain('Mill Utara')

      // Seluruh KPI berasal dari payload, apa adanya.
      expect(text(wrapper, 'kpi-total-cages-tipped')).toBe('1.284')
      expect(text(wrapper, 'kpi-total-cages-out')).toBe('1.310')
      expect(text(wrapper, 'kpi-avg-cages-per-day')).toBe('91,7')
      expect(text(wrapper, 'kpi-peak-hour')).toBe('09.00')
      expect(text(wrapper, 'kpi-idle-hours')).toBe('9')
      expect(text(wrapper, 'kpi-longest-gap')).toBe('4')
      expect(text(wrapper, 'kpi-avg-tippler-duration')).toBe('11,5')

      // hourly 24 entri, daily, queue, dan rekap harian terender.
      expect(wrapper.findAll('[data-testid^="hourly-bar-"]')).toHaveLength(24)
      expect(exists(wrapper, 'daily-trend')).toBe(true)
      expect(exists(wrapper, 'queue')).toBe(true)
      expect(exists(wrapper, 'daily-recap')).toBe(true)

      // Tidak ada satu pun kontrol tulis.
      expect(wrapper.findAll('input')).toHaveLength(0)
      expect(wrapper.findAll('textarea')).toHaveLength(0)
      expect(wrapper.text()).not.toMatch(/simpan|hapus/i)

      wrapper.unmount()
    }
  })

  // Scenario 2: "berhasil dilihat Admin setelah memilih mill"
  it('Admin — pemilih Mill dirender, memilih mill memuat ulang periode, lalu seluruh angka tampil', async () => {
    asRole('admin', null)

    const wrapper = await mountView()

    expect(exists(wrapper, 'mill-select')).toBe(true)
    expect(wrapper.findAll('[data-testid="mill-select"] option')).toHaveLength(3) // placeholder + 2
    expect(repoMocks.fetchPeriods).not.toHaveBeenCalled()

    await selectMill(wrapper, 'bu-1')

    expect(repoMocks.fetchPeriods).toHaveBeenCalledTimes(1)
    expect(scopeArgOf(repoMocks.fetchPeriods, 0, 0)).toEqual({ isAdmin: true, businessUnitId: 'bu-1' })
    expect(wrapper.findAll('[data-testid="period-select"] option')).toHaveLength(2)

    await selectPeriod(wrapper, 'per-1')

    expect(repoMocks.fetchSummary).toHaveBeenCalledWith('per-1', { isAdmin: true, businessUnitId: 'bu-1' })
    expect(text(wrapper, 'kpi-total-cages-tipped')).toBe('1.284')
    expect(wrapper.findAll('[data-testid^="hourly-bar-"]')).toHaveLength(24)
    expect(exists(wrapper, 'daily-trend')).toBe(true)
    expect(exists(wrapper, 'queue')).toBe(true)
    expect(exists(wrapper, 'daily-recap')).toBe(true)
    // Admin memilih mill sendiri — tidak ada keterangan mill akun.
    expect(exists(wrapper, 'mill-current')).toBe(false)

    // Mengganti mill memuat ulang daftar periode dan membuang angka lama.
    repoMocks.fetchPeriods.mockResolvedValue([PERIOD_ALL_TYPES])
    await selectMill(wrapper, 'bu-2')

    expect(repoMocks.fetchPeriods).toHaveBeenCalledTimes(2)
    expect(scopeArgOf(repoMocks.fetchPeriods, 1, 0)).toEqual({ isAdmin: true, businessUnitId: 'bu-2' })
    expect(exists(wrapper, 'kpi-total-cages-tipped')).toBe(false)
    // Kembali ke placeholder. Dibaca lewat option:checked, bukan
    // toHaveValue(''): opsi placeholder memakai :value="null", sehingga
    // nilai DOM-nya adalah teks opsi itu sendiri.
    expect(selectValue(wrapper, 'period-select')).not.toBe('per-1')
    expect(wrapper.get('[data-testid="period-select"] option:checked').text()).toBe('Pilih Periode')
  })

  // Scenario 3: "Operator membuka laporan"
  it('Operator — laporan penuh seperti peran lain, tanpa pemilih Mill, dan scope-nya bukan Admin', async () => {
    asRole('operator', 'bu-1')

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Sama penuhnya dengan peran lain — tidak ada bagian yang dipangkas.
    expect(text(wrapper, 'kpi-total-cages-tipped')).toBe('1.284')
    expect(exists(wrapper, 'hourly-distribution')).toBe(true)
    expect(exists(wrapper, 'daily-trend')).toBe(true)
    expect(exists(wrapper, 'queue')).toBe(true)
    expect(exists(wrapper, 'daily-recap')).toBe(true)

    // Mill tidak dapat diganti dari layar.
    expect(exists(wrapper, 'mill-select')).toBe(false)
    expect(text(wrapper, 'mill-current')).toContain('Mill Utara')

    // Setiap pemanggilan repo menyatakan isAdmin: false — dan scopeParams
    // yang menerjemahkannya menjadi params KOSONG dikunci baris demi baris
    // pada tests/cagesTrackReportRepo.spec.ts (unit_test_case 2).
    expect(scopeArgOf(repoMocks.fetchPeriods, 0, 0)).toEqual({ isAdmin: false, businessUnitId: null })
    expect(repoMocks.fetchSummary).toHaveBeenCalledWith('per-1', { isAdmin: false, businessUnitId: null })
  })

  // Scenario 4: "Admin memilih mill lebih dulu"
  it('Admin belum memilih mill — arahan memilih mill, tanpa KPI/grafik, dan fetchSummary tidak pernah dipanggil', async () => {
    asRole('admin', null)

    const wrapper = await mountView()

    expect(exists(wrapper, 'mill-select')).toBe(true)
    expect(exists(wrapper, 'mill-required-hint')).toBe(true)
    expect(text(wrapper, 'mill-required-hint')).toContain('Pilih mill')

    expect(exists(wrapper, 'kpi-total-cages-tipped')).toBe(false)
    expect(exists(wrapper, 'hourly-distribution')).toBe(false)
    expect(exists(wrapper, 'daily-trend')).toBe(false)
    expect(exists(wrapper, 'queue')).toBe(false)

    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
    expect(repoMocks.fetchPeriods).not.toHaveBeenCalled()
    // Hanya opsi mill yang boleh terpanggil.
    expect(repoMocks.fetchBusinessUnits).toHaveBeenCalledTimes(1)
  })

  // Scenario 5: "mill belum punya periode"
  it('mill belum punya periode — pemilih Periode kosong, arahan hubungi Admin, tanpa fetchSummary', async () => {
    asRole('supervisor', 'bu-1')
    repoMocks.fetchPeriods.mockResolvedValue([])

    const wrapper = await mountView()

    expect(wrapper.findAll('[data-testid="period-select"] option')).toHaveLength(1) // hanya placeholder
    expect(exists(wrapper, 'no-periods')).toBe(true)
    expect(text(wrapper, 'no-periods')).toContain('hubungi Admin')

    expect(exists(wrapper, 'kpi-total-cages-tipped')).toBe(false)
    expect(exists(wrapper, 'hourly-distribution')).toBe(false)
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()

    // Daftar kosong adalah jawaban sah, bukan galat.
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'error-message')).toBe(false)
  })

  // Scenario 6: "periode tanpa data"
  it('periode tanpa data — KPI nol, peak_hour dan jeda sebagai keterangan, tanpa grafik kosong', async () => {
    repoMocks.fetchSummary.mockResolvedValue(EMPTY_SUMMARY)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'empty-period')).toBe(true)
    expect(text(wrapper, 'empty-period')).toContain('Belum ada data')

    expect(text(wrapper, 'kpi-total-cages-tipped')).toBe('0')
    expect(text(wrapper, 'kpi-total-cages-out')).toBe('0')
    expect(text(wrapper, 'kpi-avg-cages-per-day')).toBe('0')

    // null dirender sebagai keterangan, BUKAN angka.
    expect(text(wrapper, 'kpi-peak-hour')).toBe('tidak tersedia')
    expect(text(wrapper, 'kpi-peak-hour')).not.toBe('00.00')
    expect(text(wrapper, 'kpi-longest-gap')).toBe('tidak dapat dihitung')
    expect(text(wrapper, 'kpi-longest-gap')).not.toContain('0 jam')

    // Grafik TIDAK dirender sebagai kotak kosong.
    expect(exists(wrapper, 'hourly-distribution')).toBe(false)
    expect(exists(wrapper, 'daily-trend')).toBe(false)
    expect(exists(wrapper, 'daily-recap')).toBe(false)
  })

  // Scenario 7: "hari dengan record tetapi tanpa rincian per jam"
  it('hari ber-record tanpa rincian per jam — barisnya tetap terender bernilai 0 dan rata-rata tetap nilai server', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    await recapToggle(wrapper).trigger('click')

    const rows = recapRows(wrapper)
    expect(rows).toHaveLength(2)
    // Baris 14 Mar bernilai 0 — TIDAK disembunyikan, karena hari itu tetap
    // menjadi pembagi rata-rata harian.
    expect(rows[1].text()).toContain('14 Mar 2026')
    expect(rows[1].text()).toContain('0')
    // Nilai yang tidak tercatat tidak dipalsukan menjadi 0.
    expect(rows[1].text()).toContain('tidak tercatat')

    // avg_cages_per_day sama persis dengan nilai server — bukan 1284/14.
    expect(text(wrapper, 'kpi-avg-cages-per-day')).toBe('91,7')
    expect(text(wrapper, 'days-with-records')).toContain('14')
  })

  // Scenario 8: "seluruh hari jendela operasinya tidak dapat dihitung"
  it('seluruh hari tanpa jendela operasi terhitung — durasi rata-rata "tidak tersedia", bukan 0', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({
        kpi: { ...FULL_KPI, avg_tippler_duration_hours: null, days_without_valid_window: 14 },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'kpi-avg-tippler-duration')).toBe('tidak tersedia')
    expect(text(wrapper, 'kpi-avg-tippler-duration')).not.toBe('0')
    expect(text(wrapper, 'kpi-avg-tippler-duration')).not.toContain('0')

    // Rata-rata tidak boleh terbaca tanpa tahu berapa hari yang dikeluarkan.
    expect(text(wrapper, 'days-without-valid-window')).toContain('14')
    expect(text(wrapper, 'days-without-valid-window')).toContain('tidak ikut dirata-ratakan')
  })

  // Scenario 9: "penumpahan hanya pada satu jam"
  it('penumpahan hanya pada satu jam — jeda terpanjang terbaca tidak dapat dihitung, tanpa "0 jam"', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({ kpi: { ...FULL_KPI, longest_gap_hours: null, longest_gap_date: null } }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'kpi-longest-gap')).toBe('tidak dapat dihitung')
    expect(exists(wrapper, 'kpi-longest-gap-date')).toBe(false)

    const gapCard = wrapper.get('[data-testid="kpi-longest-gap"]').element.closest('.metric-card')
    expect(gapCard?.textContent).not.toContain('0 jam')
  })

  // Scenario 10: "operasi melewati tengah malam"
  it('operasi melewati tengah malam — jam operasi positif dan jeda sesuai server, tanpa hitung ulang di klien', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({
        kpi: { ...FULL_KPI, longest_gap_hours: 3, longest_gap_date: '2026-03-02' },
        daily: [
          {
            date: '2026-03-02',
            // Jendela 22:00 -> 04:00 hari berikutnya = 6 jam, bukan -18.
            cages_tipped: 120,
            cages_out: 118,
            operating_hours: 6,
            idle_operating_hours: 2,
            longest_gap_hours: 3,
            min_remaining: 5,
          },
        ],
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    await recapToggle(wrapper).trigger('click')

    const row = recapRows(wrapper)[0]
    expect(row.text()).toContain('6 jam')
    expect(row.text()).not.toContain('-18')
    expect(row.text()).not.toContain('-')

    // Jeda apa adanya dari server — tidak melonjak menjadi nilai semu.
    expect(text(wrapper, 'kpi-longest-gap')).toBe('3')
    expect(text(wrapper, 'kpi-longest-gap-date')).toContain('02 Mar 2026')
  })

  // Scenario 11: "mencoba melihat mill lain"
  it('mencoba melihat mill lain — tidak ada jalan di layar untuk menyebut mill lain, dan scope selalu non-Admin', async () => {
    asRole('operator', 'bu-1')

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Setiap pemuatan menyatakan isAdmin: false — dan repo menjadikannya
    // params kosong (dikunci pada cagesTrackReportRepo.spec.ts).
    for (const call of repoMocks.fetchPeriods.mock.calls) {
      expect(call[0]).toEqual({ isAdmin: false, businessUnitId: null })
    }
    for (const call of repoMocks.fetchSummary.mock.calls) {
      expect(call[1]).toEqual({ isAdmin: false, businessUnitId: null })
    }

    // Layar tidak menyediakan kontrol apa pun untuk berpindah mill.
    expect(exists(wrapper, 'mill-select')).toBe(false)
    expect(wrapper.findAll('select')).toHaveLength(1) // hanya pemilih Periode
    expect(text(wrapper, 'mill-current')).toContain('Mill Utara')
    expect(text(wrapper, 'kpi-total-cages-tipped')).toBe('1.284')
  })

  // Scenario 12: "akun belum terhubung ke mill"
  it('akun belum terhubung ke mill — pesan hubungi Admin, tanpa pemilih Mill, dan NOL pemanggilan repo', async () => {
    for (const role of ['operator', 'supervisor', 'mill_management'] as const) {
      vi.clearAllMocks()
      asRole(role, null)

      const wrapper = await mountView()

      expect(exists(wrapper, 'no-mill-for-account')).toBe(true)
      expect(text(wrapper, 'no-mill-for-account')).toContain('hubungi Admin')
      // Daftar seluruh mill TIDAK ditawarkan sebagai penggantinya.
      expect(exists(wrapper, 'mill-select')).toBe(false)
      expect(exists(wrapper, 'kpi-total-cages-tipped')).toBe(false)

      expect(repoMocks.fetchBusinessUnits).not.toHaveBeenCalled()
      expect(repoMocks.fetchPeriods).not.toHaveBeenCalled()
      expect(repoMocks.fetchSummary).not.toHaveBeenCalled()

      wrapper.unmount()
    }
  })

  // Scenario 13: "jaringan gagal"
  it('jaringan gagal — pesan + Coba Lagi, periode terpilih BERTAHAN, dan retry memuat periode yang sama', async () => {
    const wrapper = await mountView()
    repoMocks.fetchSummary.mockRejectedValueOnce(NETWORK_ERROR)

    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'network-error')).toBe(true)
    expect(text(wrapper, 'network-error')).toContain('Tidak dapat terhubung ke server')
    expect(exists(wrapper, 'retry-button')).toBe(true)
    // Pengguna di area tanpa sinyal TIDAK dipaksa memilih periode dari awal.
    expect(selectValue(wrapper, 'period-select')).toBe('per-1')
    expect(exists(wrapper, 'kpi-total-cages-tipped')).toBe(false)

    // Jaringan pulih.
    repoMocks.fetchSummary.mockResolvedValue(makeSummary())
    repoMocks.fetchSummary.mockClear()

    await wrapper.get('[data-testid="retry-button"]').trigger('click')
    await flushPromises()

    // period_id yang SAMA, tanpa memilih ulang.
    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(1)
    expect(repoMocks.fetchSummary).toHaveBeenCalledWith('per-1', { isAdmin: false, businessUnitId: null })
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(text(wrapper, 'kpi-total-cages-tipped')).toBe('1.284')
    expect(selectValue(wrapper, 'period-select')).toBe('per-1')
  })

  // Scenario 14: "sesi berakhir"
  it('sesi berakhir — 401 mengarahkan ke Login, tanpa angka dan tanpa pesan jaringan', async () => {
    const wrapper = await mountView()
    repoMocks.fetchSummary.mockRejectedValueOnce(UNAUTHENTICATED_ERROR)

    await selectPeriod(wrapper, 'per-1')

    expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
    // Sesi berakhir BUKAN kasus coba lagi.
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'retry-button')).toBe(false)
    expect(exists(wrapper, 'error-message')).toBe(false)
    // Tidak ada blok angka yang sempat dirender.
    expect(exists(wrapper, 'kpi-total-cages-tipped')).toBe(false)
    expect(exists(wrapper, 'period-meta')).toBe(false)
  })

  // Scenario 15: "periode tertutup"
  it('periode tertutup — laporan tetap penuh, penanda Ditutup terender, tombol Ekspor tetap aktif', async () => {
    repoMocks.fetchPeriods.mockResolvedValue([PERIOD_CLOSED])
    repoMocks.fetchSummary.mockResolvedValue(
      makeSummary({
        period: {
          id: 'per-3',
          name: 'Periode Februari 2026',
          start_date: '2026-02-01',
          end_date: '2026-02-28',
          status: 'closed',
          business_unit_name: 'Mill Utara',
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-3')

    expect(text(wrapper, 'period-status-badge')).toBe('Ditutup')
    expect(text(wrapper, 'kpi-total-cages-tipped')).toBe('1.284')
    expect(exists(wrapper, 'hourly-distribution')).toBe(true)
    expect(exists(wrapper, 'daily-trend')).toBe(true)
    expect(exists(wrapper, 'queue')).toBe(true)
    expect(exists(wrapper, 'daily-recap')).toBe(true)

    // Kunci periode mengatur PENULISAN data, bukan pembacaan laporan.
    const exportButton = wrapper.get('[data-testid="export-button"]')
    expect(exportButton.attributes('disabled')).toBeUndefined()
    expect(exportButton.attributes('aria-disabled')).toBeUndefined()

    await exportButton.trigger('click')
    await flushPromises()
    expect(repoMocks.exportCsv).toHaveBeenCalledWith('per-3')
  })

  // Scenario 16: "layar hanya membaca, tanpa aksi tulis"
  it('layar hanya membaca — tanpa input/simpan/ubah/hapus; kontrolnya hanya periode, rekap, ekspor, coba lagi, kembali', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await recapToggle(wrapper).trigger('click')

    // Tidak ada elemen form input sama sekali.
    expect(wrapper.findAll('input')).toHaveLength(0)
    expect(wrapper.findAll('textarea')).toHaveLength(0)
    expect(wrapper.findAll('form')).toHaveLength(0)

    // Satu-satunya <select> adalah pemilih Periode (peran terikat mill).
    expect(wrapper.findAll('select')).toHaveLength(1)
    expect(exists(wrapper, 'period-select')).toBe(true)

    // Tidak ada tombol simpan/ubah/hapus di seluruh pohon komponen.
    const buttonLabels = wrapper.findAll('button').map((button) => button.text().toLowerCase())
    for (const label of buttonLabels) {
      expect(label).not.toMatch(/simpan|hapus|edit|ubah data|tambah/)
    }
    expect(wrapper.text()).not.toMatch(/simpan|hapus/i)

    // Kontrol yang memang ada.
    expect(exists(wrapper, 'export-button')).toBe(true)
    expect(exists(wrapper, 'back-button')).toBe(true)
    expect(exists(wrapper, 'daily-recap')).toBe(true)
  })

  // Scenario 17: "angka ponsel sama persis dengan laporan versi web"
  it('angka sama dengan laporan web — setiap nilai apa adanya dari payload, tanpa pembulatan atau agregasi ulang', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // KPI apa adanya — BUKAN jumlah hourly dan BUKAN jumlah daily (96 + 0).
    expect(text(wrapper, 'kpi-total-cages-tipped')).toBe('1.284')
    expect(text(wrapper, 'kpi-total-cages-tipped')).not.toBe('96')
    expect(text(wrapper, 'kpi-total-cages-out')).toBe('1.310')
    // 91,7 tidak dihitung ulang dari 1284/14 (= 91,714...).
    expect(text(wrapper, 'kpi-avg-cages-per-day')).toBe('91,7')
    expect(text(wrapper, 'kpi-peak-hour')).toBe('09.00')
    expect(text(wrapper, 'kpi-idle-hours')).toBe('9')
    expect(text(wrapper, 'kpi-longest-gap')).toBe('4')
    expect(text(wrapper, 'queue-min-remaining')).toBe('4')
    expect(text(wrapper, 'queue-avg-remaining')).toBe('11,3')

    // Blok `total` apa adanya (777/888/14), meski bertentangan dengan kpi
    // maupun dengan jumlah barisnya sendiri.
    await recapToggle(wrapper).trigger('click')
    expect(text(wrapper, 'daily-recap-total')).toContain('777')
    expect(text(wrapper, 'daily-recap-total')).toContain('888')
    expect(text(wrapper, 'daily-recap-total')).toContain('14 hari')
  })

  // Scenario 18: "periode yang tidak mencakup Cages & Tracks tidak ditawarkan"
  it('daftar periode — tepat sebanyak dan seurut entri dari server, tanpa penyaringan station_type di klien', async () => {
    repoMocks.fetchPeriods.mockResolvedValue([PERIOD_CT, PERIOD_ALL_TYPES])

    const wrapper = await mountView()

    const options = wrapper.findAll('[data-testid="period-select"] option')
    expect(options).toHaveLength(3) // placeholder + 2

    const labels = options.slice(1).map((option) => option.text())
    expect(labels).toEqual([
      'Cages & Tracks — Periode Maret 2026',
      'Semua Jenis Stasiun — Periode Semua Stasiun Maret 2026',
    ])
    // Layar tidak menambahkan apa pun ke daftar yang diterimanya.
    expect(labels.join(' ')).not.toContain('Sterilizer')
    expect(options[1].attributes('value')).toBe('per-1')
  })

  // Scenario 19: "rentang periode inklusif di kedua ujung"
  it('rentang inklusif — baris tanggal mulai dan tanggal akhir terender pada rekap dan tren harian', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Tren harian memuat kedua ujung rentang.
    const trend = text(wrapper, 'daily-trend')
    expect(trend).toContain('01')
    expect(trend).toContain('14')

    await recapToggle(wrapper).trigger('click')

    const rows = recapRows(wrapper)
    expect(rows).toHaveLength(2)
    expect(rows[0].text()).toContain('01 Mar 2026')
    expect(rows[1].text()).toContain('14 Mar 2026')

    // Ujung rentang tidak dipangkas dan tidak ada tanggal di luar rentang.
    const recap = text(wrapper, 'daily-recap')
    expect(recap).not.toContain('28 Feb 2026')
    expect(recap).not.toContain('15 Mar 2026')
  })

  // Scenario 20: "jam menganggur hanya di dalam jendela operasi tippler"
  it('jam menganggur — angka apa adanya dari server dan jam di luar jendela diberi penanda pembeda', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Nilai server, bukan hitungan sendiri atas hourly.
    expect(text(wrapper, 'kpi-idle-hours')).toBe('9')

    const bars = wrapper.findAll('[data-testid^="hourly-bar-"]')
    expect(bars).toHaveLength(24)
    // Jam 05 di luar jendela, jam 06 di dalam — penandanya berbeda.
    expect(wrapper.get('[data-testid="hourly-bar-5"]').attributes('data-within-window')).toBe('false')
    expect(wrapper.get('[data-testid="hourly-bar-6"]').attributes('data-within-window')).toBe('true')
    expect(wrapper.get('[data-testid="hourly-bar-5"]').find('.chart-fill--outside').exists()).toBe(true)
    expect(wrapper.get('[data-testid="hourly-bar-9"]').find('.chart-fill--outside').exists()).toBe(false)

    // Jumlah jam bertanda false pada fixture (11) TIDAK dipakai sebagai
    // angka jam menganggur — itu urusan server.
    expect(text(wrapper, 'kpi-idle-hours')).not.toBe('11')
  })

  // Scenario 21: "jeda terpanjang tidak melintasi pergantian tanggal"
  it('jeda terpanjang — nilai dan satu tanggal tunggal apa adanya dari payload', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'kpi-longest-gap')).toBe('4')
    // Satu tanggal tunggal, bukan rentang lintas tanggal.
    expect(text(wrapper, 'kpi-longest-gap-date')).toContain('08 Mar 2026')
    expect(text(wrapper, 'kpi-longest-gap-date')).not.toContain('–')
    expect(text(wrapper, 'kpi-longest-gap-date')).not.toContain('09 Mar')

    // Bukan diturunkan dari hourly: fixture punya 11 jam bernilai nol
    // berturut-turut di luar jendela, dan angkanya tetap 4.
    expect(text(wrapper, 'kpi-longest-gap')).not.toBe('11')
  })

  // Scenario 22: "antrean lori tersisa tidak pernah dijumlahkan"
  it('antrean lori — hanya terendah dan rata-rata beserta keterangan potret per jam, tanpa total', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'queue-min-remaining')).toBe('4')
    expect(text(wrapper, 'queue-avg-remaining')).toBe('11,3')
    expect(text(wrapper, 'queue-snapshot-note')).toContain('potret per jam')
    expect(text(wrapper, 'queue-snapshot-note')).toContain('tidak pernah dijumlahkan')

    // Tidak ada elemen bertuliskan total/jumlah antrean di bagian ini.
    const queueSection = text(wrapper, 'queue')
    expect(queueSection).not.toMatch(/total antrean|jumlah antrean/i)
    expect(exists(wrapper, 'queue-total')).toBe(false)
    // 4 + 11,3 tidak pernah muncul sebagai angka gabungan.
    expect(queueSection).not.toContain('15,3')
  })

  // Scenario 23: "rekap harian dapat ditutup agar layar ponsel tetap terbaca"
  it('rekap harian dibuka-tutup — baris tersembunyi (bukan hilang), KPI tetap, dan NOL permintaan baru', async () => {
    const thirtyDays = Array.from({ length: 30 }, (_, index) => ({
      date: `2026-03-${String(index + 1).padStart(2, '0')}`,
      cages_tipped: 10 + index,
      cages_out: 9 + index,
      operating_hours: 12,
      idle_operating_hours: 1,
      longest_gap_hours: 2,
      min_remaining: 5,
    }))
    repoMocks.fetchSummary.mockResolvedValue(makeSummary({ daily: thirtyDays }))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(1)

    // TERTUTUP secara bawaan. CollapsibleSection memakai v-show: barisnya
    // TETAP di DOM dengan display:none, jadi yang diasersi adalah
    // keterlihatannya — bukan ketiadaannya (lihat docblock berkas).
    expect(recapBody(wrapper).attributes('style')).toContain('display: none')
    expect(recapRows(wrapper)).toHaveLength(30)
    expect(recapRows(wrapper)[0].isVisible()).toBe(false)
    // KPI dan grafik tetap terender saat rekap tertutup.
    expect(text(wrapper, 'kpi-total-cages-tipped')).toBe('1.284')
    expect(exists(wrapper, 'hourly-distribution')).toBe(true)
    expect(exists(wrapper, 'daily-trend')).toBe(true)

    // Dibuka: seluruh 30 baris kembali utuh, lalu ditutup dan dibuka lagi.
    //
    // CATATAN jsdom — isVisible() membaca getComputedStyle, dan jsdom
    // MENYIMPAN hasil itu per elemen: sebuah wadah yang pernah dibaca
    // ketika display-nya 'none' tetap terbaca 'none' bagi seluruh
    // keturunannya setelah v-show membukanya kembali (dan sebaliknya).
    // Karena itu keadaan buka/tutup di bawah dibaca dari atribut style
    // milik v-show — mekanisme yang memang dipakai CollapsibleSection, dan
    // konvensi yang sama dengan LaporanSterilizerView.spec.ts — sementara
    // isVisible() dipakai TEPAT SEKALI per pohon DOM, pada keadaan yang
    // belum pernah dibaca, supaya asersinya tidak pernah lulus dari cache
    // yang basi.
    await recapToggle(wrapper).trigger('click')
    expect(recapToggle(wrapper).attributes('aria-expanded')).toBe('true')
    expect(recapBody(wrapper).attributes('style')).not.toContain('display: none')
    expect(recapRows(wrapper)).toHaveLength(30)
    expect(recapRows(wrapper)[29].text()).toContain('30 Mar 2026')

    await recapToggle(wrapper).trigger('click')
    expect(recapToggle(wrapper).attributes('aria-expanded')).toBe('false')
    expect(recapBody(wrapper).attributes('style')).toContain('display: none')

    await recapToggle(wrapper).trigger('click')
    expect(recapBody(wrapper).attributes('style')).not.toContain('display: none')
    expect(recapRows(wrapper)).toHaveLength(30)

    // ASERSI INTINYA: membuka/menutup murni penyingkapan — datanya sudah
    // ada di memori, tidak ada satu pun permintaan baru ke server.
    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(1)
    expect(repoMocks.fetchPeriods).toHaveBeenCalledTimes(1)

    // Pohon DOM yang benar-benar baru (lihat catatan jsdom di atas): di
    // sini isVisible() belum pernah dipanggil, jadi "baris rekap terlihat
    // setelah dibuka" terbaca dari keadaan yang sesungguhnya.
    const fresh = await mountView()
    await selectPeriod(fresh, 'per-1')
    await recapToggle(fresh).trigger('click')

    expect(recapRows(fresh)).toHaveLength(30)
    expect(recapRows(fresh)[0].isVisible()).toBe(true)
    expect(recapRows(fresh)[29].isVisible()).toBe(true)
  })

  // Scenario 24: "mengunduh rincian penumpahan per jam sebagai CSV"
  it('ekspor CSV — exportCsv dipanggil dengan periode yang sedang dibuka lalu blob-nya disimpan', async () => {
    const blob = new Blob(['a,b\n1,2\n'], { type: 'text/csv' })
    repoMocks.exportCsv.mockResolvedValue(blob)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    await wrapper.get('[data-testid="export-button"]').trigger('click')
    await flushPromises()

    expect(repoMocks.exportCsv).toHaveBeenCalledTimes(1)
    expect(repoMocks.exportCsv).toHaveBeenCalledWith('per-1')

    // Blob hasil server disimpan apa adanya — komponen TIDAK menyusun isi
    // CSV sendiri dari angka yang sedang tampil di layar.
    expect(repoMocks.saveCsvFile).toHaveBeenCalledTimes(1)
    const [savedBlob, filename] = repoMocks.saveCsvFile.mock.calls[0] as [Blob, string]
    expect(savedBlob).toBe(blob)
    expect(filename).toMatch(/^laporan-cages-track_.*\.csv$/)
  })
})
