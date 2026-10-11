/**
 * Pelaksana bagian 8.2 dokumen QA — Struktur Mills (MD-STR-01…05).
 */
import { test, expect } from '@playwright/test'
import { login } from '../tests/support/auth'
import { ADM, adaGalatTeknis, catat, teksHalaman } from './support/qa'

const TREE = '/master-data/tree-view'
const cap = Date.now().toString().slice(-6)

test.describe.configure({ mode: 'serial' })

test('MD-STR-01 — papan kartu per mill tampil dengan hierarki di atasnya', async ({ page }) => {
  await login(page, ADM)
  await page.goto(TREE, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1200)
  const kartu = page.getByTestId('mill-card')
  const jmlKartu = await kartu.count()
  const jmlMill = Number((await page.getByTestId('count-business-unit').innerText().catch(() => '0')).replace(/\D/g, '') || 0)

  const satu = kartu.first()
  const crumb = (await satu.getByTestId('mill-crumb').innerText().catch(() => '')).replace(/\s+/g, ' ').trim()
  const nama = (await satu.getByTestId('mill-name').innerText().catch(() => '')).trim()
  const kode = (await satu.getByTestId('mill-code').innerText().catch(() => '')).trim()
  const jmlLine = (await satu.getByTestId('mill-line-count').innerText().catch(() => '')).trim()
  const barisLine = await satu.getByTestId('line-row').count()
  const aksiLine = await satu.locator('[data-testid^="edit-line-"], [data-testid^="delete-line-"]').count()

  const adaCrumb = /›/.test(crumb) && crumb.split('›').length >= 2
  const ok = jmlKartu > 0 && adaCrumb && nama !== '' && kode !== '' && jmlLine !== '' && aksiLine > 0
  catat('MD-STR-01', '—', ok ? 'Lulus' : 'Gagal',
    `${jmlKartu} kartu mill tampil; penghitung Business Unit di bilah atas menyebut ${jmlMill}. Kartu pertama: jalur hierarki di atas nama="${crumb}" (memuat corporate › company=${adaCrumb}), nama="${nama}", kode="${kode}", jumlah Production Line="${jmlLine}"; daftar Production Line di DALAM kartu=${barisLine} baris, masing-masing membawa aksi (ubah/hapus)=${aksiLine} tombol.`)
  expect(ok).toBe(true)
})

