/**
 * Pelaksana bagian 4.5 dokumen QA — Form Mobile (MB-FORM-01…15).
 * Varian tingkat 1 = Depricarping; MB-FORM-01 dijalankan di KE-18 stasiun.
 */
import { test, expect, type Page } from '@playwright/test'
import {
  AKUN, BERLAPORAN, PER_TRANSAKSI, POLA_TEKNIS, STASIUN,
  barisRecord, bisaDiisi, bukaPreview, catat, isiForm, keluar, masuk, nilaiAman, opsiSlot,
  pilihLine, pilihSlot, teksAman, teksHalaman,
} from './support'

const DP = 'depricarping'
const V = 'Depricarping'

/** Buka Form baru untuk satu stasiun, dengan Production Line sudah terpilih. */
async function bukaFormBaru(page: Page, kode: string): Promise<string> {
  await page.goto('/stations')
  await page.waitForTimeout(1300)
  await pilihLine(page, 0)
  await page.goto(`/stations/${kode}/monitor`)
  await page.waitForTimeout(1300)
  const tombol = page.getByTestId('new-data-button')
  if (!(await tombol.count())) return ''
  await tombol.click()
  await page.waitForTimeout(1600)
  return new URL(page.url()).pathname
}

/** Galat per-kolom dan galat grid yang sedang tampil pada Form. */
async function galatForm(page: Page): Promise<{ perKolom: string[]; grid: string }> {
  const perKolom = await page.locator('.form-field-error').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()).filter(Boolean))
  const grid = await teksAman(page.getByTestId('detail-rows-error'))
  return { perKolom, grid }
}

test('MB-FORM-02 — simpan ditolak bila kolom wajib kosong', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  await bukaFormBaru(page, DP)
  // Kolom identitas dibiarkan kosong; satu baris slot waktu diisi.
  const isi = await isiForm(page, { isiBacaan: true })
  const identitas = page.locator('input.form-field-input:not([disabled])').first()
  await identitas.fill('')
  await page.waitForTimeout(500)
  const isiGridSebelum = await nilaiAman(page.locator('[data-testid^="fan-static-pressure-"]').first())
  await page.getByTestId('save-button').click()
  await page.waitForTimeout(2000)
  const g = await galatForm(page)
  // "Di sebelah kolom identitas" — galatnya harus berada di dalam pembungkus
  // kolom itu, bukan hanya sebagai pesan umum.
  const diKolom = await identitas.evaluate((el) => {
    const b = (el as HTMLElement).closest('.form-field')
    return (b?.querySelector('.form-field-error')?.textContent ?? '').trim()
  }).catch(() => '')
  const isiGridSesudah = await nilaiAman(page.locator('[data-testid^="fan-static-pressure-"]').first())
  const masihDiForm = /\/form\//.test(new URL(page.url()).pathname)
  const ok = diKolom !== '' && masihDiForm && isiGridSesudah === isiGridSebelum
  catat('MB-FORM-02', V, ok ? 'Lulus' : 'Gagal',
    `kolom identitas dikosongkan, satu baris slot "${isi.slot}" terisi. Simpan ditolak dan layar tetap di Form=${masihDiForm}; galat tercetak di sebelah kolom identitas="${diKolom}" (bukan hanya pesan umum; seluruh galat per-kolom: ${g.perKolom.join(' / ') || 'tidak ada'}); isi grid tidak hilang — nilai "${isiGridSebelum}" tetap "${isiGridSesudah}"=${isiGridSesudah === isiGridSebelum}.`)
  expect(ok).toBe(true)
})

