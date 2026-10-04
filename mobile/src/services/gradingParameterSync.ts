import apiClient from '@/services/apiClient'
import { query, run } from '@/services/localDb'

/**
 * gradingParameterSync — menyelaraskan master Quality Parameter lokal dengan
 * id ASLI di server (audit 2026-10-04, KRITIS).
 *
 * Masalahnya: localSchema.seedGradingParametersIfNeeded() mengisi tabel
 * `grading_parameter` dengan id buatan `default-grading-parameter-N`. Id itu
 * ikut tersimpan di `grading_detail.grading_parameter_id` dan dikirim apa
 * adanya ke POST /api/grading-records — server (GradingRecordService::
 * validateDetails) mencarinya sebagai UUID di PostgreSQL → 22P02 → 500.
 * Setiap sinkronisasi Grading gagal.
 *
 * Perbaikannya dua lapis:
 *   1. `fetchAndCacheGradingParameters()` mengambil master dari server
 *      (GET /api/grading-parameters), memetakan baris lokal ke baris server
 *      BERDASARKAN NAMA (case-insensitive), memindahkan rujukan
 *      grading_detail dari id buatan ke id server, lalu membuang baris
 *      buatan yang sudah terpetakan. Dipanggil saat login dan tepat sebelum
 *      sinkronisasi Grading.
 *   2. `resolveServerGradingParameterIds()` dipakai syncService saat
 *      menyusun payload: id buatan yang masih tersisa dipetakan lewat nama;
 *      bila tetap tidak bisa, record DITOLAK DI PERANGKAT dengan alasan
 *      yang jelas — tidak pernah lagi dikirim id palsu ke server.
 */

export const FAKE_GRADING_PARAMETER_PREFIX = 'default-grading-parameter-'

export function isFakeGradingParameterId(id: string | null | undefined): boolean {
  return typeof id === 'string' && id.startsWith(FAKE_GRADING_PARAMETER_PREFIX)
}

interface ServerGradingParameter {
  id: string
  name: string
  uom: string
  sort_order: number | null
}

interface LocalGradingParameterRow {
  id: string
  name: string
}

function normalizeName(name: string | null | undefined): string {
  return (name ?? '').trim().toLowerCase()
}

/**
 * Ambil master Quality Parameter dari server dan selaraskan cache lokal.
 * Melempar bila request gagal (offline / endpoint belum ada) — pemanggil
 * memperlakukannya sebagai best-effort.
 */
export async function fetchAndCacheGradingParameters(): Promise<number> {
  const response = await apiClient.get('/api/grading-parameters')
  const body = response.data as { data?: ServerGradingParameter[] } | ServerGradingParameter[]
  const serverRows = (Array.isArray(body) ? body : (body?.data ?? [])).filter(
    (row): row is ServerGradingParameter => Boolean(row && row.id && row.name),
  )

  if (serverRows.length === 0) {
    return 0
  }

  const localRows = await query<LocalGradingParameterRow>(`SELECT id, name FROM grading_parameter`)
  const now = new Date().toISOString()

  for (const server of serverRows) {
    await run(
      `INSERT INTO grading_parameter (id, name, uom, sort_order, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?)
       ON CONFLICT(id) DO UPDATE SET name = excluded.name, uom = excluded.uom,
         sort_order = excluded.sort_order, updated_at = excluded.updated_at`,
      [server.id, server.name, server.uom, server.sort_order ?? 0, now, now],
    )

    // Pindahkan rujukan dari baris lokal BUATAN yang bernama sama.
    for (const local of localRows) {
      if (local.id === server.id || !isFakeGradingParameterId(local.id)) continue
      if (normalizeName(local.name) !== normalizeName(server.name)) continue

      await run(`UPDATE grading_detail SET grading_parameter_id = ? WHERE grading_parameter_id = ?`, [server.id, local.id])
      await run(`DELETE FROM grading_parameter WHERE id = ?`, [local.id])
    }
  }

  return serverRows.length
}

export type GradingParameterResolution =
  | { ok: true; ids: Map<string, string> }
  | { ok: false; reason: string }

/**
 * Petakan setiap id parameter yang dirujuk detail ke id server. Id yang
 * sudah id server dibiarkan; id buatan dicari lewat namanya di antara baris
 * non-buatan. Gagal (dengan alasan) bila ada yang tidak terpetakan.
 */
export async function resolveServerGradingParameterIds(
  parameterIds: Array<string | null>,
): Promise<GradingParameterResolution> {
  const ids = new Map<string, string>()
  const fakeIds = [...new Set(parameterIds.filter((id): id is string => isFakeGradingParameterId(id)))]

  for (const id of parameterIds) {
    if (id && !isFakeGradingParameterId(id)) ids.set(id, id)
  }

  if (fakeIds.length === 0) {
    return { ok: true, ids }
  }

  const rows = await query<LocalGradingParameterRow>(`SELECT id, name FROM grading_parameter`)
  const nameById = new Map(rows.map((row) => [row.id, row.name]))
  const serverIdByName = new Map(
    rows.filter((row) => !isFakeGradingParameterId(row.id)).map((row) => [normalizeName(row.name), row.id]),
  )

  for (const fakeId of fakeIds) {
    const serverId = serverIdByName.get(normalizeName(nameById.get(fakeId)))
    if (!serverId) {
      return {
        ok: false,
        reason:
          'Master Quality Parameter belum tersinkron dari server — data Grading ini belum bisa dikirim. ' +
          'Pastikan online lalu coba sinkronisasi lagi.',
      }
    }
    ids.set(fakeId, serverId)
  }

  return { ok: true, ids }
}

export default { fetchAndCacheGradingParameters, resolveServerGradingParameterIds, isFakeGradingParameterId }
