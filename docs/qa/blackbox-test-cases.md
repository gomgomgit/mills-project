# Mill Smart Log — Test Case Blackbox (Web & Mobile)

**Versi dokumen:** 1.0 · **Tanggal:** 8 Oktober 2026
**Cakupan:** 154 layar yang sudah terimplementasi (dari 157 yang dirancang), aplikasi Web dan Mobile.

---

## 0. Cara memakai dokumen ini

Dokumen ini ditulis untuk diuji **tanpa melihat kode**. Setiap kasus hanya memakai apa yang
terlihat di layar: tombol, kolom, pesan, dan angka.

### 0.1 Mengapa ada "pola" dan "matriks stasiun"

Sistem ini punya **18 jenis stasiun** (Weighbridge, Grading, Cages Track, … Process Quality
Control). Untuk setiap stasiun ada layar yang **bentuknya sama persis**:

| Aplikasi | Layar per stasiun | Jumlah |
|---|---|---|
| Mobile | Monitor, Form, Data Preview | 3 × 18 = 54 |
| Web | Data Browser, Detail, Form | 3 × 18 = 54 |

Menulis 18 salinan nyaris identik akan membuat dokumen ini 6× lebih panjang **tanpa menambah
satu pun pertanyaan baru** — dan dokumen yang tak terbaca habis adalah dokumen yang tidak
dijalankan. Jadi:

- **Kasus polanya ditulis SEKALI** (mis. `MB-FORM-01`).
- **Matriks stasiun** menyebut apa yang berbeda per stasiun (nama kolom wajib, jumlah kolom
  bacaan, keanehan khusus).
- **Cara menjalankan:** satu kasus pola dijalankan **18 kali**, satu per stasiun, memakai
  kolom dari matriksnya. Catat hasilnya sebagai `MB-FORM-01 / Weighbridge`,
  `MB-FORM-01 / Grading`, dan seterusnya.

Layar yang **tidak** berpola (Login, Dashboard, Master Data, Laporan) ditulis satu per satu.

### 0.2 Format setiap kasus

```
ID — Judul
  Peran            : siapa yang menjalankan
  Prasyarat        : keadaan yang harus ada SEBELUM langkah pertama
  Langkah          : urutan tindakan
  Hasil diharapkan : apa yang harus terlihat
  Prioritas        : Tinggi / Sedang / Rendah
```

**Prioritas** dibaca begini: **Tinggi** = kalau ini gagal, fitur tidak boleh dirilis.
**Sedang** = gagal berarti pengguna terganggu tapi masih bisa bekerja. **Rendah** = kosmetik
atau kasus sangat jarang.

### 0.3 Awalan ID

| Awalan | Bagian |
|---|---|
| `LS-` | Aturan lintas-sistem — berlaku di banyak layar |
| `AU-` | Autentikasi & Akun |
| `MB-` | Mobile — operasional stasiun |
| `WB-` | Web — data stasiun |
| `LP-` | Laporan periode stasiun (web & mobile) |
| `DS-` | Dashboard & laporan lain |
| `MD-` | Master data |
| `AD-` | User & Role, Pengaturan Mill |

### 0.4 Kata yang dipakai konsisten

| Istilah | Artinya di dokumen ini |
|---|---|
| **Mill** | Pabrik. Di layar kadang tertulis "Business Unit". Satu mill punya beberapa Production Line. |
| **Production Line** | Jalur produksi di dalam satu mill. Satu stasiun menempel pada satu line. |
| **Slot waktu** | Jam pencatatan. Ada **24 slot**, mulai **07:00** lalu berurutan sampai **06:00** keesokan harinya. |
| **Record** | Satu lembar data stasiun untuk satu tanggal dan satu unit. Berisi beberapa baris slot waktu. |
| **Baris terisi** | Baris slot waktu yang **minimal satu** kolom bacaannya diisi. Baris kosong tidak dihitung. |
| **Periode Pelaporan** | Rentang tanggal yang dipakai laporan. Dibuat Admin. Punya status per jenis stasiun. |
| **Draft / Dijeda** | Status record di mobile yang belum selesai. Di layar keduanya ditampilkan sebagai "Pause". |
| **Tersimpan / Tersinkron** | Record mobile yang sudah final (Tersimpan) dan yang sudah terkirim ke server (Tersinkron). |

---

## 1. Lingkungan & akun uji

### 1.1 Yang harus siap sebelum menguji

| Hal | Keterangan |
|---|---|
| Aplikasi Web | Dibuka di browser desktop. Lebar layar minimal 1280px untuk layar tabel. |
| Aplikasi Mobile | Dibuka di perangkat atau emulator. **Lebar acuan 390px.** |
| Koneksi | Perlu bisa **dimatikan** untuk menguji mode offline mobile. |
| Data master | Minimal: 1 Corporate, 1 Company, 2 Mill, masing-masing 2 Production Line, dan stasiun aktif untuk ke-18 jenis. |
| Periode Pelaporan | Minimal satu periode yang mencakup hari ini, dengan stasiun berstatus **Terbuka**. |

### 1.2 Akun uji yang dibutuhkan

Siapkan **minimal 6 akun** supaya kasus isolasi mill bisa diuji:

| Kode akun | Peran | Mill | Dipakai untuk |
|---|---|---|---|
| `OPR-A` | Station Operator | Mill A | Alur input mobile |
| `SPV-A` | Supervisor | Mill A | Input + verifikasi "Checked" |
| `MGR-A` | Mill Management | Mill A | Verifikasi "Acknowledged", Pengaturan Mill |
| `SPV-B` | Supervisor | **Mill B** | Menguji data Mill A tidak terlihat |
| `ADM` | Admin | — (tidak terikat mill) | Master data, Periode Pelaporan |
| `NOMILL` | Supervisor | **tanpa mill** | Menguji sistem menolak dengan jelas |

> **Catatan penting untuk QA:** akun `NOMILL` sengaja dibuat cacat. Beberapa kasus menguji
> bahwa sistem **menolak dengan pesan yang menyebut sebabnya** alih-alih menampilkan halaman
> kosong — halaman kosong terbaca sebagai "tidak ada data", padahal yang benar adalah
> "akun Anda belum diatur".

### 1.3 Matriks peran — siapa boleh apa

Ini acuan untuk seluruh kasus `LS-01`. "—" berarti tidak boleh.

| Kemampuan | Operator | Supervisor | Mill Management | Admin |
|---|---|---|---|---|
| Login Mobile | ✓ | ✓ | ✓ | ✓ (bukan alur yang dirancang) |
| Login Web | ✓ **terbatas** | ✓ | ✓ | ✓ |
| Input data stasiun (Mobile) | ✓ | ✓ | ✓ | — |
| Input data stasiun (Web) | — | ✓ | ✓ | ✓ |
| Isi **Checked By** | — | ✓ | — | ✓ |
| Isi **Acknowledged By** | — | — | ✓ | ✓ |
| Lihat Data Browser / Detail (Web) | — | ✓ | ✓ | ✓ |
| Lihat "Data Saya" (Web) | ✓ | ✓ | — | — |
| Laporan periode stasiun (Web) | — | ✓ | ✓ | ✓ |
| Laporan periode stasiun (Mobile) | ✓ | ✓ | ✓ | ✓ |
| Dashboard (Web) | — | ✓ | ✓ | ✓ |
| Laporan Manajemen | — | — | ✓ | ✓ |
| Master data (Corporate…Mesin) | — | — | — | ✓ |
| Periode Pelaporan: tambah/ubah/hapus | — | — | — | ✓ |
| **Tutup / Buka Periode** | — | — | — | ✓ **(hanya Admin)** |
| Pengaturan Mill | — | — | ✓ (mill sendiri) | ✓ (semua mill) |
| Kelola User & Role | — | — | — | ✓ |
| Pemilih **Mill** pada laporan | — | — | — | ✓ **(hanya Admin)** |

**Akses web Operator dibatasi pada:** Beranda Operator, Ganti Password, dan **Data Saya**.
Seluruh alamat lain harus menjawab **halaman 403 (Akses Ditolak)** — bukan halaman kosong,
bukan diarahkan diam-diam ke beranda.

---

## 2. Aturan lintas-sistem (`LS-`)

Kasus di bagian ini **berlaku di banyak layar sekaligus**. Jalankan sekali per layar yang
disebut di kolom "Jalankan di". Kalau salah satu gagal, besar kemungkinan ia gagal di
banyak tempat — laporkan sebagai satu temuan, bukan sepuluh.

### LS-01 — Peran yang tidak berhak ditolak dengan halaman 403, bukan halaman kosong
- **Peran:** semua, bergantian
- **Prasyarat:** matriks peran §1.3 dibaca lebih dulu
- **Langkah:**
  1. Login sebagai satu peran.
  2. Buka langsung alamat layar yang **menurut matriks tidak boleh** diaksesnya (ketik alamatnya, jangan lewat menu).
  3. Ulangi untuk setiap kombinasi peran × layar yang ditandai "—".
- **Hasil diharapkan:** muncul halaman **403 / Akses Ditolak berbahasa Indonesia**, di dalam kerangka aplikasi (masih ada tombol Logout). Bukan halaman putih, bukan pesan error teknis, bukan diarahkan diam-diam ke beranda.
- **Jalankan di:** seluruh layar Web
- **Prioritas:** Tinggi

### LS-02 — Menu yang tidak boleh diakses juga tidak muncul di sidebar
- **Peran:** semua, bergantian
- **Prasyarat:** —
- **Langkah:**
  1. Login sebagai satu peran.
  2. Baca seluruh isi sidebar.
- **Hasil diharapkan:** sidebar hanya memuat menu yang peran itu boleh buka. Tidak ada menu yang terlihat tapi menjawab 403 saat diklik.
- **Prioritas:** Tinggi

### LS-03 — Data mill lain tidak pernah terlihat
- **Peran:** `SPV-A`, lalu `SPV-B`
- **Prasyarat:** Mill A dan Mill B keduanya punya record pada tanggal yang sama
- **Langkah:**
  1. Login `SPV-A`. Buka Data Browser salah satu stasiun. Catat jumlah data dan nama line yang muncul.
  2. Logout. Login `SPV-B`. Buka layar yang sama.
- **Hasil diharapkan:** `SPV-B` **tidak** melihat satu pun record Mill A, dan **tidak** melihat satu pun nama Production Line Mill A di daftar pilihan mana pun.
- **Jalankan di:** Data Browser, Detail, Form, seluruh Laporan Periode, Dashboard
- **Prioritas:** Tinggi

### LS-04 — Akun tanpa mill ditolak dengan pesan yang menyebut sebabnya
- **Peran:** `NOMILL`
- **Prasyarat:** akun Supervisor yang kolom mill-nya kosong
- **Langkah:**
  1. Login `NOMILL`.
  2. Buka Data Browser, lalu satu Laporan Periode, lalu Dashboard.
- **Hasil diharapkan:** setiap layar menampilkan pesan yang menyebut **"Akun Anda belum terhubung ke mill"** (atau kalimat sepadan yang menyebut mill). **Bukan** tabel kosong, **bukan** angka nol, **bukan** daftar seluruh mill.
- **Prioritas:** Tinggi
- **Catatan QA:** tabel kosong di sini adalah **bug**, bukan hasil yang benar. Tabel kosong terbaca "mill ini tidak punya data", padahal yang benar "akun Anda belum diatur".

### LS-05 — Hanya Admin melihat pemilih Mill
- **Peran:** `SPV-A`, `MGR-A`, `ADM`
- **Prasyarat:** minimal 2 mill ada
- **Langkah:**
  1. Buka satu Laporan Periode sebagai `SPV-A`, lalu `MGR-A`, lalu `ADM`.
- **Hasil diharapkan:** `SPV-A` dan `MGR-A` **tidak punya** pemilih Mill — nama mill-nya hanya ditampilkan. `ADM` **punya** pemilih Mill.
- **Prioritas:** Tinggi

### LS-06 — Sesi berakhir mengarahkan ke Login tanpa pesan jaringan
- **Peran:** semua
- **Prasyarat:** sudah login; sesi/token dibuat kedaluwarsa (mis. akun dinonaktifkan Admin dari layar lain)
- **Langkah:**
  1. Lakukan satu tindakan apa pun (muat ulang, pilih filter, simpan).
- **Hasil diharapkan:** diarahkan ke layar **Login**. Pesan yang muncul **tidak** menyuruh memeriksa koneksi.
- **Prioritas:** Tinggi
- **Catatan QA:** "Periksa koneksi Anda" pada sesi yang berakhir mengirim pengguna memeriksa sinyal — arah yang salah. Kalau pesan itu muncul, ini **bug**.

### LS-07 — Nol dirender sebagai pernyataan, bukan sebagai angka 0
- **Peran:** `SPV-A`
- **Prasyarat:** satu stasiun / satu periode yang **belum ada datanya sama sekali**
- **Langkah:**
  1. Buka layar yang menampilkan hitungan (Dashboard, Laporan Periode, Production Process Activity).
- **Hasil diharapkan:** tempat yang belum ada datanya berbunyi **"belum ada input"** / **"tidak tersedia"** / tanda pisah **"—"**. **Bukan** angka `0` dan **bukan** `0%`.
- **Prioritas:** Sedang
- **Catatan QA:** angka `0` menyatakan "sudah diukur, hasilnya nol". Belum ada data adalah hal berbeda, dan keduanya tidak boleh terlihat sama.

### LS-08 — Production Line wajib dipilih di Laporan Periode, termasuk saat hanya ada satu line
- **Peran:** `SPV-A`
- **Prasyarat:** satu mill dengan **satu** Production Line, dan satu mill dengan **dua** line
- **Langkah:**
  1. Buka satu Laporan Periode pada mill berline tunggal.
  2. Ulangi pada mill berline dua.
