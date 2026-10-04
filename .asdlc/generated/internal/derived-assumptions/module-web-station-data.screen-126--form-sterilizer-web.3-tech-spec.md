# Derived Assumptions Log — module-web-station-data.screen-126--form-sterilizer-web.3-tech-spec

## v2 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Kondisi error_codes PERIOD_CLOSED untuk create juga mencakup 'tanggal kejadian kosong' (perilaku trait: tanggal kosong ditolak, bukan ditempatkan ke periode hari ini) — dirumuskan agen.
- Urutan pemeriksaan di business_logic (validasi field -> resolve station -> kunci periode pada create; findOrFail -> cek akses -> validasi -> kunci periode pada update) diambil dari kode service, bukan dari usecase-141; akibatnya VALIDATION_ERROR/NO_ACTIVE_*_STATION mendahului PERIOD_CLOSED.
- Nomor langkah business_logic baru = lanjutan penomoran yang ada; kutipan pesan di edge_case_handling disingkat ('...') dari string asli EnforcesPeriodLock::periodLockReason().
- Rumusan given/expect 3 unit_test_cases (termasuk contoh rentang 2026-10-01..2026-10-31) dipilih agen; kasus-kasus ini SPESIFIKASI — belum ada sebagai test khusus stasiun ini (test per-layar hanya membuka periode sebagai prasyarat), dicatat terus terang di implementation_notes.
- Sitasi test: EnforcesPeriodLockTest.php dan KelolaPeriodePelaporanTest.php (jalur Sterilizer) dipilih sebagai bukti cakupan; file test per-layar disebut hanya sebagai pemakai prasyarat openPeriodFor()/openPeriodForStation().
- Stasiun ini satu-satunya yang diuji end-to-end oleh KelolaPeriodePelaporanTest.php (POST/PATCH /api/sterilizer-records), jadi catatan menyebut cakupan langsung tersebut.

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (SterilizerRecordService.php, FormSterilizer.php, form-sterilizer.blade.php, EnforcesPeriodLock.php, ScopesToActorMill.php, ApiExceptionHandler.php).
- api_contracts[0].endpoints[0].request.body_schema.production_line_id/date/details = UUID line 422, batas besok, checked_by_spv Supervisor-only, id baris bukan-UUID → baris baru ← resolveActiveStationForActor Str::isUuid; assertEventDateNotTooFarAhead; upsertDetails($actor).
- api_contracts[0].endpoints[1].request.body_schema.date/details = sama untuk PATCH.
- api_contracts[0].endpoints[0..1].response.error_codes += 422 tanggal > besok, 422 UUID line, 403 cross-mill, 422 QueryException 22P02/22007/22008, 500 generik. ⚠ 403 cross-mill diturunkan dari resolveActiveStationForActor bersama (perilaku lama, belum tercantum).
- api_contracts[0].business_logic[1] = resolve via resolveActiveStationForActor; [12] = checkbox Checked by SPV hanya Supervisor, lainnya teks Ya/Tidak ← blade @if isSupervisor().
- api_contracts[0].business_logic += 18 (SPV Supervisor-only, diabaikan bukan ditolak), 19 (batas tanggal), 20 (UUID + GuardsRecordIdShape).
- api_contracts[0].edge_case_handling += 4 kasus; business_rules_applied += 2 aturan.
- api_contracts[0].unit_test_cases += 4 ← tests/Feature/AuditFix20261004Test.php [spv]/[future]. ⚠ peran pembanding di uji render (Mill Management) diasumsikan dari judul uji, tidak dibaca isi ujinya.
- implementation_notes[7] = checked_by_spv kini Supervisor-only; += REVISI audit-fix.

## v4 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (SterilizerRecordService.php, AuditFix20261005Test.php).
- PATCH body_schema.details, business_logic[17], edge_case_handling[9], unit_test_cases (+3), implementation_notes (+1) ← spvMatchKey (sterilizer_no|HH:MM close_door_time), kandidat diklaim sekali.
- ⚠ body_schema POST tidak diubah: pada create record belum ada ($record->exists false) sehingga pewarisan tidak berlaku.
