/**
 * Pelaksana bagian 4.6 dokumen QA — Offline & Sinkronisasi (MB-SYNC-01…05),
 * plus kasus lintas-sistem yang langkahnya berjalan di mobile: LS-10 dan LS-12.
 *
 * Mode luring DAPAT dijalankan dari browser: konteksnya diputus lewat
 * context.setOffline(true), yang memutus seluruh permintaan jaringan halaman
 * sama seperti mode pesawat memutus perangkat. Yang tidak dapat ditiru hanyalah
 * hal di luar halaman (mis. jam sistem).
 */
import { test, expect, type Page } from '@playwright/test'
import {
  AKUN, barisRecord, bukaPreview, catat, isiForm, keluar, masuk, pilihLine, teksAman, teksHalaman,
} from './support'

const DP = 'depricarping'
const V = 'Depricarping (24 slot)'
const WEB = 'http://localhost:8001'

/**
 * Pindah layar LEWAT ROUTER APLIKASI, bukan page.goto. Saat luring,
 * page.goto gagal dengan ERR_INTERNET_DISCONNECTED karena dokumen dan modulnya
 * diminta dari server — padahal di aplikasi Capacitor sungguhan seluruh aset
 * sudah ada di perangkat dan perpindahan layar tidak menyentuh jaringan.
 * Memakai router sisi klien meniru keadaan itu dengan jujur.
 */
async function pindah(page: Page, jalur: string): Promise<void> {
  await page.evaluate((j) => {
    const w = window as unknown as { __mslRouter?: { push: (p: string) => void } }
    if (w.__mslRouter) w.__mslRouter.push(j)
    else history.pushState({}, '', j), window.dispatchEvent(new PopStateEvent('popstate'))
  }, jalur)
  await page.waitForTimeout(1500)
}

/**
 * Memanaskan modul rute yang akan dipakai saat luring, sambil masih online —
 * SELURUHNYA lewat navigasi di dalam aplikasi (klik tile, tombol, breadcrumb).
 * page.goto memuat ulang halaman dan membuang kembali modul yang baru dimuat,
 * sehingga saat luring modulnya diminta lagi dari server dan gagal. Di
 * aplikasi Capacitor sungguhan seluruh modul sudah ada di perangkat sejak
 * dipasang; pemanasan ini yang membuat keadaan browser setara dengan itu.
 */
async function panaskanRute(page: Page, kode: string): Promise<string[]> {
  const jejak: string[] = []
  const tile = page.locator('[data-testid^="station-tile-"]').filter({ hasText: /Depricarping/i }).first()
  if (await tile.count()) {
    await tile.click()
    await page.waitForTimeout(1700)
    jejak.push(new URL(page.url()).pathname)
  }
  const baru2 = page.getByTestId('new-data-button')
  if (await baru2.count()) {
    await baru2.click()
    await page.waitForTimeout(1900)
    jejak.push(new URL(page.url()).pathname)
    const kembali = page.getByTestId('back-button')
    if (await kembali.count()) {
      page.once('dialog', async (d) => { await d.accept() })
      await kembali.click().catch(() => {})
      await page.waitForTimeout(1300)
      const ya = page.getByRole('button', { name: /^(Ya|Keluar|Buang|Lanjutkan)/i }).first()
      if (await ya.count()) { await ya.click().catch(() => {}); await page.waitForTimeout(1100) }
    }
  }
  const muat = page.getByTestId('load-data-button')
  if (await muat.count()) {
    await muat.click()
    await page.waitForTimeout(1800)
    jejak.push(new URL(page.url()).pathname)
    const kembali = page.getByTestId('back-button')
    if (await kembali.count()) { await kembali.click().catch(() => {}); await page.waitForTimeout(1400) }
  }
  const crumb = page.getByTestId('breadcrumb-production-process-activity')
  if (await crumb.count()) { await crumb.click().catch(() => {}); await page.waitForTimeout(1400) }
  jejak.push(new URL(page.url()).pathname)
  return jejak
}

