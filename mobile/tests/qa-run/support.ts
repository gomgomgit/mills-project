/**
 * Modul bersama pelaksana kasus uji QA untuk aplikasi MOBILE.
 *
 * Aplikasi mobile ini adalah SPA biasa sebelum dibungkus Capacitor, jadi
 * seluruh layarnya dapat dijalankan di browser terhadap Vite dev server —
 * sama seperti layar web. Yang TIDAK dapat dijalankan dari sini hanyalah hal
 * yang menuntut perangkat sungguhan: mengubah jam sistem dan menilai tata
 * letak dengan mata.
 */
import { appendFileSync, mkdirSync } from 'node:fs'
import type { Page } from '@playwright/test'

export const KELUARAN = 'tests/qa-run/hasil-mobile.jsonl'

export type Nilai = 'Lulus' | 'Gagal' | 'Tidak diuji'

export const AKUN = {
  OPR: { username: 'operator01', password: 'Passw0rd!' },
  SPV: { username: 'supervisor01', password: 'Passw0rd!' },
  MGR: { username: 'millmanagement-a', password: 'Passw0rd!' },
  ADM: { username: 'admin', password: 'Passw0rd!' },
}

export function catat(id: string, varian: string, hasil: Nilai, diamati: string): void {
  mkdirSync('tests/qa-run', { recursive: true })
  appendFileSync(KELUARAN, JSON.stringify({ id, varian, hasil, diamati }) + '\n', 'utf8')
}

export async function masuk(page: Page, akun = AKUN.OPR): Promise<void> {
  await page.goto('/login')
  await page.locator('#username').fill(akun.username)
  await page.locator('#password').fill(akun.password)
  await page.getByRole('button', { name: 'Login' }).click()
  await page.waitForURL('**/home', { timeout: 30_000 })
}

export async function keluar(page: Page): Promise<void> {
  await page.context().clearCookies()
  await page.evaluate(() => { try { localStorage.clear() } catch { /* mode privat */ } })
}

export async function teksHalaman(page: Page): Promise<string> {
  return (await page.locator('body').innerText()).replace(/\s+/g, ' ').trim()
}

export async function teksAman(loc: any, bawaan = ''): Promise<string> {
  if (!(await loc.count())) return bawaan
  return loc.innerText({ timeout: 3000 }).then((t: string) => t.replace(/\s+/g, ' ').trim()).catch(() => bawaan)
}

export async function nilaiAman(loc: any, bawaan = ''): Promise<string> {
  if (!(await loc.count())) return bawaan
  return loc.inputValue({ timeout: 3000 }).catch(() => bawaan)
}

export async function bisaDiisi(loc: any, bilaTidakAda: boolean | null = null): Promise<boolean | null> {
  if (!(await loc.count())) return bilaTidakAda
  const aktif = await loc.isEnabled({ timeout: 3000 }).catch(() => false)
  if (!aktif) return false
  return !(await loc.evaluate((e: HTMLInputElement) => e.readOnly).catch(() => false))
}

export const POLA_TEKNIS = /(TypeError|undefined is not|Cannot read|SQLITE_|500 Internal|Unhandled)/i

/** 18 jenis stasiun, kode bertanda hubung. */
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

/** Empat stasiun per-transaksi: tanpa grid 24 slot waktu. */
export const PER_TRANSAKSI = ['weighbridge', 'grading', 'kernel-dispatch', 'cpo-dispatch']

/** 11 stasiun yang laporan periodenya sudah ada. */
export const BERLAPORAN = [
  'weighbridge', 'grading', 'cages-track', 'sterilizer', 'threshing', 'pressing',
  'clarification', 'kernel-plant', 'boiler-room', 'depricarping', 'storage-tank',
]

/**
 * Memilih Production Line pada Station List bila pemilihnya ada. Pilihan ini
 * diingat perangkat (localStorage) dan dibagi ke seluruh layar stasiun, jadi
 * sekali dipilih ia berlaku untuk Monitor, Form, dan Data Preview.
 */
