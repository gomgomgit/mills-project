/**
 * LaporanKernelPlantView.spec.ts — screen-155--laporan-kernel-plant-mobile /
 * usecase-161--laporan-kernel-plant-mobile.
 *
 * Satu test per test_scenarios[].component_test pada tech spec screen-155.
 * Kembaran kesebelas dari pola yang sama, dari LaporanSterilizerView.spec.ts
 * (screen-135) sampai LaporanDepricarpingView.spec.ts (screen-153).
 *
 * YANG DI-MOCK: repo laporan, repo Production Line, router, dan ketiga store.
 * Yang diuji adalah KEPUTUSAN LAYAR atas jawaban repo — bentuk permintaan
 * HTTP-nya milik kernelPlantReportRepo.spec.ts dan tidak diulang di sini.
 *
 * ────────────────────────────────────────────────────────────────────────
 * LIMA HAL YANG MEMBEDAKAN LAYAR INI DARI KESEPULUH LAPORAN SEBELUMNYA
 * ────────────────────────────────────────────────────────────────────────
 * 1. DUA PASANGAN BERBAGI STANDAR, BUKAN SATU — ripple_mill_1_amps ↔
 *    ripple_mill_2_amps DAN kernel_silo_1_temp_c ↔ kernel_silo_2_temp_c. EMPAT
 *    kartu membawa keterangannya, TIGA tidak. Depricarping mobile hanya punya
 *    SATU pasangan, jadi markup yang mengistimewakan satu pasangan lolos di
 *    sana dan SALAH di sini: keempatnya diasersi, dan satu test memberi
 *    payload dengan shares_standard_with KOSONG pada salah satu anggota
 *    pasangan untuk membuktikan keterangannya memang DITURUNKAN dari respons,
 *    bukan dipaku.
 *
 * 2. PENJAGA NULL PADA KETERANGAN ITU ADALAH PERCABANGAN PERNYATAAN, BUKAN
 *    NILAI CADANGAN. Dengan equipment_parameter null, keterangan tidak boleh
 *    memuat tanda kutip kosong dan tidak boleh mengklaim "master hanya memuat
 *    satu baris" — baris itu justru baru terlepas. Cacat persis ini ditemukan
 *    dan ditutup pada layar web screen-154; test ini yang mencegahnya kembali.
 *
 * 3. BLOK target HANYA DUA KOLOM ISI — tidak ada critical_limit dan tidak ada
 *    operational_consequence_justification. Diasersi atas KETIADAAN testid
 *    dan atas JUMLAH ELEMEN .metric-standard per kartu, supaya bentuk empat
 *    kolom milik Depricarping tidak dapat disalin masuk kemudian.
 *
 * 4. BAGIAN "STANDAR TANPA PENGUKURAN" NORMALNYA BERISI (satu baris, 'Final
 *    Kernel Dirt') — berlawanan dengan Depricarping, yang daftarnya normalnya
 *    kosong. Bagian itu tetap digambar WALAU KOSONG, dan itu pun diasersi.
 *
 * 5. TIDAK ADA PEMILIH MILL, dan business_unit_id tidak pernah ada jalurnya.
 *    Nama mill dibaca dari summary.business_unit.name — /periods pada layar
 *    ini TIDAK membawa business_unit_name sama sekali.
 *
 * ── PENYEBUT "N DARI M SLOT" ────────────────────────────────────────────
 * N milik tiap kartu (metrics[].filled_slot_count) dan berbeda-beda; M
 * SERAGAM pada ketujuh kartu (coverage.filled_slots). Fixture memberi TUJUH
 * N yang berbeda (2, 9, 0, 5, 3, 7, 1) atas M = 12 — dan M itu sengaja LEBIH
 * BESAR daripada setiap N, karena slot yang hanya memuat temuan tetap slot
 * terisi. Yang dilampaui adalah N, bukan M, dan layar tidak boleh
 * "memperbaikinya".
 *
 * KETIADAAN PENANDAAN DI LUAR BATAS DIASERSI ATAS JUMLAH ELEMEN BERKELAS,
 * bukan atas frasa maupun atas substring sumber: kalimat yang menyatakan
 * ketiadaannya sendiri memuat frasa "di luar batas".
 *
 * TANGGALNYA RELATIF, bukan kalender tetap. Nama periode pun sengaja bukan
 * nama bulan, supaya slug nama berkas ekspor tidak ikut basi.
 *
 * BENTUK GALAT — DATAR, TANPA `response`. Ketiadaan `status` itulah yang
 * membedakan "jaringan putus" dari "server menjawab 401/403/422", dan urutan
 * cabang handleError bergantung padanya.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import LaporanKernelPlantView from '@/views/LaporanKernelPlantView.vue'

/* ------------------------------------------------------------------ */
/* Mock modul                                                          */
/* ------------------------------------------------------------------ */

const { pushMock, routeQuery } = vi.hoisted(() => ({
  pushMock: vi.fn(),
  routeQuery: {} as Record<string, string>,
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
  useRoute: () => ({ params: {}, query: routeQuery }),
}))

const { useAuthStoreMock, logoutMock } = vi.hoisted(() => ({
  useAuthStoreMock: vi.fn(),
  logoutMock: vi.fn().mockResolvedValue(undefined),
}))

vi.mock('@/stores/auth', () => ({ useAuthStore: useAuthStoreMock }))

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

/**
 * EMPAT fungsi, bukan lima: tidak ada fetchBusinessUnits, karena rute itu
 * Admin saja dan layar ini tidak punya jalur ke sana. Mock yang menambahkannya
 * akan membuat test "tidak dipanggil" selalu hijau tanpa arti.
 */
const { repoMocks } = vi.hoisted(() => ({
  repoMocks: {
    fetchPeriods: vi.fn(),
    fetchSummary: vi.fn(),
    exportCsv: vi.fn(),
    saveCsvFile: vi.fn(),
  },
}))

vi.mock('@/services/kernelPlantReportRepo', () => ({
  default: repoMocks,
  ...repoMocks,
}))

const { productionLineMocks } = vi.hoisted(() => ({
  productionLineMocks: {
    fetchProductionLinesForReport: vi.fn(),
    fetchAndCacheStationsForProductionLine: vi.fn(),
  },
}))

vi.mock('@/services/productionLineRepo', () => ({
  default: productionLineMocks,
  productionLineRepo: productionLineMocks,
  ...productionLineMocks,
}))

/* ------------------------------------------------------------------ */
/* Bentuk galat NYATA                                                  */
/* ------------------------------------------------------------------ */

const NETWORK_ERROR = { message: 'Tidak dapat terhubung ke server.' }
const UNAUTHENTICATED_ERROR = { message: 'Unauthenticated.', status: 401 }

/* ------------------------------------------------------------------ */
/* Tanggal relatif                                                     */
/* ------------------------------------------------------------------ */

function isoDaysFromNow(offsetDays: number): string {
  return new Date(Date.now() + offsetDays * 86_400_000).toISOString().slice(0, 10)
}

const PERIOD_START = isoDaysFromNow(-30)
const PERIOD_END = isoDaysFromNow(-1)
const PREV_START = isoDaysFromNow(-60)
const PREV_END = isoDaysFromNow(-31)
const DAY_A = isoDaysFromNow(-26)
const DAY_B = isoDaysFromNow(-25)

/* ------------------------------------------------------------------ */
/* Fixture                                                             */
/* ------------------------------------------------------------------ */

/**
 * Opsi periode — PERHATIKAN APA YANG TIDAK ADA: business_unit_name. Payload
 * /periods layar ini tidak memuatnya, jadi nama mill WAJIB datang dari
 * summary.business_unit.
 */
const PERIOD = {
  id: 'per-1',
  name: 'Periode Uji Kernel Plant',
  start_date: PERIOD_START,
  end_date: PERIOD_END,
  status: 'open',
  station_type: 'kernel-plant',
  station_type_label: 'Kernel Plant',
}

const PERIOD_CLOSED = {
  id: 'per-2',
  name: 'Periode Sebelumnya',
  start_date: PREV_START,
  end_date: PREV_END,
  status: 'closed',
  station_type: 'kernel-plant',
  station_type_label: 'Kernel Plant',
}

const LINE_1 = { id: 'pl-1', name: 'Line Satu' }
const LINE_2 = { id: 'pl-2', name: 'Line Dua' }
const LINES = [LINE_1, LINE_2]

const RIPPLE_TARGET = {
  equipment_parameter: 'Ripple Mill (Cracker)',
  target_benchmark: '20 - 25 Amps (Nut Breakage >95%)',
  corrective_action_plan: 'Adjust rotor-vane clearance if uncracked nut rate >5%.',
}

