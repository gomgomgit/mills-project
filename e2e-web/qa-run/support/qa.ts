/**
 * Modul bersama pelaksana kasus uji QA.
 *
 * Hasil ditulis SEGERA per kasus ke qa-run/hasil.jsonl (satu baris JSON per
 * kasus), bukan dikumpulkan di memori lalu ditulis di afterAll. Sebabnya: satu
 * berkas spec yang jatuh di tengah jalan akan membuang seluruh hasil berkas itu
 * kalau penulisannya ditunda, dan putaran sebagian adalah hal yang normal di
 * sini — 265 kasus tidak selesai dalam satu tarikan napas.
 */
import { appendFileSync, mkdirSync } from 'node:fs'
import type { Page } from '@playwright/test'

export const KELUARAN = 'qa-run/hasil.jsonl'

/**
 * "Tidak diuji" ada di sini dengan sengaja: sebuah kasus yang premisnya tidak
 * berlaku di layar itu (mis. menguji kolom pencarian pada layar yang tidak
 * punya kolom pencarian) bukan kasus yang lulus, dan juga bukan cacat. Memaksa
 * salah satu dari dua nilai itu akan menghasilkan laporan yang keliru di kedua
 * arah.
 */
export type Nilai = 'Lulus' | 'Gagal' | 'Tidak diuji'

/**
 * Akun uji §1.2 dokumen. Keenamnya ada di basis data e2e sesudah
 * `npm run db:prepare` + qa-run/siapkan-lingkungan.php (dua di antaranya —
 * SPV-B dan NOMILL — hanya dibuat skrip itu).
 */
export const OPR = 'operator01'
export const SPV = 'supervisor01'
export const MGR = 'millmanagement-a'
export const SPV_B = 'supervisor-b'
export const ADM = 'admin'
export const NOMILL = 'supervisor-nomill'

export function catat(id: string, varian: string, hasil: Nilai, diamati: string): void {
  mkdirSync('qa-run', { recursive: true })
  appendFileSync(KELUARAN, JSON.stringify({ id, varian, hasil, diamati }) + '\n', 'utf8')
}

/**
 * Mengakhiri sesi. TIDAK memakai page.goto('/logout'): rute logout hanya
 * menerima POST, jadi GET ke sana dijawab 405 dan SESINYA TETAP HIDUP —
 * seluruh pemeriksaan peran sesudahnya akan menguji peran yang salah, dan
 * kegagalannya tidak terlihat sebagai kegagalan.
 */
export async function keluar(page: Page): Promise<void> {
  await page.context().clearCookies()
}

/** Teks seluruh halaman, dipangkas supaya catatan tetap terbaca. */
export async function teksHalaman(page: Page): Promise<string> {
  return (await page.locator('body').innerText()).replace(/\s+/g, ' ').trim()
}

/** Galat teknis yang TIDAK boleh pernah terlihat pengguna. */
export const POLA_TEKNIS =
  /(SQLSTATE|Stack trace|Whoops|Server Error|Integrity constraint|Undefined (variable|index|property)|Call to a member function|htmlspecialchars\(\))/i

export function adaGalatTeknis(teks: string): boolean {
  return POLA_TEKNIS.test(teks)
}

/** Halaman 403 yang benar: menyebut penolakannya, bukan halaman putih. */
export function ada403(teks: string): boolean {
  return /403|Akses Ditolak|Tidak Punya Akses/i.test(teks)
}

/** 18 jenis stasiun, kode bertanda hubung — urutan sesuai dokumen §0.3. */
export const STASIUN: Array<{ kode: string; label: string }> = [
  { kode: 'weighbridge', label: 'Weighbridge' },
  { kode: 'grading', label: 'Grading' },
  { kode: 'cages-track', label: 'Cages Track' },
  { kode: 'sterilizer', label: 'Sterilizer' },
  { kode: 'threshing', label: 'Threshing' },
  { kode: 'pressing', label: 'Pressing' },
  { kode: 'clarification', label: 'Clarification' },
  { kode: 'kernel-plant', label: 'Kernel Plant' },
  { kode: 'boiler-room', label: 'Boiler Room' },
  { kode: 'effluent-plant', label: 'Effluent Plant' },
  { kode: 'depricarping', label: 'Depricarping' },
  { kode: 'engine-room', label: 'Engine Room' },
  { kode: 'process-water', label: 'Process Water' },
  { kode: 'solid-waste-disposal', label: 'Solid Waste Disposal' },
  { kode: 'kernel-dispatch', label: 'Kernel Dispatch' },
  { kode: 'cpo-dispatch', label: 'CPO Dispatch' },
  { kode: 'process-quality-control', label: 'Process Quality Control' },
  { kode: 'storage-tank', label: 'Storage Tank' },
]

