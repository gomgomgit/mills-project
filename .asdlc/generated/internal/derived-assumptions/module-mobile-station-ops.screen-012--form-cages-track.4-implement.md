# Derived Assumptions Log — module-mobile-station-ops.screen-012--form-cages-track.4-implement

## v1 — 2026-08-18

- Route path pakai /stations/cages-track/form/:id (bukan /stations/cages-track/form tanpa id seperti tech-spec) — mengikuti presedan screen-010/011, param :id diperlukan untuk load draft by route param
- saveDraft() sekuensial (header UPDATE lalu tipped-time upsert per baris), bukan transaksi atomik — sama seperti gradingRecordRepo.ts, localDb.ts belum punya transaction primitive
- CagesTippedTimeGrid.vue mirror pola UX GradingDetailGrid.vue (screen-011)
- Validasi "minimal 1 baris" dan role-stripping checked_by ditegakkan di 2 lapis: repo (CagesTippedTimeRequiredError) dan view (pre-check client-side)

## Catatan proses (bukan derived assumption teknis)
Selama implementasi screen ini, sub-agent code-writer dan test-writer melaporkan tool Read/Edit tidak tersedia di sesi mereka (hanya Bash+Write) — bekerja-sekitar via pola tulis-file-baru+mv atau cat+Write. Setiap file yang dihasilkan lewat pola ini (cagesTrackRecordRepo.ts, router/index.ts, cagesTrackRecordRepo.spec.ts, FormCagesTrackView.spec.ts) diverifikasi langsung oleh command (dibaca penuh) sebelum dianggap aman — semua isinya benar dan konsisten dengan pola screen sebelumnya, tidak ada kode berbahaya atau kehilangan data.