const SILO_TARGET = {
  equipment_parameter: 'Kernel Silo 1 & 2',
  target_benchmark: '70°C - 80°C (Top/Middle zones)',
  corrective_action_plan: 'Check heater elements/steam valves if temperature drops below 65°C.',
}

/**
 * KETUJUH kolom ukur, dalam urutan kanonis KernelPlantReportService.
 *
 * Penyebutnya SENGAJA berbeda-beda (2, 9, 0, 5, 3, 7, 1) atas M = 12, supaya
 * asersi "tiap kartu mencetak penyebutnya sendiri" bermakna alih-alih selalu
 * hijau — dengan penyebut seragam, satu penyebut bersama akan lolos tanpa
 * terlihat.
 *
 * Angka kedua anggota tiap pasangan sengaja berjauhan (12,5 vs 33; 74 vs 58)
 * supaya rata-rata gabungan punya nilai yang jelas untuk DITOLAK (22,75 dan
 * 66).
 *
 * claybath_hydro_sg adalah kolom yang TIDAK PERNAH TERISI: penyebut 0, seluruh
 * angkanya null, dan masternya pun terlepas (equipment_parameter null).
 * Kartunya tetap harus dirender.
 */
function metrics(): Array<Record<string, unknown>> {
  return [
    {
      column: 'ripple_mill_1_amps',
      label: 'Arus Ripple Mill 1',
      unit: 'Amps',
      min: 10,
      // Di luar rentang min..max — mustahil dari perhitungan apa pun, dan
      // itulah yang membuat "nol aritmetika di klien" dapat dibuktikan.
      avg: 12.5,
      max: 11,
      filled_slot_count: 2,
      target: { ...RIPPLE_TARGET, shares_standard_with: ['ripple_mill_2_amps'] },
    },
    {
      column: 'ripple_mill_2_amps',
      label: 'Arus Ripple Mill 2',
      unit: 'Amps',
      min: 30,
      avg: 33,
      max: 36,
      filled_slot_count: 9,
      target: { ...RIPPLE_TARGET, shares_standard_with: ['ripple_mill_1_amps'] },
    },
    {
      column: 'claybath_hydro_sg',
      label: 'Claybath / Hydrocyclone',
      unit: 'SG',
      min: null,
      avg: null,
      max: null,
      filled_slot_count: 0,
      target: {
        equipment_parameter: null,
        target_benchmark: null,
        corrective_action_plan: null,
        shares_standard_with: [],
      },
    },
    {
      column: 'kernel_silo_1_temp_c',
      label: 'Suhu Kernel Silo 1',
      // 'C' APA ADANYA — tidak dipercantik menjadi '°C' di klien, karena
      // laporan web mencetak medan yang sama apa adanya.
      unit: 'C',
      min: 70,
      avg: 74,
      max: 80,
      filled_slot_count: 5,
      target: { ...SILO_TARGET, shares_standard_with: ['kernel_silo_2_temp_c'] },
    },
    {
      column: 'kernel_silo_2_temp_c',
      label: 'Suhu Kernel Silo 2',
      unit: 'C',
      min: 55,
      avg: 58,
      max: 62,
      filled_slot_count: 3,
      target: { ...SILO_TARGET, shares_standard_with: ['kernel_silo_1_temp_c'] },
    },
    {
      column: 'kernel_moisture_percent',
      label: 'Kadar Air Kernel',
      unit: '%',
      min: 6.2,
      avg: 6.8,
      max: 7.4,
      filled_slot_count: 7,
      target: {
        equipment_parameter: 'Final Kernel Moisture',
        // Memuat '%' DI DALAM standarnya — itulah sebabnya asersi persen
        // apa pun harus dilingkup ke elemen cakupannya, bukan ke halaman.
        target_benchmark: '≤ 7.0% (Prevents mold growth)',
        corrective_action_plan: 'Increase retention time or adjust silo air flow rates.',
        shares_standard_with: [],
      },
    },
    {
      column: 'shell_loss_percent',
      label: 'Shell Bin Kernel Loss',
      unit: '%',
      min: 1.1,
      avg: 1.3,
      max: 1.9,
      filled_slot_count: 1,
      target: {
        equipment_parameter: 'Shell Bin Kernel Loss',
        target_benchmark: '≤ 1.5% (Maximized separation recovery)',
        corrective_action_plan: 'Reduce air velocity or inspect separator screen meshes.',
        shares_standard_with: [],
      },
    },
  ]
}

/**
 * Fixture penuh, dan SENGAJA BERTENTANGAN DENGAN DIRINYA SENDIRI pada setiap
 * tempat yang dapat lahir dari perhitungan klien:
 *   - coverage_percent 41,7 padahal 12/288 = 4,2
 *   - metrics[0].avg 12,5 padahal min 10 dan max 11
 *   - daily_total.filled_slot_count 99 padahal baris hariannya 3 + 4
 *   - downtime.avg_minutes_per_recorded_slot 99 padahal 60/3 = 20
 * Layar yang menghitung sendiri PASTI gagal. Jangan "merapikan" angka ini.
 */
function summaryFixture(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    business_unit: { id: 'bu-1', name: 'Mill Utara' },
    production_line: { id: 'pl-1', name: 'Line Satu' },
    period: {
      id: 'per-1',
      name: 'Periode Uji Kernel Plant',
      start_date: PERIOD_START,
      end_date: PERIOD_END,
      status: 'open',
      // Layar SENGAJA tidak membaca medan ini: satu sumber nama mill saja.
      business_unit_name: 'Mill Dari Kepala Periode',
    },
    has_data: true,
    coverage: {
      filled_slots: 12,
      expected_slots: 288,
      coverage_percent: 41.7,
      kernel_plant_count: 2,
      slots_per_kernel_plant_per_day: 24,
      days_in_period: 30,
      days_counted: 6,
      period_running: true,
    },
    metrics: metrics(),
    // NORMALNYA BERISI — satu baris, dan itulah keadaan tenang layar ini.
    targets_without_metric: [
      {
        equipment_parameter: 'Final Kernel Dirt',
        target_benchmark: '≤ 6.0% (Standard quality premium)',
        corrective_action_plan: 'Clean winnowing ducts or re-calibrate hydrocyclone settings.',
        reason: 'no_column',
      },
    ],
    all_targets_measured: false,
    targets_master_empty: false,
    by_kernel_plant: [
      {
        // KEDUANYA string biasa dan BERNILAI SAMA — tidak ada tabel master
        // kernel_plants.
        kernel_plant_id: 'KP-1',
        kernel_plant_name: 'KP-1',
        day_count: 2,
        filled_slot_count: 7,
        downtime_minutes: 30,
        averages: { ripple_mill_1_amps: 30, claybath_hydro_sg: null },
      },
      {
        kernel_plant_id: 'KP-2',
        kernel_plant_name: 'KP-2',
        day_count: 1,
        filled_slot_count: 5,
        // null, BUKAN 0.
        downtime_minutes: null,
        averages: { ripple_mill_1_amps: 28.5, claybath_hydro_sg: null },
      },
    ],
    daily: [
      {
        date: DAY_A,
        filled_slot_count: 3,
        downtime_minutes: 10,
        averages: { ripple_mill_1_amps: 10, claybath_hydro_sg: null },
      },
      {
        date: DAY_B,
        filled_slot_count: 4,
        downtime_minutes: null,
        averages: { ripple_mill_1_amps: 100, claybath_hydro_sg: null },
      },
    ],
    daily_total: {
      // 3 + 4 = 7, dan server mengirim 99.
      filled_slot_count: 99,
      downtime_minutes: 999,
      // Bukan rata-rata dari 10 dan 100 dengan cara apa pun.
      averages: { ripple_mill_1_amps: 77.7, claybath_hydro_sg: null },
    },
    // OBJEK berisi ANGKA. Penyebutnya 3 slot pencatat dari 12 slot terisi —
    // sengaja BERBEDA supaya penyebut yang salah terlihat.
    downtime: {
      total_minutes: 60,
      recorded_slot_count: 3,
      // 60/3 = 20, dan server mengirim 99.
      avg_minutes_per_recorded_slot: 99,
      has_standard: false,
    },
    findings: [
      { finding: 'Ripple mill bergetar', slot_count: 3 },
      { finding: 'ripple mill bergetar', slot_count: 2 },
    ],
    total: {
      record_count: 12,
      days_with_records: 2,
      draft_record_count: 4,
      records_not_checked: 5,
      records_not_acknowledged: 6,
    },
    ...overrides,
  }
}

/**
 * Periode sah tanpa satu slot terisi. KETUJUH kartu tetap ada, dengan
 * penyebut nol dan seluruh angkanya null — dan coverage_percent 0, BUKAN
 * null: penyebutnya terbentuk.
 */
