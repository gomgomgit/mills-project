
## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (resources/views/livewire/data/data-browser-cpo-dispatch.blade.php, app/Support/Display.php, app/Services/CpoDispatchRecordService.php::export(), app/Support/SheetWriter.php, app/Support/ExportValue.php).
- information_displayed[0] = status berlabel Draft/Dijeda/Tersimpan/Tersinkron ← blade memakai Display::status()
- business_rules += label status Indonesia ← Display::STATUS_LABELS
- business_rules += Ekspor Excel .xlsx sungguhan; status Indonesia (ExportValue::status); sel kosong tetap kosong ← export() via SheetWriter + ExportValue

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (backend/app/Support/Concerns/ScopesToActorMill.php, backend/tests/Feature/AuditFix20261005Test.php, e2e-web/tests/audit-fix-20261005.spec.ts, backend/app/Support/{Display,ExportValue}.php, backend/app/Services/*RecordService.php, resources/views/livewire/data/detail-*.blade.php).
- business_rules[4] ← aturan format ekspor ditambah: Jam Time In / Time Out di berkas ekspor ditulis HH:MM (tanpa detik). (perilaku terlihat user berubah, audit #5).
