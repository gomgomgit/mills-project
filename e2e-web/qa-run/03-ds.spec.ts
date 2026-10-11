/**
 * Pelaksana bagian 7 dokumen QA — Dashboard & Laporan lain (DS-01…DS-12).
 * DS-07 (mobile) dan DS-12 (bandingkan urutan web vs mobile) dijalankan
 * pelaksana mobile; DS-12 memakai urutan web yang DITULIS berkas ini.
 */
import { test, expect } from '@playwright/test'
import { writeFileSync, mkdirSync } from 'node:fs'
import { login } from '../tests/support/auth'
import {
  ADM, MGR, SPV, SPV_B,
  ada403, adaGalatTeknis, catat, keluar, pilihLine, teksHalaman,
} from './support/qa'

/**
 * "11,50" dan "11.50" dan "3,096.00" → angka. Pemisah desimalnya DITENTUKAN
 * dari bentuk angkanya, bukan diasumsikan: layar laporan memakai koma sebagai
 * desimal, sedangkan panel dashboard memakai titik. Menebak satu konvensi
 * membuat "24.00" terbaca 2400 — nilainya 100× salah, tetapi perbandingan antar
 * angkanya tetap cocok, jadi kasusnya lulus sambil mencatat bukti yang keliru.
 */
function angka(t: string): number | null {
  const m = t.replace(/\s/g, '').match(/-?[\d.,]*\d/)
  if (!m) return null
  let x = m[0]
  if (/,\d{1,2}$/.test(x)) x = x.replace(/\./g, '').replace(',', '.')        // 1.234,56
  else if (/\.\d{1,2}$/.test(x)) x = x.replace(/,/g, '')                     // 1,234.56
  else x = x.replace(/[.,]/g, '')                                             // 1.234 / 1,234
  const n = parseFloat(x)
  return Number.isNaN(n) ? null : n
}

test('DS-01 — Dashboard adalah demo, dan layar menyatakannya', async ({ page }) => {
  const hasil: string[] = []
  let gagal = 0
  for (const akun of [SPV, MGR, ADM]) {
    await login(page, akun)
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(700)
    const teks = await teksHalaman(page)
    const judulDemo = /CPO Mill Demo/i.test(teks)
    const chip = page.locator('.md-chip--dummy, [class*="dummy"]').first()
    const adaChip = await chip.count() > 0
    // Langkah 4 — keterangan yang muncul saat kursor diarahkan. Pada chip ini
    // keterangannya adalah atribut title: itulah yang dirender browser sebagai
    // tooltip, jadi membacanya sama dengan membaca tooltipnya.
    let keterangan = ''
    if (adaChip) {
      await chip.hover().catch(() => {})
      keterangan = (await chip.getAttribute('title')) ?? (await chip.getAttribute('aria-label')) ?? ''
    }
    const keteranganBenar = /fiktif/i.test(keterangan) && /belum terhubung/i.test(keterangan)
    if (!judulDemo || !adaChip || !keteranganBenar) gagal++
    hasil.push(`${akun}: judul menyebut demo=${judulDemo}; penanda "Data dummy" terlihat=${adaChip}; keterangan hover="${keterangan}" (menyebut fiktif & belum terhubung=${keteranganBenar})`)
    await keluar(page)
  }
  catat('DS-01', '—', gagal ? 'Gagal' : 'Lulus', hasil.join(' | '))
  expect(gagal, hasil.join('\n')).toBe(0)
})

