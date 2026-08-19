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
