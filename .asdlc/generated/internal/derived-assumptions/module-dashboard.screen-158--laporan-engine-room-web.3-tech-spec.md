# Derived Assumptions — module-dashboard.screen-158--laporan-engine-room-web.3-tech-spec

## v1 — 2026-10-14

· `api_contracts[0].endpoints` = 4 rute (`/periods`, `/business-units/options`, `/summary`, `/export`) di bawah prefix `/api/engine-room-reports` ← bentuk yang sama dipakai dua belas laporan stasiun terdahulu; tidak dinyatakan user
· `actor_permissions` Operator = `can_access: false` untuk rute web `/reports/engine-room`, `true` untuk tiga rute API data ← Operator diterima lebih awal mengikuti preseden Grading/Kernel Plant karena screen-159 (kembaran mobile) menyusul langsung; menunda berarti tiga berkas test + empat field artefak harus disentuh dua kali
· `business_logic` penjagaan mill Operator = cabang terikat-mill di `resolveBusinessUnit()` ← melebarkan daftar peran saja adalah kebocoran lintas-mill; tidak dinyatakan user
· `/business-units/options` tetap Admin-saja ← penegakan ada DI DALAM service, bukan middleware, karena middleware `report.access` memang menerima empat peran
· `business_logic` keanggotaan periode = `whereDate('date', '>=' / '<=')` ← kolom `date` bertipe DATE; konvensi sama dengan Boiler Room dan Effluent Plant; tidak dinyatakan user
· `metrics[]` membawa field `group` (enam nilai: Turbin Uap, Genset 1, Genset 2, Kelistrikan Tersinkron, Kompresor, Penunjang) ← blade membangun enam blok DARI respons, bukan dari daftar kolom keras di template
· `electrical_sync_total_factory_load_kw` = rata-rata per tanggal ← kolom beban sesaat; agregasi rata-rata adalah satu-satunya yang bermakna
· `daily_energy_export_kwh` = nilai TERAKHIR per tanggal, TANPA rata-rata periode ← semantik kolom TIDAK terverifikasi (lihat open_question #1); "terakhir" diambil dari posisi di `canonicalTimeSlots()`, bukan perbandingan jam leksikal, karena grid berakhir 06:00 sehingga urutan leksikal salah memilih 06:00 sebagai paling awal
· Dua seri harian dengan agregasi BERBEDA dinyatakan di layar ← pembaca tidak boleh menyangka keduanya sejenis
· `filled_slots` = baris dengan ≥1 dari **27** kolom terisi (termasuk 2 kolom teks bebas) ← mewarisi `isRowFilled()`; akibatnya `filled_slots` dapat MELEBIHI penyebut setiap metrik, dan sebabnya dinyatakan di blok kelengkapan
· `READING_FIELDS` dan `isRowFilled()` dibuka dari `protected` ke `public` di `EngineRoomRecordService` ← laporan MEMINJAM definisi kolom; empat sibling (BoilerRoom, KernelPlant, Clarification, StorageTank) sudah public untuk alasan yang sama
· `expected_slots` = jumlah unit × 24 × hari periode yang SUDAH lewat (berhenti di hari ini) ← periode berjalan tidak boleh terlihat tidak lengkap karena hari yang belum terjadi
· `expected_slots == 0` → `coverage_percent` dirender '—' bukan '0,0%' ← nol slot diharapkan bukan nol kelengkapan
· TIDAK ADA penandaan nilai di luar batas ← tidak ada tabel master target untuk stasiun ini; ambang tidak diturunkan dari data periode (lihat open_question #4)
· TIDAK ADA key gabungan/selisih/ketidakseimbangan genset di payload ← tanpa master target tidak ada standar bersama; catatan di layar menyebut kesejajaran FISIK saja, berbeda dari pola `shares_standard_with` Kernel Plant
· `standby` tidak dilipat ke `off` pada sebaran status ← melipatnya melaporkan genset mati yang tidak pernah terjadi
· Ketiga nilai enum selalu dirender walau berjumlah 0, terpisah dari baris "tidak tercatat" ← "tidak pernah siaga" harus terbaca berbeda dari "tidak dilaporkan"
· Label nilai status = `ExportValue::option()` → Run/Standby/Off ← keputusan user 2026-10-14; blade MENDELEGASIKAN ke `Display::option()`, tidak menyalin map-nya
· `format` ekspor = `csv|excel` saja; nilai lain → 422 `VALIDATION_ERROR` ← dua belas laporan terdahulu sama; xlsx ditulis `App\Support\SheetWriter`
· `unit_recap` dikunci `engine_room_id` ← satu line dapat punya beberapa unit Engine Room; unit tanpa pembacaan tetap muncul sebagai baris, tidak dihilangkan
· Kode galat dinormalkan ke `VALIDATION_ERROR` / `FORBIDDEN` / `NOT_FOUND` ← agen test-spec mengarang `MILL_NOT_RESOLVED` dan `PERIOD_NOT_FOUND`; keduanya TIDAK ADA di `backend/app` maupun `backend/tests`, dan test API yang sudah ada hanya menegaskan status HTTP
· `test_scenarios` = 35 entri, satu per `bdd_scenarios` usecase-164, berurutan ← tidak ada skenario yang ditambahkan maupun dihilangkan
· Asersi label pendek menyasar `[data-status-count]` di dalam barisnya, bukan memindai substring kontainer ← pelajaran yang sudah dibayar tiga kali (screen-156 `toContainText('On')`, screen-157 `not.toContainText('78')`)
· `project.3-tech-spec.api-index` v80 ← empat endpoint Engine Room DITAMBAHKAN via `op: "append"`, 202 entri tidak disentuh

### Koreksi di tempat, 2026-10-15 (ber-`ver` sama, sesuai PATTERNS § Inline correction — belum ada node hilir saat ditambal)

Implementasi membuktikan TIGA butir spec ini salah saat ditulis. Ketiganya sudah diperbaiki langsung di artefak, dan koreksinya juga tercatat di `implementation_notes` butir 15–16.

- **`metrics` = 22 kunci, bukan 23** ← unit case idx 34 menyebut 23 sementara `success_schema.metrics._note` dan business_logic langkah 12 dan 14 menyebut 22. Yang benar 22; `daily_energy_export_kwh` punya kuncinya sendiri. Case-nya diperbaiki dan sekarang menegaskan `count(metrics) === 22` DAN absennya kolom energi dari `metrics`.
- **Empat fixture memakai perbandingan jam leksikal yang melawan aturan layar ini sendiri** ← unit case idx 43/44/46 dan `test_scenarios[14]` memasang slot 06:00 lalu mengharapkan nilai dari jam terbesar. Kisi `(7+i)%24` menaruh 06:00 di POSISI 23 alias paling akhir, jadi fixture lama memulangkan jawaban berbeda dari yang tertulis. Diganti ke 07:00/12:00/18:00 plus satu kasus jebakan 06:00 eksplisit. **Ini butir terpenting:** membangun ulang layar dari spec versi lama akan MENGEMBALIKAN cacat `sortBy([closure, closure])` yang baru ditemukan, dan menyalin fixture lamanya justru mendorong "perbaikan" ke arah pengurutan leksikal.
- **Hook `data-*` pada `test_scenarios` ilustratif, bukan kontrak** ← sebagian tidak pernah dirender dan mock yang disetujui tidak memuat satu pun atribut `data-*`. Yang berlaku adalah hook yang benar-benar dirender blade. `data-status-count` tidak ada — jumlah per nilai ada di `<td>` kedua di dalam baris `[data-status-value]`.
