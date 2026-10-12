/**
 * Pelaksana bagian 4.7 dokumen QA — Data Preview Mobile (MB-PRV-01…08).
 * Varian tingkat 1 = Depricarping; MB-PRV-06 dijalankan di KE-18 stasiun.
 */
import { test, expect, type Page } from '@playwright/test'
import {
  AKUN, PER_TRANSAKSI, STASIUN,
  barisDetailPreview, barisRecord, bisaDiisi, bukaPreview, catat, isiForm, keluar, masuk, nilaiAman,
  pilihLine, pilihSlot, tanggalLokalHariIni, teksAman, teksHalaman,
} from './support'

const DP = 'depricarping'
const V = 'Depricarping'

/**
 * Menyiapkan record di perangkat ini: basis data lokal mobile kosong pada
 * setiap konteks browser baru, jadi tiap kasus membangun datanya sendiri —
 * itu juga yang membuat hitungan barisnya dapat dipercaya.
 */
async function buatRecord(page: Page, kode: string, opsi: { jeda?: boolean; baris?: number } = {}) {
  await page.goto('/stations')
  await page.waitForTimeout(1300)
  await pilihLine(page, 0)
  await page.goto(`/stations/${kode}/monitor`)
  await page.waitForTimeout(1300)
  await page.getByTestId('new-data-button').click()
  await page.waitForTimeout(1600)
  const isi = await isiForm(page, { isiBacaan: true })
  // Baris tambahan bila diminta.
  const tambahan = (opsi.baris ?? 1) - 1
  for (let i = 0; i < tambahan; i++) {
    // Ketiga nama tombol tambah baris dicoba, sama seperti isiForm: memaku
    // satu nama membuat Cages Track hanya punya satu baris dan kasus "tepat 3
    // baris" gagal karena pelaksananya, bukan karena layarnya.
    let tambah = page.getByTestId('add-detail-row-button')
    if (!(await tambah.count())) tambah = page.getByTestId('add-row-button')
    if (!(await tambah.count())) tambah = page.getByTestId('add-tipped-time-row-button')
    if (!(await tambah.count())) break
    await tambah.click()
    await page.waitForTimeout(900)
    const n = await page.locator('[data-testid^="time-slot-select-"]').count()
    if (n) await pilihSlot(page, n - 1, 0)
    else {
      const jam = page.locator('[data-testid^="tipped-hour-select-"]')
      const nJ = await jam.count()
      if (nJ) {
        const sl = jam.nth(nJ - 1)
        const o = await sl.locator('option').evaluateAll((x) =>
          x.map((e) => (e as HTMLSelectElement).value).filter((v) => v !== ''))
        if (o.length) await sl.selectOption(o[Math.min(i + 1, o.length - 1)]).catch(() => {})
      }
    }
    const baris = page.locator('[data-testid$="-detail-row"], [data-testid^="detail-row-"], [data-testid="tipped-time-row"]').last()
    const angka = baris.locator('input[type="number"], input[inputmode="decimal"]')
    if (await angka.count()) await angka.first().fill(`${50 + i}`).catch(() => {})
    const centang = page.locator('input[data-testid^="cage-checkbox-"]:visible, [data-testid^="cage-checkbox-"] input[type="checkbox"]:visible')
    if (await centang.count()) await centang.nth(Math.min(i + 1, (await centang.count()) - 1)).check({ timeout: 2500 }).catch(() => {})
  }
  await page.getByTestId(opsi.jeda ? 'pause-button' : 'save-button').click()
  await page.waitForTimeout(2800)
  return isi
}

