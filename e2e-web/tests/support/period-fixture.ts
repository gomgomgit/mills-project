import { expect, type Browser, type Page } from '@playwright/test'
import { login, PASSWORD } from './auth'
import { statefulHeaders } from './periods'

/**
 * Prasyarat Periode Pelaporan untuk 18 spec `form-*`.
 *
 * ── MENGAPA BERKAS INI ADA, SEJAK 2026-10-02 ─────────────────────────────
 *
 * Kunci periode (usecase-141) menjadikan Periode Pelaporan TERBUKA sebagai
 * prasyarat MENULIS record stasiun. Aturannya whitelist: ke-18
 * *RecordService dan RecordVerificationService menolak 422 PERIOD_CLOSED
 * kecuali ada periode milik mill record itu yang (a) baris
 * `period_stations` untuk jenis stasiunnya berstatus `open` dan (b)
 * rentangnya memuat tanggal kejadian record.
 *
 * Ke-18 spec `form-*` menulis record lewat UI dan — diukur, bukan dugaan —
 * TIDAK SATU PUN menyebut kata "periode". Mereka lahir sebelum aturan ini
 * ada. Tanpa prasyarat di bawah, setiap skenario "Simpan berhasil" gagal
 * sebagai TIMEOUT pada waitForURL/click, bukan sebagai pesan yang menyebut
 * periode — kegagalan yang mahal dibaca justru karena penyebabnya tidak
 * disebut di mana pun.
 *
 * ── MENGAPA SATU PERIODE LEBAR, BUKAN SATU PER TANGGAL ───────────────────
 *
 * Tanggal yang dipakai ke-18 spec itu berserakan: sebagian membiarkan form
 * memakai tanggal hari ini, empat spec mengisi '2026-08-31', dan tiga spec
 * ("Tanggal Dapat Diedit Manual") mengisi '2020-01-01' lalu mengasersi
 * penyimpanannya BERHASIL. Satu periode yang memuat semuanya jauh lebih
 * sederhana daripada mengarang periode per tanggal, dan — yang lebih penting
 * — tidak mengubah satu pun tanggal yang diasersi spec-spec itu.
 *
 * Lebarnya tidak menabrak spec lain: kelola-periode-pelaporan menanam
 * periodenya di "BU Browser Test" pada tahun 2100 ke atas, spec laporan-* di
 * mill lain pada 1970-2019 (tests/support/period-lanes.ts), dan skenario
 * yang butuh "hari ini" atau Agustus 2026 memakai "Mill Periode Uji"
 * (BrowserTestFixtureSeeder::periodFixtures). Sejak 2026-10-04 periode ini
 * TIDAK dihapus di afterAll — lihat removeOpenPeriodForForms().
 *
 * ── MENGAPA LEWAT API, BUKAN LEWAT UI ────────────────────────────────────
 *
 * Spec laporan menanam periodenya lewat screen-128 + screen-142 karena
 * memang itu yang diujinya. Di sini periode hanyalah prasyarat, bukan
 * subjek: lewat API ia tiga permintaan HTTP alih-alih selusin interaksi
 * halaman per spec, dan ia tidak ikut gagal ketika yang rusak adalah layar
 * periode — kegagalan yang akan muncul di 18 spec sekaligus dan menyamarkan
 * sebabnya.
 */

/**
 * Admin mana pun bisa dipakai: penjagaan rute /api/periods berbasis PERAN,
 * dan Admin tidak dibatasi mill (ScopesToActorMill). Akun ini dibuat
 * BrowserTestFixtureSeeder bersama seluruh akun `form-*` lain, jadi ia ada
 * tepat ketika spec-spec itu bisa jalan sama sekali.
 */
const FIXTURE_ADMIN = 'brtest-admin01'

/** Mill tempat seluruh spec `form-*` menulis — BrowserTestFixtureSeeder. */
export const FIXTURE_BUSINESS_UNIT = 'BU Browser Test'

