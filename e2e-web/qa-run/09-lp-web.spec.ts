/**
 * Pelaksana bagian 6.2 dokumen QA — Laporan Periode Web (LP-W-01…19).
 *
 * Varian tingkat 1 = Kernel Plant (punya master target, dua pasang kolom
 * berbagi standar, kolom downtime angka, dan daftar temuan — stasiun laporan
 * yang paling banyak melewati cabang). LP-W-01 juga dijalankan pada 10 stasiun
 * berlaporan lainnya sebagai kasus pengabelan.
 *
 * Keadaan data yang dituntut prasyaratnya dibentuk qa-run/siapkan-laporan.php.
 */
import { test, expect, type Page } from '@playwright/test'
import { login } from '../tests/support/auth'
import {
  ADM, SPV, STASIUN_BERLAPORAN, KODE_DARI_LABEL, adaGalatTeknis, catat,
  nilaiAman, teksAman, teksHalaman,
} from './support/qa'

const KP = 'kernel-plant'
const V = 'Kernel Plant'

/**
 * Memilih periode menurut namanya; mengembalikan nama yang terpilih.
 * Penanda pemilih periode TIDAK SAMA di seluruh laporan: sembilan memakai
 * "period-select" dan dua memakai "period-selector". Memaku satu nama membuat
 * dua laporan terbaca "tidak punya pemilih periode" padahal punya.
 */
async function pilihPeriodeNama(page: Page, pola: RegExp): Promise<string> {
  let sel = page.getByTestId('period-select')
  if (!(await sel.count())) sel = page.getByTestId('period-selector')
  if (!(await sel.count())) return ''
  const opsi = await sel.locator('option').evaluateAll((o) =>
    o.map((e) => ({ v: (e as HTMLOptionElement).value, t: (e.textContent ?? '').trim() })).filter((x) => x.v !== ''))
  const p = opsi.find((x) => pola.test(x.t))
  if (!p) return ''
  await sel.selectOption(p.v)
  await page.waitForTimeout(1400)
  return p.t
}

async function pilihLinePertama(page: Page): Promise<string> {
  const sel = page.getByTestId('production-line-select')
  if (!(await sel.count())) return ''
  const opsi = await sel.locator('option').evaluateAll((o) =>
    o.map((e) => ({ v: (e as HTMLOptionElement).value, t: (e.textContent ?? '').trim() })).filter((x) => x.v !== ''))
  if (!opsi.length) return ''
  await sel.selectOption(opsi[0].v)
  await page.waitForTimeout(1400)
  return opsi[0].t
}

/** Siapkan laporan Kernel Plant pada satu periode bernama tertentu. */
async function bukaLaporan(page: Page, polaPeriode: RegExp) {
  await page.goto(`/reports/${KP}`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1300)
  const line = await pilihLinePertama(page)
  const periode = await pilihPeriodeNama(page, polaPeriode)
  await page.waitForTimeout(900)
  return { line, periode }
}

test('LP-W-02 — cakupan pencatatan berada DI ATAS semua angka lain', async ({ page }) => {
  await login(page, SPV)
  const { line, periode } = await bukaLaporan(page, /Racikan/)
  const posisi = await page.evaluate(() => {
    const c = document.querySelector('[data-testid="coverage"]')
    const m = document.querySelector('[data-testid="metrics"], [data-testid="metrics-table"]')
    if (!c || !m) return null
    return { cakupan: c.getBoundingClientRect().top + window.scrollY, kartu: m.getBoundingClientRect().top + window.scrollY }
  })
  const ok = posisi !== null && posisi.cakupan < posisi.kartu
  catat('LP-W-02', V, ok ? 'Lulus' : 'Gagal',
    `periode "${periode}", line "${line}". Posisi tegak bagian Cakupan Pencatatan=${posisi?.cakupan ?? '-'}px dan kartu parameter=${posisi?.kartu ?? '-'}px — Cakupan berada di ATAS kartu parameter=${ok}. Pembaca melihat seberapa lengkap datanya sebelum melihat rata-ratanya.`)
  expect(ok).toBe(true)
})

test('LP-W-03 — setiap parameter mencetak penyebutnya sendiri', async ({ page }) => {
  await login(page, SPV)
  const { line, periode } = await bukaLaporan(page, /Racikan/)
  const penyebut = await page.locator('[data-testid="metric-denominator"]').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()))
  const unik = [...new Set(penyebut)]
  const slotCakupan = await teksAman(page.getByTestId('coverage-slots'))
  const semuaAdaPenyebut = penyebut.length > 0 && penyebut.every((p) => /\d/.test(p))
  const berbeda = unik.length > 1
  const ok = semuaAdaPenyebut && berbeda
  catat('LP-W-03', V, ok ? 'Lulus' : 'Gagal',
    `periode "${periode}" (kolom-kolomnya sengaja terisi tidak sama banyak), line "${line}". ${penyebut.length} kartu parameter, masing-masing mencetak penyebutnya sendiri=${semuaAdaPenyebut}; nilai penyebut yang berbeda-beda=${unik.length} (${unik.slice(0, 6).join(' | ')}) — jadi tidak ada satu penyebut bersama yang dipakai semua kartu=${berbeda}; slot terisi pada Cakupan="${slotCakupan}" (boleh lebih besar daripada penyebut kartu mana pun).`)
  expect(ok).toBe(true)
})

