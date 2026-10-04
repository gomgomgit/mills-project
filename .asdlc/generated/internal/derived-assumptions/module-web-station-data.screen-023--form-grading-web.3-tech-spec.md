# Derived Assumptions Log — module-web-station-data.screen-023--form-grading-web.3-tech-spec

## v1 — 2026-08-20

- **WB Card No / Business Unit / Quality Parameter dropdowns all populated via direct Livewire queries in mount()/updated hooks, not dedicated REST endpoints** ← follows the exact established convention from screen-022 (Business Unit) and screen-034 (patterns for master-data option lists) — no new endpoint pattern introduced.
- **`details[]` upsert semantics (insert rows without id, update rows with id, delete rows removed from the array) run inside a DB transaction** — not explicitly specified by the user; standard data-integrity practice for a header+child-grid save operation, same shape as how mobile's `grading-detail` upsert already works (per screen-011 tech spec's existing business_logic), just transactionally wrapped since web allows partial re-submission on every save (not append-only draft state like mobile).
- **`create()` inserts the GradingRecord with `status=Synced` first, then flips to `status=saved` after details are inserted** — a technical necessity discovered while implementing: `GradingRecord::booted()`'s `saving` guard rejects a brand-new `status=saved` record with zero details (matching the same reasoning `GradingRecordFactory`'s own default already documented). Not a business rule — purely a persistence-ordering detail to satisfy the existing model invariant, invisible to the end user (final response always reports `status=saved`).
- **Server-side re-validation of "no duplicate grading_parameter_id across rows"** — the business spec's UI-level exclusion (dropdown hides already-used parameters) is a client-side convenience only; the server independently re-validates and rejects duplicates with 422, since the UI safeguard can't be trusted alone (direct API calls, race conditions).
- **`vehicle_code` genuinely optional in the request schema** — this required a companion database migration (`grading_records.vehicle_code` was still `NOT NULL` despite mobile's UI-only relaxation earlier this session); see `project.3-tech-spec.entity-catalog`'s own v8 log entry for the full account. `doctrine/dbal` added to `composer.json` as a result — first migration in this project needing a column CHANGE.

## v2 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Kondisi error_codes PERIOD_CLOSED untuk create juga mencakup 'tanggal kejadian kosong' (perilaku trait: tanggal kosong ditolak, bukan ditempatkan ke periode hari ini) — dirumuskan agen.
- Urutan pemeriksaan di business_logic (validasi field -> resolve station -> kunci periode pada create; findOrFail -> cek akses -> validasi -> kunci periode pada update) diambil dari kode service, bukan dari usecase-141; akibatnya VALIDATION_ERROR/NO_ACTIVE_*_STATION mendahului PERIOD_CLOSED.
- Nomor langkah business_logic baru = lanjutan penomoran yang ada; kutipan pesan di edge_case_handling disingkat ('...') dari string asli EnforcesPeriodLock::periodLockReason().
- Rumusan given/expect 3 unit_test_cases (termasuk contoh rentang 2026-10-01..2026-10-31) dipilih agen; kasus-kasus ini SPESIFIKASI — belum ada sebagai test khusus stasiun ini (test per-layar hanya membuka periode sebagai prasyarat), dicatat terus terang di implementation_notes.
- Sitasi test: EnforcesPeriodLockTest.php dan KelolaPeriodePelaporanTest.php (jalur Sterilizer) dipilih sebagai bukti cakupan; file test per-layar disebut hanya sebagai pemakai prasyarat openPeriodFor()/openPeriodForStation().

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (GradingRecordService.php, FormGrading.php, form-grading.blade.php, EnforcesPeriodLock.php, ScopesToActorMill.php, AppTime.php, ApiExceptionHandler.php).
- api_contracts[0].endpoints[0].description + request.body_schema = production_line_id (bukan business_unit_id), date normalisasi WIB + batas besok, weighbridge_record_id 'bail', grading_parameter_id/id baris cek UUID ← create() memanggil resolveActiveStationForActor($data['production_line_id']); normalizeFormFields(); validateForm(); validateDetails(); upsertDetails(). (Koreksi business_unit_id → production_line_id adalah drift lama yang ikut dibereskan.)
- api_contracts[0].endpoints[1].request.body_schema.date/weighbridge_record_id/details = sama untuk PATCH.
- api_contracts[0].endpoints[0].response.error_codes[1].condition = berbasis production_line_id.
- api_contracts[0].endpoints[0..1].response.error_codes += 422 (tanggal > besok, UUID), 403 FORBIDDEN cross-mill, 422 QueryException 22P02/22007/22008, 500 generik. ⚠ 403 cross-mill diturunkan dari resolveActiveStationForActor bersama (perilaku lama, tidak tercantum sebelumnya).
- api_contracts[0].business_logic[1], [6], [8], [12] = resolve via production_line_id; line tidak diterima di update; select Production Line tidak pernah disabled + GuardsRecordIdShape; UOM/Percentage teks.
- api_contracts[0].business_logic += 19 (batas tanggal), 20 (zona WIB), 21 (UUID sebelum SQL).
- api_contracts[0].edge_case_handling[3].condition = Production Line; += 4 kasus (tanggal > besok, UUID tidak sah, id baris bukan UUID, {id} bukan UUID).
- api_contracts[0].business_rules_applied[3], [4] dan += 2 aturan.
- api_contracts[0].unit_test_cases += 3 ← tests/Feature/AuditFix20261004Test.php [uuid]/[future].
- implementation_notes[2] = UOM/Percentage teks; += REVISI audit-fix.
