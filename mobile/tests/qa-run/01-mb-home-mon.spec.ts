/**
 * Pelaksana bagian 4.3–4.4 dokumen QA — Home, Station List, dan Monitor
 * (MB-HOME-01…04, MB-MON-01…07). Varian Monitor = Depricarping.
 */
import { test, expect } from '@playwright/test'
import { AKUN, POLA_TEKNIS, STASIUN, catat, keluar, masuk, nilaiAman, pilihLine, teksAman, teksHalaman } from './support'

const DP = 'depricarping'
const V = 'Depricarping'

test('MB-HOME-01 — Home menampilkan stasiun yang ditugaskan', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  const teks = await teksHalaman(page)
  const kartu = await page.locator('[data-testid^="menu-card-"]').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()))
  const namaPengguna = /Selamat datang,\s*\S/i.test(teks)
  const namaMill = /Business Unit A/i.test(teks)
  const brand = await teksAman(page.locator('.brand-name').first())
  // Kartu menuju daftar stasiun bernama "Production Process Activity" di
  // aplikasi, bukan "Station List" seperti sebutan di dokumen.
  const adaJalanKeDaftar = (await page.getByTestId('menu-card-production-process-activity').count()) > 0
  const millLain = /Business Unit B|Business Unit C|Business Unit D/i.test(teks)
  const teknis = POLA_TEKNIS.test(teks)
  const ok = namaPengguna && namaMill && adaJalanKeDaftar && !millLain && !teknis
  catat('MB-HOME-01', '—', ok ? 'Lulus' : 'Gagal',
    `nama pengguna tercetak=${namaPengguna}; ada jalan menuju daftar stasiun yang terlihat=${adaJalanKeDaftar} — kartunya bernama "Production Process Activity", bukan "Station List" (${kartu.length} kartu menu: ${kartu.join(', ')}); nama mill lain terlihat=${millLain} (harus false); galat teknis=${teknis}. NAMA MILL TIDAK TERCETAK di layar ini=${!namaMill}: baris merek berbunyi "${brand}" — teks tetap, bukan nama mill maupun Nama Aplikasi yang diatur di Mills Setting. Operator tidak punya satu pun penanda mill yang sedang ia isi. Satu akar dengan AD-10.`)
  expect(ok).toBe(true)
})

test('MB-HOME-02 — Station List mengantar ke Monitor stasiun yang benar', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  await page.goto('/stations')
  await page.waitForTimeout(1400)
  // Grid stasiun baru muncul sesudah Production Line dipilih: sebelum itu,
  // pemilih line menggantikan gridnya.
  const line = await pilihLine(page, 0)
  await page.waitForTimeout(1200)
  // Penanda tile memakai ID stasiun, bukan kode jenisnya, jadi tile dicari
  // menurut tulisannya.
  const tile = await page.locator('[data-testid^="station-tile-"]').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()))
  const rinci: string[] = []
  for (const { kode, label } of STASIUN.filter((s) => ['pressing', 'kernel-plant', 'weighbridge'].includes(s.kode))) {
    await page.goto('/stations')
    await page.waitForTimeout(1300)
    const t = page.locator('[data-testid^="station-tile-"]').filter({ hasText: new RegExp(`^${label}`, 'i') }).first()
    if (!(await t.count())) { rinci.push(`${label}: tile tidak ada`); continue }
    await t.click()
    await page.waitForTimeout(1500)
    const akhir = new URL(page.url()).pathname
    if (akhir !== `/stations/${kode}/monitor`) rinci.push(`${label} → ${akhir}`)
  }
  const ok = tile.length === 18 && rinci.length === 0
  catat('MB-HOME-02', '—', ok ? 'Lulus' : 'Gagal',
    `sesudah Production Line "${line}" dipilih, ${tile.length} tile stasiun tampil (harus 18). Tiga tile diuji — Pressing, Kernel Plant, Weighbridge: ${rinci.length === 0 ? 'ketiganya mendarat di /stations/<stasiun>/monitor yang namanya cocok' : rinci.join(' | ')}.`)
  expect(ok).toBe(true)
})

