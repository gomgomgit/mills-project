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

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/DataPreviewGradingView.vue, services/recordVerificationApi.ts, services/apiClient.ts, components/SyncFailureHint.vue, services/syncService.ts, backend RecordVerificationStatusController/Service, routes/api.php).
- api_contracts[0].endpoints += GET /api/records/{stationType}/verification (stationType='grading'; auth:web,sanctum, role admin/supervisor/mill_management/operator, mill-scoped; 401/403/422/404) ← routes/api.php + RecordVerificationStatusController. ⚠ error_code 404 ditulis NOT_FOUND padahal controller mengembalikan {message} saja.
- api_contracts[0].business_logic += step 14 (pull verifikasi best-effort, reload getDraftWithDetails) & 15 (SyncFailureHint, termasuk penolakan di perangkat) ← pullVerificationStatus(), refreshVerificationInBackground(), syncService.localFailure().
- api_contracts[0].data_operations += UPDATE lokal kolom verifikasi ← pullVerificationStatus().
- api_contracts[0].edge_case_handling[3].handling = offline via flag `network: true` ← apiClient.normalizeError + isNetworkError().
- api_contracts[0].edge_case_handling += pull gagal senyap; record ditolak saat sync → hint ← recordVerificationApi.ts, syncService.failure()/localFailure().
- api_contracts[0].unit_test_cases += 4 (pull mirror/offline, 404 stop, SyncFailureHint, filter tanggal toLocalDateString) ← syncService.sqljs.spec.ts, SyncFailureHint.spec.ts, DataPreviewGradingView.vue. ⚠ kasus filter tanggal ISO-UTC diturunkan dari kode, belum tentu ada uji spesifiknya.
- implementation_notes += REVISI 2026-10-04.
- test_scenarios += 2 (Status Verifikasi Ditarik dari Server; Petunjuk Gagal Sinkron). ⚠ diturunkan dari kode/e2e, bukan bdd_scenarios Phase 2.

## v6 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit f79b1fe), code is truth.
- api_contracts GET /api/records/{stationType}/verification error_codes[404].condition: body kini amplop standar { message, code: NOT_FOUND } ← RecordVerificationStatusController abort(404) (temuan audit 2026-10-05 #11).

## v7 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit e4f231e, d5da9cf), code is truth (mobile/src/components/filters/*, mobile/src/components/loading/*, mobile/src/views/DataPreview*View.vue, mobile/src/components/RecordVerificationActions.vue).
- api_contracts[0].edge_case_handling[2].handling ← Reset Filter di panel ListFilterBar
- api_contracts[0].business_logic (append langkah 16) ← ListFilterBar: pintasan, tombol ×, ringkasan jumlah, Reset tunggal
- test_scenarios[5] component/browser assert ← Reset Filter tunggal di panel + ringkasan '0 dari N data'
- test_scenarios (extend 2) ← skenario panel filter dan indikator memuat (⚠ skenario non-BDD, dari perilaku kode)
- implementation_notes (append) ← ListFilterBar + LoadingState + tombol verifikasi sibuk
