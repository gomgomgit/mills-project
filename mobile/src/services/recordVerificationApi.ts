import apiClient, { type NormalizedApiError } from '@/services/apiClient'
import { run } from '@/services/localDb'

/**
 * recordVerificationApi — the mobile side of the direct approve/un-approve
 * action added to Data Preview on 2026-09-14 (previously verification could
 * only be set by re-saving the whole record through the Form).
 *
 * ONLINE ONLY, deliberately. syncService.ts is a one-way push of records
 * this device created (`status = 'saved' AND created_by = me`) sent as POST
 * creates — there is no outbox for other mutations, no retry queue, and no
 * pull of server-side changes. So a verification made while offline would
 * have nowhere to go: rather than silently dropping it (or inventing a
 * queue this app's sync design does not have), the call fails loudly and
 * the caller surfaces "butuh koneksi". Building a real outbox is a separate
 * piece of work on syncService, not something to fake here.
 *
 * A record must already exist on the server to be verifiable: local drafts
 * that have never synced have no `server_id` yet, so `canVerifyRecord()`
 * below reports them as not-yet-verifiable and the UI explains why.
 */

export type VerificationLevel = 'checked' | 'acknowledged'

export interface VerificationResponse {
  id: string
  checked_by: string | null
  checked_by_name: string | null
  acknowledged_by: string | null
  acknowledged_by_name: string | null
}

/** Shape every station's local record row shares for verification purposes. */
export interface VerifiableRecord {
  id: string
  server_id?: string | null
  checked_by?: string | null
  acknowledged_by?: string | null
}

export function isNetworkError(error: unknown): boolean {
  // apiClient's normalizer omits `status` only when no response came back
  // at all — i.e. offline / unreachable server.
  return typeof error === 'object' && error !== null && (error as NormalizedApiError).status === undefined
}

/**
 * A record can only be verified once it exists server-side. Returns the
 * server id to address it by, or null when it has never been synced.
 */
export function serverIdOf(record: VerifiableRecord | null | undefined): string | null {
  return record?.server_id ?? null
}

/**
 * Flips one verification level on the server, then mirrors the result into
 * the local row so the Data Preview keeps showing it after going offline.
 *
 * @param stationType kebab station type, e.g. 'cages-track' — must match the
 *                    backend whitelist in RecordVerificationService.
 * @param localTable  the local SQLite table for that station, e.g. 'cages_track_record'
 */
export async function setVerification(
  stationType: string,
  localTable: string,
  record: VerifiableRecord,
  level: VerificationLevel,
  value: boolean,
): Promise<VerificationResponse> {
  const serverId = serverIdOf(record)

  if (!serverId) {
    throw new Error('Record ini belum tersinkron ke server, jadi belum bisa diverifikasi.')
  }

  const response = await apiClient.patch<VerificationResponse>(
    `/records/${stationType}/${serverId}/verification`,
    { level, value },
  )

  const column = level === 'checked' ? 'checked_by' : 'acknowledged_by'
  const stored = level === 'checked' ? response.data.checked_by : response.data.acknowledged_by
  const storedName = level === 'checked' ? response.data.checked_by_name : response.data.acknowledged_by_name

  // The NAME is mirrored alongside the id because there is no local `user`
  // table to resolve an id against offline — Data Preview would otherwise
  // have nothing but a uuid to show. See localSchema.ts's
  // migrateRecordTablesForVerifierNames().
  //
  // Table/column names are caller-supplied constants from the view, never
  // user input; the values stay parameterised.
  await run(
    `UPDATE ${localTable} SET ${column} = ?, ${column}_name = ? WHERE id = ?`,
    [stored, storedName, record.id],
  )

  return response.data
}
