import { query, run } from '@/services/localDb'
import { nowLocalDateTimeString } from '@/utils/localDate'

/**
 * shiftRepo — jadwal Shift per Production Line di perangkat, dan satu
 * pertanyaan yang dijawabnya: SHIFT MANA YANG SEDANG BERJALAN SEKARANG.
 *
 * ── MENGAPA PERANGKAT YANG MENYETEMPEL, BUKAN SERVER ────────────────────
 *
 * `shift_id` pada record menjawab "pekerjaan ini dilakukan pada shift mana".
 * Server tidak dapat menjawabnya: yang ia ketahui adalah kapan record
 * DISINKRONKAN, dan sinkronisasi memang terjadi setelah shift berakhir — itu
 * rancangannya, bukan keterlambatan. Menurunkannya di server akan salah
 * SECARA SISTEMATIS, bukan sesekali, dan salahnya akan terlihat masuk akal.
 *
 * Karena itu jadwalnya DICACHE, bukan ditanyakan saat dibutuhkan: saat
 * operator menekan Simpan, perangkat sering sedang offline — itu keadaan
 * normalnya, bukan kasus tepi.
 *
 * ── RUMUSNYA SALINAN PERSIS MILIK SERVER ────────────────────────────────
 *
 * minuteOfDay / durationMinutes / segmentsFor di bawah adalah salinan
 * karakter-demi-karakter aturan di `App\Services\ShiftService`, termasuk
 * pembacaan jam berimpit sebagai 1440 menit dan pemecahan shift yang
 * melintasi tengah malam menjadi dua segmen. Keduanya WAJIB sepakat: server
 * memeriksa ulang bahwa shift kiriman memang milik line record itu, dan
 * perangkat yang memakai rumus berbeda akan menghasilkan stempel yang
 * ditolak — atau, lebih buruk, diterima dan salah.
 *
 * SATU-SATUNYA PERBEDAAN YANG DISENGAJA dari findOverlap() di server:
 * batasnya SETENGAH TERBUKA, `start <= m < end`. Pertanyaannya memang lain.
 * "Bolehkah dua shift berdampingan" dijawab dengan batas inklusif, sehingga
 * pergantian yang berimpit diterima; "menit ini milik siapa" wajib punya
 * TEPAT SATU jawaban, sehingga dengan Pagi 07:00–15:00 dan Siang 15:00–23:00
 * menit 15:00 adalah milik Siang saja.
 *
 * ── JAM DINDING, BUKAN INSTAN ───────────────────────────────────────────
 *
 * Jam shift tidak pernah dikonversi zona. Shift 23:00–07:00 berjalan pada jam
 * dinding itu, dan memperlakukannya sebagai instan akan menggesernya tujuh
 * jam — kelas cacat yang sama dengan yang ditutup normalizeLegacyUtcDates()
 * pada kolom tanggal.
 */

export interface CachedShift {
  id: string
  production_line_id: string
  name: string
  start_time: string
  end_time: string
}

const DAY_MINUTES = 1440

/** Menit-ke-dalam-hari dari 'HH:MM' atau 'HH:MM:SS'. */
function minuteOfDay(time: string): number {
  const parts = (time || '').split(':')
  const hours = Number.parseInt(parts[0] ?? '0', 10)
  const minutes = Number.parseInt(parts[1] ?? '0', 10)

  if (Number.isNaN(hours) || Number.isNaN(minutes)) {
    return Number.NaN
  }

  return (hours % 24) * 60 + minutes
}

/**
 * Durasi shift dalam menit, memperhitungkan lintasan tengah malam.
 * Jam BERIMPIT dibaca sebagai SATU HARI PENUH (1440), bukan nol — itulah
 * satu-satunya cara mengungkapkan shift 24 jam, dan jalur tulis server
 * menerimanya pada line yang belum punya shift lain.
 */
function durationMinutes(startTime: string, endTime: string): number {
  const diff = (minuteOfDay(endTime) - minuteOfDay(startTime) + DAY_MINUTES) % DAY_MINUTES

  return diff === 0 ? DAY_MINUTES : diff
}

/** Satu segmen bila shift tidak melintasi titik nol sumbu, dua bila ia melintasinya. */
function segmentsFor(startOffset: number, duration: number): Array<[number, number]> {
  const end = startOffset + duration

  return end <= DAY_MINUTES
    ? [[startOffset, end]]
    : [
        [startOffset, DAY_MINUTES],
        [0, end - DAY_MINUTES],
      ]
}

