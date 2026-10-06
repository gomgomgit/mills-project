/**
 * depricarpingReportRepo.spec.ts — screen-151--laporan-depricarping-mobile /
 * usecase-152--laporan-depricarping-mobile.
 *
 * Satu test per unit_test_cases pada tech spec screen-151. Yang diuji di sini
 * adalah BENTUK PERMINTAAN dan KESETIAAN TERUSAN — bukan kebenaran angkanya,
 * yang sudah dibuktikan 46 kali terhadap DepricarpingReportService di backend.
 *
 * ────────────────────────────────────────────────────────────────────────
 * FIXTURE-NYA SENGAJA BERTENTANGAN DENGAN DIRINYA SENDIRI
 * ────────────────────────────────────────────────────────────────────────
 * Klaim pokok berkas ini adalah "NOL perhitungan di klien", dan klaim itu
 * hanya dapat dibuktikan oleh payload yang MUSTAHIL dihasilkan perhitungan:
 *
 *   - coverage_percent 88 padahal 96/480 = 20
 *   - metrics[0].avg 12,5 padahal min 10 dan max 11
 *   - metrics[0].filled_slot_count 2 padahal coverage.filled_slots 96
 *   - daily_total.averages tidak sama dengan rata-rata baris harian mana pun
 *   - daily_total.filled_slot_count 99 padahal baris hariannya 3 + 4
 *
 * Repo yang menghitung sendiri PASTI gagal di sini. Dengan payload yang
 * konsisten, separuh berkas ini menjadi test yang selalu hijau.
 *
 * JANGAN "merapikan" angka-angka ini.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }))

vi.mock('@/services/apiClient', () => ({
  default: { get: getMock },
}))

import depricarpingReportRepo, {
  exportCsv,
  fetchBusinessUnits,
  fetchPeriods,
  fetchSummary,
  saveCsvFile,
} from '@/services/depricarpingReportRepo'

/** Payload ringkasan yang sengaja tidak konsisten — lihat docblock berkas. */
function summaryPayload(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    business_unit: { id: 'bu-1', name: 'Mill Utara' },
    production_line: { id: 'pl-1', name: 'Line Satu' },
    period: {
      id: 'per-1',
      name: 'Periode September 2026',
      start_date: '2026-09-01',
      end_date: '2026-09-30',
      status: 'open',
    },
    has_data: true,
    coverage: {
      filled_slots: 96,
      expected_slots: 480,
      // 96/480 = 20, dan server mengirim 88. Repo yang menghitung sendiri
      // akan menjawab 20 dan test ini gagal.
      coverage_percent: 88,
      presser_count: 2,
      slots_per_presser_per_day: 24,
      days_in_period: 30,
      days_counted: 10,
      period_running: true,
    },
    metrics: [
      {
        column: 'fan_static_pressure_mmh2o',
        label: 'Tekanan Statis Fan',
        unit: 'mmH2O',
        min: 10,
        // Di luar rentang min..max — mustahil dari perhitungan apa pun.
        avg: 12.5,
        max: 11,
        // Jauh lebih kecil daripada coverage.filled_slots, dan itu benar:
        // tiap kolom punya penyebutnya sendiri.
        filled_slot_count: 2,
        target: {
          parameter_metric: 'Fan Static Pressure',
          target_range: '40 - 50 mmH2O',
          critical_limit: '< 35 or > 55 mmH2O',
          operational_consequence_justification: 'Low pressure drops fibre early (heavy losses).',
          shares_standard_with: [],
          unmapped_reason: null,
        },
      },
      {
        column: 'polishing_drum_speed_rpm',
        label: 'Putaran Polishing Drum',
        unit: 'RPM',
        min: null,
        avg: null,
        max: null,
        filled_slot_count: 0,
        target: {
          parameter_metric: null,
          target_range: null,
          critical_limit: null,
          operational_consequence_justification: null,
          shares_standard_with: [],
          unmapped_reason: null,
        },
      },
      {
        column: 'air_velocity_ms',
        label: 'Kecepatan Udara Aspirator',
        unit: 'm/s',
        min: 12,
        avg: 13,
        max: 14,
        filled_slot_count: 9,
        target: {
          parameter_metric: 'Air Velocity (Aspirator)',
          target_range: '12 - 14 m/s',
          critical_limit: '< 10 or > 16 m/s',
          operational_consequence_justification: 'Controls the pneumatic separation gap.',
          shares_standard_with: [],
          unmapped_reason: null,
        },
      },
      {
        column: 'fibre_moisture_percent',
        label: 'Kadar Air Fibre',
        unit: '%',
        min: 33,
        avg: 35,
        max: 37,
        filled_slot_count: 7,
        target: {
          parameter_metric: 'Fibre Moisture Content',
          target_range: '33% - 37%',
          critical_limit: '> 40%',
          operational_consequence_justification: 'High moisture reduces boiler efficiency.',
          shares_standard_with: [],
          unmapped_reason: null,
        },
      },
      {
        // SENGAJA TIDAK DIPETAKAN ke standar mana pun: master menyebut
        // standarnya "Kernel Loss in Fibre" (sebuah KEHILANGAN, target
        // "< 0.50%") sementara kolom ini dan keempat layar input Depricarping
        // melabelinya PEROLEHAN. Repo harus meneruskan unmapped_reason apa
        // adanya, BUKAN menyimpulkannya dari nama kolom.
        column: 'kernel_recovery_in_fibre_percent',
        label: 'Kernel Recovery in Fibre',
        unit: '%',
        min: 0.3,
        avg: 0.5,
        max: 0.7,
        filled_slot_count: 1,
        target: {
          parameter_metric: null,
          target_range: null,
          critical_limit: null,
          operational_consequence_justification: null,
          shares_standard_with: [],
          unmapped_reason: 'direction_unresolved',
        },
      },
      {
        // SATU STANDAR, DUA KOLOM — kedua silo saling menyebut, dan standar
        // masternya sama persis. Angkanya BERBEDA: merata-ratakannya akan
        // menyembunyikan silo yang menyimpang di belakang silo yang normal.
        column: 'nut_silo_1_temp_c',
        label: 'Suhu Nut Silo 1',
        unit: 'C',
        min: 60,
        avg: 65,
        max: 70,
        filled_slot_count: 5,
        target: {
          parameter_metric: 'Nut Silo Temperature',
          target_range: '60C - 70C',
          critical_limit: '< 55C or > 75C',
          operational_consequence_justification: 'Crucial for nut conditioning.',
          shares_standard_with: ['nut_silo_2_temp_c'],
          unmapped_reason: null,
        },
      },
      {
        column: 'nut_silo_2_temp_c',
        label: 'Suhu Nut Silo 2',
        unit: 'C',
        min: 80,
        avg: 85,
        max: 90,
        filled_slot_count: 3,
        target: {
          parameter_metric: 'Nut Silo Temperature',
          target_range: '60C - 70C',
          critical_limit: '< 55C or > 75C',
          operational_consequence_justification: 'Crucial for nut conditioning.',
          shares_standard_with: ['nut_silo_1_temp_c'],
          unmapped_reason: null,
        },
      },
    ],
    // SATU entri, bukan dua seperti pada Pressing — dan ALASANNYA yang
    // membedakan: di sini ada kolom yang namanya mirip tetapi arahnya
    // berlawanan, bukan "tidak ada kolomnya".
    targets_without_metric: [
      {
        parameter_metric: 'Kernel Loss in Fibre',
        target_range: '< 0.50%',
        critical_limit: '> 1.00%',
        operational_consequence_justification: 'Direct operational revenue loss.',
        reason: 'direction_unresolved',
      },
    ],
    all_targets_measured: false,
    targets_master_empty: false,
    by_presser: [
      {
        presser_id: 'PR-1',
        day_count: 2,
        filled_slot_count: 7,
        downtime_minutes: 30,
        averages: { fan_static_pressure_mmh2o: 30, polishing_drum_speed_rpm: null },
      },
    ],
    daily: [
      {
        date: '2026-09-04',
        filled_slot_count: 3,
        downtime_minutes: 10,
        averages: { fan_static_pressure_mmh2o: 10, polishing_drum_speed_rpm: null },
      },
      {
        date: '2026-09-05',
        filled_slot_count: 4,
        // null, bukan 0 — hari ini tidak satu pun slotnya mencatat downtime.
        downtime_minutes: null,
        averages: { fan_static_pressure_mmh2o: 100, polishing_drum_speed_rpm: 22 },
      },
    ],
    daily_total: {
      // 3 + 4 = 7, dan server mengirim 99.
      filled_slot_count: 99,
      // 10 + null = 10, dan server mengirim 999. Repo yang menjumlahkan baris
      // harian akan menjawab 10 dan test ini gagal.
      downtime_minutes: 999,
      // Bukan rata-rata dari 10 dan 100 dengan cara apa pun.
      averages: { fan_static_pressure_mmh2o: 77.7, polishing_drum_speed_rpm: 22 },
    },
    // OBJEK, bukan daftar alasan seperti pada payload Threshing/Pressing.
    downtime: {
      total_minutes: 60,
      recorded_slot_count: 3,
      // 60 / 3 = 20, dan server mengirim 99. Repo yang membagi sendiri akan
      // menjawab 20 dan test ini gagal.
      avg_minutes_per_recorded_slot: 99,
      has_standard: false,
    },
    // Urutan SENGAJA tidak menurun menurut slot_count: repo tidak boleh
    // mengurutkan ulang.
    findings: [
      { finding: 'belt kendur', slot_count: 2 },
      { finding: 'Belt kendur', slot_count: 3 },
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
/* Bentuk permintaan                                                   */
/* ================================================================== */

describe('scopeParams', () => {
  it('TIDAK PERNAH mengirim business_unit_id untuk ketiga peran terikat mill', async () => {
    getMock.mockResolvedValue({ data: { data: [] } })

    for (const scope of [
      { isAdmin: false, businessUnitId: 'bu-9' },
      { businessUnitId: 'bu-9' },
      { isAdmin: undefined, businessUnitId: 'bu-9' },
    ]) {
      getMock.mockClear()
      await fetchPeriods(scope)

      // Kuncinya ABSEN — bukan null dan bukan string kosong. Pertahanan
      // berlapis di atas penjagaan server, bukan penggantinya.
      expect(getMock.mock.calls[0][1]).toEqual({ params: {} })
    }
  })

  it('mengirim business_unit_id untuk Admin yang sudah memilih mill', async () => {
    getMock.mockResolvedValue({ data: { data: [] } })

    await fetchPeriods({ isAdmin: true, businessUnitId: 'bu-7' })

    expect(getMock.mock.calls[0][1]).toEqual({ params: { business_unit_id: 'bu-7' } })
  })

  it('tidak mengarang nilai untuk Admin yang belum memilih mill', async () => {
    getMock.mockResolvedValue({ data: { data: [] } })

    await fetchPeriods({ isAdmin: true, businessUnitId: null })

    // Repo tidak melempar galat sendiri: server menjawab 422 "Pilih mill
    // terlebih dahulu", dan satu sumber kebenaran itulah yang ditampilkan.
    expect(getMock.mock.calls[0][1]).toEqual({ params: {} })
  })
})

describe('productionLineParams', () => {
  it('dikirim ke summary dan export, TIDAK ke periods maupun business-units', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })
    await fetchSummary('per-1', { productionLineId: 'pl-1' })
    expect(getMock.mock.calls[0][1].params).toMatchObject({ production_line_id: 'pl-1' })

    getMock.mockReset()
    getMock.mockResolvedValue({ data: new Blob(['a']) })
    await exportCsv('per-1', { productionLineId: 'pl-1' })
    expect(getMock.mock.calls[0][1].params).toMatchObject({ production_line_id: 'pl-1' })

    getMock.mockReset()
    getMock.mockResolvedValue({ data: { data: [] } })
    await fetchPeriods({ productionLineId: 'pl-1' })
    // Periode adalah milik MILL, bukan milik Production Line — menyaringnya
    // per line akan mengarang penyempitan yang tidak ada di data.
    expect(getMock.mock.calls[0][1]).toEqual({ params: {} })

    getMock.mockReset()
    getMock.mockResolvedValue({ data: { data: [] } })
    await fetchBusinessUnits()
    expect(getMock.mock.calls[0][1]).toBeUndefined()
  })

  it('tidak mengirim parameter kosong ketika line belum berlaku', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    await fetchSummary('per-1', { productionLineId: null })

    // Server akan menjawab 422 tentang parameter yang HILANG, dan itu pesan
    // yang benar — bukan 422 tentang nilai kosong.
    expect(getMock.mock.calls[0][1].params).toEqual({ period_id: 'per-1' })
  })
})

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

    // 96/480 = 20. Repo yang menghitung sendiri menjawab 20 dan gagal di sini.
    expect(summary.coverage.coverage_percent).toBe(88)
    expect(summary.coverage.filled_slots).toBe(96)
    expect(summary.coverage.expected_slots).toBe(480)
  })

  it('meneruskan min/avg/max apa adanya walau avg di luar rentang min..max', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const metric = (await fetchSummary('per-1', { productionLineId: 'pl-1' })).metrics[0]

    expect(metric.min).toBe(10)
    expect(metric.avg).toBe(12.5)
    expect(metric.max).toBe(11)
  })

  it('meneruskan penyebut per metrik apa adanya, tanpa menyelaraskannya dengan filled_slots', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // 2 jauh lebih kecil daripada 96, dan itu BENAR: tiap kolom punya
    // penyebutnya sendiri. Repo tidak boleh "memperbaiki" selisih ini.
    expect(summary.metrics[0].filled_slot_count).toBe(2)
    expect(summary.metrics[1].filled_slot_count).toBe(0)
    expect(summary.coverage.filled_slots).toBe(96)
  })

  it('meneruskan daily_total apa adanya, tanpa menurunkannya dari daily', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // 3 + 4 = 7, dan server mengirim 99.
    expect(summary.daily_total.filled_slot_count).toBe(99)
    expect(summary.daily_total.averages.fan_static_pressure_mmh2o).toBe(77.7)
    expect(summary.daily[0].averages.fan_static_pressure_mmh2o).toBe(10)
    expect(summary.daily[1].averages.fan_static_pressure_mmh2o).toBe(100)
  })

  it('null tetap null, tidak pernah menjadi 0', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.metrics[1].min).toBeNull()
    expect(summary.metrics[1].avg).toBeNull()
    expect(summary.metrics[1].max).toBeNull()
    expect(summary.by_presser[0].averages.polishing_drum_speed_rpm).toBeNull()
  })

  it('coverage_percent null diteruskan null, bukan diganti 0', async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({
        coverage: {
          filled_slots: 0,
          expected_slots: 0,
          coverage_percent: null,
          presser_count: 0,
          slots_per_presser_per_day: 24,
          days_in_period: 30,
          days_counted: 0,
          period_running: true,
        },
      }),
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // 0% mengklaim ada yang diukur dan hasilnya nol; null berarti belum ada
    // hari yang dapat dijadikan pembagi.
    expect(summary.coverage.coverage_percent).toBeNull()
  })
})