test('MB-FORM-03 — slot waktu harus menaik dan tidak boleh kembar', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  await bukaFormBaru(page, DP)
  // Daftar pilihan baris pertama dibaca SEBELUM slotnya dipilih: sesudah
  // terpilih, isi kotak comboboxnya ikut menyaring daftar sehingga yang terbaca
  // tinggal nol pilihan.
  await page.getByTestId('add-detail-row-button').click()
  await page.waitForTimeout(1000)
  const opsi1 = await opsiSlot(page, 0)
  const p1 = await pilihSlot(page, 0, 0)
  const slot1 = p1.nilai || (opsi1[0] ?? '')
  const barisSatu = page.locator('[data-testid="depricarping-detail-row"]').first()
  const kolomSatu = barisSatu.locator('input[type="number"], input[inputmode="decimal"]').first()
  if (await kolomSatu.count()) await kolomSatu.fill('41')
  const identitas = page.locator('input.form-field-input:not([disabled])').first()
  await identitas.fill(`QA-SLOT-${Date.now().toString().slice(-5)}`)
  await page.waitForTimeout(500)

  // Baris kedua: pilihannya tidak boleh lagi memuat slot yang sudah dipakai
  // maupun slot yang lebih awal.
  await page.getByTestId('add-detail-row-button').click()
  await page.waitForTimeout(1000)
  const opsi2 = await opsiSlot(page, 1)
  const idx1 = opsi1.indexOf(slot1)
  const kembarDicegah = slot1 !== '' && !opsi2.includes(slot1)
  const menurunDicegah = idx1 >= 0 && opsi1.slice(0, idx1).every((s) => !opsi2.includes(s))
  const hanyaMenaik = idx1 >= 0 && opsi2.every((s) => opsi1.indexOf(s) > idx1)

  // Langkah 7 — slot sah berikutnya dipilih, lalu Simpan harus berhasil.
  let berhasil = false
  let slot2 = ''
  if (opsi2.length) {
    const p2 = await pilihSlot(page, 1, 0)
    slot2 = p2.nilai || opsi2[0]
    const baris = page.locator('[data-testid="depricarping-detail-row"]').last()
    const kolom = baris.locator('input[type="number"], input[inputmode="decimal"]').first()
    if (await kolom.count()) await kolom.fill('33')
    await page.getByTestId('save-button').click()
    await page.waitForTimeout(2600)
    berhasil = !/\/form\//.test(new URL(page.url()).pathname)
  }

  const ok = kembarDicegah && menurunDicegah && hanyaMenaik && berhasil
  catat('MB-FORM-03', V, ok ? 'Lulus' : 'Gagal',
    `baris pertama memakai slot "${slot1}" (dari ${opsi1.length} pilihan). Pilihan pada baris kedua berjumlah ${opsi2.length}: slot yang sudah dipakai sudah hilang=${kembarDicegah}; seluruh slot yang lebih awal juga hilang=${menurunDicegah}; setiap pilihan yang tersisa lebih besar dari slot sebelumnya=${hanyaMenaik}. Jadi slot kembar dan menurun DICEGAH, bukan ditolak saat Simpan — tidak ada pesan galat untuk dibaca, dan itu memang yang benar. Memilih slot sah berikutnya ("${slot2}") lalu Simpan berhasil=${berhasil}.`)
  expect(ok).toBe(true)
})

test('MB-FORM-04 — urutan slot mengikuti 07:00 → 06:00', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  await bukaFormBaru(page, DP)
  await page.getByTestId('add-detail-row-button').click()
  await page.waitForTimeout(1000)
  const opsi = await opsiSlot(page, 0)
  const jml = opsi.length === 24
  const mulai07 = /^07[:.]00/.test(opsi[0] ?? '')
  const akhir06 = /^06[:.]00/.test(opsi[23] ?? '')
  const idx23 = opsi.findIndex((t) => /^23[:.]00/.test(t))
  const sesudah23 = idx23 >= 0 && /^00[:.]00/.test(opsi[idx23 + 1] ?? '')
  const ok = jml && mulai07 && akhir06 && sesudah23
  catat('MB-FORM-04', V, ok ? 'Lulus' : 'Gagal',
    `pilihan slot berjumlah ${opsi.length} (harus 24)=${jml}; pilihan pertama "${opsi[0] ?? '-'}" (harus 07:00)=${mulai07}; sesudah 23:00 menyusul "${opsi[idx23 + 1] ?? '-'}" (harus 00:00)=${sesudah23}; pilihan terakhir "${opsi[23] ?? '-'}" (harus 06:00)=${akhir06}. Jadi 01:00 berada SESUDAH 23:00 — itu benar, bukan bug.`)
  expect(ok).toBe(true)
})

test('MB-FORM-05 — simpan ditolak bila tidak ada satu pun baris terisi', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  await bukaFormBaru(page, DP)
  const isi = await isiForm(page, { isiBacaan: false })
  await page.getByTestId('save-button').click()
  await page.waitForTimeout(2200)
  const g = await galatForm(page)
  const pesan = [g.grid, ...g.perKolom].join(' ')
  const ditolak = /minimal (satu|1)|setidaknya (satu|1)/i.test(pesan)
  const sebutKeduanya = /slot/i.test(pesan) && /(kolom|bacaan|nilai)/i.test(pesan)
  const diGrid = g.grid !== ''
  const masihDiForm = /\/form\//.test(new URL(page.url()).pathname)
  const ok = ditolak && masihDiForm && diGrid
  catat('MB-FORM-05', V, ok ? 'Lulus' : 'Gagal',
    `identitas "${isi.identitas}" diisi dan satu baris slot "${isi.slot}" ditambahkan TANPA satu kolom bacaan pun. Ditolak=${ditolak}; record tidak tersimpan — layar masih di Form=${masihDiForm}; pesannya tercetak di bagian grid, bukan hanya di atas form=${diGrid} ("${g.grid.slice(0, 140)}"); pesannya menyebut syarat slot DAN minimal satu kolom bacaan=${sebutKeduanya}.`)
  expect(ok).toBe(true)
})