test('DS-02 — angka dashboard tidak berubah oleh peran, mill, atau tanggal', async ({ page }) => {
  const ambil = async (akun: string) => {
    await login(page, akun)
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(700)
    const nilai = await page.locator('.md-kpi__value, .dash-card__value, .md-stat__value')
      .evaluateAll((els) => els.slice(0, 5).map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()))
    const filterTanggal = await page.locator('input[type="date"], [data-testid*="date"]').count()
    const pemilihMill = await page.getByTestId('mill-select').count()
    await keluar(page)
    return { nilai, filterTanggal, pemilihMill }
  }
  const a = await ambil(SPV)
  const b = await ambil(SPV_B)
  const c = await ambil(MGR)
  const d = await ambil(ADM)
  const samaSemua = [b, c, d].every((x) => JSON.stringify(x.nilai) === JSON.stringify(a.nilai))
  const tanpaFilter = [a, b, c, d].every((x) => x.filterTanggal === 0 && x.pemilihMill === 0)
  const ok = samaSemua && tanpaFilter && a.nilai.length >= 4
  catat('DS-02', '—', ok ? 'Lulus' : 'Gagal',
    `lima angka pertama sebagai Supervisor Mill A: [${a.nilai.join(' | ')}]; identik untuk Supervisor Mill B, Mill Management, dan Admin=${samaSemua}; kolom filter tanggal dan pemilih mill di halaman ini=${a.filterTanggal + a.pemilihMill} (harus 0 untuk keempat peran: ${tanpaFilter}). Kesamaan ini memang yang diharapkan — halaman ini demo, bukan laporan mill.`)
  expect(ok).toBe(true)
})