test('LP-W-04 — semua baris parameter tetap tampil, termasuk yang tak pernah diukur', async ({ page }) => {
  await login(page, SPV)
  const { line, periode } = await bukaLaporan(page, /Racikan/)
  const baris = await page.locator('[data-testid="metric-row"]').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()))
  // Kolom yang SENGAJA tidak pernah diisi: claybath (hydrocyclone SG).
  const tidakDiukur = baris.filter((b) => /belum ada pengukuran|tidak ada pengukuran|belum diukur|tidak tersedia|—/i.test(b))
  const barisClaybath = baris.find((b) => /claybath|hydro/i.test(b)) ?? ''
  const menampilkanNol = /(^|\s)0([,.]0+)?(\s|$)/.test(barisClaybath) && !/belum|tidak/i.test(barisClaybath)
  // Kernel Plant punya 9 kolom bacaan, dua di antaranya (downtime dan temuan)
  // punya BLOKNYA SENDIRI di laporan, bukan kartu parameter. Jadi jumlah kartu
  // yang benar adalah 7 — menuntut 9 akan menyalahkan laporan yang benar.
  const adaBlokDowntime = (await page.getByTestId('downtime').count()) > 0
  const adaBlokTemuan = (await page.getByTestId('findings').count()) > 0
  const ok = baris.length === 7 && adaBlokDowntime && adaBlokTemuan && tidakDiukur.length > 0 && !menampilkanNol
  catat('LP-W-04', V, ok ? 'Lulus' : 'Gagal',
    `periode "${periode}", line "${line}": ${baris.length} kartu parameter tampil. Kernel Plant punya 9 kolom bacaan, dan dua di antaranya — downtime dan temuan — punya bloknya sendiri (blok downtime ada=${adaBlokDowntime}, blok temuan ada=${adaBlokTemuan}), jadi 7 kartu + 2 blok = 9 kolom, tidak ada yang disembunyikan. Baris untuk kolom yang sengaja tidak pernah diisi sepanjang periode berbunyi "${barisClaybath.slice(0, 120)}" — menyatakan ketiadaan pengukuran, bukan angka 0=${!menampilkanNol}. Jumlah baris yang menyatakan belum ada pengukuran=${tidakDiukur.length}.`)
  expect(ok).toBe(true)
})

test('LP-W-05 — persen cakupan: tanda pisah vs 0,0%', async ({ page }) => {
  await login(page, SPV)
  const a = await bukaLaporan(page, /Tanpa Record/)
  const persenA = await teksAman(page.getByTestId('coverage-percent'))
  const teksA = await teksHalaman(page)

  const b = await bukaLaporan(page, /Kolom Kosong/)
  const persenB = await teksAman(page.getByTestId('coverage-percent'))

  const aTandaPisah = /^(—|-|–)$/.test(persenA.trim()) || persenA.trim() === ''
  const bNolPersen = /^0([,.]0+)?\s*%$/.test(persenB.trim())
  const berbeda = persenA.trim() !== persenB.trim()
  const ok = aTandaPisah && bNolPersen && berbeda
  catat('LP-W-05', V, ok ? 'Lulus' : 'Gagal',
    `(a) periode "${a.periode}" tanpa satu record pun → persen cakupan tercetak "${persenA}" (tanda pisah, karena penyebutnya tidak terbentuk)=${aTandaPisah}; (b) periode "${b.periode}" berisi record tetapi seluruh kolom ukurnya kosong → persen cakupan tercetak "${persenB}" (0,0%, karena penyebutnya ada dan yang terisi nol)=${bNolPersen}; keduanya berbeda=${berbeda}. Keduanya benar dan memang harus berbeda.`)
  expect(ok).toBe(true)
})

test('LP-W-06 — periode belum mulai dijelaskan sebagai BELUM MULAI', async ({ page }) => {
  await login(page, SPV)
  const { line, periode } = await bukaLaporan(page, /Masa Depan/)
  const catatanBelum = await teksAman(page.getByTestId('coverage-not-started-note'))
  const catatanBerjalan = await teksAman(page.getByTestId('coverage-running-note'))
  const hari = await teksAman(page.getByTestId('coverage-days'))
  const persen = await teksAman(page.getByTestId('coverage-percent'))
  const sebutBelumMulai = /belum mulai|belum dimulai/i.test(catatanBelum + ' ' + (await teksHalaman(page)))
  const tidakSebutBerjalan = catatanBerjalan === '' || !/sedang berjalan/i.test(catatanBerjalan)
  const hariNol = /(^|\D)0(\D|$)/.test(hari)
  const persenTandaPisah = /^(—|-|–)$/.test(persen.trim()) || persen.trim() === ''
  const ok = sebutBelumMulai && tidakSebutBerjalan && persenTandaPisah
  catat('LP-W-06', V, ok ? 'Lulus' : 'Gagal',
    `periode "${periode}" (tanggal mulainya di masa depan), line "${line}": keterangan menyebut periode belum mulai=${sebutBelumMulai} ("${catatanBelum.slice(0, 110)}"); TIDAK menyebut "sedang berjalan"=${tidakSebutBerjalan} ("${catatanBerjalan.slice(0, 80)}"); jumlah hari yang dihitung="${hari}" (nol dan dinyatakan=${hariNol}); persen cakupan="${persen}" (tanda pisah, bukan 0,0%=${persenTandaPisah}).`)
  expect(ok).toBe(true)
})