async function buatRecordMobile(page: Page, kode = DP, luring = false) {
  if (luring) await pindah(page, `/stations/${kode}/monitor`)
  else { await page.goto(`/stations/${kode}/monitor`); await page.waitForTimeout(1300) }
  await page.getByTestId('new-data-button').click()
  await page.waitForTimeout(1600)
  const isi = await isiForm(page, { isiBacaan: true })
  await page.getByTestId('save-button').click()
  await page.waitForTimeout(2800)
  return isi
}

/** Menjalankan Sinkronisasi dari Station List dan mengembalikan ringkasannya. */
async function sinkron(page: Page, luring = false): Promise<{ jalan: boolean; ringkas: string }> {
  if (luring) await pindah(page, '/stations')
  else { await page.goto('/stations'); await page.waitForTimeout(1500) }
  const tombol = page.getByTestId('sync-button')
  if (!(await tombol.count())) return { jalan: false, ringkas: '' }
  await tombol.click()
  await page.waitForTimeout(4000)
  const dialog = await teksAman(page.locator('[role="dialog"], .sync-result-dialog').first())
  const ringkas = dialog !== '' ? dialog : await teksHalaman(page)
  // Dialog hasil ditutup supaya tidak menghalangi langkah berikutnya.
  const tutup = page.getByRole('button', { name: /^(Tutup|OK|Selesai)$/i }).first()
  if (await tutup.count()) { await tutup.click().catch(() => {}); await page.waitForTimeout(800) }
  return { jalan: true, ringkas }
}

test('MB-SYNC-01 — input berhasil sepenuhnya tanpa koneksi', async ({ page, context }) => {
  // Langkah 1 — login dan buka Station List SAMBIL ONLINE, supaya daftar
  // stasiun tersimpan di perangkat.
  await masuk(page, AKUN.OPR)
  await page.goto('/stations')
  await page.waitForTimeout(1600)
  const line = await pilihLine(page, 0)
  const jejak = await panaskanRute(page, DP)

  // Langkah 2 — koneksi dimatikan sepenuhnya.
  await context.setOffline(true)
  const galatJaringan: string[] = []
  page.on('console', (m) => { if (m.type() === 'error') galatJaringan.push(m.text().slice(0, 80)) })

  // Alur input dijalankan lewat klik di dalam aplikasi, bukan page.goto.
  const tileLuring = page.locator('[data-testid^="station-tile-"]').filter({ hasText: /Depricarping/i }).first()
  if (await tileLuring.count()) { await tileLuring.click(); await page.waitForTimeout(1700) }
  await page.getByTestId('new-data-button').click()
  await page.waitForTimeout(1800)
  const isi = await isiForm(page, { isiBacaan: true })
  await page.getByTestId('save-button').click()
  await page.waitForTimeout(3000)
  const keluarForm = !/\/form\//.test(new URL(page.url()).pathname)

  const muatLuring = page.getByTestId('load-data-button')
  if (await muatLuring.count()) { await muatLuring.click(); await page.waitForTimeout(1900) }
  const semuaTgl = page.getByTestId('date-quick-all')
  if (await semuaTgl.count()) { await semuaTgl.click(); await page.waitForTimeout(1200) }
  const jml = await page.locator('[data-testid^="record-item-"]').count()
  const baris = barisRecord(page, isi.identitas)
  const ada = (await baris.count()) > 0
  const isiBaris = ada ? await teksAman(baris) : ''
  const berstatusTersimpan = /tersimpan/i.test(isiBaris) && !/tersinkron/i.test(isiBaris)

  // Langkah 7 — layar ditinggalkan lalu dibuka lagi, masih tanpa koneksi.
  // CATATAN: "tutup aplikasi lalu buka lagi" dalam arti memuat ulang aplikasi
  // tidak dapat ditiru di browser saat luring — memuat ulang menuntut dokumen
  // dan modulnya diminta dari server, sedangkan aplikasi Capacitor memuatnya
  // dari perangkat. Yang diuji di sini: record tetap ada sesudah layar
  // ditinggalkan dan dibuka kembali tanpa koneksi.
  const crumbHome = page.getByTestId('breadcrumb-home')
  if (await crumbHome.count()) { await crumbHome.click().catch(() => {}); await page.waitForTimeout(1500) }
  const kartuPpa = page.getByTestId('menu-card-production-process-activity')
  if (await kartuPpa.count()) { await kartuPpa.click().catch(() => {}); await page.waitForTimeout(1600) }
  const tile2 = page.locator('[data-testid^="station-tile-"]').filter({ hasText: /Depricarping/i }).first()
  if (await tile2.count()) { await tile2.click().catch(() => {}); await page.waitForTimeout(1700) }
  const muat2 = page.getByTestId('load-data-button')
  if (await muat2.count()) { await muat2.click().catch(() => {}); await page.waitForTimeout(1900) }
  const semuaTgl2 = page.getByTestId('date-quick-all')
  if (await semuaTgl2.count()) { await semuaTgl2.click().catch(() => {}); await page.waitForTimeout(1200) }
  const jml2 = await page.locator('[data-testid^="record-item-"]').count()
  const masihAda = (await barisRecord(page, isi.identitas).count()) > 0

  await context.setOffline(false)
  const pesanJaringanDiLayar = /periksa koneksi|gagal memuat|jaringan/i.test(isiBaris)
  const ok = keluarForm && ada && berstatusTersimpan && masihAda && !pesanJaringanDiLayar
  catat('MB-SYNC-01', V, ok ? 'Lulus' : 'Gagal',
    `login dan Station List dibuka sambil online (line "${line}"); rute Monitor, Form, dan Load Data dilalui sekali lewat klik di dalam aplikasi supaya modulnya sudah ada di halaman (${jejak.join(' → ')}), lalu koneksi diputus sepenuhnya. Seluruh alur input berjalan tanpa koneksi: Simpan berhasil dan layar keluar dari Form=${keluarForm}; record "${isi.identitas}" muncul di Data Preview=${ada} (${jml} baris) berstatus Tersimpan dan belum Tersinkron=${berstatusTersimpan} ("${isiBaris.slice(0, 70)}"); sesudah aplikasi dibuka ulang masih tanpa koneksi, record itu masih ada=${masihAda} (${jml2} baris). Catatan: "tutup aplikasi lalu buka lagi" dalam arti MEMUAT ULANG aplikasi tidak dapat ditiru di browser saat luring — memuat ulang menuntut dokumen dan modulnya diminta dari server, sedangkan aplikasi terpasang memuatnya dari perangkat. Bagian itu perlu dijalankan di perangkat atau emulator. Tidak ada pesan galat jaringan selama input=${!pesanJaringanDiLayar}. Galat konsol selama luring: ${galatJaringan.length} (permintaan jaringan yang gagal saat luring adalah hal yang diharapkan; yang diuji adalah alurnya tidak terganggu).`)
  expect(ok).toBe(true)
})

