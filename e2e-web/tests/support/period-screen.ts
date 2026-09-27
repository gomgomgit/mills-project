import { expect, type Locator, type Page } from '@playwright/test'

/**
 * Interaksi UI dengan screen-128 (Kelola Periode Pelaporan) yang dipakai
 * lebih dari satu spec: lima spec laporan menanam periodenya lewat layar ini
 * sebelum menguji laporannya sendiri.
 *
 * MENGAPA INI TIDAK BISA LAGI SEKADAR "klik tombol di baris periode".
 *
 * Sejak 2026-09-26 status tutup/buka hidup di `period_stations`, satu baris
 * per jenis stasiun, dan layarnya menjadi tabel BERTINGKAT:
 *
 *   - Baris induk (satu per periode) hanya membawa ringkasan
 *     (`status_summary`, "N stasiun · M tertutup") dan dua aksi periode
 *     (Edit, Hapus). Tidak ada lagi tombol "Tutup Periode" di sana.
 *   - Baris stasiun hidup di <tr> KEDUA, di dalam sub-tabel, dan
 *     TERTUTUP SECARA DEFAULT ($expandedPeriodIds kosong saat mount). Tombol
 *     per-stasiun tidak ada di DOM sebelum barisnya dibuka — mencarinya
 *     tanpa expand menghasilkan timeout yang menyesatkan, bukan "tombolnya
 *     hilang".
 *   - Setiap testid per-stasiun membawa id `period_stations`, bukan id
 *     periode: `station-close-button-{period_station_id}` dan seterusnya.
 *
 * Karena id itu tidak diketahui spec (periode dibuat lewat UI dan idnya
 * tidak pernah ditampilkan), helper di bawah MEMBACA id dari atribut
 * data-testid baris yang bersangkutan, bukan menebaknya.
 */

/** Baris INDUK satu periode, dicari lewat namanya. */
export function periodRow(page: Page, name: string): Locator {
  // `.kc-table__row` juga dipakai <tr> pembawa sub-tabel stasiun, tapi <tr>
  // itu tidak memuat nama periode, jadi pencocokan nama tetap tunggal.
  return page.locator('.kc-table__row', { hasText: name })
}

/**
 * Id periode, dibaca dari `data-testid="period-row-{id}"`. Dipakai untuk
 * menyusun testid anak-anaknya (expand-button, period-stations, dst).
 */
export async function periodIdFor(page: Page, name: string): Promise<string> {
  const row = periodRow(page, name)
  await expect(row).toBeVisible()

  const testId = await row.getAttribute('data-testid')

  if (testId === null || !testId.startsWith('period-row-')) {
    throw new Error(`baris periode "${name}" tidak membawa data-testid period-row-{id}: ${testId}`)
  }

  return testId.slice('period-row-'.length)
}

/**
 * Membuka baris stasiun satu periode bila belum terbuka. Idempoten: tombol
 * expand adalah toggle, jadi mengkliknya dua kali akan MENUTUP kembali
 * barisnya — karena itu keadaannya dibaca dari aria-expanded lebih dulu.
 */
export async function expandPeriod(page: Page, periodId: string): Promise<void> {
  const toggle = page.locator(`[data-testid="expand-button-${periodId}"]`)
  await expect(toggle).toBeVisible()

  if ((await toggle.getAttribute('aria-expanded')) !== 'true') {
    await toggle.click()
  }

  await expect(toggle).toHaveAttribute('aria-expanded', 'true')
}

/**
 * Id `period_stations` untuk satu jenis stasiun pada satu periode.
 *
 * Pencocokan label dilakukan pada SEL PERTAMA dan harus SAMA PERSIS, bukan
 * `hasText` yang mencocokkan sebagian: master stasiun memuat "Kernel Plant"
 * dan "Kernel Dispatch", jadi pencocokan sebagian akan cocok ke dua baris
 * dan gagal di strict mode.
 */
export async function stationIdFor(page: Page, periodId: string, stationLabel: string): Promise<string> {
  const rows = page.locator(`[data-testid="period-stations-${periodId}"] tbody tr`)
  await expect(rows.first()).toBeVisible()

  const total = await rows.count()

  for (let index = 0; index < total; index += 1) {
    const row = rows.nth(index)
    const label = (await row.locator('td').first().innerText()).trim()

    if (label !== stationLabel) {
      continue
    }

    const testId = await row.getAttribute('data-testid')

    if (testId === null || !testId.startsWith('period-station-row-')) {
      throw new Error(`baris stasiun "${stationLabel}" tidak membawa data-testid period-station-row-{id}`)
    }

    return testId.slice('period-station-row-'.length)
  }

  throw new Error(
    `periode ${periodId} tidak punya baris stasiun "${stationLabel}" — `
    + 'periksa apakah mill-nya benar-benar punya stasiun aktif berjenis itu',
  )
}

/**
 * Membuka baris stasiun periode `periodName` lalu memulangkan id
 * `period_stations` untuk `stationLabel`. Ini urutan wajibnya: id itu hanya
 * ada di DOM setelah baris induknya dibuka.
 */
