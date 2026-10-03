# Derived Assumptions Log — module-master-data.screen-031--kelola-machinery.4-implement

## v2 — 2026-10-03

Sinkronisasi artefak 4-implement dengan kode (kode tidak diubah).
- `name` diselaraskan ke 'Kelola Mesin' mengikuti nama di tech-spec v4.
- File, test, dan e2e milik Machinery Group (MachineryGroupService/Controller/Model/Exception, MachineryGroupServiceTest, Api/ & Livewire/KelolaMachineryGroupTest, e2e-web/tests/kelola-machinery*.spec.ts) dicatat juga di sini karena layar gabungan bergantung padanya; layout app.blade.php (sidebar 13→12) masuk fe_files_generated.
- test_results hanya menghitung file test Machinery (unit 34, integration 29, component 27); angka Machinery Group dicatat di artefak screen-033 supaya tidak terhitung dua kali.
- Perbaikan controller 2026-10-03 (ungrouped/search) dicatat sebagai bagian revisi ini walaupun dikerjakan koordinator pada sesi yang sama, sesuai instruksi.
- Entri tests/Browser/KelolaMachineryTest.php yang tidak ada dibiarkan dan dicatat sebagai known_issue minor, tidak dihapus.
