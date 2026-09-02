import { query } from '@/services/localDb'

/**
 * Station types temporarily hidden from the Station List grid (product
 * decision, 2026-09-01) — the underlying stations remain fully active
 * (`is_active = true`) and fully functional (Monitor/Form/Data Preview,
 * sync, existing drafts, etc. all keep working); they are simply not
 * offered as a selectable tile on this screen. Mirrors the same
 * "temporarily hidden" pattern used earlier this project for
 * Threshing/Pressing/Depricarping/Kernel Plant (later re-enabled) —
 * remove an entry from this list to re-enable that station's tile.
 */
const HIDDEN_STATION_TYPES = [
  'engine-room',
  'storage-tank',
  'effluent-plant',
  'cpo-dispatch',
  'kernel-dispatch',
  'process-water',
  'solid-waste-disposal',
  'process-quality-control',
] as const

const HIDDEN_STATION_TYPES_SQL = HIDDEN_STATION_TYPES.map((t) => `'${t}'`).join(', ')

/**
 * stationRepo — screen-006--station-list / usecase-006--station-list
 * "Pilih Stasiun" business_logic step 1.
 *
 * Reads the local (offline) `station` reference table — mirrored on the
 * device with the same shape as the server `station` entity (per
 * entity_catalog), pre-seeded by an earlier sync flow (a different
 * screen's responsibility, not this one). This repo is read-only: it never
 * writes to `station`, and it does not synthesize the "18 slots, all 18
 * active, 0 placeholder" shape in code — that shape is expected to already
 * exist as 18 rows in the local table (one per station master-data record
 * synced from the server). See localDb.ts's header comment, which lists
 * `station` alongside the write-capable local tables as an existing local
 * table this screen assumes rather than creates.
 *
 * 2026-08-23 — 4 of the former 12 placeholder slots (Threshing, Pressing,
 * Depricarping, Kernel Plant) were promoted to active MVP stations; the
 * remaining 8 stay `type = 'other'` placeholders.
 *
 * 2026-08-31 — 10 more placeholder slots promoted to active stations
 * (Solid Waste Disposal, Process Water, Kernel Dispatch, CPO Dispatch,
 * Effluent Plant, Storage Tank, Engine Room, Boiler Room, Clarification,
 * Process Quality Control), bringing the total to 17 active MVP stations.
 * At that point only Sterilizer remained a `type = 'other'` placeholder —
 * the other former placeholder, 'Loading Ramp', was removed entirely
 * (2026-09-01): it turned out to be a duplicate name for the already-active
 * Cages Track station, not a distinct station.
 *
 * 2026-09-01 (final promotion) — Sterilizer promoted from `type = 'other'`
 * placeholder to a fully active station (`type = 'sterilizer'`). This was
 * the LAST remaining placeholder — 18 active MVP stations total, 0
 * placeholders remain anywhere in this project.
 *
 * Their own record repos / Form / Monitor / Data-Preview screens are a
 * separate, later piece of work (screen-by-screen) — this addition is
 * StationListView.vue/StationGrid.vue's station-list-level wiring only
 * (type recognition, icon, Monitor route name mapping); see
 * StationListView.vue's `loadDraftStatusByType()` doc comment for the
 * always-`hasDraft: false` stopgap until each station's repo exists.
 */

export type StationType =
  | 'weighbridge'
  | 'grading'
  | 'cages-track'
  | 'threshing'
  | 'pressing'
  | 'depricarping'
  | 'kernel-plant'
  | 'solid-waste-disposal'
  | 'process-water'
  | 'kernel-dispatch'
  | 'cpo-dispatch'
  | 'effluent-plant'
  | 'storage-tank'
  | 'engine-room'
  | 'boiler-room'
  | 'clarification'
  | 'process-quality-control'
  | 'sterilizer'
  | 'other'

/**
 * A single station grid slot, camelCased from the raw `station` row for
 * consumption by StationGrid.vue / StationListView.vue.
 */
