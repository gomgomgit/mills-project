/**
 * effluentPlantReportRepo.spec.ts — screen-157--laporan-effluent-plant-mobile /
 * usecase-163--laporan-effluent-plant-mobile.
 *
 * Bagian SISI KLIEN dari 55 `unit_test_cases` pada tech spec screen-157 yang
 * menyangkut BENTUK PERMINTAAN dan KESETIAAN TERUSAN. Keputusan layar atas
 * jawaban repo ada di LaporanEffluentPlantView.spec.ts; kebenaran angkanya
 * sudah dibuktikan terhadap EffluentPlantReportService di backend (case 49-55
 * pada tech spec, yakni
 * backend/tests/Unit/Services/EffluentPlantReportServiceTest.php dan
 * backend/tests/Feature/Api/LaporanEffluentPlantTest.php).
 *
 * ────────────────────────────────────────────────────────────────────────
 * EMPAT KLAIM YANG HANYA BERKAS INI DAPAT MENCATATNYA
 * ────────────────────────────────────────────────────────────────────────
 * 1. `business_unit_id` TIDAK PERNAH ADA DI KAWAT, apa pun yang diserahkan
 *    pemanggil. Perlindungannya STRUKTURAL — tidak ada satu pun jalur kode
 *    yang dapat mengirimkannya, bukan sebuah penjagaan yang menolaknya — jadi
 *    test inilah satu-satunya yang mencatat maksudnya. Dan taruhannya di
 *    layar ini lebih besar daripada di kesebelas saudaranya: pelebaran akses
 *    Operator yang mendarat bersama screen-157 menambahkan Operator ke cabang
 *    TERIKAT-MILL resolveBusinessUnit() justru supaya nilai kiriman klien
 *    DIBUANG. Repo yang menumbuhkan cabang Admin "sekadar supaya seragam"
 *    mengirim parameter yang server memang buang — tetapi
 *    /api/effluent-plant-reports/business-units/options tetap 403 untuk
 *    Operator, dan menyerahkan daftar seluruh mill ke perangkatnya adalah
 *    justru kebocoran yang dihindari.
 *
 * 2. TIDAK ADA OBJEK `target` DAN TIDAK ADA BLOK `downtime` — pada SETIAP
 *    metrik dan pada SELURUH ringkasan. Diasersi atas KETIADAAN MEDANNYA,
 *    bukan atas nilainya null: medan yang muncul tanpa disadari akan lolos
 *    dari asersi "nilainya null", dan layar akan menumbuhkan sel yang tidak
 *    mungkin diisi siapa pun. Menyalin tipe kernelPlantReportRepo.ts
 *    mentah-mentah membuat keduanya undefined di mana-mana tanpa satu galat
 *    TypeScript.
 *
 * 3. `status_distribution` DITERUSKAN UTUH, DAN IA TIDAK MEMBAWA `share`.
 *    Payload hanya menerbitkan `counts` + `not_recorded` + `recorded_slots` +
 *    `known_values`. Lihat blok DIVERGENSI di bawah.
 *
 * 4. KEDUA DAFTAR TEKS BEBAS adalah daftar KEJADIAN {date, time_slot, text},
 *    BUKAN dikelompokkan menurut teksnya seperti Kernel Plant mobile. Fixture
 *    memberi TIGA kejadian berteks IDENTIK pada tiga slot berbeda: repo yang
 *    mengelompokkan menurut teks menjawab satu entri dan test ini gagal.
 *
 * ────────────────────────────────────────────────────────────────────────
 * DIVERGENSI SPEC-LAWAN-PAYLOAD YANG DICATAT DI SINI
 * ────────────────────────────────────────────────────────────────────────
 * unit_test_case 25 berbunyi "porsi sebaran status tidak dihitung ulang di
 * klien / state memakai share dari server apa adanya". MEDAN `share` ITU
 * TIDAK ADA pada payload: EffluentPlantReportService::statusDistributionOf()
 * menerbitkan `known_values` + `counts` + `not_recorded` + `recorded_slots`
 * dan tidak satu pun porsi. Kasus itu karena itu TIDAK DAPAT DIPENUHI SEPERTI
 * TERTULIS, dan ia tidak dibuang: yang diuji di sini adalah kontrak yang
 * SEBENARNYA — repo meneruskan keempat medan apa adanya dan TIDAK menumbuhkan
 * `share` sendiri. Porsinya dihitung di VIEW oleh formatShare(), yang ditiru
 * baris demi baris dari helper $porsi pada blade screen-156 (penyebutnya
 * recorded_slots) justru supaya ponsel tidak mungkin mencetak porsi yang
 * berbeda dari web untuk payload yang sama; asersinya ada di
 * LaporanEffluentPlantView.spec.ts.
 *
 * Divergensi kedua, lebih kecil: `metrics[kolom]` tidak membawa `label`
 * maupun `unit` (tidak seperti Kernel Plant). Keduanya medan OPSIONAL pada
 * tipe supaya nilai server MENANG bila kelak diterbitkan, dan repo tidak
 * pernah punya peta satuan yang mengubah 'C' menjadi '°C'. Tabel labelnya ada
 * di view (METRIC_META, disalin dari $metrikMeta pada blade screen-156).
 *
 * Divergensi ketiga: spec menamai `fetchProductionLines` sebagai bagian layar
 * ini. Layar ini memakai ULANG productionLineRepo.fetchProductionLinesForReport(null)
 * VERBATIM — endpoint screen-135, tidak diduplikasi di bawah prefix
 * effluent-plant-reports — dan repo ini SENGAJA tidak membungkusnya kedua
 * kali. Asersi "modul ini tidak mengekspor pengambil daftar line" ada di
 * bawah; jalur pemakaiannya diuji di spec komponen.
 *
 * ────────────────────────────────────────────────────────────────────────
 * FIXTURE-NYA SENGAJA BERTENTANGAN DENGAN DIRINYA SENDIRI
 * ────────────────────────────────────────────────────────────────────────
 * Klaim pokok berkas ini adalah "NOL perhitungan di klien", dan klaim itu
 * hanya dapat dibuktikan oleh payload yang MUSTAHIL dihasilkan perhitungan:
 *
 *   - coverage_percent 8.2 padahal 118/1440 = 8,19 dan 2 × 30 × 24 = 1440
 *     cocok, tetapi coverage_percent yang dipakai tetap yang DIKIRIM
 *   - metrics.anaerobic_pond_1_ph.avg 7,4 di LUAR rentang min 6,1 .. max 8,9?
 *     tidak — justru di dalamnya, jadi dipakai nilai kedua: avg 12,5 dengan
 *     min 10 dan max 11 pada cooling_pond_temp_c
 *   - reading_count tiap metrik berbeda-beda dan SELURUHNYA lebih kecil
 *     daripada coverage.filled_slots 118
 *   - sum(counts) + not_recorded pada biogas_flare_status TIDAK sama dengan
 *     recorded_slots — invariannya milik server, dan repo tidak boleh
 *     "memperbaikinya"
 *   - daily_total.reading_rows 99 padahal baris hariannya 3 + 4
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

import effluentPlantReportRepo, {
  exportCsv,
  fetchPeriods,
  fetchSummary,
  saveCsvFile,
  type EffluentPlantReportScope,
} from '@/services/effluentPlantReportRepo'

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

/** KEEMPAT BELAS kolom ukur, dalam urutan EffluentPlantReportService::NUMERIC_METRICS. */
const METRIC_COLUMNS = [
  'anaerobic_pond_1_ph',
  'anaerobic_pond_1_temp_c',
  'anaerobic_pond_2_ph',
  'anaerobic_pond_2_temp_c',
  'cooling_pond_ph',
  'cooling_pond_temp_c',
  'biogas_flow_rate_m3h',
  'raw_pome_feed_rate_m3h',
  'effluent_discharge_flow_rate_m3h',
  'final_discharge_ph',
  'final_discharge_bod_mgl_lab',
  'final_discharge_cod_mgl_lab',
  'final_discharge_tss_mgl_lab',
  'chemical_consumed_kgl',
]

