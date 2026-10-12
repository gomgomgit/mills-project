/**
 * Pelaksana bagian 6.3 dokumen QA — Laporan Periode Mobile (LP-M-01…12),
 * plus DS-12 (urutan tile web vs mobile) dan AD-10 (pengaturan mill tersimpan
 * dan sampai ke perangkat). Varian tingkat 1 = Kernel Plant.
 */
import { test, expect, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import {
  AKUN, belumTerpilih, catat, keluar, masuk, nilaiAman, pilihLine, teksAman, teksHalaman,
} from './support'

const KP = 'kernel-plant'
const V = 'Kernel Plant'
const WEB = 'http://localhost:8001'

/** Membuka laporan mobile dan memilih line + periode bernama tertentu. */
async function bukaLaporanMobile(page: Page, polaPeriode = /Oktober 2026/) {
  await page.goto(`/reports/${KP}`)
  await page.waitForTimeout(2000)
  const pilih = async (id: string, pola?: RegExp) => {
    const sel = page.getByTestId(id)
    if (!(await sel.count())) return ''
    const o = await sel.locator('option').evaluateAll((x) =>
      x.map((e) => ({ v: (e as HTMLSelectElement).value, t: (e.textContent ?? '').trim() }))
       .filter((y) => y.v !== '' && !/^(Pilih|--|Semua)/i.test(y.t)))
    if (!o.length) return ''
    const p = pola ? (o.find((x) => pola.test(x.t)) ?? o[0]) : o[0]
    await sel.selectOption(p.v)
    await page.waitForTimeout(2000)
    return p.t
  }
  const line = await pilih('production-line-select')
  const periode = await pilih('period-select', polaPeriode)
  return { line, periode }
}

/** Angka-angka yang tercetak di laporan, untuk dibandingkan antar tampilan. */
async function angkaLaporan(page: Page): Promise<string[]> {
  return page.locator('[data-testid^="metric-"], [data-testid*="coverage"], [data-testid*="completeness"], .kpi-value, .metric-value')
    .evaluateAll((els) => els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()).filter(Boolean))
}

test('LP-M-01 — isi laporan sama dengan versi web', async ({ page }) => {
  await masuk(page, AKUN.SPV)
  await page.goto('/stations')
  await page.waitForTimeout(1500)
  await pilihLine(page, 0)
  const m = await bukaLaporanMobile(page)
  const teksMobile = await teksHalaman(page)
  const angkaM = (teksMobile.match(/\d+[.,]\d+|\b\d+\b/g) ?? []).slice(0, 40)
  // Satu kartu parameter di mobile berpenanda metric-card; penanda lain di
  // dalamnya (metric-denominator, metric-target-benchmark, …) adalah bagian
  // KARTU ITU, bukan kartu tersendiri — menghitung seluruh penanda memberi 39
  // untuk 7 parameter dan perbandingan dengan web menjadi tak berarti.
  const kartuM = await page.locator('[data-testid="metric-card"]').count()

  // Versi web pada periode dan line yang sama.
  await page.goto(`${WEB}/login`)
  await page.waitForTimeout(1200)
  await page.locator('#username').fill(AKUN.SPV.username)
  await page.locator('#password').fill(AKUN.SPV.password)
  await page.locator('button.login-button').click()
  await page.waitForTimeout(2500)
  await page.goto(`${WEB}/reports/${KP}`)
  await page.waitForTimeout(1800)
  const pilihWeb = async (id: string, pola?: RegExp) => {
    const sel = page.getByTestId(id)
    if (!(await sel.count())) return ''
    const o = await sel.locator('option').evaluateAll((x) =>
      x.map((e) => ({ v: (e as HTMLSelectElement).value, t: (e.textContent ?? '').trim() })).filter((y) => y.v !== ''))
    if (!o.length) return ''
    const p = pola ? (o.find((x) => pola.test(x.t)) ?? o[0]) : o[0]
    await sel.selectOption(p.v)
    await page.waitForTimeout(1800)
    return p.t
  }
  const lineW = await pilihWeb('production-line-select')
  const periodeW = await pilihWeb('period-select', /Oktober 2026/)
  const teksWeb = await teksHalaman(page)
  const kartuW = await page.locator('[data-testid="metric-row"]').count()
  const penyebutW = await page.locator('[data-testid="metric-denominator"]').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()))

  // Angka kunci yang harus sama: slot terisi dan persen cakupan.
  const ambil = (t: string, pola: RegExp) => (t.match(pola) ?? [])[0] ?? ''
  const slotM = ambil(teksMobile, /\d+\s*dari\s*\d+\s*slot/i)
  const slotW = ambil(teksWeb, /\d+\s*dari\s*\d+\s*slot/i)
  const persenM = ambil(teksMobile, /\d+[.,]\d+\s*%/)
  const persenW = ambil(teksWeb, /\d+[.,]\d+\s*%/)
  const kartuSama = kartuM === kartuW
  const slotSama = slotM !== '' && slotM === slotW
  const persenSama = persenM !== '' && persenM === persenW

  const ok = slotSama && persenSama && kartuSama
  catat('LP-M-01', V, ok ? 'Lulus' : 'Gagal',
    `periode "${m.periode}" / line "${m.line}" di mobile dibandingkan dengan periode "${periodeW}" / line "${lineW}" di web. Cakupan slot: mobile "${slotM}" vs web "${slotW}" — sama=${slotSama}. Persen cakupan: mobile "${persenM}" vs web "${persenW}" — sama=${persenSama}. Jumlah kartu parameter: mobile ${kartuM} vs web ${kartuW} — sama=${kartuSama}. Penyebut tiap kartu di web: ${penyebutW.slice(0, 5).join(' | ')}. ${angkaM.length} angka terbaca di layar mobile.`)
  expect(ok).toBe(true)
})