function emptySummaryFixture(): Record<string, unknown> {
  return summaryFixture({
    has_data: false,
    coverage: {
      filled_slots: 0,
      expected_slots: 288,
      coverage_percent: 0,
      kernel_plant_count: 2,
      slots_per_kernel_plant_per_day: 24,
      days_in_period: 30,
      days_counted: 6,
      period_running: true,
    },
    metrics: metrics().map((metric) => ({
      ...metric,
      min: null,
      avg: null,
      max: null,
      filled_slot_count: 0,
    })),
    by_kernel_plant: [],
    daily: [],
    daily_total: { filled_slot_count: 0, downtime_minutes: null, averages: {} },
    downtime: {
      total_minutes: null,
      recorded_slot_count: 0,
      avg_minutes_per_recorded_slot: null,
      has_standard: false,
    },
    findings: [],
  })
}

/* ------------------------------------------------------------------ */
/* Auth store palsu — HANYA dua peran yang punya layar ini             */
/* ------------------------------------------------------------------ */

function asRole(
  role: 'operator' | 'supervisor',
  businessUnitId: string | null,
  userId = 'user-1',
): void {
  useAuthStoreMock.mockReturnValue({
    currentUser: {
      id: userId,
      username: role === 'operator' ? 'operator01' : 'supervisor01',
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
  const wrapper = mount(LaporanKernelPlantView)
  await flushPromises()

  return wrapper
}

async function selectPeriod(wrapper: VueWrapper, periodId: string): Promise<void> {
  await wrapper.get('[data-testid="period-select"]').setValue(periodId)
  await flushPromises()
}

async function selectLine(wrapper: VueWrapper, lineId: string): Promise<void> {
  await wrapper.get('[data-testid="production-line-select"]').setValue(lineId)
  await flushPromises()
}

function text(wrapper: VueWrapper, testid: string): string {
  return wrapper.get(`[data-testid="${testid}"]`).text()
}

function exists(wrapper: VueWrapper, testid: string): boolean {
  return wrapper.find(`[data-testid="${testid}"]`).exists()
}

function cards(wrapper: VueWrapper) {
  return wrapper.findAll('[data-testid="metric-card"]')
}

beforeEach(() => {
  pushMock.mockReset()
  logoutMock.mockClear()
  useAuthStoreMock.mockReset()

  Object.keys(routeQuery).forEach((key) => delete routeQuery[key])

  repoMocks.fetchPeriods.mockReset()
  repoMocks.fetchSummary.mockReset()
  repoMocks.exportCsv.mockReset()
  repoMocks.saveCsvFile.mockReset()

  productionLineMocks.fetchProductionLinesForReport.mockReset()
  productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([LINE_1])

  window.localStorage.clear()

  asRole('operator', 'bu-1')
  repoMocks.fetchPeriods.mockResolvedValue([PERIOD, PERIOD_CLOSED])
  repoMocks.fetchSummary.mockResolvedValue(summaryFixture())
})

afterEach(() => {
  vi.restoreAllMocks()
})

/* ================================================================== */
/* Jalur sukses — kedua aktor terikat satu mill                        */
/* ================================================================== */

describe('jalur sukses', () => {
  it.each(['operator', 'supervisor'] as const)(
    '%s: seluruh bagian laporan terender, TANPA pemilih mill',
    async (role) => {
      asRole(role, 'bu-1')

      const wrapper = await mountView()
      await selectPeriod(wrapper, 'per-1')

      // Tidak ada pemilih mill di layar ini, dan itu bukan pemangkasan:
      // kedua aktornya terikat satu mill, diselesaikan server dari akun.
      expect(exists(wrapper, 'mill-select')).toBe(false)
      expect(text(wrapper, 'mill-current')).toContain('Mill Utara')
      expect(text(wrapper, 'production-line-current')).toContain('Line Satu')

      for (const testid of [
        'coverage', 'coverage-slots', 'coverage-denominator', 'coverage-days',
        'metrics', 'no-flagging-note', 'targets-without-metric',
        'by-kernel-plant', 'downtime', 'findings', 'completeness', 'daily-recap',
      ]) {
        expect(exists(wrapper, testid), testid).toBe(true)
      }

      // Tidak ada satu pun kontrol tulis — layar ini BACAAN SAJA.
      const buttonLabels = wrapper.findAll('button').map((button) => button.text().toLowerCase())

      expect(buttonLabels.some((label) => /simpan|ubah|hapus|verifik/.test(label))).toBe(false)
    },
  )

  it('daftar periode diminta TANPA satu argumen pun — mill diselesaikan server', async () => {
    await mountView()

    // fetchPeriods() tanpa cakupan: tidak ada medan mill yang dapat salah
    // terkirim, karena tidak ada medannya.
    expect(repoMocks.fetchPeriods).toHaveBeenCalledWith()
    // Dan daftar line pun diminta TANPA business_unit_id.
    expect(productionLineMocks.fetchProductionLinesForReport).toHaveBeenCalledWith(null)
  })

  it('nama mill dibaca dari blok business_unit, BUKAN dari period.business_unit_name', async () => {
    useAuthStoreMock.mockReturnValue({
      currentUser: {
        id: 'user-1', username: 'spv', name: 'SPV', role: 'supervisor', business_unit_id: 'bu-1',
      },
      // Tanpa cadangan dari store, supaya satu-satunya sumber adalah payload.
      businessUnit: null,
      logout: logoutMock,
    })

    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        business_unit: { id: 'bu-1', name: 'Mill Dari Blok Business Unit' },
        period: {
          id: 'per-1',
          name: 'Periode Uji Kernel Plant',
          start_date: PERIOD_START,
          end_date: PERIOD_END,
          status: 'open',
          business_unit_name: 'Mill Dari Kepala Periode',
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Dua tempat yang dapat menyimpang satu dari yang lain adalah satu tempat
    // terlalu banyak.
    expect(text(wrapper, 'mill-current')).toContain('Mill Dari Blok Business Unit')
    expect(text(wrapper, 'mill-current')).not.toContain('Mill Dari Kepala Periode')
  })

  it('opsi periode berlabel jenis stasiun, tanpa bergantung pada business_unit_name', async () => {
    const wrapper = await mountView()

    const options = wrapper
      .get('[data-testid="period-select"]')
      .findAll('option')
      .map((option) => option.text())

    expect(options).toEqual([
      'Pilih Periode',
      'Kernel Plant — Periode Uji Kernel Plant',
      'Kernel Plant — Periode Sebelumnya',
    ])
  })
})

/* ================================================================== */
/* Cakupan — dibaca lebih dulu                                         */
/* ================================================================== */

describe('cakupan pencatatan', () => {
  it('blok cakupan berada DI ATAS kartu parameter pada urutan DOM', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const html = wrapper.html()

    // Diasersi atas URUTAN, bukan atas kehadiran: periode yang terisi
    // seperlima pun menghasilkan rata-rata yang terlihat rapi.
    expect(html.indexOf('data-testid="coverage"'))
      .toBeLessThan(html.indexOf('data-testid="metrics"'))
  })

  it('mencetak ketiga angka pembentuk penyebutnya apa adanya, bukan hanya persennya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const denominator = text(wrapper, 'coverage-denominator')

    expect(denominator).toContain('2 unit')
    expect(denominator).toContain('6 hari')
    expect(denominator).toContain('24 slot')
    expect(text(wrapper, 'coverage-slots')).toContain('12 dari 288 slot')
    // 12/288 = 4,2 — layar yang menghitung sendiri menjawab 4,2 dan gagal.
    // Dilingkup KE ELEMEN CAKUPAN: standar master memuat '≤ 7.0%' dan
    // '≤ 6.0%', jadi asersi persen tingkat halaman tidak bermakna di sini.
    expect(text(wrapper, 'coverage-percent')).toBe('41,7%')
  })

  it('periode berjalan: keterangan penyebut berhenti di hari ini', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'coverage-running-note')).toContain('sampai hari ini')
    expect(exists(wrapper, 'coverage-not-started-note')).toBe(false)
  })

  it('days_counted 0 DIPERIKSA SEBELUM period_running: keterangan belum mulai yang terender', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        coverage: {
          filled_slots: 0,
          expected_slots: 0,
          coverage_percent: null,
          kernel_plant_count: 0,
          slots_per_kernel_plant_per_day: 24,
          days_in_period: 30,
          days_counted: 0,
          // Server menandai periode yang BELUM MULAI sebagai masih berjalan
          // juga — inilah kombinasi yang benar-benar dikirimnya, dan inilah
          // sebabnya days_counted harus diperiksa lebih dulu.
          period_running: true,
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'coverage-not-started-note')).toBe(true)
    // Memeriksa period_running lebih dulu membuat keterangan ini tertukar —
    // cacat yang nyata terjadi pada tiga layar sebelumnya.
    expect(exists(wrapper, 'coverage-running-note')).toBe(false)
    expect(text(wrapper, 'coverage-percent')).toBe('—')
  })

  it('coverage_percent null: tanda pisah, karena penyebutnya tidak terbentuk', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        coverage: {
          filled_slots: 0,
          expected_slots: 0,
          coverage_percent: null,
          kernel_plant_count: 0,
          slots_per_kernel_plant_per_day: 24,
          days_in_period: 30,
          days_counted: 0,
          period_running: false,
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'coverage-percent')).toBe('—')
  })

  it('coverage_percent 0: dirender 0,0% — penyebutnya terbentuk dan yang terukur nol', async () => {
    repoMocks.fetchSummary.mockResolvedValue(emptySummaryFixture())

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Draf pertama spec layar web menyatakan ini terbalik dan sudah
    // dikoreksi: tanda pisah HANYA untuk null, tidak untuk nol.
    expect(text(wrapper, 'coverage-percent')).toBe('0,0%')
    expect(exists(wrapper, 'coverage-not-started-note')).toBe(false)
  })

  it('cakupan TETAP dirender untuk periode kosong — justru itu yang menjelaskannya', async () => {
    repoMocks.fetchSummary.mockResolvedValue(emptySummaryFixture())

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'empty-period')).toBe(true)
    expect(exists(wrapper, 'coverage')).toBe(true)
    expect(text(wrapper, 'coverage-slots')).toContain('0 dari 288 slot')
  })
})

