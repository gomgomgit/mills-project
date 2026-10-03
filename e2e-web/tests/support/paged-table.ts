import { expect, type Locator, type Page } from '@playwright/test'

/**
 * Mencari baris di tabel Kelola (master data) yang BERHALAMAN.
 *
 * ── MENGAPA INI ADA, SEJAK 2026-10-03 ───────────────────────────────────
 *
 * Layar Kelola Corporate / Company / Business Unit / Station menampilkan 20
 * baris per halaman, diurutkan menurut nama. Spec-spec itu ditulis seolah
 * setiap baris fixture selalu ada di halaman pertama — dan di database dev
 * itu tidak benar: ada puluhan corporate/company bernama faker (bocoran
 * BrowserTestFixtureSeeder sendiri, lihat businessUnit() di sana) dan
 * ratusan station. "PT Hapus Bersih" jatuh di halaman 3, "Weighbridge Ada
 * Machinery" di belasan halaman kemudian.
 *
 * Akibatnya ganda: asersi KEHADIRAN gagal sebagai timeout, dan asersi
 * KETIDAKHADIRAN (`toHaveCount(0)`) lulus tanpa arti karena hanya memeriksa
 * halaman pertama. Kedua helper di bawah memeriksa SEMUA halaman lewat
 * tombol paginasi layar itu sendiri.
 *
 * Setiap pindah halaman menunggu ringkasan "Halaman N dari M" berubah —
 * bukan sekadar respons /livewire/update, yang tiba sebelum DOM di-morph.
 */

const ROW = '.kc-table__row'

async function summaryText(page: Page): Promise<string | null> {
  const summary = page.locator('.kc-pagination__summary')

  return (await summary.count()) > 0 ? (await summary.innerText()).trim() : null
}

/**
 * Paginasi tidak bisa diklik selama modal form terbuka (backdrop-nya
 * menutupi halaman), jadi setiap helper menunggu modal tertutup lebih dulu —
 * termasuk sesudah Simpan, yang menutup modal secara asinkron.
 */
async function waitForTable(page: Page): Promise<void> {
  await expect(page.locator('.kcm-modal')).toHaveCount(0)
  await expect(page.locator('.kc-table')).toBeVisible()
}

/**
 * Menutup modal form lewat tombol Batal-nya. Dipakai skenario validasi
 * SESUDAH mengasersi modal tetap terbuka, supaya asersi "tidak ada baris
 * baru" bisa memeriksa seluruh halaman, bukan hanya halaman di bawah modal.
 */
export async function closeModal(page: Page): Promise<void> {
  await page.locator('.kcm-modal button', { hasText: 'Batal' }).click()
  await expect(page.locator('.kcm-modal')).toHaveCount(0)
}

async function turnPage(page: Page, label: 'Sebelumnya' | 'Berikutnya'): Promise<boolean> {
  const button = page.locator('.kc-pagination__controls button', { hasText: label })

  if ((await button.count()) === 0 || (await button.isDisabled())) {
    return false
  }

  const before = await summaryText(page)

  // Putaran Livewire sebelumnya (mis. hapus baris) bisa masih mendarat dan
  // menonaktifkan tombol ini SESUDAH isDisabled() di atas membacanya aktif —
  // terukur: hapus di halaman 3 mengembalikan daftar ke halaman 1. Tombol
  // yang menjadi nonaktif berarti memang tidak ada halaman ke arah itu.
  try {
    await button.click({ timeout: 5_000 })
  } catch (error) {
    if (await button.isDisabled()) {
      return false
    }

    throw error
  }

  await expect(page.locator('.kc-pagination__summary')).not.toHaveText(before ?? '')

  return true
}

/** Kembali ke halaman 1 lalu berjalan maju sampai baris ber-`text` ditemukan. */
export async function findRow(page: Page, text: string): Promise<Locator> {
  await waitForTable(page)

  while (await turnPage(page, 'Sebelumnya')) {
    // mundur ke halaman pertama
  }

  const row = page.locator(ROW, { hasText: text })

  for (;;) {
    if ((await row.count()) > 0) {
      return row
    }

    if (!(await turnPage(page, 'Berikutnya'))) {
      throw new Error(`baris "${text}" tidak ditemukan di halaman mana pun — jalankan BrowserTestFixtureSeeder`)
    }
  }
}

/** Menegaskan baris ber-`text` tidak ada di halaman MANA PUN. */
export async function expectNoRowOnAnyPage(page: Page, text: string): Promise<void> {
  await waitForTable(page)

  while (await turnPage(page, 'Sebelumnya')) {
    // mundur ke halaman pertama
  }

  const row = page.locator(ROW, { hasText: text })

  do {
    await expect(row, `baris "${text}" seharusnya tidak ada (${await summaryText(page)})`).toHaveCount(0)
  } while (await turnPage(page, 'Berikutnya'))
}

/** Menegaskan tepat `count` baris ber-`text` di seluruh halaman. */
export async function expectRowCountOnAllPages(page: Page, text: string, count: number): Promise<void> {
  await waitForTable(page)

  while (await turnPage(page, 'Sebelumnya')) {
    // mundur ke halaman pertama
  }

  const row = page.locator(ROW, { hasText: text })
  let total = 0

  do {
    total += await row.count()
  } while (await turnPage(page, 'Berikutnya'))

  expect(total, `jumlah baris "${text}" di seluruh halaman`).toBe(count)
}
