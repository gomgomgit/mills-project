import apiClient, { type NormalizedApiError } from '@/services/apiClient'
import { query, run } from '@/services/localDb'
import { useAuthStore } from '@/stores/auth'

/**
 * syncService — TEMPORARY/pragmatic bridge (2026-08-20) so records entered
 * on mobile (offline-first, local SQLite only) become visible on the web
 * app (backend DB). Not the final sync architecture (no conflict
 * resolution, no background/periodic sync, no retry queue) — a manual
 * "Sinkronisasi" button the user triggers from Station List (screen-006),
 * per explicit request.
 *
 * EXTENDED (2026-08-24): originally only Weighbridge/Grading/Cages Track
 * (the 3 original MVP stations) — Threshing/Pressing/Depricarping/Kernel
 * Plant (promoted to active 2026-08-23) were deliberately left out of
 * scope at the time (see routes/api.php's now-stale "no sync UI wiring was
 * added here" comments on those 4 stations' POST endpoints — the endpoint
 * contracts, including the `role:...,operator` grant, were already put in
 * place in anticipation of this). Root cause of the bug this fixes: an
 * Operator's Threshing/Pressing/Depricarping/Kernel Plant record saved on
 * mobile was reachable by NO ONE else — not the web app (never synced) and
 * not even a Supervisor's own mobile session (every local read in
 * {station}RecordRepo.ts is `WHERE created_by = ?`-scoped to the logged-in
 * user, same as Weighbridge/Grading/Cages Track always were). All 4 new
 * stations are standalone (no cross-reference dependency, like Cages
 * Track) and sync independently of each other and of the original 3.
 *
 * Order matters for the original 3: Weighbridge MUST sync before Grading, because
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
  threshing: SyncItemResult[]
  pressing: SyncItemResult[]
  depricarping: SyncItemResult[]
  kernelPlant: SyncItemResult[]
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

interface LocalThreshingRow {
  id: string
  thresher_id: string | null
  date: string | null
  note: string | null
  checked_by: string | null
  acknowledged_by: string | null
  server_id: string | null
}

interface LocalThreshingDetailRow {
  time_slot: string
  ffb_throughput_mt_hour: number | null
  thresher_drum_speed_rpm: number | null
  motor_current_amps: number | null
  unstripped_bunch_count_percent: number | null
  empty_bunch_oil_loss_percent: number | null
  downtime_reason: string | null
}

interface LocalPressingRow {
  id: string
  presser_id: string | null
  date: string | null
  note: string | null
  checked_by: string | null
  acknowledged_by: string | null
  server_id: string | null
}

interface LocalPressingDetailRow {
  time_slot: string
  digester_temp_c: number | null
  digester_level_percent: number | null
  press_motor_current_amps: number | null
  cone_hydraulic_pressure_bar: number | null
  dilution_water_temp_c: number | null
  downtime_reason: string | null
}

interface LocalDepricarpingRow {
  id: string
  presser_id: string | null
  date: string | null
  note: string | null
  checked_by: string | null
  acknowledged_by: string | null
  server_id: string | null
}

interface LocalDepricarpingDetailRow {
  time_slot: string
  fan_static_pressure_mmh2o: number | null
  polishing_drum_speed_rpm: number | null
  air_velocity_ms: number | null
  fibre_moisture_percent: number | null
  kernel_recovery_in_fibre_percent: number | null
  nut_silo_1_temp_c: number | null
  nut_silo_2_temp_c: number | null
  downtime_minutes: number | null
  findings: string | null
}

interface LocalKernelPlantRow {
  id: string
  kernel_plant_id: string | null
  date: string | null
  note: string | null
  checked_by: string | null
  acknowledged_by: string | null
  server_id: string | null
}

interface LocalKernelPlantDetailRow {
  time_slot: string
  ripple_mill_1_amps: number | null
  ripple_mill_2_amps: number | null
  claybath_hydro_sg: number | null
  kernel_silo_1_temp_c: number | null
  kernel_silo_2_temp_c: number | null
  kernel_moisture_percent: number | null
  shell_loss_percent: number | null
  downtime_minutes: number | null
  findings: string | null
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

async function syncThreshingRecords(productionLineId: string, userId: string): Promise<SyncItemResult[]> {
  const rows = await query<LocalThreshingRow>(
    `SELECT * FROM threshing_record WHERE status = 'saved' AND created_by = ?`,
    [userId],
  )

  const results: SyncItemResult[] = []

  for (const row of rows) {
    const label = row.thresher_id ?? row.id

    const details = await query<LocalThreshingDetailRow>(
      `SELECT time_slot, ffb_throughput_mt_hour, thresher_drum_speed_rpm, motor_current_amps, unstripped_bunch_count_percent, empty_bunch_oil_loss_percent, downtime_reason FROM threshing_detail WHERE threshing_record_id = ? ORDER BY time_slot`,
      [row.id],
    )

    try {
      const response = await apiClient.post('/api/threshing-records', {
        production_line_id: productionLineId,
        thresher_id: row.thresher_id,
        date: row.date,
        note: row.note,
        checked: Boolean(row.checked_by),
        acknowledged: Boolean(row.acknowledged_by),
        details,
      })

      const serverId = response.data?.id as string
      await run(`UPDATE threshing_record SET status = 'synced', server_id = ? WHERE id = ?`, [serverId, row.id])
      results.push({ id: row.id, label, ok: true })
    } catch (error) {
      results.push({ id: row.id, label, ok: false, reason: extractErrorMessage(error) })
    }
  }

  return results
}

async function syncPressingRecords(productionLineId: string, userId: string): Promise<SyncItemResult[]> {
  const rows = await query<LocalPressingRow>(
    `SELECT * FROM pressing_record WHERE status = 'saved' AND created_by = ?`,
    [userId],
  )

  const results: SyncItemResult[] = []

  for (const row of rows) {
    const label = row.presser_id ?? row.id

    const details = await query<LocalPressingDetailRow>(
      `SELECT time_slot, digester_temp_c, digester_level_percent, press_motor_current_amps, cone_hydraulic_pressure_bar, dilution_water_temp_c, downtime_reason FROM pressing_detail WHERE pressing_record_id = ? ORDER BY time_slot`,
      [row.id],
    )

    try {
      const response = await apiClient.post('/api/pressing-records', {
        production_line_id: productionLineId,
        presser_id: row.presser_id,
        date: row.date,
        note: row.note,
        checked: Boolean(row.checked_by),
        acknowledged: Boolean(row.acknowledged_by),
        details,
      })

      const serverId = response.data?.id as string
      await run(`UPDATE pressing_record SET status = 'synced', server_id = ? WHERE id = ?`, [serverId, row.id])
      results.push({ id: row.id, label, ok: true })
    } catch (error) {
      results.push({ id: row.id, label, ok: false, reason: extractErrorMessage(error) })
    }
  }

  return results
}

async function syncDepricarpingRecords(productionLineId: string, userId: string): Promise<SyncItemResult[]> {
  const rows = await query<LocalDepricarpingRow>(
    `SELECT * FROM depricarping_record WHERE status = 'saved' AND created_by = ?`,
    [userId],
  )

  const results: SyncItemResult[] = []

  for (const row of rows) {
    const label = row.presser_id ?? row.id

    const details = await query<LocalDepricarpingDetailRow>(
      `SELECT time_slot, fan_static_pressure_mmh2o, polishing_drum_speed_rpm, air_velocity_ms, fibre_moisture_percent, kernel_recovery_in_fibre_percent, nut_silo_1_temp_c, nut_silo_2_temp_c, downtime_minutes, findings FROM depricarping_detail WHERE depricarping_record_id = ? ORDER BY time_slot`,
      [row.id],
    )

    try {
      const response = await apiClient.post('/api/depricarping-records', {
        production_line_id: productionLineId,
        presser_id: row.presser_id,
        date: row.date,
        note: row.note,
        checked: Boolean(row.checked_by),
        acknowledged: Boolean(row.acknowledged_by),
        details,
      })

      const serverId = response.data?.id as string
      await run(`UPDATE depricarping_record SET status = 'synced', server_id = ? WHERE id = ?`, [serverId, row.id])
      results.push({ id: row.id, label, ok: true })
    } catch (error) {
      results.push({ id: row.id, label, ok: false, reason: extractErrorMessage(error) })
    }
  }

  return results
}

async function syncKernelPlantRecords(productionLineId: string, userId: string): Promise<SyncItemResult[]> {
  const rows = await query<LocalKernelPlantRow>(
    `SELECT * FROM kernel_plant_record WHERE status = 'saved' AND created_by = ?`,
    [userId],
  )

  const results: SyncItemResult[] = []

  for (const row of rows) {
    const label = row.kernel_plant_id ?? row.id

    const details = await query<LocalKernelPlantDetailRow>(
      `SELECT time_slot, ripple_mill_1_amps, ripple_mill_2_amps, claybath_hydro_sg, kernel_silo_1_temp_c, kernel_silo_2_temp_c, kernel_moisture_percent, shell_loss_percent, downtime_minutes, findings FROM kernel_plant_detail WHERE kernel_plant_record_id = ? ORDER BY time_slot`,
      [row.id],
    )

    try {
      const response = await apiClient.post('/api/kernel-plant-records', {
        production_line_id: productionLineId,
        kernel_plant_id: row.kernel_plant_id,
        date: row.date,
        note: row.note,
        checked: Boolean(row.checked_by),
        acknowledged: Boolean(row.acknowledged_by),
        details,
      })

      const serverId = response.data?.id as string
      await run(`UPDATE kernel_plant_record SET status = 'synced', server_id = ? WHERE id = ?`, [serverId, row.id])
      results.push({ id: row.id, label, ok: true })
    } catch (error) {
      results.push({ id: row.id, label, ok: false, reason: extractErrorMessage(error) })
    }
  }

  return results
}

/**
 * Runs all 7 record types' sync in order (Weighbridge, then Grading — see
 * this file's doc comment for why order matters —, then Cages Track,
 * Threshing, Pressing, Depricarping, Kernel Plant — the latter 4 have no
 * cross-reference dependency on each other or on the original 3, so their
 * relative order doesn't matter). `productionLineId` is the Production
 * Line selected on Station List's picker step (StationListView.vue's own
 * local state, unlike `userId` — read from the auth store since it IS a
 * property of the logged-in user).
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
  const threshing = await syncThreshingRecords(productionLineId, userId)
  const pressing = await syncPressingRecords(productionLineId, userId)
  const depricarping = await syncDepricarpingRecords(productionLineId, userId)
  const kernelPlant = await syncKernelPlantRecords(productionLineId, userId)

  const all = [...weighbridge, ...grading, ...cagesTrack, ...threshing, ...pressing, ...depricarping, ...kernelPlant]

  return {
    weighbridge,
    grading,
    cagesTrack,
    threshing,
    pressing,
    depricarping,
    kernelPlant,
    syncedCount: all.filter((item) => item.ok).length,
    failedCount: all.filter((item) => !item.ok).length,
  }
}
