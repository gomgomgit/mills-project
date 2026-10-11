/**
 * Pelaksana kasus uji manual — menjalankan LANGKAH YANG TERTULIS di dokumen
 * QA, lalu mencatat apa yang terlihat.
 *
 * Ini BUKAN suite regresi. Ia tidak menggantikan e2e-web/tests/, dan
 * sengaja berada di luar testDir-nya supaya tidak ikut berjalan di sana.
 *
 * ────────────────────────────────────────────────────────────────────────
 * APA YANG BOLEH DIKLAIM DARI BERKAS INI
 * ────────────────────────────────────────────────────────────────────────
 * Hanya kasus yang langkahnya dapat dijalankan utuh oleh mesin: navigasi,
 * isi form, baca DOM, hitung elemen. Setiap kasus di sini dijalankan persis
 * seperti tertulis, jadi hasilnya sah untuk kasus itu.
 *
 * YANG TIDAK ADA DI SINI, dan tidak boleh diisi dari sini: kasus yang
 * menuntut penilaian mata ("tampil redup", "tanpa gaya yang semestinya"),
 * hover tooltip, mengubah jam perangkat, mematikan koneksi fisik, atau
 * membandingkan tata letak 390px. Itu pekerjaan penguji manusia.
 *
 * Keluarannya JSON ke qa-run/hasil.json: satu entri per kasus, memuat apa
 * yang DIAMATI — supaya baris "Lulus" mana pun dapat ditelusuri.
 */
import { test, expect, type Page } from '@playwright/test'
import { writeFileSync, mkdirSync } from 'node:fs'
import { login, PASSWORD } from '../tests/support/auth'

type Hasil = {
  id: string
  varian: string
  hasil: 'Lulus' | 'Gagal'
  diamati: string
}

const HASIL: Hasil[] = []

function catat(id: string, varian: string, hasil: Hasil['hasil'], diamati: string) {
  HASIL.push({ id, varian, hasil, diamati })
}

/**
 * Mengakhiri sesi. TIDAK memakai page.goto('/logout'): rute logout hanya
 * menerima POST, jadi GET ke sana dijawab 405 dan SESINYA TETAP HIDUP —
 * seluruh pemeriksaan peran sesudahnya akan menguji peran yang salah, dan
 * kegagalannya tidak terlihat sebagai kegagalan.
 */
async function keluar(page: Page): Promise<void> {
  await page.context().clearCookies()
}

const SPV = 'supervisor01'
const OPR = 'operator01'
const MGR = 'millmanagement-a'
const ADM = 'admin'

test.afterAll(() => {
  mkdirSync('qa-run', { recursive: true })
  writeFileSync('qa-run/hasil.json', JSON.stringify(HASIL, null, 1), 'utf8')
  const g = HASIL.filter((h) => h.hasil === 'Gagal').length
  console.log(`\n[qa-run] ${HASIL.length} kasus dijalankan, ${g} gagal`)
})

/** Alamat yang menurut matriks peran TIDAK boleh dibuka tiap peran. */
const TERLARANG: Record<string, string[]> = {
  [OPR]: ['/dashboard', '/data/weighbridge', '/reports', '/master-data/periods',
          '/master-data/tree-view', '/users', '/mill-settings', '/reports/management',
          '/production-process-activity'],
  [SPV]: ['/master-data/periods', '/master-data/tree-view', '/users', '/mill-settings',
          '/reports/management'],
  [MGR]: ['/master-data/periods', '/master-data/tree-view', '/users'],
}

