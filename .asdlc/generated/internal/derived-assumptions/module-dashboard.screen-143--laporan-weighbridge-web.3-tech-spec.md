# Derived Assumptions — module-dashboard.screen-143--laporan-weighbridge-web.3-tech-spec

## v1 — 2026-10-01

Autonomy `autopilot`: kontrak API disintesis dari business spec v2 lalu diterima tanpa tinjauan
per-butir. 49 unit test dan 35 test scenario diturunkan `test-spec-writer-agent` dari kontrak itu.

- Bentuk respons `summary` (dua kelompok `receive`/`dispatch`, masing-masing dengan `trip_count`, `net_weight_total`, `net_weight_avg`, `net_weight_trip_count`, `missing_net_weight_trip_count`, 24 ember `hourly`, `busiest_hour`, `empty_hour_count`, plus `by_origin`/`by_destination`; ditambah `draft_trip_count`, `undated_trip_count`, `daily`, `daily_total`, `completeness`) ← seluruhnya rancangan agent. User menetapkan metrik apa yang dilaporkan, bukan bentuk payload-nya.

- Empat endpoint `/api/weighbridge-reports/*` dan rute web `/reports/weighbridge` ← diturunkan dari pola `/api/storage-tank-reports/*` dan `/reports/storage-tank` yang dibaca langsung dari `routes/api.php` dan `routes/web.php`. Bukan karangan, tetapi juga bukan permintaan user.

- **Middleware grup API SENGAJA TANPA `operator`** ← keputusan agent, dan BERBEDA dari kelima laporan yang sudah ada. Laporan lain menyertakan operator karena pasangan mobile-nya (screen-135/136/137/138/139) sudah dibangun; screen-144 belum. Memberi operator akses sekarang berarti melebarkan jangkauan sebelum ada layar yang memakainya. Pelebaran dilakukan saat screen-144 dikerjakan, sebagai langkah eksplisit yang dapat ditinjau — sama seperti yang dicatat untuk screen-135/136 di komentar `routes/api.php`.

- `period_id` dan `production_line_id` dijadikan WAJIB dengan 422 bila absen ← konsekuensi langsung business rule "Production Line WAJIB dipilih", tetapi memilih 422 (bukan 400, bukan diam-diam mengembalikan kosong) adalah pilihan agent, mengikuti `VALIDATION_ERROR` yang sudah jadi konvensi proyek.

- `undated_trip_count` sebagai field tersendiri pada level atas, bukan di dalam tiap arus ← pilihan agent. Trip tanpa penanda waktu tidak dapat ditempatkan pada arus mana pun secara bermakna karena ia juga tidak dapat ditempatkan pada periode; menaruhnya di level atas menegaskan ia berada di luar seluruh perhitungan.

- 19 butir `business_logic` dan 12 `edge_case_handling` ← rumusan agent dari business spec v2. Butir 7 (keanggotaan periode dari waktu kejadian, bukan `created_at`) diangkat dari aturan yang sudah tertulis eksplisit di `CagesTrackReportService::summary()` — ditemukan saat menyelidiki kolom tanggal mana yang mengikat trip ke periode, penyelidikan yang sekaligus membongkar kesalahan kolom di business spec v1.

- 49 `unit_test_cases` dan 35 `test_scenarios` ← diturunkan agent. `scenario_ref` diverifikasi cocok karakter-per-karakter DAN urutan dengan 35 `bdd_scenarios` usecase-146 v2; nol rujukan menggantung.

- **CATATAN PROSES, bukan asumsi:** tulisan pertama artefak ini mendarat dengan `test_scenarios: []` — 35 skenario hilang karena payload 102 KB terlalu besar untuk satu panggilan dan agent menuliskan array kosong. Tertangkap oleh diff terhadap oracle yang dibangun sebelum menulis, lalu diperbaiki dengan satu panggilan `artifact__patch`. Artefak final diverifikasi byte-identical dengan oracle.

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v2)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); layar ini bukan jalur tulis data stasiun sehingga tidak terdampak — penilaian agen, tidak dinyatakan user per layar

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (WeighbridgeReportService.php, laporan-weighbridge.blade.php, tests).
- api_contracts[0].endpoints[2].response.success_schema.completeness = + days_counted, period_running ← summary() + LaporanWeighbridgeTest kunci completeness
- api_contracts[0].business_logic[17] = persen hari bertrip pakai days_counted, 0 → '—' ← blade
- api_contracts[0].business_logic[18] = SheetWriter csv/xlsx, Status via ExportValue::status ← export() diff
- api_contracts[0].endpoints[3].description / request.query_params[2].description / response.success_schema = csv|excel (xlsx sungguhan), status berlabel ← controller docblock 'outside csv|excel' + fileMetaFor (spec lama 'Hanya csv' sudah usang sebelum audit — ⚠ dikoreksi sekalian)
- api_contracts[0].edge_case_handling[+2], unit_test_cases[+1] = periode berjalan / belum mulai ← ReportPeriodDays + tests
- implementation_notes[+1] = ChartAxis, klausa 'bukan', teks nol-kg & draft ← blade diff

## v4 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit db73fbd, c321f32), code is truth (components/report-filter-bar.blade.php, laporan-*.blade.php, e2e-web/tests/laporan-*.spec.ts).
- implementation_notes[+] ← report-filter-bar (props/testid diteruskan), format opsi periode, badge status, ekspor busy, .ld-region
