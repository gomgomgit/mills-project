/**
 * Tiga kasus pola Kelola yang TIDAK berlaku di tingkat Business Unit, jadi
 * dijalankan di tingkat yang memang punya aturannya:
 *
 *   MD-KEL-05  → Kelola Company  (satu-satunya tingkat yang membatasi NAMA
 *                                 per induk; enam tingkat lain membatasi kode,
 *                                 dan kode dibatasi secara global)
 *   MD-KEL-10  → Kelola Mesin    (satu-satunya tingkat yang punya kolom
 *   MD-KEL-11  → Kelola Mesin     pencarian teks bebas)
 *
 * Varian yang dicatat di sini HARUS sama dengan kolom "Stasiun / Varian" pada
 * berkas Alur — "Company" dan "Machinery" — kalau tidak, hasilnya tidak akan
 * cocok dengan barisnya dan tidak terisi sama sekali.
 */
import { test, expect } from '@playwright/test'
import { login } from '../tests/support/auth'
import { ADM, adaGalatTeknis, catat, teksHalaman } from './support/qa'
import {
  bukaTambah, cariBaris, galatPerKolom, galatUmum, isiTeks, opsiKombo, pilihKombo, simpan,
  tutupModal,
} from './support/kelola'

const COMPANIES = '/master-data/companies'
const MESIN = '/master-data/machinery'
const cap = Date.now().toString().slice(-6)

/** Membaca ringkasan paginasi "Halaman 1 dari 34 (674 data)". */
async function ringkasanHalaman(page: import('@playwright/test').Page) {
  const t = (await teksHalaman(page)).replace(/\s+/g, ' ')
  const m = t.match(/Halaman\s+(\d+)\s+dari\s+(\d+)\s*\((\d+)\s*data\)/i)
  return m ? { halaman: Number(m[1]), total_halaman: Number(m[2]), total: Number(m[3]) } : null
}

/**
 * Baris mesin pada mode RATA. Mode itu dipakai, bukan mode grup: hanya di
 * sana satu baris = satu mesin dan paginasinya menghitung mesin. Pada mode
 * grup barisnya campuran grup dan anaknya, jadi "setiap baris halaman 2 cocok
 * dengan kata kunci" tidak punya arti yang bisa diperiksa.
 *
 * Baris mode rata TIDAK punya data-testid; yang membedakannya dari baris
 * kosong/panel adalah jumlah selnya.
 */
async function barisMesin(page: import('@playwright/test').Page) {
  return page.locator('tbody tr').evaluateAll((els) =>
    els
      .map((tr) => [...tr.querySelectorAll('td')].map((td) => (td.textContent ?? '').trim()))
      .filter((sel) => sel.length >= 6)
      .map((sel) => ({ kode: sel[0], nama: sel[1] })))
}

async function keMode(page: import('@playwright/test').Page, testid: string) {
  const b = page.getByTestId(testid)
  if (await b.count()) {
    await b.click()
    await page.waitForTimeout(1200)
  }
}

async function cari(page: import('@playwright/test').Page, kata: string) {
  const kotak = page.locator('#search')
  await kotak.fill(kata)
  // Livewire menyaring lewat wire:model.live.debounce — tanpa jeda yang cukup
  // yang terbaca adalah daftar SEBELUM penyaringan, dan hasilnya terbalik.
  await page.waitForTimeout(1800)
}

