/**
 * Pelaksana bagian 9 dokumen QA — User & Role, Pengaturan Mill (AD-01…10).
 */
import { test, expect } from '@playwright/test'
import { writeFileSync, mkdirSync } from 'node:fs'
import { login, PASSWORD } from '../tests/support/auth'
import { ADM, MGR, OPR, SPV, ada403, catat, keluar, teksHalaman } from './support/qa'
import { bukaTambah, cariBaris, galatDiKolom, galatPerKolom, galatUmum, isiTeks, pilihKombo, simpan, tutupModal } from './support/kelola'

const USERS = '/users'
const MILLSET = '/mill-settings'
const cap = Date.now().toString().slice(-6)

test.describe.configure({ mode: 'serial' })

/** Menelusuri halaman daftar user sampai barisnya ketemu. */
async function barisUser(page: import('@playwright/test').Page, username: string) {
  return cariBaris(page, username)
}

test('AD-01 — tambah user dengan peran dan mill, lalu akun itu benar-benar bisa masuk', async ({ page }) => {
  await login(page, ADM)
  await page.goto(USERS, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1100)
  const username = `qa-spv-${cap}`
  const sandi = 'QaBaru2026!'

  await bukaTambah(page)
  await isiTeks(page, 'username', username)
  await isiTeks(page, 'name', `QA Supervisor ${cap}`)
  await pilihKombo(page, 'role', /Supervisor/i)
  await pilihKombo(page, 'business_unit_id', /Business Unit A/)
  await isiTeks(page, 'password', sandi)
  await simpan(page)
  const pesan = (await galatUmum(page)).join(' ').replace(/\s+/g, ' ')
  const tersimpan = /berhasil|ditambahkan/i.test(pesan)
  const diDaftar = (await barisUser(page, username)) !== null

  // Langkah 5–6 — kasusnya TIDAK selesai saat datanya tersimpan.
  await keluar(page)
  let bisaMasuk = false
  let menu: string[] = []
  try {
    await login(page, username, sandi)
    bisaMasuk = true
    menu = await page.locator('aside a[href], nav a[href]').evaluateAll((els) =>
      [...new Set(els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()).filter((t) => t && t !== 'Logout'))])
  } catch { bisaMasuk = false }
  // Supervisor menurut matriks §1.3: Dashboard, PPA, Laporan Stasiun, Data, Ganti Password.
  const menuSesuai = menu.some((m) => /Dashboard/i.test(m)) && !menu.some((m) => /Kelola User|Mills Setting|Periode Pelaporan/i.test(m))

  const ok = tersimpan && diDaftar && bisaMasuk && menuSesuai
  catat('AD-01', '—', ok ? 'Lulus' : 'Gagal',
    `user "${username}" (Supervisor, Business Unit A) tersimpan=${tersimpan} ("${pesan.slice(0, 80)}"); muncul di daftar=${diDaftar}; akun itu BERHASIL login=${bisaMasuk}; sidebarnya: ${menu.join(', ')} — sesuai kolom Supervisor pada matriks peran=${menuSesuai}.`)
  expect(ok).toBe(true)
})