/** KETIGA kolom laboratorium — penyebut kecil pada ketiganya NORMAL. */
const LAB_COLUMNS = [
  'final_discharge_bod_mgl_lab',
  'final_discharge_cod_mgl_lab',
  'final_discharge_tss_mgl_lab',
]

/**
 * Cakupan yang membawa medan yang TIDAK ADA pada tipe
 * EffluentPlantReportScope. Dipakai untuk membuktikan bahwa medan itu tidak
 * punya jalur apa pun ke kawat — bukan bahwa tipe menolaknya (tipe memang
 * menolaknya, dan itu bukan yang diuji di sini: tipe tidak berlaku pada
 * JavaScript yang sudah terkompilasi).
 */
function scopeWithForbiddenFields(productionLineId: string | null): EffluentPlantReportScope {
  return {
    productionLineId,
    businessUnitId: 'bu-9',
    business_unit_id: 'bu-9',
    isAdmin: true,
  } as unknown as EffluentPlantReportScope
}

/**
 * Satu entri metrics[kolom].
 *
 * PERHATIKAN APA YANG TIDAK ADA: tidak ada `target`, tidak ada `label`,
 * tidak ada `unit`, tidak ada severity/is_out_of_range/flag. Payload Effluent
 * Plant memang tidak menerbitkannya, dan stub yang menambahkannya akan
 * menyamarkan repo yang membacanya dari tempat yang tidak ada.
 */
function metric(
  min: number | null,
  avg: number | null,
  max: number | null,
  readingCount: number,
  isLab = false,
): Record<string, unknown> {
  return { min, avg, max, reading_count: readingCount, is_lab: isLab }
}

/**
 * KEEMPAT BELAS entri, dengan PENYEBUT YANG BERBEDA-BEDA. Dengan penyebut
 * seragam, satu penyebut bersama lolos tanpa terlihat — dan di stasiun ini
 * ketiga kolom laboratorium memang berpenyebut satu orde lebih kecil, yang
 * merupakan keadaan NORMAL.
 */
function metrics(): Record<string, Record<string, unknown>> {
  return {
    anaerobic_pond_1_ph: metric(6.1, 7.4, 8.9, 118),
    anaerobic_pond_1_temp_c: metric(38, 41.2, 45, 117),
    anaerobic_pond_2_ph: metric(6.4, 7.1, 7.9, 116),
    anaerobic_pond_2_temp_c: metric(37, 40.1, 44, 115),
    cooling_pond_ph: metric(6.8, 7.2, 7.6, 114),
    // avg DI LUAR rentang min..max — mustahil dari perhitungan apa pun, dan
    // itulah yang membuat "nol aritmetika di klien" dapat dibuktikan.
    cooling_pond_temp_c: metric(10, 12.5, 11, 113),
    biogas_flow_rate_m3h: metric(120, 240, 310, 90),
    raw_pome_feed_rate_m3h: metric(18.5, 24.1, 30.2, 110),
    effluent_discharge_flow_rate_m3h: metric(15.1, 21.8, 28.4, 109),
    final_discharge_ph: metric(6.2, 6.9, 7.5, 108),
    // KETIGA nilai laboratorium: penyebut 4 lawan 118, dan itu NORMAL.
    final_discharge_bod_mgl_lab: metric(42, 78, 120, 4, true),
    final_discharge_cod_mgl_lab: metric(180, 260, 340, 4, true),
    final_discharge_tss_mgl_lab: metric(60, 95, 140, 3, true),
    // KOLOM YANG TIDAK PERNAH TERISI: seluruh angkanya null, penyebut 0.
    // Entrinya WAJIB tetap ada — kartu yang hilang terbaca sebagai "tidak ada
    // metrik ini" padahal yang benar adalah "tidak ada yang mengukurnya".
    chemical_consumed_kgl: metric(null, null, null, 0),
  }
}

/**
 * KETIGA sebaran status.
 *
 * TIDAK ADA `share` DI SINI, dan itu bukan kelalaian stub: payload memang
 * tidak menerbitkannya (lihat blok DIVERGENSI pada docblock berkas).
 *
 * biogas_flare_status memuat 'fault' BERNILAI 0 — nilai dikenal yang tidak
 * pernah muncul — dan sum(counts) + not_recorded SENGAJA tidak sama dengan
 * recorded_slots: invariannya milik server, dan repo tidak boleh
 * "memperbaikinya".
 */
function statusDistribution(): Record<string, Record<string, unknown>> {
  return {
    biogas_flare_status: {
      known_values: ['on', 'off', 'fault'],
      counts: { on: 310, off: 90, fault: 0 },
      not_recorded: 50,
      recorded_slots: 118,
    },
    dosing_pump_1_status: {
      known_values: ['run', 'stop'],
      counts: { run: 60, stop: 8 },
      not_recorded: 50,
      recorded_slots: 118,
    },
    sludge_dewatering_status: {
      known_values: ['run', 'stop'],
      // KEDUA nilai dikenal bernilai 0 — seluruh slot tidak mencatatnya.
      counts: { run: 0, stop: 0 },
      not_recorded: 118,
      recorded_slots: 118,
    },
  }
}

