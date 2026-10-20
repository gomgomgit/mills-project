import { expect, test, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { getAuthUserId, login, USERS } from './helpers'

/**
 * laporan-kernel-dispatch.spec.ts — screen-165--laporan-kernel-dispatch-mobile /
 * usecase-171--laporan-kernel-dispatch-mobile.
 *
 * Satu test per `test_scenarios` yang punya `browser_test` (ke-36-nya punya),
 * ditambah empat test yang memecah satu skenario menjadi lebih dari satu blok
 * ketika klaimnya memang dua klaim. Berjalan di Vite dev server
 * (playwright.config.ts, baseURL http://localhost:5174): aplikasi Capacitor
 * adalah SPA biasa sebelum dibungkus native, jadi browser test layar mobile
 * TIDAK ditangguhkan sebagai "mobile-only".
 *
 * VIEWPORT PONSEL, BUKAN DESKTOP. Berkas ini menimpa viewport bawaan proyek
 * chromium (Desktop Chrome, 1280px) dengan 390x844. Separuh klaim layar ini
 * adalah klaim tata letak ("halaman tidak menggulir mendatar walau rekapnya
 * lima kolom", "tabel BERTUMPUK menjadi kartu", "kartu mutu tidak memotong
 * isinya", "prosa ketiadaan terbaca UTUH"), dan seluruhnya hampa pada kanvas
 * 1280px: apa pun muat di sana.
 *
 * ────────────────────────────────────────────────────────────────────────
 * RESPONS LAPORAN DI-STUB DI TINGKAT JARINGAN (page.route), BUKAN DISEMAI —
 * DAN ITULAH YANG MENJAGA BASIS DATA DEV
 * ────────────────────────────────────────────────────────────────────────
 * Keputusan dan alasan yang sama dengan kelima belas laporan mobile lain:
 *
 *   - Layar ini read-only total dan tidak punya satu pun UI untuk membuat
 *     data yang dibacanya.
 *   - Kebenaran ANGKA-nya bukan tanggung jawab layar ini: seluruhnya dari
 *     KernelDispatchReportService, yang sudah diuji penuh ketika screen-164
 *     dibangun. Yang khas mobile — dan hanya dapat dibuktikan di sini —
 *     adalah PEMETAAN respons itu ke layar satu kolom: tiga kartu angka
 *     tonase, EMPAT KARTU MUTU dengan empat penyebut yang berdiri sendiri,
 *     empat rekap kategoris + rekap harian yang BERTUMPUK menjadi kartu, dan
 *     kelima prosa server yang harus terbaca utuh.
 *   - Beberapa skenario mustahil dibuat dari data sungguhan tanpa merusak
 *     lingkungan (sesi kedaluwarsa, jaringan putus, 403 line milik mill lain,
 *     periode belum mulai, muatan yang sengaja tidak konsisten secara
 *     aritmetika, penyimpanan perangkat yang melempar, nilai gate_status di
 *     luar kosakata bersama).
 *   - DAN YANG PALING MENGIKAT: backend di :8000 adalah DATABASE DEV — data
 *     kerja pengguna, yang per hari ini punya NOL baris
 *     `kernel_dispatch_records`. Berkas ini karena itu TIDAK menulis,
 *     menghapus, menyemai, atau memigrasi apa pun di sana. Yang menyentuh
 *     backend sungguhan HANYA login (POST /api/login). TIDAK ADA `migrate`,
 *     `migrate:fresh`, `--seed`, maupun `db:prepare` di jalur spec ini.
 *
 * TIDAK ADA BACKEND :8001 DI SINI. `test_strategy.browser_test.start_command`
 * menyebut port :8001 dan `db:prepare` — itu menggambarkan suite e2e-web/ di
 * akar proyek, BUKAN jalur layar ini.
 *
 * ── ENAM HAL YANG HANYA BROWSER DAPAT MEMBUKTIKAN DI LAYAR INI ─────────
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
 * 3. KARTU MUTU TIDAK MEMOTONG ISINYA, DIUKUR — geometri kartu lawan geometri
 *    isinya. Empat ukuran min/avg/max berdesimal tiga di satu kolom sempit
 *    adalah godaan terbesarnya, dan kelas yang benar tetap dapat menghasilkan
 *    anak yang keluar dari kotak induknya.
 *
 * 4. PROSA KETIADAAN TERBACA UTUH pada 390px — DIUKUR pada hasil render:
 *    scrollHeight lawan clientHeight, plus `textOverflow` dan
 *    `webkitLineClamp` dari CSS HASIL COMPILE. Keterangan yang terpotong di
 *    tengah berhenti menerangkan apa pun, dan di layar sempit itulah godaan
 *    terbesarnya.
 *
 * 5. SETIAP KELAS CSS YANG DIPAKAI MARKUP PUNYA DEFINISI DI CSS HASIL COMPILE
 *    — dibaca dari document.styleSheets, bukan dari berkas sumber, DAN DENGAN
 *    PATH ASET DIAMBIL DARI ATRIBUT `href` MILIK DOKUMEN ITU SENDIRI. Pada
 *    audit sebelumnya glob aset tidak cocok sehingga satu stylesheet tidak
 *    pernah terbaca dan audit melaporkan 14 CACAT PALSU dengan nada seyakin
 *    cacat nyata; karena itu jumlah lembar gaya yang benar-benar TERBACA
 *    diasersi lebih dulu, supaya audit yang tidak membaca apa pun memerah
 *    alih-alih menuduh.
 *
 * 6. UNDUHAN CSV BENAR-BENAR TERPICU, dan berkasnya dibaca dari disk: 27 kolom
 *    header (6 konteks + 21 detail), termasuk keenam kolom yang tidak muncul
 *    di satu angka pun di layar.
 *
 * ── KONVENSI YANG HARGANYA SUDAH DIBAYAR ───────────────────────────────
 *
 * LAYAR INI TIDAK MEMILIHKAN LINE, dan TIDAK PULA MEMILIHKAN PERIODE. Setiap
 * test di bawah karena itu memilih keduanya (atau menyemai ingatan line lebih
 * dulu); test yang hanya memilih salah satunya akan menunggu angka yang memang
 * tidak akan pernah datang.
 *
 * KETIADAAN DIASERSI ATAS TESTID, ROLE DAN JUDUL — BUKAN ATAS PROSA. Layar ini
 * WAJIB merender kalimat yang menyebut 'jam', 'waktu putar', 'shift', 'batas',
 * 'CPO Dispatch' dan 'lembar kerja biner', seluruhnya untuk menerangkan
 * ketiadaannya SENDIRI. Pemindaian prosa (termasuk `body.innerText`) karena
 * itu MEMERAH pada layar yang BENAR, dan tidak dipakai satu kali pun di berkas
 * ini.
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
 * KONTROL LIPATNYA LOKAL DI VIEW DAN BAWAANNYA TERBUKA — bukan
 * CollapsibleSection. Tiap rekap punya testid lipat SENDIRI
 * (`{testid}-toggle`, `kd-daily-recap-toggle`) dan melipatnya MENGELUARKAN
 * tabelnya dari DOM (`v-if`, bukan `v-show`), jadi keadaan tertutup diasersi
 * dengan toHaveCount(0) — berbeda dari screen-163, yang memakai
 * CollapsibleSection ber-`v-show` dan karena itu diasersi dengan toBeHidden().
 * Konsekuensi yang dipakai di seluruh berkas ini: baris rekap dapat dibaca
 * LANGSUNG sesudah angka terbit, tanpa membuka apa pun lebih dulu.
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
 * webServer, reuseExistingServer). Tanpa backend hidup setiap test membakar
 * timeout navigasi 30s dan gagal karena sebab yang tidak ada hubungannya
 * dengan layar ini.
 *
 * ── TIGA KOREKSI ATAS HURUF SKENARIO, DICATAT DI TEMPATNYA ─────────────
 *
 * (a) Skenario 31 butir (3) menuntut "tabel lebar MENGGULIR MENDATAR di dalam
 *     kartunya". Itu bentuk yang justru DIBUANG temuan QA LP-M-02/LP-M-03:
 *     src/assets/stacked-table.css memasang `overflow-x: visible` pada
 *     `.detail-table-wrap` di @media (max-width: 640px) dan MENUMPUK tabelnya
 *     menjadi kartu, dan view ini tidak menimpanya. Butir (1) dan (2)
 *     skenario yang sama, serta jebakan 8 di brief, menuntut BENTUK BERTUMPUK
 *     — jadi yang diasersi di sini adalah bentuk bertumpuk yang DIUKUR, dan
 *     ketiadaan gulir mendatar di dalam pembungkusnya. Dua tuntutan itu tidak
 *     dapat dipenuhi sekaligus, dan yang menang adalah yang dipakai kode.
 *
 * (b) Penamaan sel rekap: baris berprefiks `{testid}-row-{i}` sementara selnya
 *     `{testid}-value-{i}` / `{testid}-dispatch-count-{i}` / dst. Satu sel
 *     bernama `...-row-3-count` akan membuat pencarian berprefiks `-row-`
 *     memulangkan 8 untuk rekap berisi 4 baris.
 *
 * (c) Format angka mutu mengikuti `formatQuality` (min 2 / max 3 desimal):
 *     payload 9 tercetak '9,00', 0 tercetak '0,00' dan 0.025 tercetak
 *     '0,025'. Nol TERUKUR karena itu tidak dapat tertukar dengan nilai yang
 *     TIDAK ADA ('—') — yang justru jantung asersi mutu di bawah; jumlah
 *     desimalnya sendiri tidak mengubah arti apa pun.
 */

const ROUTE = '/reports/kernel-dispatch'
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

/** SEMBILAN hari berpengiriman di dalam periode 30 hari. */
const LOADED_DAYS = [-25, -22, -20, -18, -15, -12, -9, -6, -3].map(isoDaysFromNow)

/** Urutan payload SENGAJA bukan urut tanggal naik. */
const DAILY_ORDER = [4, 0, 7, 2, 8, 1, 6, 3, 5].map((index) => LOADED_DAYS[index])

/* ------------------------------------------------------------------ */
/* Prosa SERVER — DISALIN dari KernelDispatchReportService             */
/* ------------------------------------------------------------------ */

/**
 * DISALIN VERBATIM dari backend/app/Services/KernelDispatchReportService.php
 * (NOTE_QUALITY_DENOMINATORS ± baris 387, NOTE_NO_HOURLY ± 401,
 * NOTE_NO_QUALITY_THRESHOLDS ± 419, NOTE_SHIFT_NOT_PUBLISHED ± 432,
 * NOTE_COMPLIANCE ± 444) — bukan dikarang. Layar merender kelimanya UTUH di
 * elemen yang hanya memuat kalimat server, dan prosa karangan akan membuat
 * asersi "terbaca utuh" hijau sambil membuktikan nol hal tentang kalimat yang
 * benar-benar dibaca pengguna.
 *
 * LIMA, bukan empat: stasiun ini TIDAK punya `daily_total._note` (berbeda dari
 * Solid Waste Disposal), jadi paragraf kaki rekap harian
 * (`kd-daily-total-note`) adalah prosa KLIEN dan tidak ada konstanta service
 * yang dapat dibandingkan dengannya.
 */
const NOTE_QUALITY_DENOMINATORS =
  'KEEMPAT PENYEBUT DI BAWAH BOLEH BERBEDA SATU DENGAN LAINNYA, dan boleh berbeda dari '
  + 'jumlah pengiriman periode ini. Sebabnya sederhana dan bukan cacat data: kadar air '
  + 'inti, kadar kotoran, kadar asam lemak bebas dan kadar inti pecah adalah EMPAT UJI YANG '
  + 'BERDIRI SENDIRI, dan satu pengiriman boleh punya sebagian hasil uji saja. Karena itu '
  + 'setiap ukuran menyebut penyebutnya SENDIRI — berapa pengiriman yang kolom ITU-nya '
  + 'benar-benar terisi — dan tidak satu pun dari keempatnya meminjam penyebut dari yang '
  + 'lain. Pembaca yang melihat kadar air dihitung atas 12 pengiriman sementara ALB atas 7 '
  + 'sedang melihat dua uji yang tidak selalu dilakukan bersama, BUKAN lima hasil uji yang '
  + 'hilang. Satu penyebut tunggal untuk keempat ukuran akan tampak lebih rapi dan akan '
  + 'salah untuk setidaknya tiga di antaranya: ia mengempiskan ukuran yang jarang diuji '
  + 'sambil tetap menghasilkan angka yang terlihat sah. Dan ketika sebuah ukuran tidak '
  + 'punya satu hasil uji pun, hanya ukuran ITU yang ditulis sebagai tanda pisah — ketiga '
  + 'ukuran lain tetap menampilkan angkanya, karena yang kosong adalah satu kolom, bukan '
  + 'seluruh mutu periode ini.'

const NOTE_NO_HOURLY =
  'Sebaran pengiriman per jam dan waktu putar kendaraan KEDUANYA TIDAK DAPAT dibuat untuk '
  + 'stasiun ini, dan ketiadaannya dinyatakan di sini alih-alih disembunyikan. Baris detail '
  + 'Kernel Dispatch hanya membawa event_date — sebuah tanggal — dan tidak punya satu pun '
  + 'kolom waktu: tidak ada jam masuk, tidak ada jam keluar. Bukan kolom waktu yang kosong, '
  + 'bukan kolom yang belum diisi operator: kolomnya tidak ada. Karena itu pertanyaan '
  + 'tentang kapan dalam sehari pengiriman paling padat, dan pertanyaan tentang berapa lama '
  + 'satu kendaraan berada di area pabrik, tidak dapat dihitung dari data yang ada dalam '
  + 'bentuk apa pun — tidak sebagai grafik 24 kolom, tidak sebagai kartu jam tersibuk, '
  + 'tidak sebagai rata-rata menit, tidak pula sebagai perkiraan. KEDUANYA BUKAN FITUR YANG '
  + 'DITUNDA. Bandingkan CPO Dispatch (screen-166 dan screen-167): tabel detail stasiun itu '
  + 'PUNYA time_in dan time_out, sehingga di sana sebaran per jam dan waktu putar kendaraan '
  + 'JUSTRU ADA dan memang wajib ada — perbedaannya terletak pada kolom yang tersedia, '
  + 'bukan pada kelengkapan laporannya. Menurunkan jam dari created_at berarti menebak dari '
  + 'waktu INPUT, bukan waktu kejadian, dan jawaban yang terlihat rinci padahal tidak '
  + 'pernah diukur lebih buruk daripada tidak ada jawaban. Satu-satunya sumbu waktu yang '
  + 'tersedia di layar ini adalah tanggal, yaitu rekap harian.'

const NOTE_NO_QUALITY_THRESHOLDS =
  'Tidak ada satu nilai mutu pun di layar ini yang ditandai sebagai di luar batas, dan '
  + 'ketiadaan penandaan itu disengaja: stasiun Kernel Dispatch tidak punya tabel master '
  + 'ambang mutu apa pun — tidak ada baris acuan untuk kadar air inti, kadar kotoran, kadar '
  + 'asam lemak bebas, maupun kadar inti pecah. Sebuah laporan mutu tanpa penandaan mudah '
  + 'terbaca sebagai laporan yang lupa membandingkan, jadi sebabnya ditulis di sini. '
  + 'Menurunkan ambang dari data periode ini sendiri akan menghasilkan garis yang terlihat '
  + 'berwibawa lalu dibaca sebagai batas resmi padahal bukan, dan garis itu ikut bergeser '
  + 'setiap kali kelengkapan pencatatan berubah — sebuah nilai dapat berpindah dari aman ke '
  + 'melanggar hanya karena pengiriman lain ikut diuji. Keempat ukuran karena itu '
  + 'ditampilkan apa adanya, tanpa warna peringatan dan tanpa satu sel yang ditandai; '
  + 'penilaian layak atau tidak layak tetap di tangan pembacanya, yang memegang spesifikasi '
  + 'pembelinya dan laporan ini tidak.'

const NOTE_SHIFT_NOT_PUBLISHED =
  'Kolom shift pada baris detail bertipe TEKS BEBAS, bukan daftar nilai tertutup dan '
  + 'bukan relasi ke master. Menyebarkan teks bebas menghasilkan satu nilai per ejaan yang '
  + 'berbeda, sehingga empat penulisan dari satu giliran kerja yang sama akan terbaca '
  + 'sebagai empat kategori. Kolom itu karena itu TIDAK diterbitkan di payload maupun di '
  + 'layar ini — bukan sebagai rekap, bukan sebagai kolom tabel, bukan pula sebagai tempat '
  + 'kosong. Ia TETAP ikut di berkas ekspor pada setiap baris pengiriman, bersama nomor '
  + 'tiket timbang, nomor surat jalan, nomor kendaraan, nama pengemudi dan kode operator '
  + 'timbang, karena ekspor adalah rincian mentah. Pengelompokan per giliran kerja yang '
  + 'sungguhan datang dari kolom shift_id pada tabel INDUK dan berlaku untuk 18 stasiun '
  + 'sekaligus — pekerjaan tersendiri, bukan sesuatu yang ditempelkan pada satu laporan.'

const NOTE_COMPLIANCE =
  'Nomor surat jalan dan kedua nomor segel keamanan adalah JEJAK KEPATUHAN, bukan ukuran '
  + 'produksi: ketiganya menyatakan dokumen angkut yang tidak terbit. Karena itu ketiganya '
  + 'diterbitkan HANYA sebagai penghitung kehadiran dan tidak pernah dirata-rata, tidak '
  + 'pernah diurutkan, dan tidak pernah digrafikkan. Sebuah nomor segel tidak punya '
  + 'besaran: segel bernomor 90021 bukan lebih besar daripada segel bernomor 90018, selisih '
  + 'keduanya tidak berarti apa pun, dan rata-rata nomor segel adalah angka yang tidak '
  + 'mengukur apa-apa. SEGEL ATAS DAN SEGEL BAWAH DIHITUNG TERPISAH DAN TIDAK PERNAH '
  + 'DIJUMLAHKAN MENJADI SATU ANGKA. Satu kendaraan yang kehilangan satu segel adalah '
  + 'keadaan yang berbeda dari dua kendaraan yang masing-masing kehilangan satu, dan satu '
  + 'angka gabungan menghapus perbedaan itu tepat ketika perbedaan itu paling dibutuhkan. '
  + 'Satu pengiriman yang kehilangan KEDUA segelnya terhitung di KEDUA penghitung — itu '
  + 'bukan penghitungan ganda, itu dua fakta, dan keduanya benar. Bila seluruh pengiriman '
  + 'periode lengkap, ketiga penghitung menunjukkan nol dan nol itu TETAP diterbitkan — nol '
  + 'itulah yang memberi tahu pembaca bahwa pemeriksaannya memang berjalan. Nomor-nomornya '
  + 'sendiri tidak diterbitkan di payload layar; ia hanya ada di ekspor.'
/**
 * HEADER EKSPOR — DISALIN VERBATIM dari
 * KernelDispatchReportService::EXPORT_HEADER (baris 357-385): 6 kolom konteks
 * (Periode, Rentang Periode, Mill, Production Line, Tanggal Record, Status) +
 * 21 kolom detail = 27, dengan nama, EJAAN dan URUTAN apa adanya.
 *
 * ENAM kolom konteks, BUKAN LIMA seperti kelima belas laporan sebelumnya:
 * layar ini menuntut Rentang Periode ikut, dan rentang itu tidak dapat
 * diturunkan dari nama periode. Stub yang menyalin bentuk ekspor laporan LAIN
 * karena itu berjumlah kolom salah — itu persis yang terjadi pada screen-161,
 * tempat stub CSV DIKARANG. Seandainya angka asersinya yang disesuaikan,
 * seluruh berkas akan hijau sambil membuktikan NOL hal tentang berkas yang
 * sungguh diunduh pengguna. Sebuah stub hanya sekuat kesetiaannya pada yang ia
 * tiru, dan kesetiaan itu tidak dijaga oleh apa pun selain disalin.
 *
 * ENAM di antaranya tidak menyumbang satu angka pun di layar — Shift,
 * Weighbridge Ticket No, Vehicle Plate No, Driver Name, Weighbridge Operator
 * ID, dan nomor tiket timbangnya — dan justru itu yang membuat asersi 27 kolom
 * berarti. Waybill Number serta kedua kolom segel TIDAK termasuk kelompok itu:
 * ketiganya MENYUMBANG angka lewat blok `compliance`; yang tidak muncul di
 * layar adalah NILAINYA, bukan keberadaannya.
 */
const EXPORT_HEADER = [
  'Periode',
  'Rentang Periode',
  'Mill',
  'Production Line',
  'Tanggal Record',
  'Status',
  'Tanggal Kejadian',
  'Shift',
  'Weighbridge Ticket No',
  'Waybill Number',
  'Transporter/Contractor',
  'Vehicle Plate No',
  'Driver Name',
  'Silo Source ID',
  'Destination/Buyer',
  'Gross Weight (MT)',
  'Tare Weight (MT)',
  'Net Weight (MT)',
  'Kernel Moisture (%)',
  'Dirt/Impurities (%)',
  'FFA (%)',
  'Broken Kernel (%)',
  'Security Seal No (Top)',
  'Security Seal No (Bottom)',
  'Weighbridge Operator ID',
  'Remarks/Gate Status',
  'Findings',
]

/* ------------------------------------------------------------------ */
/* Fixture — BENTUK RESPONS NYATA                                      */
/* ------------------------------------------------------------------ */

const PERIOD = {
  id: 'per-1',
  name: 'Periode Uji Kernel Dispatch',
  start_date: PERIOD_START,
  end_date: PERIOD_END,
  status: 'open',
  // DENGAN TANDA HUBUNG. Garis bawah di situ tidak cocok dengan satu baris
  // period_stations pun, dan server menjawab [] tanpa satu galat.
  station_type: 'kernel-dispatch',
  station_type_label: 'Kernel Dispatch',
}

const PERIOD_CLOSED = {
  id: 'per-2',
  name: 'Periode Sebelumnya',
  start_date: PREV_START,
  end_date: PREV_END,
  status: 'closed',
  station_type: 'kernel-dispatch',
  station_type_label: 'Kernel Dispatch',
}

const LINE_1 = { id: 'pl-1', name: 'Line Satu', code: 'L1' }
const LINE_2 = { id: 'pl-2', name: 'Line Dua', code: 'L2' }

const BUSINESS_UNITS = [
  { id: 'bu-1', name: 'Mill Sungai Rambutan' },
  { id: 'bu-9', name: 'Mill Sungai Lilin' },
]

/** Kunci ingatan line, ditulis StationListView.vue dan dibaca layar ini. */
const rememberedLineKey = (userId: string) => `msl_production_line_${userId}`

/** Keempat testid rekap kategoris, dalam urutan DOM-nya. */
const RECAP_TESTIDS = [
  'kd-recap-transporter',
  'kd-recap-silo-source',
  'kd-recap-destination-buyer',
  'kd-recap-gate-status',
]

/** Ketiga rekap TEKS BEBAS — tanpa rekap keempat yang berpilihan tertutup. */
const FREE_TEXT_RECAP_TESTIDS = RECAP_TESTIDS.slice(0, 3)

const ALL_RECAP_TESTIDS = [...RECAP_TESTIDS, 'kd-daily-recap']

/** Keempat slug kartu mutu, dalam urutan QUALITY_BLOCKS di view. */
const QUALITY_SLUGS = ['kernel-moisture', 'dirt-impurities', 'ffa', 'broken-kernel']

/**
 * Satu baris rekap, dengan nama medan PERSIS seperti payload server —
 * `dispatch_count`, bukan `load_count` seperti Solid Waste Disposal.
 */
function row(
  value: string | null,
  dispatchCount: number,
  netWeightTotal: number | null,
  netWeightDispatchCount: number,
  netWeightAvg: number | null,
): Record<string, unknown> {
  return {
    value,
    dispatch_count: dispatchCount,
    net_weight_total: netWeightTotal,
    net_weight_dispatch_count: netWeightDispatchCount,
    net_weight_avg: netWeightAvg,
  }
}

/** Satu ukuran mutu — min/avg/max/penyebut, masing-masing berdiri sendiri. */
function measure(
  min: number | null,
  avg: number | null,
  max: number | null,
  measuredCount: number,
): Record<string, unknown> {
  return { min, avg, max, measured_count: measuredCount }
}

/**
 * KEEMPAT rekap, masing-masing berjumlah TEPAT 12 dengan komposisi BERBEDA,
 * masing-masing memuat SATU baris `value: null`, dan tidak satu pun urut
 * alfabetis maupun urut jumlah — baris null bahkan BUKAN di posisi terakhir.
 *
 * Rekap keempat memuat `held_by_security`, nilai DI LUAR kosakata bersama:
 * ia punya barisnya sendiri, tidak dibuang, dan tidak dilipat ke baris 'tidak
 * tercatat' — kalau dibuang, kolom Pengiriman berhenti menjumlah ke 12.
 */
function recapsOfTwelve(): Record<string, Array<Record<string, unknown>>> {
  return {
    by_transporter: [
      row('Zahra Trans', 1, 4.5, 1, 4.5),
      row(null, 5, 21.25, 3, 7.08),
      row('CV Angkutan Inti', 4, null, 0, null),
      row('PT Kirim Cepat', 2, 11.75, 1, 11.75),
    ],
    by_silo_source: [
      row('SILO-07', 6, 18.5, 2, 9.25),
      row(null, 5, 12.5, 2, 6.25),
      row('SILO-02', 1, 6.5, 1, 6.5),
    ],
    by_destination_buyer: [
      row(null, 5, 20.5, 3, 6.83),
      row('PT Oleokimia Nusantara', 3, 11.5, 1, 11.5),
      row('Pabrik Kernel Crushing B', 4, 5.5, 1, 5.5),
    ],
    by_gate_status: [
      row('released', 4, 15.5, 2, 7.75),
      row(null, 2, 6.5, 1, 6.5),
      // NILAI DI LUAR KOSAKATA BERSAMA, dengan barisnya SENDIRI.
      row('held_by_security', 1, 2.5, 1, 2.5),
      row('not_released', 5, 12.5, 1, 12.5),
    ],
  }
}

/**
 * KEEMPAT UKURAN MUTU, dengan EMPAT PENYEBUT YANG BERBEDA SATU DENGAN LAINNYA
 * dan semuanya berbeda dari dispatch_count 12 — 11 / 7 / 3 / 9.
 *
 * ARITMETIKANYA SENGAJA MUSTAHIL pada kadar air inti: avg 3 berada DI LUAR
 * rentang min 9 .. max 4 (yang bahkan terbalik). Tidak ada penghitungan apa pun
 * yang dapat menghasilkan tiga angka itu bersamaan, jadi klien yang menghitung
 * sendiri PASTI gagal di sini. JANGAN "merapikan" angka-angka ini.
 *
 * Kadar kotoran bernilai NOL TERUKUR pada ketiga angkanya dengan penyebut 7:
 * nol yang benar-benar diuji ('0,00'), bukan ketiadaan ('—'). Penjaga '—' yang
 * memakai falsiness JavaScript akan menukar keduanya, dan itu cacat yang
 * MENGUBAH ARTI.
 *
 * ALB memakai 0.025 — tiga desimal server (QUALITY_DECIMALS = 3) yang akan
 * terlipat menjadi '0,03' kalau klien membulatkan ulang.
 */
function qualityOfFour(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    kernel_moisture_percent: measure(9, 3, 4, 11),
    dirt_impurities_percent: measure(0, 0, 0, 7),
    ffa_percent: measure(0.025, 0.5, 1.2, 3),
    broken_kernel_percent: measure(1.5, 2, 3, 9),
    _note: NOTE_QUALITY_DENOMINATORS,
    ...overrides,
  }
}

