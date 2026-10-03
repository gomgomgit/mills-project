# Derived Assumptions Log — module-mobile-station-ops.screen-014--data-preview-grading.3-tech-spec

## v2 — 2026-08-19

- Full rewrite mirroring screen-013--data-preview-weighbridge v3's business_logic/edge_case_handling/test structure ← structural mirroring per user instruction, field content per entity-catalog v2
- Search fields: grading_number / license_plate_no (not wb_card_number/driver_name) ← driver_name no longer exists on grading-record; license_plate_no is the closest vehicle-identifying field
- Detail mode resolves each grading_detail row's Quality Parameter display name via grading_parameter_id join at render time (read-only lookup, not stored) ← natural consequence of grading-detail only storing the FK + snapshot uom, not the parameter name itself
- GradingDetailGrid.vue (old component, built around the free-text `category` field) intentionally NOT reused for the read-only detail grid here, same reasoning as screen-011's inline grid ← consistent with that screen's precedent, not independently re-confirmed with user
- gradingRecordRepo.ts needs a new getAllRecords(userId) function mirroring weighbridgeRecordRepo.ts's — not yet written, to be added during implementation

## v3 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Endpoint PATCH /api/records/{stationType}/{id}/verification TIDAK ditambahkan ke `endpoints` karena list itu kosong (tak ada bentuk untuk disalin); didokumentasikan di business_logic + implementation_notes.
- Langkah verifikasi ditambahkan sebagai langkah bernomor baru di akhir business_logic (mengikuti penomoran yang ada), bukan disisipkan ke langkah Mode DETAIL.
- Edge case menggabungkan PERIOD_CLOSED dan tanpa jaringan dalam satu item; wording pesan offline disalin dari RecordVerificationActions.vue; pemulihan via screen-142 diambil dari brief.
- unit_test_case PERIOD_CLOSED ditulis sebagai kasus level komponen (RecordVerificationActions) dengan galat ternormalisasi apiClient {status:422, message} — perilaku 'baris lokal tidak berubah' disimpulkan dari urutan kode setVerification() (UPDATE lokal hanya setelah PATCH sukses).
- Catatan implementation_notes ditandai sebagai pengecualian atas catatan lama 'tidak ada panggilan API' (catatan lama tidak dihapus).

## v4 — 2026-10-03

Pembersihan teks spec usang / sinkronisasi dengan perbaikan 2026-10-03.
- implementation_notes[0] yang menyatakan "tidak ada panggilan API" diganti: data dibaca dari SQLite lokal; satu-satunya panggilan server adalah aksi verifikasi RecordVerificationActions.vue → recordVerificationApi.setVerification → PATCH /api/records/{stationType}/{server_id}/verification, hanya untuk record tersinkron (punya server id).
- Teks catatan record belum tersinkron dikutip langsung dari RecordVerificationActions.vue (data-testid verification-not-synced); frasa "kolom verifikasi baris lokal baru diperbarui setelah server menerima" diturunkan dari recordVerificationApi.setVerification (UPDATE lokal setelah respons PATCH).
- Catatan "Kunci periode … pengecualian atas 'tidak ada panggilan API'" dibiarkan apa adanya (masih akurat sebagai rujukan historis).
