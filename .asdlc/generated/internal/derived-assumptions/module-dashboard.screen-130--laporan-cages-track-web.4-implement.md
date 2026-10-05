# Derived Assumptions — module-dashboard.screen-130--laporan-cages-track-web.4-implement

## v1 — 2026-09-24

Phase 4 mengimplementasikan tech spec apa adanya, jadi yang dicatat di sini hanya pilihan yang
tidak ditentukan spec — ditambah dua koreksi terhadap spec itu sendiri.

- **`Carbon::instance($start)->diffInMinutes(Carbon::instance($stop), false)` dengan flag dan arah ditulis eksplisit** ← spec (dan briefing saya) menulis bentuk literal `$stop->diffInMinutes($start)`. Repo memakai nesbot/carbon 3.13.2 yang mengembalikan selisih BERTANDA sementara Carbon 2 mengembalikan absolut, sehingga bentuk literal itu menghasilkan −720 menit untuk jendela 06:00→18:00 yang sah — persis nilai negatif yang dilarang spec. Agen menolak menyalin briefing mentah-mentah, dan benar.
- **KOREKSI SPEC: jeda terpanjang diurutkan menurut waktu berlalu sejak jendela dibuka** ← lihat [[module-dashboard.screen-130--laporan-cages-track-web.3-tech-spec]] v2. Kesalahan ada di spec v1, bukan di implementasi. Ditemukan lewat verifikasi pasca-implementasi, dibuktikan dengan memanggil `longestGapOf([0,1,22,23])` langsung (hasil 21, seharusnya 1).
- **Tanggal tanpa jendela kembali ke urutan numerik** ← pilihan implementasi yang kemudian dinaikkan ke spec. Alternatifnya mengarang jangkar, yang berarti mengada-adakan batas shift yang tidak pernah dicatat.
- **`guardAccess()` dipanggil di service, dan di `authorizePeriod()` berjalan SEBELUM `findOrFail`** ← spec hanya menyebut "operator ditolak 403". Urutannya tidak disebut, tetapi menentukan: guard setelah `findOrFail` membocorkan keberadaan sebuah `period_id` lewat selisih 403 vs 404.
- **Urutan entri `REPORT_ROUTES` mengikuti `sort_order`** ← tidak diminta; dipilih agar asersi terurut di `Feature/Api/LaporanStasiunTest` tetap hijau tanpa disentuh.
- **Asersi `laporan-stasiun.spec.ts` diubah menjadi invarian** ← keputusan user 2026-09-24 atas `spec_mismatch`. Bukan turunan agent; dicatat di sini karena mengubah berkas test milik screen-140, bukan screen-130.
- **Dua skenario browser ditulis `test.skip` beralasan** ← "hari ber-record tanpa rincian" dan "akun terikat mill tanpa mill" keduanya diblokir validator aplikasi sendiri, jadi tidak ada layar yang dapat menyemainya. Bukan kelalaian: perilakunya tertutup di lapis unit, Api, dan Livewire.
- **`e2e-web/tests/laporan-stasiun.spec.ts` masuk `test_files_generated`** ← berkas milik screen-140, diubah oleh run ini. Dicatat agar jejaknya tidak hilang.

## re-track — 2026-10-03 (isi artefak tidak berubah, tetap v1)

Node dep-graph di-track ulang tanpa menulis artefak, atas keputusan user 2026-10-03 ("re-track tanpa ubah isi").
- Penyebab stale: entity-catalog v20 (49dc0c5) hanya mengganti satu kalimat status implementasi kunci periode (BELUM → TERIMPLEMENTASI); turunan dari re-track tech-spec, shared-decisions, entity-models, dan shared-modules pada putaran yang sama — penilaian agen, tidak dinyatakan user per layar

## v2 — 2026-10-03

Hasil run penuh e2e-web 2026-10-03 (530 lulus, 0 gagal, 9 skip).
- test_results.browser = 20 lulus/0 gagal dari e2e-full.log.counts.json (spec laporan-cages-track); 2 skip dicatat di catatan, bukan di test_results (skema tidak punya field skip).
- Known_issue tentang 2 test.skip beralasan dipertahankan — bukan 'tidak dijalankan'.

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (CagesTrackReportService.php, laporan-cages-track.blade.php, e2e-web/tests/laporan-cages-track.spec.ts, e2e-web/tests/support/backend.ts, git status).
- files_generated[+] = backend/app/Support/SheetWriter.php, backend/app/Support/ExportValue.php ← dipakai export()
- test_files_generated[+] = backend/tests/Feature/ReportAuditFix20261004Test.php, backend/tests/Feature/ExportXlsxTest.php ← menguji ekspor/#9/#10 Cages Track
- known_issues[5].description = DIPERBAIKI — pruneLaneData menyapu record & periode lajur ← diff spec + docblock backend.ts. ⚠ Bahwa pruneLaneData memanggil perintah backend PruneE2eRecords disimpulkan dari nama berkas yang berubah, tidak dibaca langsung
- implementation_notes[+] = REVISI (2026-10-04, audit-fix …) ← ringkasan diff
- test_results tidak diubah

## v4 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit db73fbd, c321f32), code is truth (laporan-*.blade.php, report-filter-bar.blade.php, tests/Feature/Livewire/Laporan*Test.php, e2e-web/tests/laporan-*.spec.ts).
- implementation_notes[+] ← report-filter-bar, loading ekspor & .ld-region, uji yang disesuaikan
