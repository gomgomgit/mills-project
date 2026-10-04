import type { Page } from '@playwright/test'
import { STATEFUL_REFERER } from './base-url'

/**
 * Pembersihan Periode Pelaporan yang dibuat oleh browser test.
 *
 * MENGAPA BERKAS INI ADA
 *
 * Sampai 2026-09-23, tidak satu pun spec yang membuat Periode Pelaporan
 * membersihkan periodenya — tidak ada afterAll, tidak ada afterEach. Periode
 * dibuat lewat UI lalu ditinggalkan di database dev. Akibatnya suite ini
 * meracuni dirinya sendiri, dan itu terukur, bukan dugaan:
 *
 *   - Laju penumpukan ~15 baris per run.
 *   - Daftar periode dipaginasi 20 baris, ORDER BY start_date DESC.
 *   - Jadi setelah sekitar satu setengah run, halaman 1 penuh oleh residu,
 *     dan baris yang baru dibuat test terdorong ke halaman 2 — sehingga
 *     toBeVisible() gagal di dalam helper createPeriod(), SEBELUM satu pun
 *     asersi perilaku sempat dieksekusi.
 *   - Ditambah tabrakan PERIOD_OVERLAP antar-run pada mill yang sama.
 *
 * Pada 2026-09-23 database dev berisi 117 periode yang 116 di antaranya
 * residu (hanya 1 nyata). Setelah dibersihkan, suite hijau penuh: 53 lolos.
 * Tiga run kemudian sudah 40 baris dan 19 test gagal lagi. Menghapus residu
 * secara manual adalah reset yang bertahan sekitar satu run, bukan perbaikan.
 *
 * CARA KERJA
 *
 * Memakai `page.request`, yang mewarisi cookie sesi dari konteks browser
 * setelah login lewat UI — jadi tidak perlu login kedua kali, dan seluruh
 * penjagaan peran tetap berlaku (rute /api/periods/* dan
 * /api/period-stations/* adalah Admin-only). Penghapusan lewat API jauh
 * lebih murah dan jauh lebih tahan daripada mengulang dialog konfirmasi di
 * UI untuk tiap baris.
 *
 * ── APA YANG BERUBAH PADA 2026-09-26 ────────────────────────────────────
 *
 * Periode dipecah menjadi induk (`periods`) + anak (`period_stations`).
 * Status tutup/buka HIDUP DI ANAK, satu baris per jenis stasiun, dan induk
 * TIDAK punya kunci `status` lagi — membaca `period.status` dari respons
 * daftar kini selalu `undefined`, yang berarti versi lama helper ini
 * mengira TIDAK ADA periode yang perlu dibuka kembali, lalu menelan 409-nya
 * dan meninggalkan residu. Kegagalan itu tidak terlihat pada run yang
 * sedang berjalan: ia muncul 1-2 run kemudian sebagai kegagalan paginasi di
 * spec lain (lihat blok di atas), yang mahal sekali didiagnosis.
 *
 * Karena itu:
 *
 *  1. Yang dibaca sekarang `stations[]` + `is_immutable`.
 *     PeriodService::delete() menolak periode yang punya MINIMAL SATU
 *     stasiun `closed` (409 PERIOD_CLOSED_IMMUTABLE) — `is_immutable`
 *     adalah kondisi itu persis, jadi ia tidak diturunkan ulang di sini.
 *  2. Pembukaan kembali dilakukan PER STASIUN, satu per satu, lewat
 *     POST /api/period-stations/{station_id}/reopen. Rute lama
 *     POST /api/periods/{id}/reopen sudah 404. `stations[n].id` adalah id
 *     `period_stations`, BUKAN id periode — menukarnya berarti 404, atau
 *     lebih buruk, membuka baris milik periode lain.
 *  3. SETIAP 409 yang tersisa DILAPORKAN, tidak ditelan diam-diam: kalau
 *     DELETE tetap ditolak, console.warn menyebut nama periode dan jenis
 *     stasiun yang masih tertutup, supaya penyebabnya terbaca di run yang
 *     sama alih-alih menjadi kegagalan paginasi dua run kemudian.
 *
 * Periode berstatus tertutup sebagian TIDAK dapat langsung dihapus, dan
 * juga tidak dapat diubah — inilah sebabnya pembersihan ini tidak bisa
 * sekadar DELETE beruntun.
 *
 * Pembersihan sengaja dibuat TIDAK PERNAH menggagalkan test: ia dipanggil
 * dari afterAll, dan kegagalan membersihkan bukan kegagalan produk. Galat
 * dicatat ke console agar tetap terlihat, lalu ditelan.
 */

/**
 * MENGAPA ADA HEADER Referer DI BAWAH.
 *
 * Rute /api/periods/* memakai guard `auth:web`, yang berjalan lewat jalur
 * stateful Sanctum. Sanctum hanya memperlakukan permintaan sebagai stateful
 * bila host pada Referer/Origin cocok dengan pola dari config('sanctum.stateful').
 * APIRequestContext milik Playwright TIDAK mengirim Referer sendiri, sehingga
 * tanpa header ini permintaan jatuh ke jalur token dan dijawab 401.
 *
 * Nilainya cukup origin aplikasi (STATEFUL_REFERER, ./base-url — mengikuti
 * E2E_WEB_BASE_URL, bukan lagi 'http://localhost:8000/' tertulis mati). Sebelum 2026-09-23 itu tidak berhasil:
 * SANCTUM_STATEFUL_DOMAINS di .env menimpa daftar bawaan Laravel dan
 * menghilangkan `localhost:8000`, padahal itulah APP_URL. Sanctum mengubah tiap
 * entri menjadi pola "<entri>/*", jadi entri telanjang "localhost" menghasilkan
 * "localhost/*" yang tidak pernah cocok dengan "localhost:8000/...". Akibatnya
 * SELURUH panggilan /api/* dari frontend aplikasi sendiri dijawab 401. Itu sudah
 * diperbaiki di .env dan .env.example; header ini tetap diperlukan semata karena
 * Playwright tidak mengirim Referer, bukan karena ada yang salah di aplikasi.
 *
 * Jalur stateful mengaktifkan CSRF, jadi token XSRF dibaca dari cookie dan
 * dikirim sebagai X-XSRF-TOKEN.
 */

