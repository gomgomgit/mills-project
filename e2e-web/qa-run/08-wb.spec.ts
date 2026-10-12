/**
 * Pelaksana bagian 5 dokumen QA — Web Data Stasiun (WB-BRW / WB-DTL / WB-FRM).
 *
 * Varian tingkat 1 = Depricarping (stasiun wakil: 9 kolom bacaan, downtime,
 * temuan, punya master target — paling banyak melewati cabang). Dua kasus
 * pengabelan dijalankan di KE-18 stasiun: WB-DTL-03 dan WB-FRM-01. Yang ketiga,
 * WB-BRW-03, hanya di 11 stasiun bergrid 24 slot — tujuh sisanya tidak memakai
 * penyebut 24, jadi kasusnya tidak punya baris di dokumen untuk ditempeli hasil.
 */
import { test, expect, type Page } from '@playwright/test'
import { login } from '../tests/support/auth'
import {
  ADM, MGR, SPV, STASIUN, adaGalatTeknis, bisaDiisi, catat, keluar, nilaiAman, teksAman, teksHalaman,
  isiFormStasiun,
} from './support/qa'

const DP = 'depricarping'
const V = 'Depricarping'
const cap = Date.now().toString().slice(-6)

/**
 * 11 stasiun yang Data Browser-nya mencetak "N / 24" karena gridnya memang 24
 * slot waktu. Tujuh sisanya menghitung hal lain — siklus (Sterilizer),
 * cage/lori (Cages Track), kejadian (Solid Waste Disposal, Kernel Dispatch,
 * CPO Dispatch), nomor kendaraan (Grading), satu transaksi per record
 * (Weighbridge) — jadi "pecahan dari 24" tidak berlaku di sana.
 */
const BERGRID_24 = [
  'boiler-room', 'clarification', 'depricarping', 'effluent-plant', 'engine-room',
  'kernel-plant', 'pressing', 'process-quality-control', 'process-water',
  'storage-tank', 'threshing',
]

// Urutannya tetap berurut (workers: 1, fullyParallel: false), tetapi BUKAN
// mode 'serial': pada mode itu satu kegagalan membuat seluruh kasus sesudahnya
// DILEWATI — dan kegagalan di sini sering kali temuan nyata, bukan tanda bahwa
// pelaksananya rusak. Melewatkan 54 kasus karena satu temuan akan membuat
// laporannya jauh lebih kosong daripada kenyataannya.

async function barisPertama(page: Page) {
  const b = page.locator('tbody tr').filter({ hasNot: page.locator('[colspan]') }).first()
  return (await b.count()) ? b : null
}

async function bukaDetailPertama(page: Page, kode: string): Promise<string> {
  await page.goto(`/data/${kode}`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1200)
  const b = await barisPertama(page)
  if (!b) return ''
  await b.click()
  await page.waitForLoadState('domcontentloaded')
  await page.waitForTimeout(1200)
  return new URL(page.url()).pathname
}

/**
 * PRASYARAT WB-FRM-* dan LS-10: "Pastikan periode stasiun ini Terbuka."
 * Periode Oktober 2026 Mill A dibuat seeder dengan seluruh stasiun berstatus
 * Draft, dan Draft MENOLAK penyimpanan data web ("Periode ... belum dibuka").
 * Dibuka di sini lewat layar Detail Periode sebagai Admin — memakai aksi yang
 * memang ada di sana, bukan menyuntik basis data.
 *
 * DUA jalan menuju Terbuka, dan keduanya harus dilalui: Draft punya tombol
 * "Buka" (`station-open-button-`), sementara stasiun yang sudah DITUTUP punya
 * "Buka Kembali" (`station-reopen-button-`) dengan dialog konfirmasi yang
 * berbeda. Bagian 8.3 (`06-md-prd.spec.ts`) menutup satu stasiun pada periode
 * yang sama untuk menyiapkan keadaan "sebagian terbuka" MD-PRD-02/04, dan
 * stasiun pertama pada daftar itu adalah Weighbridge. Tanpa cabang
 * buka-kembali, WB-FRM-01 [Weighbridge] gagal karena prasyaratnya dirusak
 * kasus lain — bukan karena ada yang salah pada aplikasinya.
 */
test('prasyarat — buka seluruh stasiun pada periode yang mencakup hari ini', async ({ page }) => {
  await login(page, ADM)
  await page.goto('/master-data/periods', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1400)
  const baris = page.getByTestId('period-table').locator('tr', { hasText: 'Oktober 2026' }).first()
  expect(await baris.count(), 'periode "Oktober 2026" harus ada di daftar').toBeGreaterThan(0)
  const tautan = baris.locator('a, [data-testid^="period-link-"]').first()
  await tautan.click()
  await page.waitForLoadState('domcontentloaded')
  await page.waitForTimeout(1500)

  let dibuka = 0
  let dibukaKembali = 0
  for (let i = 0; i < 40; i++) {
    const draft = page.locator('[data-testid^="station-open-button-"]').first()
    const tertutup = page.locator('[data-testid^="station-reopen-button-"]').first()
    const adaDraft = (await draft.count()) > 0
    const adaTertutup = (await tertutup.count()) > 0
    if (!adaDraft && !adaTertutup) break
    const tombol = adaDraft ? draft : tertutup
    await tombol.click()
    await page.waitForTimeout(700)
    const ya = adaDraft
      ? page.getByTestId('confirm-open-period')
      : page.getByTestId('confirm-reopen-button')
    if (!(await ya.count())) break
    await ya.click()
    await page.waitForTimeout(1300)
    if (adaDraft) dibuka++
    else dibukaKembali++
  }
  const terbuka = await page.locator('[data-testid^="station-status-badge-"]')
    .evaluateAll((els) => els.filter((e) => /terbuka/i.test(e.textContent ?? '')).length)
  console.log(`[prasyarat] ${dibuka} stasiun dibuka dari Draft, ${dibukaKembali} dibuka kembali dari Tertutup; kini ${terbuka} stasiun berstatus Terbuka`)
  expect(terbuka, 'minimal satu stasiun harus berstatus Terbuka agar WB-FRM-* dapat dijalankan').toBeGreaterThan(0)
})

test('WB-BRW-01 — daftar tampil dengan filter tanggal dan kolom status berbahasa Indonesia', async ({ page }) => {
  await login(page, SPV)
  await page.goto(`/data/${DP}`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1200)
  const judul = await page.locator('thead th').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()))
  const status = await page.locator('tbody tr .dp-badge, tbody tr [class*="badge"]').evaluateAll((els) =>
    [...new Set(els.map((e) => (e.textContent ?? '').trim()))])
  const adaDari = (await page.locator('#date_from').count()) > 0
  const adaSampai = (await page.locator('#date_to').count()) > 0
  const teknis = status.filter((s) => /_|^[a-z]+$/.test(s) && !/^(Draft)$/i.test(s))

  const kolomWajib = ['Tanggal', 'Jumlah Baris Terisi', 'Status'].every((k) =>
    judul.some((j) => j.toLowerCase().includes(k.toLowerCase())))
  const ok = kolomWajib && adaDari && adaSampai && teknis.length === 0
  catat('WB-BRW-01', V, ok ? 'Lulus' : 'Gagal',
    `judul kolom: ${judul.join(' | ')}; kolom identitas, Tanggal, Jumlah Baris Terisi, dan Status ada=${kolomWajib}; nilai Status yang terbaca: ${status.join(', ')} — tidak ada yang berbentuk teknis (draft_ongoing/synced)=${teknis.length === 0}; panel filter menyediakan rentang tanggal (dari=${adaDari}, sampai=${adaSampai}).`)
  expect(ok).toBe(true)
})

