/**
 * Penolong layar Kelola (master data). Bentuk layarnya sama di delapan tingkat:
 * tabel .kc-table, tombol Tambah (openCreateForm), tombol Edit/Hapus per baris,
 * modal dengan #code dan #name, dan pemilih induk berupa combobox Alpine
 * (x-searchable-select) — BUKAN <select>, jadi ia harus diklik lalu opsinya
 * dipilih, tidak bisa selectOption().
 */
import type { Page } from '@playwright/test'

export async function bukaTambah(page: Page): Promise<boolean> {
  // Namanya BUKAN "Tambah" persis — tombolnya berbunyi "+ Tambah Business Unit",
  // jadi pola berpaku awal tidak akan pernah cocok dan modalnya tidak terbuka,
  // sementara langkah-langkah sesudahnya tetap jalan dan menggantung.
  const t = page.getByRole('button', { name: /Tambah/i }).first()
  if (!(await t.count())) return false
  await t.click()
  await page.waitForTimeout(900)
  return true
}

export async function isiTeks(page: Page, id: string, nilai: string): Promise<boolean> {
  const f = page.locator(`#${id}`)
  if (!(await f.count())) return false
  await f.fill(nilai)
  await page.waitForTimeout(250)
  return true
}

/** Memilih satu opsi pada combobox pencarian (x-searchable-select). */
export async function pilihKombo(page: Page, id: string, cocok?: RegExp): Promise<string> {
  const inp = page.locator(`#${id}`)
  if (!(await inp.count())) return ''
  await inp.click()
  await page.waitForTimeout(500)
  // Opsi DISARING menurut id comboboxnya. Halaman ini punya lebih dari satu
  // combobox (penyaring di bilah filter dan pemilih induk di modal), dan
  // li[role="option"] apa adanya akan mengenai listbox penyaring yang sedang
  // tersembunyi — kliknya menggantung sampai batas waktu.
  const opsi = page.locator(`li[id^="${id}-option-"]`)
  const n = await opsi.count()
  if (!n) return ''
  // Opsi pertama biasanya PENAMPUNG ("-- Pilih Company --", "Semua Company"),
  // bukan nilai. Memilihnya terbaca seolah induk sudah dipilih padahal belum,
  // sehingga simpan ditolak dan kasusnya gagal tanpa sebab yang kelihatan.
  const teks: string[] = []
  for (let i = 0; i < n; i++) teks.push((await opsi.nth(i).innerText()).trim())
  const bukanPenampung = (t: string) => !/^(--|Semua\b|Pilih\b)/i.test(t) && t !== ''
  let ke = teks.findIndex(bukanPenampung)
  if (ke < 0) ke = 0
  if (cocok) {
    const c = teks.findIndex((t, i) => cocok.test(t) && bukanPenampung(t) && i >= 0)
    if (c >= 0) ke = c
  }
  const label = teks[ke] ?? ''
  await opsi.nth(ke).click()
  await page.waitForTimeout(600)
  return label
}

export async function simpan(page: Page): Promise<void> {
  // Tombol simpan membawa <x-busy-label>, yang merender DUA teks ("Menyimpan…"
  // dan "Simpan") di dalam satu tombol — nama aksesibelnya memuat keduanya,
  // sehingga pola /^Simpan$/ tidak cocok. Disasar lewat type=submit, yang
  // satu-satunya di modal.
  // Disaring ke yang TERLIHAT: widget chatbot di kerangka halaman juga punya
  // button[type=submit] yang tersembunyi, dan .last() akan mengenainya.
  const kandidat = page.locator('button[type="submit"]:visible')
  if (await kandidat.count()) await kandidat.last().click()
  else await page.getByRole('button', { name: /Simpan/i }).first().click()
  await page.waitForTimeout(2000)
}

/** Jumlah data yang tertulis pada bilah filter ("Total 23"), bila ada. */
export async function jumlahTertulis(page: Page): Promise<number | null> {
  const t = (await page.locator('body').innerText()).replace(/\s+/g, ' ')
  const m = t.match(/(?:Total|Menampilkan)[^0-9]{0,12}(\d+)/i)
  return m ? Number(m[1]) : null
}

export async function jumlahBaris(page: Page): Promise<number> {
  return page.locator('.kc-table__row:not(.kc-table__row--static)').count()
}

/** Mencari baris yang memuat teks, menelusuri halaman bila perlu. */
export async function cariBaris(page: Page, teks: string, maks = 25) {
  for (let h = 0; h < maks; h++) {
    const baris = page.locator('.kc-table__row', { hasText: teks })
    if (await baris.count()) return baris.first()
    const lanjut = page.getByRole('button', { name: /Berikutnya|Selanjutnya|Next/i }).first()
    if (!(await lanjut.count()) || !(await lanjut.isEnabled())) return null
    await lanjut.click()
    await page.waitForTimeout(700)
  }
  return null
}

/** Galat yang tercetak DI SEBELAH satu kolom (bukan pesan umum di atas form). */
export async function galatDiKolom(page: Page, id: string): Promise<string> {
  const f = page.locator(`#${id}`)
  if (!(await f.count())) return ''
  return f.evaluate((el) => {
    const bungkus = (el as HTMLElement).closest('.kc-form-field, .form-field, .md-field')
    return (bungkus?.querySelector('.kc-form-field__error, .form-field__error, .md-field__error')?.textContent ?? '').trim()
  })
}

/** Semua galat per-kolom yang sedang tampil di modal. */
export async function galatPerKolom(page: Page): Promise<string[]> {
  return page.locator('.kc-form-field__error, .form-field__error').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').trim()).filter(Boolean))
}

/** Pesan umum di atas form / banner halaman. */
export async function galatUmum(page: Page): Promise<string[]> {
  return page.locator('.kc-alert, [data-testid="form-error"], .settings-toast--error').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()).filter(Boolean))
}

/**
 * Menutup modal bila memang terbuka. Mengklik tombol yang tidak ada dengan
 * .catch() TIDAK aman: klik Playwright menunggu sampai batas waktu TEST, jadi
 * satu panggilan ke tombol yang sudah hilang memakan seluruh anggaran waktu
 * kasusnya dan kegagalannya terbaca sebagai "halaman tertutup", bukan sebagai
 * tombol yang tidak ada.
 */
export async function tutupModal(page: Page): Promise<void> {
  const b = page.getByRole('button', { name: /^(Batal|Tutup)$/i }).first()
  if (await b.count() && await b.isVisible().catch(() => false)) {
    await b.click({ timeout: 3000 }).catch(() => {})
    await page.waitForTimeout(500)
  }
}