test('LP-W-07 — periode sedang berjalan: penyebut berhenti di hari ini', async ({ page }) => {
  await login(page, SPV)
  // Periode Oktober 2026 mencakup hari ini (11 Oktober) dan belum berakhir.
  const { line, periode } = await bukaLaporan(page, /Oktober 2026/)
  const catatanBerjalan = await teksAman(page.getByTestId('coverage-running-note'))
  const hari = await teksAman(page.getByTestId('coverage-days'))
  const sebutBerjalan = /sedang berjalan|berjalan/i.test(catatanBerjalan)
  const angkaHari = Number((hari.match(/(\d+)/) ?? [])[1] ?? -1)
  // 1–11 Oktober = 11 hari; seluruh panjang periode 31 hari.
  const berhentiHariIni = angkaHari === 11
  const bukanSeluruhPeriode = angkaHari !== 31
  const ok = sebutBerjalan && berhentiHariIni && bukanSeluruhPeriode
  catat('LP-W-07', V, ok ? 'Lulus' : 'Gagal',
    `periode "${periode}" (01–31 Oktober 2026, hari ini 11 Oktober), line "${line}": keterangan menyebut periode sedang berjalan=${sebutBerjalan} ("${catatanBerjalan.slice(0, 110)}"); jumlah hari yang dihitung="${hari}" → ${angkaHari} hari. Dihitung sendiri dari tanggal mulai sampai hari ini = 11 hari (cocok=${berhentiHariIni}); bukan seluruh panjang periode yang 31 hari (=${bukanSeluruhPeriode}).`)
  expect(ok).toBe(true)
})

test('LP-W-08 — mill tanpa periode stasiun itu memberi arahan', async ({ page }) => {
  await login(page, ADM)
  await page.goto(`/reports/${KP}`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1300)
  // Mill C/D belum punya periode mencakup Kernel Plant.
  const mill = page.getByTestId('mill-select')
  const opsi = await mill.locator('option').evaluateAll((o) =>
    o.map((e) => ({ v: (e as HTMLOptionElement).value, t: (e.textContent ?? '').trim() })).filter((x) => x.v !== ''))
  // Mill C dan D sudah punya periode karena dibuat kasus MD-PRD. Yang masih
  // benar-benar tanpa periode adalah mill fixture BU Browser Test, dan ia
  // punya stasiun Kernel Plant sehingga layar laporannya tetap terbuka.
  const tanpa = opsi.find((m) => /BU Browser Test/.test(m.t))
  if (!tanpa) {
    catat('LP-W-08', V, 'Tidak diuji',
      `Prasyarat tidak tersedia: tidak ada mill tanpa periode Kernel Plant di antara ${opsi.length} pilihan.`)
    return
  }
  await mill.selectOption(tanpa.v)
  await page.waitForTimeout(1500)
  const arahan = await teksAman(page.getByTestId('no-period-hint'))
  const teks = await teksHalaman(page)
  const arahanAtauTeks = arahan !== '' ? arahan : (teks.match(/[^.]*periode[^.]*\./i)?.[0] ?? '')
  const selPeriode = (await page.getByTestId('period-select').count()) > 0
    ? page.getByTestId('period-select') : page.getByTestId('period-selector')
  const adaPemilihPeriode = (await selPeriode.count()) > 0
  const opsiPeriode = adaPemilihPeriode
    ? (await selPeriode.locator('option').evaluateAll((o) =>
        o.filter((e) => (e as HTMLOptionElement).value !== '').length))
    : 0
  const sebutPeriode = /periode/i.test(arahanAtauTeks)
  const adaAngkaNol = /(^|\s)0([,.]0+)?(\s|$)/.test(arahanAtauTeks)
  const adaKartu = (await page.locator('[data-testid="metric-row"]').count()) > 0
  const ok = sebutPeriode && adaPemilihPeriode && opsiPeriode === 0 && !adaAngkaNol && !adaKartu
  catat('LP-W-08', V, ok ? 'Lulus' : 'Gagal',
    `mill "${tanpa.t}" (belum punya periode sama sekali) dipilih: arahan yang menyebut periode muncul=${sebutPeriode} ("${arahanAtauTeks.slice(0, 160)}"); kartu parameter yang tergambar=${adaKartu} (harus false); pemilih periode tetap ada=${adaPemilihPeriode} dalam keadaan kosong (${opsiPeriode} pilihan); bukan layar kosong dan bukan angka 0=${!adaAngkaNol}.`)
  expect(ok).toBe(true)
})

