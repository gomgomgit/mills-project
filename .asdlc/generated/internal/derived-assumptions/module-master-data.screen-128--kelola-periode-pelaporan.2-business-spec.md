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

## v4 — 2026-10-01

Permintaan user: *"sebelum list data gw mau ada card dari periode2 yang aktif dari setiap mill."*
Itu satu kalimat; sebuah panel butuh belasan keputusan. Yang di bawah ini **seluruhnya rumusan
agent** — user hanya menyatakan: ada card, isinya periode aktif, satu per mill, letaknya sebelum
daftar.

- **Definisi "aktif" = punya minimal satu baris stasiun berstatus Terbuka (`period_stations.status = 'open'`)** ← KEPUTUSAN AGENT, dan ini yang paling perlu divalidasi user. Sejak migrasi `2026_09_26_000040` kolom `periods.status` DIBUANG (`Period::$status` melempar `LogicException`), jadi sebuah periode tidak punya status tunggal: ia bisa memuat Draft, Terbuka, dan Tertutup sekaligus. "Aktif" karena itu harus didefinisikan, tidak bisa dibaca. Dua tafsiran yang masuk akal:
    - **(a) berbasis status** — dipilih. Alasannya: (1) ia memakai kosakata yang sudah ada di layar ini ("Terbuka"), bukan konsep status ketiga; (2) `open` adalah status yang MENGIZINKAN input menurut aturan kunci periode yang user ratifikasi hari ini (usecase-141); (3) karena itu "mill tanpa periode terbuka" = "tidak ada stasiun di mill itu yang bisa menerima input" — satu-satunya kalimat pada panel ini yang punya akibat operasional langsung.
    - **(b) berbasis tanggal** (hari ini di dalam `start_date..end_date`) — ditolak. Ia akan menyebut sebuah periode "aktif" padahal seluruh 18 stasiunnya masih Draft, yaitu justru keadaan di mana tidak ada apa pun yang bisa diinput. Itu kebalikan dari yang ingin diketahui pembacanya.
  Tanggal tidak dibuang, hanya diturunkan pangkat: ia jadi **penanda** (`Sedang berjalan` / `Rentang sudah lewat`), bukan penyaring — lihat butir berikut.

- **Penanda `Rentang sudah lewat`** ← turunan agent, tidak diminta. Kombinasi "stasiun masih Terbuka + `end_date` sudah terlampaui" adalah keadaan yang perlu ditindaklanjuti (periode lupa ditutup). Bila tanggal dipakai sebagai penyaring, justru keadaan inilah yang akan hilang dari panel. Karena itu panel tidak menyaring dengan tanggal sama sekali.

- **Mill tanpa periode terbuka tetap mendapat card (redup), tidak disembunyikan** ← turunan agent. Dengan 6 Business Unit di basis data dev, menampilkan semuanya masih terbaca, dan card kosong adalah muatan informasi yang paling berguna di panel ini, bukan ruang terbuang. Bila jumlah mill tumbuh jauh di atas itu, keputusan ini perlu ditinjau ulang.

- **Panel mengikuti filter Business Unit, TIDAK mengikuti filter Status Stasiun** ← turunan agent. Filter BU berarti "sekarang saya sedang melihat mill ini", jadi panel ikut. Filter Status Stasiun berpotongan dengan isi panel yang sudah dibatasi Terbuka, sehingga mengikutinya hanya akan membuat panel kosong tanpa alasan yang bisa dijelaskan. Letaknya karena itu DI BAWAH baris filter, bukan di atasnya: panel yang bereaksi pada sebuah kontrol harus berada sesudah kontrol itu.

- **Panel merangkum seluruh periode mill, bukan halaman daftar yang sedang tampil** ← turunan agent. Panel yang ikut berpindah halaman akan menyatakan hal yang berbeda-beda tentang mill yang sama tergantung halaman, dan itu tidak bisa dibenarkan.

- **Hitungan `X dari N stasiun terbuka`** ← turunan agent. `toRow()` sekarang mengembalikan `station_count`, `closed_station_count`, dan `status_summary` — jumlah stasiun TERBUKA tidak ada di antaranya, dan `status_summary` bernilai `'mixed'` tidak menyebutkan angka apa pun. Jadi angka ini menuntut hitungan baru di service; secara sengaja tidak diturunkan dari badge ringkasan.

- **Satu card boleh memuat lebih dari satu periode terbuka** ← turunan agent. Rentang periode tidak boleh tumpang tindih per mill, tetapi dua periode yang tidak tumpang tindih bisa sama-sama punya stasiun terbuka (September lupa ditutup saat Oktober dibuka). Memangkasnya jadi satu akan menyembunyikan tepat anomali itu.

- **Panel bersifat baca saja; satu-satunya aksi adalah tautan ke screen-142** ← turunan agent, konsisten dengan keputusan 2026-09-27 yang memindahkan seluruh aksi per stasiun ke layar detail.

- Keadaan basis data dev saat keputusan ini diambil, supaya pembaca berikutnya tidak menyangka panelnya rusak: **2 periode, 20 baris stasiun (19 Draft, 1 Tertutup), NOL Terbuka.** Jadi hari ini panel akan menampilkan 6 card redup seluruhnya. Itu benar, bukan cacat — aksi "Buka Periode" (draft → open) baru ditambahkan pada screen-142 hari ini juga, sehingga belum ada periode yang pernah dibuka lewat alur normal.

