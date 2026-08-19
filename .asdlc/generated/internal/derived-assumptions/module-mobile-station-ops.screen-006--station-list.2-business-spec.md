# Derived Assumptions Log — module-mobile-station-ops.screen-006--station-list.2-business-spec

## v1 — 2026-08-14

- business_rules = ["Hanya 3 stasiun MVP yang aktif secara fungsional; 12 lainnya adalah placeholder skema data untuk fase mendatang"] ← proposed by agent in draft, accepted without correction
- edge_cases = ["User tap stasiun yang disabled"] ← proposed by agent in draft, accepted without correction

## v2 — 2026-08-18

- information_displayed: ikon hamburger + nama sistem di header ← user tanya "dimana tombol hamburger?"; sudah dimandatkan uiux-spec.layout.shell_description sejak awal tapi baru sekarang diminta diimplementasikan di screen ini, konsisten dengan Home v3
- business_rules: warna merah untuk draft ongoing DAN paused (tidak dibedakan) ← user hanya sebutkan "merah ketika ada draft", tidak minta pembedaan ongoing vs paused di level warna; disederhanakan agar konsisten dengan pola status-badge yang sudah ada (ikon/teks yang membedakan, bukan warna, tapi di sini user secara eksplisit minta warna sebagai sinyal ada/tidaknya draft, bukan jenis drafnya)
- edge_cases: multi-draft per stasiun tetap merah; tidak ada draft = hitam/netral ← turunan langsung dari business_rules baru
- test_priority: low → medium ← naik dari 1 ke 3 business rules (rentang 2-4)

## v3 — 2026-08-19

- business_rules: foto stasiun ditampilkan sebagai background tile TAPI tidak menggantikan indikator warna status draft (warna tetap terlihat sebagai border/overlay/badge di atas foto) ← tidak dinyatakan eksplisit user; agent memutuskan demikian agar indikator draft (fungsi kritikal existing) tidak hilang saat foto ditambahkan
- business_rules: station.image bersifat opsional/progresif, fallback ke pola ikon+warna solid yang sudah ada jika belum diisi ← konsisten dengan keputusan desain 2026-08-18 (menghindari placeholder generik tak bermakna); user tidak menyatakan ulang ini secara eksplisit untuk revisi kali ini
- edge_cases: station.image gagal dimuat → fallback ke ikon+warna, bukan broken-image icon ← inferensi teknis standar, tidak dinyatakan user

## v4 — 2026-08-19 (koreksi user di checkpoint v3)

- station.image (foto background tile) DIGANTI TOTAL menjadi station.icon (override nama icon Lucide, opsional) — bukan foto sama sekali, murni penggantian glyph icon dari default per-tipe (Gauge/Layers/Package) ← koreksi eksplisit user di checkpoint, entity-catalog sudah diupdate ke v7 oleh coordinator sebelum instruksi ini sampai
- Warna/shadow/radius/layout tile TIDAK berubah sama sekali dari sebelum fitur ini ada — jauh lebih kecil dampaknya dari draft v3 ← turunan langsung dari koreksi user
- edge case baru: station.icon berisi nama tidak valid/tidak dikenal → fallback ke default icon per tipe ← turunan teknis, menggantikan edge case "gagal dimuat" yang relevan untuk foto, bukan icon