test('WB-BRW-02 — rentang tanggal terbalik ditolak', async ({ page }) => {
  await login(page, SPV)
  await page.goto(`/data/${DP}`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1200)
  await page.locator('#date_from').fill('2026-10-11')
  await page.locator('#date_to').fill('2026-10-04')
  await page.waitForTimeout(1600)
  const teks = await teksHalaman(page)
  const adaPesan = /(rentang|tanggal)[^.]{0,80}(tidak|lebih|salah|harus)/i.test(teks)
  const tabelKosongTanpaPenjelasan = /Tidak ada data/i.test(teks) && !adaPesan
  const ok = adaPesan && !tabelKosongTanpaPenjelasan && !adaGalatTeknis(teks)
  catat('WB-BRW-02', V, ok ? 'Lulus' : 'Gagal',
    `pesan tentang rentang tanggal terbaca=${adaPesan}; hanya tabel kosong tanpa penjelasan=${tabelKosongTanpaPenjelasan} (harus false); galat teknis=${adaGalatTeknis(teks)}.`)
  expect(ok).toBe(true)
})

test('WB-BRW-04 — filter menghasilkan hasil yang konsisten antar halaman', async ({ page }) => {
  await login(page, SPV)
  await page.goto(`/data/weighbridge`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1300)
  // Weighbridge punya ribuan record, jadi filter yang hasilnya lebih dari satu
  // halaman memang ada di sana; Depricarping hanya 3 record.
  await page.locator('#date_from').fill('2026-09-01')
  await page.locator('#date_to').fill('2026-10-11')
  await page.waitForTimeout(1800)
  const totalAwal = (await teksHalaman(page)).match(/([\d.]+)\s+(?:data|user|periode|baris)\b/i)?.[1] ?? ''
  const lanjut = page.getByRole('button', { name: /Berikutnya|Selanjutnya|Next/i }).first()
  const adaHalaman2 = (await lanjut.count()) > 0 && (await lanjut.isEnabled())
  if (!adaHalaman2) {
    catat('WB-BRW-04', V, 'Tidak diuji',
      `Prasyarat tidak tersedia: hasil filter tidak lebih dari satu halaman (total "${totalAwal}").`)
    return
  }
  await lanjut.click()
  await page.waitForTimeout(1500)
  const tanggal = await page.locator('tbody tr td:nth-child(3)').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').trim()).filter(Boolean))
  const diLuar = tanggal.filter((t) => {
    const m = t.match(/(\d{2})\/(\d{2})\/(\d{4})/)
    if (!m) return false
    const iso = `${m[3]}-${m[2]}-${m[1]}`
    return iso < '2026-09-01' || iso > '2026-10-11'
  })
  const totalAkhir = (await teksHalaman(page)).match(/([\d.]+)\s+(?:data|user|periode|baris)\b/i)?.[1] ?? ''
  const filterTetap = (await page.locator('#date_from').inputValue()) === '2026-09-01'
  const ok = diLuar.length === 0 && totalAwal === totalAkhir && filterTetap
  catat('WB-BRW-04', V, ok ? 'Lulus' : 'Gagal',
    `filter rentang 01/09/2026–11/10/2026 diterapkan; total hasil di halaman 1="${totalAwal}", di halaman 2="${totalAkhir}" (tidak berubah=${totalAwal === totalAkhir}); dari ${tanggal.length} baris di halaman 2, yang tanggalnya DI LUAR filter=${diLuar.length}; isi filter masih terpasang sesudah pindah halaman=${filterTetap}. Dijalankan pada Weighbridge karena hanya stasiun itu yang punya data lebih dari satu halaman.`)
  expect(ok).toBe(true)
})

test('WB-BRW-05 — reset filter memulihkan keadaan awal', async ({ page }) => {
  await login(page, SPV)
  await page.goto(`/data/${DP}`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1300)
  const awalDari = await page.locator('#date_from').inputValue()
  const awalSampai = await page.locator('#date_to').inputValue()
  const awalJml = await page.locator('tbody tr').count()

  await page.locator('#date_from').fill('2026-10-06')
  await page.locator('#date_to').fill('2026-10-08')
  await page.waitForTimeout(1600)
  const sesudahFilter = await page.locator('tbody tr').count()

  const reset = page.getByRole('button', { name: /Reset/i }).first()
  const adaReset = (await reset.count()) > 0
  if (adaReset) { await reset.click(); await page.waitForTimeout(1600) }
  const pulihDari = await page.locator('#date_from').inputValue()
  const pulihSampai = await page.locator('#date_to').inputValue()
  const pulihJml = await page.locator('tbody tr').count()

  const ok = adaReset && pulihDari === awalDari && pulihSampai === awalSampai && pulihJml === awalJml
  catat('WB-BRW-05', V, ok ? 'Lulus' : 'Gagal',
    `keadaan awal: dari="${awalDari}" sampai="${awalSampai}" dengan ${awalJml} baris; sesudah filter diubah menjadi 06–08/10/2026: ${sesudahFilter} baris; tombol Reset Filter ada=${adaReset}; sesudah direset: dari="${pulihDari}" sampai="${pulihSampai}" dengan ${pulihJml} baris — kembali sama dengan keadaan awal=${pulihDari === awalDari && pulihSampai === awalSampai && pulihJml === awalJml}.`)
  expect(ok).toBe(true)
})

test('WB-BRW-06 — klik baris membuka Detail record itu', async ({ page }) => {
  await login(page, SPV)
  await page.goto(`/data/${DP}`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1300)
  const baris = page.locator('tbody tr').filter({ hasNot: page.locator('[colspan]') })
  const n = await baris.count()
  if (!n) {
    catat('WB-BRW-06', V, 'Tidak diuji', 'Tidak ada baris data pada Data Browser Depricarping untuk diklik.')
    return
  }
  // Baris ketiga bila ada, kalau tidak baris terakhir.
  const ke = Math.min(2, n - 1)
  const sel = await baris.nth(ke).locator('td').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()))
  const identitas = sel[1] ?? ''
  const tanggal = sel[2] ?? ''
  await baris.nth(ke).click()
  await page.waitForLoadState('domcontentloaded')
  await page.waitForTimeout(1300)
  const alamat = new URL(page.url()).pathname
  const teksDetail = await teksHalaman(page)
  const cocokIdentitas = identitas !== '' && teksDetail.includes(identitas)
  // Tanggal dicocokkan sebagai NILAI, bukan sebagai teks: daftar mencetaknya
  // "05/10/2026" sedangkan Detail "05 Okt 2026" — perbandingan apa adanya akan
  // melaporkan record yang salah padahal tanggalnya sama.
  const m = tanggal.match(/(\d{2})\/(\d{2})\/(\d{4})/)
  const bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
  const bulanEn = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  const bentuk = m
    ? [tanggal, `${m[1]} ${bulan[Number(m[2]) - 1]} ${m[3]}`, `${m[1]} ${bulanEn[Number(m[2]) - 1]} ${m[3]}`,
       `${Number(m[1])} ${bulan[Number(m[2]) - 1]} ${m[3]}`, `${m[3]}-${m[2]}-${m[1]}`]
    : []
  const bentukCocok = bentuk.find((b) => teksDetail.includes(b)) ?? ''
  const cocokTanggal = bentukCocok !== ''
  const alamatBenar = new RegExp(`^/data/${DP}/[0-9a-f-]{36}$`).test(alamat)
  const ok = alamatBenar && cocokIdentitas && cocokTanggal
  catat('WB-BRW-06', V, ok ? 'Lulus' : 'Gagal',
    `baris ke-${ke + 1} diklik (identitas "${identitas}", tanggal "${tanggal}"); mendarat di ${alamat} (berbentuk /data/<stasiun>/<id>=${alamatBenar}); identitas itu terbaca di Detail=${cocokIdentitas}; tanggalnya juga=${cocokTanggal} (di daftar tercetak "${tanggal}", di Detail "${bentukCocok}") — bukan record lain.`)
  expect(ok).toBe(true)
})

