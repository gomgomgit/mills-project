import { expect, type Page } from '@playwright/test'

/**
 * Menunggu putaran Livewire yang dipicu sebuah kontrol `wire:model.live`.
 *
 * ── MENGAPA INI ADA, SEJAK 2026-10-02 ───────────────────────────────────
 *
 * Form Grading dan Form Weighbridge memilih Business Unit lewat <select>
 * ber-`wire:model.live`, lalu spec-nya LANGSUNG mengetik ke input biasa di
 * bawahnya. Putaran Livewire dari pemilihan itu mendarat SESUDAHNYA dan
 * me-render ulang form dengan nilai komponen — menghapus apa yang baru
 * diketik. Gejalanya menyesatkan sepenuhnya: penyimpanan ditolak dengan
 * "Grading Number wajib diisi." padahal spec jelas-jelas mengisinya, dan
 * yang terlihat di layar hanyalah galat field wajib pada field yang terisi.
 *
 * Dibuktikan dari respons server di dalam trace.zip, bukan dari tangkapan
 * layar: tangkapan layar hanya menunjukkan form kosong, yang mengundang
 * kesimpulan yang salah (dan tiga kali hari ini memang menyesatkan saya).
 *
 * ── MENGAPA MENUNGGU RESPONS, BUKAN MENUNGGU ELEMEN ─────────────────────
 *
 * Menunggu efek samping yang terlihat (opsi dropdown terisi, field
 * ter-autofill) hanya bekerja bila putaran itu PUNYA efek yang terlihat.
 * Pemilihan Business Unit di Form Weighbridge tidak punya. Menunggu
 * `/livewire/update` menyatakan maksudnya apa adanya — "tunggu putaran yang
 * baru saja saya picu" — dan berlaku untuk kontrol mana pun.
 *
 * Bukan `waitForTimeout`: tidur sembarang akan hijau di mesin cepat dan
 * merah di mesin sibuk, yang justru jenis kegagalan paling mahal.
 */
export async function selectLive(
  page: Page,
  testId: string,
  option: { label: string } | { index: number } | { value: string },
): Promise<void> {
  await Promise.all([
    page.waitForResponse((response) => response.url().includes('/livewire/update')),
    page.locator(`[data-testid="${testId}"]`).selectOption(option),
  ])
}

/**
 * Mengisi sebuah input LALU memastikan nilainya benar-benar tinggal.
 *
 * Penjaga terhadap kelas kegagalan yang sama dari arah sebaliknya: kalau
 * sebuah putaran Livewire yang belum selesai menghapusnya, kegagalannya
 * menunjuk ke input yang bersangkutan di sini — bukan muncul belasan langkah
 * kemudian sebagai galat "wajib diisi" pada field yang sudah diisi.
 */
export async function fillAndKeep(page: Page, testId: string, value: string): Promise<void> {
  const input = page.locator(`[data-testid="${testId}"]`)

  await input.fill(value)
  await expect(input).toHaveValue(value)
}
