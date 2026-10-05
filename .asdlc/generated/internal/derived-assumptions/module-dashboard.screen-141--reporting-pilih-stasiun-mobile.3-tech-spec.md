# Derived Assumptions — module-dashboard.screen-141--reporting-pilih-stasiun-mobile.3-tech-spec

## v1 — 2026-09-23

- `route` = `/reports`, name `report-stations`, `meta.public = false` ← pola rute mobile yang ada. Perhatikan: web memakai `/reports` untuk layar yang setara (screen-140), jadi jalurnya kebetulan sama meski aplikasinya berbeda. Itu kebetulan yang enak, bukan kaitan.
- `endpoints` = `[]`, seluruh `api_test` = `[]` ← preseden screen-006 dan screen-134. **Nol perubahan backend, nol tambahan api-index.**
- **`REPORT_ROUTES` sebagai satu konstanta lokal, terpisah sepenuhnya dari `isActive`** ← ini keputusan terpenting di layar ini, dan satu-satunya yang punya jebakan tersembunyi. Hari ini Sterilizer kebetulan `isActive = true` **dan** punya laporan, sehingga implementasi yang keliru menyambungkan keduanya akan tetap hijau di seluruh test. Cacatnya baru muncul pada stasiun yang aktif di mill tetapi laporannya belum dibuat — yaitu 17 dari 18 stasiun, sekarang juga. Karena itu ada dua unit test khusus: `isActive=true` tanpa entri di peta harus nonaktif, dan `isActive=false` dengan entri di peta tetap dihitung tersedia.
- **Pemilihan sumber: `getActiveAndPlaceholderStationsForProductionLine` bila ada production line aktif, `getActiveAndPlaceholderStations` bila tidak** ← mengikuti perilaku `StationListView.vue` yang sudah ada, termasuk penukar Production Line yang ditambahkan pada sesi ini. Kalau layar ini memakai jalur yang berbeda, daftar stasiunnya bisa berbeda dari yang pengguna lihat di Daftar Stasiun — persis yang ingin dihindari.
- **Tile nonaktif memakai gaya tile aktif yang diredupkan, bukan gaya placeholder abu-abu milik `StationGrid`** ← keduanya "nonaktif" secara visual tetapi maknanya berbeda. Placeholder abu-abu berarti stasiunnya tidak ada; redup di sini berarti laporannya belum dibangun. Memakai gaya yang sama akan membuat dua keadaan berbeda tampak identik.
- **Pesan pada satu `ref` tunggal** ← membuat aturan "pesan tidak menumpuk" benar secara konstruksi, sama seperti screen-134.
- **Run ini mengubah screen-134** (mengisi `routeName` kartu Reporting), sehingga dua berkas test screen-134 yang mengasersi **kedua** kartu nonaktif akan perlu penyesuaian. Ditulis eksplisit di implementation_notes agar tidak jadi kejutan, dengan batas jelas: hanya bagian kartu Reporting yang berubah, asersi kartu Dashboard tidak boleh dilemahkan.
- `data-testid` (6 penanda) ← ditetapkan agar test punya pegangan stabil; sengaja tidak menambah penanda baru di luar daftar itu.
- Satu entri `test_scenarios` dari agent sempat salah `usecase_id` (typo `usecase-141-...`); agent menandainya sendiri dan memberi koreksi — saya pakai versi yang benar.

## v2 — 2026-09-29

- `business_logic` REPORT_ROUTES ditulis ulang dari `{ 'sterilizer': 'report-sterilizer' }` menjadi lima jenis ← alasan sama dengan screen-140: langkah itu bersebelahan langsung dengan langkah `reportQuery()` yang saya tambahkan, dan konstanta yang tertulis satu entri membuat "membawa line ke SETIAP laporan" kehilangan artinya.
- Langkah "baca production line aktif" ditulis ulang untuk MENYEBUT nama kuncinya (`msl_production_line_{userId}`) beserta alasan localStorage-bukan-jaringan ← briefing menyebut kuncinya sebagai fakta; artefak sebelumnya hanya berbunyi "dari auth store / penyimpanan lokal", yang tidak cukup untuk menjaga ketiga layar tetap memakai kunci yang sama.
- `api_contracts[0].endpoints` dibiarkan `[]` ← layar ini wajib nol pemanggilan jaringan, termasuk untuk memperoleh line. Menambahkan endpoint daftar line di sini akan meruntuhkan aturan yang paling menentukan bagi layar ini.
- `test_scenarios` = hanya DUA skenario baru ← enam uji komponen dan dua uji browser yang ada semuanya membuktikan dua perilaku saja (line terbawa; tanpa ingatan query dibiarkan kosong). Memecahnya menjadi enam skenario akan membuat artefak tampak punya cakupan lebih luas daripada yang diuji.
- Kedua `browser_test` DIISI ← `mobile/tests/e2e/reporting-pilih-stasiun.spec.ts` benar-benar memuat keduanya ("menekan tile stasiun mendaratkan laporan yang SUDAH terisi Production Line-nya" dan "tanpa ingatan line, tile tetap berpindah").

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v2)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); layar ini bukan jalur tulis data stasiun sehingga tidak terdampak — penilaian agen, tidak dinyatakan user per layar

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (ReportingPilihStasiunView.vue, ReportingPilihStasiunView.spec.ts, e2e/reporting-pilih-stasiun.spec.ts).
- business_logic[3] = bootstrapStationsWhenEmpty (fetchCurrentProductionLines → fetchAndCacheStationsForProductionLine / seedDefaultStationsIfNeeded) lalu arahan bila tetap kosong ← kode
- business_logic[9] = pesan '…belum tersedia di aplikasi mobile.' ← kode
- business_logic[14] = nol jaringan hanya bila cache ada; line untuk query tetap dari perangkat ← kode
- edge_case_handling[0], [4] = cache kosong → bootstrap / offline → seed 18 bawaan ← kode + tes
- unit_test_cases[6] (nol jaringan bila cache berisi), [7] (arahan bila tetap kosong), [9]/[10]/[18] teks pesan, [17] nol apiClient bila cache berisi; += 2 kasus bootstrap online/offline ← tes baru
- business_rules_applied[1], screen_dependencies[2].reason, implementation_notes[0] = jaringan hanya untuk bootstrap ← kode
- implementation_notes += REVISI audit 2026-10-04
- test_scenarios[1]/[2] asersi teks pesan baru; test_scenarios[3] komponen+browser = bootstrap online / server di-abort → 18 stasiun bawaan tanpa pesan teknis ← e2e baru

## v4 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (mobile/tests/e2e/reporting-pilih-stasiun.spec.ts, mobile/src/utils/floatingSafeArea.ts).
- implementation_notes ← append ruang aman elemen mengambang.
- test_scenarios ← append '390x844 ruang aman elemen mengambang'.

## v5 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit ee5294c, d5da9cf), code is truth (App.vue, utils/floatingSafeArea.ts, tests/e2e/floating-safe-area.spec.ts, ReportingPilihStasiunView.vue).
- implementation_notes[18] ← dok bawah buram: tile tak tertutup di posisi gulir mana pun (bukan hanya ujung gulir); 76/62/0 px; LoadingState grid.
- test_scenarios[13] ← diperluas ke 390x844 & 360x740, posisi atas+bawah, dok buram, bubble dapat diketuk; component test App/floatingSafeArea.
