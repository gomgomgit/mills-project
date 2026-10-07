/**
 * kernelPlantReportRepo.spec.ts — screen-155--laporan-kernel-plant-mobile /
 * usecase-161--laporan-kernel-plant-mobile.
 *
 * Satu test per unit_test_cases pada tech spec screen-155. Yang diuji di sini
 * adalah BENTUK PERMINTAAN dan KESETIAAN TERUSAN — bukan kebenaran angkanya,
 * yang sudah dibuktikan terhadap KernelPlantReportService di backend.
 *
 * ────────────────────────────────────────────────────────────────────────
 * TIGA KLAIM YANG HANYA BERKAS INI DAPAT MENCATATNYA
 * ────────────────────────────────────────────────────────────────────────
 * 1. `business_unit_id` TIDAK PERNAH ADA DI KAWAT, apa pun yang diserahkan
 *    pemanggil. Perlindungannya STRUKTURAL — tidak ada satu pun jalur kode
 *    yang dapat mengirimkannya, bukan sebuah penjagaan yang menolaknya — jadi
 *    test inilah satu-satunya yang mencatat maksudnya. Tanpa ini, menambahkan
 *    cabang Admin "sekadar supaya seragam dengan kesepuluh repo saudaranya"
 *    tampak seperti perbaikan, padahal
 *    /api/kernel-plant-reports/business-units/options justru menjawab 403
 *    untuk kedua peran layar ini — dan menyerahkan daftar seluruh mill kepada
 *    Operator adalah kebocoran yang dihindari.
 *
 * 2. BLOK `target` BERISI TEPAT EMPAT MEDAN, dan DUA medan milik Depricarping
 *    TIDAK ADA di dalamnya: tidak ada `critical_limit` dan tidak ada
 *    `operational_consequence_justification`. Diasersi atas KETIADAAN
 *    MEDANNYA, bukan atas nilainya null — medan yang kembali tanpa disadari
 *    akan lolos dari asersi "nilainya null", dan layar akan menumbuhkan dua
 *    sel yang tidak mungkin diisi siapa pun.
 *
 * 3. DUA PASANGAN BERBAGI STANDAR, BUKAN SATU: ripple_mill_1_amps ↔
 *    ripple_mill_2_amps dan kernel_silo_1_temp_c ↔ kernel_silo_2_temp_c.
 *    Depricarping hanya punya satu pasangan, jadi kode yang mengistimewakan
 *    SATU pasangan lolos di sana dan salah di sini.
 *
 * ────────────────────────────────────────────────────────────────────────
 * FIXTURE-NYA SENGAJA BERTENTANGAN DENGAN DIRINYA SENDIRI
 * ────────────────────────────────────────────────────────────────────────
 * Klaim pokok berkas ini adalah "NOL perhitungan di klien", dan klaim itu
 * hanya dapat dibuktikan oleh payload yang MUSTAHIL dihasilkan perhitungan:
 *
 *   - coverage_percent 88 padahal 12/288 = 4,2
 *   - metrics[0].avg 12,5 padahal min 10 dan max 11
 *   - metrics[0].filled_slot_count 2 padahal coverage.filled_slots 12
 *   - daily_total.filled_slot_count 99 padahal baris hariannya 3 + 4
 *   - downtime.avg_minutes_per_recorded_slot 99 padahal 60 / 3 = 20
 *
 * Repo yang menghitung sendiri PASTI gagal di sini. JANGAN "merapikan"
 * angka-angka ini.
 *
 * TANGGALNYA RELATIF, bukan kalender tetap — fixture bertanggal mati menjadi
 * basi tanpa satu pun test berubah warna.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }))

vi.mock('@/services/apiClient', () => ({
  default: { get: getMock },
}))

import kernelPlantReportRepo, {
  exportCsv,
  fetchPeriods,
  fetchSummary,
  saveCsvFile,
  type KernelPlantReportScope,
} from '@/services/kernelPlantReportRepo'

/* ------------------------------------------------------------------ */
/* Tanggal relatif                                                     */
/* ------------------------------------------------------------------ */

function isoDaysFromNow(offsetDays: number): string {
  return new Date(Date.now() + offsetDays * 86_400_000).toISOString().slice(0, 10)
}

const PERIOD_START = isoDaysFromNow(-30)
const PERIOD_END = isoDaysFromNow(-1)
const DAY_A = isoDaysFromNow(-26)
const DAY_B = isoDaysFromNow(-25)
const PREV_START = isoDaysFromNow(-60)
const PREV_END = isoDaysFromNow(-31)

/**
 * Cakupan yang membawa medan yang TIDAK ADA pada tipe KernelPlantReportScope.
 * Dipakai untuk membuktikan bahwa medan itu tidak punya jalur apa pun ke
 * kawat — bukan bahwa tipe menolaknya (tipe memang menolaknya, dan itu bukan
 * yang diuji di sini: tipe tidak berlaku pada JavaScript yang sudah
 * terkompilasi).
 */
function scopeWithForbiddenFields(productionLineId: string | null): KernelPlantReportScope {
  return {
    productionLineId,
    businessUnitId: 'bu-9',
    business_unit_id: 'bu-9',
    isAdmin: true,
  } as unknown as KernelPlantReportScope
}

