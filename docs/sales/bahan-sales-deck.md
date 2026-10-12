# Bahan Sales Deck — Mills Smart Log

31 tangkapan layar dari sistem **yang berjalan hari ini**, beserta penjelasan apa
yang ditunjukkan tiap gambar dan mengapa itu layak dijual. Berkasnya ada di
`docs/sales/screenshots/`.

---

## Yang perlu diketahui sebelum memakainya

**Angkanya data demo, bukan data pelanggan.** Seluruh tangkapan diambil dari
basis data demo (`PT Sawit Nusantara` / `Business Unit A`, periode September
2026) yang dibangun dari seeder demo proyek ini — bukan dari pabrik sungguhan.
Angka seperti "27.700.337 kg" realistis bentuknya, tetapi **jangan disajikan
sebagai hasil pelanggan**. Kalau deck-nya untuk prospek tertentu, sebaiknya
angkanya diganti data mereka sendiri sebelum dipresentasikan.

**Nama mill-nya generik.** Demo memakai "Business Unit A…D". Kalau ada waktu,
mengganti nama itu menjadi nama pabrik yang masuk akal akan menaikkan kualitas
deck secara mencolok dengan usaha kecil.

**Gelembung asisten AI disembunyikan** di hampir semua bidikan karena ia
mengambang menutupi isi; ia dipotret tersendiri di `web-14`.

**Cara membuat ulang** (misalnya sesudah UI berubah): nyalakan backend e2e +
Vite, lalu `npx playwright test -c shots/playwright.config.ts` di `e2e-web/` dan
`mobile/`. Skripnya menolak memotret layar yang kosong — jadi gambar yang
dihasilkan tidak akan pernah "cantik tapi tidak menunjukkan apa pun".

---

## WEB — untuk Supervisor, Mill Management, dan Admin

| # | Berkas | Yang ditunjukkan | Nilai jualnya |
|---|---|---|---|
| 01 | `web-01-login.png` | Layar masuk | Satu pintu untuk empat peran; sesi dan hak akses ditentukan di sini |
| 02 | `web-02-dashboard.png` | Dashboard mill | Angka operasional hari ini dalam satu layar, tanpa menunggu rekap manual |
| 03 | `web-03-pemilih-laporan-stasiun.png` | Grid 18 jenis stasiun | **Cakupan**: pabrik punya 18 jenis stasiun, semuanya sudah punya tempat. Tile yang belum berlaporan tampak kelabu — jujur, bukan disembunyikan |
| 04 | `web-04-laporan-weighbridge.png` | Laporan periode Weighbridge, satu halaman penuh | Gambar paling kuat di seluruh set. Satu periode = KPI, grafik, rekap, dan tabel harian dalam satu tarikan |
| 05 | `web-05-laporan-boiler-room.png` | Laporan periode stasiun bergrid 24 slot | Pola laporan yang sama berlaku lintas stasiun — bukan satu laporan khusus yang dibuat demo |
| 06 | `web-06-laporan-manajemen.png` | Laporan Manajemen | Rekap harian lintas stasiun per production line untuk Mill Management |
| 07 | `web-07-data-browser-weighbridge.png` | Data Browser + filter + Ekspor CSV/Excel | Data mentahnya bisa ditelusuri dan dibawa keluar; tidak terkunci di dalam sistem |
| 08 | `web-08-detail-record-boiler-room.png` | Detail satu record | Dari rekap bisa turun sampai satu baris log sheet — jejaknya utuh |
| 09 | `web-09-struktur-mills.png` | Hierarki Corporate → Company → Mill → Production Line | **Multi-pabrik sejak awal.** Satu grup dengan banyak PT dan banyak mill dikelola di satu halaman |
| 10 | `web-10-kelola-periode-pelaporan.png` | Kelola Periode Pelaporan | Periode bisa ditutup; periode tertutup mengunci input — disiplin pelaporan ditegakkan sistem |
| 11 | `web-11-mills-setting.png` | Pengaturan per mill | Tiap mill bisa diatur sendiri: gambar beranda, kirim-langsung, ikon stasiun |
| 12 | `web-12-kelola-user-role.png` | Kelola User & Role | Empat peran, dan Mill Management hanya melihat mill-nya sendiri |
| 13 | `web-13-production-process-activity.png` | Entri data stasiun lewat web | Supervisor bisa menginput dari web, tidak harus lewat ponsel |
| 14 | `web-14-asisten-ai.png` | Asisten AI di dalam aplikasi | Pembeda yang langsung terlihat saat demo |

### Potongan siap-slide dari Laporan Weighbridge

Halaman penuhnya 11.792px — bagus sebagai rujukan, tidak terpakai di slide.
Sepuluh bagiannya dipotret tersendiri dengan rasio yang wajar untuk satu slide:

