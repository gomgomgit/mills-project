import { expect, test, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { getAuthUserId, login, USERS } from './helpers'

/**
 * laporan-solid-waste-disposal.spec.ts — screen-163--laporan-solid-waste-disposal-mobile /
 * usecase-169--laporan-solid-waste-disposal-mobile.
 *
 * Satu test per `test_scenarios` yang punya `browser_test` (ke-32-nya punya).
 * Berjalan di Vite dev server (playwright.config.ts, baseURL
 * http://localhost:5174): aplikasi Capacitor adalah SPA biasa sebelum dibungkus
 * native, jadi browser test layar mobile TIDAK ditangguhkan sebagai
 * "mobile-only".
 *
 * VIEWPORT PONSEL, BUKAN DESKTOP. Berkas ini menimpa viewport bawaan proyek
 * chromium (Desktop Chrome, 1280px) dengan 390x844. Separuh klaim layar ini
 * adalah klaim tata letak ("halaman tidak menggulir mendatar walau rekapnya
 * lima kolom", "tabel BERTUMPUK menjadi kartu", "prosa ketiadaan terbaca
 * UTUH"), dan seluruhnya hampa pada kanvas 1280px: apa pun muat di sana.
 *
 * ────────────────────────────────────────────────────────────────────────
 * RESPONS LAPORAN DI-STUB DI TINGKAT JARINGAN (page.route), BUKAN DISEMAI —
 * DAN ITULAH YANG MENJAGA BASIS DATA DEV
 * ────────────────────────────────────────────────────────────────────────
 * Keputusan dan alasan yang sama dengan keempat belas laporan mobile lain:
 *
 *   - Layar ini read-only total dan tidak punya satu pun UI untuk membuat
 *     data yang dibacanya.
 *   - Kebenaran ANGKA-nya bukan tanggung jawab layar ini: seluruhnya dari
 *     SolidWasteDisposalReportService, yang sudah diuji penuh ketika
 *     screen-162 dibangun. Yang khas mobile — dan hanya dapat dibuktikan di
 *     sini — adalah PEMETAAN respons itu ke layar satu kolom: lima kartu
 *     angka, empat rekap kategoris + rekap harian yang BERTUMPUK menjadi
 *     kartu, dan ketiga prosa ketiadaan yang harus terbaca utuh.
 *   - Beberapa skenario mustahil dibuat dari data sungguhan tanpa merusak
 *     lingkungan (sesi kedaluwarsa, jaringan putus, 403 line milik mill lain,
 *     periode belum mulai, muatan yang sengaja tidak konsisten secara
 *     aritmetika, penyimpanan perangkat yang melempar).
 *   - DAN YANG PALING MENGIKAT: backend di :8000 adalah DATABASE DEV — data
 *     kerja pengguna. Berkas ini karena itu TIDAK menulis, menghapus,
 *     menyemai, atau memigrasi apa pun di sana. Yang menyentuh backend
 *     sungguhan HANYA login (POST /api/login). TIDAK ADA `migrate`,
 *     `migrate:fresh`, `--seed`, maupun `db:prepare` di jalur spec ini.
 *
 * TIDAK ADA BACKEND :8001 DI SINI. `test_strategy.browser_test.start_command`
 * menyebut port :8001 dan `db:prepare` — itu menggambarkan suite e2e-web/ di
 * akar proyek, BUKAN jalur layar ini.
 *
 * ── LIMA HAL YANG HANYA BROWSER DAPAT MEMBUKTIKAN DI LAYAR INI ─────────
 *
 * 1. TABELNYA BENAR-BENAR BERTUMPUK MENJADI KARTU pada 390px, DIUKUR: tiap sel
 *    menempati barisnya sendiri (kotaknya TURUN, bukan berderet) dan membawa
 *    `data-label` yang disalin direktif v-stacked-table dari <th>-nya. Di
 *    jsdom direktif itu tidak terdaftar sama sekali ("Failed to resolve
 *    directive: stacked-table"), jadi klaim ini TIDAK DAPAT diuji di spec
 *    komponen.
 *
 * 2. HALAMAN TIDAK PERNAH MENGGULIR MENDATAR, DIUKUR — scrollWidth lawan
 *    clientWidth pada 390px, bukan disimpulkan dari nama kelas. Markup yang
 *    benar tetap dapat terpotong induknya.
 *
 * 3. PROSA KETIADAAN TERBACA UTUH pada 390px — DIUKUR pada hasil render:
 *    scrollHeight lawan clientHeight, plus `textOverflow` dan
 *    `webkitLineClamp` dari CSS HASIL COMPILE. Keterangan yang terpotong di
 *    tengah berhenti menerangkan apa pun, dan di layar sempit itulah godaan
 *    terbesarnya.
 *
 * 4. SETIAP KELAS CSS YANG DIPAKAI MARKUP PUNYA DEFINISI DI CSS HASIL COMPILE
 *    — dibaca dari document.styleSheets, bukan dari berkas sumber. Kelas tanpa
 *    definisi tidak menggagalkan satu asersi sumber pun; ia hanya membuat
 *    bagian layar kehilangan gayanya tanpa satu tanda.
 *
 * 5. UNDUHAN CSV BENAR-BENAR TERPICU, dan berkasnya dibaca dari disk: 22 kolom
 *    header, termasuk keenam kolom yang tidak muncul di satu angka pun di
 *    layar.
 *
 * ── KONVENSI YANG HARGANYA SUDAH DIBAYAR ───────────────────────────────
 *
 * LAYAR INI TIDAK MEMILIHKAN LINE, dan TIDAK PULA MEMILIHKAN PERIODE. Setiap
 * test di bawah karena itu memilih keduanya (atau menyemai ingatan line lebih
 * dulu); test yang hanya memilih salah satunya akan menunggu angka yang memang
 * tidak akan pernah datang.
 *
 * KETIADAAN DIASERSI ATAS TESTID, ROLE DAN JUDUL — BUKAN ATAS PROSA. Layar ini
 * WAJIB merender kalimat yang menyebut 'jam', 'shift', 'downtime', 'di luar
 * batas' dan '.xlsx', seluruhnya untuk menerangkan ketiadaannya SENDIRI.
 * Pemindaian prosa (termasuk `body.innerText`) karena itu MEMERAH pada layar
 * yang BENAR, dan tidak dipakai satu kali pun di berkas ini.
 *
 * ALAMAT DIBANDINGKAN ATAS PATHNAME, bukan dengan toHaveURL('**\/x'): dengan
 * baseURL terpasang, pola glob semacam itu TIDAK PERNAH cocok dan test-nya
 * "lulus" tanpa pernah menguji apa pun.
 *
 * SETIAP count() DIDAHULUI ASERSI YANG MENUNGGU SENDIRI (toBeVisible /
 * toHaveCount). count() tanpa penantian mengembalikan 0 pada halaman yang
 * belum selesai merender, dan 0 adalah jawaban yang diam-diam meluluskan
 * asersi "tidak ada".
 *
 * KELIMA REKAP MEMAKAI CollapsibleSection ber-`v-show` dan TERTUTUP SECARA
 * BAWAAN: saat tertutup barisnya TETAP ADA di DOM dengan display:none. Karena
 * itu isinya DIBUKA lewat kontrol `collapsible-section-toggle` sebelum
 * diasersi terlihat, dan keadaan tertutup diasersi dengan toBeHidden() —
 * tidak pernah dengan toHaveCount(0).
 *
 * LURING DIPALSUKAN DI `navigator.onLine` LEWAT addInitScript, DENGAN STUB API
 * TETAP TERPASANG. `context.setOffline(true)` + `page.reload()` memutus
 * pengambilan dokumen dan bundle dari Vite dev server: navigasinya mati dengan
 * ERR_INTERNET_DISCONNECTED dan aplikasinya TIDAK PERNAH DIJALANKAN — yang
 * teruji dengan cara itu adalah Chromium, bukan layar ini. Stub dibiarkan
 * terpasang justru supaya penolakan permintaan DAPAT DIBANTAH oleh penghitung
 * permintaannya.
 *
 * PRASYARAT APA PUN TETAP DI DALAM STUB. Sebuah prasyarat tulis lintas layar
 * membuat beforeAll MENGGANTUNG alih-alih gagal; berkas ini tidak punya satu
 * pun, dan login adalah satu-satunya permintaan sungguhannya.
 *
 * TANGGAL FIXTURE RELATIF terhadap hari ini — tidak ada tanggal kalender yang
 * ditulis mati, dan nama periodenya pun bukan nama bulan supaya slug nama
 * berkas ekspor tidak ikut basi. Tidak ada satu pun asersi "tepat N record
 * hari ini": seluruh angka datang dari stub bertanggal tetap-relatif, bukan
 * dari factory bertanggal acak.
 *
 * MENJALANKANNYA MENUNTUT DUA PROSES: backend di :8000 (dengan seeder
 * DemoAccountSeeder sudah dijalankan — login di sini POST sungguhan) dan Vite
 * dev server di :5174 (dinyalakan playwright.config.ts sendiri lewat blok
 * webServer, reuseExistingServer).
 */

const ROUTE = '/reports/solid-waste-disposal'
const PHONE = { width: 390, height: 844 }

/**
 * Halaman disajikan dari :5174 sementara API berada di :8000, jadi setiap
 * respons stub harus membawa header CORS-nya sendiri (termasuk jawaban
 * preflight) — tanpa ini axios menerimanya sebagai kegagalan jaringan dan
 * setiap test di bawah akan "lulus" ke cabang yang salah.
 */
const CORS_HEADERS = {
  'access-control-allow-origin': '*',
  'access-control-allow-headers': '*',
  'access-control-allow-methods': 'GET,POST,OPTIONS',
}

/* ------------------------------------------------------------------ */
/* Tanggal relatif                                                     */
/* ------------------------------------------------------------------ */

function isoDaysFromNow(offsetDays: number): string {
  return new Date(Date.now() + offsetDays * 86_400_000).toISOString().slice(0, 10)
}

const PERIOD_START = isoDaysFromNow(-29)
const PERIOD_END = isoDaysFromNow(0)
const PREV_START = isoDaysFromNow(-70)
const PREV_END = isoDaysFromNow(-40)

/** SEMBILAN hari bermuatan di dalam periode 30 hari. */
const LOADED_DAYS = [-25, -22, -20, -18, -15, -12, -9, -6, -3].map(isoDaysFromNow)

/** Urutan payload SENGAJA bukan urut tanggal naik. */
const DAILY_ORDER = [4, 0, 7, 2, 8, 1, 6, 3, 5].map((index) => LOADED_DAYS[index])

/* ------------------------------------------------------------------ */
/* Prosa SERVER — DISALIN dari SolidWasteDisposalReportService         */
/* ------------------------------------------------------------------ */

/**
 * DISALIN VERBATIM dari backend/app/Services/SolidWasteDisposalReportService.php
 * (NOTE_NO_HOURLY ± baris 342, NOTE_SHIFT_NOT_PUBLISHED ± 354, NOTE_ABSENCES
 * ± 365, NOTE_COMPLIANCE ± 379, NOTE_DAILY_TOTAL ± 391) — bukan dikarang.
 * Layar merendernya UTUH, dan prosa karangan akan membuat asersi "terbaca
 * utuh" hijau sambil membuktikan nol hal tentang kalimat yang benar-benar
 * dibaca pengguna.
 */
const NOTE_NO_HOURLY =
  'Sebaran muatan per jam TIDAK DAPAT dibuat untuk stasiun ini, dan ketiadaannya '
  + 'dinyatakan di sini alih-alih disembunyikan. Baris detail Solid Waste Disposal hanya '
  + 'membawa event_date — sebuah tanggal — dan tidak punya satu pun kolom waktu. Bukan '
  + 'kolom waktu yang kosong, bukan kolom yang belum diisi operator: kolomnya tidak ada. '
  + 'Karena itu pertanyaan tentang kapan dalam sehari limbah paling banyak keluar tidak '
  + 'dapat dihitung dari data yang ada, dalam bentuk apa pun — tidak sebagai grafik 24 '
  + 'kolom, tidak sebagai kartu jam tersibuk, tidak pula sebagai perkiraan. Ini BUKAN fitur '
  + 'yang ditunda. Satu-satunya sumbu waktu yang tersedia di layar ini adalah tanggal, '
  + 'yaitu rekap harian. Menurunkannya dari created_at berarti menebak dari waktu INPUT, '
  + 'bukan waktu kejadian, dan jawaban yang terlihat rinci padahal tidak pernah diukur '
  + 'lebih buruk daripada tidak ada jawaban.'

const NOTE_SHIFT_NOT_PUBLISHED =
  'Kolom shift pada baris detail bertipe TEKS BEBAS, bukan daftar nilai tertutup dan '
  + 'bukan relasi ke master. Menyebarkan teks bebas menghasilkan satu nilai per ejaan yang '
  + 'berbeda, sehingga empat penulisan dari satu giliran kerja yang sama akan terbaca '
  + 'sebagai empat kategori. Kolom itu karena itu TIDAK diterbitkan di payload maupun di '
  + 'layar ini — bukan sebagai rekap, bukan sebagai kolom tabel, bukan pula sebagai tempat '
  + 'kosong. Ia TETAP ikut di berkas ekspor pada setiap baris muatan, bersama nomor gate '
  + 'pass, nomor segel dan id operator, karena ekspor adalah rincian mentah. Pengelompokan '
  + 'per giliran kerja yang sungguhan datang dari kolom shift_id pada tabel INDUK dan '
  + 'berlaku untuk 18 stasiun sekaligus — pekerjaan tersendiri, bukan sesuatu yang '
  + 'ditempelkan pada satu laporan.'

const NOTE_ABSENCES =
  'Layar ini tidak memuat blok downtime, dan ketiadaannya disengaja: stasiun Solid Waste '
  + 'Disposal tidak mencatat downtime sama sekali — tidak ada kolom durasi berhenti, tidak '
  + 'ada kolom penyebab berhenti, dan tidak ada tabel downtime pada baris detailnya. Blok '
  + 'kosong sebagai tempat yang menunggu diisi akan terbaca sebagai belum ada datanya, '
  + 'padahal yang benar adalah stasiun ini memang tidak mengukurnya. Tidak ada pula satu '
  + 'nilai pun di layar ini yang ditandai di luar batas: stasiun ini tidak punya tabel '
  + 'master target, sehingga tidak ada satu baris acuan yang dapat dibandingkan dengan '
  + 'berat bersih sebuah muatan, dengan rata-rata netto per muatan, maupun dengan jumlah '
  + 'muatan per hari. Menurunkan ambang dari data periode ini sendiri akan menghasilkan '
  + 'garis yang terlihat berwibawa lalu dibaca sebagai batas resmi padahal bukan, dan garis '
  + 'itu ikut bergeser setiap kali kelengkapan pencatatan berubah. Seluruh angka karena itu '
  + 'ditampilkan apa adanya, tanpa warna peringatan dan tanpa satu sel yang ditandai; '
  + 'penilaian layak atau tidak layak tetap di tangan pembacanya.'

const NOTE_COMPLIANCE =
  'Nomor gate pass dan nomor segel keamanan adalah JEJAK KEPATUHAN, bukan ukuran '
  + 'produksi: keduanya menyatakan dokumen angkut yang tidak terbit. Karena itu keduanya '
  + 'diterbitkan HANYA sebagai penghitung kehadiran — berapa muatan tanpa gate pass dan '
  + 'berapa muatan tanpa segel — dan tidak pernah dirata-rata, tidak pernah diurutkan, dan '
  + 'tidak pernah digrafikkan. Sebuah nomor segel tidak punya besaran: segel bernomor 90021 '
  + 'bukan lebih besar daripada segel bernomor 90018, selisih keduanya tidak berarti apa '
  + 'pun, dan rata-rata nomor segel adalah angka yang tidak mengukur apa-apa. Bila seluruh '
  + 'muatan periode lengkap, kedua penghitung menunjukkan nol dan nol itu TETAP diterbitkan '
  + '— nol itulah yang memberi tahu pembaca bahwa pemeriksaannya memang berjalan. '
  + 'Nomor-nomornya sendiri tidak diterbitkan di payload layar; ia hanya ada di ekspor.'

const NOTE_DAILY_TOTAL =
  'Baris total ini DIJUMLAHKAN dari daily[], bukan disalin dari blok loads. Keduanya '
  + 'memang dapat berbeda, tepatnya sebesar undated_load_count — muatan yang event_date-nya '
  + 'kosong ikut di loads dan di keempat rekap kategoris tetapi tidak dapat ditempatkan '
  + 'pada hari mana pun. Perbedaan itu adalah informasi; menyalin akan menyembunyikannya, '
  + 'dan selisih yang tak dijelaskan tidak dapat dibedakan dari cacat.'

/**
 * HEADER EKSPOR — DISALIN VERBATIM dari
 * SolidWasteDisposalReportService::EXPORT_HEADER (± baris 316): 5 kolom
 * konteks (EXPORT_CONTEXT_COLUMN_COUNT) + 17 kolom detail
 * (DETAIL_EXPORT_FIELDS) = 22, dengan nama, EJAAN dan URUTAN apa adanya.
 *
 * Pada screen-161 stub CSV DIKARANG dan menghasilkan 22 kolom sementara header
 * nyata punya 21. Seandainya angka asersinya yang disesuaikan, seluruh berkas
 * akan hijau sambil membuktikan NOL hal tentang berkas yang sungguh diunduh
 * pengguna — layar mengunduh dari endpoint ASLI, bukan dari stub. Sebuah stub
 * hanya sekuat kesetiaannya pada yang ia tiru, dan kesetiaan itu tidak dijaga
 * oleh apa pun selain disalin.
 */
const EXPORT_HEADER = [
  'Periode',
  'Mill',
  'Production Line',
  'Tanggal Record',
  'Status',
  'Tanggal Kejadian',
  'Shift',
  'Weighbridge Ticket No',
  'Vehicle No',
  'Driver Name',
  'Solid Waste Type',
  'Source Station',
  'Gross Weight (MT)',
  'Tare Weight (MT)',
  'Net Weight (MT)',
  'Disposal/Utilization Site',
  'Purpose/End Use',
  'Gate Pass No',
  'Security Seal No',
  'Operator ID',
  'Remarks',
  'Findings',
]

/* ------------------------------------------------------------------ */
/* Fixture — BENTUK RESPONS NYATA                                      */
/* ------------------------------------------------------------------ */

const PERIOD = {
  id: 'per-1',
  name: 'Periode Uji Solid Waste Disposal',
  start_date: PERIOD_START,
  end_date: PERIOD_END,
  status: 'open',
  // DENGAN TANDA HUBUNG. Garis bawah di situ tidak cocok dengan satu baris
  // period_stations pun, dan server menjawab [] tanpa satu galat.
  station_type: 'solid-waste-disposal',
  station_type_label: 'Solid Waste Disposal',
}

const PERIOD_CLOSED = {
  id: 'per-2',
  name: 'Periode Sebelumnya',
  start_date: PREV_START,
  end_date: PREV_END,
  status: 'closed',
  station_type: 'solid-waste-disposal',
  station_type_label: 'Solid Waste Disposal',
}

const LINE_1 = { id: 'pl-1', name: 'Line Satu', code: 'L1' }
const LINE_2 = { id: 'pl-2', name: 'Line Dua', code: 'L2' }

const BUSINESS_UNITS = [
  { id: 'bu-1', name: 'Mill Sungai Rambutan' },
  { id: 'bu-9', name: 'Mill Sungai Lilin' },
]

/** Kunci ingatan line, ditulis StationListView.vue dan dibaca layar ini. */
const rememberedLineKey = (userId: string) => `msl_production_line_${userId}`

function row(
  value: string | null,
  loadCount: number,
  netWeightTotal: number | null,
  netWeightLoadCount: number,
  netWeightAvg: number | null,
): Record<string, unknown> {
  return {
    value,
    load_count: loadCount,
    net_weight_total: netWeightTotal,
    net_weight_load_count: netWeightLoadCount,
    net_weight_avg: netWeightAvg,
  }
}

/**
 * KEEMPAT rekap, masing-masing berjumlah TEPAT 12 dengan komposisi BERBEDA,
 * masing-masing memuat SATU baris `value: null`, dan tidak satu pun urut
 * alfabetis maupun urut jumlah — baris null bahkan BUKAN di posisi terakhir.
 */
function recapsOfTwelve(): Record<string, Array<Record<string, unknown>>> {
  return {
    by_waste_type: [
      row('Zpalm shell', 1, 4.5, 1, 4.5),
      row(null, 5, 21.25, 3, 7.08),
      row('Abu boiler', 4, null, 0, null),
      row('Mesocarp fibre', 2, 11.75, 1, 11.75),
    ],
    by_source_station: [
      row('Boiler', 6, 18.5, 2, 9.25),
      row(null, 5, 12.5, 2, 6.25),
      row('Pressing', 1, 6.5, 1, 6.5),
    ],
    by_disposal_site: [
      row(null, 5, 20.5, 3, 6.83),
      row('Landfill internal', 3, 11.5, 1, 11.5),
      row('Kebun blok C', 4, 5.5, 1, 5.5),
    ],
    by_purpose_end_use: [
      row('Pupuk organik', 2, 9.5, 1, 9.5),
      row(null, 5, 15.5, 2, 7.75),
      row('Penimbunan', 4, 12.5, 1, 12.5),
      row('Bahan bakar boiler', 1, 0.5, 1, 0.5),
    ],
  }
}

const RECAP_TESTIDS = [
  'swd-recap-waste-type',
  'swd-recap-source-station',
  'swd-recap-disposal-site',
  'swd-recap-purpose-end-use',
]

const ALL_RECAP_TESTIDS = [...RECAP_TESTIDS, 'swd-daily-recap']

function dailyRows(): Array<Record<string, unknown>> {
  const counts: Record<string, number> = {}

  LOADED_DAYS.forEach((date, index) => {
    counts[date] = [3, 1, 2, 1, 1, 1, 1, 1, 1][index]
  })

  return DAILY_ORDER.map((date, index) => ({
    date,
    load_count: counts[date],
    net_weight_total: index % 3 === 1 ? null : 3.5 + index,
    net_weight_load_count: index % 3 === 1 ? 0 : 1,
    net_weight_avg: index % 3 === 1 ? null : 3.5 + index,
  }))
}

/**
 * Payload /summary LENGKAP. ANGKANYA SENGAJA TIDAK KONSISTEN SECARA
 * ARITMETIKA — net_weight_avg 9.9 padahal 37.5/5 = 7,5; daily_total
 * net_weight_total 55.0 yang bukan jumlah kolomnya; completeness.percent 41.3
 * padahal 4/11 = 36,36… Klien yang menghitung PASTI gagal. JANGAN
 * "merapikan" angka-angka ini.
 */
function makeSummary(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    business_unit: { id: 'bu-1', name: 'Mill Sungai Rambutan' },
    production_line: { id: LINE_1.id, name: LINE_1.name, code: LINE_1.code },
    period: {
      id: PERIOD.id,
      name: PERIOD.name,
      start_date: PERIOD.start_date,
      end_date: PERIOD.end_date,
      status: PERIOD.status,
    },
    loads: {
      load_count: 12,
      net_weight_total: 37.5,
      net_weight_avg: 9.9,
      net_weight_load_count: 5,
      missing_net_weight_load_count: 7,
    },
    ...recapsOfTwelve(),
    compliance: { missing_gate_pass_count: 0, missing_seal_count: 3, _note: NOTE_COMPLIANCE },
    draft_load_count: 4,
    undated_load_count: 0,
    daily: dailyRows(),
    daily_total: {
      load_count: 12,
      net_weight_total: 55.0,
      net_weight_load_count: 3,
      net_weight_avg: 7.25,
      _note: NOTE_DAILY_TOTAL,
    },
    completeness: { days_in_period: 30, days_with_load: 4, days_counted: 11, percent: 41.3 },
    text_entries: {
      findings: [{ date: LOADED_DAYS[0], value: 'Ceceran limbah di jalur keluar gerbang timbang.' }],
      remarks: [{ date: LOADED_DAYS[0], value: 'Kendaraan menunggu 40 menit di pos keamanan.' }],
    },
    _note_no_hourly: NOTE_NO_HOURLY,
    _note_shift_not_published: NOTE_SHIFT_NOT_PUBLISHED,
    _note_absences: NOTE_ABSENCES,
    ...overrides,
  }
}

