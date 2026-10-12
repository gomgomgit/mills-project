# Derived Assumptions Log — module-mobile-station-ops.screen-005--home.2-business-spec

## v1 — 2026-08-14

- business_rules = ["User bisa punya beberapa draft/record berjalan bersamaan per stasiun", "Status badge harus membedakan 3 kondisi via ikon & teks, bukan hanya warna"] ← proposed by agent in draft, accepted without correction
- edge_cases = ["Belum ada draft sama sekali (empty state)", "Banyak draft paused menumpuk"] ← proposed by agent in draft, accepted without correction

## v2 — 2026-08-18

- information_displayed: ikon hamburger menu tetap ada di header ← diturunkan dari layout.shell_description uiux-spec (header mobile pakai hamburger) + usecase-005 lama yang sudah punya action "Buka menu hamburger"; tidak diulang eksplisit oleh user pada revisi ini
- available_actions: tap menu placeholder (Estimates & Baselines / Dashboard & Reporting) menampilkan info singkat "belum tersedia", bukan no-op diam ← user hanya minta placeholder tetap ada, tidak menentukan perilaku tap; dipilih agar tombol tidak terasa mati tanpa feedback
- edge_cases: tap menu placeholder tanpa fitur, dan nama user kosong/tidak tersedia ← diturunkan dari perubahan struktur layar (bukan dinyatakan eksplisit oleh user)
- test_priority = "medium" (tidak berubah dari v1) ← 3 business rules pada v2, masuk rentang 2-4 sesuai formula derivasi test_priority

## v4 — 2026-10-12

- business_rules[2] = MEMBALIK keputusan v3 ("header memakai nama produk, TERPISAH dari app_name di Mills Setting") menjadi "header memakai Nama Aplikasi + Logo dari Mills Setting, dengan nama produk sebagai fallback" ← user minta "perbaiki 13 temuan itu" lalu "update artefak"; yang membalikkan keputusan ini sebenarnya DOKUMEN QA (AD-10: "Nilai baru berlaku di aplikasi mobile — nama baru itu yang tampil di Home"), bukan permintaan langsung user. INI SATU-SATUNYA tempat pembaruan artefak kali ini membalik keputusan yang pernah diambil sengaja — perlu ditinjau user bila v3 dulu memang keputusannya
- information_displayed = nama mill dicetak di bawah teks sambutan (bukan di header) ← agent derived; header membawa nama APLIKASI yang boleh diatur per mill dan karena itu belum tentu menyebut millnya, sementara yang perlu dibaca Operator sebelum menginput adalah mill mana ini
- edge_cases += app_name kosong/spasi → fallback, logo kosong → ikon dengan ukuran sama, akun tanpa mill → baris mill tidak dirender ← ketiganya agent derived; dokumen QA hanya menuntut nilai yang diatur ikut tampil, tidak menyebut keadaan belum-diatur
