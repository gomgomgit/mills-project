# Derived Assumptions Log — module-mobile-station-ops.screen-014--data-preview-grading.2-business-spec

## v1 — 2026-08-15

- business_rules = ["Layar murni read-only, koreksi data dilakukan lewat Form Grading"] ← proposed by agent in draft, accepted without correction
- edge_cases = ["Record tidak ditemukan / belum ada record tersimpan"] ← proposed by agent in draft, accepted without correction

## v2 — 2026-08-19

- Full revision mirroring Data Preview Weighbridge v3's dual-mode (list+filter+detail) restructure ← direct user instruction ("hal yang sama seperti weighbridge untuk halaman monitor dan load data")
- Search fields for Grading: Grading No / License Plate No (bukan wb_card_number/driver_name seperti Weighbridge) ← driver_name sudah dihapus dari entity Grading di v2; License Plate No adalah field kendaraan yang paling analog untuk pencarian
- Date filter scoped to grading-record.date (bukan weighbridge terkait) ← "Tanggal Grading" adalah timestamp milik record ini sendiri, bukan tanggal Weighbridge yang direferensikan via WB Card No
- Checked By tidak ditampilkan di detail ← konsisten dengan keputusan yang sama pada Form Grading (screen-011), field tidak diekspos di UI manapun untuk Grading saat ini
- test_priority naik dari "low" ke "medium" ← screen sekarang punya lebih banyak business rules (read-only enforcement, filter behavior, dual-mode routing, default-hari-ini) dibanding versi lama yang murni single-record display; sesuai kriteria derivasi test_priority (2-4 business rules = medium)

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/DataPreviewGradingView.vue, components/SyncFailureHint.vue, services/recordVerificationApi.ts, services/syncService.ts).
- information_displayed[1]: label field 'Note' → 'Catatan' ← FormField label="Catatan" di detail.
- information_displayed += petunjuk 'Gagal sinkron: <alasan>' ← SyncFailureHint.vue.
- information_displayed += status konfirmasi diperbarui dari server ← pullVerificationStatus().
- edge_cases += record ditolak saat sinkron (server / di perangkat: WB acuan, Quality Parameter) ← syncService.pushGradingRow()/localFailure().
- edge_cases += offline saat memperbarui status verifikasi ← pullVerificationStatus() senyap.

## v4 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit e4f231e, d5da9cf), code is truth (mobile/src/components/filters/*, mobile/src/components/loading/*, mobile/src/views/DataPreview*View.vue, mobile/src/components/RecordVerificationActions.vue).
- information_displayed (append) ← panel filter bersama ListFilterBar: pintasan Hari ini/Semua, Cari + tombol ×, ringkasan 'X dari N data', satu Reset Filter di panel
- ⚠ information_displayed (append) ← entri status verifikasi belum ada di spec layar ini padahal kode menarik status (pullVerificationStatus) — ditambahkan sekaligus penanda 'Memperbarui status verifikasi…'
- available_actions (append Reset Filter) ← tombol Reset Filter tunggal di panel filter
- available_actions (extend) ← pintasan tanggal Hari ini/Semua dan tombol × hapus kata kunci
- edge_cases (append) ← loading lambat: LoadingState setelah 150 ms, ringkasan/pesan kosong ditahan selama memuat

## v5 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit d5da9cf), code is truth (mobile/src/views/DataPreviewGradingView.vue).
- information_displayed[5] ← koreksi v4: entri status yang sudah ada ('Status konfirmasi … dikonfirmasi Mill Management', Grading hanya punya tingkat konfirmasi) diberi akhiran penanda 'Memperbarui status verifikasi…'; entri duplikat 'Status verifikasi … diperiksa Supervisor' yang ditambahkan di v4 dihapus.
