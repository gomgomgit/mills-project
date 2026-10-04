
## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/DataPreviewProcessWaterView.vue, services/recordVerificationApi.ts, services/apiClient.ts, components/SyncFailureHint.vue, services/syncService.ts, backend RecordVerificationStatusController/Service, routes/api.php).
- information_displayed[1] = header '... Acknowledged By, Catatan' (bukan 'Note') ← view merender FormField label="Catatan" (sudah begitu sebelum audit; ⚠ koreksi drift lama, bukan perubahan audit).
- information_displayed += petunjuk 'Gagal sinkron: <alasan>' untuk record Tersimpan yang ditolak server ← SyncFailureHint.vue (status saved + sync_error).
- information_displayed += status verifikasi diperbarui dari server di latar belakang ← pullVerificationStatus() di loadList()/loadDetail().
- edge_cases += record ditolak saat sinkron (offline tidak ditandai) ← syncService.failure() hanya menulis sync_error bila ada status HTTP.
- edge_cases += offline saat memperbarui status verifikasi → status lokal tetap, tanpa galat ← pullVerificationStatus() menelan galat.
