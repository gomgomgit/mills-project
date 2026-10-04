# Derived Assumptions Log — module-dashboard.screen-026--laporan-manajemen.3-tech-spec

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v1)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); layar ini bukan jalur tulis data stasiun sehingga tidak terdampak — penilaian agen, tidak dinyatakan user per layar

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (ManagementReportService.php, ManagementReportController.php, Livewire ManagementReport.php, management-report.blade.php, ApiExceptionHandler.php).
- endpoints[0,1].request.query_params += production_line_id (uuid, required) ← controller resolveProductionLine()
- endpoints[0].response.success_schema.rows[].weighbridge / total.weighbridge = {receive:{count,total_net_weight}, dispatch:{…}} ← weighbridgeSummary(), emptyWeighbridge()
- endpoints[0,1].error_codes += 422 VALIDATION_ERROR production_line_id (kosong / tidak valid) ← ValidationException::withMessages → ApiExceptionHandler code VALIDATION_ERROR
- endpoints[1].description = CSV / .xlsx sungguhan via SheetWriter, kolom Production Line + EXPORT_HEADER Indonesia, baris 'Total' ← export()
- business_logic = 7 langkah (resolve line Str::isUuid→mill; split receive/dispatch; scope production_line_id record; ekspor) ← service
- data_operations[0].description = split receive/dispatch + scope line ← weighbridgeSummary()
- edge_case_handling += API line tidak valid 422; web line belum dipilih/asing → hint; web rentang invalid → hanya errorMessage ← controller + Livewire render()
- unit_test_cases += split WB; filter line + tolak line asing; API 422 line ← ManagementReportServiceTest / Api ManagementReportTest (baru)
- implementation_notes[2] = SheetWriter xlsx sungguhan (bukan fallback CSV); += catatan revisi audit ← SheetWriter
- test_scenarios[0..4].api_test request_example += production_line_id placeholder ⚠ (placeholder "<uuid line mill acting user>" adalah inferensi, nilai konkret tergantung fixture)
- test_scenarios[0].component_test.action = pilih line dulu; test_scenarios[3].component_test.assert = hanya pesan galat, tanpa tabel/Total/ekspor ← render()
- test_scenarios += skenario "Production Line Belum/Salah Dipilih" (API 422 VALIDATION_ERROR, hint di layar) ← Livewire/Api ManagementReportTest baru
