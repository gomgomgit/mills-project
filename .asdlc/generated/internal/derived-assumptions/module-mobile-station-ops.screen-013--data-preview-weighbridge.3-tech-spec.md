# Derived Assumptions Log — module-mobile-station-ops.screen-013--data-preview-weighbridge.3-tech-spec

## v1 — 2026-08-17

- Keputusan bahwa screen ini tidak memiliki endpoint API (baca lokal SQLite by id) ← disimpulkan agent, konsisten dengan pola screen sebelumnya

## v2 — 2026-08-18

- Mode list & detail berbagi 1 komponen/route (dibedakan via ada/tidaknya :id param), bukan 2 screen terpisah ← desain agent, hemat duplikasi, konsisten dengan konvensi route :id? yang sudah ada sejak v1
- Filter tanggal & search dilakukan client-side terhadap hasil findAll() (bukan query ulang per keystroke) ← keputusan performa, volume data per user kecil
- Back di mode detail → list (bukan Monitor); Back di mode list → Monitor ← 2 perilaku Back berbeda tergantung mode, instruksi eksplisit user
- test scenarios: 18 unit (level repo/computed), 0 API, 9 component, 9 browser ← delegated test-spec-writer-agent dari Phase 2 bdd_scenarios (9 skenario)

## v3 — 2026-08-18

- dateFilter default = tanggal lokal hari ini, diisi SEKALI saat mount di mode list ← Back dari detail TIDAK reset filter yang sudah diubah user kembali ke hari ini (hanya reset saat komponen benar-benar di-mount ulang, bukan setiap kali balik ke mode list dalam instance yang sama)
- 3 unit test baru (init default, filter dgn default, ubah/kosongkan default) ditambahkan ke 18 yang sudah ada = 21 total
- 1 test_scenario baru "Filter Tanggal Default Hari Ini" ditambahkan ke 9 yang sudah ada = 10 total ← awalnya salah GANTI skenario "Filter Diterapkan" yang sudah ada, dikoreksi sebelum lanjut ke checkpoint

## v4 — 2026-08-19

- business_logic step 8 diperluas untuk secara eksplisit menyebut label record_datetime per weighbridge_type dan kondisi render destination, alih-alih tetap generik "render semua field" ← konsekuensi langsung dari entity-catalog v5 (weighbridge_type, record_datetime, destination) dan instruksi user untuk menyesuaikan tampilan load data; bukan perubahan endpoint/data source
- 3 unit test baru ditambahkan (label Arrival vs Dispatch, render destination hanya saat dispatch) = 22 total, tanpa menghapus test lama ← turunan langsung dari perubahan business_logic di atas; test-spec-writer-agent tidak dipanggil (batasan eksekusi sebagai forked worker, tidak boleh spawn subagent) sehingga derivasi dilakukan manual oleh coordinator-fork mengikuti pola test-spec-writer-agent yang sudah ada di v1-v3
- 1 test_scenario baru "Field Sesuai Tipe Weighbridge" ditambahkan (component+browser) = 11 total ← sama, derivasi manual mengikuti pola existing
- Tidak ada unit test terpisah untuk label "(tandan)" pada quantity ← ini teks statis pada label field (bukan logic bercabang), konsisten dengan keputusan yang sama di screen-010 v6

## v5 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Endpoint PATCH /api/records/{stationType}/{id}/verification TIDAK ditambahkan ke `endpoints` karena list itu kosong (tak ada bentuk untuk disalin); didokumentasikan di business_logic + implementation_notes.
- Langkah verifikasi ditambahkan sebagai langkah bernomor baru di akhir business_logic (mengikuti penomoran yang ada), bukan disisipkan ke langkah Mode DETAIL.
- Edge case menggabungkan PERIOD_CLOSED dan tanpa jaringan dalam satu item; wording pesan offline disalin dari RecordVerificationActions.vue; pemulihan via screen-142 diambil dari brief.
- unit_test_case PERIOD_CLOSED ditulis sebagai kasus level komponen (RecordVerificationActions) dengan galat ternormalisasi apiClient {status:422, message} — perilaku 'baris lokal tidak berubah' disimpulkan dari urutan kode setVerification() (UPDATE lokal hanya setelah PATCH sukses).
- Catatan implementation_notes ditandai sebagai pengecualian atas catatan lama 'tidak ada panggilan API' (catatan lama tidak dihapus).

