# Derived Assumptions Log — project.2-business-spec.usecases.usecase-012--form-cages-track

## v2 — 2026-08-19

- Full revision per mock referensi + Weighbridge/Grading Form v4 structural mirror (Pause/Clear/breadcrumb/hamburger, sectioned layout) ← direct user instruction + mock
- UNLIKE Weighbridge/Grading: Checked By dan Acknowledged By TETAP ada, role-gated (Supervisor/Mill Management) ← mock eksplisit mencantumkan kedua field, bukan mirroring keputusan penghapusan di Weighbridge/Grading
- bdd-spec-writer-agent menilai TIDAK ADA skenario lama yang kontradiktif (berbeda dari Weighbridge/Grading round) — 2 skenario "success" lama dinilai stale-di-mekanisme (masih menyebut "Cages Track ID"/model lama nomor-cage-per-baris) tapi TIDAK salah secara faktual ← command menulis ulang isi 2 skenario ini IN-PLACE (bukan hapus-ganti) untuk mencerminkan mekanisme checklist+jam yang baru, termasuk menambahkan penjelasan Acknowledged By tetap kosong untuk Supervisor (bukan role Mill Management)
- 8 skenario baru ditambahkan (Acknowledged By Khusus Mill Management, Cages Tipped Belum Diisi, Time Tidak Bisa Duplikat Atau Mundur, Hapus Baris Cages Tipped Time, Pause Progress, Clear Draft, Tap Breadcrumb, Buka Menu Hamburger)