/* ================================================================== */
/* Standar operasional                                                 */
/* ================================================================== */

describe('standar operasional', () => {
  it('meneruskan blok target tiap metrik apa adanya, termasuk yang seluruhnya null', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const metrics = (await fetchSummary('per-1', { productionLineId: 'pl-1' })).metrics

    // KEEMPAT nama kolom master Depricarping, diasersi EKSPLISIT: menyalin
    // nama dari repo Threshing atau Pressing menghasilkan blok target yang
    // seluruhnya null TANPA satu pun galat TypeScript, dan layar akan tampil
    // normal dengan setiap standar hilang.
    expect(metrics[0].target.parameter_metric).toBe('Fan Static Pressure')
    expect(metrics[0].target.target_range).toBe('40 - 50 mmH2O')
    expect(metrics[0].target.critical_limit).toBe('< 35 or > 55 mmH2O')
    expect(metrics[0].target.operational_consequence_justification)
      .toBe('Low pressure drops fibre early (heavy losses).')
    // Master belum punya baris untuk parameter ini — ketiadaan itu diteruskan,
    // bukan ditambal, supaya layar dapat menyatakan "standar belum terisi".
    expect(metrics[1].target.parameter_metric).toBeNull()
    expect(metrics[1].target.target_range).toBeNull()
    expect(metrics[1].target.operational_consequence_justification).toBeNull()
  })

  it('meneruskan KETUJUH metrik dalam urutan payload', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const metrics = (await fetchSummary('per-1', { productionLineId: 'pl-1' })).metrics

    // TUJUH, bukan lima seperti pada Threshing dan Pressing.
    expect(metrics.map((metric) => metric.column)).toEqual([
      'fan_static_pressure_mmh2o',
      'polishing_drum_speed_rpm',
      'air_velocity_ms',
      'fibre_moisture_percent',
      'kernel_recovery_in_fibre_percent',
      'nut_silo_1_temp_c',
      'nut_silo_2_temp_c',
    ])
  })

  it('meneruskan shares_standard_with: kedua nut silo saling menyebut, sisanya kosong', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const metrics = (await fetchSummary('per-1', { productionLineId: 'pl-1' })).metrics
    const byColumn = new Map(metrics.map((metric) => [metric.column, metric]))

    // SATU STANDAR MENGATUR DUA KOLOM — pertama kali terjadi di seri ini.
    // Diteruskan sebagai daftar, bukan boolean, supaya layar dapat menyebut
    // label saudara kolomnya.
    expect(byColumn.get('nut_silo_1_temp_c')?.target.shares_standard_with)
      .toEqual(['nut_silo_2_temp_c'])
    expect(byColumn.get('nut_silo_2_temp_c')?.target.shares_standard_with)
      .toEqual(['nut_silo_1_temp_c'])
    expect(byColumn.get('nut_silo_1_temp_c')?.target.parameter_metric)
      .toBe(byColumn.get('nut_silo_2_temp_c')?.target.parameter_metric)

    // Dan KOSONG — bukan undefined — pada kelima metrik lain: layar
    // memeriksanya dengan .length > 0.
    for (const column of [
      'fan_static_pressure_mmh2o',
      'polishing_drum_speed_rpm',
      'air_velocity_ms',
      'fibre_moisture_percent',
      'kernel_recovery_in_fibre_percent',
    ]) {
      expect(byColumn.get(column)?.target.shares_standard_with).toEqual([])
    }
  })

  it('meneruskan unmapped_reason apa adanya, tanpa menyimpulkannya dari nama kolom', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const metrics = (await fetchSummary('per-1', { productionLineId: 'pl-1' })).metrics
    const byColumn = new Map(metrics.map((metric) => [metric.column, metric]))

    const kernel = byColumn.get('kernel_recovery_in_fibre_percent')

    // ANGKANYA TETAP ADA — tidak dipetakan bukan berarti tidak dilaporkan.
    expect(kernel?.avg).toBe(0.5)
    expect(kernel?.filled_slot_count).toBe(1)

    // Standarnya TIDAK dipasangkan, dan alasannya diteruskan sebagai medan.
    // Pencocokan nama kolom di klien akan pecah SENYAP begitu server mengubah
    // keputusannya — dan keputusan itu memang sedang menunggu manusia.
    expect(kernel?.target.parameter_metric).toBeNull()
    expect(kernel?.target.unmapped_reason).toBe('direction_unresolved')

    // Dan null pada keenam metrik lain.
    for (const metric of metrics) {
      if (metric.column !== 'kernel_recovery_in_fibre_percent') {
        expect(metric.target.unmapped_reason).toBeNull()
      }
    }
  })

  it('meneruskan targets_without_metric dan targets_master_empty apa adanya', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.targets_master_empty).toBe(false)
    // SATU entri, bukan dua seperti pada Pressing — dan ALASANNYA yang
    // membedakan jenisnya.
    expect(summary.targets_without_metric).toHaveLength(1)
    expect(summary.targets_without_metric.map((row) => row.parameter_metric))
      .toEqual(['Kernel Loss in Fibre'])
    expect(summary.all_targets_measured).toBe(false)
    expect(summary.targets_without_metric[0].target_range).toBe('< 0.50%')
    expect(summary.targets_without_metric[0].critical_limit).toBe('> 1.00%')
    // KEEMPAT kolom master diteruskan, bukan hanya namanya — DAN alasannya.
    expect(summary.targets_without_metric[0].operational_consequence_justification)
      .toBe('Direct operational revenue loss.')
    expect(summary.targets_without_metric[0].reason).toBe('direction_unresolved')
  })

  it('TIDAK PERNAH membentuk satu pun kunci penilaian terhadap standar', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })
    const flat = JSON.stringify(summary)

    // Asersi atas KETIADAAN, dan ia harus berupa penyisiran: standar adalah
    // PROSA, jadi mengubahnya menjadi pembanding berarti mengarang batas yang
    // tidak pernah ditetapkan siapa pun.
    for (const forbidden of ['severity', 'is_out_of_range', 'out_of_range', 'flag', 'threshold', 'breach']) {
      expect(flat).not.toContain(forbidden)
    }

    // Dan daftar kuncinya dikunci: kunci yang hilang atau bertambah terlihat
    // sebagai kegagalan, bukan sebagai bagian layar yang diam-diam kosong.
    expect(Object.keys(summary).sort()).toEqual([
      'all_targets_measured', 'business_unit', 'by_presser', 'coverage',
      'daily', 'daily_total', 'downtime', 'findings', 'has_data', 'metrics',
      'period', 'production_line', 'targets_master_empty',
      'targets_without_metric', 'total',
    ].sort())
  })
})

