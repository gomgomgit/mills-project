/**
 * localDate — SATU sumber untuk tanggal/waktu "dinding" perangkat (audit
 * 2026-10-04).
 *
 * Masalah yang ditutup: 14 `*RecordRepo.createDraft()` menyimpan
 * `new Date().toISOString()` (UTC) sebagai `date`. Antara 00:00–06:59 WIB
 * itu tanggal KEMARIN — draft tidak terhitung "Hari Ini", tersembunyi oleh
 * filter tanggal Data Preview, dan terkirim ke server dengan tanggal salah.
 * Selain itu Data Preview mengikat string ISO UTC ke `<input type=
 * "datetime-local">`, yang hanya menerima `YYYY-MM-DDTHH:mm` — hasilnya
 * input kosong (mm/dd/yyyy --:--).
 *
 * Konvensi penyimpanan lokal (SQLite) sejak berkas ini:
 *   - kolom tanggal  → 'YYYY-MM-DD' (tanggal lokal perangkat)
 *   - kolom datetime → 'YYYY-MM-DDTHH:mm:ss' (jam lokal, TANPA sufiks)
 * Sama dengan `nowLocalDateTimeString()` yang sudah dipakai form-form.
 * Saat dikirim ke server, datetime diberi offset eksplisit perangkat
 * (`toOffsetDateTime()`, mis. '+07:00') supaya server tidak perlu menebak
 * zona waktunya.
 */

function pad(value: number): string {
  return String(value).padStart(2, '0')
}

const DATE_ONLY = /^\d{4}-\d{2}-\d{2}$/
/** Local wall-clock datetime with no zone suffix, e.g. '2026-10-04T01:30' or '...T01:30:00(.000)'. */
const NAIVE_DATETIME = /^(\d{4}-\d{2}-\d{2})[T ](\d{2}):(\d{2})(?::(\d{2})(?:\.\d+)?)?$/

/** Tanggal lokal perangkat, 'YYYY-MM-DD'. */
export function todayLocalDateString(now: Date = new Date()): string {
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`
}

/** Jam lokal perangkat tanpa sufiks zona, 'YYYY-MM-DDTHH:mm:ss'. */
export function nowLocalDateTimeString(now: Date = new Date()): string {
  return `${todayLocalDateString(now)}T${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`
}

/** Offset zona perangkat untuk `date`, mis. '+07:00'. */
export function localOffsetString(date: Date = new Date()): string {
  const minutes = -date.getTimezoneOffset()
  const sign = minutes >= 0 ? '+' : '-'
  const abs = Math.abs(minutes)
  return `${sign}${pad(Math.floor(abs / 60))}:${pad(abs % 60)}`
}

/**
 * Parse nilai tersimpan apa pun (date-only, naive lokal, ISO dengan Z atau
 * offset) menjadi Date. Date-only dibaca sebagai tengah malam LOKAL (bukan
 * UTC seperti `new Date('YYYY-MM-DD')`). Null bila kosong/tidak valid.
 */
export function parseStoredDateTime(value: string | null | undefined): Date | null {
  if (!value || value.trim() === '') return null
  const trimmed = value.trim()

  if (DATE_ONLY.test(trimmed)) {
    const [y, m, d] = trimmed.split('-').map(Number)
    return new Date(y, m - 1, d)
  }

  const naive = NAIVE_DATETIME.exec(trimmed)
  if (naive) {
    const [y, m, d] = naive[1].split('-').map(Number)
    return new Date(y, m - 1, d, Number(naive[2]), Number(naive[3]), Number(naive[4] ?? 0))
  }

  const parsed = new Date(trimmed)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

/**
 * Normalisasi nilai tersimpan ke tanggal lokal 'YYYY-MM-DD'. Nilai lama
 * berformat ISO UTC ('...Z') dikonversi ke tanggal LOKAL instan itu — jadi
 * '2026-10-03T18:30:00.000Z' menjadi '2026-10-04' di WIB. Null bila kosong.
 */
export function toLocalDateString(value: string | null | undefined): string | null {
  if (value && DATE_ONLY.test(value.trim())) return value.trim()
  const parsed = parseStoredDateTime(value)
  return parsed ? todayLocalDateString(parsed) : null
}

/** Normalisasi ke datetime lokal tanpa sufiks 'YYYY-MM-DDTHH:mm:ss'. */
export function toLocalDateTimeString(value: string | null | undefined): string | null {
  const parsed = parseStoredDateTime(value)
  return parsed ? nowLocalDateTimeString(parsed) : null
}

/** Nilai untuk `<input type="datetime-local">` ('YYYY-MM-DDTHH:mm'), '' bila kosong. */
export function toDateTimeLocalInputValue(value: string | null | undefined): string {
  const local = toLocalDateTimeString(value)
  return local ? local.slice(0, 16) : ''
}

/** Nilai untuk `<input type="date">` ('YYYY-MM-DD'), '' bila kosong. */
export function toDateInputValue(value: string | null | undefined): string {
  return toLocalDateString(value) ?? ''
}

/**
 * Datetime untuk payload server: jam lokal + offset eksplisit perangkat,
 * mis. '2026-10-04T01:30:00+07:00'. Null bila kosong/tidak valid.
 */
export function toOffsetDateTime(value: string | null | undefined): string | null {
  const parsed = parseStoredDateTime(value)
  return parsed ? `${nowLocalDateTimeString(parsed)}${localOffsetString(parsed)}` : null
}