test('WB-BRW-07 — Tambah Data membuka Form kosong', async ({ page }) => {
  await login(page, SPV)
  await page.goto(`/data/${DP}`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1200)
  const tambah = page.getByTestId('add-data-button')
  const adaTombol = (await tambah.count()) > 0
  if (adaTombol) { await tambah.click(); await page.waitForLoadState('domcontentloaded'); await page.waitForTimeout(1300) }
  const alamat = new URL(page.url()).pathname
  const idUnit = page.getByTestId('depricarping-id-input')
  const adaIdentitas = (await idUnit.count()) > 0
  const isiIdentitas = adaIdentitas ? await idUnit.inputValue() : '(kolom tidak ada)'
  const lineBisaDipilih = (await bisaDiisi(page.getByTestId('production-line-select'), false)) === true
  const barisGrid = await page.locator('[data-testid^="depricarping-detail-row-"]').count()
  const ok = adaTombol && alamat === `/data/${DP}/create` && adaIdentitas && isiIdentitas === '' && lineBisaDipilih && barisGrid <= 1
  catat('WB-BRW-07', V, ok ? 'Lulus' : 'Gagal',
    `tombol Tambah ada=${adaTombol}; mendarat di ${alamat}; kolom identitas ada=${adaIdentitas} dan isinya="${isiIdentitas}" (harus kosong, tanpa nilai sisa record mana pun); Production Line dapat dipilih=${lineBisaDipilih}; baris grid slot waktu yang sudah ada=${barisGrid} (kosong atau satu baris kosong).`)
  expect(ok).toBe(true)
})

test('WB-BRW-08 — filter tanpa hasil memberi keterangan', async ({ page }) => {
  await login(page, SPV)
  await page.goto(`/data/${DP}`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1200)
  await page.locator('#date_from').fill('2020-01-01')
  await page.locator('#date_to').fill('2020-01-02')
  await page.waitForTimeout(1700)
  const teks = await teksHalaman(page)
  const adaKeterangan = /Tidak ada data/i.test(teks)
  const kalimatPenjelas = /tidak ada data[^.]{0,80}filter/i.test(teks)
  const filterMasihTerlihat = (await page.locator('#date_from').count()) > 0
  const jmlBaris = await page.locator('tbody tr').filter({ hasNot: page.locator('[colspan]') }).count()
  const ok = adaKeterangan && kalimatPenjelas && filterMasihTerlihat && jmlBaris === 0
  catat('WB-BRW-08', V, ok ? 'Lulus' : 'Gagal',
    `rentang 01–02/01/2020 (pasti tanpa data) diterapkan: baris data=${jmlBaris}; keterangan "tidak ada data" muncul=${adaKeterangan}; keterangannya satu kalimat yang menyebut filter=${kalimatPenjelas}; panel filter tetap terlihat sehingga dapat diubah kembali=${filterMasihTerlihat}.`)
  expect(ok).toBe(true)
})

test('WB-BRW-09 — ekspor melebihi batas baris ditolak', async ({ page }) => {
  await login(page, ADM)
  await page.goto(`/data/weighbridge`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1400)
  const total = (await teksHalaman(page)).match(/([\d.]+)\s+(?:data|baris)\b/i)?.[1] ?? '0'
  const jml = Number(total.replace(/\./g, ''))
  catat('WB-BRW-09', V, 'Tidak diuji',
    `Prasyarat tidak tersedia di basis data uji: batas ekspor adalah 50.000 baris, sedangkan stasiun dengan data terbanyak (Weighbridge) hanya punya ${jml.toLocaleString('id-ID')} baris. Dokumen kasus ini sendiri meminta ditandai "Tidak diuji" bila datanya tidak tersedia, dan JANGAN ditandai Lulus.`)
})

test('WB-DTL-01 — seluruh kolom read-only, tanpa edit di tempat', async ({ page }) => {
  await login(page, SPV)
  const alamat = await bukaDetailPertama(page, DP)
  if (!alamat) {
    catat('WB-DTL-01', V, 'Tidak diuji', 'Tidak ada record Depricarping untuk dibuka Detailnya.')
    return
  }
  const bisaDiketik = await page.locator('input:not([type="hidden"]), textarea, select').evaluateAll((els) =>
    els.filter((e) => {
      const el = e as HTMLInputElement
      if (el.closest('[class*="chatbot"]')) return false      // widget kerangka, bukan isi layar
      return !el.disabled && !el.readOnly
    }).length)
  const adaSimpan = await page.getByRole('button', { name: /^Simpan$/i }).count()
  const adaEdit = await page.getByTestId('edit-button').count()
  const ok = bisaDiketik === 0 && adaSimpan === 0 && adaEdit > 0
  catat('WB-DTL-01', V, ok ? 'Lulus' : 'Gagal',
    `Detail ${alamat}: kolom yang dapat diketik/diubah di layar ini=${bisaDiketik} (harus 0, widget chatbot kerangka halaman tidak dihitung); tombol Simpan di layar ini=${adaSimpan} (harus 0); tombol Edit yang membuka Form ada=${adaEdit > 0}.`)
  expect(ok).toBe(true)
})

test('WB-DTL-02 — verifikasi yang kosong ditampilkan sebagai tanda pisah', async ({ page }) => {
  await login(page, SPV)
  const alamat = await bukaDetailPertama(page, DP)
  if (!alamat) {
    catat('WB-DTL-02', V, 'Tidak diuji', 'Tidak ada record Depricarping untuk dibuka Detailnya.')
    return
  }
  const baca = async (label: string) => {
    const el = page.locator('.st-detail-field, .dp-detail-field, div', { hasText: new RegExp(label, 'i') }).last()
    return (await el.count()) ? (await el.innerText()).replace(/\s+/g, ' ').trim() : ''
  }
  const checked = await baca('Checked By')
  const ack = await baca('Acknowledged By')
  const nilai = (t: string) => t.replace(/.*(Checked By|Acknowledged By)/i, '').trim()
  const vC = nilai(checked)
  const vA = nilai(ack)
  const tandaPisah = (v: string) => /^(—|-|–)$/.test(v) || /belum diverifikasi/i.test(v)
  const buruk = (v: string) => /^0$/.test(v) || /null/i.test(v) || v === ''
  const ok = tandaPisah(vC) && tandaPisah(vA) && !buruk(vC) && !buruk(vA)
  catat('WB-DTL-02', V, ok ? 'Lulus' : 'Gagal',
    `record belum diverifikasi siapa pun. Checked By tampil sebagai "${vC}"; Acknowledged By tampil sebagai "${vA}". Keduanya berupa tanda pisah atau keterangan "belum diverifikasi"=${tandaPisah(vC) && tandaPisah(vA)}; bukan kosong tanpa label, bukan 0, bukan "null"=${!buruk(vC) && !buruk(vA)}.`)
  expect(ok).toBe(true)
})

test('WB-DTL-04 — Edit membuka Form mode ubah dengan isi yang sama', async ({ page }) => {
  await login(page, SPV)
  const alamat = await bukaDetailPertama(page, DP)
  if (!alamat) {
    catat('WB-DTL-04', V, 'Tidak diuji', 'Tidak ada record Depricarping untuk dibuka Detailnya.')
    return
  }
  const idDetail = await teksAman(page.getByTestId('detail-depricarping-id'))
  const barisDetail = await page.locator('[data-testid^="depricarping-detail-row-"]').count()
  await page.getByTestId('edit-button').click()
  await page.waitForLoadState('domcontentloaded')
  await page.waitForTimeout(1500)
  const alamatForm = new URL(page.url()).pathname
  const idForm = await nilaiAman(page.getByTestId('depricarping-id-input'))
  const barisForm = await page.locator('[data-testid^="depricarping-detail-row-"]').count()
  const bisa = await bisaDiisi(page.getByTestId('production-line-select'), null)
  const lineTerkunci = bisa !== true   // mati ATAU memang tidak dirender: keduanya terkunci
  const adaKeterangan = /tidak dapat diubah|terkunci|dipaku|mengikuti record/i.test(await teksHalaman(page))

  const ok = /\/edit$/.test(alamatForm) && idForm !== '' && idDetail.includes(idForm) && barisForm === barisDetail && lineTerkunci
  catat('WB-DTL-04', V, ok ? 'Lulus' : 'Gagal',
    `Detail ${alamat} → Edit membuka ${alamatForm}; identitas di Detail="${idDetail}" dan di Form="${idForm}" (sama=${idDetail.includes(idForm)}); jumlah baris grid di Detail=${barisDetail} dan di Form=${barisForm} (sama=${barisForm === barisDetail}, tidak ada yang kosong atau tergeser); Production Line terkunci pada mode ubah=${lineTerkunci}; ada keterangan yang menyebutkan penguncian itu=${adaKeterangan}.`)
  expect(ok).toBe(true)
})

