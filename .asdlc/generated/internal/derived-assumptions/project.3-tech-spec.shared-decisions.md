# Derived Assumptions Log — project.3-tech-spec.shared-decisions

## v1 — 2026-08-17

- auth.mechanism / auth.session_strategy / auth.notes ← diturunkan dari arch_spec.tech_stack ("Laravel Sanctum + session auth") dan nfr.security, diterima tanpa koreksi
- error_format (structure, example, notes) ← diturunkan dari konvensi standar Laravel, tidak dinyatakan eksplisit di arch-spec
- pagination.strategy / pagination.defaults / pagination.notes ← diturunkan dari konvensi pagination bawaan Laravel, tidak dinyatakan eksplisit di arch-spec
- naming_conventions (api_endpoints, db_tables, db_columns) ← diturunkan dari tech stack Laravel/MySQL, tidak dinyatakan eksplisit di arch-spec
- other_decisions (timestamps UTC ISO 8601, file storage Laravel Filesystem local disk, UUID untuk entitas offline mobile) ← diturunkan dari constraints/goals di PRD, bukan pernyataan eksplisit di shared-decisions

## v5 — 2026-09-22

- error_format.structure += field `code` (UPPER_SNAKE_CASE, machine-readable) ← user stated mobile must be able to tell "periode ditutup" apart from an ordinary validation error so the record is not dropped from the sync queue; adding a `code` field to the shared error envelope is the agent's way of making that possible. Note this also closes a long-standing major known_issue on screen-001 ("ApiExceptionHandler belum mengeluarkan field error_code yang machine-readable"). Additive change — existing message/errors responses stay valid
- other_decisions += HTTP 422 dengan code=PERIOD_CLOSED sebagai penolakan sementara ← user stated the behaviour (ditolak, data tertahan di HP, ikut sync lagi setelah dibuka); the specific status code and code name are the agent's
- naming_conventions.notes += error code memakai UPPER_SNAKE_CASE ← agent-derived, follows the existing INVALID_DATE_RANGE / EXPORT_FAILED codes already used in the api-index
- pagination.notes += endpoint laporan periode tidak dipaginasi ← agent decision; user never discussed pagination for the new report screens
- auth.notes += catatan route ekspor menumpuk middleware 'web' ← documents the 401 root cause found and fixed earlier in this same session; user asked "kenapa unauthenticated saat export?" but did not ask for it to be recorded in shared-decisions
- autopilot: seluruh sub-bagian shared-decisions lain (auth mechanism, pagination strategy, naming conventions, integrations) dibawa apa adanya dari v4 tanpa konfirmasi ulang, sesuai Step 6 fast-path

## v6 — 2026-09-25

- `other_decisions[3]` ditulis ulang ke bentuk per-stasiun, termasuk kueri JOIN ← user tidak menyebut artefak ini sama sekali; ditemukan lewat pemeriksaan bahwa 21 kemunculan kata "period" ada di dalamnya. Tanpa pembaruan ini, `shared-decisions` akan tetap menyatakan kunci berlaku per periode sementara `entity-catalog` menyatakan per stasiun — dua sumber kebenaran yang bertentangan.
- `other_decisions[6]`: peringatan record belum terverifikasi dihitung UNTUK JENIS STASIUN YANG AKAN DITUTUP ← user tidak menyatakan cakupan hitungannya. Dipilih per-stasiun karena aksi tutup kini bertarget satu stasiun; hitungan se-periode akan memasukkan stasiun yang tidak terdampak aksi itu dan membuat peringatannya menyesatkan.
- `other_decisions[8]` (baru) "PERIODE ADALAH INDUK TANPA STATUS" ← tidak diminta; ditambahkan agar bahaya atribut-hilang-jadi-null terdokumentasi di tingkat proyek, bukan hanya di constraint satu entitas, karena yang terdampak tersebar di service, Livewire, dan lima ReportService.

## v7 — 2026-09-28

- Lima butir `other_decisions` baru ← user menetapkan empat keputusan isolasi line, tetapi tidak meminta pencatatannya di artefak. Dicatat karena tanpa itu rumah tunggal `ScopesToActorMill` akan terbaca sebagai pilihan gaya, bukan sebagai jawaban atas enam salinan `resolveBusinessUnit()` yang menyebabkan FormSterilizer benar sementara 15 saudaranya tidak.
- Butir "empat kebocoran yang ditutup" memuat cara pembuktiannya ← tidak diminta. Dimasukkan karena ketiganya terbukti lewat probe atau pembacaan kode, dan artefak yang hanya menyatakan "sudah ditutup" tidak memberi pembaca berikutnya cara memeriksanya lagi.
- Butir "kunci periode tidak bertambah dimensi line" memuat konsekuensi yang tidak diminta user ← yakni bahwa Line 1 tidak dapat ditutup selama Line 2 menyisakan record belum terverifikasi. Itu akibat nyata dari keputusannya dan layak tercatat sebelum ada yang menganggapnya bug.

