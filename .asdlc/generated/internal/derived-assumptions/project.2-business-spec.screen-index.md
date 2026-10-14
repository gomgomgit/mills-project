# Derived Assumptions Log — project.2-business-spec.screen-index

## v1 — 2026-08-14

- screens.module-auth = 4 screens: Login Web, Login Mobile, Ganti Password Web, Ganti Password Mobile ← screen breakdown proposed by agent, accepted without change
- screens.module-mobile-station-ops = 11 screens: Home, Station List, Monitor x3, Form x3, Data Preview x3 ← screen breakdown proposed by agent, accepted without change
- screens.module-web-station-data = 9 screens: Data Browser x3, Detail x3, Form x3 (web) ← screen breakdown proposed by agent, accepted without change
- screens.module-dashboard = 2 screens: Dashboard Web, Laporan Manajemen (mobile dashboard deferred to future phase) ← screen breakdown proposed by agent, accepted without change
- screens.module-master-data = 5 screens: Kelola Corporate/Company/Business Unit/Station/Machinery ← screen breakdown proposed by agent, accepted without change
- screens.module-user-management = 1 screen: Kelola User & Role ← screen breakdown proposed by agent, accepted without change

## v10 — 2026-09-22

- screens.128–139 = 12 layar baru untuk Full Cycle per Stasiun ← user stated the feature and the 5 stations; the split into 1 master + 5 web + 1 mobile container + 5 mobile screens is the agent's breakdown
- screens.135–139 = 5 layar laporan mobile TERPISAH per stasiun (bukan satu layar dengan pemilih stasiun) ← agent mirrored the existing per-station screen pattern (Monitor/Form/Data Preview are all per-station); user only said reports must exist on mobile
- screens.134--dashboard-reporting-mobile = layar wadah tersendiri ← user said mobile reports live "di menu dashboard & reporting"; modelling that menu as its own screen with a station list is the agent's choice
- screens.129–133 isi laporan per stasiun (siklus rebus, antrean lori, blowdown/sootblowing, produksi minyak murni, pergerakan stok) ← derived by the agent from each station's recorded columns; user never specified report contents

## v12 — 2026-09-27

- `screens[+] = screen-142--detail-periode-pelaporan` ← user menyatakan "periode bukan accordion tapi pindah page untuk melihat detailnya" dan memilih bentuk layar baru; penomoran 142 dan penamaan `detail-*` diturunkan dari konvensi repo (`screen-106--detail-storage-tank-web` dkk di `module-web-station-data`), bukan dari user.
- `screens[screen-142].module_id = module-master-data` ← tidak dinyatakan. Diletakkan sebaris dengan induknya screen-128. Catatan: ini layar `detail-*` PERTAMA di modul master-data — sembilan layar lain di sana semuanya CRUD satu-layar.
- `screens[screen-128].description` ditulis ulang ← tidak diminta user. Deskripsi lama sudah usang sejak commit 83a4065: ia masih menyebut cakupan "mill + jenis stasiun" dan menutup "periode", padahal cakupannya kini mill saja dan yang ditutup adalah jenis stasiun di dalam periode. Membiarkannya berarti screen-index berbohong tentang layar yang sudah berubah.

## v14 — 2026-10-01

- `screens[screen-144--laporan-weighbridge-mobile].description` menyebut Production Line wajib dipilih ← user hanya menyatakan versi mobile mengikuti "persis pola screen-135/136/137/138/139". Bahwa itu berarti pemilih Production Line juga hadir di mobile adalah turunan agent dari pola kelima laporan mobile yang sudah ada, bukan pernyataan user. Dasarnya kuat (mobile justru yang pertama memakai pemilih line — `StationListView.vue` mengingatnya per akun), tapi tetap turunan.
- Pilihan kata deskripsi kedua layar ← isi metriknya ditentukan user (jumlah trip, total/rata-rata berat bersih, rekap per estate/supplier dan per tujuan, lama kendaraan di pabrik, trip tanpa berat bersih). Perumusannya dalam bahasa bisnis — termasuk menyebut anomali trip tanpa berat bersih sebagai hal yang "harus terlihat, bukan tersembunyi di balik rata-rata" — adalah penekanan agent.
- NOL entri baru di actor-index dan module-index ← user melarang menyentuh keduanya. Kedua layar baru dimasukkan ke `module-dashboard`, sebaris dengan kesepuluh layar laporan yang sudah ada; tidak ada modul baru dibuat.

## v15 — 2026-10-05

- screens[screen-145--data-saya-web].module_id = "module-web-station-data" ← agen menempatkan di modul Data Stasiun (Web), bukan modul baru; tidak dinyatakan user
- screens[screen-145--data-saya-web].description = "detail record read-only dibuka di layar yang sama (bukan memakai 18 layar Detail yang ada)" ← keputusan agen: Operator tetap terkunci dari rute Detail/Data Browser (403); tidak dinyatakan user
- screens[screen-145--data-saya-web].description = "filter tanggal, stasiun, Production Line; status sinkron/verifikasi per record; dicapai dari Beranda + sidebar Operator" ← diturunkan agen, tidak dinyatakan user

## v16 — 2026-10-05
- screens[screen-145--data-saya-web].name/description = Operator & Supervisor; MM/Admin 403; Supervisor lewat sidebar ← USER (Checkpoint 3b); penyebutan "Supervisor lewat sidebar" diturunkan agen

## v16 — 2026-10-14

User berkata "lanjutkan laporan yang belum". Enam jenis stasiun tersisa belum masuk scope sama sekali, jadi scope-nya ditambahkan lebih dulu. Seluruh isi 12 entri baru adalah turunan agent dari pembacaan langsung migrasi masing-masing stasiun — user tidak menyebut satu kolom pun.