/* ================================================================== */
/* TUJUH kartu, dan penyebut "N dari M slot"                           */
/* ================================================================== */

describe('kartu parameter', () => {
  it('TEPAT tujuh kartu — bukan "setidaknya satu"', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(cards(wrapper)).toHaveLength(7)
    expect(
      cards(wrapper).map((card) => card.get('.metric-label').text()),
    ).toEqual([
      'Arus Ripple Mill 1 (Amps)',
      'Arus Ripple Mill 2 (Amps)',
      'Claybath / Hydrocyclone (SG)',
      'Suhu Kernel Silo 1 (C)',
      'Suhu Kernel Silo 2 (C)',
      'Kadar Air Kernel (%)',
      'Shell Bin Kernel Loss (%)',
    ])
  })

  it('TEPAT tujuh kartu juga ketika has_data false dan tiap kolom berpenyebut nol', async () => {
    repoMocks.fetchSummary.mockResolvedValue(emptySummaryFixture())

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Kartu yang hilang terbaca sebagai "tidak ada parameter ini", padahal
    // yang benar adalah "tidak ada yang mengukurnya".
    expect(cards(wrapper)).toHaveLength(7)
    expect(text(wrapper, 'metrics')).toContain('tidak tersedia')
    expect(
      wrapper
        .findAll('[data-testid="metric-denominator"]')
        .every((cell) => cell.text().includes('0 dari 0 slot')),
    ).toBe(true)
  })

  it('kartu kolom yang tidak pernah terisi TETAP dirender dengan angka tidak tersedia', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const claybath = cards(wrapper)[2]

    expect(claybath.text()).toContain('Claybath / Hydrocyclone')
    expect(claybath.text()).toContain('tidak tersedia')
    expect(claybath.get('[data-testid="metric-denominator"]').text())
      .toContain('0 dari 12 slot')
  })

  it('N milik tiap kartu sendiri dan BERBEDA-BEDA, sementara M SAMA pada ketujuhnya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const denominators = wrapper.findAll('[data-testid="metric-denominator"]')

    expect(denominators).toHaveLength(7)

    const texts = denominators.map((cell) => cell.text())

    expect(texts[0]).toContain('2 dari 12 slot')
    expect(texts[1]).toContain('9 dari 12 slot')
    expect(texts[2]).toContain('0 dari 12 slot')
    expect(texts[3]).toContain('5 dari 12 slot')
    expect(texts[4]).toContain('3 dari 12 slot')
    expect(texts[5]).toContain('7 dari 12 slot')
    expect(texts[6]).toContain('1 dari 12 slot')

    // KETUJUH N berbeda: dengan penyebut seragam, satu penyebut bersama akan
    // lolos tanpa terlihat.
    expect(new Set(texts).size).toBe(7)

    // Dan M SERAGAM pada ketujuhnya — skema ini tidak punya cara mengetahui M
    // per kolom, jadi M yang berbeda per kolom hanya bisa dikarang.
    expect(texts.every((value) => value.includes('dari 12 slot'))).toBe(true)
  })

  it('M yang MELAMPAUI setiap N dirender apa adanya, tanpa "dikoreksi" layar', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const numbers = wrapper
      .findAll('[data-testid="metric-denominator"]')
      .map((cell) => Number(cell.text().match(/^(\d+) dari (\d+) slot/)?.[1]))

    // Slot dihitung terisi bila salah satu dari SEMBILAN kolom bacaan terisi,
    // dan dua di antaranya (menit downtime, temuan) bukan kolom ukur — slot
    // yang hanya memuat "Ripple mill bergetar" jelas disentuh operator. Yang
    // dilampaui adalah N, BUKAN M, dan itu benar.
    expect(numbers.every((value) => value < 12)).toBe(true)
    expect(text(wrapper, 'coverage-slots')).toContain('12 dari 288 slot')
    expect(text(wrapper, 'no-flagging-note')).toContain('N, bukan M')
  })

  it('min, rata-rata, dan maks ketiganya dirender pada kartu yang sama, apa adanya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const first = cards(wrapper)[0].text()

    // avg 12,50 berada DI LUAR min 10,00 .. max 11,00 — mustahil dari
    // perhitungan apa pun, dan karena itu bukti bahwa angkanya diteruskan.
    expect(first).toContain('10,00')
    expect(first).toContain('12,50')
    expect(first).toContain('11,00')
  })

  it('nama parameter master dan KEDUA kolom target berada di dalam kartu angkanya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const moistureCard = cards(wrapper)[5]

    expect(moistureCard.get('[data-testid="metric-master-parameter"]').text())
      .toContain('Final Kernel Moisture')
    expect(moistureCard.get('[data-testid="metric-target-benchmark"]').text())
      .toContain('≤ 7.0% (Prevents mold growth)')
    // Teks TERPANJANG dari ketiga medan master, dan karena itu yang paling
    // berisiko dibuang demi ruang pada kartu 390px — tanpanya dua parameter
    // yang sama-sama melewati targetnya tampak menuntut tindakan yang sama.
    expect(moistureCard.get('[data-testid="metric-corrective-action"]').text())
      .toContain('Increase retention time or adjust silo air flow rates.')
  })

  it('DUA kolom target, bukan empat: bentuk Depricarping tidak dapat disalin masuk', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Diasersi atas KETIADAAN elemennya dan atas JUMLAH sel standar per
    // kartu, bukan atas nilai null — kolom yang kembali tanpa disadari akan
    // lolos dari asersi "nilainya null", dan menyerahkan dua sel yang tidak
    // mungkin diisi siapa pun ke layar.
    for (const card of cards(wrapper)) {
      expect(card.findAll('.metric-standard')).toHaveLength(2)
      expect(card.find('[data-testid="metric-critical-limit"]').exists()).toBe(false)
      expect(card.find('[data-testid="metric-consequence"]').exists()).toBe(false)
      expect(card.find('[data-testid="metric-target-range"]').exists()).toBe(false)
    }

    expect(text(wrapper, 'metrics')).not.toContain('Batas kritis')
    expect(text(wrapper, 'no-flagging-note')).toContain('tidak ada batas kritis terpisah')
  })

  it('standar yang null dirender sebagai PERNYATAAN, bukan sel kosong', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const claybath = cards(wrapper)[2]

    expect(claybath.get('[data-testid="metric-master-parameter"]').text())
      .toContain('tidak terpeta ke satu pun baris master')
    expect(claybath.get('[data-testid="metric-target-benchmark"]').text())
      .toContain('target belum terisi pada master')
    expect(claybath.get('[data-testid="metric-corrective-action"]').text())
      .toContain('belum terisi')
  })

  it('tidak ada satu angka pun yang diturunkan di klien', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // daily_total bukan jumlah/rata-rata baris hariannya.
    expect(text(wrapper, 'daily-recap-total')).toContain('99')
    expect(text(wrapper, 'daily-recap-total')).toContain('77,70')
    expect(text(wrapper, 'daily-recap-total')).toContain('999')
    // downtime rata-rata bukan 60/3.
    expect(text(wrapper, 'downtime-average')).toContain('99,0')
    // Dan baris hariannya tetap angkanya sendiri.
    const dailyRows = wrapper.findAll('[data-testid="daily-row"]')

    expect(dailyRows[0].text()).toContain('10,00')
    expect(dailyRows[1].text()).toContain('100,00')
  })
})