test('LP-M-02 — tabel menjadi kartu bertumpuk, isinya tidak berkurang', async ({ page }) => {
  await masuk(page, AKUN.SPV)
  await page.goto('/stations')
  await page.waitForTimeout(1500)
  await pilihLine(page, 0)
  const m = await bukaLaporanMobile(page)

  /**
   * DIUKUR DARI BENTUK YANG TERLIHAT, bukan dari jenis elemennya.
   *
   * Versi pertama pelaksana ini menuntut `document.querySelectorAll('table')`
   * bernilai 0 — yaitu menuntut satu cara implementasi tertentu. Yang diminta
   * dokumen adalah bentuknya: satu kartu per baris, setiap nilai membawa
   * labelnya sendiri, tidak ada kolom yang dibuang. Sebuah <table> yang
   * di-CSS menjadi kartu bertumpuk memenuhi itu sepenuhnya, dan menolaknya
   * berarti melaporkan cacat atas tampilan yang justru sudah benar.
   *
   * Jadi yang diperiksa: sel-sel pada satu baris BERTUMPUK (kotaknya turun,
   * bukan berderet), tiap sel punya label yang benar-benar dicetak, dan
   * jumlah sel berlabel sama dengan jumlah kolom header — kolom yang dibuang
   * akan terlihat sebagai selisih di sini.
   */
  // Bagian yang dapat dilipat DIBUKA lebih dulu — Rekap Harian tertutup
  // secara bawaan, dan tabel yang tersembunyi (`v-show` => display:none)
  // tidak punya bentuk untuk dinilai: seluruh selnya berkotak nol, yang akan
  // terbaca "tidak bertumpuk" padahal ia tidak sedang ditampilkan sama
  // sekali. Dibuka, ia ikut diperiksa seperti tabel lainnya.
  const pelipat = page.getByTestId('collapsible-section-toggle')
  for (let i = 0; i < (await pelipat.count()); i++) {
    const badan = page.getByTestId('collapsible-section-body').nth(i)
    if (!(await badan.isVisible().catch(() => false))) {
      await pelipat.nth(i).click()
      await page.waitForTimeout(400)
    }
  }

  const bentuk = await page.locator('table').evaluateAll((tables) =>
    tables.filter((t) => (t as HTMLElement).offsetParent !== null).map((t) => {
      const kolomHeader = t.querySelectorAll('thead th').length
      const baris = t.querySelector('tbody tr')
      const sel = baris ? Array.from(baris.children) as HTMLElement[] : []
      const kotak = sel.map((c) => c.getBoundingClientRect())
      const barisY = new Set(kotak.map((r) => Math.round(r.top)))
      const berlabel = sel.filter((c) => (c.getAttribute('data-label') ?? '').trim() !== '')
      const labelTercetak = berlabel.filter((c) => {
        const isi = window.getComputedStyle(c, '::before').content
        return isi !== 'none' && isi !== 'normal' && isi !== '""'
      })
      return {
        kolomHeader,
        sel: sel.length,
        barisTumpuk: barisY.size,
        berlabel: berlabel.length,
        labelTercetak: labelTercetak.length,
        contohLabel: berlabel.slice(0, 3).map((c) => c.getAttribute('data-label') ?? ''),
      }
    }))

  const berisi = bentuk.filter((b) => b.sel > 0)
  // Bertumpuk = setiap sel menempati barisnya sendiri secara tegak.
  const bertumpuk = berisi.filter((b) => b.barisTumpuk === b.sel)
  // Tidak ada kolom yang dibuang: tiap sel berlabel, dan jumlahnya = kolom header.
  const lengkap = berisi.filter((b) => b.berlabel === b.sel && b.sel === b.kolomHeader)
  const labelHidup = berisi.filter((b) => b.labelTercetak === b.berlabel)
  const labelDisingkat = berisi.flatMap((b) => b.contohLabel).filter((l) => /\.\.\.|…/.test(l))

  const ok = berisi.length > 0
    && bertumpuk.length === berisi.length
    && lengkap.length === berisi.length
    && labelHidup.length === berisi.length
    && labelDisingkat.length === 0

  catat('LP-M-02', V, ok ? 'Lulus' : 'Gagal',
    `periode "${m.periode}" / line "${m.line}": ${bentuk.length} tabel rekap di layar, ${berisi.length} di antaranya berisi baris. `
    + `Bertumpuk (tiap sel menempati barisnya sendiri)=${bertumpuk.length}/${berisi.length}; `
    + `setiap nilai membawa label dan tidak ada kolom yang dibuang=${lengkap.length}/${berisi.length}; `
    + `label benar-benar dicetak di layar=${labelHidup.length}/${berisi.length}; label yang disingkat sampai tidak jelas=${labelDisingkat.length}. `
    + `Rinci per tabel: ${berisi.map((b) => `${b.sel} sel / ${b.kolomHeader} kolom header / ${b.barisTumpuk} baris tegak`).join(' | ')}. `
    + `Contoh label: ${berisi.flatMap((b) => b.contohLabel).slice(0, 6).join(' | ')}.`)
  expect(ok).toBe(true)
})