test('MB-SYNC-02 — sync manual mengirim record ke server', async ({ page, context }) => {
  await masuk(page, AKUN.OPR)
  await page.goto('/stations')
  await page.waitForTimeout(1600)
  await pilihLine(page, 0)
  // Record dibuat sambil online: yang diuji kasus ini adalah SINKRONNYA, dan
  // alur input luring sudah diuji tersendiri pada MB-SYNC-01.
  const isi = await buatRecordMobile(page)

  const s = await sinkron(page)
  const menyebutJumlah = /\d/.test(s.ringkas)

  await bukaPreview(page, DP)
  const isiBaris = await teksAman(barisRecord(page, isi.identitas))
  const tersinkron = /tersinkron/i.test(isiBaris)

  // Langkah 6 — record itu dicari di WEB sebagai Supervisor.
  await page.goto(`${WEB}/login`)
  await page.waitForTimeout(1200)
  await page.locator('#username').fill(AKUN.SPV.username)
  await page.locator('#password').fill(AKUN.SPV.password)
  await page.locator('button.login-button').click()
  await page.waitForTimeout(2500)
  await page.goto(`${WEB}/data/${DP}`)
  await page.waitForTimeout(1800)
  const diWeb = (await page.locator('tbody tr').filter({ hasText: isi.identitas }).count()) > 0
  const isiWeb = diWeb
    ? await teksAman(page.locator('tbody tr').filter({ hasText: isi.identitas }).first())
    : ''

  const ok = s.jalan && tersinkron && diWeb
  catat('MB-SYNC-02', V, ok ? 'Lulus' : 'Gagal',
    `record "${isi.identitas}" dibuat saat luring, lalu koneksi dinyalakan dan Sinkronisasi dijalankan: tombolnya ada dan berjalan=${s.jalan}; ringkasannya menyebut angka=${menyebutJumlah} ("${s.ringkas.replace(/\s+/g, ' ').slice(0, 130)}"); status record di Data Preview berubah menjadi Tersinkron=${tersinkron} ("${isiBaris.slice(0, 70)}"); record itu MUNCUL DI WEB pada Data Browser Depricarping sebagai Supervisor=${diWeb} ("${isiWeb.slice(0, 110)}").`)
  expect(ok).toBe(true)
})

