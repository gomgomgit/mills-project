import type { Page } from '@playwright/test'

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
 *   - Ditambah tabrakan PERIOD_OVERLAP antar-run pada mill + jenis stasiun
 *     yang sama.
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
 * penjagaan peran tetap berlaku (rute /api/periods/* adalah Admin-only).
 * Penghapusan lewat API jauh lebih murah dan jauh lebih tahan daripada
 * mengulang dialog konfirmasi di UI untuk tiap baris.
 *
 * Periode berstatus `closed` TIDAK dapat langsung dihapus — PeriodService
 * ::delete() menolaknya dengan PERIOD_CLOSED_IMMUTABLE. Karena itu periode
 * tertutup dibuka kembali dulu lewat POST /reopen, baru dihapus. Inilah
 * sebabnya pembersihan ini tidak bisa sekadar DELETE beruntun.
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
 * Nilainya cukup baseURL aplikasi. Sebelum 2026-09-23 itu tidak berhasil:
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
const STATEFUL_REFERER = 'http://localhost:8000/'

/** Sama dengan Pagination::MAX_PER_PAGE di backend. */
const MAX_PER_PAGE = 100

/** Batas halaman yang ditelusuri — penjaga agar tidak berputar selamanya. */
const MAX_PAGES = 50

/**
 * Header yang membuat permintaan API dikenali sebagai stateful + lolos CSRF.
 * Token XSRF di cookie ter-URL-encode, jadi harus di-decode dulu.
 */
async function statefulHeaders(page: Page): Promise<Record<string, string>> {
  const cookies = await page.context().cookies()
  const xsrf = cookies.find((cookie) => cookie.name === 'XSRF-TOKEN')

  return {
    Referer: STATEFUL_REFERER,
    Accept: 'application/json',
    ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf.value) } : {}),
  }
}

interface PeriodRow {
  id: string
  name: string
  status: string
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
      // Periode tertutup harus dibuka kembali dulu — delete() menolak
      // status 'closed' dengan PERIOD_CLOSED_IMMUTABLE.
      if (period.status === 'closed') {
        const reopened = await page.request.post(`/api/periods/${period.id}/reopen`, { headers })

        if (!reopened.ok()) {
          console.warn(
            `[cleanup] gagal membuka kembali "${period.name}" (${reopened.status()}) — dilewati`,
          )
          continue
        }
      }

      const removed = await page.request.delete(`/api/periods/${period.id}`, { headers })

      if (removed.ok()) {
        deleted += 1
      } else {
        console.warn(`[cleanup] gagal menghapus "${period.name}" (${removed.status()})`)
      }
    } catch (error) {
      console.warn(`[cleanup] galat saat menghapus "${period.name}":`, error)
    }
  }

  return deleted
}
