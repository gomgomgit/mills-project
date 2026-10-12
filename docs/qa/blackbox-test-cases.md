# Mills Smart Log — Test Case Blackbox (Web & Mobile)

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
  Peran            : akun mana yang menjalankannya (lihat bagian 1.2)
  Prasyarat        : keadaan yang harus ada SEBELUM langkah pertama
  Langkah          : 1. 2. 3. … tindakan bernomor, dijalankan berurutan
  Hasil diharapkan : daftar pengamatan, masing-masing bisa dicentang
  Prioritas        : Tinggi / Sedang / Rendah
  Catatan QA       : hanya ada bila kasusnya punya jebakan — baca sebelum menilai
```

**Langkah selalu dimulai dari keadaan belum login.** Langkah pertama hampir selalu
sebuah kode jalan masuk seperti `[W-BROWSE]` atau `[M-FORM]` — kodenya dijabarkan di
bagian 0.3. Di berkas Excel kode itu sudah **diperluas menjadi kalimat**, jadi satu
baris di sana dapat dijalankan tanpa berpindah lembar.

**Hasil diharapkan ditulis sebagai beberapa butir, bukan satu paragraf,** supaya
penguji dapat memeriksanya satu per satu. Satu butir yang tidak terpenuhi sudah cukup
untuk menandai kasusnya **Gagal** — dan di kolom Catatan tulislah butir mana yang
tidak terpenuhi, bukan hanya "gagal".

**Prioritas** dibaca begini: **Tinggi** = kalau ini gagal, fitur tidak boleh dirilis.
**Sedang** = gagal berarti pengguna terganggu tapi masih bisa bekerja. **Rendah** = kosmetik
atau kasus sangat jarang.

### 0.3 Jalan masuk (dipakai sebagai langkah pertama)

Hampir setiap kasus dimulai dengan membuka satu layar. Supaya langkahnya tidak
mengulang "login lalu buka menu" seratus tujuh puluh kali, jalan masuknya diberi
kode di sini. Kode seperti `[W-BROWSE]` di langkah pertama berarti **jalankan
seluruh baris tabel ini lebih dulu**.

Alamatnya ditulis apa adanya supaya penguji dapat mengetiknya langsung — itu juga
yang dibutuhkan kasus `LS-01`, yang justru menguji alamat yang tidak boleh dibuka.

**Web**

| Kode | Jalannya | Alamat |
| --- | --- | --- |
| `[W-LOGIN]` | Buka alamat Login, isi username dan password akun yang disebut kolom **Peran**, tekan **Login** | `/login` |
| `[W-BROWSE]` | `[W-LOGIN]`, lalu sidebar → **Data** → nama stasiun | `/data/<stasiun>` |
| `[W-DETAIL]` | `[W-BROWSE]`, lalu klik salah satu baris pada tabel | `/data/<stasiun>/<id>` |
| `[W-FORM-NEW]` | `[W-BROWSE]`, lalu tekan **Tambah** | `/data/<stasiun>/create` |
| `[W-FORM-EDIT]` | `[W-DETAIL]`, lalu tekan **Edit** | `/data/<stasiun>/<id>/edit` |
| `[W-REPORT]` | `[W-LOGIN]`, sidebar → **Laporan Stasiun**, tekan tile stasiunnya, lalu pilih **Periode** dan **Production Line** | `/reports` → `/reports/<stasiun>` |
| `[W-DASH]` | `[W-LOGIN]`, lalu sidebar → **Dashboard** | `/dashboard` |
| `[W-PPA]` | `[W-LOGIN]`, lalu sidebar → **Production Process Activity** | `/production-process-activity` |
| `[W-MGMT]` | `[W-LOGIN]`, lalu sidebar → **Laporan Manajemen** | `/reports/management` |
| `[W-PERIOD]` | `[W-LOGIN]`, lalu sidebar → **Kelola Periode Pelaporan** | `/master-data/periods` |
| `[W-TREE]` | `[W-LOGIN]`, lalu sidebar → **Struktur Mills** | `/master-data/tree-view` |
| `[W-KELOLA]` | `[W-LOGIN]`, lalu sidebar → **Kelola <tingkat>** | `/master-data/corporates`, `/companies`, `/business-units`, `/production-lines`, `/stations`, `/machinery`, `/machinery-groups` |
| `[W-USERS]` | `[W-LOGIN]`, lalu sidebar → **Kelola User & Role** | `/users` |
| `[W-MILLSET]` | `[W-LOGIN]`, lalu sidebar → **Mills Setting** | `/mill-settings` |
| `[W-PWD]` | `[W-LOGIN]`, lalu sidebar → **Ganti Password** | `/settings/password` |

**Mobile**

| Kode | Jalannya | Alamat |
| --- | --- | --- |
| `[M-LOGIN]` | Buka aplikasi, isi username dan password, tekan **Login** | `/login` → `/home` |
| `[M-LIST]` | `[M-LOGIN]`, lalu tekan **Station List**; pilih **Production Line** bila diminta | `/stations` |
| `[M-MON]` | `[M-LIST]`, lalu tekan tile stasiunnya | `/stations/<stasiun>/monitor` |
| `[M-FORM]` | `[M-MON]`, lalu tekan **New Data** | `/stations/<stasiun>/form/<id>` |
| `[M-PREVIEW]` | `[M-MON]`, lalu tekan **Load Data** | `/stations/<stasiun>/preview` |
| `[M-REPORT]` | `[M-LOGIN]`, tekan **Dashboard & Reporting** → **Reporting**, tekan tile stasiunnya, lalu pilih **Periode** dan **Production Line** | `/dashboard-reporting` → `/reports` → `/reports/<stasiun>` |
| `[M-PWD]` | `[M-LOGIN]`, lalu menu (ikon tiga garis) → **Ganti Password** | `/settings/password` |

`<stasiun>` adalah kode stasiun bertanda hubung: `weighbridge`, `grading`,
`cages-track`, `sterilizer`, `threshing`, `pressing`, `clarification`,
`kernel-plant`, `boiler-room`, `effluent-plant`, `depricarping`, `engine-room`,
`process-water`, `storage-tank`, `solid-waste-disposal`, `kernel-dispatch`,
`cpo-dispatch`, `process-quality-control`.

### 0.4 Awalan ID

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

### 0.5 Kata yang dipakai konsisten

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
| Isi **Checked By** | — | ✓ | — | — **(Admin pun tidak)** |
| Isi **Acknowledged By** | — | — | ✓ | — **(Admin pun tidak)** |
| Lihat Data Browser / Detail (Web) | — | ✓ | ✓ | ✓ |
| Laporan periode stasiun (Web) | — | ✓ | ✓ | ✓ |
| Laporan periode stasiun (Mobile) | ✓ | ✓ | ✓ | ✓ |
| Dashboard (Web) | — | ✓ | ✓ | ✓ |
| Laporan Manajemen | — | — | ✓ | — **(Admin pun tidak)** |
| Master data (Corporate…Mesin) | — | — | — | ✓ |
| Periode Pelaporan: tambah/ubah/hapus | — | — | — | ✓ |
| **Tutup / Buka Periode** | — | — | — | ✓ **(hanya Admin)** |
| Pengaturan Mill | — | — | ✓ (mill sendiri) | ✓ (semua mill) |
| Kelola User & Role | — | — | — | ✓ |
| Pemilih **Mill** pada laporan | — | — | — | ✓ **(hanya Admin)** |

> **Verifikasi adalah pernyataan diri.** Checked By hanya dapat diisi **Supervisor**, dan
> Acknowledged By hanya **Mill Management** — keduanya menandai bahwa *orang itu sendiri* yang
> memeriksa. Admin **tidak** dapat mengisi keduanya, dan kendalinya memang tidak ditampilkan
> untuk Admin. Itu bukan bug.

**Akses web Operator dibatasi pada:** Beranda Operator dan Ganti Password — hanya dua layar.
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
  1. `[W-LOGIN]` sebagai salah satu peran.
  2. Buka matriks peran bagian 1.3. Pilih satu baris yang bertanda "—" untuk peran itu.
  3. Ketik alamat layar itu langsung di bilah alamat browser (lihat tabel bagian 0.3) — **jangan** lewat menu, karena menunya memang tidak akan muncul.
  4. Tekan Enter.
  5. Ulangi langkah 2–4 untuk **setiap** baris bertanda "—" pada peran itu.
  6. Ulangi seluruh kasus untuk keempat peran.
- **Hasil diharapkan:**
  - Muncul halaman **403 / Akses Ditolak** berbahasa Indonesia.
  - Halaman 403 itu masih berada **di dalam kerangka aplikasi** — sidebar dan tombol Logout masih ada.
  - BUKAN halaman putih, BUKAN pesan galat teknis (stack trace, "Server Error", kode dalam bahasa Inggris).
  - BUKAN dialihkan diam-diam ke beranda: alamat di bilah alamat tetap alamat yang diketik.
- **Jalankan di:** seluruh layar Web
- **Prioritas:** Tinggi

### LS-02 — Menu yang tidak boleh diakses juga tidak muncul di sidebar
- **Peran:** semua, bergantian
- **Prasyarat:** —
- **Langkah:**
  1. `[W-LOGIN]` sebagai salah satu peran.
  2. Baca seluruh isi sidebar dari atas ke bawah, catat setiap menu yang terlihat.
  3. Bandingkan catatan itu dengan kolom peran tersebut pada matriks bagian 1.3.
  4. Klik **setiap** menu yang terlihat, satu per satu.
  5. Ulangi untuk keempat peran.
- **Hasil diharapkan:**
  - Sidebar hanya memuat menu yang bertanda ✓ untuk peran itu di matriks 1.3.
  - Tidak ada menu yang bertanda "—" tetapi tetap terlihat.
  - Setiap menu yang diklik **terbuka** — tidak ada satu pun yang menjawab 403. Menu yang terlihat tapi menolak saat diklik adalah bug.
- **Prioritas:** Tinggi

### LS-03 — Data mill lain tidak pernah terlihat
- **Peran:** `SPV-A`, lalu `SPV-B`
- **Prasyarat:** Mill A dan Mill B keduanya punya record pada tanggal yang sama
- **Langkah:**
  1. `[W-LOGIN]` sebagai `SPV-A`.
  2. `[W-BROWSE]` untuk satu stasiun. Catat **jumlah data** yang tertulis dan **seluruh nama Production Line** yang muncul di pemilih maupun di tabel.
  3. Buka pemilih Production Line dan catat seluruh pilihannya.
  4. Logout.
  5. `[W-LOGIN]` sebagai `SPV-B`, lalu buka layar yang sama.
  6. Bandingkan dengan catatan langkah 2 dan 3.
- **Hasil diharapkan:**
  - `SPV-B` **tidak** melihat satu pun record milik Mill A.
  - `SPV-B` **tidak** melihat satu pun nama Production Line Mill A — tidak di pemilih, tidak di tabel, tidak di keterangan mana pun.
  - Jumlah datanya berbeda, dan yang terlihat hanya milik Mill B.
- **Jalankan di:** Data Browser, Detail, Form, seluruh Laporan Periode, Dashboard
- **Prioritas:** Tinggi

### LS-04 — Akun tanpa mill ditolak dengan pesan yang menyebut sebabnya
- **Peran:** `NOMILL`
- **Prasyarat:** akun Supervisor yang kolom mill-nya kosong
- **Langkah:**
  1. Buka `/login`. Isi username dan password `NOMILL` (akun Supervisor yang kolom mill-nya kosong), tekan **Login**.
  2. Baca pesan yang muncul **kata per kata**.
  3. Bila ternyata berhasil masuk, lanjutkan: `[W-BROWSE]` satu stasiun, `[W-REPORT]` satu stasiun, lalu `[W-DASH]`. Baca isi ketiga layar.
- **Hasil diharapkan:**
  - Akun itu **ditolak**, bukan dibiarkan masuk lalu melihat layar kosong. Boleh ditolak di Login (tidak pernah sampai ke layar mana pun), boleh juga masuk lalu setiap layar menampilkan pesannya — keduanya sama-sama benar, selama penolakannya **disebutkan sebabnya**.
  - Pesannya menyebut **mill** — misalnya "Akun Anda belum terhubung ke mill" — atau kalimat sepadan yang menyebut mill.
  - Pesannya mengarahkan tindakan (mis. menghubungi Admin), bukan sekadar menyatakan ada yang tidak sesuai.
  - Pesannya **tidak** menyebut pilihan yang tidak pernah dibuat pengguna. Layar Login hanya meminta username dan password; pesan yang menyebut sesuatu "yang dipilih" mengirim pengguna mencari pilihan yang tidak ada.
  - BUKAN tabel kosong. BUKAN angka nol. BUKAN daftar seluruh mill. BUKAN pesan periksa koneksi.
- **Prioritas:** Tinggi
- **Catatan QA:** tabel kosong di sini adalah **bug**, bukan hasil yang benar. Tabel kosong terbaca "mill ini tidak punya data", padahal yang benar "akun Anda belum diatur". Hal yang sama berlaku untuk pesan yang benar menolak tetapi salah menyebut sebabnya.

### LS-05 — Hanya Admin melihat pemilih Mill
- **Peran:** `SPV-A`, `MGR-A`, `ADM`
- **Prasyarat:** minimal 2 mill ada
- **Langkah:**
  1. `[W-REPORT]` untuk satu stasiun sebagai `SPV-A`. Cari pemilih **Mill** di panel filter.
  2. Logout. Ulangi sebagai `MGR-A`.
  3. Logout. Ulangi sebagai `ADM`.
- **Hasil diharapkan:**
  - `SPV-A`: **tidak ada** pemilih Mill. Nama mill-nya hanya **ditampilkan** sebagai teks.
  - `MGR-A`: sama — hanya ditampilkan, tidak dapat diubah.
  - `ADM`: **ada** pemilih Mill, dan isinya memuat kedua mill.
- **Prioritas:** Tinggi

### LS-06 — Sesi berakhir mengarahkan ke Login tanpa pesan jaringan
- **Peran:** semua
- **Prasyarat:** sudah login; sesi/token dibuat kedaluwarsa (mis. akun dinonaktifkan Admin dari layar lain)
- **Langkah:**
  1. `[W-LOGIN]` sebagai `SPV-A`. Biarkan layar terbuka.
  2. Di jendela browser lain, `[W-USERS]` sebagai `ADM` dan **nonaktifkan** akun `SPV-A`.
  3. Kembali ke jendela `SPV-A`. Lakukan satu tindakan apa pun: muat ulang halaman, ubah satu filter, atau tekan Simpan.
  4. Baca pesan yang muncul.
- **Hasil diharapkan:**
  - Diarahkan ke layar **Login**.
  - Pesannya menyebut sesi/akun — mis. sesi berakhir atau akun dinonaktifkan.
  - Pesannya **tidak** menyuruh memeriksa koneksi atau jaringan. Kalau muncul "Periksa koneksi Anda", itu **bug**.
- **Prioritas:** Tinggi
- **Catatan QA:** "Periksa koneksi Anda" pada sesi yang berakhir mengirim pengguna memeriksa sinyal — arah yang salah. Kalau pesan itu muncul, ini **bug**.

### LS-07 — Nol dirender sebagai pernyataan, bukan sebagai angka 0
- **Peran:** `SPV-A`
- **Prasyarat:** satu stasiun / satu periode yang **belum ada datanya sama sekali**
- **Langkah:**
  1. Siapkan satu stasiun atau satu periode yang **belum ada datanya sama sekali**.
  2. `[W-DASH]` sebagai `SPV-A`. Baca setiap angka di layar.
  3. `[W-REPORT]` untuk stasiun/periode kosong itu. Baca setiap angka dan setiap kartu.
  4. `[W-PPA]`. Baca tile stasiun yang belum ada datanya.
- **Hasil diharapkan:**
  - Tempat yang belum ada datanya berbunyi **"belum ada input"**, **"tidak tersedia"**, atau tanda pisah **"—"**.
  - BUKAN angka `0`. BUKAN `0%`. BUKAN `0,0`.
  - Angka `0` hanya muncul bila memang sudah diukur dan hasilnya nol — bukan untuk menyatakan ketiadaan data.
- **Prioritas:** Sedang
- **Catatan QA:** angka `0` menyatakan "sudah diukur, hasilnya nol". Belum ada data adalah hal berbeda, dan keduanya tidak boleh terlihat sama.

### LS-08 — Production Line wajib dipilih di Laporan Periode, termasuk saat hanya ada satu line
- **Peran:** `SPV-A`
- **Prasyarat:** satu mill dengan **satu** Production Line, dan satu mill dengan **dua** line
- **Langkah:**
  1. Siapkan satu mill dengan **satu** Production Line, dan satu mill dengan **dua** line.
  2. `[W-LOGIN]` sebagai `ADM`, lalu sidebar → **Laporan Stasiun** → tekan tile satu stasiun.
  3. Pilih mill yang hanya punya **satu** line. **Jangan** pilih Production Line apa pun. Baca layar.
  4. Pilih Production Line satu-satunya itu. Baca layar.
  5. Ganti ke mill yang punya **dua** line. Baca layar.
- **Hasil diharapkan:**
  - Pada langkah 3, meski hanya ada satu pilihan, line itu **tidak** terpilih otomatis: layar meminta memilih Production Line dan **tidak** menampilkan satu angka pun.
  - Pada langkah 4, angka-angkanya muncul.
  - Pada langkah 5, pilihan line **direset** — angkanya hilang lagi sampai line dipilih.
- **Prioritas:** Tinggi
- **Catatan QA:** memilih sendiri line tunggal akan membuat pembaca mengira ia melihat seluruh mill, padahal ia melihat satu line yang kebetulan satu-satunya hari itu.

### LS-09 — Mengirim id Production Line milik mill lain diabaikan, bukan ditolak dengan galat
- **Peran:** `SPV-A`
- **Prasyarat:** id line Mill B diketahui (ambil dari sesi `SPV-B`)
- **Langkah:**
  1. `[W-REPORT]` untuk satu stasiun sebagai `SPV-A`, pilih periode dan line milik Mill A. Catat angkanya.
  2. Salin alamat dari bilah alamat. Di dalamnya ada bagian `production_line_id=<id>`.
  3. Ambil id Production Line milik **Mill B** (dari `[W-KELOLA]` → Kelola Production Line sebagai `ADM`).
  4. Tempel alamat langkah 2 dengan `production_line_id` diganti id Mill B itu, lalu tekan Enter.
- **Hasil diharapkan:**
  - Halaman **tetap terbuka** — bukan 403, bukan galat.
  - Line mill lain itu **diabaikan**: layar jatuh ke keadaan "belum memilih Production Line", atau pemilihnya kembali kosong.
  - Nama Production Line Mill B **tidak pernah tercetak** di layar — tidak di pemilih, tidak di judul, tidak di keterangan. Idnya boleh tergaung di alamat (pemanggil sendiri yang mengirimnya); namanya tidak boleh.
- **Prioritas:** Tinggi

### LS-10 — Periode tertutup mengunci input, periode draft/terbuka tidak
- **Peran:** `SPV-A` (input), `ADM` (ubah status)
- **Prasyarat:** satu periode yang mencakup hari ini
- **Langkah:**
  1. `[W-PERIOD]` sebagai `ADM`. Pilih satu periode yang stasiunnya masih **Terbuka**.
  2. `[M-FORM]` sebagai `OPR-A` untuk stasiun itu. Isi satu baris, tekan **Simpan**. Catat hasilnya.
  3. Kembali ke `[W-PERIOD]` sebagai `ADM`. Pada periode itu, tekan **Tutup** untuk stasiun tersebut.
  4. Ulangi langkah 2: coba simpan satu record baru untuk stasiun yang sama pada tanggal dalam periode itu.
  5. Kembali sebagai `ADM`, tekan **Buka Kembali** pada stasiun itu.
  6. Ulangi langkah 2 sekali lagi.
- **Hasil diharapkan:**
  - Langkah 2 (Terbuka): **tersimpan**.
  - Langkah 4 (Tertutup): **ditolak**, dengan pesan yang menyebut periode tertutup. Record **tidak** tersimpan.
  - Langkah 6 (Terbuka kembali): **tersimpan** lagi.
  - Periode berstatus draft berperilaku **sama seperti terbuka** — tidak mengunci.
- **Prioritas:** Tinggi

### LS-11 — Ekspor menghasilkan berkas yang benar-benar terbuka
- **Peran:** `SPV-A`
- **Prasyarat:** ada data pada filter yang dipilih
- **Langkah:**
  1. `[W-REPORT]` untuk satu stasiun, pilih periode dan line yang punya data.
  2. Tekan **Ekspor CSV**. Tunggu berkas terunduh.
  3. Buka berkas itu dengan Excel atau LibreOffice.
  4. Tekan **Ekspor Excel**. Tunggu berkas terunduh, lalu buka.
  5. Bandingkan beberapa angka di berkas dengan angka di layar.
- **Hasil diharapkan:**
  - Kedua berkas **benar-benar terunduh** dan punya nama berkas yang menyebut stasiun dan periode.
  - Keduanya **terbuka tanpa peringatan rusak** di Excel/LibreOffice.
  - Baris pertama adalah baris judul kolom yang terbaca, bukan kode teknis.
  - Angka yang dicocokkan **sama** dengan yang di layar.
  - Huruf berakhiran Indonesia (mis. nama dengan é atau karakter khusus) tidak berubah menjadi simbol aneh.
- **Jalankan di:** 18 Data Browser + seluruh Laporan Periode web
- **Prioritas:** Tinggi

### LS-12 — Ekspor di mobile hanya CSV
- **Peran:** `OPR-A`
- **Prasyarat:** —
- **Langkah:**
  1. `[M-REPORT]` untuk satu stasiun, pilih periode dan line yang punya data.
  2. Baca seluruh tombol di bagian bawah layar.
  3. Tekan tombol ekspor yang ada.
- **Hasil diharapkan:**
  - Hanya ada **satu** pilihan ekspor, dan itu **CSV**.
  - **Tidak ada** tombol Ekspor Excel di mobile.
  - Berkasnya terunduh dan dapat dibuka.
- **Prioritas:** Sedang

### LS-13 — Seluruh tampilan berbahasa Indonesia
- **Peran:** semua
- **Prasyarat:** —
- **Langkah:**
  1. Jalankan satu kasus apa pun sampai selesai pada layar yang diuji.
  2. Baca seluruh teks di layar: judul, label kolom, tombol, keterangan, dan **pesan galat**.
  3. Picu sengaja satu galat (mis. kosongkan kolom wajib lalu Simpan) dan baca pesannya.
- **Hasil diharapkan:**
  - Seluruh teks berbahasa **Indonesia**.
  - Pesan galat juga berbahasa Indonesia — bukan "The field is required", bukan "Unauthenticated".
  - Nama kolom teknis (mis. `production_line_id`) tidak muncul di pesan yang dibaca pengguna.
  - Istilah stasiun dan satuan boleh tetap berbahasa Inggris bila itu memang istilah pabrik (mis. "Ripple Mill", "mmH2O").
- **Prioritas:** Sedang

### LS-14 — Tidak ada kolom input yang mati tanpa alasan
- **Peran:** `SPV-A`, `MGR-A`
- **Prasyarat:** —
- **Langkah:**
  1. `[W-FORM-NEW]` untuk satu stasiun.
  2. Coba klik dan mengetik pada **setiap** kolom input di layar, satu per satu.
  3. Catat kolom mana pun yang tidak dapat diketik atau tampak kelabu.
  4. Untuk setiap kolom seperti itu, cari keterangan di layar yang menjelaskan sebabnya.
- **Hasil diharapkan:**
  - Tidak ada kolom input yang mati **tanpa alasan yang tertulis di layar**.
  - Kolom yang memang tidak boleh diubah (mis. Tanggal yang terisi otomatis) punya keterangan yang menyebutkan itu.
  - Kolom Checked By / Acknowledged By yang tidak dapat diisi peran itu disertai keterangan siapa yang boleh mengisinya.
- **Prioritas:** Sedang

### LS-15 — Kolom tanggal dan jam bisa diketik manual, bukan hanya lewat pemilih
- **Peran:** `SPV-A`
- **Prasyarat:** —
- **Langkah:**
  1. `[W-FORM-NEW]` untuk satu stasiun yang punya kolom tanggal atau jam.
  2. Klik kolom tanggal, lalu **ketik** tanggalnya langsung dari papan tulis (mis. `08/10/2026`).
  3. Hapus isinya, lalu pilih tanggal yang sama lewat **ikon kalender**.
  4. Ulangi langkah 2 dan 3 untuk kolom jam, bila ada.
- **Hasil diharapkan:**
  - Kolom tanggal dapat **diketik manual** dan nilainya diterima.
  - Kolom tanggal **juga** dapat diisi lewat pemilih kalender.
  - Keduanya menghasilkan nilai yang sama — tidak ada yang hanya menerima salah satu cara.
  - Hal yang sama berlaku untuk kolom jam.
- **Prioritas:** Sedang

### LS-16 — Laporan tidak menandai satu nilai pun sebagai "di luar batas"
- **Peran:** `SPV-A`
- **Prasyarat:** ada record dengan nilai yang jelas-jelas jauh di luar target (mis. suhu 3× batas)
- **Langkah:**
  1. `[W-REPORT]` untuk satu stasiun yang **punya master target** (Depricarping, Grading, Kernel Plant, Pressing, atau Threshing).
  2. Pilih periode dan line yang punya data.
  3. Bandingkan tiap angka rata-rata dengan kolom target di sebelahnya. Cari minimal satu angka yang **jelas di luar** rentang targetnya.
  4. Perhatikan angka itu: apakah ia diberi warna, tebal, ikon peringatan, atau latar berbeda?
  5. Cari kotak keterangan di layar yang menjelaskan ketiadaan penandaan itu.
- **Hasil diharapkan:**
  - Angka yang di luar batas **tidak** ditandai apa pun: tidak merah, tidak kuning, tidak ada ikon, tidak ada badge.
  - Ada kotak keterangan **"Mengapa Tidak Ada Nilai yang Ditandai"** yang menjelaskan sebabnya.
  - Keterangan itu menyebut tiga alasan, bukan satu.
  - **Cara memeriksa yang salah:** jangan mencari kata "di luar batas" di halaman — kalimat penjelasnya sendiri memuat frasa itu. Yang diperiksa adalah **tampilan angkanya**.
- **Prioritas:** Sedang
- **Catatan QA:** ketiadaan penandaan di sini **disengaja**, bukan fitur yang belum selesai. Yang harus dilaporkan sebagai bug adalah kalau penandaan itu **muncul**, atau kalau kalimat penjelasnya **hilang**.

---

## 3. Autentikasi & Akun (`AU-`)

Mencakup 4 layar: Login Web, Login Mobile, Ganti Password Web, Ganti Password Mobile.

### AU-01 — Login berhasil mengarahkan ke beranda yang sesuai perannya
- **Peran:** `OPR-A`, `SPV-A`, `MGR-A`, `ADM`
- **Prasyarat:** keempat akun aktif
- **Langkah:**
  1. Buka `/login`. Isi username dan password `OPR-A`, tekan **Login**.
  2. Catat alamat yang dituju dan seluruh isi sidebar.
  3. Logout. Ulangi langkah 1–2 untuk `SPV-A`.
  4. Logout. Ulangi untuk `MGR-A`.
  5. Logout. Ulangi untuk `ADM`.
- **Hasil diharapkan:**
  - Keempatnya **berhasil masuk**.
  - `OPR-A` mendarat di **Beranda Operator** (`/beranda`) dengan pesan bahwa akses webnya terbatas; sidebar hanya memuat **Beranda** dan **Ganti Password**.
  - `SPV-A`, `MGR-A`, `ADM` mendarat di beranda masing-masing dengan menu lengkap sesuai matriks 1.3.
  - Tidak satu pun mendarat di halaman kosong atau halaman galat.
- **Prioritas:** Tinggi

### AU-02 — Login gagal: password salah
- **Peran:** `SPV-A`
- **Langkah:**
  1. Buka `/login`.
  2. Isi username `SPV-A` yang benar, dan password yang **salah**.
  3. Tekan **Login**.
  4. Catat bunyi pesannya **kata per kata** — akan dibandingkan di `AU-03`.
- **Hasil diharapkan:**
  - Tetap di layar **Login**; tidak masuk.
  - Muncul pesan galat berbahasa **Indonesia**.
  - Pesannya **tidak** menyebutkan apakah username-nya terdaftar atau tidak (mis. bukan "Password salah" yang memastikan usernamenya ada).
- **Prioritas:** Tinggi

### AU-03 — Login gagal: username tidak ada
- **Peran:** —
- **Langkah:**
  1. Buka `/login`.
  2. Isi username yang **tidak terdaftar** (mis. `tidakada999`), dan password apa saja.
  3. Tekan **Login**.
  4. Bandingkan bunyi pesannya dengan catatan `AU-02` langkah 4.
- **Hasil diharapkan:**
  - Tetap di layar Login; tidak masuk.
  - Pesannya **sama bunyinya kata per kata** dengan `AU-02`.
  - Kalau bunyinya berbeda, itu **bug**: perbedaan bunyi memberi tahu orang luar username mana yang terdaftar.
- **Prioritas:** Tinggi

### AU-04 — Akun yang dinonaktifkan tidak bisa masuk, dan yang sedang masuk dikeluarkan
- **Peran:** `ADM` + `SPV-A`
- **Prasyarat:** `SPV-A` sedang login di satu browser
- **Langkah:**
  1. `[W-LOGIN]` sebagai `SPV-A` di satu browser. Biarkan terbuka di layar Data Browser salah satu stasiun.
  2. Di browser kedua, `[W-USERS]` sebagai `ADM`.
  3. Cari akun `SPV-A`, tekan **Nonaktifkan**, setujui konfirmasinya.
  4. Kembali ke browser pertama. Muat ulang halaman atau ubah satu filter.
  5. Logout dari browser pertama bila belum. Coba `[W-LOGIN]` lagi sebagai `SPV-A`.
  6. Sebagai `ADM`, **aktifkan kembali** akun `SPV-A` supaya kasus lain tidak terhalang.
- **Hasil diharapkan:**
  - Langkah 4: `SPV-A` **dikeluarkan** ke layar Login — sesi yang sedang berjalan tidak dibiarkan hidup.
  - Langkah 5: login **gagal**, dengan pesan bahwa akunnya tidak aktif.
  - Pesannya tidak menyuruh memeriksa koneksi.
- **Prioritas:** Tinggi

### AU-05 — Login Mobile berhasil untuk keempat peran
- **Peran:** `OPR-A`, `SPV-A`, `MGR-A`, `ADM`
- **Langkah:**
  1. Buka aplikasi mobile. Isi username dan password `OPR-A`, tekan **Login**.
  2. Catat layar yang dituju dan isinya.
  3. Logout. Ulangi untuk `SPV-A`.
  4. Logout. Ulangi untuk `MGR-A`.
  5. Logout. Ulangi untuk `ADM`.
- **Hasil diharapkan:**
  - Keempatnya **berhasil masuk** — termasuk `ADM`, meski mobile bukan alur yang dirancang untuknya.
  - `OPR-A` dan `SPV-A` mendarat di **Home** dengan daftar stasiun.
  - `MGR-A` juga masuk dan melihat Home.
  - Tidak ada yang berhenti di layar kosong atau layar memuat yang tidak selesai.
- **Prioritas:** Tinggi

### AU-06 — Tamu diarahkan ke Login, tidak melihat isi apa pun
- **Peran:** belum login
- **Langkah:**
  1. Pastikan **tidak sedang login** (buka jendela privat/incognito, atau logout lebih dulu).
  2. Ketik `/dashboard` di bilah alamat, tekan Enter.
  3. Perhatikan layar **selama perpindahan** — apakah ada isi yang sempat berkedip terlihat?
  4. Ulangi untuk `/data/weighbridge`, `/reports`, `/master-data/periods`, dan `/users`.
- **Hasil diharapkan:**
  - Setiap alamat **diarahkan ke Login**.
  - Tidak ada isi halaman yang sempat terlihat, bahkan sekejap — tidak ada angka, tidak ada nama, tidak ada kerangka tabel.
  - Alamat akhir di bilah alamat adalah `/login`.
- **Prioritas:** Tinggi

### AU-07 — Ganti Password berhasil, dan password lama berhenti berlaku
- **Peran:** `SPV-A` (web), `OPR-A` (mobile)
- **Langkah:**
  1. `[W-PWD]` sebagai `SPV-A`.
  2. Isi password lama dengan benar, isi password baru dua kali, tekan **Simpan**.
  3. Logout.
  4. Coba login dengan password **baru**.
  5. Logout. Coba login dengan password **lama**.
  6. Kembalikan password ke nilai semula supaya kasus lain tidak terhalang.
- **Hasil diharapkan:**
  - Langkah 2 **berhasil**, dengan pesan konfirmasi.
  - Langkah 4 **berhasil masuk**.
  - Langkah 5 **gagal** — password lama berhenti berlaku.
- **Prioritas:** Tinggi

### AU-08 — Ganti Password ditolak bila password lama salah
- **Peran:** `SPV-A`
- **Langkah:**
  1. `[W-PWD]` sebagai `SPV-A`.
  2. Isi password lama dengan nilai yang **salah**, isi password baru dua kali, tekan **Simpan**.
  3. Baca letak pesan galatnya.
  4. Logout, lalu login dengan password yang **sebenarnya masih berlaku**.
- **Hasil diharapkan:**
  - Ditolak, dengan pesan galat yang muncul **di sebelah kolom password lama** — bukan sebagai satu pesan umum di atas form.
  - Langkah 4 **berhasil**: password tidak berubah sama sekali.
- **Prioritas:** Tinggi

### AU-09 — Logout mencabut sesi perangkat itu
- **Peran:** `OPR-A`
- **Langkah:**
  1. `[W-BROWSE]` sebagai `SPV-A` untuk satu stasiun. Catat satu angka atau nama yang terlihat.
  2. Tekan **Logout**.
  3. Tekan tombol **Back** browser satu kali.
  4. Ketik ulang alamat Data Browser stasiun itu.
- **Hasil diharapkan:**
  - Langkah 2 mengarahkan ke **Login**.
  - Langkah 3: isi halaman lama **tidak** terlihat lagi — tidak ada data yang masih terbaca dari cache browser.
  - Langkah 4 juga diarahkan ke Login.
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
dipakai pada `MB-FORM-05` (minimal satu terisi).

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
- **Langkah:**
  1. `[M-LOGIN]` sebagai `OPR-A`.
  2. Baca seluruh isi layar Home: salam, nama mill, dan daftar/tautan stasiun.
  3. Hitung berapa stasiun yang terlihat.
- **Hasil diharapkan:**
  - Nama pengguna dan nama **mill**-nya tercetak.
  - Ada jalan menuju **Station List** yang terlihat jelas.
  - Tidak ada stasiun milik mill lain yang terlihat.
  - Tidak ada bagian yang kosong tanpa keterangan.
- **Prioritas:** Tinggi

### MB-HOME-02 — Station List mengantar ke Monitor stasiun yang benar
- **Peran:** `OPR-A`
- **Langkah:**
  1. `[M-LIST]` sebagai `OPR-A`.
  2. Catat urutan dan nama ke-18 tile stasiun.
  3. Tekan tile **Pressing**.
  4. Baca judul layar yang terbuka dan alamatnya.
  5. Tekan Back, lalu ulangi langkah 3–4 untuk **Kernel Plant** dan **Weighbridge**.
- **Hasil diharapkan:**
  - Ke-18 tile tampil.
  - Setiap tile mendarat di Monitor **stasiun yang sama dengan namanya** — judulnya cocok, alamatnya `/stations/<stasiun>/monitor`.
  - Tidak ada tile yang mendarat di stasiun lain.
- **Prioritas:** Tinggi

### MB-HOME-03 — Pilihan Production Line diingat perangkat dan dibagi antar layar
- **Peran:** `OPR-A`
- **Prasyarat:** mill punya 2 line
- **Langkah:**
  1. Pastikan mill `OPR-A` punya **2** Production Line.
  2. `[M-LIST]` sebagai `OPR-A`. Pilih **Line 1** pada pemilih Production Line.
  3. Masuk ke Monitor satu stasiun. Catat Production Line yang tertulis.
  4. Tekan Back ke Station List. Perhatikan pemilihnya.
  5. Tutup aplikasi sepenuhnya, lalu buka lagi dan login ulang.
  6. Buka Station List. Perhatikan pemilihnya.
  7. Ganti ke **Line 2**, lalu buka Monitor stasiun yang sama.
- **Hasil diharapkan:**
  - Langkah 4: pemilih masih menunjukkan **Line 1** — pilihannya tidak lupa saat berpindah layar.
  - Langkah 6: masih **Line 1** — pilihannya diingat perangkat setelah aplikasi ditutup.
  - Langkah 7: Monitor kini menunjukkan **Line 2**, dan daftar draftnya ikut berubah sesuai line itu.
- **Prioritas:** Sedang

### MB-HOME-04 — Mill tanpa Production Line memberi arahan, bukan layar kosong
- **Peran:** `OPR-A` pada mill tanpa line
- **Langkah:**
  1. Sebagai `ADM` di web, siapkan satu mill yang **belum punya Production Line**, dan satu akun Operator yang terikat mill itu.
  2. `[M-LOGIN]` sebagai akun itu.
  3. Buka **Station List**. Baca isi layar.
- **Hasil diharapkan:**
  - Muncul **arahan** yang menyebut Production Line — mis. agar menghubungi Admin.
  - BUKAN layar kosong, BUKAN daftar nol tile tanpa keterangan.
  - Tidak ada pesan galat teknis.
- **Prioritas:** Sedang

### 4.4 Monitor (jalankan 18×, satu per stasiun)

### MB-MON-01 — Monitor menampilkan daftar draft dan dua penghitung hari ini
- **Peran:** `OPR-A`
- **Prasyarat:** ada minimal 1 draft dan beberapa record tersimpan hari ini
- **Langkah:**
  1. Siapkan untuk stasiun ini: minimal **1 draft** dan beberapa record **Tersimpan** bertanggal hari ini.
  2. `[M-MON]` untuk <Stasiun>.
  3. Baca dua penghitung di bagian atas layar dan catat angkanya.
  4. Hitung sendiri jumlah baris pada daftar draft.
  5. Bandingkan angka penghitung dengan jumlah record hari ini yang Anda siapkan di langkah 1.
- **Hasil diharapkan:**
  - Daftar draft menampilkan **seluruh** draft stasiun itu.
  - Dua penghitung hari ini tampil dan angkanya **cocok** dengan data yang disiapkan.
  - Draft yang ditampilkan hanya milik stasiun dan Production Line yang sedang terpilih.
- **Prioritas:** Tinggi

### MB-MON-02 — Beberapa draft bisa berjalan bersamaan dan semuanya tampil
- **Peran:** `OPR-A`
- **Langkah:**
  1. `[M-MON]` untuk <Stasiun>.
  2. Tekan **New Data**, lalu langsung tekan **Pause**.
  3. Ulangi langkah 2 dua kali lagi, sehingga ada **3** draft.
  4. Kembali ke Monitor. Hitung baris pada daftar draft.
  5. Baca label status pada ketiga baris itu.
- **Hasil diharapkan:**
  - Ketiga draft tampil — tidak ada yang menimpa yang lain.
  - Ketiganya berlabel **"Pause"**.
  - Membuat draft baru tidak menghapus atau menutup draft sebelumnya.
- **Prioritas:** Sedang

### MB-MON-03 — New Data membuat header saja, grid masih kosong
- **Peran:** `OPR-A`
- **Langkah:**
  1. `[M-MON]` untuk <Stasiun>.
  2. Tekan **New Data**.
  3. Baca bagian header: kolom identitas dan Tanggal.
  4. Baca bagian grid di bawahnya: berapa baris yang sudah ada?
- **Hasil diharapkan:**
  - Layar Form terbuka.
  - Kolom **Tanggal** sudah terisi otomatis dengan tanggal hari ini.
  - Kolom identitas unit **kosong** dan dapat diketik.
  - Grid slot waktu **masih kosong** — belum ada satu baris pun. (Untuk stasiun per-transaksi, tidak ada grid 24 slot sama sekali.)
- **Prioritas:** Tinggi

### MB-MON-04 — Tanggal otomatis memakai tanggal perangkat, termasuk dini hari
- **Peran:** `OPR-A`
- **Prasyarat:** ubah jam perangkat ke **01:00** waktu setempat
- **Langkah:**
  1. Ubah jam perangkat/emulator ke **01:00** waktu setempat, tanggal hari ini.
  2. `[M-MON]` untuk <Stasiun>, lalu tekan **New Data**.
  3. Baca kolom **Tanggal**.
  4. Bandingkan dengan tanggal pada jam perangkat.
  5. Kembalikan jam perangkat ke keadaan semula.
- **Hasil diharapkan:**
  - Tanggal terisi dengan **tanggal perangkat hari itu** — bukan tanggal sebelumnya.
  - Pada 01:00, tanggalnya tetap tanggal kalender hari itu, bukan tanggal shift yang dimulai 07:00 kemarin.
  - Kolom Tanggal tidak dapat diubah manual (lihat `LS-14` untuk keterangan yang harus menyertainya).
- **Prioritas:** Tinggi
- **Catatan QA:** ini kasus yang mudah terlewat. Shift pabrik berjalan melewati tengah malam, jadi draft yang dibuat pukul 01:00 harus bertanggal hari itu.

### MB-MON-05 — Belum ada draft: keterangan, bukan daftar kosong
- **Peran:** `OPR-A`
- **Prasyarat:** tidak ada draft untuk stasiun itu
- **Langkah:**
  1. Pastikan stasiun ini **tidak punya satu draft pun** pada Production Line terpilih (hapus lewat Clear bila ada).
  2. `[M-MON]` untuk <Stasiun>.
  3. Baca bagian daftar draft.
- **Hasil diharapkan:**
  - Muncul **keterangan** bahwa belum ada draft — kalimat, bukan ruang kosong.
  - BUKAN tabel dengan nol baris tanpa penjelasan.
  - Tombol **New Data** tetap ada dan dapat ditekan.
- **Prioritas:** Sedang

### MB-MON-06 — Breadcrumb dapat diklik, bukan label mati
- **Peran:** `OPR-A`
- **Langkah:**
  1. `[M-MON]` untuk <Stasiun>.
  2. Cari breadcrumb di bagian atas layar (mis. Home / Station List / nama stasiun).
  3. Tekan bagian **Home** pada breadcrumb.
  4. Kembali ke Monitor, lalu tekan bagian **Station List**.
- **Hasil diharapkan:**
  - Bagian breadcrumb selain yang terakhir **dapat ditekan**.
  - Menekan **Home** mendarat di Home; menekan **Station List** mendarat di Station List.
  - Bagian terakhir (nama layar sekarang) tidak dapat ditekan — itu benar.
- **Prioritas:** Rendah

### MB-MON-07 — Load Data membuka Data Preview stasiun itu
- **Peran:** `OPR-A`
- **Langkah:**
  1. `[M-MON]` untuk <Stasiun>.
  2. Tekan **Load Data**.
  3. Baca judul layar dan alamatnya.
  4. Periksa stasiun pada record yang terdaftar.
- **Hasil diharapkan:**
  - Terbuka **Data Preview** stasiun itu (`/stations/<stasiun>/preview`).
  - Daftarnya hanya memuat record stasiun tersebut — tidak tercampur stasiun lain.
- **Prioritas:** Sedang

### 4.5 Form Mobile (jalankan 18×, satu per stasiun)

### MB-FORM-01 — Simpan berhasil dengan satu baris terisi
- **Prasyarat tambahan untuk Grading:** daftar **WB Card No** pada Form Grading dibaca dari
  record Weighbridge yang ada **di perangkat itu**. Pada perangkat yang baru (atau sesudah data
  aplikasi dibersihkan) daftarnya kosong dan layar berbunyi "Tidak ada data weighbridge lokal",
  sehingga Grading tidak dapat disimpan. Buat **satu record Weighbridge lebih dulu di perangkat
  yang sama**, baru jalankan kasus ini untuk Grading. Itu bukan bug — satu muatan Grading memang
  menunjuk kartu timbang tertentu.
- **Peran:** `OPR-A`
- **Prasyarat:** periode stasiun itu **Terbuka**; Production Line terpilih
- **Langkah:**
  1. Pastikan periode stasiun ini **Terbuka** dan satu Production Line sudah terpilih.
  2. `[M-FORM]` untuk <Stasiun>.
  3. Isi kolom identitas unit (lihat kolom "Kolom wajib" pada matriks 18 stasiun).
  4. Tambahkan satu baris slot waktu, pilih **07:00**.
  5. Isi **satu** kolom bacaan pada baris itu dengan angka yang masuk akal.
  6. Tekan **Simpan**.
  7. Kembali ke Monitor, lalu buka **Load Data** dan cari record itu.
- **Hasil diharapkan:**
  - Simpan **berhasil**, dengan pesan konfirmasi.
  - Layar kembali ke **Monitor** (atau ke daftar), bukan tetap di Form.
  - Record muncul di Data Preview dengan status **Tersimpan**.
  - Satu baris terisi sudah cukup — tidak dipaksa mengisi 24 slot.
- **Prioritas:** Tinggi

### MB-FORM-02 — Simpan ditolak bila kolom wajib kosong
- **Peran:** `OPR-A`
- **Langkah:**
  1. `[M-FORM]` untuk <Stasiun>.
  2. **Kosongkan** kolom identitas unit.
  3. Tambahkan satu baris slot waktu dan isi satu kolom bacaan.
  4. Tekan **Simpan**.
  5. Perhatikan **di mana** pesan galatnya muncul.
- **Hasil diharapkan:**
  - **Ditolak** — record tidak tersimpan.
  - Pesan galat muncul **di sebelah kolom identitas** yang kosong, bukan hanya sebagai pesan umum.
  - Isi yang sudah diketik pada grid **tidak hilang**.
- **Prioritas:** Tinggi

### MB-FORM-03 — Slot waktu harus menaik dan tidak boleh kembar
- **Peran:** `OPR-A`
- **Catatan:** lewati untuk Weighbridge, Grading, Kernel Dispatch, CPO Dispatch
- **Langkah:**
  1. `[M-FORM]` untuk <Stasiun> (lewati kasus ini untuk Weighbridge, Grading, Kernel Dispatch, CPO Dispatch — pakai `MB-FORM-11`).
  2. Isi kolom identitas unit.
  3. Tambah baris pertama, pilih slot **09:00**. Isi satu kolom bacaan.
  4. Tambah baris kedua. Buka pemilih slot waktu dan baca pilihan yang tersedia.
  5. Coba pilih **08:00** (lebih awal dari baris sebelumnya).
  6. Coba pilih **09:00** (sama dengan baris sebelumnya).
  7. Pilih **10:00**, isi satu kolom, lalu tekan **Simpan**.
- **Hasil diharapkan:**
  - Langkah 4: pemilih baris kedua **tidak lagi menawarkan** slot yang sudah dipakai, dan tidak menawarkan slot yang lebih awal.
  - Langkah 5 dan 6: tidak dapat dipilih — atau bila terpilih, Simpan **ditolak** dengan pesan yang menyebut urutan slot.
  - Langkah 7: **berhasil**.
- **Prioritas:** Tinggi

### MB-FORM-04 — Urutan slot mengikuti 07:00 → 06:00, bukan 00:00 → 23:00
- **Peran:** `OPR-A`
- **Berlaku untuk:** **11 stasiun bergrid 24 slot waktu** — Boiler Room, Clarification,
  Depricarping, Effluent Plant, Engine Room, Kernel Plant, Pressing, Process Quality Control,
  Process Water, Storage Tank, Threshing. **Tujuh stasiun lain menghitung hal yang berbeda** dan
  memang tidak memakai penyebut 24: Sterilizer menghitung **siklus**, Cages Track menghitung
  **cage/lori**, Solid Waste Disposal / Kernel Dispatch / CPO Dispatch menghitung **kejadian**,
  Grading menampilkan **nomor kendaraan**, dan Weighbridge satu record berarti satu transaksi.
  Pada ketujuh stasiun itu kasus ini **tidak berlaku**, dan barisnya memang **tidak ada** di
  berkas Excel: yang terdaftar hanya jalan yang dapat dikerjakan. Tidak ada yang perlu ditandai.
- **Langkah:**
  1. `[M-FORM]` untuk <Stasiun> (lewati ketujuh stasiun di luar daftar itu).
  2. Tambah satu baris slot waktu. Buka pemilih slotnya.
  3. Catat **urutan lengkap** pilihan dari atas ke bawah.
  4. Bandingkan dengan urutan yang benar: 07:00, 08:00, …, 23:00, 00:00, 01:00, …, 06:00.
- **Hasil diharapkan:**
  - Pilihan pertama adalah **07:00**, bukan 00:00.
  - Sesudah **23:00** menyusul **00:00**, bukan selesai.
  - Pilihan terakhir adalah **06:00**.
  - Jumlah pilihannya **24**.
  - Jadi 01:00 berada **sesudah** 23:00 — itu benar, bukan bug (lihat bagian 11.4).
- **Prioritas:** Tinggi
- **Catatan QA:** ini kasus yang paling mudah salah dibaca sebagai bug padahal benar, dan sebaliknya. Shift dimulai 07:00.

### MB-FORM-05 — Simpan ditolak bila tidak ada satu pun baris terisi
- **Peran:** `OPR-A`
- **Langkah:**
  1. `[M-FORM]` untuk <Stasiun>.
  2. Isi kolom identitas unit dengan benar.
  3. Tambah satu baris slot waktu, pilih **07:00**, tapi **jangan isi satu pun kolom bacaan**.
  4. Tekan **Simpan**.
  5. Catat bunyi pesannya.
- **Hasil diharapkan:**
  - **Ditolak** — record tidak tersimpan.
  - Pesannya menyebut bahwa minimal satu baris harus punya slot waktu **dan** minimal satu kolom bacaan terisi.
  - Pesannya muncul di bagian grid, bukan hanya di atas form.
- **Prioritas:** Tinggi

### MB-FORM-06 — Baris yang sebagian terisi tetap sah
- **Peran:** `OPR-A`
- **Langkah:**
  1. `[M-FORM]` untuk <Stasiun>.
  2. Isi kolom identitas unit.
  3. Tambah satu baris slot waktu **07:00**.
  4. Isi **hanya satu** kolom bacaan; biarkan seluruh kolom lain pada baris itu kosong.
  5. Tekan **Simpan**.
  6. Buka record itu lewat **Load Data** dan periksa baris tersebut.
- **Hasil diharapkan:**
  - **Berhasil** tersimpan.
  - Kolom yang dibiarkan kosong tersimpan sebagai **kosong** — bukan diisi 0.
  - Pada detail, kolom kosong itu tampil sebagai tanda pisah atau kosong, bukan angka 0.
- **Prioritas:** Tinggi

### MB-FORM-07 — Pause menyimpan apa adanya tanpa memvalidasi
- **Peran:** `OPR-A`
- **Langkah:**
  1. `[M-FORM]` untuk <Stasiun>.
  2. **Kosongkan** kolom identitas unit (yang pada `MB-FORM-02` membuat Simpan ditolak).
  3. Tambah satu baris, pilih slot, tapi jangan isi kolom bacaan apa pun.
  4. Tekan **Pause**.
  5. Kembali ke Monitor dan cari draft itu.
  6. Buka draft itu kembali.
- **Hasil diharapkan:**
  - Pause **berhasil** — tidak ada satu pun validasi yang menolaknya.
  - Draft muncul di Monitor berlabel **Pause**.
  - Saat dibuka kembali, isinya **apa adanya** seperti saat dijeda, termasuk kolom yang kosong.
- **Prioritas:** Tinggi

### MB-FORM-08 — Melanjutkan draft memuat kembali isinya
- **Peran:** `OPR-A`
- **Prasyarat:** satu draft dengan 2 baris terisi
- **Langkah:**
  1. Buat satu draft dengan **2** baris terisi, lalu tekan **Pause**.
  2. `[M-MON]` untuk <Stasiun>. Tekan draft itu pada daftar.
  3. Bandingkan kolom identitas, Tanggal, dan **kedua baris** dengan yang diisi di langkah 1.
  4. Periksa pemilih slot waktu pada baris ketiga yang baru ditambahkan.
- **Hasil diharapkan:**
  - Kolom identitas dan Tanggal **sama** seperti saat dijeda.
  - Kedua baris muncul lengkap dengan nilainya, **pada slot waktu yang sama**.
  - Baris ketiga hanya menawarkan slot **sesudah** slot terakhir yang sudah dipakai.
- **Prioritas:** Tinggi

### MB-FORM-09 — Clear meminta konfirmasi, lalu menghapus permanen
- **Peran:** `OPR-A`
- **Langkah:**
  1. Buat satu draft dengan minimal satu baris terisi.
  2. Tekan **Clear**. Baca dialognya, lalu tekan **Batal**.
  3. Periksa apakah isi formnya masih utuh.
  4. Tekan **Clear** lagi, kali ini **setujui**.
  5. Kembali ke Monitor dan cari draft itu.
- **Hasil diharapkan:**
  - Langkah 2 memunculkan **dialog konfirmasi**, bukan langsung menghapus.
  - Langkah 3: isi form **masih utuh** sesudah Batal.
  - Langkah 5: draft **hilang** dari daftar — terhapus permanen.
- **Prioritas:** Tinggi

### MB-FORM-10 — Back dengan perubahan belum tersimpan meminta konfirmasi
- **Peran:** `OPR-A`
- **Langkah:**
  1. `[M-FORM]` untuk <Stasiun>.
  2. Isi kolom identitas unit (jangan Simpan, jangan Pause).
  3. Tekan **Back**.
  4. Baca dialog yang muncul, lalu tekan **Batal**/tetap di halaman.
  5. Tekan **Back** lagi dan kali ini setujui keluar.
  6. Buka `[M-FORM]` baru, **tanpa mengisi apa pun**, lalu tekan **Back**.
- **Hasil diharapkan:**
  - Langkah 3 memunculkan **dialog konfirmasi** karena ada perubahan belum tersimpan.
  - Langkah 4: tetap di Form, isi tidak hilang.
  - Langkah 6: **tidak ada** dialog — form yang belum disentuh tidak perlu konfirmasi.
- **Prioritas:** Sedang

### MB-FORM-11 — Stasiun per-transaksi: satu record per transaksi (Weighbridge, Grading, Kernel Dispatch, CPO Dispatch)
- **Peran:** `OPR-A`
- **Prasyarat:** periode Terbuka
- **Langkah:**
  1. Pastikan periode **Terbuka**. `[M-FORM]` untuk **Weighbridge**.
  2. Baca layarnya: apakah ada grid 24 slot waktu?
  3. Isi kolom wajib (No. Kartu / Kendaraan) dan nilai bobot yang diperlukan. Tekan **Simpan**.
  4. Perhatikan apakah ada pembedaan arus **Masuk** dan **Keluar**.
  5. Ulangi langkah 1–3 untuk **Grading**, **Kernel Dispatch**, dan **CPO Dispatch**.
- **Hasil diharapkan:**
  - **Tidak ada** grid 24 slot waktu pada keempat layar ini.
  - Masing-masing tersimpan sebagai **satu transaksi** — satu record per penimbangan/muatan/pengiriman.
  - Weighbridge membedakan arus **Masuk** dan **Keluar**, dan keduanya tidak dijumlah bersama.
  - Dapat membuat beberapa record pada tanggal yang sama tanpa saling menimpa.
- **Prioritas:** Tinggi

### MB-FORM-12 — Operator tidak dapat mengisi Checked By maupun Acknowledged By
- **Peran:** `OPR-A`
- **Langkah:**
  1. `[M-FORM]` untuk <Stasiun> sebagai `OPR-A`.
  2. Cari kolom **Checked By** dan **Acknowledged By**.
  3. Coba mengetik atau memilih pada keduanya.
  4. Pada stasiun **Sterilizer**, periksa juga kolom centang "Checked by SPV" **per baris**.
- **Hasil diharapkan:**
  - Keduanya **tidak dapat diisi** oleh Operator.
  - Ada keterangan di layar yang menyebut **siapa** yang boleh mengisinya — bukan kolom mati tanpa alasan (lihat `LS-14`).
  - Centang "Checked by SPV" per baris pada Sterilizer juga **tidak dapat ditekan** Operator.
- **Prioritas:** Tinggi

### MB-FORM-13 — Supervisor mengisi Checked, Mill Management mengisi Acknowledged
- **Peran:** `SPV-A`, lalu `MGR-A`
- **Catatan jalan:** di aplikasi mobile, daftar Data Preview dibaca dari basis data **di
  perangkat itu dan milik pengguna yang sedang masuk**. Record yang dibuat `OPR-A` **tidak
  terlihat** oleh `SPV-A` meski di perangkat yang sama, dan belum ada alur sinkronisasi yang
  menarik record pengguna lain ke perangkat. Jadi "buka record `OPR-A` sebagai `SPV-A`" **tidak
  dapat dijalankan di mobile** — itu batas alur yang ada, bukan bug. Verifikasi atas record orang
  lain diuji di **web** (`WB-FRM-08`). Yang diuji di sini adalah aturan perannya di mobile, pada
  record yang dibuat peran itu sendiri.
- **Langkah:**
  1. `[M-FORM]` sebagai `SPV-A`. Isi satu record. Periksa kolom **Checked By** dan
     **Acknowledged By** sebelum menyimpan, lalu Simpan.
  2. Logout. `[M-FORM]` sebagai `MGR-A`. Ulangi langkah 1 dan periksa kedua kolom yang sama.
- **Hasil diharapkan:**
  - `SPV-A` **dapat** mengisi Checked By.
  - `SPV-A` **tidak dapat** mengisi Acknowledged By.
  - `MGR-A` **dapat** mengisi Acknowledged By.
  - `MGR-A` **tidak dapat** mengisi Checked By.
  - Nama yang terisi adalah nama pengguna yang mengisinya, bukan diketik bebas — kendalinya kotak
    centang, bukan kolom teks.
  - Kendali yang tidak boleh dipakai sebaiknya **tidak ditampilkan** sama sekali untuk peran itu.
- **Prioritas:** Tinggi

### MB-FORM-14 — Tabel Target Operasional hanya referensi, tidak tersimpan
- **Peran:** `OPR-A`
- **Prasyarat:** stasiun yang punya tabel target (Threshing, Pressing, Depricarping, Kernel Plant, Grading)
- **Langkah:**
  1. Pilih stasiun yang punya tabel target: **Threshing**, **Pressing**, **Depricarping**, **Kernel Plant**, atau **Grading**.
  2. `[M-FORM]` untuk stasiun itu.
  3. Gulir ke bawah sampai tabel **Target Operasional**.
  4. Coba mengetik pada tabel itu.
  5. Isi form dengan benar dan **Simpan**.
  6. Buka record itu di Data Preview dan periksa apakah nilai target ikut tersimpan sebagai data record.
- **Hasil diharapkan:**
  - Tabel target tampil **read-only** — tidak satu pun selnya dapat diketik.
  - Isinya sama di mobile dan di web untuk stasiun yang sama.
  - Nilai target **tidak** menjadi bagian data record — ia hanya acuan yang ditampilkan.
- **Prioritas:** Rendah

### MB-FORM-15 — Simpan ditolak bila Production Line tidak punya stasiun aktif
- **Peran:** `OPR-A`
- **Prasyarat:** satu Production Line yang **tidak** punya stasiun jenis itu dalam keadaan aktif
- **Langkah:**
  1. Sebagai `ADM` di web, siapkan satu Production Line yang **tidak** punya stasiun jenis ini dalam keadaan aktif.
  2. `[M-LIST]` sebagai `OPR-A` dan pilih Production Line itu.
  3. Buka Monitor <Stasiun>, tekan **New Data**.
  4. Isi form dengan benar, lalu tekan **Simpan**.
  5. Baca pesannya.
- **Hasil diharapkan:**
  - **Ditolak** — record tidak tersimpan.
  - Pesannya menyebut bahwa Production Line itu tidak punya stasiun jenis ini yang aktif.
  - BUKAN pesan galat teknis, BUKAN layar yang diam tanpa tanggapan.
- **Prioritas:** Tinggi

### 4.6 Offline & Sinkronisasi — bagian paling khas aplikasi ini

### MB-SYNC-01 — Input berhasil sepenuhnya tanpa koneksi
- **Peran:** `OPR-A`
- **Prasyarat:** sudah login **sebelum** koneksi dimatikan
- **Langkah:**
  1. Login lebih dulu **sambil online** (`[M-LOGIN]`), dan buka Station List sekali supaya daftar stasiun tersimpan di perangkat.
  2. Matikan koneksi perangkat sepenuhnya (mode pesawat).
  3. Buka Monitor <Stasiun> → **New Data**.
  4. Isi kolom identitas dan minimal satu baris terisi.
  5. Tekan **Simpan**.
  6. Kembali ke Monitor, lalu buka **Load Data** dan cari record itu.
  7. Tutup aplikasi, buka lagi (masih tanpa koneksi), dan cari record itu lagi.
- **Hasil diharapkan:**
  - Seluruh alur berjalan **tanpa koneksi** — tidak ada layar memuat yang menggantung.
  - Simpan **berhasil**; record muncul di Data Preview dengan status **Tersimpan** (belum Tersinkron).
  - Sesudah aplikasi ditutup dan dibuka lagi, record **masih ada**.
  - Tidak ada pesan galat jaringan selama input — koneksi tidak dibutuhkan untuk menyimpan.
- **Prioritas:** Tinggi
- **Catatan QA:** matikan koneksi **sesudah** login. Mematikannya sebelum login menguji hal yang berbeda (lihat `MB-SYNC-05`).

### MB-SYNC-02 — Sync manual mengirim record ke server
- **Peran:** `OPR-A` + `SPV-A`
- **Prasyarat:** ada record offline berstatus Tersimpan
- **Langkah:**
  1. Siapkan minimal satu record berstatus **Tersimpan** (hasil `MB-SYNC-01`).
  2. Nyalakan kembali koneksi.
  3. Jalankan **Sync** dari layar yang menyediakannya.
  4. Tunggu sampai selesai dan baca ringkasannya.
  5. Periksa status record itu di Data Preview.
  6. Di web, `[W-BROWSE]` untuk stasiun yang sama sebagai `SPV-A` dan cari record itu.
- **Hasil diharapkan:**
  - Sync melaporkan jumlah record yang terkirim.
  - Status record berubah menjadi **Tersinkron**.
  - Record itu **muncul di web** dengan isi yang sama — identitas, tanggal, dan nilai barisnya cocok.
- **Prioritas:** Tinggi

### MB-SYNC-03 — Sync tanpa koneksi gagal dengan pesan yang jelas dan tidak merusak data
- **Peran:** `OPR-A`
- **Langkah:**
  1. Siapkan satu record berstatus **Tersimpan** yang belum tersinkron.
  2. Matikan koneksi perangkat.
  3. Jalankan **Sync**.
  4. Baca pesan yang muncul.
  5. Periksa status dan isi record itu di Data Preview.
  6. Nyalakan koneksi, jalankan Sync lagi.
- **Hasil diharapkan:**
  - Sync **gagal** dengan pesan yang menyebut **koneksi/jaringan** — jelas dan berbahasa Indonesia.
  - Record **tidak hilang** dan **tidak berubah** — statusnya tetap Tersimpan.
  - Record tidak ditandai Tersinkron secara keliru.
  - Sesudah koneksi kembali, Sync **berhasil** mengirimnya.
- **Prioritas:** Tinggi

### MB-SYNC-04 — Sync dua kali tidak menggandakan data
- **Peran:** `OPR-A` + `SPV-A`
- **Langkah:**
  1. Siapkan satu record berstatus **Tersimpan**, dengan koneksi hidup.
  2. Jalankan **Sync**. Tunggu sampai benar-benar selesai.
  3. Jalankan **Sync** lagi.
  4. Di web, `[W-BROWSE]` untuk stasiun itu dan cari record tersebut.
  5. Hitung berapa baris record dengan identitas dan tanggal itu.
- **Hasil diharapkan:**
  - Sync kedua berjalan tanpa galat.
  - Di web hanya ada **satu** record — tidak ada duplikat.
  - Jumlah baris detailnya juga tidak berganda.
  - Sync kedua melaporkan 0 record terkirim, atau pesan sepadan.
- **Prioritas:** Tinggi

### MB-SYNC-05 — Tanpa koneksi saat login: pesan jaringan, bukan "password salah"
- **Peran:** `OPR-A`
- **Langkah:**
  1. Logout dari aplikasi mobile.
  2. Matikan koneksi perangkat.
  3. Isi username dan password yang **benar**, tekan **Login**.
  4. Baca pesan yang muncul.
- **Hasil diharapkan:**
  - Pesannya menyebut **koneksi/jaringan**.
  - Pesannya **tidak** menyebut password salah atau akun tidak ditemukan — itu akan mengirim operator mengejar masalah yang tidak ada.
  - Tetap di layar Login, tidak masuk.
- **Prioritas:** Tinggi

### 4.7 Data Preview Mobile (jalankan 18×)

### MB-PRV-01 — Filter tanggal default ke hari ini
- **Peran:** `OPR-A`
- **Prasyarat:** ada record hari ini dan record beberapa hari lalu
- **Langkah:**
  1. Siapkan untuk stasiun ini: beberapa record bertanggal **hari ini**, dan beberapa bertanggal **beberapa hari lalu**.
  2. `[M-PREVIEW]` untuk <Stasiun>.
  3. Baca isi kolom filter **Tanggal** sebelum menyentuh apa pun.
  4. Hitung baris pada daftar dan bandingkan dengan jumlah record hari ini.
- **Hasil diharapkan:**
  - Filter Tanggal sudah terisi **tanggal hari ini** tanpa diisi penguji.
  - Daftar hanya memuat record **hari ini**.
  - Record tanggal lain tidak terlihat sampai filternya diubah.
- **Prioritas:** Sedang

### MB-PRV-02 — Pintasan "Semua" dan "Hari ini" bekerja
- **Peran:** `OPR-A`
- **Langkah:**
  1. `[M-PREVIEW]` untuk <Stasiun>, dengan data hari ini dan data hari lain tersedia.
  2. Tekan pintasan **Semua**. Hitung barisnya.
  3. Tekan pintasan **Hari ini**. Hitung barisnya.
  4. Bandingkan kedua angka itu dengan ringkasan jumlah hasil yang tertulis di layar.
- **Hasil diharapkan:**
  - **Semua** menampilkan record dari seluruh tanggal — jumlahnya lebih banyak.
  - **Hari ini** mengembalikan daftar ke record hari ini saja.
  - Ringkasan jumlah hasil **cocok** dengan jumlah baris yang terlihat.
- **Prioritas:** Sedang

### MB-PRV-03 — Pencarian tidak peduli huruf besar-kecil
- **Peran:** `OPR-A`
- **Prasyarat:** ada record dengan kolom wajib bernilai mis. `PRESSER-01`
- **Langkah:**
  1. Siapkan satu record dengan kolom identitas bernilai mis. `PRESSER-01`.
  2. `[M-PREVIEW]` untuk <Stasiun>. Tekan pintasan **Semua**.
  3. Pada kolom Cari, ketik `presser-01` (huruf kecil semua).
  4. Hapus, lalu ketik `PRESSER` (huruf besar semua).
  5. Hapus, lalu ketik `esser-0` (potongan di tengah).
- **Hasil diharapkan:**
  - Ketiga pencarian **menemukan** record itu.
  - Pencarian tidak peduli huruf besar-kecil.
  - Potongan di tengah juga cocok — bukan hanya awalan.
- **Prioritas:** Sedang

### MB-PRV-04 — Tombol × menghapus kata kunci dan memulihkan daftar
- **Peran:** `OPR-A`
- **Langkah:**
  1. `[M-PREVIEW]` untuk <Stasiun>. Tekan **Semua**. Catat jumlah baris.
  2. Ketik satu kata kunci yang menyaring daftar. Catat jumlah barisnya.
  3. Perhatikan apakah muncul tombol **×** pada kolom Cari.
  4. Tekan tombol **×**.
- **Hasil diharapkan:**
  - Tombol **×** muncul hanya ketika kolom Cari terisi.
  - Menekan × **mengosongkan** kata kuncinya.
  - Daftar kembali ke jumlah baris langkah 1.
- **Prioritas:** Rendah

### MB-PRV-05 — Draft mengarah ke Form, record final mengarah ke detail read-only
- **Peran:** `OPR-A`
- **Prasyarat:** ada satu draft dan satu record Tersimpan
- **Langkah:**
  1. Siapkan satu **draft** (dijeda) dan satu record **Tersimpan** untuk stasiun ini.
  2. `[M-PREVIEW]` untuk <Stasiun>. Tekan **Semua**.
  3. Tekan baris **draft**. Catat layar yang terbuka.
  4. Kembali, lalu tekan baris record **Tersimpan**. Catat layar yang terbuka.
  5. Pada layar langkah 4, coba ubah salah satu nilainya.
- **Hasil diharapkan:**
  - Baris **draft** mendarat di **Form** — dapat dilanjutkan dan diubah.
  - Baris **Tersimpan** mendarat di **detail read-only**.
  - Pada detail read-only, tidak satu pun nilai dapat diubah.
- **Prioritas:** Tinggi

### MB-PRV-06 — Detail menampilkan persis baris yang tersimpan
- **Peran:** `OPR-A`
- **Prasyarat:** record dengan **3** baris slot waktu
- **Langkah:**
  1. Siapkan satu record dengan **tepat 3** baris slot waktu terisi.
  2. `[M-PREVIEW]` untuk <Stasiun>, buka record itu.
  3. Hitung jumlah baris pada grid detailnya.
  4. Cocokkan slot waktu dan nilai tiap baris dengan yang disimpan.
- **Hasil diharapkan:**
  - Grid menampilkan **tepat 3** baris — bukan 24, bukan dipadatkan menjadi 1.
  - Slot waktu dan nilainya **sama** dengan yang disimpan.
  - Tidak ada baris kosong tambahan yang digambar untuk mengisi sampai 24.
- **Prioritas:** Tinggi

### MB-PRV-07 — Checked By / Acknowledged By terlihat oleh semua peran
- **Peran:** `OPR-A`
- **Prasyarat:** record yang sudah di-Checked Supervisor
- **Langkah:**
  1. Siapkan satu record yang sudah di-**Checked** oleh `SPV-A`.
  2. `[M-PREVIEW]` untuk <Stasiun> sebagai `OPR-A`, buka record itu.
  3. Cari baris **Checked By** dan **Acknowledged By** pada header detail.
  4. Ulangi sebagai `SPV-A`, lalu `MGR-A`.
- **Hasil diharapkan:**
  - Ketiga peran **melihat** nama pada Checked By.
  - Acknowledged By yang belum terisi tampil sebagai tanda pisah atau keterangan, **bukan** angka 0 dan bukan kosong tanpa label.
  - Melihat tidak sama dengan mengisi: Operator tetap tidak dapat mengubahnya.
- **Prioritas:** Sedang

### MB-PRV-08 — Reset Filter memulihkan keadaan awal
- **Peran:** `OPR-A`
- **Langkah:**
  1. `[M-PREVIEW]` untuk <Stasiun>. Catat keadaan awal: isi filter Tanggal dan jumlah baris.
  2. Ubah filter Tanggal, dan ketik satu kata kunci pada kolom Cari.
  3. Perhatikan apakah tombol **Reset Filter** muncul.
  4. Tekan **Reset Filter**.
- **Hasil diharapkan:**
  - Tombol **Reset Filter** muncul hanya ketika ada filter aktif.
  - Sesudah ditekan: filter Tanggal kembali ke **hari ini**, kolom Cari **kosong**.
  - Jumlah baris kembali sama dengan keadaan awal langkah 1.
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
- **Langkah:**
  1. Siapkan untuk stasiun ini record dengan status berbeda-beda: Draft, Dijeda, Tersimpan, Tersinkron.
  2. `[W-BROWSE]` untuk <Stasiun> sebagai `SPV-A`.
  3. Baca judul setiap kolom tabel.
  4. Baca isi kolom **Status** pada setiap baris.
  5. Periksa panel filter: apakah ada kolom tanggal dari dan sampai?
- **Hasil diharapkan:**
  - Tabel tampil dengan kolom identitas, Tanggal, Jumlah Baris Terisi, dan Status.
  - Status tertulis dalam **Bahasa Indonesia**: Draft / Dijeda / Tersimpan / Tersinkron.
  - Tidak ada status yang tertulis dalam bentuk teknis (`draft_ongoing`, `synced`).
  - Panel filter menyediakan rentang tanggal.
- **Prioritas:** Tinggi

### WB-BRW-02 — Rentang tanggal terbalik ditolak
- **Peran:** `SPV-A`
- **Langkah:**
  1. `[W-BROWSE]` untuk <Stasiun>.
  2. Isi tanggal **dari** = hari ini.
  3. Isi tanggal **sampai** = tanggal 7 hari **sebelum** hari ini.
  4. Terapkan filter.
- **Hasil diharapkan:**
  - **Ditolak** dengan pesan yang menyebut rentang tanggalnya.
  - BUKAN tabel kosong tanpa penjelasan — tabel kosong akan terbaca "tidak ada data", padahal filternya yang mustahil.
  - BUKAN pesan galat teknis.
- **Prioritas:** Tinggi

### WB-BRW-03 — "Jumlah Baris Terisi" tampil sebagai pecahan dari 24
- **Peran:** `SPV-A`
- **Prasyarat:** satu record dengan **3** baris terisi
- **Berlaku untuk:** **11 stasiun bergrid 24 slot waktu** — Boiler Room, Clarification,
  Depricarping, Effluent Plant, Engine Room, Kernel Plant, Pressing, Process Quality Control,
  Process Water, Storage Tank, Threshing. **Tujuh stasiun lain menghitung hal yang berbeda** dan
  memang tidak memakai penyebut 24: Sterilizer menghitung **siklus**, Cages Track menghitung
  **cage/lori**, Solid Waste Disposal / Kernel Dispatch / CPO Dispatch menghitung **kejadian**,
  Grading menampilkan **nomor kendaraan**, dan Weighbridge satu record berarti satu transaksi.
  Pada ketujuh stasiun itu kasus ini **tidak berlaku**, dan barisnya memang **tidak ada** di
  berkas Excel: yang terdaftar hanya jalan yang dapat dikerjakan. Tidak ada yang perlu ditandai.
- **Langkah:**
  1. Siapkan satu record dengan **tepat 3** baris slot waktu terisi.
  2. `[W-BROWSE]` untuk <Stasiun>, cari record itu.
  3. Baca kolom **Jumlah Baris Terisi** pada baris itu.
  4. Bandingkan dengan jumlah baris sebenarnya pada Detail record tersebut.
- **Hasil diharapkan:**
  - Tertulis sebagai **pecahan dari 24** — mis. `3 / 24` atau `3 dari 24`.
  - Angka pertamanya **3**, cocok dengan Detail.
  - BUKAN hanya `3` tanpa penyebut: tanpa penyebut pembaca tidak tahu seberapa lengkap recordnya.
  - Untuk stasiun per-transaksi (Weighbridge, Grading, Kernel Dispatch, CPO Dispatch), kolom ini boleh berbeda bentuknya — catat apa adanya.
- **Prioritas:** Sedang

### WB-BRW-04 — Filter menghasilkan hasil yang konsisten antar halaman
- **Peran:** `SPV-A`
- **Prasyarat:** data cukup untuk lebih dari satu halaman
- **Langkah:**
  1. Pastikan stasiun ini punya data lebih dari satu halaman.
  2. `[W-BROWSE]` untuk <Stasiun>.
  3. Terapkan satu filter (rentang tanggal atau kata kunci) yang hasilnya **lebih dari satu halaman**.
  4. Catat jumlah total hasil yang tertulis.
  5. Buka **halaman 2**.
  6. Periksa setiap baris di halaman 2: apakah semuanya masih cocok dengan filternya?
- **Hasil diharapkan:**
  - Seluruh baris di halaman 2 **masih memenuhi** filter yang diterapkan.
  - Jumlah total hasil tidak berubah saat berpindah halaman.
  - Filternya tidak tereset saat pindah halaman.
- **Prioritas:** Tinggi

### WB-BRW-05 — Reset filter memulihkan keadaan awal
- **Peran:** `SPV-A`
- **Langkah:**
  1. `[W-BROWSE]` untuk <Stasiun>. Catat keadaan awal: isi filter dan jumlah hasil.
  2. Ubah rentang tanggal dan isi kata kunci pencarian.
  3. Terapkan, lalu catat jumlah hasilnya.
  4. Tekan **Reset Filter**.
- **Hasil diharapkan:**
  - Seluruh kolom filter kembali ke nilai awalnya.
  - Jumlah hasil kembali sama dengan langkah 1.
  - Halaman kembali ke halaman 1.
- **Prioritas:** Rendah

### WB-BRW-06 — Klik baris membuka Detail record itu
- **Peran:** `SPV-A`
- **Langkah:**
  1. `[W-BROWSE]` untuk <Stasiun>.
  2. Catat identitas dan tanggal pada **baris ketiga** tabel.
  3. Klik baris ketiga itu.
  4. Bandingkan header layar yang terbuka dengan catatan langkah 2.
- **Hasil diharapkan:**
  - Terbuka layar **Detail** (`/data/<stasiun>/<id>`).
  - Identitas dan tanggal pada Detail **sama** dengan baris yang diklik — bukan record lain.
- **Prioritas:** Tinggi

### WB-BRW-07 — Tambah Data membuka Form kosong
- **Peran:** `SPV-A`
- **Langkah:**
  1. `[W-BROWSE]` untuk <Stasiun>.
  2. Tekan **Tambah**.
  3. Baca seluruh kolom pada form yang terbuka.
  4. Periksa bagian grid: berapa baris yang sudah ada?
- **Hasil diharapkan:**
  - Terbuka Form kosong (`/data/<stasiun>/create`).
  - Kolom identitas **kosong**; tidak ada nilai sisa dari record mana pun.
  - Kolom Production Line dapat dipilih.
  - Grid slot waktu masih kosong atau menyediakan satu baris kosong untuk diisi.
- **Prioritas:** Tinggi

### WB-BRW-08 — Filter tanpa hasil memberi keterangan, bukan tabel kosong tanpa penjelasan
- **Peran:** `SPV-A`
- **Langkah:**
  1. `[W-BROWSE]` untuk <Stasiun>.
  2. Terapkan rentang tanggal yang **pasti tidak punya data** (mis. 1–2 Januari 2020).
  3. Baca isi area tabel.
- **Hasil diharapkan:**
  - Muncul **keterangan** bahwa tidak ada data untuk filter itu — satu kalimat yang terbaca.
  - BUKAN tabel dengan nol baris tanpa penjelasan.
  - Panel filter tetap terlihat supaya penguji dapat mengubahnya kembali.
- **Prioritas:** Sedang

### WB-BRW-09 — Ekspor melebihi batas baris ditolak dengan pesan, bukan berkas rusak
- **Peran:** `ADM`
- **Prasyarat:** data melebihi batas ekspor (**50.000 baris**) — bila tak tersedia, catat kasus ini sebagai *tidak dapat diuji* dan jangan tandai lulus
- **Langkah:**
  1. Pastikan stasiun ini punya data melebihi batas ekspor (**50.000 baris**). Bila tidak tersedia, **catat kasus ini sebagai Tidak diuji** dan jangan ditandai Lulus.
  2. `[W-BROWSE]` untuk <Stasiun>, atur filter supaya mencakup seluruh data itu.
  3. Tekan **Ekspor CSV**.
  4. Baca pesan yang muncul. Periksa folder unduhan.
- **Hasil diharapkan:**
  - **Ditolak** dengan pesan yang menyebut batas jumlah barisnya.
  - Pesannya menyarankan mempersempit rentang tanggal.
  - **Tidak ada** berkas yang terunduh — lebih baik tanpa berkas daripada berkas separuh.
- **Prioritas:** Sedang

### 5.2 Detail (read-only)

### WB-DTL-01 — Seluruh kolom read-only, tanpa edit di tempat
- **Peran:** `SPV-A`
- **Langkah:**
  1. `[W-DETAIL]` untuk <Stasiun>.
  2. Coba klik dan mengetik pada setiap nilai di header: identitas, tanggal, catatan.
  3. Coba klik dan mengetik pada sel-sel grid.
  4. Cari tombol Simpan di layar ini.
- **Hasil diharapkan:**
  - Tidak satu pun nilai dapat diubah di layar ini.
  - Tidak ada tombol Simpan — layar ini hanya membaca.
  - Perubahan hanya mungkin lewat tombol **Edit**, yang membuka Form.
- **Prioritas:** Tinggi

### WB-DTL-02 — Verifikasi yang kosong ditampilkan sebagai tanda pisah
- **Peran:** `SPV-A`
- **Prasyarat:** record yang belum diverifikasi siapa pun
- **Langkah:**
  1. Siapkan satu record yang **belum diverifikasi** siapa pun.
  2. `[W-DETAIL]` untuk record itu.
  3. Baca baris **Checked By** dan **Acknowledged By**.
- **Hasil diharapkan:**
  - Keduanya tampil sebagai **tanda pisah "—"** atau keterangan "belum diverifikasi".
  - BUKAN kosong tanpa label — kosong tanpa label tidak dapat dibedakan dari kolom yang gagal dimuat.
  - BUKAN angka 0, BUKAN kata "null".
- **Prioritas:** Sedang

### WB-DTL-03 — Grid menampilkan persis baris tersimpan
- **Peran:** `SPV-A`
- **Prasyarat:** record dengan 3 baris terisi
- **Berlaku untuk:** **17 stasiun — semua kecuali Weighbridge.** Satu record Weighbridge berarti
  satu transaksi: layar Detail-nya tidak punya grid baris sama sekali, seluruh nilainya ada di
  kepala layar, jadi tidak ada yang dapat dihitung. Barisnya memang **tidak ada** di berkas Excel.
  Ke-16 stasiun lain punya grid, **termasuk Cages Track** — gridnya bernama "Cages Tipped Time",
  satu baris per jam penumpahan.
  **Penyebut 24 hanya untuk 11 stasiun bergrid slot waktu** (lihat daftarnya di `WB-BRW-03`). Pada
  enam stasiun lain, bandingkan jumlah baris grid dengan jumlah yang tertulis di kolom hitungannya
  sendiri — "Jumlah Siklus", "Jumlah Cage/Lori Tercatat", atau "Jumlah Kejadian" — bukan dengan 24.
  Sebuah record yang memang punya 24 baris terisi **harus** menampilkan 24 baris; yang dilarang
  adalah menggambar 24 baris ketika yang tersimpan lebih sedikit.
- **Langkah:**
  1. Siapkan satu record dengan **tepat 3** baris terisi.
  2. `[W-DETAIL]` untuk record itu.
  3. Hitung jumlah baris pada grid.
  4. Cocokkan slot waktu dan nilai tiap baris dengan yang disimpan.
- **Hasil diharapkan:**
  - Grid menampilkan **tepat 3** baris.
  - Tidak digambar 24 baris dengan 21 di antaranya kosong.
  - Slot waktu dan nilainya cocok.
- **Prioritas:** Tinggi

### WB-DTL-04 — Edit membuka Form mode ubah dengan isi yang sama
- **Peran:** `SPV-A`
- **Langkah:**
  1. `[W-DETAIL]` untuk <Stasiun>. Catat seluruh nilai header dan isi grid.
  2. Tekan **Edit**.
  3. Bandingkan setiap kolom pada Form dengan catatan langkah 1.
  4. Periksa kolom **Production Line**: dapat diubah atau terkunci?
- **Hasil diharapkan:**
  - Terbuka Form mode ubah (`/data/<stasiun>/<id>/edit`).
  - Seluruh nilai **sama** dengan Detail — tidak ada yang kosong atau tergeser.
  - Production Line **terkunci** pada mode ubah (lihat `WB-FRM-02`).
- **Prioritas:** Tinggi

### WB-DTL-05 — Back kembali ke Data Browser dengan filter yang masih sama
- **Peran:** `SPV-A`
- **Langkah:**
  1. `[W-BROWSE]` untuk <Stasiun>. Terapkan satu filter dan buka **halaman 2**.
  2. Catat isi filter dan nomor halaman.
  3. Klik satu baris untuk membuka Detail.
  4. Tekan **Back**.
  5. Bandingkan filter dan halaman dengan catatan langkah 2.
- **Hasil diharapkan:**
  - Kembali ke **Data Browser** stasiun yang sama.
  - Filternya **masih sama** — tidak tereset.
  - Halaman yang sedang dibuka juga tetap, atau setidaknya filternya dipertahankan sehingga baris itu mudah ditemukan lagi.
- **Prioritas:** Sedang

### 5.3 Form Web

### WB-FRM-01 — Simpan berhasil, status langsung Tersimpan
- **Peran:** `SPV-A`
- **Prasyarat:** periode Terbuka
- **Langkah:**
  1. Pastikan periode stasiun ini **Terbuka**.
  2. `[W-FORM-NEW]` untuk <Stasiun> sebagai `SPV-A`.
  3. Pilih Production Line, isi kolom identitas, isi tanggal.
  4. Tambah satu baris slot waktu dan isi satu kolom bacaan.
  5. Tekan **Simpan**.
  6. Cari record itu di Data Browser dan baca kolom Status.
- **Hasil diharapkan:**
  - Simpan **berhasil**, dengan pesan konfirmasi.
  - Status record langsung **Tersimpan** — bukan Draft, karena input web tidak punya alur draft.
  - Record muncul di Data Browser tanpa perlu muat ulang manual.
- **Prioritas:** Tinggi

### WB-FRM-02 — Production Line wajib pada mode tambah, terkunci pada mode ubah
- **Peran:** `SPV-A`
- **Langkah:**
  1. `[W-FORM-NEW]` untuk <Stasiun>. **Jangan** pilih Production Line.
  2. Isi kolom lain dengan benar, lalu tekan **Simpan**.
  3. Pilih Production Line, Simpan lagi.
  4. Buka record itu, tekan **Edit**.
  5. Coba ubah Production Line pada mode ubah.
- **Hasil diharapkan:**
  - Langkah 2: **ditolak**, dengan pesan yang menunjuk kolom Production Line.
  - Langkah 3: **berhasil**.
  - Langkah 5: Production Line **tidak dapat diubah** — ia dipaku saat record dibuat, dan ada keterangan yang menyebutkannya.
- **Prioritas:** Tinggi

### WB-FRM-03 — Kolom wajib dan tanggal divalidasi
- **Peran:** `SPV-A`
- **Langkah:**
  1. `[W-FORM-NEW]` untuk <Stasiun>.
  2. Kosongkan kolom identitas unit, isi yang lain dengan benar, tekan **Simpan**. Catat letak pesannya.
  3. Isi identitas, lalu kosongkan **Tanggal**, tekan Simpan.
  4. Isi Tanggal dengan teks yang bukan tanggal (mis. `abc`), tekan Simpan.
  5. Isi Tanggal dengan tanggal **di luar** periode yang terbuka — pilih tanggal **di masa lalu**
     (mis. dua bulan sebelum periode dimulai), tekan Simpan.
- **Hasil diharapkan:**
  - Setiap langkah **ditolak**; record tidak tersimpan.
  - Setiap pesan galat muncul **di sebelah kolom yang bersangkutan**.
  - Tanggal di luar periode terbuka ditolak dengan pesan yang menyebut periode.
  - **Jangan memakai tanggal masa depan** untuk langkah 5: tanggal masa depan ditolak lebih dulu
    oleh aturan tersendiri ("tidak boleh melewati hari ini"), sehingga pesan periodenya tidak akan
    pernah muncul dan kasus ini tampak gagal padahal menguji hal yang salah.
  - Isi kolom lain tidak hilang setiap kali penolakan terjadi.
- **Prioritas:** Tinggi

### WB-FRM-04 — Aturan slot waktu sama seperti mobile
- **Peran:** `SPV-A`
- **Berlaku untuk:** **11 stasiun bergrid 24 slot waktu** — Boiler Room, Clarification,
  Depricarping, Effluent Plant, Engine Room, Kernel Plant, Pressing, Process Quality Control,
  Process Water, Storage Tank, Threshing. **Tujuh stasiun lain menghitung hal yang berbeda** dan
  memang tidak memakai penyebut 24: Sterilizer menghitung **siklus**, Cages Track menghitung
  **cage/lori**, Solid Waste Disposal / Kernel Dispatch / CPO Dispatch menghitung **kejadian**,
  Grading menampilkan **nomor kendaraan**, dan Weighbridge satu record berarti satu transaksi.
  Pada ketujuh stasiun itu kasus ini **tidak berlaku**, dan barisnya memang **tidak ada** di
  berkas Excel: yang terdaftar hanya jalan yang dapat dikerjakan. Tidak ada yang perlu ditandai.
- **Langkah:**
  1. `[W-FORM-NEW]` untuk <Stasiun> (lewati ketujuh stasiun di luar daftar itu).
  2. Jalankan seluruh langkah `MB-FORM-03` pada form web ini: coba slot menurun dan slot kembar.
  3. Jalankan seluruh langkah `MB-FORM-04`: baca urutan 24 pilihan slot.
- **Hasil diharapkan:**
  - Aturan slotnya **sama persis** dengan mobile: harus menaik, tidak boleh kembar.
  - Urutannya 07:00 → 23:00 → 00:00 → 06:00, tepat 24 pilihan pada baris pertama.
  - Aturannya ditegakkan dengan **mencegah**, bukan dengan pesan galat: pada baris kedua dan
    seterusnya, daftar pilihan hanya memuat slot yang **lebih besar** dari slot baris sebelumnya —
    slot yang sudah dipakai dan slot yang lebih awal **hilang dari daftar**. Jadi Anda **tidak akan
    bisa** memilih slot kembar atau menurun, dan tidak akan ada pesan galat untuk dibaca. Itu yang
    benar; mencegah lebih kuat daripada menolak saat Simpan.
  - **Yang dilaporkan sebagai temuan** adalah kebalikannya: bila slot kembar atau menurun ternyata
    **masih bisa dipilih**.
  - Bila web dan mobile berbeda di titik mana pun, catat perbedaannya — itu temuan.
- **Prioritas:** Tinggi

### WB-FRM-05 — Melebihi 24 baris ditolak
- **Peran:** `SPV-A`
- **Berlaku untuk:** **11 stasiun bergrid 24 slot waktu** — Boiler Room, Clarification,
  Depricarping, Effluent Plant, Engine Room, Kernel Plant, Pressing, Process Quality Control,
  Process Water, Storage Tank, Threshing. **Tujuh stasiun lain menghitung hal yang berbeda** dan
  memang tidak memakai penyebut 24: Sterilizer menghitung **siklus**, Cages Track menghitung
  **cage/lori**, Solid Waste Disposal / Kernel Dispatch / CPO Dispatch menghitung **kejadian**,
  Grading menampilkan **nomor kendaraan**, dan Weighbridge satu record berarti satu transaksi.
  Pada ketujuh stasiun itu kasus ini **tidak berlaku**, dan barisnya memang **tidak ada** di
  berkas Excel: yang terdaftar hanya jalan yang dapat dikerjakan. Tidak ada yang perlu ditandai.
- **Langkah:**
  1. `[W-FORM-NEW]` untuk <Stasiun> (lewati ketujuh stasiun di luar daftar itu).
  2. Isi header dengan benar.
  3. Tambah baris terus-menerus sampai mencapai **24** baris.
  4. Coba tambah baris ke-**25**.
- **Hasil diharapkan:**
  - Pada 24 baris, tombol tambah baris **tidak lagi bisa dipakai**, atau penambahan ditolak dengan pesan.
  - Pesannya menyebut batas 24 slot.
  - Simpan pada 24 baris tetap **berhasil**.
- **Prioritas:** Sedang

### WB-FRM-06 — Minimal satu baris terisi
- **Peran:** `SPV-A`
- **Langkah:**
  1. `[W-FORM-NEW]` untuk <Stasiun>.
  2. Isi header dengan benar.
  3. Tambah satu baris, pilih slot waktu, tapi **jangan isi satu pun kolom bacaan**.
  4. Tekan **Simpan**.
- **Hasil diharapkan:**
  - **Ditolak** — record tidak tersimpan.
  - Pesannya menyebut bahwa minimal satu baris harus punya slot waktu **dan** minimal satu kolom bacaan terisi.
  - Perilakunya sama dengan `MB-FORM-05` di mobile.
- **Prioritas:** Tinggi

### WB-FRM-07 — Line tanpa stasiun aktif ditolak
- **Peran:** `SPV-A`
- **Langkah:**
  1. Sebagai `ADM`, siapkan satu Production Line yang **tidak** punya stasiun jenis ini dalam keadaan aktif.
  2. `[W-FORM-NEW]` untuk <Stasiun>, pilih Production Line itu.
  3. Isi seluruh kolom dengan benar, tekan **Simpan**.
- **Hasil diharapkan:**
  - **Ditolak** dengan pesan yang menyebut bahwa Production Line itu tidak punya stasiun jenis ini yang aktif.
  - BUKAN pesan galat teknis.
  - Isi form tidak hilang.
- **Prioritas:** Tinggi

### WB-FRM-08 — Pembatasan peran pada verifikasi
- **Peran:** `SPV-A`, `MGR-A`, `ADM`
- **Langkah:**
  1. `[W-FORM-EDIT]` untuk satu record <Stasiun> sebagai `SPV-A`.
  2. Coba isi **Checked By**, lalu Simpan.
  3. Coba isi **Acknowledged By** sebagai `SPV-A`.
  4. Logout. Ulangi sebagai `MGR-A`: coba Acknowledged By, lalu coba Checked By.
  5. Logout. Ulangi sebagai `ADM`: coba keduanya.
- **Hasil diharapkan:**
  - `SPV-A`: Checked By **bisa**, Acknowledged By **tidak**.
  - `MGR-A`: Acknowledged By **bisa**, Checked By **tidak**.
  - `ADM`: **keduanya tidak** — verifikasi adalah pernyataan diri, dan Admin bukan pihak yang
    memeriksa maupun mengonfirmasi. Bagi Admin, bagian **Verifikasi** tidak ditampilkan sama
    sekali. Itu bukan bug.
  - Kendali yang tidak boleh dipakai sebaiknya **tidak ditampilkan**, bukan ditampilkan kelabu;
    bila ditampilkan kelabu, harus ada keterangan siapa yang berhak (lihat `LS-14`).
- **Prioritas:** Tinggi

### WB-FRM-09 — Batal tidak menyimpan apa pun
- **Peran:** `SPV-A`
- **Langkah:**
  1. `[W-FORM-EDIT]` untuk satu record <Stasiun>. Catat nilai aslinya.
  2. Ubah beberapa nilai: identitas, satu nilai pada grid, dan catatan.
  3. Tekan **Batal**.
  4. Buka Detail record itu dan bandingkan dengan catatan langkah 1.
- **Hasil diharapkan:**
  - Kembali ke layar sebelumnya.
  - Seluruh nilai record **tidak berubah** — tidak satu pun suntingan tersimpan.
  - Bila ada dialog konfirmasi saat Batal, isinya menyebut bahwa perubahan akan dibuang.
- **Prioritas:** Sedang

### WB-FRM-10 — Ubah record berhasil dan terlihat di Detail
- **Peran:** `SPV-A`
- **Langkah:**
  1. `[W-FORM-EDIT]` untuk satu record <Stasiun>.
  2. Ubah kolom identitas, ubah satu nilai pada grid, dan isi catatan.
  3. Tekan **Simpan**.
  4. Buka **Detail** record itu dan bandingkan ketiga perubahan.
  5. Buka **Data Browser** dan periksa baris record itu.
- **Hasil diharapkan:**
  - Simpan **berhasil**.
  - Ketiga perubahan terlihat di **Detail**.
  - Baris di Data Browser juga menunjukkan identitas yang baru.
- **Prioritas:** Tinggi

### WB-FRM-11 — Hapus baris mengurangi "Jumlah Baris Terisi"
- **Peran:** `SPV-A`
- **Prasyarat:** record dengan 3 baris terisi
- **Langkah:**
  1. Siapkan satu record dengan **3** baris terisi.
  2. `[W-BROWSE]` untuk <Stasiun>. Catat kolom **Jumlah Baris Terisi** pada record itu.
  3. Buka Detail → **Edit**.
  4. Hapus **satu** baris dari grid, lalu tekan **Simpan**.
  5. Kembali ke Data Browser dan baca kolom Jumlah Baris Terisi lagi.
- **Hasil diharapkan:**
  - Angkanya turun dari **3** menjadi **2** — penyebutnya tetap 24.
  - Detail record juga hanya menampilkan 2 baris.
  - Baris yang dihapus tidak kembali setelah muat ulang.
- **Prioritas:** Sedang

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
- **Langkah:**
  1. Siapkan satu periode untuk stasiun ini dengan data terisi pada satu Production Line.
  2. `[W-LOGIN]` sebagai `SPV-A`, sidebar → **Laporan Stasiun**, tekan tile <Stasiun>.
  3. Sebelum memilih apa pun, baca isi layar.
  4. Pilih **Periode**. Baca layar lagi.
  5. Pilih **Production Line**.
  6. Catat bagian-bagian yang muncul: cakupan, kartu parameter, rekap harian, rekap per unit, dan tombol ekspor.
- **Hasil diharapkan:**
  - Langkah 3 dan 4: **tidak ada satu angka pun** sebelum keduanya terpilih.
  - Langkah 5: laporan muncul lengkap — cakupan, kartu per parameter, rekap harian, rekap per unit.
  - Nama periode dan nama Production Line tercetak di layar sehingga pembaca tahu angkanya milik apa.
  - Tombol ekspor tersedia.
- **Prioritas:** Tinggi

### LP-W-02 — Cakupan pencatatan berada DI ATAS semua angka lain
- **Peran:** `SPV-A`
- **Langkah:**
  1. `[W-REPORT]` untuk <Stasiun>, pilih periode dan line yang punya data.
  2. Gulir ke paling atas.
  3. Catat urutan bagian dari atas ke bawah.
  4. Tentukan posisi bagian **Cakupan Pencatatan** relatif terhadap kartu parameter.
- **Hasil diharapkan:**
  - Bagian **Cakupan Pencatatan** berada **di atas** seluruh kartu parameter.
  - Pembaca melihat seberapa lengkap datanya **sebelum** melihat angka rata-ratanya.
  - Kalau cakupan ada di bawah, itu temuan: angka rata-rata dari 2 slot terbaca sama meyakinkan dengan dari 400 slot.
- **Prioritas:** Tinggi
- **Catatan QA:** periode yang baru terisi seperlima pun menghasilkan rata-rata yang terlihat rapi. Pembaca harus melihat seberapa lengkap datanya **sebelum** mempercayai angkanya — itu sebabnya urutan ini diuji.

### LP-W-03 — Setiap parameter mencetak penyebutnya sendiri
- **Peran:** `SPV-A`
- **Prasyarat:** periode yang kolom-kolomnya terisi **tidak sama banyak** (mis. satu kolom 6 slot, kolom lain 2 slot)
- **Langkah:**
  1. Siapkan satu periode yang kolom-kolomnya terisi **tidak sama banyak** — mis. satu kolom terisi pada 6 slot, kolom lain hanya 2 slot.
  2. `[W-REPORT]` untuk <Stasiun> dengan periode itu.
  3. Baca **setiap** kartu parameter dan catat penyebut yang tertulis pada masing-masing.
  4. Bandingkan penyebut antar kartu.
  5. Catat juga angka **slot terisi** pada bagian Cakupan.
- **Hasil diharapkan:**
  - Setiap kartu mencetak **penyebutnya sendiri** — berapa slot yang benar-benar mencatat kolom itu.
  - Penyebut antar kartu **boleh berbeda**, dan di sini memang berbeda. Itu benar: setiap kolom terisi saling bebas.
  - Tidak ada satu penyebut bersama yang dipakai untuk semua kartu.
  - Angka slot terisi pada Cakupan **boleh lebih besar** daripada penyebut kartu mana pun — itu juga benar (lihat bagian 11.4).
- **Prioritas:** Tinggi

### LP-W-04 — Semua baris parameter tetap tampil, termasuk yang tak pernah diukur
- **Peran:** `SPV-A`
- **Prasyarat:** satu kolom yang **tidak pernah** diisi sepanjang periode
- **Langkah:**
  1. Siapkan periode di mana satu kolom bacaan **tidak pernah** diisi sepanjang periode.
  2. `[W-REPORT]` untuk <Stasiun> dengan periode itu.
  3. Hitung jumlah kartu parameter dan bandingkan dengan jumlah kolom bacaan stasiun itu (matriks 4.2).
  4. Cari kartu milik kolom yang tidak pernah diisi, dan baca isinya.
- **Hasil diharapkan:**
  - Jumlah kartu **sama** dengan jumlah kolom bacaan — tidak ada yang disembunyikan.
  - Kartu yang tak pernah diukur **tetap tampil**, dengan keterangan bahwa belum ada pengukuran.
  - Kartu itu **tidak** menampilkan angka 0 — ia menyatakan ketiadaan data.
  - Kartu yang hilang ketika kosong tidak dapat dibedakan dari kartu yang belum pernah dibuat.
- **Prioritas:** Tinggi
- **Catatan QA:** baris yang hilang terbaca "parameter ini tidak ada", padahal yang benar "tidak ada yang mengukurnya".

### LP-W-05 — Persen cakupan: tanda pisah vs 0,0%
- **Peran:** `SPV-A`
- **Prasyarat:** dua keadaan — (a) periode **tanpa satu record pun**, (b) periode yang **punya record** tapi seluruh kolomnya kosong
- **Langkah:**
  1. Siapkan **dua** periode: (a) periode **tanpa satu record pun**, dan (b) periode yang **punya record** tapi seluruh kolom bacaannya kosong.
  2. `[W-REPORT]` untuk <Stasiun> dengan periode (a). Baca persen cakupannya.
  3. Ganti ke periode (b). Baca persen cakupannya.
  4. Bandingkan keduanya.
- **Hasil diharapkan:**
  - Periode (a) menampilkan **tanda pisah "—"**: penyebutnya tidak terbentuk, jadi persennya tidak dapat dihitung.
  - Periode (b) menampilkan **0,0%**: penyebutnya ada, yang terisi nol.
  - Keduanya **berbeda**, dan perbedaannya benar — bukan bug (lihat bagian 11.4).
  - Kalau keduanya tampil sama, itu temuan.
- **Prioritas:** Tinggi
- **Catatan QA:** keduanya **benar** dan **berbeda**. (a) penyebutnya tak terbentuk — tak ada unit untuk dihitung. (b) penyebutnya ada, dan nol dari sekian slot memang terisi. Kalau keduanya terlihat sama, itu bug.

### LP-W-06 — Periode belum mulai dijelaskan sebagai BELUM MULAI
- **Peran:** `SPV-A`
- **Prasyarat:** periode yang tanggal mulainya **di masa depan**
- **Langkah:**
  1. Sebagai `ADM`, siapkan satu periode yang **tanggal mulainya di masa depan**.
  2. `[W-REPORT]` untuk <Stasiun>, pilih periode itu dan satu line.
  3. Baca keterangan di bagian Cakupan.
  4. Baca angka hari yang dihitung dan persen cakupannya.
- **Hasil diharapkan:**
  - Keterangannya menyebut bahwa periode **belum mulai**.
  - Jumlah hari yang dihitung adalah **0**, dan itu dinyatakan, bukan disembunyikan.
  - BUKAN keterangan "sedang berjalan" — periode yang belum mulai bukan periode yang berjalan.
  - Persen cakupannya tanda pisah, bukan 0,0% (penyebutnya tidak terbentuk).
- **Prioritas:** Tinggi
- **Catatan QA:** kedua keterangan ini mudah tertukar di sistem. Kalau periode masa depan dijelaskan "sedang berjalan", itu bug.

### LP-W-07 — Periode sedang berjalan: penyebut berhenti di hari ini
- **Peran:** `SPV-A`
- **Prasyarat:** periode yang sudah mulai dan **belum** berakhir
- **Langkah:**
  1. Siapkan satu periode yang **sudah mulai dan belum berakhir** (hari ini berada di tengahnya).
  2. `[W-REPORT]` untuk <Stasiun> dengan periode itu.
  3. Baca keterangan di bagian Cakupan.
  4. Hitung sendiri: dari tanggal mulai sampai **hari ini**, berapa hari? Bandingkan dengan angka hari yang dicetak.
  5. Hitung juga seluruh panjang periode dan pastikan angkanya bukan itu.
- **Hasil diharapkan:**
  - Keterangannya menyebut periode **sedang berjalan**.
  - Jumlah hari yang dihitung berhenti di **hari ini** — bukan seluruh panjang periode.
  - Kalau yang dipakai seluruh panjang periode, persen cakupan akan selalu terlihat rendah sampai periode berakhir; itu temuan.
- **Prioritas:** Tinggi

### LP-W-08 — Mill tanpa periode stasiun itu memberi arahan
- **Peran:** `SPV-A`
- **Prasyarat:** mill yang belum punya periode mencakup stasiun itu
- **Langkah:**
  1. Siapkan satu mill yang **belum punya periode** mencakup stasiun ini.
  2. `[W-LOGIN]` sebagai `ADM`, buka laporan <Stasiun>, lalu pilih mill itu.
  3. Baca isi layar dan pemilih periodenya.
- **Hasil diharapkan:**
  - Muncul **arahan** yang menyebut periode — mis. agar Admin membuat periode lebih dulu.
  - Pemilih periode tetap ada, dalam keadaan **kosong**.
  - BUKAN layar kosong, BUKAN tabel nol baris, BUKAN angka 0.
- **Prioritas:** Sedang

### LP-W-09 — Admin berganti mill mereset pilihan periode dan line
- **Peran:** `ADM`
- **Prasyarat:** 2 mill, keduanya punya data
- **Langkah:**
  1. Siapkan 2 mill yang keduanya punya periode dan data untuk stasiun ini.
  2. `[W-REPORT]` untuk <Stasiun> sebagai `ADM`. Pilih Mill A, satu periode, dan satu line. Catat angkanya.
  3. Ganti pemilih **Mill** ke Mill B.
  4. Baca pemilih periode dan pemilih line, serta area angka.
  5. Pilih periode dan line Mill B, lalu catat angkanya.
- **Hasil diharapkan:**
  - Saat mill berganti, pilihan **periode dan line direset** — keduanya kembali kosong.
  - Angka Mill A **hilang** dan tidak tertinggal di layar.
  - Pemilih periode kini hanya memuat periode Mill B; pemilih line hanya line Mill B.
  - Angka baru hanya muncul setelah keduanya dipilih lagi.
- **Prioritas:** Tinggi
- **Catatan QA:** periksa **keduanya terpisah**. Yang paling berbahaya adalah angka atau nama
  Production Line mill lama yang tertinggal — itu bug prioritas tinggi. Bila yang terjadi hanya
  pemilih **periode** yang langsung mendarat pada satu-satunya periode mill baru (sementara line
  tetap kosong dan angkanya tetap belum muncul), catat itu apa adanya sebagai selisih dari hasil
  yang diharapkan: tidak ada data yang bocor, tetapi pembaca mendapat periode yang tidak pernah
  ia pilih. Sebutkan nama periode yang terpilih sendiri itu dalam laporan Anda.

### LP-W-10 — Rekap harian dapat ditutup tanpa mengubah angka
- **Peran:** `SPV-A`
- **Langkah:**
  1. `[W-REPORT]` untuk <Stasiun> dengan data. Catat angka pada kartu parameter dan pada Cakupan.
  2. Cari bagian **Rekap Harian**. Periksa apakah ia terbuka secara bawaan.
  3. Tutup bagian itu.
  4. Bandingkan angka kartu parameter dan Cakupan dengan catatan langkah 1.
  5. Buka lagi bagian itu dan periksa isinya masih sama.
- **Hasil diharapkan:**
  - Rekap Harian **terbuka** secara bawaan.
  - Menutupnya **tidak mengubah** satu angka pun di bagian lain.
  - Membukanya kembali menampilkan isi yang sama.
- **Prioritas:** Rendah

### LP-W-11 — Baris total periode bukan rata-rata dari rata-rata harian
- **Peran:** `SPV-A`
- **Prasyarat:** periode yang **jumlah slot per harinya tidak sama** (mis. hari-1 isi 2 slot, hari-2 isi 20 slot) dengan nilai yang berbeda jauh
- **Langkah:**
  1. Siapkan periode dengan jumlah slot per hari yang **sangat tidak sama** — mis. hari-1 hanya 2 slot terisi dengan nilai tinggi, hari-2 20 slot terisi dengan nilai rendah.
  2. `[W-REPORT]` untuk <Stasiun> dengan periode itu.
  3. Catat rata-rata **hari-1** dan rata-rata **hari-2** pada Rekap Harian.
  4. Hitung sendiri rata-rata dari kedua angka itu (jumlahkan lalu bagi 2).
  5. Catat angka pada **baris total periode**.
  6. Bandingkan langkah 4 dengan langkah 5.
- **Hasil diharapkan:**
  - Angka total periode **berbeda** dari hasil hitungan langkah 4.
  - Total periode dihitung ulang dari **seluruh slot**, bukan dirata-ratakan dari rata-rata harian.
  - Kalau keduanya sama, itu temuan: hari dengan 2 slot akan berbobot sama dengan hari 20 slot.
- **Prioritas:** Sedang

### LP-W-12 — Downtime: tanda pisah vs nol yang tercatat
- **Peran:** `SPV-A`
- **Prasyarat:** stasiun yang punya kolom downtime angka (Depricarping, Kernel Plant). Dua keadaan: (a) tak satu slot pun mengisi downtime, (b) beberapa slot mengisi **0** menit
- **Langkah:**
  1. Pilih stasiun yang punya kolom downtime angka: **Depricarping** atau **Kernel Plant**.
  2. Siapkan periode (a) di mana **tidak satu slot pun** mengisi downtime.
  3. `[W-REPORT]` untuk stasiun itu dengan periode (a). Baca blok downtime dan rekap per unit.
  4. Siapkan periode (b) di mana beberapa slot mengisi downtime dengan nilai **0**.
  5. Ganti ke periode (b) dan baca blok downtime lagi.
- **Hasil diharapkan:**
  - Periode (a): downtime tampil sebagai **tanda pisah "—"** atau keterangan ketiadaan, **bukan 0 menit**.
  - Periode (b): downtime tampil sebagai **0** menit — nol yang benar-benar terukur.
  - Keduanya **berbeda**: tidak pernah dicatat bukan hal yang sama dengan dicatat nol.
- **Prioritas:** Tinggi
- **Catatan QA:** "tercatat 0 menit" berarti seseorang menyatakan stasiun tidak berhenti. "Tidak dicatat" berarti tidak ada yang mengisinya. Keduanya tidak boleh terlihat sama.

### LP-W-13 — Temuan dikelompokkan harfiah, dua ejaan jadi dua baris
- **Peran:** `SPV-A`
- **Prasyarat:** dua record dengan temuan yang **hanya beda huruf besar-kecil atau spasi** (mis. `Bearing panas` dan `bearing panas`)
- **Langkah:**
  1. Siapkan dua record dengan temuan yang **hanya beda huruf besar-kecil atau spasi** — mis. `Bearing panas` dan `bearing panas`.
  2. `[W-REPORT]` untuk <Stasiun> dengan periode itu.
  3. Cari daftar/ringkasan **Temuan**.
  4. Hitung berapa baris yang muncul untuk kedua teks itu.
- **Hasil diharapkan:**
  - Muncul **dua baris terpisah** — pengelompokannya harfiah, apa adanya.
  - Sistem **tidak** menyatukan ejaan yang berbeda; menyatukannya berarti menebak maksud operator.
  - Masing-masing membawa jumlah kemunculannya sendiri.
- **Prioritas:** Sedang
- **Catatan QA:** menggabungkan keduanya akan menyatukan hal yang mungkin bukan satu hal. Yang dilaporkan sebagai bug adalah kalau keduanya **digabung**, atau kalau keterangannya **hilang**.

### LP-W-14 — Temuan terpisah dari downtime
- **Peran:** `SPV-A`
- **Prasyarat:** satu slot yang mengisi **downtime dan temuan sekaligus**
- **Langkah:**
  1. Siapkan satu slot yang mengisi **downtime dan temuan sekaligus**, dan satu slot yang mengisi **temuan saja**.
  2. `[W-REPORT]` untuk <Stasiun> dengan periode itu.
  3. Baca blok **downtime** dan daftar **temuan**.
  4. Periksa apakah slot "temuan saja" ikut menambah angka downtime.
- **Hasil diharapkan:**
  - Keduanya dihitung **terpisah**: slot yang hanya mengisi temuan **tidak** menambah menit downtime.
  - Slot yang mengisi keduanya muncul di **kedua** tempat.
  - Daftar temuan tidak hanya memuat temuan yang punya downtime.
- **Prioritas:** Sedang

### LP-W-15 — Bagian "standar tanpa pengukuran" tetap digambar walau kosong
- **Peran:** `SPV-A`
- **Prasyarat:** stasiun yang punya master target (Threshing, Pressing, Depricarping, Kernel Plant)
- **Langkah:**
  1. Pilih stasiun bermaster target: **Threshing**, **Pressing**, **Depricarping**, atau **Kernel Plant**.
  2. `[W-REPORT]` untuk stasiun itu dengan data.
  3. Cari bagian **standar tanpa pengukuran** (standar master yang tidak punya kolom ukur).
  4. Baca isinya dan keterangannya.
- **Hasil diharapkan:**
  - Bagian itu **tetap digambar** meski daftarnya kosong.
  - Ada keterangan yang menjelaskan apa arti bagian itu dan mengapa ia boleh kosong.
  - Bagian yang hilang ketika kosong tidak dapat dibedakan dari bagian yang belum pernah dibuat.
  - Untuk Kernel Plant, bagian ini normalnya memuat **Final Kernel Dirt** — standar yang memang tidak punya kolom ukur.
- **Prioritas:** Sedang
- **Catatan QA:** bagian yang menghilang saat kosong tak dapat dibedakan dari bagian yang belum pernah dibuat.

### LP-W-16 — Kolom target dirender utuh, termasuk keterangan dalam tanda kurung
- **Peran:** `SPV-A`
- **Prasyarat:** stasiun bermaster target
- **Langkah:**
  1. Pilih stasiun bermaster target.
  2. `[W-REPORT]` untuk stasiun itu.
  3. Baca kolom target pada setiap kartu parameter **kata per kata**.
  4. Bandingkan dengan isi tabel Target Operasional pada layar Form stasiun yang sama.
- **Hasil diharapkan:**
  - Teks targetnya dirender **utuh**, termasuk keterangan dalam tanda kurung — mis. `≤ 7.0% (Prevents mold growth)`.
  - Tidak dipotong, tidak dibuang tanda kurungnya, tidak diuraikan menjadi angka saja.
  - Isinya **sama** dengan tabel Target Operasional di Form.
- **Prioritas:** Sedang

### LP-W-17 — Kolom yang berbagi satu standar ditandai, dan angkanya tidak dirata-ratakan
- **Peran:** `SPV-A`
- **Prasyarat:** **Kernel Plant** (2 pasang: ripple mill 1–2 dan kernel silo 1–2) atau **Depricarping** (1 pasang: nut silo)
- **Langkah:**
  1. Pilih **Kernel Plant** (2 pasang: ripple mill 1–2 dan kernel silo 1–2) atau **Depricarping** (1 pasang: nut silo).
  2. `[W-REPORT]` untuk stasiun itu dengan data pada kedua kolom pasangan.
  3. Cari kedua kartu pasangan itu dan baca keterangannya.
  4. Catat angka rata-rata masing-masing kartu.
  5. Periksa apakah keduanya menampilkan angka yang identik.
- **Hasil diharapkan:**
  - Kedua kartu membawa **keterangan** bahwa standarnya berbagi satu baris master.
  - Angka rata-ratanya **dihitung sendiri-sendiri** — bukan satu angka gabungan yang disalin ke dua kartu.
  - Penyebutnya juga sendiri-sendiri.
  - Keterangan berbagi standar tidak boleh berbunyi seolah angkanya juga digabung.
- **Prioritas:** Tinggi
- **Catatan QA:** pada Kernel Plant ada **dua** pasangan — pastikan **keempat** baris bertanda, bukan hanya dua.

### LP-W-18 — Ekspor mengikuti periode dan line yang terpilih
- **Peran:** `SPV-A`
- **Langkah:**
  1. `[W-REPORT]` untuk <Stasiun>. Pilih **Periode A** dan **Line 1**. Catat beberapa angkanya.
  2. Tekan **Ekspor CSV**, buka berkasnya.
  3. Cocokkan beberapa angka berkas dengan catatan langkah 1, dan periksa nama periode dan line di dalam berkas.
  4. Kembali ke layar, ganti ke **Periode B** (atau Line 2). Ekspor lagi.
  5. Bandingkan isi berkas kedua dengan yang pertama.
- **Hasil diharapkan:**
  - Berkas pertama memuat data **Periode A / Line 1** saja.
  - Berkas kedua **berbeda isinya**, mengikuti pilihan baru.
  - Nama periode dan Production Line tertulis di dalam berkas, bukan hanya di layar.
  - Tidak ada baris dari periode atau line yang tidak terpilih.
- **Prioritas:** Tinggi

### LP-W-19 — Laporan tidak pernah mengubah data
- **Peran:** `SPV-A`
- **Langkah:**
  1. `[W-BROWSE]` untuk <Stasiun>. Catat jumlah data dan status beberapa record.
  2. `[W-REPORT]` untuk <Stasiun>. Ganti periode dan line beberapa kali, buka-tutup rekap harian, dan lakukan ekspor.
  3. Cari tombol apa pun di layar laporan yang mengubah data (Simpan, Edit, Hapus, Verifikasi).
  4. Kembali ke `[W-BROWSE]` untuk <Stasiun> dan bandingkan dengan catatan langkah 1.
- **Hasil diharapkan:**
  - Layar laporan **tidak punya** satu pun tombol yang mengubah data — hanya pemilih, pengulang, dan ekspor.
  - Jumlah data dan status seluruh record **tidak berubah**.
  - Ekspor tidak mengubah status record menjadi apa pun.
- **Prioritas:** Tinggi

### 6.3 Laporan Periode — Mobile (jalankan 11×)

### LP-M-01 — Isi laporan sama dengan versi web
- **Peran:** `OPR-A` (mobile) + `SPV-A` (web)
- **Prasyarat:** periode & line yang sama tersedia di keduanya
- **Langkah:**
  1. `[W-REPORT]` untuk <Stasiun>. Pilih satu periode dan satu line. **Catat seluruh angka**: cakupan, setiap kartu parameter beserta penyebutnya, dan baris total periode.
  2. `[M-REPORT]` untuk <Stasiun>. Pilih **periode dan line yang sama**.
  3. Bandingkan angka satu per satu dengan catatan langkah 1.
  4. Bandingkan juga jumlah kartu parameter dan jumlah baris rekap.
- **Hasil diharapkan:**
  - Setiap angka **sama** antara web dan mobile — termasuk penyebut tiap kartu.
  - Jumlah kartu parameter **sama**.
  - Keterangan cakupannya menyampaikan hal yang sama.
  - Perbedaan angka mana pun adalah temuan, dan sebutkan angka mana yang berbeda.
- **Prioritas:** Tinggi
- **Catatan QA:** ini kasus terpenting di bagian ini. Kalau satu angka berbeda, laporkan sebagai temuan prioritas tinggi beserta nama kolomnya.

### LP-M-02 — Tabel menjadi kartu bertumpuk, isinya tidak berkurang
- **Peran:** `OPR-A`
- **Langkah:**
  1. `[W-REPORT]` untuk <Stasiun>. Catat judul setiap kolom pada rekap harian dan rekap per unit.
  2. `[M-REPORT]` untuk <Stasiun> dengan periode dan line yang sama.
  3. Periksa bentuk rekapnya: tabel melebar, atau kartu bertumpuk?
  4. Pada satu kartu, hitung berapa nilai yang ditampilkan dan cocokkan labelnya dengan catatan langkah 1.
- **Hasil diharapkan:**
  - Di mobile, tabel berubah menjadi **kartu bertumpuk** (satu kartu per hari / per unit).
  - Setiap kolom web punya padanan berlabel di dalam kartu — **tidak ada kolom yang dibuang** demi ruang.
  - Labelnya terbaca, bukan disingkat sampai tidak jelas.
- **Prioritas:** Tinggi

### LP-M-03 — Halaman tidak menggulir mendatar pada lebar 390px
- **Peran:** `OPR-A`
- **Prasyarat:** lebar layar **390px**
- **Langkah:**
  1. Atur lebar layar perangkat/emulator ke **390px**.
  2. `[M-REPORT]` untuk <Stasiun> dengan data penuh.
  3. Gulir dari atas ke bawah seluruh layar.
  4. Pada setiap bagian, coba **gulir ke kanan**.
  5. Perhatikan apakah ada teks atau angka yang terpotong di tepi kanan.
- **Hasil diharapkan:**
  - Halaman **tidak dapat digulir mendatar** sama sekali.
  - Tidak ada isi yang terpotong di tepi kanan.
  - Tabel yang lebar di web sudah berubah bentuk, bukan dibiarkan melebar.
  - Kalau ada satu bagian saja yang memaksa gulir mendatar, catat bagian mana.
- **Prioritas:** Sedang

### LP-M-04 — Production Line wajib, termasuk saat hanya satu
- **Peran:** `OPR-A`
- **Langkah:**
  1. Siapkan satu mill dengan **satu** Production Line saja.
  2. `[M-REPORT]` untuk <Stasiun> pada mill itu. Pilih periode, **jangan** pilih line.
  3. Baca layar: ada angka atau tidak?
  4. Pilih line satu-satunya itu.
- **Hasil diharapkan:**
  - Sebelum line dipilih: **tidak ada satu angka pun**, dan ada permintaan memilih Production Line.
  - Line tunggal **tidak** terpilih otomatis.
  - Sesudah dipilih, angkanya muncul.
  - Perilakunya sama dengan `LS-08` di web.
- **Prioritas:** Tinggi

### LP-M-05 — Penjagaan line tidak bisa dilewati lewat coba-lagi atau ganti periode
- **Peran:** `OPR-A`
- **Prasyarat:** mill dengan 2 line, belum ada yang terpilih
- **Langkah:**
  1. Siapkan mill dengan **2** Production Line, dan pastikan belum ada line yang diingat perangkat (hapus data aplikasi bila perlu).
  2. `[M-REPORT]` untuk <Stasiun>. Pilih periode, jangan pilih line.
  3. Tekan tombol **Coba Lagi** bila ada. Baca layar.
  4. Ganti ke periode lain. Baca layar.
  5. Ganti kembali ke periode pertama. Baca layar.
- **Hasil diharapkan:**
  - Tidak satu pun dari langkah 3–5 memunculkan angka.
  - Permintaan memilih Production Line **tetap berdiri** di setiap langkah.
  - Penjagaan ini tidak dapat dilewati dengan memutar lewat Coba Lagi atau ganti periode.
- **Prioritas:** Tinggi

### LP-M-06 — Line yang diingat perangkat dipakai tanpa diminta memilih lagi
- **Peran:** `OPR-A`
- **Langkah:**
  1. `[M-REPORT]` untuk <Stasiun>. Pilih periode dan **Line 1**. Catat angkanya.
  2. Tekan Back ke Reporting, lalu masuk lagi ke laporan stasiun yang sama.
  3. Perhatikan pemilih line.
  4. Tutup aplikasi sepenuhnya, buka lagi, login, dan masuk ke laporan itu lagi.
- **Hasil diharapkan:**
  - Langkah 3: **Line 1 sudah terpilih** — tidak diminta memilih lagi.
  - Langkah 4: masih Line 1 — pilihannya diingat perangkat.
  - Angkanya langsung muncul sesudah periode dipilih, tanpa langkah memilih line lagi.
- **Prioritas:** Sedang

### LP-M-07 — Line yang diingat sudah tidak ada: pemilih muncul lagi, tanpa menebak
- **Peran:** `OPR-A` + `ADM`
- **Prasyarat:** line sudah diingat perangkat
- **Langkah:**
  1. Jalankan `LP-M-06` lebih dulu sehingga **Line 1** diingat perangkat untuk <Stasiun>.
  2. Sebagai `ADM` di web, **hapus** atau nonaktifkan Line 1 itu.
  3. `[M-REPORT]` untuk <Stasiun> lagi. Pilih periode.
  4. Baca layar dan pemilih line.
- **Hasil diharapkan:**
  - Pemilih Production Line **muncul kembali** dan dalam keadaan belum terpilih.
  - Sistem **tidak menebak** line lain untuk menggantinya.
  - Tidak ada angka yang tampil sampai penguji memilih line yang masih ada.
  - Tidak ada pesan galat teknis tentang line yang hilang.
- **Prioritas:** Sedang

### LP-M-08 — Dua pesan yang harus berbeda: mill tanpa line vs daftar line gagal dimuat
- **Peran:** `OPR-A`
- **Langkah:**
  1. Siapkan satu mill yang **belum punya Production Line**, dan satu akun yang terikat mill itu.
  2. `[M-REPORT]` untuk <Stasiun> sebagai akun itu. Catat pesannya **kata per kata**.
  3. Perhatikan apakah ada tombol **Coba Lagi**.
  4. Kembalikan keadaan. Sekarang simulasikan **daftar line gagal dimuat**: matikan koneksi sesaat setelah layar terbuka, lalu picu pemuatan daftar line.
  5. Catat pesan kedua ini kata per kata, dan perhatikan tombolnya.
- **Hasil diharapkan:**
  - Kedua pesan **berbeda bunyinya**.
  - Mill tanpa line: pesannya mengarahkan menghubungi Admin, dan **tidak ada** tombol Coba Lagi — mencoba lagi tidak akan membuat line muncul.
  - Daftar line gagal dimuat: pesannya menyebut jaringan/pemuatan, dan **ada** tombol Coba Lagi.
  - Menyamakan kedua pesan ini adalah temuan: yang satu butuh Admin, yang lain butuh koneksi.
- **Prioritas:** Sedang
- **Catatan QA:** menyatukan keduanya akan menyuruh pengguna menelepon Admin untuk masalah jaringan.

### LP-M-09 — Ganti periode cepat: hanya hasil terakhir yang tampil
- **Peran:** `OPR-A`
- **Prasyarat:** 2 periode dengan angka yang berbeda jelas; koneksi lambat (gunakan throttling bila tersedia)
- **Langkah:**
  1. Siapkan 2 periode dengan angka yang **berbeda jelas**.
  2. Aktifkan pembatasan kecepatan jaringan bila tersedia (throttling), supaya pemuatan terasa lambat.
  3. `[M-REPORT]` untuk <Stasiun>. Pilih **Periode A**, lalu **segera** ganti ke **Periode B** sebelum angka A selesai muncul.
  4. Tunggu sampai layar tenang.
  5. Bandingkan angka yang tampil dengan angka Periode B.
  6. Ulangi beberapa kali dengan urutan dibalik.
- **Hasil diharapkan:**
  - Angka yang akhirnya tampil adalah angka **periode yang terakhir dipilih**.
  - Angka periode sebelumnya **tidak** menang karena datang belakangan.
  - Nama periode yang tercetak di layar cocok dengan angkanya.
  - Angka dari dua periode tidak tercampur dalam satu tampilan.
- **Prioritas:** Sedang

### LP-M-10 — Gagal jaringan tidak menghapus pilihan
- **Peran:** `OPR-A`
- **Langkah:**
  1. `[M-REPORT]` untuk <Stasiun>. Pilih periode dan line. Catat keduanya.
  2. Matikan koneksi perangkat.
  3. Tekan **Coba Lagi**, atau ganti filter supaya pemuatan dipicu.
  4. Baca pesannya, lalu periksa pemilih periode dan pemilih line.
  5. Nyalakan koneksi dan tekan **Coba Lagi**.
- **Hasil diharapkan:**
  - Muncul pesan jaringan beserta tombol **Coba Lagi**.
  - Pilihan periode dan line **tidak hilang** — keduanya masih seperti catatan langkah 1.
  - Sesudah koneksi kembali, Coba Lagi memuat laporan untuk pilihan yang sama tanpa diminta memilih ulang.
- **Prioritas:** Sedang

### LP-M-11 — Ekspor CSV berhasil mengunduh
- **Peran:** `OPR-A`
- **Langkah:**
  1. `[M-REPORT]` untuk <Stasiun> dengan data.
  2. Baca tombol di bagian bawah layar.
  3. Tekan **Ekspor CSV**.
  4. Periksa folder unduhan perangkat dan buka berkasnya.
  5. Cocokkan beberapa angka dengan yang di layar.
- **Hasil diharapkan:**
  - Hanya ada pilihan **CSV** (lihat `LS-12`).
  - Berkas benar-benar terunduh dan dapat dibuka.
  - Nama berkasnya menyebut stasiun dan periode.
  - Angkanya cocok dengan layar.
- **Prioritas:** Sedang

### LP-M-12 — Laporan mobile read-only
- **Peran:** `OPR-A`
- **Langkah:**
  1. `[M-REPORT]` untuk <Stasiun> dengan data.
  2. Baca seluruh tombol di layar, termasuk yang di bagian bawah.
  3. Coba ketik atau ubah nilai pada kartu parameter dan pada rekap.
- **Hasil diharapkan:**
  - Tombol di bagian bawah hanya **Ekspor** dan **Back** — tidak ada Simpan, Edit, atau Hapus.
  - Tidak satu pun angka dapat diubah.
  - Tidak ada kolom input di layar ini selain pemilih periode/line/mill.
- **Prioritas:** Tinggi

---

## 7. Dashboard & Laporan lain (`DS-`)

### DS-01 — Dashboard adalah demo, dan layar MENYATAKANNYA
- **Peran:** `SPV-A`, `MGR-A`, `ADM`
- **Prasyarat:** —
- **Langkah:**
  1. `[W-DASH]` sebagai `SPV-A`.
  2. Baca judul di bagian atas halaman.
  3. Cari penanda bahwa angkanya bukan data nyata — lencana, chip, atau keterangan.
  4. Arahkan kursor (hover) ke penanda itu dan baca keterangan yang muncul.
  5. Ulangi langkah 1–4 sebagai `MGR-A`, lalu `ADM`.
- **Hasil diharapkan:**
  - Judulnya menyebut **demo** — mis. "CPO Mill Demo · Laporan Harian".
  - Ada penanda **"Data dummy"** yang terlihat tanpa perlu dicari.
  - Keterangan hover-nya menyatakan angkanya **fiktif dan belum terhubung** ke data stasiun.
  - Ketiga peran melihat penanda yang sama.
- **Prioritas:** Tinggi
- **Catatan QA:** ini kasus **terpenting** di halaman ini. Angka seperti "Milling Hours 11.00" tanpa penanda akan dibaca Mill Management sebagai angka pabriknya sendiri. Kalau penandanya **hilang**, laporkan sebagai prioritas tinggi — bukan kosmetik.

### DS-02 — Angka dashboard tidak berubah oleh peran, mill, atau tanggal
- **Peran:** `SPV-A`, `SPV-B`, `MGR-A`, `ADM`
- **Prasyarat:** Mill A dan Mill B punya data stasiun yang berbeda jauh
- **Langkah:**
  1. `[W-DASH]` sebagai `SPV-A`. Catat **lima** angka dari panel berbeda (mis. Penerimaan FFB, Stok FFB, Milling per Line, OER).
  2. Cari kolom filter tanggal atau pemilih mill di halaman ini.
  3. Logout. `[W-DASH]` sebagai `SPV-B` (mill berbeda). Bandingkan kelima angka itu.
  4. Logout. Ulangi sebagai `MGR-A`, lalu `ADM`.
  5. Buka halaman itu lagi besok (atau ubah tanggal perangkat) dan bandingkan lagi.
- **Hasil diharapkan:**
  - **Tidak ada** kolom filter tanggal dan **tidak ada** pemilih mill di halaman ini.
  - Kelima angka **sama persis** untuk keempat peran, termasuk `SPV-B` yang mill-nya berbeda.
  - Angkanya juga **sama** pada hari berikutnya.
  - Semua itu **benar**, bukan bug — halaman ini demo, bukan laporan mill.
- **Prioritas:** Sedang
- **Catatan QA:** jangan jalankan `LS-03` (isolasi mill) di sini. Halaman ini tidak membaca mill sama sekali, jadi "mill lain terlihat" bukan konsep yang berlaku. Isolasi mill diuji pada Laporan Periode, Data Browser, dan Production Process Activity.

### DS-03 — Angka antar panel saling konsisten
- **Peran:** `SPV-A`
- **Prasyarat:** —
- **Langkah:**
  1. `[W-DASH]` sebagai `SPV-A`. Gulir ke panel **Milling Hours per Line**.
  2. Untuk **Line 1**, kolom **Today**, catat: Available, Milling, CDT, SDT, EDT.
  3. Hitung sendiri: Total Downtime = CDT + SDT + EDT.
  4. Hitung: Available Milling Hours = Available − Total Downtime.
  5. Bandingkan kedua hasil hitungan itu dengan angka yang tercetak di panel.
  6. Periksa Milling ≤ Available Milling Hours.
  7. Gulir ke grafik **Milling per Line** dan bandingkan jam Line 1 dengan angka Milling di langkah 2.
  8. Ulangi langkah 2–7 untuk **Line 2**, lalu untuk kolom **MTD** dan **YTD**.
- **Hasil diharapkan:**
  - Total Downtime dan Available Milling Hours yang tercetak **cocok** dengan hitungan manual.
  - Pada Line 1 / Today nilainya: Total Downtime **11,50**, Available Milling Hours **12,50**, Milling **11,00**, sisa (segmen abu-abu) **1,50**.
  - Milling **tidak pernah** melebihi Available Milling Hours pada baris mana pun.
  - Jam pada grafik **sama** dengan angka Milling di tabel — dua tempat, satu angka.
- **Prioritas:** Sedang
- **Catatan QA:** angkanya contoh, tetapi **hubungan antar angkanya tetap harus benar**. Panel tabel dan grafik menyajikan jam yang sama, jadi keduanya tidak boleh berselisih — dan Total Downtime serta Available Milling Hours harus cocok dengan hitungan manual dari kolomnya sendiri.

### DS-04 — Laporan Stasiun (Web): tile laporan yang ada aktif, sisanya kelabu
- **Peran:** `SPV-A`
- **Langkah:**
  1. `[W-LOGIN]` sebagai `SPV-A`, lalu sidebar → **Laporan Stasiun** (`/reports`).
  2. **Sebelum memilih apa pun**, baca layarnya: catat apakah grid tile stasiun sudah terlihat.
  3. Pada **Langkah 1 — Mill & Production Line**, pilih salah satu **Production Line**. (Mill sudah mengikuti akun `SPV-A`; bila Anda masuk sebagai `ADM`, pilih mill lebih dulu.)
  4. Hitung jumlah tile stasiun di layar.
  5. Hitung berapa tile yang **dapat ditekan** dan berapa yang **kelabu**.
  6. Coba tekan satu tile kelabu.
  7. Cari legenda yang menjelaskan arti tile kelabu.
- **Hasil diharapkan:**
  - **Sebelum** Production Line dipilih, grid tile **belum muncul** — layar menampilkan "Production Line belum dipilih". Ini pola yang sama dengan `LS-08`: tile membawa konteks mill + line ke layar laporannya, jadi tile ditahan sampai keduanya pasti.
  - Sesudah dipilih, seluruh **18 tile** tampil — tidak ada yang disembunyikan.
  - **11** tile aktif dan dapat ditekan; **7** kelabu dan tidak dapat ditekan.
  - Tujuh yang kelabu: Effluent Plant, Engine Room, Process Water, Solid Waste Disposal, Kernel Dispatch, CPO Dispatch, Process Quality Control.
  - Menekan tile kelabu **tidak** berpindah layar dan tidak memunculkan galat.
  - Ada legenda yang menjelaskan keadaannya.
- **Prioritas:** Tinggi
- **Catatan QA:** stasiun yang laporannya belum dibangun **tetap ditampilkan**, bukan dihilangkan — supaya pembaca tahu stasiun itu ada.

### DS-05 — Tile aktif mendarat di laporan stasiun yang benar
- **Peran:** `SPV-A`
- **Langkah:**
  1. Sidebar → **Laporan Stasiun** sebagai `SPV-A`, lalu pilih satu **Production Line** agar grid tile muncul (lihat `DS-04`).
  2. Tekan tile **Pressing**. Catat judul layar dan alamatnya.
  3. Periksa apakah mill dan Production Line ikut terbawa (sudah terpilih atau tinggal dipilih).
  4. Tekan Back, lalu ulangi untuk **Kernel Plant** dan **Storage Tank**.
- **Hasil diharapkan:**
  - Setiap tile aktif mendarat di laporan **stasiun yang sama dengan namanya** (`/reports/<stasiun>`).
  - Judul layarnya cocok dengan tile yang ditekan.
  - Konteks mill terbawa; Production Line tetap wajib dipilih (lihat `LS-08`).
- **Prioritas:** Tinggi

### DS-06 — Laporan Manajemen hanya untuk Mill Management
- **Peran:** `SPV-A`, `MGR-A`, `ADM`
- **Langkah:**
  1. `[W-LOGIN]` sebagai `SPV-A`. Cari menu **Laporan Manajemen** di sidebar.
  2. Ketik `/reports/management` langsung di bilah alamat.
  3. Logout, ulangi langkah 1–2 sebagai `MGR-A`.
  4. Logout, ulangi sebagai `ADM`.
- **Hasil diharapkan:**
  - `SPV-A`: menu **tidak ada** di sidebar, dan alamat langsung menjawab **403**.
  - `MGR-A`: menu **ada** dan layarnya terbuka.
  - `ADM`: menu **tidak ada**, dan alamat langsung menjawab **403**. Layar ini satu-satunya layar laporan yang lebih sempit dari Dashboard: Admin boleh membuka Dashboard tetapi **tidak** Laporan Manajemen, karena isinya ditujukan bagi pengelola satu mill, bukan pengelola sistem.
  - Sidebar dan alamat langsung selalu **sejalan** — tidak ada peran yang melihat menunya lalu ditolak saat menekannya, dan sebaliknya.
- **Prioritas:** Tinggi

### DS-07 — Dashboard & Reporting (Mobile) → Pilih Stasiun
- **Peran:** `OPR-A`
- **Langkah:**
  1. `[M-LOGIN]` sebagai `OPR-A`, tekan **Dashboard & Reporting**.
  2. Tekan **Reporting**. Hitung jumlah tile stasiun.
  3. Tekan satu tile stasiun yang laporannya **belum ada** (mis. Engine Room). Baca pesannya.
  4. Tekan tile yang sama **tiga kali lagi**.
  5. Hitung berapa pesan yang terlihat di layar.
  6. Tekan satu tile yang laporannya **ada** (mis. Pressing).
- **Hasil diharapkan:**
  - Ke-18 tile tampil; yang laporannya belum ada tampil kelabu dengan tanda "Belum tersedia".
  - Menekan tile belum tersedia memunculkan **satu** pesan yang menyebut nama stasiunnya.
  - Menekan berkali-kali **tidak menumpuk** pesan — tetap satu.
  - Rutenya **tidak berpindah** saat tile belum tersedia ditekan.
  - Tile yang laporannya ada **berpindah** ke layar laporannya.
- **Prioritas:** Tinggi

### DS-08 — Production Process Activity (Web): papan status, bukan sekadar peluncur
- **Peran:** `SPV-A`
- **Prasyarat:** beberapa stasiun punya record **hari ini**, beberapa belum
- **Langkah:**
  1. Siapkan keadaan: beberapa stasiun punya record **bertanggal hari ini**, beberapa belum.
  2. `[W-PPA]` sebagai `SPV-A`.
  3. Baca kepala halaman dan catat kalimatnya.
  4. Hitung tile yang **berwarna** dan tile yang tampil **tenang**.
  5. Baca isi satu tile berwarna dan satu tile tenang.
  6. Cari keterangan tentang dua jendela waktu.
- **Hasil diharapkan:**
  - Kepala halaman berbunyi **"N dari 18 stasiun sudah ada input hari ini"**.
  - Tile yang **belum** ada input hari ini **berwarna** dan berbunyi "Belum ada input hari ini" — **bukan** angka 0.
  - Tile yang **sudah** ada input menampilkan **jumlah record hari ini** dan tampil tenang.
  - Setiap tile menyebut kapan **input terakhir**.
  - Ada keterangan bahwa "hari ini" dan "input terakhir" berbeda cakupannya.
- **Prioritas:** Tinggi

### DS-09 — Jumlah tile berwarna cocok dengan angka di kepala halaman
- **Peran:** `SPV-A`
- **Langkah:**
  1. `[W-PPA]` sebagai `SPV-A`.
  2. Baca angka **pertama** pada kepala halaman (N pada "N dari 18").
  3. Hitung sendiri jumlah tile yang berkeadaan "sudah ada input".
  4. Hitung jumlah tile "belum ada input".
  5. Jumlahkan keduanya dan bandingkan dengan jumlah stasiun aktif pada kepala halaman.
- **Hasil diharapkan:**
  - Jumlah tile "sudah ada input" **sama** dengan angka N di kepala halaman.
  - Tile "sudah" + tile "belum" **sama** dengan jumlah stasiun aktif yang disebut kepala halaman.
  - Total seluruh tile tetap **18**.
  - Dua tempat menghitung hal yang sama; bila tidak cocok, salah satunya salah.
- **Prioritas:** Sedang

### DS-10 — "Nol hari ini" boleh berdampingan dengan "input terakhir beberapa menit lalu"
- **Peran:** `SPV-A`
- **Prasyarat:** satu stasiun yang record **terakhirnya dibuat tadi** tapi **tanggalnya kemarin**
- **Langkah:**
  1. Siapkan satu stasiun yang record terakhirnya **dibuat beberapa menit lalu** tetapi **bertanggal kemarin**.
  2. `[W-PPA]` sebagai `SPV-A`.
  3. Cari tile stasiun itu dan baca **kedua** keterangan pada tile tersebut.
  4. Baca keterangan dua jendela waktu di halaman.
- **Hasil diharapkan:**
  - Tile itu berbunyi **"Belum ada input hari ini"** **dan** **"Input terakhir beberapa menit lalu"** sekaligus.
  - **Keduanya benar** — bukan bug (lihat bagian 11.4). "Hari ini" dibatasi tanggal; "input terakhir" tidak.
  - Keterangan di halaman menjelaskan sebabnya, sehingga pembaca tidak membacanya sebagai pertentangan.
- **Prioritas:** Sedang
- **Catatan QA:** ini **bukan** bug. "Hari ini" menghitung record bertanggal hari ini; "input terakhir" tidak dibatasi tanggal. Keterangan di halaman menjelaskannya.

### DS-11 — Tile mengantar ke Data Browser stasiun yang benar
- **Peran:** `SPV-A`
- **Langkah:**
  1. `[W-PPA]` sebagai `SPV-A`.
  2. Tekan tile **Weighbridge**. Catat judul dan alamat layar yang terbuka.
  3. Tekan Back, lalu tekan tile **Sterilizer**.
  4. Tekan Back, lalu tekan tile **Kernel Plant**.
  5. Coba tekan satu tile yang tampil **kelabu/tidak aktif**, bila ada.
- **Hasil diharapkan:**
  - Setiap tile aktif mendarat di **Data Browser stasiun yang sama dengan namanya** (`/data/<stasiun>`).
  - Judul layarnya cocok dengan tile yang ditekan.
  - Tile tidak aktif **tidak** berpindah layar dan tidak memunculkan galat.
- **Prioritas:** Tinggi

### DS-12 — Urutan tile sama antara Web dan Mobile
- **Peran:** `SPV-A` (web) + `OPR-A` (mobile)
- **Langkah:**
  1. `[W-PPA]` sebagai `SPV-A`. Catat urutan **ke-18** nama stasiun dari kiri-atas ke kanan-bawah.
  2. `[M-LIST]` sebagai `OPR-A`. Catat urutan ke-18 nama stasiun.
  3. Bandingkan kedua daftar posisi per posisi.
  4. Periksa khusus dua posisi pertama pada keduanya.
- **Hasil diharapkan:**
  - Kedua urutan **sama persis**, posisi per posisi.
  - Keduanya dimulai **Weighbridge**, lalu **Pressing**.
  - Urutannya bukan alfabetis dan bukan urutan alur proses — ia urutan yang diminta produk.
  - Perbedaan urutan mana pun adalah temuan; sebutkan posisi keberapa yang berbeda.
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
- **Langkah:**
  1. `[W-KELOLA]` untuk <Tingkat> sebagai `ADM`. Catat jumlah data yang tertulis.
  2. Tekan **Tambah**.
  3. Isi **seluruh** kolom wajib dengan nilai yang belum dipakai.
  4. Tekan **Simpan**.
  5. Baca daftar **tanpa** menekan muat ulang browser.
- **Hasil diharapkan:**
  - Simpan **berhasil** dengan pesan konfirmasi.
  - Entri baru **langsung muncul** di daftar — tanpa muat ulang manual.
  - Jumlah data bertambah satu.
- **Prioritas:** Tinggi

### MD-KEL-02 — Kolom wajib divalidasi di sebelah kolomnya
- **Peran:** `ADM`
- **Langkah:**
  1. `[W-KELOLA]` untuk <Tingkat> sebagai `ADM`. Tekan **Tambah**.
  2. Kosongkan **satu** kolom wajib, isi yang lain, tekan **Simpan**.
  3. Catat **di mana** pesan galatnya muncul.
  4. Ulangi langkah 2–3 untuk **setiap** kolom wajib, satu per satu.
- **Hasil diharapkan:**
  - Setiap kali **ditolak**; data tidak tersimpan.
  - Pesan galat muncul **di sebelah kolom yang bersangkutan** — bukan satu pesan umum di atas form.
  - Isi kolom lain tidak hilang.
- **Prioritas:** Tinggi

### MD-KEL-03 — Entri kembar ditolak, tidak peduli huruf besar-kecil
- **Peran:** `ADM`
- **Kolom yang diperiksa berbeda per tingkat** — pakai kolom yang berlaku di tingkat yang
  sedang diuji, jangan menguji kolom yang memang tidak dibatasi:

  | Tingkat | Kolom yang tidak boleh kembar | Lingkupnya |
  |---|---|---|
  | Corporate | **Nama** | seluruh sistem |
  | Company | **Nama** | di dalam Corporate induknya (lihat `MD-KEL-05`) |
  | Business Unit | **Kode** | seluruh sistem |
  | Production Line | **Kode** | seluruh sistem |
  | Station | **Kode** | seluruh sistem |
  | Machinery Group | **Kode** | seluruh sistem |
  | Machinery | **Kode Equipment** | seluruh sistem |
  | User & Role | **Username** | seluruh sistem |

  Di lima tingkat yang dibatasi lewat Kode, **nama kembar memang diperbolehkan** — dua mill
  boleh bernama sama selama kodenya berbeda. Itu bukan bug; jangan laporkan.
- **Prasyarat:** sudah ada satu entri dengan nilai acuan, mis. kode `CONTOH-1`
- **Langkah:**
  1. `[W-KELOLA]` untuk <Tingkat> sebagai `ADM`. Buat satu entri dengan nilai acuan pada kolom
     dari tabel di atas (mis. kode `CONTOH-1`).
  2. Tekan **Tambah** lagi, isi kolom itu dengan nilai yang sama berhuruf kecil (`contoh-1`), Simpan.
  3. Tekan **Tambah** lagi, isi berhuruf besar (`CONTOH-1`), Simpan.
  4. Tekan **Tambah** lagi, isi dengan spasi di depan dan belakang (`  CONTOH-1  `), Simpan.
- **Hasil diharapkan:**
  - Langkah 3 dan 4 **ditolak**, dengan pesan yang menyebut kolom itu sudah dipakai.
  - Langkah 2 (beda huruf besar-kecil) **juga ditolak** — kembar yang hanya berbeda kapital akan
    terbaca sebagai dua hal berbeda oleh sistem tetapi satu hal yang sama oleh manusia.
  - Hanya satu entri dengan nilai itu yang ada di daftar.
- **Prioritas:** Tinggi

### MD-KEL-04 — Induk wajib dipilih
- **Peran:** `ADM`
- **Prasyarat:** berlaku untuk Company (induk: Corporate), Business Unit (induk: Company), Production Line (induk: Business Unit), Station (induk: Production Line), Machinery (induk: Machinery Group)
- **Langkah:**
  1. Berlaku untuk tingkat yang punya induk: Company (Corporate), Business Unit (Company), Production Line (Business Unit), Station (Production Line), Machinery (Machinery Group).
  2. `[W-KELOLA]` untuk <Tingkat> sebagai `ADM`. Tekan **Tambah**.
  3. Isi seluruh kolom **kecuali** kolom induk — biarkan induknya belum dipilih.
  4. Tekan **Simpan**.
  5. Baca bentuk dan letak pesannya dengan teliti.
- **Hasil diharapkan:**
  - **Ditolak** dengan **pesan validasi** yang menunjuk kolom induk.
  - **TIDAK ADA** galat teknis: bukan stack trace, bukan "SQLSTATE", bukan "Server Error", bukan halaman 500.
  - Pesannya berbahasa Indonesia dan menyebut kolom induknya.
  - Perhatikan **bentuk** pesannya, bukan hanya bahwa ia ditolak: pesan validasi di sebelah kolom induk berarti lulus, galat teknis berarti gagal.
- **Prioritas:** Tinggi
- **Catatan QA:** yang diperiksa bukan hanya **bahwa** ia ditolak, tetapi **bentuk** penolakannya. Pesan validasi di sebelah kolom induk berarti lulus; galat teknis berarti gagal, meskipun datanya sama-sama tidak tersimpan.

### MD-KEL-05 — Nama kembar diperiksa dalam lingkup induknya
- **Peran:** `ADM`
- **Berlaku untuk:** **hanya Company.** Itu satu-satunya tingkat yang namanya dibatasi per
  induk ("Nama company sudah digunakan pada Corporate ini"). Corporate dibatasi secara global,
  dan lima tingkat lainnya tidak membatasi nama sama sekali — hanya kodenya, dan kode dibatasi
  secara global, bukan per induk. Tingkat selain Company **tidak ada** barisnya di berkas Excel:
  yang terdaftar hanya jalan yang dapat dikerjakan.
- **Prasyarat:** dua Corporate berbeda
- **Langkah:**
  1. Sebagai `ADM`, pastikan ada **dua** Corporate berbeda: Corporate A dan Corporate B.
  2. `[W-KELOLA]` untuk **Company**. Tambah Company bernama `PT Sama` dengan induk **Corporate A**. Simpan.
  3. Tambah Company bernama `PT Sama` dengan induk **Corporate B**. Simpan.
  4. Tambah Company bernama `PT Sama` dengan induk **Corporate A** lagi. Simpan.
- **Hasil diharapkan:**
  - Langkah 3 **berhasil** — nama yang sama sah di induk yang berbeda.
  - Langkah 4 **ditolak** — nama kembar di induk yang sama tidak boleh.
  - Daftar memuat dua `PT Sama`, masing-masing dengan induk berbeda, dan induknya terlihat di daftar.
- **Prioritas:** Sedang

### MD-KEL-06 — Ubah data berhasil dan terlihat
- **Peran:** `ADM`
- **Langkah:**
  1. `[W-KELOLA]` untuk <Tingkat> sebagai `ADM`. Pilih satu entri yang **dipakai** di layar lain.
  2. Tekan **Edit**, ubah namanya, tekan **Simpan**.
  3. Periksa daftar.
  4. Buka satu layar lain yang menampilkan nama itu (mis. pemilih Production Line pada laporan, atau kolom Mill di daftar user).
- **Hasil diharapkan:**
  - Nama baru terlihat di daftar.
  - Nama baru juga terlihat di layar lain yang memakainya — tidak ada tempat yang masih menampilkan nama lama.
  - Id entrinya tidak berubah: record yang menunjuknya tidak terlepas.
- **Prioritas:** Tinggi

### MD-KEL-07 — Hapus ditolak bila masih punya anak, dengan menyebut jumlahnya
- **Peran:** `ADM`
- **Prasyarat:** satu Corporate yang punya 2 Company; satu Company yang punya Business Unit; satu Business Unit yang punya Production Line
- **Langkah:**
  1. Sebagai `ADM`, siapkan: satu Corporate yang punya **2** Company; satu Company yang punya Business Unit; satu Business Unit yang punya Production Line.
  2. `[W-KELOLA]` untuk **Corporate**. Coba **Hapus** Corporate itu. Baca pesannya.
  3. Periksa apakah Corporate itu dan kedua Company-nya masih ada.
  4. Ulangi langkah 2–3 untuk Company yang punya Business Unit.
  5. Ulangi untuk Business Unit yang punya Production Line.
- **Hasil diharapkan:**
  - Setiap penghapusan **ditolak**.
  - Pesannya **menyebut jumlah penghalangnya** — mis. "masih memiliki 2 Company".
  - Induknya **tidak terhapus**, dan **tidak ada penghapusan berantai**: anak-anaknya juga masih utuh.
- **Prioritas:** Tinggi

### MD-KEL-08 — Hapus berhasil bila sudah tidak punya anak
- **Peran:** `ADM`
- **Langkah:**
  1. Pakai induk yang sama dari `MD-KEL-07`.
  2. Hapus **seluruh** anaknya satu per satu lebih dulu.
  3. Periksa daftar anaknya sudah kosong.
  4. Hapus induknya.
- **Hasil diharapkan:**
  - Penghapusan anak-anaknya **berhasil**.
  - Penghapusan induknya kini **berhasil**.
  - Induk itu **hilang** dari daftar, dan jumlah data berkurang.
- **Prioritas:** Tinggi

### MD-KEL-09 — Hapus meminta konfirmasi
- **Peran:** `ADM`
- **Langkah:**
  1. `[W-KELOLA]` untuk <Tingkat> sebagai `ADM`. Pilih satu entri **tanpa anak**.
  2. Tekan **Hapus**. Baca dialognya, lalu tekan **Batal**.
  3. Periksa daftar: apakah entri itu masih ada?
  4. Tekan **Hapus** lagi, kali ini **setujui**.
- **Hasil diharapkan:**
  - Langkah 2 memunculkan **dialog konfirmasi** — tidak langsung menghapus.
  - Langkah 3: entri **masih ada**, utuh.
  - Langkah 4: entri **terhapus** dan hilang dari daftar.
- **Prioritas:** Sedang

### MD-KEL-10 — Pencarian dan paginasi bekerja bersama
- **Peran:** `ADM`
- **Berlaku untuk:** **hanya Kelola Mesin.** Dari delapan tingkat Kelola, hanya layar itu yang
  punya kolom pencarian teks bebas; tujuh lainnya (Corporate, Company, Business Unit, Production
  Line, Station, Machinery Group, User & Role) menyaring lewat pemilih induk, bukan pencarian.
  Pada tujuh layar itu kasus ini **tidak berlaku**, dan barisnya memang **tidak ada** di berkas
  Excel: yang terdaftar hanya jalan yang dapat dikerjakan. Pencarian mill diuji terpisah di
  `MD-STR-04`.
- **Prasyarat:** data lebih dari satu halaman
- **Langkah:**
  1. `[W-KELOLA]` untuk <Tingkat> sebagai `ADM`. Pastikan datanya lebih dari satu halaman.
  2. Ketik satu kata kunci yang hasilnya **lebih dari satu halaman**.
  3. Catat jumlah total hasil yang tertulis.
  4. Buka **halaman 2**.
  5. Periksa **setiap** baris di halaman 2: apakah semuanya memuat kata kunci itu?
  6. Buka halaman 3 bila ada, dan periksa lagi.
- **Hasil diharapkan:**
  - Seluruh baris di halaman 2 (dan 3) **masih cocok** dengan kata kuncinya.
  - Jumlah total hasil tidak berubah saat berpindah halaman.
  - Kata kuncinya masih terisi di kolom pencarian setelah pindah halaman.
- **Prioritas:** Tinggi

### MD-KEL-11 — Pencarian tidak peduli huruf besar-kecil, dan karakter khusus diperlakukan sebagai huruf biasa
- **Peran:** `ADM`
- **Berlaku untuk:** **hanya Kelola Mesin**, dengan sebab yang sama seperti `MD-KEL-10`.
- **Prasyarat:** entri bernama mis. `Mill 100% Murni` dan `Mill BU_A`
- **Langkah:**
  1. `[W-KELOLA]` untuk <Tingkat> sebagai `ADM`. Buat dua entri: `Mill 100% Murni` dan `Mill BU_A`. Bila memungkinkan, buat juga `Mill BUxA`.
  2. Cari `100%`. Catat hasilnya.
  3. Hapus, cari `BU_A`. Catat hasilnya.
  4. Hapus, cari `mill` (huruf kecil). Catat hasilnya.
- **Hasil diharapkan:**
  - `100%` menemukan **tepat** entri yang namanya memuat tanda persen itu — bukan semua entri.
  - `BU_A` menemukan **tepat** `Mill BU_A` — **bukan** juga `Mill BUxA`.
  - `mill` menemukan ketiganya (tidak peduli huruf besar-kecil).
  - Di banyak sistem `%` dan `_` punya arti khusus dalam pencarian; di sini keduanya huruf biasa.
- **Prioritas:** Sedang
- **Catatan QA:** `%` dan `_` adalah karakter khusus pencarian di banyak sistem. Di sini keduanya harus diperlakukan sebagai huruf biasa.

### MD-KEL-12 — Logo mill dapat diunggah dan terlihat
- **Peran:** `ADM`
- **Prasyarat:** berlaku untuk Business Unit
- **Langkah:**
  1. `[W-KELOLA]` untuk **Business Unit** sebagai `ADM`. Tekan Edit pada satu mill.
  2. Unggah satu berkas gambar (PNG atau JPG) sebagai logo. Tekan **Simpan**.
  3. Buka kembali entri itu dan periksa logonya.
  4. Tekan Edit lagi, dan coba unggah berkas yang **bukan gambar** (mis. `.pdf` atau `.txt`).
- **Hasil diharapkan:**
  - Logo gambar **tersimpan** dan **terlihat** saat entri dibuka kembali.
  - Berkas bukan gambar **ditolak** dengan pesan yang jelas.
  - Penolakan itu tidak menghapus logo yang sudah tersimpan sebelumnya.
- **Prioritas:** Rendah

### 8.2 Struktur Mills (`screen-127`)

### MD-STR-01 — Papan kartu per mill tampil dengan hierarki di atasnya
- **Peran:** `ADM`
- **Langkah:**
  1. `[W-TREE]` sebagai `ADM`.
  2. Hitung jumlah kartu dan cocokkan dengan jumlah mill yang ada.
  3. Pada satu kartu, baca: baris di atas nama mill, nama mill, kodenya, dan jumlah Production Line.
  4. Periksa apakah daftar Production Line tercetak **di dalam** kartu itu.
- **Hasil diharapkan:**
  - Satu kartu per mill.
  - Tiap kartu memuat jalur **corporate › company** di atas nama mill-nya.
  - Kode mill dan jumlah Production Line tercetak.
  - Daftar Production Line ada **di dalam** kartu, masing-masing dengan aksinya.
- **Prioritas:** Tinggi

### MD-STR-02 — Keempat tingkat dapat dikelola dari layar ini
- **Peran:** `ADM`
- **Langkah:**
  1. `[W-TREE]` sebagai `ADM`.
  2. Tambah satu **Corporate** dari layar ini, lalu ubah namanya, lalu hapus.
  3. Ulangi ketiga aksi untuk **Company**.
  4. Ulangi untuk **Business Unit**.
  5. Ulangi untuk **Production Line** (lewat tombol di dalam kartu mill).
  6. Perhatikan apakah setiap aksi membuka **modal** atau berpindah layar.
- **Hasil diharapkan:**
  - Ketiga aksi (tambah, ubah, hapus) **berhasil** pada keempat tingkat.
  - Semuanya lewat **modal** — tidak berpindah ke layar lain.
  - Sesudah tiap aksi, kartu/daftarnya ikut berubah tanpa muat ulang manual.
- **Prioritas:** Tinggi

### MD-STR-03 — Corporate dan Company tetap terkelola walau tidak punya anak
- **Peran:** `ADM`
- **Prasyarat:** satu Corporate yang **belum** punya Company
- **Langkah:**
  1. Sebagai `ADM`, siapkan satu Corporate yang **belum punya Company** sama sekali.
  2. `[W-TREE]`.
  3. Cari Corporate itu di bagian ringkas Corporate/Company.
  4. Ubah namanya dari layar ini, lalu Simpan.
- **Hasil diharapkan:**
  - Corporate itu **tetap muncul** di bagian ringkas, meski ia tidak punya mill untuk digambar sebagai kartu.
  - Namanya **tetap dapat diubah** dari layar ini.
  - Kartu mill hanya memuat mill; Corporate/Company tanpa anak tidak boleh hilang — kalau hilang, ia tidak dapat dikelola di mana pun.
- **Prioritas:** Tinggi
- **Catatan QA:** kartu mill hanya memuat mill. Corporate/Company tanpa anak tidak boleh hilang dari layar ini, karena kalau hilang ia tidak dapat dikelola di mana pun.

### MD-STR-04 — Pencarian menyaring sebelum paginasi
- **Peran:** `ADM`
- **Prasyarat:** mill lebih dari satu halaman
- **Langkah:**
  1. Pastikan jumlah mill lebih dari satu halaman.
  2. `[W-TREE]` sebagai `ADM`. **Tanpa** mengisi pencarian, buka **halaman 2** dan catat satu nama mill di sana.
  3. Kembali ke halaman 1.
  4. Ketik nama mill dari langkah 2 pada kolom pencarian.
- **Hasil diharapkan:**
  - Mill itu **ditemukan**.
  - Pencarian menyaring **seluruh** data, bukan hanya halaman yang sedang tampil.
  - Kalau tidak ditemukan, itu bug: hasil di halaman lain akan terbaca "tidak ada".
- **Prioritas:** Tinggi
- **Catatan QA:** kalau pencarian hanya menyaring halaman aktif, hasil di halaman lain terbaca sebagai "tidak ditemukan" — itu bug.

### MD-STR-05 — Aturan hapus sama dengan layar Kelola
- **Peran:** `ADM`
- **Langkah:**
  1. `[W-TREE]` sebagai `ADM`.
  2. Jalankan seluruh langkah `MD-KEL-07` dari layar ini (hapus induk yang masih punya anak).
  3. Jalankan seluruh langkah `MD-KEL-08` dari layar ini.
- **Hasil diharapkan:**
  - Hasilnya **sama** dengan layar Kelola: penolakan menyebut jumlah penghalangnya.
  - Tidak ada penghapusan berantai.
  - Penghapusan berhasil hanya setelah anaknya habis.
- **Prioritas:** Tinggi

### 8.3 Kelola Periode Pelaporan (`screen-128`)

### MD-PRD-01 — Hanya Admin dapat membuka layar ini
- **Peran:** `SPV-A`, `MGR-A`, `ADM`
- **Langkah:**
  1. `[W-LOGIN]` sebagai `SPV-A`. Cari menu **Kelola Periode Pelaporan** di sidebar.
  2. Ketik `/master-data/periods` langsung di bilah alamat.
  3. Logout, ulangi langkah 1–2 sebagai `MGR-A`.
  4. Logout, ulangi sebagai `ADM`.
- **Hasil diharapkan:**
  - `SPV-A`: menu tidak ada, alamat langsung menjawab **403**.
  - `MGR-A`: sama — **403**.
  - `ADM`: menu ada dan layarnya terbuka.
- **Prioritas:** Tinggi

### MD-PRD-02 — Panel "Periode Terbuka Hari Ini" memakai syarat gabungan
- **Peran:** `ADM`
- **Prasyarat:** (a) periode yang punya stasiun Terbuka **dan** mencakup hari ini; (b) periode yang punya stasiun Terbuka tapi **tidak** mencakup hari ini
- **Langkah:**
  1. Sebagai `ADM`, siapkan: (a) periode yang punya stasiun **Terbuka** **dan** rentangnya mencakup hari ini; (b) periode yang punya stasiun **Terbuka** tapi rentangnya **tidak** mencakup hari ini.
  2. `[W-PERIOD]`.
  3. Baca panel **Periode Terbuka Hari Ini**.
  4. Periksa apakah (a) ada di panel itu, dan apakah (b) ada.
- **Hasil diharapkan:**
  - Hanya **(a)** yang muncul di panel.
  - **(b) tidak muncul** — tanggal hari ini adalah bagian dari syaratnya, bukan sekadar penanda.
  - Keduanya tetap terlihat di daftar periode utama di bawahnya.
- **Prioritas:** Sedang

### MD-PRD-03 — Mill tanpa periode yang memenuhi syarat tetap punya kartu, dalam keadaan redup
- **Peran:** `ADM`
- **Prasyarat:** satu mill tanpa periode terbuka hari ini
- **Langkah:**
  1. Sebagai `ADM`, pastikan ada satu mill yang **tidak** punya periode terbuka hari ini.
  2. `[W-PERIOD]`.
  3. Baca panel **Periode Terbuka Hari Ini** dan cari kartu mill itu.
  4. Perhatikan tampilannya dibanding kartu mill yang punya periode terbuka.
- **Hasil diharapkan:**
  - Mill itu **tetap punya kartu**, tampil **redup**.
  - Ia **tidak disembunyikan** — ketiadaan periode adalah informasi, bukan alasan menghilangkan mill.
  - Ada keterangan pada kartu itu bahwa belum ada periode terbuka hari ini.
- **Prioritas:** Sedang

### MD-PRD-04 — Hitungan pada kartu adalah stasiun Terbuka dibanding seluruh stasiun periode
- **Peran:** `ADM`
- **Prasyarat:** periode dengan sebagian stasiun Terbuka dan sebagian Tertutup
- **Langkah:**
  1. Sebagai `ADM`, siapkan satu periode dengan **sebagian** stasiunnya Terbuka dan sebagian **Tertutup**.
  2. `[W-PERIOD]`. Baca angka pada kartu mill itu di panel atas.
  3. Buka **Detail Periode** itu (`/master-data/periods/<id>`).
  4. Hitung sendiri: berapa stasiun berstatus Terbuka, dan berapa seluruh stasiun periode itu.
  5. Bandingkan dengan angka di kartu.
- **Hasil diharapkan:**
  - Angka pada kartu adalah **stasiun Terbuka dibanding seluruh stasiun periode** itu.
  - Angkanya **cocok** dengan hitungan manual dari Detail Periode.
  - Penyebutnya bukan 18 dan bukan jumlah stasiun mill — tapi jumlah stasiun pada periode itu.
- **Prioritas:** Sedang

### MD-PRD-05 — Mill wajib dipilih saat membuat periode
- **Peran:** `ADM`
- **Langkah:**
  1. `[W-PERIOD]` sebagai `ADM`. Tekan **Tambah Periode**.
  2. Isi nama, tanggal mulai, dan tanggal selesai — tapi **jangan** pilih Business Unit.
  3. Tekan **Simpan**.
- **Hasil diharapkan:**
  - **Ditolak** dengan pesan yang menunjuk kolom mill / Business Unit.
  - Periode tidak tersimpan.
  - Isi kolom lain tidak hilang.
- **Prioritas:** Tinggi

### MD-PRD-06 — Periode tumpang tindih pada satu mill ditolak
- **Peran:** `ADM`
- **Prasyarat:** sudah ada periode 1–31 Oktober pada Mill A
- **Langkah:**
  1. Sebagai `ADM`, pastikan Mill A sudah punya periode **1–31 Oktober**.
  2. `[W-PERIOD]` → **Tambah Periode**. Pilih Mill A, rentang **15 Okt – 15 Nov**. Simpan.
  3. Baca pesannya.
  4. Ulangi dengan rentang yang sama tetapi pada **Mill B**. Simpan.
- **Hasil diharapkan:**
  - Langkah 2 **ditolak**, dengan pesan yang menyebut **tumpang tindih** dengan periode yang sudah ada.
  - Langkah 4 **berhasil** — tumpang tindih hanya dilarang dalam satu mill yang sama.
- **Prioritas:** Tinggi

### MD-PRD-07 — Periode tidak memilih jenis stasiun; daftarnya diturunkan sistem
- **Peran:** `ADM`
- **Langkah:**
  1. `[W-PERIOD]` sebagai `ADM`. Tekan **Tambah Periode**.
  2. Baca **seluruh** kolom pada form: apakah ada pilihan jenis stasiun?
  3. Isi dan Simpan.
  4. Buka **Detail Periode** yang baru dibuat itu.
  5. Baca daftar stasiunnya dan bandingkan dengan stasiun yang ada di mill tersebut.
- **Hasil diharapkan:**
  - Saat membuat, **tidak ada** pilihan jenis stasiun sama sekali.
  - Di Detail, daftar stasiunnya **sudah terisi** sesuai stasiun yang ada di mill itu — diturunkan sistem.
  - Setiap stasiun pada daftar itu punya statusnya sendiri (draft/terbuka/tertutup).
- **Prioritas:** Sedang

### MD-PRD-08 — Tanggal akhir tidak boleh sebelum tanggal mulai
- **Peran:** `ADM`
- **Langkah:**
  1. `[W-PERIOD]` sebagai `ADM`. Tekan **Tambah Periode**.
  2. Isi tanggal mulai = hari ini.
  3. Isi tanggal selesai = **7 hari sebelum** hari ini.
  4. Tekan **Simpan**. Catat letak pesannya.
  5. Coba juga tanggal selesai = **sama** dengan tanggal mulai.
- **Hasil diharapkan:**
  - Tanggal selesai lebih awal **ditolak**, dengan pesan yang menyebut rentangnya, muncul di sebelah kolom Tanggal Selesai.
  - Periode tidak tersimpan.
  - Tanggal selesai sama dengan tanggal mulai: catat perilakunya apa adanya (periode satu hari).
- **Prioritas:** Tinggi

### MD-PRD-09 — Tutup dan Buka periode hanya oleh Admin, dan statusnya berpengaruh
- **Peran:** `ADM`, lalu `SPV-A`
- **Langkah:**
  1. Jalankan seluruh langkah `LS-10` sebagai `ADM` (tutup → coba input → buka kembali → coba input).
  2. `[W-PERIOD]` sebagai `SPV-A` — atau, bila 403 menghalangi, periksa dari Detail Periode bila dapat diakses.
  3. Cari aksi **Tutup Periode** dan **Buka Periode** sebagai `SPV-A`.
  4. Ulangi pencarian itu sebagai `MGR-A`.
- **Hasil diharapkan:**
  - Hasil `LS-10` terpenuhi: status periode benar-benar mengunci dan membuka input.
  - `SPV-A` dan `MGR-A` **tidak punya** aksi tutup/buka sama sekali — bukan hanya tombolnya kelabu, tapi memang tidak ada.
  - Hanya `ADM` yang dapat melakukannya.
- **Prioritas:** Tinggi

### MD-PRD-10 — Hapus periode yang sudah punya record ditolak
- **Peran:** `ADM`
- **Prasyarat:** periode yang sudah membingkai record stasiun
- **Langkah:**
  1. Sebagai `ADM`, siapkan satu periode yang **sudah membingkai record stasiun** (ada record bertanggal di dalam rentangnya).
  2. `[W-PERIOD]`. Coba **Hapus** periode itu.
  3. Baca pesannya.
  4. Periksa daftar periode, lalu periksa record stasiunnya di Data Browser.
- **Hasil diharapkan:**
  - **Ditolak** dengan pesan yang menyebut sebabnya (masih punya record).
  - Periode **tidak terhapus**.
  - Record stasiunnya **tidak terhapus** — tidak ada penghapusan berantai.
- **Prioritas:** Tinggi

### MD-PRD-11 — Filter dan paginasi daftar periode
- **Peran:** `ADM`
- **Langkah:**
  1. `[W-PERIOD]` sebagai `ADM`.
  2. Jalankan seluruh langkah `MD-KEL-10` pada daftar periode: cari kata kunci yang hasilnya lebih dari satu halaman, lalu buka halaman 2.
  3. Periksa setiap baris halaman 2.
- **Hasil diharapkan:**
  - Hasilnya sama dengan `MD-KEL-10`: seluruh baris halaman 2 masih cocok dengan filternya.
  - Jumlah total hasil tidak berubah antar halaman.
- **Prioritas:** Sedang

---

## 9. User & Role, Pengaturan Mill (`AD-`)

### AD-01 — Tambah user dengan peran dan mill
- **Peran:** `ADM`
- **Langkah:**
  1. `[W-USERS]` sebagai `ADM`. Tekan **Tambah**.
  2. Isi username, nama, peran **Supervisor**, mill, dan password. Tekan **Simpan**.
  3. Periksa entri itu muncul di daftar.
  4. Logout.
  5. `[W-LOGIN]` dengan akun baru itu.
  6. Baca seluruh isi sidebar dan bandingkan dengan kolom Supervisor pada matriks 1.3.
- **Hasil diharapkan:**
  - User tersimpan dan muncul di daftar.
  - Akun baru itu **berhasil login** — kasus ini tidak selesai saat datanya tersimpan.
  - Sidebar memuat tepat menu yang bertanda ✓ untuk Supervisor di matriks 1.3.
  - User yang tersimpan tetapi tidak bisa masuk adalah bug yang hanya terlihat bila langkah 5 benar-benar dijalankan.
- **Prioritas:** Tinggi

### AD-02 — Username kembar ditolak
- **Peran:** `ADM`
- **Langkah:**
  1. `[W-USERS]` sebagai `ADM`. Catat satu username yang sudah ada, mis. `supervisor01`.
  2. Tekan **Tambah**, isi username **persis sama**, lengkapi kolom lain, Simpan.
  3. Tekan **Tambah** lagi, isi username yang sama dengan **huruf besar semua**, Simpan.
  4. Ulangi dengan spasi di depan/belakang username itu.
- **Hasil diharapkan:**
  - Ketiganya **ditolak**.
  - Pesannya menyebut bahwa username sudah dipakai.
  - Tidak ada user ganda yang tercipta.
- **Prioritas:** Tinggi

### AD-03 — Peran terikat mill wajib punya mill
- **Peran:** `ADM`
- **Langkah:**
  1. `[W-USERS]` sebagai `ADM`. Tekan **Tambah**.
  2. Isi username, nama, password, dan peran **Supervisor** — tapi **jangan** pilih mill. Simpan.
  3. Baca pesannya dan letaknya.
  4. Ulangi: isi semuanya dengan peran **Admin**, dan tetap **jangan** pilih mill. Simpan.
- **Hasil diharapkan:**
  - Langkah 2 **ditolak**, dengan pesan yang menunjuk kolom mill.
  - Langkah 4 **berhasil** — Admin memang tidak terikat satu mill.
  - Kalau Admin juga ditolak, itu temuan: ia akan membuat Admin mustahil dibuat.
- **Prioritas:** Tinggi

### AD-04 — Ubah peran langsung mengubah menu yang terlihat
- **Peran:** `ADM` + akun uji
- **Langkah:**
  1. `[W-USERS]` sebagai `ADM`. Pilih satu user berperan **Supervisor**.
  2. Tekan Edit, ubah perannya menjadi **Mill Management**, Simpan.
  3. Logout. `[W-LOGIN]` sebagai user itu.
  4. Baca seluruh isi sidebar dan bandingkan dengan kolom Mill Management pada matriks 1.3.
  5. Buka satu record stasiun lewat `[W-FORM-EDIT]`. Coba isi **Checked By**.
  6. Coba isi **Acknowledged By**.
- **Hasil diharapkan:**
  - Sidebar berubah sesuai peran baru — mis. **Laporan Manajemen** kini muncul.
  - **Checked By** kini **tidak bisa** diisi.
  - **Acknowledged By** kini **bisa** diisi.
  - Perubahan peran berlaku tanpa perlu membuat akun baru.
- **Prioritas:** Tinggi

### AD-05 — Nonaktifkan user memblokir login
- **Peran:** `ADM`
- **Langkah:**
  1. Jalankan seluruh langkah `AU-04` dari layar `[W-USERS]`.
  2. Pastikan langkah terakhir `AU-04` dijalankan: **aktifkan kembali** akunnya.
- **Hasil diharapkan:**
  - Hasilnya sesuai `AU-04`: sesi yang sedang berjalan dikeluarkan, dan login berikutnya gagal dengan pesan akun tidak aktif.
  - Sesudah diaktifkan kembali, akun itu bisa login lagi.
- **Prioritas:** Tinggi

### AD-06 — Reset password oleh Admin
- **Peran:** `ADM` + akun uji
- **Langkah:**
  1. `[W-USERS]` sebagai `ADM`. Pilih satu akun uji (jangan akun yang sedang Anda pakai).
  2. Jalankan aksi **Reset Password** pada akun itu, tetapkan password baru.
  3. Logout.
  4. `[W-LOGIN]` dengan akun itu memakai password **baru**.
  5. Logout. Coba login dengan password **lama**.
- **Hasil diharapkan:**
  - Reset **berhasil** dengan pesan konfirmasi.
  - Login dengan password baru **berhasil**.
  - Login dengan password lama **gagal**.
- **Prioritas:** Tinggi

### AD-07 — Pengaturan Mill: Mill Management hanya mill sendiri
- **Peran:** `MGR-A`
- **Prasyarat:** 2 mill ada
- **Langkah:**
  1. Pastikan ada **2** mill di sistem.
  2. `[W-MILLSET]` sebagai `MGR-A`.
  3. Baca nama mill yang dapat diatur. Cari pemilih mill.
  4. Periksa apakah nama mill kedua muncul di mana pun pada layar ini.
- **Hasil diharapkan:**
  - Hanya **mill sendiri** yang dapat diatur.
  - Mill lain **tidak terlihat** dan **tidak dapat dipilih** — tidak ada pemilih mill untuk peran ini.
  - Nama mill kedua tidak tercetak di layar mana pun.
- **Prioritas:** Tinggi
- **Catatan QA:** layar ini **tidak** menyebut nama mill yang sedang diatur untuk peran Mill
  Management — isinya berbunyi "untuk mill Anda". Itu **bukan** bagian dari hasil yang diuji di
  atas, jadi jangan dilaporkan sebagai bug; catat saja bila menurut Anda perlu ditegaskan.
  Untuk Admin, mill yang sedang diatur selalu terlihat karena ada pemilihnya (`AD-08`).

### AD-08 — Pengaturan Mill: Admin dapat semua mill
- **Peran:** `ADM`
- **Langkah:**
  1. `[W-MILLSET]` sebagai `ADM`.
  2. Cari pemilih mill dan buka daftarnya.
  3. Pilih Mill A, baca pengaturannya. Lalu pilih Mill B dan baca pengaturannya.
- **Hasil diharapkan:**
  - Pemilih mill **ada** dan memuat **seluruh** mill.
  - Pengaturan setiap mill dapat dibuka dan diatur.
  - Berganti mill mengganti nilai yang tampil — bukan menampilkan nilai mill sebelumnya.
- **Prioritas:** Tinggi

### AD-09 — Supervisor dan Operator tidak punya Pengaturan Mill
- **Peran:** `SPV-A`, `OPR-A`
- **Langkah:**
  1. `[W-LOGIN]` sebagai `SPV-A`. Cari menu **Mills Setting** di sidebar.
  2. Ketik `/mill-settings` langsung di bilah alamat.
  3. Logout, ulangi langkah 1–2 sebagai `OPR-A`.
- **Hasil diharapkan:**
  - Menu **tidak ada** di sidebar untuk keduanya.
  - Alamat langsung menjawab **403** untuk keduanya.
  - Halaman 403-nya masih di dalam kerangka aplikasi (lihat `LS-01`).
- **Prioritas:** Tinggi

### AD-10 — Perubahan Pengaturan Mill tersimpan dan berlaku
- **Peran:** `MGR-A` (web) + `OPR-A` (mobile)
- **Catatan jalan:** pengaturan di layar ini dipakai **aplikasi mobile**, bukan layar web mana pun
  — Gambar Halaman Utama pada Home mobile, saklar kirim-langsung pada penyimpanan Operator, dan
  Icon Station pada daftar stasiun mobile. **Nama Aplikasi dan Logo adalah pengecualian: keduanya
  belum dibaca satu layar pun.** Baris merek pada Home mobile memakai **nama produk** yang tetap,
  dan itu keputusan yang disengaja — nama produk pada header terpisah dari Nama Aplikasi per mill.
  Jadi langkah 1–3 dijalankan di web, dan langkah 4–5 **harus** dijalankan di aplikasi mobile
  untuk memeriksa bahwa nilainya **sampai ke perangkat**, bukan bahwa ia tampil di Home.
- **Langkah:**
  1. `[W-MILLSET]` sebagai `MGR-A`. Catat nilai awal **Nama Aplikasi**.
  2. Ubah nilai itu, tekan **Simpan**.
  3. Muat ulang halaman (F5). Baca nilainya.
  4. `[M-LOGIN]` sebagai `OPR-A` pada mill yang sama, lalu sinkronkan bila diminta.
  5. Periksa bahwa nilai baru itu sudah ada **di perangkat** (tersinkron), lalu baca baris merek
     pada layar Home mobile.
  6. Kembalikan nilainya ke semula lewat `[W-MILLSET]`.
- **Hasil diharapkan:**
  - Simpan **berhasil** dengan pesan konfirmasi.
  - Nilai baru **bertahan** sesudah muat ulang.
  - Nilai baru **sampai ke perangkat** sesudah sinkronisasi — itu yang diperiksa langkah 4–5:
    jalur simpan → kirim → simpan di perangkat bekerja utuh.
  - Baris merek pada Home mobile **tetap nama produk**, TIDAK berubah mengikuti Nama Aplikasi.
    Itu **bukan** kegagalan: nama produk pada header memang terpisah dari Nama Aplikasi per mill.
  - **Terbuka, dan bukan bagian dari kasus ini:** Nama Aplikasi dan Logo saat ini tersimpan dan
    tersinkron tetapi belum dibaca layar mana pun. Keduanya menunggu layar yang memakainya.
- **Prioritas:** Sedang
- **Catatan QA:** pengaturan **Jumlah Cages** sudah **tidak ada lagi** di layar ini (jumlah cages
  kini diturunkan dari data mesin stasiun). Kalau Anda mencarinya karena pernah membacanya di
  versi dokumen lama, ketiadaannya **bukan** bug.

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
| Web stasiun (`WB-`) | 25 | **450** | Browser 9×18 + Detail 5×18 + Form 11×18 |
| Laporan periode (`LP-`) | 31 | **341** | Web 19×11 + Mobile 12×11 |
| Dashboard (`DS-`) | 12 | 12 | 1× masing-masing |
| Master data (`MD-`) | 28 | **112** | Kelola 12×8 + Struktur 5×1 + Periode 11×1 |
| User/Mill (`AD-`) | 10 | 10 | 1× masing-masing |
| **Total** | **170 kasus unik** | **± 1.509 jalan** | |

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
| **Data Saya (Web)** — `screen-145` | Spesifikasinya ada di `screen-index`, **kodenya belum ada**. Akses web Operator hari ini hanya Beranda Operator dan Ganti Password. Dokumen ini pernah memuat empat kasus untuk layar ini; keempatnya dibuang 2026-10-08 karena menguji layar yang tidak dapat dibuka. |

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

> **Tabel ini adalah catatan INTERNAL dan sengaja berbeda dari berkas Excel.** Di sini
> riwayatnya disimpan — termasuk entri yang sudah selesai, nama kolom, dan tanggal
> keputusan — karena itu berguna bagi tim pengembang. Berkas Excel yang diserahkan ke klien
> memuat sheet **"Catatan & Batasan"** yang hanya menyatakan **batasan yang masih berlaku**,
> tanpa nama berkas, nama kolom basis data, riwayat perbaikan, atau rujukan ke uji otomatis.
> Perbedaan itu disengaja; jangan "diselaraskan".


Hal-hal di bawah **sudah diketahui** dan **bukan temuan baru**. Kalau QA menemukannya,
cukup rujuk nomor ini alih-alih membuat laporan bug baru.

| No | Hal |
|---|---|
| K-1 | **Satuan suhu ditulis `C`, bukan `°C`** pada laporan Kernel Plant (web & mobile). Disengaja: nilainya datang dari data master apa adanya, dan mengubahnya di tampilan akan membuat web dan mobile berselisih. |
| K-2 | **SELESAI 2026-10-08 — tidak perlu dilaporkan lagi.** Kolom Kernel Plant yang dulu bernama `shell_loss_percent` kini **"Shell Bin Kernel Loss"** di seluruh layar (Form, Detail, Data Preview, Laporan, dan ekspor CSV), mengikuti nama standar di master. Nama lamanya menyebut zat yang salah — kehilangan *shell*, padahal yang diukur adalah **kernel yang terbuang ke shell bin**. Angkanya tidak pernah salah dan tidak berubah; hanya namanya. Yang perlu diperiksa QA: label barunya konsisten di kelima tempat itu, dan header CSV berbunyi `Shell Bin Kernel Loss (%)`. |
| K-3 | **SELESAI 2026-10-08 — tidak perlu dilaporkan lagi.** Laporan Depricarping menulis "minimal satu dari **enam** kolom bacaan diisi"; kolom bacaan Depricarping ada **sembilan**, dan layar yang sama menyebut "sembilan" di tempat lain. Salah di **web dan mobile**, bukan mobile saja seperti catatan ini dulu menyebut, dan **bukan kosmetik**: angka itu dipakai pembaca untuk menafsirkan penyebut cakupan. Asal-usulnya salin-tempel dari Threshing dan Pressing, yang memang enam kolom. Kini dijaga uji yang menurunkan angkanya dari `READING_FIELDS` dan memeriksa web serta mobile sekaligus. |
| K-4 | **Laporan Depricarping (Mobile)**: tiga angka pada blok downtime tampil tanpa gaya yang semestinya. Kosmetik. |
| K-5 | **DIPERBAIKI 2026-10-08 — catatan ini dulu salah.** Ia berbunyi "Dashboard hanya meringkas 3 stasiun, bukan 18 — disengaja". Blok tiga-stasiun itu **sudah dibuang pada 2026-09-17** atas permintaan user. Keadaan sebenarnya: halaman `/dashboard` adalah **tampilan demo dengan angka hard-coded** — tanpa filter tanggal, tanpa pemilih mill, tidak membaca data stasiun sama sekali. Ia **menyatakan dirinya** demo (judul "CPO Mill Demo" + chip "Data dummy"). Ringkasan tiga stasiun kini hanya ada di balik API `/api/dashboard/summary`, bukan di layar. `DS-01`…`DS-03` ditulis ulang mengikuti keadaan ini. |
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

## 11.4b Tingkat pengujian — kalau waktunya terbatas

1.509 jalan terlalu banyak untuk satu putaran manual, dan sebabnya bukan pengulangannya
salah: **satu daftar rata mencampur dua pertanyaan berbeda.** "Apakah setiap alur bekerja"
butuh 170 jalan. "Apakah ke-18 implementasi stasiun bekerja" butuh sisanya. Kolom
**Tingkat** di berkas Excel memisahkannya **tanpa membuang apa pun** — penguji menyaring,
bukan memakai berkas lain yang lebih kecil dan bisa tertinggal usang.

| Tingkat | Isinya | Jalan |
|---|---|---:|
| **1 — ALUR** | Setiap kasus **sekali**, pada stasiun wakil | **170** |
| **2 — KABEL** | 5 kasus pada 17 stasiun sisanya (+1 untuk 10 laporan sisanya) | **87** |
| **1+2** | **Cakupan yang masuk akal untuk satu putaran manual** | **257** (18%) |
| 3 — SISA | Pengulangan penuh; untuk putaran rilis atau saat satu stasiun berubah | 1.184 |

**Stasiun wakil Tingkat 1** dipilih karena paling banyak melewati cabang, bukan karena
alfabet: **Depricarping** (9 kolom bacaan, kolom downtime angka, kolom temuan teks, punya
master target), **Kernel Plant** untuk laporan (dua pasang kolom berbagi satu standar),
**Business Unit** untuk Kelola (punya induk dan kolom logo). Untuk kasus yang tidak berlaku
di sana — mis. `MB-FORM-11` yang khusus stasiun per-transaksi — Tingkat 1 jatuh ke stasiun
pertama yang berlaku, supaya setiap kasus benar-benar punya satu jalan yang dapat dikerjakan.

**Tingkat 2 adalah lima kasus yang gagal kalau satu stasiun salah dikabel:** `MB-FORM-01`
(jalur tulis mobile), `MB-PRV-06` (jalur baca mobile), `WB-FRM-01` (jalur tulis web),
`WB-BRW-03` (penurunan hitungan), `WB-DTL-03` (jalur baca web), plus `LP-W-01` untuk
laporannya.

**Yang hilang, dinyatakan terbuka.** Tingkat 1+2 **tidak** membuktikan ke-18 stasiun
berperilaku benar pada setiap cabang. Ke-18 stasiun punya `*RecordService`, Form, dan Data
Preview-nya sendiri-sendiri, jadi bug di satu stasiun tidak mengatakan apa pun tentang yang
lain — dan ketiga defek yang ditemukan 2026-10-08 semuanya hanya ada di **satu** stasiun.
Melewatkan Tingkat 3 adalah **keputusan risiko**, bukan penghematan gratis.

### Yang dilakukan LEBIH DULU daripada memangkas

Pangkas waktu **penyiapan**, bukan cakupan. Yang mendominasi waktu QA bukan 1.509 penekanan
tombol, melainkan langkah *"Siapkan periode yang kolomnya terisi tidak sama banyak…"*.

`backend/database/seeders/BrowserTestFixtureSeeder.php` **sudah** membuat: satu mill, satu
Production Line dengan stasiun aktif untuk **setiap** jenis, satu `PL Tanpa <Station>` per
keluarga stasiun (itu persis prasyarat `MB-FORM-15` dan `WB-FRM-07`), dan akun untuk keempat
peran. Jalankan `e2e-web/scripts/prepare-db.sh`, lalu uji di basis data itu — banyak langkah
"Siapkan…" langsung gugur, dan **cakupannya tidak berkurang sedikit pun.**

> Catatan: skrip itu **destruktif** dan hanya mau menyentuh basis data berakhiran `_e2e`.
> Jangan pernah mengarahkannya ke basis data dev.

---

## 11.5 Versi ringkas (opsional)

Ada berkas Excel kedua, `Mill-Smart-Log-Test-Case-Blackbox-Ringkas.xlsx`, yang memangkas
**60 jalan** (1.509 → 1.449). Yang dipangkas hanya empat kasus, dari 18× menjadi 3×:

| Kasus | Yang sebenarnya diuji |
|---|---|
| `MB-PRV-02`, `MB-PRV-04`, `MB-PRV-08` | komponen **`ListFilterBar`** |
| `MB-PRV-07` | komponen **`RecordVerificationStatus`** |

Keduanya **komponen bersama** yang dipakai ke-18 layar Data Preview — satu berkas kode,
jadi perilakunya tidak dapat berbeda per stasiun. Dijalankan pada tiga stasiun berbeda
bentuk: Depricarping (24 slot), Weighbridge (per-transaksi), Effluent Plant (berkolom
status).

**Risikonya, dinyatakan terbuka:** kalau kelak satu layar Data Preview berhenti memakai
komponen itu dan menulis filternya sendiri, pemangkasan ini tidak akan menangkapnya.
Pakai versi **penuh** untuk putaran rilis.

**Yang TIDAK dipangkas meski juga 18×,** dan sebabnya diperiksa bukan diduga: breadcrumb
(`MB-MON-06`) ditulis ulang di setiap view, bukan komponen bersama. Dan ke-18 stasiun punya
`*RecordService`, komponen Livewire Form, Form mobile, serta Data Preview-nya
**sendiri-sendiri** — 18 berkas masing-masing — jadi bug di satu stasiun tidak mengatakan
apa pun tentang yang lain. Itu sebabnya 88% pengulangan di dokumen ini beralasan, dan hanya
4% yang layak dibuang.

### Tiga keluaran Excel, satu sumber

| Berkas | Jalan | Dipakai untuk |
|---|---:|---|
| `…-Blackbox-Alur.xlsx` | **265** | **Satu putaran manual.** Tingkat 1+2 saja. |
| `…-Blackbox-Ringkas.xlsx` | 1.449 | Penuh, dikurangi 60 jalan komponen bersama. |
| `…-Blackbox.xlsx` | 1.509 | Putaran rilis. Cakupan penuh. |

Ketiganya dibangun dari **skrip yang sama**, jadi tidak ada yang bisa tertinggal usang:

```
python3 parse-cases.py cases.json
python3 build-xlsx.py cases.json Mill-Smart-Log-Test-Case-Blackbox.xlsx
python3 build-xlsx.py cases.json Mill-Smart-Log-Test-Case-Blackbox-Ringkas.xlsx ringkas
python3 build-xlsx.py cases.json Mill-Smart-Log-Test-Case-Blackbox-Alur.xlsx    alur
```

Berkas **Alur** memuat kotak merah di sheet Panduan yang menyatakan apa yang **tidak** ada
di dalamnya — 1.244 jalan Tingkat 3 — supaya penguji yang menyelesaikannya tidak melaporkan
"semua lulus" untuk cakupan yang memang lebih sempit.

---

## 12. Lembar hasil

Di berkas Excel pendamping, lembar ini **sudah terjabarkan**: sheet `Kasus Uji` memuat
satu baris per jalan (**1.509 baris**), nama stasiunnya sudah disubstitusikan ke dalam
langkahnya, dan kolom pengisian menempel langsung pada barisnya. Tidak ada lagi kolom
"Jml Jalan" yang harus diterjemahkan sendiri.

Tabel di bawah hanya untuk yang menguji dari kertas. Untuk kasus yang dijalankan 18× atau
11×, buat **satu baris per stasiun**.

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

Tidak ada nilai "tidak berlaku". Varian yang kasusnya memang tidak punya apa pun untuk diuji —
mis. `WB-BRW-03` di Weighbridge, yang gridnya bukan 24 slot waktu — **tidak terdaftar sebagai
baris sama sekali**. Setiap baris di berkas Excel adalah jalan yang dapat dikerjakan, jadi
seluruhnya ikut dihitung sebagai pekerjaan dan penyebutnya tidak perlu dikoreksi.

> Kasus yang prasyaratnya tidak tersedia **bukan** kasus yang lulus. Menandainya Lulus
> membuat laporan pengujian menyatakan sesuatu yang tidak pernah diperiksa.