function dailyRows(): Array<Record<string, unknown>> {
  const counts: Record<string, number> = {}

  LOADED_DAYS.forEach((date, index) => {
    counts[date] = [3, 1, 1, 1, 1, 1, 1, 1, 1][index]
  })

  return DAILY_ORDER.map((date, index) => ({
    date,
    dispatch_count: counts[date],
    net_weight_total: index % 3 === 1 ? null : 3.5 + index,
    net_weight_dispatch_count: index % 3 === 1 ? 0 : 1,
    net_weight_avg: index % 3 === 1 ? null : 3.5 + index,
  }))
}

/**
 * Payload /summary LENGKAP. ANGKANYA SENGAJA TIDAK KONSISTEN SECARA
 * ARITMETIKA — net_weight_avg 777 padahal 999/5 = 199,8; daily_total
 * net_weight_total 55 yang bukan jumlah kolomnya; completeness.percent 88.8
 * padahal 9/26 = 34,6…; dan keempat ukuran mutu seperti diterangkan di
 * qualityOfFour(). Klien yang menghitung PASTI gagal. JANGAN "merapikan"
 * angka-angka ini.
 *
 * daily_total.dispatch_count 11 BERBEDA dari dispatches.dispatch_count 12, dan
 * perbedaan itu INFORMASI, bukan cacat fixture: baris total rekap harian
 * dibaca dari payload, bukan dijumlahkan dari daily[] dan bukan disalin dari
 * blok dispatches.
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
    dispatches: {
      dispatch_count: 12,
      net_weight_total: 999,
      net_weight_avg: 777,
      net_weight_dispatch_count: 5,
      missing_net_weight_dispatch_count: 7,
    },
    quality: qualityOfFour(),
    ...recapsOfTwelve(),
    not_released_count: 5,
    compliance: {
      missing_waybill_count: 3,
      missing_seal_top_count: 2,
      missing_seal_bottom_count: 4,
      _note: NOTE_COMPLIANCE,
    },
    draft_dispatch_count: 4,
    // event_date NOT NULL di skema, jadi 0 adalah angka NYATA di produksi;
    // cabang > 0 diuji lewat muatan stub tersendiri.
    undated_dispatch_count: 0,
    daily: dailyRows(),
    daily_total: {
      dispatch_count: 11,
      net_weight_total: 55.0,
      net_weight_dispatch_count: 3,
      net_weight_avg: 7.25,
    },
    completeness: { days_in_period: 30, days_with_dispatch: 9, days_counted: 26, percent: 88.8 },
    // SATU daftar teks bebas, bukan dua: stasiun ini TIDAK punya kolom remarks.
    text_entries: {
      findings: [
        { date: LOADED_DAYS[0], value: 'Segel bawah satu kendaraan terpasang setelah kendaraan bergerak.' },
      ],
    },
    _note_no_hourly: NOTE_NO_HOURLY,
    _note_no_quality_thresholds: NOTE_NO_QUALITY_THRESHOLDS,
    _note_shift_not_published: NOTE_SHIFT_NOT_PUBLISHED,
    ...overrides,
  }
}

function emptyPeriodSummary(): Record<string, unknown> {
  return makeSummary({
    dispatches: {
      dispatch_count: 0,
      net_weight_total: null,
      net_weight_avg: null,
      net_weight_dispatch_count: 0,
      missing_net_weight_dispatch_count: 0,
    },
    quality: qualityOfFour({
      kernel_moisture_percent: measure(null, null, null, 0),
      dirt_impurities_percent: measure(null, null, null, 0),
      ffa_percent: measure(null, null, null, 0),
      broken_kernel_percent: measure(null, null, null, 0),
    }),
    by_transporter: [],
    by_silo_source: [],
    by_destination_buyer: [],
    by_gate_status: [],
    not_released_count: 0,
    compliance: {
      missing_waybill_count: 0,
      missing_seal_top_count: 0,
      missing_seal_bottom_count: 0,
      _note: NOTE_COMPLIANCE,
    },
    draft_dispatch_count: 0,
    daily: [],
    daily_total: {
      dispatch_count: 0,
      net_weight_total: null,
      net_weight_dispatch_count: 0,
      net_weight_avg: null,
    },
    text_entries: { findings: [] },
  })
}

/* ------------------------------------------------------------------ */
/* Stub jaringan                                                       */
/* ------------------------------------------------------------------ */

