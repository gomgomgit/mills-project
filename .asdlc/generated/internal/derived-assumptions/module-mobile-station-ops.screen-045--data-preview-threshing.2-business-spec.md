
## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/DataPreviewThreshingView.vue, components/SyncFailureHint.vue, services/recordVerificationApi.ts).
- information_displayed[1]: Tanggal = tanggal saja; label 'Note' → 'Catatan' ← FormField type="date" (toDateInputValue) + label="Catatan".
- information_displayed += petunjuk 'Gagal sinkron: <alasan>' ← SyncFailureHint.vue.
- information_displayed += status verifikasi diperbarui dari server ← pullVerificationStatus().
- edge_cases += record ditolak saat sinkron (offline tidak ditandai); offline saat memperbarui status verifikasi.