function emptyPeriodSummary(): Record<string, unknown> {
  return makeSummary({
    loads: {
      load_count: 0,
      net_weight_total: null,
      net_weight_avg: null,
      net_weight_load_count: 0,
      missing_net_weight_load_count: 0,
    },
    by_waste_type: [],
    by_source_station: [],
    by_disposal_site: [],
    by_purpose_end_use: [],
    compliance: { missing_gate_pass_count: 0, missing_seal_count: 0, _note: NOTE_COMPLIANCE },
    draft_load_count: 0,
    daily: [],
    daily_total: {
      load_count: 0,
      net_weight_total: null,
      net_weight_load_count: 0,
      net_weight_avg: null,
      _note: NOTE_DAILY_TOTAL,
    },
    text_entries: { findings: [], remarks: [] },
  })
}

/* ------------------------------------------------------------------ */
/* Stub jaringan                                                       */
/* ------------------------------------------------------------------ */

interface ApiState {
  periods: unknown[]
  businessUnits: unknown[]
  businessUnitsStatus: number
  summary: Record<string, unknown>
  summaryStatus: number
  summaryMessage: string
  /** true = permintaan ringkasan gagal di tingkat JARINGAN (tanpa status). */
  summaryAbort: boolean
  /** Sekali gagal, lalu sukses — dipakai test Coba Lagi. */
  summaryAbortOnce: boolean
  /** Line yang ditolak server (403) — line milik mill lain. */
  rejectedLineId: string | null
  exportStatus: number
  /**
   * Tundaan ms sebelum respons /export diselesaikan. WAJIB bagi test klik
   * ganda: tanpa tundaan stub menjawab seketika, `exporting` sudah kembali
   * false sebelum ketukan kedua mendarat, dan dua permintaan terkirim — bukan
   * karena penjagaannya gagal, melainkan karena tidak sekali pun diuji.
   */
  exportDelayMs: number
  productionLines: unknown[]
  productionLinesAbort: boolean
  hits: { periods: number; summary: number; export: number; units: number; lines: number }
  /** Seluruh URL yang benar-benar dikirim halaman ke endpoint laporan. */
  urls: string[]
  lineUrls: string[]
}

