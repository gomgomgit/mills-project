import apiClient, { type NormalizedApiError } from '@/services/apiClient'
import { query, run } from '@/services/localDb'
import { useAuthStore } from '@/stores/auth'

/**
 * syncService — TEMPORARY/pragmatic bridge (2026-08-20) so Weighbridge/
 * Grading/Cages Track records entered on mobile (offline-first, local
 * SQLite only) become visible on the web app (backend DB). Not the final
 * sync architecture (no conflict resolution, no background/periodic sync,
 * no retry queue) — a manual "Sinkronisasi" button the user triggers from
 * Station List (screen-006), per explicit request.
 *
 * Order matters: Weighbridge MUST sync before Grading, because
 * POST /api/grading-records' `weighbridge_record_id` validates
 * `exists:weighbridge_records,id` against a REAL backend id — and the
 * backend always assigns its own new UUID on create (WeighbridgeRecord
 * uses Eloquent's HasUuids, the client-supplied local id is never sent/
 * accepted). Each local record's `server_id` column (see localSchema.ts's
 * migrateRecordTablesForSync()) stores that backend-assigned id once
 * synced, so a Grading record's local `weighbridge_record_id` (a LOCAL id)
 * can be resolved to the matching server id — whether that Weighbridge
 * record was just synced in this same batch, or synced in an earlier run.
 * If it was never synced (still a local-only draft, or failed to sync just
 * now), the Grading record is reported as failed with a clear reason
 * rather than sent with an invalid/missing weighbridge_record_id.
 *
 * Cages Track has no such cross-reference and syncs independently.
 *
 * station_id is deliberately NEVER sent — mobile's local `station` rows use
 * synthetic ids unrelated to real backend Station UUIDs (see
 * localSchema.ts's CREATE_STATION comment). Each backend create() instead
 * resolves the target Station itself from `production_line_id` + record
 * type (the same "1 active station per type per production line" pattern
 * used by MillSettingService's old business_unit_id-based lookup), so this
 * sidesteps the synthetic-id problem entirely — confirmed directly against
 * WeighbridgeRecordService/GradingRecordService/CagesTrackRecordService::
 * create() before relying on it.
 *
 * production_line_id (2026-08-20, entity-catalog v9 Production Line
 * feature): the backend's create() endpoints now resolve the target
 * Station from `production_line_id`, not `business_unit_id` — a Business
 * Unit/mill can have several Production Lines, so a single mill-wide
 * business_unit_id can no longer unambiguously resolve which Station to
 * use. `syncAllRecords()` takes `productionLineId` as a required argument
 * (the Production Line selected on Station List's new picker step —
 * StationListView.vue passes it through) rather than reading it from the
 * auth store, since — unlike business_unit_id — it is not a property of
 * the logged-in user, it is a per-visit UI selection.
 */

export interface SyncItemResult {
  id: string
  label: string
  ok: boolean
  reason?: string
}

export interface SyncSummary {
  weighbridge: SyncItemResult[]
  grading: SyncItemResult[]
  cagesTrack: SyncItemResult[]
  syncedCount: number
  failedCount: number
}

interface LocalWeighbridgeRow {
  id: string
  wb_card_number: string | null
  weighbridge_type: string | null
  record_datetime: string | null
  vehicle_number: string | null
  driver_name: string | null
  estate_supplier: string | null
  destination: string | null
  division: string | null
  block: string | null
  gross_weight: number | null
  tare_weight: number | null
  quantity: number | null
  checked_by: string | null
  acknowledged_by: string | null
  server_id: string | null
}

interface LocalGradingRow {
  id: string
  grading_number: string | null
  date: string | null
  weighbridge_record_id: string | null
  license_plate_no: string | null
  vehicle_code: string | null
  estate_supplier: string | null
  division: string | null
  netto: number | null
  quantity: number | null
  note: string | null
  checked_by: string | null
  acknowledged_by: string | null
  server_id: string | null
}

interface LocalGradingDetailRow {
  grading_parameter_id: string | null
  quantity: number | null
}

interface LocalCagesTrackRow {
  id: string
  cages_track_number: string | null
  date: string | null
  tippler_start_time: string | null
  tippler_stop_time: string | null
  cages_out: number | null
  cages_tipped: number | null
  note: string | null
  checked_by: string | null
  acknowledged_by: string | null
  server_id: string | null
}

interface LocalCagesTippedTimeRow {
  tipped_hour: number | null
  checked_cage_numbers: string | null
}

function extractErrorMessage(error: unknown): string {
  const apiError = error as NormalizedApiError
  return apiError?.message ?? 'Gagal sinkronisasi — kesalahan tidak diketahui.'
}

async function syncWeighbridgeRecords(productionLineId: string, userId: string): Promise<SyncItemResult[]> {
  const rows = await query<LocalWeighbridgeRow>(
    `SELECT * FROM weighbridge_record WHERE status = 'saved' AND created_by = ?`,
    [userId],
  )

  const results: SyncItemResult[] = []

  for (const row of rows) {
    const label = row.wb_card_number ?? row.id
    try {
      const response = await apiClient.post('/api/weighbridge-records', {
        production_line_id: productionLineId,
        wb_card_number: row.wb_card_number,
        weighbridge_type: row.weighbridge_type,
        record_datetime: row.record_datetime,
        vehicle_number: row.vehicle_number,
        driver_name: row.driver_name,
        estate_supplier: row.estate_supplier,
        destination: row.destination,
        division: row.division,
        block: row.block,
        gross_weight: row.gross_weight,
        tare_weight: row.tare_weight,
        quantity: row.quantity,
        checked: Boolean(row.checked_by),
        acknowledged: Boolean(row.acknowledged_by),
      })

      const serverId = response.data?.id as string
      await run(`UPDATE weighbridge_record SET status = 'synced', server_id = ? WHERE id = ?`, [serverId, row.id])
      results.push({ id: row.id, label, ok: true })
    } catch (error) {
      results.push({ id: row.id, label, ok: false, reason: extractErrorMessage(error) })
    }
  }

  return results
}

