# Derived Assumptions Log — module-web-station-data.screen-017--data-browser-grading-web.2-business-spec

## v1 — 2026-08-16

- business_rules = ["Data mencakup hasil sync mobile & input web", "Ekspor mengikuti filter aktif", "Daftar termuat <=2 detik untuk dataset normal"] ← proposed by agent (mirrored from Data Browser Weighbridge Web pattern), accepted without correction
- edge_cases = ["Tidak ada data sesuai filter", "Rentang tanggal tidak valid", "Ekspor gagal/dataset terlalu besar"] ← proposed by agent, accepted without correction

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (data-browser-grading.blade.php, app/Support/Display.php, app/Services/GradingRecordService.php::export(), app/Support/SheetWriter.php, app/Support/ExportValue.php).
- information_displayed[0] = status berlabel Draft/Dijeda/Tersimpan/Tersinkron ← blade memakai Display::status()
- business_rules += label status Indonesia ← Display::STATUS_LABELS
- business_rules += Ekspor Excel .xlsx sungguhan, status Indonesia, sel kosong tetap kosong, tanpa kolom Checked By ← export() via SheetWriter + ExportValue::status(); komentar kode "Grading memang tidak pernah mengumpulkannya"
