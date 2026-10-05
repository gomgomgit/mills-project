# Derived Assumptions Log — module-mobile-station-ops.screen-015--data-preview-cages-track.3-tech-spec

## v2 — 2026-08-19

- Full rewrite mirroring screen-013/014's business_logic/edge_case_handling/test structure ← structural mirroring per user instruction, field content per entity-catalog v3
- Search field: cages_track_number ← closest analog to grading_number/wb_card_number
- Detail mode reuses getDraftWithTippedTimes() (already implemented in screen-012) unmodified ← consistent with screen-014's reuse of getDraftWithDetails()
- Cages Tipped Time grid rendered read-only directly from stored columns (Time/checked_cage_numbers/total_cages/cages_remain), no recomputation ← historical data display, not an editable form
- Checked By/Acknowledged By shown plainly to any viewer regardless of role ← agent's logical extension of "read-only view" (viewing ≠ editing), not independently confirmed by user

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

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/DataPreviewCagesTrackView.vue, services/recordVerificationApi.ts, services/apiClient.ts, components/SyncFailureHint.vue, utils/localDate.ts, services/syncService.ts, backend RecordVerificationStatusController/Service, routes/api.php).
- api_contracts[0].endpoints += GET /api/records/{stationType}/verification (stationType='cages-track'; auth:web,sanctum, role 4 peran incl. operator, mill-scoped; 401/403/422/404) ← routes/api.php + controller. ⚠ error_code 404 ditulis NOT_FOUND padahal controller mengembalikan {message} saja.
- api_contracts[0].business_logic += 14 (pull verifikasi, reload getDraftWithTippedTimes), 15 (SyncFailureHint), 16 (Tanggal type=date via toDateInputValue; Tippler via toDateTimeLocalInputValue) ← view.
- api_contracts[0].data_operations += UPDATE lokal kolom verifikasi ← pullVerificationStatus().
- api_contracts[0].edge_case_handling[3].handling = offline via flag `network: true` ← apiClient + isNetworkError().
- api_contracts[0].edge_case_handling += pull gagal senyap; record ditolak saat sync → hint.
- api_contracts[0].unit_test_cases += 4 (pull, 404 stop, SyncFailureHint, Tanggal date-only) ← syncService.sqljs.spec.ts, SyncFailureHint.spec.ts, DataPreviewCagesTrackView.spec.ts ('#field-tanggal' = '2026-08-17').
- implementation_notes += REVISI 2026-10-04.
- test_scenarios += 2. ⚠ diturunkan dari kode/e2e, bukan bdd_scenarios Phase 2.

## v6 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit f79b1fe), code is truth.
- api_contracts GET /api/records/{stationType}/verification error_codes[404].condition: body kini amplop standar { message, code: NOT_FOUND } ← RecordVerificationStatusController abort(404) (temuan audit 2026-10-05 #11).

## v7 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit e4f231e, d5da9cf), code is truth (mobile/src/components/filters/*, mobile/src/components/loading/*, mobile/src/views/DataPreview*View.vue, mobile/src/components/RecordVerificationActions.vue).
- api_contracts[0].edge_case_handling[2].handling ← Reset Filter di panel ListFilterBar
- api_contracts[0].business_logic (append langkah 17) ← ListFilterBar: pintasan, tombol ×, ringkasan jumlah, Reset tunggal
- test_scenarios[5] component/browser assert ← Reset Filter tunggal di panel + ringkasan '0 dari N data'
- test_scenarios (extend 2) ← skenario panel filter dan indikator memuat (⚠ skenario non-BDD, dari perilaku kode)
- implementation_notes (append) ← ListFilterBar + LoadingState + tombol verifikasi sibuk
