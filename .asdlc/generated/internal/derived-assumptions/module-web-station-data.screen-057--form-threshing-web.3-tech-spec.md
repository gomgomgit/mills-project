# Derived Assumptions Log — module-web-station-data.screen-057--form-threshing-web.3-tech-spec

## v3 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Kondisi error_codes PERIOD_CLOSED untuk create juga mencakup 'tanggal kejadian kosong' (perilaku trait: tanggal kosong ditolak, bukan ditempatkan ke periode hari ini) — dirumuskan agen.
- Urutan pemeriksaan di business_logic (validasi field -> resolve station -> kunci periode pada create; findOrFail -> cek akses -> validasi -> kunci periode pada update) diambil dari kode service, bukan dari usecase-141; akibatnya VALIDATION_ERROR/NO_ACTIVE_*_STATION mendahului PERIOD_CLOSED.
- Nomor langkah business_logic baru = lanjutan penomoran yang ada; kutipan pesan di edge_case_handling disingkat ('...') dari string asli EnforcesPeriodLock::periodLockReason().
- Rumusan given/expect 3 unit_test_cases (termasuk contoh rentang 2026-10-01..2026-10-31) dipilih agen; kasus-kasus ini SPESIFIKASI — belum ada sebagai test khusus stasiun ini (test per-layar hanya membuka periode sebagai prasyarat), dicatat terus terang di implementation_notes.
- Sitasi test: EnforcesPeriodLockTest.php dan KelolaPeriodePelaporanTest.php (jalur Sterilizer) dipilih sebagai bukti cakupan; file test per-layar disebut hanya sebagai pemakai prasyarat openPeriodFor()/openPeriodForStation().

## v4 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (ThreshingRecordService.php, FormThreshing.php, EnforcesPeriodLock.php, ScopesToActorMill.php, AppTime.php, ApiExceptionHandler.php).
- api_contracts[0].endpoints[0].request.body_schema.production_line_id = bukan-UUID → 422 errors.production_line_id ← ScopesToActorMill::resolveActiveStationForActor Str::isUuid.
- api_contracts[0].endpoints[0..1].request.body_schema.date = tidak boleh melewati besok (WIB) ← create/update memanggil assertEventDateNotTooFarAhead('date').
- api_contracts[0].endpoints[0..1].response.error_codes += 422 VALIDATION_ERROR (tanggal > besok, UUID line, QueryException 22P02/22007/22008) + 500 generik ← EnforcesPeriodLock, ApiExceptionHandler. ⚠ 22P02 untuk PATCH {id} bukan-UUID diturunkan dari handler (khusus PostgreSQL), tanpa uji per layar.
- api_contracts[0].business_logic += batas atas tanggal (urutan cek), zona WIB untuk penurunan tanggal kunci periode, id detail bukan-UUID = baris baru, GuardsRecordIdShape ← diff service + FormThreshing.php.
- api_contracts[0].edge_case_handling += 4 kasus (tanggal > besok, id edit bukan UUID, line bukan UUID, id detail bukan UUID).
- api_contracts[0].business_rules_applied += batas atas tanggal.
- api_contracts[0].unit_test_cases += 2 (future, id detail bukan-UUID) ⚠ spesifikasi; tidak ada uji khusus stasiun ini — mekanismenya diuji via CPO Dispatch/Weighbridge di AuditFix20261004Test.
- implementation_notes += catatan REVISI audit-fix.
