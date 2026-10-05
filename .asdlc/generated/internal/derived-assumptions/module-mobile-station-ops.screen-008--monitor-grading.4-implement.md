# Derived Assumptions Log — module-mobile-station-ops.screen-008--monitor-grading.4-implement

## v1 — 2026-08-17

- getProgressSummary() menghitung semua grading_record milik user lokal terlepas dari status ("jumlah record dinilai pada sesi berjalan") karena grading_record tidak punya field weight/quantity sendiri
- createDraft(userId) hanya insert status=draft_ongoing + created_by, field lain (grading_number, vehicle_number, dst) dibiarkan null untuk diisi Form Grading (screen-011)
- deleteDraft() cascade di level aplikasi (2 DELETE berurutan: grading_detail lalu grading_record), bukan FK cascade level DB — konsisten dengan localSchema.ts

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v3)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v4 — 2026-10-03

Run penuh Playwright mobile 2026-10-03: 430 lulus, 0 gagal.
- test_results.browser = 8/0.
- Spec diperbaiki hari ini (drift spec, bukan cacat aplikasi; hanya mobile/tests/e2e yang berubah): cek negatif not.toHaveURL dengan glob '**/…' (digabung ke baseURL, tak pernah cocok) diganti RegExp.

## v5 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit d5da9cf), code is truth (mobile/src/components/filters/*, mobile/src/components/loading/*, mobile/src/views/Monitor*View.vue, mobile/src/composables/useBusyAction.ts).
- implementation_notes (append) ← perubahan kode New Data busy + LoadingState