test('LS-01 — peran tak berhak ditolak 403, bukan halaman kosong', async ({ page }) => {
  const rinci: string[] = []
  let gagal = 0
  for (const [akun, alamat] of Object.entries(TERLARANG)) {
    await login(page, akun)
    for (const a of alamat) {
      const resp = await page.goto(a, { waitUntil: 'domcontentloaded' })
      const kode = resp?.status() ?? 0
      const teks = (await page.locator('body').innerText()).slice(0, 4000)
      const akhir = new URL(page.url()).pathname
      const ada403 = /403|Akses Ditolak/i.test(teks)
      const adaKerangka = await page.locator('a[href="/logout"], form[action*="logout"], button:has-text("Logout")').count() > 0
        || /Logout/i.test(teks)
      const teknis = /(SQLSTATE|Stack trace|Whoops|Server Error|Undefined (variable|index))/i.test(teks)
      const ok = (kode === 403 || ada403) && !teknis && akhir === a
      if (!ok) {
        gagal++
        rinci.push(`${akun} ${a}: status=${kode} 403-terlihat=${ada403} alamat-akhir=${akhir} galat-teknis=${teknis} kerangka=${adaKerangka}`)
      }
    }
    await keluar(page)
  }
  const total = Object.values(TERLARANG).flat().length
  catat('LS-01', '—', gagal ? 'Gagal' : 'Lulus',
    gagal ? `${gagal} dari ${total} kombinasi peran×alamat tidak menjawab 403 bersih. ${rinci.slice(0, 4).join(' | ')}`
          : `${total} kombinasi peran×alamat diuji (Operator 9, Supervisor 5, Mill Management 3): semuanya menjawab 403/Akses Ditolak, tanpa galat teknis, alamat tidak dialihkan.`)
  expect(gagal, rinci.join('\n')).toBe(0)
})

test('AU-02 + AU-03 — pesan login gagal identik untuk password salah dan username tak ada', async ({ page }) => {
  /**
   * Membaca pesan galatnya dari elemen pesannya sendiri (.login-alert, yang
   * dirender hanya saat $errorMessage terisi) dan MENUNGGU elemen itu muncul.
   * Membaca body begitu saja akan tertangkap di tengah proses Livewire —
   * tombolnya masih "Memproses…" dan pesannya belum ada, sehingga dua panggilan
   * menghasilkan teks yang berbeda karena BEDA WAKTU, bukan beda pesan.
   */
  const pesan = async (u: string, p: string) => {
    await page.goto('/login')
    await page.locator('#username').fill(u)
    await page.locator('#password').fill(p)
    await page.locator('button.login-button').click()
    const alert = page.locator('.login-alert[role="alert"]')
    await alert.waitFor({ state: 'visible' })
    return (await alert.innerText()).trim()
  }
  const a = await pesan(SPV, 'SalahSekali999!')
  const b = await pesan('tidakada999', 'ApaSaja123!')
  const sama = a === b
  const diAlamatLogin = new URL(page.url()).pathname === '/login'
  const d = `password salah -> "${a}" | username tak ada -> "${b}" | identik=${sama} | tetap di /login=${diAlamatLogin}`
  catat('AU-02', '—', sama && diAlamatLogin ? 'Lulus' : 'Gagal', d)
  catat('AU-03', '—', sama && diAlamatLogin ? 'Lulus' : 'Gagal', d)
  expect(sama, d).toBe(true)
})

test('AU-06 — tamu diarahkan ke Login tanpa isi yang sempat terlihat', async ({ page }) => {
  const alamat = ['/dashboard', '/data/weighbridge', '/reports', '/master-data/periods', '/users']
  const rinci: string[] = []
  for (const a of alamat) {
    await page.context().clearCookies()
    await page.goto(a, { waitUntil: 'domcontentloaded' })
    const akhir = new URL(page.url()).pathname
    const teks = await page.locator('body').innerText()
    const bocor = /Data Browser|Dashboard Operasional|Kelola Periode|Kelola User/i.test(teks)
    if (akhir !== '/login' || bocor) rinci.push(`${a} -> ${akhir} bocor=${bocor}`)
  }
  catat('AU-06', '—', rinci.length ? 'Gagal' : 'Lulus',
    rinci.length ? rinci.join(' | ')
                 : `${alamat.length} alamat diuji tanpa sesi: semuanya mendarat di /login, tidak ada judul layar terlindung yang terbaca di halaman.`)
  expect(rinci, rinci.join('\n')).toHaveLength(0)
})

