/**
 * activeStation.spec.ts — src/services/activeStation.ts.
 *
 * APA YANG DIJAGA BERKAS INI. Sampai 2026-10-02 `station_id` pada record lokal
 * selalu null — tidak satu pun dari 18 `createDraft()` mengisinya — sehingga
 * Production Line baru menempel SAAT SYNC, dari pilihan yang kebetulan aktif di
 * Station List. Draft yang di-pause di Line 1 lalu disinkronkan setelah pilihan
 * berpindah ke Line 2 tercatat sebagai data Line 2, dan tidak ada apa pun di data
 * yang merekam di mana ia sebenarnya dihasilkan.
 *
 * Resolver ini menutupnya, dan test di bawah menjaga tiga hal yang sama
 * pentingnya: bahwa ia MENEMUKAN stasiun yang benar, bahwa ia TIDAK MENEBAK
 * ketika bahannya kurang, dan bahwa ia TIDAK PERNAH MELEMPAR — pembuatan draft
 * harus tetap berhasil offline, dan operator yang tidak bisa mulai mencatat jauh
 * lebih buruk daripada draft tanpa stasiun.
 *
 * `@/services/localDb` di-mock; resolver-nya sendiri berjalan sungguhan, jadi
 * asersi tentang SQL dan parameter yang dikirim benar-benar membuktikan sesuatu.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/services/localDb', () => ({
  query: vi.fn(),
  run: vi.fn(),
}))

import { query } from '@/services/localDb'
import { readRememberedProductionLineId, resolveActiveStationId } from '@/services/activeStation'

const USER_ID = 'user-1'
const LINE_ID = 'line-1'
const KEY = `msl_production_line_${USER_ID}`

beforeEach(() => {
  vi.clearAllMocks()
  window.localStorage.clear()
})

describe('readRememberedProductionLineId()', () => {
  it('mengembalikan line yang disimpan Station List untuk user itu', () => {
    window.localStorage.setItem(KEY, LINE_ID)

    expect(readRememberedProductionLineId(USER_ID)).toBe(LINE_ID)
  })

  it('tidak membaca line milik user lain', () => {
    window.localStorage.setItem('msl_production_line_user-2', 'line-lain')

    expect(readRememberedProductionLineId(USER_ID)).toBeNull()
  })

  it('mengembalikan null tanpa userId', () => {
    window.localStorage.setItem(KEY, LINE_ID)

    expect(readRememberedProductionLineId(null)).toBeNull()
    expect(readRememberedProductionLineId(undefined)).toBeNull()
  })

  it('tidak melempar ketika localStorage menolak (mode privat)', () => {
    const spy = vi.spyOn(window.localStorage, 'getItem').mockImplementation(() => {
      throw new Error('SecurityError')
    })

    expect(() => readRememberedProductionLineId(USER_ID)).not.toThrow()
    expect(readRememberedProductionLineId(USER_ID)).toBeNull()

    spy.mockRestore()
  })
})

describe('resolveActiveStationId()', () => {
  it('mencari stasiun AKTIF berjenis itu pada line yang dipilih, dan mengembalikan id-nya', async () => {
    window.localStorage.setItem(KEY, LINE_ID)
    vi.mocked(query).mockResolvedValueOnce([{ id: 'station-9' }])

    await expect(resolveActiveStationId('boiler-room', USER_ID)).resolves.toBe('station-9')

    const [sql, params] = vi.mocked(query).mock.calls[0]
    expect(sql).toContain('FROM station')
    expect(sql).toContain('production_line_id = ?')
    expect(sql).toContain('type = ?')
    // is_active DISARING DI SQL, bukan di klien: stasiun nonaktif tidak boleh
    // pernah menjadi tempat sebuah draft dibuat.
    expect(sql).toContain('is_active = 1')
    expect(params).toEqual([LINE_ID, 'boiler-room'])
  })

  it('mengembalikan null TANPA menyentuh database bila belum ada line dipilih', async () => {
    await expect(resolveActiveStationId('boiler-room', USER_ID)).resolves.toBeNull()

    // Bukan sekadar "hasilnya null": tidak boleh ada query sama sekali, karena
    // tanpa line tidak ada pertanyaan yang bisa diajukan.
    expect(query).not.toHaveBeenCalled()
  })

  it('mengembalikan null bila line terpilih tidak punya stasiun jenis itu', async () => {
    window.localStorage.setItem(KEY, LINE_ID)
    vi.mocked(query).mockResolvedValueOnce([])

    await expect(resolveActiveStationId('weighbridge', USER_ID)).resolves.toBeNull()
  })

  it('TIDAK MENEBAK stasiun line lain — jenis dan line keduanya ikut dalam query', async () => {
    window.localStorage.setItem(KEY, LINE_ID)
    vi.mocked(query).mockResolvedValueOnce([{ id: 'station-9' }])

    await resolveActiveStationId('cages-track', USER_ID)

    const [, params] = vi.mocked(query).mock.calls[0]
    expect(params).toEqual([LINE_ID, 'cages-track'])
  })

  it('tidak melempar ketika database lokal gagal — draft tetap bisa dibuat', async () => {
    window.localStorage.setItem(KEY, LINE_ID)
    vi.mocked(query).mockRejectedValueOnce(new Error('no such table: station'))

    await expect(resolveActiveStationId('sterilizer', USER_ID)).resolves.toBeNull()
  })

  it('tidak melempar ketika query mengembalikan undefined', async () => {
    window.localStorage.setItem(KEY, LINE_ID)
    vi.mocked(query).mockResolvedValueOnce(undefined as never)

    await expect(resolveActiveStationId('pressing', USER_ID)).resolves.toBeNull()
  })
})
