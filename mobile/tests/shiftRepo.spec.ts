import { beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * shiftRepo.spec.ts — aturan "record ini masuk shift mana" di sisi PERANGKAT.
 *
 * ── MENGAPA importActual ────────────────────────────────────────────────
 *
 * tests/setup/shiftRepoStub.ts men-stub `@/services/shiftRepo` secara global,
 * supaya dua pembacaan baru di dalam `saveDraft()` tidak menggeser antrian
 * `mockResolvedValueOnce` di 20 spec repo. Stub itu TIDAK membuktikan apa pun
 * tentang shift — berkas inilah yang membuktikannya, dengan memuat modul yang
 * sesungguhnya.
 *
 * ── RUMUSNYA WAJIB SAMA DENGAN SERVER ───────────────────────────────────
 *
 * Nilai-nilai yang diasersi di sini sengaja dipilih sama dengan yang
 * diasersi tests/Unit/Services/ShiftServiceTest.php di sisi server. Server
 * memeriksa ulang setiap stempel kiriman perangkat, jadi rumus yang berbeda
 * akan menghasilkan stempel yang DITOLAK — atau, lebih buruk, diterima dan
 * salah. Dua berkas test, satu aturan.
 */

vi.mock('@/services/localDb', () => ({
  query: vi.fn(),
  run: vi.fn(),
}))

const { query, run } = await import('@/services/localDb')
const shiftRepo = await vi.importActual<typeof import('@/services/shiftRepo')>('@/services/shiftRepo')

const LINE = 'line-a'

/** Pagi 07:00–15:00, Siang 15:00–23:00, Malam 23:00–07:00. */
const TIGA_SHIFT = [
  { id: 'pagi', production_line_id: LINE, name: 'Pagi', start_time: '07:00', end_time: '15:00' },
  { id: 'siang', production_line_id: LINE, name: 'Siang', start_time: '15:00', end_time: '23:00' },
  { id: 'malam', production_line_id: LINE, name: 'Malam', start_time: '23:00', end_time: '07:00' },
]

function pukul(jam: number, menit = 0): Date {
  return new Date(2026, 9, 16, jam, menit, 0)
}

/**
 * Mock yang menjawab menurut SQL-nya, bukan menurut urutan panggilan.
 * Antrian `mockResolvedValueOnce` akan membuat test ini bergantung pada
 * berapa kali kode produksi kebetulan membaca basis data — dan perubahan
 * sekecil satu pembacaan tambahan akan memerahkan semuanya tanpa ada yang
 * rusak. Itu persis yang terjadi pada 20 spec repo ketika stempel shift
 * mendarat.
 */
function jadwal(rows: unknown[]): void {
  vi.mocked(query).mockImplementation(async (sql: string) => {
    if (sql.includes('FROM shift')) return rows as never
    if (sql.includes('FROM station ')) return [{ production_line_id: LINE }] as never
    if (sql.includes('SELECT station_id FROM ')) return [{ station_id: 'st-1' }] as never
    return [] as never
  })
}

beforeEach(() => {
  vi.mocked(query).mockReset()
  vi.mocked(run).mockReset()
  vi.mocked(run).mockResolvedValue(undefined as never)
})

describe('resolveShiftIdForLine', () => {
  it('memulangkan shift yang sedang berjalan pada jam yang diberikan', async () => {
    jadwal(TIGA_SHIFT)

    expect(await shiftRepo.resolveShiftIdForLine(LINE, pukul(7))).toBe('pagi')
    expect(await shiftRepo.resolveShiftIdForLine(LINE, pukul(11, 30))).toBe('pagi')
    expect(await shiftRepo.resolveShiftIdForLine(LINE, pukul(14, 59))).toBe('pagi')
    expect(await shiftRepo.resolveShiftIdForLine(LINE, pukul(15))).toBe('siang')
    expect(await shiftRepo.resolveShiftIdForLine(LINE, pukul(22, 59))).toBe('siang')
  })

  /**
   * KASUS JEBAKAN, dan ia harus benar-benar memerahkan implementasi yang
   * membandingkan string jam: '01:00' tidak berada di antara '23:00' dan
   * '07:00' secara leksikografis, sehingga implementasi semacam itu akan
   * memulangkan null untuk SELURUH shift malam — dan null terlihat seperti
   * "memang sedang tidak ada shift", bukan seperti cacat.
   */
  it('kedua sisi tengah malam milik shift yang SAMA', async () => {
    jadwal(TIGA_SHIFT)

    expect(await shiftRepo.resolveShiftIdForLine(LINE, pukul(23))).toBe('malam')
    expect(await shiftRepo.resolveShiftIdForLine(LINE, pukul(23, 59))).toBe('malam')
    expect(await shiftRepo.resolveShiftIdForLine(LINE, pukul(0))).toBe('malam')
    expect(await shiftRepo.resolveShiftIdForLine(LINE, pukul(1))).toBe('malam')
    expect(await shiftRepo.resolveShiftIdForLine(LINE, pukul(6, 59))).toBe('malam')
  })

  it('menit pergantian milik shift yang BARU, bukan yang berakhir', async () => {
    jadwal(TIGA_SHIFT)

    expect(await shiftRepo.resolveShiftIdForLine(LINE, pukul(14, 59))).toBe('pagi')
    expect(await shiftRepo.resolveShiftIdForLine(LINE, pukul(15))).toBe('siang')
    expect(await shiftRepo.resolveShiftIdForLine(LINE, pukul(15))).not.toBe('pagi')
  })

  it('null pada celah jadwal — dan itu hasil yang sah, bukan kegagalan', async () => {
    jadwal([
      { id: 'pagi', production_line_id: LINE, name: 'Pagi', start_time: '07:00', end_time: '15:00' },
      { id: 'malam', production_line_id: LINE, name: 'Malam', start_time: '19:00', end_time: '07:00' },
    ])

    expect(await shiftRepo.resolveShiftIdForLine(LINE, pukul(15))).toBeNull()
    expect(await shiftRepo.resolveShiftIdForLine(LINE, pukul(17, 30))).toBeNull()
    expect(await shiftRepo.resolveShiftIdForLine(LINE, pukul(18, 59))).toBeNull()
    expect(await shiftRepo.resolveShiftIdForLine(LINE, pukul(19))).toBe('malam')
  })

  it('null ketika cache kosong, dan null ketika line tidak diketahui', async () => {
    jadwal([])

    expect(await shiftRepo.resolveShiftIdForLine(LINE, pukul(8))).toBeNull()
    expect(await shiftRepo.resolveShiftIdForLine(null, pukul(8))).toBeNull()
    expect(await shiftRepo.resolveShiftIdForLine('', pukul(8))).toBeNull()
  })

  it('shift 24 jam memiliki setiap menit', async () => {
    jadwal([{ id: 'penuh', production_line_id: LINE, name: 'Penuh', start_time: '07:00', end_time: '07:00' }])

    for (const jam of [0, 6, 7, 12, 23]) {
      expect(await shiftRepo.resolveShiftIdForLine(LINE, pukul(jam))).toBe('penuh')
    }
  })

  it('pada data bertindih memilih segmen yang paling baru dimulai', async () => {
    jadwal([
      { id: 'lama', production_line_id: LINE, name: 'Lama', start_time: '06:00', end_time: '18:00' },
      { id: 'baru', production_line_id: LINE, name: 'Baru', start_time: '10:00', end_time: '20:00' },
    ])

    expect(await shiftRepo.resolveShiftIdForLine(LINE, pukul(12))).toBe('baru')
  })

  it('membaca jam berbentuk HH:MM:SS sama dengan HH:MM', async () => {
    jadwal([{ id: 'pagi', production_line_id: LINE, name: 'Pagi', start_time: '07:00:00', end_time: '15:00:00' }])

    expect(await shiftRepo.resolveShiftIdForLine(LINE, pukul(8))).toBe('pagi')
  })
})

describe('stampShiftOnRecord', () => {
  it('menulis shift yang sedang berjalan ke baris record itu', async () => {
    jadwal(TIGA_SHIFT)

    const stamped = await shiftRepo.stampShiftOnRecord('process_water_record', 'rec-1', pukul(8))

    expect(stamped).toBe('pagi')
    expect(vi.mocked(run)).toHaveBeenCalledWith('UPDATE process_water_record SET shift_id = ? WHERE id = ?', [
      'pagi',
      'rec-1',
    ])
  })

  /**
   * MENULIS NULL, BUKAN MELEWATI PENULISAN. Draft yang pernah tersimpan di
   * dalam shift lalu disimpan ulang di luar shift wajib kehilangan
   * stempelnya; melewatkan UPDATE-nya akan meninggalkan stempel LAMA yang
   * kini salah — dan salah yang tertinggal lebih buruk daripada kosong.
   */
  it('menulis null ketika saat itu tidak tercakup shift mana pun', async () => {
    jadwal([{ id: 'pagi', production_line_id: LINE, name: 'Pagi', start_time: '07:00', end_time: '15:00' }])

    const stamped = await shiftRepo.stampShiftOnRecord('process_water_record', 'rec-1', pukul(20))

    expect(stamped).toBeNull()
    expect(vi.mocked(run)).toHaveBeenCalledWith('UPDATE process_water_record SET shift_id = ? WHERE id = ?', [
      null,
      'rec-1',
    ])
  })

  it('tidak melempar ketika record tidak punya station_id', async () => {
    vi.mocked(query).mockImplementation(async (sql: string) => {
      if (sql.includes('FROM process_water_record')) return [{ station_id: null }] as never
      return [] as never
    })

    await expect(shiftRepo.stampShiftOnRecord('process_water_record', 'rec-1', pukul(8))).resolves.toBeNull()
  })

  /**
   * `query()` yang memulangkan undefined adalah bentuk yang dipakai belasan
   * spec repo (mock tanpa implementasi). Stempel shift adalah TAMBAHAN pada
   * penyimpanan — ia tidak boleh menjadi cara baru sebuah penyimpanan gagal.
   */
  it('tidak melempar ketika query memulangkan undefined', async () => {
    vi.mocked(query).mockResolvedValue(undefined as never)

    await expect(shiftRepo.stampShiftOnRecord('process_water_record', 'rec-1', pukul(8))).resolves.toBeNull()
  })
})

describe('fetchAndCacheShiftsForProductionLine', () => {
  it('membuang baris line itu lebih dulu, lalu menulis ulang dari respons', async () => {
    vi.doMock('@/services/apiClient', () => ({
      default: {
        get: vi.fn(async () => ({
          data: { data: [{ id: 'pagi', name: 'Pagi', start_time: '07:00', end_time: '15:00' }] },
        })),
      },
    }))

    await shiftRepo.fetchAndCacheShiftsForProductionLine(LINE)

    const sqls = vi.mocked(run).mock.calls.map((call) => String(call[0]))

    // DELETE LEBIH DULU, bukan upsert murni: shift yang dihapus Admin tidak
    // muncul lagi di respons, dan upsert murni akan meninggalkannya hidup di
    // perangkat selamanya.
    expect(sqls[0]).toContain('DELETE FROM shift WHERE production_line_id = ?')
    expect(sqls[1]).toContain('INSERT INTO shift')
    expect(vi.mocked(run).mock.calls[1]?.[1]).toEqual([
      'pagi',
      LINE,
      'Pagi',
      '07:00',
      '15:00',
      expect.any(String),
    ])
  })
})