test('WB-DTL-05 — Back kembali ke Data Browser dengan filter yang masih sama', async ({ page }) => {
  await login(page, SPV)
  await page.goto(`/data/weighbridge`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1400)
  await page.locator('#date_from').fill('2026-09-01')
  await page.locator('#date_to').fill('2026-10-11')
  await page.waitForTimeout(1800)
  const lanjut = page.getByRole('button', { name: /Berikutnya|Selanjutnya|Next/i }).first()
  let diHalaman2 = false
  if ((await lanjut.count()) && (await lanjut.isEnabled())) { await lanjut.click(); await page.waitForTimeout(1500); diHalaman2 = true }
  const filterSebelum = `${await page.locator('#date_from').inputValue()}..${await page.locator('#date_to').inputValue()}`

  const baris = await barisPertama(page)
  if (!baris) {
    catat('WB-DTL-05', V, 'Tidak diuji', 'Tidak ada baris untuk dibuka Detailnya pada halaman yang difilter.')
    return
  }
  await baris.click()
  await page.waitForLoadState('domcontentloaded')
  await page.waitForTimeout(1300)
  const kembali = page.getByTestId('back-button')
  const adaBack = (await kembali.count()) > 0
  if (adaBack) { await kembali.click(); await page.waitForLoadState('domcontentloaded'); await page.waitForTimeout(1600) }
  const alamat = new URL(page.url()).pathname
  const filterSesudah = `${await nilaiAman(page.locator('#date_from'))}..${await nilaiAman(page.locator('#date_to'))}`

  const ok = adaBack && alamat === '/data/weighbridge' && filterSesudah === filterSebelum
  catat('WB-DTL-05', V, ok ? 'Lulus' : 'Gagal',
    `filter "${filterSebelum}" diterapkan${diHalaman2 ? ' dan halaman 2 dibuka' : ''}; sesudah membuka Detail lalu menekan Back (tombol ada=${adaBack}), mendarat di ${alamat} dan filternya terbaca "${filterSesudah}" — masih sama=${filterSesudah === filterSebelum}.`)
  expect(ok).toBe(true)
})

// ─────────────────────────────────────────────────────────────────────────────
// Tiga kasus PENGABELAN, dijalankan di KE-18 stasiun (tingkat 2 berkas Alur).
// Kelimanya yang gagal kalau satu stasiun salah dikabel — jalur baca, jalur
// tulis, dan penurunan hitungannya.
// ─────────────────────────────────────────────────────────────────────────────

/** Jumlah baris grid pada layar Detail, tanpa bergantung nama testid per stasiun. */
async function barisGridDetail(page: Page, kode: string): Promise<{ jml: number; cara: string }> {
  const berpola = page.locator(`[data-testid^="${kode}-detail-row-"], [data-testid^="detail-row-"]`)
  if (await berpola.count()) return { jml: await berpola.count(), cara: 'penanda baris grid' }
  // Tabel terbesar di layar, tanpa tabel master target.
  const jml = await page.locator('table:not([data-testid="operational-target-table"]) tbody tr').count()
  return { jml, cara: 'baris tabel di layar (tanpa tabel master target)' }
}

// WB-BRW-03 hanya berlaku di 11 stasiun bergrid 24 slot — dan karena itu hanya
// 11 baris yang ada di berkas Alur untuk kasus ini. Perulangannya memakai
// daftar yang sama (BERGRID_24) supaya jumlah tes yang dibuat tidak dapat
// berselisih dengan jumlah baris dokumen tanpa ada yang tahu.
for (const { kode, label } of STASIUN.filter((s) => BERGRID_24.includes(s.kode))) {
  test(`WB-BRW-03 [${label}] — "Jumlah Baris Terisi" tampil sebagai pecahan dari 24`, async ({ page }) => {
    await login(page, SPV)
    await page.goto(`/data/${kode}`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1300)
    const baris = await barisPertama(page)
    if (!baris) {
      catat('WB-BRW-03', label, 'Tidak diuji', `Tidak ada record ${label} di Data Browser untuk dibaca kolomnya.`)
      return
    }
    const sel = await baris.locator('td').evaluateAll((els) =>
      els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()))
    const kolomPecahan = sel.find((t) => /\d+\s*(\/|dari)\s*24/.test(t)) ?? ''
    const m = kolomPecahan.match(/(\d+)\s*(?:\/|dari)\s*24/)
    const angkaDaftar = m ? Number(m[1]) : null

    // Langkah 4 — dicocokkan dengan jumlah baris sebenarnya pada Detail.
    await baris.click()
    await page.waitForLoadState('domcontentloaded')
    await page.waitForTimeout(1300)
    const { jml, cara } = await barisGridDetail(page, kode)

    const berpenyebut = kolomPecahan !== ''
    const cocok = angkaDaftar !== null && angkaDaftar === jml
    const ok = berpenyebut && cocok
    catat('WB-BRW-03', label, ok ? 'Lulus' : 'Gagal',
      `kolom Jumlah Baris Terisi tertulis "${kolomPecahan}" — berbentuk pecahan dari 24, bukan angka tanpa penyebut=${berpenyebut}; angka pertamanya ${angkaDaftar}, dan Detail record itu memuat ${jml} baris grid (dihitung dari ${cara}) — cocok=${cocok}.`)
    expect(ok).toBe(true)
  })
}

