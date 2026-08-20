import { query } from '@/services/localDb'

/**
 * stationRepo — screen-006--station-list / usecase-006--station-list
 * "Pilih Stasiun" business_logic step 1.
 *
 * Reads the local (offline) `station` reference table — mirrored on the
 * device with the same shape as the server `station` entity (per
 * entity_catalog), pre-seeded by an earlier sync flow (a different
 * screen's responsibility, not this one). This repo is read-only: it never
 * writes to `station`, and it does not synthesize the "15 slots (3 active +
 * 12 placeholder)" shape in code — that shape is expected to already exist
 * as 15 rows in the local table (one per station master-data record synced
 * from the server, including the currently-not-implemented / not-yet-active
 * ones with `is_active = 0`). See localDb.ts's header comment, which lists
 * `station` alongside the 3 write-capable local tables as an existing local
 * table this screen assumes rather than creates.
 */

export type StationType = 'weighbridge' | 'grading' | 'cages-track' | 'other'

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
 * of 15 synced rows (3 active real station types + 12 inactive
 * placeholder/not-yet-implemented entries), per business_logic step 1.
 *
 * Ordered by a FIXED canonical grid order (uiux-spec ver 2,
 * screen_type_patterns[type=list].body_area — mobile "list" sub-pattern):
 * the 3 active MVP stations first, in the exact order
 * Weighbridge → Grading → Cages Track, followed by the 12 placeholder
 * ("other") stations in a stable non-alphabetical order (`id ASC`).
 * Deliberately NOT alphabetical by `name` — the uiux-spec explicitly calls
 * out that the grid order must not be alphabetized.
 */
export async function getActiveAndPlaceholderStations(businessUnitId: string): Promise<StationSlot[]> {
  const rows = await query<StationRow>(
    `SELECT id, business_unit_id, name, type, is_active, icon
     FROM station
     WHERE business_unit_id = ?
       AND id = (
         SELECT s2.id FROM station s2
         WHERE s2.business_unit_id = station.business_unit_id AND s2.type = station.type
         ORDER BY s2.updated_at DESC, s2.id DESC
         LIMIT 1
       )
     ORDER BY
       CASE type
         WHEN 'weighbridge' THEN 1
         WHEN 'grading' THEN 2
         WHEN 'cages-track' THEN 3
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
       AND id = (
         SELECT s2.id FROM station s2
         WHERE s2.production_line_id = station.production_line_id AND s2.type = station.type
         ORDER BY s2.updated_at DESC, s2.id DESC
         LIMIT 1
       )
     ORDER BY
       CASE type
         WHEN 'weighbridge' THEN 1
         WHEN 'grading' THEN 2
         WHEN 'cages-track' THEN 3
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