test('MB-PRV-01 — filter tanggal default ke hari ini', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  // Satu record hari ini (dibuat di sini), dan satu record bertanggal lampau
  // yang disuntik ke basis data lokal lewat jembatan uji — menggeser tanggal
  // perangkat tidak mungkin dari browser, jadi recordnya yang diberi tanggal
  // lampau, bukan jamnya yang digeser.
  const hariIni = await buatRecord(page, DP)
  const lampau = await page.evaluate(async () => {
    const db = (window as any).__mslTestDb
    if (!db) return ''
    const id = 'qa-lampau-' + Date.now()
    const now = new Date().toISOString()
    const tgl = new Date(Date.now() - 6 * 86400000).toISOString().slice(0, 10)
    const u = JSON.parse(localStorage.getItem('msl_auth_user') ?? '{}')
    await db.run(
      `INSERT OR REPLACE INTO depricarping_record (id, status, depricarping_id, date, created_by, created_at, updated_at)
       VALUES (?, 'saved', ?, ?, ?, ?, ?)`,
      [id, 'QA-LAMPAU', tgl, u.id ?? null, now, now])
    await db.run(
      `INSERT OR REPLACE INTO depricarping_detail (id, depricarping_record_id, time_slot, fan_static_pressure_mmh2o, created_at, updated_at)
       VALUES (?, ?, '07:00', 11, ?, ?)`,
      [id + '-d', id, now, now])
    return tgl
  })

  await page.goto(`/stations/${DP}/preview`)
  await page.waitForTimeout(1800)
  const isiFilter = await nilaiAman(page.getByTestId('date-filter-input'))
  const hariIniIso = tanggalLokalHariIni()
  const bawaanHariIni = isiFilter === hariIniIso
  const item = await page.locator('[data-testid^="record-item-"]').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()))
  const adaHariIni = item.some((t) => t.includes(hariIni.identitas))
  const adaLampau = item.some((t) => t.includes('QA-LAMPAU'))
  const ok = bawaanHariIni && adaHariIni && !adaLampau && lampau !== ''
  catat('MB-PRV-01', V, ok ? 'Lulus' : 'Gagal',
    `satu record dibuat hari ini ("${hariIni.identitas}") dan satu record bertanggal ${lampau} disuntik ke basis data perangkat. Filter Tanggal sudah terisi "${isiFilter}" tanpa disentuh penguji — sama dengan hari ini (${hariIniIso})=${bawaanHariIni}; daftar memuat ${item.length} baris: record hari ini terlihat=${adaHariIni}, record tanggal lampau terlihat=${adaLampau} (harus false sampai filternya diubah).`)
  expect(ok).toBe(true)
})

test('MB-PRV-02 — pintasan "Semua" dan "Hari ini" bekerja', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  const hariIni = await buatRecord(page, DP)
  await page.evaluate(async () => {
    const db = (window as any).__mslTestDb
    if (!db) return
    const now = new Date().toISOString()
    const tgl = new Date(Date.now() - 5 * 86400000).toISOString().slice(0, 10)
    const u = JSON.parse(localStorage.getItem('msl_auth_user') ?? '{}')
    const id = 'qa-prv02-' + Date.now()
    await db.run(`INSERT OR REPLACE INTO depricarping_record (id, status, depricarping_id, date, created_by, created_at, updated_at)
                  VALUES (?, 'saved', 'QA-PRV02', ?, ?, ?, ?)`, [id, tgl, u.id ?? null, now, now])
    await db.run(`INSERT OR REPLACE INTO depricarping_detail (id, depricarping_record_id, time_slot, fan_static_pressure_mmh2o, created_at, updated_at)
                  VALUES (?, ?, '08:00', 9, ?, ?)`, [id + '-d', id, now, now])
  })
  await page.goto(`/stations/${DP}/preview`)
  await page.waitForTimeout(1800)

  await page.getByTestId('date-quick-all').click()
  await page.waitForTimeout(1300)
  const nSemua = await page.locator('[data-testid^="record-item-"]').count()
  const ringkasSemua = await teksAman(page.getByTestId('filter-result-count'))

  await page.getByTestId('date-quick-today').click()
  await page.waitForTimeout(1300)
  const nHariIni = await page.locator('[data-testid^="record-item-"]').count()
  const ringkasHariIni = await teksAman(page.getByTestId('filter-result-count'))
  const hanyaHariIni = (await page.locator('[data-testid^="record-item-"]').evaluateAll((els) =>
    els.map((e) => (e.textContent ?? '').trim()))).every((t) => t.includes(hariIni.identitas))

  const cocokRingkas = ringkasSemua.includes(String(nSemua)) && ringkasHariIni.includes(String(nHariIni))
  const ok = nSemua > nHariIni && hanyaHariIni && cocokRingkas
  catat('MB-PRV-02', V, ok ? 'Lulus' : 'Gagal',
    `pintasan "Semua" menampilkan ${nSemua} baris (ringkasan "${ringkasSemua}") — memuat record dari seluruh tanggal; pintasan "Hari ini" mengembalikan daftar ke ${nHariIni} baris (ringkasan "${ringkasHariIni}") dan seluruhnya record hari ini=${hanyaHariIni}; jumlah pada ringkasan cocok dengan jumlah baris yang terlihat=${cocokRingkas}.`)
  expect(ok).toBe(true)
})

