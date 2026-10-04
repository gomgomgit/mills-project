# Derived Assumptions Log — module-mobile-station-ops.screen-013--data-preview-weighbridge.2-business-spec

## v1 — 2026-08-15

- business_rules = ["Layar murni read-only, koreksi data dilakukan lewat Form Weighbridge"] ← proposed by agent in draft, accepted without correction
- edge_cases = ["Record tidak ditemukan / belum ada record tersimpan"] ← proposed by agent in draft, accepted without correction

## v2 — 2026-08-18

- Scope diperluas total: dari "single-record read-only" jadi "list+filter (date+search) + detail dual-mode" ← instruksi eksplisit user ("load data akan menampilkan data yang telah disave maupun masih draft dengan filter date dan search")
- Tap item draft/pause di list → buka Form (bukan tampilkan read-only) ← desain agent, dikonfirmasi user secara implisit (tidak dikoreksi saat disampaikan sebagai rencana desain sebelum eksekusi)
- Tap item saved/synced → tampilkan detail read-only di layar yang sama (bukan layar terpisah) ← reuse tampilan read-only yang sudah ada sejak v1, desain agent
- test_priority: low → medium ← 4 business rules (naik dari 1), kompleksitas list+filter+dual-mode

## v3 — 2026-08-18

- Filter tanggal default hari ini SAAT LIST DIBUKA (bukan permanen/tidak bisa diubah) — user tetap bisa ubah/kosongkan ← instruksi eksplisit user, tapi scope "bisa diubah" adalah interpretasi wajar (default bukan berarti terkunci)
- edge_case baru: tidak ada data hari ini saat filter default aktif → sama seperti "filter tidak menghasilkan apapun" (bukan kondisi error terpisah)

## v4 — 2026-08-19

- Label field tanggal & tujuan muatan di detail read-only ("Tanggal & Waktu Arrival"/"Tanggal & Waktu Dispatch") mengikuti wording yang sama dengan Form Weighbridge (screen-010) ← konsistensi lintas-screen, tidak dinyatakan eksplisit user, diturunkan dari entity-catalog v5 (weighbridge_type, record_datetime, destination) dan instruksi user untuk menyesuaikan tampilan load data

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/DataPreviewWeighbridgeView.vue, components/SyncFailureHint.vue, services/recordVerificationApi.ts, services/syncService.ts).
- information_displayed += petunjuk 'Gagal sinkron: <alasan>' untuk record Tersimpan yang ditolak server ← SyncFailureHint.vue (status saved + sync_error).
- information_displayed += status verifikasi diperbarui dari server di latar belakang ← pullVerificationStatus() di loadList()/loadDetail().
- edge_cases += record ditolak saat sinkron (offline tidak ditandai) ← syncService.failure() hanya menulis sync_error bila ada status HTTP.
- edge_cases += offline saat memperbarui status verifikasi → status lokal tetap, tanpa galat ← pullVerificationStatus() menelan galat.
