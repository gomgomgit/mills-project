# Derived Assumptions Log — module-auth.screen-004--ganti-password-mobile.4-implement

## v1 — 2026-08-17

- PATCH /api/me/password dijadikan satu route bersama untuk screen-003 (web session) dan screen-004 (mobile Sanctum) via auth:web,sanctum + role:admin,supervisor,mill_management,operator ← keputusan merge eksplisit (bukan 2 route terpisah), rasional: ganti password sendiri tidak punya restriksi lintas-user sehingga semua role semestinya boleh
- Konsekuensi: ChangePasswordWebTest.php (screen-003) yang tadinya expect 403 untuk role operator diubah jadi expect 200 ← perubahan capability nyata (operator kini bisa akses endpoint ini walau operator sebenarnya tidak bisa akses web sama sekali secara praktik), dicatat eksplisit sebagai perubahan access-control, bukan sekadar bug fix
- useConnectivityGuard.ts digeneralisasi (bukan diganti) dengan opsi blocksAction/offlineActionMessage generik di samping path khusus login yang sudah ada
- Error shape API hanya expose message (bukan machine-readable code) ← keterbatasan yang sama seperti screen-003, form mobile cocokkan pesan exception untuk routing error per-field