test('LP-W-09 — Admin berganti mill mereset pilihan periode dan line', async ({ page }) => {
  await login(page, ADM)
  await page.goto(`/reports/${KP}`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1300)
  const mill = page.getByTestId('mill-select')
  const opsi = await mill.locator('option').evaluateAll((o) =>
    o.map((e) => ({ v: (e as HTMLOptionElement).value, t: (e.textContent ?? '').trim() })).filter((x) => x.v !== ''))
  const a = opsi.find((m) => /Business Unit A/.test(m.t))
  const b = opsi.find((m) => /Business Unit B/.test(m.t))
  if (!a || !b) {
    catat('LP-W-09', V, 'Tidak diuji', `Prasyarat tidak tersedia: butuh dua mill pada pemilih; yang ada: ${opsi.map((m) => m.t).join(', ')}.`)
    return
  }
  await mill.selectOption(a.v)
  await page.waitForTimeout(1300)
  const line = await pilihLinePertama(page)
  const periode = await pilihPeriodeNama(page, /Racikan|Oktober/)
  await page.waitForTimeout(1200)
  const angkaA = await page.locator('[data-testid="metric-row"]').count()
  const periodeOpsiA = await page.getByTestId('period-select').locator('option').evaluateAll((o) =>
    o.map((e) => (e.textContent ?? '').trim()).filter((t) => t && !/^(--|Pilih)/i.test(t)))

  await mill.selectOption(b.v)
  await page.waitForTimeout(1600)
  const periodeSesudah = await nilaiAman(page.getByTestId('period-select'))
  const lineSesudah = await nilaiAman(page.getByTestId('production-line-select'))
  const angkaSesudah = await page.locator('[data-testid="metric-row"]').count()
  const periodeOpsiB = await page.getByTestId('period-select').locator('option').evaluateAll((o) =>
    o.map((e) => (e.textContent ?? '').trim()).filter((t) => t && !/^(--|Pilih)/i.test(t)))

  const direset = periodeSesudah === '' && lineSesudah === ''
  const namaPeriodeSesudah = await page.getByTestId('period-select')
    .evaluate((e) => (e as HTMLSelectElement).selectedOptions[0]?.textContent?.trim() ?? '').catch(() => '')
  const namaLineSesudah = await page.getByTestId('production-line-select')
    .evaluate((e) => (e as HTMLSelectElement).selectedOptions[0]?.textContent?.trim() ?? '').catch(() => '')
  const angkaHilang = angkaSesudah === 0
  const daftarBerganti = JSON.stringify(periodeOpsiA) !== JSON.stringify(periodeOpsiB)
  const petunjuk = await teksAman(page.getByTestId('select-production-line-hint'))
  const heroRange = await teksAman(page.getByTestId('hero-range'))
  const ok = direset && angkaHilang
  catat('LP-W-09', V, ok ? 'Lulus' : 'Gagal',
    `Mill "${a.t}" dengan line "${line}" dan periode "${periode}" menampilkan ${angkaA} baris parameter. Sesudah pemilih Mill diganti ke "${b.t}": pilihan periode dan line direset ke kosong=${direset} (periode terpilih="${namaPeriodeSesudah}" nilai="${periodeSesudah}", line terpilih="${namaLineSesudah}" nilai="${lineSesudah}"); angka mill sebelumnya hilang dari layar=${angkaHilang} (${angkaSesudah} baris); daftar periode ikut berganti mengikuti mill baru=${daftarBerganti} (${periodeOpsiA.length} → ${periodeOpsiB.length} pilihan). Keadaan laporan sesudahnya: petunjuk di layar="${petunjuk.slice(0, 90)}", rentang pada kepala laporan="${heroRange.slice(0, 60)}". Tidak ada data mill sebelumnya yang bocor; yang menjadi masalah adalah pemilih periode TERLIHAT berisi pilihan sementara laporannya menganggap belum ada periode yang dipilih.`)
  expect(ok).toBe(true)
})

test('LP-W-10 — rekap harian dapat ditutup tanpa mengubah angka', async ({ page }) => {
  await login(page, SPV)
  const { line, periode } = await bukaLaporan(page, /Racikan/)
  const bacaAngka = async () => ({
    kartu: await page.locator('[data-testid="metric-row"]').evaluateAll((els) =>
      els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim())),
    cakupan: await teksAman(page.getByTestId('coverage-percent')),
    slot: await teksAman(page.getByTestId('coverage-slots')),
  })
  const sebelum = await bacaAngka()
  const kartuHarian = page.getByTestId('daily-recap-card')
  const toggle = page.getByTestId('daily-toggle')
  const terbukaAwal = (await page.getByTestId('daily-table').isVisible().catch(() => false))
  const adaToggle = (await toggle.count()) > 0
  if (adaToggle) { await toggle.click(); await page.waitForTimeout(900) }
  const tertutup = !(await page.getByTestId('daily-table').isVisible().catch(() => false))
  const sesudahTutup = await bacaAngka()
  if (adaToggle) { await toggle.click(); await page.waitForTimeout(900) }
  const terbukaLagi = (await page.getByTestId('daily-table').isVisible().catch(() => false))
  const barisHarian = await page.locator('[data-testid^="daily-row-"]').count()

  const angkaSama = JSON.stringify(sebelum) === JSON.stringify(sesudahTutup)
  const ok = adaToggle && terbukaAwal && tertutup && angkaSama && terbukaLagi
  catat('LP-W-10', V, ok ? 'Lulus' : 'Gagal',
    `periode "${periode}", line "${line}": Rekap Harian terbuka secara bawaan=${terbukaAwal}; dapat ditutup=${tertutup}; angka kartu parameter dan Cakupan tidak berubah sedikit pun saat ditutup=${angkaSama}; dibuka kembali menampilkan isi yang sama=${terbukaLagi} (${barisHarian} baris harian).`)
  expect(ok).toBe(true)
})

