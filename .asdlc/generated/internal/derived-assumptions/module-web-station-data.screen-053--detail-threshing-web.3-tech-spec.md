# Derived Assumptions Log — module-web-station-data.screen-053--detail-threshing-web.3-tech-spec

## v3 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Endpoint PATCH /api/records/{stationType}/{id}/verification didokumentasikan sebagai endpoint ke-2 di api_contracts[0] (sebelumnya tech-spec hanya memuat GET detail; aksi verifikasi 2026-09-14 belum pernah didokumentasikan). Dicatat bahwa layar Detail web memanggil RecordVerificationService langsung lewat aksi Livewire, bukan lewat HTTP — endpoint adalah padanan API-nya.
- error_codes diturunkan dari kode + RecordVerificationTest: 403 peran (respons hanya message, error_code ditulis FORBIDDEN), 403 FORBIDDEN mill lain, 404 (stationType/id), 422 VALIDATION_ERROR, 422 PERIOD_CLOSED. Format respons API memakai kunci `code`, bukan `error_code`.
- Wording 2 edge case + 2 unit test case dipilih sendiri; edge case mill lain menyebut alert $verificationMessage (ditangkap sebagai AuthorizationException di trait).