export async function pilihLine(page: Page, ke = 0): Promise<string> {
  // Pemilih line di mobile BUKAN <select>: ia tombol production-line-switcher
  // yang membuka daftar tombol production-line-option-<id>. Dan saat belum ada
  // line terpilih, pemilihnya langsung terbuka menggantikan grid stasiun —
  // jadi tombol pembukanya belum tentu ada.
  const pembuka = page.getByTestId('production-line-switcher')
  if (await pembuka.count()) {
    await pembuka.click()
    await page.waitForTimeout(700)
  }
  const opsi = page.locator('[data-testid^="production-line-option-"]')
  const n = await opsi.count()
  if (!n) return ''
  const target = opsi.nth(Math.min(ke, n - 1))
  const nama = (await target.innerText()).replace(/\s+/g, ' ').trim()
  await target.click()
  await page.waitForTimeout(1200)
  return nama
}

/**
 * Mengisi Form stasiun mobile secukupnya untuk dapat disimpan, pada stasiun
 * apa pun. Kolom identitas dan kolom bacaan berbeda per stasiun, jadi
 * nilainya diturunkan dari jenis dan penanda kolomnya — bukan dari daftar
 * per stasiun yang harus dirawat 18 kali.
 */
export async function isiForm(page: Page, opsi: { isiBacaan?: boolean } = {}): Promise<{
  identitas: string; kolomTerisi: number; slot: string; barisDitambah: number
}> {
  const isiBacaan = opsi.isiBacaan ?? true
  const cap = Date.now().toString().slice(-6)

  /** Nilai yang masuk akal untuk satu kolom, diturunkan dari tipe dan labelnya. */
  const nilaiUntuk = (tipe: string, label: string, i: number): string => {
    const l = label.toLowerCase()
    if (tipe === 'date') return new Date().toISOString().slice(0, 10)
    if (tipe === 'time') return `0${7 + (i % 3)}:00`.slice(-5)
    if (tipe === 'datetime-local') return `${new Date().toISOString().slice(0, 10)}T08:00`
    if (/gross|masuk/.test(l)) return '12000'
    if (/tare|keluar/.test(l)) return '4000'
    if (/netto|net\b/.test(l)) return '8000'
    if (/berat|weight|kg/.test(l)) return '1200'
    if (/qty|quantity|jumlah|cages|lori/.test(l)) return '6'
    if (/persen|percent|%/.test(l)) return '4'
    if (/temp|suhu/.test(l)) return '62'
    if (/kendaraan|plate|nopol/.test(l)) return `B 12${cap.slice(-2)} QA`
    if (/sopir|driver|nama|petugas|operator/.test(l)) return 'Penguji QA'
    if (/estate|supplier|asal|tujuan|destination|divisi|blok/.test(l)) return 'QA Uji'
    if (/card|kartu|no\.|nomor|id\b/.test(l)) return `QA${cap}${i}`
    if (tipe === 'number') return '5'
    return `QA${cap}${i}`
  }

  /** Label kolom: dibaca dari <label for> atau placeholder-nya. */
  const labelDari = async (loc: any): Promise<string> => {
    return loc.evaluate((e: HTMLInputElement) => {
      const id = e.getAttribute('id')
      const lab = id ? document.querySelector(`label[for="${id}"]`) : null
      return (lab?.textContent ?? e.getAttribute('placeholder') ?? e.getAttribute('aria-label') ?? '').trim()
    }).catch(() => '')
  }

  /** Mengisi setiap kolom yang masih kosong di dalam satu lingkup. */
  const isiSemua = async (lingkup: any): Promise<number> => {
    const kolom = lingkup.locator('input:not([disabled]):not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([role="combobox"]), textarea:not([disabled])')
    const n = await kolom.count()
    let terisi = 0
    for (let i = 0; i < n; i++) {
      const k = kolom.nth(i)
      if (!(await k.isVisible().catch(() => false))) continue
      if (await k.evaluate((e: HTMLInputElement) => e.readOnly).catch(() => true)) continue
      if ((await k.inputValue().catch(() => '')) !== '') continue
      const tipe = (await k.getAttribute('type')) ?? 'text'
      const label = await labelDari(k)
      await k.fill(nilaiUntuk(tipe, label, i)).catch(() => {})
      terisi++
    }
    // Pilihan (select biasa) yang masih kosong.
    const sel = lingkup.locator('select:not([disabled])')
    const nS = await sel.count()
    for (let i = 0; i < nS; i++) {
      const sl = sel.nth(i)
      if ((await sl.inputValue().catch(() => '')) !== '') continue
      const o = await sl.locator('option').evaluateAll((x: any[]) =>
        x.map((e) => (e as HTMLSelectElement).value).filter((v) => v !== ''))
      if (o.length) { await sl.selectOption(o[0]).catch(() => {}); terisi++ }
    }
    // Combobox pencarian (SearchableSelect) selain slot waktu.
    const kombo = lingkup.locator('input[role="combobox"]')
    const nK = await kombo.count()
    for (let i = 0; i < nK; i++) {
      const kb = kombo.nth(i)
      const id = (await kb.getAttribute('id')) ?? ''
      if (/time-slot/.test(id)) continue
      if ((await kb.inputValue().catch(() => '')) !== '') continue
      await kb.click().catch(() => {})
      await page.waitForTimeout(500)
      const li = page.locator('[role="option"]:visible')
      if (await li.count()) { await li.first().click().catch(() => {}); terisi++ }
      else await page.keyboard.press('Escape')
      await page.waitForTimeout(300)
    }
    return terisi
  }

  // SELURUH kolom header yang wajib diisi — bukan hanya kolom identitas.
  // Tiap stasiun punya kolom wajibnya sendiri (Weighbridge butuh nomor
  // kendaraan, nama sopir, estate, dan berat masuk; Cages Track butuh Cages
  // Out dan Cages Tipped), jadi mengisi satu kolom saja membuat penyimpanan
  // ditolak oleh kolom yang bahkan tidak pernah disentuh pelaksana.
  const header = page.locator('section').filter({ hasNot: page.locator('[data-testid$="-detail-row"]') })
  let kolomTerisi = await isiSemua(page.locator('form, main').first())
  const identitas = await page.locator('input.form-field-input:not([disabled])').first().inputValue().catch(() => '')
  void header

  // Satu baris grid, bila stasiun ini memakainya. Nama tombolnya BERBEDA antar
  // stasiun — add-detail-row-button (14 stasiun), add-row-button (Sterilizer),
  // add-tipped-time-row-button (Cages Track) — jadi ketiganya dicoba. Memaku
  // satu nama membuat stasiun lain tersimpan tanpa satu baris pun dan ditolak
  // "minimal satu baris harus diisi".
  let slot = ''
  let barisDitambah = 0
  let tambah = page.getByTestId('add-detail-row-button')
  if (!(await tambah.count())) tambah = page.getByTestId('add-row-button')
  if (!(await tambah.count())) tambah = page.getByTestId('add-tipped-time-row-button')
  if (await tambah.count()) {
    await tambah.click()
    await page.waitForTimeout(900)
    barisDitambah = 1
    const n = await page.locator('[data-testid^="time-slot-select-"]').count()
    if (n) {
      const dipilih = await pilihSlot(page, n - 1, 0)
      slot = dipilih.nilai || (dipilih.opsi[0] ?? '')
    } else {
      // Cages Track menamai kendali jamnya tipped-hour-select-X.
      const jam = page.locator('[data-testid^="tipped-hour-select-"]')
      const nJ = await jam.count()
      if (nJ) {
        const sl = jam.nth(nJ - 1)
        const o = await sl.locator('option').evaluateAll((x) =>
          x.map((e) => ({ v: (e as HTMLSelectElement).value, t: (e.textContent ?? '').trim() })).filter((y) => y.v !== ''))
        if (o.length) { await sl.selectOption(o[0].v); slot = o[0].t; await page.waitForTimeout(500) }
        else {
          const kb = sl.locator('input[role="combobox"]')
          if (await kb.count()) {
            await kb.click(); await page.waitForTimeout(500)
            const li = page.locator('[role="option"]:visible')
            if (await li.count()) { slot = (await li.first().innerText()).trim(); await li.first().click() }
            await page.waitForTimeout(400)
          }
        }
      }
    }
    if (isiBacaan) {
      // Lingkup diambil dari baris yang MEMUAT kendali slot/jam itu sendiri.
      // Pola seluas '[data-testid$="-row"]' ikut mengenai baris tabel Target
      // Operasional, dan mengisi baris itu membuat kolom bacaan yang sebenarnya
      // tetap kosong — penyimpanan lalu ditolak "minimal satu baris harus
      // diisi" pada stasiun yang sebelumnya lulus.
      // Baris grid dikenali dari PENANDA BARISNYA lebih dulu. Menelusuri induk
      // terdekat kendali slot tidak cukup: induk itu hanyalah pembungkus
      // .form-field milik kendali itu sendiri (satu input), sehingga kolom
      // bacaan di sel-sel lain pada baris yang sama tidak pernah terisi.
      let lingkup = page.locator('form, main').first()
      const baris = page.locator(
        '[data-testid$="-detail-row"], [data-testid^="detail-row-"], [data-testid="tipped-time-row"], [data-testid="grading-detail-row"]').last()
      if (await baris.count()) lingkup = baris
      else {
        const kendali = page.locator('[data-testid^="time-slot-select-"], [data-testid^="tipped-hour-select-"], [data-testid^="detail-close-door-time-"]').last()
        if (await kendali.count()) {
          const induk = kendali.locator('xpath=ancestor::*[self::tr or self::li or self::fieldset][1]')
          if (await induk.count()) lingkup = induk.first()
        }
      }
      kolomTerisi += await isiSemua(lingkup)
      // Minimal satu kotak centang pada baris, bila barisnya memintanya
      // (Cages Track menolak baris tanpa satu cage pun tercentang).
      const centang = lingkup.locator('input[type="checkbox"]:visible')
      if (await centang.count()) await centang.first().check({ timeout: 2500 }).catch(() => {})
      else {
        // Cages Track menaruh kotak centang cage di grid tersendiri
        // (cage-checkbox-<baris>-<cage>), di luar pembungkus barisnya.
        const cage = page.locator('[data-testid^="cage-checkbox-"] input[type="checkbox"]:visible, input[data-testid^="cage-checkbox-"]:visible')
        if (await cage.count()) await cage.first().check({ timeout: 2500 }).catch(() => {})
      }
    }
  }
  return { identitas, kolomTerisi, slot, barisDitambah }
}