/* ================================================================== */
/* Alasan downtime                                                     */
/* ================================================================== */

describe('alasan downtime', () => {
  it('meneruskan daftar apa adanya, tanpa menormalkan ejaan maupun huruf', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Normalisasi di klien akan menggabungkan sebab yang penulisnya memang
    // maksudkan berbeda, DAN membuat layar ini berselisih dengan laporan web
    // yang memakai sumber yang sama.
    expect(summary.findings).toEqual([
      { finding: 'belt kendur', slot_count: 2 },
      { finding: 'Belt kendur', slot_count: 3 },
    ])
  })

  it('TIDAK mengurutkan ulang findings: urutan server dipertahankan apa adanya', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Urutan payload SENGAJA tidak menurun menurut slot_count (2 lalu 3).
    // Repo yang mengurutkan sendiri akan membalikkannya dan test ini gagal.
    expect(summary.findings.map((row) => row.slot_count)).toEqual([2, 3])
  })

  it('meneruskan blok downtime sebagai OBJEK tanpa menghitung apa pun', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.downtime.total_minutes).toBe(60)
    expect(summary.downtime.recorded_slot_count).toBe(3)
    // 60 / 3 = 20, dan payload mengirim 99. Repo yang membagi sendiri akan
    // menjawab 20.
    expect(summary.downtime.avg_minutes_per_recorded_slot).toBe(99)
    // Selalu false — downtime tidak punya baris pada master target, dan
    // medan ini membuat ketiadaan itu DINYATAKAN alih-alih terbaca sebagai
    // master yang belum terisi.
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

    expect(summary.by_presser[0].downtime_minutes).toBe(30)
    expect(summary.daily[0].downtime_minutes).toBe(10)
    // null, BUKAN 0 — hari yang tidak satu pun slotnya mencatat downtime
    // bukan hari tanpa downtime.
    expect(summary.daily[1].downtime_minutes).toBeNull()
    // 10 + null = 10, dan payload mengirim 999. Repo yang menjumlahkan baris
    // harian akan menjawab 10.
    expect(summary.daily_total.downtime_minutes).toBe(999)
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

    // Urutan server dipertahankan apa adanya — pengurutan kedua di klien akan
    // menyimpang dari laporan web tanpa ketahuan.
    expect(summary.findings.map((row) => row.finding)).toEqual(['Zebra', 'Alfa'])
  })
})

