# Derived Assumptions — module-dashboard.screen-154--laporan-kernel-plant-web.2-business-spec

## v1 — 2026-10-07

Autonomy `autopilot`: seluruh isi spec ini rumusan agent. User menetapkan satu hal saja —
"lanjutkan laporan stasiun, satu per satu" — dan memilih melewati checkpoint pra-implementasi
untuk rangkaian ini. Pemilihan Kernel Plant sebagai stasiun berikutnya, dan setiap keputusan
di bawah, adalah turunan agent.

- `screen-154` dipilih sebagai stasiun berikutnya ← Kernel Plant adalah yang ke-7 dari kelompok stasiun aktif MVP (enam lainnya sudah punya laporan) dan penerus Depricarping pada alur proses; bukan dinyatakan user.

- Peta kolom-ke-parameter (7 kolom ukur ← 6 baris master) = turunan agent dari pembacaan verbatim `kernel_plant_operational_targets` (6 baris) terhadap `kernel_plant_details` (7 kolom `double precision` nullable). Pemetaannya: `Ripple Mill (Cracker)` → `ripple_mill_1_amps` + `ripple_mill_2_amps`; `Claybath / Hydrocyclone` → `claybath_hydro_sg`; `Kernel Silo 1 & 2` → `kernel_silo_1_temp_c` + `kernel_silo_2_temp_c`; `Final Kernel Moisture` → `kernel_moisture_percent`; `Shell Bin Kernel Loss` → `shell_loss_percent`; `Final Kernel Dirt` → TIDAK ADA KOLOM. Tidak satu pun pasangan ini dinyatakan di mana pun — keduanya tabel terpisah tanpa kunci asing di antaranya.

- Dua master mengatur DUA kolom masing-masing ← konsekuensi peta di atas, bukan fakta yang tertulis di master. Akibatnya jumlah entri peta (7) tidak sama dengan jumlah parameter yang disebutnya (5), dan itulah sebabnya business rule 12 menuntut perbandingan terhadap HIMPUNAN, bukan terhadap jumlah.

- `shell_loss_percent` DIANGGAP kolom untuk `Shell Bin Kernel Loss` ← namanya menyebut "shell loss" sementara masternya menyebut "Shell Bin **Kernel** Loss" dengan target ≤ 1,5%. Kedua nama itu bisa berarti dua hal berbeda. Dicatat sebagai open question, BUKAN diganti nama sendiri — meski tabelnya masih 0 baris sehingga biaya penggantiannya serendah penggantian nama di Depricarping.

- `information_displayed` (13 butir), `available_actions` (5), `business_rules` (16), `edge_cases` (15) = seluruhnya turunan agent ← pola diambil dari lima laporan kondisi yang sudah ada (Threshing, Pressing, Depricarping, Boiler Room, Storage Tank), lalu disesuaikan dengan kolom yang NYATA ada di skema Kernel Plant. Tidak ada satu butir pun yang diminta user secara eksplisit.

- Business rule 7 (definisi slot 'terisi' DIPINJAM dari `KernelPlantRecordService::READING_FIELDS` + `isRowFilled()`) ← keputusan agent untuk meminjam, bukan mendefinisikan ulang. Konsekuensinya — `coverage.filled_slots` dapat MELEBIHI penyebut tiap metrik — dinyatakan terbuka justru supaya tidak dibaca sebagai ketidakkonsistenan lalu "diperbaiki" belakangan menjadi salah.

- Business rule 14 (NOL penandaan otomatis di luar batas) ← penilaian agent bahwa kedua kolom target adalah teks bebas yang bentuknya tidak dibatasi skema, dan satu nilai saja — `'20 - 25 Amps (Nut Breakage >95%)'` — sudah memuat dua angka dengan satuan dan arah pembanding berbeda di satu sel. Ketiadaan penandaan sengaja DINYATAKAN di layar supaya tidak dilengkapi belakangan oleh orang yang menyangka itu kelalaian.

- Business rule 16 (agregasi di PHP, NOL agregat SQL atas kolom nullable) ← turunan agent dari divergensi SQLite/PostgreSQL yang sudah berbiaya dua kali di proyek ini; bukan permintaan user.

- `test_priority: "high"` ← turunan agent; sejajar dengan lima laporan kondisi lain, bukan dinyatakan user.

- `actors` = Admin, Supervisor, Mill Management ← sama dengan lima laporan kondisi yang sudah ada. Operator SENGAJA dikecualikan dari rute web meski ketiga rute API menerimanya, mengikuti pola yang berlaku; itu konsistensi yang diturunkan, bukan ketentuan baru.
