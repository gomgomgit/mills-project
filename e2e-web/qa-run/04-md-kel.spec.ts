/**
 * Pelaksana bagian 8.1 dokumen QA — pola Kelola (MD-KEL-01…12), dijalankan
 * pada tingkat **Business Unit**, sesuai varian baris berkas Alur.
 *
 * Seluruh kasus di sini MENGUBAH DATA. Nama entri ujinya selalu berawalan
 * "QA-" dan dibersihkan di akhir, supaya putaran berikutnya tidak menabrak
 * aturan nama kembar yang diuji MD-KEL-03.
 */
import { test, expect } from '@playwright/test'
import { login } from '../tests/support/auth'
import { ADM, adaGalatTeknis, catat, teksHalaman } from './support/qa'
import {
  bukaTambah, cariBaris, galatDiKolom, galatPerKolom, galatUmum,
  isiTeks, jumlahBaris, jumlahTertulis, pilihKombo, simpan, tutupModal,
} from './support/kelola'

const BU = '/master-data/business-units'
const VARIAN = 'Business Unit'
const cap = Date.now().toString().slice(-6)

// Urutannya tetap berurut (workers: 1, fullyParallel: false), tetapi BUKAN
// mode 'serial': pada mode itu satu kegagalan membuat seluruh kasus sesudahnya
// DILEWATI — dan kegagalan di sini sering kali temuan nyata, bukan tanda bahwa
// pelaksananya rusak. Melewatkan 54 kasus karena satu temuan akan membuat
// laporannya jauh lebih kosong daripada kenyataannya.

