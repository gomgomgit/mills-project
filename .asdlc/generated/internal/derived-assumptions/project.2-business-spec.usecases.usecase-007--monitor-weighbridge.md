# Derived Assumptions Log — project.2-business-spec.usecases.usecase-007--monitor-weighbridge

## v2 — 2026-08-18

- main_flow/alternative_flows/postconditions/business_rules = diturunkan penuh dari revisi screen-007 (restrukturisasi total: list draft/pause, New Data, Load Data, breadcrumb, hamburger) ← user secara eksplisit menyatakan requirement baru
- bdd_scenarios: 4 skenario lama (Pause Progress, Clear Draft, Pause Ditekan Tanpa Draft Ongoing, Clear Draft dibatalkan) DIHAPUS dari usecase ini (bukan dipertahankan) ← override kebijakan default agen "never remove" — Pause & Clear sudah dipindah total ke Form Weighbridge (usecase-010), tidak lagi ada di layar ini sama sekali; skenario setara akan diturunkan ulang secara alami di usecase-010 saat direvisi
- bdd_scenarios: 2 skenario lama (success, Lanjutkan Draft Paused) diubah redaksinya (bukan konten intinya) untuk mengganti nama tombol lama "Mulai Input Baru" → "New Data" ← nama tombol berubah, alur intinya sama

## v3 — 2026-08-18

- main_flow step 2 + business_rule + 2 bdd_scenario baru (Counter Hari Ini Menampilkan Data, Belum Ada Data Hari Ini) ← instruksi eksplisit user (counter WB/Net Weight/Quantity hari ini di atas list draft)