async function stubApi(page: Page, overrides: Partial<ApiState> = {}): Promise<ApiState> {
  const state: ApiState = {
    periods: [PERIOD, PERIOD_CLOSED],
    businessUnits: BUSINESS_UNITS,
    businessUnitsStatus: 403,
    summary: makeSummary(),
    summaryStatus: 200,
    summaryMessage: 'Permintaan ditolak.',
    summaryAbort: false,
    summaryAbortOnce: false,
    rejectedLineId: null,
    exportStatus: 200,
    exportDelayMs: 0,
    productionLines: [LINE_1, LINE_2],
    productionLinesAbort: false,
    hits: { periods: 0, summary: 0, export: 0, units: 0, lines: 0 },
    urls: [],
    lineUrls: [],
    ...overrides,
  }

  // Daftar Production Line — endpoint DI LUAR prefiks laporan (dipakai ULANG
  // verbatim dari screen-135), jadi rutenya sendiri. Tanpa stub ini
  // permintaan menembus ke backend sungguhan di :8000 dan hasil test
  // bergantung pada isi DATABASE DEV.
  await page.route('**/api/production-lines/options-for-report*', async (route) => {
    if (route.request().method() === 'OPTIONS') {
      await route.fulfill({ status: 204, headers: CORS_HEADERS, body: '' })

      return
    }

    state.hits.lines += 1
    state.lineUrls.push(route.request().url())

    if (state.productionLinesAbort) {
      await route.abort('failed')

      return
    }

    await route.fulfill({
      status: 200,
      headers: CORS_HEADERS,
      json: { data: state.productionLines },
    })
  })

  await page.route('**/api/solid-waste-disposal-reports/**', async (route) => {
    const request = route.request()
    const url = request.url()

    if (request.method() === 'OPTIONS') {
      await route.fulfill({ status: 204, headers: CORS_HEADERS, body: '' })

      return
    }

    state.urls.push(url)

    if (url.includes('/business-units/options')) {
      state.hits.units += 1

      // ADMIN SAJA; untuk ketiga peran lain server menjawab 403. Stub ini ada
      // JUSTRU supaya pemanggilan yang tak diinginkan TERHITUNG alih-alih
      // diam-diam menembus ke backend dev.
      if (state.businessUnitsStatus !== 200) {
        await route.fulfill({
          status: state.businessUnitsStatus,
          headers: CORS_HEADERS,
          json: { message: 'Anda tidak memiliki akses untuk aksi ini.' },
        })

        return
      }

      await route.fulfill({
        status: 200,
        headers: CORS_HEADERS,
        json: { data: state.businessUnits },
      })

      return
    }

    if (url.includes('/periods')) {
      state.hits.periods += 1

      if (state.summaryStatus === 422 && state.periods.length === 0) {
        await route.fulfill({
          status: 422,
          headers: CORS_HEADERS,
          json: { message: state.summaryMessage },
        })

        return
      }

      await route.fulfill({ status: 200, headers: CORS_HEADERS, json: { data: state.periods } })

      return
    }

    if (url.includes('/export')) {
      state.hits.export += 1

      if (state.exportDelayMs > 0) {
        await new Promise((resolveDelay) => setTimeout(resolveDelay, state.exportDelayMs))
      }

      if (state.exportStatus !== 200) {
        await route.fulfill({
          status: state.exportStatus,
          headers: CORS_HEADERS,
          json: { message: 'Ekspor melebihi 50.000 baris muatan.' },
        })

        return
      }

      await route.fulfill({
        status: 200,
        headers: { ...CORS_HEADERS, 'content-type': 'text/csv' },
        body:
          `${EXPORT_HEADER.join(',')}\n`
          + `${PERIOD.name},Mill Sungai Rambutan,${LINE_1.name},${PERIOD_START},open,`
          + `${LOADED_DAYS[0]},Pagi,WB-0001,BG 1234 XY,Budi,Abu boiler,Boiler,`
          + `12.5,8.0,4.5,Landfill internal,Penimbunan,GP-001,SL-001,op-1,,\n`
          + `${PERIOD.name},Mill Sungai Rambutan,${LINE_1.name},${PERIOD_START},open,`
          + `${LOADED_DAYS[1]},Malam,WB-0002,BG 5678 ZZ,Andi,Mesocarp fibre,Pressing,`
          + `,,,Kebun blok C,Pupuk organik,,,op-2,,\n`,
      })

      return
    }

    if (url.includes('/summary')) {
      state.hits.summary += 1

      if (state.summaryAbort || (state.summaryAbortOnce && state.hits.summary === 1)) {
        await route.abort('failed')

        return
      }

      const query = new URL(url).searchParams
      const requestedLineId = query.get('production_line_id')

      if (state.rejectedLineId !== null && requestedLineId === state.rejectedLineId) {
        await route.fulfill({
          status: 403,
          headers: CORS_HEADERS,
          json: { message: 'Anda tidak memiliki akses untuk aksi ini.' },
        })

        return
      }

      if (state.summaryStatus !== 200) {
        await route.fulfill({
          status: state.summaryStatus,
          headers: CORS_HEADERS,
          json: { message: state.summaryMessage },
        })

        return
      }

      // Blok production_line dan period DIBENTUK DARI PARAMETER yang diminta,
      // bukan dipaku: layar mendahulukan nama dari SERVER atas nama dari
      // daftarnya sendiri, jadi stub yang mengabaikan parameternya akan
      // membuat setiap asersi "cakupan mana yang berlaku" membaca jawaban yang
      // salah — dan selalu hijau.
      const requestedLine = (state.productionLines as Array<{ id: string; name: string }>).find(
        (line) => line.id === requestedLineId,
      )
      const requestedPeriod = (
        state.periods as Array<{
          id: string
          name: string
          start_date: string
          end_date: string
          status: string
        }>
      ).find((period) => period.id === query.get('period_id'))

      const body: Record<string, unknown> = { ...state.summary }

      if (requestedLine) {
        body.production_line = { id: requestedLine.id, name: requestedLine.name }
      }

      if (requestedPeriod) {
        body.period = {
          id: requestedPeriod.id,
          name: requestedPeriod.name,
          start_date: requestedPeriod.start_date,
          end_date: requestedPeriod.end_date,
          status: requestedPeriod.status,
        }
      }

      // /summary menjawab JSON TANPA pembungkus `data` — bentuk controller
      // yang sungguhan.
      await route.fulfill({ status: 200, headers: CORS_HEADERS, json: body })

      return
    }

    await route.fulfill({ status: 404, headers: CORS_HEADERS, json: { message: 'not stubbed' } })
  })

  return state
}

/* ------------------------------------------------------------------ */
/* Bantuan navigasi                                                    */
/* ------------------------------------------------------------------ */

async function openReport(page: Page, path: string = ROUTE): Promise<void> {
  await page.goto(path)
  await expect(page.getByTestId('laporan-solid-waste-disposal-mobile')).toBeVisible()
}

/** MEMILIH LINE ITU LANGKAH TERSENDIRI — layar ini tidak memilihkannya. */
async function pickLine(page: Page, lineId = LINE_1.id): Promise<void> {
  await expect(page.getByTestId('production-line-select')).toBeVisible()
  await page.getByTestId('production-line-select').selectOption(lineId)
}

/** DAN PERIODE PUN TIDAK PERNAH DIPILIHKAN. */
async function pickPeriod(page: Page, periodId = PERIOD.id): Promise<void> {
  await expect(page.getByTestId('period-select')).toBeVisible()
  await page.getByTestId('period-select').selectOption(periodId)
}

