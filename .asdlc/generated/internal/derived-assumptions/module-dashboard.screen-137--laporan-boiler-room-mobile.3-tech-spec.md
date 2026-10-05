# Derived Assumptions — module-dashboard.screen-137--laporan-boiler-room-mobile.3-tech-spec

## v3 — 2026-09-27

- `implementation_notes[0..]` = catatan pemisahan periods/period_stations disisipkan di AWAL daftar, bukan di akhir ← briefing tidak menentukan posisinya; perubahan model adalah hal pertama yang perlu dibaca sebelum catatan lain, dan catatan lama tetap utuh di bawahnya.
- `test_scenarios[*].scenario_ref` = dibiarkan identik byte-per-byte ← ref ini mengunci ke `bdd_scenarios` pada usecase Fase 2 yang TIDAK basi dan tidak saya sentuh. Menamai ulangnya akan memutus tautan itu tanpa satu pun kegagalan yang terlihat, padahal isi skenarionya memang berubah dan yang wajib berubah hanya action/assert/request_example.
- `endpoints[*].response.success_schema.*.status` = `"string (draft|open|closed) — status BARIS period_stations <Stasiun>"` (bukan tetap `"string"` telanjang) ← briefing hanya menyebut bahwa artinya berubah. Skema yang tetap berbunyi `"string"` masih terbaca sebagai status periode oleh pembaca mana pun, jadi kualifikasi prosa dipasang di dalam nilai skemanya sendiri.
- `station_type` = non-nullable pada skema, MESKIPUN kode mobile masih menyatakannya nullable ← ini keputusan yang paling mungkin dipertanyakan. `mobile/src/services/boilerRoomReportRepo.ts` masih mendeklarasikan `station_type: string | null` dan fixture test mobile masih memakai `station_type: null` + `station_type_label: 'Semua Jenis Stasiun'`. Bentuk itu MUSTAHIL diproduksi backend sejak 2026-09-25, jadi yang stale adalah kodenya, bukan kontraknya; artefak ditulis menurut kontrak yang sebenarnya berlaku dan penyimpangan kodenya dicatat eksplisit di `implementation_notes` alih-alih diperbaiki (kode tidak boleh disentuh pada tugas ini).
- `implementation_notes` = ditambah catatan "tidak ada penyaringan station_type di klien" yang DIPERKUAT, bukan dihapus ← sifat pass-through itu tetap benar setelah pemisahan dan justru menjadi lebih penting: keanggotaan periode sekarang ditentukan oleh keberadaan baris `period_stations`, data yang klien tidak punya aksesnya sama sekali.
- `data_operations[period-station]` = dideskripsikan sebagai "dibaca SERVER (bukan klien)" ← layar mobile tidak pernah menyentuh tabelnya; tanpa kualifikasi itu entri data_operations akan terbaca seperti layar mobile mengueri DB.

## v4 — 2026-09-29

- Endpoint `/api/production-lines/options-for-report` ditambahkan sebagai endpoint TERAKHIR pada daftar, bukan disisipkan sebelum `/periods` yang mencerminkan urutan pemanggilan di layar ← empat endpoint pertama adalah milik layar web yang dipakai ulang apa adanya; endpoint kelima ini satu-satunya yang lahir dari revisi ini, dan memisahkannya di akhir membuat asal-usulnya terbaca.
- `success_schema` endpoint baru memakai kunci `_note` untuk alasan bentuk `{id, name, code}` ← mengikuti preseden yang sudah ada pada artefak ini (skema ringkasan Boiler Room juga memakai `_note`), ketimbang menaruhnya di `implementation_notes` yang jauh dari skemanya.
- `production_line` disisipkan TEPAT SETELAH `period`; ditulis sebagai string deskriptif dengan alasan yang sama seperti layar web (nullability seluruh blok tidak dapat dinyatakan objek bersarang).
- `business_logic` = delapan langkah baru ditambahkan di AKHIR ← urutan penentuan line (rute → ingatan → satu-line-otomatis → pemilih) ditulis sebagai SATU langkah bernomor di dalamnya, bukan empat langkah terpisah, karena yang mengikat adalah urutannya, bukan keempat cabangnya sendiri-sendiri.
- `unit_test_cases` = 15 kasus baru, satu per uji yang benar-benar ada (13 uji komponen + 2 perilaku repo yang tidak punya padanan komponen) ← jumlahnya sengaja mengikuti suite, bukan mengikuti jumlah cabang di `business_logic`; cabang tanpa uji tidak ditulis sebagai kasus.
- `test_scenarios` = 6 skenario baru; lima `browser_test` diisi, satu dikosongkan ← `mobile/tests/e2e/laporan-*.spec.ts` punya 7 uji browser Production Line yang nyata (dijalankan terhadap Vite dev server), tetapi tidak satu pun menguji "stasiun dipindah". Skenario itulah yang `browser_test`-nya dikosongkan.
- `data_operations` dibiarkan `[]` ← artefak layar mobile memang tidak mendaftarkan operasi data (seluruh akses data lewat API layar web), dan menambahnya hanya untuk revisi ini akan membuat satu layar berbeda dari empat saudaranya.

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v4)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); layar ini bukan jalur tulis data stasiun sehingga tidak terdampak — penilaian agen, tidak dinyatakan user per layar

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (LaporanBoilerRoomView.vue, boilerRoomReportRepo.ts, BoilerRoomReportService.php, mobile/tests/*BoilerRoom*.spec.ts).
- api_contracts[0].endpoints[2].response.success_schema.coverage = + days_counted, period_running; expected_slots pakai days_counted ← service summary()
- api_contracts[0].endpoints[3].response.success_schema._note = kolom Periode/Mill/Production Line + label + HH:MM + Ya/Tidak; excel = xlsx (tak dipakai mobile) ← EXPORT_HEADER, SheetWriter
- api_contracts[0].business_logic (+1) = repo + view: days_counted/period_running, period-running-note, '-' + coverage-no-expected, label 'Tertutup' ← diff view/repo
- api_contracts[0].unit_test_cases (+5) = repo pass-through; berjalan; selesai; penyebut 0; badge Tertutup ← LaporanBoilerRoomView.spec.ts, boilerRoomReportRepo.spec.ts (⚠ angka contoh 9/4 hari pada given disusun agent, bukan disalin dari fixture tes)
- implementation_notes (+1) = REVISI audit-fix

## v6 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit e4f231e, d5da9cf, ee5294c), code is truth (mobile/src/views/LaporanBoilerRoomView.vue, mobile/src/utils/latestRequest.ts, tests/laporanStaleResponse.spec.ts, tests/e2e/laporan-stale-response.spec.ts).
- implementation_notes ← latestRequest guard + resetSummary, nama berkas ekspor saat klik, periodsLoaded/LoadingState, FilterPanel/FilterSelectField/FilterChip.
- test_scenarios ← skenario baru respons ringkasan lama diabaikan (component laporanStaleResponse.spec.ts, browser e2e/laporan-stale-response.spec.ts).
- ⚠ scenario_ref baru belum punya bdd_scenario padanan di usecase layar ini (usecase tidak di-patch).
