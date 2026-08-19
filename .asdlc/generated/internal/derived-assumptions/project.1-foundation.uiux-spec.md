# Derived Assumptions Log — project.1-foundation.uiux-spec

## v1 — 2026-08-14

- design_system = neutral palette + brand hijau #249360 + red #D20000 (station/status, user confirmed) + Inter typography + Lucide icons; nilai kontras/spacing/radius/shadow spesifik (angka px, hex non-brand) ditentukan agen ← agent default, not individually confirmed by user
- layout.shell_description dimensi spesifik = sidebar 240px (collapse 64px), header 56px web / 64px mobile ← agent-derived specifics, navigation pattern itself (sidebar+header web, hamburger drawer mobile) was user-confirmed
- layout.navigation_per_role = 12 menu item (6 web + 6 mobile) diturunkan dari PRD initial_actors & confirmed screen types ← agent derived, not dictated item-by-item by user
- screen_type_patterns = 6 tipe (auth, dashboard, list, form, detail, settings) — konten detail layout/header/body/footer/states per tipe diturunkan agen dari PRD & referensi Figma; hanya daftar tipe yang dikonfirmasi user ← agent derived
- component_patterns = 5 komponen (data-table, form, toast, loading, status-badge) — pola & behavior diturunkan agen, tidak ditanyakan ke user ← agent derived
- accessibility = level Basic (user confirmed); rasio kontras 3:1 & aturan structure_semantics detail diturunkan agen dari level yang dipilih ← agent derived detail

## v2 — 2026-08-18

- screen_type_patterns[list].body_area (mobile ordering) = 3 stasiun aktif tetap urutan Weighbridge → Grading → Cages Track, placeholder tidak diurutkan alfabetis ← user hanya menyatakan urutan alfabetis membingungkan; urutan spesifik (Weighbridge/Grading/Cages Track) diturunkan agen dari prioritas MVP yang konsisten di PRD/business-spec
- component_patterns['station-tile'] ikon per jenis stasiun = Gauge (Weighbridge), Layers (Grading), Package (Cages Track), dari Lucide ← user tidak menentukan ikon spesifik; dipilih agen dari icon_library yang sudah ada berdasarkan makna semantik nama stasiun
- screen_type_patterns[list].header_area (mobile) — gambar referensi dijadikan opsional (bukan wajib) ← user komplain placeholder gambar tidak bermakna; tidak ada asset final yang dinyatakan tersedia, jadi dihilangkan sebagai requirement wajib, bukan diganti asset lain
- component_patterns['station-tile'] shadow = token 'card' yang sudah ada di design_system tapi sebelumnya tidak direferensikan komponen manapun ← agent derived untuk mengatasi keluhan 'flat/blocky', memakai token yang sudah didefinisikan bukan nilai baru

## v3 — 2026-08-19

- component_patterns['web-form-input'] pengecualian untuk field Checked By/Acknowledged By (role-based disable pada component 'form') tetap berlaku dan tidak termasuk larangan disabled/readonly baru ← user tidak menyebutkan pengecualian ini secara eksplisit; agen menyimpulkan agar konvensi baru tidak bertentangan dengan pola role-based access control yang sudah ada sebelumnya
- component_patterns['web-form-input'] cakupan field date/datetime = native `<input type="date">`/`<input type="datetime-local">` secara spesifik (bukan sekadar "harus ada picker + manual") ← user hanya menyatakan "bisa input manual ataupun date picker"; agen memilih implementasi HTML native konkret yang sudah dipakai di semua form date existing (dikonfirmasi via audit) sebagai cara memenuhi requirement tersebut