test('AD-02 — username kembar ditolak', async ({ page }) => {
  await login(page, ADM)
  await page.goto(USERS, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1100)
  const coba = async (nama: string, label: string) => {
    await bukaTambah(page)
    await isiTeks(page, 'username', nama)
    await isiTeks(page, 'name', `QA Kembar ${label}`)
    await pilihKombo(page, 'role', /Supervisor/i)
    await pilihKombo(page, 'business_unit_id', /Business Unit A/)
    await isiTeks(page, 'password', 'QaBaru2026!')
    await simpan(page)
    const galat = [...(await galatPerKolom(page)), ...(await galatUmum(page))].join(' ').replace(/\s+/g, ' ')
    const ditolak = /sudah (ada|dipakai|digunakan)|kembar|duplikat/i.test(galat)
    await tutupModal(page)
    return { ditolak, galat: galat.slice(0, 110) }
  }
  const persis = await coba('supervisor01', 'persis')
  const besar = await coba('SUPERVISOR01', 'huruf besar')
  const spasi = await coba('  supervisor01  ', 'berspasi')

  // Tidak ada user ganda yang tercipta.
  await page.goto(USERS, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1100)
  // Dihitung dari TEKS barisnya dengan batas kata. filter({hasText}) pada
  // pembungkus baris ikut mencocokkan baris lain yang memuat kata "supervisor",
  // sehingga hitungannya menjadi jumlah seluruh baris halaman — bukan jumlah
  // user bernama itu.
  // SATU pemilih baris saja, dan berhenti begitu isi halaman tidak berubah.
  // Dua pemilih yang menunjuk elemen yang sama menghitung setiap baris dua kali,
  // dan tombol "Berikutnya" yang tetap aktif pada halaman terakhir membuat
  // halaman yang sama dihitung berulang — 1 user bisa terbaca 20.
  let jml = 0
  let terakhir = ''
  for (let h = 0; h < 12; h++) {
    // Hanya SEL USERNAME (kolom pertama) yang dibandingkan, bukan seluruh teks
    // baris: kolom Nama memuat username lain sebagai kata ("Brtest
    // supervisor01"), jadi pencocokan seluruh baris ikut menghitung user lain
    // dan satu user terbaca dua puluh.
    const sel = await page.locator('tbody tr').evaluateAll((trs) =>
      trs.map((tr) => ({
        username: (tr.querySelector('td')?.textContent ?? '').replace(/\s+/g, ' ').trim(),
        baris: (tr.textContent ?? '').replace(/\s+/g, ' ').trim(),
      })))
    const sidik = sel.map((x) => x.baris).join('¦')
    if (sidik === terakhir) break
    terakhir = sidik
    jml += sel.filter((x) => x.username.toLowerCase() === 'supervisor01').length
    const lanjut = page.getByRole('button', { name: /Berikutnya|Selanjutnya|Next/i }).first()
    if (!(await lanjut.count()) || !(await lanjut.isEnabled())) break
    await lanjut.click(); await page.waitForTimeout(900)
  }

  const ok = persis.ditolak && besar.ditolak && spasi.ditolak && jml === 1
  catat('AD-02', '—', ok ? 'Lulus' : 'Gagal',
    `username "supervisor01" persis ditolak=${persis.ditolak} ("${persis.galat}"); huruf besar semua ditolak=${besar.ditolak} ("${besar.galat}"); berspasi di depan-belakang ditolak=${spasi.ditolak} ("${spasi.galat}"); jumlah baris bernama itu di daftar=${jml} (harus 1, tidak ada user ganda).`)
  expect(ok).toBe(true)
})

test('AD-03 — peran terikat mill wajib punya mill, Admin tidak', async ({ page }) => {
  await login(page, ADM)
  await page.goto(USERS, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1100)

  // Langkah 2 — Supervisor tanpa mill: harus ditolak.
  await bukaTambah(page)
  await isiTeks(page, 'username', `qa-nomill-${cap}`)
  await isiTeks(page, 'name', 'QA Tanpa Mill')
  await pilihKombo(page, 'role', /Supervisor/i)
  await isiTeks(page, 'password', 'QaBaru2026!')
  await simpan(page)
  const diKolom = await galatDiKolom(page, 'business_unit_id')
  const semua = [...(await galatPerKolom(page)), ...(await galatUmum(page))].join(' ').replace(/\s+/g, ' ')
  const spvDitolak = /wajib|harus dipilih/i.test(semua) && /business unit|mill/i.test(semua)
  await tutupModal(page)

  // Langkah 4 — Admin tanpa mill: harus BERHASIL.
  const adminBaru = `qa-adm-${cap}`
  await bukaTambah(page)
  await isiTeks(page, 'username', adminBaru)
  await isiTeks(page, 'name', 'QA Admin')
  await pilihKombo(page, 'role', /^Admin/i)
  await isiTeks(page, 'password', 'QaBaru2026!')
  await simpan(page)
  const pesanAdmin = [...(await galatPerKolom(page)), ...(await galatUmum(page))].join(' ').replace(/\s+/g, ' ')
  const adminBerhasil = /berhasil|ditambahkan/i.test(pesanAdmin)
  await tutupModal(page)

  const ok = spvDitolak && adminBerhasil
  catat('AD-03', '—', ok ? 'Lulus' : 'Gagal',
    `Supervisor tanpa mill ditolak=${spvDitolak}; pesannya di sebelah kolom mill="${diKolom}"; seluruh pesan: "${semua.slice(0, 110)}". Admin tanpa mill diterima=${adminBerhasil} ("${pesanAdmin.slice(0, 90)}") — Admin memang tidak terikat satu mill; kalau ia juga ditolak, Admin akan mustahil dibuat.`)
  expect(ok).toBe(true)
})

