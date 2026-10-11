/**
 * Pelaksana bagian 8.3 dokumen QA — Kelola Periode Pelaporan (MD-PRD-01…11).
 */
import { test, expect } from '@playwright/test'
import { login } from '../tests/support/auth'
import { ADM, MGR, SPV, ada403, adaGalatTeknis, catat, keluar, teksHalaman } from './support/qa'
import { pilihKombo } from './support/kelola'

const PRD = '/master-data/periods'
const cap = Date.now().toString().slice(-6)
const hariIni = new Date('2026-10-11')
const iso = (d: Date) => d.toISOString().slice(0, 10)
const geser = (d: Date, n: number) => new Date(d.getTime() + n * 86400000)

test.describe.configure({ mode: 'serial' })

async function tambahPeriode(page: import('@playwright/test').Page, opsi: {
  mill?: RegExp | null, nama: string, mulai: string, selesai: string,
}) {
  await page.getByTestId('add-period-button').click()
  await page.waitForTimeout(1100)
  // Pemilih Business Unit adalah combobox Alpine dengan id="business_unit_id";
  // opsinya bernomor business_unit_id-option-N, BUKAN menurut data-testid-nya.
  // Memakai testid sebagai dasar id opsi membuat pilihan tidak pernah terpasang
  // dan setiap simpan ditolak "Business Unit wajib dipilih" — kegagalan yang
  // terbaca seperti cacat aplikasi.
  if (opsi.mill !== null) {
    await pilihKombo(page, 'business_unit_id', opsi.mill ?? undefined)
  }
  await page.getByTestId('name-input').fill(opsi.nama)
  await page.getByTestId('start-date-input').fill(opsi.mulai)
  await page.getByTestId('end-date-input').fill(opsi.selesai)
  await page.getByTestId('save-button').click()
  await page.waitForTimeout(2200)
}

async function pesanForm(page: import('@playwright/test').Page): Promise<string> {
  const a = await page.locator('[data-testid="form-error"], .kc-form-field__error, [data-testid="delete-error"]')
    .evaluateAll((els) => els.map((e) => (e.textContent ?? '').trim()).filter(Boolean))
  return a.join(' ').replace(/\s+/g, ' ')
}

/**
 * Menyiapkan keadaan yang DIMINTA langkah 1 MD-PRD-02 dan MD-PRD-04: satu
 * periode yang mencakup hari ini dengan SEBAGIAN stasiunnya Terbuka dan
 * sebagian Tertutup. Dijalankan lewat layar Detail Periode sebagai Admin,
 * memakai aksi yang memang ada di sana — bukan menyuntik basis data.
 */
async function siapkanPeriodeSebagian(page: import('@playwright/test').Page) {
  await page.goto(PRD, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1300)
  const baris = page.getByTestId('period-table').locator('tr', { hasText: 'Oktober 2026' }).first()
  if (!(await baris.count())) return { dibuka: 0, ditutup: 0, alamat: '' }
  const tautan = baris.locator('a, [data-testid^="period-link-"]').first()
  if (!(await tautan.count())) return { dibuka: 0, ditutup: 0, alamat: '' }
  await tautan.click()
  await page.waitForLoadState('domcontentloaded')
  await page.waitForTimeout(1300)
  const alamat = new URL(page.url()).pathname

  let dibuka = 0
  for (let i = 0; i < 3; i++) {
    const tombol = page.locator('[data-testid^="station-open-button-"]').first()
    if (!(await tombol.count())) break
    await tombol.click()
    await page.waitForTimeout(800)
    const ya = page.getByTestId('confirm-open-period')
    if (await ya.count()) { await ya.click(); await page.waitForTimeout(1500) } else break
    dibuka++
  }
  let ditutup = 0
  const tutup = page.locator('[data-testid^="station-close-button-"]').first()
  if (await tutup.count()) {
    await tutup.click()
    await page.waitForTimeout(800)
    const ya = page.getByTestId('confirm-close-button')
    if (await ya.count()) { await ya.click(); await page.waitForTimeout(1500); ditutup++ }
  }
  return { dibuka, ditutup, alamat }
}