- **Hasil diharapkan:** pada **keduanya**, line **tidak** terpilih otomatis. Muncul arahan memilih line, dan **tidak satu angka pun** ditampilkan sebelum line dipilih — bukan nol, benar-benar tidak ada angka.
- **Prioritas:** Tinggi
- **Catatan QA:** memilih sendiri line tunggal akan membuat pembaca mengira ia melihat seluruh mill, padahal ia melihat satu line yang kebetulan satu-satunya hari itu.

### LS-09 — Mengirim id Production Line milik mill lain diabaikan, bukan ditolak dengan galat
- **Peran:** `SPV-A`
- **Prasyarat:** id line Mill B diketahui (ambil dari sesi `SPV-B`)
- **Langkah:**
  1. Login `SPV-A`, buka Laporan Periode.
  2. Ubah alamat di bilah browser sehingga parameter line berisi id line Mill B.
- **Hasil diharapkan:** halaman tetap terbuka normal (bukan galat, bukan 404). Angka yang tampil adalah angka Mill A. **Nama line Mill B tidak muncul di mana pun** pada halaman.
- **Prioritas:** Tinggi

### LS-10 — Periode tertutup mengunci input, periode draft/terbuka tidak
- **Peran:** `SPV-A` (input), `ADM` (ubah status)
- **Prasyarat:** satu periode yang mencakup hari ini
- **Langkah:**
  1. `ADM` memastikan stasiun pada periode itu berstatus **Terbuka**. `SPV-A` menyimpan satu record → berhasil.
  2. `ADM` **menutup** stasiun itu. `SPV-A` mencoba menyimpan record baru pada tanggal dalam periode itu.
  3. `ADM` **membuka kembali**. `SPV-A` menyimpan lagi.
- **Hasil diharapkan:** langkah 1 dan 3 berhasil. Langkah 2 **ditolak** dengan pesan yang menyebut periodenya tertutup. Record yang sudah ada **tidak** terhapus atau berubah.
- **Prioritas:** Tinggi

### LS-11 — Ekspor menghasilkan berkas yang benar-benar terbuka
- **Peran:** `SPV-A`
- **Prasyarat:** ada data pada filter yang dipilih
- **Langkah:**
  1. Pada Data Browser, tekan **Ekspor CSV**. Buka berkasnya.
  2. Tekan **Ekspor Excel**. Buka berkasnya di Excel.
- **Hasil diharapkan:** CSV terbuka sebagai CSV. **Excel terbuka tanpa peringatan format** — berkas `.xlsx` sungguhan, bukan isi CSV yang dinamai `.xlsx`. Isi kedua berkas sesuai filter yang aktif.
- **Jalankan di:** 18 Data Browser + seluruh Laporan Periode web
- **Prioritas:** Tinggi

### LS-12 — Ekspor di mobile hanya CSV
- **Peran:** `OPR-A`
- **Prasyarat:** —
- **Langkah:** buka Laporan Periode mobile mana pun, cari aksi ekspor.
- **Hasil diharapkan:** hanya **CSV** ditawarkan. Tidak ada pilihan Excel/`.xlsx` di mana pun.
- **Prioritas:** Sedang

### LS-13 — Seluruh tampilan berbahasa Indonesia
- **Peran:** semua
- **Prasyarat:** —
- **Langkah:** telusuri setiap layar; perhatikan label status, pesan galat, tombol, dan judul kolom.
- **Hasil diharapkan:** tidak ada teks mentah bahasa Inggris atau nilai teknis yang bocor. Khususnya status record harus berbunyi **Draft / Dijeda / Tersimpan / Tersinkron**, bukan `draft_ongoing` / `saved` / `synced`.
- **Prioritas:** Sedang

### LS-14 — Tidak ada kolom input yang mati tanpa alasan
- **Peran:** `SPV-A`, `MGR-A`
- **Prasyarat:** —
- **Langkah:** buka setiap Form web, coba klik dan isi **setiap** kolom.
- **Hasil diharapkan:** semua kolom dapat diisi, **kecuali** yang dibatasi peran (Checked By hanya Supervisor, Acknowledged By hanya Mill Management). Tidak ada kolom yang abu-abu/terkunci di luar dua pembatasan itu.
- **Prioritas:** Sedang

### LS-15 — Kolom tanggal dan jam bisa diketik manual, bukan hanya lewat pemilih
- **Peran:** `SPV-A`
- **Prasyarat:** —
- **Langkah:** pada Form web, isi kolom tanggal dengan **mengetik**, lalu dengan **pemilih kalender**. Ulangi untuk kolom jam bila ada.
- **Hasil diharapkan:** kedua cara berhasil dan menghasilkan nilai yang sama.
- **Prioritas:** Sedang

### LS-16 — Laporan tidak menandai satu nilai pun sebagai "di luar batas"
- **Peran:** `SPV-A`
- **Prasyarat:** ada record dengan nilai yang jelas-jelas jauh di luar target (mis. suhu 3× batas)
- **Langkah:** buka Laporan Periode stasiun itu dan baca tabel/kartu parameternya.
- **Hasil diharapkan:** **tidak ada** warna peringatan, ikon bahaya, atau label "di luar batas" pada nilai mana pun. Dan ada **kalimat yang menyatakan** bahwa laporan ini memang tidak menandainya, beserta sebabnya.
- **Prioritas:** Sedang
- **Catatan QA:** ketiadaan penandaan di sini **disengaja**, bukan fitur yang belum selesai. Yang harus dilaporkan sebagai bug adalah kalau penandaan itu **muncul**, atau kalau kalimat penjelasnya **hilang**.

---

## 3. Autentikasi & Akun (`AU-`)

Mencakup 4 layar: Login Web, Login Mobile, Ganti Password Web, Ganti Password Mobile.

### AU-01 — Login berhasil mengarahkan ke beranda yang sesuai perannya
- **Peran:** `OPR-A`, `SPV-A`, `MGR-A`, `ADM`
- **Prasyarat:** keempat akun aktif
- **Langkah:** login di **Web**, satu per satu, dengan username dan password yang benar.
- **Hasil diharapkan:** masuk. **Operator mendarat di Beranda Operator** dengan pesan akses terbatas dan sidebar hanya memuat Beranda, Ganti Password, dan Data Saya. Tiga peran lain mendarat di beranda masing-masing dengan menu lengkap sesuai §1.3.
- **Prioritas:** Tinggi

### AU-02 — Login gagal: password salah
- **Peran:** `SPV-A`
- **Langkah:** login dengan username benar dan password salah.
- **Hasil diharapkan:** pesan galat berbahasa Indonesia, tetap di layar Login. Pesannya **tidak** menyebutkan apakah username-nya ada atau tidak.
- **Prioritas:** Tinggi

### AU-03 — Login gagal: username tidak ada
- **Peran:** —
- **Langkah:** login dengan username yang tidak terdaftar.
- **Hasil diharapkan:** pesan galat **sama bunyinya** dengan `AU-02`. Perbedaan bunyi pesan akan memberi tahu orang luar username mana yang terdaftar.
- **Prioritas:** Tinggi

### AU-04 — Akun yang dinonaktifkan tidak bisa masuk, dan yang sedang masuk dikeluarkan
- **Peran:** `ADM` + `SPV-A`
- **Prasyarat:** `SPV-A` sedang login di satu browser
- **Langkah:**
  1. `ADM` menonaktifkan akun `SPV-A` dari Kelola User & Role.
  2. Di browser `SPV-A`, lakukan satu tindakan apa pun.
  3. Coba login ulang sebagai `SPV-A`.
- **Hasil diharapkan:** langkah 2 mengeluarkan `SPV-A` ke Login. Langkah 3 gagal dengan pesan bahwa akunnya tidak aktif.
- **Prioritas:** Tinggi

### AU-05 — Login Mobile berhasil untuk keempat peran
- **Peran:** `OPR-A`, `SPV-A`, `MGR-A`, `ADM`
- **Langkah:** login di aplikasi **Mobile** satu per satu.
- **Hasil diharapkan:** keempatnya berhasil masuk. Operator dan Supervisor mendarat di layar Home dengan daftar stasiun; Mill Management juga bisa masuk (ini disengaja).
- **Prioritas:** Tinggi

### AU-06 — Tamu diarahkan ke Login, tidak melihat isi apa pun
- **Peran:** belum login
- **Langkah:** buka langsung alamat layar dalam (Dashboard, Data Browser, Laporan) tanpa login. Ulangi di mobile.
- **Hasil diharapkan:** diarahkan ke **Login**. Tidak ada isi halaman yang sempat terlihat.
- **Prioritas:** Tinggi

### AU-07 — Ganti Password berhasil, dan password lama berhenti berlaku
- **Peran:** `SPV-A` (web), `OPR-A` (mobile)
- **Langkah:**
  1. Buka Ganti Password, isi password lama dan password baru yang sah, simpan.
  2. Logout, login dengan password **baru**.
  3. Logout, login dengan password **lama**.
- **Hasil diharapkan:** langkah 2 berhasil, langkah 3 **gagal**.
- **Prioritas:** Tinggi

### AU-08 — Ganti Password ditolak bila password lama salah
- **Peran:** `SPV-A`
- **Langkah:** isi password lama yang salah, password baru yang sah, simpan.
- **Hasil diharapkan:** ditolak dengan pesan yang menunjuk kolom password lama. Password **tidak** berubah — buktikan dengan login memakai password sebelumnya.
- **Prioritas:** Tinggi

### AU-09 — Logout mencabut sesi perangkat itu
- **Peran:** `OPR-A`
- **Langkah:** logout dari mobile, lalu coba muat ulang layar yang butuh login.
- **Hasil diharapkan:** diarahkan ke Login; data lama tidak terlihat lagi.
- **Prioritas:** Tinggi

---

## 4. Mobile — Operasional Stasiun (`MB-`)

### 4.1 Alur yang diuji

```
Login → Home → Station List → [pilih stasiun] → Monitor
                                                   ├─ New Data  → Form → Simpan / Pause / Clear
                                                   └─ Load Data → Data Preview → (detail read-only)
```

### 4.2 Matriks 18 stasiun

Kolom **"Kolom wajib"** adalah kolom identitas unit yang harus diisi sebelum Simpan.
Kolom **"Jml kolom bacaan"** adalah banyaknya kolom pengukuran per baris slot waktu —
dipakai pada kasus `MB-FORM-05` (minimal satu terisi).

| No | Stasiun | Kolom wajib | Jml kolom bacaan | Keanehan yang perlu diperhatikan |
|---:|---|---|---:|---|
| 1 | Weighbridge | No. Kartu / Kendaraan | per-transaksi | **Bukan** pola 24 slot — satu record per transaksi timbang, ada arus Masuk & Keluar |
| 2 | Grading | Parameter grading | per-muatan | Satu record per muatan, ada beberapa baris parameter |
| 3 | Cages Track | ID Cage | ± | — |
| 4 | Sterilizer | ID Sterilizer | ± | Ada **checkbox "Checked by SPV" per baris** — Operator tidak boleh bisa mengisinya |
| 5 | Threshing | ID Thresher | 5 | — |
| 6 | Pressing | ID Presser | 5 | — |
| 7 | Clarification | ID unit | ± | — |
| 8 | Kernel Plant | ID unit kernel plant | 7 | Dua pasang kolom berbagi satu standar (2 ripple mill, 2 kernel silo) |
| 9 | Boiler Room | ID boiler | ± | — |
| 10 | Effluent Plant | ID unit | 14 | **Paling banyak kolom.** Ada 3 kolom status pilihan (on/off/fault, run/stop) dan **2** kolom teks bebas |
| 11 | Depricarping | ID Presser | 7 | Ada kolom **Downtime (menit)** berupa angka + kolom Temuan |
| 12 | Engine Room | ID unit | ± | — |
| 13 | Process Water | ID unit | ± | Ada kolom shift & petugas |
| 14 | Storage Tank | ID tangki | ± | — |
| 15 | Solid Waste Disposal | ID unit | ± | — |
| 16 | Kernel Dispatch | No. Kartu / Kendaraan | per-transaksi | Pola per-transaksi, ada kolom status pilihan |
| 17 | CPO Dispatch | No. Kartu / Kendaraan | per-transaksi | Pola per-transaksi |
| 18 | Process Quality Control | ID unit | ± | — |

> **± artinya:** jumlahnya berbeda per stasiun dan harus dibaca dari layar saat menguji.
> Yang diuji bukan angkanya, melainkan **aturannya**: minimal satu kolom bacaan terisi.
>
> **Weighbridge, Grading, Kernel Dispatch, dan CPO Dispatch tidak memakai 24 slot waktu.**
> Untuk keempat stasiun ini, lewati `MB-FORM-03` dan `MB-FORM-04` (aturan urutan slot), dan
> ganti dengan `MB-FORM-11`.

### 4.3 Home & Station List

### MB-HOME-01 — Home menampilkan stasiun yang ditugaskan
- **Peran:** `OPR-A`
- **Langkah:** login mobile, baca layar Home.
- **Hasil diharapkan:** daftar/grid stasiun tampil. Nama mill pengguna terlihat. Tidak ada stasiun milik mill lain.
- **Prioritas:** Tinggi

### MB-HOME-02 — Station List mengantar ke Monitor stasiun yang benar
- **Peran:** `OPR-A`
- **Langkah:** buka Station List, tekan satu stasiun. Ulangi untuk ke-18 stasiun.
- **Hasil diharapkan:** mendarat di **Monitor stasiun yang ditekan**, judulnya sesuai.
- **Prioritas:** Tinggi

### MB-HOME-03 — Pilihan Production Line diingat perangkat dan dibagi antar layar
- **Peran:** `OPR-A`
- **Prasyarat:** mill punya 2 line
- **Langkah:**
  1. Pilih Production Line di Station List.
  2. Buka satu Laporan Periode mobile.
  3. Tutup aplikasi, buka lagi, kembali ke Station List.