/* ================================================================== */
/* DUA PASANGAN BERBAGI STANDAR                                        */
/* ================================================================== */

describe('standar bersama — dua pasangan, bukan satu', () => {
  it('EMPAT kartu membawa keterangannya, TIGA tidak', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const shared = wrapper.findAll('[data-testid="metric-shared-standard"]')

    // EMPAT, bukan dua: 'Ripple Mill (Cracker)' mengatur kedua ripple mill,
    // dan 'Kernel Silo 1 & 2' mengatur kedua silo. Markup yang
    // mengistimewakan SATU pasangan benar di Depricarping mobile dan SALAH di
    // sini.
    expect(shared).toHaveLength(4)

    // Label saudaranya diambil dari metrics[].label pada payload, bukan dari
    // peta yang ditulis ulang di view.
    expect(shared[0].text()).toContain('Arus Ripple Mill 2')
    expect(shared[1].text()).toContain('Arus Ripple Mill 1')
    expect(shared[2].text()).toContain('Suhu Kernel Silo 2')
    expect(shared[3].text()).toContain('Suhu Kernel Silo 1')

    const all = cards(wrapper)

    for (const index of [0, 1, 3, 4]) {
      expect(
        all[index].find('[data-testid="metric-shared-standard"]').exists(),
        `kartu ${index} seharusnya membawa keterangan`,
      ).toBe(true)
    }

    // Ketiga metrik lain TIDAK membawanya: keterangan yang muncul di mana-mana
    // berhenti berarti apa pun.
    for (const index of [2, 5, 6]) {
      expect(
        all[index].find('[data-testid="metric-shared-standard"]').exists(),
        `kartu ${index} seharusnya tanpa keterangan`,
      ).toBe(false)
    }
  })

  it('keterangan menyebut baris masternya dan menyatakan angkanya tidak dirata-ratakan', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const shared = wrapper.findAll('[data-testid="metric-shared-standard"]')

    for (const index of [0, 1]) {
      expect(shared[index].text()).toContain('Ripple Mill (Cracker)')
      expect(shared[index].text()).toContain('Angkanya TIDAK dirata-ratakan menjadi satu')
    }

    for (const index of [2, 3]) {
      expect(shared[index].text()).toContain('Kernel Silo 1 & 2')
      expect(shared[index].text()).toContain('Angkanya TIDAK dirata-ratakan menjadi satu')
    }
  })

  it('angka kedua anggota pasangan TIDAK dirata-ratakan menjadi satu', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const all = cards(wrapper)

    expect(all[0].text()).toContain('12,50')
    expect(all[1].text()).toContain('33,00')
    expect(all[3].text()).toContain('74,00')
    expect(all[4].text()).toContain('58,00')

    // Rata-rata gabungan tiap pasangan — empat mesin fisik, dan
    // merata-ratakannya akan menyembunyikan ketidakseimbangan beban yang
    // justru menjadi alasan parameter itu diukur.
    expect(wrapper.text()).not.toContain('22,75')
    expect(wrapper.text()).not.toContain('66,00')
  })

  it('keterangannya DITURUNKAN dari shares_standard_with: kosong pada satu anggota, hilang untuknya', async () => {
    const trimmed = metrics()

    ;(trimmed[1].target as Record<string, unknown>).shares_standard_with = []

    repoMocks.fetchSummary.mockResolvedValue(summaryFixture({ metrics: trimmed }))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // TIGA keterangan, bukan empat — dan yang hilang adalah milik kartu yang
    // payload-nya mengosongkannya. Markup yang memaku keanggotaan pasangan
    // akan tetap mencetak empat dan gagal di sini.
    expect(wrapper.findAll('[data-testid="metric-shared-standard"]')).toHaveLength(3)
    expect(cards(wrapper)[1].find('[data-testid="metric-shared-standard"]').exists()).toBe(false)
    // Kartu pasangan lainnya tidak terpengaruh.
    expect(cards(wrapper)[0].find('[data-testid="metric-shared-standard"]').exists()).toBe(true)
    expect(cards(wrapper)[3].find('[data-testid="metric-shared-standard"]').exists()).toBe(true)
    expect(cards(wrapper)[4].find('[data-testid="metric-shared-standard"]').exists()).toBe(true)
  })

  it('pasangan KETIGA mana pun ikut tertandai tanpa menyentuh berkas view', async () => {
    const extended = metrics()

    ;(extended[5].target as Record<string, unknown>).equipment_parameter = 'Kadar Air Kernel'
    ;(extended[5].target as Record<string, unknown>).shares_standard_with = ['shell_loss_percent']
    ;(extended[6].target as Record<string, unknown>).equipment_parameter = 'Kadar Air Kernel'
    ;(extended[6].target as Record<string, unknown>).shares_standard_with = ['kernel_moisture_percent']

    repoMocks.fetchSummary.mockResolvedValue(summaryFixture({ metrics: extended }))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // ENAM keterangan sekarang — view yang memaku dua pasangan akan tetap
    // mencetak empat.
    expect(wrapper.findAll('[data-testid="metric-shared-standard"]')).toHaveLength(6)
    expect(cards(wrapper)[5].get('[data-testid="metric-shared-standard"]').text())
      .toContain('Shell Bin Kernel Loss')
    expect(cards(wrapper)[6].get('[data-testid="metric-shared-standard"]').text())
      .toContain('Kadar Air Kernel')
  })

  it('equipment_parameter null: PERNYATAANNYA yang berganti, bukan sekadar nilainya', async () => {
    const detached = metrics()

    for (const index of [0, 1]) {
      ;(detached[index].target as Record<string, unknown>).equipment_parameter = null
      ;(detached[index].target as Record<string, unknown>).target_benchmark = null
      ;(detached[index].target as Record<string, unknown>).corrective_action_plan = null
    }

    repoMocks.fetchSummary.mockResolvedValue(summaryFixture({ metrics: detached }))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const shared = wrapper.findAll('[data-testid="metric-shared-standard"]')

    // Keterangannya TETAP ADA — hubungan pasangannya tidak hilang hanya
    // karena masternya terlepas.
    expect(shared).toHaveLength(4)

    for (const index of [0, 1]) {
      const note = shared[index].text()

      // Mencetaknya apa adanya menghasilkan 'master hanya memuat satu baris
      // “” untuk keduanya': tanda kutip kosong yang MENGKLAIM sebuah baris
      // master yang justru baru terlepas. Cacat persis ini ditemukan dan
      // ditutup pada layar web screen-154.
      expect(note).not.toContain('master hanya memuat satu baris')
      expect(note).not.toContain('“”')
      expect(note).not.toContain('""')
      // Yang berganti adalah PERNYATAANNYA.
      expect(note).toContain('tidak terpeta ke satu pun baris master')
      expect(note).toContain('tidak lagi tercetak di kartu ini')
      // Dan ia TETAP menyatakan bahwa angkanya tidak dirata-ratakan — itulah
      // alasan keterangan ini ada sejak awal.
      expect(note).toContain('Angkanya TIDAK dirata-ratakan menjadi satu')
    }

    // Kartu pasangan silo, yang masternya masih terpeta, tetap memakai
    // kalimat yang satunya.
    expect(shared[2].text()).toContain('master hanya memuat satu baris')
    expect(shared[2].text()).toContain('Kernel Silo 1 & 2')
  })
})

/* ================================================================== */
/* Ketiadaan penandaan di luar batas                                   */
/* ================================================================== */

describe('tidak ada penandaan di luar batas', () => {
  it('tidak ada satu pun elemen berkelas penanda pada hasil render', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Rata-rata ripple mill 2 (33 Amps) jelas di luar '20 - 25 Amps', dan
    // tetap tidak ditandai. DIASERSI ATAS JUMLAH ELEMEN BERKELAS, bukan atas
    // substring sumber maupun atas frasa: kalimat yang menyatakan ketiadaan
    // penandaan sendiri memuat frasa "di luar batas".
    for (const className of ['md-threshold', 'is-danger', 'is-warning', 'md-chip--danger']) {
      expect(wrapper.findAll(`.${className}`).length, className).toBe(0)
    }
  })

  it('ketiadaannya DINYATAKAN, berikut sebabnya yang khas stasiun ini', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const note = text(wrapper, 'no-flagging-note')

    expect(note).toContain('tidak menilai satu angka pun')
    expect(note).toContain('teks bebas')
    // Sebab yang KHAS Kernel Plant: satu sel memuat dua angka dengan satuan
    // berbeda dan arah pembanding berlawanan.
    expect(note).toContain('dua angka dengan satuan berbeda dan arah pembanding berlawanan')
    expect(note).toContain('berhenti memperingatkan tanpa satu pun galat')
    expect(note).toContain('Kedua kolom target berlaku umum untuk seluruh mill')
    expect(note).toContain('M sama untuk ketujuh kartu')
    expect(note).toContain('sembilan')
  })
})