test('MB-PRV-03 — pencarian tidak peduli huruf besar-kecil', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  // Identitas yang bentuknya pasti, supaya potongan di tengah dapat diuji.
  await buatRecord(page, DP)
  await page.goto(`/stations/${DP}/monitor`)
  await page.waitForTimeout(1300)
  await page.getByTestId('new-data-button').click()
  await page.waitForTimeout(1600)
  await isiForm(page, { isiBacaan: true })
  await page.locator('input.form-field-input:not([disabled])').first().fill('PRESSER-01')
  await page.getByTestId('save-button').click()
  await page.waitForTimeout(2800)

  await bukaPreview(page, DP)
  const cari = page.getByTestId('search-filter-input')
  const coba = async (kata: string) => {
    await cari.fill(kata)
    await page.waitForTimeout(1200)
    const n = await page.locator('[data-testid^="record-item-"]').filter({ hasText: 'PRESSER-01' }).count()
    return n
  }
  const kecil = await coba('presser-01')
  const besar = await coba('PRESSER')
  const tengah = await coba('esser-0')
  const ok = kecil >= 1 && besar >= 1 && tengah >= 1
  catat('MB-PRV-03', V, ok ? 'Lulus' : 'Gagal',
    `record beridentitas "PRESSER-01" dibuat. Pencarian "presser-01" (huruf kecil) menemukan ${kecil} baris; "PRESSER" (huruf besar) menemukan ${besar}; "esser-0" (potongan di tengah) menemukan ${tengah}. Ketiganya menemukan record itu=${ok} — pencarian tidak peduli huruf besar-kecil dan cocok pada potongan di tengah, bukan hanya awalan.`)
  expect(ok).toBe(true)
})

test('MB-PRV-04 — tombol × menghapus kata kunci dan memulihkan daftar', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  const a = await buatRecord(page, DP)
  await page.goto(`/stations/${DP}/monitor`)
  await page.waitForTimeout(1300)
  await page.getByTestId('new-data-button').click()
  await page.waitForTimeout(1600)
  await isiForm(page, { isiBacaan: true })
  await page.locator('input.form-field-input:not([disabled])').first().fill('QA-LAIN-02')
  await page.getByTestId('save-button').click()
  await page.waitForTimeout(2800)

  await bukaPreview(page, DP)
  const awal = await page.locator('[data-testid^="record-item-"]').count()
  const xSebelum = await page.getByTestId('search-filter-input-clear').count()
  await page.getByTestId('search-filter-input').fill('QA-LAIN-02')
  await page.waitForTimeout(1200)
  const disaring = await page.locator('[data-testid^="record-item-"]').count()
  const xSesudah = await page.getByTestId('search-filter-input-clear').count()
  if (xSesudah) { await page.getByTestId('search-filter-input-clear').click(); await page.waitForTimeout(1200) }
  const kataKunci = await nilaiAman(page.getByTestId('search-filter-input'))
  const pulih = await page.locator('[data-testid^="record-item-"]').count()

  const ok = xSebelum === 0 && xSesudah > 0 && kataKunci === '' && pulih === awal && disaring < awal
  catat('MB-PRV-04', V, ok ? 'Lulus' : 'Gagal',
    `daftar awal ${awal} baris (record "${a.identitas}" dan "QA-LAIN-02"); tombol × sebelum kolom Cari terisi=${xSebelum} (harus 0); sesudah kata kunci "QA-LAIN-02" diketik: ${disaring} baris dan tombol × muncul=${xSesudah > 0}; sesudah × ditekan: kata kunci kosong=${kataKunci === ''} dan daftar kembali ke ${pulih} baris=${pulih === awal}.`)
  expect(ok).toBe(true)
})

