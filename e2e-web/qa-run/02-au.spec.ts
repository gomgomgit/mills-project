/**
 * Pelaksana bagian 3 dokumen QA — Autentikasi & Akun (AU-01…AU-09).
 * AU-05 (Login Mobile) dijalankan pelaksana mobile, bukan di sini.
 */
import { test, expect } from '@playwright/test'
import { login, PASSWORD } from '../tests/support/auth'
import { ADM, MGR, OPR, SPV, adaGalatTeknis, catat, keluar, teksHalaman } from './support/qa'

test('AU-01 — login berhasil mengarahkan ke beranda yang sesuai perannya', async ({ page }) => {
  const hasil: string[] = []
  let gagal = 0
  for (const [akun, mendarat] of [[OPR, '/beranda'], [SPV, '/dashboard'],
                                  [MGR, '/dashboard'], [ADM, '/dashboard']] as [string, string][]) {
    await login(page, akun)
    const akhir = new URL(page.url()).pathname
    const teks = await teksHalaman(page)
    const menu = await page.locator('aside a[href], nav a[href]').evaluateAll((els) =>
      [...new Set(els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()).filter((t) => t && t !== 'Logout'))])
    const kosong = teks.length < 40
    const sesuaiAlamat = akhir === mendarat
    if (akun === OPR) {
      // Operator: hanya Beranda dan Ganti Password, plus keterangan akses terbatas.
      const terbatas = /terbatas/i.test(teks)
      const hanyaDua = menu.length <= 3
      if (!sesuaiAlamat || !terbatas || !hanyaDua) gagal++
      hasil.push(`${akun}: mendarat di ${akhir} (harus ${mendarat}); keterangan akses terbatas terbaca=${terbatas}; menu=${menu.length} (${menu.join(', ')})`)
    } else {
      if (!sesuaiAlamat || kosong || adaGalatTeknis(teks)) gagal++
      hasil.push(`${akun}: mendarat di ${akhir} (harus ${mendarat}); menu=${menu.length} (${menu.join(', ')}); halaman kosong=${kosong}; galat teknis=${adaGalatTeknis(teks)}`)
    }
    await keluar(page)
  }
  catat('AU-01', '—', gagal ? 'Gagal' : 'Lulus', hasil.join(' | '))
  expect(gagal, hasil.join('\n')).toBe(0)
})

/**
 * Membaca pesan galatnya dari elemen pesannya sendiri (.login-alert, yang
 * dirender hanya saat $errorMessage terisi) dan MENUNGGU elemen itu muncul.
 * Membaca body begitu saja akan tertangkap di tengah proses: tombolnya masih
 * "Memproses…" dan pesannya belum ada, sehingga dua panggilan menghasilkan teks
 * berbeda karena BEDA WAKTU, bukan beda pesan.
 */
async function pesanLoginGagal(page: import('@playwright/test').Page, u: string, p: string): Promise<string> {
  await page.goto('/login')
  await page.locator('#username').fill(u)
  await page.locator('#password').fill(p)
  await page.locator('button.login-button').click()
  const alert = page.locator('.login-alert[role="alert"]')
  await alert.waitFor({ state: 'visible' })
  return (await alert.innerText()).trim()
}

test('AU-02 + AU-03 — pesan login gagal identik untuk password salah dan username tak ada', async ({ page }) => {
  const a = await pesanLoginGagal(page, SPV, 'SalahSekali999!')
  const diLoginA = new URL(page.url()).pathname === '/login'
  const b = await pesanLoginGagal(page, 'tidakada999', 'ApaSaja123!')
  const diLoginB = new URL(page.url()).pathname === '/login'

  const identik = a === b
  // Pesannya tidak boleh memastikan username itu terdaftar.
  const bocorUsername = /password salah|password tidak/i.test(a) && !/username/i.test(a)
  const indonesia = !/\b(invalid|credentials|incorrect|failed)\b/i.test(a)

  const d = `password salah → "${a}" | username tak ada → "${b}" | identik kata per kata=${identik} | keduanya tetap di /login=${diLoginA && diLoginB} | berbahasa Indonesia=${indonesia} | memastikan username terdaftar=${bocorUsername} (harus false)`
  const ok = identik && diLoginA && diLoginB && indonesia && !bocorUsername
  catat('AU-02', '—', ok ? 'Lulus' : 'Gagal', d)
  catat('AU-03', '—', ok ? 'Lulus' : 'Gagal', d)
  expect(ok, d).toBe(true)
})

