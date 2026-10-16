import type { Page } from '@playwright/test'
import { statefulHeaders } from './periods'

/**
 * Prasyarat dan pembersihan data Shift untuk tests/kelola-shift.spec.ts
 * (screen-170--kelola-shift).
 *
 * ── MENGAPA LEWAT API, DAN MENGAPA BUKAN SEEDER BARU ────────────────────
 *
 * Mengikuti konvensi yang sudah ada di suite ini — tests/support/periods.ts
 * (pembersihan) dan tests/support/period-fixture.ts (prasyarat) — bukan
 * mekanisme baru: `page.request`, yang MEWARISI COOKIE SESI dari konteks
 * browser setelah login lewat UI, ditambah header dari statefulHeaders()
 * (Referer + X-XSRF-TOKEN; Playwright tidak mengirim Referer sendiri, dan
 * tanpa itu Sanctum menjatuhkan permintaan ke jalur token lalu menjawab 401).
 * Penjagaan peran tetap berlaku sepenuhnya: /api/shifts/* adalah Admin-only.
 *
 * Yang DIUJI spec-nya dijalankan lewat UI — menambah, mengubah, menghapus,
 * pratinjau. Yang hanya PRASYARAT dijalankan lewat API: menyapu bersih shift
 * sebelum setiap test, dan menanam dua puluh satu shift untuk skenario
 * paginasi (dua puluh satu kali mengisi form modal akan memakan menit tanpa
 * membuktikan apa pun yang belum dibuktikan skenario 1).
 *
 * ── MENGAPA DISAPU BERSIH, BUKAN DIBERI NAMA BER-AWALAN PER RUN ─────────
 *
 * `shifts` punya unique index (production_line_id, name) DAN aturan tumpang
 * tindih di lapisan aplikasi. Sebuah run yang mati sebelum pembersihannya
 * jalan meninggalkan 'Pagi' 07:00–15:00 di line yang sama, sehingga run
 * berikutnya ditolak VALIDATION_ERROR atau SHIFT_OVERLAP — bukan sebagai
 * kegagalan produk, melainkan sebagai kegagalan yang terbaca seperti
 * kegagalan produk. Menyapu bersih lebih dulu membuat nama 'Pagi' / 'Siang' /
 * 'Malam' dapat dipakai apa adanya, sehingga asersinya terbaca seperti
 * kalimat spec-nya.
 *
 * SPEC INI SATU-SATUNYA YANG MEMBUAT SHIFT. Kalau kelak ada spec lain yang
 * juga menanam shift di "BU Browser Test", sapuan di bawah harus dipersempit
 * ke daftar line-nya sendiri (parameter `lineNames` sudah ada untuk itu).
 */

/** Sama dengan Pagination::MAX_PER_PAGE di backend. */
const MAX_PER_PAGE = 100

export interface ShiftBlock {
  kind: 'shift' | 'gap'
  shift_id: string | null
  label: string | null
  /** Menit-ke-dalam-hari, 0..1440, diukur dari 00:00. */
  start_offset_minutes: number
  duration_minutes: number
  time_label: string
  /** true pada KEDUA potongan shift yang melintasi titik awal sumbu. */
  crosses_midnight: boolean
  /** 0 = potongan tempat shift DIMULAI; 1 hanya ada pada shift lintas-batas. */
  segment_index: number
  segment_count: number
  /** Potongan terpanjang — tempat nama shift ditulis. */
  is_label_segment: boolean
}

export interface ShiftRow {
  id: string
  production_line_id: string
  production_line_name: string
  name: string
  start_time: string
  end_time: string
  crosses_midnight: boolean
  duration_minutes: number
  start_offset_minutes: number
  position: number
  time_label: string
}

export interface CoverageRow {
  production_line_id: string
  production_line_name: string
  shift_count: number
  state: 'covered' | 'gap' | 'unconfigured'
  uncovered_minutes: number
  uncovered_hours_text: string
  blocks: ShiftBlock[]
}

interface Option {
  id: string
  name: string
}

async function okJson<T>(page: Page, url: string): Promise<T> {
  const response = await page.request.get(url, { headers: await statefulHeaders(page) })

  if (!response.ok()) {
    throw new Error(`[shifts] GET ${url} menjawab ${response.status()}: ${await response.text()}`)
  }

  return (await response.json()) as T
}

