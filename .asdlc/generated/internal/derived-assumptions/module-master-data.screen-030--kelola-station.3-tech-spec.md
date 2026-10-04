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
