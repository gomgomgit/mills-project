# Derived Assumptions Log — module-master-data.screen-128--kelola-periode-pelaporan.3-tech-spec

## v1–v2 — 2026-09-22

- route = "/master-data/periods", api = "/api/periods", tabel = "periods" ← derived from the entity id `period` + shared-decisions naming conventions, following the production-line precedent exactly. User never named the route or table
- 8 endpoint (bukan 5 CRUD biasa) ← 3 endpoint tambahan di luar pola master data biasa: /unverified-count, /close, /reopen. User menyatakan aksinya, pemecahannya menjadi endpoint terpisah adalah turunan agen
- close() memakai UPDATE BERSYARAT `WHERE id=? AND status <> 'closed'` lalu memeriksa affected_rows ← agent's answer to the "dua Admin menutup bersamaan" edge case; user never specified a concurrency mechanism. Alternatif (SELECT FOR UPDATE / optimistic version column) tidak dipakai karena satu UPDATE sudah cukup
- error code baru: PERIOD_OVERLAP (422), PERIOD_CLOSED_IMMUTABLE (409), PERIOD_ALREADY_CLOSED (409), PERIOD_NOT_CLOSED (409) ← seluruhnya dinamai agen. Hanya PERIOD_CLOSED yang berasal dari keputusan user (shared-decisions v5)
- definisi "belum terverifikasi" = `checked_by IS NULL OR acknowledged_by IS NULL` ← agent-derived. User berkata "jumlah record yang belum terverifikasi" tanpa mendefinisikan mana dari dua tahap verifikasi yang dihitung. Konsekuensinya: record yang sudah di-check Supervisor tapi belum di-acknowledge Mill Management TETAP dihitung sebagai belum terverifikasi
- unverified-count dihitung on-demand saat dialog dibuka, bukan disimpan sebagai snapshot ← agent's performance call; menyentuh hingga 18 tabel per panggilan
- batas rentang periode bersifat INKLUSIF di kedua ujung, dan dua periode yang bersentuhan tepat di batas dianggap beririsan ← agent-derived; tidak pernah dibahas
- station_type_label "Semua Stasiun" untuk station_type NULL ← mengikuti frasa yang sudah dipakai di business spec information_displayed
- per_page dibatasi maksimum 100 ← mengikuti shared-decisions.pagination.defaults
- test_scenarios: 20 entri, unit_test_cases: 46 (29 + 17) ← diturunkan oleh test-spec-writer-agent dari 20 bdd_scenarios Phase 2, tidak dikonfirmasi satu per satu (autopilot)
- CATATAN PROSES: v1 sempat ditulis dengan derivasi test yang dibuat langsung oleh command ini karena test-spec-writer-agent tampak mandek (transcript berhenti tumbuh 2,5 menit tanpa hand-back). Agent ternyata selesai tepat setelah v1 ditulis, dan hasilnya lebih lengkap — v2 menggantikan seluruh unit_test_cases dan test_scenarios dengan keluaran agent. Tidak ada isi v1 yang bertahan di bagian itu
- 4 test_scenario (data mobile menyusul, upaya verifikasi, mengubah data stasiun, data di luar rentang) memakai endpoint record stasiun yang TIDAK ada di api_contracts screen-128 ← keputusan agen untuk mempertahankannya sebagai kontrak lintas-layar alih-alih membuangnya. Konsekuensi nyata di Phase 4: keempatnya tidak akan lolos saat screen-128 diimplementasikan sendirian

## v3 — 2026-09-23

Kontrak baru `POST /api/periods/{id}/open` (usecase-144). Sengaja dibuat cermin `reopen`.

