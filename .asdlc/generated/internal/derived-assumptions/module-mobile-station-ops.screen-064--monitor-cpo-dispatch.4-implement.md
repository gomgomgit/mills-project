# Derived Assumptions Log — module-mobile-station-ops.screen-064--monitor-cpo-dispatch.4-implement

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v1)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v2 — 2026-10-03

Spec e2e mobile baru + run penuh 727 lulus / 0 gagal.
- mobile/tests/e2e/monitor-cpo-dispatch.spec.ts ditambahkan ke fe_test_files_generated; test_results.browser = 12 lulus / 0 gagal (run_at 2026-10-03T00:00:00Z, jumlah dari run penuh suite).
- known_issue 'spec browser/E2E belum ada' dihapus; known_issue lain dibiarkan.
- Cacat tanggal UTC di mobile/src/services/cpoDispatchRecordRepo.ts (todayDateString pakai toISOString → draft 00:00–06:59 WIB bertanggal kemarin) diperbaiki ke tanggal lokal; regresi: vitest 'mengisi tanggal draft dengan tanggal lokal, bukan UTC (dini hari WIB)' di mobile/tests/cpoDispatchRecordRepo.spec.ts + e2e 'New Data dini hari ...' di monitor-cpo-dispatch.spec.ts (dari fixme ke lulus). File repo sudah tercantum di fe_files_generated.

## v3 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit d5da9cf), code is truth (mobile/src/components/filters/*, mobile/src/components/loading/*, mobile/src/views/Monitor*View.vue, mobile/src/composables/useBusyAction.ts).
- implementation_notes (append) ← perubahan kode New Data busy + LoadingState