export const KODE_DARI_LABEL = new Map(STASIUN.map((s) => [s.label, s.kode]))

/** 11 stasiun yang laporan periodenya sudah ada (sisanya tile kelabu). */
export const STASIUN_BERLAPORAN = [
  'Weighbridge', 'Grading', 'Cages Track', 'Sterilizer', 'Threshing', 'Pressing',
  'Clarification', 'Kernel Plant', 'Boiler Room', 'Depricarping', 'Storage Tank',
]

/**
 * Memilih Production Line pada toolbar filter laporan / Laporan Stasiun, lalu
 * mengembalikan nama yang terpilih. Grid tile dan angka laporan ditahan sampai
 * line pasti (lihat LS-08), jadi hampir setiap kasus laporan memerlukan ini.
 */
export async function pilihLine(page: Page, ke = 0): Promise<string> {
  const sel = page.getByTestId('production-line-select')
  if (!(await sel.count())) return ''
  const opsi = await sel.locator('option').evaluateAll((o) =>
    o.map((e) => ({ v: (e as HTMLOptionElement).value, t: (e.textContent ?? '').trim() }))
      .filter((x) => x.v !== ''))
  if (!opsi.length) return ''
  const p = opsi[Math.min(ke, opsi.length - 1)]
  await sel.selectOption(p.v)
  await page.waitForTimeout(600)
  return p.t
}

/** Memilih periode pelaporan bila pemilihnya ada. Mengembalikan namanya. */
export async function pilihPeriode(page: Page, ke = 0): Promise<string> {
  const sel = page.getByTestId('period-select')
  if (!(await sel.count())) return ''
  const opsi = await sel.locator('option').evaluateAll((o) =>
    o.map((e) => ({ v: (e as HTMLOptionElement).value, t: (e.textContent ?? '').trim() }))
      .filter((x) => x.v !== ''))
  if (!opsi.length) return ''
  const p = opsi[Math.min(ke, opsi.length - 1)]
  await sel.selectOption(p.v)
  await page.waitForTimeout(800)
  return p.t
}

/**
 * Memilih periode pertama yang BENAR-BENAR BERDATA pada layar laporan.
 *
 * `pilihPeriode(page, 0)` tidak cukup untuk kasus yang memeriksa isi laporan:
 * `siapkan-laporan.php` sengaja membuat "QA Masa Depan" dan "QA Tanpa Record",
 * dan salah satunya bisa berada di urutan pertama. Pada periode tanpa data
 * laporannya menggambar empty state, dan seluruh bagian isi — tabel
 * parameter, rekap harian, serta kotak keterangan "laporan ini tidak menilai
 * satu angka pun" — TIDAK dirender sama sekali. Pelaksana lalu melaporkan
 * "kotak keterangan tidak ada" sebagai cacat aplikasi, padahal yang salah
 * adalah periode yang ia pilih sendiri.
 *
 * Mengembalikan nama periode yang terpakai, atau '' bila tidak ada satu pun
 * periode berdata — dan itu memang keadaan "Tidak diuji", bukan "Gagal".
 */
export async function pilihPeriodeBerdata(page: Page): Promise<string> {
  const sel = page.getByTestId('period-select')
  if (!(await sel.count())) return ''
  const opsi = await sel.locator('option').evaluateAll((o) =>
    o.map((e) => ({ v: (e as HTMLOptionElement).value, t: (e.textContent ?? '').trim() }))
      .filter((x) => x.v !== ''))
  for (const p of opsi) {
    await sel.selectOption(p.v)
    await page.waitForTimeout(1100)
    if (!(await page.getByTestId('empty-state').count())) return p.t
  }
  return ''
}

/**
 * Mencari elemen dengan salah satu dari beberapa data-testid. Nama testid
 * BERBEDA antar layar laporan (mill-current vs mill-name, export-csv vs
 * export-button), dan memaku satu nama membuat pelaksana melaporkan "tombol
 * tidak ada" padahal tombolnya ada dengan nama lain — kegagalan palsu yang
 * terbaca seperti cacat aplikasi.
 */
