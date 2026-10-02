import { expect, type Locator, type Page } from '@playwright/test'

/**
 * Interaksi UI dengan Periode Pelaporan yang dipakai lebih dari satu spec:
 * screen-128 (daftar periode, `/master-data/periods`) untuk menanam periode,
 * dan screen-142 (detail periode, `/master-data/periods/{id}`) untuk seluruh
 * aksi per stasiun. Lima spec laporan menanam periodenya lewat kedua layar
 * ini sebelum menguji laporannya sendiri.
 *
 * ── APA YANG BERUBAH PADA 2026-09-27 ────────────────────────────────────
 *
 * Daftar stasiun sebuah periode BUKAN LAGI ACCORDION di screen-128. Ia pindah
 * ke layar tersendiri, screen-142, yang dicapai lewat nama periode (sebuah
 * <a href> sungguhan, `data-testid="period-link-{periodId}"`).
 *
 *   - `expand-button-{periodId}` dan sub-tabel di <tr> kedua SUDAH TIDAK ADA.
 *     Versi lama helper ini mengklik toggle itu; kini pembukaan daftar
 *     stasiun berarti NAVIGASI ke `/master-data/periods/{periodId}`.
 *   - Testid tabel stasiun tidak berubah bentuknya — `period-stations-{periodId}`,
 *     `period-station-row-{stationId}`, `station-status-badge-{stationId}`,
 *     `station-close-button-{stationId}`, `station-open-button-{stationId}`,
 *     `station-reopen-button-{stationId}` — hanya rumahnya yang pindah ke
 *     screen-142. Itulah sebabnya stationIdFor() di bawah tetap sama persis;
 *     yang berubah hanya HALAMAN tempat ia dipanggil.
 *   - SETIAP testid per-stasiun tetap membawa id `period_stations`, BUKAN id
 *     periode. Endpoint tutup/buka (`/api/period-stations/{id}/...`) menerima
 *     id itu, dan mengirim id periode ke sana adalah kekeliruan termahal
 *     layar ini: 404, atau lebih buruk, mengenai baris milik periode lain.
 *     Karena id itu tidak pernah diketahui spec (periode dibuat lewat UI),
 *     helper di bawah MEMBACA id dari atribut data-testid baris yang
 *     bersangkutan, bukan menebaknya.
 *
 * Baris induk di screen-128 kini hanya membawa ringkasan (`status_summary`,
 * "N stasiun · M tertutup") dan dua aksi periode (Edit, Hapus) — tidak ada
 * satu pun aksi stasiun di sana.
 */

export const PERIODS_PATH = '/master-data/periods'

/** URL layar detail satu periode (screen-142). */
export function periodDetailPath(periodId: string): string {
  return `${PERIODS_PATH}/${periodId}`
}

/** Baris satu periode pada DAFTAR (screen-128), dicari lewat namanya. */
export function periodRow(page: Page, name: string): Locator {
  // Dibatasi ke tabel daftar: `.kc-table__row` juga dipakai baris stasiun di
  // screen-142, dan kedua layar memakai kosakata kelas yang sama.
  return page.locator('[data-testid="period-table"] .kc-table__row', { hasText: name })
}

/**
 * Id periode, dibaca dari `data-testid="period-row-{id}"` pada DAFTAR.
 * Dipakai untuk menyusun URL detailnya dan testid aksi periodenya.
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
 * Membuka layar detail satu periode lewat URL-nya dan menunggu ringkasannya
 * ter-render.
 *
 * Idempoten dan tidak bergantung pada halaman asal — dipakai juga untuk
 * "kembali ke detail" setelah sebuah aksi memindahkan halaman. Navigasi lewat
 * tautan nama periode (`period-link-{id}`, wire:navigate) diuji tersendiri di
 * tests/detail-periode-pelaporan.spec.ts; helper ini sengaja memakai goto agar
 * penyiapan fixture tidak bergantung pada tautan yang sedang diuji.
 */