for (const { kode, label } of STASIUN) {
  test(`WB-DTL-03 [${label}] — grid menampilkan persis baris tersimpan`, async ({ page }) => {
    await login(page, SPV)
    // Jumlah yang TERSIMPAN dibaca dulu dari kolom hitungan Data Browser, lalu
    // dibandingkan dengan jumlah baris yang digambar Detail. Membandingkannya
    // dengan angka 24 akan menyalahkan record yang memang punya 24 baris.
    await page.goto(`/data/${kode}`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1300)
    const b0 = await barisPertama(page)
    if (!b0) {
      catat('WB-DTL-03', label, 'Tidak diuji', `Tidak ada record ${label} di Data Browser untuk diperiksa.`)
      return
    }
    const selDaftar = await b0.locator('td').evaluateAll((els) =>
      els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()))
    const kolomHitung = selDaftar[3] ?? ''
    // Hanya dipakai bila kolomnya MEMANG hitungan, bukan tanggal atau teks:
    // pada Weighbridge kolom ke-4 berbunyi "08/10/2026 10:00", dan membaca "08"
    // sebagai jumlah baris akan menyalahkan layar yang benar.
    const mHitung = /^\d+(\s*(\/|dari)\s*\d+)?$/.test(kolomHitung) ? kolomHitung.match(/^(\d+)/) : null
    const tersimpan = mHitung ? Number(mHitung[1]) : null

    await b0.click()
    await page.waitForLoadState('domcontentloaded')
    await page.waitForTimeout(1300)
    const alamat = new URL(page.url()).pathname
    const { jml, cara } = await barisGridDetail(page, kode)
    const adaGrid = (await page.locator('[data-testid*="detail-grid"], [data-testid*="detail-log"], [data-testid*="-row-"]').count()) > 0
    if (!adaGrid) {
      catat('WB-DTL-03', label, 'Tidak diuji',
        `Tidak berlaku di ${label}: layar Detail stasiun ini tidak punya grid baris sama sekali — satu record adalah satu transaksi dan seluruh nilainya ada di kepala layar. Yang diuji kasus ini (grid menampilkan persis baris tersimpan) tidak ada di sini.`)
      return
    }
    const barisKosong = await page.locator(`[data-testid^="${kode}-detail-row-"], [data-testid^="detail-row-"]`)
      .evaluateAll((els) => els.filter((e) => (e.textContent ?? '').replace(/[\s—\-|]/g, '') === '').length)
    const cocok = tersimpan !== null ? jml === tersimpan : jml > 0
    const ok = jml > 0 && cocok && barisKosong === 0
    catat('WB-DTL-03', label, ok ? 'Lulus' : 'Gagal',
      `kolom hitungan di Data Browser berbunyi "${kolomHitung}" (tersimpan ${tersimpan ?? 'tidak terbaca'}); Detail ${alamat} menggambar ${jml} baris (dihitung dari ${cara}) — sama dengan yang tersimpan=${cocok}; baris yang isinya kosong seluruhnya=${barisKosong} (harus 0, tidak digambar baris kosong sebagai pengisi).`)
    expect(ok).toBe(true)
  })

  test(`WB-FRM-01 [${label}] — simpan berhasil, status langsung Tersimpan`, async ({ page }) => {
    await login(page, SPV)
    await page.goto(`/data/${kode}/create`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1500)
    const isi = await isiFormStasiun(page, kode, '2026-10-07')
    await page.getByTestId('save-button').click()
    await page.waitForTimeout(2600)

    const galat = await page.locator('[data-testid="general-error"], [data-testid="detail-error"], .kc-form-field__error, .st-form-field__error, [class*="field__error"]')
      .evaluateAll((els) => els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()).filter(Boolean))
    const alamat = new URL(page.url()).pathname
    const pindahDariCreate = alamat !== `/data/${kode}/create`
    const teks = await teksHalaman(page)
    const sukses = /berhasil|tersimpan/i.test(teks)

    // Status record di Data Browser harus langsung Tersimpan.
    // Status dicari pada baris mana pun yang MEMBAWA penanda status, bukan
    // pada baris teratas: urutan daftar tidak selalu menempatkan record terbaru
    // di atas, dan kolom Status bukan kolom yang sama di tiap stasiun.
    let status = ''
    let caraStatus = ''
    if (pindahDariCreate || sukses) {
      await page.goto(`/data/${kode}`, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(1500)
      const semua = page.locator('tbody tr')
      const n = await semua.count()
      for (let i = 0; i < n; i++) {
        // SELURUH penanda di baris itu diperiksa, bukan yang pertama saja:
        // Weighbridge menempatkan penanda jenis muatan (Receive/Dispatch)
        // sebelum penanda status, jadi mengambil yang pertama selalu melewatkan
        // statusnya.
        const semuaBadge = await semua.nth(i).locator('[class*="badge"]')
          .evaluateAll((els) => els.map((e) => (e.textContent ?? '').trim()))
        const x = semuaBadge.find((b) => /Tersimpan|Tersinkron|Draft|Dijeda/i.test(b))
        if (x) { status = x; caraStatus = `baris ke-${i + 1}`; break }
      }
    }
    const statusBenar = /Tersimpan|Tersinkron/i.test(status)
    const ok = galat.length === 0 && (pindahDariCreate || sukses) && statusBenar
    catat('WB-FRM-01', label, ok ? 'Lulus' : 'Gagal',
      `Form baru diisi (Production Line "${isi.line}", ${isi.kolomTerisi} kolom, ${isi.barisDitambah} baris grid) lalu disimpan: galat validasi yang muncul=${galat.length}${galat.length ? ' → "' + galat.slice(0, 3).join(' / ').slice(0, 150) + '"' : ''}; layar berpindah dari form=${pindahDariCreate}; pesan berhasil terbaca=${sukses}; status record di Data Browser="${status}" (${caraStatus || 'penanda status tidak ditemukan di baris mana pun'}) (harus Tersimpan, bukan Draft)=${statusBenar}.`)
    expect(ok).toBe(true)
  })
}

// ─────────────────────────────────────────────────────────────────────────────
// Form web — sisa kasus, dijalankan pada stasiun wakil (Depricarping).
// ─────────────────────────────────────────────────────────────────────────────

/** Galat per-kolom dan pesan umum yang sedang tampil pada Form stasiun. */
async function galatForm(page: Page): Promise<{ perKolom: string[]; umum: string[] }> {
  const perKolom = await page.locator('[class*="field__error"], .st-form-field__error, .dp-form-field__error')
    .evaluateAll((els) => els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()).filter(Boolean))
  const umum = await page.locator('[data-testid="general-error"], [data-testid="detail-error"]')
    .evaluateAll((els) => els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()).filter(Boolean))
  return { perKolom, umum }
}

test('WB-FRM-02 — Production Line wajib pada mode tambah, terkunci pada mode ubah', async ({ page }) => {
  await login(page, SPV)
  await page.goto(`/data/${DP}/create`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1500)
  // Form diisi LENGKAP dulu (termasuk satu baris grid), lalu Production Line
  // dikosongkan kembali. Tanpa itu, yang ditolak adalah "minimal satu baris
  // harus diisi" — penolakan yang benar, tetapi bukan yang diuji kasus ini.
  const isi = await isiFormStasiun(page, DP, '2026-10-07')
  await page.getByTestId('depricarping-id-input').fill(`QA-FRM02-${cap}`)
  await page.getByTestId('production-line-select').selectOption('').catch(() => {})
  await page.waitForTimeout(800)
  await page.getByTestId('save-button').click()
  await page.waitForTimeout(2400)
  const g1 = await galatForm(page)
  const ditolak = [...g1.perKolom, ...g1.umum].some((t) => /production line/i.test(t))

  // Langkah 3 — Production Line dipilih kembali, simpan harus berhasil.
  await isiFormStasiun(page, DP, '2026-10-07')
  await page.getByTestId('save-button').click()
  await page.waitForTimeout(2600)
  const g2 = await galatForm(page)
  const berhasil = g2.perKolom.length === 0 && g2.umum.length === 0

  // Langkah 4–5 — mode ubah: Production Line tidak dapat diubah.
  const alamatBaru = new URL(page.url()).pathname
  let terkunci: boolean | null = null
  let keterangan = false
  if (/\/data\/.+\/[0-9a-f-]{36}$/.test(alamatBaru)) {
    await page.goto(`${alamatBaru}/edit`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1500)
    const bisa = await bisaDiisi(page.getByTestId('production-line-select'), null)
    terkunci = bisa !== true
    keterangan = /tidak dapat diubah|terkunci|dipaku|mengikuti record|ditetapkan saat/i.test(await teksHalaman(page))
  }

  const ok = ditolak && berhasil && terkunci === true
  catat('WB-FRM-02', V, ok ? 'Lulus' : 'Gagal',
    `tanpa Production Line, simpan ditolak dengan pesan yang menunjuk kolom itu=${ditolak} ("${[...g1.perKolom, ...g1.umum].join(' / ').slice(0, 120)}"); sesudah Production Line "${isi.line}" dipilih, simpan berhasil=${berhasil}; pada mode ubah Production Line tidak dapat diubah=${terkunci}; ada keterangan yang menyebutkan penguncian itu=${keterangan}.`)
  expect(ok).toBe(true)
})

