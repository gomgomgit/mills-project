# Derived Assumptions Log — project.2-business-spec.usecases.usecase-013--data-preview-weighbridge

## v2 — 2026-08-18

- main_flow/alternative_flows diperluas total: dari single-record read-only menjadi list+filter+dual-mode ← instruksi eksplisit user
- bdd_scenarios: skenario lama "berhasil" DIGANTI (bukan dipertahankan berdampingan) dengan versi baru ← override "never remove" — bdd-spec-writer-agent secara eksplisit menandai kontradiksi faktual (skenario lama: Back→Monitor langsung; alur baru: Back dari mode detail→list dulu), mempertahankan keduanya akan membingungkan test-spec-writer-agent di Phase 3
- bdd_scenarios: skenario "Record Tidak Ditemukan" dipertahankan utuh (masih valid untuk mode detail)

## v3 — 2026-08-18

- Skenario baru "Filter Tanggal Default Hari Ini" DITAMBAHKAN (bukan menggantikan "Filter Tanggal/Pencarian Diterapkan" yang sudah ada) ← instruksi eksplisit user (filter tanggal auto hari ini); skenario generik lama tetap valid untuk kasus user mengubah filter dari default
- Ditemukan & diperbaiki sendiri: percobaan pertama menulis tech spec v3 salah GANTI (bukan tambah) skenario generik dengan skenario default-hari-ini — dikoreksi sebelum lanjut, sekarang usecase & tech spec konsisten 10 skenario