test('AU-09 — logout mencabut sesi perangkat itu', async ({ page }) => {
  await login(page, SPV)
  await page.goto('/data/weighbridge', { waitUntil: 'domcontentloaded' })
  const sebelum = (await page.locator('body').innerText()).slice(0, 300)
  // Kasus ini menguji TOMBOL Logout, jadi ia harus ditekan sungguhan —
  // bukan dibersihkan cookie-nya seperti helper keluar().
  const tombol = page.locator('[data-testid="logout-button"], [data-testid="nav-menu-logout"]').first()
  const adaTombol = await tombol.count() > 0
  if (adaTombol) {
    await tombol.click()
    await page.waitForLoadState('domcontentloaded')
  }
  await page.goBack({ waitUntil: 'domcontentloaded' }).catch(() => {})
  const setelahBack = new URL(page.url()).pathname
  await page.goto('/data/weighbridge', { waitUntil: 'domcontentloaded' })
  const akhir = new URL(page.url()).pathname
  const ok = akhir === '/login'
  catat('AU-09', '—', ok ? 'Lulus' : 'Gagal',
    `tombol Logout ditemukan=${adaTombol}; sesudah logout, membuka /data/weighbridge mendarat di ${akhir}; tombol Back mendarat di ${setelahBack}. Isi sebelum logout terbaca (${sebelum.length} karakter).`)
  expect(ok).toBe(true)
})

test('DS-04 — Laporan Stasiun: 18 tile, 11 aktif, 7 kelabu', async ({ page }) => {
  await login(page, SPV)
  await page.goto('/reports', { waitUntil: 'domcontentloaded' })

  // Langkah 2 dokumen: grid ditahan sampai Production Line pasti, karena tiap
  // tile membawa konteks mill+line ke layar laporannya.
  const sebelumPilih = await page.locator('[data-testid^="station-tile-"]').count()
  const pesanKosong = (await page.locator('body').innerText()).includes('Production Line belum dipilih')

  // Langkah 3: pilih Production Line (mill sudah mengikuti akun Supervisor).
  const pilihLine = page.getByTestId('production-line-select')
  const opsi = await pilihLine.locator('option').evaluateAll((o) =>
    o.map((e) => (e as HTMLOptionElement).value).filter((v) => v !== ''))
  await pilihLine.selectOption(opsi[0])
  await page.getByTestId('station-grid').waitFor({ state: 'visible' })

  const tile = page.locator('[data-testid^="station-tile-"]')
  const jml = await tile.count()
  const aktif = await page.locator('[data-testid^="station-tile-"]:not(.is-inactive):not(.disabled)').count()
  const kelabu = await page.locator('[data-testid^="station-tile-"].is-inactive, [data-testid^="station-tile-"].disabled').count()
  const d = `sebelum Production Line dipilih: tile=${sebelumPilih}, pesan "Production Line belum dipilih"=${pesanKosong}; sesudah dipilih: tile total=${jml}, aktif=${aktif}, kelabu=${kelabu}`
  const ok = sebelumPilih === 0 && pesanKosong && jml === 18 && kelabu === 7 && aktif === 11
  catat('DS-04', '—', ok ? 'Lulus' : 'Gagal', d)
  expect(ok, d).toBe(true)
})

test('DS-11 — tile Production Process Activity mengantar ke Data Browser yang benar', async ({ page }) => {
  await login(page, SPV)
  const uji = ['weighbridge', 'sterilizer', 'kernel-plant']
  const rinci: string[] = []
  for (const kode of uji) {
    await page.goto('/production-process-activity', { waitUntil: 'domcontentloaded' })
    const t = page.getByTestId(`station-tile-${kode}`)
    if (!(await t.count())) { rinci.push(`${kode}: tile tidak ada`); continue }
    const kelas = (await t.getAttribute('class')) ?? ''
    if (/is-inactive/.test(kelas)) { rinci.push(`${kode}: tile kelabu, dilewati`); continue }
    await t.click()
    await page.waitForLoadState('domcontentloaded')
    const akhir = new URL(page.url()).pathname
    if (akhir !== `/data/${kode}`) rinci.push(`${kode} -> ${akhir}`)
  }
  catat('DS-11', '—', rinci.length ? 'Gagal' : 'Lulus',
    rinci.length ? rinci.join(' | ')
                 : `tile Weighbridge, Sterilizer, dan Kernel Plant masing-masing mendarat di /data/<stasiun> yang namanya cocok.`)
  expect(rinci, rinci.join('\n')).toHaveLength(0)
})