test('WB-FRM-03 — kolom wajib dan tanggal divalidasi', async ({ page }) => {
  await login(page, SPV)
  const rinci: string[] = []
  let gagal = 0

  const siap = async () => {
    await page.goto(`/data/${DP}/create`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1500)
    return isiFormStasiun(page, DP, '2026-10-07')
  }

  // (a) identitas unit dikosongkan.
  await siap()
  await page.getByTestId('depricarping-id-input').fill('')
  await page.getByTestId('save-button').click()
  await page.waitForTimeout(2200)
  let g = await galatForm(page)
  const diKolomIdentitas = await page.getByTestId('depricarping-id-input').evaluate((el) => {
    const b = (el as HTMLElement).closest('.st-form-field, .dp-form-field, .form-field, div')
    return (b?.querySelector('[class*="field__error"]')?.textContent ?? '').trim()
  }).catch(() => '')
  const tanggalTetap = (await nilaiAman(page.getByTestId('date-input'))) !== ''
  if (diKolomIdentitas === '') gagal++
  rinci.push(`identitas dikosongkan → galat di sebelah kolomnya="${diKolomIdentitas}"; isi kolom Tanggal tidak hilang=${tanggalTetap}`)

  // (b) Tanggal dikosongkan.
  await siap()
  await page.getByTestId('date-input').fill('')
  await page.getByTestId('save-button').click()
  await page.waitForTimeout(2200)
  const diKolomTanggal = await page.getByTestId('date-input').evaluate((el) => {
    const b = (el as HTMLElement).closest('.st-form-field, .dp-form-field, .form-field, div')
    return (b?.querySelector('[class*="field__error"]')?.textContent ?? '').trim()
  }).catch(() => '')
  if (diKolomTanggal === '') gagal++
  rinci.push(`Tanggal dikosongkan → galat di sebelah kolomnya="${diKolomTanggal}"`)

  // (c) Tanggal di luar periode yang terbuka.
  await siap()
  // Tanggal masa LALU di luar periode: tanggal masa depan ditolak lebih dulu
  // oleh aturan "tidak boleh melewati hari ini", sehingga pesan periodenya
  // tidak akan pernah muncul.
  await page.getByTestId('date-input').fill('2026-07-15')
  await page.getByTestId('save-button').click()
  await page.waitForTimeout(2400)
  g = await galatForm(page)
  const semua = [...g.perKolom, ...g.umum].join(' ')
  const sebutPeriode = /periode/i.test(semua)
  if (!sebutPeriode) gagal++
  rinci.push(`Tanggal di luar periode terbuka (15/07/2026, di masa lalu) → ditolak dengan pesan yang menyebut periode=${sebutPeriode} ("${semua.slice(0, 130)}")`)

  // Kolom tanggal bertipe date menolak teks bukan tanggal di tingkat browser,
  // jadi langkah "isi abc" tidak dapat dijalankan sebagai pengetikan biasa.
  rinci.push('Langkah "isi Tanggal dengan teks bukan tanggal" tidak dapat dijalankan: kolomnya bertipe date, sehingga browser sendiri menolak karakter yang bukan tanggal sebelum form dikirim.')

  catat('WB-FRM-03', V, gagal ? 'Gagal' : 'Lulus', rinci.join(' | '))
  expect(gagal, rinci.join('\n')).toBe(0)
})

test('WB-FRM-04 — aturan slot waktu sama seperti mobile', async ({ page }) => {
  await login(page, SPV)
  await page.goto(`/data/${DP}/create`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1500)
  await isiFormStasiun(page, DP, '2026-10-07')

  // Urutan 24 pilihan slot pada baris pertama.
  const sel1 = page.locator('[data-testid^="time-slot-select-"]').first()
  const opsi1 = await sel1.locator('option').evaluateAll((o) =>
    o.map((e) => (e.textContent ?? '').trim()).filter((x) => x !== '' && !/pilih/i.test(x)))
  const slotTerpilih = await sel1.inputValue()
  const urutBenar = opsi1.length === 24 && /^07[:.]00/.test(opsi1[0]) && /^06[:.]00/.test(opsi1[23])

  // Baris kedua: aturannya ditegakkan dengan MENCEGAH — slot yang sudah dipakai
  // dan slot yang lebih awal dihilangkan dari daftar pilihan. Jadi yang diuji
  // adalah isi daftarnya, bukan ada tidaknya pesan galat.
  await page.getByTestId('add-row-button').click()
  await page.waitForTimeout(1100)
  const sel = page.locator('[data-testid^="time-slot-select-"]')
  const n = await sel.count()
  let opsi2: string[] = []
  if (n >= 2) {
    opsi2 = await sel.nth(n - 1).locator('option').evaluateAll((o) =>
      o.map((e) => (e.textContent ?? '').trim()).filter((x) => x !== '' && !/pilih/i.test(x)))
  }
  const idxTerpilih = opsi1.indexOf(slotTerpilih)
  const kembarDicegah = slotTerpilih !== '' && !opsi2.includes(slotTerpilih)
  const menurunDicegah = idxTerpilih >= 0 &&
    opsi1.slice(0, idxTerpilih).every((sl) => !opsi2.includes(sl))
  const hanyaMenaik = idxTerpilih >= 0 && opsi2.every((sl) => opsi1.indexOf(sl) > idxTerpilih)

  const ok = urutBenar && kembarDicegah && menurunDicegah && hanyaMenaik
  catat('WB-FRM-04', V, ok ? 'Lulus' : 'Gagal',
    `baris pertama menawarkan ${opsi1.length} pilihan slot (harus 24), mulai "${opsi1[0] ?? '-'}" dan berakhir "${opsi1[opsi1.length - 1] ?? '-'}" — urutan 07:00 → 06:00 benar=${urutBenar}. Baris pertama memakai "${slotTerpilih}"; baris kedua menawarkan ${opsi2.length} pilihan: slot yang sudah dipakai sudah hilang dari daftar=${kembarDicegah}; seluruh slot yang lebih awal juga hilang=${menurunDicegah}; setiap pilihan yang tersisa lebih besar dari slot baris sebelumnya=${hanyaMenaik}. Aturannya ditegakkan dengan mencegah pilihan, bukan dengan pesan galat saat Simpan — jadi tidak ada galat untuk dibaca, dan itu memang yang benar. Dibandingkan dengan mobile pada MB-FORM-03 dan MB-FORM-04.`)
  expect(ok).toBe(true)
})

test('WB-FRM-05 — melebihi 24 baris ditolak', async ({ page }) => {
  await login(page, SPV)
  await page.goto(`/data/${DP}/create`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1500)
  await isiFormStasiun(page, DP, '2026-10-07')
  const tambah = page.getByTestId('add-row-button')
  let baris = await page.locator('[data-testid^="depricarping-detail-row-"]').count()
  // Ditambah sampai 24 baris.
  for (let i = baris; i < 24; i++) {
    if (!(await tambah.isEnabled().catch(() => false))) break
    await tambah.click()
    await page.waitForTimeout(260)
  }
  baris = await page.locator('[data-testid^="depricarping-detail-row-"]').count()
  const masihBisa = await tambah.isEnabled().catch(() => false)
  let pesan = ''
  if (masihBisa) {
    await tambah.click()
    await page.waitForTimeout(800)
    const sesudah = await page.locator('[data-testid^="depricarping-detail-row-"]').count()
    const g = await galatForm(page)
    pesan = [...g.perKolom, ...g.umum].join(' ')
    if (sesudah > 24) pesan += ` (baris ke-25 justru tertambah: kini ${sesudah} baris)`
  }
  const dibatasi = baris === 24 && (!masihBisa || /24/.test(pesan))
  const barisAkhir = await page.locator('[data-testid^="depricarping-detail-row-"]').count()
  catat('WB-FRM-05', V, dibatasi ? 'Lulus' : 'Gagal',
    `baris ditambah sampai ${baris} (batasnya 24); pada 24 baris tombol tambah baris masih dapat dipakai=${masihBisa}${pesan ? `, dan penambahan ke-25 dijawab "${pesan.slice(0, 120)}"` : ''}; jumlah baris akhir=${barisAkhir} (tidak melewati 24=${barisAkhir <= 24}).`)
  expect(dibatasi).toBe(true)
})

