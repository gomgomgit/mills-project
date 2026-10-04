# Derived Assumptions Log — module-master-data.screen-036--kelola-production-line.3-tech-spec

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v4)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); layar ini bukan jalur tulis data stasiun sehingga tidak terdampak — penilaian agen, tidak dinyatakan user per layar

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (ProductionLineService.php, StationService::RECORD_TABLES, KelolaProductionLine.php, tests Api/Livewire KelolaProductionLineTest diff, MasterDataDeleteGuardTest).
- api_contracts[0].endpoints[2..3].request.body_schema.code = unik case-insensitive ← validate().
- api_contracts[0].endpoints[4].description + error_codes[1].condition = hapus line+station kosong; 409 bila record/MG/Machinery ← delete().
- api_contracts[0].business_logic[1], [3] = create case-insensitive; alur delete transaksional ← kode.
- api_contracts[0].data_operations[3], [5]; edge_case_handling[0..1]; business_rules_applied[2], [5] = sesuai delete() baru.
- api_contracts[0].unit_test_cases[7..8] (diubah) + (1 baru) = line+station kosong terhapus; ditolak bila data menempel; kode beda huruf ← MasterDataDeleteGuardTest/MasterDataValidationAuditTest ⚠ ditulis sebagai kasus unit walau tesnya Feature/Livewire.
- test_scenarios[2..3].component_test (+ scenario_ref [3]) = skenario hapus berhasil/ditolak baru ← tes Livewire yang diubah.
- implementation_notes (+) = REVISI audit-fix (juga menandai catatan 'belum ada Browser test' usang).