- **Hasil diharapkan:** line yang dipilih **tetap** terpilih di langkah 2 dan 3, tanpa diminta memilih ulang.
- **Prioritas:** Sedang

### MB-HOME-04 — Mill tanpa Production Line memberi arahan, bukan layar kosong
- **Peran:** `OPR-A` pada mill tanpa line
- **Langkah:** buka Station List.
- **Hasil diharapkan:** arahan **menghubungi Admin**. **Tidak ada** tombol "coba lagi" — mencoba lagi tidak akan mengubah apa pun.
- **Prioritas:** Sedang

### 4.4 Monitor (jalankan 18×, satu per stasiun)

### MB-MON-01 — Monitor menampilkan daftar draft dan dua penghitung hari ini
- **Peran:** `OPR-A`
- **Prasyarat:** ada minimal 1 draft dan beberapa record tersimpan hari ini
- **Langkah:** buka Monitor stasiun.
- **Hasil diharapkan:** daftar draft tampil; dua penghitung "hari ini" (jumlah record dan jumlah baris slot tercatat) menampilkan angka yang sesuai data milik pengguna itu.
- **Prioritas:** Tinggi

### MB-MON-02 — Beberapa draft bisa berjalan bersamaan dan semuanya tampil
- **Peran:** `OPR-A`
- **Langkah:** buat 3 draft berurutan lewat **New Data**, tekan Pause pada masing-masing, kembali ke Monitor.
- **Hasil diharapkan:** ketiganya tampil di daftar. Keduanya berlabel **"Pause"** — draft dan dijeda tidak dibedakan di tampilan.
- **Prioritas:** Sedang

### MB-MON-03 — New Data membuat header saja, grid masih kosong
- **Peran:** `OPR-A`
- **Langkah:** tekan **New Data**.
- **Hasil diharapkan:** Form terbuka dengan header terisi (tanggal otomatis) dan **grid baris slot waktu KOSONG** — tidak ada 24 baris yang dibuat otomatis.
- **Prioritas:** Tinggi

### MB-MON-04 — Tanggal otomatis memakai tanggal perangkat, termasuk dini hari
- **Peran:** `OPR-A`
- **Prasyarat:** ubah jam perangkat ke **01:00** waktu setempat
- **Langkah:** tekan New Data, baca kolom Tanggal.
- **Hasil diharapkan:** tanggal yang terisi adalah **tanggal lokal perangkat hari itu** — bukan tanggal kemarin, bukan tanggal UTC.
- **Prioritas:** Tinggi
- **Catatan QA:** ini kasus yang mudah terlewat. Shift pabrik berjalan melewati tengah malam, jadi draft yang dibuat pukul 01:00 harus bertanggal hari itu.

### MB-MON-05 — Belum ada draft: keterangan, bukan daftar kosong
- **Peran:** `OPR-A`
- **Prasyarat:** tidak ada draft untuk stasiun itu
- **Langkah:** buka Monitor.
- **Hasil diharapkan:** keterangan "belum ada draft" (atau sepadan). Bukan area kosong tanpa penjelasan.
- **Prioritas:** Sedang

### MB-MON-06 — Breadcrumb dapat diklik, bukan label mati
- **Peran:** `OPR-A`
- **Langkah:** tekan setiap bagian breadcrumb.
- **Hasil diharapkan:** setiap bagian menavigasi ke layar yang disebutnya.
- **Prioritas:** Rendah

### MB-MON-07 — Load Data membuka Data Preview stasiun itu
- **Peran:** `OPR-A`
- **Langkah:** tekan **Load Data**.
- **Hasil diharapkan:** mendarat di Data Preview stasiun yang sama.
- **Prioritas:** Sedang

### 4.5 Form Mobile (jalankan 18×, satu per stasiun)

### MB-FORM-01 — Simpan berhasil dengan satu baris terisi
- **Peran:** `OPR-A`
- **Prasyarat:** periode stasiun itu **Terbuka**; Production Line terpilih
- **Langkah:**
  1. New Data.
  2. Isi kolom wajib (lihat matriks §4.2).
  3. Tambah baris, pilih slot waktu, isi **satu** kolom bacaan.
  4. Simpan.
- **Hasil diharapkan:** tersimpan dengan status **Tersimpan**. Kembali ke Monitor, record muncul dan penghitung bertambah.
- **Prioritas:** Tinggi

### MB-FORM-02 — Simpan ditolak bila kolom wajib kosong
- **Peran:** `OPR-A`
- **Langkah:** New Data, **jangan** isi kolom wajib, tambah satu baris terisi, Simpan.
- **Hasil diharapkan:** ditolak dengan pesan yang **menunjuk kolom wajib itu**, di sebelah kolomnya. Data tidak tersimpan.
- **Prioritas:** Tinggi

### MB-FORM-03 — Slot waktu harus menaik dan tidak boleh kembar
- **Peran:** `OPR-A`
- **Catatan:** lewati untuk Weighbridge, Grading, Kernel Dispatch, CPO Dispatch
- **Langkah:**
  1. Tambah baris, pilih slot **10:00**.
  2. Tambah baris lagi, pilih slot **08:00** (lebih awal). Coba Simpan.
  3. Ubah ke **10:00** (kembar). Coba Simpan.
  4. Ubah ke **11:00**. Simpan.
- **Hasil diharapkan:** langkah 2 dan 3 **ditolak** dengan pesan yang menyebut urutan/duplikasi slot. Langkah 4 berhasil.
- **Prioritas:** Tinggi

### MB-FORM-04 — Urutan slot mengikuti 07:00 → 06:00, bukan 00:00 → 23:00
- **Peran:** `OPR-A`
- **Catatan:** lewati untuk 4 stasiun per-transaksi
- **Langkah:**
  1. Tambah baris, pilih slot **23:00**.
  2. Tambah baris, pilih slot **01:00**. Simpan.
- **Hasil diharapkan:** **berhasil** — karena 01:00 datang **sesudah** 23:00 dalam urutan shift (07:00, 08:00, … 23:00, 00:00, 01:00, … 06:00). Kalau ditolak, ini bug.
- **Prioritas:** Tinggi
- **Catatan QA:** ini kasus yang paling mudah salah dibaca sebagai bug padahal benar, dan sebaliknya. Shift dimulai 07:00.

### MB-FORM-05 — Simpan ditolak bila tidak ada satu pun baris terisi
- **Peran:** `OPR-A`
- **Langkah:**
  1. Isi kolom wajib.
  2. Tambah baris, pilih slot waktu, **jangan isi satu pun kolom bacaan**.
  3. Simpan.
- **Hasil diharapkan:** ditolak dengan pesan yang menyebut **belum ada baris yang terisi**. Baris yang hanya punya slot waktu tidak dihitung terisi.
- **Prioritas:** Tinggi

### MB-FORM-06 — Baris yang sebagian terisi tetap sah
- **Peran:** `OPR-A`
- **Langkah:** isi kolom wajib; tambah baris; isi **hanya satu** kolom bacaan dari beberapa yang ada; Simpan.
- **Hasil diharapkan:** **berhasil**. Setiap kolom bacaan bersifat opsional; yang wajib hanya "minimal satu terisi".
- **Prioritas:** Tinggi

### MB-FORM-07 — Pause menyimpan apa adanya tanpa memvalidasi
- **Peran:** `OPR-A`
- **Langkah:** New Data, **jangan isi apa pun**, tekan **Pause**.
- **Hasil diharapkan:** tersimpan sebagai draft tanpa satu pun pesan galat. Muncul di daftar Monitor berlabel "Pause".
- **Prioritas:** Tinggi

### MB-FORM-08 — Melanjutkan draft memuat kembali isinya
- **Peran:** `OPR-A`
- **Prasyarat:** satu draft dengan 2 baris terisi
- **Langkah:** dari Monitor, tekan draft itu.
- **Hasil diharapkan:** Form terbuka dengan **persis** isi yang tadi: kolom wajib, kedua baris, dan nilai kolomnya.
- **Prioritas:** Tinggi

### MB-FORM-09 — Clear meminta konfirmasi, lalu menghapus permanen
- **Peran:** `OPR-A`
- **Langkah:** buka draft berisi data, tekan **Clear**, batalkan. Lalu tekan Clear lagi, setujui.
- **Hasil diharapkan:** batal → data tetap utuh. Setuju → draft hilang dari Monitor dan tidak bisa dipulihkan.
- **Prioritas:** Tinggi

### MB-FORM-10 — Back dengan perubahan belum tersimpan meminta konfirmasi
- **Peran:** `OPR-A`
- **Langkah:** ubah satu kolom lalu tekan **Back**. Batalkan. Tekan Back lagi, setujui.
- **Hasil diharapkan:** batal → tetap di Form dengan perubahan utuh. Setuju → keluar; perubahan tidak tersimpan. Tekan Back **tanpa** perubahan → langsung keluar tanpa bertanya.
- **Prioritas:** Sedang

### MB-FORM-11 — Stasiun per-transaksi: satu record per transaksi (Weighbridge, Grading, Kernel Dispatch, CPO Dispatch)
- **Peran:** `OPR-A`
- **Prasyarat:** periode Terbuka
- **Langkah:**
  1. New Data pada Weighbridge. Isi kolom wajib dan bobot/nilai yang diperlukan. Simpan.
  2. Ulangi untuk Grading, Kernel Dispatch, CPO Dispatch.
- **Hasil diharapkan:** masing-masing tersimpan sebagai satu transaksi. **Tidak ada** grid 24 slot waktu di keempat layar ini. Weighbridge membedakan arus **Masuk** dan **Keluar**.
- **Prioritas:** Tinggi

### MB-FORM-12 — Operator tidak dapat mengisi Checked By maupun Acknowledged By
- **Peran:** `OPR-A`
- **Langkah:** buka Form, cari kedua kolom verifikasi itu.
- **Hasil diharapkan:** keduanya **tidak dapat diisi** Operator (tidak ada, atau ada tapi terkunci dengan keterangan). Khusus **Sterilizer**, checkbox "Checked by SPV" **per baris** juga tidak dapat diisi Operator.
- **Prioritas:** Tinggi

### MB-FORM-13 — Supervisor mengisi Checked, Mill Management mengisi Acknowledged
- **Peran:** `SPV-A`, lalu `MGR-A`
- **Langkah:**
  1. `SPV-A` buka Form, isi **Checked By**, simpan.
  2. `SPV-A` cari **Acknowledged By**.
  3. `MGR-A` buka record yang sama, isi **Acknowledged By**, simpan.
  4. `MGR-A` cari **Checked By**.
- **Hasil diharapkan:** langkah 1 dan 3 berhasil. Langkah 2 dan 4: kolom yang bukan haknya tidak dapat diisi.
- **Prioritas:** Tinggi

### MB-FORM-14 — Tabel Target Operasional hanya referensi, tidak tersimpan
- **Peran:** `OPR-A`
- **Prasyarat:** stasiun yang punya tabel target (Threshing, Pressing, Depricarping, Kernel Plant, Grading)
- **Langkah:** buka Form, cari tabel Target Operasional. Coba ubah isinya. Simpan record.
- **Hasil diharapkan:** tabel itu **read-only**. Isinya tidak ikut tersimpan ke record dan tidak memengaruhi validasi.
- **Prioritas:** Rendah

### MB-FORM-15 — Simpan ditolak bila Production Line tidak punya stasiun aktif
- **Peran:** `OPR-A`
- **Prasyarat:** satu Production Line yang **tidak** punya stasiun jenis itu dalam keadaan aktif
- **Langkah:** pilih line itu, isi Form lengkap, Simpan.
- **Hasil diharapkan:** ditolak dengan pesan yang menyebut **tidak ada stasiun aktif** pada line itu. Bukan galat teknis.
- **Prioritas:** Tinggi

### 4.6 Offline & Sinkronisasi — bagian paling khas aplikasi ini

### MB-SYNC-01 — Input berhasil sepenuhnya tanpa koneksi
- **Peran:** `OPR-A`
- **Prasyarat:** sudah login **sebelum** koneksi dimatikan
- **Langkah:**
  1. Matikan koneksi (mode pesawat).
  2. New Data, isi lengkap, Simpan.
  3. Buka Monitor dan Data Preview.
- **Hasil diharapkan:** seluruh langkah berhasil **tanpa satu pun pesan galat jaringan**. Record tersimpan di perangkat dan terlihat di kedua layar.
- **Prioritas:** Tinggi
- **Catatan QA:** matikan koneksi **sesudah** login. Mematikannya sebelum login menguji hal yang berbeda (lihat `MB-SYNC-05`).

### MB-SYNC-02 — Sync manual mengirim record ke server
- **Peran:** `OPR-A` + `SPV-A`
- **Prasyarat:** ada record offline berstatus Tersimpan
- **Langkah:**
  1. Nyalakan koneksi. Jalankan **Sync**.
  2. Dari Web, `SPV-A` buka Data Browser stasiun itu.
- **Hasil diharapkan:** status record di mobile berubah menjadi **Tersinkron**. Record yang sama **terlihat di Web** dengan isi yang identik.
- **Prioritas:** Tinggi

### MB-SYNC-03 — Sync tanpa koneksi gagal dengan pesan yang jelas dan tidak merusak data
- **Peran:** `OPR-A`
- **Langkah:** matikan koneksi, jalankan Sync.
- **Hasil diharapkan:** pesan galat yang menyebut masalah jaringan. Record **tetap** berstatus Tersimpan — tidak berubah menjadi Tersinkron, tidak hilang, tidak ganda.
- **Prioritas:** Tinggi