/** Alur baku: buka, pilih line, pilih periode, tunggu kelima kartu angka. */
async function openFullReport(page: Page, periodId = PERIOD.id): Promise<void> {
  await openReport(page)
  await pickLine(page)
  await pickPeriod(page, periodId)
  await expect(page.getByTestId('period-meta')).toBeVisible()
  await expect(page.locator('[data-testid^="swd-kpi-"]')).toHaveCount(5)
}

/**
 * Alamat dibandingkan atas PATHNAME yang sudah terurai, bukan atas pola glob:
 * dengan baseURL terpasang, toHaveURL('**\/login') tidak pernah cocok dan
 * test-nya "lulus" tanpa pernah menguji apa pun.
 */
async function expectPathname(page: Page, expected: string): Promise<void> {
  await page.waitForURL((url) => url.pathname === expected)
  expect(new URL(page.url()).pathname).toBe(expected)
}

function recapToggle(page: Page, recapTestId: string) {
  return page.getByTestId(recapTestId).getByTestId('collapsible-section-toggle')
}

function recapBody(page: Page, recapTestId: string) {
  return page.getByTestId(recapTestId).getByTestId('collapsible-section-body')
}

/** KELIMA rekap dibuka — isinya baru benar-benar TERLIHAT sesudah ini. */
async function openAllRecaps(page: Page): Promise<void> {
  // TEPAT LIMA kontrol lipat di seluruh halaman: kelima kartu angka dan blok
  // kepatuhan/kelengkapan TIDAK punya satu pun.
  await expect(page.getByTestId('collapsible-section-toggle')).toHaveCount(5)

  for (const testId of ALL_RECAP_TESTIDS) {
    await recapToggle(page, testId).click()
    await expect(recapBody(page, testId)).toBeVisible()
  }
}

/**
 * "BELUM ADA YANG DIPILIH" DIBACA DARI OPSI TERPILIH, BUKAN DARI .value.
 *
 * Vue mengikat `:value="null"` pada opsi placeholder lewat properti
 * internalnya dan TIDAK menuliskan atribut `value`, sehingga `select.value`
 * di browser jatuh ke TEKS opsi itu — `toHaveValue('')` karena itu memerah
 * pada keadaan yang justru benar.
 */
async function expectNothingSelected(
  page: Page,
  testId: string,
  placeholder: string,
): Promise<void> {
  await expect(page.getByTestId(testId).locator('option:checked')).toHaveText(placeholder)
}

/* ------------------------------------------------------------------ */
/* Bantuan asersi khas ponsel — DIUKUR, bukan disimpulkan              */
/* ------------------------------------------------------------------ */

async function expectNoHorizontalPageScroll(page: Page): Promise<void> {
  const overflow = await page.evaluate(() => {
    const doc = document.scrollingElement ?? document.documentElement

    return {
      scrollWidth: doc.scrollWidth,
      clientWidth: doc.clientWidth,
      bodyScrollWidth: document.body.scrollWidth,
    }
  })

  // Toleransi 1px untuk pembulatan subpiksel.
  expect(overflow.scrollWidth).toBeLessThanOrEqual(overflow.clientWidth + 1)
  expect(overflow.bodyScrollWidth).toBeLessThanOrEqual(overflow.clientWidth + 1)
}

/** Satu kolom: tidak ada dua kartu angka yang berdampingan mendatar. */
async function expectSingleColumn(page: Page): Promise<void> {
  const cards = page.locator('[data-testid^="swd-card-"]')

  await expect(cards).toHaveCount(5)
  await expect(cards.first()).toBeVisible()

  const boxes = await cards.evaluateAll((elements) =>
    elements.map((element) => {
      const rect = element.getBoundingClientRect()

      return { top: rect.top, bottom: rect.bottom }
    }),
  )

  for (let index = 1; index < boxes.length; index += 1) {
    expect(boxes[index].top).toBeGreaterThanOrEqual(boxes[index - 1].bottom - 1)
  }
}

/**
 * Tabel rekap BERTUMPUK MENJADI KARTU di dalam kartunya, bukan menggulir
 * mendatar — DIUKUR DARI BENTUK YANG TERLIHAT: tiap sel menempati barisnya
 * sendiri (kotaknya TURUN, bukan berderet), tiap sel membawa data-label yang
 * disalin direktif dari <th>-nya, dan tidak ada isi yang disembunyikan di
 * balik gulir mendatar.
 */
async function expectRecapStacksIntoCards(page: Page, tableTestId: string): Promise<void> {
  await expect(page.getByTestId(tableTestId)).toBeVisible()

  const result = await page.getByTestId(tableTestId).evaluate((element) => {
    const row = element.querySelector('tbody tr')
    const cells = row ? Array.from(row.children) : []
    const tops = new Set(cells.map((cell) => Math.round(cell.getBoundingClientRect().top)))
    const wrapper = element.parentElement as HTMLElement

    return {
      scrollWidth: element.scrollWidth,
      clientWidth: element.clientWidth,
      cells: cells.length,
      barisTegak: tops.size,
      berlabel: cells.filter((cell) => (cell.getAttribute('data-label') ?? '') !== '').length,
      wrapperClass: wrapper.className,
      wrapperOverflowX: getComputedStyle(wrapper).overflowX,
      wrapperScrollWidth: wrapper.scrollWidth,
      wrapperClientWidth: wrapper.clientWidth,
    }
  })

  expect(result.cells, `${tableTestId} tidak punya satu baris isi pun untuk dinilai`).toBeGreaterThan(0)
  // Tidak ada isi yang tersembunyi di balik gulir mendatar.
  expect(result.scrollWidth).toBeLessThanOrEqual(result.clientWidth + 1)
  // Setiap sel menempati barisnya sendiri, dan membawa label kolomnya.
  expect(result.barisTegak).toBe(result.cells)
  expect(result.berlabel).toBe(result.cells)

  /**
   * DAN PEMBUNGKUSNYA MELAPORKAN overflow-x 'visible' PADA 390px.
   *
   * CATATAN KEKHUSUSAN SELEKTOR, karena ini mudah salah dibaca — dan karena
   * layar ini BERBEDA dari screen-161: LaporanProcessWaterView.vue menimpa
   * `.detail-table-wrap` di blok <style scoped>-nya menjadi `overflow-x:
   * auto`, yang kekhususannya lebih tinggi dan menang di 390px. Layar INI
   * tidak menimpanya sama sekali, jadi yang berlaku adalah aturan
   * src/assets/stacked-table.css pada @media (max-width: 640px):
   * `overflow-x: visible` — disengaja, karena membiarkan `auto` di sini akan
   * MENYEMBUNYIKAN isi yang melebar alih-alih memaksanya terlihat. Kelas
   * tanpa definisi akan menjawab 'visible' juga, jadi nama kelasnya diasersi
   * terpisah dan bentuk bertumpuknya DIUKUR di atas.
   */
  expect(result.wrapperClass).toContain('detail-table-wrap')
  expect(result.wrapperOverflowX).toBe('visible')
  expect(result.wrapperScrollWidth).toBeLessThanOrEqual(result.wrapperClientWidth + 1)
}

/** Prosa panjang TERBACA UTUH: tidak terpotong, tidak di-clamp. */
async function expectProseFullyReadable(page: Page, testId: string): Promise<void> {
  await expect(page.getByTestId(testId)).toBeVisible()

  const shape = await page.getByTestId(testId).evaluate((element) => {
    const style = getComputedStyle(element)

    return {
      scrollHeight: element.scrollHeight,
      clientHeight: element.clientHeight,
      scrollWidth: element.scrollWidth,
      clientWidth: element.clientWidth,
      textOverflow: style.textOverflow,
      lineClamp: style.webkitLineClamp,
      overflowY: style.overflowY,
    }
  })

  expect(shape.scrollHeight).toBeLessThanOrEqual(shape.clientHeight + 1)
  expect(shape.scrollWidth).toBeLessThanOrEqual(shape.clientWidth + 1)
  expect(shape.textOverflow).not.toBe('ellipsis')
  expect(['none', 'auto', '', 'normal']).toContain(shape.lineClamp)
}

/**
 * Setiap kelas CSS yang dipakai MARKUP LAYAR INI punya definisi di CSS HASIL
 * COMPILE — dibaca dari document.styleSheets, bukan dari berkas sumber.
 *
 * DILINGKUP KE SUBPOHON akar layar ini: kerangka aplikasi (App.vue, jam
 * mengambang, bubble AI) membawa kelasnya sendiri yang bukan tanggung jawab
 * layar ini.
 */
async function expectEveryClassDefined(page: Page): Promise<void> {
  await expect(page.getByTestId('laporan-solid-waste-disposal-mobile')).toBeVisible()

  const result = await page.evaluate(() => {
    const defined = new Set<string>()

    const walk = (rules: CSSRuleList): void => {
      for (const rule of Array.from(rules)) {
        const styleRule = rule as CSSStyleRule & { cssRules?: CSSRuleList }

        if (typeof styleRule.selectorText === 'string') {
          for (const match of styleRule.selectorText.matchAll(/\.(-?[_a-zA-Z]+[_a-zA-Z0-9-]*)/g)) {
            defined.add(match[1])
          }
        }

        if (styleRule.cssRules) {
          walk(styleRule.cssRules)
        }
      }
    }

    for (const sheet of Array.from(document.styleSheets)) {
      try {
        walk(sheet.cssRules)
      } catch {
        // Stylesheet lintas asal tidak dapat dibaca — tidak ada di dev server
        // ini, tetapi dilewati alih-alih melempar.
      }
    }

    const root = document.querySelector('[data-testid="laporan-solid-waste-disposal-mobile"]')
    const used = new Set<string>()

    if (root) {
      for (const element of [root, ...Array.from(root.querySelectorAll('[class]'))]) {
        // getAttribute, BUKAN .className: pada elemen SVG nilainya
        // SVGAnimatedString, dan .toString()-nya '[object SVGAnimatedString]'.
        for (const name of (element.getAttribute('class') ?? '').split(/\s+/)) {
          if (name !== '') {
            used.add(name)
          }
        }
      }
    }

    return {
      used: used.size,
      definedCount: defined.size,
      missing: [...used].filter((name) => !defined.has(name)),
    }
  })

  expect(result.definedCount).toBeGreaterThan(50)
  expect(result.used).toBeGreaterThan(20)
  expect(result.missing, `kelas tanpa definisi: ${result.missing.join(', ')}`).toEqual([])
}

function numberOf(text: string): number {
  return Number(text.replace(/\./g, '').replace(',', '.'))
}

/* ================================================================== */
/* JALUR SUKSES, ANGKA, DAN BENTUK                                     */
/* ================================================================== */