test('WB-FRM-06 — minimal satu baris terisi', async ({ page }) => {
  await login(page, SPV)
  await page.goto(`/data/${DP}/create`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1500)
  // Header diisi, satu baris ditambah dengan slot waktu, tetapi TANPA satu pun
  // kolom bacaan — jadi kolom bacaan yang sudah terisi otomatis dibersihkan.
  await isiFormStasiun(page, DP, '2026-10-07')
  const bacaan = page.locator('[data-testid^="fan-static-pressure-"], [data-testid^="polishing-drum-speed-"], [data-testid^="air-velocity-"], [data-testid^="fibre-moisture-"], [data-testid^="kernel-recovery-"], [data-testid^="nut-silo-"], [data-testid^="downtime-minutes-"], [data-testid^="findings-"]')
  const nB = await bacaan.count()
  for (let i = 0; i < nB; i++) await bacaan.nth(i).fill('').catch(() => {})
  await page.waitForTimeout(600)
  await page.getByTestId('save-button').click()
  await page.waitForTimeout(2400)
  const g = await galatForm(page)
  const pesan = [...g.perKolom, ...g.umum].join(' ')
  const ditolak = /minimal satu|setidaknya satu|minimal 1/i.test(pesan)
  const sebutKeduanya = /slot/i.test(pesan) && /(kolom|bacaan|nilai)/i.test(pesan)
  const masihDiForm = new URL(page.url()).pathname === `/data/${DP}/create`
  const ok = ditolak && masihDiForm
  catat('WB-FRM-06', V, ok ? 'Lulus' : 'Gagal',
    `satu baris dengan slot waktu tetapi seluruh ${nB} kolom bacaannya dikosongkan: ditolak=${ditolak}; pesannya menyebut syarat slot DAN minimal satu kolom bacaan=${sebutKeduanya} ("${pesan.slice(0, 150)}"); record tidak tersimpan — layar masih di form=${masihDiForm}. Perilakunya dibandingkan dengan MB-FORM-05 di mobile.`)
  expect(ok).toBe(true)
})

test('WB-FRM-07 — line tanpa stasiun aktif ditolak', async ({ page }) => {
  await login(page, SPV)
  await page.goto(`/data/${DP}/create`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1500)
  const sel = page.getByTestId('production-line-select')
  const opsi = await sel.locator('option').evaluateAll((o) =>
    o.map((e) => ({ v: (e as HTMLOptionElement).value, t: (e.textContent ?? '').trim() })).filter((x) => x.v !== ''))
  // Line fixture yang sengaja TIDAK punya stasiun jenis ini: namanya berawalan
  // "PL Tanpa" (dibuat BrowserTestFixtureSeeder).
  const tanpa = opsi.find((o) => /tanpa/i.test(o.t))
  if (!tanpa) {
    catat('WB-FRM-07', V, 'Tidak diuji',
      `Prasyarat tidak tersedia: tidak ada Production Line tanpa stasiun Depricarping aktif di antara pilihan akun ini. Pilihan yang ada: ${opsi.map((o) => o.t).join(', ')}.`)
    return
  }
  await sel.selectOption(tanpa.v)
  await page.waitForTimeout(900)
  await isiFormStasiun(page, DP, '2026-10-07')
  await sel.selectOption(tanpa.v).catch(() => {})
  await page.waitForTimeout(700)
  const isiSebelum = await nilaiAman(page.getByTestId('depricarping-id-input'))
  await page.getByTestId('save-button').click()
  await page.waitForTimeout(2500)
  const g = await galatForm(page)
  const pesan = [...g.perKolom, ...g.umum].join(' ')
  const ditolak = /tidak memiliki station|belum memiliki station|tidak punya stasiun|station .* aktif/i.test(pesan)
  const teks = await teksHalaman(page)
  const isiTetap = (await nilaiAman(page.getByTestId('depricarping-id-input'))) === isiSebelum
  const ok = ditolak && !adaGalatTeknis(teks) && isiTetap
  catat('WB-FRM-07', V, ok ? 'Lulus' : 'Gagal',
    `Production Line "${tanpa.t}" (tanpa stasiun Depricarping aktif) dipilih: ditolak dengan pesan yang menyebut hal itu=${ditolak} ("${pesan.slice(0, 140)}"); galat teknis=${adaGalatTeknis(teks)} (harus false); isi form tidak hilang=${isiTetap}.`)
  expect(ok).toBe(true)
})

test('WB-FRM-08 — pembatasan peran pada verifikasi', async ({ page }) => {
  const hasil: string[] = []
  let gagal = 0
  const alamatEdit = async () => {
    await page.goto(`/data/${DP}`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1300)
    const b = await barisPertama(page)
    if (!b) return ''
    await b.click()
    await page.waitForLoadState('domcontentloaded')
    await page.waitForTimeout(1200)
    const id = new URL(page.url()).pathname.split('/').pop() ?? ''
    if (!id || id === DP) return ''
    await page.goto(`/data/${DP}/${id}/edit`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1500)
    return new URL(page.url()).pathname
  }
  // Verifikasi adalah PERNYATAAN DIRI: Checked hanya oleh Supervisor,
  // Acknowledged hanya oleh Mill Management. Admin bukan pihak yang memeriksa
  // maupun mengonfirmasi, jadi bagian Verifikasi memang tidak ditampilkan
  // untuknya — terbukti di layanan tiap stasiun, bukan hanya di tampilan.
  for (const [akun, harusChecked, harusAck] of [
    [SPV, true, false], [MGR, false, true], [ADM, false, false],
  ] as [string, boolean, boolean][]) {
    await login(page, akun)
    const al = await alamatEdit()
    if (!al) { gagal++; hasil.push(`${akun}: tidak ada record untuk dibuka`); await keluar(page); continue }
    const c = await bisaDiisi(page.getByTestId('checked-checkbox'), null)
    const a = await bisaDiisi(page.getByTestId('acknowledged-checkbox'), null)
    const sesuaiC = harusChecked ? c === true : c !== true
    const sesuaiA = harusAck ? a === true : a !== true
    if (!sesuaiC || !sesuaiA) gagal++
    hasil.push(`${akun}: Checked ${c === null ? 'tidak dirender' : `dapat diisi=${c}`} (harus ${harusChecked ? 'bisa' : 'tidak'}), Acknowledged ${a === null ? 'tidak dirender' : `dapat diisi=${a}`} (harus ${harusAck ? 'bisa' : 'tidak'})${sesuaiC && sesuaiA ? '' : ' <- TIDAK SESUAI'}`)
    await keluar(page)
  }
  catat('WB-FRM-08', V, gagal ? 'Gagal' : 'Lulus',
    `${hasil.join(' | ')}. Kendali yang tidak dirender dihitung sebagai "tidak dapat diisi" — itu lebih tegas daripada kendali kelabu.`)
  expect(gagal, hasil.join('\n')).toBe(0)
})

test('WB-FRM-09 — Batal tidak menyimpan apa pun', async ({ page }) => {
  await login(page, SPV)
  await page.goto(`/data/${DP}`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1300)
  const b = await barisPertama(page)
  if (!b) {
    catat('WB-FRM-09', V, 'Tidak diuji', 'Tidak ada record Depricarping untuk diubah.')
    return
  }
  await b.click()
  await page.waitForLoadState('domcontentloaded')
  await page.waitForTimeout(1200)
  const alamatDetail = new URL(page.url()).pathname
  const idSebelum = await teksAman(page.getByTestId('detail-depricarping-id'))
  const teksSebelum = (await teksHalaman(page)).slice(0, 600)

  await page.goto(`${alamatDetail}/edit`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1500)
  await page.getByTestId('depricarping-id-input').fill(`QA-BATAL-${cap}`)
  const catatan = page.getByTestId('note-input')
  if (await catatan.count()) await catatan.fill('Catatan uji yang harus dibuang.')
  await page.getByTestId('cancel-button').click()
  await page.waitForLoadState('domcontentloaded')
  await page.waitForTimeout(1600)
  const alamatSesudah = new URL(page.url()).pathname

  await page.goto(alamatDetail, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1300)
  const idSesudah = await teksAman(page.getByTestId('detail-depricarping-id'))
  const teksSesudah = (await teksHalaman(page)).slice(0, 600)
  const tidakBerubah = idSebelum === idSesudah && !teksSesudah.includes('Catatan uji yang harus dibuang')

  const ok = tidakBerubah
  catat('WB-FRM-09', V, ok ? 'Lulus' : 'Gagal',
    `identitas diubah menjadi "QA-BATAL-${cap}" dan catatan diisi, lalu Batal ditekan: layar kembali ke ${alamatSesudah}; identitas di Detail sebelum="${idSebelum}" dan sesudah="${idSesudah}" (tidak berubah=${idSebelum === idSesudah}); catatan uji tidak tersimpan=${!teksSesudah.includes('Catatan uji yang harus dibuang')}.`)
  expect(ok).toBe(true)
})