/* ================================================================== */
/* Standar tanpa pengukuran — NORMALNYA BERISI                         */
/* ================================================================== */

describe('standar tanpa pengukuran', () => {
  it('keadaan NORMAL layar ini: satu baris Final Kernel Dirt, beserta alasannya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Berlawanan dengan Depricarping, yang daftarnya normalnya kosong:
    // daftar yang BERISI di sini bukan tanda ada yang rusak.
    expect(exists(wrapper, 'targets-without-metric')).toBe(true)
    expect(wrapper.findAll('[data-testid="targets-without-metric-row"]')).toHaveLength(1)
    expect(text(wrapper, 'targets-without-metric')).toContain('Final Kernel Dirt')
    expect(text(wrapper, 'targets-without-metric')).toContain('≤ 6.0% (Standard quality premium)')
    expect(text(wrapper, 'targets-without-metric'))
      .toContain('Clean winnowing ducts or re-calibrate hydrocyclone settings.')
    // ALASANNYA tercetak — pembaca perlu tahu tindakan apa yang diperlukan.
    expect(text(wrapper, 'targets-without-metric-reason'))
      .toContain('Tidak ada kolom pengukurannya')
    expect(text(wrapper, 'targets-without-metric-note'))
      .toContain('terbaca seperti terpenuhi')
    expect(text(wrapper, 'targets-without-metric-note'))
      .toContain('adalah penghuni normal daftar ini')
    expect(exists(wrapper, 'targets-all-measured')).toBe(false)
    expect(exists(wrapper, 'targets-master-empty')).toBe(false)
  })

  it('bagiannya TETAP DIGAMBAR walau kosong, dengan NOL baris di dalamnya', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({ targets_without_metric: [], all_targets_measured: true }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Bagian yang hilang ketika kosong tidak dapat dibedakan dari bagian yang
    // belum pernah dibuat. Diasersi atas KEHADIRAN WADAHNYA dan JUMLAH BARIS
    // NOL, bukan atas salah satunya saja.
    expect(exists(wrapper, 'targets-without-metric')).toBe(true)
    expect(wrapper.findAll('[data-testid="targets-without-metric-row"]')).toHaveLength(0)
    expect(exists(wrapper, 'targets-all-measured')).toBe(true)
  })

  it('master kosong: bagian itu sengaja TIDAK digambar, dan angka ukur tetap tampil', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        targets_master_empty: true,
        targets_without_metric: [],
        all_targets_measured: true,
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    // Daftar kosong karena master belum terisi BERLAWANAN artinya dengan
    // daftar kosong karena seluruh standar sudah terukur — mencampurnya akan
    // membuat master yang kosong terbaca sebagai "tidak ada yang tertinggal".
    expect(exists(wrapper, 'targets-master-empty')).toBe(true)
    expect(exists(wrapper, 'targets-without-metric')).toBe(false)
    expect(exists(wrapper, 'targets-all-measured')).toBe(false)
    // Master yang belum diisi tidak menghapus pengukuran yang sudah terjadi.
    expect(cards(wrapper)).toHaveLength(7)
    expect(text(wrapper, 'metrics')).toContain('12,50')
  })
})

/* ================================================================== */
/* Rekap, downtime, temuan, kelengkapan                                */
/* ================================================================== */

describe('rekap dan kelengkapan', () => {
  it('rekap per unit memakai nama unit apa adanya, dengan downtime null dinyatakan', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const rows = wrapper.findAll('[data-testid="by-kernel-plant-row"]')

    expect(rows).toHaveLength(2)
    expect(rows[0].text()).toContain('KP-1')
    expect(rows[1].text()).toContain('KP-2')
    // null, BUKAN 0 — unit yang tidak dicatat bukan unit yang tidak pernah
    // berhenti.
    expect(rows[0].get('[data-testid="by-kernel-plant-downtime"]').text()).toBe('30')
    expect(rows[1].get('[data-testid="by-kernel-plant-downtime"]').text()).toBe('belum dicatat')
  })

  it('nama unit kosong tetap berlabel, bukan sel kosong', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        by_kernel_plant: [
          {
            // kernel_plant_id adalah kolom TEKS yang diketik di layar input,
            // jadi nilai kosong mungkin saja sampai ke sini.
            kernel_plant_id: '  ',
            kernel_plant_name: '  ',
            day_count: 1,
            filled_slot_count: 1,
            downtime_minutes: null,
            averages: {},
          },
        ],
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'by-kernel-plant-row')).toContain('Belum diisi')
  })

  it('blok downtime menerbitkan angka dengan penyebut slot pencatatnya', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'downtime-total')).toContain('60')
    // PENYEBUTNYA 3 slot pencatat, BUKAN 12 slot terisi.
    expect(text(wrapper, 'downtime-recorded-slots')).toContain('3')
    expect(text(wrapper, 'downtime-recorded-slots')).toContain('dari 12 slot terisi')
    expect(text(wrapper, 'downtime-average')).toContain('99,0')
    expect(text(wrapper, 'downtime-note')).toContain('tidak dihitung sebagai nol')
    expect(text(wrapper, 'downtime-note')).toContain('tidak punya baris pada master target')
  })

  it('tanpa satu pun slot pencatat: blok downtime menyatakan ketiadaan, bukan 0 menit', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        downtime: {
          total_minutes: null,
          recorded_slot_count: 0,
          avg_minutes_per_recorded_slot: null,
          has_standard: false,
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'downtime-empty')).toBe(true)
    expect(exists(wrapper, 'downtime-total')).toBe(false)
    expect(text(wrapper, 'downtime-empty')).toContain('Belum ada satu slot pun')
    // Keterangan cara-hitung TETAP terender: ia menjelaskan cara hitungnya,
    // bukan hasilnya.
    expect(text(wrapper, 'downtime-note')).toContain('tidak dihitung sebagai nol')
  })

  it('temuan TERPISAH dari downtime, dua ejaan jadi dua baris, dengan keterangan harfiah', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const rows = wrapper.findAll('[data-testid="findings-row"]')

    expect(rows).toHaveLength(2)
    expect(rows[0].text()).toContain('Ripple mill bergetar')
    expect(rows[1].text()).toContain('ripple mill bergetar')
    expect(text(wrapper, 'findings-note')).toContain('harfiah')
    // Satu menjawab "berapa lama", satu "apa yang terlihat".
    expect(text(wrapper, 'findings-note')).toContain('tidak digabungkan')
    expect(exists(wrapper, 'downtime')).toBe(true)
    expect(exists(wrapper, 'findings')).toBe(true)
  })

  it('tanpa temuan: keterangan terender, bukan tabel kosong', async () => {
    repoMocks.fetchSummary.mockResolvedValue(summaryFixture({ findings: [] }))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'findings-empty')).toBe(true)
    expect(exists(wrapper, 'findings-table')).toBe(false)
  })

  it('kelima penghitung kelengkapan terender, dengan keterangan draft ikut terhitung', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'record-count')).toBe('12')
    expect(text(wrapper, 'days-with-records')).toBe('2')
    expect(text(wrapper, 'draft-record-count')).toBe('4')
    expect(text(wrapper, 'records-not-checked')).toBe('5')
    expect(text(wrapper, 'records-not-acknowledged')).toBe('6')
    expect(text(wrapper, 'draft-note')).toContain('IKUT terhitung')
    expect(text(wrapper, 'draft-note')).toContain('bukan penyaring')
  })

  it('rekap harian: baris harian dan baris total periode, tanpa permintaan saat dibuka', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(wrapper.findAll('[data-testid="daily-row"]')).toHaveLength(2)
    expect(exists(wrapper, 'daily-recap-total')).toBe(true)

    const before = repoMocks.fetchSummary.mock.calls.length
    const toggle = wrapper.find('[data-testid="daily-recap"] button')

    if (toggle.exists()) {
      await toggle.trigger('click')
      await flushPromises()
      await toggle.trigger('click')
      await flushPromises()
    }

    expect(repoMocks.fetchSummary.mock.calls.length).toBe(before)
  })

  it('periode tanpa data: rekap tidak digambar, kartu dan cakupan tetap ada', async () => {
    repoMocks.fetchSummary.mockResolvedValue(emptySummaryFixture())

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'empty-period')).toBe(true)
    expect(exists(wrapper, 'coverage')).toBe(true)
    expect(cards(wrapper)).toHaveLength(7)
    // Tabel kosong akan terbaca sebagai hasil pengukuran bernilai nol.
    expect(exists(wrapper, 'by-kernel-plant')).toBe(false)
    expect(exists(wrapper, 'downtime')).toBe(false)
    expect(exists(wrapper, 'findings')).toBe(false)
    expect(exists(wrapper, 'daily-recap')).toBe(false)
    // Tetapi bagian standar tanpa pengukuran TETAP digambar.
    expect(exists(wrapper, 'targets-without-metric')).toBe(true)
  })
})

