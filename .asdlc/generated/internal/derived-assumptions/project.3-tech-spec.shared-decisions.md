# Derived Assumptions Log — project.3-tech-spec.shared-decisions

## v1 — 2026-08-17

- auth.mechanism / auth.session_strategy / auth.notes ← diturunkan dari arch_spec.tech_stack ("Laravel Sanctum + session auth") dan nfr.security, diterima tanpa koreksi
- error_format (structure, example, notes) ← diturunkan dari konvensi standar Laravel, tidak dinyatakan eksplisit di arch-spec
- pagination.strategy / pagination.defaults / pagination.notes ← diturunkan dari konvensi pagination bawaan Laravel, tidak dinyatakan eksplisit di arch-spec
- naming_conventions (api_endpoints, db_tables, db_columns) ← diturunkan dari tech stack Laravel/MySQL, tidak dinyatakan eksplisit di arch-spec
- other_decisions (timestamps UTC ISO 8601, file storage Laravel Filesystem local disk, UUID untuk entitas offline mobile) ← diturunkan dari constraints/goals di PRD, bukan pernyataan eksplisit di shared-decisions

## v5 — 2026-09-22

- error_format.structure += field `code` (UPPER_SNAKE_CASE, machine-readable) ← user stated mobile must be able to tell "periode ditutup" apart from an ordinary validation error so the record is not dropped from the sync queue; adding a `code` field to the shared error envelope is the agent's way of making that possible. Note this also closes a long-standing major known_issue on screen-001 ("ApiExceptionHandler belum mengeluarkan field error_code yang machine-readable"). Additive change — existing message/errors responses stay valid
- other_decisions += HTTP 422 dengan code=PERIOD_CLOSED sebagai penolakan sementara ← user stated the behaviour (ditolak, data tertahan di HP, ikut sync lagi setelah dibuka); the specific status code and code name are the agent's
- naming_conventions.notes += error code memakai UPPER_SNAKE_CASE ← agent-derived, follows the existing INVALID_DATE_RANGE / EXPORT_FAILED codes already used in the api-index
- pagination.notes += endpoint laporan periode tidak dipaginasi ← agent decision; user never discussed pagination for the new report screens
- auth.notes += catatan route ekspor menumpuk middleware 'web' ← documents the 401 root cause found and fixed earlier in this same session; user asked "kenapa unauthenticated saat export?" but did not ask for it to be recorded in shared-decisions
- autopilot: seluruh sub-bagian shared-decisions lain (auth mechanism, pagination strategy, naming conventions, integrations) dibawa apa adanya dari v4 tanpa konfirmasi ulang, sesuai Step 6 fast-path

## v6 — 2026-09-25

- `other_decisions[3]` ditulis ulang ke bentuk per-stasiun, termasuk kueri JOIN ← user tidak menyebut artefak ini sama sekali; ditemukan lewat pemeriksaan bahwa 21 kemunculan kata "period" ada di dalamnya. Tanpa pembaruan ini, `shared-decisions` akan tetap menyatakan kunci berlaku per periode sementara `entity-catalog` menyatakan per stasiun — dua sumber kebenaran yang bertentangan.
- `other_decisions[6]`: peringatan record belum terverifikasi dihitung UNTUK JENIS STASIUN YANG AKAN DITUTUP ← user tidak menyatakan cakupan hitungannya. Dipilih per-stasiun karena aksi tutup kini bertarget satu stasiun; hitungan se-periode akan memasukkan stasiun yang tidak terdampak aksi itu dan membuat peringatannya menyesatkan.
- `other_decisions[8]` (baru) "PERIODE ADALAH INDUK TANPA STATUS" ← tidak diminta; ditambahkan agar bahaya atribut-hilang-jadi-null terdokumentasi di tingkat proyek, bukan hanya di constraint satu entitas, karena yang terdampak tersebar di service, Livewire, dan lima ReportService.

## v7 — 2026-09-28