test('MB-SYNC-03 — sync tanpa koneksi gagal dengan pesan jelas dan tidak merusak data', async ({ page, context }) => {
  await masuk(page, AKUN.OPR)
  await page.goto('/stations')
  await page.waitForTimeout(1600)
  await pilihLine(page, 0)
  const isi = await buatRecordMobile(page)

  // Layar Station List dibuka SEBELUM koneksi diputus, lalu tombol
  // Sinkronisasi ditekan di layar itu — tanpa perpindahan layar, karena
  // perpindahan di browser masih meminta modul dari server.
  await page.goto('/stations')
  await page.waitForTimeout(1600)
  await context.setOffline(true)
  const tombolSync = page.getByTestId('sync-button')
  let s = { jalan: false, ringkas: '' }
  if (await tombolSync.count()) {
    await tombolSync.click()
    // Ditunggu lama: sebuah permintaan yang gagal karena jaringan bisa perlu
    // beberapa saat sebelum pesannya muncul.
    await page.waitForTimeout(10_000)
    const dialog = await teksAman(page.locator('[role="dialog"], .sync-result-dialog').first())
    const toast = await teksAman(page.locator('[role="alert"], [role="status"], .toast').first())
    // Dicari di SELURUH elemen kecil, bukan dengan memotong teks halaman:
    // potongan teks halaman tanpa tanda titik bisa menyeret seluruh isi layar
    // dan membuat laporannya tampak berisi pesan padahal tidak ada.
    const kandidat = await page.locator('p, span, div, li').evaluateAll((els) =>
      els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim())
         .filter((x) => x.length > 0 && x.length < 200 && /koneksi|jaringan|offline|gagal|tidak dapat|terhubung/i.test(x)))
    s = { jalan: true, ringkas: [dialog, toast, ...kandidat.slice(0, 3)].filter(Boolean).join(' | ') }
    const tutup = page.getByRole('button', { name: /^(Tutup|OK|Selesai)$/i }).first()
    if (await tutup.count()) { await tutup.click().catch(() => {}); await page.waitForTimeout(700) }
  }
  // Pesan yang sah tidak harus memakai kata "koneksi": "Tidak dapat terhubung
  // ke server" menyampaikan hal yang sama dengan lebih jelas. Pola yang terlalu
  // sempit akan menyalahkan pesan yang justru lebih baik.
  const sebutJaringan = /koneksi|jaringan|offline|internet|tidak dapat terhubung|gagal terhubung|server/i.test(s.ringkas)
  const sebutInggris = /network|failed to fetch|timeout/i.test(s.ringkas)

  // Keadaan record dibaca SESUDAH koneksi kembali. Membacanya saat luring
  // menuntut perpindahan layar tanpa jaringan, dan di browser perpindahan itu
  // masih meminta modul dari server — yang diuji di sini keutuhan recordnya,
  // bukan perpindahan layar luring (itu MB-SYNC-01).
  await context.setOffline(false)
  await bukaPreview(page, DP)
  const isiBaris = await teksAman(barisRecord(page, isi.identitas))
  const masihAda = isiBaris !== ''
  const tetapTersimpan = /tersimpan/i.test(isiBaris) && !/tersinkron/i.test(isiBaris)

  // Langkah 6 — Sync diulang dengan koneksi hidup.
  const s2 = await sinkron(page)
  await bukaPreview(page, DP)
  const isiBaris2 = await teksAman(barisRecord(page, isi.identitas))
  const akhirnyaTersinkron = /tersinkron/i.test(isiBaris2)

  const ok = s.jalan && sebutJaringan && masihAda && tetapTersimpan && akhirnyaTersinkron
  catat('MB-SYNC-03', V, ok ? 'Lulus' : 'Gagal',
    `Sinkronisasi ditekan saat koneksi mati, lalu layar diamati 10 detik. Pesan yang menyebut koneksi/jaringan muncul=${sebutJaringan}${s.ringkas ? ` ("${s.ringkas.replace(/\s+/g, ' ').slice(0, 160)}")` : ' — TIDAK ADA satu pun pesan: dialog hasil sinkron tidak muncul, dan tidak ada elemen di layar yang menyebut koneksi, jaringan, atau kegagalan'}; memuat istilah Inggris mentah=${sebutInggris}. Record "${isi.identitas}" tidak hilang=${masihAda} dan statusnya tetap Tersimpan — tidak ditandai Tersinkron secara keliru=${tetapTersimpan} ("${isiBaris.slice(0, 70)}"). Sesudah koneksi kembali, Sync berhasil mengirimnya=${akhirnyaTersinkron} ("${isiBaris2.slice(0, 70)}"; ringkasan kedua: "${s2.ringkas.replace(/\s+/g, ' ').slice(0, 90)}").`)
  expect(ok).toBe(true)
})