test('AU-04 — akun dinonaktifkan tidak bisa masuk, dan yang sedang masuk dikeluarkan', async ({ browser }) => {
  const kSpv = await browser.newContext()
  const kAdm = await browser.newContext()
  const spv = await kSpv.newPage()
  const adm = await kAdm.newPage()
  const AKUN = 'webtest-deact01'
  try {
    await login(spv, AKUN)
    await spv.goto('/data/weighbridge', { waitUntil: 'domcontentloaded' })

    await login(adm, ADM)
    await adm.goto('/users', { waitUntil: 'domcontentloaded' })
    adm.on('dialog', (d) => d.accept())
    let ketemu = await adm.locator('tr', { hasText: AKUN }).count() > 0
    for (let h = 0; h < 25 && !ketemu; h++) {
      const lanjut = adm.getByRole('button', { name: /Berikutnya|Selanjutnya|Next/i }).first()
      if (!(await lanjut.count()) || !(await lanjut.isEnabled())) break
      await lanjut.click(); await adm.waitForTimeout(700)
      ketemu = await adm.locator('tr', { hasText: AKUN }).count() > 0
    }
    const tombol = adm.locator('tr', { hasText: AKUN }).first().getByRole('button', { name: /^Nonaktifkan$/ })
    const adaTombol = await tombol.count() > 0
    if (adaTombol) { await tombol.click(); await adm.waitForTimeout(1500) }

    // Langkah 4 — jendela pertama dimuat ulang.
    await spv.reload({ waitUntil: 'domcontentloaded' })
    const keluarPaksa = new URL(spv.url()).pathname === '/login'

    // Langkah 5 — coba login lagi.
    await spv.context().clearCookies()
    await spv.goto('/login')
    await spv.locator('#username').fill(AKUN)
    await spv.locator('#password').fill(PASSWORD)
    await spv.locator('button.login-button').click()
    await spv.waitForTimeout(2500)
    const alert = spv.locator('.login-alert[role="alert"]')
    const pesan = (await alert.count()) ? (await alert.innerText()).trim() : ''
    const gagalMasuk = new URL(spv.url()).pathname === '/login'
    const sebutTidakAktif = /tidak aktif|dinonaktifkan|nonaktif/i.test(pesan)
    const sebutJaringan = /periksa koneksi|koneksi Anda|jaringan/i.test(pesan)

    const ok = adaTombol && keluarPaksa && gagalMasuk && sebutTidakAktif && !sebutJaringan
    catat('AU-04', '—', ok ? 'Lulus' : 'Gagal',
      `tombol Nonaktifkan ditemukan=${adaTombol}; sesi yang sedang berjalan dikeluarkan ke Login=${keluarPaksa}; login berikutnya gagal=${gagalMasuk}; pesannya: "${pesan}"; menyebut akun tidak aktif=${sebutTidakAktif}; menyuruh periksa koneksi=${sebutJaringan} (harus false).`)
    expect(ok).toBe(true)
  } finally {
    // Langkah 6 — diaktifkan kembali supaya kasus lain tidak terhalang.
    await adm.goto('/users', { waitUntil: 'domcontentloaded' }).catch(() => {})
    let k2 = await adm.locator('tr', { hasText: AKUN }).count().catch(() => 0) > 0
    for (let h = 0; h < 25 && !k2; h++) {
      const lanjut = adm.getByRole('button', { name: /Berikutnya|Selanjutnya|Next/i }).first()
      if (!(await lanjut.count().catch(() => 0)) || !(await lanjut.isEnabled().catch(() => false))) break
      await lanjut.click().catch(() => {}); await adm.waitForTimeout(700)
      k2 = await adm.locator('tr', { hasText: AKUN }).count().catch(() => 0) > 0
    }
    const aktifkan = adm.locator('tr', { hasText: AKUN }).first().getByRole('button', { name: /^Aktifkan$/ })
    if (await aktifkan.count().catch(() => 0)) { await aktifkan.click().catch(() => {}); await adm.waitForTimeout(1200) }
    await kSpv.close(); await kAdm.close()
  }
})