interface ApiState {
  periods: unknown[]
  /** Status jawaban /periods — 422 di sini berarti akun tanpa mill. */
  periodsStatus: number
  periodsMessage: string
  businessUnits: unknown[]
  businessUnitsStatus: number
  summary: Record<string, unknown>
  summaryStatus: number
  summaryMessage: string
  /** true = permintaan ringkasan gagal di tingkat JARINGAN (tanpa status). */
  summaryAbort: boolean
  /** Sekali gagal, lalu sukses — dipakai test Coba Lagi. */
  summaryAbortOnce: boolean
  /** Status yang dijawab /summary mulai permintaan ke-N (1-based). */
  summaryStatusFromHit: number | null
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

/**
 * Isi berkas CSV stub: baris header DARI EXPORT_HEADER (bukan diketik ulang)
 * disusul dua baris pengiriman ber-27 sel. Baris kedua bernetto KOSONG — sel
 * kosong, bukan 0 — karena itulah bentuk yang dikirim server untuk pengiriman
 * yang penimbangannya belum selesai.
 */
function exportCsvBody(): string {
  const context = [PERIOD.name, `${PERIOD_START} s.d. ${PERIOD_END}`, 'Mill Sungai Rambutan', LINE_1.name]
  const rows = [
    [
      ...context,
      PERIOD_START,
      'open',
      LOADED_DAYS[0],
      'Pagi',
      'WB-0001',
      'WJ-0001',
      'PT Kirim Cepat',
      'BG 1234 XY',
      'Budi',
      'SILO-07',
      'PT Oleokimia Nusantara',
      '20.5',
      '8.0',
      '12.5',
      '7.10',
      '0.00',
      '0.025',
      '2.50',
      'SL-TOP-001',
      'SL-BTM-001',
      'op-1',
      'released',
      '',
    ],
    [
      ...context,
      PERIOD_START,
      'open',
      LOADED_DAYS[1],
      'Malam',
      'WB-0002',
      '',
      'CV Angkutan Inti',
      'BG 5678 ZZ',
      'Andi',
      'SILO-02',
      'Pabrik Kernel Crushing B',
      '',
      '',
      '',
      '',
      '',
      '',
      '',
      '',
      '',
      'op-2',
      'not_released',
      'Segel bawah terpasang terlambat.',
    ],
  ]

  return `${EXPORT_HEADER.join(',')}\n${rows.map((cells) => cells.join(',')).join('\n')}\n`
}

async function stubApi(page: Page, overrides: Partial<ApiState> = {}): Promise<ApiState> {
  const state: ApiState = {
    periods: [PERIOD, PERIOD_CLOSED],
    periodsStatus: 200,
    periodsMessage: 'Akun Anda belum terhubung ke mill. Hubungi Admin.',
    businessUnits: BUSINESS_UNITS,
    businessUnitsStatus: 403,
    summary: makeSummary(),
    summaryStatus: 200,
    summaryMessage: 'Permintaan ditolak.',
    summaryAbort: false,
    summaryAbortOnce: false,
    summaryStatusFromHit: null,
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

  await page.route('**/api/kernel-dispatch-reports/**', async (route) => {
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

      if (state.periodsStatus !== 200) {
        await route.fulfill({
          status: state.periodsStatus,
          headers: CORS_HEADERS,
          json: { message: state.periodsMessage },
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
          json: { message: 'Ekspor melebihi 50.000 baris pengiriman.' },
        })

        return
      }

      await route.fulfill({
        status: 200,
        headers: { ...CORS_HEADERS, 'content-type': 'text/csv' },
        body: exportCsvBody(),
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

      // `summaryStatusFromHit` memisahkan "gagal sejak awal" dari "berhasil
      // dulu, baru gagal": yang kedua adalah satu-satunya cara menguji bahwa
      // angka yang SUDAH tampil benar-benar dibersihkan saat 403 mendarat.
      let effectiveStatus = state.summaryStatus

      if (state.summaryStatusFromHit !== null && state.hits.summary < state.summaryStatusFromHit) {
        effectiveStatus = 200
      }

      if (effectiveStatus !== 200) {
        await route.fulfill({
          status: effectiveStatus,
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
  await expect(page.getByTestId('laporan-kernel-dispatch-mobile')).toBeVisible()
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

/**
 * Alur baku: buka, pilih line, pilih periode, tunggu ketiga wadah tingkat atas
 * dan keempat kartu mutu.
 *
 * KETIGA, bukan lima: prefiks `kd-card-` dipakai HANYA untuk wadah tingkat
 * atas (tonase, mutu, penimbangan belum selesai) sementara kartu angka di
 * dalamnya berprefiks `kd-metric-`. Itulah yang membuat asersi kedekatan
 * tonase–mutu dapat dibaca dari pencarian berprefiks tanpa kartu dalam yang
 * menyelip di antara keduanya.
 */
async function openFullReport(page: Page, periodId = PERIOD.id): Promise<void> {
  await openReport(page)
  await pickLine(page)
  await pickPeriod(page, periodId)
  await expect(page.getByTestId('period-meta')).toBeVisible()
  await expect(page.locator('[data-testid^="kd-card-"]')).toHaveCount(3)
  await expect(page.locator('[data-testid^="kd-quality-card-"]')).toHaveCount(4)
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

/**
 * Kontrol lipat LOKAL milik view — bukan `collapsible-section-toggle` milik
 * CollapsibleSection, yang TIDAK dipakai layar ini (lihat docblock berkas).
 */
function recapToggle(page: Page, recapTestId: string) {
  return recapTestId === 'kd-daily-recap'
    ? page.getByTestId('kd-daily-recap-toggle')
    : page.getByTestId(`${recapTestId}-toggle`)
}

/** Testid tabel sebuah rekap — rekap harian tidak mengikuti pola `-table`. */
function recapTableTestId(recapTestId: string): string {
  return recapTestId === 'kd-daily-recap' ? 'kd-daily-table' : `${recapTestId}-table`
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

/** Satu kolom: ketiga wadah tingkat atas TURUN, tidak berderet. */
async function expectSingleColumn(page: Page): Promise<void> {
  const cards = page.locator('[data-testid^="kd-card-"]')

  await expect(cards).toHaveCount(3)
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
 * KARTU MUTU TIDAK MEMOTONG ISINYA — GEOMETRI KARTU LAWAN GEOMETRI ISINYA.
 *
 * Diukur untuk `kd-card-quality` dan SETIAP anak ukurannya: kotak pembatas tiap
 * anak harus berada DI DALAM kotak pembatas kartunya pada KEDUA sumbu. Markup
 * yang benar tetap dapat menghasilkan elemen yang terpotong induknya, dan nama
 * kelas tidak pernah membuktikan sebaliknya.
 *
 * DAN keempat ukurannya BERTUMPUK: nilai `top` keempat kartu ukuran menaik,
 * `left`-nya sama.
 */
async function expectQualityCardDoesNotClipItsContent(page: Page): Promise<void> {
  await expect(page.getByTestId('kd-card-quality')).toBeVisible()
  await expect(page.locator('[data-testid^="kd-quality-card-"]')).toHaveCount(4)

  const result = await page.getByTestId('kd-card-quality').evaluate((card) => {
    const cardRect = card.getBoundingClientRect()
    const children = Array.from(card.querySelectorAll('[data-testid]')) as HTMLElement[]

    return {
      card: {
        left: cardRect.left,
        right: cardRect.right,
        top: cardRect.top,
        bottom: cardRect.bottom,
      },
      overflowing: children
        .map((child) => {
          const rect = child.getBoundingClientRect()

          return {
            testid: child.getAttribute('data-testid') ?? '',
            left: rect.left,
            right: rect.right,
            top: rect.top,
            bottom: rect.bottom,
          }
        })
        .filter(
          (child) =>
            child.left < cardRect.left - 1
            || child.right > cardRect.right + 1
            || child.top < cardRect.top - 1
            || child.bottom > cardRect.bottom + 1,
        )
        .map((child) => child.testid),
      scrollWidth: card.scrollWidth,
      clientWidth: card.clientWidth,
      scrollHeight: card.scrollHeight,
      clientHeight: card.clientHeight,
      measureBoxes: (Array.from(
        card.querySelectorAll('[data-testid^="kd-quality-card-"]'),
      ) as HTMLElement[]).map((measureCard) => {
        const rect = measureCard.getBoundingClientRect()

        return { top: Math.round(rect.top), left: Math.round(rect.left) }
      }),
    }
  })

  expect(
    result.overflowing,
    `isi kartu mutu keluar dari kotak induknya: ${result.overflowing.join(', ')}`,
  ).toEqual([])
  // Dan kartunya sendiri tidak menyembunyikan apa pun di balik gulir.
  expect(result.scrollWidth).toBeLessThanOrEqual(result.clientWidth + 1)
  expect(result.scrollHeight).toBeLessThanOrEqual(result.clientHeight + 1)

  // KEEMPAT UKURAN BERTUMPUK: top menaik, left sama.
  expect(result.measureBoxes).toHaveLength(4)

  for (let index = 1; index < result.measureBoxes.length; index += 1) {
    expect(result.measureBoxes[index].top).toBeGreaterThan(result.measureBoxes[index - 1].top)
    expect(result.measureBoxes[index].left).toBe(result.measureBoxes[0].left)
  }
}

/**
 * Tabel rekap BERTUMPUK MENJADI KARTU di dalam kartunya, bukan menggulir
 * mendatar — DIUKUR DARI BENTUK YANG TERLIHAT: tiap sel menempati barisnya
 * sendiri (kotaknya TURUN, bukan berderet), tiap sel membawa data-label yang
 * disalin direktif dari <th>-nya, dan tidak ada isi yang disembunyikan di
 * balik gulir mendatar.
 *
 * KOREKSI ATAS HURUF SKENARIO 31 butir (3) — lihat docblock berkas, koreksi
 * (a): menggulir mendatar di dalam kartu adalah bentuk LAMA yang dibuang
 * temuan QA LP-M-02/LP-M-03, dan view ini tidak menimpa
 * `.detail-table-wrap` dari src/assets/stacked-table.css. Yang berlaku pada
 * 390px karena itu `overflow-x: visible` + bentuk kartu, dan itulah yang
 * diukur di sini. Kelas tanpa definisi akan menjawab 'visible' juga, jadi nama
 * kelasnya diasersi terpisah dan bentuk bertumpuknya DIUKUR.
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
 * PATH ASETNYA DIAMBIL DARI DOKUMEN ITU SENDIRI (atribut `href` setiap
 * `link[rel="stylesheet"]`), bukan dari glob berkas: pada audit sebelumnya glob
 * aset tidak cocok sehingga satu stylesheet tidak pernah terbaca dan audit
 * melaporkan 14 CACAT PALSU dengan nada seyakin cacat nyata. Lembar yang
 * cssRules-nya tidak dapat dibaca DIAMBIL ULANG lewat href-nya dan dipindai
 * sebagai teks, dan jumlah lembar yang BENAR-BENAR terbaca dikembalikan supaya
 * audit yang tidak membaca apa pun memerah alih-alih menuduh.
 *
 * DILINGKUP KE SUBPOHON akar layar ini: kerangka aplikasi (App.vue, jam
 * mengambang, bubble AI) membawa kelasnya sendiri yang bukan tanggung jawab
 * layar ini.
 */
async function expectEveryClassDefined(page: Page): Promise<void> {
  await expect(page.getByTestId('laporan-kernel-dispatch-mobile')).toBeVisible()

  const result = await page.evaluate(async () => {
    const defined = new Set<string>()
    const classPattern = /\.(-?[_a-zA-Z]+[_a-zA-Z0-9-]*)/g

    const walk = (rules: CSSRuleList): void => {
      for (const rule of Array.from(rules)) {
        const styleRule = rule as CSSStyleRule & { cssRules?: CSSRuleList }

        if (typeof styleRule.selectorText === 'string') {
          for (const match of styleRule.selectorText.matchAll(classPattern)) {
            defined.add(match[1])
          }
        }

        if (styleRule.cssRules) {
          walk(styleRule.cssRules)
        }
      }
    }

    let sheetsRead = 0
    let sheetsFetched = 0
    const unreadable: string[] = []

    for (const sheet of Array.from(document.styleSheets)) {
      try {
        walk(sheet.cssRules)
        sheetsRead += 1
      } catch {
        // Lembar yang tidak dapat dibaca langsung DIAMBIL ULANG lewat href
        // MILIK DOKUMEN INI — bukan lewat glob berkas sumber.
        unreadable.push(sheet.href ?? '(inline)')
      }
    }

    // Dan setiap <link rel=stylesheet> diperiksa atas href-nya sendiri,
    // sehingga lembar yang gagal terpasang pun tidak lolos tanpa tanda.
    const hrefs = (Array.from(
      document.querySelectorAll('link[rel="stylesheet"]'),
    ) as HTMLLinkElement[]).map((link) => link.href)

    for (const href of [...new Set([...hrefs, ...unreadable])]) {
      if (href === '(inline)') {
        continue
      }

      try {
        const text = await (await fetch(href)).text()

        for (const match of text.matchAll(classPattern)) {
          defined.add(match[1])
        }

        sheetsFetched += 1
      } catch {
        // Dicatat lewat selisih jumlah di bawah.
      }
    }

    const root = document.querySelector('[data-testid="laporan-kernel-dispatch-mobile"]')
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
      sheetsRead,
      sheetsFetched,
      linkHrefs: hrefs.length,
      missing: [...used].filter((name) => !defined.has(name)),
    }
  })

  // DIASERSI LEBIH DULU: audit yang tidak membaca satu lembar gaya pun akan
  // melaporkan setiap kelas sebagai cacat. Angka-angka ini yang membedakan
  // "tidak ada definisi" dari "tidak pernah dibaca".
  expect(
    result.sheetsRead + result.sheetsFetched,
    `nol lembar gaya terbaca (link: ${result.linkHrefs})`,
  ).toBeGreaterThan(0)
  expect(result.definedCount).toBeGreaterThan(50)
  expect(result.used).toBeGreaterThan(20)
  expect(result.missing, `kelas tanpa definisi: ${result.missing.join(', ')}`).toEqual([])
}

function numberOf(text: string): number {
  return Number(text.replace(/\./g, '').replace(',', '.'))
}

/** Penjumlahan kolom Pengiriman sebuah rekap, DARI DOM. */
async function sumDispatchCounts(page: Page, recapTestId: string): Promise<number> {
  const counts = page.locator(`[data-testid^="${recapTestId}-dispatch-count-"]`)

  // toHaveCount/toBeVisible menunggu sendiri — allInnerTexts() di bawah karena
  // itu tidak pernah membaca halaman setengah render.
  await expect(counts.first()).toBeVisible()

  const values = await counts.allInnerTexts()

  return values.map((value) => numberOf(value.trim())).reduce((total, value) => total + value, 0)
}

/* ================================================================== */
/* JALUR SUKSES, ANGKA, DAN BENTUK                                     */
/* ================================================================== */

test.describe('Laporan Kernel Dispatch Mobile (screen-165)', () => {
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
      'kd-kpi-dispatch-count': '12',
      'kd-kpi-net-weight-total': '999,0',
      'kd-kpi-net-weight-avg': '777,0',
      'kd-kpi-net-weight-dispatch-count': '5',
      'kd-kpi-missing-net-weight': '7',
      'kd-kpi-not-released': '5',
      'kd-draft-dispatch-count': '4',
      'kd-coverage-percent': '88,8',
      'kd-coverage-days-with-dispatch': '9',
      'kd-coverage-denominator': '26',
      'kd-coverage-days-in-period': '30',
      'kd-compliance-missing-waybill': '3',
      'kd-compliance-missing-seal-top': '2',
      'kd-compliance-missing-seal-bottom': '4',
      'kd-daily-total-dispatch-count': '11',
      'kd-daily-total-net-weight-total': '55,0',
      'kd-daily-total-net-weight-dispatch-count': '3',
      'kd-daily-total-net-weight-avg': '7,25',
    }

    for (const [testId, value] of Object.entries(expected)) {
      await expect(page.getByTestId(testId), testId).toHaveText(value)
    }

    // KEDUA BELAS ANGKA MUTU + KEEMPAT PENYEBUTNYA, dari payload apa adanya.
    // Format mengikuti formatQuality (min 2 / max 3 desimal) — lihat koreksi
    // (c) pada docblock berkas.
    const quality: Record<string, string> = {
      'kd-quality-kernel-moisture-min': '9,00',
      'kd-quality-kernel-moisture-avg': '3,00',
      'kd-quality-kernel-moisture-max': '4,00',
      'kd-quality-kernel-moisture-count': '11',
      'kd-quality-dirt-impurities-min': '0,00',
      'kd-quality-dirt-impurities-avg': '0,00',
      'kd-quality-dirt-impurities-max': '0,00',
      'kd-quality-dirt-impurities-count': '7',
      'kd-quality-ffa-min': '0,025',
      'kd-quality-ffa-avg': '0,50',
      'kd-quality-ffa-max': '1,20',
      'kd-quality-ffa-count': '3',
      'kd-quality-broken-kernel-min': '1,50',
      'kd-quality-broken-kernel-avg': '2,00',
      'kd-quality-broken-kernel-max': '3,00',
      'kd-quality-broken-kernel-count': '9',
    }

    for (const [testId, value] of Object.entries(quality)) {
      await expect(page.getByTestId(testId), testId).toHaveText(value)
    }

    await expect(page.getByTestId('period-status-badge')).toHaveText('Terbuka')
    await expect(page.getByTestId('period-meta')).toContainText(PERIOD.name)

    // SELURUH wadah tingkat atas ADA, dalam urutan yang sama dengan web.
    for (const testId of [
      'kd-card-tonnage',
      'kd-card-quality',
      'kd-card-missing-net-weight',
      'kd-not-released',
      ...RECAP_TESTIDS,
      'kd-compliance',
      'kd-daily-recap',
      'kd-coverage',
      'kd-findings',
      'kd-draft',
      'kd-absences',
      'kd-export-csv',
    ]) {
      await expect(page.getByTestId(testId), testId).toBeVisible()
    }

    // Kontrol lipat BAWAANNYA TERBUKA: kelima tabel terbaca tanpa satu ketukan.
    for (const testId of ALL_RECAP_TESTIDS) {
      await expect(page.getByTestId(recapTableTestId(testId)), testId).toBeVisible()
    }

    await expectSingleColumn(page)
    await expectNoHorizontalPageScroll(page)

    expect(pageErrors, pageErrors.join(' | ')).toHaveLength(0)
    expect(consoleErrors, consoleErrors.join(' | ')).toHaveLength(0)
  })

  test('skenario 9: berat bersih dan persen dibaca apa adanya — 777,0 dan 88,8, bukan hasil hitung', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    await expect(page.getByTestId('kd-kpi-net-weight-total')).toHaveText('999,0')
    // 999 / 5 = 199,8 — angka itu TIDAK BOLEH muncul.
    await expect(page.getByTestId('kd-kpi-net-weight-avg')).toHaveText('777,0')
    await expect(page.getByTestId('kd-kpi-net-weight-avg')).not.toHaveText('199,8')
    // 9 / 26 = 34,6… — juga tidak boleh muncul.
    await expect(page.getByTestId('kd-coverage-percent')).toHaveText('88,8')
    await expect(page.getByTestId('kd-coverage-percent')).not.toHaveText('34,6')
    // missing_net_weight DIBACA, bukan diturunkan dari 12 − 5 = 7 yang
    // kebetulan sama; fixture di bawah membuktikan kebetulan itu tidak dipakai.
    await expect(page.getByTestId('kd-kpi-missing-net-weight')).toHaveText('7')

    // avg mutu DI LUAR rentang min..max — mustahil berasal dari penghitungan
    // apa pun, dan itulah jantung asersi ini.
    await expect(page.getByTestId('kd-quality-kernel-moisture-min')).toHaveText('9,00')
    await expect(page.getByTestId('kd-quality-kernel-moisture-avg')).toHaveText('3,00')
    await expect(page.getByTestId('kd-quality-kernel-moisture-max')).toHaveText('4,00')

    // daily_total BERBEDA dari blok dispatches, dan perbedaan itu dibiarkan.
    await expect(page.getByTestId('kd-daily-total-dispatch-count')).toHaveText('11')
    await expect(page.getByTestId('kd-kpi-dispatch-count')).toHaveText('12')
  })

  test('skenario 10: seluruh pengiriman belum selesai ditimbang — garis, bukan nol, dan mutu tetap berangka', async ({
    page,
  }) => {
    await stubApi(page, {
      summary: makeSummary({
        dispatches: {
          dispatch_count: 8,
          net_weight_total: null,
          net_weight_avg: null,
          net_weight_dispatch_count: 0,
          missing_net_weight_dispatch_count: 8,
        },
      }),
    })
    await openFullReport(page)

    await expect(page.getByTestId('kd-kpi-net-weight-total')).toHaveText('—')
    await expect(page.getByTestId('kd-kpi-net-weight-avg')).toHaveText('—')
    await expect(page.getByTestId('kd-kpi-net-weight-dispatch-count')).toHaveText('0')
    await expect(page.getByTestId('kd-kpi-missing-net-weight')).toHaveText('8')
    await expect(page.getByTestId('kd-kpi-dispatch-count')).toHaveText('8')

    // NOL pil satuan 'MT' di kedua kartu berat — bentuk '0,0 MT' memang
    // mustahil: satuannya ada di pil SEBELAHNYA, dan pil itu tidak dirender
    // ketika nilainya null.
    for (const card of ['kd-metric-net-weight-total', 'kd-metric-net-weight-avg']) {
      await expect(page.getByTestId(card).locator('.metric-unit-pill'), card).toHaveCount(0)
    }

    // Kartu penimbangan-belum-selesai BERANGKA, dan kalimatnya berbunyi
    // 'penimbangan belum selesai' — bukan 'data tidak lengkap'.
    await expect(page.getByTestId('kd-card-missing-net-weight')).toContainText(
      'penimbangan belum selesai',
    )

    // DAN MUTUNYA TIDAK IKUT KOSONG: berat dan mutu adalah kolom yang berbeda.
    await expect(page.getByTestId('kd-quality-kernel-moisture-avg')).toHaveText('3,00')
    await expect(page.getByTestId('kd-quality-dirt-impurities-avg')).toHaveText('0,00')
  })

  test('skenario 11: rata-rata dan penyebutnya di dalam SATU kotak pembatas — DIUKUR', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    // LOCATOR BERSARANG di bawah testid kartunya — bukan kedekatan teks.
    const avgCard = page.getByTestId('kd-metric-net-weight-avg')

    await expect(avgCard.getByTestId('kd-kpi-net-weight-avg')).toHaveText('777,0')
    await expect(avgCard.getByTestId('kd-kpi-net-weight-dispatch-count')).toHaveText('5')

    // DAN GEOMETRINYA: penyebut berada DI DALAM kotak pembatas kartu
    // rata-rata, jadi keduanya terbaca tanpa menggulir di antara keduanya.
    const geometry = await avgCard.evaluate((card) => {
      const cardRect = card.getBoundingClientRect()
      const denominator = card.querySelector(
        '[data-testid="kd-kpi-net-weight-dispatch-count"]',
      ) as HTMLElement
      const value = card.querySelector('[data-testid="kd-kpi-net-weight-avg"]') as HTMLElement
      const denominatorRect = denominator.getBoundingClientRect()
      const valueRect = value.getBoundingClientRect()

      return {
        card: { top: cardRect.top, bottom: cardRect.bottom, left: cardRect.left, right: cardRect.right },
        denominator: {
          top: denominatorRect.top,
          bottom: denominatorRect.bottom,
          left: denominatorRect.left,
          right: denominatorRect.right,
        },
        value: { top: valueRect.top, bottom: valueRect.bottom },
        viewportHeight: window.innerHeight,
      }
    })

    expect(geometry.denominator.top).toBeGreaterThanOrEqual(geometry.card.top - 1)
    expect(geometry.denominator.bottom).toBeLessThanOrEqual(geometry.card.bottom + 1)
    expect(geometry.denominator.left).toBeGreaterThanOrEqual(geometry.card.left - 1)
    expect(geometry.denominator.right).toBeLessThanOrEqual(geometry.card.right + 1)
    // Dan jarak tegak antara angka dan penyebutnya lebih kecil dari satu layar
    // penuh — tidak ada gulir di antara keduanya.
    expect(geometry.denominator.bottom - geometry.value.top).toBeLessThan(geometry.viewportHeight)

    // Penyebut KEEMPAT ukuran mutu pun berada di dalam kartunya masing-masing.
    for (const slug of QUALITY_SLUGS) {
      await expect(
        page.getByTestId(`kd-quality-card-${slug}`).getByTestId(`kd-quality-${slug}-count`),
        slug,
      ).toBeVisible()
    }
  })

  test('skenario 12: kartu mutu LANGSUNG menyusul kartu tonase, dan keduanya tidak dapat dilipat', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    // KEDEKATANNYA DIBACA DARI DOM: elemen bertestid pertama SESUDAH
    // kd-card-tonnage wajib kd-card-quality — bukan "terlihat berdekatan".
    const neighbour = await page.evaluate(() => {
      let node = document.querySelector('[data-testid="kd-card-tonnage"]')?.nextElementSibling

      while (node && !node.hasAttribute('data-testid')) {
        node = node.nextElementSibling
      }

      return node?.getAttribute('data-testid') ?? null
    })

    expect(neighbour).toBe('kd-card-quality')

    // Dan posisi tegaknya: mutu TURUN tepat di bawah tonase.
    const boxes = await page.evaluate(() => {
      const rect = (testid: string) =>
        document.querySelector(`[data-testid="${testid}"]`)!.getBoundingClientRect()

      return { tonnage: rect('kd-card-tonnage'), quality: rect('kd-card-quality') }
    })

    expect(boxes.quality.top).toBeGreaterThanOrEqual(boxes.tonnage.bottom - 1)

    // NOL kontrol lipat DI DALAM kedua kartu — dicari lewat testid, bukan
    // lewat nama kelas.
    for (const card of ['kd-card-tonnage', 'kd-card-quality']) {
      await expect(page.getByTestId(card).locator('[data-testid$="-toggle"]'), card).toHaveCount(0)
    }

    // MELIPAT rekap pengangkut dan rekap harian: keduanya keluar dari DOM
    // (v-if, bukan v-show), sementara kedua kartu TETAP terbuka berangka.
    await recapToggle(page, 'kd-recap-transporter').click()
    await expect(page.getByTestId('kd-recap-transporter-table')).toHaveCount(0)
    await recapToggle(page, 'kd-daily-recap').click()
    await expect(page.getByTestId('kd-daily-table')).toHaveCount(0)

    await expect(page.getByTestId('kd-kpi-dispatch-count')).toHaveText('12')
    await expect(page.getByTestId('kd-quality-kernel-moisture-avg')).toHaveText('3,00')
    await expect(page.getByTestId('kd-card-tonnage')).toBeVisible()
    await expect(page.getByTestId('kd-card-quality')).toBeVisible()
  })

  test('skenario 13: empat ukuran mutu bertumpuk dalam satu kartu, ketiga angkanya terbaca, nol galat console', async ({
    page,
  }) => {
    const pageErrors: string[] = []

    page.on('pageerror', (error) => pageErrors.push(error.message))

    await stubApi(page)
    await openFullReport(page)

    const labels: Record<string, string> = {
      'kernel-moisture': 'Kadar Air Inti',
      'dirt-impurities': 'Kadar Kotoran',
      ffa: 'Kadar Asam Lemak Bebas (ALB)',
      'broken-kernel': 'Kadar Inti Pecah',
    }

    for (const slug of QUALITY_SLUGS) {
      const card = page.getByTestId(`kd-quality-card-${slug}`)

      await expect(card, slug).toBeVisible()
      await expect(card, slug).toContainText(labels[slug])

      for (const part of ['min', 'avg', 'max', 'count']) {
        await expect(card.getByTestId(`kd-quality-${slug}-${part}`), `${slug}-${part}`).toBeVisible()
      }
    }

    // Kartu mutu tidak memotong isinya, dan keempat ukurannya BERTUMPUK.
    await expectQualityCardDoesNotClipItsContent(page)

    expect(pageErrors, pageErrors.join(' | ')).toHaveLength(0)
  })

  test('skenario 14: empat penyebut BERBEDA satu dengan lainnya dan berbeda dari jumlah pengiriman', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    const denominators: string[] = []

    for (const slug of QUALITY_SLUGS) {
      denominators.push(
        (await page.getByTestId(`kd-quality-${slug}-count`).innerText()).trim(),
      )
    }

    expect(denominators).toEqual(['11', '7', '3', '9'])
    // EMPAT ANGKA BERBEDA — tidak satu pun meminjam penyebut dari yang lain.
    expect(new Set(denominators).size).toBe(4)
    // DAN tidak satu pun sama dengan jumlah pengiriman periode.
    await expect(page.getByTestId('kd-kpi-dispatch-count')).toHaveText('12')
    expect(denominators).not.toContain('12')

    // Prosa penyebut DI DALAM kartu mutu, UTUH, dan sama persis dengan
    // konstanta service.
    const note = page.getByTestId('kd-card-quality').getByTestId('kd-quality-note')

    await expect(note).toBeVisible()
    await expect(note).toHaveText(NOTE_QUALITY_DENOMINATORS)
    await expectProseFullyReadable(page, 'kd-quality-note')
  })

  test('skenario 15: satu ukuran tanpa hasil uji menampilkan garis TANPA mengosongkan tiga lainnya', async ({
    page,
  }) => {
    await stubApi(page, {
      summary: makeSummary({
        quality: qualityOfFour({ ffa_percent: measure(null, null, null, 0) }),
      }),
    })
    await openFullReport(page)

    // Ukuran yang kosong: ketiga angkanya garis, penyebutnya 0, dan
    // keterangannya SENDIRI terender.
    for (const part of ['min', 'avg', 'max']) {
      await expect(page.getByTestId(`kd-quality-ffa-${part}`), part).toHaveText('—')
    }

    await expect(page.getByTestId('kd-quality-ffa-count')).toHaveText('0')
    await expect(page.getByTestId('kd-quality-unmeasured-ffa')).toBeVisible()
    // Pil '%' tidak berdiri di samping tanda pisah.
    await expect(page.getByTestId('kd-quality-card-ffa').locator('.metric-unit-pill')).toHaveCount(0)

    // KETIGA UKURAN LAIN TETAP BERANGKA — v-if ada di tingkat UKURAN, bukan
    // tingkat kartu.
    await expect(page.getByTestId('kd-quality-kernel-moisture-avg')).toHaveText('3,00')
    await expect(page.getByTestId('kd-quality-dirt-impurities-avg')).toHaveText('0,00')
    await expect(page.getByTestId('kd-quality-broken-kernel-avg')).toHaveText('2,00')

    for (const slug of ['kernel-moisture', 'dirt-impurities', 'broken-kernel']) {
      await expect(page.getByTestId(`kd-quality-unmeasured-${slug}`), slug).toHaveCount(0)
    }

    // Kartunya TIDAK HILANG, dan kalimat 'keempatnya kosong' TIDAK terbit.
    await expect(page.getByTestId('kd-card-quality')).toBeVisible()
    await expect(page.locator('[data-testid^="kd-quality-card-"]')).toHaveCount(4)
    await expect(page.getByTestId('kd-quality-unmeasured-all')).toHaveCount(0)

    // Dan nol TERUKUR tidak tertukar dengan ketiadaan: kadar kotoran bernilai
    // '0,00' dengan pil '%' TETAP terpasang.
    await expect(
      page.getByTestId('kd-quality-card-dirt-impurities').locator('.metric-unit-pill'),
    ).toHaveCount(1)
  })

  test('skenario 16: ketiga rekap teks bebas menjumlah ke 12, urutannya milik server, baris null TIDAK di akhir', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    await expect(page.getByTestId('kd-kpi-dispatch-count')).toHaveText('12')

    for (const testId of FREE_TEXT_RECAP_TESTIDS) {
      expect(await sumDispatchCounts(page, testId), testId).toBe(12)
    }

    // URUTAN BARIS = URUTAN PAYLOAD, dan kelompok 'tidak tercatat' bertonase
    // TERBESAR duduk di posisi KEDUA — bukan dibuang ke baris terakhir.
    const transporter = page.locator('[data-testid^="kd-recap-transporter-value-"]')

    await expect(transporter.first()).toBeVisible()
    expect((await transporter.allInnerTexts()).map((value) => value.trim())).toEqual([
      'Zahra Trans',
      'tidak tercatat',
      'CV Angkutan Inti',
      'PT Kirim Cepat',
    ])

    // Baris null itu memang bertonase terbesar — jadi posisinya bukan
    // kebetulan urutan alfabetis.
    await expect(page.getByTestId('kd-recap-transporter-net-weight-total-1')).toHaveText('21,25')
    await expect(page.getByTestId('kd-recap-transporter-dispatch-count-1')).toHaveText('5')

    // Dan baris 'tidak tercatat' muncul TEPAT SEKALI pada setiap rekap.
    for (const testId of RECAP_TESTIDS) {
      const values = page.locator(`[data-testid^="${testId}-value-"]`)

      await expect(values.first(), testId).toBeVisible()

      const texts = (await values.allInnerTexts()).map((value) => value.trim())

      expect(texts.filter((value) => value === 'tidak tercatat'), testId).toHaveLength(1)
    }
  })

  test('skenario 17: silo sumber adalah TEKS — nilainya apa adanya, nol permintaan tambahan', async ({
    page,
  }) => {
    const state = await stubApi(page, {
      summary: makeSummary({
        by_silo_source: [
          row('SILO-07', 6, 18.5, 2, 9.25),
          row(null, 5, 12.5, 2, 6.25),
          // Berbentuk seperti UUID, dan TETAP tidak dicari namanya.
          row('9f1c2b7e-0000-4a11-9c3d-5e6f70819aa1', 1, 6.5, 1, 6.5),
        ],
      }),
    })

    await openFullReport(page)

    const urlsBefore = state.urls.length
    const values = page.locator('[data-testid^="kd-recap-silo-source-value-"]')

    await expect(values.first()).toBeVisible()
    expect((await values.allInnerTexts()).map((value) => value.trim())).toEqual([
      'SILO-07',
      'tidak tercatat',
      '9f1c2b7e-0000-4a11-9c3d-5e6f70819aa1',
    ])

    // NOL permintaan tambahan sesudah /summary: tidak ada pencarian nama silo.
    await expect.poll(() => state.urls.length).toBe(urlsBefore)
    expect(state.hits.summary).toBe(1)
  })

  test('skenario 18: tiga ejaan yang hanya berbeda huruf tetap TIGA baris, dan baris berfrekuensi satu tidak dilipat', async ({
    page,
  }) => {
    const variants = [
      row('PT Mitra Kernel', 4, 12.5, 2, 6.25),
      row('pt mitra kernel', 3, 8.5, 1, 8.5),
      row('PT MITRA KERNEL', 4, 6.5, 1, 6.5),
      row('Pembeli Langka', 1, 2.5, 1, 2.5),
    ]

    await stubApi(page, { summary: makeSummary({ by_destination_buyer: variants }) })
    await openFullReport(page)

    const values = page.locator('[data-testid^="kd-recap-destination-buyer-value-"]')

    await expect(values.first()).toBeVisible()
    await expect(values).toHaveCount(4)
    expect((await values.allInnerTexts()).map((value) => value.trim())).toEqual(
      variants.map((item) => item.value),
    )

    // Baris berfrekuensi SATU tetap punya barisnya sendiri, dan nol kelompok
    // 'lainnya' DI DALAM kartu rekapnya.
    await expect(page.getByTestId('kd-recap-destination-buyer-dispatch-count-3')).toHaveText('1')
    await expect(
      page.getByTestId('kd-recap-destination-buyer').getByText(/^lainnya$/i),
    ).toHaveCount(0)
    expect(await sumDispatchCounts(page, 'kd-recap-destination-buyer')).toBe(12)
  })

  test('skenario 19: label status gerbang dari kosakata bersama, kode mentahnya TIDAK tercetak di sel nilai', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    const values = page.locator('[data-testid^="kd-recap-gate-status-value-"]')

    await expect(values.first()).toBeVisible()

    const texts = (await values.allInnerTexts()).map((value) => value.trim())

    // 'Released' / 'Not Released' datang dari OPTION_LABELS, bukan dari peta
    // buatan view — dan ejaannya persis sama dengan Display::OPTION_LABELS.
    expect(texts).toEqual(['Released', 'tidak tercatat', 'held_by_security', 'Not Released'])
    // KODE MENTAH kedua nilai yang DIKENAL tidak pernah tercetak sebagai nilai
    // sel; dicari atas isi sel, bukan atas sapuan prosa seluruh halaman —
    // keterangan rekap ini memang menyebut kedua pilihan itu.
    expect(texts).not.toContain('released')
    expect(texts).not.toContain('not_released')
  })

  test('skenario 20: nilai di luar kosakata bersama punya barisnya sendiri dan tetap ikut menjumlah', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    const rows = page.locator('[data-testid^="kd-recap-gate-status-row-"]')

    await expect(rows.first()).toBeVisible()
    // TEPAT EMPAT baris. Penamaan sel yang ikut berprefiks '-row-' akan
    // memulangkan delapan di sini — lihat koreksi (b) pada docblock berkas.
    await expect(rows).toHaveCount(4)

    // Nilai tak dikenal APA ADANYA, dengan barisnya sendiri — tidak dipaksa
    // masuk 'Released'/'Not Released' dan tidak dilipat ke 'tidak tercatat'.
    await expect(page.getByTestId('kd-recap-gate-status-value-2')).toHaveText('held_by_security')
    await expect(page.getByTestId('kd-recap-gate-status-dispatch-count-2')).toHaveText('1')

    // DAN PENJUMLAHANNYA TETAP 12: membuang nilai tak dikenal akan
    // memecahkannya, dan penjumlahan inilah yang menangkap klien yang
    // 'merapikan'.
    expect(await sumDispatchCounts(page, 'kd-recap-gate-status')).toBe(12)
    await expect(page.getByTestId('kd-kpi-dispatch-count')).toHaveText('12')
  })

  test('skenario 21: tidak dilepas DITONJOLKAN di luar rekap, dan baris tidak tercatat TIDAK ikut angkanya', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    // Angka tidak-dilepas berada di SECTION-NYA SENDIRI, di luar rekap yang
    // dapat dilipat — dan nilainya 5, dari server.
    await expect(page.getByTestId('kd-not-released')).toBeVisible()
    await expect(page.getByTestId('kd-not-released').getByTestId('kd-kpi-not-released')).toHaveText(
      '5',
    )
    await expect(
      page.getByTestId('kd-recap-gate-status').getByTestId('kd-kpi-not-released'),
    ).toHaveCount(0)

    // Baris 'tidak tercatat' hidup DI DALAM rekap dengan nilainya SENDIRI (2),
    // dan 2 itu tidak dijumlahkan ke dalam angka tidak-dilepas.
    await expect(page.getByTestId('kd-recap-gate-status-value-1')).toHaveText('tidak tercatat')
    await expect(page.getByTestId('kd-recap-gate-status-dispatch-count-1')).toHaveText('2')
    // 5 + 2 = 7 — angka gabungan itu tidak boleh muncul sebagai angka
    // tidak-dilepas.
    await expect(page.getByTestId('kd-kpi-not-released')).not.toHaveText('7')

    // Dan baris 'not_released' di rekap membawa angka yang SAMA dengan kartunya
    // — satu fakta, satu angka.
    await expect(page.getByTestId('kd-recap-gate-status-dispatch-count-3')).toHaveText('5')
  })

  test('skenario 22: tiga penghitung kepatuhan terpisah, nol angka gabungan segel, prosanya utuh', async ({
    page,
  }) => {
    const state = await stubApi(page)

    await openFullReport(page)

    await expect(page.getByTestId('kd-compliance-missing-waybill')).toHaveText('3')
    await expect(page.getByTestId('kd-compliance-missing-seal-top')).toHaveText('2')
    await expect(page.getByTestId('kd-compliance-missing-seal-bottom')).toHaveText('4')

    // NOL ANGKA GABUNGAN — dicari lewat testid, bukan lewat sapuan teks '6':
    // '6' muncul di tempat lain yang sah di layar ini.
    for (const testId of [
      'kd-compliance-missing-seal',
      'kd-compliance-missing-seal-total',
      'kd-compliance-seal-total',
      'kd-compliance-total',
      'kd-compliance-avg',
    ]) {
      await expect(page.getByTestId(testId), testId).toHaveCount(0)
    }

    await expect(page.locator('[data-testid^="kd-compliance-chart"]')).toHaveCount(0)
    await expect(page.getByTestId('kd-compliance').locator('canvas')).toHaveCount(0)

    // Prosanya UTUH dan sama persis dengan konstanta service.
    await expect(page.getByTestId('kd-compliance-note')).toHaveText(NOTE_COMPLIANCE)
    await expectProseFullyReadable(page, 'kd-compliance-note')

    // Periode kedua: ketiganya NOL dan TETAP tercetak — nol itulah yang
    // memberi tahu pembaca bahwa pemeriksaannya berjalan.
    state.summary = makeSummary({
      compliance: {
        missing_waybill_count: 0,
        missing_seal_top_count: 0,
        missing_seal_bottom_count: 0,
        _note: NOTE_COMPLIANCE,
      },
    })

    await page.getByTestId('period-select').selectOption(PERIOD_CLOSED.id)

    await expect(page.getByTestId('kd-compliance-missing-waybill')).toHaveText('0')
    await expect(page.getByTestId('kd-compliance-missing-seal-top')).toHaveText('0')
    await expect(page.getByTestId('kd-compliance-missing-seal-bottom')).toHaveText('0')
  })

  test('skenario 23: draft IKUT terhitung dan jumlahnya dinyatakan, nol penyaring status', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    await expect(page.getByTestId('kd-draft-dispatch-count')).toHaveText('4')
    await expect(page.getByTestId('kd-draft-note')).toContainText('IKUT terhitung')

    // NOL PENYARING STATUS — lewat testid, dan lewat jumlah <select> yang
    // benar-benar ada di layar: hanya Production Line dan Periode (Operator
    // tidak melihat pemilih Mill).
    for (const testId of ['kd-status-filter', 'kd-draft-filter', 'status-select', 'kd-draft-toggle']) {
      await expect(page.getByTestId(testId), testId).toHaveCount(0)
    }

    const selects = page.getByTestId('laporan-kernel-dispatch-mobile').locator('select')

    await expect(selects).toHaveCount(2)
    await expect(page.getByTestId('production-line-select')).toBeVisible()
    await expect(page.getByTestId('period-select')).toBeVisible()
  })

  test('skenario 24: periode TERTUTUP tetap terbaca penuh dan tetap dapat diekspor', async ({
    page,
  }) => {
    await stubApi(page)
    await openReport(page)
    await pickLine(page)
    await pickPeriod(page, PERIOD_CLOSED.id)

    await expect(page.getByTestId('period-status-badge')).toHaveText('Tertutup')
    await expect(page.locator('[data-testid^="kd-card-"]')).toHaveCount(3)
    await expect(page.getByTestId('kd-kpi-dispatch-count')).toHaveText('12')
    await expect(page.getByTestId('kd-quality-kernel-moisture-avg')).toHaveText('3,00')
    await expect(page.getByTestId('kd-export-csv')).toBeEnabled()

    const downloadPromise = page.waitForEvent('download')

    await page.getByTestId('kd-export-csv').click()

    const download = await downloadPromise

    expect(download.suggestedFilename()).toMatch(/\.csv$/)
  })

  test('skenario 25: rekap harian TEPAT sembilan baris bertanggal + baris total, kelengkapan dalam satuan hari', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    const rows = page.locator('[data-testid^="kd-daily-row-"]')

    await expect(rows.first()).toBeVisible()
    await expect(rows).toHaveCount(9)
    await expect(page.getByTestId('kd-daily-total')).toBeVisible()

    // NOL baris bertanggal TANPA pengiriman: himpunan tanggalnya tepat
    // sembilan tanggal payload, dalam urutan payload.
    const dates = await rows.evaluateAll((elements) =>
      elements.map((element) =>
        (element.getAttribute('data-testid') ?? '').replace('kd-daily-row-', ''),
      ),
    )

    expect([...dates].sort()).toEqual([...LOADED_DAYS].sort())
    expect(dates).toEqual(DAILY_ORDER)

    // Kelengkapan diukur dalam HARI, dan penyebutnya hari yang sudah LEWAT.
    await expect(page.getByTestId('kd-coverage-days-with-dispatch')).toHaveText('9')
    await expect(page.getByTestId('kd-coverage-denominator')).toHaveText('26')
    await expect(page.getByTestId('kd-coverage-days-in-period')).toHaveText('30')
    await expect(page.getByTestId('kd-coverage-running-note')).toBeVisible()
    await expectProseFullyReadable(page, 'kd-coverage-note')
  })