### MB-SYNC-04 — Sync dua kali tidak menggandakan data
- **Peran:** `OPR-A` + `SPV-A`
- **Langkah:** jalankan Sync, tunggu selesai, jalankan Sync lagi. Periksa Data Browser web.
- **Hasil diharapkan:** jumlah record di web **tidak bertambah** pada sync kedua.
- **Prioritas:** Tinggi

### MB-SYNC-05 — Tanpa koneksi saat login: pesan jaringan, bukan "password salah"
- **Peran:** `OPR-A`
- **Langkah:** logout, matikan koneksi, coba login.
- **Hasil diharapkan:** pesan yang menyebut **masalah koneksi**. Bukan "password salah" — itu akan menyuruh pengguna mengganti password yang sebenarnya benar.
- **Prioritas:** Tinggi

### 4.7 Data Preview Mobile (jalankan 18×)

### MB-PRV-01 — Filter tanggal default ke hari ini
- **Peran:** `OPR-A`
- **Prasyarat:** ada record hari ini dan record beberapa hari lalu
- **Langkah:** buka Data Preview.
- **Hasil diharapkan:** hanya record **hari ini** tampil. Record tanggal lain tidak tampil sampai filternya diubah.
- **Prioritas:** Sedang

### MB-PRV-02 — Pintasan "Semua" dan "Hari ini" bekerja
- **Peran:** `OPR-A`
- **Langkah:** tekan **Semua**, lalu **Hari ini**.
- **Hasil diharapkan:** "Semua" menampilkan record seluruh tanggal; "Hari ini" kembali menyaring ke hari ini.
- **Prioritas:** Sedang

### MB-PRV-03 — Pencarian tidak peduli huruf besar-kecil
- **Peran:** `OPR-A`
- **Prasyarat:** ada record dengan kolom wajib bernilai mis. `PRESSER-01`
- **Langkah:** cari `presser`, lalu `PRESSER`, lalu `Presser-0`.
- **Hasil diharapkan:** ketiganya menemukan record yang sama.
- **Prioritas:** Sedang

### MB-PRV-04 — Tombol × menghapus kata kunci dan memulihkan daftar
- **Peran:** `OPR-A`
- **Langkah:** isi kata kunci, tekan **×**.
- **Hasil diharapkan:** kolom cari kosong dan daftar kembali seperti sebelum dicari.
- **Prioritas:** Rendah

### MB-PRV-05 — Draft mengarah ke Form, record final mengarah ke detail read-only
- **Peran:** `OPR-A`
- **Prasyarat:** ada satu draft dan satu record Tersimpan
- **Langkah:** tekan item draft. Kembali. Tekan item Tersimpan.
- **Hasil diharapkan:** draft membuka **Form** (bisa dilanjutkan). Record Tersimpan membuka **detail read-only** — tidak ada tombol edit di layar itu.
- **Prioritas:** Tinggi

### MB-PRV-06 — Detail menampilkan persis baris yang tersimpan
- **Peran:** `OPR-A`
- **Prasyarat:** record dengan **3** baris slot waktu
- **Langkah:** buka detailnya, hitung barisnya.
- **Hasil diharapkan:** tepat **3** baris. **Bukan** 24 baris dengan 21 di antaranya kosong.
- **Prioritas:** Tinggi

### MB-PRV-07 — Checked By / Acknowledged By terlihat oleh semua peran
- **Peran:** `OPR-A`
- **Prasyarat:** record yang sudah di-Checked Supervisor
- **Langkah:** buka detailnya sebagai Operator.
- **Hasil diharapkan:** nama pemverifikasi **terlihat**. Operator tidak boleh bisa **mengisi**, tapi boleh **melihat** — dua hal berbeda.
- **Prioritas:** Sedang

### MB-PRV-08 — Reset Filter memulihkan keadaan awal
- **Peran:** `OPR-A`
- **Langkah:** ubah tanggal dan kata kunci, tekan **Reset Filter**.
- **Hasil diharapkan:** tanggal kembali ke hari ini, kata kunci kosong.
- **Prioritas:** Rendah

---

## 5. Web — Data Stasiun (`WB-`)

Tiga layar per stasiun: **Data Browser** (daftar + filter + ekspor), **Detail** (read-only),
**Form** (tambah/ubah). Jalankan setiap kasus **18×** memakai matriks §4.2.

> **Operator tidak boleh membuka ketiga layar ini.** Diuji oleh `LS-01`.

### 5.1 Data Browser

### WB-BRW-01 — Daftar tampil dengan filter tanggal dan kolom status berbahasa Indonesia
- **Peran:** `SPV-A`
- **Prasyarat:** ada record dengan status berbeda-beda
- **Langkah:** buka Data Browser stasiun.
- **Hasil diharapkan:** tabel tampil. Kolom status berbunyi **Draft / Dijeda / Tersimpan / Tersinkron** — tidak ada nilai teknis.
- **Prioritas:** Tinggi

### WB-BRW-02 — Rentang tanggal terbalik ditolak
- **Peran:** `SPV-A`
- **Langkah:** isi Tanggal Dari = **hari ini**, Tanggal Sampai = **kemarin**. Terapkan.
- **Hasil diharapkan:** ditolak dengan pesan yang menyebut rentang tanggalnya. Tabel tidak menampilkan hasil yang menyesatkan.
- **Prioritas:** Tinggi

### WB-BRW-03 — "Jumlah Baris Terisi" tampil sebagai pecahan dari 24
- **Peran:** `SPV-A`
- **Prasyarat:** satu record dengan **3** baris terisi
- **Catatan:** lewati untuk 4 stasiun per-transaksi
- **Langkah:** cari record itu di tabel.
- **Hasil diharapkan:** kolomnya berbunyi **3 / 24** (atau sepadan). Bukan hanya "3", dan bukan 24.
- **Prioritas:** Sedang

### WB-BRW-04 — Filter menghasilkan hasil yang konsisten antar halaman
- **Peran:** `SPV-A`
- **Prasyarat:** data cukup untuk lebih dari satu halaman
- **Langkah:** terapkan filter yang menyisakan banyak baris, lalu buka halaman 2 dan 3.
- **Hasil diharapkan:** seluruh baris di halaman mana pun memenuhi filter. Filter **tidak** ikut hilang saat pindah halaman.
- **Prioritas:** Tinggi

### WB-BRW-05 — Reset filter memulihkan keadaan awal
- **Peran:** `SPV-A`
- **Langkah:** ubah semua filter, tekan Reset.
- **Hasil diharapkan:** seluruh filter kembali ke nilai awal dan tabel dimuat ulang.
- **Prioritas:** Rendah

### WB-BRW-06 — Klik baris membuka Detail record itu
- **Peran:** `SPV-A`
- **Langkah:** klik satu baris; catat kolom wajib dan tanggalnya lebih dulu.
- **Hasil diharapkan:** mendarat di Detail **record yang sama** — kolom wajib dan tanggalnya cocok.
- **Prioritas:** Tinggi

### WB-BRW-07 — Tambah Data membuka Form kosong
- **Peran:** `SPV-A`
- **Langkah:** tekan **Tambah Data**.
- **Hasil diharapkan:** Form terbuka dalam mode tambah, dengan pemilih Production Line aktif.
- **Prioritas:** Tinggi

### WB-BRW-08 — Filter tanpa hasil memberi keterangan, bukan tabel kosong tanpa penjelasan
- **Peran:** `SPV-A`
- **Langkah:** isi filter yang pasti tidak ada hasilnya (mis. rentang tanggal tahun lalu).
- **Hasil diharapkan:** keterangan "tidak ada data pada filter ini" (atau sepadan), dan filternya tetap terlihat supaya bisa diubah.
- **Prioritas:** Sedang

### WB-BRW-09 — Ekspor melebihi batas baris ditolak dengan pesan, bukan berkas rusak
- **Peran:** `ADM`
- **Prasyarat:** data melebihi batas ekspor (**50.000 baris**) — bila tak tersedia, catat kasus ini sebagai *tidak dapat diuji* dan jangan tandai lulus
- **Langkah:** pilih filter yang mencakup seluruh data, tekan Ekspor.
- **Hasil diharapkan:** **pesan gagal ekspor** yang menyebut batasnya. **Tidak** ada berkas setengah terunduh.
- **Prioritas:** Sedang

### 5.2 Detail (read-only)

### WB-DTL-01 — Seluruh kolom read-only, tanpa edit di tempat
- **Peran:** `SPV-A`
- **Langkah:** buka Detail, coba klik dan ubah setiap nilai.
- **Hasil diharapkan:** tidak satu pun dapat diubah dari layar ini. Satu-satunya jalan mengubah adalah tombol **Edit**.
- **Prioritas:** Tinggi

### WB-DTL-02 — Verifikasi yang kosong ditampilkan sebagai tanda pisah
- **Peran:** `SPV-A`
- **Prasyarat:** record yang belum diverifikasi siapa pun
- **Langkah:** baca kolom Checked By dan Acknowledged By.
- **Hasil diharapkan:** keduanya berisi **"-"**. Bukan kosong tanpa penanda, bukan "null".
- **Prioritas:** Sedang

### WB-DTL-03 — Grid menampilkan persis baris tersimpan
- **Peran:** `SPV-A`
- **Prasyarat:** record dengan 3 baris terisi
- **Langkah:** hitung baris di grid Detail.
- **Hasil diharapkan:** tepat **3** baris, dengan nilai apa adanya dari data tersimpan — tidak dihitung ulang, tidak dilengkapi menjadi 24.
- **Prioritas:** Tinggi

### WB-DTL-04 — Edit membuka Form mode ubah dengan isi yang sama
- **Peran:** `SPV-A`
- **Langkah:** tekan **Edit**.
- **Hasil diharapkan:** Form terbuka berisi data record itu. Pemilih **Production Line tidak dapat diubah** pada mode ubah.
- **Prioritas:** Tinggi

### WB-DTL-05 — Back kembali ke Data Browser dengan filter yang masih sama
- **Peran:** `SPV-A`
- **Langkah:** dari Data Browser ber-filter, buka Detail, tekan **Back**.
- **Hasil diharapkan:** kembali ke Data Browser dengan **filter dan halaman yang sama** seperti sebelum masuk.
- **Prioritas:** Sedang

### 5.3 Form Web

### WB-FRM-01 — Simpan berhasil, status langsung Tersimpan
- **Peran:** `SPV-A`
- **Prasyarat:** periode Terbuka
- **Langkah:** Tambah Data, pilih Production Line, isi kolom wajib dan tanggal, tambah satu baris terisi, Simpan.
- **Hasil diharapkan:** tersimpan dengan status **Tersimpan**. Tidak ada konsep draft/pause di web.
- **Prioritas:** Tinggi

### WB-FRM-02 — Production Line wajib pada mode tambah, terkunci pada mode ubah
- **Peran:** `SPV-A`
- **Langkah:**
  1. Tambah Data tanpa memilih line, isi sisanya, Simpan.
  2. Buka record lama lewat Edit, coba ubah pemilih line.
- **Hasil diharapkan:** langkah 1 **ditolak** dengan pesan yang menunjuk pemilih line. Langkah 2: pemilih line **tidak dapat diubah**.
- **Prioritas:** Tinggi

### WB-FRM-03 — Kolom wajib dan tanggal divalidasi
- **Peran:** `SPV-A`
- **Langkah:** kosongkan kolom wajib, lalu kosongkan tanggal, Simpan masing-masing.
- **Hasil diharapkan:** ditolak dengan pesan **di sebelah kolom yang bersangkutan**, bukan satu pesan umum di atas halaman.
- **Prioritas:** Tinggi

### WB-FRM-04 — Aturan slot waktu sama seperti mobile
- **Peran:** `SPV-A`
- **Catatan:** lewati untuk 4 stasiun per-transaksi
- **Langkah:** jalankan `MB-FORM-03` dan `MB-FORM-04` pada Form web.
- **Hasil diharapkan:** sama: harus menaik, tidak boleh kembar, **maksimal 24 baris**, dan 23:00 → 01:00 **sah**.
- **Prioritas:** Tinggi

### WB-FRM-05 — Melebihi 24 baris ditolak
- **Peran:** `SPV-A`
- **Catatan:** lewati untuk 4 stasiun per-transaksi
- **Langkah:** tambah baris sampai 24, lalu coba tambah baris ke-25.
- **Hasil diharapkan:** baris ke-25 tidak dapat ditambahkan, atau Simpan ditolak dengan pesan yang menyebut batas 24.
- **Prioritas:** Sedang

### WB-FRM-06 — Minimal satu baris terisi
- **Peran:** `SPV-A`
- **Langkah:** jalankan `MB-FORM-05` pada Form web.
- **Hasil diharapkan:** sama — ditolak dengan pesan yang menyebut belum ada baris terisi.
- **Prioritas:** Tinggi

### WB-FRM-07 — Line tanpa stasiun aktif ditolak
- **Peran:** `SPV-A`
- **Langkah:** jalankan `MB-FORM-15` pada Form web.
- **Hasil diharapkan:** sama — pesan yang menyebut tidak ada stasiun aktif pada line itu.
- **Prioritas:** Tinggi

### WB-FRM-08 — Pembatasan peran pada verifikasi
- **Peran:** `SPV-A`, `MGR-A`, `ADM`
- **Langkah:** jalankan `MB-FORM-13` pada Form web, lalu ulangi sebagai `ADM`.
- **Hasil diharapkan:** Supervisor hanya Checked, Mill Management hanya Acknowledged, dan **Admin dapat mengisi keduanya**.
- **Prioritas:** Tinggi

### WB-FRM-09 — Batal tidak menyimpan apa pun
- **Peran:** `SPV-A`
- **Langkah:** isi Form sebagian, tekan **Batal**.
- **Hasil diharapkan:** kembali ke layar sebelumnya; tidak ada record baru dan record lama tidak berubah.
- **Prioritas:** Sedang

