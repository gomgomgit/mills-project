# Derived Assumptions Log — project.3-tech-spec.entity-catalog

## v1 — 2026-08-17

- Hierarki relasi Corporate → Company → Business Unit → Station → Machinery (entitas & relationships one-to-many) ← disimpulkan dari arch_spec.architecture_notes, tidak eksplisit di business spec manapun
- Field `user.business_unit_id` ← disimpulkan dari alur "pilih Business Area/Company" saat login (screen-001, screen-002), bukan pernyataan eksplisit soal skema data
- `station.type` enum (weighbridge, grading, cages-track, other) + split `is_active` untuk 3 stasiun MVP vs 12 placeholder ← disintesis dari business_rules screen-006--station-list
- Enum status lifecycle `draft_ongoing → draft_paused → saved → synced` pada weighbridge-record/grading-record/cages-track-record ← disintesis dari status badge Monitor (screen-007/008/009), Home (screen-005), dan Data Preview (screen-013/014/015)
- Constraint `net_weight = gross_weight - tare_weight` pada weighbridge-record ← field disebutkan di screen-010, formula perhitungan tidak dinyatakan eksplisit
- Tipe UUID untuk id seluruh entitas, khususnya entitas record yang dibuat offline di mobile ← disimpulkan dari goal PRD soal ID stabil untuk sync ke API/ERP fase mendatang
- Field `created_by` pada weighbridge-record/grading-record/cages-track-record ← ditambahkan untuk akuntabilitas/sync, tidak eksplisit tercantum di information_displayed screen manapun

## v2 — 2026-08-18

- Entity grading-parameter baru (id, name, uom enum, sort_order) + 16 baris kanonis ← daftar diberikan eksplisit oleh user; sort_order & struktur field diturunkan agent
- grading-record.weighbridge_record_id (FK) DITAMBAHKAN ← koreksi dari draft konfirmasi sebelumnya yang keliru menyatakan "wb_card_number sudah ada" pada grading-record; field ini sebenarnya belum ada, perlu FK baru agar dropdown WB Card No + auto-fill License Plate No/Estate/Division bisa berfungsi
- grading-record.note DITAMBAHKAN ← ada di mock referensi ("ikuti mock persis" sudah dikonfirmasi user), tidak disebutkan eksplisit ulang saat listing field yang kurang
- grading-record.date jadi timestamp (bukan date) ← konsisten dengan pola "auto-terisi sekali" seperti Weighbridge Arrival yang butuh presisi waktu, bukan cuma tanggal
- grading-detail.uom = snapshot (disalin saat parameter dipilih), BUKAN live-join ke grading-parameter ← keputusan integritas data historis, tidak dinyatakan eksplisit oleh user
- License Plate No/Estate/Division auto-terisi dari Weighbridge TAPI tetap bisa diedit (tidak disabled) ← interpretasi wajar atas "bisa terisi otomatis", karena field bisnis (bukan timestamp sistem) mungkin perlu koreksi manual

## v3 — 2026-08-19

- cages-track-record: tippler_start_time/tippler_stop_time/cages_out/cages_tipped/note DITAMBAHKAN ← dari mock referensi gambar + deskripsi user; tippler_stop_time dikonfirmasi TIDAK live-ticking (beda dari Weighbridge dispatch_datetime), cukup dibekukan sekali saat Simpan
- cages-tipped-time restrukturisasi TOTAL: cage_number+tipped_time (model lama 1-baris-1-cage) DIHAPUS, diganti tipped_hour (integer 0-23, bukan timestamp) + checked_cage_numbers (CSV) + total_cages + cages_remain ← instruksi eksplisit user, model lama tidak bisa merepresentasikan "1 baris = checklist banyak cage sekaligus"
- checked_cage_numbers disimpan sebagai CSV string, bukan tabel anak/JSON column terpisah ← keputusan teknis agent (konsisten dengan filosofi penyimpanan ringan yang sudah dipakai di proyek ini, mis. grading-detail.uom sebagai snapshot bukan live-join), BUKAN instruksi eksplisit user — didiskusikan sebagai bagian draft entity-catalog, dikonfirmasi via GO
- cages_remain = PER BARIS (cages_tipped header − total_cages baris itu sendiri), BUKAN kumulatif lintas baris ← konfirmasi eksplisit user via pertanyaan pilihan
- tipped_hour harus > tipped_hour baris terakhir yang ditambahkan (urutan menaik kronologis) ← konfirmasi eksplisit user atas tafsiran agent terhadap instruksi asli yang ambigu
- Field "jumlah cages" untuk grid ditafsirkan SEBAGAI cages_tipped (bukan field baru terpisah, bukan juga cages_out) ← user menjawab "field terpisah" (dari Cages Out), agent memetakan ke field header cages_tipped yang sudah ada di mock, bukan field ke-3 baru — DIFLAG untuk konfirmasi ulang di checkpoint pra-implementasi karena bukan jawaban langsung dari pertanyaan yang diajukan
- date pada cages-track-record TETAP type date (bukan timestamp seperti grading-record) ← karena Tippler Start/Stop Time sudah menyimpan presisi waktu secara terpisah, Date murni representasi tanggal kalender
