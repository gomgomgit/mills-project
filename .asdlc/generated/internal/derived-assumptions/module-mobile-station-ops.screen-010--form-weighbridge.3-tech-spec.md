# Derived Assumptions Log — module-mobile-station-ops.screen-010--form-weighbridge.3-tech-spec

## v1 — 2026-08-17

- Keputusan bahwa Simpan bersifat operasi lokal SQLite (UPDATE), tanpa endpoint API; sync ke server terpisah dan di luar scope screen ini ← disimpulkan agent
- Validasi role untuk Checked By diberlakukan baik di UI maupun di layer service lokal (defense in depth) ← keputusan desain agent, tidak eksplisit di business spec
- Daftar field wajib spesifik (WB ID, tanggal, kendaraan, supir, estate, gross_weight, dispatch_datetime) ← disintesis dari information_displayed business spec, bukan daftar eksplisit field wajib

## v2 — 2026-08-18

- Arrival auto-set HANYA jika draft baru (arrival_datetime kosong), dipertahankan kalau draft dilanjutkan ← interpretasi teknis dari "set otomatis saat masuk form" — arrival adalah fakta bisnis (waktu kedatangan kendaraan sungguhan), tidak masuk akal ditimpa ulang tiap kali draft paused dibuka lagi; beda dengan Dispatch yang eksplisit diminta selalu live
- Dispatch SELALU live-ticking dari now di setiap mount (baru maupun resume), mengabaikan nilai lama ← instruksi eksplisit user + jawaban AskUserQuestion (live ticking, bukan snapshot)
- Pause TANPA validasi field wajib (beda dari Simpan yang wajib validasi) ← Pause adalah checkpoint sementara, bukan final save; disimpulkan agent, tidak dinyatakan eksplisit
- Dispatch butuh interval timer (setInterval) yang di-clear saat unmount/freeze ← housekeeping teknis standar, tidak eksplisit di business spec
- test scenarios: 23 unit (level komponen/reaktif, tanpa backend), 0 API, 10 component, 10 browser ← delegated test-spec-writer-agent dari Phase 2 bdd_scenarios (10 skenario)

## v3 — 2026-08-18 (inline correction saat checkpoint)

- Checked By dihapus total: actor_permissions conditions dikosongkan, business_logic step Checked By dihapus, 3 unit_test_cases terkait dihapus (23→20), 1 test_scenario "Checked By Khusus Supervisor" dihapus, 2 scenario "success as X/Y" digabung jadi 1 "success" ← instruksi eksplisit user saat checkpoint

## v4 — 2026-08-18

- Tanggal Dispatch pakai fungsi format yang sama dengan Tanggal Arrival (formatDateID) ← tidak perlu fungsi baru, dispatch_datetime sudah full ISO datetime, cukup panggil ulang fungsi format yang sama
- Label (kg) murni teks statis di label, tidak ada perubahan tipe data/kolom/konversi satuan ← instruksi user hanya soal label, bukan soal penyimpanan data