/** Payload ringkasan yang sengaja tidak konsisten — lihat docblock berkas. */
function summaryPayload(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    business_unit: { id: 'bu-1', name: 'Mill Sungai Rambutan' },
    production_line: { id: 'pl-1', name: 'Line Satu' },
    period: {
      id: 'per-1',
      name: 'Periode Uji Effluent Plant',
      start_date: PERIOD_START,
      end_date: PERIOD_END,
      status: 'open',
    },
    has_data: true,
    coverage: {
      filled_slots: 118,
      expected_slots: 1440,
      coverage_percent: 8.2,
      effluent_plant_unit_count: 2,
      slots_per_unit_per_day: 24,
      days_in_period: 30,
      days_counted: 30,
      period_running: false,
    },
    metrics: metrics(),
    status_distribution: statusDistribution(),
    /**
     * TIGA KEJADIAN BERTEKS IDENTIK pada tiga slot berbeda. Repo yang
     * mengelompokkan menurut teks menjawab SATU entri dan test ini gagal.
     */
    maintenance_remarks: [
      { date: DAY_A, time_slot: '07:00', text: 'Ganti membran pompa dosing' },
      { date: DAY_A, time_slot: '13:00', text: 'Ganti membran pompa dosing' },
      { date: DAY_B, time_slot: '21:00', text: 'Ganti membran pompa dosing' },
    ],
    /**
     * DAFTAR TERPISAH, dan entri PERTAMA-nya memakai date + time_slot yang
     * SAMA dengan entri pertama maintenance_remarks: satu slot yang mengisi
     * kedua kolom muncul pada KEDUA daftar, dan itu benar.
     */
    findings: [
      { date: DAY_A, time_slot: '07:00', text: 'Busa tebal di kolam anaerobik 2' },
      { date: DAY_B, time_slot: '03:00', text: 'Bau menyengat di dekat cooling pond' },
    ],
    daily_trend: {
      raw_pome_feed_rate_m3h: [
        { date: DAY_A, avg: 22.5, reading_count: 3 },
        // null — BUKAN 0 — pada tanggal yang tidak sekali pun mencatatnya.
        { date: DAY_B, avg: null, reading_count: 0 },
      ],
      final_discharge_ph: [
        { date: DAY_A, avg: 6.8, reading_count: 3 },
        { date: DAY_B, avg: 7.1, reading_count: 4 },
      ],
    },
    by_unit: [
      {
        effluent_plant_id: 'EP-1',
        reading_rows: 70,
        reading_count: 70,
        metrics: {
          ...metrics(),
          anaerobic_pond_1_ph: metric(6.1, 7.0, 7.9, 70),
        },
      },
      {
        // Unit yang punya record tetapi TIDAK SATU PUN pembacaan — tetap
        // muncul, karena membuangnya justru menyembunyikan unit yang tidak
        // pernah dituliskan.
        effluent_plant_id: 'EP-2',
        reading_rows: 0,
        reading_count: 0,
        metrics: Object.fromEntries(
          METRIC_COLUMNS.map((column) => [
            column,
            metric(null, null, null, 0, LAB_COLUMNS.includes(column)),
          ]),
        ),
      },
    ],
    daily: [
      {
        date: DAY_A,
        reading_rows: 3,
        metrics_avg: {
          anaerobic_pond_1_ph: { avg: 7.0, reading_count: 3 },
          chemical_consumed_kgl: { avg: null, reading_count: 0 },
        },
      },
      {
        date: DAY_B,
        reading_rows: 4,
        metrics_avg: {
          anaerobic_pond_1_ph: { avg: 8.0, reading_count: 4 },
          chemical_consumed_kgl: { avg: null, reading_count: 0 },
        },
      },
    ],
    // 3 + 4 = 7, dan server mengirim 99.
    daily_total: { reading_rows: 99 },
    total: { days_with_records: 2, reading_rows: 118 },
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
  // unit_test_case 16
  it('fetchPeriods memanggil endpoint TANPA argumen kedua, apa pun yang diserahkan pemanggil', async () => {
    getMock.mockResolvedValue({ data: { data: [] } })

    // Pemanggil yang menyerahkan cakupan ala kesepuluh repo pertama. Di
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

      expect(getMock.mock.calls[0][0]).toBe('/api/effluent-plant-reports/periods')
      // TIDAK ADA objek konfigurasi sama sekali — bukan `{ params: {} }`.
      // Periode adalah milik MILL, dan mill diselesaikan server dari akun.
      expect(getMock.mock.calls[0][1]).toBeUndefined()
    }
  })

  // unit_test_case 15
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

  // unit_test_case 19
  it('tidak ada satu pun pemanggilan ke /business-units/options dari modul ini', async () => {
    getMock.mockResolvedValue({ data: { data: [] } })

    await fetchPeriods()
    getMock.mockResolvedValue({ data: summaryPayload() })
    await fetchSummary('per-1', { productionLineId: 'pl-1' })
    getMock.mockResolvedValue({ data: new Blob(['a']) })
    await exportCsv('per-1', { productionLineId: 'pl-1' })

    const urls = getMock.mock.calls.map((call) => String(call[0]))

    // Rute itu Admin saja — 403 untuk Operator, Supervisor, DAN Mill
    // Management, diangkat dari DALAM service dan karena itu TIDAK ikut
    // terbuka ketika pelebaran screen-157 menyentuh daftar peran middleware.
    // Menyerahkan daftar seluruh mill kepada peran terikat-mill adalah justru
    // kebocoran yang dihindari.
    expect(urls.some((url) => url.includes('business-unit'))).toBe(false)
    expect(urls).toEqual([
      '/api/effluent-plant-reports/periods',
      '/api/effluent-plant-reports/summary',
      '/api/effluent-plant-reports/export',
    ])
  })

  it('modul tidak mengekspor fungsi pengambil daftar mill sama sekali', async () => {
    const module = await import('@/services/effluentPlantReportRepo')

    // Ketiadaan FUNGSINYA, bukan ketiadaan pemanggilannya: fungsi yang ada
    // akan dipakai seseorang.
    expect(Object.keys(module)).not.toContain('fetchBusinessUnits')
    expect(Object.keys(effluentPlantReportRepo)).not.toContain('fetchBusinessUnits')
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
    // per line akan mengarang penyempitan yang tidak ada di data, dan backend
    // pun tidak menerima parameter ini di sana.
    expect(getMock.mock.calls[0][1]).toBeUndefined()
  })

  // unit_test_case 41 (separuh klien): permintaan yang 422-nya BENAR
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
    expect(summary.metrics).toEqual({})
    expect(summary.status_distribution).toEqual({})
    expect(summary.maintenance_remarks).toEqual([])
    expect(summary.findings).toEqual([])
    expect(summary.by_unit).toEqual([])
    expect(summary.daily).toEqual([])
    // null, BUKAN 0 — bentuk bawaan yang berbohong lebih buruk daripada
    // bentuk bawaan yang kosong.
    expect(summary.coverage.coverage_percent).toBeNull()
    expect(summary.coverage.filled_slots).toBe(0)
    expect(summary.coverage.expected_slots).toBe(0)
    expect(summary.daily_trend).toEqual({
      raw_pome_feed_rate_m3h: [],
      final_discharge_ph: [],
    })
    expect(summary.daily_total).toEqual({ reading_rows: 0 })
    expect(summary.total).toEqual({ days_with_records: 0, reading_rows: 0 })
  })
})

/* ================================================================== */
/* Kesetiaan terusan — NOL perhitungan di klien                        */
/* ================================================================== */

