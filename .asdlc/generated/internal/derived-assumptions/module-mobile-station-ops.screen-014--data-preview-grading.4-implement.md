# Derived Assumptions Log — module-mobile-station-ops.screen-014--data-preview-grading.4-implement

## v1 — 2026-08-18

- Reuse getDraftWithDetails() yang sudah ada (screen-011) daripada duplikat method baca
- grading_detail rows dirender sebagai list read-only sederhana (bukan GradingDetailGrid.vue yang edit-only khusus screen-011)
- Route path pakai /stations/grading/preview/:id? (optional param), mirror pola screen-013
- StatusBadge dipakai ulang via status+label override, mirror pola screen-013

## Temuan gap lintas-screen (konsisten dengan screen-013)
MonitorGradingView.vue (screen-008) tombol "Buka Data Preview" navigasi ke data-preview-grading TANPA parameter id — situasi identik dengan screen-007/013. Route sendiri benar terdaftar, hanya belum dipakai dengan id oleh pemanggilnya.

## v4 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Test yang dikutip: backend/tests/Feature/Api/RecordVerificationTest.php (blok kunci periode) dan backend/tests/Unit/Support/EnforcesPeriodLockTest.php; dicatat bahwa mobile/tests/recordVerification.spec.ts belum punya kasus PERIOD_CLOSED khusus (hanya offline). test_results tidak disentuh.

## v5 — 2026-10-03

Sinkronisasi catatan uji 4-implement dengan uji yang ditambahkan 2026-10-03.
- Tidak ada known_issue yang menyatakan uji mobile PERIOD_CLOSED belum ada — celah itu hanya tercatat di catatan REVISI kunci periode (implementation_notes), jadi tidak ada yang dihapus; catatan REVISI v5 menyatakannya tertutup.
- Uji baru berada di mobile/tests/recordVerification.spec.ts (komponen bersama RecordVerificationActions), bukan di spec layar ini; berkas itu tidak ditambahkan ke fe_test_files_generated dan test_results tidak diubah karena jumlah uji per layar tidak berubah.

## v6 — 2026-10-03

Run penuh Playwright mobile 2026-10-03: 430 lulus, 0 gagal.
- test_results.browser = 11/0.
- Spec diperbaiki hari ini (drift spec, bukan cacat aplikasi; hanya mobile/tests/e2e yang berubah): assert blok status verifikasi (Acknowledged By saja) alih-alih ketiadaannya; cek negatif not.toHaveURL dengan glob '**/…' (digabung ke baseURL, tak pernah cocok) diganti RegExp.

## v7 — 2026-10-03

Pembersihan rujukan komponen grid yang dihapus.
- known_issue dead-code GradingDetailGrid.vue dihapus: komponen dihapus di 004aacd. Klaim lama bahwa error vue-tsc-nya 'tidak mempengaruhi build' ternyata keliru — vue-tsc adalah langkah pertama npm run build.
- Ditambah satu catatan REVISI merujuk 004aacd.
