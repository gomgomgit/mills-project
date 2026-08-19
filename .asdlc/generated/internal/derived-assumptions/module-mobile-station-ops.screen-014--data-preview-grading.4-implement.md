# Derived Assumptions Log — module-mobile-station-ops.screen-014--data-preview-grading.4-implement

## v1 — 2026-08-18

- Reuse getDraftWithDetails() yang sudah ada (screen-011) daripada duplikat method baca
- grading_detail rows dirender sebagai list read-only sederhana (bukan GradingDetailGrid.vue yang edit-only khusus screen-011)
- Route path pakai /stations/grading/preview/:id? (optional param), mirror pola screen-013
- StatusBadge dipakai ulang via status+label override, mirror pola screen-013

## Temuan gap lintas-screen (konsisten dengan screen-013)
MonitorGradingView.vue (screen-008) tombol "Buka Data Preview" navigasi ke data-preview-grading TANPA parameter id — situasi identik dengan screen-007/013. Route sendiri benar terdaftar, hanya belum dipakai dengan id oleh pemanggilnya.