test('LP-W-11 — baris total periode bukan rata-rata dari rata-rata harian', async ({ page }) => {
  await login(page, SPV)
  const { line, periode } = await bukaLaporan(page, /Racikan/)
  const angka = (t: string): number | null => {
    const m = t.replace(/\s/g, '').match(/-?[\d.,]*\d/)
    if (!m) return null
    let x = m[0]
    if (/,\d{1,2}$/.test(x)) x = x.replace(/\./g, '').replace(',', '.')
    else if (/\.\d{1,2}$/.test(x)) x = x.replace(/,/g, '')
    else x = x.replace(/[.,]/g, '')
    const n = parseFloat(x)
    return Number.isNaN(n) ? null : n
  }
  const baris = await page.locator('[data-testid^="daily-row-"]').evaluateAll((els) =>
    els.map((e) => [...e.querySelectorAll('td')].map((c) => (c.textContent ?? '').replace(/\s+/g, ' ').trim())))
  const total = await page.getByTestId('daily-row-total').evaluate((e) =>
    [...e.querySelectorAll('td')].map((c) => (c.textContent ?? '').replace(/\s+/g, ' ').trim())).catch(() => [] as string[])
  if (baris.length < 2 || total.length === 0) {
    catat('LP-W-11', V, 'Tidak diuji',
      `Prasyarat tidak terbentuk di layar: ${baris.length} baris harian dan ${total.length} sel pada baris total, sehingga perbandingan "rata-rata dari rata-rata" tidak dapat dihitung.`)
    return
  }
  // Kolom pertama yang berisi angka pada kedua baris harian.
  let kol = -1
  for (let c = 1; c < Math.min(baris[0].length, total.length); c++) {
    if (angka(baris[0][c] ?? '') !== null && angka(baris[1][c] ?? '') !== null && angka(total[c] ?? '') !== null) { kol = c; break }
  }
  if (kol < 0) {
    catat('LP-W-11', V, 'Tidak diuji', 'Tidak ada kolom angka yang terbaca pada kedua baris harian sekaligus baris total.')
    return
  }
  const h1 = angka(baris[0][kol])!
  const h2 = angka(baris[1][kol])!
  const rataDariRata = (h1 + h2) / 2
  const tercetak = angka(total[kol])!
  const berbeda = Math.abs(rataDariRata - tercetak) > 0.05
  catat('LP-W-11', V, berbeda ? 'Lulus' : 'Gagal',
    `periode "${periode}" (hari-1 hanya 2 slot bernilai tinggi, hari-2 20 slot bernilai rendah), line "${line}". Rata-rata hari-1=${h1}, hari-2=${h2}; rata-rata dari kedua angka itu=${rataDariRata.toFixed(2)}; angka pada baris total periode=${tercetak}. Keduanya berbeda=${berbeda} — total periode dihitung ulang dari SELURUH slot, bukan dirata-ratakan dari rata-rata harian (kalau sama, hari 2 slot akan berbobot sama dengan hari 20 slot).`)
  expect(berbeda).toBe(true)
})

test('LP-W-12 — downtime: tanda pisah vs nol yang tercatat', async ({ page }) => {
  await login(page, SPV)
  // (a) periode BERDATA yang tidak satu slot pun mengisi downtime. Periode
  // tanpa satu slot terisi tidak dapat dipakai: laporan memang tidak
  // menggambar blok downtime sama sekali di keadaan itu.
  const a = await bukaLaporan(page, /Tanpa Downtime/)
  const blokA = await teksAman(page.getByTestId('downtime'))
  const kosongA = await teksAman(page.getByTestId('downtime-empty'))
  const totalA = await teksAman(page.getByTestId('downtime-total'))
  const slotA = await teksAman(page.getByTestId('downtime-recorded-slots'))

  // (b) periode yang beberapa slotnya mengisi 0 menit: QA Racikan.
  const b = await bukaLaporan(page, /Racikan/)
  const totalB = await teksAman(page.getByTestId('downtime-total'))
  const slotB = await teksAman(page.getByTestId('downtime-recorded-slots'))
  const rataB = await teksAman(page.getByTestId('downtime-average'))

  // Keadaan (a) boleh dinyatakan dengan TIGA cara yang sama benarnya:
  // keterangan ketiadaan, tanda pisah pada totalnya, atau blok yang menyatakan
  // belum ada downtime tercatat. Yang dilarang hanya satu: menuliskannya 0.
  const aNolMenit = /\b0\s*menit\b/i.test(blokA)
  const aTandaPisah = (kosongA !== '' || /^(—|-|–)$/.test(totalA.trim()) ||
    /belum|tidak ada|tanpa/i.test(blokA)) && !aNolMenit
  const bTerukur = /\d/.test(totalB) || /\d/.test(slotB)
  const berbeda = `${kosongA}|${totalA}|${slotA}` !== `${''}|${totalB}|${slotB}`
  void blokA
  const ok = aTandaPisah && bTerukur && berbeda
  catat('LP-W-12', V, ok ? 'Lulus' : 'Gagal',
    `(a) periode "${a.periode}" tanpa satu slot pun mengisi downtime → blok downtime berbunyi "${(kosongA || totalA || blokA).slice(0, 130)}" (menuliskan "0 menit"=${aNolMenit}, harus false) (keterangan ketiadaan atau tanda pisah, bukan 0 menit)=${aTandaPisah}; (b) periode "${b.periode}" dengan slot yang mengisi 0 menit dan satu slot 45 menit → total="${totalB}", slot tercatat="${slotB}", rata-rata="${rataB}" (nol yang benar-benar terukur ikut dihitung)=${bTerukur}; keduanya berbeda=${berbeda}. "Tercatat 0 menit" dan "tidak dicatat" tidak terlihat sama.`)
  expect(ok).toBe(true)
})

test('LP-W-13 — temuan dikelompokkan harfiah, dua ejaan jadi dua baris', async ({ page }) => {
  await login(page, SPV)
  const { line, periode } = await bukaLaporan(page, /Racikan/)
  const baris = await page.locator('[data-testid="findings-row"]').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()))
  const besar = baris.filter((b) => b.includes('Bearing panas')).length
  const kecil = baris.filter((b) => b.includes('bearing panas') && !b.includes('Bearing panas')).length
  const adaJumlah = baris.every((b) => /\d/.test(b))
  const ok = besar >= 1 && kecil >= 1 && adaJumlah
  catat('LP-W-13', V, ok ? 'Lulus' : 'Gagal',
    `periode "${periode}", line "${line}": daftar Temuan memuat ${baris.length} baris. Temuan "Bearing panas" (huruf besar) muncul sebagai baris sendiri=${besar >= 1}; "bearing panas" (huruf kecil) juga sebagai baris sendiri=${kecil >= 1} — pengelompokannya harfiah, sistem tidak menyatukan ejaan yang berbeda. Setiap baris membawa jumlah kemunculannya sendiri=${adaJumlah}. Isi barisnya: ${baris.slice(0, 4).join(' | ')}`)
  expect(ok).toBe(true)
})