### WB-FRM-10 — Ubah record berhasil dan terlihat di Detail
- **Peran:** `SPV-A`
- **Langkah:** Edit satu record, ubah satu nilai kolom bacaan, Simpan, lihat Detail.
- **Hasil diharapkan:** nilai baru terlihat di Detail dan di Data Browser.
- **Prioritas:** Tinggi

### WB-FRM-11 — Hapus baris mengurangi "Jumlah Baris Terisi"
- **Peran:** `SPV-A`
- **Prasyarat:** record dengan 3 baris terisi
- **Langkah:** Edit, hapus satu baris, Simpan, periksa Data Browser.
- **Hasil diharapkan:** kolom jumlah baris terisi menjadi **2 / 24**, dan Detail menampilkan 2 baris.
- **Prioritas:** Sedang

### 5.4 Data Saya (`screen-145`) — khusus Operator & Supervisor

### WB-SAYA-01 — Operator hanya melihat record buatannya sendiri
- **Peran:** `OPR-A`, dibantu `SPV-A`
- **Prasyarat:** `OPR-A` punya record; `SPV-A` juga punya record di stasiun yang sama
- **Langkah:** login `OPR-A` di **web**, buka **Data Saya**.
- **Hasil diharapkan:** hanya record **buatan `OPR-A`** tampil, lintas 18 stasiun. Record `SPV-A` **tidak** tampil.
- **Prioritas:** Tinggi

### WB-SAYA-02 — Data Saya read-only sepenuhnya
- **Peran:** `OPR-A`
- **Langkah:** telusuri layar dan detailnya; cari tombol tambah, ubah, hapus, verifikasi, dan ekspor.
- **Hasil diharapkan:** **tidak ada satu pun**. Layar ini hanya membaca.
- **Prioritas:** Tinggi

### WB-SAYA-03 — Supervisor melihat record buatannya sendiri di mill-nya
- **Peran:** `SPV-A`
- **Langkah:** buka Data Saya.
- **Hasil diharapkan:** record buatan `SPV-A` di mill-nya tampil. Record Operator tidak tampil; record mill lain tidak tampil.
- **Prioritas:** Tinggi

### WB-SAYA-04 — Mill Management dan Admin tidak punya menu ini
- **Peran:** `MGR-A`, `ADM`
- **Langkah:** periksa sidebar, lalu buka alamatnya langsung.
- **Hasil diharapkan:** menu tidak ada di sidebar, dan alamatnya menjawab **403**.
- **Prioritas:** Sedang

---

## 6. Laporan Periode Stasiun (`LP-`)

### 6.1 Stasiun yang sudah punya laporan

| No | Stasiun | Web | Mobile | Keluarga laporan |
|---:|---|:--:|:--:|---|
| 1 | Weighbridge | ✓ | ✓ | Per-transaksi |
| 2 | Grading | ✓ | ✓ | Komposisi muatan |
| 3 | Cages Track | ✓ | ✓ | Kondisi |
| 4 | Sterilizer | ✓ | ✓ | Kondisi |
| 5 | Threshing | ✓ | ✓ | Kondisi (punya master target) |
| 6 | Pressing | ✓ | ✓ | Kondisi (punya master target) |
| 7 | Clarification | ✓ | ✓ | Kondisi |
| 8 | Kernel Plant | ✓ | ✓ | Kondisi (punya master target) |
| 9 | Boiler Room | ✓ | ✓ | Kondisi |
| 10 | Depricarping | ✓ | ✓ | Kondisi (punya master target) |
| 11 | Storage Tank | ✓ | ✓ | Kondisi |
| — | **Effluent Plant** | **belum** | **belum** | dirancang, belum dibangun |
| — | 6 stasiun lain | belum | belum | belum dirancang |

**Artinya:** `LP-` dijalankan **11×** untuk web dan **11×** untuk mobile. Tile stasiun yang
laporannya belum ada harus **kelabu dan tidak dapat ditekan** — diuji oleh `DS-04` dan `DS-07`.

### 6.2 Laporan Periode — Web (jalankan 11×)

### LP-W-01 — Laporan tampil lengkap setelah periode dan line dipilih
- **Peran:** `SPV-A`
- **Prasyarat:** periode dengan data terisi pada satu line
- **Langkah:** buka laporan dari tile di **Laporan Stasiun**, pilih Production Line.
- **Hasil diharapkan:** seluruh bagian tampil: **cakupan pencatatan paling atas**, tabel/kartu parameter, rekap per unit, rekap per hari beserta baris total periode, dan ekspor. Tidak ada bagian yang hilang.
- **Prioritas:** Tinggi

### LP-W-02 — Cakupan pencatatan berada DI ATAS semua angka lain
- **Peran:** `SPV-A`
- **Langkah:** lihat urutan dari atas ke bawah.
- **Hasil diharapkan:** blok cakupan berada **sebelum** tabel parameter dan sebelum rekap mana pun.
- **Prioritas:** Tinggi
- **Catatan QA:** periode yang baru terisi seperlima pun menghasilkan rata-rata yang terlihat rapi. Pembaca harus melihat seberapa lengkap datanya **sebelum** mempercayai angkanya — itu sebabnya urutan ini diuji.

### LP-W-03 — Setiap parameter mencetak penyebutnya sendiri
- **Peran:** `SPV-A`
- **Prasyarat:** periode yang kolom-kolomnya terisi **tidak sama banyak** (mis. satu kolom 6 slot, kolom lain 2 slot)
- **Langkah:** baca kolom penyebut pada setiap baris/kartu parameter.
- **Hasil diharapkan:** setiap baris punya penyebutnya **sendiri** (mis. "6 dari 20 slot", "2 dari 20 slot"). Angka pertamanya **berbeda-beda**; angka keduanya **sama** untuk semua baris.
- **Prioritas:** Tinggi

### LP-W-04 — Semua baris parameter tetap tampil, termasuk yang tak pernah diukur
- **Peran:** `SPV-A`
- **Prasyarat:** satu kolom yang **tidak pernah** diisi sepanjang periode
- **Langkah:** cari baris kolom itu.
- **Hasil diharapkan:** barisnya **tetap ada**, dengan nilai "tidak tersedia"/tanda pisah dan penyebut 0. Jumlah baris parameter **tidak menyusut**.
- **Prioritas:** Tinggi
- **Catatan QA:** baris yang hilang terbaca "parameter ini tidak ada", padahal yang benar "tidak ada yang mengukurnya".

### LP-W-05 — Persen cakupan: tanda pisah vs 0,0%
- **Peran:** `SPV-A`
- **Prasyarat:** dua keadaan — (a) periode **tanpa satu record pun**, (b) periode yang **punya record** tapi seluruh kolomnya kosong
- **Langkah:** buka laporan pada keadaan (a), lalu (b).
- **Hasil diharapkan:** keadaan (a) → persen dirender **tanda pisah "—"**. Keadaan (b) → persen dirender **"0,0%"**.
- **Prioritas:** Tinggi
- **Catatan QA:** keduanya **benar** dan **berbeda**. (a) penyebutnya tak terbentuk — tak ada unit untuk dihitung. (b) penyebutnya ada, dan nol dari sekian slot memang terisi. Kalau keduanya terlihat sama, itu bug.

### LP-W-06 — Periode belum mulai dijelaskan sebagai BELUM MULAI
- **Peran:** `SPV-A`
- **Prasyarat:** periode yang tanggal mulainya **di masa depan**
- **Langkah:** pilih periode itu dan satu line.
- **Hasil diharapkan:** keterangan **"belum mulai"**. Keterangan "sedang berjalan" **tidak** muncul.
- **Prioritas:** Tinggi
- **Catatan QA:** kedua keterangan ini mudah tertukar di sistem. Kalau periode masa depan dijelaskan "sedang berjalan", itu bug.

### LP-W-07 — Periode sedang berjalan: penyebut berhenti di hari ini
- **Peran:** `SPV-A`
- **Prasyarat:** periode yang sudah mulai dan **belum** berakhir
- **Langkah:** baca keterangan penyebut cakupan.
- **Hasil diharapkan:** menyebut **hari terakhir yang terhitung** (hari ini), **bukan** tanggal akhir periode yang belum tiba.
- **Prioritas:** Tinggi

### LP-W-08 — Mill tanpa periode stasiun itu memberi arahan
- **Peran:** `SPV-A`
- **Prasyarat:** mill yang belum punya periode mencakup stasiun itu
- **Langkah:** buka laporannya.
- **Hasil diharapkan:** arahan yang menyebut belum ada periode. Pemilih periode **tetap ada** dalam keadaan kosong. **Bukan** galat.
- **Prioritas:** Sedang

### LP-W-09 — Admin berganti mill mereset pilihan periode dan line
- **Peran:** `ADM`
- **Prasyarat:** 2 mill, keduanya punya data
- **Langkah:** pilih Mill A + periode + line sampai angka muncul. Lalu ganti pemilih Mill ke Mill B.
- **Hasil diharapkan:** pilihan periode dan line **dikosongkan**; arahan memilih line muncul lagi; **nol angka** tampil sampai keduanya dipilih ulang. **Tidak ada** angka Mill A yang bertahan di bawah nama Mill B.
- **Prioritas:** Tinggi

### LP-W-10 — Rekap harian dapat ditutup tanpa mengubah angka
- **Peran:** `SPV-A`
- **Langkah:** catat baris total periode, tutup rekap harian, buka lagi.
- **Hasil diharapkan:** angka total **tidak berubah** sebelum dan sesudah ditutup.
- **Prioritas:** Rendah

### LP-W-11 — Baris total periode bukan rata-rata dari rata-rata harian
- **Peran:** `SPV-A`
- **Prasyarat:** periode yang **jumlah slot per harinya tidak sama** (mis. hari-1 isi 2 slot, hari-2 isi 20 slot) dengan nilai yang berbeda jauh
- **Langkah:** bandingkan baris **total periode** dengan rata-rata kasar dari kolom rata-rata harian.
- **Hasil diharapkan:** total periode **lebih dekat** ke nilai hari yang slotnya banyak. Ia dihitung dari seluruh slot, bukan dengan merata-ratakan rata-rata harian (yang akan memberi bobot sama pada hari 2 slot dan hari 20 slot).
- **Prioritas:** Sedang

### LP-W-12 — Downtime: tanda pisah vs nol yang tercatat
- **Peran:** `SPV-A`
- **Prasyarat:** stasiun yang punya kolom downtime angka (Depricarping, Kernel Plant). Dua keadaan: (a) tak satu slot pun mengisi downtime, (b) beberapa slot mengisi **0** menit
- **Langkah:** buka laporan pada keadaan (a), lalu (b).
- **Hasil diharapkan:** (a) → **tanda pisah** dan keterangan bahwa tak ada yang mencatatnya. (b) → **angka 0**, dan penyebutnya menyebut jumlah slot yang mencatat.
- **Prioritas:** Tinggi
- **Catatan QA:** "tercatat 0 menit" berarti seseorang menyatakan stasiun tidak berhenti. "Tidak dicatat" berarti tidak ada yang mengisinya. Keduanya tidak boleh terlihat sama.

### LP-W-13 — Temuan dikelompokkan harfiah, dua ejaan jadi dua baris
- **Peran:** `SPV-A`
- **Prasyarat:** dua record dengan temuan yang **hanya beda huruf besar-kecil atau spasi** (mis. `Bearing panas` dan `bearing panas`)
- **Langkah:** baca bagian Temuan.
- **Hasil diharapkan:** **dua baris terpisah**, bukan satu baris berjumlah 2. Dan ada **keterangan** bahwa pengelompokannya harfiah.
- **Prioritas:** Sedang
- **Catatan QA:** menggabungkan keduanya akan menyatukan hal yang mungkin bukan satu hal. Yang dilaporkan sebagai bug adalah kalau keduanya **digabung**, atau kalau keterangannya **hilang**.

### LP-W-14 — Temuan terpisah dari downtime
- **Peran:** `SPV-A`
- **Prasyarat:** satu slot yang mengisi **downtime dan temuan sekaligus**
- **Langkah:** baca blok downtime dan bagian temuan.
- **Hasil diharapkan:** dua **bagian berbeda**. Slot itu dihitung **sekali** di masing-masing, tidak digabung dan tidak dihitung dua kali.
- **Prioritas:** Sedang

### LP-W-15 — Bagian "standar tanpa pengukuran" tetap digambar walau kosong
- **Peran:** `SPV-A`
- **Prasyarat:** stasiun yang punya master target (Threshing, Pressing, Depricarping, Kernel Plant)
- **Langkah:** cari bagian itu.
- **Hasil diharapkan:** bagiannya **ada**, walau isinya kosong, beserta keterangannya. Pada **Kernel Plant** ia normalnya memuat satu baris: **Final Kernel Dirt**.
- **Prioritas:** Sedang
- **Catatan QA:** bagian yang menghilang saat kosong tak dapat dibedakan dari bagian yang belum pernah dibuat.

### LP-W-16 — Kolom target dirender utuh, termasuk keterangan dalam tanda kurung
- **Peran:** `SPV-A`
- **Prasyarat:** stasiun bermaster target
- **Langkah:** baca kolom target pada baris parameter.
- **Hasil diharapkan:** teksnya **utuh**, termasuk keterangan di dalam tanda kurung (mis. `20 - 25 Amps (Nut Breakage >95%)`). Tidak dipotong dengan "…".
- **Prioritas:** Sedang

