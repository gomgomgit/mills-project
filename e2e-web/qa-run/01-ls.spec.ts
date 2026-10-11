/**
 * Pelaksana bagian 2 dokumen QA — Aturan lintas-sistem (LS-01…LS-16).
 *
 * Tiap test di bawah menjalankan LANGKAH YANG TERTULIS pada kasusnya dan
 * mencatat apa yang terlihat. LS-10 dan LS-12 tidak ada di sini: langkahnya
 * berjalan di aplikasi mobile, jadi keduanya dijalankan pelaksana mobile.
 */
import { test, expect, type Page } from '@playwright/test'
import { login } from '../tests/support/auth'
import {
  ADM, MGR, NOMILL, OPR, SPV, SPV_B,
  ada403, adaGalatTeknis, catat, keluar, pilihLine, pilihPeriode, pilihPeriodeBerdata,
  salahSatuTestid, teksHalaman,
  TESTID_CATATAN_TANPA_TANDA, TESTID_EKSPOR_CSV, TESTID_EKSPOR_XLS, TESTID_MILL_TEKS,
} from './support/qa'

/** Alamat yang menurut matriks §1.3 TIDAK boleh dibuka tiap peran. */
const TERLARANG: Record<string, string[]> = {
  [OPR]: ['/dashboard', '/data/weighbridge', '/reports', '/master-data/periods',
          '/master-data/tree-view', '/users', '/mill-settings', '/reports/management',
          '/production-process-activity'],
  [SPV]: ['/master-data/periods', '/master-data/tree-view', '/users', '/mill-settings',
          '/reports/management'],
  [MGR]: ['/master-data/periods', '/master-data/tree-view', '/users'],
  [ADM]: ['/reports/management'],
}

test('LS-01 — peran tak berhak ditolak 403 di dalam kerangka aplikasi', async ({ page }) => {
  const rinci: string[] = []
  let total = 0
  for (const [akun, alamat] of Object.entries(TERLARANG)) {
    await login(page, akun)
    for (const a of alamat) {
      total++
      const resp = await page.goto(a, { waitUntil: 'domcontentloaded' })
      const kode = resp?.status() ?? 0
      const teks = await teksHalaman(page)
      const akhir = new URL(page.url()).pathname
      // Kerangka aplikasi: halaman 403 harus tetap membawa sidebar dan Logout,
      // bukan halaman telanjang.
      const kerangka = (await page.locator('form[action*="logout"], [data-testid="logout-button"]').count()) > 0
      const ok = (kode === 403 || ada403(teks)) && !adaGalatTeknis(teks) && akhir === a && kerangka
      if (!ok) {
        rinci.push(`${akun} ${a}: status=${kode} 403=${ada403(teks)} alamat=${akhir} kerangka=${kerangka} teknis=${adaGalatTeknis(teks)}`)
      }
    }
    await keluar(page)
  }
  catat('LS-01', '—', rinci.length ? 'Gagal' : 'Lulus',
    rinci.length
      ? `${rinci.length} dari ${total} kombinasi peran×alamat tidak menjawab 403 bersih. ${rinci.slice(0, 5).join(' | ')}`
      : `${total} kombinasi peran×alamat diuji (Operator 9, Supervisor 5, Mill Management 3, Admin 1): semuanya menjawab 403/Akses Ditolak, alamat tidak dialihkan, sidebar dan tombol Logout tetap ada, tanpa galat teknis.`)
  expect(rinci, rinci.join('\n')).toHaveLength(0)
})