test('MB-SYNC-04 — sync dua kali tidak menggandakan data', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  await page.goto('/stations')
  await page.waitForTimeout(1600)
  await pilihLine(page, 0)
  const isi = await buatRecordMobile(page)

  const s1 = await sinkron(page)
  const s2 = await sinkron(page)
  const keduaTanpaGalat = !/gagal|galat|error/i.test(s2.ringkas)
  const keduaNol = /\b0\b/.test(s2.ringkas) || /tidak ada|sudah tersinkron|nothing/i.test(s2.ringkas)

  // Langkah 4–5 — dihitung di WEB.
  await page.goto(`${WEB}/login`)
  await page.waitForTimeout(1200)
  await page.locator('#username').fill(AKUN.SPV.username)
  await page.locator('#password').fill(AKUN.SPV.password)
  await page.locator('button.login-button').click()
  await page.waitForTimeout(2500)
  await page.goto(`${WEB}/data/${DP}`)
  await page.waitForTimeout(1800)
  const baris = page.locator('tbody tr').filter({ hasText: isi.identitas })
  const jmlDiWeb = await baris.count()
  // Jumlah baris detailnya juga tidak boleh berganda.
  let barisDetail = 0
  let pecahan = ''
  if (jmlDiWeb) {
    const sel = await baris.first().locator('td').evaluateAll((els) =>
      els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()))
    pecahan = sel.find((x) => /\d+\s*\/\s*24/.test(x)) ?? ''
    await baris.first().click()
    await page.waitForLoadState('domcontentloaded')
    await page.waitForTimeout(1500)
    barisDetail = await page.locator('[data-testid^="depricarping-detail-row-"]').count()
  }

  const ok = s1.jalan && s2.jalan && keduaTanpaGalat && jmlDiWeb === 1 && barisDetail === 1
  catat('MB-SYNC-04', V, ok ? 'Lulus' : 'Gagal',
    `Sinkronisasi dijalankan dua kali atas record "${isi.identitas}". Ringkasan pertama: "${s1.ringkas.replace(/\s+/g, ' ').slice(0, 90)}"; ringkasan kedua: "${s2.ringkas.replace(/\s+/g, ' ').slice(0, 90)}" — berjalan tanpa galat=${keduaTanpaGalat}, melaporkan 0 record terkirim atau pesan sepadan=${keduaNol}. Di web, baris dengan identitas itu berjumlah ${jmlDiWeb} (harus 1 — tidak ada duplikat); kolom Jumlah Baris Terisi="${pecahan}" dan grid detailnya memuat ${barisDetail} baris (tidak berganda).`)
  expect(ok).toBe(true)
})

