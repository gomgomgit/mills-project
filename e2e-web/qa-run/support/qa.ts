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
