# Derived Assumptions Log — module-mobile-station-ops.screen-005--home.4-implement

## v1 — 2026-08-17

- localDb.ts + draftRecordsRepo.ts dibangun sebagai fondasi SQLite lokal (query mockable, koneksi native Capacitor belum teruji tanpa device/simulator) ← akan dipakai ulang oleh screen-006 s/d 015
- Nama tabel lokal diasumsikan snake_case (weighbridge_record, grading_record, cages_track_record) ← belum ada skema/migration SQLite lokal formal, dicatat sebagai asumsi untuk screen write-capable pertama memformalkannya
- Badge priority: ongoing mengalahkan paused per stasiun jika keduanya ada ← aturan tie-break tidak eksplisit di tech-spec
- StatusBadge.vue dibuat generik (props status: none/ongoing/paused) untuk dipakai ulang di screen-006 s/d 015 ← keputusan desain agent
- Belum ada global design-token CSS, komponen baru ikuti konvensi scoped-style + inline Inter font + hardcoded #D20000 seperti LoginView.vue

## v3 — 2026-08-18

- Nav menu hamburger (Ganti Password/Logout) dibuat sebagai state lokal (`isNavMenuOpen`) di HomeView.vue, bukan komponen bersama ← tidak ada komponen dropdown/nav-menu lain di codebase untuk dipakai ulang, single caller
- "Segera Hadir" info message: transient-message lokal auto-dismiss 3 detik ← tidak ada toast/snackbar bersama di codebase, meniru pola `infoMessage` StationGrid.vue
- Hero image didownload manual ke mobile/src/assets/home-hero-mill.jpg (bukan di-generate oleh code-writer-agent) ← keputusan offline-first, URL Unsplash diverifikasi HTTP 200 sebelum didownload
- Dead-code cleanup (dieksekusi & diverifikasi terpisah dari screen-impl-agent, bukan bagian laporannya): PausedDraftsList.vue, useHomeSummary.ts, draftRecordsRepo.ts, useHomeSummary.spec.ts dihapus setelah grep konfirmasi tidak ada consumer lain selain HomeView versi lama ← konsekuensi langsung dari business rule "Home tidak menampilkan status draft/record"
- tests/e2e/station-list.spec.ts diperbaiki (selector "Daftar Stasiun" → testid menu-card-production-process-activity) ← ditemukan sebagai regresi nyata saat menjalankan ulang seluruh suite Playwright secara independen (bukan hanya trust laporan agent), bukan oleh screen-impl-agent
