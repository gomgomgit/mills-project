# Derived Assumptions Log — module-mobile-station-ops.screen-007--monitor-weighbridge.4-implement

## v1 — 2026-08-17

- localSchema.ts (bukan file .sql migration) dipilih untuk skema SQLite lokal pertama — Capacitor importFromAssets belum di-wire, Vite tidak punya raw-.sql loader; CREATE TABLE dieksekusi lewat primitive run()/query() yang sudah mockable
- initLocalSchema() sengaja BELUM di-wire ke main.ts — akan memaksa koneksi SQLite native nyata di environment test yang belum ada mock-nya, berisiko merusak test screen-005/006. Perlu langkah wiring lanjutan.
- Skema grading_record/grading_detail/cages_track_record/cages_tipped_time di localSchema.ts masih bentuk placeholder, menunggu tech-spec screen 008/009/013
- pauseDraft "tanpa draft ongoing → no-op" diimplementasikan sebagai early-return sebelum run() dipanggil, dicerminkan juga di UI via tombol Pause disabled/guarded
- localDb.ts ditambah primitive run() (write) di samping query() (read) yang sudah ada dari screen-005

## v3 — 2026-08-18

- getDrafts() dibuat sebagai fungsi baru terpisah (bukan modifikasi getSummary()) dengan tipe WeighbridgeDraftListItem sendiri ← mengikuti konvensi file yang sudah ada (tipe khusus per kebutuhan), getSummary() tetap dipakai StationListView.vue jadi tidak boleh diubah signature-nya
- ConfirmDialog.vue tidak dihapus dari codebase (masih dipakai screen lain), hanya tidak lagi diimpor di screen ini
- 7 kegagalan Playwright di form-weighbridge.spec.ts/data-preview-weighbridge.spec.ts dibiarkan (tidak diperbaiki di sini) ← di luar scope screen-007, akan diperbaiki natural saat merevisi screen-010/013 yang memang sudah direncanakan

## v4 — 2026-08-18

- getTodaySummary(userId) fungsi baru terpisah, bukan modifikasi getSummary() ← getSummary() masih dipakai StationListView.vue untuk currentDraft, scope agregat berbeda (semua waktu vs hari ini)
- loadTodaySummary() gagal silent (tidak pakai shared error ref) ← kegagalan load counter tidak boleh memblokir UX list draft yang jauh lebih penting
- Ditemukan saat verifikasi independen: 8/8 Playwright gagal karena backend (php artisan serve) mati di tengah sesi panjang ini — bukan regresi kode, restart backend menyelesaikannya, dicatat di implementation_notes agar transparan bukan disembunyikan

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v5)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v6 — 2026-10-03

Run penuh Playwright mobile 2026-10-03: 430 lulus, 0 gagal.
- test_results.browser = 8/0.
- Spec diperbaiki hari ini (drift spec, bukan cacat aplikasi; hanya mobile/tests/e2e yang berubah): cek negatif not.toHaveURL dengan glob '**/…' (digabung ke baseURL, tak pernah cocok) diganti RegExp.

## v7 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit d5da9cf), code is truth (mobile/src/components/filters/*, mobile/src/components/loading/*, mobile/src/views/Monitor*View.vue, mobile/src/composables/useBusyAction.ts).
- implementation_notes (append) ← perubahan kode New Data busy + LoadingState
