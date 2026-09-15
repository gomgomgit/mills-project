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
 * Every failure here is therefore SILENT to the caller: the record is
 * already safely saved locally, and a failed push just means it waits for
 * the next sync — exactly the state it would have been in before this
 * feature existed. Surfacing an error would be actively misleading, since
 * nothing was lost.
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

/**
 * Call right after a successful local save. Pushes that one record when the
 * mill has write-through enabled; otherwise does nothing at all.
 *
 * @param localTable  the station's local table, e.g. 'threshing_record'
 * @param recordId    the local row id that was just saved
 * @returns true when the record reached the server, false in every other
 *          case (feature off, offline, push rejected) — callers may use
 *          this for a "tersinkron" hint, but must not treat false as a
 *          save failure.
 */
export async function syncAfterSave(localTable: string, recordId: string): Promise<boolean> {
  if (!(await isImmediateSyncEnabled())) {
    return false
  }

  try {
    const result = await pushSavedRecordNow(localTable, recordId)
    return result?.ok ?? false
  } catch {
    return false
  }
}

export default { isImmediateSyncEnabled, syncAfterSave }