/** Payload ringkasan yang sengaja tidak konsisten — lihat docblock berkas. */
function summaryPayload(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    business_unit: { id: 'bu-1', name: 'Mill Utara' },
    production_line: { id: 'pl-1', name: 'Line Satu' },
    period: {
      id: 'per-1',
      name: 'Periode Uji Kernel Plant',
      start_date: PERIOD_START,
      end_date: PERIOD_END,
      status: 'open',
    },
    has_data: true,
    coverage: {
      filled_slots: 12,
      expected_slots: 288,
      // 12/288 = 4,2 dan server mengirim 88. Repo yang menghitung sendiri
      // akan menjawab 4,2 dan test ini gagal.
      coverage_percent: 88,
      kernel_plant_count: 2,
      slots_per_kernel_plant_per_day: 24,
      days_in_period: 30,
      days_counted: 6,
      period_running: true,
    },
    metrics: [
      {
        column: 'ripple_mill_1_amps',
        label: 'Arus Ripple Mill 1',
        unit: 'Amps',
        min: 10,
        // Di luar rentang min..max — mustahil dari perhitungan apa pun.
        avg: 12.5,
        max: 11,
        // Jauh lebih kecil daripada coverage.filled_slots, dan itu benar:
        // tiap kolom punya penyebutnya sendiri.
        filled_slot_count: 2,
        target: {
          equipment_parameter: 'Ripple Mill (Cracker)',
          // DUA angka, DUA satuan, dan arah pembanding yang BERLAWANAN di
          // dalam satu sel — satu sel ini saja sudah mematahkan pengurai apa
          // pun, dan itulah sebabnya repo tidak membentuk kunci penilaian.
          target_benchmark: '20 - 25 Amps (Nut Breakage >95%)',
          corrective_action_plan: 'Adjust rotor-vane clearance if uncracked nut rate >5%.',
          // PASANGAN PERTAMA.
          shares_standard_with: ['ripple_mill_2_amps'],
        },
      },
      {
        column: 'ripple_mill_2_amps',
        label: 'Arus Ripple Mill 2',
        unit: 'Amps',
        // Angkanya SENGAJA jauh berbeda dari saudaranya: merata-ratakan
        // pasangan akan menyembunyikan ketidakseimbangan beban yang justru
        // menjadi alasan parameter ini diukur.
        min: 30,
        avg: 33,
        max: 36,
        filled_slot_count: 9,
        target: {
          equipment_parameter: 'Ripple Mill (Cracker)',
          target_benchmark: '20 - 25 Amps (Nut Breakage >95%)',
          corrective_action_plan: 'Adjust rotor-vane clearance if uncracked nut rate >5%.',
          shares_standard_with: ['ripple_mill_1_amps'],
        },
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
          // Master belum punya baris untuk parameter ini — ketiadaan itu
          // diteruskan, bukan ditambal.
          equipment_parameter: null,
          target_benchmark: null,
          corrective_action_plan: null,
          shares_standard_with: [],
        },
      },
      {
        column: 'kernel_silo_1_temp_c',
        label: 'Suhu Kernel Silo 1',
        // 'C' APA ADANYA dari server — tidak dipercantik menjadi '°C' di
        // klien, karena laporan web mencetak medan yang sama apa adanya.
        unit: 'C',
        min: 70,
        avg: 74,
        max: 80,
        filled_slot_count: 5,
        target: {
          equipment_parameter: 'Kernel Silo 1 & 2',
          // Menyebut ZONA yang tidak punya kolom sama sekali.
          target_benchmark: '70°C - 80°C (Top/Middle zones)',
          corrective_action_plan:
            'Check heater elements/steam valves if temperature drops below 65°C.',
          // PASANGAN KEDUA — yang tidak ada padanannya di Depricarping.
          shares_standard_with: ['kernel_silo_2_temp_c'],
        },
      },
      {
        column: 'kernel_silo_2_temp_c',
        label: 'Suhu Kernel Silo 2',
        unit: 'C',
        min: 55,
        avg: 58,
        max: 62,
        filled_slot_count: 3,
        target: {
          equipment_parameter: 'Kernel Silo 1 & 2',
          target_benchmark: '70°C - 80°C (Top/Middle zones)',
          corrective_action_plan:
            'Check heater elements/steam valves if temperature drops below 65°C.',
          shares_standard_with: ['kernel_silo_1_temp_c'],
        },
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
          // Mencampur BATAS dengan ALASAN batas itu ada, di dalam satu sel.
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
    ],
    /**
     * TEPAT SATU BARIS, dan itulah KEADAAN TENANGNYA — berlawanan dengan
     * Depricarping, yang daftarnya normalnya kosong. 'Final Kernel Dirt'
     * tidak punya kolom kadar kotoran DI MANA PUN pada skema ini.
     */
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
        // KEDUANYA string biasa, dan BERNILAI SAMA — bukan uuid dan bukan
        // kunci asing: tidak ada tabel master kernel_plants.
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
        // null, BUKAN 0 — unit yang tidak dicatat bukan unit yang tidak
        // pernah berhenti.
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
        // null, bukan 0 — hari ini tidak satu pun slotnya mencatat downtime.
        downtime_minutes: null,
        averages: { ripple_mill_1_amps: 100, claybath_hydro_sg: 22 },
      },
    ],
    daily_total: {
      // 3 + 4 = 7, dan server mengirim 99.
      filled_slot_count: 99,
      // 10 + null = 10, dan server mengirim 999.
      downtime_minutes: 999,
      // Bukan rata-rata dari 10 dan 100 dengan cara apa pun.
      averages: { ripple_mill_1_amps: 77.7, claybath_hydro_sg: 22 },
    },
    // OBJEK BERISI ANGKA, bukan daftar alasan teks seperti pada payload
    // Threshing/Pressing.
    downtime: {
      total_minutes: 60,
      recorded_slot_count: 3,
      // 60 / 3 = 20, dan server mengirim 99.
      avg_minutes_per_recorded_slot: 99,
      has_standard: false,
    },
    // Urutan SENGAJA tidak menurun menurut slot_count: repo tidak boleh
    // mengurutkan ulang.
    findings: [
      { finding: 'ripple mill bergetar', slot_count: 2 },
      { finding: 'Ripple mill bergetar', slot_count: 3 },
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

beforeEach(() => {
  getMock.mockReset()
})

/* ================================================================== */
/* business_unit_id — TIDAK PERNAH ADA DI KAWAT                        */
/* ================================================================== */

describe('business_unit_id tidak pernah dikirim', () => {
  it('fetchPeriods memanggil endpoint TANPA argumen kedua, apa pun yang diserahkan pemanggil', async () => {
    getMock.mockResolvedValue({ data: { data: [] } })

    // Pemanggil yang menyerahkan cakupan ala repo Depricarping. Di
    // JavaScript yang sudah terkompilasi tipe tidak berlaku, jadi inilah
    // bentuk pemanggilan yang benar-benar mungkin terjadi.
    const fetchPeriodsLoose = fetchPeriods as unknown as (scope?: unknown) => Promise<unknown>

    for (const scope of [
      undefined,
      {},
      { isAdmin: true, businessUnitId: 'bu-9' },
      { business_unit_id: 'bu-9' },
      { productionLineId: 'pl-1', businessUnitId: 'bu-9' },
    ]) {
      getMock.mockClear()
      await fetchPeriodsLoose(scope)

      expect(getMock.mock.calls[0][0]).toBe('/api/kernel-plant-reports/periods')
      // TIDAK ADA objek konfigurasi sama sekali — bukan `{ params: {} }`.
      // Periode adalah milik MILL, dan mill diselesaikan server dari akun.
      expect(getMock.mock.calls[0][1]).toBeUndefined()
    }
  })

  it('fetchSummary mengirim TEPAT period_id + production_line_id, tanpa medan mill', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    await fetchSummary('per-1', scopeWithForbiddenFields('pl-1'))

    // toEqual, bukan toMatchObject: kunci yang BERTAMBAH harus terlihat
    // sebagai kegagalan. Perlindungannya struktural (tidak ada jalurnya),
    // bukan penjagaan — jadi test inilah yang mencatat maksudnya.
    expect(getMock.mock.calls[0][1].params).toEqual({
      period_id: 'per-1',
      production_line_id: 'pl-1',
    })
    expect(Object.keys(getMock.mock.calls[0][1].params)).not.toContain('business_unit_id')
  })

  it('exportCsv mengirim TEPAT period_id + format + production_line_id, tanpa medan mill', async () => {
    getMock.mockResolvedValue({ data: new Blob(['a,b']) })

    await exportCsv('per-1', scopeWithForbiddenFields('pl-1'))

    expect(getMock.mock.calls[0][1].params).toEqual({
      period_id: 'per-1',
      format: 'csv',
      production_line_id: 'pl-1',
    })
  })

  it('tidak ada satu pun pemanggilan ke /business-units/options dari modul ini', async () => {
    getMock.mockResolvedValue({ data: { data: [] } })

    await fetchPeriods()
    getMock.mockResolvedValue({ data: summaryPayload() })
    await fetchSummary('per-1', { productionLineId: 'pl-1' })
    getMock.mockResolvedValue({ data: new Blob(['a']) })
    await exportCsv('per-1', { productionLineId: 'pl-1' })

    const urls = getMock.mock.calls.map((call) => String(call[0]))

    // Rute itu Admin saja (403 untuk kedua peran layar ini), dan menyerahkan
    // daftar seluruh mill kepada Operator adalah justru kebocoran yang
    // dihindari.
    expect(urls.some((url) => url.includes('business-unit'))).toBe(false)
    expect(urls).toEqual([
      '/api/kernel-plant-reports/periods',
      '/api/kernel-plant-reports/summary',
      '/api/kernel-plant-reports/export',
    ])
  })

  it('modul tidak mengekspor fungsi pengambil daftar mill sama sekali', async () => {
    const module = await import('@/services/kernelPlantReportRepo')

    // Ketiadaan FUNGSINYA, bukan ketiadaan pemanggilannya: fungsi yang ada
    // akan dipakai seseorang.
    expect(Object.keys(module)).not.toContain('fetchBusinessUnits')
    expect(Object.keys(kernelPlantReportRepo)).not.toContain('fetchBusinessUnits')
  })
})

