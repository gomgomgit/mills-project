# Derived Assumptions Log — module-master-data.screen-030--kelola-station.3-tech-spec

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v1)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); layar ini bukan jalur tulis data stasiun sehingga tidak terdampak — penilaian agen, tidak dinyatakan user per layar

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (StationService.php, StationController::index(), KelolaStation.php, KelolaStationAuditTest.php).
- api_contracts[0].endpoints[0].description + response.success_schema.data[0] = + production_line_id/name, urut per line; filter line hanya Livewire ← listStations(), toRow(), StationController::index() (tidak meneruskan production_line_id).
- api_contracts[0].endpoints[2..3].request.body_schema + error_codes[0].condition = production_line_id wajib (milik BU), type ∈ station_types, satu-tipe-per-line, code case-insensitive ← validate().
- api_contracts[0].business_logic[0], [2], [3] = alur baru ← kode.
- api_contracts[0].edge_case_handling[0] + (3 baru) = beda huruf, tipe kembar, tipe nonaktif saat edit, empty state terfilter ← kode.
- api_contracts[0].business_rules_applied[0..1] + (1) = sesuai aturan baru.
- api_contracts[0].unit_test_cases[9] + (3 baru) ← KelolaStationAuditTest ⚠ dicatat sebagai kasus unit walau tesnya Feature/Livewire.
- implementation_notes (+) = REVISI audit-fix (#7, #8, #10, #11, #12, #15).

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (StationService.php, Api/StationController.php, ApiExceptionHandler.php, StationHasMachineryException.php).
- GET /api/stations query_params (+production_line_id), description, business_logic[0] ← filter line diekspos di API; nilai bukan UUID diabaikan (Str::isUuid).
- DELETE error_codes[0], description, business_logic[4], data_operations, edge_case_handling, business_rules_applied[2] ← guard 18 tabel RECORD_TABLES dalam transaksi + lockForUpdate.
- ⚠ error_code STATION_HAS_MACHINERY dipertahankan sebagai label kondisi, tetapi dicatat bahwa body aktual hanya { message } (exception tanpa HasErrorCode → ApiExceptionHandler::codeFor() mengembalikan null untuk 409); test_scenarios baru memakai expected_error_code null.
- unit_test_cases (+4), test_scenarios (+1), implementation_notes (+1) ← tes baru StationServiceTest/KelolaStationTest.

## v4 — 2026-10-05

Sumber: perbaikan lanjutan audit 2026-10-05 (belum di-commit), code is truth (StationHasMachineryException.php, KelolaStationTest API).
- endpoints[4] DELETE error_codes[0].condition: catatan 'body tanpa code' diganti — body kini { message, code: STATION_HAS_MACHINERY } untuk kedua kasus.
- edge_case_handling (record stasiun) menyebut STATION_HAS_MACHINERY; implementation_notes[terakhir] dikoreksi + (1) REVISI baru; test_scenarios (record stasiun) expected_error_code null → STATION_HAS_MACHINERY.
- ⚠ Kasus guard record stasiun memakai kode yang sama (STATION_HAS_MACHINERY), bukan kode baru — keputusan agen konservatif (satu kelas exception, spec error_codes[0] sudah menggabungkan kedua kasus).