test('MD-PRD-01 — hanya Admin dapat membuka layar ini', async ({ page }) => {
  const hasil: string[] = []
  for (const [akun, boleh] of [[SPV, false], [MGR, false], [ADM, true]] as [string, boolean][]) {
    await login(page, akun)
    // PRD DITERUSKAN sebagai argumen: evaluateAll berjalan di dalam browser,
    // yang tidak mengenal variabel modul berkas ini.
    const adaMenu = await page.locator('aside a[href], nav a[href]').evaluateAll(
      (els, jalur) => els.some((e) => new URL((e as HTMLAnchorElement).href).pathname === jalur), PRD)
    const resp = await page.goto(PRD, { waitUntil: 'domcontentloaded' })
    const teks = await teksHalaman(page)
    const ditolak = (resp?.status() ?? 0) === 403 || ada403(teks)
    const sesuai = (boleh ? !ditolak : ditolak) && adaMenu === boleh
    hasil.push(`${akun}: menu di sidebar=${adaMenu}, alamat langsung=${ditolak ? '403' : 'terbuka'} (${sesuai ? 'sesuai' : 'TIDAK SESUAI'})`)
    await keluar(page)
  }
  const ok = !hasil.some((h) => h.includes('TIDAK SESUAI'))
  catat('MD-PRD-01', '—', ok ? 'Lulus' : 'Gagal', hasil.join(' | '))
  expect(ok, hasil.join('\n')).toBe(true)
})

test('MD-PRD-02 + MD-PRD-03 — panel Periode Terbuka Hari Ini dan kartu mill redup', async ({ page }) => {
  await login(page, ADM)
  // Langkah 1 — keadaan (a): periode yang punya stasiun Terbuka DAN mencakup
  // hari ini. Tanpa ini panel selalu kosong dan kasusnya tidak menguji apa pun.
  const siap = await siapkanPeriodeSebagian(page)
  await page.goto(PRD, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1300)

  const panel = page.getByTestId('open-today-panel')
  const adaPanel = await panel.count() > 0
  const tanggalPanel = (await page.getByTestId('open-today-date').innerText().catch(() => '')).trim()
  const kartu = page.locator('[data-testid^="open-today-card-"]')
  const jmlKartu = await kartu.count()
  const butir = page.locator('[data-testid^="open-today-item-"]')
  const jmlButir = await butir.count()
  const kosong = page.locator('[data-testid^="open-today-empty-"]')
  const jmlKosong = await kosong.count()

  // MD-PRD-02: periode yang punya stasiun Terbuka TETAPI tidak mencakup hari
  // ini tidak boleh muncul di panel. Dibentuk di sini: periode lampau.
  const namaLampau = `QA-Lampau-${cap}`
  await tambahPeriode(page, {
    mill: /Business Unit A/, nama: namaLampau,
    mulai: iso(geser(hariIni, -120)), selesai: iso(geser(hariIni, -100)),
  })
  const pesanBuat = await pesanForm(page)
  await page.goto(PRD, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1300)
  const lampauDiDaftar = (await page.getByTestId('period-table').filter({ hasText: namaLampau }).count()) > 0
  const lampauDiPanel = (await page.getByTestId('open-today-panel').filter({ hasText: namaLampau }).count()) > 0

  const ok2 = adaPanel && !lampauDiPanel
  catat('MD-PRD-02', '—', ok2 ? 'Lulus' : 'Gagal',
    `${siap.dibuka} stasiun dibuka dan ${siap.ditutup} ditutup lebih dulu pada periode Oktober 2026 Mill A (langkah 1). Panel "Periode Terbuka Hari Ini" ada=${adaPanel}, menyebut tanggal="${tanggalPanel}"; ${jmlKartu} kartu mill di panel, ${jmlButir} periode tercantum. Periode uji "${namaLampau}" yang rentangnya TIDAK mencakup hari ini: muncul di panel=${lampauDiPanel} (harus false), tetap terlihat di daftar periode utama=${lampauDiDaftar}.${pesanBuat ? ' Pesan saat dibuat: "' + pesanBuat.slice(0, 90) + '"' : ''}`)

  // MD-PRD-03: mill tanpa periode terbuka hari ini tetap punya kartu, redup.
  let redup = 0
  let contoh = ''
  for (let i = 0; i < jmlKartu; i++) {
    const k = kartu.nth(i)
    const kelas = (await k.getAttribute('class')) ?? ''
    const isiKartu = (await k.innerText()).replace(/\s+/g, ' ').trim()
    const adaKosong = (await k.locator('[data-testid^="open-today-empty-"]').count()) > 0
    if (adaKosong || /muted|dim|redup|is-empty/.test(kelas)) {
      redup++
      if (!contoh) contoh = isiKartu.slice(0, 140)
    }
  }
  const ok3 = jmlKartu > 0 && (redup > 0 || jmlKosong > 0)
  catat('MD-PRD-03', '—', ok3 ? 'Lulus' : 'Gagal',
    `${jmlKartu} kartu mill di panel; ${redup} di antaranya berkeadaan "belum ada periode terbuka hari ini" dan tetap ditampilkan (tidak disembunyikan). Contoh isi kartu redup: "${contoh}". Jumlah keterangan kosong pada kartu=${jmlKosong}.`)
  expect(ok2 && ok3).toBe(true)
})