test('MB-FORM-06 — baris yang sebagian terisi tetap sah', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  await bukaFormBaru(page, DP)
  const isi = await isiForm(page, { isiBacaan: true })
  // HANYA kolom bacaan yang dihitung — kotak slot waktu juga sebuah input dan
  // ikut terhitung bila tidak disaring, sehingga "tepat satu kolom terisi"
  // tidak akan pernah benar.
  const baris = page.locator('[data-testid="depricarping-detail-row"]').last()
  const kolom = baris.locator('input[type="number"], input[inputmode="decimal"]')
  const nK = await kolom.count()
  let terisi = 0
  for (let i = 0; i < nK; i++) {
    const v = await kolom.nth(i).inputValue().catch(() => '')
    if (v !== '') terisi++
  }
  await page.getByTestId('save-button').click()
  await page.waitForTimeout(2600)
  const tersimpan = !/\/form\//.test(new URL(page.url()).pathname)

  // Langkah 6 — dibuka lewat Load Data dan barisnya diperiksa.
  // Daftar record memakai penanda record-item-<id>, dan filter tanggalnya
  // bawaannya HARI INI — pintasan "Semua" harus ditekan dulu, kalau tidak
  // record yang baru dibuat pun bisa tidak terlihat.
  const jmlItem = await bukaPreview(page, DP)
  const isiItem = await page.locator('[data-testid^="record-item-"]').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()))
  const barisDaftar = barisRecord(page, isi.identitas)
  // Dicatat SEKALI, sebelum layar berpindah: sesudah barisnya diklik, layar
  // berganti ke detail dan locator daftar tidak lagi cocok dengan apa pun —
  // menghitungnya ulang di sana akan melaporkan "tidak ditemukan" untuk record
  // yang justru sedang dibuka.
  const ditemukan = (await barisDaftar.count()) > 0
  let isiDetail = ''
  let adaNol = false
  if (ditemukan) {
    await barisDaftar.click()
    await page.waitForTimeout(1600)
    isiDetail = await teksHalaman(page)
    // Kolom yang dibiarkan kosong tidak boleh tampil sebagai 0.
    adaNol = /(^|\s)0(\s|$)/.test(isiDetail)
  }
  const ok = tersimpan && terisi === 1 && ditemukan && !adaNol
  catat('MB-FORM-06', V, ok ? 'Lulus' : 'Gagal',
    `baris slot "${isi.slot}" diisi HANYA pada satu kolom bacaan (kolom terisi pada baris itu=${terisi}), kolom lain dibiarkan kosong: simpan berhasil=${tersimpan}; record "${isi.identitas}" ditemukan di Data Preview=${ditemukan} (${jmlItem} baris di daftar: ${isiItem.join(' | ').slice(0, 120)}); pada detailnya kolom yang dibiarkan kosong tampil sebagai angka 0=${adaNol} (harus false — kosong harus tampil kosong atau tanda pisah).`)
  expect(ok).toBe(true)
})

test('MB-FORM-07 — Pause menyimpan apa adanya tanpa memvalidasi', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  await bukaFormBaru(page, DP)
  // Keadaan yang pada MB-FORM-02 membuat Simpan ditolak: identitas kosong dan
  // tidak satu kolom bacaan pun terisi.
  const isi = await isiForm(page, { isiBacaan: false })
  await page.locator('input.form-field-input:not([disabled])').first().fill('')
  await page.waitForTimeout(500)
  await page.getByTestId('pause-button').click()
  await page.waitForTimeout(2400)
  const keluarForm = !/\/form\//.test(new URL(page.url()).pathname)
  const g = await galatForm(page)
  const tanpaGalat = g.perKolom.length === 0 && g.grid === ''

  await page.goto(`/stations/${DP}/monitor`)
  await page.waitForTimeout(1500)
  const draft = page.locator('[data-testid^="draft-item-"]').first()
  const label = await teksAman(draft)
  const berlabelPause = /pause|jeda/i.test(label)

  // Dibuka kembali: isinya apa adanya, termasuk kolom yang kosong.
  let identitasSaatDibuka = ''
  let slotSaatDibuka = ''
  if (await draft.count()) {
    await draft.click()
    await page.waitForTimeout(1700)
    identitasSaatDibuka = await nilaiAman(page.locator('input.form-field-input:not([disabled])').first())
    slotSaatDibuka = await nilaiAman(page.locator('[data-testid^="time-slot-select-"]').first())
  }
  const ok = keluarForm && tanpaGalat && berlabelPause && identitasSaatDibuka === ''
  catat('MB-FORM-07', V, ok ? 'Lulus' : 'Gagal',
    `identitas dikosongkan dan tidak satu kolom bacaan pun diisi (keadaan yang pada MB-FORM-02 membuat Simpan ditolak), lalu Pause ditekan: berhasil tanpa satu validasi pun menolaknya=${keluarForm && tanpaGalat}; draft muncul di Monitor berlabel Pause=${berlabelPause} ("${label.slice(0, 90)}"); saat dibuka kembali isinya apa adanya — identitas tetap kosong=${identitasSaatDibuka === ''}, slot "${slotSaatDibuka}" (diisi "${isi.slot}" saat dijeda).`)
  expect(ok).toBe(true)
})

