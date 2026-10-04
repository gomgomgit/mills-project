# Derived Assumptions Log — module-web-station-data.screen-016--data-browser-weighbridge-web.3-tech-spec

## v1 — 2026-08-17

- Endpoint `GET /api/weighbridge-records` (list) dan `GET /api/weighbridge-records/export` (ekspor) beserta skema request/response ← desain teknis agent, tidak eksplisit di business spec
- Export dimodelkan sebagai response file stream/binary, bukan JSON ← keputusan desain agent
- Daftar error code: 422 INVALID_DATE_RANGE, 422 EXPORT_FAILED ← diturunkan dari edge_cases business spec
- screen_dependencies ke screen-019 (detail) dan screen-022 (form web) ← disimpulkan dari available_actions business spec
- Implementation note: export sebaiknya async/background job untuk dataset besar ← catatan implementasi agent, bukan keputusan final

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

Sumber: audit-fix 2026-10-04, code is truth (app/Services/WeighbridgeRecordService.php, app/Http/Controllers/Api/WeighbridgeRecordController.php, app/Support/SheetWriter.php, app/Support/Display.php, data-browser-weighbridge.blade.php, tests/Unit/Services/WeighbridgeRecordServiceTest.php).
- api_contracts[0].endpoints[1].response.success_schema.note = excel → .xlsx OOXML sungguhan via SheetWriter ← fileMetaFor()/export() docblock + SheetWriter
- api_contracts[0].business_logic[7] = export menulis via SheetWriter::open($format) ← export() closure
- api_contracts[0].business_logic += 11 (judul kolom label Detail + Checked By/Acknowledged By, Tipe Receive/Dispatch, 'Y-m-d H:i', Display::status) ← export() header/rows
- api_contracts[0].business_logic += 12 (blade memakai Display::status/Display::number id-ID; JSON status tetap enum) ← blade diff + docblock Display ("Nilai di API … tidak berubah")
- api_contracts[0].unit_test_cases[6].expect = xlsx 'PK' + header 'No. WB Card'/'Checked By'/'Acknowledged By' + 'Tersimpan' ← WeighbridgeRecordServiceTest export
- implementation_notes += REVISI 2026-10-04 ← ringkasan di atas