  test('skenario 26: undated 0 → nol blok; undated 3 → blok ada dengan angka 3', async ({
    page,
  }) => {
    const state = await stubApi(page)

    await openFullReport(page)

    // event_date NOT NULL di skema, jadi inilah keadaan produksi: TIDAK ADA
    // satu elemen pun yang mencetak "nol pengiriman tanpa tanggal".
    await expect(page.getByTestId('kd-kpi-dispatch-count')).toBeVisible()
    await expect(page.getByTestId('kd-undated-dispatch-count')).toHaveCount(0)
    await expect(page.getByTestId('kd-undated-note')).toHaveCount(0)

    // Cabang > 0 tetap diuji — lewat muatan stub, bukan lewat data nyata.
    state.summary = makeSummary({ undated_dispatch_count: 3 })

    await page.getByTestId('period-select').selectOption(PERIOD_CLOSED.id)

    await expect(page.getByTestId('kd-undated-dispatch-count')).toHaveCount(1)
    await expect(page.getByTestId('kd-undated-dispatch-count')).toHaveText('3')
    await expect(page.getByTestId('kd-undated-note')).toBeVisible()
  })

  test('skenario 27: nol sebaran per jam, nol waktu putar, nol penandaan di luar batas — dan ketiga sebabnya utuh', async ({
    page,
  }) => {
    await stubApi(page, {
      summary: makeSummary({
        // Kunci yang TIDAK PERNAH diterbitkan server, dibawa sengaja.
        hourly: [{ hour: 7, dispatch_count: 2 }],
        busiest_hour: 7,
        turnaround_minutes_avg: 42,
        quality_thresholds: [{ measure: 'ffa_percent', max: 3 }],
      }),
    })
    await openFullReport(page)

    // ASERSI LEWAT TESTID DAN ROLE — BUKAN lewat pemindaian prosa: layar ini
    // WAJIB merender kalimat yang MEMUAT 'jam', 'waktu putar', 'batas' dan
    // bahkan menyebut CPO Dispatch, seluruhnya untuk menerangkan ketiadaannya
    // SENDIRI.
    await expect(page.locator('[data-testid^="kd-hourly"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="kd-turnaround"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="kd-threshold"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="kd-quality-warning"]')).toHaveCount(0)

    for (const testId of [
      'kd-busiest-hour',
      'kd-empty-hour-count',
      'kd-turnaround-avg',
      'kd-quality-threshold-badge',
      'hourly-dispatch',
      'hourly-receive',
    ]) {
      await expect(page.getByTestId(testId), testId).toHaveCount(0)
    }

    await expect(page.getByRole('heading', { name: /per jam/i })).toHaveCount(0)
    await expect(page.getByRole('heading', { name: /waktu putar/i })).toHaveCount(0)

    // KETIGA PROSA SERVER, UTUH, dan sama persis dengan konstanta service.
    await expect(page.getByTestId('kd-note-no-hourly')).toHaveText(NOTE_NO_HOURLY)
    await expectProseFullyReadable(page, 'kd-note-no-hourly')
    await expect(page.getByTestId('kd-note-no-quality-thresholds')).toHaveText(
      NOTE_NO_QUALITY_THRESHOLDS,
    )
    await expectProseFullyReadable(page, 'kd-note-no-quality-thresholds')
    await expect(page.getByTestId('kd-note-shift')).toHaveText(NOTE_SHIFT_NOT_PUBLISHED)
    await expectProseFullyReadable(page, 'kd-note-shift')
  })