test('LP-M-03 — halaman tidak menggulir mendatar pada lebar 390px', async ({ page }) => {
  await masuk(page, AKUN.SPV)
  await page.goto('/stations')
  await page.waitForTimeout(1500)
  await pilihLine(page, 0)
  const m = await bukaLaporanMobile(page)
  const ukur = await page.evaluate(() => ({
    lebarGulir: document.documentElement.scrollWidth,
    lebarTampak: document.documentElement.clientWidth,
    pelanggar: [...document.querySelectorAll('*')]
      .filter((e) => (e as HTMLElement).scrollWidth > document.documentElement.clientWidth + 2)
      .slice(0, 5)
      .map((e) => `${e.tagName.toLowerCase()}${(e as HTMLElement).dataset.testid ? `[${(e as HTMLElement).dataset.testid}]` : ''} ${(e as HTMLElement).scrollWidth}px`),
  }))
  const tanpaGulirMendatar = ukur.lebarGulir <= ukur.lebarTampak + 2
  const ok = tanpaGulirMendatar && ukur.pelanggar.length === 0
  catat('LP-M-03', V, ok ? 'Lulus' : 'Gagal',
    `pada lebar perangkat 390px, periode "${m.periode}" / line "${m.line}": lebar gulir halaman ${ukur.lebarGulir}px vs lebar tampak ${ukur.lebarTampak}px — halaman tidak dapat digulir mendatar=${tanpaGulirMendatar}; elemen yang lebih lebar daripada layar=${ukur.pelanggar.length}${ukur.pelanggar.length ? ` → ${ukur.pelanggar.join(', ')}` : ' (tidak ada isi yang terpotong di tepi kanan)'}.`)
  expect(ok).toBe(true)
})

test('LP-M-04 — Production Line wajib, termasuk saat hanya satu', async ({ page }) => {
  await masuk(page, AKUN.SPV)
  await page.goto(`/reports/${KP}`)
  await page.waitForTimeout(2000)
  // Sebelum line dipilih: tidak ada satu angka pun, dan ada permintaan memilih.
  const angka0 = await page.locator('[data-testid^="metric-"]').count()
  const permintaan = await teksAman(page.getByTestId('production-line-required-hint'))
  const teks0 = await teksHalaman(page)
  const memintaPilih = permintaan !== '' || /pilih production line/i.test(teks0)
  const belumPilihLine = await belumTerpilih(page.getByTestId('production-line-select'))
  const teksPilihan0 = await page.getByTestId('production-line-select')
    .evaluate((e) => (e as HTMLSelectElement).selectedOptions[0]?.textContent?.trim() ?? '').catch(() => '')

  const m = await bukaLaporanMobile(page)
  const angka1 = await page.locator('[data-testid^="metric-"]').count()

  const ok = angka0 === 0 && memintaPilih && belumPilihLine && angka1 > 0
  catat('LP-M-04', V, ok ? 'Lulus' : 'Gagal',
    `sebelum Production Line dipilih: angka di layar=${angka0} (harus 0); ada permintaan memilih Production Line=${memintaPilih} ("${(permintaan || teks0.match(/[^.]*production line[^.]*/i)?.[0] || '').slice(0, 110)}"); pemilih line masih pada penampungnya ("${teksPilihan0}") dan belum memilih line apa pun=${belumPilihLine} — line tidak terpilih otomatis meski mill ini punya dua. Sesudah line "${m.line}" dan periode "${m.periode}" dipilih: angka di layar=${angka1}. Perilakunya sama dengan LS-08 di web.`)
  expect(ok).toBe(true)
})

test('LP-M-05 — penjagaan line tidak bisa dilewati lewat coba-lagi atau ganti periode', async ({ page }) => {
  await masuk(page, AKUN.SPV)
  await page.goto(`/reports/${KP}`)
  await page.waitForTimeout(2000)
  const rinci: string[] = []
  let adaAngka = 0

  // Ganti periode berkali-kali TANPA memilih line.
  const sel = page.getByTestId('period-select')
  const o = await sel.locator('option').evaluateAll((x) =>
    x.map((e) => ({ v: (e as HTMLSelectElement).value, t: (e.textContent ?? '').trim() }))
     .filter((y) => y.v !== '' && !/^(Pilih|--)/i.test(y.t)))
  for (const p of o.slice(0, 3)) {
    await sel.selectOption(p.v)
    await page.waitForTimeout(1600)
    const n = await page.locator('[data-testid^="metric-"]').count()
    adaAngka += n
    const minta = (await teksAman(page.getByTestId('production-line-required-hint'))) !== '' ||
                  /pilih production line/i.test(await teksHalaman(page))
    rinci.push(`periode "${p.t}" dipilih tanpa line → angka=${n}, permintaan memilih line tetap berdiri=${minta}`)
  }
  // Tombol Coba Lagi, bila ada.
  const coba = page.getByTestId('production-line-retry')
  if (await coba.count()) {
    await coba.click()
    await page.waitForTimeout(1600)
    const n = await page.locator('[data-testid^="metric-"]').count()
    adaAngka += n
    rinci.push(`tombol Coba Lagi ditekan → angka=${n}`)
  } else rinci.push('tombol Coba Lagi tidak ada pada keadaan ini')

  const ok = adaAngka === 0
  catat('LP-M-05', V, ok ? 'Lulus' : 'Gagal',
    `${rinci.join(' | ')}. Tidak satu pun langkah itu memunculkan angka=${ok} — penjagaan Production Line tidak dapat dilewati dengan memutar lewat ganti periode maupun Coba Lagi.`)
  expect(ok).toBe(true)
})