test('MD-STR-02 — keempat tingkat dapat dikelola dari layar ini, lewat modal', async ({ page }) => {
  await login(page, ADM)
  const rinci: string[] = []
  let gagal = 0

  for (const [tingkat, tombolTambah, polaEdit, polaHapus] of [
    ['Corporate', 'add-corporate-button', 'edit-corporate-', 'delete-corporate-'],
    ['Company', 'add-company-button', 'edit-company-', 'delete-company-'],
    ['Business Unit', 'add-mill-button', 'edit-mill-', 'delete-mill-'],
  ] as [string, string, string, string][]) {
    await page.goto(TREE, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1200)
    const alamatSebelum = new URL(page.url()).pathname

    const tambah = page.getByTestId(tombolTambah)
    if (!(await tambah.count())) { gagal++; rinci.push(`${tingkat}: tombol tambah tidak ada`); continue }
    await tambah.click()
    await page.waitForTimeout(1000)
    const modalTerbuka = (await page.getByTestId('save-button').count()) > 0
    const alamatSaatModal = new URL(page.url()).pathname

    const nama = `QA-${tingkat.replace(/\s/g, '')}-${cap}`
    // Kolom nama/kode dinamai field-<nama kolom> di layar ini.
    // Hanya kolom wajib: Kode dan Nama. Mengisi SELURUH kolom dengan nilai
    // yang sama membuat Kode ikut tertimpa dan ditolak sebagai kode kembar —
    // kegagalannya lalu terbaca seolah tambah data tidak bekerja.
    const kolomKode = page.locator('input[data-testid$="_code"]:visible, input[data-testid="field-code"]:visible').first()
    if (await kolomKode.count()) await kolomKode.fill(`QS${cap}${tingkat[0]}`)
    const kolomNama = page.getByTestId('field-name')
    if (await kolomNama.count()) await kolomNama.fill(nama)
    // Induk, bila ada pemilihnya.
    const induk = page.getByTestId('parent-select')
    if (await induk.count()) {
      const opsi = await induk.locator('option').evaluateAll((o) =>
        o.map((e) => (e as HTMLOptionElement).value).filter((v) => v !== ''))
      if (opsi.length) await induk.selectOption(opsi[0])
      else {
        await induk.click(); await page.waitForTimeout(400)
        const li = page.locator('li[role="option"]:visible')
        if (await li.count()) await li.first().click()
      }
      await page.waitForTimeout(500)
    }
    await page.getByTestId('save-button').click()
    await page.waitForTimeout(2200)
    const cari = page.getByTestId('search-input')
    if (await cari.count()) { await cari.fill(nama); await page.waitForTimeout(1300) }
    const dibuat = (await teksHalaman(page)).includes(nama)

    // Ubah
    let diubah = false
    const barisBaru = page.locator('tr, [data-testid="mill-card"]').filter({ hasText: nama }).first()
    if (await barisBaru.count()) {
      const tombolEdit = barisBaru.locator(`[data-testid^="${polaEdit}"]`).first()
      if (await tombolEdit.count()) {
        await tombolEdit.click()
        await page.waitForTimeout(1000)
        await page.getByTestId('field-name').fill(`${nama}-ubah`)
        await page.getByTestId('save-button').click()
        await page.waitForTimeout(2200)
        const c2 = page.getByTestId('search-input')
        if (await c2.count()) { await c2.fill(`${nama}-ubah`); await page.waitForTimeout(1300) }
        diubah = (await teksHalaman(page)).includes(`${nama}-ubah`)
      }
    }

    // Hapus
    let dihapus = false
    const baris2 = page.locator('tr, [data-testid="mill-card"]').filter({ hasText: `${nama}` }).first()
    if (await baris2.count()) {
      const tombolHapus = baris2.locator(`[data-testid^="${polaHapus}"]`).first()
      if (await tombolHapus.count()) {
        await tombolHapus.click()
        await page.waitForTimeout(800)
        const ya = page.getByTestId('confirm-delete-button')
        if (await ya.count()) { await ya.click(); await page.waitForTimeout(2000) }
        // Diperiksa dengan MENGHITUNG BARIS sesudah dicari ulang, bukan dengan
        // mencari namanya di teks halaman: pesan sukses penghapusan memuat nama
        // entri yang baru dihapus, jadi pemeriksaan berbasis teks akan selalu
        // melaporkan "masih ada".
        const c3 = page.getByTestId('search-input')
        if (await c3.count()) { await c3.fill(''); await page.waitForTimeout(800); await c3.fill(nama); await page.waitForTimeout(1300) }
        dihapus = (await page.locator('tr, [data-testid="mill-card"]').filter({ hasText: nama }).count()) === 0
      }
    }

    const tanpaPindahLayar = alamatSebelum === alamatSaatModal
    const okTingkat = modalTerbuka && dibuat && diubah && dihapus && tanpaPindahLayar
    if (!okTingkat) gagal++
    rinci.push(`${tingkat}: modal terbuka tanpa pindah layar=${modalTerbuka && tanpaPindahLayar}; tambah=${dibuat}; ubah=${diubah}; hapus=${dihapus}`)
  }

  // Production Line — lewat tombol di DALAM kartu mill.
  await page.goto(TREE, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1200)
  const tambahLine = page.locator('[data-testid^="add-line-"]').first()
  let lineOk = false
  if (await tambahLine.count()) {
    await tambahLine.click()
    await page.waitForTimeout(1000)
    const modal = (await page.getByTestId('save-button').count()) > 0
    const namaLine = `QA-Line-${cap}`
    const kodeLine = page.locator('input[data-testid$="_code"]:visible, input[data-testid="field-code"]:visible').first()
    if (await kodeLine.count()) await kodeLine.fill(`QLN${cap}`)
    await page.getByTestId('field-name').fill(namaLine)
    await page.getByTestId('save-button').click()
    await page.waitForTimeout(2000)
    const adaLine = (await teksHalaman(page)).includes(namaLine)
    // hapus kembali
    const barisL = page.getByTestId('line-row').filter({ hasText: namaLine }).first()
    let hapusLine = false
    if (await barisL.count()) {
      const h = barisL.locator('[data-testid^="delete-line-"]').first()
      if (await h.count()) {
        await h.click(); await page.waitForTimeout(800)
        const ya = page.getByTestId('confirm-delete-button')
        if (await ya.count()) { await ya.click(); await page.waitForTimeout(1800) }
        hapusLine = !(await teksHalaman(page)).includes(namaLine)
      }
    }
    lineOk = modal && adaLine && hapusLine
    rinci.push(`Production Line (lewat tombol di dalam kartu mill): modal=${modal}; tambah=${adaLine}; hapus=${hapusLine}`)
  } else {
    rinci.push('Production Line: tombol [+ Line] di dalam kartu mill tidak ada')
  }
  if (!lineOk) gagal++

  catat('MD-STR-02', '—', gagal ? 'Gagal' : 'Lulus', rinci.join(' | '))
  expect(gagal, rinci.join('\n')).toBe(0)
})