export interface StationSlot {
  id: string
  businessUnitId: string
  name: string
  type: StationType
  isActive: boolean
  /**
   * entity-catalog v7 (Mills Setting feature) — optional Lucide icon-name
   * override (one of MillSettingService::SUPPORTED_ICONS on the backend,
   * e.g. 'truck', 'gauge'), synced by fetchAndCacheStationIconOverrides()
   * in stores/auth.ts. `null`/unrecognized → StationGrid.vue falls back to
   * the existing type-based default icon (business_logic step 3).
   */
  icon: string | null
}

interface StationRow {
  id: string
  business_unit_id: string
  name: string
  type: StationType
  is_active: number | boolean
  icon: string | null
}

function toStationSlot(row: StationRow): StationSlot {
  return {
    id: row.id,
    businessUnitId: row.business_unit_id,
    name: row.name,
    type: row.type,
    // SQLite has no native boolean type — @capacitor-community/sqlite
    // returns INTEGER columns as 0/1, so this normalizes either shape
    // (number from the real native driver, boolean from mocked test rows)
    // into a real boolean for the rest of the app.
    isActive: row.is_active === true || row.is_active === 1,
    icon: row.icon ?? null,
  }
}

/**
 * Loads all station grid slots for the given business unit — the full set
 * of 18 synced rows, ALL 18 active real station types, 0 placeholders (as
 * of 2026-09-01 — Sterilizer was the last one promoted), per business_logic
 * step 1. 8 of the 18 (see `HIDDEN_STATION_TYPES` above) are filtered out
 * of the result entirely as of 2026-09-01 (product decision to temporarily
 * hide them from this grid) — they remain fully active/functional, just
 * not returned by this query.
 *
 * Ordered by a FIXED canonical grid order (uiux-spec ver 2,
 * screen_type_patterns[type=list].body_area — mobile "list" sub-pattern),
 * explicit per-type via the CASE expression below (revised 2026-09-01 to
 * a full custom 18-station layout, replacing the old 3-explicit-then-99
 * ordering): Weighbridge, Pressing, Storage Tank, Grading, Clarification,
 * Effluent Plant, Cages Track, Engine Room, CPO Dispatch, Sterilizer,
 * Boiler Room, Kernel Dispatch, Kernel Plant, Process Water, Threshing,
 * Depricarping, Solid Waste Disposal, Process Quality Control. Deliberately
 * NOT alphabetical by `name` — the uiux-spec explicitly calls out that the
 * grid order must not be alphabetized.
 */
export async function getActiveAndPlaceholderStations(businessUnitId: string): Promise<StationSlot[]> {
  const rows = await query<StationRow>(
    `SELECT id, business_unit_id, name, type, is_active, icon
     FROM station
     WHERE business_unit_id = ?
       AND type NOT IN (${HIDDEN_STATION_TYPES_SQL})
       AND id = (
         SELECT s2.id FROM station s2
         WHERE s2.business_unit_id = station.business_unit_id AND s2.type = station.type
         ORDER BY s2.updated_at DESC, s2.id DESC
         LIMIT 1
       )
     ORDER BY
       CASE type
         WHEN 'weighbridge' THEN 1
         WHEN 'pressing' THEN 2
         WHEN 'storage-tank' THEN 3
         WHEN 'grading' THEN 4
         WHEN 'clarification' THEN 5
         WHEN 'effluent-plant' THEN 6
         WHEN 'cages-track' THEN 7
         WHEN 'engine-room' THEN 8
         WHEN 'cpo-dispatch' THEN 9
         WHEN 'sterilizer' THEN 10
         WHEN 'boiler-room' THEN 11
         WHEN 'kernel-dispatch' THEN 12
         WHEN 'kernel-plant' THEN 13
         WHEN 'process-water' THEN 14
         WHEN 'threshing' THEN 15
         WHEN 'depricarping' THEN 16
         WHEN 'solid-waste-disposal' THEN 17
         WHEN 'process-quality-control' THEN 18
         ELSE 99
       END ASC,
       id ASC`,
    [businessUnitId],
  )

  return rows.map(toStationSlot)
}