/* ================================================================== */
/* Daftar periode & mill                                               */
/* ================================================================== */

describe('daftar periode dan mill', () => {
  it('meneruskan daftar periode apa adanya, termasuk periode tertutup', async () => {
    getMock.mockResolvedValue({
      data: {
        data: [
          { id: 'per-1', name: 'A', start_date: '2026-09-01', end_date: '2026-09-30', status: 'open', station_type: 'depricarping', station_type_label: 'Depricarping' },
          { id: 'per-2', name: 'B', start_date: '2026-08-01', end_date: '2026-08-31', status: 'closed', station_type: 'depricarping', station_type_label: 'Depricarping' },
        ],
      },
    })

    const periods = await fetchPeriods({})

    // Status mengatur penulisan data, bukan pembacaan laporan.
    expect(periods.map((p) => p.status)).toEqual(['open', 'closed'])
    expect(getMock.mock.calls[0][0]).toBe('/api/depricarping-reports/periods')
  })

  it('daftar periode kosong adalah jawaban yang sah, bukan galat', async () => {
    getMock.mockResolvedValue({ data: { data: [] } })

    await expect(fetchPeriods({})).resolves.toEqual([])
  })

  it('fetchBusinessUnits memanggil endpoint Admin tanpa parameter', async () => {
    getMock.mockResolvedValue({ data: { data: [{ id: 'bu-1', name: 'Mill Utara' }] } })

    const units = await fetchBusinessUnits()

    expect(getMock.mock.calls[0][0]).toBe('/api/depricarping-reports/business-units/options')
    expect(units).toEqual([{ id: 'bu-1', name: 'Mill Utara' }])
  })
})

