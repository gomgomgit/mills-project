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

export const stationRepo = {
  getActiveAndPlaceholderStations,
  getActiveAndPlaceholderStationsForProductionLine,
}

export default stationRepo