/* ================================================================== */
/* production_line_id                                                  */
/* ================================================================== */

describe('productionLineParams', () => {
  it('dikirim ke summary dan export, TIDAK ke periods', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })
    await fetchSummary('per-1', { productionLineId: 'pl-1' })
    expect(getMock.mock.calls[0][1].params).toMatchObject({ production_line_id: 'pl-1' })

    getMock.mockReset()
    getMock.mockResolvedValue({ data: new Blob(['a']) })
    await exportCsv('per-1', { productionLineId: 'pl-1' })
    expect(getMock.mock.calls[0][1].params).toMatchObject({ production_line_id: 'pl-1' })

    getMock.mockReset()
    getMock.mockResolvedValue({ data: { data: [] } })
    await fetchPeriods()
    // Periode adalah milik MILL, bukan milik Production Line — menyaringnya
    // per line akan mengarang penyempitan yang tidak ada di data.
    expect(getMock.mock.calls[0][1]).toBeUndefined()
  })

  it('tidak mengirim parameter kosong ketika line belum berlaku', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    await fetchSummary('per-1', { productionLineId: null })

    // Server akan menjawab 422 tentang parameter yang HILANG, dan itu pesan
    // yang benar — bukan 422 tentang nilai kosong.
    expect(getMock.mock.calls[0][1].params).toEqual({ period_id: 'per-1' })
  })

  it('cakupan yang tidak diberikan sama sekali tetap menghasilkan permintaan yang sah', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    await fetchSummary('per-1')

    expect(getMock.mock.calls[0][1].params).toEqual({ period_id: 'per-1' })
  })
})

