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
import {
  activeStationStatus,
  cacheStasiunSegar,
  galatStasiunTidakAktif,
  readRememberedProductionLineId,
  readRememberedProductionLineName,
  rememberProductionLineName,
  resolveActiveStationId,
  stationTypeFromFormRoute,
  tandaiCacheStasiunSegar,
} from '@/services/activeStation'

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

/*
 * TEMUAN QA MB-HOME-03 — nama line ikut diingat, bukan hanya id-nya, karena
 * layar Monitor mencetak namanya dan harus bisa melakukannya offline
 * sementara daftar Production Line tidak di-cache lokal.
 */
describe('ingatan NAMA Production Line', () => {
  const KEY_NAMA = `msl_production_line_name_${USER_ID}`

  it('menyimpan dan membaca kembali nama yang diingat', () => {
    rememberProductionLineName(USER_ID, 'Line 02')

    expect(window.localStorage.getItem(KEY_NAMA)).toBe('Line 02')
    expect(readRememberedProductionLineName(USER_ID)).toBe('Line 02')
  })

  it('tidak menyimpan nama kosong atau hanya spasi', () => {
    rememberProductionLineName(USER_ID, '   ')

    expect(window.localStorage.getItem(KEY_NAMA)).toBeNull()
    expect(readRememberedProductionLineName(USER_ID)).toBeNull()
  })

  it('mengembalikan null tanpa userId — pilihan satu akun tidak boleh terbawa ke akun lain', () => {
    rememberProductionLineName(null, 'Line 02')

    expect(readRememberedProductionLineName(null)).toBeNull()
    expect(readRememberedProductionLineName(USER_ID)).toBeNull()
  })
})

/*
 * TEMUAN QA MB-FORM-15. Mobile dulu MENERIMA dan menyimpan record pada
 * Production Line yang tidak punya stasiun jenis itu dalam keadaan aktif —
 * station_id null, status "Tersimpan", tanpa satu pesan pun — sementara layar
 * web menolak keadaan yang sama dengan pesan yang tepat.
 *
 * Yang dijaga di sini adalah GARIS PEMISAHNYA: menolak hanya ketika
 * jawabannya DIKETAHUI "tidak ada", dan tetap mengizinkan ketika kita memang
 * tidak tahu. Menolak pada keadaan "tidak tahu" akan mematahkan pencatatan
 * offline di perangkat baru, yaitu inti aplikasi ini.
 */
describe('activeStationStatus()', () => {
  it('"ada" ketika line punya stasiun jenis itu dalam keadaan aktif', async () => {
    window.localStorage.setItem(KEY, LINE_ID)
    vi.mocked(query).mockResolvedValueOnce([{ id: 'station-1' }])

    await expect(activeStationStatus('depricarping', USER_ID)).resolves.toBe('ada')
  })

  it('"tidak-aktif" ketika line punya stasiun LAIN ter-cache tetapi tidak satu pun jenis itu yang aktif', async () => {
    window.localStorage.setItem(KEY, LINE_ID)
    vi.mocked(query)
      .mockResolvedValueOnce([]) // tidak ada yang aktif untuk jenis ini
      .mockResolvedValueOnce([{ id: 'station-lain' }]) // line ini memang punya baris

    await expect(activeStationStatus('depricarping', USER_ID)).resolves.toBe('tidak-aktif')
  })

  it('"tidak-diketahui" ketika cache stasiun untuk line itu masih kosong (perangkat baru / offline)', async () => {
    window.localStorage.setItem(KEY, LINE_ID)
    vi.mocked(query).mockResolvedValueOnce([]).mockResolvedValueOnce([])

    await expect(activeStationStatus('depricarping', USER_ID)).resolves.toBe('tidak-diketahui')
  })

  it('"tidak-diketahui" ketika belum ada line yang dipilih, tanpa menyentuh basis data', async () => {
    await expect(activeStationStatus('depricarping', USER_ID)).resolves.toBe('tidak-diketahui')
    expect(vi.mocked(query)).not.toHaveBeenCalled()
  })

  it('"tidak-diketahui" ketika query melempar — tidak pernah menghalangi karena galat lokal', async () => {
    window.localStorage.setItem(KEY, LINE_ID)
    vi.mocked(query).mockRejectedValueOnce(new Error('sqlite mati'))

    await expect(activeStationStatus('depricarping', USER_ID)).resolves.toBe('tidak-diketahui')
  })
})