| Berkas | Isi | Dipakai untuk slide tentang |
|---|---|---|
| `web-04x-laporan-bagian-01.png` | Kepala laporan: periode, mill, line, status TERTUTUP | Konteks: angka ini milik periode dan line yang mana |
| `web-04x-laporan-bagian-02.png` | Baris filter: Mill · Production Line · Periode · Ekspor | Satu laporan, banyak potongan |
| `web-04x-laporan-bagian-03.png` | **KPI arus masuk**: 1.418 trip · 27.700.337 kg · 19.534,79 kg/trip | **Slide angka utama.** Tiap kartu menjelaskan penyebutnya sendiri |
| `web-04x-laporan-bagian-04.png` | KPI arus keluar: 204 trip · 5.606.107 kg | Masuk dan keluar tidak pernah dijumlahkan — ketelitian yang terlihat |
| `web-04x-laporan-bagian-05.png` | Kelengkapan Pencatatan: 30 dari 30 hari, 100% | **Slide kepatuhan.** Laporan ikut menilai kelengkapan datanya sendiri |
| `web-04x-laporan-bagian-06.png` | Yang Belum Selesai & Yang Dikecualikan | Yang tidak dihitung dinyatakan terbuka, bukan disembunyikan |
| `web-04x-laporan-bagian-07.png` | Jam tersibuk / jam tanpa trip | Wawasan operasional, bukan sekadar total |
| `web-04x-laporan-bagian-08.png` | **Grafik sebaran trip per jam** | Slide visual terbaik setelah KPI |
| `web-04x-laporan-bagian-09.png` | Rekap per estate/supplier | Siapa pemasok terbesar — angka yang dicari manajemen |
| `web-04x-laporan-bagian-10.png` | Rekap per tujuan pengiriman | Ke mana CPO/kernel dikirim |

---

## MOBILE — untuk Operator di lapangan

Semua bidikan seukuran layar ponsel (390×844), siap ditempel ke mockup.

| # | Berkas | Yang ditunjukkan | Nilai jualnya |
|---|---|---|---|
| 01 | `mob-01-login.png` | Masuk | Akun yang sama dengan web, hak akses mengikuti peran |
| 02 | `mob-02-home.png` | Beranda: salam, nama mill, 3 kartu menu | Operator tahu sedang mengisi mill yang mana — data terpisah per mill |
| 03 | `mob-03-station-list.png` | **18 tile stasiun + pemilih Production Line** | Gambar mobile terkuat. Satu aplikasi untuk seluruh stasiun, dan line dipilih sebagai konteks kerja |
| 04 | `mob-04-monitor-depricarping.png` | Monitor satu stasiun: daftar draft | Pekerjaan yang belum selesai terlihat, tidak hilang |
| 05 | `mob-05-form-depricarping.png` | Form entri | Pengganti log sheet kertas, di tempat kerjanya |
| 06 | `mob-06-data-preview-depricarping.png` | Pratinjau data tersimpan | Operator bisa memeriksa yang sudah ia isi |
| 07 | `mob-07-dashboard-reporting.png` | Laporan versi mobile | Laporan ikut terbaca di ponsel, bukan hanya di web |

---

## Alur deck yang saya sarankan

1. **Masalah** — log sheet kertas: rekap lambat, sulit ditelusuri, tidak ada
   kendali kelengkapan. *(tanpa gambar)*
2. **Satu aplikasi untuk seluruh pabrik** — `mob-03` (18 stasiun) +
   `web-03` (18 jenis laporan). Pesannya: cakupan, bukan satu-dua stasiun.
3. **Operator mengisi di lapangan** — `mob-02`, `mob-04`, `mob-05`.
   Tekankan **offline-first**: aplikasi menyimpan ke basis data di perangkat
   dan menyinkronkan kemudian, jadi sinyal buruk tidak menghentikan pencatatan.
4. **Angka langsung jadi** — `web-04x-bagian-03` (KPI) lalu
   `web-04x-bagian-08` (grafik per jam). Inilah lompatan dari kertas.
5. **Bisa dipercaya** — `web-04x-bagian-05` (kelengkapan 100%) dan
   `bagian-06` (yang dikecualikan dinyatakan). Laporan yang jujur tentang
   batas dirinya lebih mudah dipercaya daripada yang hanya menampilkan total.
6. **Disiplin & kendali** — `web-10` (periode ditutup → input terkunci) dan
   `web-12` (peran).
7. **Skala grup** — `web-09` (Corporate → Company → Mill → Line).
8. **Data tetap milik Anda** — `web-07` (Ekspor CSV/Excel).
9. **Nilai tambah** — `web-14` (asisten AI).

## Tiga klaim yang aman disebut, karena sudah diverifikasi

- **18 jenis stasiun** seluruhnya terimplementasi di mobile dan web.
- **154 layar** sudah terimplementasi dari 157 yang dirancang.
- **Putaran uji terakhir: 255 dari 257 jalan Lulus, 0 Gagal.** Dua sisanya
  menuntut penguji manusia (mengubah jam perangkat, dan data melebihi 50.000
  baris), bukan cacat. Berkasnya ada di `docs/qa/`.

Pemisahan akses juga sudah terbukti saat tangkapan ini dibuat: Mill Management
dan Supervisor benar-benar menerima **403** pada halaman yang bukan haknya —
itu bukan janji, itu perilaku yang terlihat.