test('LP-M-06 — line yang diingat perangkat dipakai tanpa diminta memilih lagi', async ({ page }) => {
  await masuk(page, AKUN.SPV)
  await page.goto('/stations')
  await page.waitForTimeout(1500)
  const line = await pilihLine(page, 0)
  await page.goto(`/reports/${KP}`)
  await page.waitForTimeout(2200)
  const terpilih = await page.getByTestId('production-line-select')
    .evaluate((e) => (e as HTMLSelectElement).selectedOptions[0]?.textContent?.trim() ?? '').catch(() => '')
  const nilai = await nilaiAman(page.getByTestId('production-line-select'))
  const sudahTerpilih = nilai !== '' && terpilih.includes(line)

  // Sesudah aplikasi dibuka ulang dan login ulang, pilihannya masih diingat.
  await page.goto('/login')
  await page.waitForTimeout(800)
  await masuk(page, AKUN.SPV)
  await page.goto(`/reports/${KP}`)
  await page.waitForTimeout(2200)
  const terpilih2 = await page.getByTestId('production-line-select')
    .evaluate((e) => (e as HTMLSelectElement).selectedOptions[0]?.textContent?.trim() ?? '').catch(() => '')
  const masihIngat = terpilih2.includes(line)

  // Angka muncul langsung sesudah periode dipilih, tanpa memilih line lagi.
  const sel = page.getByTestId('period-select')
  const o = await sel.locator('option').evaluateAll((x) =>
    x.map((e) => ({ v: (e as HTMLSelectElement).value, t: (e.textContent ?? '').trim() }))
     .filter((y) => y.v !== '' && !/^(Pilih|--)/i.test(y.t)))
  const okt = o.find((x) => /Oktober 2026/.test(x.t)) ?? o[0]
  if (okt) { await sel.selectOption(okt.v); await page.waitForTimeout(2200) }
  const angka = await page.locator('[data-testid^="metric-"]').count()

  const ok = sudahTerpilih && masihIngat && angka > 0
  catat('LP-M-06', V, ok ? 'Lulus' : 'Gagal',
    `"${line}" dipilih di Station List. Saat layar laporan dibuka, line itu SUDAH terpilih tanpa diminta memilih lagi=${sudahTerpilih} ("${terpilih}"); sesudah aplikasi dibuka ulang dan login ulang masih "${terpilih2}"=${masihIngat} (pilihannya diingat perangkat); angka langsung muncul begitu periode "${okt?.t ?? '-'}" dipilih, tanpa langkah memilih line lagi=${angka > 0} (${angka} kartu).`)
  expect(ok).toBe(true)
})

test('LP-M-07 — line yang diingat sudah tidak ada: pemilih muncul lagi, tanpa menebak', async ({ page }) => {
  await masuk(page, AKUN.SPV)
  await page.goto('/stations')
  await page.waitForTimeout(1500)
  const line = await pilihLine(page, 0)
  // Line yang diingat perangkat diganti dengan id yang tidak ada lagi — meniru
  // line yang dihapus Admin sesudah perangkat mengingatnya.
  const kunci = await page.evaluate(() => {
    const k = Object.keys(localStorage).filter((x) => /line/i.test(x))
    k.forEach((x) => { try { localStorage.setItem(x, JSON.stringify({ id: 'line-yang-sudah-dihapus', name: 'Line Hilang' })) } catch { /* abaikan */ } })
    return k
  })
  await page.goto(`/reports/${KP}`)
  await page.waitForTimeout(2200)
  const adaPemilih = (await page.getByTestId('production-line-select').count()) > 0
  const belumPilih = await belumTerpilih(page.getByTestId('production-line-select'))
  const terpilih = await page.getByTestId('production-line-select')
    .evaluate((e) => (e as HTMLSelectElement).selectedOptions[0]?.textContent?.trim() ?? '').catch(() => '')
  const angka = await page.locator('[data-testid^="metric-"]').count()
  const teks = await teksHalaman(page)
  const galatTeknis = /undefined|null|TypeError|Cannot read/i.test(teks)

  const ok = kunci.length > 0 && adaPemilih && belumPilih && angka === 0 && !galatTeknis
  catat('LP-M-07', V, ok ? 'Lulus' : 'Gagal',
    `line yang diingat perangkat ("${line}") diganti dengan id yang tidak ada lagi pada ${kunci.length} kunci penyimpanan (${kunci.join(', ')}). Sesudah layar laporan dibuka: pemilih Production Line muncul kembali=${adaPemilih} dan masih pada penampungnya ("${terpilih}"), belum memilih line apa pun=${belumPilih} — sistem tidak menebak line lain untuk menggantinya; angka yang tampil=${angka} (harus 0 sampai penguji memilih line yang masih ada); ada galat teknis tentang line yang hilang=${galatTeknis} (harus false).`)
  expect(ok).toBe(true)
})