- **Tidak mendaftarkan `401 UNAUTHENTICATED`** ← test-spec-writer sempat mengusulkannya, dan itu keliru. Diverifikasi ke tech spec v2: `close` dan `reopen` hanya mendaftarkan 404/409/403; satu-satunya endpoint periode yang mendaftarkan 401 adalah `GET /api/periods`. Penanganan sesi ada di lapisan middleware, bukan di kontrak ini. Menyamakan lebih penting daripada melengkapi.
- `error_code` = `PERIOD_NOT_DRAFT` dengan http 409 ← meniru `PERIOD_NOT_CLOSED` milik `reopen`. Exception barunya `PeriodNotDraftException`, salinan struktur `PeriodNotClosedException`.
- **UPDATE berkondisi `WHERE id=? AND status='draft'`, bukan `save()` pada model** ← kondisi pada WHERE itu sendiri yang menjadi penjaga dua Admin bersamaan; tidak ada penguncian baris maupun transaksi tambahan. Pola ini disalin dari `close()`, yang komentarnya di `PeriodClosureService` baris 120-131 menjelaskan alasannya.
- **Satu unit test khusus mengasersi nol pemanggilan repository record stasiun** ← `close()` memang menghitung data belum terverifikasi, sehingga `open()` mudah dianggap simetris dan ikut dibebani validasi data. Test itu yang menahannya.
- **Satu unit test khusus mengasersi periode `open` tetap bisa di-update dan di-delete** ← `PeriodService::update()`/`delete()` hanya menolak `closed`. Sangat mudah seseorang "merapikan" ini menjadi ikut mengunci `open`; test itu yang menangkapnya.
- Skenario "status Terbuka tidak dapat dikembalikan ke Draft" berakhir **200**, bukan 422 ← diverifikasi ke `PeriodService::validate()`: field `status` tidak pernah divalidasi maupun ditulis, jadi kiriman itu **diabaikan**, bukan ditolak. Perbedaan yang halus tapi penting bagi penulis test.
- `DELETE` berakhir **200** (`{"deleted": true}`), bukan 204 ← diverifikasi ke `PeriodController::destroy()`.
- Dialog konfirmasi (`askOpen`/`cancelOpen`/`confirmOpen`) ← meniru pasangan close/reopen yang sudah ada. Jalur batal tidak punya bdd_scenario tersendiri, jadi ditulis sebagai catatan implementasi agar tetap diuji.

## v4 — 2026-09-27

Kontrak `usecase-140` dan `usecase-144` DIPINDAHKAN ke screen-142 (bukan dibuang), dan kontrak
`usecase-128` yang tersisa disusulkan ke model induk–anak yang sudah berjalan di kode.

- Seluruh `success_schema` periode diganti dengan bentuk `PeriodService::toRow()` (induk + `stations[]` + `station_count` + `closed_station_count` + `is_immutable` + `status_summary`) ← dibaca dari docblock `toRow()`; v3 masih memakai bentuk pipih ber-`status`/`station_type` yang kolomnya sudah dihapus dari database
- Induk sengaja TIDAK punya `status` ← alasan disalin dari `toRow()`: kunci bernama `status` akan mengundang `$row['status'] === 'closed'` hidup terus. Dicatat sebagai business_logic langkah 0 supaya tidak "dirapikan" kembali
- `PERIOD_CLOSED_IMMUTABLE` pada PATCH/DELETE dijawab dengan aturan "ANY" (satu baris closed sudah cukup) ← dari `guardAgainstClosedStation()`
- Urutan `PATCH` = guard 409 → validate → overlap → UPDATE → backfill ← dari `update()`; ditulis eksplisit karena posisi backfill SETELAH guard itulah sumber lubang backfill
- Lubang backfill dicatat sebagai `edge_case_handling` DAN `implementation_notes`, dengan jalan keluar manual (buka kembali → simpan → tutup lagi) ← turunan agen dari docblock `update()`; user tidak diminta memutuskan perbaikan tuntasnya
- 48 `unit_test_cases` (dari 29 milik usecase-128 di v3) ← ditulis ulang oleh agen terhadap perilaku kode saat ini; 12 kasus lama yang menguji `station_type`/`Semua Stasiun`/status periode tunggal digantikan kasus yang menguji baris stasiun, backfill add-only, aturan immutable "ANY", dan makna filter EXISTS. Tidak ada kasus yang dihapus tanpa penggantinya
- 13 `test_scenarios` (dari 9 milik usecase-128 di v3) ← 9 lama dipertahankan dengan payload tanpa `station_type`, ditambah 4 baru: mill tanpa stasiun aktif, backfill lewat simpan ulang, makna filter Status Stasiun, dan navigasi ke layar detail. 19 `test_scenarios` milik usecase-140/144 PINDAH ke screen-142
- Daftar `data-testid` yang hilang vs yang tetap ditulis eksplisit di `implementation_notes` ← turunan agen; dibuat agar langkah implementasi (dan helper e2e-web) punya satu daftar yang bisa dipakai memeriksa dirinya sendiri
- Endpoint BARU `GET /api/periods/{id}` TIDAK didaftarkan di artefak ini melainkan di screen-142 ← ia hanya dipakai layar detail; catatan urutan rute (literal sebelum berparameter) tetap ditulis di sini karena rute /periods-nya milik layar ini
