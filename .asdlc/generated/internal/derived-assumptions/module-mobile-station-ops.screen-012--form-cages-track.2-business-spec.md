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

## v3 — 2026-08-19

- KOREKSI dari v2: jumlah kolom checklist grid Cages Tipped Time TIDAK LAGI berasal dari field header Cages Tipped — sekarang berasal dari `mill-setting.jumlah_cages` (fitur Mills Setting baru, entity-catalog v6) milik business unit tempat stasiun ini berada ← instruksi eksplisit user (dikonfirmasi ulang 2x setelah koreksi awal user sendiri)
- Cages Tipped (header) TIDAK BERUBAH maknanya — tetap input manual "jumlah cage yang akan di-tipping sesi ini", HANYA TIDAK LAGI mengontrol jumlah kolom ← instruksi eksplisit user
- open_question v2 "Cages Tipped dikunci setelah baris pertama dibuat" DIHAPUS/dianggap tidak relevan lagi — locking itu dulu untuk mencegah N (jumlah kolom) berubah di tengah pengisian; karena N sekarang bersumber dari mill-setting (fixed, tidak diedit di form ini), alasan locking tersebut tidak berlaku lagi ← inferensi agent dari perubahan sumber data N, bukan pernyataan eksplisit user
- Tombol "Tambah baris" disabled jika mill-setting.jumlah_cages belum tersedia secara lokal (belum sync) atau 0 ← inferensi agent, mengikuti pola disabled sebelumnya (dulu untuk cages_tipped kosong), sekarang diarahkan ke ketersediaan data mill_setting; belum dikonfirmasi eksplisit oleh user