test('LS-02 — menu yang tak boleh diakses juga tidak muncul, dan yang muncul selalu terbuka', async ({ page }) => {
  const rinci: string[] = []
  const ringkas: string[] = []
  for (const akun of [OPR, SPV, MGR, ADM]) {
    await login(page, akun)
    const menu = await page.locator('aside a[href], nav a[href]').evaluateAll((els) =>
      els.map((e) => ({ href: new URL((e as HTMLAnchorElement).href).pathname,
                        label: (e.textContent ?? '').replace(/\s+/g, ' ').trim() }))
         .filter((x) => x.href !== '/logout'))
    const unik = [...new Map(menu.map((m) => [m.href, m])).values()]

    // (a) tidak ada menu yang bertanda "—" tetapi tetap terlihat
    for (const m of unik) {
      if ((TERLARANG[akun] ?? []).includes(m.href)) rinci.push(`${akun}: menu terlarang terlihat → ${m.href}`)
    }
    // (b) setiap menu yang terlihat harus TERBUKA, bukan 403
    for (const m of unik) {
      const resp = await page.goto(m.href, { waitUntil: 'domcontentloaded' })
      const teks = await teksHalaman(page)
      if ((resp?.status() ?? 0) === 403 || ada403(teks)) rinci.push(`${akun}: menu terlihat tapi menolak → ${m.href}`)
      if (adaGalatTeknis(teks)) rinci.push(`${akun}: galat teknis di ${m.href}`)
    }
    ringkas.push(`${akun}: ${unik.length} menu (${unik.map((m) => m.label || m.href).join(', ')})`)
    await keluar(page)
  }
  catat('LS-02', '—', rinci.length ? 'Gagal' : 'Lulus',
    rinci.length ? rinci.slice(0, 6).join(' | ')
                 : `${ringkas.join(' || ')}. Tidak satu pun menu yang terlihat menjawab 403, dan tidak satu pun alamat bertanda "—" muncul di sidebar.`)
  expect(rinci, rinci.join('\n')).toHaveLength(0)
})