test.describe('Laporan Solid Waste Disposal Mobile (screen-163)', () => {
  // Layar ponsel sungguhan — lihat catatan VIEWPORT pada docblock berkas.
  test.use({ viewport: PHONE })

  test.beforeEach(async ({ page }) => {
    await login(page, USERS.operator)
  })

  test('skenario 1: seluruh blok laporan terlihat pada 390x844, angka = angka fixture, nol galat console', async ({
    page,
  }) => {
    const consoleErrors: string[] = []
    const pageErrors: string[] = []

    page.on('console', (message) => {
      if (message.type() === 'error') {
        consoleErrors.push(message.text())
      }
    })
    page.on('pageerror', (error) => pageErrors.push(error.message))

    await stubApi(page)
    await openFullReport(page)

    // PETA testid → medan payload. Tidak satu pun dapat diturunkan dari yang
    // lain, jadi klien yang menghitung akan menyimpang di salah satunya.
    const expected: Record<string, string> = {
      'swd-kpi-load-count': '12',
      'swd-kpi-net-weight-total': '37,5',
      'swd-kpi-net-weight-avg': '9,9',
      'swd-kpi-net-weight-load-count': '5',
      'swd-kpi-missing-net-weight': '7',
      'swd-draft-load-count': '4',
      'swd-coverage-percent': '41,3',
      'swd-coverage-denominator': '11',
      'swd-compliance-missing-gate-pass': '0',
      'swd-compliance-missing-seal': '3',
    }

    for (const [testId, value] of Object.entries(expected)) {
      await expect(page.getByTestId(testId), testId).toHaveText(value)
    }

    await expect(page.getByTestId('period-status-badge')).toHaveText('Terbuka')
    await expect(page.getByTestId('period-meta')).toContainText(PERIOD.name)

    // SELURUH blok laporan ADA, dan rekapnya terlihat sesudah dibuka.
    for (const testId of [
      'swd-loads',
      'swd-compliance',
      'swd-coverage',
      'swd-findings',
      'swd-remarks',
      'swd-absences',
      'swd-export-csv',
    ]) {
      await expect(page.getByTestId(testId), testId).toBeVisible()
    }

    await openAllRecaps(page)

    for (const testId of RECAP_TESTIDS) {
      await expect(page.getByTestId(`${testId}-table`), testId).toBeVisible()
    }

    await expect(page.getByTestId('swd-daily-table')).toBeVisible()
    await expect(page.getByTestId('swd-daily-total-load-count')).toHaveText('12')
    await expect(page.getByTestId('swd-daily-total-net-weight-total')).toHaveText('55,0')

    await expectSingleColumn(page)
    await expectNoHorizontalPageScroll(page)

    expect(pageErrors, pageErrors.join(' | ')).toHaveLength(0)
    expect(consoleErrors, consoleErrors.join(' | ')).toHaveLength(0)
  })

  test('skenario 10: jumlah muatan 12, dan penjumlahan baris SETIAP rekap juga 12', async ({ page }) => {
    await stubApi(page)
    await openFullReport(page)
    await openAllRecaps(page)

    await expect(page.getByTestId('swd-kpi-load-count')).toHaveText('12')

    for (const testId of RECAP_TESTIDS) {
      const counts = page.locator(`[data-testid^="${testId}-load-count-"]`)

      // toHaveCount menunggu sendiri — allInnerTexts() di bawah karena itu
      // tidak pernah membaca halaman setengah render.
      await expect(counts.first()).toBeVisible()

      const values = await counts.allInnerTexts()

      expect(
        values.map((value) => numberOf(value.trim())).reduce((total, value) => total + value, 0),
        testId,
      ).toBe(12)
    }
  })

  test('skenario 11: berat bersih dibaca apa adanya — 9,9 dan BUKAN 7,5', async ({ page }) => {
    await stubApi(page)
    await openFullReport(page)

    await expect(page.getByTestId('swd-kpi-net-weight-total')).toHaveText('37,5')
    await expect(page.getByTestId('swd-kpi-net-weight-avg')).toHaveText('9,9')
    await expect(page.getByTestId('swd-kpi-net-weight-avg')).not.toHaveText('7,5')
  })

  test('skenario 12: seluruh muatan belum ditimbang — kedua kartu berat menampilkan garis', async ({
    page,
  }) => {
    await stubApi(page, {
      summary: makeSummary({
        loads: {
          load_count: 8,
          net_weight_total: null,
          net_weight_avg: null,
          net_weight_load_count: 0,
          missing_net_weight_load_count: 8,
        },
      }),
    })
    await openFullReport(page)

    await expect(page.getByTestId('swd-kpi-net-weight-total')).toHaveText('—')
    await expect(page.getByTestId('swd-kpi-net-weight-avg')).toHaveText('—')
    await expect(page.getByTestId('swd-kpi-net-weight-load-count')).toHaveText('0')
    await expect(page.getByTestId('swd-kpi-missing-net-weight')).toHaveText('8')
    await expect(page.getByTestId('swd-kpi-load-count')).toHaveText('8')

    // NOL elemen '0,00 MT' di blok berat — dan bentuk itu memang mustahil:
    // satuannya ada di pil SEBELAHNYA, dan pil itu tidak dirender ketika
    // nilainya null.
    for (const card of ['swd-card-net-weight-total', 'swd-card-net-weight-avg']) {
      await expect(page.getByTestId(card).locator('.metric-unit-pill'), card).toHaveCount(0)
    }

    await expect(page.getByTestId('swd-card-missing-net-weight')).toContainText(
      'penimbangan belum selesai',
    )
  })

  test('skenario 13: penyebut rata-rata terlihat DI DALAM kartu rata-rata yang sama', async ({
    page,
  }) => {
    await stubApi(page, {
      summary: makeSummary({
        loads: {
          load_count: 10,
          net_weight_total: 60,
          net_weight_avg: 9.9,
          net_weight_load_count: 6,
          missing_net_weight_load_count: 4,
        },
      }),
    })
    await openFullReport(page)

    // LOCATOR BERSARANG di bawah testid kartunya — bukan kedekatan teks.
    const avgCard = page.getByTestId('swd-card-net-weight-avg')

    await expect(avgCard.getByTestId('swd-kpi-net-weight-avg')).toHaveText('9,9')
    await expect(avgCard.getByTestId('swd-kpi-net-weight-load-count')).toHaveText('6')

    // Kartu penimbangan-belum-selesai TERPISAH, dengan angka 4.
    await expect(avgCard.getByTestId('swd-card-missing-net-weight')).toHaveCount(0)
    await expect(page.getByTestId('swd-kpi-missing-net-weight')).toHaveText('4')
    await expect(page.getByTestId('swd-kpi-load-count')).toHaveText('10')
  })

  test('skenario 14: rekap tujuan penggunaan akhir menampilkan nilai apa adanya, nol ember', async ({
    page,
  }) => {
    const freeText = [
      row('Dibakar di insinerator pihak ketiga', 3, 8.5, 2, 4.25),
      row('Dijual ke pengepul', 4, 12.5, 2, 6.25),
      row('Ditimbun di lahan sendiri', 4, 6.5, 1, 6.5),
      row('Dipakai ulang sebagai bahan bakar', 1, 2.5, 1, 2.5),
    ]

    await stubApi(page, { summary: makeSummary({ by_purpose_end_use: freeText }) })
    await openFullReport(page)
    await openAllRecaps(page)

    const values = page.locator('[data-testid^="swd-recap-purpose-end-use-value-"]')

    await expect(values.first()).toBeVisible()
    await expect(values).toHaveCount(freeText.length)
    expect((await values.allInnerTexts()).map((value) => value.trim())).toEqual(
      freeText.map((item) => item.value),
    )

    // Baris 'dibuang' / 'dimanfaatkan' / 'lainnya' DI DALAM kartu rekap: nol.
    for (const bucket of ['dibuang', 'dimanfaatkan', 'lainnya']) {
      await expect(
        page.getByTestId('swd-recap-purpose-end-use').getByText(new RegExp(`^${bucket}$`, 'i')),
        bucket,
      ).toHaveCount(0)
    }
  })

  test('skenario 15: baris "tidak tercatat" terlihat pada KEEMPAT rekap dan ikut dijumlahkan', async ({
    page,
  }) => {
    const caseSensitive = [
      row('Abu boiler', 4, 10.5, 2, 5.25),
      row('abu boiler', 3, 7.5, 1, 7.5),
      row(null, 4, 6.5, 1, 6.5),
      row('Serat tunggal', 1, 1.5, 1, 1.5),
    ]

    await stubApi(page, {
      summary: makeSummary({
        by_waste_type: caseSensitive,
        by_source_station: caseSensitive,
        by_disposal_site: caseSensitive,
        by_purpose_end_use: caseSensitive,
      }),
    })
    await openFullReport(page)
    await openAllRecaps(page)

    for (const testId of RECAP_TESTIDS) {
      const values = page.locator(`[data-testid^="${testId}-value-"]`)

      await expect(values.first()).toBeVisible()

      const texts = (await values.allInnerTexts()).map((value) => value.trim())

      expect(texts.filter((value) => value === 'tidak tercatat'), testId).toHaveLength(1)
      // KEDUA EJAAN tampil terpisah.
      expect(texts, testId).toContain('Abu boiler')
      expect(texts, testId).toContain('abu boiler')
      expect(texts.length, testId).toBe(caseSensitive.length)
    }
  })

  test('skenario 16: urutan baris yang terbaca SAMA PERSIS dengan urutan payload', async ({
    page,
  }) => {
    const ordered = [
      row('Zpalm shell', 1, 4.5, 1, 4.5),
      // Bertonase BESAR, dan tetap di posisi KEDUA sesuai payload.
      row(null, 9, 61.25, 3, 20.41),
      row('Abu boiler', 4, null, 0, null),
      row('Mesocarp fibre', 2, 11.75, 1, 11.75),
    ]

    await stubApi(page, { summary: makeSummary({ by_waste_type: ordered }) })
    await openFullReport(page)
    await openAllRecaps(page)

    const values = page.locator('[data-testid^="swd-recap-waste-type-value-"]')

    await expect(values.first()).toBeVisible()
    expect((await values.allInnerTexts()).map((value) => value.trim())).toEqual([
      'Zpalm shell',
      'tidak tercatat',
      'Abu boiler',
      'Mesocarp fibre',
    ])
  })

  test('skenario 17: rekap berekor panjang — setiap nilai punya barisnya sendiri, nol "lainnya"', async ({
    page,
  }) => {
    const longTail = Array.from({ length: 15 }, (_unused, index) =>
      row(`Nilai ${index + 1}`, index < 8 ? 1 : index, index % 4 === 0 ? null : 1.5 + index, 1, 1.5),
    )

    await stubApi(page, { summary: makeSummary({ by_waste_type: longTail }) })
    await openFullReport(page)
    await openAllRecaps(page)

    const rows = page.locator('[data-testid^="swd-recap-waste-type-row-"]')

    await expect(rows.first()).toBeVisible()
    await expect(rows).toHaveCount(15)
    await expect(
      page.getByTestId('swd-recap-waste-type').getByText(/^lainnya$/i),
    ).toHaveCount(0)
  })

  test('skenario 18: penghitung kepatuhan pada KEDUA keadaan, nol pun tetap terlihat', async ({
    page,
  }) => {
    const state = await stubApi(page, {
      summary: makeSummary({
        compliance: { missing_gate_pass_count: 12, missing_seal_count: 12, _note: NOTE_COMPLIANCE },
      }),
    })

    await openFullReport(page)

    await expect(page.getByTestId('swd-compliance-missing-gate-pass')).toHaveText('12')
    await expect(page.getByTestId('swd-compliance-missing-seal')).toHaveText('12')
    await expect(page.getByTestId('swd-compliance-note')).toBeVisible()
    await expectProseFullyReadable(page, 'swd-compliance-note')

    // Periode kedua: keduanya NOL dan TETAP terlihat.
    state.summary = makeSummary({
      compliance: { missing_gate_pass_count: 0, missing_seal_count: 0, _note: NOTE_COMPLIANCE },
    })

    await page.getByTestId('period-select').selectOption(PERIOD_CLOSED.id)

    await expect(page.getByTestId('swd-compliance-missing-gate-pass')).toHaveText('0')
    await expect(page.getByTestId('swd-compliance-missing-seal')).toHaveText('0')
    await expect(page.getByTestId('swd-compliance-note')).toBeVisible()
    // Nol grafik, nol rata-rata di dalam blok kepatuhan.
    await expect(page.getByTestId('swd-compliance').locator('canvas')).toHaveCount(0)
    await expect(page.locator('[data-testid^="swd-compliance-chart"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="swd-compliance-avg"]')).toHaveCount(0)
  })

  test('skenario 19: dua bagian kejadian terpisah, dan periode kosong menampilkan keterangannya', async ({
    page,
  }) => {
    const state = await stubApi(page)

    await openFullReport(page)

    await expect(page.getByTestId('swd-findings-row-0')).toBeVisible()
    await expect(page.getByTestId('swd-remarks-row-0')).toBeVisible()
    await expect(page.getByTestId('swd-findings-row-0')).toContainText('Ceceran limbah')
    await expect(page.getByTestId('swd-remarks-row-0')).toContainText('Kendaraan menunggu')
    // Satu muatan yang mengisi kedua kolom muncul di KEDUA daftar, dengan
    // tanggal yang sama.
    await expect(page.getByTestId('swd-findings-row-0')).toHaveAttribute('data-date', LOADED_DAYS[0])
    await expect(page.getByTestId('swd-remarks-row-0')).toHaveAttribute('data-date', LOADED_DAYS[0])

    state.summary = makeSummary({ text_entries: { findings: [], remarks: [] } })

    await page.getByTestId('period-select').selectOption(PERIOD_CLOSED.id)

    await expect(page.getByTestId('swd-findings-empty')).toBeVisible()
    await expect(page.getByTestId('swd-remarks-empty')).toBeVisible()
  })

  test('skenario 20: muatan draft IKUT terhitung, dan jumlahnya dinyatakan', async ({ page }) => {
    await stubApi(page)
    await openFullReport(page)

    await expect(page.getByTestId('swd-kpi-load-count')).toHaveText('12')
    await expect(page.getByTestId('swd-draft-load-count')).toHaveText('4')
    await expect(page.getByTestId('swd-draft-note')).toContainText('IKUT terhitung')
  })

  test('skenario 21: rekap harian — TEPAT sembilan baris bertanggal, beserta baris total', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)
    await openAllRecaps(page)

    const rows = page.locator('[data-testid^="swd-daily-row-"]')

    await expect(rows.first()).toBeVisible()
    await expect(rows).toHaveCount(9)
    await expect(page.getByTestId('swd-daily-total')).toBeVisible()

    // Nol baris bertanggal TANPA muatan: himpunan tanggalnya tepat sembilan
    // tanggal payload.
    const dates = await rows.evaluateAll((elements) =>
      elements.map((element) => (element.getAttribute('data-testid') ?? '').replace('swd-daily-row-', '')),
    )

    expect([...dates].sort()).toEqual([...LOADED_DAYS].sort())
    expect(dates).toEqual(DAILY_ORDER)
  })

  test('skenario 22: periode berjalan lalu periode belum mulai — penyebut dan garisnya', async ({
    page,
  }) => {
    const state = await stubApi(page)

    await openFullReport(page)

    await expect(page.getByTestId('swd-coverage-percent')).toHaveText('41,3')
    await expect(page.getByTestId('swd-coverage-denominator')).toHaveText('11')
    await expect(page.getByTestId('swd-coverage-running-note')).toBeVisible()
    await expectProseFullyReadable(page, 'swd-coverage-running-note')

    state.summary = makeSummary({
      completeness: { days_in_period: 30, days_with_load: 0, days_counted: 0, percent: null },
    })

    await page.getByTestId('period-select').selectOption(PERIOD_CLOSED.id)

    await expect(page.getByTestId('swd-coverage-percent')).toHaveText('—')
    await expect(page.getByTestId('swd-coverage-not-started-note')).toBeVisible()
    await expect(page.getByTestId('swd-coverage-running-note')).toHaveCount(0)
    // Nol '0,0%' / '0%' DI DALAM blok kelengkapan.
    await expect(page.getByTestId('swd-coverage').getByText('0,0%', { exact: false })).toHaveCount(0)
  })

  test('skenario 23: nol testid sebaran per jam dan nol judul "per jam", prosanya terbaca UTUH', async ({
    page,
  }) => {
    await stubApi(page, {
      summary: makeSummary({
        // Kunci yang TIDAK PERNAH diterbitkan server, dibawa sengaja.
        hourly: [{ hour: 7, load_count: 2 }],
        busiest_hour: 7,
        empty_hour_count: 22,
      }),
    })
    await openFullReport(page)
    await openAllRecaps(page)

    // ASERSI LEWAT TESTID DAN ROLE — BUKAN lewat pemindaian prosa: layar ini
    // WAJIB merender kalimat yang MEMUAT kata 'jam'.
    await expect(page.locator('[data-testid^="swd-hourly"]')).toHaveCount(0)

    for (const testId of ['swd-busiest-hour', 'swd-empty-hour-count', 'hourly-dispatch', 'hourly-receive']) {
      await expect(page.getByTestId(testId), testId).toHaveCount(0)
    }

    await expect(page.getByRole('heading', { name: /per jam/i })).toHaveCount(0)

    await expect(page.getByTestId('swd-note-no-hourly')).toBeVisible()
    await expect(page.getByTestId('swd-note-no-hourly')).toHaveText(NOTE_NO_HOURLY)
    await expectProseFullyReadable(page, 'swd-note-no-hourly')
    await expect(page.getByTestId('swd-note-absences')).toHaveText(NOTE_ABSENCES)
    await expectProseFullyReadable(page, 'swd-note-absences')
  })

  test('skenario 24: undated 0 → blok tidak ada; undated 3 → blok ada dengan angka 3', async ({
    page,
  }) => {
    const state = await stubApi(page)

    await openFullReport(page)

    await expect(page.getByTestId('swd-kpi-load-count')).toBeVisible()
    await expect(page.getByTestId('swd-undated-load-count')).toHaveCount(0)
    await expect(page.getByTestId('swd-undated-note')).toHaveCount(0)

    state.summary = makeSummary({ undated_load_count: 3 })

    await page.getByTestId('period-select').selectOption(PERIOD_CLOSED.id)

    await expect(page.getByTestId('swd-undated-load-count')).toHaveCount(1)
    await expect(page.getByTestId('swd-undated-load-count')).toHaveText('3')
    await expect(page.getByTestId('swd-undated-note')).toBeVisible()
  })

  test('skenario 25: nol kolom Shift di layar, dan header CSV 22 kolom MEMUAT Shift', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)
    await openAllRecaps(page)

    // NOL kolom Shift di DOM layar — lewat ROLE dan TESTID.
    await expect(page.getByRole('columnheader', { name: /^Shift$/i })).toHaveCount(0)
    await expect(page.locator('[data-testid^="swd-recap-shift"]')).toHaveCount(0)
    await expect(page.getByTestId('swd-shift-column')).toHaveCount(0)
    await expect(page.getByTestId('swd-shift-filter')).toHaveCount(0)
    // Nol tombol xlsx/Excel — lewat ROLE dan TESTID, bukan sapuan prosa:
    // swd-note-shift dan swd-export-scope-note JUSTRU menyebut kata itu.
    await expect(page.getByRole('button', { name: /xlsx|excel/i })).toHaveCount(0)
    await expect(page.getByTestId('swd-export-xlsx')).toHaveCount(0)
    await expect(page.getByTestId('swd-note-shift')).toHaveText(NOTE_SHIFT_NOT_PUBLISHED)

    // DAN BERKASNYA: 22 kolom, termasuk keenam kolom yang tidak muncul di satu
    // angka pun di layar.
    const downloadPromise = page.waitForEvent('download')

    await page.getByTestId('swd-export-csv').click()

    const download = await downloadPromise
    const path = await download.path()

    expect(path).not.toBeNull()

    const header = readFileSync(path as string, 'utf-8').split('\n')[0]

    expect(header.split(',')).toHaveLength(22)

    // SATU NEEDLE PER PEMANGGILAN: toContain(a, b) memperlakukan argumen kedua
    // sebagai NEEDLE KEDUA, bukan sebagai pesan.
    for (const column of [
      'Shift',
      'Weighbridge Ticket No',
      'Vehicle No',
      'Driver Name',
      'Gate Pass No',
      'Security Seal No',
      'Operator ID',
    ]) {
      expect(header, column).toContain(column)
    }

    expect(download.suggestedFilename()).toMatch(/\.csv$/)
  })

  test('skenario 27: tabel BERTUMPUK jadi kartu, halaman tidak bergeser (DIUKUR), lipat/buka tidak memuat ulang', async ({
    page,
  }) => {
    const state = await stubApi(page)

    await openFullReport(page)
    await openAllRecaps(page)

    const requestsBefore = state.hits.summary
    const numbersBefore = await page
      .locator('[data-testid^="swd-kpi-"]')
      .allInnerTexts()

    for (const testId of [...RECAP_TESTIDS, 'swd-daily-recap']) {
      await expectRecapStacksIntoCards(page, testId === 'swd-daily-recap' ? 'swd-daily-table' : `${testId}-table`)
    }

    await expectNoHorizontalPageScroll(page)
    await expectEveryClassDefined(page)

    // MELIPAT dan MEMBUKA kembali: nol permintaan, nol angka berubah.
    for (const testId of ALL_RECAP_TESTIDS) {
      await recapToggle(page, testId).click()
      await expect(recapBody(page, testId)).toBeHidden()
      await recapToggle(page, testId).click()
      await expect(recapBody(page, testId)).toBeVisible()
    }

    expect(state.hits.summary).toBe(requestsBefore)
    expect(await page.locator('[data-testid^="swd-kpi-"]').allInnerTexts()).toEqual(numbersBefore)

    // Dan kedua pengukuran tetap berlaku sesudahnya.
    await expectRecapStacksIntoCards(page, 'swd-daily-table')
    await expectNoHorizontalPageScroll(page)
  })

  test('tabel menjadi TABEL SUNGGUHAN di atas 640px, dan halaman tetap tidak bergeser mendatar', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)
    await openAllRecaps(page)

    // Bentuk kartu dipasang pada @media (max-width: 640px) saja — di jendela
    // lebar pembacanya memang lebih mudah membandingkan baris secara
    // mendatar. Diperiksa DI SINI supaya klaim "bertumpuk di 390px" tidak
    // dibaca sebagai "selalu bertumpuk".
    await page.setViewportSize({ width: 900, height: 844 })
    await expect(page.getByTestId('swd-daily-table')).toBeVisible()

    const shape = await page.getByTestId('swd-daily-table').evaluate((element) => {
      const row = element.querySelector('tbody tr') as HTMLElement
      const cells = Array.from(row.children)
      const tops = new Set(cells.map((cell) => Math.round(cell.getBoundingClientRect().top)))

      return {
        rowDisplay: getComputedStyle(row).display,
        cells: cells.length,
        barisTegak: tops.size,
        scrollWidth: element.scrollWidth,
        clientWidth: element.clientWidth,
      }
    })

    expect(shape.rowDisplay).toBe('table-row')
    // Sel-selnya kini BERDERET pada satu garis, bukan bertumpuk.
    expect(shape.barisTegak).toBe(1)
    expect(shape.cells).toBe(5)
    expect(shape.scrollWidth).toBeLessThanOrEqual(shape.clientWidth + 1)

    await expectNoHorizontalPageScroll(page)
  })

  test('skenario 28: periode TERTUTUP tetap terbaca penuh dan tetap dapat diekspor', async ({
    page,
  }) => {
    await stubApi(page)
    await openReport(page)
    await pickLine(page)
    await pickPeriod(page, PERIOD_CLOSED.id)

    await expect(page.getByTestId('period-status-badge')).toHaveText('Tertutup')
    await expect(page.locator('[data-testid^="swd-kpi-"]')).toHaveCount(5)
    await expect(page.getByTestId('swd-kpi-load-count')).toHaveText('12')
    await expect(page.getByTestId('swd-export-csv')).toBeEnabled()

    const downloadPromise = page.waitForEvent('download')

    await page.getByTestId('swd-export-csv').click()

    const download = await downloadPromise

    expect(download.suggestedFilename()).toMatch(/\.csv$/)
  })

  test('skenario 9: periode tanpa muatan — kerangka penuh dengan keadaan kosong yang menyebut periode dan line', async ({
    page,
  }) => {
    await stubApi(page, { summary: emptyPeriodSummary() })
    await openFullReport(page)

    await expect(page.getByTestId('swd-empty-period-note')).toBeVisible()
    await expect(page.getByTestId('swd-empty-period-note')).toContainText(PERIOD.name)
    await expect(page.getByTestId('swd-empty-period-note')).toContainText(LINE_1.name)

    await expect(page.getByTestId('swd-kpi-net-weight-total')).toHaveText('—')
    await expect(page.getByTestId('swd-kpi-net-weight-avg')).toHaveText('—')
    await expect(page.getByTestId('swd-coverage')).toBeVisible()
    await expect(page.getByTestId('swd-coverage-days-in-period')).toHaveText('30')

    // Nol elemen '0,00 MT' pada blok berat.
    for (const card of ['swd-card-net-weight-total', 'swd-card-net-weight-avg']) {
      await expect(page.getByTestId(card).locator('.metric-unit-pill'), card).toHaveCount(0)
    }
  })
})