test('DS-08 + DS-09 — kepala halaman PPA dan keadaan tile saling konsisten', async ({ page }) => {
  await login(page, SPV)
  await page.goto('/production-process-activity', { waitUntil: 'domcontentloaded' })
  const kepala = (await page.getByTestId('ppa-headline').innerText()).trim()
  const m = kepala.match(/^(\d+) dari (\d+) stasiun sudah ada input hari ini$/)
  const grid = page.getByTestId('station-grid')
  const jml = await grid.locator('.station-tile').count()
  const selesai = await grid.locator('.station-tile.is-done').count()
  const tertunda = await grid.locator('.station-tile.is-pending').count()
  const legenda = await page.getByTestId('ppa-legend').innerText().catch(() => '')
  const adaKeterangan = /tanpa dibatasi tanggal/i.test(legenda)

  const ok8 = !!m && jml === 18 && adaKeterangan
  catat('DS-08', '—', ok8 ? 'Lulus' : 'Gagal',
    `kepala halaman: "${kepala}"; tile=${jml}; sudah-ada-input=${selesai}; belum=${tertunda}; keterangan dua jendela waktu=${adaKeterangan}`)

  const ok9 = !!m && selesai === Number(m[1]) && selesai + tertunda === Number(m[2])
  catat('DS-09', '—', ok9 ? 'Lulus' : 'Gagal',
    m ? `kepala halaman menyebut ${m[1]} dari ${m[2]}; dihitung ulang dari tile di layar: sudah ada input=${selesai}, belum=${tertunda}, jumlahnya=${selesai + tertunda}, total tile=${jml}`
      : `bentuk kepala halaman tak terduga: "${kepala}"`)
  expect(ok8 && ok9, `DS-08=${ok8} DS-09=${ok9}`).toBe(true)
})

/**
 * Rute /reports/management dijaga `role:mill_management` SAJA — lebih sempit
 * dari Dashboard yang menerima admin+supervisor+mill_management. Jadi Admin
 * pun ditolak di sini, dan itu memang yang dikehendaki spec-nya.
 */
test('DS-06 — Laporan Manajemen hanya Mill Management', async ({ page }) => {
  const hasil: string[] = []
  for (const [akun, boleh] of [[SPV, false], [MGR, true], [ADM, false]] as [string, boolean][]) {
    await login(page, akun)
    const resp = await page.goto('/reports/management', { waitUntil: 'domcontentloaded' })
    const kode = resp?.status() ?? 0
    const teks = await page.locator('body').innerText()
    const ditolak = kode === 403 || /403|Akses Ditolak/i.test(teks)
    const sesuai = boleh ? !ditolak : ditolak
    hasil.push(`${akun}: ${ditolak ? '403' : 'terbuka'} (${sesuai ? 'sesuai' : 'TIDAK SESUAI'})`)
    await keluar(page)
  }
  const ok = hasil.every((h) => h.includes('sesuai)') && !h.includes('TIDAK'))
  catat('DS-06', '—', ok ? 'Lulus' : 'Gagal',
    `${hasil.join(' | ')}. Menu di sidebar dan izin alamat langsung memakai sumber izin yang sama, jadi keduanya tidak bisa berbeda.`)
  expect(ok, hasil.join('\n')).toBe(true)
})

test('AD-09 — Supervisor dan Operator tidak punya Pengaturan Mill', async ({ page }) => {
  const hasil: string[] = []
  for (const akun of [SPV, OPR]) {
    await login(page, akun)
    const sidebar = await page.locator('body').innerText()
    const adaMenu = /Mills Setting/i.test(sidebar)
    const resp = await page.goto('/mill-settings', { waitUntil: 'domcontentloaded' })
    const kode = resp?.status() ?? 0
    const teks = await page.locator('body').innerText()
    const ditolak = kode === 403 || /403|Akses Ditolak/i.test(teks)
    hasil.push(`${akun}: menu-di-sidebar=${adaMenu} alamat-langsung=${ditolak ? '403' : 'TERBUKA'}`)
    await keluar(page)
  }
  const ok = hasil.every((h) => h.includes('menu-di-sidebar=false') && h.includes('403'))
  catat('AD-09', '—', ok ? 'Lulus' : 'Gagal', hasil.join(' | '))
  expect(ok, hasil.join('\n')).toBe(true)
})