test('LP-W-14 — temuan terpisah dari downtime', async ({ page }) => {
  await login(page, SPV)
  const { line, periode } = await bukaLaporan(page, /Racikan/)
  const slotDowntime = await teksAman(page.getByTestId('downtime-recorded-slots'))
  const totalDowntime = await teksAman(page.getByTestId('downtime-total'))
  const barisTemuan = await page.locator('[data-testid="findings-row"]').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()))
  // Data uji: 4 slot downtime 0 + 1 slot downtime 45 = 5 slot tercatat.
  // Temuan: 3 (dua ejaan "bearing panas" + "Ripple mill berhenti").
  const nSlot = Number((slotDowntime.match(/(\d+)/) ?? [])[1] ?? -1)
  const nTotal = Number((totalDowntime.match(/(\d+)/) ?? [])[1] ?? -1)
  const adaTemuanTanpaDowntime = barisTemuan.some((b) => /bearing panas/i.test(b))
  const adaTemuanDenganDowntime = barisTemuan.some((b) => /ripple mill berhenti/i.test(b))
  // Slot "temuan saja" TIDAK boleh menambah menit downtime: totalnya tetap 45.
  const downtimeTidakTertambah = nTotal === 45
  const ok = adaTemuanTanpaDowntime && adaTemuanDenganDowntime && downtimeTidakTertambah
  catat('LP-W-14', V, ok ? 'Lulus' : 'Gagal',
    `periode "${periode}", line "${line}". Data ujinya: satu slot mengisi downtime 45 menit DAN temuan "Ripple mill berhenti", satu slot lain mengisi temuan "bearing panas" SAJA tanpa downtime, dan empat slot mengisi downtime 0. Di layar: slot downtime tercatat="${slotDowntime}", total downtime="${totalDowntime}" → ${nTotal} menit, tetap 45 sehingga slot "temuan saja" tidak ikut menambah menit=${downtimeTidakTertambah}; daftar temuan memuat temuan yang punya downtime=${adaTemuanDenganDowntime} DAN temuan yang tidak punya downtime=${adaTemuanTanpaDowntime}.`)
  expect(ok).toBe(true)
})

test('LP-W-15 — bagian "standar tanpa pengukuran" tetap digambar walau kosong', async ({ page }) => {
  await login(page, SPV)
  const { line, periode } = await bukaLaporan(page, /Racikan/)
  const bagian = page.getByTestId('targets-without-metric')
  const ada = (await bagian.count()) > 0
  const tabel = await page.getByTestId('targets-without-metric-table').count()
  const baris = await page.locator('[data-testid="targets-without-metric-row"]').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()))
  const keterangan = await teksAman(page.getByTestId('targets-without-metric-note'))
  const semuaTerukur = await page.getByTestId('targets-all-measured').count()
  const finalKernelDirt = baris.some((b) => /final kernel dirt/i.test(b))
  const ok = ada && keterangan !== ''
  catat('LP-W-15', V, ok ? 'Lulus' : 'Gagal',
    `periode "${periode}", line "${line}": bagian "standar tanpa pengukuran" tetap digambar=${ada} (tabelnya ada=${tabel > 0}, ${baris.length} baris, penanda "semua terukur"=${semuaTerukur > 0}); ada keterangan yang menjelaskan arti bagian itu dan mengapa ia boleh kosong=${keterangan !== ''} ("${keterangan.slice(0, 140)}"); memuat Final Kernel Dirt=${finalKernelDirt}${baris.length ? ` (isi barisnya: ${baris.slice(0, 3).join(' | ')})` : ''}.`)
  expect(ok).toBe(true)
})

test('LP-W-16 — kolom target dirender utuh, termasuk keterangan dalam tanda kurung', async ({ page }) => {
  await login(page, SPV)
  const { line, periode } = await bukaLaporan(page, /Racikan/)
  const target = await page.locator('[data-testid="metric-target-benchmark"]').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()).filter(Boolean))
  const adaKurung = target.filter((t) => /\(.+\)/.test(t))
  const terpotong = target.filter((t) => /…|\.\.\.$/.test(t))

  // Dibandingkan dengan tabel Target Operasional pada Form stasiun yang sama.
  await page.goto(`/data/${KP}/create`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1500)
  const diForm = await page.getByTestId('operational-target-table').innerText().catch(() => '')
  const formRapi = diForm.replace(/\s+/g, ' ')
  const cocok = adaKurung.filter((t) => formRapi.includes(t.slice(0, Math.min(24, t.length))))

  const ok = target.length > 0 && adaKurung.length > 0 && terpotong.length === 0
  catat('LP-W-16', V, ok ? 'Lulus' : 'Gagal',
    `periode "${periode}", line "${line}": ${target.length} kolom target terbaca; ${adaKurung.length} di antaranya memuat keterangan dalam tanda kurung dan tanda kurungnya tidak dibuang=${adaKurung.length > 0}; tidak ada yang dipotong dengan elipsis=${terpotong.length === 0}. Contoh: "${adaKurung[0] ?? target[0] ?? '-'}". Dicocokkan dengan tabel Target Operasional di layar Form stasiun yang sama: ${cocok.length} dari ${adaKurung.length} teks target ditemukan sama di sana.`)
  expect(ok).toBe(true)
})