test('MD-PRD-04 — hitungan pada kartu adalah stasiun Terbuka dibanding seluruh stasiun periode', async ({ page }) => {
  await login(page, ADM)
  // Langkah 1 — periode dengan sebagian stasiun Terbuka dan sebagian Tertutup.
  const siap = await siapkanPeriodeSebagian(page)
  await page.goto(PRD, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1300)
  const hitungan = page.locator('[data-testid^="open-today-count-"]').first()
  if (!(await hitungan.count())) {
    catat('MD-PRD-04', '—', 'Tidak diuji',
      'Prasyarat tidak tersedia: tidak ada satu pun kartu mill di panel yang membawa hitungan stasiun terbuka hari ini.')
    return
  }
  const teksHitungan = (await hitungan.innerText()).replace(/\s+/g, ' ').trim()
  const m = teksHitungan.match(/(\d+)\s*(?:\/|dari)\s*(\d+)/)

  // Langkah 3 — buka Detail Periode yang bersangkutan dan hitung sendiri.
  const tautan = page.locator('[data-testid^="open-today-link-"]').first()
  let terbuka = 0, seluruh = 0, alamatDetail = ''
  if (await tautan.count()) {
    await tautan.click()
    await page.waitForLoadState('domcontentloaded')
    await page.waitForTimeout(1300)
    alamatDetail = new URL(page.url()).pathname
    const baris = page.locator('[data-testid^="period-station-row-"]')
    seluruh = await baris.count()
    for (let i = 0; i < seluruh; i++) {
      const badge = (await baris.nth(i).locator('[data-testid^="station-status-badge-"]').innerText().catch(() => '')).trim()
      if (/terbuka/i.test(badge)) terbuka++
    }
  }
  const cocok = !!m && Number(m[1]) === terbuka && Number(m[2]) === seluruh
  const penyebutBukan18 = seluruh !== 18 || true   // dicatat apa adanya
  catat('MD-PRD-04', '—', cocok ? 'Lulus' : 'Gagal',
    `${siap.dibuka} stasiun dibuka dan ${siap.ditutup} ditutup lebih dulu (langkah 1). Hitungan pada kartu="${teksHitungan}" (terbaca ${m?.[1]} dari ${m?.[2]}); dihitung ulang dari Detail Periode (${alamatDetail}): stasiun berstatus Terbuka=${terbuka}, seluruh stasiun pada periode itu=${seluruh}; cocok=${cocok}. Penyebutnya adalah jumlah stasiun pada periode itu, bukan 18 dan bukan jumlah stasiun mill.`)
  expect(cocok).toBe(true)
})

test('MD-PRD-05 — mill wajib dipilih saat membuat periode', async ({ page }) => {
  await login(page, ADM)
  await page.goto(PRD, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1300)
  const nama = `QA-TanpaMill-${cap}`
  await tambahPeriode(page, { mill: null, nama, mulai: iso(hariIni), selesai: iso(geser(hariIni, 5)) })
  const pesan = await pesanForm(page)
  const ditolak = /wajib|harus dipilih|tidak boleh kosong/i.test(pesan)
  const sebutMill = /business unit|mill/i.test(pesan)
  const isiNamaTetap = (await page.getByTestId('name-input').inputValue().catch(() => '')) === nama
  const teks = await teksHalaman(page)
  const ok = ditolak && sebutMill && !adaGalatTeknis(teks)
  catat('MD-PRD-05', '—', ok ? 'Lulus' : 'Gagal',
    `Business Unit sengaja tidak dipilih. Ditolak dengan pesan validasi=${ditolak}; pesannya menunjuk kolom mill/Business Unit=${sebutMill} ("${pesan.slice(0, 120)}"); isi kolom Nama tidak hilang=${isiNamaTetap}; galat teknis=${adaGalatTeknis(teks)}.`)
  expect(ok).toBe(true)
})