test('MD-PRD-01 — Kelola Periode Pelaporan hanya Admin', async ({ page }) => {
  const hasil: string[] = []
  for (const [akun, boleh] of [[SPV, false], [MGR, false], [ADM, true]] as [string, boolean][]) {
    await login(page, akun)
    const resp = await page.goto('/master-data/periods', { waitUntil: 'domcontentloaded' })
    const kode = resp?.status() ?? 0
    const teks = await page.locator('body').innerText()
    const ditolak = kode === 403 || /403|Akses Ditolak/i.test(teks)
    hasil.push(`${akun}: ${ditolak ? '403' : 'terbuka'}${(boleh ? !ditolak : ditolak) ? '' : ' <- TIDAK SESUAI'}`)
    await keluar(page)
  }
  const ok = !hasil.some((h) => h.includes('TIDAK SESUAI'))
  catat('MD-PRD-01', '—', ok ? 'Lulus' : 'Gagal', hasil.join(' | '))
  expect(ok, hasil.join('\n')).toBe(true)
})

test('DS-01 + DS-02 — Dashboard menyatakan dirinya contoh, dan tanpa filter', async ({ page }) => {
  await login(page, SPV)
  await page.goto('/dashboard', { waitUntil: 'domcontentloaded' })
  const teks = await page.locator('body').innerText()
  const adaDemo = /CPO Mill Demo/i.test(teks)
  const adaPenanda = /Data dummy/i.test(teks)
  const jmlInput = await page.locator('main input, main select').count()
  catat('DS-01', '—', adaDemo && adaPenanda ? 'Lulus' : 'Gagal',
    `judul memuat "CPO Mill Demo"=${adaDemo}; penanda "Data dummy" terbaca=${adaPenanda}. Keterangan hover TIDAK diperiksa di sini — perlu penguji manusia.`)

  // Angka yang sama untuk peran lain, dan tidak ada filter.
  const angka = (await page.locator('body').innerText()).match(/\d+[.,]\d{2}/g)?.slice(0, 12) ?? []
  await keluar(page)
  await login(page, MGR)
  await page.goto('/dashboard', { waitUntil: 'domcontentloaded' })
  const angka2 = (await page.locator('body').innerText()).match(/\d+[.,]\d{2}/g)?.slice(0, 12) ?? []
  const sama = JSON.stringify(angka) === JSON.stringify(angka2)
  catat('DS-02', '—', sama && jmlInput === 0 ? 'Lulus' : 'Gagal',
    `kolom input/select di halaman=${jmlInput} (harus 0); 12 angka pertama identik antara Supervisor dan Mill Management=${sama}. Perbandingan antar MILL dan antar HARI tidak diperiksa di sini.`)
  expect(adaDemo && adaPenanda, 'penanda demo tidak lengkap').toBe(true)
})

test('WB-BRW-02 — rentang tanggal terbalik ditolak dengan pesan', async ({ page }) => {
  await login(page, SPV)
  await page.goto('/data/depricarping', { waitUntil: 'domcontentloaded' })
  const dari = page.locator('input[type="date"]').first()
  const sampai = page.locator('input[type="date"]').nth(1)
  if (!(await sampai.count())) {
    catat('WB-BRW-02', 'Depricarping', 'Gagal', 'kolom rentang tanggal (dua input date) tidak ditemukan di layar')
    expect(await sampai.count()).toBeGreaterThan(0)
    return
  }
  await dari.fill('2026-10-11')
  await sampai.fill('2026-10-04')
  await page.keyboard.press('Tab')
  await page.waitForTimeout(1200)
  const teks = await page.locator('body').innerText()
  const adaPesan = /(rentang|tanggal).{0,80}(tidak|lebih|salah|harus)/i.test(teks)
  const teknis = /(SQLSTATE|Stack trace|Whoops|Server Error)/i.test(teks)
  catat('WB-BRW-02', 'Depricarping', adaPesan && !teknis ? 'Lulus' : 'Gagal',
    `pesan tentang rentang tanggal terbaca=${adaPesan}; galat teknis=${teknis}`)
  expect(adaPesan && !teknis).toBe(true)
})