  test('skenario 28: SATU daftar teks bebas, bukan dua — dan keadaan kosongnya pun dinyatakan', async ({
    page,
  }) => {
    const state = await stubApi(page)

    await openFullReport(page)

    await expect(page.getByTestId('kd-findings-row-0')).toBeVisible()
    await expect(page.getByTestId('kd-findings-row-0')).toContainText('Segel bawah')
    await expect(page.getByTestId('kd-findings-row-0')).toHaveAttribute('data-date', LOADED_DAYS[0])
    await expect(page.locator('[data-testid^="kd-findings-row-"]')).toHaveCount(1)

    // NOL BAGIAN KEDUA — lewat testid dan nama kuncinya, bukan lewat sapuan
    // teks: keterangan bagian ini JUSTRU menyebut kata 'catatan'.
    for (const testId of ['kd-remarks', 'kd-remarks-empty', 'kd-remarks-row-0', 'kd-text-remarks']) {
      await expect(page.getByTestId(testId), testId).toHaveCount(0)
    }

    await expect(page.locator('[data-testid^="kd-remarks"]')).toHaveCount(0)

    // Kunci `findings` tetap diterbitkan meski kosong — bagiannya TETAP
    // tampil, dengan pernyataan 'tidak ada', bukan dihilangkan dari layar.
    state.summary = makeSummary({ text_entries: { findings: [] } })

    await page.getByTestId('period-select').selectOption(PERIOD_CLOSED.id)

    await expect(page.getByTestId('kd-findings')).toBeVisible()
    await expect(page.getByTestId('kd-findings-empty')).toBeVisible()
    await expect(page.locator('[data-testid^="kd-findings-row-"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="kd-remarks"]')).toHaveCount(0)
  })

