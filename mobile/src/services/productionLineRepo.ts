import { run } from '@/services/localDb'

/**
 * productionLineRepo — screen-006--station-list / usecase-006--station-list
 * "Pilih Stasiun", updated 2026-08-20 for the Production Line feature
 * (entity-catalog v9: Business Unit → Production Line → Station — a
 * Business Unit/mill can now have several Production Lines, each with its
 * own full set of 15 stations).
 *
 * Talks to the two self-scoped mobile-facing backend endpoints
 * (App\Http\Controllers\Api\ProductionLineController::current()/
 * currentStations()):
 *   - GET /api/production-lines/current — the authenticated user's own
 *     business unit's Production Lines (StationListView.vue's new picker
 *     step, before the station-tile grid — auto-skipped when there is
 *     exactly one).
 *   - GET /api/production-lines/current/stations?production_line_id=<id> —
 *     REAL backend Station rows (real UUIDs, not the synthetic ids
 *     `seedDefaultStationsIfNeeded()` generates) for one Production Line,
 *     including `machinery_count` for the Cages Track station.
 *
 * fetchAndCacheStationsForProductionLine() upserts these real rows into the
 * local `station` table — this finally fixes the long-standing "local rows
 * use synthetic ids, not real server Station UUIDs" limitation documented
 * on localSchema.ts's `fetchAndCacheStationIconOverrides()` (which had to
 * fall back to a fragile `(business_unit_id, type)` match instead of a
 * proper id join, and explicitly WOULD BREAK once a mill had more than one
 * active station of the same type — now guaranteed true for any Business
 * Unit with 2+ Production Lines). Real ids also make the `icon`/
 * `machinery_count` overrides trivially correct going forward, no fragile
 * matching needed.
 *
 * Both functions dynamic-import `@/services/apiClient` (mirrors
 * localSchema.ts's `fetchAndCacheMillSetting()`/
 * `fetchAndCacheStationIconOverrides()` precedent exactly) rather than a
 * module-level import, keeping this file's own import surface minimal for
 * callers that only need the (already-mocked-in-tests) `run()` primitive.
 */

export interface ProductionLineOption {
  id: string
  name: string
  code: string | null
}

interface CurrentStationRow {
  id: string
  name: string
  type: string
  icon: string | null
  is_active: boolean
  machinery_count: number | null
}

/**
 * fetchCurrentProductionLines() — GET /api/production-lines/current. No
 * local cache/fallback: the picker step needs a live, authoritative list
 * (production lines are genuinely server-authored master data, edited via
 * the web "Kelola Production Line" screen) — same reasoning
 * fetchAndCacheMillSetting() gives for not treating mill-setting as
 * hardcodable local seed data. Callers are expected to treat a rejected
 * promise (offline, 404 — user has no business_unit_id) as "no production
 * lines known right now" and fall back gracefully (see
 * StationListView.vue's onMounted()).
 */
export async function fetchCurrentProductionLines(): Promise<ProductionLineOption[]> {
  const { default: apiClient } = await import('@/services/apiClient')

  const response = await apiClient.get('/api/production-lines/current')

  return (response.data?.data ?? []) as ProductionLineOption[]
}

/**
 * fetchAndCacheStationsForProductionLine() — GET
 * /api/production-lines/current/stations?production_line_id=<id>, then
 * upserts each returned row into the local `station` table keyed by its
 * REAL backend id (`ON CONFLICT(id) DO UPDATE`) — unlike
 * `seedDefaultStationsIfNeeded()`'s synthetic
 * `default-${businessUnitId}-${idSuffix}` ids, so a row synced here can
 * never collide with a synthetic-seed row for the same slot. `businessUnitId`
 * is passed in explicitly (not re-derived from the response) since the
 * denormalized `business_unit_id` column is still read by
 * `stationRepo.getActiveAndPlaceholderStations()`'s legacy business-
 * unit-scoped query.
 */
export async function fetchAndCacheStationsForProductionLine(
  productionLineId: string,
  businessUnitId: string,
): Promise<void> {
  const { default: apiClient } = await import('@/services/apiClient')

  const response = await apiClient.get('/api/production-lines/current/stations', {
    params: { production_line_id: productionLineId },
  })
  const stations = (response.data?.data ?? []) as CurrentStationRow[]
  const now = new Date().toISOString()

  // Clears any leftover rows from the legacy synthetic seed
  // (seedDefaultStationsIfNeeded(), production_line_id IS NULL) for this
  // business unit before inserting the real set below — prevents the
  // synthetic and real stations from ever coexisting and rendering as
  // doubled tiles (2026-08-20, found via user report). Scoped to
  // `production_line_id IS NULL` only, so other Production Lines' already-
  // synced real rows (a different, non-null production_line_id) are left
  // untouched.
  await run('DELETE FROM station WHERE business_unit_id = ? AND production_line_id IS NULL', [businessUnitId])

  for (const station of stations) {
    await run(
      `INSERT INTO station (id, business_unit_id, production_line_id, name, type, is_active, icon, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
       ON CONFLICT(id) DO UPDATE SET
         business_unit_id = excluded.business_unit_id,
         production_line_id = excluded.production_line_id,
         name = excluded.name,
         type = excluded.type,
         is_active = excluded.is_active,
         icon = excluded.icon,
         updated_at = excluded.updated_at`,
      [
        station.id,
        businessUnitId,
        productionLineId,
        station.name,
        station.type,
        station.is_active ? 1 : 0,
        station.icon,
        now,
        now,
      ],
    )
  }
}

export const productionLineRepo = {
  fetchCurrentProductionLines,
  fetchAndCacheStationsForProductionLine,
}

export default productionLineRepo
