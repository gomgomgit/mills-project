# Derived Assumptions Log — module-master-data.screen-128--kelola-periode-pelaporan.2-business-spec

## v1 — 2026-09-22

- test_priority = "high" ← derived by the agent: 12 business rules (>= 5 triggers "high"), and the screen's Tutup Periode action locks operational data across every station in a mill — the widest blast radius of any master-data screen
- business_rules += "Periode berstatus Tertutup tidak dapat diubah maupun dihapus sebelum dibuka kembali" ← agent-derived. User stated closing locks the station records; whether the period row ITSELF stays editable was never discussed. Agent locked it too, reasoning that editing a closed period's date range would silently move which records are locked
- business_rules += "Periode baru selalu dimulai berstatus Draft" ← agent-derived, follows from the `draft` enum value the agent itself added to the entity in tech-1-core
- business_rules += "Saat periode dibuka kembali, catatan penutup dan waktu penutupan dihapus" ← agent-derived; user only said reopening is possible, not what happens to the audit trail
- information_displayed += filter berdasarkan Business Unit dan Status ← agent-derived from the existing master-data screen pattern (Kelola Production Line filters by Business Unit); user never mentioned filters
- available_actions = 7 aksi (filter, tambah, edit, hapus, tutup, buka kembali, navigasi halaman) ← user named only tutup/buka kembali explicitly; full CRUD + pagination derived from the master-data screen pattern
- edge_cases = 9 kasus ← all agent-derived from the constraints. Two are worth a second look: "Mengubah rentang tanggal periode yang sedang Terbuka sehingga data yang sudah ada menjadi masuk atau keluar dari periode → diizinkan selama tidak menimbulkan tumpang tindih" (agent chose to allow this; it silently changes which records a future close would lock), and "Dua Admin menutup periode yang sama bersamaan → penutupan kedua tidak menimpa catatan penutup pertama" (concurrency behaviour never discussed)
- usecase_ids = usecase-128 + usecase-140 ← IDs and names were already fixed in bus-1-scope; their full content (flows, preconditions, postconditions) is drafted here by the agent and was not individually confirmed, per autopilot

## v2 — 2026-09-23

Revisi: menambahkan aksi **Buka Periode** (Draft → Terbuka). User menyatakan keputusannya
secara eksplisit ("tambahkan aksi Buka Periode, Admin saja"); butir di bawah ini adalah
turunan agent di sekitar keputusan itu.

- `business_rules` "siklus Draft → Terbuka → Tertutup, **tidak ada jalan kembali dari Terbuka ke Draft**" ← user tidak menyebut arah sebaliknya. Dipilih searah karena Draft berarti "masih disiapkan"; sekali periode dinyatakan berjalan, memundurkannya akan mengaburkan arti statusnya sendiri. Kalau ternyata perlu, ini keputusan yang gampang dibalik selama belum ada data.
- `business_rules` "periode **Terbuka tetap dapat diubah dan dihapus** seperti Draft" ← konsekuensi yang tidak dinyatakan user tapi harus ditegaskan, karena `PeriodService::update()` dan `delete()` saat ini hanya menolak status `closed`. Tanpa aturan tertulis ini, seseorang bisa menyangka Terbuka juga mengunci, lalu "memperbaikinya" dan merusak perilaku yang benar.
- `business_rules` "membuka periode **hanya mengubah status**, tidak menyentuh data stasiun" ← ditulis eksplisit agar aksi ini tidak kelak dibebani validasi data seperti yang dilakukan Tutup Periode (yang memang menghitung data belum terverifikasi). Keduanya mudah dianggap simetris padahal tidak.
- Penolakan pada periode **Tertutup** mengarahkan Admin ke aksi "Buka Kembali Periode" ← dua aksi ini mudah tertukar; pesannya harus menyebut yang benar, bukan sekadar menolak.
- `edge_cases` dua butir baru (dua Admin bersamaan, periode sudah tertutup) ← turunan agent, menyalin bentuk penjagaan bersamaan yang sudah dipakai `close()`.
- Transisi ini ditulis sebagai **usecase terpisah** `usecase-144--buka-periode-pelaporan`, bukan ditambahkan ke `usecase-140` ← keputusan agent. usecase-140 punya 9 langkah alur dan 6 alternative flow yang triggernya merujuk nomor langkah ("Pada langkah 5", "Pada langkah 3"). Menyisipkan transisi baru di awalnya memaksa penomoran ulang dan membuat seluruh rujukan itu meleset. Memisahkannya menghindari churn yang justru rawan salah.

## v3 — 2026-09-27

Penulisan ulang layar daftar setelah daftar stasiun dipindahkan ke screen-142, sekaligus
menyusul model induk–anak yang sudah berjalan di kode sejak 2026-09-25/26 tetapi belum pernah
masuk ke artefak ini (v2 masih menggambarkan periode ber-`station_type` dan berstatus tunggal).

- `information_displayed` = kolom Nama (tautan detail), Business Unit, Tanggal Mulai, Tanggal Selesai, Stasiun, Status Stasiun, Aksi ← dibaca langsung dari `resources/views/livewire/master-data/kelola-periode-pelaporan.blade.php`; user hanya memutuskan "badge ringkasan status + hitungan stasiun tetap di daftar"
- Teks ringkasan `"N stasiun · M tertutup"` dan `"Belum ada stasiun"` ← kutipan apa adanya dari blade yang sudah berjalan, bukan rumusan baru
- Label filter `Status Stasiun` beserta makna "punya ≥1 stasiun berstatus ini" ← dari `PeriodService::listPeriods()` + komentar blade; user tidak merumuskan maknanya
- edge case "Memindahkan periode ke mill lain menghasilkan gabungan kedua inventaris" ← turunan agen dari docblock `backfillStationRows()`; tidak pernah dibahas user, tetapi ia konsekuensi nyata dari aturan add-only sehingga dicatat sebagai edge case, bukan dibiarkan tersembunyi
- edge case "berakhir tepat pada tanggal mulai periode lain tetap beririsan" ← dari `findOverlapping()` (batas inklusif); turunan agen
- `usecase_ids` tinggal `usecase-128` ← konsekuensi langsung dari repoint usecase-index v16 yang sudah dikerjakan sebelum langkah ini
- Kalimat eksplisit "penegakan kunci belum diimplementasikan (usecase-141)" ← diverifikasi ulang 2026-09-27: nol referensi `Period` di seluruh `app/Services/*RecordService.php`. Dicatat sebagai business rule agar artefak tidak mengklaim kunci yang tidak ada