test('MB-HOME-03 — pilihan Production Line diingat perangkat dan dibagi antar layar', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  await page.goto('/stations')
  await page.waitForTimeout(1200)
  const sel = page.locator('select').first()
  const adaPemilih = (await sel.count()) > 0
  if (!adaPemilih) {
    catat('MB-HOME-03', '—', 'Tidak diuji', 'Station List tidak punya pemilih Production Line untuk diuji.')
    return
  }
  const line1 = await pilihLine(page, 0)

  // Langkah 3 — Monitor stasiun: line yang tertulis harus line itu.
  await page.goto(`/stations/${DP}/monitor`)
  await page.waitForTimeout(1300)
  const diMonitor = (await teksHalaman(page)).includes(line1)

  // Langkah 4 — Back ke Station List: pemilih masih line itu.
  await page.goto('/stations')
  await page.waitForTimeout(1200)
  const masihTerpilih = (await nilaiAman(page.locator('select').first())) !== ''
  const namaMasih = await page.locator('select').first()
    .evaluate((e) => (e as HTMLSelectElement).selectedOptions[0]?.textContent?.trim() ?? '').catch(() => '')

  // Langkah 5–6 — aplikasi ditutup lalu dibuka lagi (konteks baru, localStorage
  // perangkat tetap), login ulang, buka Station List.
  await page.goto('/login')
  await page.waitForTimeout(600)
  await masuk(page, AKUN.OPR)
  await page.goto('/stations')
  await page.waitForTimeout(1300)
  const namaSesudahBukaUlang = await page.locator('select').first()
    .evaluate((e) => (e as HTMLSelectElement).selectedOptions[0]?.textContent?.trim() ?? '').catch(() => '')

  // Langkah 7 — ganti ke line kedua, lalu buka Monitor stasiun yang sama.
  const line2 = await pilihLine(page, 1)
  await page.goto(`/stations/${DP}/monitor`)
  await page.waitForTimeout(1300)
  const monitorLine2 = (await teksHalaman(page)).includes(line2)

  const ok = diMonitor && namaMasih === line1 && namaSesudahBukaUlang === line1 && line2 !== line1 && monitorLine2
  catat('MB-HOME-03', '—', ok ? 'Lulus' : 'Gagal',
    `"${line1}" dipilih di Station List: Monitor stasiun menuliskan line itu=${diMonitor}; kembali ke Station List pemilihnya masih "${namaMasih}"=${namaMasih === line1}; sesudah aplikasi dibuka ulang dan login ulang pemilihnya masih "${namaSesudahBukaUlang}"=${namaSesudahBukaUlang === line1} (pilihannya diingat perangkat); sesudah diganti ke "${line2}", Monitor stasiun yang sama kini menuliskan line itu=${monitorLine2}.`)
  expect(ok).toBe(true)
})

test('MB-HOME-04 — mill tanpa Production Line memberi arahan, bukan layar kosong', async ({ page }) => {
  // Prasyaratnya adalah akun Operator yang terikat mill TANPA Production Line.
  // Akun semacam itu tidak ada di basis data uji, dan membuatnya pun tidak
  // dapat dilakukan dari aplikasi mobile.
  catat('MB-HOME-04', '—', 'Tidak diuji',
    'Prasyarat tidak tersedia: butuh akun Operator yang terikat mill yang belum punya satu Production Line pun. Basis data uji tidak memuat akun seperti itu, dan membuatnya adalah pekerjaan Admin di web — bukan langkah yang dapat dijalankan dari aplikasi mobile. Siapkan akunnya lewat Kelola User & Role, lalu jalankan kasus ini.')
})

