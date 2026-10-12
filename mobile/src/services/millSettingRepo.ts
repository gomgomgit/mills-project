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
 *
 * `appName`/`logo` DICABUT 2026-10-12 bersama kolom server-nya: tidak ada
 * layar yang membacanya. Kepala halaman Home memakai nama produk yang tetap,
 * terpisah dari nama aplikasi per mill (business_rules screen-005 v5).
 */

export interface MillSetting {
  id: string
  businessUnitId: string
  homePageImage: string | null
  jumlahCages: number | null
  /** Write-through saving: push each save to the server right away (2026-09-14). */
  immediateSyncEnabled: boolean
}

interface MillSettingRow {
  id: string
  business_unit_id: string
  home_page_image: string | null
  jumlah_cages: number | null
  immediate_sync_enabled: number | boolean | null
}

function toMillSetting(row: MillSettingRow): MillSetting {
  return {
    id: row.id,
    businessUnitId: row.business_unit_id,
    homePageImage: row.home_page_image,
    jumlahCages: row.jumlah_cages,
    // SQLite stores this as INTEGER 0/1; a mocked row may use a real boolean.
    immediateSyncEnabled: Boolean(row.immediate_sync_enabled),
  }
}

/**
 * Loads the cached mill-setting for `businessUnitId`, or `null` if it has
 * not been synced yet (e.g. first-run offline before any successful
 * login-time fetch — see localSchema.ts's `fetchAndCacheMillSetting()`).
 */
export async function getMillSetting(businessUnitId: string): Promise<MillSetting | null> {
  const rows = await query<MillSettingRow>(
    // immediate_sync_enabled WAJIB ikut di-SELECT — sebelumnya terlewat,
    // sehingga toMillSetting() selalu membaca undefined → false dan
    // write-through tidak pernah aktif (audit 2026-10-04).
    `SELECT id, business_unit_id, home_page_image, jumlah_cages, immediate_sync_enabled
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
