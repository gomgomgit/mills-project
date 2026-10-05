# Derived Assumptions Log — module-mobile-station-ops.screen-015--data-preview-cages-track.2-business-spec

## v1 — 2026-08-16

- business_rules = ["Layar murni read-only, koreksi data dilakukan lewat Form Cages Track"] ← proposed by agent in draft, accepted without correction
- edge_cases = ["Record tidak ditemukan / belum ada record tersimpan"] ← proposed by agent in draft, accepted without correction

## v2 — 2026-08-19

- Full revision mirroring Data Preview Weighbridge/Grading dual-mode (list+filter+detail) pattern ← direct user instruction, structural mirroring
- Checked By/Acknowledged By DITAMPILKAN di detail (read-only), TIDAK dihilangkan seperti Weighbridge/Grading ← konsisten dengan keputusan Form Cages Track yang mempertahankan kedua field ini; agent menyimpulkan tidak ada pembatasan role untuk MELIHAT (view-only), hanya untuk MENGISI (yang sudah ditegakkan di Form) — bukan instruksi eksplisit user, murni konsistensi logis
- Search field: Cages Track Number (bukan field lain) ← field paling analog dengan grading_number/wb_card_number di 2 stasiun lain
- test_priority naik dari "low" ke "medium" ← alasan sama seperti Grading: lebih banyak business rules dibanding versi single-record display lama

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/DataPreviewCagesTrackView.vue, components/SyncFailureHint.vue, services/recordVerificationApi.ts).
- information_displayed[1]: Tanggal = tanggal saja; label 'Note' → 'Catatan' ← FormField type="date" + label="Catatan".
- information_displayed += petunjuk 'Gagal sinkron: <alasan>' ← SyncFailureHint.vue.
- information_displayed += status verifikasi diperbarui dari server ← pullVerificationStatus().
- edge_cases += record ditolak saat sinkron; offline saat memperbarui status verifikasi.

## v4 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit e4f231e, d5da9cf), code is truth (mobile/src/components/filters/*, mobile/src/components/loading/*, mobile/src/views/DataPreview*View.vue, mobile/src/components/RecordVerificationActions.vue).
- information_displayed (append) ← panel filter bersama ListFilterBar: pintasan Hari ini/Semua, Cari + tombol ×, ringkasan 'X dari N data', satu Reset Filter di panel
- information_displayed[5] ← tambah penanda 'Memperbarui status verifikasi…' (LoadingState compact verification-refreshing)
- available_actions (append Reset Filter) ← tombol Reset Filter tunggal di panel filter
- available_actions (extend) ← pintasan tanggal Hari ini/Semua dan tombol × hapus kata kunci
- edge_cases (append) ← loading lambat: LoadingState setelah 150 ms, ringkasan/pesan kosong ditahan selama memuat