test('MD-STR-03 — Corporate dan Company tetap terkelola walau tidak punya anak', async ({ page }) => {
  await login(page, ADM)
  await page.goto(TREE, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1200)

  // Prasyarat dibuat di layar ini juga: satu Corporate baru belum punya Company.
  const nama = `QA-Corp-Yatim-${cap}`
  await page.getByTestId('add-corporate-button').click()
  await page.waitForTimeout(1000)
  await page.getByTestId('field-corporate_code').fill(`QCY${cap}`)
  await page.getByTestId('field-name').fill(nama)
  await page.getByTestId('save-button').click()
  await page.waitForTimeout(2000)

  // Langkah 3 — dicari di bagian ringkas Corporate/Company.
  const tabel = page.getByTestId('corporate-table')
  let terlihat = (await tabel.filter({ hasText: nama }).count()) > 0
  for (let h = 0; h < 10 && !terlihat; h++) {
    const lanjut = page.getByTestId('corporate-next-page')
    if (!(await lanjut.count()) || !(await lanjut.isEnabled())) break
    await lanjut.click(); await page.waitForTimeout(700)
    terlihat = (await page.getByTestId('corporate-table').filter({ hasText: nama }).count()) > 0
  }
  // Tidak digambar sebagai kartu mill, karena memang belum punya mill.
  const sebagaiKartu = (await page.getByTestId('mill-card').filter({ hasText: nama }).count()) > 0

  // Langkah 4 — namanya diubah dari layar ini.
  const baris = page.getByTestId('corporate-row').filter({ hasText: nama }).first()
  let dapatDiubah = false
  if (await baris.count()) {
    const e = baris.locator('[data-testid^="edit-corporate-"]').first()
    if (await e.count()) {
      await e.click(); await page.waitForTimeout(1000)
      await page.getByTestId('field-name').fill(`${nama}-ubah`)
      await page.getByTestId('save-button').click()
      await page.waitForTimeout(2000)
      dapatDiubah = (await teksHalaman(page)).includes(`${nama}-ubah`)
    }
  }

  // Dibersihkan.
  const b3 = page.getByTestId('corporate-row').filter({ hasText: `${nama}-ubah` }).first()
  if (await b3.count()) {
    const h = b3.locator('[data-testid^="delete-corporate-"]').first()
    if (await h.count()) {
      await h.click(); await page.waitForTimeout(800)
      const ya = page.getByTestId('confirm-delete-button')
      if (await ya.count()) { await ya.click(); await page.waitForTimeout(1500) }
    }
  }

  const ok = terlihat && dapatDiubah && !sebagaiKartu
  catat('MD-STR-03', '—', ok ? 'Lulus' : 'Gagal',
    `Corporate "${nama}" yang belum punya Company sama sekali: tetap muncul di bagian ringkas Corporate=${terlihat}; namanya tetap dapat diubah dari layar ini=${dapatDiubah}; tidak digambar sebagai kartu mill (benar, karena belum punya mill)=${!sebagaiKartu}.`)
  expect(ok).toBe(true)
})

test('MD-STR-04 — pencarian menyaring sebelum paginasi', async ({ page }) => {
  await login(page, ADM)
  await page.goto(TREE, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1200)

  // Langkah 2 — buka halaman 2 kartu mill, catat satu nama di sana.
  const lanjut = page.getByTestId('mill-next-page')
  const adaHalaman2 = (await lanjut.count()) > 0 && (await lanjut.isEnabled())
  if (!adaHalaman2) {
    catat('MD-STR-04', '—', 'Tidak diuji',
      'Prasyarat tidak tersedia: jumlah mill tidak lebih dari satu halaman, jadi "pencarian menyaring seluruh data, bukan hanya halaman aktif" tidak dapat dibedakan di sini.')
    return
  }
  await lanjut.click()
  await page.waitForTimeout(900)
  const namaHal2 = (await page.getByTestId('mill-card').first().getByTestId('mill-name').innerText()).trim()

  // Langkah 3–4 — kembali ke halaman 1, lalu cari nama itu.
  const prev = page.getByTestId('mill-prev-page')
  if (await prev.count()) { await prev.click(); await page.waitForTimeout(900) }
  const cari = page.getByTestId('search-input')
  await cari.fill(namaHal2)
  await page.waitForTimeout(1200)
  const ketemu = (await page.getByTestId('mill-card').filter({ hasText: namaHal2 }).count()) > 0
  const teks = await teksHalaman(page)

  catat('MD-STR-04', '—', ketemu ? 'Lulus' : 'Gagal',
    `mill "${namaHal2}" diambil dari halaman 2 daftar kartu; sesudah kembali ke halaman 1 dan namanya dicari, mill itu ditemukan=${ketemu}. Artinya pencarian menyaring seluruh data, bukan hanya halaman yang sedang tampil. Galat teknis=${adaGalatTeknis(teks)}.`)
  expect(ketemu).toBe(true)
})

