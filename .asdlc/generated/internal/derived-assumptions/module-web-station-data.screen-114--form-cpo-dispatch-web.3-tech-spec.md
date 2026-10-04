# Derived Assumptions Log — module-web-station-data.screen-114--form-cpo-dispatch-web.3-tech-spec

## v2 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Kondisi error_codes PERIOD_CLOSED untuk create juga mencakup 'tanggal kejadian kosong' (perilaku trait: tanggal kosong ditolak, bukan ditempatkan ke periode hari ini) — dirumuskan agen.
- Urutan pemeriksaan di business_logic (validasi field -> resolve station -> kunci periode pada create; findOrFail -> cek akses -> validasi -> kunci periode pada update) diambil dari kode service, bukan dari usecase-141; akibatnya VALIDATION_ERROR/NO_ACTIVE_*_STATION mendahului PERIOD_CLOSED.
- Nomor langkah business_logic baru = lanjutan penomoran yang ada; kutipan pesan di edge_case_handling disingkat ('...') dari string asli EnforcesPeriodLock::periodLockReason().
- Rumusan given/expect 3 unit_test_cases (termasuk contoh rentang 2026-10-01..2026-10-31) dipilih agen; kasus-kasus ini SPESIFIKASI — belum ada sebagai test khusus stasiun ini (test per-layar hanya membuka periode sebagai prasyarat), dicatat terus terang di implementation_notes.
- Sitasi test: EnforcesPeriodLockTest.php dan KelolaPeriodePelaporanTest.php (jalur Sterilizer) dipilih sebagai bukti cakupan; file test per-layar disebut hanya sebagai pemakai prasyarat openPeriodFor()/openPeriodForStation().
- Ditegaskan bahwa kunci periode memakai tanggal header `date`, BUKAN event_date per baris Log Kejadian (diverifikasi dari pemanggilan assertPeriodOpenForWrite di service).

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (CpoDispatchRecordService.php, EnforcesPeriodLock.php, ScopesToActorMill.php, ApiExceptionHandler.php, FormCpoDispatch.php, form-cpo-dispatch.blade.php).
- api_contracts[0].endpoints[0..1].request.body_schema.{production_line_id,date,details} = UUID line 422; date <= besok; event_date sah, <= besok, di periode Terbuka; id baris bukan-UUID → baris baru ← resolveActiveStationForActor, assertEventDateNotTooFarAhead, validateDetails(), assertDetailEventDatesWritable, upsertDetails Str::isUuid.
- api_contracts[0].endpoints[0..1].response.error_codes += 422 VALIDATION_ERROR (date/details/line), 422 PERIOD_CLOSED baris detail, 422/500 QueryException ← kode di atas. ⚠ 22P02 untuk PATCH {id} bukan-UUID diturunkan dari handler, tanpa uji per layar.
- api_contracts[0].business_logic[8] = + GuardsRecordIdShape; [11] = Net Weight teks; [16] = kunci periode juga per baris (baru + lama); += 18 (batas atas tanggal), 19 (id baris bukan-UUID), 20 (pemetaan error Livewire: errors.details → $detailError ⚠ dibaca dari pola FormThreshing::save, diasumsikan sama di FormCpoDispatch).
- api_contracts[0].edge_case_handling += 6 kasus; business_rules_applied[1] + 2 aturan baru.
- api_contracts[0].unit_test_cases += 4 ← tests/Feature/AuditFix20261004Test.php.
- implementation_notes[2] = Net Weight teks; implementation_notes += REVISI audit-fix.