/* ================================================================== */
/* PRODUCTION LINE, MILL, EKSPOR, DAN KEADAAN                          */
/* ================================================================== */

test.describe('Laporan Solid Waste Disposal Mobile — cakupan, ekspor, dan keadaan', () => {
  test.use({ viewport: PHONE })

  test('skenario 3: tanpa line — jumlah permintaan /summary TEPAT 0, juga sesudah ganti periode', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    const state = await stubApi(page)

    await openReport(page)

    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()
    await expectNothingSelected(page, 'production-line-select', 'Pilih Production Line')

    await pickPeriod(page, PERIOD.id)
    await page.getByTestId('period-select').selectOption(PERIOD_CLOSED.id)

    // DIUKUR, bukan disimpulkan dari angka yang tidak tampil.
    await expect.poll(() => state.hits.summary).toBe(0)
    await expect(page.locator('[data-testid^="swd-kpi-"]')).toHaveCount(0)
    // Tombol Ekspor CSV tidak ada di DOM.
    await expect(page.getByTestId('swd-export-csv')).toHaveCount(0)
    // Permintaan memilih line TERLIHAT.
    await expect(page.getByTestId('production-line-select')).toBeVisible()
  })

  test('skenario 3: jalur COBA LAGI pun tidak meminta ringkasan selama line kosong', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    const state = await stubApi(page, { productionLinesAbort: true })

    await openReport(page)

    await expect(page.getByTestId('production-line-unavailable')).toBeVisible()
    await page.getByTestId('production-line-retry').click()
    await expect.poll(() => state.hits.lines).toBeGreaterThan(1)
    await expect.poll(() => state.hits.summary).toBe(0)
  })

  test('skenario 2: line yang diingat perangkat dipakai tanpa diminta memilih, dan terbawa ke /summary', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    const userId = await getAuthUserId(page)

    expect(userId).not.toBe('')

    const state = await stubApi(page)

    await page.addInitScript(
      ([key, value]) => {
        window.localStorage.setItem(key, value)
      },
      [rememberedLineKey(userId), LINE_1.id] as const,
    )

    await openReport(page)

    // Terpilih otomatis — nol langkah pengguna.
    await expect(page.getByTestId('production-line-select').locator('option:checked')).toHaveText(
      LINE_1.name,
    )
    await expect(page.getByTestId('production-line-required-hint')).toHaveCount(0)

    // PERIODE TIDAK PERNAH DIPILIHKAN: sampai ia dipilih, nol permintaan.
    await expect.poll(() => state.hits.summary).toBe(0)

    await pickPeriod(page)
    await expect(page.locator('[data-testid^="swd-kpi-"]')).toHaveCount(5)

    expect(new URL(state.urls[state.urls.length - 1]).searchParams.get('production_line_id')).toBe(
      LINE_1.id,
    )

    // Pemilih line tetap dapat dibuka dan diganti.
    await page.getByTestId('production-line-select').selectOption(LINE_2.id)
    await expect(page.getByTestId('production-line-current')).toContainText(LINE_2.name)

    expect(new URL(state.urls[state.urls.length - 1]).searchParams.get('production_line_id')).toBe(
      LINE_2.id,
    )
  })

  test('TIDAK memilihkan line, bahkan ketika mill hanya punya SATU', async ({ page }) => {
    await login(page, USERS.operator)

    const state = await stubApi(page, { productionLines: [LINE_1] })

    await openReport(page)

    await expectNothingSelected(page, 'production-line-select', 'Pilih Production Line')
    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()
    await expect.poll(() => state.hits.summary).toBe(0)

    await pickLine(page)
    await pickPeriod(page)

    await expect(page.locator('[data-testid^="swd-kpi-"]')).toHaveCount(5)
    await expect.poll(() => state.hits.summary).toBe(1)
  })

  test('skenario 4: Storage.prototype.getItem melempar — layar tetap merender dan alur berlanjut', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    const consoleErrors: string[] = []

    page.on('pageerror', (error) => consoleErrors.push(error.message))

    const state = await stubApi(page)

    /**
     * DIGANTI PADA PROTOTYPE, bukan pada instance window.localStorage: itulah
     * jalur yang benar-benar dipakai kode produksi, dan satu-satunya yang
     * dapat dibuat melempar tanpa membuat seluruh halaman gagal boot.
     * Kunci sesi DIKECUALIKAN supaya login yang sudah terjadi tidak ikut
     * hilang — yang diuji adalah pembacaan ingatan LINE, bukan sesi.
     */
    await page.addInitScript(() => {
      const original = Storage.prototype.getItem

      Storage.prototype.getItem = function patched(key: string) {
        if (key.startsWith('msl_production_line')) {
          throw new DOMException('denied')
        }

        return original.call(this, key)
      }
    })

    await openReport(page)

    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('error-message')).toHaveCount(0)

    await pickLine(page)
    await pickPeriod(page)

    await expect(page.locator('[data-testid^="swd-kpi-"]')).toHaveCount(5)
    await expect.poll(() => state.hits.summary).toBe(1)
    expect(consoleErrors, consoleErrors.join(' | ')).toHaveLength(0)
  })

  test('skenario 5: line yang diingat milik mill lain — 403, dan nol angka asing pernah terlihat', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    const userId = await getAuthUserId(page)
    const state = await stubApi(page, { rejectedLineId: LINE_2.id })

    await page.addInitScript(
      ([key, value]) => {
        window.localStorage.setItem(key, value)
      },
      [rememberedLineKey(userId), LINE_2.id] as const,
    )

    await openReport(page)
    await pickPeriod(page)

    await expect(page.getByTestId('production-line-rejected')).toBeVisible()
    await expect(page.locator('[data-testid^="swd-kpi-"]')).toHaveCount(0)
    await expectNothingSelected(page, 'production-line-select', 'Pilih Production Line')
    await expect(page.getByTestId('network-error')).toHaveCount(0)

    // Dan ingatan yang tidak sah tidak dikirim ulang.
    await pickLine(page, LINE_1.id)
    await expect(page.locator('[data-testid^="swd-kpi-"]')).toHaveCount(5)
    expect(new URL(state.urls[state.urls.length - 1]).searchParams.get('production_line_id')).toBe(
      LINE_1.id,
    )
  })

  test('skenario 6: Operator tidak melihat pemilih Mill, dan business_unit_id tidak ada di satu alamat pun', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    const state = await stubApi(page)

    await openFullReport(page)

    await expect(page.getByTestId('mill-select')).toHaveCount(0)
    await expect(page.getByTestId('mill-current')).toBeVisible()
    // Pemilih mill ADMIN SAJA tidak pernah diminta.
    expect(state.hits.units).toBe(0)

    for (const url of [...state.urls, ...state.lineUrls]) {
      expect(new URL(url).searchParams.has('business_unit_id'), url).toBe(false)
    }
  })

  test('skenario 7: 422 akun tanpa mill — arahan menghubungi Admin, bukan galat jaringan', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    await stubApi(page, {
      periods: [],
      summaryStatus: 422,
      summaryMessage: 'Akun Anda belum terhubung ke mill. Hubungi Admin.',
    })

    await openReport(page)

    await expect(page.getByTestId('no-mill-for-account')).toBeVisible()
    await expect(page.getByTestId('no-mill-for-account')).toContainText('hubungi Admin')
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.locator('[data-testid^="swd-kpi-"]')).toHaveCount(0)
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
  })

  test('skenario 8: mill tanpa periode — pemilih periode kosong dengan kalimat sebabnya', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    const state = await stubApi(page, { periods: [] })

    await openReport(page)
    await pickLine(page)

    await expect(page.getByTestId('no-periods')).toBeVisible()
    await expect(page.getByTestId('no-periods')).toContainText('Solid Waste Disposal')
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect.poll(() => state.hits.summary).toBe(0)
    await expect(page.locator('[data-testid^="swd-kpi-"]')).toHaveCount(0)
  })

  test('skenario 26: ekspor ditekan dua kali cepat — TEPAT satu permintaan dan satu unduhan', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    const state = await stubApi(page, { exportDelayMs: 1500 })
    const downloads: string[] = []

    page.on('download', (download) => downloads.push(download.suggestedFilename()))

    await openFullReport(page)

    const button = page.getByTestId('swd-export-csv')

    await button.click()
    await expect(button).toBeDisabled()
    await expect(button).toHaveAttribute('aria-busy', 'true')

    // KETUKAN KEDUA DIPAKSA: tanpa force, Playwright menunggu tombolnya
    // enabled lagi dan yang teruji adalah penantian alat uji, bukan
    // penjagaan aplikasi.
    await button.click({ force: true })

    await expect(button).toBeEnabled({ timeout: 10_000 })
    await expect.poll(() => state.hits.export).toBe(1)
    await expect.poll(() => downloads.length).toBe(1)
  })

  test('skenario 26: URL ekspor membawa format=csv dan kedua parameter lingkup', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    const state = await stubApi(page)

    await openFullReport(page)

    const downloadPromise = page.waitForEvent('download')

    await page.getByTestId('swd-export-csv').click()
    await downloadPromise

    const exportUrl = state.urls.find((url) => url.includes('/export'))

    expect(exportUrl).toBeTruthy()

    const params = new URL(exportUrl as string).searchParams

    expect(new URL(exportUrl as string).pathname).toBe('/api/solid-waste-disposal-reports/export')
    expect(params.get('period_id')).toBe(PERIOD.id)
    expect(params.get('production_line_id')).toBe(LINE_1.id)
    expect(params.get('format')).toBe('csv')
    expect(params.get('format')).not.toBe('xlsx')
  })

  test('skenario 31: /summary gagal sekali — keadaan galat + Coba Lagi, lalu angka termuat; jumlah DIHITUNG', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    const state = await stubApi(page, { summaryAbortOnce: true })

    await openReport(page)
    await pickLine(page)
    await pickPeriod(page)

    await expect(page.getByTestId('network-error')).toBeVisible()
    await expect(page.getByTestId('retry-button')).toBeVisible()
    await expect(page.locator('[data-testid^="swd-kpi-"]')).toHaveCount(0)

    await page.getByTestId('retry-button').click()

    await expect(page.locator('[data-testid^="swd-kpi-"]')).toHaveCount(5)
    // TEPAT 2 — bukan 3.
    await expect.poll(() => state.hits.summary).toBe(2)
  })

  test('skenario 30: sesi berakhir (401) — diarahkan ke Login tanpa pesan jaringan', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    await stubApi(page, { summaryStatus: 401, summaryMessage: 'Unauthenticated.' })

    await openReport(page)
    await pickLine(page)
    await pickPeriod(page)

    // Dibandingkan atas PATHNAME, bukan toHaveURL('**/login').
    await expectPathname(page, '/login')
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.locator('[data-testid^="swd-kpi-"]')).toHaveCount(0)
  })

  test('skenario 29: luring DINYATAKAN — nol permintaan ke kelima jalur, DAPAT DIBANTAH penghitungnya', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    const state = await stubApi(page)

    // LAYARNYA DIBUKA LEBIH DULU: rutenya lazy-import, jadi menyetel luring
    // sebelum chunk-nya terunduh membuat navigasi MENGGANTUNG alih-alih
    // gagal.
    await openFullReport(page)

    const before = { ...state.hits }

    /**
     * LURINGNYA DIPALSUKAN DI `navigator.onLine`, BUKAN LEWAT
     * context.setOffline() — dan perbedaannya menentukan, bukan selera.
     * setOffline(true) memutus jaringan pada tingkat BROWSER, jadi reload
     * sesudahnya gagal mengambil dokumen dan bundle-nya sendiri dari Vite dev
     * server: navigasinya mati dengan ERR_INTERNET_DISCONNECTED dan
     * aplikasinya TIDAK PERNAH DIJALANKAN.
     *
     * STUB API DIBIARKAN TERPASANG justru supaya penolakan permintaan DAPAT
     * DIBANTAH: kalau layarnya ternyata meminta, penghitungnya naik dan asersi
     * terakhir memerah.
     */
    await page.addInitScript(() => {
      Object.defineProperty(window.navigator, 'onLine', {
        configurable: true,
        get: () => false,
      })
    })

    await page.reload()

    await expect(page.getByTestId('offline-notice')).toBeVisible()
    await expect(page.getByTestId('offline-notice')).toContainText('memerlukan jaringan')
    await expect(page.getByTestId('offline-notice')).toContainText(
      'tidak menampilkan satu angka pun dari pemuatan sebelumnya',
    )
    await expect(page.locator('[data-testid^="swd-kpi-"]')).toHaveCount(0)
    await expect(page.getByTestId('swd-daily-table')).toHaveCount(0)
    await expect(page.getByTestId('laporan-solid-waste-disposal-mobile')).toBeVisible()

    // KELIMA jalur diperiksa satu per satu.
    await expect.poll(() => state.hits.periods).toBe(before.periods)
    await expect.poll(() => state.hits.lines).toBe(before.lines)
    await expect.poll(() => state.hits.summary).toBe(before.summary)
    await expect.poll(() => state.hits.units).toBe(before.units)
    await expect.poll(() => state.hits.export).toBe(before.export)
  })

  test('layar hanya MEMBACA: nol permintaan POST/PUT/PATCH/DELETE ke API laporan', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    const writes: string[] = []

    page.on('request', (request) => {
      if (
        ['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method())
        && request.url().includes('/api/')
        && !request.url().includes('/api/login')
      ) {
        writes.push(`${request.method()} ${request.url()}`)
      }
    })

    await stubApi(page)
    await openFullReport(page)
    await openAllRecaps(page)

    const downloadPromise = page.waitForEvent('download')

    await page.getByTestId('swd-export-csv').click()
    await downloadPromise

    expect(writes, writes.join(' | ')).toHaveLength(0)
  })

  test('Back kembali ke Dashboard & Reporting', async ({ page }) => {
    await login(page, USERS.operator)
    await stubApi(page)
    await openReport(page)

    await page.getByTestId('back-button').click()

    await expectPathname(page, '/dashboard-reporting')
  })
})