test('MD-STR-05 — aturan hapus sama dengan layar Kelola', async ({ page }) => {
  await login(page, ADM)
  await page.goto(TREE, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1200)
  const rinci: string[] = []
  let gagal = 0

  // MD-KEL-07 dari layar ini: hapus induk yang masih punya anak.
  const kartu = page.getByTestId('mill-card').filter({ hasText: 'Business Unit A' }).first()
  if (!(await kartu.count())) {
    catat('MD-STR-05', '—', 'Tidak diuji', 'Kartu "Business Unit A" (mill yang punya Production Line) tidak ditemukan.')
    return
  }
  const hapusMill = kartu.locator('[data-testid^="delete-mill-"]').first()
  await hapusMill.click()
  await page.waitForTimeout(800)
  const ya = page.getByTestId('confirm-delete-button')
  if (await ya.count()) { await ya.click(); await page.waitForTimeout(1800) }
  const penolakan = (await page.getByTestId('delete-refusal').innerText().catch(() => ''))
    || (await page.getByTestId('delete-error').innerText().catch(() => ''))
  const pesan = penolakan.replace(/\s+/g, ' ').trim()
  const ditolak = /tidak dapat dihapus|masih memiliki|masih ada/i.test(pesan)
  const sebutJumlah = /\d+/.test(pesan)
  const millMasihAda = (await page.getByTestId('mill-card').filter({ hasText: 'Business Unit A' }).count()) > 0
  const lineMasihAda = (await page.getByTestId('mill-card').filter({ hasText: 'Business Unit A' }).first().getByTestId('line-row').count()) > 0
  if (!ditolak || !sebutJumlah || !millMasihAda || !lineMasihAda) gagal++
  rinci.push(`hapus mill yang masih punya Production Line: ditolak=${ditolak}; pesannya menyebut jumlah=${sebutJumlah} ("${pesan.slice(0, 120)}"); mill masih ada=${millMasihAda}; Production Line-nya juga masih utuh (tidak ada penghapusan berantai)=${lineMasihAda}`)

  // MD-KEL-08 dari layar ini: entri tanpa anak boleh dihapus.
  // Dialog penolakan hapus TETAP TERBUKA sesudah penolakan dan menutupi
  // halaman — tanpa ditutup lebih dulu, klik tombol apa pun di belakangnya
  // menggantung sampai batas waktu, bukan gagal dengan jelas.
  const batalHapus = page.getByTestId('cancel-delete-button')
  if (await batalHapus.count() && await batalHapus.isVisible().catch(() => false)) {
    await batalHapus.click({ timeout: 5000 }).catch(() => {})
    await page.waitForTimeout(700)
  }
  await page.goto(TREE, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1200)

  const nama = `QA-Mill-Yatim-${cap}`
  await page.getByTestId('add-mill-button').click()
  await page.waitForTimeout(1000)
  const kodeMill = page.locator('input[data-testid$="_code"]:visible, input[data-testid="field-code"]:visible').first()
  if (await kodeMill.count()) await kodeMill.fill(`QMY${cap}`)
  await page.getByTestId('field-name').fill(nama)
  const induk = page.getByTestId('parent-select')
  if (await induk.count()) {
    const opsi = await induk.locator('option').evaluateAll((o) =>
      o.map((e) => (e as HTMLOptionElement).value).filter((v) => v !== ''))
    if (opsi.length) await induk.selectOption(opsi[0])
    else { await induk.click(); await page.waitForTimeout(400)
           const li = page.locator('li[role="option"]:visible'); if (await li.count()) await li.first().click() }
    await page.waitForTimeout(500)
  }
  await page.getByTestId('save-button').click()
  await page.waitForTimeout(2200)
  const cariMill = page.getByTestId('search-input')
  if (await cariMill.count()) { await cariMill.fill(nama); await page.waitForTimeout(1300) }
  const kartuBaru = page.getByTestId('mill-card').filter({ hasText: nama }).first()
  let hapusBerhasil = false
  if (await kartuBaru.count()) {
    const h = kartuBaru.locator('[data-testid^="delete-mill-"]').first()
    await h.click(); await page.waitForTimeout(800)
    const ya2 = page.getByTestId('confirm-delete-button')
    if (await ya2.count()) { await ya2.click(); await page.waitForTimeout(1800) }
    const c = page.getByTestId('search-input')
    if (await c.count()) { await c.fill(''); await page.waitForTimeout(700); await c.fill(nama); await page.waitForTimeout(1300) }
    hapusBerhasil = (await page.getByTestId('mill-card').filter({ hasText: nama }).count()) === 0
  }
  if (!hapusBerhasil) gagal++
  rinci.push(`hapus mill tanpa Production Line: berhasil=${hapusBerhasil}`)

  catat('MD-STR-05', '—', gagal ? 'Gagal' : 'Lulus', rinci.join(' | '))
  expect(gagal, rinci.join('\n')).toBe(0)
})
