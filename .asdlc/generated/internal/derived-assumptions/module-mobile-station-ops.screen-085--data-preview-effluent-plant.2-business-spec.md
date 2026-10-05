
## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/DataPreviewEffluentPlantView.vue, components/SyncFailureHint.vue, services/recordVerificationApi.ts, services/syncService.ts).
- information_displayed[1] = field header 'Note' → 'Catatan' ← label detail di DataPreviewEffluentPlantView.vue (FormField label="Catatan" (sudah begitu sebelum audit; artefak tertinggal)).
- information_displayed += petunjuk 'Gagal sinkron: <alasan>' untuk record Tersimpan yang ditolak server ← SyncFailureHint.vue (status saved + sync_error).
- information_displayed += status verifikasi diperbarui dari server di latar belakang ← pullVerificationStatus() di loadList()/loadDetail().
- edge_cases += record ditolak saat sinkron (offline tidak ditandai) ← syncService.failure() hanya menulis sync_error bila ada status HTTP.
- edge_cases += offline saat memperbarui status verifikasi → status lokal tetap, tanpa galat ← pullVerificationStatus() menelan galat.

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (mobile/src/views/DataPreviewEffluentPlantView.vue, mobile/src/utils/optionLabel.ts).
- business_rules ← append: field pilihan (biogas_flare_status, dosing_pump_1_status, sludge_dewatering_status) tampil sebagai label (Display::OPTION_LABELS), kosong '-'.

## v4 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit e4f231e, d5da9cf), code is truth (mobile/src/components/filters/*, mobile/src/components/loading/*, mobile/src/views/DataPreview*View.vue, mobile/src/components/RecordVerificationActions.vue).
- information_displayed (append) ← panel filter bersama ListFilterBar: pintasan Hari ini/Semua, Cari + tombol ×, ringkasan 'X dari N data', satu Reset Filter di panel
- information_displayed[5] ← tambah penanda 'Memperbarui status verifikasi…' (LoadingState compact verification-refreshing)
- available_actions (append Reset Filter) ← tombol Reset Filter tunggal di panel filter
- available_actions (extend) ← pintasan tanggal Hari ini/Semua dan tombol × hapus kata kunci
- edge_cases (append) ← loading lambat: LoadingState setelah 150 ms, ringkasan/pesan kosong ditahan selama memuat