/**
 * Menyegarkan cache jadwal satu Production Line dari server.
 *
 * Baris line ini DIBUANG LEBIH DULU, lalu ditulis ulang dari respons — bukan
 * sekadar upsert. Shift yang dihapus Admin (soft delete di server) tidak
 * muncul lagi di respons, dan upsert murni akan meninggalkannya hidup di
 * perangkat selamanya: operator akan terus menyetempel shift yang sudah tidak
 * dijalankan siapa pun, dan server akan menolaknya saat sync. Cakupan
 * penghapusan dibatasi pada line ini saja, sehingga jadwal line lain yang
 * sudah tercache tidak ikut hilang ketika operator berpindah line.
 *
 * Kegagalan jaringan SENGAJA dibiarkan merambat ke pemanggil, yang
 * memperlakukannya sebagai tidak-fatal: cache lama lebih baik daripada tidak
 * ada cache sama sekali, dan satu-satunya akibat cache basi adalah stempel
 * yang ditolak server — bukan data yang hilang.
 */
export async function fetchAndCacheShiftsForProductionLine(productionLineId: string): Promise<void> {
  const { default: apiClient } = await import('@/services/apiClient')

  const response = await apiClient.get('/api/production-lines/current/shifts', {
    params: { production_line_id: productionLineId },
  })

  const shifts = (response.data?.data ?? []) as Array<Omit<CachedShift, 'production_line_id'>>
  const now = new Date().toISOString()

  await run('DELETE FROM shift WHERE production_line_id = ?', [productionLineId])

  for (const shift of shifts) {
    await run(
      `INSERT INTO shift (id, production_line_id, name, start_time, end_time, updated_at)
       VALUES (?, ?, ?, ?, ?, ?)
       ON CONFLICT(id) DO UPDATE SET
         production_line_id = excluded.production_line_id,
         name = excluded.name,
         start_time = excluded.start_time,
         end_time = excluded.end_time,
         updated_at = excluded.updated_at`,
      [shift.id, productionLineId, shift.name, shift.start_time, shift.end_time, now],
    )
  }
}

/** Jadwal satu line yang sedang tercache — urut jam mulai, untuk ditampilkan atau diuji. */
export async function getCachedShifts(productionLineId: string): Promise<CachedShift[]> {
  // `?? []` BUKAN hiasan: `query()` dipanggil lewat lapisan driver yang
  // berbeda di perangkat (@capacitor-community/sqlite) dan di web
  // (jeep-sqlite), dan tak satu pun jalur di berkas ini boleh melempar
  // hanya karena sebuah SELECT tidak memulangkan apa pun. Stempel shift
  // adalah tambahan pada penyimpanan — ia TIDAK BOLEH menjadi cara baru
  // sebuah penyimpanan gagal.
  const rows = await query<CachedShift>(
    `SELECT id, production_line_id, name, start_time, end_time
     FROM shift
     WHERE production_line_id = ?
     ORDER BY start_time, name`,
    [productionLineId],
  )

  return rows ?? []
}

/**
 * Shift yang sedang berjalan pada sebuah line di sebuah saat; null bila saat
 * itu tidak tercakup shift mana pun.
 *
 * NULL ADALAH JAWABAN YANG SAH, DAN SERING: celah jadwal diizinkan oleh
 * rancangan (layar Kelola Shift menandainya, tidak menolaknya), sebuah line
 * boleh belum punya shift sama sekali, dan cache perangkat bisa saja masih
 * kosong karena belum pernah online. Pemanggil TIDAK boleh menolak menyimpan
 * karenanya — menukar data lapangan dengan kelengkapan master akan menghukum
 * orang yang tidak dapat memperbaikinya.
 *
 * Data yang bertindih (mustahil lahir dari aplikasi, mungkin dari data lama)
 * dijawab deterministik: segmen yang paling BARU dimulai sebelum menit itu.
 * Memulangkan yang pertama ditemukan akan membuat jawabannya bergantung pada
 * urutan baris SQLite, dan jawaban yang berubah tanpa datanya berubah adalah
 * yang paling mahal ditelusuri.
 */