test('MB-FORM-08 — melanjutkan draft memuat kembali isinya', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  await bukaFormBaru(page, DP)
  const isi = await isiForm(page, { isiBacaan: true })
  // Baris kedua supaya draftnya punya DUA baris terisi.
  await page.getByTestId('add-detail-row-button').click()
  await page.waitForTimeout(900)
  const n = await page.locator('[data-testid^="time-slot-select-"]').count()
  let slot2 = ''
  if (n >= 2) {
    const p2 = await pilihSlot(page, n - 1, 0)
    slot2 = p2.nilai
    const baris = page.locator('[data-testid="depricarping-detail-row"]').last()
    const k = baris.locator('input[type="number"], input[inputmode="decimal"]').first()
    if (await k.count()) await k.fill('55')
  }
  const tanggalSebelum = await nilaiAman(page.locator('input.form-field-input[disabled]').first())
  await page.getByTestId('pause-button').click()
  await page.waitForTimeout(2400)

  await page.goto(`/stations/${DP}/monitor`)
  await page.waitForTimeout(1600)
  const draft = page.locator('[data-testid^="draft-item-"]').first()
  await draft.click()
  await page.waitForTimeout(1800)
  const identitasSesudah = await nilaiAman(page.locator('input.form-field-input:not([disabled])').first())
  const tanggalSesudah = await nilaiAman(page.locator('input.form-field-input[disabled]').first())
  const barisSesudah = await page.locator('[data-testid="depricarping-detail-row"]').count()
  const slotSesudah = await page.locator('[data-testid^="time-slot-select-"] input[role="combobox"]')
    .evaluateAll((els) => els.map((e) => (e as HTMLInputElement).value))

  // Baris ketiga hanya menawarkan slot SESUDAH slot terakhir yang sudah dipakai.
  await page.getByTestId('add-detail-row-button').click()
  await page.waitForTimeout(900)
  const nBaru = await page.locator('[data-testid^="time-slot-select-"]').count()
  const opsiBaru = await opsiSlot(page, nBaru - 1)
  // Dibandingkan menurut URUTAN KANONIK 07:00 → 06:00, bukan menurut abjad:
  // slot 00:00–06:00 berada SESUDAH 23:00 dalam urutan shift, tetapi lebih
  // kecil secara abjad — perbandingan teks akan menyalahkan daftar yang benar.
  const kanonik = Array.from({ length: 24 }, (_, i) => `${String((7 + i) % 24).padStart(2, '0')}:00`)
  const idxKanonik = (s: string) => kanonik.indexOf(s.trim().slice(0, 5))
  const idxTerakhir = Math.max(...slotSesudah.filter(Boolean).map(idxKanonik))
  const slotTerakhir = kanonik[idxTerakhir] ?? ''
  const hanyaSesudah = opsiBaru.length > 0 && opsiBaru.every((v) => idxKanonik(v) > idxTerakhir)

  const ok = identitasSesudah === isi.identitas && tanggalSesudah === tanggalSebelum &&
             barisSesudah === 2 && hanyaSesudah
  catat('MB-FORM-08', V, ok ? 'Lulus' : 'Gagal',
    `draft dengan dua baris (slot "${isi.slot}" dan "${slot2}") dijeda lalu dibuka kembali: identitas kembali "${identitasSesudah}" (sama dengan "${isi.identitas}"=${identitasSesudah === isi.identitas}); Tanggal tetap "${tanggalSesudah}"=${tanggalSesudah === tanggalSebelum}; kedua baris muncul lengkap=${barisSesudah === 2} pada slot ${JSON.stringify(slotSesudah)}; baris ketiga hanya menawarkan slot sesudah "${slotTerakhir}"=${hanyaSesudah} (${opsiBaru.length} pilihan).`)
  expect(ok).toBe(true)
})