test('MB-PRV-05 — draft mengarah ke Form, record final ke detail read-only', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  const final = await buatRecord(page, DP)
  const draft = await buatRecord(page, DP, { jeda: true })

  await bukaPreview(page, DP)
  const barisDraft = barisRecord(page, draft.identitas)
  const adaDraft = (await barisDraft.count()) > 0
  let alamatDraft = ''
  if (adaDraft) {
    await barisDraft.click()
    await page.waitForTimeout(1700)
    alamatDraft = new URL(page.url()).pathname
  }

  await bukaPreview(page, DP)
  const barisFinal = barisRecord(page, final.identitas)
  const adaFinal = (await barisFinal.count()) > 0
  let alamatFinal = ''
  let bisaDiubah: boolean | null = null
  if (adaFinal) {
    await barisFinal.click()
    await page.waitForTimeout(1700)
    alamatFinal = new URL(page.url()).pathname
    const kolom = page.locator('input:not([type="hidden"]), textarea, select')
    const n = await kolom.count()
    let bisa = 0
    for (let i = 0; i < n; i++) {
      const k = kolom.nth(i)
      if (!(await k.isVisible().catch(() => false))) continue
      if ((await bisaDiisi(k, false)) === true) bisa++
    }
    bisaDiubah = bisa > 0
  }
  const ok = adaDraft && /\/form\//.test(alamatDraft) && adaFinal && !/\/form\//.test(alamatFinal) && bisaDiubah === false
  catat('MB-PRV-05', V, ok ? 'Lulus' : 'Gagal',
    `baris draft "${draft.identitas}" mendarat di ${alamatDraft} (Form — dapat dilanjutkan)=${/\/form\//.test(alamatDraft)}; baris record Tersimpan "${final.identitas}" mendarat di ${alamatFinal} (detail read-only)=${!/\/form\//.test(alamatFinal)}; pada layar detail itu ada nilai yang dapat diubah=${bisaDiubah} (harus false).`)
  expect(ok).toBe(true)
})

test('MB-PRV-07 — Checked By / Acknowledged By terlihat oleh semua peran', async ({ page }) => {
  // Record dibuat SUPERVISOR dan ditandai Checked olehnya — hanya Supervisor
  // yang dapat menandainya, dan di mobile tiap peran hanya melihat recordnya
  // sendiri (lihat catatan MB-FORM-13), jadi yang diperiksa di sini adalah
  // bahwa baris Checked By / Acknowledged By TERLIHAT, bukan dapat diisi.
  await masuk(page, AKUN.SPV)
  await page.goto('/stations')
  await page.waitForTimeout(1300)
  await pilihLine(page, 0)
  await page.goto(`/stations/${DP}/monitor`)
  await page.waitForTimeout(1300)
  await page.getByTestId('new-data-button').click()
  await page.waitForTimeout(1600)
  const isi = await isiForm(page, { isiBacaan: true })
  const centang = page.getByTestId('checked-by-toggle')
  const bisaCentang = (await bisaDiisi(centang, false)) === true
  if (bisaCentang) { await centang.check({ timeout: 3000 }).catch(() => {}) }
  await page.getByTestId('save-button').click()
  await page.waitForTimeout(2800)

  await bukaPreview(page, DP)
  const baris = barisRecord(page, isi.identitas)
  const ada = (await baris.count()) > 0
  let isiDetail = ''
  if (ada) { await baris.click(); await page.waitForTimeout(1700); isiDetail = await teksHalaman(page) }
  const terlihatChecked = /checked by/i.test(isiDetail)
  const terlihatAck = /acknowledged by/i.test(isiDetail)
  // Acknowledged yang belum terisi harus tampil sebagai tanda pisah atau
  // keterangan — bukan 0 dan bukan kosong tanpa label.
  const ackKosongBenar = /acknowledged by\s*[:\-]?\s*(—|-|–|belum)/i.test(isiDetail)
  const ackNol = /acknowledged by\s*[:\-]?\s*0\b/i.test(isiDetail)
  const ok = ada && terlihatChecked && terlihatAck && !ackNol
  catat('MB-PRV-07', V, ok ? 'Lulus' : 'Gagal',
    `record "${isi.identitas}" dibuat Supervisor dan ditandai Checked olehnya (kendalinya dapat dipakai=${bisaCentang}). Pada detail Data Preview: baris Checked By terlihat=${terlihatChecked}; baris Acknowledged By terlihat=${terlihatAck}; Acknowledged yang belum terisi tampil sebagai tanda pisah atau keterangan=${ackKosongBenar}; tampil sebagai angka 0=${ackNol} (harus false). Catatan: di mobile tiap peran hanya melihat recordnya sendiri, jadi "ketiga peran melihat record yang sama" tidak dapat dijalankan di sini — bagian itu diuji di web.`)
  expect(ok).toBe(true)
})