/* ================================================================== */
/* Pembungkus respons                                                  */
/* ================================================================== */

describe('unwrap', () => {
  it('menerima respons dengan maupun tanpa pembungkus data', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })
    const flat = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    getMock.mockReset()
    getMock.mockResolvedValue({ data: { data: summaryPayload() } })
    const wrapped = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(wrapped).toEqual(flat)
  })

  it('respons kosong tidak melempar dan menghasilkan blok bawaan', async () => {
    getMock.mockResolvedValue({ data: null })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.has_data).toBe(false)
    expect(summary.metrics).toEqual([])
    expect(summary.coverage.coverage_percent).toBeNull()
    expect(summary.coverage.filled_slots).toBe(0)
    expect(summary.daily_total).toEqual({
      filled_slot_count: 0,
      // null, BUKAN 0 — bentuk bawaan yang berbohong lebih buruk daripada
      // bentuk bawaan yang kosong.
      downtime_minutes: null,
      averages: {},
    })
    expect(summary.downtime).toEqual({
      total_minutes: null,
      recorded_slot_count: 0,
      avg_minutes_per_recorded_slot: null,
      has_standard: false,
    })
    expect(summary.findings).toEqual([])
    expect(summary.by_kernel_plant).toEqual([])
    expect(summary.all_targets_measured).toBe(false)
  })
})

/* ================================================================== */
/* Kesetiaan terusan — NOL perhitungan di klien                        */
/* ================================================================== */

describe('nol perhitungan di klien', () => {
  it('meneruskan coverage_percent apa adanya walau bertentangan dengan pembilang/penyebutnya', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // 12/288 = 4,2. Repo yang menghitung sendiri menjawab 4,2 dan gagal.
    expect(summary.coverage.coverage_percent).toBe(88)
    expect(summary.coverage.filled_slots).toBe(12)
    expect(summary.coverage.expected_slots).toBe(288)
    expect(summary.coverage.kernel_plant_count).toBe(2)
    expect(summary.coverage.slots_per_kernel_plant_per_day).toBe(24)
    expect(summary.coverage.days_counted).toBe(6)
  })

  it('meneruskan min/avg/max apa adanya walau avg di luar rentang min..max', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const metric = (await fetchSummary('per-1', { productionLineId: 'pl-1' })).metrics[0]

    expect(metric.min).toBe(10)
    expect(metric.avg).toBe(12.5)
    expect(metric.max).toBe(11)
  })

  it('meneruskan KETUJUH penyebut per metrik apa adanya, tanpa menyelaraskannya dengan filled_slots', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Ketujuhnya berbeda, dan tidak satu pun sama dengan coverage.filled_slots
    // — tiap kolom punya penyebutnya sendiri. Repo tidak boleh "memperbaiki"
    // selisih ini.
    expect(summary.metrics.map((metric) => metric.filled_slot_count)).toEqual([
      2, 9, 0, 5, 3, 7, 1,
    ])
    expect(summary.coverage.filled_slots).toBe(12)
  })

  it('coverage.filled_slots BOLEH melampaui setiap penyebut kartu, dan itu benar', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Slot dihitung terisi bila salah satu dari SEMBILAN kolom bacaan terisi,
    // dan dua di antaranya (downtime_minutes, findings) bukan kolom ukur.
    // Yang dilampaui adalah N, BUKAN M — bukan ketidaksesuaian yang perlu
    // "diperbaiki".
    for (const metric of summary.metrics) {
      expect(metric.filled_slot_count).toBeLessThan(summary.coverage.filled_slots)
    }
  })

  it('meneruskan daily_total apa adanya, tanpa menurunkannya dari daily', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // 3 + 4 = 7, dan server mengirim 99.
    expect(summary.daily_total.filled_slot_count).toBe(99)
    expect(summary.daily_total.averages.ripple_mill_1_amps).toBe(77.7)
    expect(summary.daily[0].averages.ripple_mill_1_amps).toBe(10)
    expect(summary.daily[1].averages.ripple_mill_1_amps).toBe(100)
  })

  it('null tetap null, tidak pernah menjadi 0', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.metrics[2].min).toBeNull()
    expect(summary.metrics[2].avg).toBeNull()
    expect(summary.metrics[2].max).toBeNull()
    expect(summary.by_kernel_plant[0].averages.claybath_hydro_sg).toBeNull()
    expect(summary.by_kernel_plant[1].downtime_minutes).toBeNull()
    expect(summary.daily[1].downtime_minutes).toBeNull()
  })

  it('coverage_percent null diteruskan null, bukan diganti 0', async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({
        coverage: {
          filled_slots: 0,
          expected_slots: 0,
          coverage_percent: null,
          kernel_plant_count: 0,
          slots_per_kernel_plant_per_day: 24,
          days_in_period: 30,
          days_counted: 0,
          period_running: true,
        },
      }),
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // 0% mengklaim ada yang diukur dan hasilnya nol; null berarti penyebutnya
    // tidak terbentuk. Dua fakta yang berbeda.
    expect(summary.coverage.coverage_percent).toBeNull()
  })

  it('coverage_percent 0 diteruskan 0, bukan diganti null', async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({
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
      }),
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Penyebutnya TERBENTUK, dan yang terukur memang nol slot. Draf pertama
    // spec layar web menyatakan ini terbalik dan sudah dikoreksi.
    expect(summary.coverage.coverage_percent).toBe(0)
  })
})