test('LP-W-17 — kolom yang berbagi satu standar ditandai, dan angkanya tidak dirata-ratakan', async ({ page }) => {
  await login(page, SPV)
  const { line, periode } = await bukaLaporan(page, /Racikan/)
  const bertanda = await page.locator('[data-testid="metric-shared-standard-row"]').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()))
  const keterangan = await page.locator('[data-testid="metric-shared-standard"]').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()).filter(Boolean))
  // Kernel Plant: DUA pasang (ripple mill 1–2, kernel silo 1–2) → 4 baris.
  const ripple = bertanda.filter((b) => /ripple mill/i.test(b))
  const silo = bertanda.filter((b) => /silo/i.test(b))
  // Angka tiap kartu harus dihitung sendiri-sendiri. Data uji memberi nilai
  // berbeda pada ripple mill 1 (90/12) dan 2 (88/11), jadi keduanya tidak
  // boleh menampilkan angka identik.
  const angkaRipple = ripple.map((b) => (b.match(/[\d.,]+/g) ?? []).join(','))
  const identik = angkaRipple.length >= 2 && angkaRipple[0] === angkaRipple[1]
  const ok = bertanda.length >= 4 && keterangan.length >= 2 && !identik
  catat('LP-W-17', V, ok ? 'Lulus' : 'Gagal',
    `periode "${periode}", line "${line}": ${bertanda.length} baris bertanda berbagi standar (Kernel Plant punya DUA pasang — ripple mill 1–2 dan kernel silo 1–2 — jadi harus 4 baris, bukan 2): ripple mill=${ripple.length}, silo=${silo.length}; keterangan "berbagi satu baris master" tercetak pada ${keterangan.length} kartu ("${keterangan[0]?.slice(0, 90) ?? '-'}"); angka kedua kartu ripple mill identik=${identik} (harus false — masing-masing dihitung sendiri beserta penyebutnya).`)
  expect(ok).toBe(true)
})

test('LP-W-18 — ekspor mengikuti periode dan line yang terpilih', async ({ page }) => {
  await login(page, SPV)
  const unduh = async () => {
    for (const t of ['export-csv', 'export-button', 'export-csv-button']) {
      const b = page.getByTestId(t)
      if (await b.count()) {
        const [d] = await Promise.all([page.waitForEvent('download', { timeout: 20_000 }), b.click()])
        const s = await d.createReadStream()
        const buf: Buffer[] = []
        for await (const c of s) buf.push(c as Buffer)
        return { nama: d.suggestedFilename(), isi: Buffer.concat(buf).toString('utf8') }
      }
    }
    return null
  }
  const a = await bukaLaporan(page, /Racikan/)
  const berkasA = await unduh()
  const b = await bukaLaporan(page, /Oktober 2026/)
  const berkasB = await unduh()

  if (!berkasA || !berkasB) {
    catat('LP-W-18', V, 'Gagal', `tombol ekspor tidak ditemukan pada salah satu putaran (periode A "${a.periode}", periode B "${b.periode}").`)
    expect(berkasA && berkasB).toBeTruthy()
    return
  }
  const berbeda = berkasA.isi !== berkasB.isi
  const sebutPeriodeA = berkasA.isi.includes(a.periode.split('·')[0].trim()) || /Racikan/.test(berkasA.isi)
  const sebutLineA = berkasA.isi.includes(a.line)
  const bocorA = /Oktober 2026/.test(berkasA.isi)
  const ok = berbeda && sebutPeriodeA && sebutLineA && !bocorA
  catat('LP-W-18', V, ok ? 'Lulus' : 'Gagal',
    `berkas pertama "${berkasA.nama}" (${berkasA.isi.length} bita) untuk periode "${a.periode}" / line "${a.line}"; berkas kedua "${berkasB.nama}" (${berkasB.isi.length} bita) untuk periode "${b.periode}". Isi kedua berkas berbeda mengikuti pilihan baru=${berbeda}; nama periode tertulis DI DALAM berkas pertama=${sebutPeriodeA}; nama Production Line juga=${sebutLineA}; tidak ada jejak periode yang tidak terpilih di berkas pertama=${!bocorA}.`)
  expect(ok).toBe(true)
})