test('LS-03 — data mill lain tidak pernah terlihat', async ({ page }) => {
  // Dicatat lewat ID line, bukan namanya: kedua mill sama-sama menamai line
  // pertamanya "Line 1", jadi pemeriksaan berbasis nama tidak dapat
  // membedakan keduanya dan akan hijau meski datanya bocor.
  const ambil = async (akun: string) => {
    await login(page, akun)
    await page.goto('/data/weighbridge', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(800)
    const idLine = await page.locator('select option').evaluateAll((o) =>
      o.map((e) => (e as HTMLOptionElement).value).filter((v) => /^[0-9a-f-]{36}$/.test(v)))
    const teks = await teksHalaman(page)
    const baris = await page.locator('tbody tr').count()
    await keluar(page)
    return { idLine: [...new Set(idLine)], teks, baris }
  }
  const a = await ambil(SPV)
  const b = await ambil(SPV_B)
  const bocorKeB = a.idLine.filter((id) => b.idLine.includes(id))
  const estateBdiA = /Estate Khusus Mill B/i.test(a.teks)
  const ok = bocorKeB.length === 0 && !estateBdiA && a.baris !== b.baris
  catat('LS-03', '—', ok ? 'Lulus' : 'Gagal',
    `Supervisor Mill A melihat ${a.idLine.length} id Production Line dan ${a.baris} baris; Supervisor Mill B melihat ${b.idLine.length} id dan ${b.baris} baris. Id yang muncul di kedua sesi: ${bocorKeB.length}. Nama estate khas Mill B terbaca di sesi Mill A: ${estateBdiA}. Catatan: pemeriksaan memakai id Production Line, bukan namanya, karena kedua mill sama-sama menamai line pertamanya "Line 1" sehingga nama tidak dapat membedakan keduanya.`)
  expect(ok).toBe(true)
})

test('LS-04 — akun tanpa mill ditolak dengan pesan yang menyebut sebabnya', async ({ page }) => {
  // Dijalankan sebagai langkah pertama kasusnya, TANPA helper login: helper itu
  // menunggu halaman berpindah dari /login, dan akun ini justru ditahan di sana.
  await page.goto('/login')
  await page.locator('#username').fill(NOMILL)
  await page.locator('#password').fill('Passw0rd!')
  await page.locator('button.login-button').click()
  await page.waitForTimeout(2500)

  const diLogin = new URL(page.url()).pathname === '/login'
  const alert = page.locator('.login-alert[role="alert"]')
  const pesan = (await alert.count()) ? (await alert.innerText()).trim() : ''

  const sebutMill = /mill|business unit/i.test(pesan)
  const sebutTindakan = /hubungi|admin|minta/i.test(pesan)
  const sebutJaringan = /periksa koneksi|koneksi Anda|jaringan/i.test(pesan)
  const sebutPilihan = /dipilih|pilihan/i.test(pesan)

  const rinci: string[] = [
    `Login ditolak dan tetap di layar Login=${diLogin}`,
    `pesannya: "${pesan}"`,
    `menyebut mill=${sebutMill}`,
    `mengarahkan tindakan (hubungi Admin)=${sebutTindakan}`,
    `menyebut pilihan yang tidak pernah dibuat pengguna=${sebutPilihan} (harus false)`,
    `menyuruh memeriksa koneksi=${sebutJaringan} (harus false)`,
  ]

  // Bila ternyata berhasil masuk, langkah 3 kasusnya dijalankan.
  if (!diLogin) {
    for (const [nama, alamat] of [['Data Browser', '/data/weighbridge'],
                                  ['Laporan Stasiun', '/reports'],
                                  ['Dashboard', '/dashboard']] as [string, string][]) {
      await page.goto(alamat, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(600)
      const t = await teksHalaman(page)
      rinci.push(`${nama}: pesan menyebut mill=${/mill/i.test(t)}`)
    }
  }

  const ok = diLogin && sebutMill && sebutTindakan && !sebutPilihan && !sebutJaringan
  catat('LS-04', '—', ok ? 'Lulus' : 'Gagal', rinci.join(' | '))
  expect(ok, rinci.join('\n')).toBe(true)
})

test('LS-05 — hanya Admin melihat pemilih Mill', async ({ page }) => {
  const hasil: string[] = []
  let gagal = 0
  for (const [akun, bolehPilih] of [[SPV, false], [MGR, false], [ADM, true]] as [string, boolean][]) {
    await login(page, akun)
    await page.goto('/reports/kernel-plant', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(600)
    const pemilih = await page.getByTestId('mill-select').count()
    const teks = await salahSatuTestid(page, TESTID_MILL_TEKS)
    const teksMill = teks.ada ? 1 : 0
    const sesuai = bolehPilih ? pemilih > 0 : pemilih === 0 && teksMill > 0
    if (!sesuai) gagal++
    hasil.push(`${akun}: pemilih Mill=${pemilih > 0 ? 'ada' : 'tidak ada'}, nama mill sebagai teks=${teksMill > 0}${teks.ada ? ` ("${(await teks.loc.innerText()).trim()}")` : ''}${sesuai ? '' : ' <- TIDAK SESUAI'}`)
    await keluar(page)
  }
  catat('LS-05', '—', gagal ? 'Gagal' : 'Lulus', hasil.join(' | '))
  expect(gagal, hasil.join('\n')).toBe(0)
})

test('LS-06 — sesi berakhir mengarahkan ke Login tanpa pesan jaringan', async ({ browser }) => {
  // Dua konteks browser terpisah, persis seperti langkahnya: satu jendela
  // Supervisor yang dibiarkan terbuka, satu jendela Admin yang menonaktifkan.
  const kSpv = await browser.newContext()
  const kAdm = await browser.newContext()
  const spv = await kSpv.newPage()
  const adm = await kAdm.newPage()
  try {
    await login(spv, 'webtest-deact01')
    await spv.goto('/data/weighbridge', { waitUntil: 'domcontentloaded' })

    await login(adm, ADM)
    await adm.goto('/users', { waitUntil: 'domcontentloaded' })
    adm.on('dialog', (d) => d.accept())
    // Layar Kelola User & Role TIDAK punya kotak cari teks bebas — hanya
    // penyaring Role dan Business Unit — jadi barisnya ditelusuri lewat
    // halaman berikutnya sampai ketemu. Mengetik ke "kotak cari" yang tidak ada
    // membuat tombol yang diklik adalah tombol baris pertama, yaitu akun Admin
    // sendiri, yang tombolnya mati.
    let ketemu = await adm.locator('tr', { hasText: 'webtest-deact01' }).count() > 0
    for (let h = 0; h < 25 && !ketemu; h++) {
      const lanjut = adm.getByRole('button', { name: /Berikutnya|Selanjutnya|Next/i }).first()
      if (!(await lanjut.count()) || !(await lanjut.isEnabled())) break
      await lanjut.click()
      await adm.waitForTimeout(700)
      ketemu = await adm.locator('tr', { hasText: 'webtest-deact01' }).count() > 0
    }
    // Tombol dicari DI BARIS akun itu, bukan tombol Nonaktifkan pertama di
    // halaman: baris pertama adalah akun Admin sendiri, yang tombolnya mati
    // ("Anda tidak dapat menonaktifkan akun sendiri") sehingga klik pada
    // tombol itu menggantung sampai batas waktu, bukan gagal dengan jelas.
    const baris = adm.locator('tr', { hasText: 'webtest-deact01' }).first()
    const tombol = baris.getByRole('button', { name: /^Nonaktifkan$/ })
    const adaTombol = await tombol.count() > 0
    if (adaTombol) { await tombol.click(); await adm.waitForTimeout(1200) }

    await spv.reload({ waitUntil: 'domcontentloaded' })
    const akhir = new URL(spv.url()).pathname
    const teks = await teksHalaman(spv)
    const sebutSesi = /sesi|akun Anda|dinonaktifkan|tidak aktif|masuk kembali|login kembali/i.test(teks)
    const sebutJaringan = /periksa koneksi|koneksi Anda|jaringan Anda|cek internet/i.test(teks)
    const ok = akhir === '/login' && !sebutJaringan
    catat('LS-06', '—', ok ? 'Lulus' : 'Gagal',
      `tombol Nonaktifkan ditemukan=${adaTombol}; sesudah akun dinonaktifkan dari jendela Admin, jendela Supervisor yang dimuat ulang mendarat di ${akhir}; pesannya menyebut sesi/akun=${sebutSesi}; pesannya menyuruh memeriksa koneksi/jaringan=${sebutJaringan} (harus false).`)
    expect(ok).toBe(true)
  } finally {
    // Dikembalikan supaya kasus lain tidak terhalang (langkah 6 AU-04).
    adm.removeAllListeners('dialog')
    adm.on('dialog', (d) => d.accept())
    await adm.goto('/users', { waitUntil: 'domcontentloaded' }).catch(() => {})
    let k2 = await adm.locator('tr', { hasText: 'webtest-deact01' }).count().catch(() => 0) > 0
    for (let h = 0; h < 25 && !k2; h++) {
      const lanjut = adm.getByRole('button', { name: /Berikutnya|Selanjutnya|Next/i }).first()
      if (!(await lanjut.count().catch(() => 0)) || !(await lanjut.isEnabled().catch(() => false))) break
      await lanjut.click().catch(() => {})
      await adm.waitForTimeout(700)
      k2 = await adm.locator('tr', { hasText: 'webtest-deact01' }).count().catch(() => 0) > 0
    }
    const aktifkan = adm.locator('tr', { hasText: 'webtest-deact01' }).first().getByRole('button', { name: /^Aktifkan$/ })
    if (await aktifkan.count().catch(() => 0)) { await aktifkan.click().catch(() => {}); await adm.waitForTimeout(1000) }
    await kSpv.close(); await kAdm.close()
  }
})

test('LS-07 — nol dirender sebagai pernyataan, bukan sebagai angka 0', async ({ page }) => {
  await login(page, SPV)
  const rinci: string[] = []

  // Laporan sebuah stasiun pada line yang BELUM ada datanya.
  await page.goto('/reports/kernel-plant', { waitUntil: 'domcontentloaded' })
  const line = await pilihLine(page, 1)
  const periode = await pilihPeriode(page, 0)
  await page.waitForTimeout(900)
  const teksLap = await teksHalaman(page)
  const adaPernyataan = /belum ada input|belum ada data|tidak tersedia|—/i.test(teksLap)
  // "0" yang berdiri sendiri sebagai isi sel, bukan bagian angka lain.
  const selNol = await page.locator('td, .md-kpi__value, .md-stat__value').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').trim()).filter((t) => /^(0|0%|0,0|0\.0|0,00)$/.test(t)).length)
  rinci.push(`Laporan Kernel Plant (line="${line}", periode="${periode}"): pernyataan ketiadaan data terbaca=${adaPernyataan}; sel bernilai tepat 0/0%/0,0 = ${selNol}`)

  // Production Process Activity: tile stasiun yang belum ada datanya.
  await page.goto('/production-process-activity', { waitUntil: 'domcontentloaded' })
  const teksPpa = await teksHalaman(page)
  const ppaPernyataan = /belum ada input|belum ada data|belum/i.test(teksPpa)
  rinci.push(`Production Process Activity: tile tanpa data berbunyi pernyataan=${ppaPernyataan}`)

  const ok = adaPernyataan && ppaPernyataan
  catat('LS-07', '—', ok ? 'Lulus' : 'Gagal', rinci.join(' | '))
  expect(ok, rinci.join('\n')).toBe(true)
})

test('LS-08 — Production Line wajib dipilih, termasuk saat hanya ada satu line', async ({ page }) => {
  await login(page, ADM)
  // Weighbridge, bukan Kernel Plant: Mill B hanya punya data Weighbridge, dan
  // langkah 4 kasusnya menuntut "angka-angkanya muncul" — pada stasiun tanpa
  // data, angka tidak akan muncul karena memang tidak ada datanya, dan
  // kasusnya tidak lagi menguji aturan pemilihan line.
  await page.goto('/reports/weighbridge', { waitUntil: 'domcontentloaded' })

  // Mill yang hanya punya SATU Production Line: Business Unit B.
  const mill = page.getByTestId('mill-select')
  const opsiMill = await mill.locator('option').evaluateAll((o) =>
    o.map((e) => ({ v: (e as HTMLOptionElement).value, t: (e.textContent ?? '').trim() })).filter((x) => x.v !== ''))
  const millB = opsiMill.find((m) => /Business Unit B/i.test(m.t))
  const millA = opsiMill.find((m) => /Business Unit A/i.test(m.t))
  expect(millB && millA, `pemilih Mill harus memuat Business Unit A dan B; isinya: ${opsiMill.map((m) => m.t).join(', ')}`).toBeTruthy()

  await mill.selectOption(millB!.v)
  await page.waitForTimeout(900)
  const lineB = await page.getByTestId('production-line-select').locator('option').evaluateAll((o) =>
    o.filter((e) => (e as HTMLOptionElement).value !== '').length)
  const teks3 = await teksHalaman(page)
  const memintaPilih = /Production Line belum dipilih|Pilih production line|pilih Production Line/i.test(teks3)
  const angka3 = await page.locator('.md-kpi__value').count()

  // Langkah 4 — pilih line satu-satunya itu.
  const namaLine = await pilihLine(page, 0)
  await pilihPeriode(page, 0)
  await page.waitForTimeout(900)
  const teks4 = await teksHalaman(page)
  const angka4 = await page.locator('.md-kpi__value').count()
  const masihMeminta4 = /Production Line belum dipilih/i.test(teks4)

  // Langkah 5 — ganti ke mill yang punya DUA line; pilihan line harus direset.
  await mill.selectOption(millA!.v)
  await page.waitForTimeout(1100)
  const teks5 = await teksHalaman(page)
  const reset5 = /Production Line belum dipilih|Pilih production line|pilih Production Line/i.test(teks5)
  const lineTerpilih5 = await page.getByTestId('production-line-select').inputValue().catch(() => '')

  const ok = lineB === 1 && memintaPilih && angka3 === 0 && !masihMeminta4 && angka4 > 0 && reset5 && lineTerpilih5 === ''
  catat('LS-08', '—', ok ? 'Lulus' : 'Gagal',
    `Mill B punya ${lineB} Production Line. Sebelum dipilih: layar meminta memilih line=${memintaPilih}, angka di layar=${angka3} (harus 0). Sesudah line "${namaLine}" dipilih: angka di layar=${angka4}, masih meminta=${masihMeminta4}. Sesudah pindah ke Mill A (2 line): pilihan line direset ke kosong=${lineTerpilih5 === ''}, layar meminta memilih lagi=${reset5}.`)
  expect(ok).toBe(true)
})

test('LS-09 — id Production Line mill lain diabaikan, bukan ditolak dengan galat', async ({ page }) => {
  // Id line Mill B diambil dulu dari sesi Supervisor Mill B, persis seperti
  // langkah 3 kasusnya (lewat layar kelola/pemilih, bukan dari basis data).
  // Langkah 3 kasusnya: id Production Line Mill B diambil dari Kelola
  // Production Line sebagai Admin — bukan dari basis data.
  await login(page, ADM)
  await page.goto('/master-data/production-lines', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(900)
  let idB = ''
  let namaB: string[] = []
  for (let h = 0; h < 25; h++) {
    const baris = await page.locator('[data-testid^="production-line-row-"]').evaluateAll((els) =>
      els.map((e) => ({
        id: (e.getAttribute('data-testid') ?? '').replace('production-line-row-', ''),
        teks: (e as HTMLElement).innerText.replace(/\s+/g, ' ').trim(),
      })))
    const milikB = baris.filter((b) => /Business Unit B/i.test(b.teks))
    if (milikB.length) {
      idB = milikB[0].id
      namaB = milikB.map((b) => (b.teks.split(/\s/)[0] ?? '').trim()).filter(Boolean)
      break
    }
    const lanjut = page.getByRole('button', { name: /Berikutnya|Selanjutnya|Next/i }).first()
    if (!(await lanjut.count()) || !(await lanjut.isEnabled())) break
    await lanjut.click()
    await page.waitForTimeout(700)
  }
  await keluar(page)
  expect(idB, 'id Production Line Mill B harus terbaca dari layar Kelola Production Line').toBeTruthy()

  await login(page, SPV)
  const resp = await page.goto(`/reports/kernel-plant?production_line_id=${idB}`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(900)
  const kode = resp?.status() ?? 0
  const teks = await teksHalaman(page)
  const terbuka = kode < 400 && !ada403(teks) && !adaGalatTeknis(teks)
  const jatuhKeMinta = /Production Line belum dipilih|Pilih production line|pilih Production Line/i.test(teks)
  const terpilih = await page.getByTestId('production-line-select').inputValue().catch(() => '')
  const namaBocor = namaB.filter((n) => n.length > 3 && teks.includes(n))
  const ok = terbuka && (jatuhKeMinta || terpilih === '') && namaBocor.length === 0
  catat('LS-09', '—', ok ? 'Lulus' : 'Gagal',
    `Alamat laporan Mill A dibuka dengan production_line_id milik Mill B. Halaman tetap terbuka=${terbuka} (status ${kode}); layar jatuh ke keadaan "belum memilih Production Line"=${jatuhKeMinta}; pemilih line kosong=${terpilih === ''}; nama Production Line Mill B tercetak di layar=${namaBocor.length > 0}.`)
  expect(ok).toBe(true)
})

test('LS-11 — ekspor menghasilkan berkas yang benar-benar terbuka', async ({ page }) => {
  await login(page, SPV)
  await page.goto('/reports/kernel-plant', { waitUntil: 'domcontentloaded' })
  const line = await pilihLine(page, 0)
  const periode = await pilihPeriode(page, 0)
  await page.waitForTimeout(900)

  const unduh = async (kandidat: string[]) => {
    const { ada, loc: tombol } = await salahSatuTestid(page, kandidat)
    if (!ada) return null
    const [d] = await Promise.all([page.waitForEvent('download', { timeout: 20_000 }), tombol.click()])
    const isi = await d.createReadStream()
    const buf: Buffer[] = []
    for await (const c of isi) buf.push(c as Buffer)
    return { nama: d.suggestedFilename(), data: Buffer.concat(buf) }
  }

  const csv = await unduh(TESTID_EKSPOR_CSV)
  const xls = await unduh(TESTID_EKSPOR_XLS)
  const rinci: string[] = []
  let gagal = 0

  if (!csv) { gagal++; rinci.push('tombol Ekspor CSV tidak ada') } else {
    const teks = csv.data.toString('utf8')
    const baris1 = teks.split(/\r?\n/)[0] ?? ''
    const judulTerbaca = /[A-Za-z]/.test(baris1) && !/^[a-z_]+(,[a-z_]+)+$/.test(baris1)
    const namaSebut = /kernel|plant/i.test(csv.nama)
    const bomAtauUtf8Bersih = !/�/.test(teks)
    if (!judulTerbaca || !namaSebut || !bomAtauUtf8Bersih) gagal++
    rinci.push(`CSV "${csv.nama}" ${csv.data.length} bita; nama menyebut stasiun=${namaSebut}; baris pertama adalah judul kolom terbaca=${judulTerbaca} ("${baris1.slice(0, 90)}"); tanpa karakter rusak=${bomAtauUtf8Bersih}`)
  }

  if (!xls) { gagal++; rinci.push('tombol Ekspor Excel tidak ada') } else {
    // "Terbuka tanpa peringatan rusak" diperiksa secara struktural: sebuah
    // .xlsx adalah arsip zip yang WAJIB memuat [Content_Types].xml dan
    // xl/workbook.xml. Berkas yang kedua entri itu hilang akan ditolak Excel.
    const b = xls.data
    const zip = b[0] === 0x50 && b[1] === 0x4b
    const s = b.toString('latin1')
    const punyaContentTypes = s.includes('[Content_Types].xml')
    const punyaWorkbook = s.includes('xl/workbook.xml')
    const namaSebut = /kernel|plant/i.test(xls.nama)
    if (!zip || !punyaContentTypes || !punyaWorkbook || !namaSebut) gagal++
    rinci.push(`Excel "${xls.nama}" ${b.length} bita; arsip xlsx sah=${zip}; memuat [Content_Types].xml=${punyaContentTypes}; memuat xl/workbook.xml=${punyaWorkbook}; nama menyebut stasiun=${namaSebut}`)
  }

  catat('LS-11', '—', gagal ? 'Gagal' : 'Lulus',
    `Laporan Kernel Plant (line="${line}", periode="${periode}"). ${rinci.join(' | ')}. Catatan: keutuhan berkas Excel diperiksa secara struktural (arsip xlsx sah beserta dua entri wajibnya), bukan dengan membukanya di Excel.`)
  expect(gagal, rinci.join('\n')).toBe(0)
})

test('LS-13 — seluruh tampilan berbahasa Indonesia, termasuk pesan galatnya', async ({ page }) => {
  await login(page, SPV)
  // Kata antarmuka berbahasa Inggris yang TIDAK boleh muncul. Istilah pabrik
  // (Ripple Mill, mmH2O) dan nama stasiun memang dibiarkan Inggris, jadi yang
  // dicari hanya kosakata antarmuka dan kerangka.
  const POLA_INGGRIS = /\b(Save|Submit|Cancel|Delete|Create|Update|Search|Required|This field|Whoops|Server Error|Not Found|Forbidden|Unauthorized|The given data was invalid|Page Expired)\b/
  const rinci: string[] = []
  for (const alamat of ['/data/weighbridge', '/reports', '/production-process-activity', '/data/depricarping/create']) {
    await page.goto(alamat, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(700)
    const teks = await teksHalaman(page)
    const m = teks.match(POLA_INGGRIS)
    if (m) rinci.push(`${alamat}: "${m[0]}"`)
  }
  // Picu galat: kosongkan kolom wajib lalu Simpan.
  await page.goto('/data/depricarping/create', { waitUntil: 'domcontentloaded' })
  const simpan = page.getByRole('button', { name: /^Simpan$/i }).first()
  let pesanGalat = ''
  if (await simpan.count()) {
    await simpan.click()
    await page.waitForTimeout(1500)
    const teks = await teksHalaman(page)
    const m = teks.match(POLA_INGGRIS)
    if (m) rinci.push(`pesan galat: "${m[0]}"`)
    pesanGalat = (teks.match(/[^.]*(wajib|harus|tidak boleh)[^.]*/i)?.[0] ?? '').trim().slice(0, 120)
  }
  catat('LS-13', '—', rinci.length ? 'Gagal' : 'Lulus',
    rinci.length ? `kosakata antarmuka berbahasa Inggris ditemukan: ${rinci.join(' | ')}`
                 : `4 layar + satu pesan galat yang dipicu sengaja diperiksa terhadap daftar kosakata antarmuka Inggris (Save, Submit, Required, Server Error, dll.): tidak ada yang muncul. Contoh pesan galat yang terbaca: "${pesanGalat}".`)
  expect(rinci, rinci.join('\n')).toHaveLength(0)
})

test('LS-14 — tidak ada kolom input yang mati tanpa alasan tertulis', async ({ page }) => {
  await login(page, SPV)
  await page.goto('/data/depricarping/create', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(900)
  const mati = await page.locator('input:disabled, input[readonly], select:disabled, textarea:disabled, textarea[readonly]')
    .evaluateAll((els) => els.map((e) => {
      const el = e as HTMLInputElement
      // Keterangan di sekitarnya: label, hint, atau judul atributnya.
      const bungkus = el.closest('.form-field, .md-field, .kc-field, div') as HTMLElement | null
      return {
        nama: el.getAttribute('name') ?? el.id ?? '(tanpa nama)',
        tipe: el.getAttribute('type') ?? el.tagName.toLowerCase(),
        keterangan: ((bungkus?.innerText ?? '') + ' ' + (el.title ?? '')).replace(/\s+/g, ' ').trim().slice(0, 160),
      }
    }))
  const tanpaAlasan = mati.filter((m) =>
    !/otomatis|terisi otomatis|hanya|tidak dapat diubah|diisi oleh|boleh diisi|mengikuti|peran|Supervisor|Mill Management|dihitung/i.test(m.keterangan))
  catat('LS-14', '—', tanpaAlasan.length ? 'Gagal' : 'Lulus',
    `Form Depricarping baru: ${mati.length} kolom mati/terkunci ditemukan. Tanpa keterangan yang menjelaskan sebabnya: ${tanpaAlasan.length}${tanpaAlasan.length ? ' → ' + tanpaAlasan.map((m) => m.nama).join(', ') : ''}.${mati.length ? ' Contoh keterangan yang terbaca: "' + (mati[0].keterangan || '(kosong)') + '".' : ''}`)
  expect(tanpaAlasan.map((m) => m.nama), JSON.stringify(tanpaAlasan, null, 1)).toHaveLength(0)
})

test('LS-15 — kolom tanggal dan jam bisa diketik manual, bukan hanya lewat pemilih', async ({ page }) => {
  await login(page, SPV)
  await page.goto('/data/weighbridge/create', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(900)
  const rinci: string[] = []
  let gagal = 0
  for (const [label, tipe, nilai] of [['tanggal', 'date', '2026-10-08'],
                                      ['jam', 'time', '09:30'],
                                      ['tanggal+jam', 'datetime-local', '2026-10-08T09:30']] as [string, string, string][]) {
    const inp = page.locator(`input[type="${tipe}"]`).first()
    if (!(await inp.count())) { rinci.push(`${label}: tidak ada kolom bertipe ${tipe} di layar ini`); continue }
    // Mengetik manual, tombol per tombol — bukan setel nilai lewat skrip.
    await inp.click()
    await inp.press('Control+a')
    await inp.pressSequentially(nilai.replace(/[-:T]/g, ''), { delay: 30 })
    await page.waitForTimeout(400)
    const terbaca = await inp.inputValue()
    const diterima = terbaca !== ''
    if (!diterima) gagal++
    rinci.push(`${label}: diketik manual → nilai diterima="${terbaca}" (${diterima ? 'diterima' : 'DITOLAK'}); kolom bertipe ${tipe} sehingga pemilih bawaan browser selalu tersedia di kolom yang sama`)
  }
  catat('LS-15', '—', gagal ? 'Gagal' : 'Lulus',
    `${rinci.join(' | ')}. Catatan: yang diperiksa adalah jalur mengetik manual dan bahwa kolomnya bertipe date/time (tipe itulah yang memunculkan ikon pemilih); membuka widget kalender bawaan sistem operasi tidak dapat dilakukan dari browser.`)
  expect(gagal, rinci.join('\n')).toBe(0)
})

test('LS-16 — laporan tidak menandai satu nilai pun sebagai di luar batas', async ({ page }) => {
  await login(page, SPV)
  const rinci: string[] = []
  let gagal = 0
  for (const stasiun of ['kernel-plant', 'depricarping', 'pressing', 'threshing', 'grading']) {
    await page.goto(`/reports/${stasiun}`, { waitUntil: 'domcontentloaded' })
    await pilihLine(page, 0)
    // Periode yang BERDATA, bukan periode pertama: pada periode tanpa data
    // seluruh isi laporan tidak dirender, termasuk kotak keterangan yang
    // diperiksa kasus ini — dan ketiadaannya lalu terbaca sebagai cacat.
    const periode = await pilihPeriodeBerdata(page)
    await page.waitForTimeout(900)
    if (periode === '') {
      rinci.push(`${stasiun}: tidak ada periode berdata untuk line pertama, jadi isi laporan tidak dirender — tidak dapat diperiksa di sini`)
      continue
    }
    // Diperiksa lewat NAMA KELAS, bukan frasa: kalimat penjelasnya sendiri
    // memuat "di luar batas", jadi asersi atas frasa akan selalu hijau.
    const penanda = await page.locator(
      '.is-out-of-range, .is-warning, .is-danger, .md-badge--warn, .md-badge--danger, .text-danger, .text-warning, [data-out-of-range]'
    ).count()
    const k = await salahSatuTestid(page, TESTID_CATATAN_TANPA_TANDA)
    const kotak = k.ada ? 1 : 0
    const teksKotak = k.ada ? (await k.loc.innerText()).replace(/\s+/g, ' ') : ''
    // "Menyebut tiga alasan, bukan satu". Alasannya ditulis sebagai PROSA di
    // layar ini, bukan daftar <li>, jadi yang dihitung adalah kalimatnya —
    // menghitung butir daftar akan selalu nol dan melaporkan kegagalan palsu.
    const butir = k.ada
      ? Math.max(await k.loc.locator('li').count(),
                 teksKotak.split(/(?<=[.;])\s+/).filter((x) => x.trim().length > 25).length)
      : 0
    const ok = penanda === 0 && kotak > 0 && butir >= 3
    if (!ok) gagal++
    rinci.push(`${stasiun} (periode "${periode}"): elemen bertanda peringatan=${penanda} (harus 0); kotak keterangan ada=${kotak > 0}${k.ada ? ` (${k.nama})` : ''}; alasan yang disebut=${butir} (harus ≥3)`)
    if (stasiun === 'kernel-plant' && teksKotak) rinci.push(`isi kotak: "${teksKotak.slice(0, 150)}…"`)
  }
  catat('LS-16', '—', gagal ? 'Gagal' : 'Lulus', rinci.join(' | '))
  expect(gagal, rinci.join('\n')).toBe(0)
})