/* ================================================================== */
/* Standar operasional — TIGA MEDAN ISI, BUKAN EMPAT                   */
/* ================================================================== */

describe('standar operasional', () => {
  it('blok target membawa TEPAT empat kunci — nama milik master Kernel Plant', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const metrics = (await fetchSummary('per-1', { productionLineId: 'pl-1' })).metrics

    // Menyalin nama dari repo Threshing / Pressing / Depricarping
    // menghasilkan blok target yang SELURUHNYA null tanpa satu pun galat
    // TypeScript — nama-nama itu memang bukan properti yang ada, dan optional
    // chaining akan menelannya. Karena itu nama-nama ini diasersi EKSPLISIT.
    expect(metrics[0].target.equipment_parameter).toBe('Ripple Mill (Cracker)')
    expect(metrics[0].target.target_benchmark).toBe('20 - 25 Amps (Nut Breakage >95%)')
    expect(metrics[0].target.corrective_action_plan)
      .toBe('Adjust rotor-vane clearance if uncracked nut rate >5%.')

    for (const metric of metrics) {
      expect(Object.keys(metric.target).sort()).toEqual([
        'corrective_action_plan',
        'equipment_parameter',
        'shares_standard_with',
        'target_benchmark',
      ])
    }
  })

  it('TIDAK ADA critical_limit dan TIDAK ADA operational_consequence_justification', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Diasersi atas KETIADAAN MEDANNYA pada SETIAP metrik, bukan atas
    // nilainya null: medan yang kembali tanpa disadari akan lolos dari asersi
    // "nilainya null", dan layar akan menumbuhkan dua sel yang tidak mungkin
    // diisi siapa pun. kernel_plant_operational_targets tidak punya kolom itu.
    for (const metric of summary.metrics) {
      expect(metric.target).not.toHaveProperty('critical_limit')
      expect(metric.target).not.toHaveProperty('operational_consequence_justification')
      // Dan tidak pula nama-nama milik ketiga master saudaranya.
      expect(metric.target).not.toHaveProperty('parameter_metric')
      expect(metric.target).not.toHaveProperty('target_range')
      expect(metric.target).not.toHaveProperty('parameter')
      expect(metric.target).not.toHaveProperty('standard_operational_target')
    }

    // Termasuk pada daftar standar tanpa pengukuran, yang memakai nama medan
    // yang SAMA dengan blok target.
    for (const row of summary.targets_without_metric) {
      expect(Object.keys(row).sort()).toEqual([
        'corrective_action_plan',
        'equipment_parameter',
        'reason',
        'target_benchmark',
      ])
    }
  })

  it('meneruskan KETUJUH metrik dalam urutan payload', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const metrics = (await fetchSummary('per-1', { productionLineId: 'pl-1' })).metrics

    expect(metrics.map((metric) => metric.column)).toEqual([
      'ripple_mill_1_amps',
      'ripple_mill_2_amps',
      'claybath_hydro_sg',
      'kernel_silo_1_temp_c',
      'kernel_silo_2_temp_c',
      'kernel_moisture_percent',
      'shell_loss_percent',
    ])
  })

  it('DUA pasangan berbagi standar, bukan satu — dan tiga metrik sisanya kosong', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const metrics = (await fetchSummary('per-1', { productionLineId: 'pl-1' })).metrics
    const byColumn = new Map(metrics.map((metric) => [metric.column, metric]))

    // PASANGAN PERTAMA — 'Ripple Mill (Cracker)'.
    expect(byColumn.get('ripple_mill_1_amps')?.target.shares_standard_with)
      .toEqual(['ripple_mill_2_amps'])
    expect(byColumn.get('ripple_mill_2_amps')?.target.shares_standard_with)
      .toEqual(['ripple_mill_1_amps'])

    // PASANGAN KEDUA — 'Kernel Silo 1 & 2'. Depricarping hanya punya SATU
    // pasangan, jadi kode yang mengistimewakan satu pasangan lolos di sana
    // dan SALAH di sini.
    expect(byColumn.get('kernel_silo_1_temp_c')?.target.shares_standard_with)
      .toEqual(['kernel_silo_2_temp_c'])
    expect(byColumn.get('kernel_silo_2_temp_c')?.target.shares_standard_with)
      .toEqual(['kernel_silo_1_temp_c'])

    // Standar KEDUA anggota pasangan memang sama persis — itulah yang perlu
    // dinyatakan layar.
    expect(byColumn.get('ripple_mill_1_amps')?.target.equipment_parameter)
      .toBe(byColumn.get('ripple_mill_2_amps')?.target.equipment_parameter)
    expect(byColumn.get('kernel_silo_1_temp_c')?.target.target_benchmark)
      .toBe(byColumn.get('kernel_silo_2_temp_c')?.target.target_benchmark)

    // Dan KOSONG — bukan undefined — pada ketiga metrik lain: layar
    // memeriksanya dengan .length > 0.
    for (const column of ['claybath_hydro_sg', 'kernel_moisture_percent', 'shell_loss_percent']) {
      expect(byColumn.get(column)?.target.shares_standard_with).toEqual([])
    }

    // EMPAT kartu membawa keterangan, TIGA tidak.
    expect(metrics.filter((metric) => metric.target.shares_standard_with.length > 0))
      .toHaveLength(4)
  })

  it('angka kedua anggota pasangan TIDAK dirata-ratakan menjadi satu', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const metrics = (await fetchSummary('per-1', { productionLineId: 'pl-1' })).metrics
    const byColumn = new Map(metrics.map((metric) => [metric.column, metric]))

    // Empat mesin fisik. Rata-rata gabungan (22,75 dan 66) akan menyembunyikan
    // ketidakseimbangan beban yang justru menjadi alasan parameter itu diukur.
    expect(byColumn.get('ripple_mill_1_amps')?.avg).toBe(12.5)
    expect(byColumn.get('ripple_mill_2_amps')?.avg).toBe(33)
    expect(byColumn.get('kernel_silo_1_temp_c')?.avg).toBe(74)
    expect(byColumn.get('kernel_silo_2_temp_c')?.avg).toBe(58)

    // Dan penyebutnya pun tetap terpisah.
    expect(byColumn.get('kernel_silo_1_temp_c')?.filled_slot_count).toBe(5)
    expect(byColumn.get('kernel_silo_2_temp_c')?.filled_slot_count).toBe(3)
  })

  it('equipment_parameter null diteruskan null, bukan ditambal', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const claybath = (await fetchSummary('per-1', { productionLineId: 'pl-1' })).metrics[2]

    // Keadaan ini BUKAN teoretis: nama parameter pada master dapat disunting
    // sehingga tak lagi cocok dengan peta kolom. Ketiadaannya diteruskan
    // supaya layar dapat MENCABANGKAN PERNYATAANNYA, bukan sekadar
    // menyediakan nilai cadangan.
    expect(claybath.target.equipment_parameter).toBeNull()
    expect(claybath.target.target_benchmark).toBeNull()
    expect(claybath.target.corrective_action_plan).toBeNull()
  })

  it('targets_without_metric normalnya TEPAT SATU baris: Final Kernel Dirt', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // KEADAAN TENANG layar ini — berlawanan dengan Depricarping, yang
    // daftarnya normalnya kosong. Daftar yang berisi di sini BUKAN tanda ada
    // yang rusak.
    expect(summary.targets_without_metric).toHaveLength(1)
    expect(summary.targets_without_metric[0].equipment_parameter).toBe('Final Kernel Dirt')
    expect(summary.targets_without_metric[0].target_benchmark)
      .toBe('≤ 6.0% (Standard quality premium)')
    expect(summary.targets_without_metric[0].corrective_action_plan)
      .toBe('Clean winnowing ducts or re-calibrate hydrocyclone settings.')
    expect(summary.targets_without_metric[0].reason).toBe('no_column')
    expect(summary.all_targets_measured).toBe(false)
    expect(summary.targets_master_empty).toBe(false)
  })

  it('TIDAK PERNAH membentuk satu pun kunci penilaian terhadap standar', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })
    const flat = JSON.stringify(summary)

    // Standar di stasiun ini adalah PROSA — satu sel memuat dua angka dengan
    // satuan berbeda dan arah pembanding berlawanan. Mengubahnya menjadi
    // pembanding berarti mengarang batas yang tidak pernah ditetapkan
    // siapa pun.
    for (const forbidden of [
      'severity',
      'is_out_of_range',
      'out_of_range',
      '"flag"',
      'threshold',
      'breach',
    ]) {
      expect(flat, forbidden).not.toContain(forbidden)
    }

    // Dan daftar kuncinya dikunci: kunci yang hilang atau bertambah terlihat
    // sebagai kegagalan, bukan sebagai bagian layar yang diam-diam kosong.
    expect(Object.keys(summary).sort()).toEqual([
      'all_targets_measured',
      'business_unit',
      'by_kernel_plant',
      'coverage',
      'daily',
      'daily_total',
      'downtime',
      'findings',
      'has_data',
      'metrics',
      'period',
      'production_line',
      'targets_master_empty',
      'targets_without_metric',
      'total',
    ])
  })
})

