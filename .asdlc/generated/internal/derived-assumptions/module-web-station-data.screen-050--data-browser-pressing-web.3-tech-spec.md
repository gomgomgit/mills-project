# Derived Assumptions — module-web-station-data.screen-050--data-browser-pressing-web.3-tech-spec

## v3 — 2026-09-29

- `business_logic` = tiga langkah baru (cakupan aktor, filter di kolom record, kolom `production_line_name`) DISISIPKAN setelah langkah 1, satu langkah ekspor disisipkan tepat setelah langkah ekspor yang sudah ada, lalu SELURUH daftar dinomori ulang ← briefing tidak menentukan posisinya. Menempelkannya di akhir akan meletakkan "terapkan cakupan" sesudah "kembalikan file stream", padahal di kode ia pernyataan PERTAMA `buildFilteredQuery()`. Penomoran ulang aman karena nomor di sini hanya urutan baca, bukan tautan ke apa pun.
- `success_schema.data[0].production_line_name` diletakkan TEPAT SEBELUM `status` ← mengikuti urutan kunci `toListRow()` di service (18 dari 18 menaruhnya di sana), bukan di kolom pertama seperti tampilan tabel. Bentuk respons dan urutan kolom layar memang dua hal berbeda, dan yang mengikat pembaca API adalah yang pertama.
- `production_line_id` disisipkan tepat setelah `business_unit_id` pada query_params kedua endpoint ← urutan yang sama dengan `$request->only([...])` di controller, sehingga daftar di artefak dapat dibaca berdampingan dengan kodenya.
- `data_operations` = ditambah DUA entri `production-line`, bukan satu ← keduanya kueri nyata yang berbeda tujuannya: satu memberi opsi dropdown, satu memeriksa keberadaan line untuk menjepitnya ke mill. Menggabungkannya akan menyembunyikan bahwa penjepitan memang menyentuh tabel `production_lines` — justru bagian yang tidak boleh hilang, karena SQLite memperlakukan kolom tak dikenal pada WHERE sebagai literal string (0 baris, tanpa error).
- `unit_test_cases` = 5 kasus baru ditulis dalam BAHASA INGGRIS ← mengikuti gaya kasus yang sudah ada pada artefak ini; keduanya bercampur dalam satu daftar akan terbaca seperti dua sumber yang berbeda.
- `test_scenarios[*].component_test.component` pada empat skenario baru = nama kelas Livewire NYATA (`DataBrowser<Stasiun>`), berbeda dari nama pada skenario lama yang sebagian berbunyi `<Stasiun>DataBrowser` ← kode adalah sumber kebenaran, tetapi nama lama TIDAK saya ubah: ia tidak basi untuk revisi ini dan menyentuhnya akan mengubah baris yang tidak diminta. Ketidakseragaman itu disengaja dan dicatat di sini.
- `test_scenarios[*].browser_test` pada keempat skenario Production Line = tiga string KOSONG ← tidak ada uji browser untuk filter ini; `e2e-web/tests/data-browser-*.spec.ts` tidak menyentuhnya sama sekali. Skema mewajibkan objek berisi tiga string, jadi "tidak ada" hanya dapat dinyatakan sebagai string kosong. Celahnya ditulis eksplisit di `implementation_notes` agar tidak terbaca sebagai kelalaian pengisian.
- `shared_entities` SENGAJA TIDAK ditambah ← artefak-artefak ini sudah mendaftarkan entitas pendukung hanya di `data_operations`; menambah `production-line` di dua tempat akan membuatnya menonjol berbeda dari entri sejenis.
- `business_logic` langkah "Build query ... via station->business_unit_id" TIDAK diubah ← langkah itu memang tentang cakupan mill (revisi 2026-09-28, di luar cakupan tugas ini), dan langkah baru di atasnya sudah menyatakan bahwa `scopeFiltersToActorMill()` berjalan lebih dulu. Dicatat sebagai ketidakcocokan yang tersisa, bukan diperbaiki diam-diam.

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v3)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); layar ini bukan jalur tulis data stasiun sehingga tidak terdampak — penilaian agen, tidak dinyatakan user per layar

## v4 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (app/Services/PressingRecordService.php::export(), app/Support/SheetWriter.php, app/Support/ExportValue.php, app/Support/Display.php, data-browser-pressing.blade.php).
- api_contracts[0].endpoints[1].response.success_schema.note = excel → .xlsx OOXML via SheetWriter ← export()/fileMetaFor()
- api_contracts[0].business_logic[8] = langkah ekspor menulis via SheetWriter::open($format) ← export() closure
- api_contracts[0].business_logic += format nilai ekspor (status Indonesia, Time Slot HH:MM tanpa detik (ExportValue::time/status); judul kolom tetap) ← export() rows
- api_contracts[0].business_logic += blade Display::status (JSON status tetap enum) ← blade diff + docblock Display
- api_contracts[0].unit_test_cases += export excel xlsx sungguhan ← PressingRecordServiceTest export assert 'PK' + xlsxRows (assert jam/status di kasus uji = ⚠ ringkasan agen dari kode, tidak semuanya di-assert uji)
- implementation_notes += REVISI 2026-10-04

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (backend/app/Support/Concerns/ScopesToActorMill.php, backend/tests/Feature/AuditFix20261005Test.php, e2e-web/tests/audit-fix-20261005.spec.ts).
- api_contracts[0].endpoints[*].request.query_params production_line_id/business_unit_id.description ← nilai bukan UUID kini diabaikan (= semua line / semua mill) tanpa query, Str::isUuid() di ScopesToActorMill.
- api_contracts[0].edge_case_handling ← +1 kasus nilai filter bukan UUID (audit #6).
- api_contracts[0].unit_test_cases ← +1 uji filter bukan UUID (AuditFix20261005Test #6, dataset data_browser_stations).
- implementation_notes ← +1 catatan REVISI 2026-10-05 (validasi bentuk filter, uji binding query).
- ⚠ Tidak ditambah test_scenarios baru: tidak ada bdd_scenario Phase 2 untuk nilai filter bukan UUID (skema: satu test_scenario per BDD); cakupan dicatat di unit_test_cases + implementation_notes.

## v6 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit 658cedc, c321f32), code is truth (data-browser-*.blade.php, DataBrowser*.php, HasFilterReset.php, components/filter/*, loading-assets.blade.php, SignalDownloadReady.php).
- api_contracts[0].edge_case_handling[0].handling ← reset filter ada di x-filter.bar, bukan di empty state
- test_scenarios[1].component_test.assert ← Reset filter berada di bar filter (filter-reset), empty state tetap pesan 'Tidak ada data'
- test_scenarios[1].browser_test.assert ← Reset filter berada di bar filter (filter-reset), empty state tetap pesan 'Tidak ada data'
- implementation_notes[+] ← catatan x-filter.bar + HasFilterReset + loading (.ld-region, pagination disabled saat memuat, tautan ekspor sibuk)