export async function gotoPeriodDetail(page: Page, periodId: string): Promise<void> {
  await page.goto(periodDetailPath(periodId))
  await expect(page.locator('[data-testid="period-summary"]')).toBeVisible()
}

/**
 * Id `period_stations` untuk satu jenis stasiun pada HALAMAN DETAIL yang
 * sedang terbuka.
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
 * Dari DAFTAR menuju baris stasiun satu periode: membaca id periode dari
 * barisnya, membuka layar detailnya, lalu memulangkan id `period_stations`
 * untuk `stationLabel`.
 *
 * Ini urutan wajibnya, dan halaman berpindah: setelah pemanggilan ini browser
 * berada di `/master-data/periods/{periodId}`, bukan lagi di daftar.
 */
export async function openStationRow(
  page: Page,
  periodName: string,
  stationLabel: string,
): Promise<{ periodId: string; stationId: string }> {
  const periodId = await periodIdFor(page, periodName)
  await gotoPeriodDetail(page, periodId)

  return { periodId, stationId: await stationIdFor(page, periodId, stationLabel) }
}

/**
 * Mengubah satu baris stasiun dari Draft menjadi Terbuka ("Buka Stasiun",
 * usecase-144) pada layar detail yang sedang terbuka. Idempoten: baris yang
 * sudah Terbuka/Tertutup dibiarkan.
 *
 * `periodId` tidak lagi dibutuhkan untuk membuka ulang accordion — layar
 * detail tidak punya keadaan terlipat — tapi tetap diterima agar pemanggil
 * membawa kedua id secara eksplisit dan tidak pernah tergoda mengirim satu
 * id ke tempat yang salah.
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
}

/**
 * Menutup SATU jenis stasiun pada satu periode lewat UI, dan memastikan
 * badge stasiun itu benar-benar menjadi "Tertutup".
 *
 * Dipanggil dari DAFTAR; halaman berakhir di layar detail periode itu.
 *
 * DUA LANGKAH, BUKAN SATU. Layar hanya menawarkan "Tutup Stasiun" pada baris
 * berstatus TERBUKA: baris Draft menawarkan "Buka Stasiun" dan baris
 * Tertutup menawarkan "Buka Kembali" (lihat blade screen-142). Siklusnya
 * draft -> open -> closed, tanpa jalan pintas dan tanpa jalan pulang ke
 * draft.
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
 * Mengisi dan menyimpan form "Tambah Periode" di DAFTAR (screen-128), lalu
 * menunggu barisnya muncul.
 *
 * TIDAK ADA PILIHAN JENIS STASIUN. Sebuah periode mencakup SELURUH mill:
 * create() mendaftarkan satu baris `period_stations` untuk setiap jenis
 * stasiun aktif di mill itu. Form menggantinya dengan pratinjau daftar
 * stasiun yang akan didaftarkan.
 *
 * PRATINJAU ITU JUGA TITIK SINKRONISASI. `business_unit_id` terikat
 * `wire:model.live`, jadi memilih mill memicu satu round trip Livewire yang
 * me-render ulang modal. Mengetik nama periode sementara round trip itu
 * masih berjalan berisiko ditimpa oleh respons yang datang belakangan —
 * karena itu helper ini menunggu pratinjaunya muncul lebih dulu (daftar
 * stasiun, atau hint "mill ini belum punya stasiun aktif" untuk mill tanpa
 * stasiun) sebelum menyentuh field lain.
 */
