# Derived Assumptions Log — module-web-station-data.screen-018--data-browser-cages-track-web.2-business-spec

## v1 — 2026-08-18

- description/information_displayed/available_actions/business_rules/edge_cases = seluruh isi diturunkan meniru pola screen-016/017 (Data Browser Weighbridge/Grading Web, trio yang sama) dengan terminologi Cages Track (Cages Track ID, jumlah cage/lori tercatat) diambil dari entity-catalog `cages-track-record` ← tidak dinyatakan user, autopilot: diturunkan dari precedent 2 screen sejenis + entity-catalog
- test_priority = "medium" ← disamakan dengan screen-016/017 (trio sejenis, kompleksitas dan sensitivitas data setara)

## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (data-browser-cages-track.blade.php, app/Support/Display.php, app/Services/CagesTrackRecordService.php::export(), app/Support/SheetWriter.php, app/Support/ExportValue.php).
- information_displayed[0] = status berlabel Draft/Dijeda/Tersimpan/Tersinkron ← blade memakai Display::status()
- business_rules += label status Indonesia ← Display::STATUS_LABELS
- business_rules += Ekspor Excel .xlsx sungguhan; status Indonesia, tippler start/stop tanpa detik, jam tipping 'HH:00', sel kosong tetap kosong ← export() via SheetWriter + ExportValue::dateTime/status + sprintf('%02d:00')