/**
 * Awalan nama, dan sekaligus kunci pembersihan. Dipakai juga untuk
 * membersihkan sisa run yang mati sebelum afterAll-nya jalan.
 */
const FIXTURE_PREFIX = 'Prasyarat Form '

/**
 * Hari paling awal yang harus dimuat: '2020-01-01' dipakai apa adanya oleh
 * tiga skenario "Tanggal Dapat Diedit Manual".
 */
const FIXTURE_START = '2020-01-01'

interface PeriodStationRow {
  id: string
  station_type: string
  status: 'draft' | 'open' | 'closed'
}

interface PeriodRow {
  id: string
  name: string
  business_unit_id: string
  stations: PeriodStationRow[]
}

/** Hari ini + `days`, sebagai YYYY-MM-DD waktu lokal mesin test. */
function isoDay(days: number): string {
  const now = new Date()
  now.setDate(now.getDate() + days)

  return [
    now.getFullYear(),
    String(now.getMonth() + 1).padStart(2, '0'),
    String(now.getDate()).padStart(2, '0'),
  ].join('-')
}

/**
 * Menyiapkan satu periode TERBUKA untuk seluruh jenis stasiun di
 * "BU Browser Test", lalu mengembalikan `page` Admin-nya supaya afterAll
 * bisa memakai sesi yang sama untuk menghapusnya.
 *
 * MEMBUKA SATU BARIS STASIUN SAJA, bukan ke-18. Versi pertama membuka
 * semuanya demi menghilangkan satu parameter per spec — dan itu DIUKUR
 * terlalu mahal: `beforeAll` Playwright dibatasi 30 detik, `php artisan serve`
 * melayani satu permintaan sekaligus, dan 18 POST berturut-turut membuat 4 dari
 * 9 spec pertama gagal di hook-nya sendiri. Satu baris cukup: kunci periode
 * hanya mengatur PENULISAN, dan setiap spec `form-*` menulis ke satu jenis
 * stasiun saja — yang lain (Grading membaca kartu Weighbridge) hanya dibaca.
 */
export async function seedOpenPeriodForForms(browser: Browser, stationType: string): Promise<Page> {
  const page = await browser.newPage()

  await login(page, FIXTURE_ADMIN, PASSWORD)

  const headers = await statefulHeaders(page)

  // TIDAK lagi menghapus sisa lebih dulu (2026-10-04): periode prasyarat
  // yang sudah membingkai record ditolak 409 PERIOD_HAS_RECORDS, jadi sisanya
  // DIPAKAI ULANG di bawah (findPeriodByPrefix) alih-alih dihapus.

  const units = await page.request.get('/api/periods/business-units/options', { headers })
  expect(units.ok(), `tidak bisa membaca daftar mill: ${units.status()}`).toBe(true)

  const businessUnitId = ((await units.json()).data as Array<{ id: string; name: string }>)
    .find((row) => row.name === FIXTURE_BUSINESS_UNIT)?.id

  expect(businessUnitId, `mill "${FIXTURE_BUSINESS_UNIT}" tidak ada — jalankan BrowserTestFixtureSeeder`)
    .toBeTruthy()

  // SISA YANG TIDAK BISA DIHAPUS DIPAKAI ULANG (2026-10-04). Sejak
  // PeriodService::delete() menolak periode yang sudah BERISI record stasiun
  // (409 PERIOD_HAS_RECORDS), periode prasyarat yang sempat dipakai spec
  // `form-*` untuk menyimpan record tidak pernah lagi bisa dihapus — dan
  // record stasiun memang tidak punya jalur hapus. Membuat periode baru di
  // sampingnya akan ditolak 422 PERIOD_OVERLAP, jadi sisanya diperluas
  // sampai hari ini + 7 dan dipakai lagi.
  const leftover = await findPeriodByPrefix(page, headers, FIXTURE_PREFIX, businessUnitId!)
  const name = leftover?.name ?? `${FIXTURE_PREFIX}${Date.now()}`

  const created = leftover !== undefined
    ? await page.request.patch(`/api/periods/${leftover.id}`, {
      headers,
      data: {
        business_unit_id: businessUnitId,
        name,
        start_date: FIXTURE_START,
        end_date: isoDay(7),
      },
    })
    : await page.request.post('/api/periods', {
      headers,
      data: {
        business_unit_id: businessUnitId,
        name,
        start_date: FIXTURE_START,
        end_date: isoDay(7),
      },
    })

  expect(
    created.ok(),
    `gagal menyiapkan periode prasyarat (${created.status()}): ${await created.text()}`,
  ).toBe(true)

  // Baris stasiunnya dibaca dari DAFTAR, bukan dari respons POST: daftar
  // adalah bentuk yang sudah dipakai tests/support/periods.ts dan satu-satunya
  // yang dijamin membawa `stations[]`.
  const stations = await stationRowsOf(page, headers, name)

  const target = stations.find((station) => station.station_type === stationType)

  expect(
    target,
    `periode prasyarat tidak punya baris stasiun "${stationType}" — `
    + `periksa apakah "${FIXTURE_BUSINESS_UNIT}" benar-benar punya stasiun aktif berjenis itu`,
  ).toBeTruthy()

  if (target!.status !== 'open') {
    const opened = await page.request.post(`/api/period-stations/${target!.id}/open`, { headers })

    expect(
      opened.ok(),
      `gagal membuka stasiun ${stationType} (${opened.status()}): ${await opened.text()}`,
    ).toBe(true)
  }

  return page
}