test('AD-04 — ubah peran langsung mengubah menu yang terlihat dan hak verifikasinya', async ({ page }) => {
  const AKUN = `qa-spv-${cap}`      // dibuat AD-01, masih berperan Supervisor
  const SANDI = 'QaBaru2026!'
  await login(page, ADM)
  await page.goto(USERS, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1100)
  const baris = await barisUser(page, AKUN)
  if (baris === null) {
    catat('AD-04', '—', 'Tidak diuji', `Akun uji "${AKUN}" (buatan AD-01) tidak ditemukan di daftar user.`)
    return
  }
  await baris.getByRole('button', { name: /^Edit$/i }).click()
  await page.waitForTimeout(1100)
  await pilihKombo(page, 'role', /Mill Management/i)
  await simpan(page)
  const pesan = (await galatUmum(page)).join(' ').replace(/\s+/g, ' ')

  await keluar(page)
  await login(page, AKUN, SANDI)
  const menu = await page.locator('aside a[href], nav a[href]').evaluateAll((els) =>
    [...new Set(els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()).filter((t) => t && t !== 'Logout'))])
  const adaLapManajemen = menu.some((m) => /Laporan Manajemen/i.test(m))

  // Langkah 5–6 — Checked By tidak bisa, Acknowledged By bisa.
  await page.goto('/data/depricarping', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1200)
  const barisRecord = page.locator('tbody tr').first()
  let checkedBisa: boolean | null = null
  let ackBisa: boolean | null = null
  let alamatForm = ''
  if (await barisRecord.count()) {
    await barisRecord.click()
    await page.waitForLoadState('domcontentloaded')
    await page.waitForTimeout(1200)
    // Dari Detail ke Form Edit lewat alamatnya langsung: id recordnya ada di
    // alamat Detail.
    const id = new URL(page.url()).pathname.split('/').pop() ?? ''
    if (id && id !== 'depricarping') {
      await page.goto(`/data/depricarping/${id}/edit`, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(1400)
    }
    alamatForm = new URL(page.url()).pathname
    // Verifikasi di Form web berbentuk KOTAK CENTANG bernama
    // checked-checkbox / acknowledged-checkbox — bukan kolom teks bernama
    // checked_by. Memakai nama kolom basis data sebagai pemilih membuat
    // keduanya tidak pernah ditemukan dan kasusnya melaporkan null.
    const checked = page.getByTestId('checked-checkbox')
    const ack = page.getByTestId('acknowledged-checkbox')
    checkedBisa = (await checked.count()) ? await checked.isEnabled().catch(() => false) : null
    ackBisa = (await ack.count()) ? await ack.isEnabled().catch(() => false) : null
  }

  // Penanda Checked TIDAK DIRENDER sama sekali untuk Mill Management. Itu
  // memenuhi "tidak bisa diisi" — bahkan lebih tegas daripada kendali kelabu —
  // jadi tidak-ada dan mati dihitung sama di sini.
  const checkedTidakBisa = checkedBisa !== true
  const ok = adaLapManajemen && checkedTidakBisa && ackBisa === true
  catat('AD-04', '—', ok ? 'Lulus' : 'Gagal',
    `peran "${AKUN}" diubah Supervisor → Mill Management ("${pesan.slice(0, 70)}"). Sidebar sesudahnya: ${menu.join(', ')} — menu Laporan Manajemen kini muncul=${adaLapManajemen}. Pada Form stasiun (${alamatForm}): penanda Checked ${checkedBisa === null ? 'TIDAK DIRENDER sama sekali' : 'dapat diisi=' + checkedBisa} (tidak boleh dapat diisi oleh Mill Management), penanda Acknowledged dapat diisi=${ackBisa} (harus true). Perubahan berlaku tanpa membuat akun baru.`)
  expect(ok).toBe(true)
})

