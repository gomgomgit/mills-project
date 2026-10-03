# Derived Assumptions Log — module-master-data.screen-031--kelola-machinery.4-implement

## v2 — 2026-10-03

Sinkronisasi artefak 4-implement dengan kode (kode tidak diubah).
- `name` diselaraskan ke 'Kelola Mesin' mengikuti nama di tech-spec v4.
- File, test, dan e2e milik Machinery Group (MachineryGroupService/Controller/Model/Exception, MachineryGroupServiceTest, Api/ & Livewire/KelolaMachineryGroupTest, e2e-web/tests/kelola-machinery*.spec.ts) dicatat juga di sini karena layar gabungan bergantung padanya; layout app.blade.php (sidebar 13→12) masuk fe_files_generated.
- test_results hanya menghitung file test Machinery (unit 34, integration 29, component 27); angka Machinery Group dicatat di artefak screen-033 supaya tidak terhitung dua kali.
- Perbaikan controller 2026-10-03 (ungrouped/search) dicatat sebagai bagian revisi ini walaupun dikerjakan koordinator pada sesi yang sama, sesuai instruksi.
- Entri tests/Browser/KelolaMachineryTest.php yang tidak ada dibiarkan dan dicatat sebagai known_issue minor, tidak dihapus.

## v3 — 2026-10-03

Pembersihan teks spec usang / sinkronisasi dengan perbaikan 2026-10-03.
- Entri backend/tests/Browser/KelolaMachineryTest.php (tidak ada) dihapus dari test_files_generated setelah diverifikasi dengan ls; known_issue yang mencatatnya ikut dihapus.
- Known issue redirect 301 Admin tanpa test backend DIPERTAHANKAN.

## v4 — 2026-10-03

Pembersihan teks spec usang / sinkronisasi dengan perbaikan 2026-10-03.
- Known issue redirect 301 Admin tanpa test backend dihapus; test baru di backend/tests/Feature/Livewire/KelolaMachineryGroupTest.php (sudah tercatat di test_files_generated).

## v5 — 2026-10-03

Hasil run penuh e2e-web 2026-10-03 (530 lulus, 0 gagal, 9 skip).
- test_results.browser = 8 lulus/0 gagal dari e2e-full.log.counts.json — hanya spec kelola-machinery, walau test_files_generated juga mencatat kelola-machinery-group.spec.ts (mengikuti pemetaan 1 spec → 1 layar; spec grup dicatat di screen-033).
- Known_issue 'Browser test dibuat tapi tidak dijalankan' dihapus.
