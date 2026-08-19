# Derived Assumptions Log — module-mobile-station-ops.screen-009--monitor-cages-track.3-tech-spec

## v2 — 2026-08-19

- Business logic dan test_scenarios ditulis ulang total mengikuti pola screen-007/008 Monitor v4 (counter row + list draft/pause + New Data/Load Data) ← mirroring struktural dari instruksi user, diterjemahkan ke entitas cages-track-record
- Counter dihitung dari filter date(date) = hari ini (field `date`, bukan tippler_start_time) ← `date` adalah field kalender murni yang sudah ada; tippler_start_time punya presisi waktu tapi bukan field yang dimaksud untuk filter "hari ini"
- New Data sekarang juga men-set tippler_start_time=now saat INSERT (bukan cuma status/created_by seperti Weighbridge/Grading) ← konsekuensi langsung dari business rule baru "tippler_start_time diisi otomatis sekali saat draft baru dibuat" di entity-catalog v3
- sumCagesRecorded (Jumlah Cage/Lori Tercatat) dihitung via 2 query terpisah (record ids hari ini → SUM total_cages WHERE cages_track_record_id IN (...)) bukan 1 JOIN SQL ← localDb.ts hanya expose query()/run() generik, pola yang sama dipakai di screen lain untuk agregasi lintas tabel
