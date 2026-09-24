# Derived Assumptions — module-dashboard.screen-135--laporan-sterilizer-mobile.4-implement

## v1 — 2026-09-24 (ditulis susulan)

Artefak ini ditulis sehari setelah implementasinya selesai. Yang dicatat di sini adalah pilihan yang tidak ditentukan spec, ditambah dua hal yang hanya terlihat justru karena penulisannya tertunda.

- **`status` = `partial`, bukan `complete`** ← seluruh test yang ada hijau (39 component, 18 browser, 58 backend), tetapi lapisan repo `sterilizerReportRepo.ts` tidak punya unit test sama sekali. Kembarannya [[module-dashboard.screen-136--laporan-cages-track-mobile.4-implement]] punya 21 kasus yang mengunci hal-hal yang tidak dijaga component test. Menyebutnya `complete` akan menyembunyikan bahwa aturan "tidak ada perhitungan kedua di sisi ponsel" tidak terjaga test apa pun di layar ini.
- **Angka test diukur ulang pada 2026-09-24, bukan direkonstruksi** ← artefak susulan paling mudah diisi dengan angka dari ingatan atau dari laporan agen lama. Ketiga suite dijalankan ulang untuk mendapatkan angkanya.
- **Kekeliruan briefing saya dicatat di `implementation_notes`, bukan dihilangkan** ← saya memberi tahu agen bahwa `resolveBusinessUnit()` sudah menangani seluruh peran non-Admin; itu keliru dan memakan satu putaran agen penuh. Pelajaran itu yang membuat saya membaca fungsi yang sama sebelum membriefing screen-136, dan di sanalah ditemukan bahwa melebarkan `guardAccess()` saja menjatuhkan Operator ke cabang Admin. Menghapus catatan itu akan menghapus sebab dari akibatnya.
- **`files_generated` menyertakan berkas milik screen-129** (`routes/api.php`, `SterilizerReportService.php`) ← perluasan akses Operator memang MENGUBAH layar yang sudah jadi, bukan hanya menambah. Mencatatnya di sini supaya jejaknya tidak hilang saat orang menelusuri kenapa screen-129 berubah.
- **Tidak ada endpoint baru dan tidak ada tambahan api-index** ← keputusan user 2026-09-23. Dicatat karena artefak Phase 4 tanpa `files_generated` di sisi API mudah disalahbaca sebagai pekerjaan yang belum selesai.
- **Dep-graph tidak dapat dipakai sendirian untuk menjawab "apa yang sudah jadi"** ← selama sehari ia menyatakan `not_started` sementara kodenya sudah berjalan. Dicatat sebagai `known_issue` tingkat rendah karena itu sifat prosesnya, bukan cacat satu layar.
