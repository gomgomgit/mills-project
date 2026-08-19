import { query } from '@/services/localDb'

/**
 * millSettingRepo — read-only access to the local `mill_setting` reference
 * cache table (see localSchema.ts's CREATE_MILL_SETTING and
 * fetchAndCacheMillSetting() for how the table is populated). Mirrors
 * stationRepo.ts's shape/conventions: a thin typed wrapper over
 * localDb.ts's `query()`, never writes.
 *
 * Consumed by screen-005--home (home_page_image), screen-006--station-list
 * (indirectly, via station.icon — not this repo), and
 * screen-012--form-cages-track (jumlah_cages, to size the Cages Tipped Time
 * grid).
 */

export interface MillSetting {
  id: string
  businessUnitId: string
  appName: string | null
  logo: string | null
  homePageImage: string | null
  jumlahCages: number | null
}

interface MillSettingRow {
  id: string
  business_unit_id: string
  app_name: string | null
  logo: string | null
  home_page_image: string | null
  jumlah_cages: number | null
}

function toMillSetting(row: MillSettingRow): MillSetting {
  return {
    id: row.id,
    businessUnitId: row.business_unit_id,
    appName: row.app_name,
    logo: row.logo,
    homePageImage: row.home_page_image,
    jumlahCages: row.jumlah_cages,
  }
}

/**
 * Loads the cached mill-setting for `businessUnitId`, or `null` if it has
 * not been synced yet (e.g. first-run offline before any successful
 * login-time fetch — see localSchema.ts's `fetchAndCacheMillSetting()`).
 */
export async function getMillSetting(businessUnitId: string): Promise<MillSetting | null> {
  const rows = await query<MillSettingRow>(
    `SELECT id, business_unit_id, app_name, logo, home_page_image, jumlah_cages
     FROM mill_setting WHERE business_unit_id = ?`,
    [businessUnitId],
  )

  return rows[0] ? toMillSetting(rows[0]) : null
}

/**
 * Convenience accessor for screen-012--form-cages-track's business_logic
 * step 5 (grid column count) — returns `null` if no mill-setting is cached
 * yet for this business unit, distinct from a cached `jumlah_cages = 0`
 * (both cases disable "Tambah baris" per that screen's tech spec, but the
 * distinction is preserved here for the caller to message appropriately).
 */
export async function getJumlahCages(businessUnitId: string): Promise<number | null> {
  const setting = await getMillSetting(businessUnitId)

  return setting?.jumlahCages ?? null
}

export const millSettingRepo = {
  getMillSetting,
  getJumlahCages,
}

export default millSettingRepo