/* ================================================================== */
/* Baris rekap per unit kernel plant                                   */
/* ================================================================== */

describe('rekap per unit kernel plant', () => {
  it('kernel_plant_id dan kernel_plant_name keduanya string biasa dan bernilai sama', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const rows = (await fetchSummary('per-1', { productionLineId: 'pl-1' })).by_kernel_plant

    // Tidak ada tabel master kernel_plants: id itu TEKS yang diketik operator
    // di layar input, dan name bernilai sama dengannya. Menamainya presser_id,
    // atau memperlakukannya sebagai id yang dapat ditelusuri ke sebuah baris,
    // keduanya salah.
    for (const row of rows) {
      expect(typeof row.kernel_plant_id).toBe('string')
      expect(typeof row.kernel_plant_name).toBe('string')
      expect(row.kernel_plant_name).toBe(row.kernel_plant_id)
      expect(row.kernel_plant_id).not.toMatch(
        /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i,
      )
    }

    expect(rows.map((row) => row.kernel_plant_id)).toEqual(['KP-1', 'KP-2'])
    expect(rows.map((row) => row.day_count)).toEqual([2, 1])
    expect(rows.map((row) => row.filled_slot_count)).toEqual([7, 5])
    expect(Object.keys(rows[0]).sort()).toEqual([
      'averages',
      'day_count',
      'downtime_minutes',
      'filled_slot_count',
      'kernel_plant_id',
      'kernel_plant_name',
    ])
  })
})

