
## v1 — 2026-10-05
- route = /data-saya (nama rute 'operator.data-saya', middleware ['auth', 'role:operator']) ← agen; rute Indonesia mengikuti /beranda, bukan pola /data/{stasiun}
- auth_requirement = role: operator saja; Supervisor/Mill Management/Admin can_access=false (403, menu tersembunyi lewat RouteAccess) ← agen, dari business rule turunan Phase 2
- api_contracts.endpoints = GET /api/my-records + GET /api/my-records/{stationType}/{id}, guard 'auth:web' + role:operator (tanpa Sanctum) ← agen; layar web-only, pola proyek tiap layar punya kontrak /api yang memakai service yang sama dengan Livewire
- api_contracts.request.date_from/date_to default = [hari ini-6, hari ini] WIB juga di API (bukan "semua tanggal") ← agen, menyamakan perilaku API dengan layar
- api_contracts.request.station_type tak dikenal = diabaikan (Semua Stasiun), bukan 422 ← agen, konsisten dengan perlakuan production_line_id asing
- api_contracts.response.error_codes = detail bukan milik / mill lain / id rusak / stationType tak dikenal → satu jawaban 404 NOT_FOUND (bukan 403) ← agen, agar keberadaan record orang lain tidak terkonfirmasi (pola RecordVerificationStatusService)
- api_contracts.request.date format salah = 422 VALIDATION_ERROR (API) / pesan 'Format tanggal tidak valid' tanpa query (Livewire) ← agen, mencegah SQLSTATE 22007 PostgreSQL
- business_logic[5-6] = daftar lintas 18 tabel lewat UNION ALL SELECT ringan (CAST eksplisit kolom teks) + fromSub + ORDER BY record_date DESC, created_at DESC, id DESC + paginate ← agen; alternatif (gabung di PHP) ditolak karena pagination/NFR ≤2 detik
- business_logic[5] = cakupan mill per cabang via station_id IN (SELECT id FROM stations WHERE business_unit_id = millId), millId dari actorReadMillId() ← agen; setara scopeQueryToActorMill() tetapi lebih ringan per cabang
- business_logic[9] = peta jenis stasiun → model dipinjam dari RecordVerificationService::modelForStationType(), MyRecordsService hanya menambah peta presentasi (kolom identitas, label, kolom tanggal, service getDetail) ← agen, menghindari daftar model ke-3
- business_logic[10-11] = cek kepemilikan created_by + mill dulu, lalu payload dari {Station}RecordService::getDetail() yang sudah ada ← agen; getDetail() tidak memuat created_by id, jadi kepemilikan dicek terpisah
- implementation_notes[detail partial] = body ke-18 view Detail diekstrak ke resources/views/livewire/data/partials/detail-body-<slug>.blade.php (beserta CSS-nya) dan di-@include oleh view Detail lama DAN Data Saya ← agen, menjamin label identik; konsekuensi: menyentuh 18 view Detail yang sudah ada (dijaga uji Detail* yang ada)
- implementation_notes[ringkasan verifikasi] = strip ringkasan Data Saya di atas partial menampilkan 'Belum diperiksa'/'Tidak berlaku'/'Belum dikonfirmasi', partial sendiri tetap '-' seperti layar Detail ← agen, menyelaraskan BDD "Record Grading" (daftar + detail) tanpa mengubah tampilan layar Detail Supervisor
- implementation_notes[state URL] = #[Url] untuk date_from, date_to, stasiun, production_line_id, page, detail_station, detail_id ← agen; memenuhi "Kembali ke Daftar mempertahankan filter & halaman" + tautan langsung detail
- implementation_notes[label identitas] = wb_card_number 'No. WB Card', grading_number 'Grading Number', cages_track_number 'Cages Track Number', thresher_id 'Thresher ID', presser_id 'Presser ID' (pressing & depricarping), kernel_plant_id 'Kernel Plant ID', solid_waste_disposal_id 'Solid Waste Disp. ID', process_water_id 'Process Water ID', kernel_dispatch_id 'Kernel Dispatch ID', cpo_dispatch_id 'CPO Dispatch ID', effluent_plant_id 'Effluent Plant ID', storage_tank_id 'Storage Tank ID', engine_room_id 'Engine Room ID', boiler_room_id 'Boiler Room ID', clarification_id 'Clarification ID', process_qc_id 'Process QC ID', sterilizer_id 'Sterilizer ID' ← dibaca dari label pertama 18 view Detail yang ada (kode acuan)
- implementation_notes[index] = index (created_by, date) TIDAK ditambahkan sekarang ← agen; perubahan skema = ranah entity-catalog/tech-1-core, dataset per Operator kecil dengan default 7 hari
- implementation_notes[sidebar/beranda/WebAccessTest] = entri sidebar 'Data Saya' setelah 'Beranda' (RouteAccess), tombol 'Lihat Data Saya' di operator/home.blade.php, uji WebAccessTest yang mengasersi menu Operator diperbarui ← agen
- shared_entities = [] ← hanya satu usecase di layar ini
- screen_dependencies = screen-001 (login), Beranda Operator (/beranda), 18 layar Detail (sumber partial), Form mobile (asal record) ← agen
- test_scenarios = test scenarios derived: 30 unit, 12 API (30 langkah), 12 component, 11 browser (1 sengaja kosong: "Stasiun record dipindah ke line lain" — butuh mutasi master data di tengah uji; dibuktikan di uji Livewire + API) ← test-spec-writer-agent dari 12 BDD Phase 2; rute placeholder agen (PUT weighbridge, POST /api/records/verify) diganti rute nyata: PATCH /api/records/{stationType}/{id}/verification (403) dan GET /api/weighbridge-records/export (403)
- test_scenarios["Upaya mengubah…"].api_test[1] = POST /api/my-records → 405 tanpa error_code ← agen; kode bawaan ApiExceptionHandler untuk 405 belum diverifikasi
- implementation_notes[cakupan tulis] = Operator TETAP boleh POST/PATCH /api/{stasiun}-records (jalur sync mobile) — tidak diubah; uji "ditolak" memakai rute web Form, ekspor, dan PATCH verifikasi ← agen, dari routes/api.php (code is truth)

## v2 — 2026-10-05
- implementation_notes[detail partial] = ekstraksi body 18 view Detail ke partial bersama ← USER mengonfirmasi asumsi v1 (keputusan 1)
- implementation_notes[index] = index created_by TIDAK ditambahkan ← USER mengonfirmasi asumsi v1 (keputusan 4)
- auth_requirement = role: operator,supervisor (web & API) ← USER (keputusan 3); MM/Admin 403 ← USER
- implementation_notes[nama rute] = nama rute tetap 'operator.data-saya' walau Supervisor ikut ← agen (hindari mengganti nama yang sudah di-spec; nama bukan pembatas peran)
- business_logic[1] = satu jalur logika created_by = auth()->id() untuk kedua peran, tanpa cabang per peran ← agen
- implementation_notes[sidebar/WebAccessTest] = entri sidebar muncul otomatis untuk Supervisor lewat RouteAccess; WebAccessTest ditambah asersi Supervisor lihat 'Data Saya', MM/Admin tidak ← agen
- test_scenarios[+2 Supervisor] + unit_test_cases[+2 Supervisor] ← diminta USER; isi diturunkan agen