async function syncGradingRecords(productionLineId: string, userId: string): Promise<SyncItemResult[]> {
  const rows = await query<LocalGradingRow>(
    `SELECT * FROM grading_record WHERE status = 'saved' AND created_by = ?`,
    [userId],
  )

  const results: SyncItemResult[] = []

  for (const row of rows) {
    const label = row.grading_number ?? row.id

    let weighbridgeServerId: string | null = null

    if (row.weighbridge_record_id) {
      const parent = await query<{ server_id: string | null }>(
        `SELECT server_id FROM weighbridge_record WHERE id = ?`,
        [row.weighbridge_record_id],
      )
      weighbridgeServerId = parent[0]?.server_id ?? null
    }

    if (!weighbridgeServerId) {
      results.push({
        id: row.id,
        label,
        ok: false,
        reason: 'Weighbridge terkait belum tersinkron — sinkronkan Weighbridge-nya dahulu.',
      })
      continue
    }

    const details = await query<LocalGradingDetailRow>(
      `SELECT grading_parameter_id, quantity FROM grading_detail WHERE grading_record_id = ?`,
      [row.id],
    )

    try {
      const response = await apiClient.post('/api/grading-records', {
        production_line_id: productionLineId,
        grading_number: row.grading_number,
        date: row.date,
        weighbridge_record_id: weighbridgeServerId,
        license_plate_no: row.license_plate_no,
        vehicle_code: row.vehicle_code,
        estate_supplier: row.estate_supplier,
        division: row.division,
        netto: row.netto,
        quantity: row.quantity,
        note: row.note,
        checked: Boolean(row.checked_by),
        acknowledged: Boolean(row.acknowledged_by),
        details: details.map((detail) => ({
          grading_parameter_id: detail.grading_parameter_id,
          quantity: detail.quantity,
        })),
      })

      const serverId = response.data?.id as string
      await run(`UPDATE grading_record SET status = 'synced', server_id = ? WHERE id = ?`, [serverId, row.id])
      results.push({ id: row.id, label, ok: true })
    } catch (error) {
      results.push({ id: row.id, label, ok: false, reason: extractErrorMessage(error) })
    }
  }

  return results
}

async function syncCagesTrackRecords(productionLineId: string, userId: string): Promise<SyncItemResult[]> {
  const rows = await query<LocalCagesTrackRow>(
    `SELECT * FROM cages_track_record WHERE status = 'saved' AND created_by = ?`,
    [userId],
  )

  const results: SyncItemResult[] = []

  for (const row of rows) {
    const label = row.cages_track_number ?? row.id

    const details = await query<LocalCagesTippedTimeRow>(
      `SELECT tipped_hour, checked_cage_numbers FROM cages_tipped_time WHERE cages_track_record_id = ?`,
      [row.id],
    )

    try {
      const response = await apiClient.post('/api/cages-track-records', {
        production_line_id: productionLineId,
        cages_track_number: row.cages_track_number,
        date: row.date,
        tippler_start_time: row.tippler_start_time,
        tippler_stop_time: row.tippler_stop_time,
        cages_out: row.cages_out,
        cages_tipped: row.cages_tipped,
        note: row.note,
        checked: Boolean(row.checked_by),
        acknowledged: Boolean(row.acknowledged_by),
        details: details.map((detail) => ({
          tipped_hour: detail.tipped_hour,
          checked_cage_numbers: (detail.checked_cage_numbers ?? '')
            .split(',')
            .map((value) => value.trim())
            .filter((value) => value !== ''),
        })),
      })

      const serverId = response.data?.id as string
      await run(`UPDATE cages_track_record SET status = 'synced', server_id = ? WHERE id = ?`, [serverId, row.id])
      results.push({ id: row.id, label, ok: true })
    } catch (error) {
      results.push({ id: row.id, label, ok: false, reason: extractErrorMessage(error) })
    }
  }

  return results
}

/**
 * Runs all 3 record types' sync in order (Weighbridge, then Grading — see
 * this file's doc comment for why order matters —, then Cages Track).
 * `productionLineId` is the Production Line selected on Station List's
 * picker step (StationListView.vue's own local state, unlike `userId` —
 * read from the auth store since it IS a property of the logged-in user).
 */
export async function syncAllRecords(productionLineId: string | null | undefined): Promise<SyncSummary> {
  const authStore = useAuthStore()
  const userId = authStore.currentUser?.id

  if (!productionLineId || !userId) {
    throw new Error('Tidak dapat sinkronisasi: production line atau user tidak diketahui.')
  }

  const weighbridge = await syncWeighbridgeRecords(productionLineId, userId)
  const grading = await syncGradingRecords(productionLineId, userId)
  const cagesTrack = await syncCagesTrackRecords(productionLineId, userId)

  const all = [...weighbridge, ...grading, ...cagesTrack]

  return {
    weighbridge,
    grading,
    cagesTrack,
    syncedCount: all.filter((item) => item.ok).length,
    failedCount: all.filter((item) => !item.ok).length,
  }
}