## v5 — 2026-10-01

**KOREKSI ATAS PILIHAN AGENT DI v4, ATAS KEPUTUSAN USER.** v4 mendefinisikan "aktif" murni
berbasis status (≥1 stasiun Terbuka), dengan tanggal turun pangkat jadi penanda. Di checkpoint
pra-implementasi user memilih **syarat GABUNGAN**: Terbuka **DAN** tanggal hari ini berada di
dalam rentang periode. Pilihan itu diambil setelah akibatnya diperlihatkan lebih dulu pada
pratinjau opsi — termasuk bahwa periode yang lupa ditutup akan hilang dari panel.

Yang berubah karena keputusan itu, semuanya konsekuensi logis dan bukan tambahan baru:

- **Tanggal pindah dari penanda ke WHERE.** Dua penanda `Sedang berjalan` / `Rentang sudah lewat` DIHAPUS seluruhnya, begitu pula field `is_running` dan `is_past_range` pada respons API. Alasannya bukan penyederhanaan melainkan kejujuran: setelah tanggal jadi syarat, setiap periode yang sampai ke panel pasti berjalan hari ini, sehingga penanda semacam itu akan selalu bernilai sama dan justru mengundang pembacanya menyangka ada keadaan lain yang ikut terkirim.

- **Panel kini bermakna satu kalimat, bukan dua.** Syarat gabungan ini PERSIS predikat kunci periode (usecase-141): stasiun terbuka dan tanggal di dalam rentang. Jadi yang terbaca di panel adalah yang benar-benar berlaku bagi operator. Pada rancangan v4 panel dan kunci periode bisa berbeda jawaban untuk mill yang sama — itu hilang.

- **Satu card normalnya memuat NOL atau SATU periode** ← turunan agent, konsekuensi yang baru muncul setelah syarat tanggal masuk: aturan tumpang tindih membuat satu tanggal hanya dimiliki satu periode per mill. Tetapi aturan itu ditegakkan di lapisan APLIKASI dan bukan oleh constraint database (diverifikasi: `findOverlapping()` adalah guard service, tidak ada exclusion constraint di migrasi), jadi kodenya tetap mengembalikan list dan tetap mengirim semua yang cocok. `first()` dilarang eksplisit di spec: memangkasnya akan menyembunyikan pelanggaran tumpang tindih alih-alih menampakkannya.

- **`meta.today` naik dari alat bantu test menjadi bagian tampilan** ← turunan agent. Ketika tanggal hanya penanda, pembaca tidak perlu tahu acuannya. Setelah tanggal menentukan apa yang MUNCUL dan apa yang HILANG, pembaca berhak tahu "hari ini" yang dimaksud adalah tanggal server, bukan tanggal perangkatnya. Ditampilkan sebagai keterangan panel.

- **Endpoint tidak menerima parameter tanggal** ← turunan agent. Membuat tanggalnya dapat dikirim klien akan membuat panel bisa menjawab hari yang bukan hari ini, dan artinya berhenti tunggal.

- **`open_questions` kini berisi dua butir, sebelumnya kosong** ← butir pertama adalah lubang yang DIBUKA oleh keputusan ini dan saya catat terbuka alih-alih ditambal diam-diam: periode yang masih punya stasiun Terbuka sementara rentangnya sudah lewat kini tidak punya permukaan khusus di mana pun. Saya sengaja TIDAK menambahkan penanda sendiri untuk itu, karena user baru saja memilih supaya panel hanya menjawab keadaan hari ini; menambal lewat panel akan membatalkan separuh keputusannya. Jalan keluarnya, bila nanti dibutuhkan, ada di tabel daftar — bukan di panel. Butir kedua: batas keterbacaan "satu card per mill" bila jumlah mill tumbuh jauh di atas 6.

Nama panel ikut berubah: **'Periode Terbuka Hari Ini per Mill'** — judul yang menyebut syaratnya,
supaya tidak ada pembaca yang menyangka panel ini mendaftar seluruh periode terbuka.

## v7 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (backend/app/Services/PeriodService.php guardAgainstFramedRecords(), backend/app/Exceptions/PeriodHasRecordsException.php, EnforcesPeriodLock.php, tests/Feature/AuditFix20261004Test.php).
- available_actions[6].description, business_rules (+1), edge_cases (+1) = periode berisi data tidak dapat dihapus; penolakan inline setelah konfirmasi, tombol tidak dinonaktifkan ← guardAgainstFramedRecords() + PeriodHasRecordsException extends PeriodClosedImmutableException (catch yang sudah ada di KelolaPeriodePelaporan). ⚠ "tombol tidak dinonaktifkan" diinferensikan dari is_immutable yang hanya menghitung baris closed.
- business_rules[20] = kunci periode kini juga event_date detail, tanggal berzona → WIB, tanggal > besok ditolak ← EnforcesPeriodLock diff + AuditFix20261004Test [detail-lock]/[future]/[tz].
