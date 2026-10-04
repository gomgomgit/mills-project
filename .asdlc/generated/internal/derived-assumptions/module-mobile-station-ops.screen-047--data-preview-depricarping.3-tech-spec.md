# Derived Assumptions Log — module-mobile-station-ops.screen-047--data-preview-depricarping.3-tech-spec

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

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/DataPreviewDepricarpingView.vue, services/recordVerificationApi.ts, services/apiClient.ts, components/SyncFailureHint.vue, services/syncService.ts, backend RecordVerificationStatusController/Service, routes/api.php).
- api_contracts[0].endpoints += GET /api/records/{stationType}/verification?ids[]= (stationType='depricarping'; auth:web,sanctum, role admin/supervisor/mill_management/operator, mill-scoped; 401/403/422/404) ← routes/api.php + RecordVerificationStatusController. ⚠ error_code 404 ditulis NOT_FOUND padahal controller mengembalikan {message} saja (inferensi label).
- api_contracts[0].business_logic += step 8/9 (pull verifikasi best-effort maks 100, timeout 5 dtk, reload detail via getDraftWithDetails bila berubah; SyncFailureHint dari sync_error) ← pullVerificationStatus(), refreshVerificationInBackground(), SyncFailureHint.vue.
- api_contracts[0].data_operations += UPDATE lokal checked_by/acknowledged_by(+name) ← pullVerificationStatus().
- api_contracts[0].edge_case_handling[5].handling = offline dideteksi flag `network: true` (bukan "tanpa status") ← apiClient.normalizeError + isNetworkError().
- api_contracts[0].edge_case_handling += pull gagal senyap (404/405 → pullUnsupported); record ditolak saat sync → hint 'Gagal sinkron' ← recordVerificationApi.ts, syncService.failure()/markSynced().
- api_contracts[0].unit_test_cases += 3 (pull mirror/offline, 404 stop, SyncFailureHint) ← syncService.sqljs.spec.ts #6, SyncFailureHint.spec.ts.
- implementation_notes += REVISI 2026-10-04 (GET endpoint, /api prefix PATCH, network flag, SyncFailureHint, Tanggal FormField datetime-local → date (toDateInputValue), filter-row minmax).
- test_scenarios += 2 (Status Verifikasi Ditarik dari Server; Petunjuk Gagal Sinkron). ⚠ diturunkan dari kode/e2e, bukan dari bdd_scenarios Phase 2.

## v6 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit f79b1fe), code is truth.
- api_contracts GET /api/records/{stationType}/verification error_codes[404].condition: body kini amplop standar { message, code: NOT_FOUND } ← RecordVerificationStatusController abort(404) (temuan audit 2026-10-05 #11).
