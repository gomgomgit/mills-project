# Derived Assumptions — module-dashboard.screen-140--laporan-stasiun-web.3-tech-spec

## v1 — 2026-09-23

- `route` = `/reports`, name `reports.stations` ← konvensi repo `/reports/*` (lihat `reports.management`, `reports.sterilizer`). User tidak menyebut rute.
- 2 endpoint API (`/api/station-reports/business-units/options`, `/api/station-reports/stations`) ← layar ini bisa saja dibangun murni sebagai Livewire tanpa API sama sekali. Endpoint tetap dibuat agar screen-141 (pemilih stasiun di mobile) memakai sumber yang sama persis, sehingga daftar stasiun web dan mobile tidak mungkin berbeda.
- Cabang **Supervisor mengirim `business_unit_id` mill lain → 200 berisi mill sendiri, BUKAN 403** ← disalin dari perilaku `SterilizerReportService::resolveBusinessUnit()` yang sudah berjalan di screen-129. Alasannya: parameter itu tidak pernah dipakai untuk peran tersebut, jadi tidak ada upaya akses yang perlu ditolak. Ini mudah salah diimplementasikan sebagai otorisasi keras.
- Cabang **akun tanpa `business_unit_id` → 422 dengan pesan hubungi Admin, dan `businessUnitRepo->all()` tidak pernah dipanggil** ← gagal tertutup. Jatuh ke daftar seluruh mill akan membocorkan mill lain ke peran yang seharusnya terikat satu mill. Diuji secara eksplisit sebagai spy.
- `report_available` / `report_path` dari satu peta konstanta `REPORT_ROUTES` di service ← agar menambah laporan stasiun baru berarti menambah satu baris, bukan menyebar pengecekan ke blade. Tidak dinyatakan user.
- **Grid tidak disaring per mill** ← master Jenis Stasiun bersifat global, sehingga mill yang tidak punya stasiun tertentu tetap melihat tile-nya. Konsisten dengan aturan "tampilkan semua, nonaktifkan yang belum siap", tetapi artinya langkah pilih Mill tidak mengubah isi grid — ia hanya menetapkan cakupan untuk laporan berikutnya. **Diangkat sebagai pertanyaan terbuka di checkpoint.**
- Tile aktif memakai hijau merek `#249360`, bukan merah `#D20000` milik screen-035 ← layar ini masuk keluarga laporan bersama dashboard dan Laporan Sterilizer, bukan keluarga input data. Pilihan agent.
- Urutan tile mengikuti `station_types.sort_order` (urutan proses) ← sementara screen-035 memakai **urutan kustom** yang user minta 2026-09-01. Dua grid berisi 18 stasiun yang sama dengan urutan berbeda. **Diangkat sebagai pertanyaan terbuka di checkpoint.**
- Daftar `data-testid` (mill-select, mill-current, station-grid, station-tile-<code>, mill-required-hint, no-business-units, no-mill-for-account, no-station-types) ← ditetapkan agar component test dan browser test punya pegangan stabil; bukan permintaan user.
- Cabang `404 NOT_FOUND` tidak punya pasangan skenario BDD — hanya tercakup unit test. Disengaja, bukan celah.
- `shared_entities` = `[]` ← skema mendefinisikannya sebagai entitas yang dipakai lebih dari satu usecase; layar ini hanya punya satu usecase.