/** Sama dengan Pagination::MAX_PER_PAGE di backend. */
const MAX_PER_PAGE = 100

/** Batas halaman yang ditelusuri — penjaga agar tidak berputar selamanya. */
const MAX_PAGES = 50

/**
 * Header yang membuat permintaan API dikenali sebagai stateful + lolos CSRF.
 * Token XSRF di cookie ter-URL-encode, jadi harus di-decode dulu.
 */
export async function statefulHeaders(page: Page): Promise<Record<string, string>> {
  const cookies = await page.context().cookies()
  const xsrf = cookies.find((cookie) => cookie.name === 'XSRF-TOKEN')

  return {
    Referer: STATEFUL_REFERER,
    Accept: 'application/json',
    ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf.value) } : {}),
  }
}

/**
 * Satu entri `stations[]` dari GET /api/periods. `id` adalah id
 * `period_stations` — id yang diterima aksi tutup/buka/buka-kembali.
 */
interface PeriodStationRow {
  id: string
  station_type: string
  station_type_label: string
  status: 'draft' | 'open' | 'closed'
}

/**
 * Bentuk baris daftar periode SEJAK 2026-09-26. Tidak ada `status`,
 * `station_type`, `closed_by` maupun `closed_at` di tingkat induk — kalau
 * salah satu dibutuhkan, ia ada di `stations[]`.
 */
interface PeriodRow {
  id: string
  name: string
  is_immutable: boolean
  closed_station_count: number
  stations: PeriodStationRow[]
}

/**
 * Menghapus seluruh periode yang namanya diawali salah satu `prefixes`.
 *
 * Dipanggil dari afterAll dengan `page` yang sudah login sebagai Admin.
 * Mengembalikan jumlah baris yang benar-benar terhapus.
 */
export async function deletePeriodsByPrefix(page: Page, prefixes: string[]): Promise<number> {
  const targets: PeriodRow[] = []
  const headers = await statefulHeaders(page)

  try {
    for (let pageNo = 1; pageNo <= MAX_PAGES; pageNo += 1) {
      const response = await page.request.get(
        `/api/periods?page=${pageNo}&per_page=${MAX_PER_PAGE}`,
        { headers },
      )

      if (!response.ok()) {
        console.warn(
          `[cleanup] GET /api/periods halaman ${pageNo} menjawab ${response.status()} — pembersihan dihentikan`,
        )
        break
      }

      const body = (await response.json()) as { data?: PeriodRow[] }
      const rows = body.data ?? []

      if (rows.length === 0) {
        break
      }

      targets.push(...rows.filter((row) => prefixes.some((prefix) => row.name.startsWith(prefix))))

      if (rows.length < MAX_PER_PAGE) {
        break
      }
    }
  } catch (error) {
    console.warn('[cleanup] gagal membaca daftar periode:', error)
    return 0
  }

  let deleted = 0

  for (const period of targets) {
    try {
      // SETIAP stasiun tertutup harus dibuka kembali lebih dulu — satu pun
      // yang tertinggal membuat DELETE dijawab 409
      // PERIOD_CLOSED_IMMUTABLE. `is_immutable` adalah kondisi itu persis,
      // tapi yang dipakai di sini daftar stasiunnya, karena yang perlu
      // dibuka adalah baris-barisnya, bukan periodenya.
      const stillClosed: PeriodStationRow[] = []

      for (const station of period.stations ?? []) {
        if (station.status !== 'closed') {
          continue
        }

        const reopened = await page.request.post(
          `/api/period-stations/${station.id}/reopen`,
          { headers },
        )

        if (!reopened.ok()) {
          stillClosed.push(station)
          console.warn(
            `[cleanup] gagal membuka kembali "${period.name}" / ${station.station_type_label} `
            + `(${reopened.status()})`,
          )
        }
      }

      const removed = await page.request.delete(`/api/periods/${period.id}`, { headers })

      if (removed.ok()) {
        deleted += 1
        continue
      }

      // BERISIK ON PURPOSE. 409 di sini berarti masih ada stasiun tertutup,
      // dan periodenya akan tertinggal di database — akibatnya baru terasa
      // 1-2 run kemudian lewat paginasi, jadi penyebabnya harus terbaca
      // SEKARANG, lengkap dengan stasiun mana yang menahannya.
      const remaining = stillClosed.length > 0
        ? stillClosed.map((station) => station.station_type_label).join(', ')
        : '(tidak ada yang gagal dibuka kembali — periksa stasiun yang ditutup setelah daftar ini dibaca)'

      console.warn(
        `[cleanup] gagal menghapus "${period.name}" (${removed.status()}); `
        + `closed_station_count saat daftar dibaca = ${period.closed_station_count}; `
        + `stasiun yang masih tertutup: ${remaining}`,
      )
    } catch (error) {
      console.warn(`[cleanup] galat saat menghapus "${period.name}":`, error)
    }
  }

  return deleted
}
