# Derived Assumptions Log — module-mobile-station-ops.screen-012--form-cages-track.2-business-spec

## v1 — 2026-08-15

- information_displayed = header + Cages Tipped Time grid + Checked By field ← proposed by agent in draft (mirrored from Form Grading pattern + uiux-spec "Cages Tipped Time" example), accepted without correction
- business_rules = ["Checked By hanya Supervisor", "Minimal satu baris Cages Tipped Time sebelum Simpan", "Record tersimpan langsung tanpa approval"] ← proposed by agent, accepted without correction
- edge_cases = ["Field wajib belum lengkap", "Belum ada baris Cages Tipped Time", "Operator akses Checked By", "Lanjutkan draft paused", "Back dengan perubahan belum tersimpan"] ← proposed by agent, accepted without correction

## v2 — 2026-08-19

- Full rewrite mengikuti mock referensi gambar: Tippler Start/Stop Time otomatis, Cages Out/Tipped manual, Inputted By otomatis, grid Cages Tipped Time berubah total dari "1 baris = 1 cage" menjadi "1 baris = 1 slot jam dengan checklist banyak cage" ← instruksi eksplisit user + mock
- Checked By/Acknowledged By TETAP ditampilkan dan role-gated (Supervisor/Mill Management) ← BERBEDA dari Weighbridge/Grading yang menghapus Checked By total; mock Cages Track eksplisit mencantumkan kedua field ini, jadi TIDAK di-mirror dari keputusan Weighbridge/Grading
- Time per baris harus > Time baris terakhir ditambahkan, tidak boleh duplikat ← konfirmasi eksplisit user
- "Jumlah cages" untuk checklist grid = field header Cages Tipped (bukan field baru terpisah) ← user menjawab "field terpisah" dari Cages Out saat ditanya; agent memetakan ke field mock yang sudah ada (Cages Tipped) daripada membuat field ke-3 baru — DIFLAG untuk konfirmasi ulang di checkpoint pra-implementasi
- open_question: Cages Tipped dikunci setelah baris pertama dibuat ← BELUM dikonfirmasi user, asumsi agent demi konsistensi kolom checklist, akan diverifikasi di checkpoint
- open_question: Hapus baris Cages Tipped Time = queue-for-deletion (bukan langsung hapus) ← BELUM dikonfirmasi user, mirror pola Grading Detail, akan diverifikasi di checkpoint