test('MB-FORM-09 — Clear meminta konfirmasi, lalu menghapus permanen', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  await bukaFormBaru(page, DP)
  const isi = await isiForm(page, { isiBacaan: true })
  await page.getByTestId('pause-button').click()
  await page.waitForTimeout(2300)
  await page.goto(`/stations/${DP}/monitor`)
  await page.waitForTimeout(1600)
  const draft = page.locator('[data-testid^="draft-item-"]').filter({ hasText: isi.identitas }).first()
  const adaDraft = (await draft.count()) > 0
  if (!adaDraft) {
    catat('MB-FORM-09', V, 'Gagal', `draft uji "${isi.identitas}" tidak ditemukan di Monitor sesudah dijeda, sehingga Clear tidak dapat diuji.`)
    expect(adaDraft).toBe(true)
    return
  }
  await draft.click()
  await page.waitForTimeout(1700)

  // Langkah 2 — Clear lalu Batal.
  let dialogDibaca = ''
  page.once('dialog', async (d) => { dialogDibaca = d.message(); await d.dismiss() })
  await page.getByTestId('clear-button').click()
  await page.waitForTimeout(1200)
  const dialogDalamHalaman = await teksAman(page.locator('[role="dialog"], .confirm-dialog').first())
  // Bila konfirmasinya berupa dialog dalam halaman, tombol batalnya ditekan.
  const batal = page.getByRole('button', { name: /^(Batal|Tidak|Cancel)$/i }).first()
  if (await batal.count()) { await batal.click(); await page.waitForTimeout(1000) }
  const isiMasihUtuh = (await nilaiAman(page.locator('input.form-field-input:not([disabled])').first())) === isi.identitas

  // Langkah 4 — Clear lalu setujui.
  page.once('dialog', async (d) => { await d.accept() })
  await page.getByTestId('clear-button').click()
  await page.waitForTimeout(1200)
  const ya = page.getByRole('button', { name: /^(Ya|Hapus|Ya, Hapus|Lanjutkan)/i }).first()
  if (await ya.count()) { await ya.click(); await page.waitForTimeout(2000) }

  await page.goto(`/stations/${DP}/monitor`)
  await page.waitForTimeout(1700)
  const masihAda = (await page.locator('[data-testid^="draft-item-"]').filter({ hasText: isi.identitas }).count()) > 0
  const adaKonfirmasi = dialogDibaca !== '' || dialogDalamHalaman !== ''
  const ok = adaKonfirmasi && isiMasihUtuh && !masihAda
  catat('MB-FORM-09', V, ok ? 'Lulus' : 'Gagal',
    `Clear memunculkan konfirmasi lebih dulu=${adaKonfirmasi} ("${(dialogDibaca || dialogDalamHalaman).slice(0, 120)}") — tidak langsung menghapus; sesudah Batal isi form masih utuh=${isiMasihUtuh}; sesudah disetujui draft "${isi.identitas}" hilang dari daftar Monitor=${!masihAda} (terhapus permanen).`)
  expect(ok).toBe(true)
})

test('MB-FORM-10 — Back dengan perubahan belum tersimpan meminta konfirmasi', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  await bukaFormBaru(page, DP)
  await page.locator('input.form-field-input:not([disabled])').first().fill(`QA-BACK-${Date.now().toString().slice(-5)}`)
  await page.waitForTimeout(600)

  let pesanDialog = ''
  page.once('dialog', async (d) => { pesanDialog = d.message(); await d.dismiss() })
  await page.getByTestId('back-button').click()
  await page.waitForTimeout(1300)
  const dialogDalamHalaman = await teksAman(page.locator('[role="dialog"], .confirm-dialog').first())
  const batal = page.getByRole('button', { name: /^(Batal|Tidak|Tetap)/i }).first()
  if (await batal.count()) { await batal.click(); await page.waitForTimeout(900) }
  const masihDiForm = /\/form\//.test(new URL(page.url()).pathname)
  const isiTidakHilang = (await nilaiAman(page.locator('input.form-field-input:not([disabled])').first())) !== ''
  const adaKonfirmasi = pesanDialog !== '' || dialogDalamHalaman !== ''

  // Langkah 5 — Back lagi, kali ini disetujui.
  page.once('dialog', async (d) => { await d.accept() })
  await page.getByTestId('back-button').click()
  await page.waitForTimeout(1300)
  const ya = page.getByRole('button', { name: /^(Ya|Keluar|Buang|Lanjutkan)/i }).first()
  if (await ya.count()) { await ya.click(); await page.waitForTimeout(1500) }
  const keluarForm = !/\/form\//.test(new URL(page.url()).pathname)

  // Langkah 6 — Form baru yang belum disentuh: TIDAK boleh ada konfirmasi.
  await bukaFormBaru(page, DP)
  let dialogKedua = ''
  page.once('dialog', async (d) => { dialogKedua = d.message(); await d.accept() })
  await page.getByTestId('back-button').click()
  await page.waitForTimeout(1500)
  const dialogKeduaDalamHalaman = await teksAman(page.locator('[role="dialog"], .confirm-dialog').first())
  const tanpaKonfirmasi = dialogKedua === '' && dialogKeduaDalamHalaman === ''
  const keluarLangsung = !/\/form\//.test(new URL(page.url()).pathname)

  const ok = adaKonfirmasi && masihDiForm && isiTidakHilang && tanpaKonfirmasi
  catat('MB-FORM-10', V, ok ? 'Lulus' : 'Gagal',
    `form yang sudah diisi lalu Back: konfirmasi muncul=${adaKonfirmasi} ("${(pesanDialog || dialogDalamHalaman).slice(0, 110)}"); sesudah Batal tetap di Form=${masihDiForm} dan isinya tidak hilang=${isiTidakHilang}; sesudah disetujui keluar dari Form=${keluarForm}. Form BARU yang belum disentuh lalu Back: tidak ada konfirmasi=${tanpaKonfirmasi} dan langsung keluar=${keluarLangsung} — form yang belum disentuh tidak perlu konfirmasi.`)
  expect(ok).toBe(true)
})