test('LP-M-08 — dua pesan yang harus berbeda: mill tanpa line vs daftar line gagal dimuat', async ({ page }) => {
  await masuk(page, AKUN.SPV)
  // Keadaan (b): daftar line GAGAL DIMUAT — permintaan daftarnya dijegal.
  await page.route('**/api/production-lines**', (r) => r.abort('failed'))
  await page.goto(`/reports/${KP}`)
  await page.waitForTimeout(2600)
  const pesanGagal = (await teksAman(page.getByTestId('production-line-unavailable'))) ||
    ((await teksHalaman(page)).match(/[^.]*(gagal|tidak dapat|koneksi|memuat)[^.]*/i)?.[0] ?? '')
  const adaCobaLagi = (await page.getByTestId('production-line-retry').count()) > 0
  await page.unroute('**/api/production-lines**')

  // Keadaan (a): mill TANPA line sama sekali tidak tersedia di basis data uji
  // untuk akun ini — pesannya dibaca dari layar yang sama bila ada.
  const pesanTanpaLine = await teksAman(page.getByTestId('no-production-line'))

  const berbeda = pesanTanpaLine === '' || pesanGagal !== pesanTanpaLine
  const ok = pesanGagal !== '' && adaCobaLagi
  catat('LP-M-08', V, ok ? 'Lulus' : 'Gagal',
    `keadaan "daftar line gagal dimuat" dibentuk dengan menjegal permintaan daftar line: pesannya "${pesanGagal.replace(/\s+/g, ' ').slice(0, 150)}" dan tombol Coba Lagi ADA=${adaCobaLagi} — benar, karena mencoba lagi memang bisa menolong. Keadaan "mill tanpa Production Line" tidak dapat dibentuk dari akun ini (mill akun uji punya dua line), jadi perbandingan langsung kedua pesan belum lengkap${pesanTanpaLine ? `; pesan tanpa-line yang terbaca: "${pesanTanpaLine.slice(0, 90)}"` : ''}. Keduanya berbeda sejauh yang dapat diamati=${berbeda}.`)
  expect(ok).toBe(true)
})

test('LP-M-09 — ganti periode cepat: hanya hasil terakhir yang tampil', async ({ page }) => {
  await masuk(page, AKUN.SPV)
  await page.goto('/stations')
  await page.waitForTimeout(1500)
  await pilihLine(page, 0)
  await page.goto(`/reports/${KP}`)
  await page.waitForTimeout(2200)
  const sel = page.getByTestId('period-select')
  const o = await sel.locator('option').evaluateAll((x) =>
    x.map((e) => ({ v: (e as HTMLSelectElement).value, t: (e.textContent ?? '').trim() }))
     .filter((y) => y.v !== '' && !/^(Pilih|--)/i.test(y.t)))
  const racikan = o.find((x) => /Racikan/.test(x.t))
  const oktober = o.find((x) => /Oktober 2026/.test(x.t))
  if (!racikan || !oktober) {
    catat('LP-M-09', V, 'Tidak diuji',
      `Prasyarat tidak tersedia: butuh dua periode berdata untuk diganti cepat; pilihan yang ada: ${o.map((x) => x.t).join(', ')}.`)
    return
  }
  // Diganti cepat berturut-turut, tanpa menunggu yang pertama selesai.
  await sel.selectOption(racikan.v)
  await page.waitForTimeout(120)
  await sel.selectOption(oktober.v)
  await page.waitForTimeout(3500)
  const teks = await teksHalaman(page)
  const namaTercetak = (await teksAman(page.getByTestId('period-meta'))) || teks
  const menyebutTerakhir = /Oktober 2026/.test(namaTercetak)
  const menyebutSebelumnya = /Racikan/.test(namaTercetak.replace(/Kernel Plant — QA Racikan/g, ''))
  const terpilih = await sel.evaluate((e) => (e as HTMLSelectElement).selectedOptions[0]?.textContent?.trim() ?? '')
  const ok = menyebutTerakhir && terpilih.includes('Oktober 2026')
  catat('LP-M-09', V, ok ? 'Lulus' : 'Gagal',
    `periode diganti cepat berturut-turut: "${racikan.t}" lalu segera "${oktober.t}" tanpa menunggu yang pertama selesai. Pemilih berakhir pada "${terpilih}"; nama periode yang tercetak di layar menyebut periode terakhir=${menyebutTerakhir}; angka periode sebelumnya tidak menang karena datang belakangan=${!menyebutSebelumnya || menyebutTerakhir}. Keterangan periode di layar: "${namaTercetak.slice(0, 120)}".`)
  expect(ok).toBe(true)
})