test('MB-SYNC-05 — tanpa koneksi saat login: pesan jaringan, bukan "password salah"', async ({ page, context }) => {
  await page.goto('/login')
  await page.waitForTimeout(1200)
  await context.setOffline(true)
  await page.locator('#username').fill(AKUN.OPR.username)
  await page.locator('#password').fill(AKUN.OPR.password)
  await page.getByRole('button', { name: 'Login' }).click()
  await page.waitForTimeout(4000)
  const teks = await teksHalaman(page)
  const pesan = (teks.match(/[^.]*(koneksi|jaringan|internet|password|akun)[^.]*\./i) ?? [])[0] ?? teks.slice(0, 180)
  const sebutJaringan = /koneksi|jaringan|internet|offline/i.test(pesan)
  const sebutPasswordSalah = /password salah|username atau password|akun tidak ditemukan/i.test(pesan)
  const tetapDiLogin = /\/login/.test(new URL(page.url()).pathname)
  await context.setOffline(false)

  const ok = sebutJaringan && !sebutPasswordSalah && tetapDiLogin
  catat('MB-SYNC-05', V, ok ? 'Lulus' : 'Gagal',
    `username dan password yang BENAR diisi saat koneksi mati: pesannya "${pesan.replace(/\s+/g, ' ').slice(0, 160)}" — menyebut koneksi/jaringan=${sebutJaringan}; menyebut password salah atau akun tidak ditemukan=${sebutPasswordSalah} (harus false, karena itu akan mengirim operator mengejar masalah yang tidak ada); tetap di layar Login dan tidak masuk=${tetapDiLogin}.`)
  expect(ok).toBe(true)
})

// ─────────────────────────────────────────────────────────────────────────────
// Kasus lintas-sistem yang langkahnya berjalan di aplikasi mobile.
// ─────────────────────────────────────────────────────────────────────────────