/**
 * Production Line-scoped counterpart to getActiveAndPlaceholderStations()
 * (entity-catalog v9, 2026-08-20) — used once a Production Line has been
 * selected (StationListView.vue's new picker step) and its real stations
 * synced locally via productionLineRepo.fetchAndCacheStationsForProductionLine()
 * (real backend ids, not the legacy synthetic per-business-unit seed).
 * Filtering by `production_line_id` (rather than reusing the
 * business-unit-scoped query above) avoids showing duplicate/merged tiles
 * once a mill has more than one Production Line's stations cached locally.
 * Same fixed canonical grid ordering as the business-unit-scoped query.
 */
export async function getActiveAndPlaceholderStationsForProductionLine(
  productionLineId: string,
): Promise<StationSlot[]> {
  const rows = await query<StationRow>(
    `SELECT id, business_unit_id, name, type, is_active, icon
     FROM station
     WHERE production_line_id = ?
       AND type NOT IN (${HIDDEN_STATION_TYPES_SQL})
       AND id = (
         SELECT s2.id FROM station s2
         WHERE s2.production_line_id = station.production_line_id AND s2.type = station.type
         ORDER BY s2.updated_at DESC, s2.id DESC
         LIMIT 1
       )
     ORDER BY
       CASE type
         WHEN 'weighbridge' THEN 1
         WHEN 'pressing' THEN 2
         WHEN 'storage-tank' THEN 3
         WHEN 'grading' THEN 4
         WHEN 'clarification' THEN 5
         WHEN 'effluent-plant' THEN 6
         WHEN 'cages-track' THEN 7
         WHEN 'engine-room' THEN 8
         WHEN 'cpo-dispatch' THEN 9
         WHEN 'sterilizer' THEN 10
         WHEN 'boiler-room' THEN 11
         WHEN 'kernel-dispatch' THEN 12
         WHEN 'kernel-plant' THEN 13
         WHEN 'process-water' THEN 14
         WHEN 'threshing' THEN 15
         WHEN 'depricarping' THEN 16
         WHEN 'solid-waste-disposal' THEN 17
         WHEN 'process-quality-control' THEN 18
         ELSE 99
       END ASC,
       id ASC`,
    [productionLineId],
  )

  return rows.map(toStationSlot)
}

interface MachineryCountRow {
  machinery_count: number | null
}

/**
 * getMachineryCountForCagesTrackStation() — screen-012--form-cages-track
 * fix (2026-08-20): the Cages Tipped Time grid's checklist column count
 * (Cage 1..N) now derives from this business unit's active Cages Track
 * station's `machinery_count` (synced via
 * productionLineRepo.fetchAndCacheStationsForProductionLine(), see
 * `station.machinery_count`'s own migration comment) instead of the
 * removed `mill_setting.jumlah_cages`.
 *
 * Scoped by `business_unit_id`, not `production_line_id` — same
 * single-business-unit-per-session simplification
 * FormCagesTrackView.vue's own business_unit_id derivation already
 * documents (`cages_track_record.station_id`/a "current production line"
 * are not threaded through this screen's navigation yet, matching the
 * pre-existing gap already noted there). If a business unit ever has more
 * than one Production Line each with its own active Cages Track station,
 * this picks whichever synced most recently — a known limitation of the
 * same class as `getActiveAndPlaceholderStations()`'s own dedup subquery,
 * not a new one introduced here.
 */
export async function getMachineryCountForCagesTrackStation(businessUnitId: string): Promise<number | null> {
  const rows = await query<MachineryCountRow>(
    `SELECT machinery_count FROM station
     WHERE business_unit_id = ? AND type = 'cages-track' AND is_active = 1
     ORDER BY updated_at DESC, id DESC
     LIMIT 1`,
    [businessUnitId],
  )

  return rows[0]?.machinery_count ?? null
}

export const stationRepo = {
  getActiveAndPlaceholderStations,
  getActiveAndPlaceholderStationsForProductionLine,
  getMachineryCountForCagesTrackStation,
}

export default stationRepo
