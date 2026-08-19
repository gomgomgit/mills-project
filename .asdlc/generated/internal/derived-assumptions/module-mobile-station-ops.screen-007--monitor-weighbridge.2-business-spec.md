# Derived Assumptions Log — module-mobile-station-ops.screen-007--monitor-weighbridge.2-business-spec

## v1 — 2026-08-14

- business_rules = ["Aksi Pause tersedia untuk 3 stasiun MVP", "User bisa punya beberapa draft berjalan bersamaan", "Clear menghapus draft tanpa opsi undo, perlu konfirmasi"] ← proposed by agent in draft, accepted without correction
- edge_cases = ["Clear tanpa dialog konfirmasi", "Pause ditekan tanpa draft ongoing"] ← proposed by agent in draft, accepted without correction

## v2 — 2026-08-18

- entry_points: dihapus "Tap draft Weighbridge yang di-pause dari Home" ← entry point ini sudah stale sejak Home v2/v3 (revisi sebelumnya) tidak lagi menampilkan draft/paused list sama sekali
- information_displayed: ringkasan Sum WB Card/Net Weight/Quantity TETAP dipertahankan ← keputusan konservatif agent karena user tidak menjawab langsung pertanyaan soal ini; dipilih agar tetap konsisten dengan pola uiux-spec screen_type_patterns[type=dashboard] yang juga dipakai Monitor Grading & Cages Track (belum direvisi)
- business_rules: "ongoing dan paused digabung sebagai 'Pause'" ← instruksi eksplisit user, tapi scope diterapkan hanya untuk LABEL/tampilan di UI Weighbridge; enum status internal (draft_ongoing/draft_paused) di entity-catalog TIDAK diubah agar tidak berdampak ke Grading/Cages Track yang belum disentuh
- available_actions: Pause & Clear dipindah ke Form (dihapus dari Monitor) ← instruksi eksplisit user
- test_priority tetap "high" (tidak berubah dari v1) ← masih 4 business rules + sensitivitas tinggi (data timbangan)

## v3 — 2026-08-18 (inline correction saat checkpoint)

- information_displayed: ringkasan Sum WB Card/Net Weight/Quantity DIHAPUS ← user eksplisit mengoreksi asumsi konservatif di v2 ("tidak perlu ditampilkan") saat checkpoint pre-implementation, sebelum Phase 4 dimulai

## v4 — 2026-08-18

- Counter "hari ini" dihitung dari SEMUA status (draft+tersimpan), bukan hanya tersimpan ← konsisten dengan perilaku summary lama sebelum dihapus di v3, user tidak spesifikasi scope status
- Filter "hari ini" berdasarkan arrival_datetime ← konsisten dengan keputusan filter tanggal di screen-013 (arrival = tanggal truk, bukan dispatch)
- edge_case "belum ada data hari ini → counter 0" ← turunan langsung, tidak dinyatakan eksplisit user