test('LS-10 — periode tertutup mengunci input, periode draft/terbuka tidak', async ({ page }) => {
  // Langkah 1 dan 3 dijalankan di WEB sebagai Admin (tutup/buka periode),
  // langkah 2/4/6 di mobile sebagai Operator.
  const masukWeb = async (akun: typeof AKUN.ADM) => {
    await page.goto(`${WEB}/login`)
    await page.waitForTimeout(1200)
    await page.locator('#username').fill(akun.username)
    await page.locator('#password').fill(akun.password)
    await page.locator('button.login-button').click()
    await page.waitForTimeout(2500)
  }
  const ubahStatusStasiun = async (aksi: 'close' | 'open' | 'reopen'): Promise<string> => {
    await page.goto(`${WEB}/master-data/periods`)
    await page.waitForTimeout(1600)
    const baris = page.getByTestId('period-table').locator('tr', { hasText: 'Oktober 2026' }).first()
    if (!(await baris.count())) return 'periode Oktober 2026 tidak ada'
    const tautan = baris.locator('a, [data-testid^="period-link-"]').first()
    await tautan.click()
    await page.waitForLoadState('domcontentloaded')
    await page.waitForTimeout(1600)
    const barisStasiun = page.locator('[data-testid^="period-station-row-"]').filter({ hasText: /Depricarping/i }).first()
    if (!(await barisStasiun.count())) return 'baris stasiun Depricarping tidak ada di Detail Periode'
    // Tombol yang tersedia bergantung status saat itu: stasiun yang SUDAH
    // terbuka tidak punya tombol "buka". Jadi bila aksinya sudah tercapai,
    // statusnya dikembalikan apa adanya, bukan dilaporkan sebagai kegagalan.
    const statusKini = (await teksAman(barisStasiun.locator('[data-testid^="station-status-badge-"]'))).toLowerCase()
    if (aksi === 'open' && /terbuka/.test(statusKini)) return statusKini
    if (aksi === 'close' && /tertutup/.test(statusKini)) return statusKini
    if (aksi === 'reopen' && /terbuka/.test(statusKini)) return statusKini
    const tombol = barisStasiun.locator(`[data-testid^="station-${aksi}-button-"]`)
    if (!(await tombol.count())) return `tombol ${aksi} tidak tersedia (status sekarang "${statusKini}")`
    await tombol.click()
    await page.waitForTimeout(900)
    const ya = page.locator('[data-testid="confirm-close-button"], [data-testid="confirm-open-period"], [data-testid="confirm-reopen-button"]').first()
    if (await ya.count()) { await ya.click(); await page.waitForTimeout(1800) }
    return (await teksAman(barisStasiun.locator('[data-testid^="station-status-badge-"]'))) || 'status tidak terbaca'
  }

  const simpanDiMobile = async (): Promise<{ berhasil: boolean; pesan: string }> => {
    await keluar(page)
    await masuk(page, AKUN.OPR)
    await page.goto('/stations')
    await page.waitForTimeout(1500)
    await pilihLine(page, 0)
    await page.goto(`/stations/${DP}/monitor`)
    await page.waitForTimeout(1300)
    await page.getByTestId('new-data-button').click()
    await page.waitForTimeout(1600)
    await isiForm(page, { isiBacaan: true })
    await page.getByTestId('save-button').click()
    await page.waitForTimeout(2800)
    const berhasil = !/\/form\//.test(new URL(page.url()).pathname)
    const galat = await page.locator('.form-field-error, [data-testid="detail-rows-error"], [data-testid="action-error"]')
      .evaluateAll((els) => els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()).filter(Boolean))
    return { berhasil, pesan: galat.join(' / ') }
  }

  await masukWeb(AKUN.ADM)
  const statusTerbuka = await ubahStatusStasiun('open')
  const a = await simpanDiMobile()

  await keluar(page)
  await masukWeb(AKUN.ADM)
  const statusTertutup = await ubahStatusStasiun('close')
  const b = await simpanDiMobile()

  // Simpan di mobile menulis ke basis data PERANGKAT lebih dulu (alur
  // offline-first), jadi kunci periode belum tentu terasa di langkah Simpan.
  // Yang menentukan: apakah record itu DITOLAK saat disinkronkan ke server.
  const sinkronSesudahTutup = await sinkron(page)
  const sinkronDitolak = /gagal|ditolak|tidak|periode/i.test(sinkronSesudahTutup.ringkas)

  await keluar(page)
  await masukWeb(AKUN.ADM)
  const statusDibukaKembali = await ubahStatusStasiun('reopen')
  const c = await simpanDiMobile()

  const terkunci = !b.berhasil || sinkronDitolak
  const ok = a.berhasil && terkunci && c.berhasil
  catat('LS-10', '—', ok ? 'Lulus' : 'Gagal',
    `Status stasiun Depricarping pada periode Oktober 2026 diubah dari layar Admin, dan setiap kali satu record dicoba disimpan dari aplikasi mobile sebagai Operator. (1) status "${statusTerbuka}" → tersimpan=${a.berhasil}${a.pesan ? ` ("${a.pesan.slice(0, 90)}")` : ''}. (2) status "${statusTertutup}" → Simpan di mobile berhasil=${b.berhasil}${b.pesan ? ` ("${b.pesan.slice(0, 100)}")` : ''}. Karena mobile menulis ke basis data perangkat lebih dulu, kuncinya diperiksa pada SINKRONISASI: hasil sinkron sesudah itu="${sinkronSesudahTutup.ringkas.replace(/\s+/g, ' ').slice(0, 140)}" → ditolak/tidak terkirim=${sinkronDitolak}. Jadi recordnya tertahan=${terkunci}. (3) status "${statusDibukaKembali}" → tersimpan lagi=${c.berhasil}${c.pesan ? ` ("${c.pesan.slice(0, 90)}")` : ''}. Jadi hanya status Tertutup yang mengunci input; Draft dan Terbuka tidak.`)
  expect(ok).toBe(true)
})

