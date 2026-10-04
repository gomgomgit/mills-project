
## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (backend/app/Livewire/Data/DetailEngineRoom.php, backend/resources/views/livewire/data/detail-engine-room.blade.php, backend/app/Livewire/Data/Concerns/GuardsRecordIdShape.php, backend/app/Support/Display.php).
- business_rules += aturan tampilan Bahasa Indonesia (status label, bulan Indonesia WIB, angka id-ID, kosong '-') ← blade memakai Display::date()/dateTime()/value()/status(); Display::date() setTimezone(AppTime::zone())->locale('id')
- edge_cases += {id} bukan UUID diperlakukan sama dengan record tidak ditemukan ← mount() `if (! $this->isRecordIdShapeValid($id)) { $this->notFound = true; return; }`

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (backend/app/Support/Concerns/ScopesToActorMill.php, backend/tests/Feature/AuditFix20261005Test.php, e2e-web/tests/audit-fix-20261005.spec.ts, backend/app/Support/{Display,ExportValue}.php, backend/app/Services/*RecordService.php, resources/views/livewire/data/detail-*.blade.php).
- business_rules[3] ← kolom pilihan tampil berlabel (Diesel Gen 1 Status / Diesel Gen 2 Status (Run/Standby/Off)), bukan kode mentah — perilaku terlihat user berubah (audit #5).
- ⚠ Himpunan opsi Diesel Gen (run/standby/off) diambil dari komentar Display::OPTION_LABELS + form-engine-room.blade.php.
