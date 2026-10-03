# Derived Assumptions Log — module-mobile-station-ops.screen-038--monitor-pressing.4-implement

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v2)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v3 — 2026-10-03

Run penuh Playwright mobile 2026-10-03: 430 lulus, 0 gagal.
- test_results.browser = 8/0 (sebelumnya kosong).
- known_issue 'spec tidak dapat dijalankan di sandbox' dihapus — kini dijalankan nyata.
- Spec diperbaiki hari ini (drift spec, bukan cacat aplikasi; hanya mobile/tests/e2e yang berubah): cek negatif not.toHaveURL dengan glob '**/…' (digabung ke baseURL, tak pernah cocok) diganti RegExp.

## v4 — 2026-10-03

Run penuh Playwright mobile 2026-10-03: 430 lulus, 0 gagal.
- known_issue (major) "StationListView belum menautkan Pressing" dihapus karena basi: mobile/src/views/StationListView.vue:141-144 memetakan stasiun ini ke monitor-pressing, dan station-list.spec.ts bernavigasi lewat grid.