test('MD-KEL-05 — nama kembar diperiksa dalam lingkup induknya (Company)', async ({ page }) => {
  await login(page, ADM)
  // Dua induk diambil dari DAFTAR OPSI pemilih induknya sendiri, bukan dari
  // kolom tabel Kelola Corporate: di layar itu kolom kedua adalah kode
  // ("CORP-38100"), sementara opsi pemilih menampilkan nama — pola yang
  // dibangun dari kode tidak cocok dengan satu opsi pun, dan kedua
  // penambahan lalu memakai induk yang SAMA.
  await page.goto(COMPANIES, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1100)
  await bukaTambah(page)
  const opsiInduk = await opsiKombo(page, 'corporate_id')
  await tutupModal(page)
  if (opsiInduk.length < 2) {
    catat('MD-KEL-05', 'Company', 'Tidak diuji',
      `Prasyarat tidak tersedia: butuh dua Corporate berbeda, pemilih induk hanya memuat ${opsiInduk.length} pilihan.`)
    return
  }
  const [indukA, indukB] = opsiInduk
  const nama = `QA Sama ${cap}`
  const rinci: string[] = [`dua Corporate dipakai: "${indukA}" dan "${indukB}"`]

  const tambah = async (induk: string, kode: string) => {
    await page.goto(COMPANIES, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1000)
    await bukaTambah(page)
    const dipilih = await pilihKombo(page, 'corporate_id',
      new RegExp(induk.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')))
    await isiTeks(page, 'company_code', kode)
    await isiTeks(page, 'name', nama)
    await simpan(page)
    const galat = [...(await galatPerKolom(page)), ...(await galatUmum(page))]
      .join(' ').replace(/\s+/g, ' ')
    const ditolak = /sudah (ada|dipakai|digunakan|terpakai)|kembar|duplikat/i.test(galat)
    const berhasil = /berhasil|ditambahkan|tersimpan/i.test(galat) && !ditolak
    await tutupModal(page)
    return { dipilih, galat: galat.slice(0, 140), ditolak, berhasil }
  }

  // Langkah 2 — acuan di Corporate A.
  const satu = await tambah(indukA, `QS1${cap}`)
  rinci.push(`"${nama}" pada "${satu.dipilih}" (induk A): berhasil=${satu.berhasil} ("${satu.galat}")`)
  // Langkah 3 — nama SAMA, induk BERBEDA: harus boleh.
  const dua = await tambah(indukB, `QS2${cap}`)
  rinci.push(`nama sama pada "${dua.dipilih}" (induk B): berhasil=${dua.berhasil}, ditolak=${dua.ditolak}`)
  // Langkah 4 — nama SAMA, induk SAMA: harus ditolak.
  const tiga = await tambah(indukA, `QS3${cap}`)
  rinci.push(`nama sama pada induk A lagi: ditolak=${tiga.ditolak} ("${tiga.galat}")`)

  // Daftar memuat dua entri bernama sama, dan induknya terlihat di daftar.
  await page.goto(COMPANIES, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1100)
  const barisSama = await page.locator('.kc-table__row', { hasText: nama })
    .evaluateAll((els) => els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()))
  const indukTerlihat = barisSama.some((b) => b.includes(indukA)) && barisSama.some((b) => b.includes(indukB))
  rinci.push(`daftar memuat ${barisSama.length} entri bernama "${nama}" (harus 2); induk masing-masing terlihat di daftar=${indukTerlihat}`)

  // Bersih-bersih: kedua entri uji dihapus supaya putaran berikutnya tidak
  // menabrak aturan yang sama dan melaporkannya sebagai cacat.
  for (let i = 0; i < 3; i++) {
    const b = await cariBaris(page, nama)
    if (b === null) break
    await b.getByRole('button', { name: /^Hapus$/i }).click()
    await page.waitForTimeout(700)
    const ya = page.getByRole('button', { name: /Ya, Hapus/i }).first()
    if (await ya.count()) { await ya.click(); await page.waitForTimeout(1600) }
  }

  const ok = satu.berhasil && dua.berhasil && !dua.ditolak && tiga.ditolak
    && barisSama.length === 2 && indukTerlihat
  catat('MD-KEL-05', 'Company', ok ? 'Lulus' : 'Gagal', rinci.join(' | ') + '.')
  expect(ok, rinci.join('\n')).toBe(true)
})

test('MD-KEL-10 — pencarian dan paginasi bekerja bersama (Kelola Mesin)', async ({ page }) => {
  await login(page, ADM)
  await page.goto(MESIN, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1400)
  await keMode(page, 'mode-rata')

  const KATA = 'machine'
  await cari(page, KATA)
  const h1 = await ringkasanHalaman(page)
  if (h1 === null || h1.total_halaman < 2) {
    catat('MD-KEL-10', 'Machinery', 'Tidak diuji',
      `Prasyarat tidak tersedia: pencarian "${KATA}" hanya menghasilkan ${h1 ? h1.total : 0} data `
      + `(${h1 ? h1.total_halaman : 0} halaman), sedangkan kasus ini menuntut hasil lebih dari satu halaman.`)
    return
  }

  const rinci: string[] = [
    `mode daftar: rata (satu baris = satu mesin); kata kunci "${KATA}" → ${h1.total} data pada ${h1.total_halaman} halaman`,
  ]
  let gagal = 0
  const cocok = (r: { kode: string; nama: string }) =>
    r.kode.toLowerCase().includes(KATA) || r.nama.toLowerCase().includes(KATA)

  for (const keHalaman of [2, 3]) {
    if (keHalaman > h1.total_halaman) break
    const lanjut = page.getByRole('button', { name: /Berikutnya|Selanjutnya|Next/i }).first()
    if (!(await lanjut.count()) || !(await lanjut.isEnabled())) {
      rinci.push(`tombol ke halaman ${keHalaman} tidak tersedia`)
      gagal++
      break
    }
    await lanjut.click()
    await page.waitForTimeout(1600)
    const baris = await barisMesin(page)
    const tidakCocok = baris.filter((r) => !cocok(r))
    const h = await ringkasanHalaman(page)
    const kotakMasihTerisi = (await page.locator('#search').inputValue().catch(() => '')) === KATA
    if (tidakCocok.length || h?.total !== h1.total || !kotakMasihTerisi) gagal++
    rinci.push(
      `halaman ${h?.halaman ?? keHalaman}: ${baris.length} baris, yang TIDAK memuat kata kuncinya=${tidakCocok.length}`
      + (tidakCocok.length ? ` (mis. "${tidakCocok[0].nama}")` : '')
      + `; jumlah total yang tertulis=${h?.total} (harus tetap ${h1.total})`
      + `; kata kunci masih terisi di kolom pencarian=${kotakMasihTerisi}`)
  }

  catat('MD-KEL-10', 'Machinery', gagal ? 'Gagal' : 'Lulus', rinci.join(' | ') + '.')
  expect(gagal, rinci.join('\n')).toBe(0)
})

test('MD-KEL-11 — % dan _ diperlakukan sebagai huruf biasa (Kelola Mesin)', async ({ page }) => {
  await login(page, ADM)
  await page.goto(MESIN, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1400)

  // Tiga entri uji. Namanya berawalan "QA" supaya pencarian "mill" di langkah
  // 4 tidak menyeret ratusan mesin seeder, tetapi tetap memuat kata "Mill"
  // seperti yang diminta kasusnya.
  const NAMA = [`QA Mill 100% Murni ${cap}`, `QA Mill BU_A ${cap}`, `QA Mill BUxA ${cap}`]
  const dibuat: string[] = []
  for (const [i, nama] of NAMA.entries()) {
    await page.goto(MESIN, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1200)
    const t = page.getByTestId('add-machinery')
    if (!(await t.count())) break
    await t.click()
    await page.waitForTimeout(1000)
    await pilihKombo(page, 'machinery_group_id')
    await isiTeks(page, 'equipment_code', `QM${i}${cap}`)
    await isiTeks(page, 'name', nama)
    await simpan(page)
    const pesan = (await galatUmum(page)).join(' ')
    if (/berhasil|ditambahkan|tersimpan/i.test(pesan)) dibuat.push(nama)
    await tutupModal(page)
  }
  if (dibuat.length < 3) {
    catat('MD-KEL-11', 'Machinery', 'Tidak diuji',
      `Prasyarat tidak tersedia: hanya ${dibuat.length} dari 3 entri uji berhasil dibuat `
      + `(${dibuat.join(', ') || 'tidak satu pun'}), sehingga perbandingan "BU_A vs BUxA" tidak dapat dilakukan.`)
    return
  }

  await page.goto(MESIN, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1300)
  await keMode(page, 'mode-rata')

  const periksa = async (kata: string) => {
    await cari(page, kata)
    const baris = await barisMesin(page)
    const h = await ringkasanHalaman(page)
    const kita = baris.filter((r) => NAMA.includes(r.nama)).map((r) => r.nama)
    return { total: h?.total ?? baris.length, kita, contoh: baris.slice(0, 3).map((r) => r.nama) }
  }

  const persen = await periksa('100%')
  const garis = await periksa('BU_A')
  const kecil = await periksa('mill')

  // `%` sebagai huruf biasa: hanya entri yang namanya MEMUAT tanda persen.
  const persenTepat = persen.kita.length === 1 && persen.kita[0] === NAMA[0]
  // `_` sebagai huruf biasa: "BU_A" tidak boleh ikut mengenai "BUxA".
  const garisTepat = garis.kita.length === 1 && garis.kita[0] === NAMA[1]
  // Tidak peduli huruf besar-kecil: "mill" huruf kecil menemukan ketiganya.
  const kecilTepat = NAMA.every((n) => kecil.kita.includes(n))

  const rinci = [
    `cari "100%": ${persen.total} data, entri uji yang ikut=${persen.kita.length} (${persen.kita.join('; ') || 'tidak ada'}) — harus tepat "${NAMA[0]}"`,
    `cari "BU_A": ${garis.total} data, entri uji yang ikut=${garis.kita.length} (${garis.kita.join('; ') || 'tidak ada'}) — harus tepat "${NAMA[1]}", BUKAN juga "${NAMA[2]}"`,
    `cari "mill" (huruf kecil): entri uji yang ikut=${kecil.kita.length} dari 3 (${kecil.kita.join('; ')})`,
  ]

  // Bersih-bersih.
  for (const nama of NAMA) {
    await page.goto(MESIN, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1100)
    await cari(page, nama)
    const b = page.locator('tbody tr', { hasText: nama }).first()
    if (!(await b.count())) continue
    const hapus = b.getByRole('button', { name: /^Hapus$/i }).first()
    if (!(await hapus.count())) continue
    await hapus.click()
    await page.waitForTimeout(700)
    const ya = page.getByRole('button', { name: /Ya, Hapus/i }).first()
    if (await ya.count()) { await ya.click(); await page.waitForTimeout(1500) }
  }

  const teks = await teksHalaman(page)
  const ok = persenTepat && garisTepat && kecilTepat && !adaGalatTeknis(teks)
  catat('MD-KEL-11', 'Machinery', ok ? 'Lulus' : 'Gagal',
    `${rinci.join(' | ')}. Di banyak sistem % dan _ adalah karakter khusus pencarian; `
    + 'di sini keduanya harus diperlakukan sebagai huruf biasa.')
  expect(ok, rinci.join('\n')).toBe(true)
})