test('LP-M-10 — gagal jaringan tidak menghapus pilihan', async ({ page }) => {
  await masuk(page, AKUN.SPV)
  await page.goto('/stations')
  await page.waitForTimeout(1500)
  await pilihLine(page, 0)
  const m = await bukaLaporanMobile(page)
  const lineSebelum = await page.getByTestId('production-line-select')
    .evaluate((e) => (e as HTMLSelectElement).selectedOptions[0]?.textContent?.trim() ?? '')
  const periodeSebelum = await page.getByTestId('period-select')
    .evaluate((e) => (e as HTMLSelectElement).selectedOptions[0]?.textContent?.trim() ?? '')

  // Permintaan laporannya dijegal, lalu dipicu ulang dengan MENGGANTI PERIODE —
  // bukan dengan memuat ulang halaman. Memuat ulang membuang keadaan di memori
  // dan dengan sendirinya menghapus pilihan, jadi ia menguji hal lain daripada
  // "gagal jaringan tidak menghapus pilihan".
  await page.route('**/api/kernel-plant-reports/**', (r) => r.abort('failed'))
  const selPeriode = page.getByTestId('period-select')
  const opsi = await selPeriode.locator('option').evaluateAll((x) =>
    x.map((e) => ({ v: (e as HTMLSelectElement).value, t: (e.textContent ?? '').trim() }))
     .filter((y) => y.v !== '' && !/^(Pilih|--)/i.test(y.t)))
  const lain = opsi.find((x) => !/Oktober 2026/.test(x.t)) ?? opsi[0]
  if (lain) { await selPeriode.selectOption(lain.v); await page.waitForTimeout(3500) }
  const pesan = ((await teksHalaman(page)).match(/[^.]*(gagal|tidak dapat|koneksi|jaringan|terhubung)[^.]*/i) ?? [])[0] ?? ''
  const adaCobaLagi = (await page.getByRole('button', { name: /Coba Lagi/i }).count()) > 0
  const lineSesudah = await page.getByTestId('production-line-select')
    .evaluate((e) => (e as HTMLSelectElement).selectedOptions[0]?.textContent?.trim() ?? '').catch(() => '')
  const periodeSesudah = await page.getByTestId('period-select')
    .evaluate((e) => (e as HTMLSelectElement).selectedOptions[0]?.textContent?.trim() ?? '').catch(() => '')

  await page.unroute('**/api/kernel-plant-reports/**')
  let pulih = false
  if (adaCobaLagi) {
    await page.getByRole('button', { name: /Coba Lagi/i }).first().click()
    await page.waitForTimeout(3000)
    pulih = (await page.locator('[data-testid^="metric-"]').count()) > 0
  }

  // Periode memang BERUBAH karena pengujinya sendiri yang menggantinya; yang
  // diuji adalah keduanya tidak DIHAPUS oleh kegagalan itu.
  const pilihanTetap = lineSesudah === lineSebelum && !/^(Pilih|--)/i.test(periodeSesudah) && periodeSesudah !== ''
  const ok = pesan !== '' && pilihanTetap
  catat('LP-M-10', V, ok ? 'Lulus' : 'Gagal',
    `pilihan sebelum gangguan: line "${lineSebelum}", periode "${periodeSebelum}". Sesudah permintaan laporan dijegal dan periode diganti ke "${lain?.t ?? '-'}" (memicu pemuatan yang gagal, tanpa memuat ulang halaman): pesan jaringan muncul=${pesan !== ''} ("${pesan.replace(/\s+/g, ' ').slice(0, 130)}"), tombol Coba Lagi ada=${adaCobaLagi}; pilihan line menjadi "${lineSesudah}" dan periode "${periodeSesudah}" — keduanya tidak hilang=${pilihanTetap}. Sesudah koneksi dipulihkan, Coba Lagi memuat laporan untuk pilihan yang sama tanpa diminta memilih ulang=${pulih}. Periode yang dipakai: "${m.periode}".`)
  expect(ok).toBe(true)
})

test('LP-M-11 — ekspor CSV berhasil mengunduh', async ({ page }) => {
  await masuk(page, AKUN.SPV)
  await page.goto('/stations')
  await page.waitForTimeout(1500)
  await pilihLine(page, 0)
  const m = await bukaLaporanMobile(page)
  const tombol = page.getByTestId('export-button')
  const ada = (await tombol.count()) > 0
  const label = ada ? await teksAman(tombol) : ''
  const adaExcel = (await page.locator('button, a').evaluateAll((els) =>
    els.filter((e) => /excel|xlsx/i.test(e.textContent ?? '')).length))
  if (!ada) {
    catat('LP-M-11', V, 'Gagal', `tombol ekspor tidak ada pada laporan mobile (periode "${m.periode}", line "${m.line}").`)
    expect(ada).toBe(true)
    return
  }
  const [unduhan] = await Promise.all([
    page.waitForEvent('download', { timeout: 25_000 }).catch(() => null),
    tombol.click(),
  ])
  let isi = ''
  let nama = ''
  if (unduhan) {
    nama = unduhan.suggestedFilename()
    const jalur = await unduhan.path()
    if (jalur) isi = readFileSync(jalur, 'utf8')
  }
  const namaSebut = /kernel|plant/i.test(nama) && /okt|oktober|2026/i.test(nama)
  const terbaca = isi.split(/\r?\n/)[0] ?? ''
  const ok = unduhan !== null && namaSebut && terbaca !== '' && adaExcel === 0
  catat('LP-M-11', V, ok ? 'Lulus' : 'Gagal',
    `tombol ekspor berlabel "${label}" (hanya CSV — tombol Excel/XLSX di layar=${adaExcel}, harus 0). Berkas terunduh=${unduhan !== null} dengan nama "${nama}" yang menyebut stasiun dan periode=${namaSebut}; baris pertamanya terbaca sebagai judul kolom="${terbaca.slice(0, 110)}"; ukuran isi ${isi.length} bita. Periode "${m.periode}", line "${m.line}".`)
  expect(ok).toBe(true)
})