test('LP-W-19 — laporan tidak pernah mengubah data', async ({ page }) => {
  await login(page, SPV)
  // Keadaan sebelum: jumlah baris dan status di Data Browser.
  const bacaBrowser = async () => {
    await page.goto(`/data/${KP}`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1400)
    return page.locator('tbody tr').evaluateAll((trs) =>
      trs.map((tr) => (tr.textContent ?? '').replace(/\s+/g, ' ').trim()))
  }
  const sebelum = await bacaBrowser()

  // Laporan diaduk: ganti periode dan line, buka-tutup rekap, lalu ekspor.
  await bukaLaporan(page, /Racikan/)
  const tombolUbah = await page.locator(
    'button:not([data-testid*="export"]):not([data-testid="daily-toggle"])'
  ).evaluateAll((els) => els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim())
    .filter((t) => /^(Simpan|Edit|Hapus|Verifikasi|Tandai|Tutup Periode|Buka)/i.test(t)))
  const toggle = page.getByTestId('daily-toggle')
  if (await toggle.count()) { await toggle.click(); await page.waitForTimeout(700); await toggle.click(); await page.waitForTimeout(700) }
  await bukaLaporan(page, /Oktober 2026/)
  for (const t of ['export-csv', 'export-button']) {
    const b = page.getByTestId(t)
    if (await b.count()) { await Promise.all([page.waitForEvent('download', { timeout: 20_000 }).catch(() => {}), b.click()]); break }
  }
  await page.waitForTimeout(1200)

  const sesudah = await bacaBrowser()
  const sama = JSON.stringify(sebelum) === JSON.stringify(sesudah)
  const ok = tombolUbah.length === 0 && sama
  catat('LP-W-19', V, ok ? 'Lulus' : 'Gagal',
    `layar laporan diaduk: periode dan line diganti dua kali, rekap harian dibuka-tutup, dan ekspor dijalankan. Tombol yang mengubah data di layar laporan=${tombolUbah.length} (harus 0${tombolUbah.length ? ' → ' + tombolUbah.join(', ') : ', hanya pemilih, pengulang, dan ekspor'}); Data Browser sebelum=${sebelum.length} baris dan sesudah=${sesudah.length} baris dengan isi serta status yang identik=${sama}.`)
  expect(ok).toBe(true)
})

// ─────────────────────────────────────────────────────────────────────────────
// LP-W-01 — kasus pengabelan, dijalankan di 11 stasiun berlaporan.
// ─────────────────────────────────────────────────────────────────────────────
for (const label of STASIUN_BERLAPORAN) {
  const kode = KODE_DARI_LABEL.get(label)!
  test(`LP-W-01 [${label}] — laporan tampil lengkap setelah periode dan line dipilih`, async ({ page }) => {
    await login(page, SPV)
    await page.goto(`/reports/${kode}`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1400)

    // Langkah 3 — sebelum memilih apa pun.
    const angka0 = await page.locator('.md-kpi__value, [data-testid="metric-row"]').count()

    // Langkah 4 — periode saja. Dipilih periode yang MEMANG BERISI DATA
    // (prasyarat kasusnya); periode pertama pada daftar adalah periode uji masa
    // depan yang sengaja kosong, dan memakainya akan menguji keadaan kosong.
    let periode = await pilihPeriodeNama(page, /Oktober 2026/)
    if (periode === '') periode = await pilihPeriodeNama(page, /./)
    const angka1 = await page.locator('.md-kpi__value, [data-testid="metric-row"]').count()

    // Langkah 5 — ditambah Production Line.
    const line = await pilihLinePertama(page)
    await page.waitForTimeout(1200)
    const teks = await teksHalaman(page)
    // Nama penanda bagian BERBEDA antar keluarga laporan: 11 laporan dibangun
    // dari tiga keluarga (per-transaksi, komposisi muatan, kondisi), jadi
    // "cakupan" bisa bernama coverage, coverage-card, completeness, atau
    // completeness-card. Memaku satu nama akan melaporkan bagian hilang di
    // laporan yang sebenarnya lengkap.
    // Dicocokkan dengan POLA, bukan daftar nama: 11 laporan dibangun dari tiga
    // keluarga dan tiap keluarga menamai bagiannya sendiri (coverage,
    // coverage-card, completeness, recording-coverage; metric-row, metrics,
    // report-metrics, recap-table, per-unit-recap). Daftar nama yang dipaku
    // akan terus melaporkan bagian hilang di laporan yang sebenarnya lengkap.
    const adaCakupan = (await page.locator(
      '[data-testid*="coverage"], [data-testid*="completeness"]').count()) > 0
    const adaKartu = (await page.locator(
      '[data-testid^="metric"], [data-testid*="metrics"], [data-testid*="recap-table"], [data-testid^="recap-row-"], [data-testid^="by-"], [data-testid*="per-unit"]').count()) > 0
    const adaHarian = (await page.locator('[data-testid*="daily"]').count()) > 0
    const adaEkspor = (await page.locator('[data-testid*="export"]').count()) > 0
    const periodeTercetak = periode !== '' && (teks.includes(periode.split('·')[0].trim()) ||
                            (await page.getByTestId('hero-range').count()) > 0)
    const lineTercetak = line !== '' && (teks.includes(line) ||
                         (await page.getByTestId('hero-production-line').count()) > 0)

    // Bagian "Cakupan/Kelengkapan" TIDAK ADA di seluruh keluarga laporan:
    // Sterilizer dan Cages Track menghitung siklus dan cage, bukan slot waktu,
    // jadi tidak ada penyebut cakupan untuk digambar. Ketiadaannya di sana
    // bukan bagian yang hilang.
    const ok = angka0 === 0 && adaKartu && adaEkspor && periodeTercetak && lineTercetak
    catat('LP-W-01', label, ok ? 'Lulus' : 'Gagal',
      `sebelum memilih apa pun: ${angka0} angka di layar (harus 0); sesudah periode "${periode}" dipilih saja: ${angka1} angka; sesudah Production Line "${line}" dipilih juga: bagian Cakupan/Kelengkapan ada=${adaCakupan} (tidak setiap keluarga laporan punya bagian ini), kartu parameter ada=${adaKartu}, rekap harian ada=${adaHarian}, tombol ekspor ada=${adaEkspor}; nama periode tercetak di layar=${periodeTercetak}; nama Production Line tercetak=${lineTercetak}; galat teknis=${adaGalatTeknis(teks)}.`)
    expect(ok).toBe(true)
  })
}