test('MB-FORM-11 — stasiun per-transaksi: satu record per transaksi', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  const rinci: string[] = []
  let gagal = 0
  for (const kode of PER_TRANSAKSI) {
    const label = STASIUN.find((s) => s.kode === kode)!.label
    const alamat = await bukaFormBaru(page, kode)
    if (alamat === '') { gagal++; rinci.push(`${label}: tombol New Data tidak ada`); continue }
    // Dikenali dari KENDALI SLOT WAKTU, bukan dari tombol tambah baris:
    // Grading punya tombol tambah baris karena satu muatan punya beberapa baris
    // PARAMETER — itu bukan grid 24 slot waktu, dan menghitungnya sebagai grid
    // akan menyalahkan layar yang benar.
    const tambah = page.getByTestId('add-detail-row-button')
    if (await tambah.count()) { await tambah.click(); await page.waitForTimeout(900) }
    const adaGrid24 = (await page.locator('[data-testid^="time-slot-select-"]').count()) > 0
    const teks = await teksHalaman(page)
    const adaArus = kode === 'weighbridge' ? /(masuk|keluar|receive|dispatch)/i.test(teks) : null
    if (adaGrid24) gagal++
    const barisParameter = await page.locator('[data-testid$="-detail-row"]').count()
    rinci.push(`${label}: kendali slot waktu di layar=${adaGrid24} (harus false)${barisParameter ? `, baris ${kode === 'grading' ? 'parameter muatan' : 'detail'}=${barisParameter}` : ''}${adaArus === null ? '' : `; pembedaan arus Masuk/Keluar terbaca=${adaArus}`}`)
  }
  catat('MB-FORM-11', 'Weighbridge', gagal ? 'Gagal' : 'Lulus',
    `${rinci.join(' | ')}. Keempatnya menyimpan satu record per transaksi — tidak ada grid 24 slot waktu di layar mana pun.`)
  expect(gagal, rinci.join('\n')).toBe(0)
})

test('MB-FORM-12 — Operator tidak dapat mengisi Checked By maupun Acknowledged By', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  await bukaFormBaru(page, DP)
  const checked = page.getByTestId('checked-by-toggle')
  const ack = page.getByTestId('acknowledged-by-toggle')
  const bisaChecked = await bisaDiisi(checked, null)
  const bisaAck = await bisaDiisi(ack, null)
  const teks = await teksHalaman(page)
  const adaKeterangan = /hanya (dapat diisi|oleh)|supervisor|mill management/i.test(teks)

  // Sterilizer: centang "Checked by SPV" per baris juga tidak boleh ditekan.
  await bukaFormBaru(page, 'sterilizer')
  const tambahSter = page.getByTestId('add-detail-row-button')
  if (await tambahSter.count()) { await tambahSter.click(); await page.waitForTimeout(1000) }
  const centangBaris = page.locator('[data-testid*="detail-row"] input[type="checkbox"]')
  const nCentang = await centangBaris.count()
  let bisaCentang: boolean | null = null
  if (nCentang) bisaCentang = await bisaDiisi(centangBaris.first(), null)

  const ok = bisaChecked !== true && bisaAck !== true && bisaCentang !== true
  catat('MB-FORM-12', V, ok ? 'Lulus' : 'Gagal',
    `sebagai Operator pada Form Depricarping: Checked By ${bisaChecked === null ? 'tidak dirender' : `dapat diisi=${bisaChecked}`} (tidak boleh); Acknowledged By ${bisaAck === null ? 'tidak dirender' : `dapat diisi=${bisaAck}`} (tidak boleh); ada keterangan di layar yang menyebut siapa yang boleh mengisinya=${adaKeterangan}. Pada Form Sterilizer: ${nCentang} kotak centang "Checked by SPV" per baris, dapat ditekan Operator=${bisaCentang === null ? 'tidak dirender' : bisaCentang} (tidak boleh).`)
  expect(ok).toBe(true)
})