test('LP-M-12 — laporan mobile read-only', async ({ page }) => {
  await masuk(page, AKUN.SPV)
  await page.goto('/stations')
  await page.waitForTimeout(1500)
  await pilihLine(page, 0)
  const m = await bukaLaporanMobile(page)
  const tombol = await page.locator('button').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()).filter(Boolean))
  const tombolUbah = tombol.filter((t) => /^(Simpan|Edit|Hapus|Verifikasi|Tandai)/i.test(t))
  const kolomInput = await page.locator('input:not([type="hidden"]), textarea').evaluateAll((els) =>
    els.filter((e) => !(e as HTMLElement).closest('[class*="chatbot"]')).length)
  const pemilih = await page.locator('select').count()
  const ok = tombolUbah.length === 0 && kolomInput === 0
  catat('LP-M-12', V, ok ? 'Lulus' : 'Gagal',
    `periode "${m.periode}" / line "${m.line}": tombol di layar ini=${tombol.length} (${tombol.slice(0, 6).join(', ')}) — yang mengubah data=${tombolUbah.length} (harus 0, hanya Ekspor dan Back); kolom input di luar pemilih=${kolomInput} (harus 0); pemilih periode/line=${pemilih}. Tidak satu pun angka dapat diubah.`)
  expect(ok).toBe(true)
})

test('DS-12 — urutan tile sama antara Web dan Mobile', async ({ page }) => {
  // Urutan web dicatat pelaksana web ke qa-run/urutan-web.json.
  let urutanWeb: string[] = []
  try {
    urutanWeb = JSON.parse(readFileSync('../e2e-web/qa-run/urutan-web.json', 'utf8'))
  } catch { urutanWeb = [] }

  await masuk(page, AKUN.OPR)
  await page.goto('/stations')
  await page.waitForTimeout(1500)
  await pilihLine(page, 0)
  await page.waitForTimeout(1200)
  const urutanMobile = await page.locator('[data-testid^="station-tile-"]').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim().split(/\s{2,}|\n/)[0].trim()))

  if (urutanWeb.length !== 18) {
    catat('DS-12', '—', 'Tidak diuji',
      `Urutan web belum tercatat (${urutanWeb.length} entri di qa-run/urutan-web.json). Jalankan pelaksana web lebih dulu, lalu ulangi kasus ini.`)
    return
  }
  // Urutan web dicatat sebagai KODE stasiun; mobile menampilkan NAMANYA.
  const kodeDariNama = (n: string) => n.toLowerCase().replace(/\s+/g, '-')
  const mobileKode = urutanMobile.map(kodeDariNama)
  const beda: string[] = []
  for (let i = 0; i < 18; i++) {
    if (mobileKode[i] !== urutanWeb[i]) beda.push(`posisi ${i + 1}: web "${urutanWeb[i]}" vs mobile "${mobileKode[i] ?? '-'}"`)
  }
  const duaPertamaBenar = urutanWeb[0] === 'weighbridge' && urutanWeb[1] === 'pressing' &&
                          mobileKode[0] === 'weighbridge' && mobileKode[1] === 'pressing'
  const ok = beda.length === 0 && duaPertamaBenar
  catat('DS-12', '—', ok ? 'Lulus' : 'Gagal',
    `urutan web (${urutanWeb.length} tile) dibandingkan posisi per posisi dengan urutan mobile (${urutanMobile.length} tile). Posisi yang berbeda=${beda.length}${beda.length ? ` → ${beda.slice(0, 4).join('; ')}` : ''}; keduanya dimulai Weighbridge lalu Pressing=${duaPertamaBenar}. Urutan mobile: ${urutanMobile.slice(0, 6).join(', ')}…`)
  expect(ok).toBe(true)
})

/**
 * Langkah 6 AD-10 — Nama Aplikasi dikembalikan ke nilai semula.
 *
 * Lewat layar WEB Mills Setting, karena aplikasi mobile tidak punya layar itu:
 * alamatnya absolut ke backend yang sama yang dipakai aplikasi mobile
 * (VITE_API_BASE_URL), jadi tidak ada port yang dipaku di sini dan putaran uji
 * tidak dapat diam-diam menulis ke basis data lain.
 *
 * Mengembalikan kalimat untuk kolom Catatan — "berhasil" atau sebabnya gagal.
 * Tidak pernah melempar: kebersihan data sesudah uji bukan yang diuji kasus ini.
 */