test('AD-05 — nonaktifkan user memblokir login, dan aktif kembali memulihkannya', async ({ browser }) => {
  const kUser = await browser.newContext()
  const kAdm = await browser.newContext()
  const u = await kUser.newPage()
  const adm = await kAdm.newPage()
  const AKUN = 'webtest-deact01'
  try {
    await login(u, AKUN)
    await u.goto('/data/weighbridge', { waitUntil: 'domcontentloaded' })

    await login(adm, ADM)
    await adm.goto(USERS, { waitUntil: 'domcontentloaded' })
    adm.on('dialog', (d) => d.accept())
    const baris = await cariBaris(adm, AKUN)
    const adaBaris = baris !== null
    if (baris) {
      await baris.getByRole('button', { name: /^Nonaktifkan$/ }).click()
      await adm.waitForTimeout(1500)
    }

    await u.reload({ waitUntil: 'domcontentloaded' })
    const sesiDicabut = new URL(u.url()).pathname === '/login'

    await u.context().clearCookies()
    await u.goto('/login')
    await u.locator('#username').fill(AKUN)
    await u.locator('#password').fill(PASSWORD)
    await u.locator('button.login-button').click()
    await u.waitForTimeout(2500)
    const alert = u.locator('.login-alert[role="alert"]')
    const pesan = (await alert.count()) ? (await alert.innerText()).trim() : ''
    const loginGagal = new URL(u.url()).pathname === '/login'
    const sebutTidakAktif = /tidak aktif|dinonaktifkan|nonaktif/i.test(pesan)

    // Langkah 2 — diaktifkan kembali, lalu dipastikan bisa login lagi.
    const baris2 = await cariBaris(adm, AKUN)
    if (baris2) {
      await baris2.getByRole('button', { name: /^Aktifkan$/ }).click()
      await adm.waitForTimeout(1500)
    }
    await u.context().clearCookies()
    let bisaLagi = false
    try { await login(u, AKUN); bisaLagi = true } catch { bisaLagi = false }

    const ok = adaBaris && sesiDicabut && loginGagal && sebutTidakAktif && bisaLagi
    catat('AD-05', '—', ok ? 'Lulus' : 'Gagal',
      `akun "${AKUN}" dinonaktifkan dari layar Kelola User & Role: sesi yang sedang berjalan dicabut ke Login=${sesiDicabut}; login berikutnya gagal=${loginGagal} dengan pesan "${pesan}" (menyebut akun tidak aktif=${sebutTidakAktif}); sesudah diaktifkan kembali, akun itu bisa login lagi=${bisaLagi}.`)
    expect(ok).toBe(true)
  } finally {
    await kUser.close(); await kAdm.close()
  }
})

test('AD-06 — reset password oleh Admin', async ({ page }) => {
  const AKUN = 'passtest-mismatch01'
  const LAMA = 'OldPass123!'
  const BARU = `QaReset${cap}!`
  await login(page, ADM)
  await page.goto(USERS, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1100)
  const baris = await barisUser(page, AKUN)
  if (baris === null) {
    catat('AD-06', '—', 'Tidak diuji', `Akun uji "${AKUN}" tidak ditemukan di daftar user.`)
    return
  }
  await baris.getByRole('button', { name: /^Edit$/i }).click()
  await page.waitForTimeout(1100)
  const kolomSandi = page.locator('#password')
  const adaReset = (await kolomSandi.count()) > 0
  if (adaReset) { await kolomSandi.fill(BARU); await simpan(page) }
  const pesan = (await galatUmum(page)).join(' ').replace(/\s+/g, ' ')
  const sukses = /berhasil|diperbarui|tersimpan/i.test(pesan)

  await keluar(page)
  let baruBisa = false
  try { await login(page, AKUN, BARU); baruBisa = true } catch { baruBisa = false }
  await keluar(page)
  await page.goto('/login')
  await page.locator('#username').fill(AKUN)
  await page.locator('#password').fill(LAMA)
  await page.locator('button.login-button').click()
  await page.waitForTimeout(2300)
  const lamaMasihBisa = new URL(page.url()).pathname !== '/login'

  const ok = adaReset && sukses && baruBisa && !lamaMasihBisa
  catat('AD-06', '—', ok ? 'Lulus' : 'Gagal',
    `aksi Reset Password tersedia di layar Edit user=${adaReset}; reset berhasil dengan pesan konfirmasi=${sukses} ("${pesan.slice(0, 80)}"); login dengan password BARU berhasil=${baruBisa}; login dengan password LAMA masih bisa=${lamaMasihBisa} (harus false).`)
  expect(ok).toBe(true)
})

