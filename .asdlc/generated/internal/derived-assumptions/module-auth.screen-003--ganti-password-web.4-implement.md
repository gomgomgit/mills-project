# Derived Assumptions Log — module-auth.screen-003--ganti-password-web.4-implement

## v1 — 2026-08-17

- password.blade.php dibangun sekaligus sebagai shell sidebar+header (belum ada shared app layout, karena screen-001/002 adalah pre-auth) ← ditandai untuk diekstrak ke layouts.app bersama nanti
- Middleware role:admin,supervisor,mill_management ditambahkan di kedua route untuk menegakkan actor_permissions ← eksplisit dari tech-spec, translasi teknis langsung
- Sesi lama tidak di-invalidate setelah ganti password ← sesuai asumsi open-question di tech-spec
- Business logic dibagi identik antara API controller dan Livewire component via AuthService::changePassword() ← keputusan desain agent, konsisten dengan pola screen-001