  test('skenario 8: periode tanpa satu pengiriman pun — kerangka penuh, keadaan kosong menyebut periode dan line, ekspor tetap ada', async ({
    page,
  }) => {
    await stubApi(page, { summary: emptyPeriodSummary() })
    await openFullReport(page)

    await expect(page.getByTestId('kd-empty-period-note')).toBeVisible()
    await expect(page.getByTestId('kd-empty-period-note')).toContainText(PERIOD.name)
    await expect(page.getByTestId('kd-empty-period-note')).toContainText(LINE_1.name)

    await expect(page.getByTestId('kd-kpi-dispatch-count')).toHaveText('0')
    await expect(page.getByTestId('kd-kpi-net-weight-total')).toHaveText('—')
    await expect(page.getByTestId('kd-kpi-net-weight-avg')).toHaveText('—')

    // KERANGKANYA TETAP PENUH — termasuk kartu mutunya, yang keempat
    // ukurannya ditulis sebagai tanda pisah beserta kalimat penjelasnya.
    await expect(page.locator('[data-testid^="kd-quality-card-"]')).toHaveCount(4)
    await expect(page.getByTestId('kd-quality-unmeasured-all')).toBeVisible()

    for (const slug of QUALITY_SLUGS) {
      await expect(page.getByTestId(`kd-quality-${slug}-avg`), slug).toHaveText('—')
      await expect(page.getByTestId(`kd-quality-unmeasured-${slug}`), slug).toBeVisible()
    }

    // Rekap kosong punya BARIS KETERANGANNYA, bukan tabel yang menghilang.
    for (const testId of RECAP_TESTIDS) {
      await expect(page.getByTestId(`${testId}-empty`), testId).toBeVisible()
    }

    await expect(page.getByTestId('kd-daily-empty')).toBeVisible()
    await expect(page.getByTestId('kd-findings-empty')).toBeVisible()
    await expect(page.getByTestId('kd-coverage')).toBeVisible()
    await expect(page.getByTestId('kd-coverage-days-in-period')).toHaveText('30')

    // DAN EKSPORNYA TETAP TERSEDIA: berkas kosong adalah jawaban yang sah.
    await expect(page.getByTestId('kd-export-csv')).toBeEnabled()

    // Nol pil satuan di kedua kartu berat.
    for (const card of ['kd-metric-net-weight-total', 'kd-metric-net-weight-avg']) {
      await expect(page.getByTestId(card).locator('.metric-unit-pill'), card).toHaveCount(0)
    }
  })