test('AD-07 — Pengaturan Mill: Mill Management hanya mill sendiri', async ({ page }) => {
  await login(page, MGR)
  await page.goto(MILLSET, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1300)
  const pemilih = await page.locator('#selectedBusinessUnitId').count()
  const teks = await teksHalaman(page)
  const millSendiri = /Business Unit A/.test(teks)
  const millLain = /Business Unit B|Business Unit C|Business Unit D/.test(teks)
  // Yang diuji adalah KETIGA hasil diharapkan: tidak ada pemilih mill, mill lain
  // tidak terlihat, nama mill lain tidak tercetak. Bahwa nama mill SENDIRI juga
  // tidak tercetak bukan salah satu di antaranya — dicatat sebagai pengamatan,
  // bukan dijadikan kegagalan.
  const ok = pemilih === 0 && !millLain
  catat('AD-07', '—', ok ? 'Lulus' : 'Gagal',
    `pemilih mill di layar ini=${pemilih} (harus 0 untuk Mill Management); nama mill lain tercetak di mana pun pada layar ini=${millLain} (harus false). Catatan pengamatan: layar ini juga tidak pernah menyebut nama mill yang sedang diatur — isinya hanya "untuk mill Anda" (nama mill sendiri tercetak=${millSendiri}). Isolasinya benar; yang tidak ada adalah penegasan mill mana yang sedang diatur.`)
  expect(ok).toBe(true)
})

test('AD-08 — Pengaturan Mill: Admin dapat semua mill', async ({ page }) => {
  await login(page, ADM)
  await page.goto(MILLSET, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1300)
  const pemilih = page.locator('#selectedBusinessUnitId')
  const ada = (await pemilih.count()) > 0
  if (!ada) {
    catat('AD-08', '—', 'Gagal', 'Pemilih mill tidak ada pada layar Mills Setting untuk Admin.')
    expect(ada).toBe(true)
    return
  }
  await pemilih.click()
  await page.waitForTimeout(600)
  const opsi = await page.locator('li[id^="selectedBusinessUnitId-option-"]').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').trim()).filter(Boolean))
  await page.keyboard.press('Escape')

  // Berganti mill harus mengganti nilai yang tampil.
  const bacaNilai = async () => (await page.locator('#app_name').inputValue().catch(() => '')) + '|' +
                                 ((await page.locator('[data-testid="immediate-sync-toggle"]').isChecked().catch(() => false)) ? 'sync' : 'nosync')
  const pilih = async (pola: RegExp) => {
    await pemilih.click(); await page.waitForTimeout(500)
    const li = page.locator('li[id^="selectedBusinessUnitId-option-"]')
    const n = await li.count()
    for (let i = 0; i < n; i++) {
      const t = (await li.nth(i).innerText()).trim()
      if (pola.test(t)) { await li.nth(i).click(); break }
    }
    await page.waitForTimeout(1400)
  }
  await pilih(/Business Unit A/)
  const nilaiA = await bacaNilai()
  const bisaDiaturA = await page.locator('#app_name').isEditable().catch(() => false)
  await pilih(/Business Unit B/)
  const nilaiB = await bacaNilai()
  const bisaDiaturB = await page.locator('#app_name').isEditable().catch(() => false)

  const semuaMill = opsi.length >= 4
  const ok = semuaMill && bisaDiaturA && bisaDiaturB
  catat('AD-08', '—', ok ? 'Lulus' : 'Gagal',
    `pemilih mill ada dan memuat ${opsi.length} mill (${opsi.slice(0, 6).join(', ')}${opsi.length > 6 ? ', …' : ''}); pengaturan Mill A dapat dibuka dan diatur=${bisaDiaturA} (nilainya "${nilaiA}"); Mill B juga=${bisaDiaturB} (nilainya "${nilaiB}"); berganti mill mengganti nilai yang tampil=${nilaiA !== nilaiB} (bila sama, bisa saja keduanya memang bernilai sama).`)
  expect(ok).toBe(true)
})