/* ================================================================== */
/* Production Line — KONTEKS YANG DIPILIH                              */
/* ================================================================== */

describe('Production Line', () => {
  it('memakai line dari query rute, mengalahkan ingatan perangkat', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(LINES)
    window.localStorage.setItem('msl_production_line_user-1', 'pl-1')
    routeQuery.production_line_id = 'pl-2'

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(repoMocks.fetchSummary).toHaveBeenCalledWith(
      'per-1',
      expect.objectContaining({ productionLineId: 'pl-2' }),
    )
    // Dan pilihan dari rute ikut menjadi ingatan: layar Daftar Stasiun dan
    // layar laporan berbagi satu konteks.
    expect(window.localStorage.getItem('msl_production_line_user-1')).toBe('pl-2')
  })

  it('memakai ingatan perangkat milik pengguna ITU, bukan pengguna lain', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(LINES)
    window.localStorage.setItem('msl_production_line_user-1', 'pl-2')
    window.localStorage.setItem('msl_production_line_user-9', 'pl-1')

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(repoMocks.fetchSummary).toHaveBeenCalledWith(
      'per-1',
      expect.objectContaining({ productionLineId: 'pl-2' }),
    )
  })

  it('satu line: berlaku tanpa pemilih, tetapi namanya tetap tampil', async () => {
    const wrapper = await mountView()

    expect(exists(wrapper, 'production-line-select')).toBe(false)

    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'production-line-current')).toContain('Line Satu')
  })

  it('line yang diingat sudah tidak ada: pemilih kembali, dan TIDAK ada line lain yang ditebak', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(LINES)
    window.localStorage.setItem('msl_production_line_user-1', 'pl-hilang')

    const wrapper = await mountView()

    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
    // Tidak menebak Line Satu maupun Line Dua sebagai penggantinya.
    expect(exists(wrapper, 'production-line-current')).toBe(false)

    await selectPeriod(wrapper, 'per-1')

    // Penjagaan ada di loadSummary(), bukan hanya di template.
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
  })

  it('localStorage yang melempar diperlakukan sebagai "tidak ada ingatan", bukan galat', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(LINES)

    const spy = vi.spyOn(window.localStorage, 'getItem').mockImplementation(() => {
      throw new Error('SecurityError')
    })

    const wrapper = await mountView()

    // Layar tetap utuh, dan yang terjadi hanyalah pemilih yang muncul.
    expect(exists(wrapper, 'laporan-kernel-plant-mobile')).toBe(true)
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'error-message')).toBe(false)

    spy.mockRestore()
  })

  it('tanpa line yang berlaku, /summary TIDAK PERNAH diminta — juga sesudah Coba Lagi dan ganti periode', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(LINES)
    productionLineMocks.fetchProductionLinesForReport.mockRejectedValueOnce(NETWORK_ERROR)

    const wrapper = await mountView()

    expect(exists(wrapper, 'production-line-retry')).toBe(true)
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()

    await wrapper.get('[data-testid="production-line-retry"]').trigger('click')
    await flushPromises()

    // Dua line, tidak satu pun terpilih.
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)

    await selectPeriod(wrapper, 'per-1')
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()

    await selectPeriod(wrapper, 'per-2')
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()

    // Dan begitu sebuah line berlaku, permintaannya berangkat — tanpa ini
    // test di atas juga akan hijau pada layar yang rusak total.
    await selectLine(wrapper, 'pl-2')

    expect(repoMocks.fetchSummary).toHaveBeenCalledTimes(1)
    expect(repoMocks.fetchSummary).toHaveBeenCalledWith(
      'per-2',
      expect.objectContaining({ productionLineId: 'pl-2' }),
    )
  })

  it('mill tanpa line: ARAHAN menghubungi Admin, TANPA tombol coba lagi', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([])

    const wrapper = await mountView()

    expect(text(wrapper, 'production-line-unavailable')).toContain('belum memiliki Production Line')
    expect(text(wrapper, 'production-line-unavailable')).toContain('hubungi Admin')
    // Mencoba lagi tidak akan mengubah sesuatu yang tidak ada.
    expect(exists(wrapper, 'production-line-retry')).toBe(false)
  })

  it('daftar line gagal dimuat: pesan BERBEDA, dengan tombol coba lagi sendiri', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockRejectedValueOnce(NETWORK_ERROR)

    const wrapper = await mountView()

    const notice = text(wrapper, 'production-line-unavailable')

    // DUA keadaan yang menuntut tindakan berbeda, jadi dua pesan berbeda.
    expect(notice).toContain('tidak dapat dimuat')
    expect(notice).toContain('Periksa koneksi jaringan Anda')
    expect(notice).not.toContain('belum memiliki Production Line')
    expect(exists(wrapper, 'production-line-retry')).toBe(true)
    // Kegagalan daftar line tidak boleh menyamar sebagai galat laporan.
    expect(exists(wrapper, 'network-error')).toBe(false)

    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([LINE_1])
    await wrapper.get('[data-testid="production-line-retry"]').trigger('click')
    await flushPromises()

    expect(exists(wrapper, 'production-line-unavailable')).toBe(false)
  })

  it('ganti line mengosongkan ringkasan lama sebelum yang baru tiba', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(LINES)
    routeQuery.production_line_id = 'pl-1'

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'period-meta')).toBe(true)

    await selectLine(wrapper, 'pl-2')

    expect(repoMocks.fetchSummary).toHaveBeenLastCalledWith(
      'per-1',
      expect.objectContaining({ productionLineId: 'pl-2' }),
    )
    expect(window.localStorage.getItem('msl_production_line_user-1')).toBe('pl-2')
  })
})

/* ================================================================== */
/* Keadaan tanpa angka                                                 */
/* ================================================================== */

describe('keadaan tanpa angka', () => {
  it('akun tanpa business_unit_id: berhenti tanpa satu pun permintaan, tanpa pemilih mill', async () => {
    asRole('operator', null)

    const wrapper = await mountView()

    expect(exists(wrapper, 'no-mill-for-account')).toBe(true)
    expect(exists(wrapper, 'mill-select')).toBe(false)
    expect(repoMocks.fetchPeriods).not.toHaveBeenCalled()
    expect(repoMocks.fetchSummary).not.toHaveBeenCalled()
    expect(productionLineMocks.fetchProductionLinesForReport).not.toHaveBeenCalled()
  })

  it('daftar periode kosong: arahan tampil hanya SETELAH pemuatan selesai', async () => {
    let resolvePeriods: ((value: unknown[]) => void) | null = null

    repoMocks.fetchPeriods.mockReturnValue(
      new Promise((resolve) => {
        resolvePeriods = resolve as (value: unknown[]) => void
      }),
    )

    const wrapper = mount(LaporanKernelPlantView)
    await flushPromises()

    // SELAMA memuat: indikator muat, BUKAN "belum punya periode".
    expect(exists(wrapper, 'periods-loading')).toBe(true)
    expect(exists(wrapper, 'no-periods')).toBe(false)

    resolvePeriods?.([])
    await flushPromises()

    expect(exists(wrapper, 'no-periods')).toBe(true)
    expect(exists(wrapper, 'periods-loading')).toBe(false)
    // Pemilih yang LENYAP dan pemilih yang KOSONG menceritakan hal berbeda.
    expect(exists(wrapper, 'period-select')).toBe(true)
  })
})

/* ================================================================== */
/* Galat                                                               */
/* ================================================================== */

describe('penanganan galat', () => {
  it('kegagalan jaringan: pesan + coba lagi, dan periode terpilih TIDAK hilang', async () => {
    repoMocks.fetchSummary.mockRejectedValueOnce(NETWORK_ERROR)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'network-error')).toBe(true)
    expect(exists(wrapper, 'retry-button')).toBe(true)
    expect(
      (wrapper.get('[data-testid="period-select"]').element as HTMLSelectElement).value,
    ).toBe('per-1')

    repoMocks.fetchSummary.mockResolvedValue(summaryFixture())
    await wrapper.get('[data-testid="retry-button"]').trigger('click')
    await flushPromises()

    expect(repoMocks.fetchSummary).toHaveBeenLastCalledWith('per-1', expect.anything())
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(cards(wrapper)).toHaveLength(7)
  })

  it('401: diarahkan ke Login, TANPA pesan jaringan dan tanpa tombol coba lagi', async () => {
    repoMocks.fetchSummary.mockRejectedValueOnce(UNAUTHENTICATED_ERROR)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
    // "Periksa koneksi Anda" pada sesi yang berakhir mengirim pembaca
    // memeriksa sinyalnya alih-alih masuk kembali.
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'error-message')).toBe(false)
    expect(exists(wrapper, 'retry-button')).toBe(false)
    expect(exists(wrapper, 'period-meta')).toBe(false)
  })

  it('galat ber-status selain 401: pesan dapat dibaca, bukan pesan jaringan', async () => {
    repoMocks.fetchSummary.mockRejectedValueOnce({
      message: 'Production Line wajib dipilih.',
      status: 422,
    })

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    expect(text(wrapper, 'error-message')).toContain('Production Line wajib dipilih.')
    expect(exists(wrapper, 'network-error')).toBe(false)
  })
})