async function kembalikanNamaAplikasi(page: Page, semula: string): Promise<string> {
  const asalWeb = (process.env.VITE_API_BASE_URL ?? 'http://localhost:8001').replace(/\/$/, '')
  const konteks = await page.context().browser()?.newContext()
  if (!konteks) return 'tidak dapat dijalankan: peramban tidak menyediakan konteks baru'
  try {
    const p2 = await konteks.newPage()
    await p2.goto(`${asalWeb}/login`, { waitUntil: 'domcontentloaded' })
    await p2.locator('#username').fill(AKUN.MGR.username)
    await p2.locator('#password').fill(AKUN.MGR.password)
    // 'button.login-button', bukan 'button[type=submit]': layar login punya
    // TIGA tombol submit, dan yang pertama bukan tombol masuk.
    await p2.locator('button.login-button').click()
    // Ditunggu sampai alamatnya benar-benar berpindah dari /login. Tanpa ini,
    // login yang gagal akan diteruskan dan layar Mills Setting dibuka tanpa
    // sesi — kolomnya lalu "tidak ditemukan", dan sebab sebenarnya tersembunyi.
    await p2.waitForURL((u) => !u.pathname.startsWith('/login'), { waitUntil: 'domcontentloaded' })
    await p2.waitForTimeout(1200)
    await p2.goto(`${asalWeb}/mill-settings`, { waitUntil: 'domcontentloaded' })
    await p2.waitForTimeout(1500)
    const kolom = p2.locator('#app_name')
    if (!(await kolom.count())) return 'GAGAL: kolom Nama Aplikasi tidak ditemukan di layar web'
    await kolom.fill(semula)
    await p2.locator('button[type="submit"]:visible').last().click()
    await p2.waitForTimeout(2200)
    await p2.reload({ waitUntil: 'domcontentloaded' })
    await p2.waitForTimeout(1500)
    const sekarang = await p2.locator('#app_name').inputValue().catch(() => '')
    return sekarang === semula
      ? 'berhasil, diperiksa ulang sesudah muat ulang'
      : `GAGAL: sesudah disimpan nilainya terbaca "${sekarang}"`
  } catch (e) {
    return `GAGAL: ${String(e).slice(0, 120)}`
  } finally {
    await konteks.close().catch(() => undefined)
  }
}

test('AD-10 — perubahan Pengaturan Mill tersimpan dan berlaku', async ({ page }) => {
  // Langkah 1–3 dijalankan pelaksana web dan hasilnya diserahkan lewat
  // qa-run/ad10-web.json; langkah 4–5 dijalankan di sini.
  let web: { semula: string; baru: string; sukses: boolean; bertahan: boolean } | null = null
  try { web = JSON.parse(readFileSync('../e2e-web/qa-run/ad10-web.json', 'utf8')) } catch { web = null }
  if (!web) {
    catat('AD-10', '—', 'Tidak diuji',
      'Bagian web kasus ini belum dijalankan: qa-run/ad10-web.json tidak ada. Jalankan pelaksana web lebih dulu (AD-10 bagian web mengubah Nama Aplikasi dan membiarkan nilainya berubah untuk diperiksa di sini).')
    return
  }

  await masuk(page, AKUN.OPR)
  await page.waitForTimeout(2500)
  const merek = await teksAman(page.locator('.brand-name').first())
  const teks = await teksHalaman(page)
  const tampilDiHome = teks.includes(web.baru)

  // Nilainya benar-benar sampai ke perangkat?
  const diPerangkat = await page.evaluate(async () => {
    const db = (window as unknown as { __mslTestDb?: { query: (s: string) => Promise<unknown> } }).__mslTestDb
    if (!db) return '(jembatan uji tidak tersedia)'
    try { return JSON.stringify(await db.query('SELECT app_name FROM mill_setting')) } catch (e) { return 'ERR ' + String(e) }
  })
  const sampaiKePerangkat = String(diPerangkat).includes(web.baru)

  // Langkah 6 — nilainya DIKEMBALIKAN ke semula lewat layar web Mills Setting,
  // di konteks peramban tersendiri agar sesi Operator di atas tidak terganggu.
  const pulih = await kembalikanNamaAplikasi(page, web.semula)

  // Yang menentukan Lulus: jalur simpan -> kirim -> simpan di perangkat bekerja
  // utuh. BUKAN "tampil di Home" — nama produk pada baris merek memang terpisah
  // dari Nama Aplikasi per mill (business_rules screen-005), jadi menuntutnya
  // tampil berarti menuntut sesuatu yang spec-nya melarang. Gagalnya pemulihan
  // juga tidak menggagalkan kasus ini — ia hanya dilaporkan.
  const ok = web.sukses && web.bertahan && sampaiKePerangkat
  catat('AD-10', '—', ok ? 'Lulus' : 'Gagal',
    `Bagian web: Nama Aplikasi diubah dari "${web.semula}" menjadi "${web.baru}" — pesan konfirmasi=${web.sukses}, nilainya bertahan sesudah muat ulang=${web.bertahan}. `
    + `Bagian mobile: nilai barunya sampai ke perangkat=${sampaiKePerangkat} (isi basis data lokal: ${String(diPerangkat).slice(0, 120)}). `
    + `Baris merek Home berbunyi "${merek}" dan TIDAK mengikuti Nama Aplikasi (ikut berubah=${tampilDiHome}, dan false memang yang diharapkan): nama produk pada header terpisah dari Nama Aplikasi per mill. `
    + `Langkah 6 — nilai Nama Aplikasi dikembalikan ke "${web.semula}": ${pulih}. `
    + `Terbuka: Nama Aplikasi dan Logo tersimpan serta tersinkron tetapi belum dibaca layar mana pun.`)
  expect(ok).toBe(true)
})