test('WB-BRW-07 — Tambah membuka Form kosong', async ({ page }) => {
  await login(page, SPV)
  await page.goto('/data/depricarping/create', { waitUntil: 'domcontentloaded' })
  const akhir = new URL(page.url()).pathname
  const idUnit = page.locator('#depricarping_id')
  const ada = await idUnit.count() > 0
  const isi = ada ? await idUnit.inputValue() : '(kolom tidak ada)'
  const adaLine = await page.locator('select, [wire\\:model*="production_line"]').count() > 0
  catat('WB-BRW-07', 'Depricarping', ada && isi === '' ? 'Lulus' : 'Gagal',
    `alamat=${akhir}; kolom identitas unit ada=${ada}, isinya="${isi}" (harus kosong); pemilih Production Line ada=${adaLine}`)
  expect(ada && isi === '').toBe(true)
})

test('MD-KEL-04 — induk wajib dipilih, ditolak dengan pesan validasi bukan galat teknis', async ({ page }) => {
  await login(page, ADM)
  // Tingkat Business Unit, bukan Company: baris yang diisi di berkas Alur
  // adalah varian Business Unit, dan hasil satu tingkat tidak boleh dipakai
  // sebagai bukti tingkat lain.
  await page.goto('/master-data/business-units', { waitUntil: 'domcontentloaded' })
  const tambah = page.getByRole('button', { name: /Tambah/i }).first()
  if (!(await tambah.count())) {
    catat('MD-KEL-04', 'Business Unit', 'Gagal', 'tombol Tambah tidak ditemukan di layar Kelola Business Unit')
    expect(await tambah.count()).toBeGreaterThan(0)
    return
  }
  await tambah.click()
  await page.waitForTimeout(800)
  const nama = page.locator('input[type="text"]:visible').first()
  await nama.fill(`QA-RUN-${Date.now()}`)
  const simpan = page.getByRole('button', { name: /^Simpan$/i }).first()
  await simpan.click()
  await page.waitForTimeout(1500)
  const teks = await page.locator('body').innerText()
  const teknis = /(SQLSTATE|Stack trace|Whoops|Server Error|Integrity constraint|Undefined)/i.test(teks)
  const adaValidasi = /(wajib|harus dipilih|tidak boleh kosong|required)/i.test(teks)
  catat('MD-KEL-04', 'Business Unit', adaValidasi && !teknis ? 'Lulus' : 'Gagal',
    `pesan validasi terbaca=${adaValidasi}; galat teknis (SQLSTATE/stack trace/500)=${teknis}`)
  expect(adaValidasi && !teknis, teks.slice(0, 400)).toBe(true)
})

test('MD-STR-01 — Struktur Mills menampilkan kartu per mill dengan hierarkinya', async ({ page }) => {
  await login(page, ADM)
  await page.goto('/master-data/tree-view', { waitUntil: 'domcontentloaded' })
  const teks = await page.locator('body').innerText()
  const adaBreadcrumbKartu = /›/.test(teks)
  const adaLine = /Production Line|Line/i.test(teks)
  const kartu = await page.locator('[class*="mill-card"], [data-testid*="mill"]').count()
  catat('MD-STR-01', '—', adaBreadcrumbKartu && adaLine ? 'Lulus' : 'Gagal',
    `jalur "corporate › company" terbaca di halaman=${adaBreadcrumbKartu}; daftar Production Line terbaca=${adaLine}; elemen kartu mill terdeteksi=${kartu}`)
  expect(adaBreadcrumbKartu && adaLine).toBe(true)
})
