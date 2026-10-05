
## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/DataPreviewProcessQualityControlView.vue, components/SyncFailureHint.vue, services/recordVerificationApi.ts, services/syncService.ts).
- information_displayed[1] = label 'Note' → 'Catatan', Tanggal = tanggal saja ← label="Catatan" di detail (noteLabelConsistency.spec.ts), toDateInputValue + FormField type=date.
- information_displayed += petunjuk 'Gagal sinkron: <alasan>' untuk record Tersimpan yang ditolak server ← SyncFailureHint.vue (status saved + sync_error).
- information_displayed += status verifikasi diperbarui dari server di latar belakang ← pullVerificationStatus() di loadList()/loadDetail().
- edge_cases += record ditolak saat sinkron (offline tidak ditandai) ← syncService.failure() hanya menulis sync_error bila ada status HTTP.
- edge_cases += offline saat memperbarui status verifikasi → status lokal tetap, tanpa galat ← pullVerificationStatus() menelan galat.

## v3 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit e4f231e, d5da9cf), code is truth (mobile/src/components/filters/*, mobile/src/components/loading/*, mobile/src/views/DataPreview*View.vue, mobile/src/components/RecordVerificationActions.vue).
- information_displayed (append) ← panel filter bersama ListFilterBar: pintasan Hari ini/Semua, Cari + tombol ×, ringkasan 'X dari N data', satu Reset Filter di panel
- information_displayed[3] ← tambah penanda 'Memperbarui status verifikasi…' (LoadingState compact verification-refreshing)
- available_actions[3].description ← Reset Filter kini di panel filter & tampil selama filter aktif (dulu hanya saat hasil kosong)
- available_actions (extend) ← pintasan tanggal Hari ini/Semua dan tombol × hapus kata kunci
- edge_cases[1] ← Reset Filter dipindah dari kotak kosong ke panel filter
- edge_cases (append) ← loading lambat: LoadingState setelah 150 ms, ringkasan/pesan kosong ditahan selama memuat
