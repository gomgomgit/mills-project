# Derived Assumptions Log — module-mobile-station-ops.screen-010--form-weighbridge.4-implement

## v1 — 2026-08-18

- Clear/Pause sengaja tidak diikutsertakan di FormWeighbridgeView.vue — business_logic screen ini hanya menyebutkan Simpan & Back; Clear/Pause tetap milik Monitor Weighbridge (screen-007) yang beroperasi terhadap repo yang sama
- FormField.vue dibuat generik (label/required/error/modelValue) untuk dipakai ulang di screen-011/012
- Enforcement role checked_by diterapkan di 2 lapis: UI (v-if hide total) dan weighbridgeRecordRepo.saveDraft() (strip/null independen dari payload)
- arrival_datetime/dispatch_datetime pakai input type=datetime-local, kolom SQLite tetap TEXT
- Router file tidak punya marker ASDLC_ROUTES_START/END (beda dari backend), mengikuti konvensi satu route object per screen yang sudah ada di file mobile

## v3 — 2026-08-18

- pauseDraftWithFormData() dibuat sebagai fungsi baru terpisah, bukan modifikasi pauseDraft(recordId) ← guard WHERE status='draft_ongoing' pada pauseDraft() lama load-bearing untuk pemanggil lain, tidak boleh diubah signature/perilakunya
- checked_by/acknowledged_by tetap dikirim string kosong ke saveDraft()/pauseDraftWithFormData() (bukan dihapus dari WeighbridgeFormData type) ← kolom masih dipakai DataPreviewWeighbridgeView.vue, mengubah tipe akan cascading ke file lain yang di luar scope
- isDirty hanya bandingkan 9 field editable (bukan seluruh form) ← agar live-ticking Dispatch tidak membuat form selalu "dirty" secara palsu
- Fix cross-screen: tests/e2e/data-preview-weighbridge.spec.ts (selector Monitor lama) ← rusak akibat rename tombol di screen-007 v3, diperbaiki sekalian karena terkait langsung dengan alur masuk ke screen ini

## v4 — 2026-08-18

- dispatchDateDisplay reuse formatDateID() yang sama dengan arrivalDateDisplay ← tidak perlu fungsi baru, dispatch_datetime sudah format ISO yang sama
- REQUIRED_FIELD_LABELS.gross_weight tidak diberi '(kg)' ← itu teks pesan error validasi, bukan label field yang terlihat user, jadi tidak termasuk instruksi "label weight"

## v5 — 2026-08-19

- DIRTY_CHECK_FIELDS menambahkan destination sebagai field dirty-check, sementara weighbridge_type/record_datetime tetap dikecualikan ← tech spec tidak eksplisit menyebut field mana yang masuk dirty-check; agent mengikuti pola lama (field auto/derived dikecualikan, field yang diketik user dimasukkan)
- buildPayload() memaksa destination='' saat type=receive sebagai jaminan defensif tambahan (bukan hanya UI hide) ← konsisten dengan pola enforcement checked_by dua-lapis (UI + payload) yang sudah ada di screen ini sejak v1, tidak dinyatakan eksplisit di tech spec v6