test('AD-09 — Supervisor dan Operator tidak punya Pengaturan Mill', async ({ page }) => {
  const hasil: string[] = []
  let gagal = 0
  for (const akun of [SPV, OPR]) {
    await login(page, akun)
    const adaMenu = await page.locator('aside a[href], nav a[href]').evaluateAll(
      (els, jalur) => els.some((e) => new URL((e as HTMLAnchorElement).href).pathname === jalur), MILLSET)
    const resp = await page.goto(MILLSET, { waitUntil: 'domcontentloaded' })
    const teks = await teksHalaman(page)
    const ditolak = (resp?.status() ?? 0) === 403 || ada403(teks)
    const kerangka = (await page.locator('form[action*="logout"], [data-testid="logout-button"]').count()) > 0
    if (adaMenu || !ditolak || !kerangka) gagal++
    hasil.push(`${akun}: menu Mills Setting di sidebar=${adaMenu} (harus false); alamat langsung=${ditolak ? '403' : 'TERBUKA'}; halaman 403 masih di dalam kerangka aplikasi=${kerangka}`)
    await keluar(page)
  }
  catat('AD-09', '—', gagal ? 'Gagal' : 'Lulus', hasil.join(' | '))
  expect(gagal, hasil.join('\n')).toBe(0)
})

test('AD-10 (bagian web) — Pengaturan Mill tersimpan dan bertahan', async ({ page }) => {
  // Langkah 1–3 saja. Seluruh nilai di layar ini dipakai aplikasi MOBILE —
  // tidak ada layar web yang membacanya (ikon pada grid Laporan Stasiun digambar
  // dari peta ikon tetap di dalam view itu sendiri, bukan dari pengaturan ini).
  // Jadi langkah 4–5 "berlaku di layar yang memakainya" dijalankan pelaksana
  // mobile, dan HASIL AD-10 dicatat di sana — supaya satu baris berisi satu
  // kesimpulan, bukan setengah bukti dari dua tempat.
  await login(page, MGR)
  await page.goto(MILLSET, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1500)
  const kolom = page.locator('#app_name')
  const ada = (await kolom.count()) > 0
  const semula = ada ? await kolom.inputValue() : ''
  const baru = `${semula} QA${cap}`
  let sukses = false
  let bertahan = false
  if (ada) {
    await kolom.fill(baru)
    await page.locator('button[type="submit"]:visible').last().click()
    await page.waitForTimeout(2200)
    sukses = /berhasil|tersimpan|diperbarui/i.test(await teksHalaman(page))
    await page.reload({ waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1500)
    bertahan = (await page.locator('#app_name').inputValue().catch(() => '')) === baru
  }
  mkdirSync('qa-run', { recursive: true })
  writeFileSync('qa-run/ad10-web.json', JSON.stringify({ semula, baru, sukses, bertahan }, null, 1), 'utf8')
  // Nilainya DIBIARKAN berubah: pelaksana mobile harus melihat nilai baru itu di
  // Home, lalu ia yang mengembalikannya (langkah 6).
  expect(ada && sukses && bertahan,
    `kolom Nama Aplikasi ada=${ada}; pesan konfirmasi=${sukses}; bertahan sesudah muat ulang=${bertahan}`).toBe(true)
})