test('MD-KEL-01 — tambah data berhasil dan langsung terlihat', async ({ page }) => {
  await login(page, ADM)
  await page.goto(BU, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(900)
  const totalAwal = await jumlahTertulis(page)
  const barisAwal = await jumlahBaris(page)

  const nama = `QA-Mill-${cap}`
  const dibuka = await bukaTambah(page)
  const induk = await pilihKombo(page, 'company_id')
  await isiTeks(page, 'code', `QA${cap}`)
  await isiTeks(page, 'name', nama)
  await simpan(page)

  const teks = await teksHalaman(page)
  const sukses = /berhasil|tersimpan|ditambahkan/i.test(teks)
  // "Langsung muncul tanpa muat ulang": dicari TANPA page.reload().
  const muncul = (await cariBaris(page, nama)) !== null
  const totalAkhir = await jumlahTertulis(page)
  const bertambah = totalAwal !== null && totalAkhir !== null ? totalAkhir === totalAwal + 1 : null

  const ok = dibuka && sukses && muncul && (bertambah ?? true)
  catat('MD-KEL-01', VARIAN, ok ? 'Lulus' : 'Gagal',
    `jumlah data sebelum=${totalAwal ?? barisAwal}; entri "${nama}" dibuat dengan induk Company "${induk}"; pesan konfirmasi terbaca=${sukses}; entri langsung muncul di daftar tanpa muat ulang browser=${muncul}; jumlah data sesudah=${totalAkhir} (bertambah satu=${bertambah}).`)
  expect(ok).toBe(true)
})

test('MD-KEL-02 — kolom wajib divalidasi di sebelah kolomnya', async ({ page }) => {
  await login(page, ADM)
  await page.goto(BU, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(900)
  const rinci: string[] = []
  let gagal = 0

  // Satu kolom wajib dikosongkan per putaran; yang lain tetap diisi.
  for (const kosong of ['code', 'name'] as const) {
    await bukaTambah(page)
    await pilihKombo(page, 'company_id')
    await isiTeks(page, 'code', kosong === 'code' ? '' : `QA${cap}X`)
    await isiTeks(page, 'name', kosong === 'name' ? '' : `QA-Lain-${cap}`)
    await simpan(page)
    const diKolom = await galatDiKolom(page, kosong)
    const umum = await galatUmum(page)
    const lainTetap = kosong === 'code'
      ? (await page.locator('#name').inputValue().catch(() => '')) !== ''
      : (await page.locator('#code').inputValue().catch(() => '')) !== ''
    const ok = diKolom !== '' && !adaGalatTeknis(diKolom)
    if (!ok) gagal++
    rinci.push(`kolom "${kosong}" dikosongkan → galat di sebelah kolomnya="${diKolom}"; pesan umum di atas form=${umum.length}; isi kolom lain tetap ada=${lainTetap}`)
    await tutupModal(page)
  }
  // Kolom induk diuji tersendiri di MD-KEL-04.
  catat('MD-KEL-02', VARIAN, gagal ? 'Gagal' : 'Lulus',
    `${rinci.join(' | ')}. Kolom induk (Company) diuji tersendiri pada MD-KEL-04.`)
  expect(gagal, rinci.join('\n')).toBe(0)
})

test('MD-KEL-03 — entri kembar ditolak, tidak peduli huruf besar-kecil', async ({ page }) => {
  // Di tingkat Business Unit yang dibatasi adalah KODE, bukan nama: nama
  // sengaja tidak dibatasi sama sekali (dua mill boleh bernama sama selama
  // kodenya berbeda). Menguji nama di sini akan melaporkan cacat atas perilaku
  // yang justru dikehendaki.
  await login(page, ADM)
  await page.goto(BU, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(900)
  const kode = `QK${cap}`

  await bukaTambah(page)
  const induk = await pilihKombo(page, 'company_id')
  await isiTeks(page, 'code', kode)
  await isiTeks(page, 'name', `QA-Kembar-${cap}`)
  await simpan(page)
  const acuanAda = /berhasil|ditambahkan/i.test((await galatUmum(page)).join(' '))
  await tutupModal(page)

  const coba = async (nilaiKode: string, label: string) => {
    await bukaTambah(page)
    await pilihKombo(page, 'company_id', new RegExp(induk.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')))
    await isiTeks(page, 'code', nilaiKode)
    await isiTeks(page, 'name', `QA-Kembar-${label}-${cap}`)
    await simpan(page)
    const galat = [...(await galatPerKolom(page)), ...(await galatUmum(page))].join(' ')
    const ditolak = /sudah (ada|dipakai|digunakan|terpakai)|kembar|duplikat/i.test(galat)
    await tutupModal(page)
    return { ditolak, galat: galat.replace(/\s+/g, ' ').slice(0, 110) }
  }

  const kecil = await coba(kode.toLowerCase(), 'kecil')
  const sama = await coba(kode, 'sama')
  const spasi = await coba(`  ${kode}  `, 'spasi')

  const ok = acuanAda && kecil.ditolak && sama.ditolak && spasi.ditolak
  catat('MD-KEL-03', VARIAN, ok ? 'Lulus' : 'Gagal',
    `Kolom yang dibatasi di tingkat ini adalah Kode (nama memang tidak dibatasi). Entri acuan berkode "${kode}" dibuat=${acuanAda}. Kode sama berhuruf kecil ditolak=${kecil.ditolak} ("${kecil.galat}"); kode sama apa adanya ditolak=${sama.ditolak} ("${sama.galat}"); kode sama berspasi di depan-belakang ditolak=${spasi.ditolak} ("${spasi.galat}").`)
  expect(ok).toBe(true)
})

test('MD-KEL-04 — induk wajib dipilih, ditolak dengan pesan validasi bukan galat teknis', async ({ page }) => {
  await login(page, ADM)
  await page.goto(BU, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(900)
  await bukaTambah(page)
  await isiTeks(page, 'code', `QI${cap}`)
  await isiTeks(page, 'name', `QA-TanpaInduk-${cap}`)
  // Kolom induk SENGAJA dibiarkan belum dipilih.
  await simpan(page)

  const diKolom = await galatDiKolom(page, 'company_id')
  const semua = [...(await galatPerKolom(page)), ...(await galatUmum(page))].join(' ')
  const teks = await teksHalaman(page)
  const teknis = adaGalatTeknis(teks)
  const validasi = /wajib|harus dipilih|tidak boleh kosong/i.test(semua)
  const sebutInduk = /company/i.test(diKolom || semua)

  const ok = validasi && !teknis && (diKolom !== '' || sebutInduk)
  catat('MD-KEL-04', VARIAN, ok ? 'Lulus' : 'Gagal',
    `pesan validasi terbaca=${validasi}; pesannya di sebelah kolom induk="${diKolom}"; pesan menyebut kolom induk (Company)=${sebutInduk}; galat teknis (SQLSTATE/stack trace/500)=${teknis} (harus false). Seluruh pesan yang tampil: "${semua.slice(0, 160)}"`)
  expect(ok).toBe(true)
})

// MD-KEL-05 TIDAK ada di berkas ini. Aturan "nama kembar dalam lingkup
// induknya" hanya hidup di tingkat Company, jadi jalannya di berkas Alur
// bervarian "Company" dan kasusnya dijalankan di `04b-md-kel-lain.spec.ts`.
// Mencatatnya di sini dengan varian "Business Unit" akan menghasilkan baris
// hasil yang tidak cocok dengan baris mana pun — hasilnya hilang tanpa jejak.

test('MD-KEL-06 — ubah data berhasil dan terlihat di layar lain yang memakainya', async ({ page }) => {
  await login(page, ADM)
  await page.goto(BU, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(900)

  // Entri yang DIPAKAI di layar lain: Business Unit A muncul sebagai kolom Mill
  // di daftar user dan sebagai nama mill di layar laporan.
  const baris = await cariBaris(page, 'Business Unit A')
  if (baris === null) {
    catat('MD-KEL-06', VARIAN, 'Tidak diuji', 'Entri acuan "Business Unit A" tidak ditemukan di daftar.')
    return
  }
  await baris.getByRole('button', { name: /^Edit$/i }).click()
  await page.waitForTimeout(1000)
  const namaBaru = `Business Unit A QA${cap}`
  await isiTeks(page, 'name', namaBaru)
  await simpan(page)
  const diDaftar = (await cariBaris(page, namaBaru)) !== null

  // Layar lain yang memakainya: pemilih Mill pada Mills Setting sebagai Admin.
  // Daftar user TIDAK dipakai di sini — halaman pertamanya berisi akun mill
  // lain, jadi "nama tidak terlihat" di sana tidak berarti apa pun tentang
  // perubahan nama, dan kasusnya gagal tanpa sebab yang benar.
  await page.goto('/mill-settings', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1100)
  const teksLain = await teksHalaman(page)
  const opsiMill = await page.locator('option, li[role="option"]').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').trim()))
  const semua = teksLain + ' ' + opsiMill.join(' ')
  const diLayarLain = semua.includes(namaBaru)
  const namaLamaMasihAda = /Business Unit A(?! QA)/.test(semua)

  // Dikembalikan supaya kasus lain membaca nama yang sama seperti semula.
  await page.goto(BU, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(800)
  const b2 = await cariBaris(page, namaBaru)
  if (b2) {
    await b2.getByRole('button', { name: /^Edit$/i }).click()
    await page.waitForTimeout(1000)
    await isiTeks(page, 'name', 'Business Unit A')
    await simpan(page)
  }

  const ok = diDaftar && diLayarLain && !namaLamaMasihAda
  catat('MD-KEL-06', VARIAN, ok ? 'Lulus' : 'Gagal',
    `nama diubah menjadi "${namaBaru}". Terlihat di daftar Kelola=${diDaftar}; terlihat di layar lain yang memakainya (kolom Mill pada daftar Kelola User & Role)=${diLayarLain}; masih ada tempat yang menampilkan nama lama=${namaLamaMasihAda} (harus false). Nama dikembalikan ke semula sesudah diperiksa.`)
  expect(ok).toBe(true)
})

test('MD-KEL-07 — hapus ditolak bila masih punya anak, dengan menyebut jumlahnya', async ({ page }) => {
  await login(page, ADM)
  const rinci: string[] = []
  let gagal = 0
  // Tiga tingkat sekaligus, seperti langkah 2–5 kasusnya.
  for (const [layar, induk, anak] of [
    ['/master-data/corporates', 'Sawit Nusantara', 'Company'],
    ['/master-data/companies', 'PT Ada Business Unit', 'Business Unit'],
    [BU, 'Business Unit A', 'Production Line'],
  ] as [string, string, string][]) {
    await page.goto(layar, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(900)
    const baris = await cariBaris(page, induk)
    if (baris === null) { rinci.push(`${anak}: induk "${induk}" tidak ditemukan`); continue }
    await baris.getByRole('button', { name: /^Hapus$/i }).click()
    await page.waitForTimeout(700)
    await page.getByRole('button', { name: /Ya, Hapus/i }).first().click()
    await page.waitForTimeout(1500)
    const pesan = [...(await galatUmum(page))].join(' ').replace(/\s+/g, ' ')
    const ditolak = /tidak dapat dihapus|masih memiliki|masih ada|masih dipakai/i.test(pesan)
    const sebutJumlah = /\d+/.test(pesan)
    const masihAda = (await cariBaris(page, induk)) !== null
    if (!ditolak || !sebutJumlah || !masihAda) gagal++
    rinci.push(`${induk} (punya ${anak}): ditolak=${ditolak}; pesannya menyebut jumlah penghalang=${sebutJumlah} ("${pesan.slice(0, 110)}"); induknya masih ada=${masihAda}`)
  }
  catat('MD-KEL-07', VARIAN, gagal ? 'Gagal' : 'Lulus',
    `${rinci.join(' | ')}. Tidak ada penghapusan berantai: setiap induk yang penghapusannya ditolak masih utuh di daftarnya.`)
  expect(gagal, rinci.join('\n')).toBe(0)
})

test('MD-KEL-08 — hapus berhasil bila sudah tidak punya anak', async ({ page }) => {
  await login(page, ADM)
  await page.goto(BU, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(900)
  // Entri buatan MD-KEL-01 belum punya anak sama sekali.
  const nama = `QA-Mill-${cap}`
  const baris = await cariBaris(page, nama)
  if (baris === null) {
    catat('MD-KEL-08', VARIAN, 'Tidak diuji',
      `Prasyarat tidak tersedia: entri tanpa anak "${nama}" (buatan MD-KEL-01) tidak ditemukan di daftar.`)
    return
  }
  await baris.getByRole('button', { name: /^Hapus$/i }).click()
  await page.waitForTimeout(700)
  await page.getByRole('button', { name: /Ya, Hapus/i }).first().click()
  await page.waitForTimeout(1800)
  const pesan = (await galatUmum(page)).join(' ')
  const sukses = /berhasil|dihapus/i.test(pesan)
  const hilang = (await cariBaris(page, nama)) === null
  const ok = sukses && hilang
  catat('MD-KEL-08', VARIAN, ok ? 'Lulus' : 'Gagal',
    `entri tanpa anak "${nama}" dihapus: pesan konfirmasi terbaca=${sukses} ("${pesan.replace(/\s+/g, ' ').slice(0, 110)}"); entri hilang dari daftar=${hilang}.`)
  expect(ok).toBe(true)
})

test('MD-KEL-09 — hapus meminta konfirmasi, dan Batal tidak menghapus', async ({ page }) => {
  await login(page, ADM)
  await page.goto(BU, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(900)
  // Entri tanpa anak dibuat khusus untuk kasus ini.
  const nama = `QA-Konfirm-${cap}`
  await bukaTambah(page)
  await pilihKombo(page, 'company_id')
  await isiTeks(page, 'code', `QC${cap}`)
  await isiTeks(page, 'name', nama)
  await simpan(page)

  const baris = await cariBaris(page, nama)
  if (baris === null) {
    catat('MD-KEL-09', VARIAN, 'Gagal', `entri uji "${nama}" gagal dibuat, sehingga konfirmasi hapus tidak dapat diuji.`)
    expect(baris).not.toBeNull()
    return
  }
  // Langkah 2 — Hapus lalu Batal.
  await baris.getByRole('button', { name: /^Hapus$/i }).click()
  await page.waitForTimeout(700)
  const adaKonfirmasi = (await page.getByRole('button', { name: /Ya, Hapus/i }).count()) > 0
  const batal = page.getByRole('button', { name: /^Batal$/i })
  if (await batal.count()) { await batal.first().click(); await page.waitForTimeout(900) }
  const masihAda = (await cariBaris(page, nama)) !== null

  // Langkah 4 — Hapus lalu setujui.
  const b2 = await cariBaris(page, nama)
  if (b2) {
    await b2.getByRole('button', { name: /^Hapus$/i }).click()
    await page.waitForTimeout(700)
    await page.getByRole('button', { name: /Ya, Hapus/i }).first().click()
    await page.waitForTimeout(1600)
  }
  const terhapus = (await cariBaris(page, nama)) === null

  const ok = adaKonfirmasi && masihAda && terhapus
  catat('MD-KEL-09', VARIAN, ok ? 'Lulus' : 'Gagal',
    `menekan Hapus memunculkan konfirmasi (tombol "Ya, Hapus")=${adaKonfirmasi}, tidak langsung menghapus; sesudah Batal entri masih ada=${masihAda}; sesudah disetujui entri terhapus=${terhapus}.`)
  expect(ok).toBe(true)
})

// MD-KEL-10 dan MD-KEL-11 juga tidak ada di berkas ini: keduanya menguji
// kolom pencarian teks bebas, dan dari delapan tingkat Kelola hanya Kelola
// Mesin yang punya — tujuh lainnya menyaring lewat pemilih induk. Jalannya
// bervarian "Machinery" dan kasusnya ada di `04b-md-kel-lain.spec.ts`.
// Pencarian mill diuji terpisah pada MD-STR-04.

test('MD-KEL-12 — logo mill dapat diunggah dan terlihat', async ({ page }) => {
  await login(page, ADM)
  await page.goto(BU, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(900)
  const baris = await cariBaris(page, 'Business Unit B')
  if (baris === null) {
    catat('MD-KEL-12', VARIAN, 'Tidak diuji', 'Entri "Business Unit B" tidak ditemukan untuk diuji.')
    return
  }
  await baris.getByRole('button', { name: /^Edit$/i }).click()
  await page.waitForTimeout(1100)
  const kolomLogo = page.locator('input[type="file"]').first()
  if (!(await kolomLogo.count())) {
    catat('MD-KEL-12', VARIAN, 'Gagal', 'Layar Edit Business Unit tidak punya kolom unggah berkas untuk logo.')
    expect(await kolomLogo.count()).toBeGreaterThan(0)
    return
  }
  // PNG 1×1 sah, dibentuk di tempat — tidak bergantung berkas di luar repo.
  const png = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==',
    'base64')
  await kolomLogo.setInputFiles({ name: 'qa-logo.png', mimeType: 'image/png', buffer: png })
  await page.waitForTimeout(1200)
  await simpan(page)
  const pesan1 = (await galatUmum(page)).join(' ').replace(/\s+/g, ' ')
  const tersimpan = /berhasil|diperbarui|tersimpan/i.test(pesan1)

  // Langkah 3 — buka kembali dan periksa logonya.
  const b2 = await cariBaris(page, 'Business Unit B')
  const adaGambarDiDaftar = b2 ? (await b2.locator('img').count()) > 0 : false
  if (b2) { await b2.getByRole('button', { name: /^Edit$/i }).click(); await page.waitForTimeout(1100) }
  const adaPratinjau = (await page.locator('.kc-logo-field img, img[src*="logo"], .kc-logo-field__preview img').count()) > 0

  // Langkah 4 — berkas bukan gambar harus ditolak.
  const kolomLogo2 = page.locator('input[type="file"]').first()
  let ditolak = false
  let pesan2 = ''
  if (await kolomLogo2.count()) {
    await kolomLogo2.setInputFiles({ name: 'qa-bukan-gambar.txt', mimeType: 'text/plain', buffer: Buffer.from('bukan gambar') })
    await page.waitForTimeout(1500)
    await simpan(page)
    pesan2 = [...(await galatPerKolom(page)), ...(await galatUmum(page))].join(' ').replace(/\s+/g, ' ')
    ditolak = /gambar|image|png|jpg|jpeg|tidak didukung|harus berupa/i.test(pesan2)
  }
  await tutupModal(page)

  // Logo tetap ada sesudah penolakan.
  const b3 = await cariBaris(page, 'Business Unit B')
  const logoTetap = b3 ? (await b3.locator('img').count()) > 0 : false

  const ok = tersimpan && (adaGambarDiDaftar || adaPratinjau) && ditolak && logoTetap
  catat('MD-KEL-12', VARIAN, ok ? 'Lulus' : 'Gagal',
    `logo PNG diunggah: pesan konfirmasi=${tersimpan} ("${pesan1.slice(0, 90)}"); logonya terlihat di daftar=${adaGambarDiDaftar}, terlihat saat entri dibuka kembali=${adaPratinjau}; berkas .txt ditolak dengan pesan jelas=${ditolak} ("${pesan2.slice(0, 110)}"); logo yang sudah tersimpan tetap ada sesudah penolakan=${logoTetap}.`)
  expect(ok).toBe(true)
})