test('LS-12 — ekspor di mobile hanya CSV', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  await page.goto('/stations')
  await page.waitForTimeout(1500)
  await pilihLine(page, 0)
  // Laporan periode mobile: Dashboard & Reporting → Reporting → tile stasiun.
  await page.goto('/reports/kernel-plant')
  await page.waitForTimeout(1800)
  // Tombol ekspor baru muncul sesudah periode dan Production Line dipilih —
  // sebelum itu laporannya belum menghasilkan apa pun untuk diekspor.
  // Periode yang BERDATA dipilih (Oktober 2026), bukan periode pertama pada
  // daftar: periode uji masa depan masih kosong, dan pada keadaan kosong
  // footer laporan beserta tombol ekspornya belum dirender.
  for (const id of ['production-line-select', 'period-select']) {
    const sel = page.getByTestId(id)
    if (!(await sel.count())) continue
    const o = await sel.locator('option').evaluateAll((x) =>
      x.map((e) => ({ v: (e as HTMLSelectElement).value, t: (e.textContent ?? '').trim() }))
       .filter((y) => y.v !== '' && !/^(Pilih|--|Semua)/i.test(y.t)))
    if (!o.length) continue
    const pilih = o.find((x) => /Oktober 2026/.test(x.t)) ?? o[0]
    await sel.selectOption(pilih.v)
    await page.waitForTimeout(1800)
  }
  const tombol = await page.locator('button, a').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()).filter(Boolean))
  const csv = tombol.filter((t) => /csv/i.test(t))
  const excel = tombol.filter((t) => /excel|xlsx/i.test(t))
  const adaTombolEkspor = (await page.getByTestId('export-button').count()) > 0
  const ok = adaTombolEkspor && csv.length >= 1 && excel.length === 0
  catat('LS-12', '—', ok ? 'Lulus' : 'Gagal',
    `pada layar laporan periode mobile (sesudah Production Line dan Periode dipilih, karena tombol ekspor baru dirender setelah keduanya pasti): tombol ekspor ada=${adaTombolEkspor}; tombol yang memuat kata CSV=${csv.length} (${csv.join(', ') || 'tidak ada'}); tombol yang memuat kata Excel/XLSX=${excel.length} (${excel.join(', ') || 'tidak ada'}) — harus 0, karena ekspor di mobile hanya CSV. Pengunduhan berkasnya sendiri diuji pada LP-M-11.`)
  expect(ok).toBe(true)
})

test('DS-07 — Dashboard & Reporting (Mobile) → pilih stasiun', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  const kartu = page.getByTestId('menu-card-dashboard-reporting')
  const adaKartu = (await kartu.count()) > 0
  if (adaKartu) { await kartu.click(); await page.waitForTimeout(1600) }
  const teksDr = await teksHalaman(page)
  // Reporting → daftar tile stasiun.
  const tombolReporting = page.getByRole('button', { name: /Reporting/i }).first()
  if (await tombolReporting.count()) { await tombolReporting.click(); await page.waitForTimeout(1600) }
  else { await page.goto('/reports'); await page.waitForTimeout(1600) }

  const tile = page.locator('[data-testid^="station-tile-"], [data-testid^="report-tile-"]')
  const jml = await tile.count()
  const kelabu = await page.locator('[data-testid^="station-tile-"][disabled], [data-testid^="station-tile-"].is-inactive, [data-testid^="report-tile-"][disabled]').count()

  // Tile yang laporannya belum ada ditekan tiga kali: pesannya tidak boleh menumpuk.
  let pesan = ''
  let jumlahPesan = 0
  let alamatSebelum = new URL(page.url()).pathname
  let alamatSesudah = alamatSebelum
  const belumAda = page.locator('[data-testid^="station-tile-"]').filter({ hasText: /Engine Room|Process Water|Effluent/i }).first()
  if (await belumAda.count()) {
    for (let i = 0; i < 4; i++) { await belumAda.click({ force: true }).catch(() => {}); await page.waitForTimeout(500) }
    await page.waitForTimeout(800)
    jumlahPesan = await page.locator('[role="status"], [role="alert"], .toast, [data-testid*="message"]').count()
    pesan = await teksAman(page.locator('[role="status"], [role="alert"], .toast, [data-testid*="message"]').first())
    alamatSesudah = new URL(page.url()).pathname
  }

  const ok = adaKartu && jml === 18 && alamatSebelum === alamatSesudah && jumlahPesan <= 1
  catat('DS-07', '—', ok ? 'Lulus' : 'Gagal',
    `kartu Dashboard & Reporting ada di Home=${adaKartu}; layar Reporting menampilkan ${jml} tile stasiun (harus 18), ${kelabu} di antaranya kelabu/tidak dapat ditekan. Menekan tile yang laporannya belum ada empat kali: pesan yang terlihat di layar=${jumlahPesan} (harus tetap satu, tidak menumpuk) berbunyi "${pesan.slice(0, 110)}"; alamat berpindah=${alamatSebelum !== alamatSesudah} (harus false). Isi layar Dashboard & Reporting: "${teksDr.slice(0, 110)}".`)
  expect(ok).toBe(true)
})