test('MB-PRV-08 — Reset Filter memulihkan keadaan awal', async ({ page }) => {
  await masuk(page, AKUN.OPR)
  await buatRecord(page, DP)
  await page.goto(`/stations/${DP}/preview`)
  await page.waitForTimeout(1800)
  const tanggalAwal = await nilaiAman(page.getByTestId('date-filter-input'))
  const barisAwal = await page.locator('[data-testid^="record-item-"]').count()
  const resetSebelum = await page.getByTestId('reset-filter-button').count()

  await page.getByTestId('date-filter-input').fill('2026-01-01')
  await page.waitForTimeout(1100)
  await page.getByTestId('search-filter-input').fill('QA-TIDAK-ADA')
  await page.waitForTimeout(1200)
  const resetSesudah = await page.getByTestId('reset-filter-button').count()
  const barisDisaring = await page.locator('[data-testid^="record-item-"]').count()

  if (resetSesudah) { await page.getByTestId('reset-filter-button').click(); await page.waitForTimeout(1300) }
  const tanggalPulih = await nilaiAman(page.getByTestId('date-filter-input'))
  const cariPulih = await nilaiAman(page.getByTestId('search-filter-input'))
  const barisPulih = await page.locator('[data-testid^="record-item-"]').count()

  const ok = resetSebelum === 0 && resetSesudah > 0 && tanggalPulih === tanggalAwal &&
             cariPulih === '' && barisPulih === barisAwal
  catat('MB-PRV-08', V, ok ? 'Lulus' : 'Gagal',
    `Keadaan awal tanpa disentuh: filter Tanggal "${tanggalAwal}" dengan ${barisAwal} baris. `
    + `Tombol Reset Filter terlihat pada keadaan awal itu=${resetSebelum > 0} (harus false — tanggal bawaan hari ini adalah BAWAAN, bukan filter yang dipilih penguji; tombol yang praktis selalu ada berhenti menandakan apa pun). `
    + `Sesudah Tanggal diubah ke 01/01/2026 dan kata kunci diketik: ${barisDisaring} baris, tombol Reset Filter ada=${resetSesudah > 0} (harus true). `
    + `Sesudah Reset ditekan: kolom Cari kosong=${cariPulih === ''}; filter Tanggal kembali ke hari ini "${tanggalAwal}"=${tanggalPulih === tanggalAwal} (terbaca "${tanggalPulih || '(kosong)'}"); jumlah baris kembali seperti keadaan awal=${barisPulih === barisAwal} (${barisPulih} vs ${barisAwal}). `
    + `Mengosongkan tanggal alih-alih memulihkannya akan MELEBARKAN daftar ke seluruh tanggal — layar sesudah Reset menampilkan lebih banyak data daripada saat dibuka.`)
  expect(ok).toBe(true)
})