/**
 * Memilih slot waktu pada baris grid ke-`baris`. Kendalinya BUKAN <select>
 * melainkan combobox pencarian (components/SearchableSelect.vue): sebuah div
 * berpenanda time-slot-select-<n> yang memuat input role="combobox" dan
 * listbox role="option". Memperlakukannya sebagai <select> membuat seluruh
 * kasus slot terbaca "0 pilihan" padahal pilihannya ada.
 */
export async function pilihSlot(page: Page, baris = 0, ke = 0): Promise<{ nilai: string; opsi: string[] }> {
  const kotak = page.locator(`[data-testid="time-slot-select-${baris}"]`)
  if (!(await kotak.count())) return { nilai: '', opsi: [] }
  const input = kotak.locator('input[role="combobox"]')
  await input.click()
  await page.waitForTimeout(600)
  const opsi = await kotak.locator('[role="option"]').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()).filter(Boolean))
  if (!opsi.length) { await page.keyboard.press('Escape'); return { nilai: '', opsi: [] } }
  const target = kotak.locator('[role="option"]').nth(Math.min(ke, opsi.length - 1))
  await target.click()
  await page.waitForTimeout(700)
  return { nilai: await input.inputValue().catch(() => ''), opsi }
}

/** Daftar pilihan slot pada baris tertentu, tanpa memilih apa pun. */
export async function opsiSlot(page: Page, baris = 0): Promise<string[]> {
  const kotak = page.locator(`[data-testid="time-slot-select-${baris}"]`)
  if (!(await kotak.count())) return []
  const input = kotak.locator('input[role="combobox"]')
  await input.click()
  await page.waitForTimeout(600)
  const opsi = await kotak.locator('[role="option"]').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()).filter(Boolean))
  await page.keyboard.press('Escape')
  await page.waitForTimeout(300)
  return opsi
}

/**
 * Membuka Data Preview satu stasiun dan menekan pintasan "Semua", supaya
 * record dari tanggal mana pun ikut terlihat (filter tanggal bawaannya
 * hari ini).
 */
export async function bukaPreview(page: Page, kode: string): Promise<number> {
  await page.goto(`/stations/${kode}/preview`)
  await page.waitForTimeout(1700)
  const semua = page.getByTestId('date-quick-all')
  if (await semua.count()) { await semua.click(); await page.waitForTimeout(1300) }
  return page.locator('[data-testid^="record-item-"]').count()
}

/** Baris record pada Data Preview yang memuat teks tertentu. */
export function barisRecord(page: Page, teks: string) {
  return page.locator('[data-testid^="record-item-"]').filter({ hasText: teks }).first()
}
