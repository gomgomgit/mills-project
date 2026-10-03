import millSettingRepo from '@/services/millSettingRepo'
import { pushSavedRecordNow } from '@/services/syncService'
import { useAuthStore } from '@/stores/auth'

/**
 * writeThroughSync — "kirim data langsung ke server saat disimpan"
 * (product decision 2026-09-14).
 *
 * When a mill turns on Mills Setting's `immediate_sync_enabled`, a save
 * that lands in local SQLite is pushed to the server in the same breath,
 * instead of sitting on the device until an operator remembers to tap
 * "Sinkronisasi". Turning the flag off restores exactly the old behaviour;
 * nothing else in the app changes shape.
 *
 * What this deliberately does NOT do is bypass the local database. The
 * local write stays the source of truth for the save, which is what keeps
 * three things working:
 *   - drafts and the Pause/resume flow, which have no server-side
 *     representation at all (the API only knows saved/synced),
 *   - saving with no signal, which must keep succeeding,
 *   - the existing manual sync, which still picks up anything this could
 *     not push.
 *
 * Transient failures here are therefore SILENT to the caller: the record is
 * already safely saved locally, and a failed push just means it waits for
 * the next sync — exactly the state it would have been in before this
 * feature existed. Surfacing an error would be actively misleading, since
 * nothing was lost.
 *
 * A REJECTION is different (2026-10-03). When the server answers 4xx — most
 * importantly 422 PERIOD_CLOSED, usecase-141 — retrying will be refused
 * again every time, and the operator only learned of it hours later on the
 * manual sync screen. So a rejection is reported back with the server's own
 * message; the record still stays 'saved' locally, nothing is lost or
 * rolled back, and the caller decides how to show it.
 */

/** Returns true when this mill has opted into write-through saving. */
export async function isImmediateSyncEnabled(): Promise<boolean> {
  const businessUnitId = useAuthStore().currentUser?.business_unit_id

  if (!businessUnitId) {
    return false
  }

  try {
    const setting = await millSettingRepo.getMillSetting(businessUnitId)
    return setting?.immediateSyncEnabled ?? false
  } catch {
    // No cached mill setting yet (first run, offline) — treat as off rather
    // than blocking the save path on a lookup that is only an optimisation.
    return false
  }
}

export interface WriteThroughOutcome {
  /** True when the record reached the server and is now 'synced'. */
  synced: boolean
  /**
   * The server's message when it REJECTED the record (HTTP 4xx), else null.
   * Offline, feature off, no production line — all null: those just wait
   * for the next sync and must stay silent.
   */
  rejection: string | null
}

/**
 * Call right after a successful local save. Pushes that one record when the
 * mill has write-through enabled; otherwise does nothing at all.
 *
 * @param localTable  the station's local table, e.g. 'threshing_record'
 * @param recordId    the local row id that was just saved
 * @returns never throws; `synced` false is NOT a save failure — the record
 *          is saved locally either way. Only a non-null `rejection` is
 *          worth telling the operator about.
 */
export async function syncAfterSave(localTable: string, recordId: string): Promise<WriteThroughOutcome> {
  if (!(await isImmediateSyncEnabled())) {
    return { synced: false, rejection: null }
  }

  try {
    const result = await pushSavedRecordNow(localTable, recordId)

    if (result?.ok) {
      return { synced: true, rejection: null }
    }

    const rejected = result?.status !== undefined && result.status >= 400 && result.status < 500

    return { synced: false, rejection: rejected ? (result?.reason ?? null) : null }
  } catch {
    return { synced: false, rejection: null }
  }
}

export default { isImmediateSyncEnabled, syncAfterSave }
