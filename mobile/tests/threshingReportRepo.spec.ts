/**
 * threshingReportRepo.spec.ts — screen-149--laporan-threshing-mobile /
 * usecase-152--laporan-threshing-mobile.
 *
 * Satu test per unit_test_cases pada tech spec screen-149. Yang diuji di sini
 * adalah BENTUK PERMINTAAN dan KESETIAAN TERUSAN — bukan kebenaran angkanya,
 * yang sudah dibuktikan 46 kali terhadap ThreshingReportService di backend.
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

import threshingReportRepo, {
  exportCsv,
  fetchBusinessUnits,
  fetchPeriods,
  fetchSummary,
  saveCsvFile,
} from '@/services/threshingReportRepo'

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
      thresher_count: 2,
      slots_per_thresher_per_day: 24,
      days_in_period: 30,
      days_counted: 10,
      period_running: true,
    },
    metrics: [
      {
        column: 'ffb_throughput_mt_hour',
        label: 'FFB Throughput',
        unit: 'MT/jam',
        min: 10,
        // Di luar rentang min..max — mustahil dari perhitungan apa pun.
        avg: 12.5,
        max: 11,
        // Jauh lebih kecil daripada coverage.filled_slots, dan itu benar:
        // tiap kolom punya penyebutnya sendiri.
        filled_slot_count: 2,
        target: {
          parameter: 'FFB Throughput',
          standard_operational_target: 'As per mill capacity design (e.g., 30-60 MT/hr)',
          action_plan_on_deviation: 'Adjust feeder conveyor speed.',
        },
      },
      {
        column: 'thresher_drum_speed_rpm',
        label: 'Putaran Drum Thresher',
        unit: 'RPM',
        min: null,
        avg: null,
        max: null,
        filled_slot_count: 0,
        target: {
          parameter: null,
          standard_operational_target: null,
          action_plan_on_deviation: null,
        },
      },
    ],
    targets_without_metric: [
      {
        parameter: 'Bearing Temperature',
        standard_operational_target: 'Below 70C (Check if >75C)',
        action_plan_on_deviation: 'Lubricate bearings / check for mechanical wear.',
      },
    ],
    targets_master_empty: false,
    by_thresher: [
      {
        thresher_id: 'TH-1',
        day_count: 2,
        filled_slot_count: 7,
        averages: { ffb_throughput_mt_hour: 30, thresher_drum_speed_rpm: null },
      },
    ],
    daily: [
      {
        date: '2026-09-04',
        filled_slot_count: 3,
        averages: { ffb_throughput_mt_hour: 10, thresher_drum_speed_rpm: null },
      },
      {
        date: '2026-09-05',
        filled_slot_count: 4,
        averages: { ffb_throughput_mt_hour: 100, thresher_drum_speed_rpm: 22 },
      },
    ],
    daily_total: {
      // 3 + 4 = 7, dan server mengirim 99.
      filled_slot_count: 99,
      // Bukan rata-rata dari 10 dan 100 dengan cara apa pun.
      averages: { ffb_throughput_mt_hour: 77.7, thresher_drum_speed_rpm: 22 },
    },
    downtime_reasons: [
      { reason: 'Belt kendur', slot_count: 3 },
      { reason: 'belt kendur', slot_count: 2 },
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
    expect(summary.daily_total).toEqual({ filled_slot_count: 0, averages: {} })
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
    expect(summary.daily_total.averages.ffb_throughput_mt_hour).toBe(77.7)
    expect(summary.daily[0].averages.ffb_throughput_mt_hour).toBe(10)
    expect(summary.daily[1].averages.ffb_throughput_mt_hour).toBe(100)
  })

  it('null tetap null, tidak pernah menjadi 0', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.metrics[1].min).toBeNull()
    expect(summary.metrics[1].avg).toBeNull()
    expect(summary.metrics[1].max).toBeNull()
    expect(summary.by_thresher[0].averages.thresher_drum_speed_rpm).toBeNull()
  })

  it('coverage_percent null diteruskan null, bukan diganti 0', async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({
        coverage: {
          filled_slots: 0,
          expected_slots: 0,
          coverage_percent: null,
          thresher_count: 0,
          slots_per_thresher_per_day: 24,
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

    expect(metrics[0].target.standard_operational_target)
      .toBe('As per mill capacity design (e.g., 30-60 MT/hr)')
    expect(metrics[0].target.action_plan_on_deviation).toBe('Adjust feeder conveyor speed.')
    // Master belum punya baris untuk parameter ini — ketiadaan itu diteruskan,
    // bukan ditambal, supaya layar dapat menyatakan "standar belum terisi".
    expect(metrics[1].target.parameter).toBeNull()
    expect(metrics[1].target.standard_operational_target).toBeNull()
  })

  it('meneruskan targets_without_metric dan targets_master_empty apa adanya', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.targets_master_empty).toBe(false)
    expect(summary.targets_without_metric).toEqual([
      {
        parameter: 'Bearing Temperature',
        standard_operational_target: 'Below 70C (Check if >75C)',
        action_plan_on_deviation: 'Lubricate bearings / check for mechanical wear.',
      },
    ])
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
      'business_unit', 'by_thresher', 'coverage', 'daily', 'daily_total',
      'downtime_reasons', 'has_data', 'metrics', 'period', 'production_line',
      'targets_master_empty', 'targets_without_metric', 'total',
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
    expect(summary.downtime_reasons).toEqual([
      { reason: 'Belt kendur', slot_count: 3 },
      { reason: 'belt kendur', slot_count: 2 },
    ])
  })

  it('tidak mengurutkan ulang daftar yang sudah diurutkan server', async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({
        downtime_reasons: [
          { reason: 'Zebra', slot_count: 9 },
          { reason: 'Alfa', slot_count: 1 },
        ],
      }),
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Urutan server dipertahankan apa adanya — pengurutan kedua di klien akan
    // menyimpang dari laporan web tanpa ketahuan.
    expect(summary.downtime_reasons.map((row) => row.reason)).toEqual(['Zebra', 'Alfa'])
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
          { id: 'per-1', name: 'A', start_date: '2026-09-01', end_date: '2026-09-30', status: 'open', station_type: 'threshing', station_type_label: 'Threshing' },
          { id: 'per-2', name: 'B', start_date: '2026-08-01', end_date: '2026-08-31', status: 'closed', station_type: 'threshing', station_type_label: 'Threshing' },
        ],
      },
    })

    const periods = await fetchPeriods({})

    // Status mengatur penulisan data, bukan pembacaan laporan.
    expect(periods.map((p) => p.status)).toEqual(['open', 'closed'])
    expect(getMock.mock.calls[0][0]).toBe('/api/threshing-reports/periods')
  })

  it('daftar periode kosong adalah jawaban yang sah, bukan galat', async () => {
    getMock.mockResolvedValue({ data: { data: [] } })

    await expect(fetchPeriods({})).resolves.toEqual([])
  })

  it('fetchBusinessUnits memanggil endpoint Admin tanpa parameter', async () => {
    getMock.mockResolvedValue({ data: { data: [{ id: 'bu-1', name: 'Mill Utara' }] } })

    const units = await fetchBusinessUnits()

    expect(getMock.mock.calls[0][0]).toBe('/api/threshing-reports/business-units/options')
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

    expect(getMock.mock.calls[0][0]).toBe('/api/threshing-reports/export')
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

    saveCsvFile(new Blob(['a,b']), 'laporan-threshing_periode.csv')

    expect(anchor.download).toBe('laporan-threshing_periode.csv')
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
    expect(Object.keys(threshingReportRepo).sort()).toEqual([
      'exportCsv', 'fetchBusinessUnits', 'fetchPeriods', 'fetchSummary', 'saveCsvFile',
    ])
  })
})
