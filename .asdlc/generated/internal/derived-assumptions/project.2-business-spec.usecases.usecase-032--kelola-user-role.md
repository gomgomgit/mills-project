
## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (kelola-user-role.blade.php wire:confirm, UserService.php).
- alternative_flows[3].steps = Nonaktifkan dengan konfirmasi; penonaktifan mencabut token & mengakhiri sesi web; pesan sukses ← dulu "langsung memperbarui" bertentangan dengan wire:confirm
- business_rules[0] = unik case-insensitive, tanpa spasi ← UserService::usernameUniqueRule + regex