/* ================================================================== */
/* Downtime & temuan                                                   */
/* ================================================================== */

describe('downtime dan temuan', () => {
  it('meneruskan blok downtime sebagai OBJEK tanpa menghitung apa pun', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // OBJEK, bukan daftar: menyalin tipe DowntimeRow dari repo Threshing /
    // Pressing ke sini menghasilkan undefined di mana-mana.
    expect(Array.isArray(summary.downtime)).toBe(false)
    expect(summary.downtime.total_minutes).toBe(60)
    expect(summary.downtime.recorded_slot_count).toBe(3)
    // 60 / 3 = 20, dan payload mengirim 99.
    expect(summary.downtime.avg_minutes_per_recorded_slot).toBe(99)
    // Selalu false — downtime_minutes tidak punya baris pada master target.
    expect(summary.downtime.has_standard).toBe(false)
  })

  it('downtime.total_minutes null diteruskan sebagai null, bukan 0', async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({
        downtime: {
          total_minutes: null,
          recorded_slot_count: 0,
          avg_minutes_per_recorded_slot: null,
          has_standard: false,
        },
      }),
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // 0 akan membuat view mencetak "0 menit", yang terbaca seperti "stasiun
    // tidak pernah berhenti" padahal yang benar adalah "tidak ada yang
    // mencatatnya".
    expect(summary.downtime.total_minutes).toBeNull()
    expect(summary.downtime.avg_minutes_per_recorded_slot).toBeNull()
  })

  it('meneruskan downtime_minutes per baris rekap, null maupun angka, tanpa menjumlahkan', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.by_kernel_plant[0].downtime_minutes).toBe(30)
    expect(summary.by_kernel_plant[1].downtime_minutes).toBeNull()
    expect(summary.daily[0].downtime_minutes).toBe(10)
    expect(summary.daily[1].downtime_minutes).toBeNull()
    // 10 + null = 10, dan payload mengirim 999.
    expect(summary.daily_total.downtime_minutes).toBe(999)
  })

  it('temuan berkunci `finding` dan diteruskan harfiah, tanpa normalisasi', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Kuncinya `finding`, BUKAN `reason` seperti pada repo Threshing /
    // Pressing. Normalisasi di klien akan menggabungkan hal yang penulisnya
    // memang maksudkan berbeda, DAN membuat layar ini berselisih dengan
    // laporan web yang memakai sumber yang sama.
    expect(summary.findings).toEqual([
      { finding: 'ripple mill bergetar', slot_count: 2 },
      { finding: 'Ripple mill bergetar', slot_count: 3 },
    ])
  })

  it('TIDAK mengurutkan ulang findings: urutan server dipertahankan apa adanya', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Urutan payload SENGAJA tidak menurun menurut slot_count (2 lalu 3).
    // Repo yang mengurutkan sendiri akan membalikkannya dan test ini gagal.
    expect(summary.findings.map((row) => row.slot_count)).toEqual([2, 3])
  })

  it('tidak mengurutkan ulang daftar yang sudah diurutkan server', async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({
        findings: [
          { finding: 'Zebra', slot_count: 9 },
          { finding: 'Alfa', slot_count: 1 },
        ],
      }),
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.findings.map((row) => row.finding)).toEqual(['Zebra', 'Alfa'])
  })
})

/* ================================================================== */
/* Daftar periode                                                      */
/* ================================================================== */

