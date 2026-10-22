import type { Page } from '@playwright/test'
import { statefulHeaders } from './periods'

/**
 * Prasyarat data untuk tests/jadwal-shift.spec.ts (screen-171--jadwal-shift).
 *
 * Konvensi yang sama dengan ./shifts.ts: `page.request` MEWARISI cookie sesi
 * dari konteks browser setelah login lewat UI, ditambah statefulHeaders()
 * (Referer + X-XSRF-TOKEN). Yang DIUJI spec-nya dijalankan lewat UI; yang
 * hanya PRASYARAT (menanam penugasan, membuat user, menutup shift) lewat API.
 *
 * ── USER BANTU ──────────────────────────────────────────────────────────
 *
 * Seeder tidak memiliki user berperan Engineer maupun user yang dinonaktifkan
 * setelah ditugaskan. Keduanya dibuat lewat POST /api/users (Admin-only) dengan
 * username berawalan `jdtest-` dan dipakai ulang antar-run (dicari dulu, baru
 * dibuat). Tidak ada endpoint hapus user, jadi mereka tetap ada setelah suite
 * selesai: engineer tetap aktif (ia bukan calon bagaimanapun), user
 * `jdtest-nonaktif01` ditinggal nonaktif.
 */

export const HELPER_PASSWORD = 'Passw0rd!'

export interface ApiResult<T = any> {
  status: number
  body: T
}

async function send<T>(
  page: Page,
  method: 'get' | 'post' | 'put' | 'patch' | 'delete',
  url: string,
  data?: unknown,
): Promise<ApiResult<T>> {
  const headers = await statefulHeaders(page)
  const response = await page.request[method](url, data === undefined ? { headers } : { headers, data })
  let body: unknown = null

  try {
    body = await response.json()
  } catch {
    body = null
  }

  return { status: response.status(), body: body as T }
}

function must<T>(result: ApiResult<T>, what: string, expected = 200): T {
  if (result.status !== expected) {
    throw new Error(`[shift-schedules] ${what} menjawab ${result.status} (diharapkan ${expected}): ${JSON.stringify(result.body)}`)
  }

  return result.body
}

export interface Candidate {
  user_id: string
  name: string
  role_label: string
  already_on_shifts: { shift_id: string; shift_name: string }[]
}

export async function candidatesOf(page: Page, productionLineId: string, date?: string): Promise<{ data: Candidate[]; empty_reason: string | null }> {
  const query = new URLSearchParams({ production_line_id: productionLineId })
  if (date) query.set('date', date)

  return must(await send(page, 'get', `/api/shift-schedules/candidates?${query.toString()}`), 'GET candidates')
}

/** PUT /api/shift-schedules/cell. Mengembalikan hasil mentah (status + body). */
export async function putCell(
  page: Page,
  cell: { shiftId: string; date: string; userIds: string[]; loadedUserIds?: string[] },
): Promise<ApiResult> {
  return send(page, 'put', '/api/shift-schedules/cell', {
    shift_id: cell.shiftId,
    date: cell.date,
    user_ids: cell.userIds,
    ...(cell.loadedUserIds ? { loaded_user_ids: cell.loadedUserIds } : {}),
  })
}

/** Menanam isi sel sebagai PRASYARAT; gagal bila ditolak. */
export async function seedCell(page: Page, shiftId: string, date: string, userIds: string[]): Promise<void> {
  must(await putCell(page, { shiftId, date, userIds }), `PUT cell ${date}`)
}

export interface GridCell {
  shift_id: string
  date: string
  editable: boolean
  assignees: { user_id: string; name: string; is_active: boolean }[]
}

export interface GridData {
  state: 'ready' | 'no_live_shift'
  month: string
  days: { date: string }[]
  shifts: { id: string; name: string; closed: boolean; closed_from: string | null }[]
  cells: GridCell[]
  summary: { total_cells: number; filled_cells: number; empty_cells: number; text: string }
}

export async function gridOf(page: Page, productionLineId: string, month: string): Promise<GridData> {
  const body = must<{ data: GridData }>(
    await send(page, 'get', `/api/shift-schedules?production_line_id=${productionLineId}&month=${month}`),
    'GET grid',
  )

  return body.data
}

export function cellOf(grid: GridData, shiftId: string, date: string): GridCell | undefined {
  return grid.cells.find((cell) => cell.shift_id === shiftId && cell.date === date)
}

export async function rawGet(page: Page, url: string): Promise<ApiResult> {
  return send(page, 'get', url)
}

/** DELETE /api/shifts/{id} — soft delete: menutup shift. */
export async function closeShift(page: Page, shiftId: string): Promise<void> {
  must(await send(page, 'delete', `/api/shifts/${shiftId}`), 'DELETE shift')
}

/** PATCH /api/shifts/{id} — mengubah jam, nama dan line dipertahankan. */
export async function changeShiftHours(
  page: Page,
  shift: { id: string; productionLineId: string; name: string },
  start: string,
  end: string,
): Promise<void> {
  must(
    await send(page, 'patch', `/api/shifts/${shift.id}`, {
      production_line_id: shift.productionLineId,
      name: shift.name,
      start_time: start,
      end_time: end,
    }),
    'PATCH shift',
  )
}

interface UserRow {
  id: string
  username: string
  name: string
  role: string
  business_unit_id: string | null
  is_active: boolean
}

export async function userByUsername(page: Page, username: string, role: string, businessUnitId: string): Promise<UserRow | undefined> {
  const query = new URLSearchParams({ role, business_unit_id: businessUnitId, per_page: '100' })
  const body = must<{ data: UserRow[] }>(await send(page, 'get', `/api/users?${query.toString()}`), `GET users role=${role}`)

  return body.data.find((row) => row.username === username)
}

/** Seeded account → row; error that names the fixture when missing. */
export async function seededUser(page: Page, username: string, role: string, businessUnitId: string): Promise<UserRow> {
  const row = await userByUsername(page, username, role, businessUnitId)

  if (!row) {
    throw new Error(`[shift-schedules] user "${username}" (${role}) tidak ada pada mill ini. Jalankan BrowserTestFixtureSeeder (npm run db:prepare).`)
  }

  return row
}

export async function setUserActive(page: Page, userId: string, active: boolean): Promise<void> {
  must(await send(page, 'patch', `/api/users/${userId}/status`, { is_active: active }), 'PATCH user status')
}

/** Membuat (atau memakai ulang) user bantu pada mill itu; selalu mengembalikan barisnya dalam keadaan AKTIF. */
export async function ensureHelperUser(
  page: Page,
  user: { username: string; name: string; role: string; businessUnitId: string },
): Promise<UserRow> {
  let row = await userByUsername(page, user.username, user.role, user.businessUnitId)

  if (!row) {
    must(
      await send(page, 'post', '/api/users', {
        username: user.username,
        name: user.name,
        role: user.role,
        business_unit_id: user.businessUnitId,
        password: HELPER_PASSWORD,
      }),
      `POST user ${user.username}`,
      201,
    )
    row = await userByUsername(page, user.username, user.role, user.businessUnitId)
  }

  if (!row) {
    throw new Error(`[shift-schedules] user bantu ${user.username} tidak ditemukan setelah dibuat`)
  }

  if (!row.is_active) {
    await setUserActive(page, row.id, true)
    row.is_active = true
  }

  return row
}