export async function resolveShiftIdForLine(
  productionLineId: string | null | undefined,
  at: Date = new Date(),
): Promise<string | null> {
  if (!productionLineId) {
    return null
  }

  const minute = at.getHours() * 60 + at.getMinutes()
  const shifts = await getCachedShifts(productionLineId)

  let found: string | null = null
  let foundStart = -1

  for (const shift of shifts) {
    const start = minuteOfDay(shift.start_time)

    if (Number.isNaN(start)) {
      continue
    }

    for (const [from, to] of segmentsFor(start, durationMinutes(shift.start_time, shift.end_time))) {
      if (minute < from || minute >= to) {
        continue
      }

      if (from > foundStart) {
        found = shift.id
        foundStart = from
      }
    }
  }

  return found
}

/**
 * Shift yang sedang berjalan untuk sebuah STASIUN — bentuk yang dipakai ke-18
 * `*RecordRepo.saveDraft()`, karena yang dipegang record lokal adalah
 * `station_id`, bukan line.
 *
 * `station_id` null adalah keadaan yang sudah dikenal (lihat activeStation.ts:
 * draft lama dibuat sebelum stasiunnya di-resolve), dan jawabannya null —
 * bukan galat.
 */
export async function resolveShiftIdForStation(
  stationId: string | null | undefined,
  at: Date = new Date(),
): Promise<string | null> {
  if (!stationId) {
    return null
  }

  const rows = await query<{ production_line_id: string | null }>(
    'SELECT production_line_id FROM station WHERE id = ? LIMIT 1',
    [stationId],
  )

  return resolveShiftIdForLine(rows?.[0]?.production_line_id ?? null, at)
}

/**
 * MENYETEMPEL `shift_id` pada satu baris record lokal — bentuk yang dipanggil
 * ke-18 `*RecordRepo.saveDraft()`.
 *
 * ── MENGAPA UPDATE TERSENDIRI, BUKAN IKUT UPDATE HEADER ─────────────────
 *
 * Pernyataan UPDATE header di ke-18 repo berbeda-beda jumlah dan urutan
 * parameternya, dan menyisipkan satu parameter ke tengah 18 daftar berbeda
 * adalah tempat kekeliruan transkripsi hidup — kekeliruan yang menyimpan
 * nilai ke kolom yang salah, lalu lolos karena kedua kolomnya TEXT.
 * Pernyataan tersendiri membuat perubahan di ke-18 berkas IDENTIK huruf demi
 * huruf, dan ia memang kepentingan yang berbeda: `shift_id` bukan isian
 * formulir, melainkan stempel yang diputuskan perangkat.
 *
 * Keduanya tidak dibungkus transaksi, dan itu tidak apa-apa: kegagalan di
 * antara keduanya meninggalkan record tersimpan dengan `shift_id` null —
 * keadaan yang memang sudah sah dan sudah ditangani di mana-mana.
 *
 * ── KAPAN: SAAT SIMPAN, BUKAN SAAT DRAFT DIBUAT ─────────────────────────
 *
 * Draft yang dibuka pukul 06:50 dan disimpan pukul 07:10 adalah pekerjaan
 * shift PAGI. Yang ditanyakan adalah kapan operator MENYELESAIKAN catatannya,
 * bukan kapan ia membuka formulirnya.
 *
 * @param table nama tabel record lokal, mis. 'process_water_record'
 */
export async function stampShiftOnRecord(
  table: string,
  recordId: string,
  at: Date = new Date(),
): Promise<string | null> {
  const rows = await query<{ station_id: string | null }>(
    `SELECT station_id FROM ${table} WHERE id = ? LIMIT 1`,
    [recordId],
  )

  const shiftId = await resolveShiftIdForStation(rows?.[0]?.station_id ?? null, at)

  await run(`UPDATE ${table} SET shift_id = ? WHERE id = ?`, [shiftId, recordId])

  return shiftId
}

/**
 * Jam dinding perangkat saat ini sebagai string tanpa sufiks zona — bentuk
 * yang sama dengan yang disimpan kolom datetime lain di basis data lokal.
 * Disediakan di sini supaya test dapat memakai satu sumber yang sama dengan
 * jalur produksinya.
 */
export function shiftStampMoment(now: Date = new Date()): string {
  return nowLocalDateTimeString(now)
}

export default {
  fetchAndCacheShiftsForProductionLine,
  getCachedShifts,
  resolveShiftIdForLine,
  resolveShiftIdForStation,
  stampShiftOnRecord,
  shiftStampMoment,
}
