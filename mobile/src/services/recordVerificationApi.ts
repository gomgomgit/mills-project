import apiClient, { type NormalizedApiError } from '@/services/apiClient'
import { query, run } from '@/services/localDb'

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
  // apiClient's normalizer sets `network: true` only when the request went
  // out and no response came back at all — offline / unreachable server.
  // Audit 2026-10-04: this used to be "no `status`", which also matched a
  // plain thrown Error, so any non-HTTP failure was misreported to the user
  // as "butuh koneksi internet" while the device was online.
  return typeof error === 'object' && error !== null && (error as NormalizedApiError).network === true
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

  // `/api` prefix wajib — sama seperti setiap repo lain (VITE_API_BASE_URL
  // adalah origin server, bukan origin + /api). Tanpa prefix ini request
  // jatuh ke route web yang tidak ber-CORS → browser memblokirnya → UI
  // salah menampilkan "butuh koneksi" padahal online (audit 2026-10-04).
  const response = await apiClient.patch<VerificationResponse>(
    `/api/records/${stationType}/${serverId}/verification`,
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

/**
 * Tarik status verifikasi TERBARU dari server untuk record milik user ini
 * yang sudah tersinkron (audit 2026-10-04, SEDANG): tanpa ini operator
 * terus melihat "Belum diperiksa" setelah Supervisor memverifikasi lewat web,
 * karena sync aplikasi ini hanya satu arah (push).
 *
 * Endpoint: GET /api/records/{stationType}/verification?ids[]=<server_id>…
 * → { data: [{ id, checked_by, checked_by_name, acknowledged_by,
 * acknowledged_by_name }] } — pasangan baca dari PATCH di atas, dijaga
 * auth:web,sanctum untuk ke-4 peran. Endpoint GET /api/<station>-records/{id}
 * yang ada hanya `auth:web` + tanpa operator, jadi tidak bisa dipakai dari
 * mobile (token Sanctum → 401).
 *
 * BEST-EFFORT, SENYAP: offline, endpoint belum tersedia, atau error apa pun
 * → data lokal dibiarkan apa adanya dan fungsi mengembalikan 0. Data
 * Preview tidak boleh gagal dimuat karena ini.
 *
 * @returns jumlah record lokal yang statusnya berubah
 */
/**
 * Server tanpa endpoint baca ini (404/405) → berhenti mencoba sampai app
 * dimuat ulang, supaya setiap pembukaan Data Preview tidak menghasilkan 404
 * baru. Diekspor hanya untuk test.
 */
let pullUnsupported = false
export function resetVerificationPullSupport(): void {
  pullUnsupported = false
}

export async function pullVerificationStatus(
  stationType: string,
  localTable: string,
  userId: string | null | undefined,
  options: { timeoutMs?: number; limit?: number } = {},
): Promise<number> {
  if (!userId || pullUnsupported) return 0

  try {
    const rows = await query<{ id: string; server_id: string; checked_by: string | null; acknowledged_by: string | null }>(
      `SELECT id, server_id, checked_by, acknowledged_by FROM ${localTable}
       WHERE created_by = ? AND status = 'synced' AND server_id IS NOT NULL
       ORDER BY updated_at DESC LIMIT ?`,
      [userId, options.limit ?? 100],
    )

    if (rows.length === 0) return 0

    const response = await apiClient.get<{ data?: VerificationResponse[] }>(
      `/api/records/${stationType}/verification`,
      { params: { ids: rows.map((row) => row.server_id) }, timeout: options.timeoutMs ?? 5000 },
    )

    const byServerId = new Map((response.data?.data ?? []).map((item) => [item.id, item]))
    let changed = 0

    for (const row of rows) {
      const remote = byServerId.get(row.server_id)
      if (!remote) continue
      if (remote.checked_by === row.checked_by && remote.acknowledged_by === row.acknowledged_by) continue

      await run(
        `UPDATE ${localTable}
         SET checked_by = ?, checked_by_name = ?, acknowledged_by = ?, acknowledged_by_name = ?
         WHERE id = ?`,
        [remote.checked_by, remote.checked_by_name, remote.acknowledged_by, remote.acknowledged_by_name, row.id],
      )
      changed += 1
    }

    return changed
  } catch (error) {
    const status = (error as NormalizedApiError | null)?.status
    if (status === 404 || status === 405) {
      pullUnsupported = true
    }
    return 0
  }
}