describe('nol perhitungan di klien', () => {
  // unit_test_case 23 + 27
  it('meneruskan coverage_percent dan expected_slots apa adanya, tanpa menurunkan satu pun', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.coverage.coverage_percent).toBe(8.2)
    expect(summary.coverage.filled_slots).toBe(118)
    // TIDAK diturunkan dari 2 × 30 × 24 meski ketiga angkanya tersedia.
    expect(summary.coverage.expected_slots).toBe(1440)
    expect(summary.coverage.effluent_plant_unit_count).toBe(2)
    expect(summary.coverage.slots_per_unit_per_day).toBe(24)
    expect(summary.coverage.days_counted).toBe(30)
    expect(summary.coverage.days_in_period).toBe(30)
    expect(summary.coverage.period_running).toBe(false)
  })

  // unit_test_case 24
  it('meneruskan min/avg/max apa adanya walau avg di luar rentang min..max', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.metrics.anaerobic_pond_1_ph.min).toBe(6.1)
    expect(summary.metrics.anaerobic_pond_1_ph.avg).toBe(7.4)
    expect(summary.metrics.anaerobic_pond_1_ph.max).toBe(8.9)

    // Tidak ada (min+max)/2 maupun turunan lain: 12,5 berada di LUAR
    // rentang 10..11, jadi repo yang menghitung sendiri menjawab 10,5.
    expect(summary.metrics.cooling_pond_temp_c.min).toBe(10)
    expect(summary.metrics.cooling_pond_temp_c.avg).toBe(12.5)
    expect(summary.metrics.cooling_pond_temp_c.max).toBe(11)
  })

  // unit_test_case 29
  it('KEEMPAT BELAS entri metrik selalu ada, dalam urutan payload, termasuk yang kosong', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(Object.keys(summary.metrics)).toEqual(METRIC_COLUMNS)

    // Kolom yang tidak pernah terisi TIDAK disaring keluar: null tetap null,
    // dan penyebutnya 0 — bukan dihapus dan bukan dikoersi menjadi 0/0/0.
    expect(summary.metrics.chemical_consumed_kgl.min).toBeNull()
    expect(summary.metrics.chemical_consumed_kgl.avg).toBeNull()
    expect(summary.metrics.chemical_consumed_kgl.max).toBeNull()
    expect(summary.metrics.chemical_consumed_kgl.reading_count).toBe(0)
  })

  it('KEEMPAT BELAS penyebut diteruskan apa adanya, tanpa diselaraskan dengan filled_slots', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(METRIC_COLUMNS.map((column) => summary.metrics[column].reading_count)).toEqual([
      118, 117, 116, 115, 114, 113, 90, 110, 109, 108, 4, 4, 3, 0,
    ])

    // Tiap kolom punya penyebutnya SENDIRI: empat belas kolom nullable yang
    // terisi saling bebas, jadi satu penyebut bersama akan salah untuk
    // setidaknya tiga belas di antaranya. Repo tidak boleh "memperbaiki"
    // selisih ini.
    expect(new Set(METRIC_COLUMNS.map((column) => summary.metrics[column].reading_count)).size)
      .toBeGreaterThan(1)
  })

  it('coverage.filled_slots BOLEH menyamai atau melampaui setiap penyebut kartu, dan itu benar', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Slot dihitung terisi bila salah satu dari SEMBILAN BELAS kolom bacaan
    // terisi, dan lima di antaranya (tiga kolom status, dua kolom teks) bukan
    // kolom ukur. Yang dilampaui adalah penyebut KARTU, bukan penyebut
    // CAKUPAN — bukan ketidaksesuaian yang perlu "diperbaiki".
    for (const column of METRIC_COLUMNS) {
      expect(summary.metrics[column].reading_count).toBeLessThanOrEqual(
        summary.coverage.filled_slots,
      )
    }
  })

  // unit_test_case 26
  it("satuan 'C' TIDAK dipetakan menjadi '°C' — dan repo tidak punya peta satuan sama sekali", async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({
        metrics: {
          ...metrics(),
          // Server MENERBITKAN unit di sini (biasanya tidak), dan nilainya
          // 'C'. Repo yang mempercantiknya menjadi '°C' membuat ponsel
          // berselisih dengan laporan web yang mencetak medan yang sama apa
          // adanya.
          anaerobic_pond_1_temp_c: { ...metric(38, 41.2, 45, 117), unit: 'C' },
        },
      }),
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.metrics.anaerobic_pond_1_temp_c.unit).toBe('C')
    expect(JSON.stringify(summary)).not.toContain('°C')
  })

  it('meneruskan daily_total dan total apa adanya, tanpa menurunkannya dari daily', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // 3 + 4 = 7, dan server mengirim 99.
    expect(summary.daily_total.reading_rows).toBe(99)
    expect(summary.daily[0].reading_rows).toBe(3)
    expect(summary.daily[1].reading_rows).toBe(4)
    expect(summary.total).toEqual({ days_with_records: 2, reading_rows: 118 })

    // `metrics_avg` dengan bentuk {avg, reading_count} — BUKAN
    // `averages: Record<string, number | null>` milik repo Kernel Plant.
    expect(summary.daily[0].metrics_avg.anaerobic_pond_1_ph).toEqual({
      avg: 7.0,
      reading_count: 3,
    })
    expect(summary.daily[0].metrics_avg.chemical_consumed_kgl.avg).toBeNull()
  })

  it('null tetap null, tidak pernah menjadi 0', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.metrics.chemical_consumed_kgl.avg).toBeNull()
    expect(summary.by_unit[1].metrics.anaerobic_pond_1_ph.avg).toBeNull()
    expect(summary.daily[0].metrics_avg.chemical_consumed_kgl.avg).toBeNull()
    // Titik tren tanpa pembacaan: null, BUKAN 0 — batang nol terbaca sebagai
    // hasil pengukuran yang memang nol.
    expect(summary.daily_trend.raw_pome_feed_rate_m3h[1].avg).toBeNull()
    expect(summary.daily_trend.raw_pome_feed_rate_m3h[1].reading_count).toBe(0)
  })

  it('coverage_percent 0 diteruskan 0, bukan diganti null', async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({
        coverage: {
          filled_slots: 0,
          expected_slots: 1440,
          // Penyebutnya TERBENTUK, dan yang terukur memang nol slot.
          coverage_percent: 0,
          effluent_plant_unit_count: 2,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
          days_counted: 30,
          period_running: false,
        },
      }),
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.coverage.coverage_percent).toBe(0)
    expect(summary.coverage.expected_slots).toBe(1440)
  })

  it('coverage_percent 0.0 pada penyebut NOL pun diteruskan 0, bukan diubah menjadi null', async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({
        coverage: {
          filled_slots: 0,
          expected_slots: 0,
          // INI YANG DIKIRIM server Effluent Plant pada keadaan penyebut-nol
          // (lihat EffluentPlantReportService::buildSummary) — 0.0, BUKAN
          // null. Repo meneruskannya apa adanya, dan VIEW yang memutuskan
          // tanda pisah dari expected_slots === 0. Mengubahnya menjadi null di
          // sini akan memindahkan keputusan itu ke tempat yang salah.
          coverage_percent: 0,
          effluent_plant_unit_count: 0,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
          days_counted: 0,
          period_running: true,
        },
      }),
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.coverage.coverage_percent).toBe(0)
    expect(summary.coverage.expected_slots).toBe(0)
  })

  it('coverage_percent null tetap null — tipe menerima keduanya', async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({
        coverage: {
          filled_slots: 0,
          expected_slots: 0,
          coverage_percent: null,
          effluent_plant_unit_count: 0,
          slots_per_unit_per_day: 24,
          days_in_period: 30,
          days_counted: 0,
          period_running: true,
        },
      }),
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.coverage.coverage_percent).toBeNull()
  })
})