/* ================================================================== */
/* Respons basi                                                        */
/* ================================================================== */

describe('hanya jawaban permintaan terbaru yang berlaku', () => {
  it('jawaban permintaan LAMA yang tiba belakangan tidak menimpa yang baru', async () => {
    const lamaMetrics = metrics()
    const baruMetrics = metrics()

    lamaMetrics[0].avg = 111
    baruMetrics[0].avg = 222

    const lama = summaryFixture({ metrics: lamaMetrics })
    const baru = summaryFixture({ metrics: baruMetrics })

    let resolveLama: ((value: unknown) => void) | null = null

    repoMocks.fetchSummary
      .mockImplementationOnce(
        () =>
          new Promise((resolve) => {
            resolveLama = resolve as (value: unknown) => void
          }),
      )
      .mockResolvedValueOnce(baru)

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await selectPeriod(wrapper, 'per-2')

    expect(text(wrapper, 'metrics')).toContain('222,00')

    // Permintaan PERTAMA selesai PALING AKHIR — dan tetap kalah.
    resolveLama?.(lama)
    await flushPromises()

    expect(text(wrapper, 'metrics')).toContain('222,00')
    expect(text(wrapper, 'metrics')).not.toContain('111,00')
  })

  it('galat permintaan lama tidak memunculkan pesan basi', async () => {
    let rejectLama: ((error: unknown) => void) | null = null

    repoMocks.fetchSummary
      .mockImplementationOnce(
        () =>
          new Promise((_resolve, reject) => {
            rejectLama = reject as (error: unknown) => void
          }),
      )
      .mockResolvedValueOnce(summaryFixture())

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await selectPeriod(wrapper, 'per-2')

    rejectLama?.(NETWORK_ERROR)
    await flushPromises()

    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'summary-loading')).toBe(false)
    expect(cards(wrapper)).toHaveLength(7)
  })

  it('mengosongkan pilihan periode saat ringkasan dimuat: tidak ada angka yang muncul kemudian', async () => {
    let resolveLama: ((value: unknown) => void) | null = null

    repoMocks.fetchSummary.mockImplementationOnce(
      () =>
        new Promise((resolve) => {
          resolveLama = resolve as (value: unknown) => void
        }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await selectPeriod(wrapper, '')

    resolveLama?.(summaryFixture())
    await flushPromises()

    expect(exists(wrapper, 'period-meta')).toBe(false)
    expect(exists(wrapper, 'summary-loading')).toBe(false)
  })
})

/* ================================================================== */
/* Periode tertutup & ekspor                                           */
/* ================================================================== */

describe('periode tertutup', () => {
  it('laporan tampil penuh, badge Tertutup tampil, dan ekspor TIDAK dinonaktifkan', async () => {
    repoMocks.fetchSummary.mockResolvedValue(
      summaryFixture({
        period: {
          id: 'per-2',
          name: 'Periode Sebelumnya',
          start_date: PREV_START,
          end_date: PREV_END,
          status: 'closed',
        },
      }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-2')

    expect(text(wrapper, 'period-status-badge')).toContain('Tertutup')
    expect(cards(wrapper)).toHaveLength(7)
    expect(wrapper.get('[data-testid="export-button"]').attributes('disabled')).toBeUndefined()
    expect(text(wrapper, 'period-meta')).toContain('mengatur penulisan data')
  })
})

describe('ekspor', () => {
  it('mengekspor periode terpilih dan menyimpan berkas bernama slug periodenya', async () => {
    repoMocks.exportCsv.mockResolvedValue(new Blob(['a,b']))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await wrapper.get('[data-testid="export-button"]').trigger('click')
    await flushPromises()

    expect(repoMocks.exportCsv).toHaveBeenCalledWith(
      'per-1',
      expect.objectContaining({ productionLineId: 'pl-1' }),
    )
    expect(repoMocks.saveCsvFile).toHaveBeenCalledWith(
      expect.any(Blob),
      'laporan-kernel-plant_periode-uji-kernel-plant.csv',
    )
  })

  it('cakupan ekspor TIDAK membawa medan mill, karena medannya tidak ada', async () => {
    repoMocks.exportCsv.mockResolvedValue(new Blob(['a,b']))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await wrapper.get('[data-testid="export-button"]').trigger('click')
    await flushPromises()

    expect(repoMocks.exportCsv.mock.calls[0][1]).toEqual({ productionLineId: 'pl-1' })
    // Dan cakupan ringkasan pun sama bentuknya: satu medan.
    expect(repoMocks.fetchSummary.mock.calls[0][1]).toEqual({ productionLineId: 'pl-1' })
  })

  it('HANYA CSV: tidak ada satu pun tawaran .xlsx di layar ini', async () => {
    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const labels = wrapper.findAll('button').map((button) => button.text())

    expect(labels.some((label) => label.includes('Ekspor CSV'))).toBe(true)
    expect(labels.some((label) => /xlsx|excel/i.test(label))).toBe(false)
    expect(wrapper.html()).not.toMatch(/xlsx/i)
    expect(wrapper.text()).not.toMatch(/excel/i)
  })

  it('ketukan ganda hanya menghasilkan satu ekspor', async () => {
    let resolveExport: ((value: Blob) => void) | null = null

    repoMocks.exportCsv.mockImplementation(
      () =>
        new Promise((resolve) => {
          resolveExport = resolve as (value: Blob) => void
        }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')

    const button = wrapper.get('[data-testid="export-button"]')

    await button.trigger('click')
    await button.trigger('click')
    await flushPromises()

    expect(repoMocks.exportCsv).toHaveBeenCalledTimes(1)
    expect(button.attributes('disabled')).toBeDefined()
    expect(button.text()).toContain('Mengekspor')

    resolveExport?.(new Blob(['a,b']))
    await flushPromises()
  })

  it('nama berkas mengikuti periode yang DIEKSPOR walau pilihan berganti selama unduhan', async () => {
    let resolveExport: ((value: Blob) => void) | null = null

    repoMocks.exportCsv.mockImplementation(
      () =>
        new Promise((resolve) => {
          resolveExport = resolve as (value: Blob) => void
        }),
    )

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await wrapper.get('[data-testid="export-button"]').trigger('click')

    await selectPeriod(wrapper, 'per-2')

    resolveExport?.(new Blob(['a,b']))
    await flushPromises()

    // Isi berkas milik per-1, jadi namanya pun harus milik per-1.
    expect(repoMocks.saveCsvFile).toHaveBeenCalledWith(
      expect.any(Blob),
      'laporan-kernel-plant_periode-uji-kernel-plant.csv',
    )
  })

  it('tombol ekspor tidak dirender sebelum periode dan line keduanya berlaku', async () => {
    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue(LINES)

    const wrapper = await mountView()

    expect(exists(wrapper, 'export-button')).toBe(false)

    await selectLine(wrapper, 'pl-1')

    expect(exists(wrapper, 'export-button')).toBe(false)

    await selectPeriod(wrapper, 'per-1')

    expect(exists(wrapper, 'export-button')).toBe(true)
  })
})

/* ================================================================== */
/* Layar hanya membaca                                                 */
/* ================================================================== */

describe('layar hanya membaca', () => {
  it('footer hanya Ekspor dan Back, dan seluruh pemanggilan repo adalah pembacaan', async () => {
    repoMocks.exportCsv.mockResolvedValue(new Blob(['a,b']))

    const wrapper = await mountView()
    await selectPeriod(wrapper, 'per-1')
    await wrapper.get('[data-testid="export-button"]').trigger('click')
    await flushPromises()

    const footerButtons = wrapper.findAll('.action-footer button').map((button) => button.text())

    expect(footerButtons).toHaveLength(2)
    expect(footerButtons.join(' ')).toContain('Ekspor')
    expect(footerButtons.join(' ')).toContain('Back')

    expect(Object.keys(repoMocks).every((key) => /^fetch|^export|^save/.test(key))).toBe(true)
  })

  it('Back kembali ke Dashboard & Reporting', async () => {
    const wrapper = await mountView()
    await wrapper.get('[data-testid="back-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'dashboard-reporting' })
  })
})
