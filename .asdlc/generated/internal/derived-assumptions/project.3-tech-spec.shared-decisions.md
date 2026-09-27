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
