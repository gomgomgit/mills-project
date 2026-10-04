# Derived Assumptions Log — module-web-station-data.screen-016--data-browser-weighbridge-web.2-business-spec

## v1 — 2026-08-16

- business_rules = ["Data mencakup hasil sync mobile & input web", "Ekspor mengikuti filter aktif", "Daftar termuat <=2 detik untuk dataset normal"] ← proposed by agent in draft, accepted without correction
- edge_cases = ["Tidak ada data sesuai filter", "Rentang tanggal tidak valid", "Ekspor gagal/dataset terlalu besar"] ← proposed by agent in draft, accepted without correction

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (resources/views/livewire/data/data-browser-weighbridge.blade.php, app/Support/Display.php, app/Services/WeighbridgeRecordService.php::export(), app/Support/SheetWriter.php).
- information_displayed[0] = net weight berformat id-ID, status berlabel Draft/Dijeda/Tersimpan/Tersinkron ← blade kini memakai Display::number($v, 2) dan Display::status()
- business_rules += label status Indonesia + angka id-ID di tabel ← Display::STATUS_LABELS dipakai blade
- business_rules += Ekspor Excel = .xlsx sungguhan; judul kolom = label Detail (+ Checked By/Acknowledged By), status Indonesia, waktu tanpa detik, sel kosong tetap kosong ← WeighbridgeRecordService::export() via SheetWriter; "sel kosong tetap kosong/angka tanpa pemisah ribuan" disimpulkan dari nilai mentah yang dikirim ke SheetWriter (⚠ inferensi, bukan konstanta eksplisit)
