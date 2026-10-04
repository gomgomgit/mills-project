
## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (backend/app/Livewire/Data/DetailKernelDispatch.php, backend/resources/views/livewire/data/detail-kernel-dispatch.blade.php, backend/app/Livewire/Data/Concerns/GuardsRecordIdShape.php, backend/app/Support/Display.php).
- information_displayed[3] = status berlabel Indonesia (Tersimpan/Tersinkron/Draft/Dijeda), bukan enum mentah ← blade kini memanggil Display::status(); STATUS_LABELS di Display.php
- business_rules += aturan tampilan Bahasa Indonesia (status label, bulan Indonesia WIB, angka id-ID, kosong '-') ← blade memakai Display::date()/dateTime()/value()/status(); Display::date() setTimezone(AppTime::zone())->locale('id')
- business_rules += verifikasi ikut terkunci event_date setiap baris Log Kejadian ← RecordVerificationService::setVerification() memanggil assertDetailEventDatesWritable(..., 'verify') untuk relasi dari detailEventDateRelation()
- edge_cases += {id} bukan UUID diperlakukan sama dengan record tidak ditemukan ← mount() `if (! $this->isRecordIdShapeValid($id)) { $this->notFound = true; return; }`