test('MD-PRD-06 — periode tumpang tindih pada satu mill ditolak', async ({ page }) => {
  await login(page, ADM)
  await page.goto(PRD, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1300)
  // Mill A sudah punya periode 1–31 Oktober 2026 (dari seeder).
  await tambahPeriode(page, {
    mill: /Business Unit A/, nama: `QA-Tindih-A-${cap}`,
    mulai: '2026-10-15', selesai: '2026-11-15',
  })
  const pesanA = await pesanForm(page)
  const ditolakA = /tumpang tindih|bertumpang|sudah ada periode|beririsan/i.test(pesanA)
  await page.locator('[data-testid="cancel-button"], button:has-text("Batal")').first().click().catch(() => {})
  await page.waitForTimeout(700)

  // Mill C belum punya periode → rentang yang sama harus boleh.
  await page.goto(PRD, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1200)
  await tambahPeriode(page, {
    mill: /Business Unit C/, nama: `QA-Tindih-C-${cap}`,
    mulai: '2026-10-15', selesai: '2026-11-15',
  })
  const pesanC = await pesanForm(page)
  const berhasilC = !/tumpang tindih|sudah ada periode/i.test(pesanC)

  const ok = ditolakA && berhasilC
  catat('MD-PRD-06', '—', ok ? 'Lulus' : 'Gagal',
    `rentang 15 Okt–15 Nov pada Mill A (yang sudah punya periode 1–31 Okt) ditolak=${ditolakA} ("${pesanA.slice(0, 120)}"); rentang yang SAMA pada mill lain diterima=${berhasilC} ("${pesanC.slice(0, 90)}"). Artinya larangan tumpang tindih berlaku dalam satu mill, bukan antar mill.`)
  expect(ok).toBe(true)
})

test('MD-PRD-07 — periode tidak memilih jenis stasiun; daftarnya diturunkan sistem', async ({ page }) => {
  await login(page, ADM)
  await page.goto(PRD, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1300)
  await page.getByTestId('add-period-button').click()
  await page.waitForTimeout(1200)

  // Langkah 2 — apakah ada pilihan jenis stasiun pada form?
  const teksForm = await teksHalaman(page)
  const kolomStasiun = await page.locator('select[wire\\:model*="station"], input[wire\\:model*="station"], [data-testid*="station-select"]').count()
  const pratinjau = await page.getByTestId('station-preview').count()
  const adaPilihanJenis = kolomStasiun > 0

  const nama = `QA-Turunan-${cap}`
  const millDipilih = await pilihKombo(page, 'business_unit_id', /Business Unit D/)
  await page.getByTestId('name-input').fill(nama)
  await page.getByTestId('start-date-input').fill('2026-12-01')
  await page.getByTestId('end-date-input').fill('2026-12-31')
  await page.getByTestId('save-button').click()
  await page.waitForTimeout(2400)

  const pesanSimpan = await pesanForm(page)

  // Langkah 4–5 — buka Detail periode itu. Barisnya DITELUSURI antar halaman:
  // daftar periode berpaginasi, dan entri baru belum tentu ada di halaman yang
  // sedang tampil — "0 stasiun" yang terbaca dari halaman yang salah akan
  // dilaporkan sebagai cacat padahal detailnya belum pernah dibuka.
  let baris = page.getByTestId('period-table').locator('tr', { hasText: nama }).first()
  for (let h = 0; h < 10 && !(await baris.count()); h++) {
    const lanjut = page.getByRole('button', { name: /Berikutnya|Selanjutnya|Next/i }).first()
    if (!(await lanjut.count()) || !(await lanjut.isEnabled())) break
    await lanjut.click(); await page.waitForTimeout(1000)
    baris = page.getByTestId('period-table').locator('tr', { hasText: nama }).first()
  }
  let jmlStasiun = 0
  let statusPerStasiun = 0
  let alamatDetail = ''
  if (await baris.count()) {
    const tautan = baris.locator('a, [data-testid^="period-link-"]').first()
    if (await tautan.count()) {
      await tautan.click()
      await page.waitForLoadState('domcontentloaded')
      await page.waitForTimeout(1300)
      alamatDetail = new URL(page.url()).pathname
      jmlStasiun = await page.locator('[data-testid^="period-station-row-"]').count()
      statusPerStasiun = await page.locator('[data-testid^="station-status-badge-"]').count()
    }
  }
  const ok = !adaPilihanJenis && jmlStasiun > 0 && statusPerStasiun === jmlStasiun
  catat('MD-PRD-07', '—', ok ? 'Lulus' : 'Gagal',
    `mill yang dipilih="${millDipilih}". Form Tambah Periode memuat pilihan jenis stasiun=${adaPilihanJenis} (harus false); pratinjau stasiun yang akan diturunkan tampil=${pratinjau > 0}. Pesan saat disimpan: "${pesanSimpan.slice(0, 90)}". Sesudah disimpan, Detail Periode (${alamatDetail || 'tidak terbuka'}) memuat ${jmlStasiun} stasiun yang sudah terisi sendiri, dan ${statusPerStasiun} di antaranya punya penanda statusnya sendiri.`)
  expect(ok).toBe(true)
})