/* ================================================================== */
/* ADMIN — PEMILIH MILL                                                */
/* ================================================================== */

test.describe('Laporan Solid Waste Disposal Mobile — Admin memilih mill', () => {
  test.use({ viewport: PHONE })

  /**
   * SESI ADMIN DIPALSUKAN DI PENYIMPANAN PERANGKAT, bukan lewat login
   * sungguhan: helpers.USERS hanya menyediakan operator dan supervisor
   * (DemoAccountSeeder), dan menambahkan akun admin ke basis data DEV demi
   * satu test adalah TULISAN ke data kerja pengguna — yang berkas ini memang
   * tidak melakukan. Yang diuji di sini murni cabang tampilan `role ===
   * 'admin'` di dalam view, dan cabang itu dibaca dari authStore, bukan dari
   * server.
   */
  async function loginAsFakeAdmin(page: Page): Promise<void> {
    await login(page, USERS.operator)

    const raw = await page.evaluate(() => window.localStorage.getItem('msl_auth_user'))

    expect(raw).not.toBeNull()

    const admin = { ...JSON.parse(raw as string), role: 'admin', business_unit_id: null }

    await page.addInitScript((value) => {
      window.localStorage.setItem('msl_auth_user', value)
    }, JSON.stringify(admin))
  }

  test('skenario 6: Admin memilih mill lalu menggantinya — kedua pemilih kosong, nol angka', async ({
    page,
  }) => {
    await loginAsFakeAdmin(page)

    const state = await stubApi(page, { businessUnitsStatus: 200 })

    await openReport(page)

    await expect(page.getByTestId('mill-select')).toBeVisible()
    await expect(page.getByTestId('mill-required-hint')).toBeVisible()
    // Sampai mill dipilih: nol permintaan periode/line/ringkasan.
    await expect.poll(() => state.hits.periods).toBe(0)
    await expect.poll(() => state.hits.lines).toBe(0)
    await expect.poll(() => state.hits.summary).toBe(0)

    await page.getByTestId('mill-select').selectOption('bu-9')
    await pickLine(page)
    await pickPeriod(page)

    await expect(page.locator('[data-testid^="swd-kpi-"]')).toHaveCount(5)

    // business_unit_id IKUT pada alamat yang tertangkap.
    const summaryUrl = state.urls.filter((url) => url.includes('/summary')).pop()

    expect(new URL(summaryUrl as string).searchParams.get('business_unit_id')).toBe('bu-9')

    // Ganti mill: KEDUA pemilih kosong dan nol kartu angka.
    await page.getByTestId('mill-select').selectOption('bu-1')

    await expectNothingSelected(page, 'production-line-select', 'Pilih Production Line')
    await expectNothingSelected(page, 'period-select', 'Pilih Periode')
    await expect(page.locator('[data-testid^="swd-kpi-"]')).toHaveCount(0)
    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()
  })
})

