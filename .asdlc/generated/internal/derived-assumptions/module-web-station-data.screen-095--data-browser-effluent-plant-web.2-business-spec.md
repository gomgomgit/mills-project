
## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (resources/views/livewire/data/data-browser-effluent-plant.blade.php, app/Support/Display.php, app/Services/EffluentPlantRecordService.php::export(), app/Support/SheetWriter.php, app/Support/ExportValue.php).
- information_displayed[0] = status berlabel Draft/Dijeda/Tersimpan/Tersinkron ← blade memakai Display::status()
- business_rules += label status Indonesia ← Display::STATUS_LABELS
- business_rules += Ekspor Excel .xlsx sungguhan; status Indonesia, Time Slot HH:MM tanpa detik (ExportValue::time/status); sel kosong tetap kosong ← export() via SheetWriter + ExportValue

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (backend/app/Support/Concerns/ScopesToActorMill.php, backend/tests/Feature/AuditFix20261005Test.php, e2e-web/tests/audit-fix-20261005.spec.ts, backend/app/Support/{Display,ExportValue}.php, backend/app/Services/*RecordService.php, resources/views/livewire/data/detail-*.blade.php).
- business_rules[5] ← aturan format ekspor ditambah: Kolom pilihan (Biogas Flare Status, Dosing Pump 1 Status, Sludge Dewatering Status) di berkas ekspor memakai label yang sama dengan layar Detail (On/Off/Fault, Run/Stop), bukan kode mentah. (perilaku terlihat user berubah, audit #5).
- ⚠ Daftar label Run/Stop untuk Dosing Pump 1 / Sludge Dewatering diambil dari <option> form-effluent-plant.blade.php (run/stop); Display::OPTION_LABELS juga memuat standby.
