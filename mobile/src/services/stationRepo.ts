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
}

interface StationRow {
  id: string
  business_unit_id: string
  name: string
  type: StationType
  is_active: number | boolean
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
    `SELECT id, business_unit_id, name, type, is_active
     FROM station WHERE business_unit_id = ?
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

export const stationRepo = {
  getActiveAndPlaceholderStations,
}

export default stationRepo
