# Derived Assumptions Log — module-mobile-station-ops.screen-009--monitor-cages-track.4-implement

## v1 — 2026-08-18

- cagesTrackRecordRepo.ts dan MonitorCagesTrackView.vue mengikuti pola gradingRecordRepo.ts/MonitorGradingView.vue (trio screen 007/008) secara konsisten
- deleteDraft cascade ke cages_tipped_time mengikuti pola cascade aplikasi-level yang sama seperti grading-detail di screen-008

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v3)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v4 — 2026-10-03

Run penuh Playwright mobile 2026-10-03: 430 lulus, 0 gagal.
- test_results.browser = 8/0.
- Spec diperbaiki hari ini (drift spec, bukan cacat aplikasi; hanya mobile/tests/e2e yang berubah): cek negatif not.toHaveURL dengan glob '**/…' (digabung ke baseURL, tak pernah cocok) diganti RegExp.