## v6 — 2026-10-03

Pembersihan teks spec usang / sinkronisasi dengan perbaikan 2026-10-03.
- implementation_notes[0] yang menyatakan "tidak ada panggilan API" diganti: data dibaca dari SQLite lokal; satu-satunya panggilan server adalah aksi verifikasi RecordVerificationActions.vue → recordVerificationApi.setVerification → PATCH /api/records/{stationType}/{server_id}/verification, hanya untuk record tersinkron (punya server id).
- Teks catatan record belum tersinkron dikutip langsung dari RecordVerificationActions.vue (data-testid verification-not-synced); frasa "kolom verifikasi baris lokal baru diperbarui setelah server menerima" diturunkan dari recordVerificationApi.setVerification (UPDATE lokal setelah respons PATCH).
- Catatan "Kunci periode … pengecualian atas 'tidak ada panggilan API'" dibiarkan apa adanya (masih akurat sebagai rujukan historis).

## v7 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (mobile/src/views/DataPreviewWeighbridgeView.vue, services/recordVerificationApi.ts, services/apiClient.ts, components/SyncFailureHint.vue, services/syncService.ts, backend RecordVerificationStatusController/Service, routes/api.php).
- api_contracts[0].endpoints += GET /api/records/{stationType}/verification?ids[]= (auth:web,sanctum, role admin/supervisor/mill_management/operator, mill-scoped; 401/403/422/404) ← routes/api.php + RecordVerificationStatusController. ⚠ error_code untuk 404 ditulis NOT_FOUND padahal controller mengembalikan {message} saja (inferensi label).
- api_contracts[0].business_logic += step 14 (pull verifikasi best-effort, maks 100, timeout 5 dtk, reload detail bila berubah) & 15 (SyncFailureHint dari sync_error) ← pullVerificationStatus(), refreshVerificationInBackground(), SyncFailureHint.vue.
- api_contracts[0].data_operations += UPDATE lokal checked_by/acknowledged_by(+name) ← pullVerificationStatus().
- api_contracts[0].edge_case_handling[3].handling = offline kini dideteksi flag `network: true` (bukan "tanpa status") ← apiClient.normalizeError + isNetworkError().
- api_contracts[0].edge_case_handling += pull gagal senyap (404/405 → pullUnsupported); record ditolak saat sync → hint 'Gagal sinkron' ← recordVerificationApi.ts, syncService.failure()/markSynced().
- api_contracts[0].unit_test_cases += 3 (pull mirror/offline, 404 stop, SyncFailureHint) ← syncService.sqljs.spec.ts #6, SyncFailureHint.spec.ts.
- implementation_notes += REVISI 2026-10-04 (GET endpoint, /api prefix PATCH, network flag, SyncFailureHint, toDateTimeLocalInputValue, filter-row minmax).
- test_scenarios += 2 (Status Verifikasi Ditarik dari Server; Petunjuk Gagal Sinkron). ⚠ diturunkan dari kode/e2e, bukan dari bdd_scenarios Phase 2.

## v8 — 2026-10-05

Sumber: audit-fix 2026-10-05 (commit f79b1fe), code is truth.
- api_contracts GET /api/records/{stationType}/verification error_codes[404].condition: body kini amplop standar { message, code: NOT_FOUND } ← RecordVerificationStatusController abort(404) (temuan audit 2026-10-05 #11).

## v9 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit e4f231e, d5da9cf), code is truth (mobile/src/components/filters/*, mobile/src/components/loading/*, mobile/src/views/DataPreview*View.vue, mobile/src/components/RecordVerificationActions.vue).
- api_contracts[0].edge_case_handling[2].handling ← Reset Filter di panel ListFilterBar
- api_contracts[0].business_logic (append langkah 16) ← ListFilterBar: pintasan, tombol ×, ringkasan jumlah, Reset tunggal
- test_scenarios[5] component/browser assert ← Reset Filter tunggal di panel + ringkasan '0 dari N data'
- test_scenarios (extend 2) ← skenario panel filter dan indikator memuat (⚠ skenario non-BDD, dari perilaku kode)
- implementation_notes (append) ← ListFilterBar + LoadingState + tombol verifikasi sibuk
