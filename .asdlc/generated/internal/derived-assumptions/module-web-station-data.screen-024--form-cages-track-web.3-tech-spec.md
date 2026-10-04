# Derived Assumptions Log — module-web-station-data.screen-024--form-cages-track-web.3-tech-spec

## v2 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Kondisi error_codes PERIOD_CLOSED untuk create juga mencakup 'tanggal kejadian kosong' (perilaku trait: tanggal kosong ditolak, bukan ditempatkan ke periode hari ini) — dirumuskan agen.
- Urutan pemeriksaan di business_logic (validasi field -> resolve station -> kunci periode pada create; findOrFail -> cek akses -> validasi -> kunci periode pada update) diambil dari kode service, bukan dari usecase-141; akibatnya VALIDATION_ERROR/NO_ACTIVE_*_STATION mendahului PERIOD_CLOSED.
- Nomor langkah business_logic baru = lanjutan penomoran yang ada; kutipan pesan di edge_case_handling disingkat ('...') dari string asli EnforcesPeriodLock::periodLockReason().
- Rumusan given/expect 3 unit_test_cases (termasuk contoh rentang 2026-10-01..2026-10-31) dipilih agen; kasus-kasus ini SPESIFIKASI — belum ada sebagai test khusus stasiun ini (test per-layar hanya membuka periode sebagai prasyarat), dicatat terus terang di implementation_notes.
- Sitasi test: EnforcesPeriodLockTest.php dan KelolaPeriodePelaporanTest.php (jalur Sterilizer) dipilih sebagai bukti cakupan; file test per-layar disebut hanya sebagai pemakai prasyarat openPeriodFor()/openPeriodForStation().

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (CagesTrackRecordService.php, FormCagesTrack.php, form-cages-track.blade.php, EnforcesPeriodLock.php, ScopesToActorMill.php, AppTime.php, ApiExceptionHandler.php).
- api_contracts[0].endpoints[0].description + request.body_schema = production_line_id (bukan business_unit_id), date batas besok, tippler_start/stop_time normalisasi WIB, id baris bukan-UUID → baris baru ← create() resolveActiveStationForActor($data['production_line_id']); normalizeFormFields(); upsertDetails() Str::isUuid. (business_unit_id → production_line_id adalah drift lama yang ikut dibereskan.)
- api_contracts[0].endpoints[1].request.body_schema.date/tippler_start_time/tippler_stop_time/details = sama untuk PATCH.
- api_contracts[0].endpoints[0].response.error_codes[1].condition = berbasis production_line_id.
- api_contracts[0].endpoints[0..1].response.error_codes += 422 tanggal > besok, 422 UUID line, 403 cross-mill, 422 QueryException 22P02/22007/22008, 500 generik. ⚠ 403 cross-mill diturunkan dari resolveActiveStationForActor bersama (perilaku lama, belum tercantum).
- api_contracts[0].business_logic[1], [7], [14] = resolve via line; line immutable; Total/Remain teks.
- api_contracts[0].business_logic += 21 (batas tanggal), 22 (zona WIB tippler), 23 (UUID + GuardsRecordIdShape).
- api_contracts[0].edge_case_handling[3].condition = Production Line; += 4 kasus.
- api_contracts[0].business_rules_applied[2], [4] dan += 2 aturan.
- api_contracts[0].unit_test_cases += 2 (future; tz tippler). ⚠ kasus tz tippler diturunkan dari kode, belum ada uji khusus Cages Track.
- implementation_notes[2] = Total/Remain teks; += REVISI audit-fix.