test('AU-06 — tamu diarahkan ke Login, tidak melihat isi apa pun', async ({ page }) => {
  const alamat = ['/dashboard', '/data/weighbridge', '/reports', '/master-data/periods', '/users']
  const rinci: string[] = []
  for (const a of alamat) {
    await page.context().clearCookies()
    await page.goto(a, { waitUntil: 'domcontentloaded' })
    const akhir = new URL(page.url()).pathname
    const teks = await teksHalaman(page)
    const bocor = /Data Browser|Dashboard Operasional|Kelola Periode|Kelola User|Laporan Periode/i.test(teks)
    if (akhir !== '/login' || bocor) rinci.push(`${a} → ${akhir} isi terlindung terbaca=${bocor}`)
  }
  catat('AU-06', '—', rinci.length ? 'Gagal' : 'Lulus',
    rinci.length ? rinci.join(' | ')
                 : `${alamat.length} alamat diuji tanpa sesi: semuanya mendarat di /login, dan tidak satu pun judul layar terlindung terbaca di halaman yang muncul.`)
  expect(rinci, rinci.join('\n')).toHaveLength(0)
})

test('AU-07 — ganti password berhasil, dan password lama berhenti berlaku', async ({ page }) => {
  // Akun khusus Ganti Password (passtest-success01), supaya mengubah
  // passwordnya tidak menghalangi kasus lain yang memakai supervisor01.
  const AKUN = 'passtest-success01'
  const LAMA = 'OldPass123!'
  const BARU = 'QaBaru2026!'
  const rinci: string[] = []
  let gagal = 0
  try {
    await login(page, AKUN, LAMA)
    await page.goto('/settings/password', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(700)
    const isi = page.locator('input[type="password"]')
    const jml = await isi.count()
    if (jml < 3) { gagal++; rinci.push(`layar Ganti Password hanya punya ${jml} kolom password (harus 3)`) }
    else {
      await isi.nth(0).fill(LAMA)
      await isi.nth(1).fill(BARU)
      await isi.nth(2).fill(BARU)
      await page.getByRole('button', { name: /Simpan|Ubah|Ganti/i }).first().click()
      await page.waitForTimeout(2000)
      const teks = await teksHalaman(page)
      const berhasil = /berhasil|tersimpan|diperbarui/i.test(teks)
      if (!berhasil) gagal++
      rinci.push(`pesan konfirmasi terbaca=${berhasil}`)

      // Langkah 4 — login dengan password BARU.
      await keluar(page)
      await login(page, AKUN, BARU).then(() => rinci.push('login dengan password baru=berhasil'))
        .catch(() => { gagal++; rinci.push('login dengan password baru=GAGAL') })

      // Langkah 5 — login dengan password LAMA harus gagal.
      await keluar(page)
      await page.goto('/login')
      await page.locator('#username').fill(AKUN)
      await page.locator('#password').fill(LAMA)
      await page.locator('button.login-button').click()
      await page.waitForTimeout(2200)
      const masihBisa = new URL(page.url()).pathname !== '/login'
      if (masihBisa) gagal++
      rinci.push(`login dengan password lama masih bisa=${masihBisa} (harus false)`)
    }
  } finally {
    // Langkah 6 — dikembalikan ke nilai semula.
    await keluar(page)
    await page.goto('/login')
    await page.locator('#username').fill(AKUN).catch(() => {})
    await page.locator('#password').fill(BARU).catch(() => {})
    await page.locator('button.login-button').click().catch(() => {})
    await page.waitForTimeout(2000)
    if (new URL(page.url()).pathname !== '/login') {
      await page.goto('/settings/password', { waitUntil: 'domcontentloaded' }).catch(() => {})
      const isi2 = page.locator('input[type="password"]')
      if (await isi2.count().catch(() => 0) >= 3) {
        await isi2.nth(0).fill(BARU).catch(() => {})
        await isi2.nth(1).fill(LAMA).catch(() => {})
        await isi2.nth(2).fill(LAMA).catch(() => {})
        await page.getByRole('button', { name: /Simpan|Ubah|Ganti/i }).first().click().catch(() => {})
        await page.waitForTimeout(1500)
      }
    }
  }
  catat('AU-07', '—', gagal ? 'Gagal' : 'Lulus', rinci.join(' | '))
  expect(gagal, rinci.join('\n')).toBe(0)
})

test('AU-08 — ganti password ditolak bila password lama salah, galat di sebelah kolomnya', async ({ page }) => {
  const AKUN = 'passtest-wrongold01'
  const LAMA = 'OldPass123!'
  await login(page, AKUN, LAMA)
  await page.goto('/settings/password', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(700)
  const isi = page.locator('input[type="password"]')
  await isi.nth(0).fill('SalahSekali999!')
  await isi.nth(1).fill('QaBaru2026!')
  await isi.nth(2).fill('QaBaru2026!')
  await page.getByRole('button', { name: /Simpan|Ubah|Ganti/i }).first().click()
  await page.waitForTimeout(2000)

  // "Di sebelah kolom password lama" — galatnya harus berada di dalam bungkus
  // kolom itu, bukan sebagai satu pesan umum di atas form.
  const diKolom = await isi.nth(0).evaluate((el) => {
    const bungkus = (el as HTMLElement).closest('.form-field, .md-field, .kc-field, div')
    return /[a-z]/i.test(bungkus?.querySelector('.form-field__error, .md-field__error, .kc-field__error, [role="alert"], .error, .text-danger')?.textContent ?? '')
  })
  const teks = await teksHalaman(page)
  const ditolak = /salah|tidak sesuai|tidak cocok/i.test(teks)

  // Langkah 4 — password tidak berubah: login dengan yang lama harus berhasil.
  await keluar(page)
  let masihBerlaku = false
  try { await login(page, AKUN, LAMA); masihBerlaku = true } catch { masihBerlaku = false }

  const ok = ditolak && diKolom && masihBerlaku
  catat('AU-08', '—', ok ? 'Lulus' : 'Gagal',
    `permintaan ditolak=${ditolak}; pesan galat berada di sebelah kolom password lama (bukan pesan umum di atas form)=${diKolom}; password tidak berubah — login dengan password lama masih berhasil=${masihBerlaku}.`)
  expect(ok).toBe(true)
})

test('AU-09 — logout mencabut sesi perangkat itu', async ({ page }) => {
  await login(page, SPV)
  await page.goto('/data/weighbridge', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(700)
  const sebelum = (await teksHalaman(page)).slice(0, 200)
  // Kasus ini menguji TOMBOL Logout, jadi ia harus ditekan sungguhan — bukan
  // dibersihkan cookie-nya seperti helper keluar().
  const tombol = page.locator('[data-testid="logout-button"], [data-testid="nav-menu-logout"]').first()
  const adaTombol = await tombol.count() > 0
  if (adaTombol) { await tombol.click(); await page.waitForLoadState('domcontentloaded') }
  const sesudahLogout = new URL(page.url()).pathname

  // Langkah 3 — tombol Back browser.
  await page.goBack({ waitUntil: 'domcontentloaded' }).catch(() => {})
  await page.waitForTimeout(600)
  const teksBack = await teksHalaman(page)
  const dataMasihTerbaca = /Data Browser/i.test(teksBack) && !/Masuk untuk melanjutkan/i.test(teksBack)

  // Langkah 4 — alamat diketik ulang.
  await page.goto('/data/weighbridge', { waitUntil: 'domcontentloaded' })
  const akhir = new URL(page.url()).pathname

  const ok = adaTombol && sesudahLogout === '/login' && !dataMasihTerbaca && akhir === '/login'
  catat('AU-09', '—', ok ? 'Lulus' : 'Gagal',
    `tombol Logout ditemukan=${adaTombol}; menekannya mendarat di ${sesudahLogout}; tombol Back menampilkan data lama lagi=${dataMasihTerbaca} (harus false); mengetik ulang alamat Data Browser mendarat di ${akhir}. Isi sebelum logout memang terbaca (${sebelum.length} karakter), jadi perbandingannya berarti.`)
  expect(ok).toBe(true)
})
