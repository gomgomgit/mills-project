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

## v2 — 2026-09-29

- `business_logic[9]` DITULIS ULANG: "REPORT_ROUTES (saat ini hanya 'sterilizer')" → lima jenis (cages-track, sterilizer, clarification, boiler-room, storage-tank) ← tidak diminta briefing, tetapi langkah itulah yang saya sentuh untuk menambahkan `production_line_id` pada `report_path`, dan membiarkan separuhnya salah sambil memperbaiki separuh lainnya akan membuat baris itu lebih menyesatkan daripada sebelumnya. Sifat load-bearing urutannya ikut ditulis karena ada uji yang mengasersi `array_keys` secara berurutan.
- `stations[0].report_path` diubah dari `"<string|null>"` menjadi kalimat yang menyebut kedua kunci query DAN kewajiban nama `production_line_id` ← nilai skema adalah satu-satunya tempat yang pasti dibaca bersama medannya; catatan di `implementation_notes` saja terbukti tidak cukup untuk pasangan nama yang sama pada mill (commit 8658f6e).
- `production_line` disisipkan tepat setelah `business_unit` di dalam `data` ← layar ini memang membungkus responsnya dengan `data`, berbeda dari endpoint `/summary` laporan.
- `test_scenarios[*].browser_test` = KOSONG pada keempat skenario baru ← `e2e-web/tests/laporan-stasiun.spec.ts` tidak menyentuh Production Line sama sekali. Yang nyata hanya 3 uji API + 3 uji Livewire, dan ketiganya sudah tercermin di `unit_test_cases`.
- `data_operations` = ditambah SATU entri `production-line` (bukan dua seperti Data Browser) ← di layar ini opsi dan resolusi nama datang dari satu kueri pada tabel yang sama; tidak ada penjepitan terpisah seperti `clampProductionLineIdToMill()`.

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v2)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); layar ini bukan jalur tulis data stasiun sehingga tidak terdampak — penilaian agen, tidak dinyatakan user per layar

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (dashboard/partials/report-styles.blade.php, components/layouts/app.blade.php, Support/RouteAccess.php, tests/Feature/WebAccessTest.php).
- actor_permissions[3].conditions = Operator boleh login web terbatas (/beranda, /settings/password) tetapi tanpa entri sidebar 'Laporan Stasiun' (RouteAccess) dan /reports → 403 (errors/403) ← WebAccessTest '#2 login web Operator…', layout RouteAccess::allows('reports.stations')
- implementation_notes[8] = .station-grid auto-fill minmax(150px,1fr), ≤767px minmax(110px,1fr), bukan 3 kolom tetap ← report-styles diff (#10)
