# Derived Assumptions Log — module-mobile-station-ops.screen-009--monitor-cages-track.2-business-spec

## v1 — 2026-08-14

- business_rules = ["Pause tersedia untuk 3 stasiun MVP", "Multiple draft bisa berjalan bersamaan", "Clear menghapus tanpa undo, perlu konfirmasi"] ← proposed by agent in draft (mirrored from Monitor Weighbridge/Grading pattern), accepted without correction
- edge_cases = ["Clear tanpa dialog konfirmasi", "Pause ditekan tanpa draft ongoing"] ← proposed by agent in draft, accepted without correction

## v2 — 2026-08-19

- Full revision mirroring Monitor Weighbridge/Grading v4 pattern (counter row + draft/pause list + New Data/Load Data), per user instruction to bring the same treatment to Cages Track ← direct instruction, structural mirroring is agent's own translation
- Counter = 2 cards (bukan 3 seperti Weighbridge/Grading) — Jumlah Cages Track, Jumlah Cage/Lori Tercatat ← user eksplisit memilih opsi 2-card via pertanyaan pilihan, karena tidak ada field berat/kuantitas kedua yang alami untuk stasiun ini
- "Jumlah Cage/Lori Tercatat" = SUM(total_cages) seluruh baris Cages Tipped Time pada record hari ini ← agent menurunkan definisi metrik ini dari field cages-tipped-time.total_cages yang baru; belum dikonfirmasi eksplisit oleh user secara terpisah, akan diflag di checkpoint pra-implementasi
- entry_points dikurangi jadi satu entry saja (hapus "Tap draft ... dari Home") ← Home tidak lagi menampilkan info draft sejak revisi screen-005, konsisten dengan screen-007/008