/**
 * createPeriodViaUi() + MEMBUKA satu baris stasiunnya, lewat UI, lalu kembali ke
 * daftar periode.
 *
 * WAJIB SEJAK 2026-10-02. Kunci periode (usecase-141) menolak setiap penulisan
 * data stasiun tanpa periode yang baris stasiunnya berstatus TERBUKA dan yang
 * rentangnya mencakup tanggal kejadian record. Periode yang baru dibuat lahir
 * dengan SEMUA baris stasiunnya berstatus Draft — dan Draft menolak, sama seperti
 * Tertutup. Jadi spec yang menyemai record lewat form HARUS membuka stasiunnya
 * lebih dulu; `createPeriodViaUi()` saja tidak cukup lagi.
 *
 * `stationLabel` memakai LABEL manusiawi dari master station_types (mis. "Boiler
 * Room"), karena itulah yang dirender layar Detail Periode Pelaporan — bukan kode
 * seperti 'boiler-room'.
 *
 * TOLERAN TERHADAP MILL TANPA BARIS STASIUN ITU. Mill seperti "Mill Kode
 * Duplikat" tidak punya stasiun aktif, sehingga periodenya lahir tanpa satu pun
 * baris dan tidak ada yang bisa dibuka. Itu BUKAN kegagalan: beberapa spec sengaja
 * menanam periode di mill seperti itu untuk menguji keadaan "Tanpa Stasiun".
 * Dalam hal itu helper ini melewatkan langkah pembukaannya dan kembali seperti
 * biasa.
 */
/**
 * Membuat satu periode lewat screen-128 LALU MEMBUKA baris stasiun yang
 * diminta di screen-142, dan kembali ke daftar.
 *
 * MENGAPA INI ADA, SEJAK 2026-10-02. Kunci periode (usecase-141) menjadikan
 * periode TERBUKA sebagai prasyarat menulis record stasiun: ke-18
 * *RecordService dan RecordVerificationService menolak 422 PERIOD_CLOSED
 * ketika tidak ada periode `open` milik mill itu yang memuat tanggal record.
 * createPeriodViaUi() sendiri hanya menghasilkan periode DRAFT —
 * PeriodService::create() menetapkan status Draft secara keras dan tidak
 * menerima field `status` — jadi setiap spec yang menanam record lewat UI
 * setelah membuat periodenya HARUS membuka stasiunnya lebih dulu, atau
 * form-nya ditolak dan spec-nya menggantung di beforeAll.
 *
 * TOLERAN TERHADAP MILL TANPA BARIS STASIUN ITU, dan itu bukan kelonggaran:
 * setiap spec laporan menanam satu PERIOD_OTHER_MILL di mill yang memang
 * TIDAK punya stasiun aktif berjenis itu — justru itu yang diujinya (periode
 * tanpa baris stasiun tidak boleh muncul di pemilih periode). Tidak ada
 * record yang ditanam di periode seperti itu, jadi tidak ada yang perlu
 * dibuka; melemparkan galat di sini hanya akan menggagalkan skenario yang
 * sehat.
 */
export async function createOpenPeriodViaUi(
  page: Page,
  options: { businessUnit: string; name: string; start: string; end: string; stationLabel: string },
): Promise<void> {
  await createPeriodViaUi(page, options)

  const periodId = await periodIdFor(page, options.name)

  await gotoPeriodDetail(page, periodId)

  const stationId = await findStationIdFor(page, periodId, options.stationLabel)

  if (stationId !== null) {
    await openStation(page, periodId, stationId)
  }

  await page.goto(PERIODS_PATH)
  await expect(periodRow(page, options.name)).toBeVisible()
}

/**
 * Seperti stationIdFor(), tapi mengembalikan null alih-alih melempar ketika
 * periode itu tidak punya baris stasiun berlabel itu — termasuk ketika ia
 * tidak punya baris stasiun sama sekali.
 */
export async function findStationIdFor(
  page: Page,
  periodId: string,
  stationLabel: string,
): Promise<string | null> {
  const rows = page.locator(`[data-testid="period-stations-${periodId}"] tbody tr`)

  // Menunggu SALAH SATU dari dua keadaan akhir yang sah: tabel stasiun berisi,
  // atau layar menyatakan periode ini tanpa stasiun. Tanpa penantian ini,
  // count() bisa membaca 0 semata karena tabelnya belum ter-render.
  await expect(
    rows.first().or(page.locator(`[data-testid="period-stations-empty-${periodId}"]`)).first(),
  ).toBeVisible()

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

  return null
}

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
