## v1 — 2026-08-20

- Response resolves checked_by/acknowledged_by to display names (checked_by_name/acknowledged_by_name) via join with User, rather than returning raw UUIDs ← not explicitly requested; inferred as necessary for a human-readable read-only detail screen (list screen-016 doesn't need this, but a detail view showing raw UUIDs would be unusable)
- station_name resolved via join with Station, same reasoning ← inferred
- Route `/data/weighbridge/{id}` ← taken directly from screen-016's own test_scenarios ("browser navigates to /data/weighbridge/{id}"), not independently chosen
- 404 RECORD_NOT_FOUND error code naming ← follows the existing error_format/naming convention already used elsewhere in shared-decisions, not explicitly specified for this screen

## v3 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Endpoint PATCH /api/records/{stationType}/{id}/verification didokumentasikan sebagai endpoint ke-2 di api_contracts[0] (sebelumnya tech-spec hanya memuat GET detail; aksi verifikasi 2026-09-14 belum pernah didokumentasikan). Dicatat bahwa layar Detail web memanggil RecordVerificationService langsung lewat aksi Livewire, bukan lewat HTTP — endpoint adalah padanan API-nya.
- error_codes diturunkan dari kode + RecordVerificationTest: 403 peran (respons hanya message, error_code ditulis FORBIDDEN), 403 FORBIDDEN mill lain, 404 (stationType/id), 422 VALIDATION_ERROR, 422 PERIOD_CLOSED. Format respons API memakai kunci `code`, bukan `error_code`.
- Wording 2 edge case + 2 unit test case dipilih sendiri; edge case mill lain menyebut alert $verificationMessage (ditangkap sebagai AuthorizationException di trait).

## v4 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (backend/app/Livewire/Data/DetailWeighbridge.php, backend/resources/views/livewire/data/detail-weighbridge.blade.php, backend/app/Livewire/Data/Concerns/GuardsRecordIdShape.php, backend/app/Support/Display.php, backend/app/Support/Concerns/EnforcesPeriodLock.php, backend/app/Services/RecordVerificationService.php, backend/app/Exceptions/ApiExceptionHandler.php).
- api_contracts[0].endpoints[0].response.error_codes += 422 VALIDATION_ERROR untuk id bukan UUID ← ⚠ INFERENSI: route GET tanpa whereUuid dan getDetail() tanpa guard, sehingga di PostgreSQL findOrFail('abc') -> QueryException 22P02 -> ApiExceptionHandler memetakan 22P02 ke 422; tidak diuji khusus untuk endpoint ini (di SQLite tetap 404)
- api_contracts[0].business_logic += langkah AUDIT-FIX (guard UUID di mount, format Display, kalimat 'verify') ← diff Detail*.php/blade + EnforcesPeriodLock::periodLockReason($action) + RecordVerificationService
- api_contracts[0].edge_case_handling += {id} bukan UUID; penolakan verifikasi berkalimat verifikasi ← kode yang sama
- api_contracts[0].unit_test_cases += non-UUID id -> notFound tanpa query ; kalimat verify-msg ; tampilan Indonesia ← RecordIdShapeGuardTest / AuditFix20261004Test
- implementation_notes += catatan AUDIT-FIX 2026-10-04 (trait GuardsRecordIdShape, Display, action 'verify') ← kode yang sama
