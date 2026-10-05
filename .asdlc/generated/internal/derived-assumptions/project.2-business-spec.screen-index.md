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