test('WB-FRM-10 — ubah record berhasil dan terlihat di Detail', async ({ page }) => {
  await login(page, SPV)
  await page.goto(`/data/${DP}`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1300)
  const b = await barisPertama(page)
  if (!b) {
    catat('WB-FRM-10', V, 'Tidak diuji', 'Tidak ada record Depricarping untuk diubah.')
    return
  }
  await b.click()
  await page.waitForLoadState('domcontentloaded')
  await page.waitForTimeout(1200)
  const alamatDetail = new URL(page.url()).pathname
  await page.goto(`${alamatDetail}/edit`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1500)

  const idBaru = `QA-UBAH-${cap}`
  const catatanBaru = `Catatan uji QA ${cap}`
  await page.getByTestId('depricarping-id-input').fill(idBaru)
  const catatan = page.getByTestId('note-input')
  if (await catatan.count()) await catatan.fill(catatanBaru)
  // Satu nilai pada grid.
  const selGrid = page.locator('[data-testid^="fan-static-pressure-"]').first()
  let nilaiGrid = ''
  if (await selGrid.count()) { nilaiGrid = '77'; await selGrid.fill(nilaiGrid) }
  await page.getByTestId('save-button').click()
  await page.waitForTimeout(2600)
  const g = await galatForm(page)
  const berhasil = g.perKolom.length === 0 && g.umum.length === 0

  await page.goto(alamatDetail, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1400)
  const teksDetail = await teksHalaman(page)
  const idTerlihat = teksDetail.includes(idBaru)
  const catatanTerlihat = catatanBaru === '' || teksDetail.includes(catatanBaru)
  const gridTerlihat = nilaiGrid === '' || teksDetail.includes(nilaiGrid)

  await page.goto(`/data/${DP}`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1400)
  const diDaftar = (await teksHalaman(page)).includes(idBaru)

  const ok = berhasil && idTerlihat && catatanTerlihat && gridTerlihat && diDaftar
  catat('WB-FRM-10', V, ok ? 'Lulus' : 'Gagal',
    `identitas → "${idBaru}", catatan → "${catatanBaru}", satu nilai grid → "${nilaiGrid}": simpan tanpa galat=${berhasil}; di Detail terlihat identitas=${idTerlihat}, catatan=${catatanTerlihat}, nilai grid=${gridTerlihat}; baris di Data Browser menunjukkan identitas baru=${diDaftar}.`)
  expect(ok).toBe(true)
})

test('WB-FRM-11 — hapus baris mengurangi "Jumlah Baris Terisi"', async ({ page }) => {
  await login(page, SPV)
  await page.goto(`/data/${DP}`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1400)
  const b = await barisPertama(page)
  if (!b) {
    catat('WB-FRM-11', V, 'Tidak diuji', 'Tidak ada record Depricarping untuk diubah.')
    return
  }
  await b.click()
  await page.waitForLoadState('domcontentloaded')
  await page.waitForTimeout(1200)
  const alamatDetail = new URL(page.url()).pathname
  const idRecord = await teksAman(page.getByTestId('detail-depricarping-id'))

  /**
   * Pecahan "N / 24" dibaca dari baris yang MEMBAWA identitas record ini —
   * bukan dari baris teratas. Kasus-kasus lain di berkas ini ikut mengubah
   * record yang sama, jadi baris teratas belum tentu record yang sedang diuji,
   * dan angka yang dibandingkan bisa milik record lain.
   */
  const pecahanRecord = async (): Promise<{ teks: string; n: number }> => {
    await page.goto(`/data/${DP}`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1500)
    const baris = page.locator('tbody tr').filter({ hasText: idRecord })
    if (!(await baris.count())) return { teks: '', n: 0 }
    const sel = await baris.first().locator('td').evaluateAll((els) =>
      els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()))
    const teks = sel.find((x) => /\d+\s*\/\s*24/.test(x)) ?? ''
    return { teks, n: Number((teks.match(/^(\d+)/) ?? [])[1] ?? 0) }
  }

  // Prasyarat: record dengan beberapa baris terisi. Ditambah lewat form bila
  // barisnya tinggal satu — menghapus satu dari satu baris tidak dapat menguji
  // "turun satu".
  await page.goto(`${alamatDetail}/edit`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1500)
  let barisKini = await page.locator('[data-testid^="depricarping-detail-row-"]').count()
  let ditambah = 0
  while (barisKini < 3 && ditambah < 3) {
    await page.getByTestId('add-row-button').click()
    await page.waitForTimeout(900)
    const sel = page.locator('[data-testid^="time-slot-select-"]')
    const n = await sel.count()
    const o = await sel.nth(n - 1).locator('option').evaluateAll((x) =>
      x.map((e) => (e as HTMLSelectElement).value).filter((v) => v !== ''))
    if (!o.length) break
    await sel.nth(n - 1).selectOption(o[0], { timeout: 4000 }).catch(() => {})
    await page.locator('[data-testid^="fan-static-pressure-"]').nth(n - 1).fill('42').catch(() => {})
    await page.waitForTimeout(500)
    barisKini = await page.locator('[data-testid^="depricarping-detail-row-"]').count()
    ditambah++
  }
  if (ditambah > 0) {
    await page.getByTestId('save-button').click()
    await page.waitForTimeout(2800)
  }

  const awal = await pecahanRecord()
  if (awal.n < 2) {
    catat('WB-FRM-11', V, 'Tidak diuji',
      `Prasyarat tidak terbentuk: record "${idRecord}" hanya punya ${awal.n} baris terisi ("${awal.teks}") meski ${ditambah} baris coba ditambahkan, sehingga "turun dari N ke N-1" tidak dapat diamati.`)
    return
  }

  // Langkah 4 — satu baris dihapus.
  await page.goto(`${alamatDetail}/edit`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1500)
  const hapus = page.locator('[data-testid^="remove-row-button-"]')
  const adaHapus = await hapus.count()
  if (!adaHapus) {
    catat('WB-FRM-11', V, 'Gagal', `Form mode ubah tidak punya tombol hapus baris; jumlah baris awal "${awal.teks}".`)
    expect(adaHapus).toBeGreaterThan(0)
    return
  }
  await hapus.last().click()
  await page.waitForTimeout(900)
  await page.getByTestId('save-button').click()
  await page.waitForTimeout(2800)

  await page.goto(alamatDetail, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1400)
  const barisDetail = await page.locator('[data-testid^="depricarping-detail-row-"]').count()
  const akhir = await pecahanRecord()

  const turunSatu = akhir.n === awal.n - 1
  const penyebutTetap = /\/\s*24/.test(akhir.teks)
  const detailCocok = barisDetail === akhir.n
  const ok = turunSatu && penyebutTetap && detailCocok
  catat('WB-FRM-11', V, ok ? 'Lulus' : 'Gagal',
    `record "${idRecord}" (${ditambah} baris ditambahkan lebih dulu agar prasyaratnya terbentuk): kolom Jumlah Baris Terisi sebelum="${awal.teks}", sesudah satu baris dihapus="${akhir.teks}" — turun tepat satu=${turunSatu}, penyebutnya tetap 24=${penyebutTetap}; Detail record kini menampilkan ${barisDetail} baris, cocok dengan angka barunya=${detailCocok}; baris yang dihapus tidak kembali sesudah layar dibuka ulang.`)
  expect(ok).toBe(true)
})