  test('skenario 31: tabel BERTUMPUK jadi kartu, halaman tidak bergeser, kartu mutu tidak memotong isinya — SEMUANYA DIUKUR', async ({
    page,
  }) => {
    const consoleErrors: string[] = []

    page.on('pageerror', (error) => consoleErrors.push(error.message))

    const state = await stubApi(page, {
      summary: makeSummary({
        // REKAP PANJANG: nilai teks bebas yang panjang adalah yang paling
        // mudah melebarkan halaman.
        by_transporter: Array.from({ length: 14 }, (_unused, index) =>
          row(
            `PT Pengangkutan Inti Nusantara Cabang Nomor ${index + 1}`,
            index === 0 ? 12 : 0,
            index % 3 === 0 ? null : 1.5 + index,
            index % 3 === 0 ? 0 : 1,
            index % 3 === 0 ? null : 1.5 + index,
          ),
        ),
      }),
    })

    await openFullReport(page)

    const requestsBefore = state.hits.summary
    const numbersBefore = await page.locator('[data-testid^="kd-kpi-"]').allInnerTexts()

    // (1) + bentuk bertumpuk: KELIMA tabel, masing-masing DIUKUR.
    for (const testId of ALL_RECAP_TESTIDS) {
      await expectRecapStacksIntoCards(page, recapTableTestId(testId))
    }

    // (2) HALAMAN TIDAK BERGESER MENDATAR, diukur SESUDAH kartu mutu dan
    // kelima rekap terender.
    await expectNoHorizontalPageScroll(page)

    // (3) KARTU MUTU TIDAK MEMOTONG ISINYA — geometri kartu lawan isinya.
    await expectQualityCardDoesNotClipItsContent(page)

    // Dan setiap kelas yang dipakai markup punya definisi di CSS HASIL
    // COMPILE, dengan path aset dari dokumen itu sendiri.
    await expectEveryClassDefined(page)

    // MELIPAT dan MEMBUKA kembali: nol permintaan, nol angka berubah.
    for (const testId of ALL_RECAP_TESTIDS) {
      await recapToggle(page, testId).click()
      await expect(page.getByTestId(recapTableTestId(testId)), testId).toHaveCount(0)
      await recapToggle(page, testId).click()
      await expect(page.getByTestId(recapTableTestId(testId)), testId).toBeVisible()
    }

    expect(state.hits.summary).toBe(requestsBefore)
    expect(await page.locator('[data-testid^="kd-kpi-"]').allInnerTexts()).toEqual(numbersBefore)

    // Dan ketiga pengukuran tetap berlaku sesudahnya.
    await expectRecapStacksIntoCards(page, 'kd-daily-table')
    await expectNoHorizontalPageScroll(page)
    await expectQualityCardDoesNotClipItsContent(page)

    expect(consoleErrors, consoleErrors.join(' | ')).toHaveLength(0)
  })

  test('tabel menjadi TABEL SUNGGUHAN di atas 640px, dan halaman tetap tidak bergeser mendatar', async ({
    page,
  }) => {
    await stubApi(page)
    await openFullReport(page)

    // Bentuk kartu dipasang pada @media (max-width: 640px) saja — di jendela
    // lebar pembacanya memang lebih mudah membandingkan baris secara
    // mendatar. Diperiksa DI SINI supaya klaim "bertumpuk di 390px" tidak
    // dibaca sebagai "selalu bertumpuk".
    await page.setViewportSize({ width: 900, height: 844 })
    await expect(page.getByTestId('kd-daily-table')).toBeVisible()

    const shape = await page.getByTestId('kd-daily-table').evaluate((element) => {
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
})

/* ================================================================== */
/* PRODUCTION LINE, PERIODE, EKSPOR, DAN KEADAAN                       */
/* ================================================================== */

test.describe('Laporan Kernel Dispatch Mobile — cakupan, ekspor, dan keadaan', () => {
  test.use({ viewport: PHONE })

  test('skenario 2: line yang diingat perangkat dipakai tanpa diminta memilih, tetap dapat diganti', async ({
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

    // Terpilih otomatis — nol langkah pengguna — dan PEMILIHNYA TETAP ADA
    // dengan nilai line itu.
    await expect(page.getByTestId('production-line-select').locator('option:checked')).toHaveText(
      LINE_1.name,
    )
    await expect(page.getByTestId('production-line-required-hint')).toHaveCount(0)

    // PERIODE TIDAK PERNAH DIPILIHKAN: sampai ia dipilih, nol permintaan.
    await expect.poll(() => state.hits.summary).toBe(0)

    await pickPeriod(page)
    await expect(page.locator('[data-testid^="kd-card-"]')).toHaveCount(3)

    expect(new URL(state.urls[state.urls.length - 1]).searchParams.get('production_line_id')).toBe(
      LINE_1.id,
    )

    // MENGGANTI LINE MEMUAT ULANG ANGKANYA — dan alamat terakhir membawa line
    // yang baru.
    await page.getByTestId('production-line-select').selectOption(LINE_2.id)
    await expect(page.getByTestId('production-line-current')).toContainText(LINE_2.name)
    await expect.poll(() => state.hits.summary).toBe(2)

    expect(new URL(state.urls[state.urls.length - 1]).searchParams.get('production_line_id')).toBe(
      LINE_2.id,
    )
  })

  test('skenario 2: line yang diingat tetapi TIDAK LAGI SAH dibuang, bukan dipakai diam-diam', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    const userId = await getAuthUserId(page)
    const state = await stubApi(page)

    // Line yang sudah tidak ada di daftar server.
    await page.addInitScript(
      ([key, value]) => {
        window.localStorage.setItem(key, value)
      },
      [rememberedLineKey(userId), 'pl-sudah-dihapus'] as const,
    )

    await openReport(page)

    await expectNothingSelected(page, 'production-line-select', 'Pilih Production Line')
    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()
    await expect.poll(() => state.hits.summary).toBe(0)

    // Dan ingatan yang tidak sah tidak pernah ikut ke satu alamat pun.
    await pickPeriod(page)
    await expect.poll(() => state.hits.summary).toBe(0)

    for (const url of state.urls) {
      expect(new URL(url).searchParams.get('production_line_id'), url).not.toBe('pl-sudah-dihapus')
    }
  })

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
    await expect(page.locator('[data-testid^="kd-card-"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="kd-kpi-"]')).toHaveCount(0)
    // Tombol Ekspor CSV tidak ada di DOM.
    await expect(page.getByTestId('kd-export-csv')).toHaveCount(0)
    // Arahan memilih line TERLIHAT.
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

  test('TIDAK memilihkan line, bahkan ketika mill hanya punya SATU', async ({ page }) => {
    await login(page, USERS.operator)

    const state = await stubApi(page, { productionLines: [LINE_1] })

    await openReport(page)

    await expectNothingSelected(page, 'production-line-select', 'Pilih Production Line')
    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()
    await expect.poll(() => state.hits.summary).toBe(0)

    await pickLine(page)
    await pickPeriod(page)

    await expect(page.locator('[data-testid^="kd-card-"]')).toHaveCount(3)
    await expect.poll(() => state.hits.summary).toBe(1)
  })

  test('skenario 4: Storage.prototype.getItem melempar — layar tetap merender dan alur berlanjut', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    const pageErrors: string[] = []

    page.on('pageerror', (error) => pageErrors.push(error.message))

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
    // NOL ELEMEN GALAT — kegagalan mengingat pilihan bukan galat.
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('error-message')).toHaveCount(0)

    // DAN MEMILIH LINE SECARA MANUAL TETAP MENERBITKAN ANGKANYA.
    await pickLine(page)
    await pickPeriod(page)

    await expect(page.locator('[data-testid^="kd-card-"]')).toHaveCount(3)
    await expect(page.getByTestId('kd-kpi-dispatch-count')).toHaveText('12')
    await expect.poll(() => state.hits.summary).toBe(1)
    expect(pageErrors, pageErrors.join(' | ')).toHaveLength(0)
  })

  test('skenario 6: 422 akun tanpa mill — arahan menghubungi Admin, nol pesan jaringan, nol tombol coba lagi', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    const state = await stubApi(page, {
      periods: [],
      periodsStatus: 422,
      periodsMessage: 'Akun Anda belum terhubung ke mill. Hubungi Admin.',
    })

    await openReport(page)

    await expect(page.getByTestId('no-mill-for-account')).toBeVisible()
    await expect(page.getByTestId('no-mill-for-account')).toContainText('hubungi Admin')
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect(page.getByTestId('retry-button')).toHaveCount(0)
    await expect(page.getByTestId('production-line-retry')).toHaveCount(0)
    await expect(page.locator('[data-testid^="kd-card-"]')).toHaveCount(0)
    // Daftar seluruh mill TIDAK ditawarkan sebagai jalan keluar.
    await expect(page.getByTestId('mill-select')).toHaveCount(0)
    await expect.poll(() => state.hits.summary).toBe(0)
  })

  test('skenario 7: mill tanpa periode yang mencakup stasiun ini — kalimat sebabnya, nol permintaan /summary', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    const state = await stubApi(page, { periods: [] })

    await openReport(page)
    await pickLine(page)

    await expect(page.getByTestId('no-periods')).toBeVisible()
    // Menyebut STASIUN INI — bukan kalimat umum yang sama untuk 18 stasiun.
    await expect(page.getByTestId('no-periods')).toContainText('Kernel Dispatch')
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    await expect.poll(() => state.hits.summary).toBe(0)
    await expect(page.locator('[data-testid^="kd-card-"]')).toHaveCount(0)
  })

  test('skenario 29: giliran kerja nol di layar, DAN 27 kolom di berkas yang benar-benar diunduh', async ({
    page,
  }) => {
    await login(page, USERS.operator)
    await stubApi(page)
    await openFullReport(page)

    // NOL kolom Shift di DOM layar — lewat ROLE dan TESTID.
    await expect(page.getByRole('columnheader', { name: /^Shift$/i })).toHaveCount(0)
    await expect(page.locator('[data-testid^="kd-recap-shift"]')).toHaveCount(0)
    await expect(page.getByTestId('kd-shift-column')).toHaveCount(0)
    await expect(page.getByTestId('kd-shift-filter')).toHaveCount(0)
    await expect(page.getByTestId('kd-note-shift')).toHaveText(NOTE_SHIFT_NOT_PUBLISHED)

    // DAN BERKASNYA: 27 kolom, DIBACA DARI DISK.
    const downloadPromise = page.waitForEvent('download')

    await page.getByTestId('kd-export-csv').click()

    const download = await downloadPromise
    const path = await download.path()

    expect(path).not.toBeNull()

    const header = readFileSync(path as string, 'utf-8').split('\n')[0]

    // 6 kolom konteks + 21 kolom detail. Angka ini DISALIN dari
    // EXPORT_HEADER, bukan disesuaikan supaya cocok dengan stub.
    expect(header.split(',')).toHaveLength(27)
    expect(header.split(',')).toEqual(EXPORT_HEADER)

    // SATU NEEDLE PER PEMANGGILAN: toContain(a, b) memperlakukan argumen kedua
    // sebagai NEEDLE KEDUA, bukan sebagai pesan.
    for (const column of [
      'Shift',
      'Weighbridge Ticket No',
      'Waybill Number',
      'Vehicle Plate No',
      'Driver Name',
      'Weighbridge Operator ID',
      'Rentang Periode',
    ]) {
      expect(header, column).toContain(column)
    }

    expect(download.suggestedFilename()).toMatch(/\.csv$/)
  })

