# Derived Assumptions Log — module-master-data.screen-128--kelola-periode-pelaporan.4-implement

## v1 — 2026-09-22

- RECORD_DATE_COLUMN_OVERRIDES: weighbridge_records memakai `record_datetime`, bukan `date` seperti 17 tabel record lainnya ← ditemukan agen dari skema saat implementasi; tech spec menulis "record.date" untuk semua stasiun. Tanpa penanganan ini hitungan Weighbridge akan selalu 0 secara diam-diam
- tipe 'other' di-skip saat menghitung unverified (tidak punya tabel record), bukan melempar exception ← agent's call; tech spec tidak menyebut 'other' sama sekali
- pemecahan menjadi DUA service (PeriodService untuk CRUD, PeriodClosureService untuk penutupan) ← tech spec tidak menentukan jumlah service; agen memisahkan agar hanya jalur penutupan yang perlu tahu 18 tabel record
- resources/views/master-data/periods.blade.php dibuat meski tidak ada di implementation_plan ← konsekuensi mekanis dari #[Layout('master-data.periods')]; setiap layar master-data punya wrapper one-liner yang sama. Agen melaporkannya eksplisit alih-alih menyelipkannya
- reopen memakai konfirmasi inline (seperti delete), close memakai modal ← agen memilih beda perlakuan karena close harus menampilkan angka belum-terverifikasi lebih dulu; business spec hanya mewajibkan angka itu tampil, tidak menentukan bentuk dialognya
- tombol konfirmasi penutupan tetap enabled berapa pun jumlah record belum terverifikasi ← turunan dari business rule "Admin tetap boleh melanjutkan secara sadar"; angka adalah peringatan, bukan penghalang
- label status Indonesia (Draft/Terbuka/Tertutup) hanya di UI, nilai tersimpan tetap draft|open|closed ← tidak dinyatakan di spec manapun
- fix auto-fix round 1: normalisasi business_unit_id '' -> null di buildValidator() ← bug nyata PostgreSQL-only (22P02) yang lolos dari unit+component test karena suite berjalan di SQLite. Perbaikan mengikuti pola $stationType yang sudah ada di file yang sama
- status "complete" meski coverage gate 80% tidak terverifikasi ← keputusan command ini, bukan agen (agen menyerahkannya). Dasarnya preseden proyek: 38 screen lain berstatus complete dengan known_issue coverage identik, dan tidak ada driver xdebug/pcov di environment ini sehingga tidak satu pun screen pernah punya coverage terukur. test_results.unit.coverage = 0 mengikuti konvensi 39 screen lain sebagai penanda "tidak terukur"

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v8)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v9 — 2026-10-03

Hasil run penuh e2e-web 2026-10-03 (530 lulus, 0 gagal, 9 skip).
- test_results.browser = 18 lulus/0 gagal dari e2e-full.log.counts.json (spec kelola-periode-pelaporan); 1 skip dicatat di catatan.
- Known_issue scenario 6 di-skip dipertahankan — skip disengaja, bukan 'tidak dijalankan'.