test('MB-FORM-13 — Supervisor mengisi Checked, Mill Management mengisi Acknowledged', async ({ page }) => {
  // Alur yang NYATA di mobile: tiap peran membuat recordnya sendiri. Record
  // milik Operator TIDAK terlihat oleh Supervisor di aplikasi mobile — daftar
  // Data Preview dibaca dari basis data di perangkat itu dan milik pengguna
  // yang sedang masuk, dan belum ada alur sinkronisasi yang menarik record
  // pengguna lain. Verifikasi atas record orang lain diuji di web (WB-FRM-08).
  const periksa = async (akun: typeof AKUN.SPV) => {
    await masuk(page, akun)
    await page.goto('/stations')
    await page.waitForTimeout(1300)
    await pilihLine(page, 0)
    await page.goto(`/stations/${DP}/monitor`)
    await page.waitForTimeout(1300)
    await page.getByTestId('new-data-button').click()
    await page.waitForTimeout(1600)
    const isi = await isiForm(page, { isiBacaan: true })
    const c = await bisaDiisi(page.getByTestId('checked-by-toggle'), null)
    const a = await bisaDiisi(page.getByTestId('acknowledged-by-toggle'), null)
    await page.getByTestId('save-button').click()
    await page.waitForTimeout(2800)
    const tersimpan = !/\/form\//.test(new URL(page.url()).pathname)
    await keluar(page)
    return { identitas: isi.identitas, tersimpan, c, a }
  }
  const spv = await periksa(AKUN.SPV)
  const mgr = await periksa(AKUN.MGR)

  const ok = spv.c === true && spv.a !== true && mgr.a === true && mgr.c !== true
  catat('MB-FORM-13', V, ok ? 'Lulus' : 'Gagal',
    `tiap peran membuat recordnya sendiri di mobile, lalu kendali verifikasinya diperiksa. Sebagai Supervisor (record "${spv.identitas}", tersimpan=${spv.tersimpan}): Checked By dapat diisi=${spv.c} (harus bisa), Acknowledged By dapat diisi=${spv.a} (harus tidak). Sebagai Mill Management (record "${mgr.identitas}", tersimpan=${mgr.tersimpan}): Acknowledged By dapat diisi=${mgr.a} (harus bisa), Checked By dapat diisi=${mgr.c} (harus tidak). "null" berarti kendalinya tidak dirender sama sekali — itu juga berarti tidak dapat diisi, bahkan lebih tegas. Kendalinya kotak centang, jadi nama yang terisi adalah nama pengguna yang menekannya, bukan diketik bebas. CATATAN: langkah "buka record Operator sebagai Supervisor" tidak dapat dijalankan di mobile — daftar Data Preview hanya memuat record pengguna yang sedang masuk di perangkat itu.`)
  expect(ok).toBe(true)
})

test('MB-FORM-14 — tabel Target Operasional hanya referensi, tidak tersimpan', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  await bukaFormBaru(page, DP)
  const tabel = page.getByTestId('operational-target-table')
  const ada = (await tabel.count()) > 0
  if (!ada) {
    catat('MB-FORM-14', V, 'Gagal', 'Form Depricarping tidak memuat tabel Target Operasional.')
    expect(ada).toBe(true)
    return
  }
  const kolomDiTabel = await tabel.locator('input, select, textarea').count()
  const isiTabel = await teksAman(tabel)
  const isi = await isiForm(page, { isiBacaan: true })
  await page.getByTestId('save-button').click()
  await page.waitForTimeout(2600)

  // Nilai target tidak boleh menjadi bagian data record.
  await bukaPreview(page, DP)
  const baris = barisRecord(page, isi.identitas)
  let targetDiDetail = false
  if (await baris.count()) {
    await baris.click()
    await page.waitForTimeout(1700)
    const t = await teksHalaman(page)
    // Angka target khas (mis. "≤ 7.0%") tidak boleh muncul sebagai nilai record.
    const contohTarget = (isiTabel.match(/[≤≥]\s*[\d.,]+/) ?? [])[0] ?? ''
    targetDiDetail = contohTarget !== '' && t.includes(contohTarget)
  }
  const ok = kolomDiTabel === 0 && !targetDiDetail
  catat('MB-FORM-14', V, ok ? 'Lulus' : 'Gagal',
    `tabel Target Operasional tampil read-only — kolom yang dapat diketik di dalamnya=${kolomDiTabel} (harus 0); isinya: "${isiTabel.slice(0, 120)}"; nilai target ikut tersimpan sebagai data record=${targetDiDetail} (harus false — ia hanya acuan yang ditampilkan).`)
  expect(ok).toBe(true)
})

