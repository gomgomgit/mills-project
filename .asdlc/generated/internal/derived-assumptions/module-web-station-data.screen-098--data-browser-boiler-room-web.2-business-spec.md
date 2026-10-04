
## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (resources/views/livewire/data/data-browser-boiler-room.blade.php, app/Support/Display.php, app/Services/BoilerRoomRecordService.php::export(), app/Support/SheetWriter.php, app/Support/ExportValue.php).
- information_displayed[0] = status berlabel Draft/Dijeda/Tersimpan/Tersinkron ← blade memakai Display::status()
- business_rules += label status Indonesia ← Display::STATUS_LABELS
- business_rules += Ekspor Excel .xlsx sungguhan; status Indonesia, Time Slot HH:MM tanpa detik (ExportValue::time/status); sel kosong tetap kosong ← export() via SheetWriter + ExportValue
