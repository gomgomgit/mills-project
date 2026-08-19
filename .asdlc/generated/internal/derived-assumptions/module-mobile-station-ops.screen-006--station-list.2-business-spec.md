# Derived Assumptions Log — module-mobile-station-ops.screen-006--station-list.2-business-spec

## v1 — 2026-08-14

- business_rules = ["Hanya 3 stasiun MVP yang aktif secara fungsional; 12 lainnya adalah placeholder skema data untuk fase mendatang"] ← proposed by agent in draft, accepted without correction
- edge_cases = ["User tap stasiun yang disabled"] ← proposed by agent in draft, accepted without correction

## v2 — 2026-08-18

- information_displayed: ikon hamburger + nama sistem di header ← user tanya "dimana tombol hamburger?"; sudah dimandatkan uiux-spec.layout.shell_description sejak awal tapi baru sekarang diminta diimplementasikan di screen ini, konsisten dengan Home v3
- business_rules: warna merah untuk draft ongoing DAN paused (tidak dibedakan) ← user hanya sebutkan "merah ketika ada draft", tidak minta pembedaan ongoing vs paused di level warna; disederhanakan agar konsisten dengan pola status-badge yang sudah ada (ikon/teks yang membedakan, bukan warna, tapi di sini user secara eksplisit minta warna sebagai sinyal ada/tidaknya draft, bukan jenis drafnya)
- edge_cases: multi-draft per stasiun tetap merah; tidak ada draft = hitam/netral ← turunan langsung dari business_rules baru
- test_priority: low → medium ← naik dari 1 ke 3 business rules (rentang 2-4)