/* ================================================================== */
/* is_lab — DITURUNKAN DARI RESPONS, bukan dari daftar nama kolom       */
/* ================================================================== */

describe('penanda laboratorium', () => {
  it('is_lab diteruskan apa adanya: true pada ketiga kolom lab, false pada sebelas sisanya', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    for (const column of METRIC_COLUMNS) {
      expect(summary.metrics[column].is_lab, column).toBe(LAB_COLUMNS.includes(column))
    }

    expect(METRIC_COLUMNS.filter((column) => summary.metrics[column].is_lab)).toHaveLength(3)
  })

  it('kolom laboratorium KEEMPAT dari respons ikut bertanda, tanpa menyentuh satu berkas klien pun', async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({
        metrics: {
          ...metrics(),
          // Kolom yang HARI INI bukan laboratorium, ditandai lab oleh server.
          // Repo yang memegang daftar nama kolom sendiri akan menjawab false.
          final_discharge_ph: { ...metric(6.2, 6.9, 7.5, 12, true) },
        },
      }),
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.metrics.final_discharge_ph.is_lab).toBe(true)
    expect(METRIC_COLUMNS.filter((column) => summary.metrics[column].is_lab)).toHaveLength(4)
  })

  it('penyebut kolom laboratorium yang satu orde lebih kecil TIDAK diselaraskan', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // 4 / 4 / 3 lawan 118 — keadaan NORMAL, bukan data yang hilang.
    for (const column of LAB_COLUMNS) {
      expect(summary.metrics[column].reading_count).toBeLessThan(10)
    }
    expect(summary.metrics.anaerobic_pond_1_ph.reading_count).toBe(118)
  })
})

/* ================================================================== */
/* KETIADAAN target DAN downtime — diasersi atas KETIADAAN MEDANNYA    */
/* ================================================================== */

describe('tidak ada target dan tidak ada downtime', () => {
  it('SETIAP metrik tidak punya medan target, dan tidak satu pun nama milik keempat master saudaranya', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Diasersi atas KETIADAAN MEDANNYA, bukan atas nilainya null: medan yang
    // muncul tanpa disadari akan lolos dari asersi "nilainya null", dan layar
    // akan menumbuhkan sel yang tidak mungkin diisi siapa pun. Effluent Plant
    // TIDAK PUNYA tabel master target operasional — hanya Threshing,
    // Pressing, Depricarping, dan Kernel Plant punya.
    for (const column of METRIC_COLUMNS) {
      const metricEntry = summary.metrics[column] as unknown as Record<string, unknown>

      expect(Object.keys(metricEntry).sort()).toEqual([
        'avg',
        'is_lab',
        'max',
        'min',
        'reading_count',
      ])

      for (const forbidden of [
        'target',
        'equipment_parameter',
        'target_benchmark',
        'corrective_action_plan',
        'shares_standard_with',
        'critical_limit',
        'operational_consequence_justification',
        'parameter_metric',
        'target_range',
        'standard_operational_target',
      ]) {
        expect(metricEntry, `${column}.${forbidden}`).not.toHaveProperty(forbidden)
      }
    }
  })

  it('ringkasan tidak punya blok downtime, tidak punya targets_without_metric, dan daftar kuncinya dikunci', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // effluent_plant_details TIDAK PUNYA satu pun kolom downtime: menyalin
    // KernelPlantReportDowntime ke sini menghasilkan undefined di mana-mana,
    // dan layar yang menggambarnya memamerkan blok yang tidak pernah bisa
    // terisi.
    for (const forbidden of [
      'downtime',
      'targets_without_metric',
      'all_targets_measured',
      'targets_master_empty',
      'by_kernel_plant',
    ]) {
      expect(summary, forbidden).not.toHaveProperty(forbidden)
    }

    // Kunci yang hilang atau bertambah terlihat sebagai kegagalan, bukan
    // sebagai bagian layar yang diam-diam kosong.
    expect(Object.keys(summary).sort()).toEqual([
      'business_unit',
      'by_unit',
      'coverage',
      'daily',
      'daily_total',
      'daily_trend',
      'findings',
      'has_data',
      'maintenance_remarks',
      'metrics',
      'period',
      'production_line',
      'status_distribution',
      'total',
    ])
  })

  it('TIDAK PERNAH membentuk satu pun kunci penilaian terhadap standar atau kepatuhan', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })
    const flat = JSON.stringify(summary)

    // Empat dari empat belas metrik stasiun ini (pH/BOD/COD/TSS buangan
    // akhir) adalah parameter KEPATUHAN LINGKUNGAN yang baku mutunya hidup DI
    // LUAR aplikasi ini. Membentuk kunci penilaian di sini berarti mengarang
    // batas yang tidak pernah ditetapkan siapa pun.
    for (const forbidden of [
      'severity',
      'is_out_of_range',
      'out_of_range',
      '"flag"',
      'threshold',
      'breach',
      'compliant',
      'compliance',
    ]) {
      expect(flat, forbidden).not.toContain(forbidden)
    }
  })
})

/* ================================================================== */
/* Sebaran status — TIDAK ADA `share` PADA PAYLOAD                     */
/* ================================================================== */