/**
 * Menutup `page` milik seedOpenPeriodForForms(). Dipanggil dari afterAll.
 *
 * PERIODENYA SENGAJA TIDAK DIHAPUS (sejak 2026-10-04). Setelah spec `form-*`
 * menyimpan record di dalamnya, PeriodService::delete() menolaknya dengan
 * 409 PERIOD_HAS_RECORDS — dan record stasiun memang tidak punya jalur hapus.
 * Mencoba menghapusnya di setiap afterAll hanya menghasilkan 18 peringatan
 * 409 per run. Periode ini kini fixture yang hidup sepanjang database e2e:
 * spec berikutnya memakainya ulang (findPeriodByPrefix), dan
 * e2e-web/scripts/prepare-db.sh membuangnya bersama seluruh database.
 */
export async function removeOpenPeriodForForms(page: Page): Promise<void> {
  await page.close()
}

async function findPeriodByPrefix(
  page: Page,
  headers: Record<string, string>,
  prefix: string,
  businessUnitId: string,
): Promise<PeriodRow | undefined> {
  for (let pageNo = 1; pageNo <= 50; pageNo += 1) {
    const response = await page.request.get(`/api/periods?page=${pageNo}&per_page=100`, { headers })
    expect(response.ok(), `tidak bisa membaca daftar periode: ${response.status()}`).toBe(true)

    const rows = ((await response.json()).data ?? []) as PeriodRow[]
    const match = rows.find((row) => row.name.startsWith(prefix) && row.business_unit_id === businessUnitId)

    if (match !== undefined) {
      return match
    }

    if (rows.length < 100) {
      return undefined
    }
  }

  return undefined
}

async function stationRowsOf(
  page: Page,
  headers: Record<string, string>,
  name: string,
): Promise<PeriodStationRow[]> {
  for (let pageNo = 1; pageNo <= 50; pageNo += 1) {
    const response = await page.request.get(`/api/periods?page=${pageNo}&per_page=100`, { headers })
    expect(response.ok(), `tidak bisa membaca daftar periode: ${response.status()}`).toBe(true)

    const rows = ((await response.json()).data ?? []) as PeriodRow[]

    if (rows.length === 0) {
      break
    }

    const match = rows.find((row) => row.name === name)

    if (match !== undefined) {
      return match.stations ?? []
    }

    if (rows.length < 100) {
      break
    }
  }

  throw new Error(`periode prasyarat "${name}" tidak ditemukan pada daftar setelah dibuat`)
}
