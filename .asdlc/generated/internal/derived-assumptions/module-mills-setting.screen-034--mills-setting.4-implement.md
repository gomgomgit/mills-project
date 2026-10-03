## v1 — 2026-08-19

- Own shell layout (`resources/views/settings/mill-settings.blade.php`) lists all existing nav links plus its own, but does NOT retrofit a "Mills Setting" link into any other screen's shell file ← not stated in tech spec; matches this codebase's established pattern of per-screen static shell snapshots (verified against `machinery.blade.php`, the newest existing shell, which itself doesn't link back to every prior screen consistently either).
- CSS class prefix `ms-` (Mills Setting) chosen for the Livewire view's inlined `<style>` block, adapting `kc-`'s form-field/button/alert/empty/table rules verbatim (renamed) plus new `ms-image-field`/`ms-filter` rules for the dual-image-upload + Admin mill-picker layout ← implementation-level styling choice, not specified in tech spec.
- Icon picker rendered as a plain `<select>` per station row (fires immediately via `wire:change`, no separate "Simpan" for that sub-section) ← tech spec's implementation_notes said "FE: picker rendered as dropdown/select" but left the exact interaction model (immediate vs. batched-with-main-form) unspecified; chose immediate-save for simplicity and to keep the icon list in sync without requiring the main form's Simpan to also cover it.

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v1)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v2 — 2026-10-03

Mencatat perbaikan kode/uji 2026-10-03.
- test_results.browser diisi passed 6 failed 0 run_at 2026-10-03 (1 skip tidak punya kolom).
- Known issue 'browser test tidak dijalankan' dihapus; e2e-web/tests/mills-setting.spec.ts ditambahkan ke fe_test_files_generated.

## v3 — 2026-10-03

Pembersihan entri berkas uji yang sudah tidak ada.
- `backend/tests/Browser/MillsSettingTest.php` dihapus dari `test_files_generated` (berkas tidak ada; direktori dihapus di 8879d8d).
