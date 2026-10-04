# Derived Assumptions Log — module-web-station-data.screen-022--form-weighbridge-web.3-tech-spec

## v2 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Kondisi error_codes PERIOD_CLOSED untuk create juga mencakup 'tanggal kejadian kosong' (perilaku trait: tanggal kosong ditolak, bukan ditempatkan ke periode hari ini) — dirumuskan agen.
- Urutan pemeriksaan di business_logic (validasi field -> resolve station -> kunci periode pada create; findOrFail -> cek akses -> validasi -> kunci periode pada update) diambil dari kode service, bukan dari usecase-141; akibatnya VALIDATION_ERROR/NO_ACTIVE_*_STATION mendahului PERIOD_CLOSED.
- Nomor langkah business_logic baru = lanjutan penomoran yang ada; kutipan pesan di edge_case_handling disingkat ('...') dari string asli EnforcesPeriodLock::periodLockReason().
- Rumusan given/expect 3 unit_test_cases (termasuk contoh rentang 2026-10-01..2026-10-31) dipilih agen; kasus-kasus ini SPESIFIKASI — belum ada sebagai test khusus stasiun ini (test per-layar hanya membuka periode sebagai prasyarat), dicatat terus terang di implementation_notes.
- Sitasi test: EnforcesPeriodLockTest.php dan KelolaPeriodePelaporanTest.php (jalur Sterilizer) dipilih sebagai bukti cakupan; file test per-layar disebut hanya sebagai pemakai prasyarat openPeriodFor()/openPeriodForStation().
- Tanggal kejadian Weighbridge = bagian tanggal dari record_datetime (trait menormalkan ke Y-m-d); contoh batas inklusif memakai jam 08:00/17:00.

## v3 — 2026-10-03

Pembersihan teks spec usang / sinkronisasi dengan perbaikan 2026-10-03.
- Body POST /api/weighbridge-records kini mendokumentasikan production_line_id sebagai 'wajib secara praktis' (bukan required di validator): kosong/tidak dikenal menghasilkan 422 NO_ACTIVE_WEIGHBRIDGE_STATION, bukan VALIDATION_ERROR — dibaca dari resolveActiveStationForActor() yang mengembalikan null.
- Error code 403 FORBIDDEN (production line milik mill lain) dan edge case-nya ditambahkan karena terbukti di ScopesToActorMill::assertMillWritable(); tidak tercantum di versi spec sebelumnya.
- request_example test_scenarios diganti ke production_line_id; scenario_ref 'Business Unit Tanpa Station Weighbridge Aktif' sengaja TIDAK diganti agar tetap cocok dengan nama BDD Phase 2.
- business_rules_applied 'Business Unit tidak berubah' diperluas menjadi 'Business Unit / Production Line (station)'.

## v4 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (WeighbridgeRecordService.php, FormWeighbridge.php, form-weighbridge.blade.php, EnforcesPeriodLock.php, ScopesToActorMill.php, AppTime.php, ApiExceptionHandler.php).
- api_contracts[0].endpoints[0..1].request.body_schema.record_datetime = normalisasi zona ke WIB + batas atas besok ← normalizeFormFields() memanggil AppTime::normalizeClientDateTime; create/update memanggil assertEventDateNotTooFarAhead.
- api_contracts[0].endpoints[0].request.body_schema.production_line_id = bukan-UUID → 422 errors.production_line_id ← ScopesToActorMill::resolveActiveStationForActor Str::isUuid.
- api_contracts[0].endpoints[0..1].response.error_codes += 422 VALIDATION_ERROR (tanggal > besok, UUID line, QueryException 22P02/22007/22008) + 500 generik ← EnforcesPeriodLock, ApiExceptionHandler. ⚠ Penerapan 22P02 untuk PATCH {id} bukan-UUID diturunkan dari handler (PostgreSQL), tidak ada uji API khusus per layar.
- api_contracts[0].business_logic[7] = mode edit menampilkan business_unit_name + production_line_name; line kosong → teks ← FormWeighbridge::mount, form-weighbridge.blade.php.
- api_contracts[0].business_logic[9] = prefill setTimezone WIB ← FormWeighbridge::mount.
- api_contracts[0].business_logic[10] = Net Weight teks read-only ← blade span net-weight-preview.
- api_contracts[0].business_logic += 16 (batas atas tanggal), 17 (zona WIB), 18 (GuardsRecordIdShape).
- api_contracts[0].edge_case_handling += 5 kasus (tanggal > besok, input ber-Z, id bukan UUID, line bukan UUID, line kosong → teks).
- api_contracts[0].business_rules_applied[2] dan += 2 aturan (batas tanggal, zona WIB).
- api_contracts[0].unit_test_cases += 3 (future, tz, uuid) ← tests/Feature/AuditFix20261004Test.php.
- implementation_notes[2] = Net Weight teks, bukan disabled; implementation_notes += catatan REVISI audit-fix.