/* ================================================================== */
/* Ekspor                                                              */
/* ================================================================== */

describe('ekspor', () => {
  it('meminta CSV sebagai blob untuk periode dan line yang diberikan', async () => {
    getMock.mockResolvedValue({ data: new Blob(['a,b']) })

    await exportCsv('per-1', { productionLineId: 'pl-1' })

    expect(getMock.mock.calls[0][0]).toBe('/api/depricarping-reports/export')
    expect(getMock.mock.calls[0][1]).toMatchObject({
      params: { period_id: 'per-1', format: 'csv', production_line_id: 'pl-1' },
      responseType: 'blob',
    })
  })

  it('saveCsvFile menyimpan blob dengan nama yang diberikan', () => {
    const createObjectURL = vi.fn(() => 'blob:x')
    const revokeObjectURL = vi.fn()

    vi.stubGlobal('URL', { createObjectURL, revokeObjectURL })

    const click = vi.fn()
    const anchor = document.createElement('a')

    anchor.click = click
    vi.spyOn(document, 'createElement').mockReturnValueOnce(anchor)

    saveCsvFile(new Blob(['a,b']), 'laporan-depricarping_periode.csv')

    expect(anchor.download).toBe('laporan-depricarping_periode.csv')
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
})

describe('bentuk modul', () => {
  it('mengekspor tepat kelima fungsi yang dipakai view, seluruhnya pembacaan', () => {
    expect(Object.keys(depricarpingReportRepo).sort()).toEqual([
      'exportCsv', 'fetchBusinessUnits', 'fetchPeriods', 'fetchSummary', 'saveCsvFile',
    ])
  })
})
