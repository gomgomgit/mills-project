import { expect, type Browser, type Page } from '@playwright/test'
import { login, PASSWORD } from './auth'
import { deletePeriodsByPrefix, statefulHeaders } from './periods'

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
 * Lebarnya tidak menabrak spec lain: seluruh spec yang menanam periode di
 * mill ini memakai tanggal tahun 2100 ke atas (kelola-periode-pelaporan) atau
 * 2600 ke atas (lima spec laporan, lihat tests/support/period-lanes.ts),
 * SATU pengecualian adalah tiga skenario panel "Periode Terbuka Hari Ini"
 * yang memakai kemarin..besok di mill yang sama. Itu sebabnya periode ini
 * DIHAPUS di afterAll setiap spec, bukan ditinggalkan sebagai fixture
 * permanen: Playwright di repo ini berjalan serial (`workers: 1`,
 * `fullyParallel: false`), jadi periode ini hanya hidup selama spec yang
 * memakainya berjalan.
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

  // Sisa run yang mati sebelum afterAll-nya jalan akan menolak pembuatan di
  // bawah dengan 422 PERIOD_OVERLAP. Dibersihkan lebih dulu, memakai helper
  // yang sama dengan spec laporan — ia juga membuka kembali stasiun yang
  // tertutup, yang tanpa itu membuat DELETE dijawab 409.
  await deletePeriodsByPrefix(page, [FIXTURE_PREFIX])

  const units = await page.request.get('/api/periods/business-units/options', { headers })
  expect(units.ok(), `tidak bisa membaca daftar mill: ${units.status()}`).toBe(true)

  const businessUnitId = ((await units.json()).data as Array<{ id: string; name: string }>)
    .find((row) => row.name === FIXTURE_BUSINESS_UNIT)?.id

  expect(businessUnitId, `mill "${FIXTURE_BUSINESS_UNIT}" tidak ada — jalankan BrowserTestFixtureSeeder`)
    .toBeTruthy()

  const name = `${FIXTURE_PREFIX}${Date.now()}`

  const created = await page.request.post('/api/periods', {
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
    `gagal membuat periode prasyarat (${created.status()}): ${await created.text()}`,
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
 * Menghapus periode prasyarat. Dipanggil dari afterAll dengan `page` yang
 * dikembalikan seedOpenPeriodForForms(), lalu menutup page itu.
 *
 * Membersihkan BERDASARKAN AWALAN, bukan id, supaya sisa run lain ikut
 * terbawa dan tidak pernah menjadi PERIOD_OVERLAP di run berikutnya.
 */
export async function removeOpenPeriodForForms(page: Page): Promise<void> {
  try {
    await deletePeriodsByPrefix(page, [FIXTURE_PREFIX])
  } finally {
    await page.close()
  }
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