- Lima butir `other_decisions` baru ← user menetapkan empat keputusan isolasi line, tetapi tidak meminta pencatatannya di artefak. Dicatat karena tanpa itu rumah tunggal `ScopesToActorMill` akan terbaca sebagai pilihan gaya, bukan sebagai jawaban atas enam salinan `resolveBusinessUnit()` yang menyebabkan FormSterilizer benar sementara 15 saudaranya tidak.
- Butir "empat kebocoran yang ditutup" memuat cara pembuktiannya ← tidak diminta. Dimasukkan karena ketiganya terbukti lewat probe atau pembacaan kode, dan artefak yang hanya menyatakan "sudah ditutup" tidak memberi pembaca berikutnya cara memeriksanya lagi.
- Butir "kunci periode tidak bertambah dimensi line" memuat konsekuensi yang tidak diminta user ← yakni bahwa Line 1 tidak dapat ditutup selama Line 2 menyisakan record belum terverifikasi. Itu akibat nyata dari keputusannya dan layak tercatat sebelum ada yang menganggapnya bug.

## v8 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (config/app.php, AppTime, EnforcesPeriodLock, PeriodService, PeriodHasRecordsException, ApiExceptionHandler, EnsureUserIsActive, AppServiceProvider, UserService, PasswordPolicy, RouteAccess, GuardsRecordIdShape, SheetWriter, ExportValue, Display, ReportPeriodDays, ChartAxis, UniqueCaseInsensitive, RealImage, ValidatesUploadOnSelect, mobile utils/localDate.ts, localSchema.ts).
- auth.notes = + akun nonaktif (EnsureUserIsActive web+api, 401/redirect berpesan; Sanctum menolak token user nonaktif; token dicabut saat nonaktif) + PasswordPolicy tunggal + RouteAccess sidebar/403 + Operator boleh login web terbatas (/beranda, /settings/password) ← kode
- error_format.notes = + 409 PERIOD_HAS_RECORDS; QueryException 22P02/22007/22008 → 422 VALIDATION_ERROR, lainnya 500 generik tanpa teks SQL ← ApiExceptionHandler
- naming_conventions.notes = + PERIOD_CLOSED_IMMUTABLE, PERIOD_HAS_RECORDS, VALIDATION_ERROR ← kode yang dipancarkan handler/exception
- other_decisions[0] = ZONA WAKTU WIB (Asia/Jakarta) menggantikan "Timestamps disimpan UTC format ISO 8601"; normalisasi input ber-zona; mobile tanggal lokal ← config/app.php + AppTime + localDate.ts
- other_decisions += BATAS ATAS TANGGAL KEJADIAN (besok WIB, EVENT_DATE_MAX_DAYS_AHEAD) ← AppTime::latestEventDate + assertEventDateNotTooFarAhead
- other_decisions += KUNCI PERIODE PER BARIS DETAIL (CPO/Kernel Dispatch, Solid Waste) + kalimat 'verify' ← EnforcesPeriodLock
- other_decisions += PERIODE YANG MEMBINGKAI DATA TIDAK BOLEH DIHAPUS (409) ← PeriodService/PeriodHasRecordsException
- other_decisions += ID BUKAN-UUID = TIDAK DITEMUKAN (GuardsRecordIdShape; UUID divalidasi di service) ← kode
- other_decisions += EKSPOR TABEL (SheetWriter xlsx sungguhan, ExportValue vs Display) ← kode
- other_decisions += KELENGKAPAN LAPORAN PERIODE (ReportPeriodDays, days_counted/period_running, ChartAxis) ← kode; "respons laporan membawa days_counted/period_running" digeneralisasi agen — terverifikasi di Weighbridge/StorageTank/Clarification/BoilerRoom report service
- other_decisions += VALIDASI MASTER DATA & UNGGAHAN (UniqueCaseInsensitive, RealImage, ValidatesUploadOnSelect) ← kode

## v9 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit 30b7f27 / f79b1fe), code is truth (EnsureUserIsActive.php, AppServiceProvider.php, UserService.php, ScopesToActorMill.php, Display.php, ExportValue.php, RecordVerification*Controller.php, mobile apiClient.ts/auth.ts/errorHandler.ts/main.ts/optionLabel.ts).
- auth.notes += reset password mencabut sesi (sessions_revoked_at, REVOKED_MESSAGE) + penanganan 401 terpusat mobile.
- error_format.notes += verifikasi 404/403 via abort → NOT_FOUND/FORBIDDEN; 409 hapus Station ber-record (StationHasMachineryException); SESSION_REVOKED_MESSAGE mobile.
- other_decisions += filter baca bukan-UUID diabaikan; peta label enum tunggal (Display/ExportValue/optionLabel.ts).
- ⚠ 'handler hanya berjalan sekali per sesi' ← disimpulkan dari pemeriksaan Authorization = token sesi saat ini (bukan flag eksplisit).