export async function salahSatuTestid(page: Page, kandidat: string[]) {
  for (const t of kandidat) {
    const l = page.getByTestId(t)
    if (await l.count()) return { ada: true, nama: t, loc: l }
  }
  return { ada: false, nama: '', loc: page.getByTestId(kandidat[0]) }
}

export const TESTID_MILL_TEKS = ['mill-current', 'mill-name', 'mill-static']
export const TESTID_EKSPOR_CSV = ['export-csv', 'export-button', 'export-csv-button']
export const TESTID_EKSPOR_XLS = ['export-excel', 'export-excel-button']
export const TESTID_CATATAN_TANPA_TANDA = ['no-flagging-note', 'no-sum-note', 'no-threshold-note']

/**
 * isEnabled / inputValue / innerText pada locator yang TIDAK ADA tetap
 * MENUNGGU sampai batas waktu test — `.catch()` menangkapnya terlambat, ketika
 * anggaran waktu kasusnya sudah habis, dan kegagalannya terbaca "halaman
 * tertutup" alih-alih "elemennya tidak ada". Ketiga pembungkus ini memeriksa
 * keberadaannya lebih dulu.
 */
export async function adaLoc(loc: { count(): Promise<number> }): Promise<boolean> {
  return (await loc.count()) > 0
}

export async function bisaDiisi(loc: any, bila_tidak_ada: boolean | null = null): Promise<boolean | null> {
  if (!(await adaLoc(loc))) return bila_tidak_ada
  return loc.isEnabled({ timeout: 3000 }).catch(() => false)
}

export async function nilaiAman(loc: any, bawaan = ''): Promise<string> {
  if (!(await adaLoc(loc))) return bawaan
  return loc.inputValue({ timeout: 3000 }).catch(() => bawaan)
}

export async function teksAman(loc: any, bawaan = ''): Promise<string> {
  if (!(await adaLoc(loc))) return bawaan
  return loc.innerText({ timeout: 3000 }).then((t: string) => t.replace(/\s+/g, ' ').trim()).catch(() => bawaan)
}

/**
 * Mengisi sebuah Form stasiun web SECUKUPNYA untuk dapat disimpan, pada stasiun
 * apa pun. Bentuk formnya berbeda jauh antar stasiun (Weighbridge memakai
 * record-datetime dan dua berat, Grading memakai parameter muatan, 14 stasiun
 * lain memakai grid 24 slot), jadi nilainya DITURUNKAN dari jenis dan nama
 * kolomnya — bukan dari daftar kolom per stasiun yang harus dirawat 18 kali.
 */