  test('skenario 30: ekspor CSV saja, nol tombol Excel, dan dua ketukan cepat = SATU unduhan', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    const state = await stubApi(page, { exportDelayMs: 1500 })
    const downloads: string[] = []

    page.on('download', (download) => downloads.push(download.suggestedFilename()))

    await openFullReport(page)

    // NOL TOMBOL EXCEL — lewat ROLE dan TESTID, bukan sapuan 'excel'/'xlsx':
    // kd-export-scope-note JUSTRU menyebut berkas lembar kerja biner, dan
    // sapuan teks akan lulus atas prosa layar itu sendiri.
    await expect(page.getByRole('button', { name: /xlsx|excel/i })).toHaveCount(0)
    await expect(page.getByTestId('kd-export-xlsx')).toHaveCount(0)
    await expect(page.getByTestId('kd-export-excel')).toHaveCount(0)
    await expect(page.locator('[data-testid^="kd-export-"]')).toHaveCount(2)

    const button = page.getByTestId('kd-export-csv')

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

    // Dan alamatnya membawa format=csv beserta kedua parameter lingkup.
    const exportUrl = state.urls.find((url) => url.includes('/export'))

    expect(exportUrl).toBeTruthy()

    const params = new URL(exportUrl as string).searchParams

    expect(new URL(exportUrl as string).pathname).toBe('/api/kernel-dispatch-reports/export')
    expect(params.get('period_id')).toBe(PERIOD.id)
    expect(params.get('production_line_id')).toBe(LINE_1.id)
    expect(params.get('format')).toBe('csv')
    expect(params.get('format')).not.toBe('xlsx')
  })

  test('skenario 32: LURING SEJAK AWAL — nol permintaan ke kelima jalur, DAPAT DIBANTAH penghitungnya', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    const state = await stubApi(page)

    /**
     * LURINGNYA DIPALSUKAN DI `navigator.onLine`, BUKAN LEWAT
     * context.setOffline() — dan perbedaannya menentukan, bukan selera.
     * setOffline(true) memutus jaringan pada tingkat BROWSER, jadi pengambilan
     * dokumen dan bundle dari Vite dev server gagal: navigasinya mati dengan
     * ERR_INTERNET_DISCONNECTED dan aplikasinya TIDAK PERNAH DIJALANKAN.
     * Terbukti pada screen-161.
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

    await openReport(page)

    await expect(page.getByTestId('offline-notice')).toBeVisible()
    await expect(page.getByTestId('offline-notice')).toContainText('memerlukan jaringan')
    await expect(page.getByTestId('offline-notice')).toContainText(
      'tidak menampilkan satu angka pun dari pemuatan sebelumnya',
    )

    // NOL kartu tonase, NOL kartu mutu, NOL rekap di DOM.
    await expect(page.locator('[data-testid^="kd-card-"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="kd-quality-card-"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="kd-kpi-"]')).toHaveCount(0)

    for (const testId of ALL_RECAP_TESTIDS) {
      await expect(page.getByTestId(testId), testId).toHaveCount(0)
    }

    await expect(page.getByTestId('laporan-kernel-dispatch-mobile')).toBeVisible()

    // KELIMA jalur diperiksa satu per satu, dan TEPAT 0.
    await expect.poll(() => state.hits.periods).toBe(0)
    await expect.poll(() => state.hits.lines).toBe(0)
    await expect.poll(() => state.hits.summary).toBe(0)
    await expect.poll(() => state.hits.units).toBe(0)
    await expect.poll(() => state.hits.export).toBe(0)
  })

  test('skenario 33: luring SESUDAH angka termuat — angkanya menghilang, nol angka tertinggal', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    const state = await stubApi(page)

    // LAYARNYA DIBUKA DAN ANGKANYA DIMUAT LEBIH DULU.
    await openFullReport(page)
    await expect(page.getByTestId('kd-kpi-dispatch-count')).toHaveText('12')

    const before = { ...state.hits }

    // TANPA reload dan TANPA setOffline: navigator.onLine dipalsukan di
    // halaman yang SUDAH berjalan, lalu event 'offline' dipicu — itulah jalur
    // yang benar-benar dilalui perangkat yang kehilangan sinyal.
    await page.evaluate(() => {
      Object.defineProperty(window.navigator, 'onLine', {
        configurable: true,
        get: () => false,
      })
      window.dispatchEvent(new Event('offline'))
    })

    await expect(page.getByTestId('offline-notice')).toBeVisible()
    await expect(page.locator('[data-testid^="kd-card-"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="kd-quality-card-"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="kd-kpi-"]')).toHaveCount(0)
    await expect(page.getByTestId('kd-daily-table')).toHaveCount(0)

    // Dan luring tidak memicu satu permintaan baru pun.
    await expect.poll(() => state.hits.summary).toBe(before.summary)
    await expect.poll(() => state.hits.periods).toBe(before.periods)

    // LISTENER DILEPAS SAAT UNMOUNT: berpindah layar lalu memicu 'offline'
    // lagi tidak boleh menyentuh layar yang sudah tidak ada.
    const pageErrors: string[] = []

    page.on('pageerror', (error) => pageErrors.push(error.message))

    await page.getByTestId('back-button').click()
    await expectPathname(page, '/dashboard-reporting')

    await page.evaluate(() => {
      window.dispatchEvent(new Event('offline'))
      window.dispatchEvent(new Event('online'))
    })

    await expect(page.getByTestId('laporan-kernel-dispatch-mobile')).toHaveCount(0)
    await expect.poll(() => state.hits.summary).toBe(before.summary)
    expect(pageErrors, pageErrors.join(' | ')).toHaveLength(0)
  })

  test('skenario 34: sesi berakhir (401) mengarahkan ke Login tanpa pesan jaringan', async ({
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
    await expect(page.locator('[data-testid^="kd-card-"]')).toHaveCount(0)
  })

  test('skenario 34: 403 SESUDAH pemuatan pertama berhasil — arahan memilih line, nol angka tertinggal', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    // Permintaan PERTAMA berhasil, permintaan KEDUA 403: itulah satu-satunya
    // cara menguji bahwa angka yang SUDAH tampil benar-benar dibersihkan.
    const state = await stubApi(page, {
      summaryStatus: 403,
      summaryStatusFromHit: 2,
      summaryMessage: 'Anda tidak memiliki akses untuk aksi ini.',
    })

    await openFullReport(page)
    await expect(page.getByTestId('kd-kpi-dispatch-count')).toHaveText('12')

    await page.getByTestId('period-select').selectOption(PERIOD_CLOSED.id)

    await expect(page.getByTestId('production-line-rejected')).toBeVisible()
    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()
    await expect(page.getByTestId('network-error')).toHaveCount(0)
    // NOL ANGKA TERTINGGAL: kartu tonase DAN kartu mutu sama-sama hilang.
    await expect(page.locator('[data-testid^="kd-card-"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="kd-quality-card-"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="kd-kpi-"]')).toHaveCount(0)
    await expectNothingSelected(page, 'production-line-select', 'Pilih Production Line')

    expect(state.hits.summary).toBe(2)
  })

  test('skenario 35: /summary gagal sekali — keadaan galat + Coba Lagi, lalu angka terbit; TEPAT dua permintaan', async ({
    page,
  }) => {
    await login(page, USERS.operator)

    const state = await stubApi(page, { summaryAbortOnce: true })

    await openReport(page)
    await pickLine(page)
    await pickPeriod(page)

    await expect(page.getByTestId('network-error')).toBeVisible()
    await expect(page.getByTestId('retry-button')).toBeVisible()
    await expect(page.locator('[data-testid^="kd-card-"]')).toHaveCount(0)

    await page.getByTestId('retry-button').click()

    await expect(page.locator('[data-testid^="kd-card-"]')).toHaveCount(3)
    await expect(page.getByTestId('kd-kpi-dispatch-count')).toHaveText('12')
    // TEPAT 2 — bukan 3.
    await expect.poll(() => state.hits.summary).toBe(2)

    // Dan penjagaan line tetap berlaku sesudah Coba Lagi: mengosongkan line
    // menghentikan permintaan berikutnya.
    await page.getByTestId('production-line-select').selectOption({ label: 'Pilih Production Line' })
    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()
    await page.getByTestId('period-select').selectOption(PERIOD_CLOSED.id)
    await expect.poll(() => state.hits.summary).toBe(2)
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

    const downloadPromise = page.waitForEvent('download')

    await page.getByTestId('kd-export-csv').click()
    await downloadPromise

    expect(writes, writes.join(' | ')).toHaveLength(0)
  })

  test('Operator tidak melihat pemilih Mill, dan business_unit_id tidak ada di satu alamat pun', async ({
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

test.describe('Laporan Kernel Dispatch Mobile — Admin memilih mill', () => {
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

  test('skenario 5: Admin memilih mill, lalu menggantinya — kedua pemilih kosong, nol angka mill lama', async ({
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

    await expect(page.locator('[data-testid^="kd-card-"]')).toHaveCount(3)
    await expect(page.getByTestId('kd-kpi-dispatch-count')).toHaveText('12')

    // business_unit_id IKUT pada alamat yang tertangkap.
    const summaryUrl = state.urls.filter((url) => url.includes('/summary')).pop()

    expect(new URL(summaryUrl as string).searchParams.get('business_unit_id')).toBe('bu-9')

    // GANTI MILL: kedua pemilih kosong, nol kartu angka, nol kartu mutu, dan
    // arahan memilih line + periode terlihat kembali.
    await page.getByTestId('mill-select').selectOption('bu-1')

    await expectNothingSelected(page, 'production-line-select', 'Pilih Production Line')
    await expectNothingSelected(page, 'period-select', 'Pilih Periode')
    await expect(page.locator('[data-testid^="kd-card-"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="kd-quality-card-"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="kd-kpi-"]')).toHaveCount(0)
    await expect(page.getByTestId('production-line-required-hint')).toBeVisible()
  })
})

/* ================================================================== */
/* PINTU MASUK DARI REPORTING (screen-141)                             */
/* ================================================================== */

/**
 * Tabel `station` lokal diisi `seedDefaultStationsIfNeeded()` saat login
 * (satu baris per jenis stasiun, termasuk 'kernel-dispatch'), tetapi layar
 * Reporting membacanya PER PRODUCTION LINE. Dibuka lewat layar Daftar Stasiun
 * supaya alur aplikasi sendiri yang memilih line dan mengisi relasinya — bukan
 * lewat penyemaian langsung. TIDAK ADA TULISAN KE BASIS DATA DEV di sini: yang
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
   * 'kernel-dispatch' pada REPORT_ROUTES di ReportingPilihStasiunView.vue —
   * DENGAN TANDA HUBUNG dan TERKUTIP. Kunci bergaris-bawah akan terkompilasi,
   * terlihat benar, dan tidak pernah cocok dengan satu station.type pun. Tanpa
   * test ini, layar laporan bisa lengkap, seluruh test lainnya hijau, dan
   * tile-nya tetap kelabu — pola kegagalan yang sudah terjadi sekali pada
   * laporan Cages & Tracks versi web.
   */
  test('skenario 36: tile Kernel Dispatch aktif dan menavigasi ke layar laporannya', async ({
    page,
  }) => {
    const consoleErrors: string[] = []

    page.on('pageerror', (error) => consoleErrors.push(error.message))

    await login(page, USERS.operator)

    const state = await stubApi(page)

    await seedLocalStations(page)
    await goToReports(page)

    const tile = page.getByTestId('station-tile-kernel-dispatch')

    await tile.scrollIntoViewIfNeeded()
    await expect(tile).toBeVisible()
    // AKTIF, bukan kelabu — aria-disabled, bukan atribut disabled native.
    await expect(tile).toHaveAttribute('aria-disabled', 'false')
    await expect(tile).not.toContainText('Belum tersedia')

    await tile.click()

    // Alamatnya membawa ?production_line_id= dari layar itu, jadi yang
    // dibandingkan adalah PATHNAME-nya saja.
    await expectPathname(page, ROUTE)
    await expect(page.getByTestId('laporan-kernel-dispatch-mobile')).toBeVisible()
    await expect(page.getByTestId('info-message')).toHaveCount(0)

    /**
     * LINE-NYA BENAR-BENAR TERBAWA: layar Reporting menempelkan
     * ?production_line_id= pada alamat, dan id itu datang dari tabel `station`
     * LOKAL — yaitu id Production Line SUNGGUHAN milik basis data dev, bukan
     * id fiktif stub ini. Karena itu ia tidak dapat cocok dengan daftar line
     * stub apa adanya, dan layar yang BENAR memang menolak memakai line yang
     * tidak ada di daftar server (resolveProductionLine membuang yang tidak
     * sah alih-alih mengirimkannya).
     *
     * Jadi yang dilakukan di sini adalah menambahkan id ITU ke daftar line
     * stub lalu memuat ulang halaman: dengan begitu jalur "line dari rute"
     * teruji UTUH — id yang dititipkan layar Reporting adalah id yang terpilih
     * DAN id yang dikirim ke /summary — alih-alih dilewati dengan memilih line
     * secara manual.
     */
    const carriedLineId = new URL(page.url()).searchParams.get('production_line_id')

    expect(carriedLineId, 'layar Reporting tidak menitipkan production_line_id').toBeTruthy()

    state.productionLines = [
      { id: carriedLineId as string, name: 'Line Dari Reporting', code: 'LR' },
      LINE_2,
    ]

    await page.reload()
    await expect(page.getByTestId('laporan-kernel-dispatch-mobile')).toBeVisible()
    await expect(page.getByTestId('production-line-select').locator('option:checked')).toHaveText(
      'Line Dari Reporting',
    )

    // Dan kerangka laporannya benar-benar terbit pada 390x844 — line sudah
    // terbawa dari layar Reporting, jadi tinggal periodenya yang dipilih.
    await pickPeriod(page)
    await expect(page.locator('[data-testid^="kd-card-"]')).toHaveCount(3)
    await expect(page.locator('[data-testid^="kd-quality-card-"]')).toHaveCount(4)

    expect(
      new URL(state.urls[state.urls.length - 1]).searchParams.get('production_line_id'),
    ).toBe(carriedLineId)

    expect(consoleErrors, consoleErrors.join(' | ')).toHaveLength(0)
  })
})