describe('daftar periode', () => {
  it('meneruskan daftar apa adanya, termasuk periode tertutup, tanpa business_unit_name', async () => {
    const options = [
      {
        id: 'per-1',
        name: 'Periode Uji Kernel Plant',
        start_date: PERIOD_START,
        end_date: PERIOD_END,
        status: 'open',
        // DENGAN TANDA HUBUNG. Garis bawah di situ tidak akan cocok dengan
        // satu baris pun dan server menjawab [] tanpa galat.
        station_type: 'kernel-plant',
        station_type_label: 'Kernel Plant',
      },
      {
        id: 'per-0',
        name: 'Periode Sebelumnya',
        start_date: PREV_START,
        end_date: PREV_END,
        status: 'closed',
        station_type: 'kernel-plant',
        station_type_label: 'Kernel Plant',
      },
    ]

    getMock.mockResolvedValue({ data: { data: options } })

    const periods = await fetchPeriods()

    // Status mengatur penulisan data, bukan pembacaan laporan.
    expect(periods.map((period) => period.status)).toEqual(['open', 'closed'])
    expect(periods.map((period) => period.station_type)).toEqual(['kernel-plant', 'kernel-plant'])
    expect(getMock.mock.calls[0][0]).toBe('/api/kernel-plant-reports/periods')

    // /periods TIDAK membawa nama mill — nama mill dibaca view dari
    // summary.business_unit.name, satu sumber saja supaya tidak ada dua
    // tempat yang dapat menyimpang.
    for (const period of periods) {
      expect(period).not.toHaveProperty('business_unit_name')
      expect(Object.keys(period).sort()).toEqual([
        'end_date',
        'id',
        'name',
        'start_date',
        'station_type',
        'station_type_label',
        'status',
      ])
    }
  })

  it('daftar periode kosong adalah jawaban yang sah, bukan galat', async () => {
    getMock.mockResolvedValue({ data: { data: [] } })

    await expect(fetchPeriods()).resolves.toEqual([])
  })

  it('respons tanpa pembungkus data menghasilkan daftar kosong, bukan lemparan', async () => {
    getMock.mockResolvedValue({ data: {} })

    await expect(fetchPeriods()).resolves.toEqual([])
  })
})

/* ================================================================== */
/* Ekspor — CSV SAJA                                                   */
/* ================================================================== */

describe('ekspor', () => {
  it('meminta CSV sebagai blob untuk periode dan line yang diberikan', async () => {
    getMock.mockResolvedValue({ data: new Blob(['a,b']) })

    await exportCsv('per-1', { productionLineId: 'pl-1' })

    expect(getMock.mock.calls[0][0]).toBe('/api/kernel-plant-reports/export')
    expect(getMock.mock.calls[0][1]).toMatchObject({
      params: { period_id: 'per-1', format: 'csv', production_line_id: 'pl-1' },
      // text/csv streaming, BUKAN JSON.
      responseType: 'blob',
    })
  })

  it('format yang diminta selalu csv — tidak ada jalur xlsx di modul ini', async () => {
    getMock.mockResolvedValue({ data: new Blob(['a,b']) })

    await exportCsv('per-1', { productionLineId: 'pl-1' })

    // Ekspor .xlsx hanya ada di layar web — bukan karena endpoint-nya tidak
    // mendukungnya (ia mendukung), melainkan karena menyimpan dan membukanya
    // di WebView ponsel menuntut penanganan berkas native yang belum ada.
    expect(getMock.mock.calls[0][1].params.format).toBe('csv')
    expect(JSON.stringify(getMock.mock.calls[0][1])).not.toContain('xlsx')
  })

  it('saveCsvFile menyimpan blob dengan nama yang diberikan', () => {
    const createObjectURL = vi.fn(() => 'blob:x')
    const revokeObjectURL = vi.fn()

    vi.stubGlobal('URL', { createObjectURL, revokeObjectURL })

    const click = vi.fn()
    const anchor = document.createElement('a')

    anchor.click = click
    vi.spyOn(document, 'createElement').mockReturnValueOnce(anchor)

    saveCsvFile(new Blob(['a,b']), 'laporan-kernel-plant_periode.csv')

    expect(anchor.download).toBe('laporan-kernel-plant_periode.csv')
    expect(click).toHaveBeenCalledOnce()
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:x')

    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })
})

/* ================================================================== */
/* Galat                                                               */
/* ================================================================== */

describe('galat diteruskan apa adanya', () => {
  it('galat tanpa status dan galat ber-status keduanya diteruskan dengan bentuk aslinya', async () => {
    getMock.mockRejectedValueOnce({ message: 'Tidak dapat terhubung ke server.' })

    await expect(fetchSummary('per-1', { productionLineId: 'pl-1' }))
      .rejects.toEqual({ message: 'Tidak dapat terhubung ke server.' })

    getMock.mockRejectedValueOnce({ message: 'Unauthenticated.', status: 401 })

    // Ketiadaan `status` itulah yang membedakan "jaringan putus" dari "server
    // menjawab", dan urutan cabang handleError di view bergantung padanya.
    await expect(fetchSummary('per-1', { productionLineId: 'pl-1' }))
      .rejects.toEqual({ message: 'Unauthenticated.', status: 401 })
  })

  it('galat /periods diteruskan, tidak diganti daftar kosong', async () => {
    getMock.mockRejectedValueOnce({ message: 'Server error.', status: 500 })

    await expect(fetchPeriods()).rejects.toEqual({ message: 'Server error.', status: 500 })
  })
})

describe('bentuk modul', () => {
  it('mengekspor tepat keempat fungsi yang dipakai view, seluruhnya pembacaan', () => {
    // EMPAT, bukan lima: tidak ada fetchBusinessUnits, karena kedua aktor
    // layar ini terikat satu mill dan rute itu Admin saja.
    expect(Object.keys(kernelPlantReportRepo).sort()).toEqual([
      'exportCsv',
      'fetchPeriods',
      'fetchSummary',
      'saveCsvFile',
    ])
    expect(
      Object.keys(kernelPlantReportRepo).every((key) => /^fetch|^export|^save/.test(key)),
    ).toBe(true)
  })
})