// ─────────────────────────────────────────────────────────────────────────────
// MB-PRV-06 — kasus pengabelan, dijalankan di KE-18 stasiun.
// ─────────────────────────────────────────────────────────────────────────────
for (const { kode, label } of STASIUN) {
  test(`MB-PRV-06 [${label}] — detail menampilkan persis baris yang tersimpan`, async ({ page }) => {
    await masuk(page, AKUN.OPR)
    if (kode === 'grading') {
      // Prasyarat Grading: satu record Weighbridge lokal lebih dulu.
      const alamatWb = await (async () => {
        await page.goto('/stations'); await page.waitForTimeout(1300)
        await pilihLine(page, 0)
        await page.goto('/stations/weighbridge/monitor'); await page.waitForTimeout(1300)
        const t = page.getByTestId('new-data-button')
        if (!(await t.count())) return ''
        await t.click(); await page.waitForTimeout(1600)
        await isiForm(page, { isiBacaan: true })
        await page.getByTestId('save-button').click(); await page.waitForTimeout(2800)
        return 'ok'
      })()
      void alamatWb
    }
    // Grading termasuk keluarga per-transaksi (satu record per muatan) TETAPI
    // punya beberapa baris PARAMETER per muatan, jadi ia diperiksa seperti
    // stasiun bergrid: jumlah baris detailnya harus sama dengan yang disimpan.
    const perTransaksi = PER_TRANSAKSI.includes(kode) && kode !== 'grading'
    const diminta = perTransaksi ? 1 : 3
    const isi = await buatRecord(page, kode, { baris: diminta })

    const jmlItem = await bukaPreview(page, kode)
    const teksItem = await page.locator('[data-testid^="record-item-"]').evaluateAll((els) =>
      els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()))
    // Dicari menurut identitasnya; bila perangkat ini hanya punya SATU record
    // (basis data lokal kosong di setiap konteks browser baru), record itulah
    // yang baru saja disimpan — tiap layar menampilkan kolom identitas yang
    // berbeda, jadi memaksakan pencocokan teks akan gagal di beberapa stasiun
    // tanpa alasan yang berarti.
    let baris = barisRecord(page, isi.identitas)
    let caraTemu = `dicocokkan dengan identitas "${isi.identitas}"`
    if (!(await baris.count()) && jmlItem === 1) {
      baris = page.locator('[data-testid^="record-item-"]').first()
      caraTemu = `satu-satunya record di perangkat ini ("${teksItem[0] ?? ''}")`
    }
    const ada = (await baris.count()) > 0
    if (!ada) {
      catat('MB-PRV-06', label, 'Gagal',
        `record "${isi.identitas}" yang baru disimpan tidak ditemukan di Data Preview ${label} (${jmlItem} baris di daftar: ${teksItem.join(' | ').slice(0, 120)}), sehingga grid detailnya tidak dapat diperiksa.`)
      expect(ada).toBe(true)
      return
    }
    await baris.click()
    await page.waitForTimeout(1800)
    const { jml: barisDetail, cara } = await barisDetailPreview(page)
    const kosong = await page.locator('[data-testid$="-detail-rows-empty"], [data-testid$="-rows-empty"]').count()
    // Tidak boleh digambar 24 baris untuk mengisi sampai penuh.
    const ok = perTransaksi
      ? (barisDetail <= 1 || kosong > 0)
      : barisDetail === diminta
    catat('MB-PRV-06', label, ok ? 'Lulus' : 'Gagal',
      perTransaksi
        ? `${label} adalah stasiun per-transaksi: satu record adalah satu transaksi, jadi grid 24 slot tidak ada. Pada detail record (${caraTemu}) terbaca ${barisDetail} baris grid (dihitung dari ${cara}; penanda daftar kosong=${kosong}) — tidak digambar baris pengisi.`
        : `record ${caraTemu}, disimpan dengan ${diminta} baris; detailnya menggambar ${barisDetail} baris (dihitung dari ${cara}) — tepat sama=${barisDetail === diminta}, bukan 24 dan bukan dipadatkan menjadi satu. Tidak ada baris kosong tambahan yang digambar untuk mengisi sampai 24.`)
    expect(ok).toBe(true)
  })
}