describe('sebaran status', () => {
  // unit_test_case 30
  it('nilai dikenal dengan jumlah 0 TIDAK disaring keluar, dan known_values dipertahankan', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })
    const flare = summary.status_distribution.biogas_flare_status

    // known_values itulah yang diiterasi VIEW — bukan kunci `counts`.
    // Mengiterasi counts menghilangkan baris bernilai 0, dan baris yang
    // hilang terbaca sebagai "nilai ini tidak ada" padahal yang benar adalah
    // "nilai ini tidak pernah terjadi".
    expect(flare.known_values).toEqual(['on', 'off', 'fault'])
    expect(flare.counts).toEqual({ on: 310, off: 90, fault: 0 })
    expect(flare.counts.fault).toBe(0)
    expect(Object.keys(flare.counts)).toContain('fault')

    // Dan ketika KEDUA nilai dikenal bernilai 0, keduanya tetap ada.
    expect(summary.status_distribution.sludge_dewatering_status.known_values)
      .toEqual(['run', 'stop'])
    expect(summary.status_distribution.sludge_dewatering_status.counts)
      .toEqual({ run: 0, stop: 0 })
  })

  // unit_test_case 31
  it('not_recorded adalah kunci TERPISAH dan tidak pernah dilipat ke off/stop', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })
    const pump = summary.status_distribution.dosing_pump_1_status

    expect(pump.not_recorded).toBe(50)
    // 8, BUKAN 58: kolom yang kosong berarti tidak ada yang mencatatnya,
    // bukan pompa yang berhenti.
    expect(pump.counts.stop).toBe(8)
    expect(summary.status_distribution.biogas_flare_status.counts.off).toBe(90)
  })

  /**
   * DIVERGENSI SPEC-LAWAN-PAYLOAD — lihat docblock berkas.
   *
   * unit_test_case 25 meminta "state memakai share dari server apa adanya".
   * Medan itu TIDAK ADA pada payload, jadi yang diasersi di sini adalah
   * kontrak yang sebenarnya: repo meneruskan keempat medan yang memang
   * dikirim dan TIDAK menumbuhkan `share` sendiri. Porsinya dihitung di VIEW
   * oleh formatShare(), ditiru baris demi baris dari blade screen-156 dengan
   * penyebut recorded_slots.
   */
  it('payload TIDAK membawa `share`, dan repo pun tidak menumbuhkannya', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    for (const [column, distribution] of Object.entries(summary.status_distribution)) {
      expect(Object.keys(distribution).sort(), column).toEqual([
        'counts',
        'known_values',
        'not_recorded',
        'recorded_slots',
      ])
      expect(distribution, column).not.toHaveProperty('share')
      expect(distribution, column).not.toHaveProperty('shares')
      expect(distribution, column).not.toHaveProperty('percent')
    }
  })

  it('recorded_slots diteruskan sebagai penyebut porsi — BUKAN expected_slots', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // 118, bukan 1440: penyebut kedua itu akan mencampur slot tak tersentuh
    // ke dalam porsi sebuah nilai yang justru tercatat.
    for (const distribution of Object.values(summary.status_distribution)) {
      expect(distribution.recorded_slots).toBe(118)
    }
    expect(summary.coverage.expected_slots).toBe(1440)
  })

  it('invarian penjumlahan milik SERVER tidak pernah "diperbaiki" repo', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })
    const flare = summary.status_distribution.biogas_flare_status
    const total = Object.values(flare.counts).reduce((sum, value) => sum + value, 0)

    // 310 + 90 + 0 + 50 = 450, dan recorded_slots 118. Payload-nya SENGAJA
    // melanggar invariannya sendiri: repo yang menormalkan angka ini akan
    // menjawab 118 dan test ini gagal.
    expect(total + flare.not_recorded).toBe(450)
    expect(flare.recorded_slots).toBe(118)
  })

  it('KETIGA sebaran diteruskan, dan tidak satu pun punya min/avg/max', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(Object.keys(summary.status_distribution)).toEqual([
      'biogas_flare_status',
      'dosing_pump_1_status',
      'sludge_dewatering_status',
    ])

    // Terendah, rata-rata, dan tertinggi tidak punya arti untuk kolom
    // kategoris — dan karena itu ketiganya juga tidak pernah muncul di
    // `metrics`.
    for (const column of Object.keys(summary.status_distribution)) {
      expect(summary.metrics, column).not.toHaveProperty(column)
      const distribution = summary.status_distribution[column] as unknown as Record<string, unknown>

      expect(distribution).not.toHaveProperty('min')
      expect(distribution).not.toHaveProperty('avg')
      expect(distribution).not.toHaveProperty('max')
    }
  })
})

/* ================================================================== */
/* Kedua daftar teks bebas — DAFTAR KEJADIAN, bukan dikelompokkan      */
/* ================================================================== */

describe('rekap teks bebas', () => {
  // unit_test_case 28
  it('tiga kejadian berteks IDENTIK tetap tiga entri {date, time_slot, text}', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Pengelompokan menurut teks menjawab SATU entri (dengan slot_count 3,
    // bentuk Kernel Plant mobile) dan menghilangkan tanggal serta slot waktu
    // yang justru satu-satunya cara menemukan kembali kejadiannya.
    expect(summary.maintenance_remarks).toHaveLength(3)
    expect(summary.maintenance_remarks).toEqual([
      { date: DAY_A, time_slot: '07:00', text: 'Ganti membran pompa dosing' },
      { date: DAY_A, time_slot: '13:00', text: 'Ganti membran pompa dosing' },
      { date: DAY_B, time_slot: '21:00', text: 'Ganti membran pompa dosing' },
    ])

    for (const entry of summary.maintenance_remarks) {
      expect(Object.keys(entry).sort()).toEqual(['date', 'text', 'time_slot'])
      // Kuncinya BUKAN `finding`/`slot_count` seperti pada repo Kernel Plant.
      expect(entry).not.toHaveProperty('slot_count')
      expect(entry).not.toHaveProperty('finding')
    }
  })

  it('kedua daftar TERPISAH, dan satu slot yang mengisi keduanya muncul pada keduanya', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.findings).toHaveLength(2)

    // SLOT YANG SAMA (tanggal + slot waktu) pada KEDUA daftar, dengan teks
    // masing-masing. Menyatukannya akan menghitung slot itu dua kali di dalam
    // satu daftar DAN mencampur dua pertanyaan yang berbeda (apa yang
    // dikerjakan vs apa yang ditemukan).
    expect(summary.maintenance_remarks[0].date).toBe(summary.findings[0].date)
    expect(summary.maintenance_remarks[0].time_slot).toBe(summary.findings[0].time_slot)
    expect(summary.maintenance_remarks[0].text).not.toBe(summary.findings[0].text)

    // Dan tidak ada daftar KETIGA yang menyatukan keduanya.
    expect(summary).not.toHaveProperty('free_text')
    expect(summary).not.toHaveProperty('notes')
    expect(summary).not.toHaveProperty('remarks')
  })

  it('TIDAK mengurutkan ulang: urutan server dipertahankan apa adanya', async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({
        findings: [
          { date: DAY_B, time_slot: '03:00', text: 'Zebra' },
          { date: DAY_A, time_slot: '07:00', text: 'Alfa' },
        ],
      }),
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Urutan payload SENGAJA tidak menurut tanggal maupun alfabet. Repo yang
    // mengurutkan sendiri akan membalikkannya dan test ini gagal.
    expect(summary.findings.map((entry) => entry.text)).toEqual(['Zebra', 'Alfa'])
  })

  it('kedua daftar kosong adalah jawaban yang sah, bukan medan yang hilang', async () => {
    getMock.mockResolvedValue({
      data: summaryPayload({ maintenance_remarks: [], findings: [] }),
    })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.maintenance_remarks).toEqual([])
    expect(summary.findings).toEqual([])
  })
})