## v8 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (config/app.php, AppTime, EnforcesPeriodLock, PeriodService, PeriodHasRecordsException, ApiExceptionHandler, EnsureUserIsActive, AppServiceProvider, UserService, PasswordPolicy, RouteAccess, GuardsRecordIdShape, SheetWriter, ExportValue, Display, ReportPeriodDays, ChartAxis, UniqueCaseInsensitive, RealImage, ValidatesUploadOnSelect, mobile utils/localDate.ts, localSchema.ts).
- auth.notes = + akun nonaktif (EnsureUserIsActive web+api, 401/redirect berpesan; Sanctum menolak token user nonaktif; token dicabut saat nonaktif) + PasswordPolicy tunggal + RouteAccess sidebar/403 + Operator boleh login web terbatas (/beranda, /settings/password) ← kode
- error_format.notes = + 409 PERIOD_HAS_RECORDS; QueryException 22P02/22007/22008 → 422 VALIDATION_ERROR, lainnya 500 generik tanpa teks SQL ← ApiExceptionHandler
- naming_conventions.notes = + PERIOD_CLOSED_IMMUTABLE, PERIOD_HAS_RECORDS, VALIDATION_ERROR ← kode yang dipancarkan handler/exception
- other_decisions[0] = ZONA WAKTU WIB (Asia/Jakarta) menggantikan "Timestamps disimpan UTC format ISO 8601"; normalisasi input ber-zona; mobile tanggal lokal ← config/app.php + AppTime + localDate.ts
- other_decisions += BATAS ATAS TANGGAL KEJADIAN (besok WIB, EVENT_DATE_MAX_DAYS_AHEAD) ← AppTime::latestEventDate + assertEventDateNotTooFarAhead
- other_decisions += KUNCI PERIODE PER BARIS DETAIL (CPO/Kernel Dispatch, Solid Waste) + kalimat 'verify' ← EnforcesPeriodLock
- other_decisions += PERIODE YANG MEMBINGKAI DATA TIDAK BOLEH DIHAPUS (409) ← PeriodService/PeriodHasRecordsException
- other_decisions += ID BUKAN-UUID = TIDAK DITEMUKAN (GuardsRecordIdShape; UUID divalidasi di service) ← kode
- other_decisions += EKSPOR TABEL (SheetWriter xlsx sungguhan, ExportValue vs Display) ← kode
- other_decisions += KELENGKAPAN LAPORAN PERIODE (ReportPeriodDays, days_counted/period_running, ChartAxis) ← kode; "respons laporan membawa days_counted/period_running" digeneralisasi agen — terverifikasi di Weighbridge/StorageTank/Clarification/BoilerRoom report service
- other_decisions += VALIDASI MASTER DATA & UNGGAHAN (UniqueCaseInsensitive, RealImage, ValidatesUploadOnSelect) ← kode

## v9 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (EnsureUserIsActive.php, AppServiceProvider.php, UserService.php, ScopesToActorMill.php, Display.php, ExportValue.php, RecordVerification*Controller.php, mobile apiClient.ts/auth.ts/errorHandler.ts/main.ts/optionLabel.ts).
- auth.notes += reset password mencabut sesi (sessions_revoked_at, REVOKED_MESSAGE) + penanganan 401 terpusat mobile.
- error_format.notes += verifikasi 404/403 via abort → NOT_FOUND/FORBIDDEN; 409 hapus Station ber-record (StationHasMachineryException); SESSION_REVOKED_MESSAGE mobile.
- other_decisions += filter baca bukan-UUID diabaikan; peta label enum tunggal (Display/ExportValue/optionLabel.ts).
- ⚠ 'handler hanya berjalan sekali per sesi' ← disimpulkan dari pemeriksaan Authorization = token sesi saat ini (bukan flag eksplisit).

## v10 — 2026-10-05

Sumber: perbaikan lanjutan audit 2026-10-05 (belum di-commit), code is truth (mobile syncService.ts/writeThroughSync.ts; backend StationHasMachineryException/BusinessUnitHasStationsException/ProductionLineHasStationsException).
- auth.notes += SINKRON BERHENTI DI 401 PERTAMA (manual + write-through, tanpa sync_error).
- error_format.notes += 3 delete-guard 409 kini membawa code (HasErrorCode); kasus 'record stasiun' Station juga STATION_HAS_MACHINERY.
- ⚠ 'Delete-guard lain (Company/Corporate/MachineryGroup) belum membawa code' — dicatat agen sebagai sisa inkonsistensi, tidak diubah (di luar cakupan perintah).