### LP-W-17 — Kolom yang berbagi satu standar ditandai, dan angkanya tidak dirata-ratakan
- **Peran:** `SPV-A`
- **Prasyarat:** **Kernel Plant** (2 pasang: ripple mill 1–2 dan kernel silo 1–2) atau **Depricarping** (1 pasang: nut silo)
- **Langkah:** isi kedua anggota satu pasangan dengan nilai yang **berbeda jauh** (mis. 24 dan 12). Buka laporannya.
- **Hasil diharapkan:** **dua baris terpisah** dengan nilai masing-masing, keduanya membawa **keterangan bahwa standarnya satu baris master yang sama**, dan **tidak ada** baris gabungan maupun nilai rata-ratanya (18) di mana pun.
- **Prioritas:** Tinggi
- **Catatan QA:** pada Kernel Plant ada **dua** pasangan — pastikan **keempat** baris bertanda, bukan hanya dua.

### LP-W-18 — Ekspor mengikuti periode dan line yang terpilih
- **Peran:** `SPV-A`
- **Langkah:** pilih periode & line, ekspor, buka berkasnya.
- **Hasil diharapkan:** berkas hanya memuat data periode dan line itu, **satu baris per slot waktu**, dengan kolom konteks (periode, mill, line, tanggal, unit) **diulang di setiap baris**.
- **Prioritas:** Tinggi

### LP-W-19 — Laporan tidak pernah mengubah data
- **Peran:** `SPV-A`
- **Langkah:** telusuri layar; cari tombol simpan, ubah, hapus, atau verifikasi.
- **Hasil diharapkan:** **tidak ada**. Satu-satunya aksi adalah memilih filter, membuka/menutup bagian, dan ekspor.
- **Prioritas:** Tinggi

### 6.3 Laporan Periode — Mobile (jalankan 11×)

### LP-M-01 — Isi laporan sama dengan versi web
- **Peran:** `OPR-A` (mobile) + `SPV-A` (web)
- **Prasyarat:** periode & line yang sama tersedia di keduanya
- **Langkah:** buka laporan stasiun itu di mobile, lalu di web, dengan **periode dan line yang sama**. Bandingkan angkanya satu per satu.
- **Hasil diharapkan:** **setiap angka identik** — cakupan, min/rata-rata/maks, penyebut, downtime, temuan, rekap.
- **Prioritas:** Tinggi
- **Catatan QA:** ini kasus terpenting di bagian ini. Kalau satu angka berbeda, laporkan sebagai temuan prioritas tinggi beserta nama kolomnya.

### LP-M-02 — Tabel menjadi kartu bertumpuk, isinya tidak berkurang
- **Peran:** `OPR-A`
- **Langkah:** buka laporan mobile; hitung kartu parameter dan baca isi setiap kartu.
- **Hasil diharapkan:** jumlah kartu **sama** dengan jumlah baris parameter di web. Setiap kartu memuat min/rata-rata/maks, **penyebutnya**, dan **seluruh kolom target** — tidak ada kolom yang dibuang demi ruang.
- **Prioritas:** Tinggi

### LP-M-03 — Halaman tidak menggulir mendatar pada lebar 390px
- **Peran:** `OPR-A`
- **Prasyarat:** lebar layar **390px**
- **Langkah:** gulir seluruh halaman; coba gulir ke kanan pada area di luar tabel rekap.
- **Hasil diharapkan:** **halaman tidak menggulir mendatar**. Tabel rekap yang lebar menggulir **di dalam kartunya sendiri**.
- **Prioritas:** Sedang

### LP-M-04 — Production Line wajib, termasuk saat hanya satu
- **Peran:** `OPR-A`
- **Langkah:** jalankan `LS-08` pada laporan mobile.
- **Hasil diharapkan:** sama — tidak terpilih otomatis, nol angka sebelum dipilih.
- **Prioritas:** Tinggi

### LP-M-05 — Penjagaan line tidak bisa dilewati lewat coba-lagi atau ganti periode
- **Peran:** `OPR-A`
- **Prasyarat:** mill dengan 2 line, belum ada yang terpilih
- **Langkah:**
  1. Buka laporan; arahan memilih line muncul.
  2. Tekan **Coba Lagi** bila ada.
  3. **Ganti periode** ke periode lain.
- **Hasil diharapkan:** setelah langkah 2 dan 3, **masih** nol angka dan arahan memilih line masih ada. Angka hanya muncul setelah line benar-benar dipilih.
- **Prioritas:** Tinggi

### LP-M-06 — Line yang diingat perangkat dipakai tanpa diminta memilih lagi
- **Peran:** `OPR-A`
- **Langkah:** pilih line, tutup aplikasi, buka lagi, buka laporan yang sama.
- **Hasil diharapkan:** line itu sudah berlaku; angka langsung tampil tanpa diminta memilih.
- **Prioritas:** Sedang

### LP-M-07 — Line yang diingat sudah tidak ada: pemilih muncul lagi, tanpa menebak
- **Peran:** `OPR-A` + `ADM`
- **Prasyarat:** line sudah diingat perangkat
- **Langkah:** `ADM` menghapus/menonaktifkan line itu. `OPR-A` membuka laporan.
- **Hasil diharapkan:** pemilih line muncul lagi dalam keadaan **kosong**. Sistem **tidak** memilih line lain sebagai gantinya.
- **Prioritas:** Sedang

### LP-M-08 — Dua pesan yang harus berbeda: mill tanpa line vs daftar line gagal dimuat
- **Peran:** `OPR-A`
- **Langkah:**
  1. Buka laporan pada mill **tanpa** Production Line.
  2. Matikan koneksi, buka laporan pada mill yang punya line.
- **Hasil diharapkan:** langkah 1 → arahan **menghubungi Admin**, **tanpa** tombol coba lagi. Langkah 2 → pesan **berbeda** yang menyebut masalah koneksi, **dengan** tombol coba lagi. Kedua pesan tidak boleh sama.
- **Prioritas:** Sedang
- **Catatan QA:** menyatukan keduanya akan menyuruh pengguna menelepon Admin untuk masalah jaringan.

### LP-M-09 — Ganti periode cepat: hanya hasil terakhir yang tampil
- **Peran:** `OPR-A`
- **Prasyarat:** 2 periode dengan angka yang berbeda jelas; koneksi lambat (gunakan throttling bila tersedia)
- **Langkah:** pilih periode A lalu **segera** pilih periode B sebelum A selesai dimuat.
- **Hasil diharapkan:** angka yang akhirnya tampil adalah angka **periode B**. Angka periode A tidak boleh mendarat sesudahnya.
- **Prioritas:** Sedang

### LP-M-10 — Gagal jaringan tidak menghapus pilihan
- **Peran:** `OPR-A`
- **Langkah:** pilih periode & line, matikan koneksi, tekan muat ulang/coba lagi.
- **Hasil diharapkan:** pesan galat + tombol coba lagi. Pilihan periode dan line **tetap** seperti sebelumnya — tidak perlu memilih ulang.
- **Prioritas:** Sedang

### LP-M-11 — Ekspor CSV berhasil mengunduh
- **Peran:** `OPR-A`
- **Langkah:** tekan ekspor.
- **Hasil diharapkan:** berkas CSV terunduh dan dapat dibuka. Hanya CSV (lihat `LS-12`).
- **Prioritas:** Sedang

### LP-M-12 — Laporan mobile read-only
- **Peran:** `OPR-A`
- **Langkah:** telusuri layar; cari aksi yang mengubah data.
- **Hasil diharapkan:** hanya ekspor dan navigasi. Tidak ada aksi tulis.
- **Prioritas:** Tinggi

---

## 7. Dashboard & Laporan lain (`DS-`)

### DS-01 — Dashboard Web menampilkan ringkasan tiga stasiun
- **Peran:** `SPV-A`
- **Prasyarat:** ada data Weighbridge, Grading, dan Cages Track hari ini
- **Langkah:** buka Dashboard.
- **Hasil diharapkan:** ringkasan ketiga stasiun itu tampil beserta jumlah dan totalnya, sesuai rentang tanggal yang terpilih.
- **Prioritas:** Tinggi
- **Catatan QA:** Dashboard memang hanya meringkas **tiga** stasiun, bukan 18. Itu bukan bug.

### DS-02 — Rentang tanggal Dashboard default ke hari ini
- **Peran:** `SPV-A`
- **Langkah:** buka Dashboard tanpa mengubah filter.
- **Hasil diharapkan:** rentangnya **hari ini sampai hari ini**, dan angkanya sesuai data hari ini saja.
- **Prioritas:** Sedang

### DS-03 — Dashboard ter-scope mill aktor
- **Peran:** `SPV-A`, `SPV-B`
- **Langkah:** jalankan `LS-03` pada Dashboard.
- **Hasil diharapkan:** angka masing-masing hanya dari mill-nya sendiri.
- **Prioritas:** Tinggi

### DS-04 — Laporan Stasiun (Web): tile laporan yang ada aktif, sisanya kelabu
- **Peran:** `SPV-A`
- **Langkah:** buka **Laporan Stasiun**, baca seluruh tile.
- **Hasil diharapkan:** **18 tile** tampil — tidak ada yang disembunyikan. Tile 11 stasiun yang laporannya sudah ada **dapat ditekan**; 7 sisanya **kelabu dan tidak dapat ditekan**. Ada legenda/penjelasan keadaannya.
- **Prioritas:** Tinggi
- **Catatan QA:** stasiun yang laporannya belum dibangun **tetap ditampilkan**, bukan dihilangkan — supaya pembaca tahu stasiun itu ada.

### DS-05 — Tile aktif mendarat di laporan stasiun yang benar
- **Peran:** `SPV-A`
- **Langkah:** tekan masing-masing dari 11 tile aktif.
- **Hasil diharapkan:** mendarat di laporan **stasiun yang ditekan**, dan mill serta line yang sudah dipilih **ikut terbawa** — tidak perlu memilih mill dua kali dalam satu alur.
- **Prioritas:** Tinggi

### DS-06 — Laporan Manajemen hanya untuk Mill Management dan Admin
- **Peran:** `SPV-A`, `MGR-A`, `ADM`
- **Langkah:** periksa sidebar ketiganya, lalu buka alamatnya langsung sebagai `SPV-A`.
- **Hasil diharapkan:** `MGR-A` dan `ADM` punya menunya; `SPV-A` **tidak** punya dan alamatnya menjawab **403**.
- **Prioritas:** Tinggi

### DS-07 — Dashboard & Reporting (Mobile) → Pilih Stasiun
- **Peran:** `OPR-A`
- **Langkah:** buka **Dashboard & Reporting** di mobile, lalu **Reporting**.
- **Hasil diharapkan:** grid 18 stasiun tampil; 11 dapat ditekan, 7 kelabu. Menekan tile kelabu memberi pesan "belum tersedia" yang **tidak menumpuk** bila ditekan berulang.
- **Prioritas:** Tinggi

### DS-08 — Production Process Activity (Web): papan status, bukan sekadar peluncur
- **Peran:** `SPV-A`
- **Prasyarat:** beberapa stasiun punya record **hari ini**, beberapa belum
- **Langkah:** buka **Production Process Activity**.
- **Hasil diharapkan:**
  - Kepala halaman berbunyi **"N dari 18 stasiun sudah ada input hari ini"**.
  - Tile stasiun yang **belum** ada input hari ini **berwarna** dan berbunyi **"Belum ada input hari ini"** — bukan angka `0`.
  - Tile yang **sudah** ada input menampilkan **jumlah record hari ini** dan tampil tenang.
  - Setiap tile menyebut **kapan terakhir ada input**.
  - Ada keterangan bahwa **"hari ini"** dan **"input terakhir"** berbeda cakupannya.
- **Prioritas:** Tinggi

### DS-09 — Jumlah tile berwarna cocok dengan angka di kepala halaman
- **Peran:** `SPV-A`
- **Langkah:** hitung tile yang sudah ada input, bandingkan dengan angka pertama di kepala halaman.
- **Hasil diharapkan:** **sama**. Dan tile berwarna + tile tenang = 18 (atau 18 dikurangi stasiun yang dinonaktifkan).
- **Prioritas:** Sedang

### DS-10 — "Nol hari ini" boleh berdampingan dengan "input terakhir beberapa menit lalu"
- **Peran:** `SPV-A`
- **Prasyarat:** satu stasiun yang record **terakhirnya dibuat tadi** tapi **tanggalnya kemarin**
- **Langkah:** baca tile stasiun itu.
- **Hasil diharapkan:** tertulis **"Belum ada input hari ini"** DAN **"Input terakhir beberapa menit lalu"** sekaligus. **Keduanya benar.**
- **Prioritas:** Sedang
- **Catatan QA:** ini **bukan** bug. "Hari ini" menghitung record bertanggal hari ini; "input terakhir" tidak dibatasi tanggal. Keterangan di halaman menjelaskannya.

### DS-11 — Tile mengantar ke Data Browser stasiun yang benar
- **Peran:** `SPV-A`
- **Langkah:** tekan beberapa tile.
- **Hasil diharapkan:** mendarat di **Data Browser** stasiun yang ditekan.
- **Prioritas:** Tinggi

### DS-12 — Urutan tile sama antara Web dan Mobile
- **Peran:** `SPV-A` (web) + `OPR-A` (mobile)
- **Langkah:** catat urutan 18 stasiun di Production Process Activity (web), lalu di Station List (mobile).
- **Hasil diharapkan:** **urutannya sama** di kedua aplikasi. Keduanya dimulai **Weighbridge, lalu Pressing**.
- **Prioritas:** Sedang
- **Catatan QA:** urutan ini **bukan** alfabetis dan **bukan** alur proses — ia urutan yang diminta produk. Yang diuji adalah web dan mobile **tidak menyimpang** satu dari yang lain.

---

## 8. Master Data (`MD-`)

Tujuh layar Kelola + Struktur Mills + Kelola Periode Pelaporan. **Seluruhnya Admin saja**
(kecuali Pengaturan Mill, lihat §9). Machinery Group tidak punya layarnya sendiri — ia dikelola
di dalam layar Kelola Mesin — tetapi tetap satu tingkat data yang diuji dengan pola yang sama.