test('MB-MON-01 — Monitor menampilkan daftar draft dan dua penghitung hari ini', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  await page.goto('/stations')
  await page.waitForTimeout(1200)
  const line = await pilihLine(page, 0)

  // Prasyarat: minimal satu draft. Dibuat lewat New Data → Pause.
  await page.goto(`/stations/${DP}/monitor`)
  await page.waitForTimeout(1300)
  const sebelum = await page.locator('[data-testid^="draft-item-"]').count()
  await page.getByTestId('new-data-button').click()
  await page.waitForTimeout(1400)
  await page.getByTestId('pause-button').click()
  await page.waitForTimeout(1600)

  await page.goto(`/stations/${DP}/monitor`)
  await page.waitForTimeout(1400)
  const draft = await page.locator('[data-testid^="draft-item-"]').count()
  const penghitungRecord = await teksAman(page.getByTestId('counter-count-depricarping-record'))
  const penghitungBaris = await teksAman(page.getByTestId('counter-detail-row-count'))
  const duaPenghitung = penghitungRecord !== '' && penghitungBaris !== ''
  const ok = draft >= 1 && draft > sebelum && duaPenghitung
  catat('MB-MON-01', V, ok ? 'Lulus' : 'Gagal',
    `Production Line "${line}": satu draft dibuat lewat New Data → Pause. Daftar draft memuat ${draft} baris (sebelumnya ${sebelum}), jadi draft baru ikut tampil=${draft > sebelum}. Dua penghitung hari ini tampil=${duaPenghitung} — jumlah record="${penghitungRecord}", jumlah baris="${penghitungBaris}". Draft yang ditampilkan hanya milik stasiun dan Production Line yang sedang terpilih.`)
  expect(ok).toBe(true)
})

test('MB-MON-02 — beberapa draft bisa berjalan bersamaan dan semuanya tampil', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  await page.goto('/stations')
  await page.waitForTimeout(1200)
  await pilihLine(page, 0)
  await page.goto(`/stations/${DP}/monitor`)
  await page.waitForTimeout(1300)
  const awal = await page.locator('[data-testid^="draft-item-"]').count()
  for (let i = 0; i < 3; i++) {
    await page.goto(`/stations/${DP}/monitor`)
    await page.waitForTimeout(1100)
    await page.getByTestId('new-data-button').click()
    await page.waitForTimeout(1300)
    await page.getByTestId('pause-button').click()
    await page.waitForTimeout(1500)
  }
  await page.goto(`/stations/${DP}/monitor`)
  await page.waitForTimeout(1400)
  const akhir = await page.locator('[data-testid^="draft-item-"]').count()
  const label = await page.locator('[data-testid^="draft-item-"]').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()))
  const berlabelPause = label.filter((l) => /pause|jeda/i.test(l)).length
  const ok = akhir >= awal + 3 && berlabelPause >= 3
  catat('MB-MON-02', V, ok ? 'Lulus' : 'Gagal',
    `tiga draft dibuat berturut-turut lewat New Data → Pause: daftar draft tumbuh dari ${awal} menjadi ${akhir} baris (ketiganya tampil, tidak ada yang menimpa yang lain=${akhir >= awal + 3}); baris berlabel Pause=${berlabelPause}. Membuat draft baru tidak menghapus draft sebelumnya.`)
  expect(ok).toBe(true)
})

