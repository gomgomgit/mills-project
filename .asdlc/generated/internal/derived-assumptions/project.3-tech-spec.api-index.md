
## v58 — 2026-09-28

- Hanya SATU endpoint baru didaftarkan (`/production-lines/options-for-report`), bukan kesembilan rute `/production-lines` ← ditemukan bahwa api-index memuat NOL endpoint `/production-lines`, padahal 9 ada di kode. Celah itu pra-ada dan milik screen-036; mendaftarkan kedelapan sisanya adalah pekerjaan tersendiri, bukan diselundupkan ke sinkronisasi ini.
- Parameter `production_line_id` TIDAK dicatat sebagai daftar parameter ← skema `api-index` tidak punya field untuk parameter permintaan (hanya method, path, description, screen_id, usecase_id, auth_required, actor_ids), dan deskripsi yang ada berkonvensi menjelaskan apa yang DIKEMBALIKAN, bukan mendaftar parameter. Menambahkan nama parameter ke 29 deskripsi akan melanggar konvensi itu; yang diperbarui hanya 11 endpoint laporan, tempat perilakunya benar-benar berubah.
- `screen_id` endpoint baru diisi screen-135 ← ia melayani kelima layar laporan mobile, tetapi skema hanya menyediakan satu `screen_id`. Dipilih yang pertama; deskripsinya menyebut perannya agar tidak terbaca sebagai milik satu layar saja.

## v62 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (routes/api.php blok "ENDPOINT BACA MOBILE", GradingParameterController, RecordVerificationStatusController/Service, AuthService::ROLE_REDIRECTS, PeriodService::delete, PeriodHasRecordsException).
- endpoints[0] (POST /api/login, screen-001).actor_ids += actor-station-operator; description = Operator boleh login web terbatas → /beranda ← AuthService + routes/web.php
- endpoints[135] (DELETE /api/periods/{id}).description = + 409 PERIOD_HAS_RECORDS untuk periode yang membingkai record ← PeriodService
- endpoints += GET /api/grading-parameters (screen-011--form-grading / usecase-011--form-grading, 4 peran) ← routes/api.php; penetapan screen_id ke screen-011 adalah keputusan agen (endpoint juga dipanggil saat login mobile screen-002)
- endpoints += GET /api/records/{stationType}/verification (screen-013--data-preview-weighbridge / usecase-013, 4 peran) ← routes/api.php; satu entri mewakili ke-18 Data Preview — screen_id perwakilan dipilih agen karena schema hanya menerima satu screen_id

## v63 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (Api/StationController.php, StationService.php, Api/UserController.php, UserService.php).
- endpoints[28] GET /api/stations: + filter production_line_id (bukan UUID diabaikan).
- endpoints[32] DELETE /api/stations/:id: + guard record stasiun 18 tabel → 409.
- endpoints[59] PATCH /api/users/:id: password opsional = Reset Password (PasswordPolicy, cabut token+sesi).