### 8.1 Pola Kelola (jalankan untuk: Corporate, Company, Business Unit, Production Line, Station, Machinery Group, Machinery, User & Role)

### MD-KEL-01 — Tambah data berhasil dan langsung terlihat
- **Peran:** `ADM`
- **Langkah:** buka layar Kelola, tekan Tambah, isi seluruh kolom wajib, Simpan.
- **Hasil diharapkan:** tersimpan; muncul di daftar tanpa perlu muat ulang manual.
- **Prioritas:** Tinggi

### MD-KEL-02 — Kolom wajib divalidasi di sebelah kolomnya
- **Peran:** `ADM`
- **Langkah:** kosongkan setiap kolom wajib satu per satu, Simpan.
- **Hasil diharapkan:** pesan galat muncul **di sebelah kolom yang bersangkutan**, bukan sebagai satu pesan umum. Data tidak tersimpan.
- **Prioritas:** Tinggi

### MD-KEL-03 — Nama kembar ditolak, tidak peduli huruf besar-kecil
- **Peran:** `ADM`
- **Prasyarat:** sudah ada entri bernama mis. `PT Contoh`
- **Langkah:** tambah entri baru bernama `pt contoh`, lalu `PT CONTOH`.
- **Hasil diharapkan:** **keduanya ditolak** dengan pesan bahwa namanya sudah dipakai.
- **Prioritas:** Tinggi

### MD-KEL-04 — Induk wajib dipilih
- **Peran:** `ADM`
- **Prasyarat:** berlaku untuk Company (induk: Corporate), Business Unit (induk: Company), Production Line (induk: Business Unit), Station (induk: Production Line), Machinery (induk: Machinery Group)
- **Langkah:** tambah entri **tanpa** memilih induknya, Simpan.
- **Hasil diharapkan:** ditolak dengan pesan yang menunjuk kolom induk. **Tidak** muncul galat teknis apa pun.
- **Prioritas:** Tinggi
- **Catatan QA:** pernah ada bug di sini yang memunculkan galat teknis, bukan pesan validasi. Perhatikan bentuk pesannya.

### MD-KEL-05 — Nama kembar diperiksa dalam lingkup induknya
- **Peran:** `ADM`
- **Prasyarat:** dua Corporate berbeda
- **Langkah:** buat Company bernama `PT Sama` di Corporate A. Lalu buat Company bernama `PT Sama` di Corporate B.
- **Hasil diharapkan:** **berhasil** — nama yang sama sah di induk yang berbeda. Membuat yang kedua di Corporate A yang sama harus **ditolak**.
- **Prioritas:** Sedang

### MD-KEL-06 — Ubah data berhasil dan terlihat
- **Peran:** `ADM`
- **Langkah:** Edit satu entri, ubah namanya, Simpan.
- **Hasil diharapkan:** nama baru terlihat di daftar dan di layar lain yang memakainya.
- **Prioritas:** Tinggi

### MD-KEL-07 — Hapus ditolak bila masih punya anak, dengan menyebut jumlahnya
- **Peran:** `ADM`
- **Prasyarat:** satu Corporate yang punya 2 Company; satu Company yang punya Business Unit; satu Business Unit yang punya Production Line
- **Langkah:** coba hapus masing-masing induk itu.
- **Hasil diharapkan:** **ditolak**, dengan pesan yang **menyebut jumlah penghalangnya** (mis. "masih memiliki 2 Company"). Data **tidak** terhapus dan **tidak** ada penghapusan berantai.
- **Prioritas:** Tinggi

### MD-KEL-08 — Hapus berhasil bila sudah tidak punya anak
- **Peran:** `ADM`
- **Langkah:** hapus seluruh anaknya lebih dulu, lalu hapus induknya.
- **Hasil diharapkan:** berhasil; hilang dari daftar.
- **Prioritas:** Tinggi

### MD-KEL-09 — Hapus meminta konfirmasi
- **Peran:** `ADM`
- **Langkah:** tekan Hapus, batalkan. Tekan lagi, setujui.
- **Hasil diharapkan:** batal → data utuh. Setuju → terhapus.
- **Prioritas:** Sedang

### MD-KEL-10 — Pencarian dan paginasi bekerja bersama
- **Peran:** `ADM`
- **Prasyarat:** data lebih dari satu halaman
- **Langkah:** cari kata kunci yang hasilnya lebih dari satu halaman, lalu buka halaman 2.
- **Hasil diharapkan:** seluruh hasil di halaman 2 **tetap** cocok kata kuncinya. Hasil di halaman lain tidak terbaca sebagai "tidak ditemukan".
- **Prioritas:** Tinggi

### MD-KEL-11 — Pencarian tidak peduli huruf besar-kecil, dan karakter khusus diperlakukan sebagai huruf biasa
- **Peran:** `ADM`
- **Prasyarat:** entri bernama mis. `Mill 100% Murni` dan `Mill BU_A`
- **Langkah:** cari `100%`, lalu `BU_A`, lalu `mill`.
- **Hasil diharapkan:** `100%` menemukan **tepat** entri yang namanya memuat tanda persen itu. `BU_A` menemukan **tepat** `Mill BU_A` — **bukan** juga `Mill BUxA` bila ada. `mill` menemukan keduanya.
- **Prioritas:** Sedang
- **Catatan QA:** `%` dan `_` adalah karakter khusus pencarian di banyak sistem. Di sini keduanya harus diperlakukan sebagai huruf biasa.

### MD-KEL-12 — Logo mill dapat diunggah dan terlihat
- **Peran:** `ADM`
- **Prasyarat:** berlaku untuk Business Unit
- **Langkah:** unggah gambar logo, Simpan, buka kembali.
- **Hasil diharapkan:** logo tersimpan dan terlihat. Mengunggah berkas yang bukan gambar **ditolak** dengan pesan yang jelas.
- **Prioritas:** Rendah

### 8.2 Struktur Mills (`screen-127`)

### MD-STR-01 — Papan kartu per mill tampil dengan hierarki di atasnya
- **Peran:** `ADM`
- **Langkah:** buka **Struktur Mills**.
- **Hasil diharapkan:** satu kartu per mill. Tiap kartu memuat jalur **corporate › company** di atas namanya, kodenya, jumlah Production Line, dan **daftar line di dalam kartu**.
- **Prioritas:** Tinggi

### MD-STR-02 — Keempat tingkat dapat dikelola dari layar ini
- **Peran:** `ADM`
- **Langkah:** tambah, ubah, dan hapus satu entri pada masing-masing tingkat: Corporate, Company, Business Unit, Production Line.
- **Hasil diharapkan:** ketiga aksi berhasil di keempat tingkat, lewat modal, tanpa berpindah layar.
- **Prioritas:** Tinggi

### MD-STR-03 — Corporate dan Company tetap terkelola walau tidak punya anak
- **Peran:** `ADM`
- **Prasyarat:** satu Corporate yang **belum** punya Company
- **Langkah:** cari Corporate itu di layar ini dan ubah namanya.
- **Hasil diharapkan:** ia **tetap muncul** di bagian ringkas Corporate/Company dan **tetap dapat diubah** — meski ia tidak punya mill untuk ditampilkan sebagai kartu.
- **Prioritas:** Tinggi
- **Catatan QA:** kartu mill hanya memuat mill. Corporate/Company tanpa anak tidak boleh hilang dari layar ini, karena kalau hilang ia tidak dapat dikelola di mana pun.

### MD-STR-04 — Pencarian menyaring sebelum paginasi
- **Peran:** `ADM`
- **Prasyarat:** mill lebih dari satu halaman
- **Langkah:** cari nama mill yang ada di **halaman kedua** tanpa pencarian.
- **Hasil diharapkan:** mill itu **ditemukan**. Pencarian menyaring seluruh data, bukan hanya halaman yang sedang tampil.
- **Prioritas:** Tinggi
- **Catatan QA:** kalau pencarian hanya menyaring halaman aktif, hasil di halaman lain terbaca sebagai "tidak ditemukan" — itu bug.

### MD-STR-05 — Aturan hapus sama dengan layar Kelola
- **Peran:** `ADM`
- **Langkah:** jalankan `MD-KEL-07` dan `MD-KEL-08` dari layar ini.
- **Hasil diharapkan:** sama — penolakan menyebut jumlah penghalangnya, tanpa penghapusan berantai.
- **Prioritas:** Tinggi

### 8.3 Kelola Periode Pelaporan (`screen-128`)

### MD-PRD-01 — Hanya Admin dapat membuka layar ini
- **Peran:** `SPV-A`, `MGR-A`, `ADM`
- **Langkah:** jalankan `LS-01` pada layar ini.
- **Hasil diharapkan:** hanya `ADM` masuk; dua lainnya **403**.
- **Prioritas:** Tinggi

### MD-PRD-02 — Panel "Periode Terbuka Hari Ini" memakai syarat gabungan
- **Peran:** `ADM`
- **Prasyarat:** (a) periode yang punya stasiun Terbuka **dan** mencakup hari ini; (b) periode yang punya stasiun Terbuka tapi **tidak** mencakup hari ini
- **Langkah:** baca panel.
- **Hasil diharapkan:** hanya (a) yang muncul di panel. (b) **tidak** muncul — tanggal hari ini adalah bagian dari syaratnya, bukan sekadar penanda.
- **Prioritas:** Sedang

### MD-PRD-03 — Mill tanpa periode yang memenuhi syarat tetap punya kartu, dalam keadaan redup
- **Peran:** `ADM`
- **Prasyarat:** satu mill tanpa periode terbuka hari ini
- **Langkah:** baca panel.
- **Hasil diharapkan:** mill itu **tetap punya kartu**, tampil redup. Ia **tidak** disembunyikan — ketiadaan periode adalah informasi, bukan alasan menghilangkan mill.
- **Prioritas:** Sedang

### MD-PRD-04 — Hitungan pada kartu adalah stasiun Terbuka dibanding seluruh stasiun periode
- **Peran:** `ADM`
- **Prasyarat:** periode dengan sebagian stasiun Terbuka dan sebagian Tertutup
- **Langkah:** baca angka pada kartu, bandingkan dengan Detail Periode.
- **Hasil diharapkan:** angkanya **cocok** — jumlah stasiun Terbuka per jumlah seluruh stasiun periode itu.
- **Prioritas:** Sedang

### MD-PRD-05 — Mill wajib dipilih saat membuat periode
- **Peran:** `ADM`
- **Langkah:** Tambah Periode tanpa memilih Business Unit, Simpan.
- **Hasil diharapkan:** ditolak dengan pesan yang menunjuk kolom mill.
- **Prioritas:** Tinggi

### MD-PRD-06 — Periode tumpang tindih pada satu mill ditolak
- **Peran:** `ADM`
- **Prasyarat:** sudah ada periode 1–31 Oktober pada Mill A
- **Langkah:** buat periode baru pada Mill A yang rentangnya bersinggungan (mis. 15 Okt – 15 Nov).
- **Hasil diharapkan:** **ditolak** dengan pesan yang menyebut tumpang tindih. Membuat rentang yang sama pada **Mill B** harus **berhasil**.
- **Prioritas:** Tinggi

### MD-PRD-07 — Periode tidak memilih jenis stasiun; daftarnya diturunkan sistem
- **Peran:** `ADM`
- **Langkah:** buat periode baru, lalu buka Detail Periode-nya.
- **Hasil diharapkan:** saat membuat, **tidak ada** pilihan jenis stasiun. Di Detail, daftar stasiunnya **sudah terisi** sesuai stasiun yang ada di mill itu.
- **Prioritas:** Sedang

### MD-PRD-08 — Tanggal akhir tidak boleh sebelum tanggal mulai
- **Peran:** `ADM`
- **Langkah:** isi tanggal akhir lebih awal dari tanggal mulai, Simpan.
- **Hasil diharapkan:** ditolak dengan pesan yang menyebut rentangnya.
- **Prioritas:** Tinggi

### MD-PRD-09 — Tutup dan Buka periode hanya oleh Admin, dan statusnya berpengaruh
- **Peran:** `ADM`, lalu `SPV-A`
- **Langkah:** jalankan `LS-10`.
- **Hasil diharapkan:** sesuai `LS-10`. Dan `SPV-A`/`MGR-A` **tidak punya** aksi tutup/buka sama sekali.
- **Prioritas:** Tinggi

### MD-PRD-10 — Hapus periode yang sudah punya record ditolak
- **Peran:** `ADM`
- **Prasyarat:** periode yang sudah membingkai record stasiun
- **Langkah:** coba hapus periode itu.
- **Hasil diharapkan:** **ditolak** dengan pesan yang menyebut sebabnya. Record **tidak** terhapus.
- **Prioritas:** Tinggi

### MD-PRD-11 — Filter dan paginasi daftar periode
- **Peran:** `ADM`
- **Langkah:** jalankan `MD-KEL-10` pada daftar periode.
- **Hasil diharapkan:** sama.
- **Prioritas:** Sedang

---

## 9. User & Role, Pengaturan Mill (`AD-`)

### AD-01 — Tambah user dengan peran dan mill
- **Peran:** `ADM`
- **Langkah:** buka **Kelola User & Role**, tambah user: username, nama, peran, mill, password. Simpan. Lalu login dengan akun itu.
- **Hasil diharapkan:** tersimpan dan **dapat login**; menu yang terlihat sesuai perannya (§1.3).
- **Prioritas:** Tinggi

### AD-02 — Username kembar ditolak
- **Peran:** `ADM`
- **Langkah:** tambah user dengan username yang sudah dipakai, termasuk dengan huruf besar-kecil berbeda.
- **Hasil diharapkan:** ditolak dengan pesan bahwa username sudah dipakai.
- **Prioritas:** Tinggi

