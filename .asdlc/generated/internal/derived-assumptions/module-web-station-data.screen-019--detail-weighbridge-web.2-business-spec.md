## v1 — 2026-08-20

- actors = actor-supervisor, actor-mill-management, actor-admin ← copied verbatim from screen-016's actors (this screen's only entry point), not independently derived
- Field grouping (Identitas, Kendaraan & Supir, Asal Muatan, Data Timbangan, Verifikasi) ← mirrors Form Weighbridge's section structure per uiux-spec `detail` screen_type_pattern's "dikelompokkan sesuai struktur form aslinya"
- No edit/export actions on this screen — pure read-only, correction routed to screen-022 (not yet implemented) ← inferred from uiux-spec `detail` pattern description, not explicitly stated by user

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (backend/app/Livewire/Data/DetailWeighbridge.php, backend/resources/views/livewire/data/detail-weighbridge.blade.php, backend/app/Livewire/Data/Concerns/GuardsRecordIdShape.php, backend/app/Support/Display.php).
- information_displayed[5] = status berlabel Indonesia (Tersimpan/Tersinkron/Draft/Dijeda), bukan enum mentah ← blade kini memanggil Display::status(); STATUS_LABELS di Display.php
- business_rules += aturan tampilan Bahasa Indonesia (status label, bulan Indonesia WIB, angka id-ID, kosong '-') ← blade memakai Display::date()/dateTime()/value()/status(); Display::date() setTimezone(AppTime::zone())->locale('id')
- edge_cases += {id} bukan UUID diperlakukan sama dengan record tidak ditemukan ← mount() `if (! $this->isRecordIdShapeValid($id)) { $this->notFound = true; return; }`
