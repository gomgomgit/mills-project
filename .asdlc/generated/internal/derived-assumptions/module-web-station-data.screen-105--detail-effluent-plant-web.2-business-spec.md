
## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (backend/app/Livewire/Data/DetailEffluentPlant.php, backend/resources/views/livewire/data/detail-effluent-plant.blade.php, backend/app/Livewire/Data/Concerns/GuardsRecordIdShape.php, backend/app/Support/Display.php).
- information_displayed[3] = status berlabel Indonesia (Tersimpan/Tersinkron/Draft/Dijeda), bukan enum mentah ← blade kini memanggil Display::status(); STATUS_LABELS di Display.php
- business_rules += aturan tampilan Bahasa Indonesia (status label, bulan Indonesia WIB, angka id-ID, kosong '-') ← blade memakai Display::date()/dateTime()/value()/status(); Display::date() setTimezone(AppTime::zone())->locale('id')
- edge_cases += {id} bukan UUID diperlakukan sama dengan record tidak ditemukan ← mount() `if (! $this->isRecordIdShapeValid($id)) { $this->notFound = true; return; }`

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (backend/app/Support/Concerns/ScopesToActorMill.php, backend/tests/Feature/AuditFix20261005Test.php, e2e-web/tests/audit-fix-20261005.spec.ts, backend/app/Support/{Display,ExportValue}.php, backend/app/Services/*RecordService.php, resources/views/livewire/data/detail-*.blade.php).
- business_rules[5] ← kolom pilihan tampil berlabel (Biogas Flare Status (On/Off/Fault), Dosing Pump 1 Status dan Sludge Dewatering Status (Run/Stop)), bukan kode mentah — perilaku terlihat user berubah (audit #5).
- ⚠ Daftar label Run/Stop untuk Dosing Pump 1 / Sludge Dewatering diambil dari <option> form-effluent-plant.blade.php (run/stop); Display::OPTION_LABELS juga memuat standby.