export async function openStationRow(
  page: Page,
  periodName: string,
  stationLabel: string,
): Promise<{ periodId: string; stationId: string }> {
  const periodId = await periodIdFor(page, periodName)
  await expandPeriod(page, periodId)

  return { periodId, stationId: await stationIdFor(page, periodId, stationLabel) }
}

/**
 * Mengubah satu baris stasiun dari Draft menjadi Terbuka ("Buka Stasiun",
 * usecase-144). Idempoten: baris yang sudah Terbuka/Tertutup dibiarkan.
 */
export async function openStation(page: Page, periodId: string, stationId: string): Promise<void> {
  const badge = page.locator(`[data-testid="station-status-badge-${stationId}"]`)
  await expect(badge).toBeVisible()

  if ((await badge.innerText()).trim() !== 'Draft') {
    return
  }

  await page.locator(`[data-testid="station-open-button-${stationId}"]`).click()
  await expect(page.locator('[data-testid="open-period-dialog"]')).toBeVisible()
  await page.locator('[data-testid="confirm-open-period"]').click()

  await expect(badge).toHaveText('Terbuka')
  await expandPeriod(page, periodId)
}

/**
 * Menutup SATU jenis stasiun pada satu periode lewat UI, dan memastikan
 * badge stasiun itu benar-benar menjadi "Tertutup".
 *
 * DUA LANGKAH, BUKAN SATU. Layar hanya menawarkan "Tutup Stasiun" pada baris
 * berstatus TERBUKA: baris Draft menawarkan "Buka Stasiun" dan baris
 * Tertutup menawarkan "Buka Kembali" (lihat blade screen-128). Siklusnya
 * draft -> open -> closed, tanpa jalan pintas dan tanpa jalan pulang ke
 * draft. closePeriod() lama mengklik satu tombol "Tutup Periode" di baris
 * induk pada periode yang masih Draft; tombol itu tidak ada lagi, dan
 * periode tidak punya status untuk ditutup.
 *
 * Yang ditutup hanya stasiun itu — jenis lain pada periode yang sama tetap
 * seperti semula.
 */
export async function closeStation(
  page: Page,
  periodName: string,
  stationLabel: string,
): Promise<string> {
  const { periodId, stationId } = await openStationRow(page, periodName, stationLabel)

  await openStation(page, periodId, stationId)

  await page.locator(`[data-testid="station-close-button-${stationId}"]`).click()
  await expect(page.locator('[data-testid="unverified-warning"]')).toBeVisible()
  await page.locator('[data-testid="confirm-close-button"]').click()

  await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Tertutup')

  return stationId
}

/**
 * `x-searchable-select` (resources/views/components/searchable-select.blade.php)
 * bukan <select> native: nilainya dipilih dengan mengetik lalu mengklik
 * option di listbox-nya.
 */
export async function selectSearchable(page: Page, id: string, label: string): Promise<void> {
  await page.locator(`#${id}`).click()
  await page.locator(`#${id}`).fill(label)
  await page.locator(`#${id}-listbox`).getByRole('option', { name: label, exact: true }).click()
}

/**
 * Mengisi dan menyimpan form "Tambah Periode", lalu menunggu baris induknya
 * muncul.
 *
 * TIDAK ADA LAGI PILIHAN JENIS STASIUN. Sebuah periode mencakup SELURUH
 * mill: create() mendaftarkan satu baris `period_stations` untuk setiap jenis
 * stasiun aktif di mill itu. Form menggantinya dengan pratinjau daftar
 * stasiun yang akan didaftarkan.
 *
 * PRATINJAU ITU JUGA TITIK SINKRONISASI. `business_unit_id` kini terikat
 * `wire:model.live`, jadi memilih mill memicu satu round trip Livewire yang
 * me-render ulang modal. Mengetik nama periode sementara round trip itu
 * masih berjalan berisiko ditimpa oleh respons yang datang belakangan —
 * karena itu helper ini menunggu pratinjaunya muncul lebih dulu (daftar
 * stasiun, atau hint "mill ini belum punya stasiun aktif" untuk mill tanpa
 * stasiun) sebelum menyentuh field lain.
 */
export async function createPeriodViaUi(
  page: Page,
  options: { businessUnit: string; name: string; start: string; end: string },
): Promise<void> {
  await page.locator('[data-testid="add-period-button"]').click()
  await selectSearchable(page, 'business_unit_id', options.businessUnit)
  await expect(
    page
      .locator('[data-testid="station-preview"] .kc-preview__list li')
      .or(page.locator('[data-testid="station-preview-empty"]'))
      .first(),
  ).toBeVisible()

  await page.locator('#name').fill(options.name)
  await page.locator('#start_date').fill(options.start)
  await page.locator('#end_date').fill(options.end)
  await page.locator('[data-testid="save-button"]').click()

  await expect(periodRow(page, options.name)).toBeVisible()
}