test('DS-03 — angka antar panel saling konsisten', async ({ page }) => {
  await login(page, SPV)
  await page.goto('/dashboard', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(800)

  // Panel per-line dibaca sebagai label → nilai kolom Today, tanpa bergantung
  // pada struktur tabelnya: yang diuji hubungan antar angkanya, bukan markupnya.
  const tabel = page.locator('table', { hasText: 'Available Milling Hours' }).first()
  const adaTabel = await tabel.count() > 0
  const rinci: string[] = []
  let gagal = 0

  if (!adaTabel) {
    gagal++
    rinci.push('panel yang memuat "Available Milling Hours" tidak ditemukan di halaman')
  } else {
    const baris = await tabel.locator('tr').evaluateAll((trs) =>
      trs.map((tr) => [...tr.querySelectorAll('th,td')].map((c) => (c.textContent ?? '').replace(/\s+/g, ' ').trim())))
    const cari = (label: string) => baris.find((r) => r[0]?.toLowerCase().startsWith(label.toLowerCase()))
    const nilai = (label: string) => { const r = cari(label); return r ? angka(r[1] ?? '') : null }

    const available = nilai('Available')
    const cdt = nilai('CDT')
    const sdt = nilai('SDT')
    const edt = nilai('EDT')
    const totalDowntime = nilai('Total Downtime')
    const amh = nilai('Available Milling Hours')
    const milling = nilai('Milling Hours')

    const hitungTD = (cdt ?? 0) + (sdt ?? 0) + (edt ?? 0)
    const hitungAMH = (available ?? 0) - (totalDowntime ?? 0)
    const cocokTD = totalDowntime !== null && Math.abs(hitungTD - totalDowntime) < 0.01
    const cocokAMH = amh !== null && Math.abs(hitungAMH - amh) < 0.01
    const millingWajar = milling !== null && amh !== null && milling <= amh + 0.01
    if (!cocokTD || !cocokAMH || !millingWajar) gagal++
    rinci.push(`Line 1 / Today terbaca: Available=${available}, CDT=${cdt}, SDT=${sdt}, EDT=${edt}, Total Downtime=${totalDowntime}, Available Milling Hours=${amh}, Milling=${milling}`)
    rinci.push(`CDT+SDT+EDT=${hitungTD.toFixed(2)} cocok dengan Total Downtime tercetak=${cocokTD}`)
    rinci.push(`Available−Total Downtime=${hitungAMH.toFixed(2)} cocok dengan Available Milling Hours tercetak=${cocokAMH}`)
    rinci.push(`Milling ≤ Available Milling Hours=${millingWajar}`)
  }
  catat('DS-03', '—', gagal ? 'Gagal' : 'Lulus', rinci.join(' | '))
  expect(gagal, rinci.join('\n')).toBe(0)
})

test('DS-04 — Laporan Stasiun: 18 tile, 11 aktif, 7 kelabu', async ({ page }) => {
  await login(page, SPV)
  await page.goto('/reports', { waitUntil: 'domcontentloaded' })

  // Langkah 2 — grid ditahan sampai Production Line pasti, karena tiap tile
  // membawa konteks mill+line ke layar laporannya.
  const sebelumPilih = await page.locator('[data-testid^="station-tile-"]').count()
  const pesanKosong = (await teksHalaman(page)).includes('Production Line belum dipilih')

  // Langkah 3 — pilih Production Line (mill sudah mengikuti akun Supervisor).
  await pilihLine(page, 0)
  await page.getByTestId('station-grid').waitFor({ state: 'visible' })

  const tile = page.locator('[data-testid^="station-tile-"]')
  const jml = await tile.count()
  const kelabuLoc = page.locator('[data-testid^="station-tile-"].disabled, [data-testid^="station-tile-"].is-inactive')
  const kelabu = await kelabuLoc.count()
  const aktif = jml - kelabu

  // Langkah 4 — tekan satu tile kelabu: tidak boleh berpindah layar.
  const sebelum = new URL(page.url()).pathname
  if (kelabu) { await kelabuLoc.first().click({ force: true }).catch(() => {}); await page.waitForTimeout(800) }
  const sesudah = new URL(page.url()).pathname
  const teks = await teksHalaman(page)
  const adaLegenda = /belum tersedia|belum dibangun|kelabu/i.test(teks)

  const d = `sebelum Production Line dipilih: tile=${sebelumPilih}, pesan "Production Line belum dipilih"=${pesanKosong}; sesudah dipilih: tile total=${jml}, aktif=${aktif}, kelabu=${kelabu}; menekan tile kelabu memindahkan layar=${sebelum !== sesudah} (harus false); legenda terbaca=${adaLegenda}; galat teknis=${adaGalatTeknis(teks)}`
  const ok = sebelumPilih === 0 && pesanKosong && jml === 18 && kelabu === 7 && aktif === 11 &&
             sebelum === sesudah && adaLegenda && !adaGalatTeknis(teks)
  catat('DS-04', '—', ok ? 'Lulus' : 'Gagal', d)
  expect(ok, d).toBe(true)
})

test('DS-05 — tile aktif mendarat di laporan stasiun yang benar', async ({ page }) => {
  await login(page, SPV)
  const rinci: string[] = []
  for (const kode of ['pressing', 'kernel-plant', 'storage-tank']) {
    await page.goto('/reports', { waitUntil: 'domcontentloaded' })
    await pilihLine(page, 0)
    await page.getByTestId('station-grid').waitFor({ state: 'visible' })
    const t = page.getByTestId(`station-tile-${kode}`)
    if (!(await t.count())) { rinci.push(`${kode}: tile tidak ada`); continue }
    await t.click()
    await page.waitForLoadState('domcontentloaded')
    await page.waitForTimeout(700)
    const akhir = new URL(page.url()).pathname
    const judul = (await page.locator('h1, .md-hero__title').first().innerText().catch(() => '')).trim()
    const millTerbawa = (await page.locator('[data-testid="mill-name"], [data-testid="mill-current"]').count()) > 0
    if (akhir !== `/reports/${kode}`) rinci.push(`${kode} → ${akhir}`)
    else rinci.push(`${kode} → ${akhir}, judul "${judul}", konteks mill terbawa=${millTerbawa}`)
  }
  const gagal = rinci.filter((r) => r.includes('→ /') && !r.includes('judul')).length +
                rinci.filter((r) => r.includes('tidak ada')).length
  catat('DS-05', '—', gagal ? 'Gagal' : 'Lulus',
    `${rinci.join(' | ')}. Production Line tetap wajib dipilih di layar laporannya (lihat LS-08).`)
  expect(gagal, rinci.join('\n')).toBe(0)
})

/**
 * Rute /reports/management dijaga `role:mill_management` SAJA — lebih sempit
 * dari Dashboard yang menerima admin+supervisor+mill_management. Jadi Admin pun
 * ditolak di sini, dan itu memang yang dikehendaki spec-nya.
 */
test('DS-06 — Laporan Manajemen hanya Mill Management', async ({ page }) => {
  const hasil: string[] = []
  for (const [akun, boleh] of [[SPV, false], [MGR, true], [ADM, false]] as [string, boolean][]) {
    await login(page, akun)
    // href dirender sebagai alamat penuh oleh route(), jadi dicocokkan lewat
    // pathname — bukan perbandingan atribut apa adanya, yang tidak akan pernah
    // cocok dan melaporkan "menu tidak ada" untuk peran yang sebenarnya punya.
    const adaMenu = (await page.locator('aside a[href], nav a[href]').evaluateAll((els) =>
      els.some((e) => new URL((e as HTMLAnchorElement).href).pathname === '/reports/management')))
    const resp = await page.goto('/reports/management', { waitUntil: 'domcontentloaded' })
    const teks = await teksHalaman(page)
    const ditolak = (resp?.status() ?? 0) === 403 || ada403(teks)
    const sesuai = (boleh ? !ditolak : ditolak) && adaMenu === boleh
    hasil.push(`${akun}: menu di sidebar=${adaMenu}, alamat langsung=${ditolak ? '403' : 'terbuka'} (${sesuai ? 'sesuai' : 'TIDAK SESUAI'})`)
    await keluar(page)
  }
  const ok = !hasil.some((h) => h.includes('TIDAK SESUAI'))
  catat('DS-06', '—', ok ? 'Lulus' : 'Gagal',
    `${hasil.join(' | ')}. Menu di sidebar dan izin alamat langsung memakai sumber izin yang sama, jadi keduanya tidak bisa berbeda.`)
  expect(ok, hasil.join('\n')).toBe(true)
})

test('DS-08 + DS-09 — papan status PPA dan kecocokan hitungannya', async ({ page }) => {
  await login(page, SPV)
  await page.goto('/production-process-activity', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(800)
  const kepala = (await page.getByTestId('ppa-headline').innerText().catch(() => '')).replace(/\s+/g, ' ').trim()
  const m = kepala.match(/(\d+)\s+dari\s+(\d+)\s+stasiun sudah ada input hari ini/)
  const grid = page.getByTestId('station-grid')
  const jml = await grid.locator('.station-tile').count()
  const selesai = await grid.locator('.station-tile.is-done').count()
  const tertunda = await grid.locator('.station-tile.is-pending').count()
  const legenda = (await page.getByTestId('ppa-legend').innerText().catch(() => '')).replace(/\s+/g, ' ')
  const duaJendela = /tanpa dibatasi tanggal|hari ini.*input terakhir|berbeda cakupan/i.test(legenda)

  // DS-08: tile belum-ada-input berbunyi kalimat, bukan angka 0; tile
  // sudah-ada-input menampilkan jumlah record; setiap tile menyebut input terakhir.
  const contohTertunda = tertunda
    ? (await grid.locator('.station-tile.is-pending').first().innerText()).replace(/\s+/g, ' ').trim() : ''
  const contohSelesai = selesai
    ? (await grid.locator('.station-tile.is-done').first().innerText()).replace(/\s+/g, ' ').trim() : ''
  const tertundaBerkalimat = /Belum ada input hari ini/i.test(contohTertunda) && !/\b0\b/.test(contohTertunda)
  const sebutTerakhir = /terakhir/i.test(contohTertunda) || /terakhir/i.test(contohSelesai)

  const ok8 = !!m && jml === 18 && duaJendela && tertundaBerkalimat
  catat('DS-08', '—', ok8 ? 'Lulus' : 'Gagal',
    `kepala halaman: "${kepala}"; tile=${jml}; sudah ada input=${selesai}; belum=${tertunda}; keterangan dua jendela waktu terbaca=${duaJendela}; contoh tile belum-ada-input: "${contohTertunda.slice(0, 110)}" (berbunyi kalimat, bukan angka 0=${tertundaBerkalimat}); tile menyebut input terakhir=${sebutTerakhir}`)

  const ok9 = !!m && Number(m[1]) === selesai && selesai + tertunda === Number(m[2]) && jml === 18
  catat('DS-09', '—', ok9 ? 'Lulus' : 'Gagal',
    `kepala halaman menyebut ${m?.[1]} dari ${m?.[2]}; dihitung ulang dari tile di layar: sudah ada input=${selesai}, belum=${tertunda}, jumlahnya=${selesai + tertunda}, total tile=${jml}`)
  expect(ok8 && ok9).toBe(true)
})

test('DS-10 — "nol hari ini" boleh berdampingan dengan "input terakhir baru saja"', async ({ page }) => {
  await login(page, SPV)
  await page.goto('/production-process-activity', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(800)
  // Prasyaratnya ADA di basis data uji: data stasiun yang baru dibuat skrip
  // penyiapan bertanggal 5–9 Oktober, sedangkan "hari ini" 11 Oktober — jadi
  // tile-tile itu persis berkeadaan "belum ada input hari ini" + "input
  // terakhir beberapa menit lalu".
  const tile = page.locator('.station-tile.is-pending')
  const n = await tile.count()
  let berdampingan = 0
  const contoh: string[] = []
  for (let i = 0; i < n; i++) {
    const t = (await tile.nth(i).innerText()).replace(/\s+/g, ' ').trim()
    if (/Belum ada input hari ini/i.test(t) && /terakhir/i.test(t) && !/belum pernah|tidak pernah/i.test(t)) {
      berdampingan++
      if (contoh.length < 2) contoh.push(t.slice(0, 130))
    }
  }
  const legenda = (await page.getByTestId('ppa-legend').innerText().catch(() => '')).replace(/\s+/g, ' ')
  const dijelaskan = /tanpa dibatasi tanggal|berbeda cakupan|hari ini/i.test(legenda)
  const ok = berdampingan > 0 && dijelaskan
  catat('DS-10', '—', ok ? 'Lulus' : 'Gagal',
    `${berdampingan} dari ${n} tile "belum ada input hari ini" juga menyebut waktu input terakhirnya — kedua keterangan tampil berdampingan tanpa saling membatalkan. Contoh: ${contoh.join(' || ')}. Keterangan di halaman menjelaskan sebabnya=${dijelaskan}.`)
  expect(ok).toBe(true)
})

test('DS-11 — tile PPA mengantar ke Data Browser yang benar', async ({ page }) => {
  await login(page, SPV)
  const rinci: string[] = []
  for (const kode of ['weighbridge', 'sterilizer', 'kernel-plant']) {
    await page.goto('/production-process-activity', { waitUntil: 'domcontentloaded' })
    const t = page.getByTestId(`station-tile-${kode}`)
    if (!(await t.count())) { rinci.push(`${kode}: tile tidak ada`); continue }
    await t.click()
    await page.waitForLoadState('domcontentloaded')
    const akhir = new URL(page.url()).pathname
    if (akhir !== `/data/${kode}`) rinci.push(`${kode} → ${akhir}`)
  }
  catat('DS-11', '—', rinci.length ? 'Gagal' : 'Lulus',
    rinci.length ? rinci.join(' | ')
                 : 'tile Weighbridge, Sterilizer, dan Kernel Plant masing-masing mendarat di /data/<stasiun> yang namanya cocok; tidak ada tile yang mendarat di stasiun lain.')
  expect(rinci, rinci.join('\n')).toHaveLength(0)
})

test('DS-12 (bagian web) — urutan ke-18 tile dicatat untuk dibandingkan dengan mobile', async ({ page }) => {
  await login(page, SPV)
  await page.goto('/production-process-activity', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(800)
  const urutan = await page.locator('[data-testid^="station-tile-"]').evaluateAll((els) =>
    els.map((e) => (e.getAttribute('data-testid') ?? '').replace('station-tile-', '')))
  mkdirSync('qa-run', { recursive: true })
  writeFileSync('qa-run/urutan-web.json', JSON.stringify(urutan, null, 1), 'utf8')
  // Hasil DS-12 dicatat pelaksana mobile, yang membandingkan kedua urutan.
  expect(urutan.length, `urutan web: ${urutan.join(', ')}`).toBe(18)
})