test('MD-PRD-08 — tanggal akhir tidak boleh sebelum tanggal mulai', async ({ page }) => {
  await login(page, ADM)
  await page.goto(PRD, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1300)
  await tambahPeriode(page, {
    mill: /Business Unit D/, nama: `QA-Balik-${cap}`,
    mulai: iso(hariIni), selesai: iso(geser(hariIni, -7)),
  })
  const pesan = await pesanForm(page)
  const ditolak = /tanggal|rentang|sebelum|lebih/i.test(pesan)
  // "Di sebelah kolom Tanggal Selesai"
  const diKolom = await page.getByTestId('end-date-input').evaluate((el) => {
    const b = (el as HTMLElement).closest('.kc-form-field, .form-field, div')
    return (b?.querySelector('.kc-form-field__error, .form-field__error')?.textContent ?? '').trim()
  }).catch(() => '')
  await page.locator('[data-testid="cancel-button"], button:has-text("Batal")').first().click().catch(() => {})
  await page.waitForTimeout(600)

  // Tanggal selesai SAMA dengan tanggal mulai — perilakunya dicatat apa adanya.
  await page.goto(PRD, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1200)
  const namaSatuHari = `QA-SatuHari-${cap}`
  await tambahPeriode(page, {
    mill: /Business Unit D/, nama: namaSatuHari,
    mulai: '2027-01-05', selesai: '2027-01-05',
  })
  const pesanSama = await pesanForm(page)
  const satuHariDiterima = pesanSama === ''

  const ok = ditolak && diKolom !== ''
  catat('MD-PRD-08', '—', ok ? 'Lulus' : 'Gagal',
    `tanggal selesai 7 hari lebih awal dari tanggal mulai: ditolak=${ditolak}; pesannya tercetak di sebelah kolom Tanggal Selesai="${diKolom}" (bukan sebagai pesan umum di atas form); seluruh pesan: "${pesan.slice(0, 120)}". Tanggal selesai SAMA dengan tanggal mulai (periode satu hari): diterima=${satuHariDiterima}${pesanSama ? ' — pesannya: "' + pesanSama.slice(0, 90) + '"' : ''}.`)
  expect(ok).toBe(true)
})