/* ================================================================== */
/* Tren harian — DUA seri, dan tidak satu pun atas kolom status         */
/* ================================================================== */

describe('tren harian', () => {
  // unit_test_case 32
  it('TEPAT dua seri, dan tidak satu pun untuk ketiga kolom status', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(Object.keys(summary.daily_trend).sort()).toEqual([
      'final_discharge_ph',
      'raw_pome_feed_rate_m3h',
    ])

    for (const column of [
      'biogas_flare_status',
      'dosing_pump_1_status',
      'sludge_dewatering_status',
    ]) {
      expect(summary.daily_trend, column).not.toHaveProperty(column)
    }

    // Ketiga nilai laboratorium pun SENGAJA tidak digrafikkan harian: dengan
    // penyebut 3-4 garisnya lebih banyak lubang daripada data.
    for (const column of LAB_COLUMNS) {
      expect(summary.daily_trend, column).not.toHaveProperty(column)
    }
  })

  it('tiap titik tren membawa penyebutnya sendiri, dan avg null bukan 0', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.daily_trend.raw_pome_feed_rate_m3h).toEqual([
      { date: DAY_A, avg: 22.5, reading_count: 3 },
      { date: DAY_B, avg: null, reading_count: 0 },
    ])
    expect(summary.daily_trend.final_discharge_ph.map((point) => point.avg)).toEqual([6.8, 7.1])
  })
})

/* ================================================================== */
/* Rekap per unit                                                      */
/* ================================================================== */

describe('rekap per unit', () => {
  it('effluent_plant_id adalah string biasa — bukan uuid dan bukan kunci asing', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const rows = (await fetchSummary('per-1', { productionLineId: 'pl-1' })).by_unit

    // Tidak ada tabel master effluent_plants: nilainya kolom TEKS yang
    // DIKETIK di layar input, bentuk yang sama dengan boiler_room_id.
    for (const row of rows) {
      expect(typeof row.effluent_plant_id).toBe('string')
      expect(row.effluent_plant_id).not.toMatch(
        /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i,
      )
      // TIDAK ada `effluent_plant_name` — berbeda dari by_kernel_plant, yang
      // menerbitkan id DAN name bernilai sama.
      expect(row).not.toHaveProperty('effluent_plant_name')
    }

    expect(rows.map((row) => row.effluent_plant_id)).toEqual(['EP-1', 'EP-2'])
  })

  it('reading_rows dan reading_count diterbitkan BERDAMPINGAN dan bernilai sama', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const rows = (await fetchSummary('per-1', { productionLineId: 'pl-1' })).by_unit

    // Kedua nama dipakai bergantian di sebelas laporan saudaranya; keduanya
    // diterbitkan supaya setiap pemanggil tetap membaca kunci yang sudah
    // diharapkannya.
    for (const row of rows) {
      expect(row.reading_count).toBe(row.reading_rows)
    }
    expect(rows.map((row) => row.reading_rows)).toEqual([70, 0])
  })

  it('metrics per unit berbentuk SAMA dengan metrics tingkat periode, bukan {avg, reading_count}', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const rows = (await fetchSummary('per-1', { productionLineId: 'pl-1' })).by_unit

    // EMPAT BELAS entri, masing-masing dengan penyebutnya sendiri DAN
    // penanda is_lab-nya sendiri — bukan bentuk {avg, reading_count} milik
    // `daily`.
    for (const row of rows) {
      expect(Object.keys(row.metrics)).toEqual(METRIC_COLUMNS)
      expect(Object.keys(row.metrics.anaerobic_pond_1_ph).sort()).toEqual([
        'avg',
        'is_lab',
        'max',
        'min',
        'reading_count',
      ])
    }

    expect(rows[0].metrics.anaerobic_pond_1_ph.avg).toBe(7.0)
    // Unit tanpa satu pun pembacaan TETAP muncul, dengan null dan penyebut 0.
    expect(rows[1].metrics.anaerobic_pond_1_ph.avg).toBeNull()
    expect(rows[1].metrics.anaerobic_pond_1_ph.reading_count).toBe(0)
  })
})

/* ================================================================== */
/* Daftar periode                                                      */
/* ================================================================== */