### AD-03 — Peran terikat mill wajib punya mill
- **Peran:** `ADM`
- **Langkah:** tambah user berperan Supervisor **tanpa** memilih mill, Simpan.
- **Hasil diharapkan:** ditolak dengan pesan yang menunjuk kolom mill. (Admin boleh tanpa mill — coba juga, dan itu harus **berhasil**.)
- **Prioritas:** Tinggi

### AD-04 — Ubah peran langsung mengubah menu yang terlihat
- **Peran:** `ADM` + akun uji
- **Langkah:** ubah peran satu user dari Supervisor menjadi Mill Management. Login sebagai user itu.
- **Hasil diharapkan:** sidebar dan hak verifikasinya berubah sesuai peran baru — Checked By tidak lagi bisa, Acknowledged By jadi bisa.
- **Prioritas:** Tinggi

### AD-05 — Nonaktifkan user memblokir login
- **Peran:** `ADM`
- **Langkah:** jalankan `AU-04`.
- **Hasil diharapkan:** sesuai `AU-04`.
- **Prioritas:** Tinggi

### AD-06 — Reset password oleh Admin
- **Peran:** `ADM` + akun uji
- **Langkah:** reset password satu user dari layar ini, lalu login dengan password baru.
- **Hasil diharapkan:** berhasil; password lama tidak berlaku lagi.
- **Prioritas:** Tinggi

### AD-07 — Pengaturan Mill: Mill Management hanya mill sendiri
- **Peran:** `MGR-A`
- **Prasyarat:** 2 mill ada
- **Langkah:** buka **Mills Setting**.
- **Hasil diharapkan:** hanya **mill sendiri** yang dapat diatur. Mill lain tidak terlihat dan tidak dapat dipilih.
- **Prioritas:** Tinggi

### AD-08 — Pengaturan Mill: Admin dapat semua mill
- **Peran:** `ADM`
- **Langkah:** buka Mills Setting.
- **Hasil diharapkan:** seluruh mill dapat dipilih dan diatur.
- **Prioritas:** Tinggi

### AD-09 — Supervisor dan Operator tidak punya Pengaturan Mill
- **Peran:** `SPV-A`, `OPR-A`
- **Langkah:** jalankan `LS-01` pada layar ini.
- **Hasil diharapkan:** **403** untuk keduanya, dan menunya tidak ada di sidebar.
- **Prioritas:** Tinggi

### AD-10 — Perubahan Pengaturan Mill tersimpan dan berlaku
- **Peran:** `MGR-A`
- **Langkah:** ubah satu nilai pengaturan, Simpan, muat ulang, lalu periksa layar yang memakainya.
- **Hasil diharapkan:** nilai baru bertahan setelah muat ulang dan berlaku di layar yang bersangkutan.
- **Prioritas:** Sedang

---

## 10. Matriks penelusuran — layar → kasus uji

Dipakai untuk memastikan **tidak ada layar yang terlewat**. Kolom "Jumlah jalan" adalah
berapa kali kasus itu dijalankan (18× berarti sekali per stasiun).

| Kelompok layar | Jumlah layar | Kasus uji | Jumlah jalan |
|---|---:|---|---|
| Login Web / Mobile | 2 | `AU-01`…`AU-06` | 1× |
| Ganti Password Web / Mobile | 2 | `AU-07`, `AU-08` | 1× |
| Home (Mobile) | 1 | `MB-HOME-01` | 1× |
| Station List (Mobile) | 1 | `MB-HOME-02`…`04`, `DS-12` | 1× |
| **Monitor (Mobile)** | **18** | `MB-MON-01`…`07` | **18×** |
| **Form (Mobile)** | **18** | `MB-FORM-01`…`15`, `MB-SYNC-01`…`05` | **18×** |
| **Data Preview (Mobile)** | **18** | `MB-PRV-01`…`08` | **18×** |
| **Data Browser (Web)** | **18** | `WB-BRW-01`…`09`, `LS-11` | **18×** |
| **Detail (Web)** | **18** | `WB-DTL-01`…`05` | **18×** |
| **Form (Web)** | **18** | `WB-FRM-01`…`11`, `LS-14`, `LS-15` | **18×** |
| Data Saya (Web) | 1 | `WB-SAYA-01`…`04` | 1× |
| Production Process Activity (Web) | 1 | `DS-08`…`DS-12` | 1× |
| Dashboard (Web) | 1 | `DS-01`…`DS-03` | 1× |
| Laporan Manajemen | 1 | `DS-06` | 1× |
| Laporan Stasiun (Web) | 1 | `DS-04`, `DS-05` | 1× |
| Dashboard & Reporting (Mobile) | 1 | `DS-07` | 1× |
| Reporting — Pilih Stasiun (Mobile) | 1 | `DS-07` | 1× |
| **Laporan Periode (Web)** | **11** | `LP-W-01`…`19` | **11×** |
| **Laporan Periode (Mobile)** | **11** | `LP-M-01`…`12` | **11×** |
| Kelola Corporate…Machinery (7 layar) | 7 | `MD-KEL-01`…`12` | 7× |
| Kelola User & Role | 1 | `AD-01`…`AD-06`, `MD-KEL-01`…`12` | 1× |
| Struktur Mills | 1 | `MD-STR-01`…`05` | 1× |
| Kelola Periode Pelaporan | 1 | `MD-PRD-01`…`11` | 1× |
| Mills Setting | 1 | `AD-07`…`AD-10` | 1× |
| **Total layar tercakup** | **154** | | |

### 10.1 Perkiraan beban kerja

| Bagian | Kasus unik | Jumlah jalan | Hitungannya |
|---|---:|---:|---|
| Lintas-sistem (`LS-`) | 16 | tersebar | dijalankan di layar yang disebut kolom "Jalankan di" |
| Autentikasi (`AU-`) | 9 | 9 | 1× masing-masing |
| Mobile (`MB-`) | 39 | **559** | Home 4×1 + Monitor 7×18 + Form 15×18 + Sync 5×3 + Preview 8×18 |
| Web stasiun (`WB-`) | 29 | **454** | Browser 9×18 + Detail 5×18 + Form 11×18 + Data Saya 4×1 |
| Laporan periode (`LP-`) | 31 | **341** | Web 19×11 + Mobile 12×11 |
| Dashboard (`DS-`) | 12 | 12 | 1× masing-masing |
| Master data (`MD-`) | 28 | **112** | Kelola 12×8 + Struktur 5×1 + Periode 11×1 |
| User/Mill (`AD-`) | 10 | 10 | 1× masing-masing |
| **Total** | **174 kasus unik** | **± 1.500 jalan** | |

> **`MB-SYNC-01`…`05` dijalankan 3×, bukan 18×.** Yang diuji adalah mekanisme
> sinkronisasinya, bukan stasiunnya — dan mekanisme itu satu untuk semua. Jalankan pada
> **tiga stasiun yang bentuknya berbeda**: satu stasiun 24-slot (mis. **Depricarping**),
> satu stasiun per-transaksi (mis. **Weighbridge**), dan satu stasiun yang punya kolom
> status pilihan (mis. **Effluent Plant**). Kalau ketiganya lulus, mengulanginya 15× lagi
> tidak menjawab pertanyaan baru.

> **Saran urutan pengerjaan:** jalankan `LS-` dan `AU-` lebih dulu — kalau salah satu di
> situ gagal, banyak kasus di bawahnya akan gagal karena sebab yang sama, dan menjalankannya
> dulu akan membuang waktu. Setelah itu kerjakan **satu stasiun secara penuh** (mis.
> Depricarping: Monitor → Form → Data Preview → Data Browser → Detail → Form web → Laporan)
> sebelum mengulangi untuk 17 stasiun lain. Dengan begitu kesalahan pola ditemukan pada
> stasiun pertama, bukan pada stasiun kedelapan belas.

---

## 11. Yang TIDAK dicakup dokumen ini

Bagian ini ada supaya tidak ada yang mengira cakupannya lebih luas daripada kenyataannya.

### 11.1 Layar yang belum dibangun

| Layar | Keadaan |
|---|---|
| Laporan Effluent Plant (Web) — `screen-156` | Spesifikasinya sudah ditulis, **kodenya belum ada**. Tidak dapat diuji. |
| Laporan Effluent Plant (Mobile) — `screen-157` | Sama. |
| Satu layar Form web | Spesifikasinya ada, implementasinya belum tercatat selesai. |

**6 stasiun belum punya laporan periode sama sekali**: Engine Room, Process Water,
Solid Waste Disposal, Kernel Dispatch, CPO Dispatch, Process Quality Control. Tile-nya
harus **kelabu** (diuji `DS-04` dan `DS-07`), bukan hilang.

### 11.2 Hal yang sengaja tidak diuji di sini

| Tidak dicakup | Alasan |
|---|---|
| Performa / beban | Butuh alat dan data berukuran produksi; bukan blackbox fungsional. |
| Keamanan mendalam (injeksi, token) | Perlu pengujian keamanan tersendiri. Yang dicakup di sini hanya **batas peran dan batas mill**. |
| Tampilan antar-browser | Belum ditetapkan browser mana yang didukung. |
| Aplikasi native (Android/iOS terpasang) | Kasus mobile di sini dijalankan di browser perangkat / emulator. Perilaku native (notifikasi, izin penyimpanan) belum dicakup. |
| Migrasi data lama | Tidak ada data lama di lingkup MVP. |

### 11.3 Catatan yang sudah diketahui tim pengembang

Hal-hal di bawah **sudah diketahui** dan **bukan temuan baru**. Kalau QA menemukannya,
cukup rujuk nomor ini alih-alih membuat laporan bug baru.

| No | Hal |
|---|---|
| K-1 | **Satuan suhu ditulis `C`, bukan `°C`** pada laporan Kernel Plant (web & mobile). Disengaja: nilainya datang dari data master apa adanya, dan mengubahnya di tampilan akan membuat web dan mobile berselisih. |
| K-2 | **Nama kolom `shell_loss_percent` vs standar "Shell Bin Kernel Loss"** pada Kernel Plant — kemungkinan dua kuantitas berbeda. Keputusan penamaan belum diambil. Tabelnya masih kosong. |
| K-3 | **Laporan Depricarping (Mobile)**: keterangan cakupan menyebut "enam kolom bacaan" padahal yang benar sembilan. Kosmetik. |
| K-4 | **Laporan Depricarping (Mobile)**: tiga angka pada blok downtime tampil tanpa gaya yang semestinya. Kosmetik. |
| K-5 | **Dashboard hanya meringkas 3 stasiun** (Weighbridge, Grading, Cages Track), bukan 18. Disengaja. |
| K-6 | **Satu kasus uji otomatis kadang gagal acak** (`DashboardServiceTest`). Tidak memengaruhi perilaku aplikasi; sedang diselidiki. |
| K-7 | **Cakupan baris kode belum diukur.** Tidak ada hubungannya dengan hasil pengujian manual. |

### 11.4 Hal yang TERLIHAT seperti bug tetapi BUKAN

Daftar ini ada karena keenam hal berikut **akan** dilaporkan sebagai bug oleh QA yang
belum membaca dokumen ini — dan keenamnya benar.

| Kelihatan salah | Sebenarnya |
|---|---|
| Laporan tidak menandai nilai yang jelas di luar batas | **Disengaja.** Kolom targetnya teks bebas yang bentuknya tidak dibatasi, dan satu sel bisa memuat dua angka dengan satuan berbeda. Ada kalimat di layar yang menjelaskannya. (`LS-16`) |
| Slot waktu 01:00 dianggap **sesudah** 23:00 | **Benar.** Shift dimulai 07:00, jadi urutannya 07:00 → 23:00 → 00:00 → 06:00. (`MB-FORM-04`) |
| Satu stasiun menunjukkan "belum ada input hari ini" **dan** "input terakhir 3 menit lalu" | **Benar.** Dua jendela waktu yang berbeda, dan keterangan di halaman menjelaskannya. (`DS-10`) |
| Persen cakupan **0,0%** di satu periode dan **tanda pisah** di periode lain, keduanya "tanpa data" | **Benar dan berbeda.** 0,0% = penyebutnya ada, nol yang terisi. Tanda pisah = penyebutnya tidak terbentuk. (`LP-W-05`) |
| Jumlah slot terisi pada cakupan **lebih besar** daripada penyebut parameter mana pun | **Benar.** Slot yang hanya mengisi kolom temuan terhitung terisi tanpa menambah penyebut parameter. (`LP-W-03`) |
| Line tunggal tetap harus dipilih manual | **Disengaja.** Memilihnya otomatis membuat pembaca mengira ia melihat seluruh mill. (`LS-08`) |

---

## 12. Lembar hasil

Salin tabel ini per bagian. Untuk kasus yang dijalankan 18× atau 11×, buat satu baris
per stasiun.

| ID Kasus | Stasiun / Varian | Peran | Tanggal | Penguji | Hasil | Catatan / No. Bug |
|---|---|---|---|---|---|---|
| `LS-01` | Data Browser | Operator | | | Lulus / Gagal / Tidak diuji | |
| `MB-FORM-04` | Depricarping | Operator | | | | |
| … | | | | | | |

**Aturan pengisian kolom Hasil:**

- **Lulus** — hasil yang terlihat **sama** dengan "Hasil diharapkan", seluruhnya.
- **Gagal** — ada perbedaan. Tulis **apa yang terlihat**, bukan dugaan sebabnya.
- **Tidak diuji** — prasyaratnya tidak dapat disiapkan. **Jangan** ditandai Lulus.
  Contoh: `WB-BRW-09` butuh data melebihi 50.000 baris.

> Kasus yang prasyaratnya tidak tersedia **bukan** kasus yang lulus. Menandainya Lulus
> membuat laporan pengujian menyatakan sesuatu yang tidak pernah diperiksa.