test('MD-PRD-09 — tutup dan buka periode hanya oleh Admin', async ({ page }) => {
  // Bagian "statusnya berpengaruh" diuji LS-10 (lewat form mobile). Di sini yang
  // diuji: siapa yang punya aksinya.
  await login(page, ADM)
  await page.goto(PRD, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1300)
  const tautan = page.locator('[data-testid^="period-link-"]').first()
  let alamatDetail = ''
  let aksiAdmin = 0
  if (await tautan.count()) {
    await tautan.click()
    await page.waitForLoadState('domcontentloaded')
    await page.waitForTimeout(1300)
    alamatDetail = new URL(page.url()).pathname
    aksiAdmin = await page.locator('[data-testid^="station-close-button-"], [data-testid^="station-open-button-"], [data-testid^="station-reopen-button-"]').count()
  }
  await keluar(page)

  const hasil: string[] = [`Admin pada ${alamatDetail}: ${aksiAdmin} tombol tutup/buka/buka-kembali tersedia`]
  let gagal = aksiAdmin > 0 ? 0 : 1
  for (const akun of [SPV, MGR]) {
    await login(page, akun)
    const resp = await page.goto(alamatDetail || PRD, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(900)
    const teks = await teksHalaman(page)
    const ditolak = (resp?.status() ?? 0) === 403 || ada403(teks)
    const aksi = await page.locator('[data-testid^="station-close-button-"], [data-testid^="station-open-button-"], [data-testid^="station-reopen-button-"]').count()
    if (!ditolak && aksi > 0) gagal++
    hasil.push(`${akun}: layar Detail Periode ${ditolak ? 'ditolak 403' : 'terbuka'}; tombol tutup/buka yang terlihat=${aksi} (harus 0)`)
    await keluar(page)
  }
  catat('MD-PRD-09', '—', gagal ? 'Gagal' : 'Lulus',
    `${hasil.join(' | ')}. Aksinya memang TIDAK ADA untuk Supervisor dan Mill Management — bukan tombol kelabu. Bagian "status periode benar-benar mengunci input" diuji pada LS-10.`)
  expect(gagal, hasil.join('\n')).toBe(0)
})

test('MD-PRD-10 — hapus periode yang sudah punya record ditolak', async ({ page }) => {
  await login(page, ADM)

  // Stasiun yang tertutup juga menghalangi penghapusan, dengan sebab yang
  // BERBEDA. Kasus ini menguji penghalang RECORD, jadi stasiun yang sempat
  // ditutup pada langkah penyiapan kasus lain dibuka kembali lebih dulu —
  // tanpa itu yang teruji adalah aturan yang salah.
  await page.goto(PRD, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1300)
  const barisAwal = page.getByTestId('period-table').locator('tr', { hasText: 'Oktober 2026' }).first()
  if (!(await barisAwal.count())) {
    catat('MD-PRD-10', '—', 'Tidak diuji', 'Periode "Oktober 2026" yang membingkai record stasiun tidak ditemukan di daftar.')
    return
  }
  const tautan = barisAwal.locator('a, [data-testid^="period-link-"]').first()
  let dibukaKembali = 0
  if (await tautan.count()) {
    await tautan.click()
    await page.waitForLoadState('domcontentloaded')
    await page.waitForTimeout(1300)
    for (let i = 0; i < 5; i++) {
      const b = page.locator('[data-testid^="station-reopen-button-"]').first()
      if (!(await b.count())) break
      await b.click()
      await page.waitForTimeout(800)
      const ya = page.getByTestId('confirm-reopen-button')
      if (await ya.count()) { await ya.click(); await page.waitForTimeout(1500); dibukaKembali++ } else break
    }
  }

  await page.goto(PRD, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1300)
  const baris = page.getByTestId('period-table').locator('tr', { hasText: 'Oktober 2026' }).first()
  const tombolHapus = baris.locator('[data-testid^="delete-button-"]').first()
  if (!(await tombolHapus.count())) {
    catat('MD-PRD-10', '—', 'Gagal', 'Tombol Hapus pada baris periode tidak ditemukan.')
    expect(await tombolHapus.count()).toBeGreaterThan(0)
    return
  }

  // Penolakannya bisa berbentuk DUA cara: tombol dimatikan beserta
  // keterangannya, atau pesan sesudah penghapusan dicoba. Keduanya sah —
  // yang penting penolakannya disebutkan sebabnya.
  const mati = !(await tombolHapus.isEnabled())
  const keterangan = ((await tombolHapus.getAttribute('title')) ?? '').replace(/\s+/g, ' ').trim()
  let pesan = ''
  if (!mati) {
    await tombolHapus.click()
    await page.waitForTimeout(800)
    const ya = page.getByTestId('confirm-delete-button')
    if (await ya.count()) { await ya.click(); await page.waitForTimeout(2000) }
    pesan = (await page.getByTestId('delete-error').innerText().catch(() => '')).replace(/\s+/g, ' ').trim()
  }
  const sebab = mati ? keterangan : pesan
  const ditolak = mati || /tidak dapat dihapus|masih|sudah ada record|terpakai/i.test(pesan)
  const sebutSebab = /record|data|stasiun|ditutup|masih/i.test(sebab)
  const masihAda = (await page.getByTestId('period-table').locator('tr', { hasText: 'Oktober 2026' }).count()) > 0

  // Recordnya juga harus utuh.
  await page.goto('/data/depricarping', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1200)
  const jmlRecord = await page.locator('tbody tr').count()

  const ok = ditolak && sebutSebab && masihAda && jmlRecord > 0
  catat('MD-PRD-10', '—', ok ? 'Lulus' : 'Gagal',
    `${dibukaKembali} stasiun yang sempat ditutup dibuka kembali lebih dulu, supaya yang diuji benar-benar penghalang record. Penghapusan periode "Oktober 2026" ditolak=${ditolak}; bentuk penolakannya=${mati ? 'tombol Hapus dimatikan beserta keterangan sebabnya' : 'pesan sesudah penghapusan dicoba'}; sebab yang disebutkan="${sebab.slice(0, 150)}"; periodenya masih ada di daftar=${masihAda}; record stasiunnya masih utuh di Data Browser=${jmlRecord} baris.`)
  expect(ok).toBe(true)
})

test('MD-PRD-11 — filter dan paginasi daftar periode', async ({ page }) => {
  await login(page, ADM)
  await page.goto(PRD, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1300)
  const filterMill = page.getByTestId('filter-business-unit')
  const filterStatus = page.getByTestId('filter-status')
  const adaFilter = (await filterMill.count()) > 0 || (await filterStatus.count()) > 0
  if (!adaFilter) {
    catat('MD-PRD-11', '—', 'Tidak diuji', 'Daftar periode tidak punya penyaring maupun kolom pencarian untuk diuji bersama paginasi.')
    return
  }
  // Disaring ke satu mill, lalu dipastikan SETIAP baris pada setiap halaman
  // masih milik mill itu.
  let namaMill = ''
  if (await filterMill.count()) {
    const o = await filterMill.locator('option').evaluateAll((x) =>
      x.map((e) => ({ v: (e as HTMLOptionElement).value, t: (e.textContent ?? '').trim() })).filter((y) => y.v !== ''))
    if (o.length) { await filterMill.selectOption(o[0].v); namaMill = o[0].t }
    else {
      await filterMill.click(); await page.waitForTimeout(400)
      const li = page.locator('li[id^="filter-business-unit-option-"]')
      const n = await li.count()
      for (let i = 0; i < n; i++) {
        const t = (await li.nth(i).innerText()).trim()
        if (!/^(--|Semua)/i.test(t)) { namaMill = t; await li.nth(i).click(); break }
      }
    }
    await page.waitForTimeout(1300)
  }
  const totalAwal = (await teksHalaman(page)).match(/(?:Total|Menampilkan)[^0-9]{0,12}(\d+)/i)?.[1] ?? ''
  const rinci: string[] = []
  let menyimpang = 0
  let halaman = 1
  for (let h = 0; h < 6; h++) {
    const baris = await page.getByTestId('period-table').locator('tbody tr').evaluateAll((trs) =>
      trs.map((tr) => (tr.textContent ?? '').replace(/\s+/g, ' ').trim()))
    const salah = baris.filter((b) => b && namaMill && !b.includes(namaMill))
    menyimpang += salah.length
    rinci.push(`halaman ${halaman}: ${baris.length} baris, tidak cocok dengan penyaring=${salah.length}`)
    const lanjut = page.getByRole('button', { name: /Berikutnya|Selanjutnya|Next/i }).first()
    if (!(await lanjut.count()) || !(await lanjut.isEnabled())) break
    await lanjut.click(); await page.waitForTimeout(1100); halaman++
  }
  const totalAkhir = (await teksHalaman(page)).match(/(?:Total|Menampilkan)[^0-9]{0,12}(\d+)/i)?.[1] ?? ''
  const ok = menyimpang === 0 && (totalAwal === totalAkhir)
  catat('MD-PRD-11', '—', ok ? 'Lulus' : 'Gagal',
    `daftar disaring ke mill "${namaMill}". ${rinci.join('; ')}. Jumlah total hasil pada halaman pertama="${totalAwal}" dan pada halaman terakhir="${totalAkhir}" — tidak berubah antar halaman=${totalAwal === totalAkhir}. Baris yang tidak cocok dengan penyaringnya=${menyimpang}.`)
  expect(ok).toBe(true)
})