describe('stationTypeFromFormRoute()', () => {
  it('menurunkan jenis stasiun dari nama rute Form', () => {
    expect(stationTypeFromFormRoute('depricarping-form')).toBe('depricarping')
    expect(stationTypeFromFormRoute('process-quality-control-form')).toBe('process-quality-control')
  })

  it('mengembalikan null untuk rute yang bukan layar Form', () => {
    expect(stationTypeFromFormRoute('depricarping-monitor')).toBeNull()
    expect(stationTypeFromFormRoute('-form')).toBeNull()
    expect(stationTypeFromFormRoute(undefined)).toBeNull()
  })
})

describe('galatStasiunTidakAktif()', () => {
  it('memulangkan pesan yang menyebut jenis stasiunnya, sama dengan kalimat layar web', async () => {
    window.localStorage.setItem(KEY, LINE_ID)
    vi.mocked(query)
      .mockResolvedValueOnce([])
      .mockResolvedValueOnce([{ id: 'station-lain' }])

    await expect(galatStasiunTidakAktif('process-quality-control-form', USER_ID)).resolves.toBe(
      'Production Line yang dipilih belum memiliki station Process Quality Control yang aktif.',
    )
  })

  it('memulangkan null ketika stasiunnya ada', async () => {
    window.localStorage.setItem(KEY, LINE_ID)
    vi.mocked(query).mockResolvedValueOnce([{ id: 'station-1' }])

    await expect(galatStasiunTidakAktif('depricarping-form', USER_ID)).resolves.toBeNull()
  })

  it('memulangkan null ketika keadaannya tidak diketahui — menyimpan offline tetap boleh', async () => {
    window.localStorage.setItem(KEY, LINE_ID)
    vi.mocked(query).mockResolvedValueOnce([]).mockResolvedValueOnce([])

    await expect(galatStasiunTidakAktif('depricarping-form', USER_ID)).resolves.toBeNull()
  })
})

/*
 * Garis pemisah antara "line ini memang tanpa stasiun" dan "stasiunnya belum
 * disinkronkan" — dari jumlah baris saja keduanya nol, dan keduanya menuntut
 * jawaban yang berlawanan. Penanda cache-segar itulah yang membedakannya
 * (QA MB-FORM-15: line tanpa stasiun sama sekali, yang justru keadaan yang
 * diuji, tidak punya satu baris pun untuk dijadikan petunjuk).
 */
describe('penanda cache stasiun segar', () => {
  it('"tidak-aktif" ketika cache line itu sudah segar meski NOL baris stasiun', async () => {
    window.localStorage.setItem(KEY, LINE_ID)
    tandaiCacheStasiunSegar(LINE_ID)
    vi.mocked(query).mockResolvedValueOnce([]) // tidak ada stasiun jenis ini

    await expect(activeStationStatus('depricarping', USER_ID)).resolves.toBe('tidak-aktif')
  })

  it('"tidak-diketahui" ketika cache line itu belum pernah ditandai segar', async () => {
    window.localStorage.setItem(KEY, LINE_ID)
    vi.mocked(query).mockResolvedValueOnce([]).mockResolvedValueOnce([])

    expect(cacheStasiunSegar(LINE_ID)).toBe(false)
    await expect(activeStationStatus('depricarping', USER_ID)).resolves.toBe('tidak-diketahui')
  })

  it('penanda satu line tidak terbawa ke line lain', () => {
    tandaiCacheStasiunSegar(LINE_ID)

    expect(cacheStasiunSegar(LINE_ID)).toBe(true)
    expect(cacheStasiunSegar('line-lain')).toBe(false)
  })
})