export async function isiFormStasiun(page: Page, kode: string, tanggal: string): Promise<{
  line: string; kolomTerisi: number; barisDitambah: number
}> {
  // Business Unit dulu bila Admin, lalu Production Line: daftar line baru terisi
  // sesudah mill pasti, dan kolom lain sering baru muncul sesudah line pasti.
  const bu = page.getByTestId('business-unit-select')
  if (await bu.count() && await bu.isEnabled().catch(() => false)) {
    const o = await bu.locator('option').evaluateAll((x) =>
      x.map((e) => (e as HTMLOptionElement).value).filter((v) => v !== ''))
    if (o.length) { await bu.selectOption(o[0]); await page.waitForTimeout(900) }
  }
  let line = ''
  const sel = page.getByTestId('production-line-select')
  if (await sel.count()) {
    // Opsinya bisa terisi belakangan (Livewire), jadi ditunggu sebentar.
    for (let c = 0; c < 6; c++) {
      const opsi = await sel.locator('option').evaluateAll((o) =>
        o.map((e) => ({ v: (e as HTMLOptionElement).value, t: (e.textContent ?? '').trim() })).filter((x) => x.v !== ''))
      if (opsi.length) {
        await sel.selectOption(opsi[0].v)
        line = opsi[0].t
        await page.waitForTimeout(900)
        break
      }
      await page.waitForTimeout(600)
    }
  }

  // Nilai per kolom diturunkan dari tipe dan nama testidnya.
  const nilaiUntuk = (tipe: string, testid: string, idx: number): string => {
    const t = testid.toLowerCase()
    if (tipe === 'date') return tanggal
    if (tipe === 'datetime-local') return `${tanggal}T08:00`
    if (tipe === 'time') return '08:00'
    if (/gross/.test(t)) return '12000'
    if (/tare/.test(t)) return '4000'
    if (/net|netto/.test(t)) return '8000'
    if (/weight|berat/.test(t)) return '1200'
    if (/quantity|qty|jumlah/.test(t)) return '6'
    if (/percent|persen/.test(t)) return '4'
    if (/temp/.test(t)) return '62'
    if (/pressure|bar/.test(t)) return '3'
    if (/rpm|speed/.test(t)) return '900'
    if (/amps/.test(t)) return '30'
    if (tipe === 'number') return '5'
    if (/-id-input$/.test(t) || /number-input$/.test(t) || /card/.test(t)) return `QA${Date.now().toString().slice(-6)}${idx}`
    if (/plate/.test(t)) return 'B 1234 QA'
    if (/driver|name/.test(t)) return 'Penguji QA'
    if (/estate|supplier|destination|division|block/.test(t)) return 'QA Uji'
    if (/note|catatan|finding/.test(t)) return 'Diisi pelaksana uji QA.'
    return 'QA'
  }

  // Satu baris grid, bila stasiun ini memakai grid slot waktu.
  let barisDitambah = 0
  const tambahBaris = page.getByTestId('add-row-button')
  if (await tambahBaris.count()) {
    await tambahBaris.click()
    await page.waitForTimeout(900)
    barisDitambah = 1
    // Slot waktu / parameter pada baris baru.
    const selBaris = page.locator('[data-testid^="time-slot-select-"], [data-testid^="detail-parameter-select-"], [data-testid^="detail-hour-select-"]')
    const n = await selBaris.count()
    if (n) {
      const terakhir = selBaris.nth(n - 1)
      const o = await terakhir.locator('option').evaluateAll((x) =>
        x.map((e) => (e as HTMLSelectElement).value).filter((v) => v !== ''))
      if (o.length) { await terakhir.selectOption(o[0]); await page.waitForTimeout(500) }
    }
  }

  // Seluruh SELECT lain yang masih kosong (mis. "WB Card No" wajib di Grading,
  // jenis muatan, parameter). Tanpa ini penyimpanan ditolak oleh kolom yang
  // bahkan tidak pernah diisi pelaksana.
  const semuaSelect = page.locator('select:visible')
  const jmlSel = await semuaSelect.count()
  for (let i = 0; i < jmlSel; i++) {
    const sl = semuaSelect.nth(i)
    if (!(await sl.isEnabled().catch(() => false))) continue
    if ((await sl.inputValue().catch(() => '')) !== '') continue
    const o = await sl.locator('option').evaluateAll((x) =>
      x.map((e) => (e as HTMLSelectElement).value).filter((v) => v !== ''))
    if (o.length) { await sl.selectOption(o[0]).catch(() => {}); await page.waitForTimeout(350) }
  }

  // Minimal satu kotak centang pada baris grid, bila stasiun ini memintanya
  // (Cages Track menolak baris tanpa satu cage pun tercentang).
  // Dicari di SELURUH grid baris, apa pun nama penandanya: Cages Track memakai
  // cages-tipped-time-row-X dan detail-cage-X, bukan *-detail-row-X.
  const centang = page.locator(
    '[data-testid*="-row-"] input[type="checkbox"]:visible, [data-testid^="detail-cage-"]:visible, [data-testid*="grid"] input[type="checkbox"]:visible')
  const jmlCentang = await centang.count()
  for (let i = 0; i < Math.min(2, jmlCentang); i++) await centang.nth(i).check({ timeout: 2500 }).catch(() => {})

  // Seluruh kolom teks/angka/tanggal yang terlihat dan masih bisa diisi.
  const kolom = page.locator('input[data-testid]:visible, textarea[data-testid]:visible')
  const jml = await kolom.count()
  let kolomTerisi = 0
  for (let i = 0; i < jml; i++) {
    const k = kolom.nth(i)
    const tipe = (await k.getAttribute('type')) ?? 'text'
    if (['checkbox', 'radio', 'file', 'hidden'].includes(tipe)) continue
    const testid = (await k.getAttribute('data-testid')) ?? ''
    if (!(await k.isEnabled().catch(() => false))) continue
    if (await k.evaluate((e) => (e as HTMLInputElement).readOnly).catch(() => true)) continue
    const sudah = await k.inputValue().catch(() => '')
    if (sudah !== '') continue
    await k.fill(nilaiUntuk(tipe, testid, i)).catch(() => {})
    kolomTerisi++
  }
  return { line, kolomTerisi, barisDitambah }
}
