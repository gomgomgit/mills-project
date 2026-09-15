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
  /**
   * Results keyed by station (`weighbridge`, `boilerRoom`, …). Replaced the
   * old one-field-per-station shape on 2026-09-14: that shape only ever
   * covered 7 stations, and the other 11 had no sync path at all, so their
   * data never left the device. A map scales to all 18 without the
   * interface growing a field per station.
   */
  byStation: Record<string, SyncItemResult[]>
  /** Every result across every station, flattened — what the dialog lists. */
  items: SyncItemResult[]
  syncedCount: number
  failedCount: number
}

/**
 * A station whose create payload is the uniform
 * `{station}_id / date / note / checked / acknowledged / details` shape —
 * 15 of the 18. Weighbridge, Grading and Cages Track are NOT here: they
 * carry bespoke header fields (and Grading resolves a linked weighbridge
 * server id first), so they keep hand-written push functions below.
 */
interface StationPushConfig {
  key: string
  table: string
  endpoint: string
  /** Local column holding the human-facing id, also used as the result label. */
  idColumn: string
  /** Payload key for that id — usually the same, but e.g. pressing sends `presser_id`. */
  idPayloadKey: string
  detailTable: string
  detailFk: string
  detailColumns: string[]
  detailOrderBy: string
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
  checked_by_name?: string | null
  acknowledged_by_name?: string | null
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
  checked_by_name?: string | null
  acknowledged_by_name?: string | null
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
  checked_by_name?: string | null
  acknowledged_by_name?: string | null
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
  checked_by_name?: string | null
  acknowledged_by_name?: string | null
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
  checked_by_name?: string | null
  acknowledged_by_name?: string | null
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
  checked_by_name?: string | null
  acknowledged_by_name?: string | null
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
  checked_by_name?: string | null
  acknowledged_by_name?: string | null
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

/**
 * The 15 uniform stations. Order is the canonical grid order, so a sync
 * summary reads the same way the station grid does.
 */
const STATION_PUSH_CONFIGS: StationPushConfig[] = [
  {
    key: 'threshing',
    table: 'threshing_record',
    endpoint: '/api/threshing-records',
    idColumn: 'thresher_id',
    idPayloadKey: 'thresher_id',
    detailTable: 'threshing_detail',
    detailFk: 'threshing_record_id',
    detailColumns: ['time_slot', 'ffb_throughput_mt_hour', 'thresher_drum_speed_rpm', 'motor_current_amps', 'unstripped_bunch_count_percent', 'empty_bunch_oil_loss_percent', 'downtime_reason'],
    detailOrderBy: 'time_slot',
  },
  {
    key: 'pressing',
    table: 'pressing_record',
    endpoint: '/api/pressing-records',
    idColumn: 'presser_id',
    idPayloadKey: 'presser_id',
    detailTable: 'pressing_detail',
    detailFk: 'pressing_record_id',
    detailColumns: ['time_slot', 'digester_temp_c', 'digester_level_percent', 'press_motor_current_amps', 'cone_hydraulic_pressure_bar', 'dilution_water_temp_c', 'downtime_reason'],
    detailOrderBy: 'time_slot',
  },
  {
    key: 'depricarping',
    table: 'depricarping_record',
    endpoint: '/api/depricarping-records',
    idColumn: 'presser_id',
    idPayloadKey: 'presser_id',
    detailTable: 'depricarping_detail',
    detailFk: 'depricarping_record_id',
    detailColumns: ['time_slot', 'fan_static_pressure_mmh2o', 'polishing_drum_speed_rpm', 'air_velocity_ms', 'fibre_moisture_percent', 'kernel_recovery_in_fibre_percent', 'nut_silo_1_temp_c', 'nut_silo_2_temp_c', 'downtime_minutes', 'findings'],
    detailOrderBy: 'time_slot',
  },
  {
    key: 'kernelPlant',
    table: 'kernel_plant_record',
    endpoint: '/api/kernel-plant-records',
    idColumn: 'kernel_plant_id',
    idPayloadKey: 'kernel_plant_id',
    detailTable: 'kernel_plant_detail',
    detailFk: 'kernel_plant_record_id',
    detailColumns: ['time_slot', 'ripple_mill_1_efficiency_percent', 'ripple_mill_2_efficiency_percent', 'claybath_hydro_sg', 'kernel_silo_1_temp_c', 'kernel_silo_2_temp_c', 'kernel_moisture_percent', 'shell_loss_percent', 'downtime_minutes', 'findings'],
    detailOrderBy: 'time_slot',
  },
  {
    key: 'sterilizer',
    table: 'sterilizer_record',
    endpoint: '/api/sterilizer-records',
    idColumn: 'sterilizer_id',
    idPayloadKey: 'sterilizer_id',
    detailTable: 'sterilizer_detail',
    detailFk: 'sterilizer_record_id',
    detailColumns: ['sterilizer_no', 'close_door_time', 'peak_1_time', 'exhaust_1_time', 'peak_2_time', 'exhaust_2_time', 'peak_3_time', 'exhaust_3_time', 'open_door_time', 'duration_minutes', 'number_of_cages', 'cages_status', 'checked_by_spv', 'remarks'],
    detailOrderBy: 'sterilizer_no',
  },
  {
    key: 'boilerRoom',
    table: 'boiler_room_record',
    endpoint: '/api/boiler-room-records',
    idColumn: 'boiler_room_id',
    idPayloadKey: 'boiler_room_id',
    detailTable: 'boiler_room_detail',
    detailFk: 'boiler_room_record_id',
    detailColumns: ['time_slot', 'steam_pressure_bar', 'steam_temp_c', 'feed_water_temp_c', 'feed_water_tank_level_percent', 'boiler_water_level_percent', 'water_tds_ppm', 'water_ph', 'fuel_feed_rate', 'id_fan_load', 'sa_fan_load', 'exhaust_gas_temp_c', 'dust_collector_differential_pressure_mmh2o', 'blowdown_executed', 'sootblowing_executed', 'findings'],
    detailOrderBy: 'time_slot',
  },
  {
    key: 'engineRoom',
    table: 'engine_room_record',
    endpoint: '/api/engine-room-records',
    idColumn: 'engine_room_id',
    idPayloadKey: 'engine_room_id',
    detailTable: 'engine_room_detail',
    detailFk: 'engine_room_record_id',
    detailColumns: ['time_slot', 'steam_turbine_inlet_pressure_bar', 'steam_turbine_inlet_temp_c', 'steam_turbine_exhaust_pressure_bar', 'steam_turbine_rpm', 'steam_turbine_alternator_bearing_temp_1_c', 'steam_turbine_alternator_bearing_temp_2_c', 'diesel_gen_1_status', 'diesel_gen_1_load_kw', 'diesel_gen_1_amperage_a', 'diesel_gen_1_jacket_water_temp_c', 'diesel_gen_1_lube_oil_pressure_bar', 'diesel_gen_2_status', 'diesel_gen_2_load_kw', 'diesel_gen_2_amperage_a', 'diesel_gen_2_jacket_water_temp_c', 'diesel_gen_2_lube_oil_pressure_bar', 'electrical_sync_total_factory_load_kw', 'electrical_sync_system_frequency_hz', 'electrical_sync_power_factor', 'electrical_sync_busbar_voltage_v', 'air_compressor_1_pressure_bar', 'compressor_2_pressure_bar', 'battery_charger_ups_voltage_v', 'fuel_tank_level', 'daily_energy_export_kwh', 'action_taken_maintenance_remark', 'findings'],
    detailOrderBy: 'time_slot',
  },
  {
    key: 'clarification',
    table: 'clarification_record',
    endpoint: '/api/clarification-records',
    idColumn: 'clarification_id',
    idPayloadKey: 'clarification_id',
    detailTable: 'clarification_detail',
    detailFk: 'clarification_record_id',
    detailColumns: ['time_slot', 'clarification_tank_temp_c', 'oil_tank_temperature_c', 'sludge_tank_temp_c', 'buffer_tank_level_percent', 'pure_oil_production_rate_ton_hour', 'downtime_mins', 'findings'],
    detailOrderBy: 'time_slot',
  },
  {
    key: 'storageTank',
    table: 'storage_tank_record',
    endpoint: '/api/storage-tank-records',
    idColumn: 'storage_tank_id',
    idPayloadKey: 'storage_tank_id',
    detailTable: 'storage_tank_detail',
    detailFk: 'storage_tank_record_id',
    detailColumns: ['time_slot', 'cpo_sounding_depth_mm', 'water_dip_bottom_depth_mm', 'net_oil_depth_mm', 'oil_temperature_top_c', 'oil_temperature_middle_c', 'oil_temperature_bottom_c', 'average_temperature_c', 'calculated_volume_m3', 'calculated_weight_mt', 'ffa_percent', 'moisture_content_percent', 'impurities_dirt_percent', 'dobi_index', 'steam_heating_valve_status', 'tank_structural_condition', 'inspector_name', 'findings'],
    detailOrderBy: 'time_slot',
  },
  {
    key: 'effluentPlant',
    table: 'effluent_plant_record',
    endpoint: '/api/effluent-plant-records',
    idColumn: 'effluent_plant_id',
    idPayloadKey: 'effluent_plant_id',
    detailTable: 'effluent_plant_detail',
    detailFk: 'effluent_plant_record_id',
    detailColumns: ['time_slot', 'anaerobic_pond_1_ph', 'anaerobic_pond_1_temp_c', 'anaerobic_pond_2_ph', 'anaerobic_pond_2_temp_c', 'cooling_pond_ph', 'cooling_pond_temp_c', 'biogas_flare_status', 'biogas_flow_rate_m3h', 'raw_pome_feed_rate_m3h', 'effluent_discharge_flow_rate_m3h', 'final_discharge_ph', 'final_discharge_bod_mgl_lab', 'final_discharge_cod_mgl_lab', 'final_discharge_tss_mgl_lab', 'dosing_pump_1_status', 'chemical_consumed_kgl', 'sludge_dewatering_status', 'remarks_maintenance_actions', 'findings'],
    detailOrderBy: 'time_slot',
  },
  {
    key: 'processWater',
    table: 'process_water_record',
    endpoint: '/api/process-water-records',
    idColumn: 'process_water_id',
    idPayloadKey: 'process_water_id',
    detailTable: 'process_water_detail',
    detailFk: 'process_water_record_id',
    detailColumns: ['time_slot', 'shift', 'inspector_id', 'raw_water_flow_m3h', 'clarified_water_flow_m3h', 'softener_inlet_ph', 'softener_outlet_hardness_ppm', 'alum_dosing_kgh', 'polymer_dosing_gh', 'boiler_feed_tank_temp_c', 'boiler_feed_water_ph', 'boiler_feed_tds_ppm', 'action_taken_status', 'findings'],
    detailOrderBy: 'time_slot',
  },
  {
    key: 'processQualityControl',
    table: 'process_quality_control_record',
    endpoint: '/api/process-quality-control-records',
    idColumn: 'process_qc_id',
    idPayloadKey: 'process_qc_id',
    detailTable: 'process_quality_control_detail',
    detailFk: 'process_quality_control_record_id',
    detailColumns: ['time_slot', 'shift', 'fruit_press_oil_loss_in_sludge_percent', 'fruit_press_oil_loss_in_fibre_percent', 'purifier_clarification_balance_inlet_temp_c', 'purifier_clarification_balance_backpressure_bar', 'vacuum_drying_station_drier_temp_c', 'vacuum_drying_station_vacuum_pressure_bar', 'decanter_centrifuge_feed_rate_mth', 'decanter_centrifuge_oil_loss_in_cake_percent', 'final_storage_ffa_percent', 'final_storage_moisture_content_percent', 'final_storage_impurities_dirt_percent', 'final_storage_dobi_index', 'qc_inspector_id', 'qc_engineering_corrective_actions', 'findings'],
    detailOrderBy: 'time_slot',
  },
  {
    key: 'solidWasteDisposal',
    table: 'solid_waste_disposal_record',
    endpoint: '/api/solid-waste-disposal-records',
    idColumn: 'solid_waste_disposal_id',
    idPayloadKey: 'solid_waste_disposal_id',
    detailTable: 'solid_waste_disposal_detail',
    detailFk: 'solid_waste_disposal_record_id',
    detailColumns: ['event_date', 'shift', 'weighbridge_ticket_no', 'vehicle_no', 'driver_name', 'solid_waste_type', 'source_station', 'gross_weight_mt', 'tare_weight_mt', 'net_weight_mt', 'disposal_utilization_site', 'purpose_end_use', 'gate_pass_no', 'security_seal_no', 'operator_id', 'remarks', 'findings'],
    detailOrderBy: 'event_date',
  },
  {
    key: 'cpoDispatch',
    table: 'cpo_dispatch_record',
    endpoint: '/api/cpo-dispatch-records',
    idColumn: 'cpo_dispatch_id',
    idPayloadKey: 'cpo_dispatch_id',
    detailTable: 'cpo_dispatch_detail',
    detailFk: 'cpo_dispatch_record_id',
    detailColumns: ['event_date', 'shift', 'time_in', 'time_out', 'waybill_number', 'tanker_plate_no', 'transport_company', 'driver_name', 'storage_tank_source', 'seal_no_top', 'seal_no_bottom', 'gross_weight_mt', 'tare_weight_mt', 'net_weight_mt', 'ffa_percent', 'moisture_percent', 'impurities_percent', 'dobi', 'destination_buyer', 'weighbridge_operator', 'findings'],
    detailOrderBy: 'event_date',
  },
  {
    key: 'kernelDispatch',
    table: 'kernel_dispatch_record',
    endpoint: '/api/kernel-dispatch-records',
    idColumn: 'kernel_dispatch_id',
    idPayloadKey: 'kernel_dispatch_id',
    detailTable: 'kernel_dispatch_detail',
    detailFk: 'kernel_dispatch_record_id',
    detailColumns: ['event_date', 'shift', 'weighbridge_ticket_no', 'waybill_number', 'transporter_contractor', 'vehicle_plate_no', 'driver_name', 'silo_source_id', 'destination_buyer', 'gross_weight_mt', 'tare_weight_mt', 'net_weight_mt', 'kernel_moisture_percent', 'dirt_impurities_percent', 'ffa_percent', 'broken_kernel_percent', 'security_seal_no_top', 'security_seal_no_bottom', 'weighbridge_operator_id', 'remarks_gate_status', 'findings'],
    detailOrderBy: 'event_date',
  },
]

const CONFIG_BY_TABLE = new Map(STATION_PUSH_CONFIGS.map((config) => [config.table, config]))

/**
 * Pushes ONE already-saved local row to the server and marks it synced.
 * Shared by the manual "Sinkronisasi" button and by write-through saving
 * (see pushSavedRecordNow below) so the two can never drift on payload
 * shape or on the synced/server_id bookkeeping.
 */
async function pushUniformRow(
  config: StationPushConfig,
  productionLineId: string,
  row: Record<string, unknown>,
): Promise<SyncItemResult> {
  const id = String(row.id)
  const label = (row[config.idColumn] as string) ?? id

  const details = await query<Record<string, unknown>>(
    `SELECT ${config.detailColumns.join(', ')} FROM ${config.detailTable} WHERE ${config.detailFk} = ? ORDER BY ${config.detailOrderBy}`,
    [id],
  )

  try {
    const response = await apiClient.post(config.endpoint, {
      production_line_id: productionLineId,
      [config.idPayloadKey]: row[config.idColumn],
      date: row.date,
      note: row.note,
      checked: Boolean(row.checked_by),
      acknowledged: Boolean(row.acknowledged_by),
      details,
    })

    const serverId = response.data?.id as string
    await run(`UPDATE ${config.table} SET status = 'synced', server_id = ? WHERE id = ?`, [serverId, id])

    return { id, label, ok: true }
  } catch (error) {
    return { id, label, ok: false, reason: extractErrorMessage(error) }
  }
}

async function syncUniformStation(
  config: StationPushConfig,
  productionLineId: string,
  userId: string,
): Promise<SyncItemResult[]> {
  const rows = await query<Record<string, unknown>>(
    `SELECT * FROM ${config.table} WHERE status = 'saved' AND created_by = ?`,
    [userId],
  )

  const results: SyncItemResult[] = []
  for (const row of rows) {
    results.push(await pushUniformRow(config, productionLineId, row))
  }

  return results
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

  const byStation: Record<string, SyncItemResult[]> = {
    // Bespoke payloads — see each function for why it is not config-driven.
    weighbridge: await syncWeighbridgeRecords(productionLineId, userId),
    grading: await syncGradingRecords(productionLineId, userId),
    cagesTrack: await syncCagesTrackRecords(productionLineId, userId),
  }

  // The other 15, including the 11 that had no sync path at all before
  // 2026-09-14 and whose records could never leave the device.
  for (const config of STATION_PUSH_CONFIGS) {
    byStation[config.key] = await syncUniformStation(config, productionLineId, userId)
  }

  const items = Object.values(byStation).flat()

  return {
    byStation,
    items,
    syncedCount: items.filter((item) => item.ok).length,
    failedCount: items.filter((item) => !item.ok).length,
  }
}

/**
 * Write-through saving (2026-09-14) — pushes ONE record the caller just
 * saved locally, instead of waiting for the operator to tap
 * "Sinkronisasi". Enabled per mill via Mills Setting's
 * `immediate_sync_enabled`; see millSettingRepo.
 *
 * Returns null when this station has no push path or the row is not in a
 * pushable state, so a caller can treat "nothing to do" differently from a
 * failure. Never throws for a network problem — an offline save must stay
 * a successful local save, exactly as it was before this existed, and the
 * record simply waits for the next manual sync.
 */
export async function pushSavedRecordNow(
  table: string,
  recordId: string,
  productionLineId?: string | null,
): Promise<SyncItemResult | null> {
  const config = CONFIG_BY_TABLE.get(table)

  if (!config) {
    return null
  }

  const rows = await query<Record<string, unknown>>(
    `SELECT * FROM ${config.table} WHERE id = ? AND status = 'saved'`,
    [recordId],
  )

  if (rows.length === 0) {
    return null
  }

  const row = rows[0]
  // The caller (the manual sync) knows which production line the operator
  // picked. A form does not — that selection lives only in Station List's
  // local state. Rather than thread it through 18 views, derive it from the
  // record itself: the record knows its station, and the cached station row
  // knows its production line.
  const resolved = productionLineId ?? (await resolveProductionLineId(row.station_id as string | null))

  if (!resolved) {
    return null
  }

  return pushUniformRow(config, resolved, row)
}

async function resolveProductionLineId(stationId: string | null): Promise<string | null> {
  if (!stationId) {
    return null
  }

  const rows = await query<{ production_line_id: string | null }>(
    `SELECT production_line_id FROM station WHERE id = ?`,
    [stationId],
  )

  return rows[0]?.production_line_id ?? null
}