describe('daftar periode', () => {
  // unit_test_case 18
  it('meneruskan daftar apa adanya, termasuk periode tertutup, tanpa business_unit_name', async () => {
    const options = [
      {
        id: 'per-1',
        name: 'Periode Uji Effluent Plant',
        start_date: PERIOD_START,
        end_date: PERIOD_END,
        status: 'open',
        // DENGAN TANDA HUBUNG. Garis bawah di situ tidak akan cocok dengan
        // satu baris period_stations pun dan server menjawab [] untuk setiap
        // mill tanpa satu galat.
        station_type: 'effluent-plant',
        station_type_label: 'Effluent Plant',
      },
      {
        id: 'per-0',
        name: 'Periode Sebelumnya',
        start_date: PREV_START,
        end_date: PREV_END,
        status: 'closed',
        station_type: 'effluent-plant',
        station_type_label: 'Effluent Plant',
      },
    ]

    getMock.mockResolvedValue({ data: { data: options } })

    const periods = await fetchPeriods()

    // Status mengatur penulisan data, bukan pembacaan laporan.
    expect(periods.map((period) => period.status)).toEqual(['open', 'closed'])
    expect(periods.map((period) => period.station_type)).toEqual([
      'effluent-plant',
      'effluent-plant',
    ])
    expect(getMock.mock.calls[0][0]).toBe('/api/effluent-plant-reports/periods')

    // unit_test_case 17: /periods TIDAK membawa nama mill — nama mill dibaca
    // view dari summary.business_unit.name, satu sumber saja supaya tidak ada
    // dua tempat yang dapat menyimpang.
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

  // unit_test_case 17 (separuh respons /summary)
  it('nama mill hanya ada pada summary.business_unit', async () => {
    getMock.mockResolvedValue({ data: summaryPayload() })

    const summary = await fetchSummary('per-1', { productionLineId: 'pl-1' })

    expect(summary.business_unit).toEqual({ id: 'bu-1', name: 'Mill Sungai Rambutan' })
    // Kepala periode pada /summary pun TIDAK ditulis membawa nama mill:
    // membacanya dari dua tempat adalah dua tempat yang dapat menyimpang.
    expect(Object.keys(summary.period ?? {}).sort()).toEqual([
      'end_date',
      'id',
      'name',
      'start_date',
      'status',
    ])
  })
})

/* ================================================================== */
/* Ekspor — CSV SAJA                                                   */
/* ================================================================== */

describe('ekspor', () => {
  // unit_test_case 42
  it('meminta CSV sebagai blob untuk periode dan line yang diberikan', async () => {
    getMock.mockResolvedValue({ data: new Blob(['a,b']) })

    await exportCsv('per-1', { productionLineId: 'pl-1' })

    expect(getMock.mock.calls[0][0]).toBe('/api/effluent-plant-reports/export')
    expect(getMock.mock.calls[0][1]).toMatchObject({
      params: { period_id: 'per-1', format: 'csv', production_line_id: 'pl-1' },
      // text/csv streaming, BUKAN JSON.
      responseType: 'blob',
    })
  })

  // unit_test_case 43
  it('format yang diminta selalu csv — tidak ada jalur xlsx di modul ini', async () => {
    getMock.mockResolvedValue({ data: new Blob(['a,b']) })

    await exportCsv('per-1', { productionLineId: 'pl-1' })

    // Ekspor .xlsx hanya ada di layar web — bukan karena endpoint-nya tidak
    // mendukungnya (ia mendukung), melainkan karena menyimpan dan membukanya
    // di WebView ponsel menuntut penanganan berkas native yang belum ada.
    expect(getMock.mock.calls[0][1].params.format).toBe('csv')
    expect(JSON.stringify(getMock.mock.calls[0][1])).not.toContain('xlsx')
    expect(JSON.stringify(getMock.mock.calls[0][1])).not.toContain('excel')
  })

  // unit_test_case 44
  it('saveCsvFile menyimpan blob dengan nama yang diberikan', () => {
    const createObjectURL = vi.fn(() => 'blob:x')
    const revokeObjectURL = vi.fn()

    vi.stubGlobal('URL', { createObjectURL, revokeObjectURL })

    const click = vi.fn()
    const anchor = document.createElement('a')

    anchor.click = click
    vi.spyOn(document, 'createElement').mockReturnValueOnce(anchor)

    saveCsvFile(new Blob(['a,b']), 'laporan-effluent-plant_periode_line.csv')

    expect(anchor.download).toBe('laporan-effluent-plant_periode_line.csv')
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
  // unit_test_case 34 + 37 (separuh repo): bentuk galat DATAR, tanpa response
  it('galat tanpa status dan galat ber-status keduanya diteruskan dengan bentuk aslinya', async () => {
    getMock.mockRejectedValueOnce({ message: 'Tidak dapat terhubung ke server.' })

    await expect(fetchSummary('per-1', { productionLineId: 'pl-1' }))
      .rejects.toEqual({ message: 'Tidak dapat terhubung ke server.' })

    getMock.mockRejectedValueOnce({ message: 'Unauthenticated.', status: 401 })

    // Ketiadaan `status` itulah yang membedakan "jaringan putus" dari "server
    // menjawab", dan urutan cabang handleError di view bergantung padanya.
    // normalizeError apiClient MEMBUANG `response`, jadi medan itu tidak
    // pernah ada pada galat yang sampai ke pemanggil.
    await expect(fetchSummary('per-1', { productionLineId: 'pl-1' }))
      .rejects.toEqual({ message: 'Unauthenticated.', status: 401 })
  })

  // unit_test_case 40
  it('404 periode tidak ditemukan diteruskan dengan status-nya, bukan diubah menjadi 401', async () => {
    getMock.mockRejectedValueOnce({ message: 'Periode tidak ditemukan.', status: 404 })

    await expect(fetchSummary('per-9', { productionLineId: 'pl-1' }))
      .rejects.toEqual({ message: 'Periode tidak ditemukan.', status: 404 })
  })

  // unit_test_case 41
  it('422 validasi diteruskan beserta medan errors-nya', async () => {
    getMock.mockRejectedValueOnce({
      message: 'Pilih Production Line terlebih dahulu.',
      status: 422,
      errors: { production_line_id: ['Pilih Production Line terlebih dahulu.'] },
    })

    // Jalur pertahanan KEDUA: penjaga klien di loadSummary() seharusnya sudah
    // mencegah permintaan ini terbentuk.
    await expect(fetchSummary('per-1', { productionLineId: null })).rejects.toMatchObject({
      status: 422,
      errors: { production_line_id: ['Pilih Production Line terlebih dahulu.'] },
    })
  })

  // unit_test_case 33
  it('galat /periods diteruskan, tidak diganti daftar kosong', async () => {
    getMock.mockRejectedValueOnce({ message: 'Unauthenticated.', status: 401 })

    await expect(fetchPeriods()).rejects.toEqual({ message: 'Unauthenticated.', status: 401 })
  })

  // unit_test_case 36
  it('galat /export diteruskan, dan tidak ada berkas yang tersimpan', async () => {
    getMock.mockRejectedValueOnce({ message: 'Unauthenticated.', status: 401 })

    await expect(exportCsv('per-1', { productionLineId: 'pl-1' }))
      .rejects.toEqual({ message: 'Unauthenticated.', status: 401 })
  })
})

/* ================================================================== */
/* Bentuk modul                                                        */
/* ================================================================== */

describe('bentuk modul', () => {
  it('mengekspor tepat keempat fungsi yang dipakai view, seluruhnya pembacaan', () => {
    // EMPAT, bukan lima: tidak ada fetchBusinessUnits, karena rute itu Admin
    // saja — 403 bahkan setelah pelebaran screen-157, yang menyentuh daftar
    // peran middleware tetapi TIDAK businessUnitOptions().
    expect(Object.keys(effluentPlantReportRepo).sort()).toEqual([
      'exportCsv',
      'fetchPeriods',
      'fetchSummary',
      'saveCsvFile',
    ])
    expect(
      Object.keys(effluentPlantReportRepo).every((key) => /^fetch|^export|^save/.test(key)),
    ).toBe(true)
  })

  /**
   * DIVERGENSI SPEC — lihat docblock berkas. Spec menamai
   * `fetchProductionLines` sebagai bagian layar ini; yang benar adalah
   * productionLineRepo.fetchProductionLinesForReport(null), DIPAKAI ULANG
   * VERBATIM dari screen-135 seperti kesebelas laporan mobile saudaranya.
   * Repo ini SENGAJA tidak membungkusnya kedua kali: pembungkus kedua adalah
   * tempat kedua yang dapat menyimpang, dan ia tidak menambahkan satu pun
   * perilaku.
   */
  it('TIDAK mengekspor pengambil daftar Production Line, dan tidak menyentuh endpoint-nya', async () => {
    const module = await import('@/services/effluentPlantReportRepo')

    expect(Object.keys(module)).not.toContain('fetchProductionLines')
    expect(Object.keys(module)).not.toContain('fetchProductionLinesForReport')
    expect(Object.keys(effluentPlantReportRepo)).not.toContain('fetchProductionLines')

    getMock.mockResolvedValue({ data: { data: [] } })
    await fetchPeriods()
    getMock.mockResolvedValue({ data: summaryPayload() })
    await fetchSummary('per-1', { productionLineId: 'pl-1' })

    // Endpoint-nya TIDAK diduplikasi di bawah prefix effluent-plant-reports,
    // dan modul ini tidak pernah memanggilnya.
    for (const call of getMock.mock.calls) {
      expect(String(call[0])).not.toContain('production-lines')
    }
  })
})
