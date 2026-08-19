# Derived Assumptions Log — module-mobile-station-ops.screen-010--form-weighbridge.2-business-spec

## v1 — 2026-08-14

- information_displayed = full field list (WB ID, kendaraan, supir, estate/supplier, divisi, blok, berat, waktu dispatch, Checked By) ← proposed by agent in draft based on PRD context, accepted without correction
- business_rules = ["Checked By hanya Supervisor", "Data kendaraan/supir/estate/divisi/blok teks bebas", "Record tersimpan langsung, bukan approval workflow", "Field wajib bertanda asterisk dengan validasi inline"] ← proposed by agent in draft, accepted without correction
- edge_cases = ["Field wajib belum lengkap", "Operator akses Checked By", "Lanjutkan draft paused", "Back dengan perubahan belum tersimpan"] ← proposed by agent in draft, accepted without correction

## v2 — 2026-08-18

- entry_points diubah: "Mulai Input Baru"/"Lanjutkan draft paused" (nama tombol lama di Monitor) → "New Data"/"tap item list" ← mengikuti rename tombol di screen-007 v2/v3
- Pause & Clear SUDAH ada di available_actions sejak v1 (bukan hal baru) — yang baru adalah IMPLEMENTASI-nya akhirnya dibuat di sini (sebelumnya cuma ada di spec, kode aslinya hanya Simpan+Back) ← gap spec-vs-kode lama, sekarang ditutup
- Live-ticking clock untuk Waktu Dispatch (update visual tiap detik, freeze saat Simpan) ← user eksplisit pilih opsi ini lewat AskUserQuestion, bukan "snapshot otomatis" yang direkomendasikan agent
- Net Weight computed (Gross-Tare) & Arrival auto-set-sekali & Dispatch live-ticking = 3 business_rules baru ← instruksi eksplisit user
- test_priority tetap "high" (sudah high dari v1, sekarang 8 business rules total)

## v3 — 2026-08-18 (inline correction saat checkpoint)

- Checked By DIHAPUS TOTAL dari layar ini (information_displayed, available_actions "Isi Checked By", business_rule terkait, edge_case "operator akses Checked By") ← instruksi eksplisit user saat checkpoint pre-implementation ("tidak perlu ada checked by"), sebelum Phase 4 dimulai. Field checked_by di entity-catalog TIDAK diubah (tetap ada di skema, hanya tidak dipakai/ditampilkan di Form Weighbridge mobile ini) — tidak berdampak ke screen web (022) atau stasiun lain
- Operator dan Supervisor kini identik perilakunya di form ini (tidak ada lagi field yang dibedakan per role) ← konsekuensi langsung dari penghapusan Checked By

## v4 — 2026-08-18

- Dispatch sekarang juga menampilkan TANGGAL (bukan cuma waktu) ← instruksi eksplisit user, mengikuti pola Arrival yang sudah punya Tanggal+Waktu terpisah
- Label (kg) ditambahkan ke Gross/Tare/Net Weight, TIDAK ke Kuantitas ← user hanya sebut "weight dan net", Kuantitas punya satuan berbeda/tidak disebutkan