- **Keenam stasiun di-scope SEKALIGUS dalam satu append, bukan enam kali** ← keputusan agent, dan alasannya mekanis: setiap bump `screen-index` membuat `2-business-spec` SETIAP layar stale (seluruhnya bergantung padanya), jadi satu bump jauh lebih murah daripada enam. Implementasinya tetap satu stasiun per putaran.
- **Urutan 12 entri mengikuti `station_types.sort_order`**, bukan urutan abjad maupun urutan deklarasi enum: engine-room 120, process-water 130, solid-waste-disposal 150, kernel-dispatch 160, cpo-dispatch 170, process-quality-control 180. Web lalu mobile per stasiun. Ini konsisten dengan `REPORT_ROUTES` yang urutannya load-bearing.
- **Pembagian dua kelompok bentuk, diturunkan dari ada/tidaknya kolom `time_slot`** ← Engine Room, Process Water, dan Process Quality Control bergrid 24 slot waktu (pola laporan kondisi, sudah terbukti 9 kali); Solid Waste Disposal, Kernel Dispatch, dan CPO Dispatch adalah per-transaksi (pola Weighbridge screen-143). Diverifikasi juga terhadap himpunan SLOT11 di `docs/qa/build-xlsx.py` baris 256–258, yang menyebut ketiga stasiun 24-slot itu dan tidak menyebut ketiga yang per-transaksi.
- **Engine Room dinyatakan sebagai laporan terbesar sejauh ini: 23 kolom ukur numerik** ← dihitung dari migrasinya (6 turbin + 4×2 genset + 4 kelistrikan + 2 kompresor + 3 penunjang). Melampaui Effluent Plant yang 14 dan Kernel Plant yang 7.
- **DUA PASANGAN GENSET IDENTIK (diesel_gen_1 / diesel_gen_2) dinyatakan sebagai pola yang sama dengan ripple mill dan kernel silo di Kernel Plant** ← turunan agent dari kesamaan bentuk kolomnya; aturan "tidak pernah dirata-ratakan menjadi satu" dibawa dari sana karena alasannya identik (rata-rata gabungan menyembunyikan ketidakseimbangan beban).
- **Kedua kolom status Engine Room bernilai TIGA (run/standby/off), bukan dua** ← dibaca dari `enum(...)` di migrasi. Dicatat karena 'standby' bukan 'off': genset siaga berbeda dari genset mati, dan melipat keduanya akan melaporkan pembangkit mati yang tidak pernah terjadi.
- **TIGA jebakan skema Engine Room diangkat ke deskripsi scope, bukan dibiarkan muncul di Phase 3:** (1) `daily_energy_export_kwh` adalah angka KUMULATIF HARIAN yang disimpan per slot waktu, sehingga merata-ratakannya antar 24 slot tidak bermakna — ia menuntut perlakuan berbeda dari 22 metrik lain; (2) penamaan pasangan kompresor tidak konsisten (`air_compressor_1_pressure_bar` melawan `compressor_2_pressure_bar`), jadi label layar tidak boleh diturunkan dari nama kolom secara mekanis; (3) `fuel_tank_level` satu-satunya kolom numerik tanpa satuan di namanya — persen atau liter belum jelas. Ketiganya ditemukan dengan membaca migrasi, bukan dari spec mana pun.
- **Process Water: kedua kolom dosing bersatuan massa BERBEDA** (alum kg/jam, polimer g/jam) ← dari nama kolomnya; dicatat karena keduanya "dosing" dan akan berdampingan di layar, sehingga label satuan wajib ikut di setiap angka.
- **`shift`, `inspector_id`, `action_taken_status`, dan `qc_inspector_id` adalah STRING BEBAS, bukan enum** ← dibaca dari `string(...)` di migrasi. Konsekuensinya dinyatakan: ketiganya TIDAK dapat diterbitkan sebagai sebaran kategoris seperti kolom status Effluent Plant, dan memaksanya menjadi sebaran akan melahirkan baris sebanyak variasi ejaannya.
- **Aturan "tidak menyatakan memenuhi spesifikasi" diperluas ke TIGA stasiun baru** ← Kernel Dispatch, CPO Dispatch, dan Process Quality Control semuanya menerbitkan parameter mutu yang batasnya ditetapkan spesifikasi pembeli DI LUAR aplikasi, persis seperti parameter kepatuhan lingkungan di Effluent Plant. Turunan agent; tidak ada master target untuk ketiganya.
- **DOBI dinyatakan sebagai indeks TANPA satuan, bukan persentase** ← `final_storage_dobi_index` dan `dobi` tidak berakhiran `_percent` sementara tetangganya iya. Dicatat karena menampilkannya dengan tanda persen adalah kesalahan yang akan terlihat benar.
- **CPO Dispatch punya `time_in` dan `time_out`, sehingga turnaround tanker dapat dihitung** ← satu-satunya laporan pengiriman yang bisa; metrik sejenis pernah diturunkan dari `arrival_datetime`→`dispatch_datetime` di Weighbridge.
- **Solid Waste Disposal punya EMPAT sumbu pengelompokan** (jenis limbah, stasiun asal, lokasi pembuangan, tujuan penggunaan akhir) melawan dua di Weighbridge ← dari kolomnya. Dan `purpose_end_use` dinyatakan sebagai pembeda yang menentukan: limbah yang DIBUANG versus DIMANFAATKAN kembali, yang kalau dijumlahkan menghapus justru informasi yang paling dicari.
- **Nomor gate pass dan segel diterbitkan sebagai penghitung kelengkapan, bukan dirata-rata** ← keduanya kolom string jejak kepatuhan. Turunan agent, sejajar dengan perlakuan kolom teks bebas di laporan lain.
