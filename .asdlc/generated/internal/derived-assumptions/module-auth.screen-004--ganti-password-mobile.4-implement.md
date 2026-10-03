# Derived Assumptions Log — module-auth.screen-004--ganti-password-mobile.4-implement

## v1 — 2026-08-17

- PATCH /api/me/password dijadikan satu route bersama untuk screen-003 (web session) dan screen-004 (mobile Sanctum) via auth:web,sanctum + role:admin,supervisor,mill_management,operator ← keputusan merge eksplisit (bukan 2 route terpisah), rasional: ganti password sendiri tidak punya restriksi lintas-user sehingga semua role semestinya boleh
- Konsekuensi: ChangePasswordWebTest.php (screen-003) yang tadinya expect 403 untuk role operator diubah jadi expect 200 ← perubahan capability nyata (operator kini bisa akses endpoint ini walau operator sebenarnya tidak bisa akses web sama sekali secara praktik), dicatat eksplisit sebagai perubahan access-control, bukan sekadar bug fix
- useConnectivityGuard.ts digeneralisasi (bukan diganti) dengan opsi blocksAction/offlineActionMessage generik di samping path khusus login yang sudah ada
- Error shape API hanya expose message (bukan machine-readable code) ← keterbatasan yang sama seperti screen-003, form mobile cocokkan pesan exception untuk routing error per-field

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v1)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v2 — 2026-10-03

Pembersihan entri berkas uji yang sudah tidak ada.
- `backend/tests/Browser/ChangePasswordMobileTest.php` dihapus dari `test_files_generated` (berkas tidak ada; direktori dihapus di 8879d8d).
- known_issue dihapus (semata soal berkas tests/Browser yang tidak dijalankan): "Browser test (tests/Browser/ChangePasswordMobileTest.php) dibuat tapi tidak dijalankan — t..."
- Tidak ada spec e2e-web yang jelas cocok (layar mobile) — tidak ditambahkan.

## v3 — 2026-10-03

Run penuh Playwright mobile 2026-10-03: 430 lulus, 0 gagal.
- test_results.browser = 5/0 (sebelumnya kosong).
- mobile/tests/e2e/change-password.spec.ts ditambahkan ke fe_test_files_generated.
- Spec diperbaiki hari ini (drift spec, bukan cacat aplikasi; hanya mobile/tests/e2e yang berubah): locator .field-error strict-mode + timing offline-sebelum-bootstrap.
