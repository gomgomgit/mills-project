# Derived Assumptions — project.2-business-spec.usecases.usecase-160--laporan-kernel-plant-web

## v1 — 2026-10-07

Autonomy `autopilot`: usecase ini diturunkan dari spec layar screen-154 lalu diterima tanpa
konfirmasi per-usecase. Seluruh isinya rumusan agent.

- `usecase-160--laporan-kernel-plant-web` = alur lengkap membaca laporan periode Kernel Plant (14 langkah main_flow, 13 alternative_flow, 16 business rule, 26 bdd_scenario) ← diturunkan dari `available_actions`, `business_rules`, `information_displayed`, dan `edge_cases` screen-154; tidak dikonfirmasi satu per satu.

- `bdd_scenarios` = 26 skenario. Dua puluh tiga di antaranya ditulis `bdd-spec-writer-agent`; tiga ditambahkan sesudahnya oleh command setelah membandingkan hasil agent terhadap artifact yang sebenarnya.

- Agent BDD TIDAK DAPAT MEMBACA artifact screen-154 (tool `artifact__read` tidak tersedia di environment-nya) dan menyatakannya sendiri. Ia bekerja hanya dari payload usecase yang dikirim command. Pembandingan terhadap artifact dilakukan command, dan menemukan tiga celah nyata: edge case 6 (mill belum punya satu pun periode Kernel Plant), edge case 15 (master disunting tangan sehingga nama parameternya tak lagi cocok dengan peta kolom), dan business rule 16 (slot null tidak boleh diperlakukan sebagai nol). Ketiganya kini punya skenario sendiri, dan dua yang pertama juga ditambahkan sebagai `alternative_flows`.

- Happy-path Supervisor dan Mill Management DIGABUNG menjadi satu skenario ← keputusan agent BDD, diperiksa command terhadap `available_actions` screen-154: tidak ada satu pun aksi atau information item yang membedakan keduanya pada layar ini, keduanya terikat mill akunnya dan tak melihat pemilih mill. Admin terpisah karena ia satu-satunya yang melihat pemilih mill.

- Empat business rule SENGAJA tanpa skenario tersendiri karena sudah tercakup penuh oleh alternative_flow dengan makna sama: `coverage_percent null bukan 0.0`, `days_counted === 0 diperiksa lebih dulu`, `definisi slot terisi dipinjam dari layar input`, dan `hanya Admin melihat pemilih mill`. Keputusan agent BDD, bukan kelalaian — dicatat supaya tidak dibaca sebagai celah cakupan.

- `preconditions` memuat "Ada periode pelaporan Kernel Plant pada mill yang bersangkutan" ← turunan agent; konsekuensi dari periode yang dibuka per jenis stasiun, tidak dinyatakan user. Keadaan sebaliknya tetap diberi alternative_flow sendiri supaya ketiadaan periode tidak dibaca sebagai galat.