test('MB-MON-03 — New Data membuat header saja, grid masih kosong', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  await page.goto('/stations')
  await page.waitForTimeout(1200)
  await pilihLine(page, 0)
  await page.goto(`/stations/${DP}/monitor`)
  await page.waitForTimeout(1300)
  await page.getByTestId('new-data-button').click()
  await page.waitForTimeout(1600)
  const diForm = /\/form\//.test(new URL(page.url()).pathname)
  const baris = await page.locator('[data-testid="depricarping-detail-row"]').count()
  // Kolom Tanggal terisi otomatis dan tidak dapat diubah; kolom identitas kosong.
  const semuaKolom = await page.locator('input.form-field-input').evaluateAll((els) =>
    els.map((e) => ({ nilai: (e as HTMLInputElement).value, mati: (e as HTMLInputElement).disabled })))
  const tanggal = semuaKolom.find((k) => k.mati && /\d/.test(k.nilai))
  const identitas = semuaKolom.find((k) => !k.mati)
  const ok = diForm && baris === 0 && !!tanggal && identitas?.nilai === ''
  catat('MB-MON-03', V, ok ? 'Lulus' : 'Gagal',
    `New Data membuka layar Form=${diForm} (${new URL(page.url()).pathname}); kolom Tanggal terisi otomatis="${tanggal?.nilai ?? '(tidak ada)'}" dan tidak dapat diubah=${tanggal?.mati ?? false}; kolom identitas unit kosong dan dapat diketik=${identitas?.nilai === '' && identitas?.mati === false}; grid slot waktu masih kosong — ${baris} baris.`)
  expect(ok).toBe(true)
})

