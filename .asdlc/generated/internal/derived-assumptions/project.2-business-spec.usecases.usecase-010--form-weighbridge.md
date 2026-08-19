# Derived Assumptions Log — project.2-business-spec.usecases.usecase-010--form-weighbridge

## v2 — 2026-08-18

- main_flow/alternative_flows diperluas: Pause Progress, Clear Draft, Tap Breadcrumb, Buka Menu Hamburger ditambahkan (bukan mengganti yang lama) ← Pause/Clear akhirnya diimplementasikan di layar ini (sudah ada di business spec sejak v1, kodenya belum pernah dibuat), breadcrumb/hamburger baru
- bdd_scenarios: 4 skenario baru ditambahkan (bukan mengganti 6 yang lama, semua masih valid) ← delegated bdd-spec-writer-agent, additive merge normal (tidak perlu override "never remove" seperti kasus Home, karena flow lama di sini tetap berlaku)
- "Lanjutkan Draft Paused" — Waktu Dispatch tetap live-ticking dari saat form dibuka (bukan dari nilai draft lama) ← keputusan konsisten dengan business_rule baru "Dispatch live-ticking sampai Simpan", berlaku sama baik draft baru maupun draft lama yang dilanjutkan

## v3 — 2026-08-18 (inline correction saat checkpoint)

- alternative_flow "Checked By Khusus Supervisor" DIHAPUS ← Checked By dihapus total dari screen ini, instruksi eksplisit user
- bdd_scenarios: "success as Station Operator" + "success as Supervisor" DIGABUNG jadi satu "success" tanpa Checked By, "Checked By Khusus Supervisor" scenario DIHAPUS (bukan dipertahankan) ← override "never remove" — skenario lama menguji perilaku (field Checked By) yang sudah tidak ada sama sekali di layar ini, mempertahankannya akan kontradiktif dengan business spec baru

## v4 — 2026-08-19

- main_flow step 3-4 (pilih Tipe Weighbridge via tab, lalu sistem menyesuaikan field tanggal/tujuan muatan) ← diturunkan dari business spec v5 (perubahan entity weighbridge_type), bukan pernyataan eksplisit user pada level usecase
- alternative_flow "Ganti Tipe Setelah Field Terisi" ← ditambahkan agar konsisten dengan business_rule baru di business spec (nilai lama dibuang saat ganti tipe), bukan dinyatakan eksplisit user

## v5 — 2026-08-19 (inline correction saat checkpoint)

- main_flow step 4/7 dan alternative_flow "Lanjutkan Draft Paused" diperbarui: Dispatch tidak lagi live-ticking, kini auto-isi sekali sama seperti Receive ← instruksi eksplisit user saat REVISI checkpoint
- bdd_scenarios "success" (v1 dan v4) diedit langsung (bukan re-derivasi bdd-spec-writer-agent) untuk menghapus bahasa "dibekukan saat Simpan" ← koreksi teks mengikuti perubahan business_rules, tidak menambah skenario baru