/* ================================================================== */
/* PINTU MASUK DARI REPORTING (screen-141)                             */
/* ================================================================== */

/**
 * Tabel `station` lokal kosong pada perangkat yang baru login, dan layar
 * Reporting membaca stasiunnya dari sana. Dibuka lewat layar Daftar Stasiun
 * supaya alur sinkronisasi aplikasi sendiri yang mengisinya — bukan lewat
 * penyemaian langsung. TIDAK ADA TULISAN KE BASIS DATA DEV di sini: yang
 * terisi adalah SQLite DI DALAM browser.
 */
async function seedLocalStations(page: Page): Promise<void> {
  await page.getByTestId('menu-card-production-process-activity').click()
  await page.waitForURL((url) => url.pathname === '/stations')

  const picker = page.getByTestId('production-line-picker')
  const firstTile = page.locator('[data-testid^="station-tile-"]').first()

  await expect(picker.or(firstTile).first()).toBeVisible({ timeout: 15_000 })

  if (await picker.isVisible()) {
    await page.locator('[data-testid^="production-line-option-"]').first().click()
  }

  await expect(firstTile).toBeVisible({ timeout: 15_000 })

  await page.getByTestId('breadcrumb-home').click()
  await page.waitForURL((url) => url.pathname === '/home')
}

/** Home → Dashboard & Reporting → Reporting, seluruhnya lewat klik. */
async function goToReports(page: Page): Promise<void> {
  await page.getByTestId('menu-card-dashboard-reporting').click()
  await page.waitForURL((url) => url.pathname === '/dashboard-reporting')

  await page.getByTestId('menu-card-reporting').click()
  await page.waitForURL((url) => url.pathname === '/reports')
  await expect(page.getByTestId('station-grid')).toBeVisible()
}

test.describe('Pintu masuk dari Reporting (screen-141)', () => {
  test.use({ viewport: PHONE })

  /**
   * SATU BARIS yang menentukan layar ini dapat dicapai: entri
   * 'solid-waste-disposal' pada REPORT_ROUTES di ReportingPilihStasiunView.vue
   * — DENGAN TANDA HUBUNG dan TERKUTIP. Kunci bergaris-bawah akan
   * terkompilasi, terlihat benar, dan tidak pernah cocok dengan satu
   * station.type pun. Tanpa test ini, layar laporan bisa lengkap, seluruh test
   * lainnya hijau, dan tile-nya tetap kelabu — pola kegagalan yang sudah
   * terjadi sekali pada laporan Cages & Tracks versi web.
   */
  test('skenario 32: tile Solid Waste Disposal aktif dan menavigasi ke layar laporannya', async ({
    page,
  }) => {
    const consoleErrors: string[] = []

    page.on('pageerror', (error) => consoleErrors.push(error.message))

    await login(page, USERS.operator)
    await stubApi(page)
    await seedLocalStations(page)
    await goToReports(page)

    const tile = page.getByTestId('station-tile-solid-waste-disposal')

    await tile.scrollIntoViewIfNeeded()
    await expect(tile).toBeVisible()
    await expect(tile).toHaveAttribute('aria-disabled', 'false')
    await expect(tile).not.toContainText('Belum tersedia')

    await tile.click()

    // Alamatnya membawa ?production_line_id= dari layar itu, jadi yang
    // dibandingkan adalah PATHNAME-nya saja.
    await expectPathname(page, ROUTE)
    await expect(page.getByTestId('laporan-solid-waste-disposal-mobile')).toBeVisible()
    await expect(page.getByTestId('info-message')).toHaveCount(0)

    expect(consoleErrors, consoleErrors.join(' | ')).toHaveLength(0)
  })
})
