# Derived Assumptions Log — module-web-station-data.screen-104--detail-cpo-dispatch-web.3-tech-spec

## v2 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Endpoint PATCH /api/records/{stationType}/{id}/verification didokumentasikan sebagai endpoint ke-2 di api_contracts[0] (sebelumnya tech-spec hanya memuat GET detail; aksi verifikasi 2026-09-14 belum pernah didokumentasikan). Dicatat bahwa layar Detail web memanggil RecordVerificationService langsung lewat aksi Livewire, bukan lewat HTTP — endpoint adalah padanan API-nya.
- error_codes diturunkan dari kode + RecordVerificationTest: 403 peran (respons hanya message, error_code ditulis FORBIDDEN), 403 FORBIDDEN mill lain, 404 (stationType/id), 422 VALIDATION_ERROR, 422 PERIOD_CLOSED. Format respons API memakai kunci `code`, bukan `error_code`.
- Wording 2 edge case + 2 unit test case dipilih sendiri; edge case mill lain menyebut alert $verificationMessage (ditangkap sebagai AuthorizationException di trait).

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (backend/app/Livewire/Data/DetailCpoDispatch.php, backend/resources/views/livewire/data/detail-cpo-dispatch.blade.php, backend/app/Livewire/Data/Concerns/GuardsRecordIdShape.php, backend/app/Support/Display.php, backend/app/Support/Concerns/EnforcesPeriodLock.php, backend/app/Services/RecordVerificationService.php, backend/app/Exceptions/ApiExceptionHandler.php).
- api_contracts[0].endpoints[0].response.error_codes += 422 VALIDATION_ERROR untuk id bukan UUID ← ⚠ INFERENSI: route GET tanpa whereUuid dan getDetail() tanpa guard, sehingga di PostgreSQL findOrFail('abc') -> QueryException 22P02 -> ApiExceptionHandler memetakan 22P02 ke 422; tidak diuji khusus untuk endpoint ini (di SQLite tetap 404)
- api_contracts[0].endpoints[1].response.error_codes += 422 PERIOD_CLOSED untuk event_date baris Log Kejadian ('Baris log bertanggal kejadian dd/mm/yyyy ditolak.') ← EnforcesPeriodLock::assertDetailEventDatesWritable() + RecordVerificationService
- api_contracts[0].business_logic += langkah AUDIT-FIX (guard UUID di mount, format Display, kalimat 'verify', kunci event_date baris saat verifikasi) ← diff Detail*.php/blade + EnforcesPeriodLock::periodLockReason($action) + RecordVerificationService
- api_contracts[0].edge_case_handling += {id} bukan UUID; penolakan verifikasi berkalimat verifikasi; baris Log Kejadian di periode tertutup saat verifikasi ← kode yang sama
- api_contracts[0].unit_test_cases += non-UUID id -> notFound tanpa query ; verifikasi ditolak oleh event_date baris ← RecordIdShapeGuardTest / AuditFix20261004Test
- implementation_notes += catatan AUDIT-FIX 2026-10-04 (trait GuardsRecordIdShape, Display, action 'verify') ← kode yang sama
