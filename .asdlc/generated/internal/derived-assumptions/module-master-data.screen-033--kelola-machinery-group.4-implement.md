# Derived Assumptions Log — module-master-data.screen-033--kelola-machinery-group.4-implement

## v2 — 2026-10-03

Sinkronisasi artefak 4-implement dengan kode (kode tidak diubah).
- status dibiarkan 'complete' karena skema hanya mengenal complete/partial/wip dan tidak punya nilai untuk 'diserap'; status penyerapan dijelaskan di implementation_notes.
- File pengganti layar gabungan (KelolaMachinery.php, kelola-machinery.blade.php, machinery.blade.php, MachineryController.php untuk machinery-groups/options) ditambahkan ke daftar file; tiga file yang sudah dihapus dibiarkan dan dicatat sebagai known_issue minor.
- test_results memakai file test Machinery Group: unit 32 (MachineryGroupServiceTest), integration 26 (Api/KelolaMachineryGroupTest), component 20 (Livewire/KelolaMachineryGroupTest, kini menguji KelolaMachinery).
- Redirect 301 dinyatakan terverifikasi lewat `php artisan route:list` dan pembacaan routes/web.php, bukan lewat test backend (belum ada test seperti itu; dicatat sebagai known_issue).

## v3 — 2026-10-03

Pembersihan teks spec usang / sinkronisasi dengan perbaikan 2026-10-03.
- Entri file yang tidak ada lagi (3 file Livewire/blade KelolaMachineryGroup dihapus di 3312973, backend/tests/Browser/KelolaMachineryGroupTest.php tidak pernah ada — direktori backend/tests/Browser tidak ada) dihapus dari daftar setelah diverifikasi dengan ls; dua known_issues yang mencatat entri mati itu ikut dihapus.
- Known issue redirect 301 Admin tanpa test backend DIPERTAHANKAN.

## v4 — 2026-10-03

Pembersihan teks spec usang / sinkronisasi dengan perbaikan 2026-10-03.
- Known issue redirect 301 Admin tanpa test backend dihapus: test 'Admin: rute lama /master-data/machinery-groups mengalihkan 301 ke Kelola Mesin' terverifikasi ada di backend/tests/Feature/Livewire/KelolaMachineryGroupTest.php (baris 337); file test itu sudah ada di test_files_generated sehingga tidak ditambah ulang.

## v5 — 2026-10-03

Hasil run penuh e2e-web 2026-10-03 (530 lulus, 0 gagal, 9 skip).
- test_results.browser = 11 lulus/0 gagal dari e2e-full.log.counts.json (spec kelola-machinery-group).
- Known_issue 'Browser test dibuat tapi tidak dijalankan' dihapus; path spec sudah ada di test_files_generated.