## v10 — 2026-10-05

Sumber: perbaikan lanjutan audit 2026-10-05 (belum di-commit), code is truth (mobile syncService.ts/writeThroughSync.ts; backend StationHasMachineryException/BusinessUnitHasStationsException/ProductionLineHasStationsException).
- auth.notes += SINKRON BERHENTI DI 401 PERTAMA (manual + write-through, tanpa sync_error).
- error_format.notes += 3 delete-guard 409 kini membawa code (HasErrorCode); kasus 'record stasiun' Station juga STATION_HAS_MACHINERY.
- ⚠ 'Delete-guard lain (Company/Corporate/MachineryGroup) belum membawa code' — dicatat agen sebagai sisa inkonsistensi, tidak diubah (di luar cakupan perintah).

## v11 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit 54f6a13, 658cedc, db73fbd, c321f32, d5da9cf, e4f231e, ee5294c), code is truth (AuthController::logout, routes/api.php, errors/404.blade.php, NotFoundPageTest, SignalDownloadReady, loading-assets, busy-label, filter/*, HasFilterReset, report-filter-bar, mobile stores/auth.ts, components/loading/*, components/filters/*, useBusyAction, latestRequest, App.vue, floatingSafeArea.ts, chatbot-widget).
- auth.notes += LOGOUT MOBILE MENCABUT TOKEN: POST /api/logout (token saat ini saja / sesi web), 200 'Logout berhasil.', 401; logout offline hanya lokal; token tak kedaluwarsa; loggingOut + logout idempoten.
- error_format.notes += halaman 404 web (kartu mandiri vs di dalam shell, api/* tetap JSON NOT_FOUND, tanpa Route::fallback).
- other_decisions += 4 entri: filter daftar web (x-filter.* + HasFilterReset + report-filter-bar), filter daftar mobile (components/filters/*, SearchableSelect), loading state & cegah aksi ganda (web + mobile, SignalDownloadReady/ms_download, useBusyAction, latestRequest), elemen mengambang (dok bawah mobile, chatbot web tersembunyi saat modal).
- ⚠ uiux-spec TIDAK di-bump: component_patterns 'loading' (skeleton + spinner tombol, tidak memblokir input lain) masih konsisten dengan kode; pola filter bar / loading global / dok mengambang yang baru didokumentasikan di sini dan di shared-modules agar tidak memicu cascade ke 144 business spec (brief: utamakan shared-decisions/shared-modules).
- ⚠ Teks 'Record tidak ditemukan' (GuardsRecordIdShape) dinyatakan tetap di dalam layar, bukan halaman 404 — disimpulkan dari kode, tidak diuji ulang.

## v12 — 2026-10-12

- other_decisions += tujuh keputusan dari putaran QA blackbox (filter web di query string, escape wildcard LIKE, periode direset saat ganti mill, dua kalimat untuk BUSINESS_AREA_MISMATCH + galat per kolom, penjaga stasiun aktif mobile, mill-setting dipakai layar mobile, daftar kosong ≠ pengambilan gagal) ← user hanya minta "update artefak" sesudah 13 temuan diperbaiki; agen memilih shared-decisions sebagai rumahnya karena ketujuhnya lintas-layar, mengikuti bentuk entri [21]–[26] yang sudah ada untuk keputusan lintas-layar sejenis
- Ketujuh entri dicatat di sini dan TIDAK disalin ke spec 18/11 layar per keluarga ← agent derived; satu aturan yang disalin 18 kali adalah aturan yang akan menyimpang, dan dep-graph sudah menandai tech-spec hilir sebagai stale sehingga penelusurannya tidak hilang
- Penjaga stasiun aktif mobile: AMBANG penolakan = hanya keadaan yang diketahui "tidak aktif", dengan penanda cache-segar per line sebagai pembeda ← dokumen QA MB-FORM-15 hanya menuntut penolakan; batas itu diturunkan agen agar pencatatan offline di perangkat baru tidak ikut mati, yaitu sifat yang paling penting dari aplikasi ini
- Jenis stasiun pada penjaga itu diturunkan dari NAMA RUTE (`<slug>-form`), bukan konstanta per view ← agent derived, menghindari 18 salinan daftar literal
- Pemilih periode mendapat option penampung "Pilih Periode" ← dokumen QA LP-W-09 hanya menuntut pilihan direset ke kosong; agen menemukan bahwa reset saja tidak terlihat tanpa option bernilai '' karena peramban menandai option pertama sebagai terpilih
- Penanda ganti-mill ditetapkan di hook `updating`, bukan `updated` ← agent derived sesudah versi pertama (di `updated`) juga mematikan pengisian otomatis bagi Admin yang memilih mill PERTAMANYA, yang terlihat sebagai 31 uji laporan gagal

## v13 — 2026-10-12

- other_decisions[32] ditulis ulang ← judulnya sendiri berubah arah: dari "MILL-SETTING BENAR-BENAR DIPAKAI LAYAR MOBILE" menjadi "KONTEKS MILL/LINE DICETAK DI LAYAR MOBILE, TETAPI BARIS MEREK TETAP NAMA PRODUK". Akibat keputusan user memulihkan aturan header screen-005 v3
- "AKIBAT YANG TERBUKA: app_name dan logo tidak dibaca satu layar pun" dinyatakan di dalam entri keputusan itu sendiri, bukan hanya di screen-034 ← agent derived; entri lintas-layar inilah yang akan dibaca orang berikutnya yang menambah pengaturan ke Mills Setting, jadi di sinilah pelanggaran aturannya harus terlihat
- aturan umum "pengaturan baru harus disertai layar yang membacanya" DIPERTAHANKAN meski kini ada dua pengaturan yang melanggarnya ← agent derived; aturan itu tetap benar dan pelanggaran yang tercatat lebih berguna daripada aturan yang dilunakkan agar cocok dengan keadaan

## v14 — 2026-10-12

- other_decisions[32] ditulis ulang untuk ketiga kalinya dalam satu hari, dan judulnya berubah lagi ← urutannya: (1) "MILL-SETTING BENAR-BENAR DIPAKAI LAYAR MOBILE" saat app_name/logo mulai dibaca Home, (2) "...BARIS MEREK TETAP NAMA PRODUK" saat pembacaan itu dibatalkan, (3) sekarang "...DAN PENGATURAN TANPA PEMBACA DICABUT" saat kolomnya dihapus. Ketiganya keputusan user; entri ini sengaja menyimpan seluruh urutannya, bukan hanya keadaan akhir, supaya tidak ada yang mengulang langkah pertama tanpa melihat mengapa ia dibatalkan
- dua konsekuensi teknis dinaikkan menjadi aturan UMUM, bukan catatan satu layar ← agent derived: (1) endpoint mengabaikan kunci yang kolomnya dicabut, dan (2) tabel SQLite perangkat lama tetap punya kolom lamanya sehingga kolom nullable yang tak terpakai tidak memerlukan migrasi lokal. Keduanya akan berlaku persis sama pada pencabutan kolom berikutnya, dan di sinilah tempatnya dibaca
- "jaminan uji yang kendaraannya dicabut DIPINDAHKAN, tidak dibuang" dicatat sebagai bagian keputusan ← agent derived; tiga jaminan nyata (unggahan tersimpan, format ditolak, pesan berbahasa Indonesia) hanya diuji lewat logo, dan menghapus ujinya bersama kolomnya akan menurunkan cakupan tanpa ada yang menyadarinya

## v15 — 2026-10-16 · penandaan `shift_id` pada ke-18 tabel record

### Yang DINYATAKAN pemilik produk (bukan asumsi)

1. **Record yang diinput lewat web ditandai dari JAM SIMPAN di server.** Dipilih setelah konsekuensinya disampaikan: form web punya Tanggal yang boleh dimundurkan, sehingga input susulan tertandai shift saat input — bukan shift saat kejadian. Dua alternatif ditolak: manusia memilih shift di form (satu field di 18 form), dan membiarkan record web tanpa shift.
2. **Saat tidak tercakup shift mana pun, record TETAP TERSIMPAN dengan `shift_id` null** — tidak ditolak.
3. **Ke-18 stasiun sekaligus**, bukan pilot satu stasiun.

### Yang SAYA turunkan, dan alasannya

- **Satu endpoint baru `/api/production-lines/current/shifts`, bukan melonggarkan `/api/shifts`** ← layar Kelola Shift admin-saja sampai ke dalam service-nya, sementara yang butuh jadwal ini justru operator. Melonggarkan gate di sana membuka seluruh layar master data.
- **Bentuk barisnya minimal (id, name, start_time, end_time)** ← perangkat menghitung sendiri shift mana yang berjalan; mengirim cakupan/celah/urutan hanya menciptakan perhitungan kedua yang dapat berselisih dengan server.
- **Batas SETENGAH TERBUKA `start <= m < end`**, berbeda dari batas inklusif `findOverlap()` ← dua pertanyaan yang berbeda. "Bolehkah dua shift berdampingan" dijawab inklusif supaya pergantian berimpit diterima; "menit ini milik siapa" wajib punya TEPAT SATU jawaban.
- **Pemilihan deterministik pada data bertindih** (segmen yang paling baru dimulai) ← tumpang tindih mustahil lahir dari aplikasi tapi mungkin dari factory/data lama; jawaban yang bergantung pada urutan baris adalah yang paling mahal ditelusuri.
- **`shiftIdAt()` TANPA `guardAdmin()`** — satu-satunya metode publik ShiftService yang begitu ← pemanggilnya ke-18 record service saat OPERATOR menyimpan; gate admin di sini menjatuhkan setiap penyimpanan operator dengan 403. Diasersi oleh test supaya "kelalaian" ini tidak diperbaiki orang berikutnya.
- **`UPDATE` tersendiri untuk stempel di mobile, bukan ikut UPDATE header** ← ke-18 UPDATE header berbeda jumlah dan urutan parameternya; menyisipkan satu parameter ke tengah 18 daftar berbeda adalah tempat kekeliruan transkripsi hidup, dan kekeliruan itu menyimpan nilai ke kolom salah lalu lolos karena kedua kolomnya TEXT.
- **Stub global `shiftRepo` di setup test mobile** ← 20 spec repo memakai antrian `mockResolvedValueOnce`, jadi jawabannya ditentukan URUTAN PANGGILAN bukan SQL-nya; dua pembacaan baru menggeser semuanya dan 164 test memerah tanpa ada yang rusak. Menyesuaikan antrian di 20 berkas justru MEMPERDALAM ketergantungan itu.
- **ON DELETE RESTRICT**, bukan cascade/set null ← sama dengan `station_id` dan `production_line_id` pada ke-18 tabel yang sama; record adalah bukti pekerjaan yang sudah terjadi.
- **Index kolom tunggal per tabel** ← PostgreSQL tidak meng-index kolom FK otomatis; tanpa ini setiap pemeriksaan FK memindai 18 tabel berurutan.

### Dua bug nyata yang DITEMUKAN TEST, bukan oleh pembacaan

Keduanya ditemukan karena `tests/Feature/ShiftStampingTest.php` ditulis sebelum dianggap selesai, dan keduanya tidak akan terlihat dari jalur mana pun yang sudah ada:

1. **`$request->only([...])` di ke-18 controller tidak memuat `shift_id`** — stempel perangkat DIBUANG diam-diam dan server menurunkan sendiri. Seluruh alur tetap 201, record tetap bershift, dan nilainya salah secara sistematis persis pada kasus yang menjadi alasan fitur ini ada.
2. **Pemeriksaan kepemilikan line tidak pernah berjalan** sebagai akibat (1) — shift line lain tidak akan pernah ditolak.

Catatan ketiga, untuk diri sendiri: asersi `expect(...)->toContain($needle, $message)` saya pakai lagi di test baru ini, dan ia memerah menuntut array memuat kalimat pesannya sendiri. Jebakan itu sudah tercatat di [[feedback-vacuous-assertions]] sejak 2026-10-15. Dibiarkan tertulis di komentar test-nya supaya pembaca berikutnya mengenalinya.
