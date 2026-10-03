# Derived Assumptions Log — module-master-data.screen-033--kelola-machinery-group.4-implement

## v2 — 2026-10-03

Sinkronisasi artefak 4-implement dengan kode (kode tidak diubah).
- status dibiarkan 'complete' karena skema hanya mengenal complete/partial/wip dan tidak punya nilai untuk 'diserap'; status penyerapan dijelaskan di implementation_notes.
- File pengganti layar gabungan (KelolaMachinery.php, kelola-machinery.blade.php, machinery.blade.php, MachineryController.php untuk machinery-groups/options) ditambahkan ke daftar file; tiga file yang sudah dihapus dibiarkan dan dicatat sebagai known_issue minor.
- test_results memakai file test Machinery Group: unit 32 (MachineryGroupServiceTest), integration 26 (Api/KelolaMachineryGroupTest), component 20 (Livewire/KelolaMachineryGroupTest, kini menguji KelolaMachinery).
- Redirect 301 dinyatakan terverifikasi lewat `php artisan route:list` dan pembacaan routes/web.php, bukan lewat test backend (belum ada test seperti itu; dicatat sebagai known_issue).