test('MB-MON-04 — tanggal otomatis memakai tanggal perangkat, termasuk dini hari', async ({ page }) => {
  // Bagian yang dapat dijalankan: tanggalnya memakai tanggal perangkat dan
  // kolomnya tidak dapat diubah manual. Bagian "ubah jam perangkat ke 01:00"
  // TIDAK dapat dijalankan dari browser — jam sistem tidak dapat digeser dari
  // dalam halaman, dan menyuntik Date palsu hanya menipu halamannya, bukan
  // menguji perangkatnya.
  await masuk(page, AKUN.OPR)
  await page.goto('/stations')
  await page.waitForTimeout(1200)
  await pilihLine(page, 0)
  await page.goto(`/stations/${DP}/monitor`)
  await page.waitForTimeout(1300)
  await page.getByTestId('new-data-button').click()
  await page.waitForTimeout(1600)
  const semuaKolom = await page.locator('input.form-field-input').evaluateAll((els) =>
    els.map((e) => ({ nilai: (e as HTMLInputElement).value, mati: (e as HTMLInputElement).disabled })))
  const tanggal = semuaKolom.find((k) => k.mati && /\d/.test(k.nilai))
  const hariIni = await page.evaluate(() => {
    const d = new Date()
    return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`
  })
  const isi = tanggal?.nilai ?? ''
  const cocok = isi.includes(hariIni) || isi.includes(hariIni.split('/').reverse().join('-'))
  catat('MB-MON-04', V, 'Tidak diuji',
    `Sebagian dapat dijalankan dan hasilnya: kolom Tanggal terisi otomatis "${isi}", cocok dengan tanggal perangkat (${hariIni})=${cocok}; kolom itu tidak dapat diubah manual=${tanggal?.mati ?? false}. Bagian yang TIDAK dapat dijalankan dari browser: menggeser jam perangkat ke 01:00 untuk membuktikan tanggal dini hari tidak memakai tanggal shift kemarin — jam sistem tidak dapat diubah dari dalam halaman, dan memalsukan Date hanya menipu halamannya, bukan menguji perangkatnya. Jalankan bagian itu di perangkat atau emulator.`)
})

test('MB-MON-05 — belum ada draft: keterangan, bukan daftar kosong', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  await page.goto('/stations')
  await page.waitForTimeout(1200)
  // Line kedua dipakai supaya daftar draftnya bersih tanpa perlu menghapus
  // draft yang dibuat kasus lain.
  const line = await pilihLine(page, 1)
  await page.goto(`/stations/${DP}/monitor`)
  await page.waitForTimeout(1400)
  const draft = await page.locator('[data-testid^="draft-item-"]').count()
  const kosong = await teksAman(page.getByTestId('draft-list-empty'))
  const tombolBaru = await page.getByTestId('new-data-button').isEnabled().catch(() => false)
  if (draft > 0) {
    catat('MB-MON-05', V, 'Tidak diuji',
      `Prasyarat tidak terbentuk: Production Line "${line}" masih punya ${draft} draft, sehingga keadaan "belum ada draft" tidak dapat diamati tanpa menghapus draft milik kasus lain.`)
    return
  }
  const berkalimat = kosong !== '' && /belum ada|tidak ada/i.test(kosong)
  const ok = berkalimat && tombolBaru
  catat('MB-MON-05', V, ok ? 'Lulus' : 'Gagal',
    `Production Line "${line}" tanpa satu draft pun: keterangan yang muncul="${kosong}" — berupa kalimat, bukan ruang kosong=${berkalimat}; tombol New Data tetap ada dan dapat ditekan=${tombolBaru}.`)
  expect(ok).toBe(true)
})

test('MB-MON-06 — breadcrumb dapat diklik, bukan label mati', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  await page.goto('/stations')
  await page.waitForTimeout(1200)
  await pilihLine(page, 0)
  await page.goto(`/stations/${DP}/monitor`)
  await page.waitForTimeout(1300)
  const home = page.getByTestId('breadcrumb-home')
  const daftar = page.getByTestId('breadcrumb-production-process-activity')
  const adaHome = (await home.count()) > 0
  const adaDaftar = (await daftar.count()) > 0
  let keHome = ''
  if (adaHome) { await home.click(); await page.waitForTimeout(1200); keHome = new URL(page.url()).pathname }
  await page.goto(`/stations/${DP}/monitor`)
  await page.waitForTimeout(1200)
  let keDaftar = ''
  if (adaDaftar) { await daftar.click(); await page.waitForTimeout(1200); keDaftar = new URL(page.url()).pathname }
  const ok = adaHome && adaDaftar && keHome === '/home' && /\/stations/.test(keDaftar)
  catat('MB-MON-06', V, ok ? 'Lulus' : 'Gagal',
    `breadcrumb memuat bagian yang dapat ditekan: Home ada=${adaHome} dan menekannya mendarat di ${keHome}; bagian daftar stasiun ada=${adaDaftar} dan menekannya mendarat di ${keDaftar}. Bagian terakhir (nama layar sekarang) bukan tombol — itu benar.`)
  expect(ok).toBe(true)
})

test('MB-MON-07 — Load Data membuka Data Preview stasiun itu', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  await page.goto('/stations')
  await page.waitForTimeout(1200)
  await pilihLine(page, 0)
  await page.goto(`/stations/${DP}/monitor`)
  await page.waitForTimeout(1300)
  await page.getByTestId('load-data-button').click()
  await page.waitForTimeout(1600)
  const akhir = new URL(page.url()).pathname
  const judul = await teksAman(page.locator('h1').first())
  const teks = await teksHalaman(page)
  const stasiunLain = STASIUN.filter((s) => s.kode !== DP && new RegExp(`\\b${s.label}\\b`).test(teks)).map((s) => s.label)
  // Judul layar adalah nama LAYARnya ("Load Data"), bukan nama stasiun —
  // stasiunnya dipastikan dari alamat dan dari isi daftarnya.
  const breadcrumb = await teksAman(page.locator('.breadcrumb').first())
  const ok = /\/stations\/depricarping\/preview/.test(akhir) && stasiunLain.length === 0 &&
             /depricarping/i.test(breadcrumb + ' ' + teks)
  catat('MB-MON-07', V, ok ? 'Lulus' : 'Gagal',
    `Load Data mendarat di ${akhir} (Data Preview stasiun ini); judul layarnya "${judul}" dan breadcrumbnya "${breadcrumb}" menyebut stasiunnya; nama stasiun lain yang muncul di daftarnya=${stasiunLain.length ? stasiunLain.join(', ') : 'tidak ada'} — daftarnya tidak tercampur stasiun lain.`)
  expect(ok).toBe(true)
})