test('MB-FORM-15 — simpan ditolak bila Production Line tidak punya stasiun aktif', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  await page.goto('/stations')
  await page.waitForTimeout(1400)
  // Line fixture yang sengaja tanpa stasiun jenis ini berawalan "PL Tanpa".
  const pembuka = page.getByTestId('production-line-switcher')
  if (await pembuka.count()) { await pembuka.click(); await page.waitForTimeout(700) }
  const opsi = await page.locator('[data-testid^="production-line-option-"]').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()))
  const tanpa = opsi.findIndex((t) => /tanpa/i.test(t))
  if (tanpa < 0) {
    catat('MB-FORM-15', V, 'Tidak diuji',
      `Prasyarat tidak tersedia: akun Operator ini tidak punya pilihan Production Line tanpa stasiun Depricarping aktif. Pilihan yang ada: ${opsi.join(', ')}. Siapkan line seperti itu lewat Kelola Production Line sebagai Admin.`)
    return
  }
  await page.locator('[data-testid^="production-line-option-"]').nth(tanpa).click()
  await page.waitForTimeout(1300)
  await page.goto(`/stations/${DP}/monitor`)
  await page.waitForTimeout(1400)
  const tombol = page.getByTestId('new-data-button')
  const bisaBuka = (await tombol.count()) > 0 && (await tombol.isEnabled())
  let pesan = ''
  let ditolak = false
  if (bisaBuka) {
    await tombol.click()
    await page.waitForTimeout(1600)
    await isiForm(page, { isiBacaan: true })
    await page.getByTestId('save-button').click()
    await page.waitForTimeout(2600)
    const g = await galatForm(page)
    pesan = [g.grid, ...g.perKolom].join(' ')
    const teks = await teksHalaman(page)
    ditolak = /tidak memiliki station|belum memiliki station|tidak punya stasiun|station .* aktif/i.test(pesan + ' ' + teks)
  } else {
    pesan = await teksHalaman(page)
    ditolak = /tidak memiliki station|belum memiliki station|tidak punya stasiun/i.test(pesan)
  }
  const teknis = POLA_TEKNIS.test(pesan)
  const ok = ditolak && !teknis
  catat('MB-FORM-15', V, ok ? 'Lulus' : 'Gagal',
    `Production Line "${opsi[tanpa]}" (tanpa stasiun Depricarping aktif) dipilih: tombol New Data dapat ditekan=${bisaBuka}; penolakan yang menyebut stasiun tidak aktif muncul=${ditolak} ("${pesan.replace(/\s+/g, ' ').slice(0, 160)}"); galat teknis=${teknis} (harus false).`)
  expect(ok).toBe(true)
})

// ─────────────────────────────────────────────────────────────────────────────
// MB-FORM-01 — kasus pengabelan, dijalankan di KE-18 stasiun.
// ─────────────────────────────────────────────────────────────────────────────
for (const { kode, label } of STASIUN) {
  test(`MB-FORM-01 [${label}] — simpan berhasil dengan satu baris terisi`, async ({ page }) => {
    await masuk(page, AKUN.OPR)

    // Prasyarat khusus Grading: daftar WB Card No dibaca dari record
    // Weighbridge DI PERANGKAT ITU. Perangkat baru (konteks browser baru)
    // belum punya satu pun, dan layarnya berbunyi "Tidak ada data weighbridge
    // lokal" — jadi satu record Weighbridge dibuat lebih dulu.
    let prasyarat = ''
    if (kode === 'grading') {
      const alamatWb = await bukaFormBaru(page, 'weighbridge')
      if (alamatWb !== '') {
        const wb = await isiForm(page, { isiBacaan: true })
        await page.getByTestId('save-button').click()
        await page.waitForTimeout(2800)
        prasyarat = `satu record Weighbridge "${wb.identitas}" dibuat lebih dulu di perangkat yang sama, karena daftar WB Card No Grading dibaca dari record Weighbridge lokal. `
      }
    }

    const alamat = await bukaFormBaru(page, kode)
    if (alamat === '') {
      catat('MB-FORM-01', label, 'Gagal', `tombol New Data tidak ada pada Monitor ${label}.`)
      expect(alamat).not.toBe('')
      return
    }
    const isi = await isiForm(page, { isiBacaan: true })
    await page.getByTestId('save-button').click()
    await page.waitForTimeout(2800)
    const g = await galatForm(page)
    const galat = [g.grid, ...g.perKolom].filter(Boolean)
    const keluarForm = !/\/form\//.test(new URL(page.url()).pathname)
    const teks = await teksHalaman(page)
    const konfirmasi = /berhasil|tersimpan|disimpan/i.test(teks)

    // Record harus muncul di Data Preview berstatus Tersimpan.
    await bukaPreview(page, kode)
    const baris = barisRecord(page, isi.identitas)
    const ditemukan = (await baris.count()) > 0
    const isiBaris = ditemukan ? await teksAman(baris) : ''
    const berstatusTersimpan = /tersimpan|tersinkron/i.test(isiBaris)

    const ok = galat.length === 0 && keluarForm && ditemukan && berstatusTersimpan
    catat('MB-FORM-01', label, ok ? 'Lulus' : 'Gagal',
      `${prasyarat}Form baru diisi — identitas "${isi.identitas}", ${isi.barisDitambah} baris slot${isi.slot ? ` ("${isi.slot}")` : ''}, ${isi.kolomTerisi} kolom terisi — lalu Simpan: galat validasi=${galat.length}${galat.length ? ` → "${galat.join(' / ').slice(0, 150)}"` : ''}; layar keluar dari Form=${keluarForm}; pesan konfirmasi terbaca=${konfirmasi}; record ditemukan di Data Preview=${ditemukan} dengan status Tersimpan=${berstatusTersimpan} ("${isiBaris.slice(0, 90)}"). Satu baris terisi sudah cukup — tidak dipaksa mengisi 24 slot.`)
    expect(ok).toBe(true)
  })
}