/** Id mill berdasarkan namanya, dibaca dari endpoint options layar ini. */
export async function businessUnitIdByName(page: Page, name: string): Promise<string> {
  const body = await okJson<{ data: Option[] }>(page, '/api/shifts/business-units/options')
  const match = body.data.find((option) => option.name === name)

  if (!match) {
    throw new Error(
      `[shifts] mill "${name}" tidak ada. Jalankan BrowserTestFixtureSeeder `
      + `(e2e-web: npm run db:prepare). Mill yang ada: ${body.data.map((o) => o.name).join(', ')}`,
    )
  }

  return match.id
}

/** Seluruh Production Line satu mill, urut nama — sama dengan filter di layar. */
export async function productionLineOptions(page: Page, businessUnitId: string): Promise<Option[]> {
  const body = await okJson<{ data: Option[] }>(
    page,
    `/api/shifts/production-lines/options?business_unit_id=${businessUnitId}`,
  )

  return body.data
}

export async function productionLineIdByName(
  page: Page,
  businessUnitId: string,
  name: string,
): Promise<string> {
  const options = await productionLineOptions(page, businessUnitId)
  const match = options.find((option) => option.name === name)

  if (!match) {
    throw new Error(
      `[shifts] Production Line "${name}" tidak ada pada mill ini. `
      + `Line yang ada: ${options.map((o) => o.name).join(', ')}`,
    )
  }

  return match.id
}

/**
 * GET /api/shifts — dipakai untuk MENYIAPKAN dan MEMERIKSA ULANG, tidak untuk
 * menggantikan asersi DOM: invarian geometris spec ini justru ada karena
 * payload yang benar masih bisa tergambar salah.
 */
export async function listShifts(
  page: Page,
  businessUnitId: string,
  productionLineId?: string,
  options: { page?: number; perPage?: number } = {},
): Promise<{ data: ShiftRow[]; coverage: CoverageRow[]; meta: Record<string, number> }> {
  const query = new URLSearchParams({ business_unit_id: businessUnitId })

  if (productionLineId) query.set('production_line_id', productionLineId)
  if (options.page) query.set('page', String(options.page))
  if (options.perPage) query.set('per_page', String(options.perPage))

  return okJson(page, `/api/shifts?${query.toString()}`)
}

/**
 * Menanam satu shift. Memulangkan id-nya, supaya pemanggil dapat menyasar
 * elemen `timeline-block-{id}` / `shift-row-{id}` tanpa menebak.
 */
export async function seedShift(
  page: Page,
  shift: { productionLineId: string; name: string; start: string; end: string },
): Promise<string> {
  const response = await page.request.post('/api/shifts', {
    headers: await statefulHeaders(page),
    data: {
      production_line_id: shift.productionLineId,
      name: shift.name,
      start_time: shift.start,
      end_time: shift.end,
    },
  })

  if (!response.ok()) {
    throw new Error(
      `[shifts] POST /api/shifts untuk "${shift.name}" ${shift.start}–${shift.end} `
      + `menjawab ${response.status()}: ${await response.text()}`,
    )
  }

  const body = (await response.json()) as { data: { id: string } }

  return body.data.id
}

/**
 * Menyapu bersih shift pada satu mill — seluruh line, atau hanya line yang
 * disebut. Dipanggil dari beforeEach spec-nya: lihat docblock di atas untuk
 * alasan mengapa penyapuan, bukan penamaan ber-awalan.
 *
 * TIDAK PERNAH menggagalkan test: ia prasyarat, bukan subjek. Kegagalan
 * dicatat ke console supaya tetap terbaca di run yang sama.
 */
export async function clearShifts(
  page: Page,
  businessUnitId: string,
  lineNames?: string[],
): Promise<number> {
  let deleted = 0

  try {
    const headers = await statefulHeaders(page)
    const body = await listShifts(page, businessUnitId, undefined, { perPage: MAX_PER_PAGE })

    const targets = lineNames
      ? body.data.filter((row) => lineNames.includes(row.production_line_name))
      : body.data

    for (const row of targets) {
      const removed = await page.request.delete(`/api/shifts/${row.id}`, { headers })

      if (removed.ok()) {
        deleted += 1
        continue
      }

      console.warn(
        `[shifts] gagal menghapus "${row.name}" pada ${row.production_line_name} `
        + `(${removed.status()})`,
      )
    }
  } catch (error) {
    console.warn('[shifts] penyapuan gagal:', error)
  }

  return deleted
}
