# Derived Assumptions Log — module-mobile-station-ops.screen-122--form-sterilizer.3-tech-spec

## v2 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Tidak menambah unit_test_cases: form tidak punya logika periode untuk diuji (simpan lokal tanpa cek periode).
- TEMUAN yang menyimpang dari brief: form ini memanggil writeThroughSync.syncAfterSave() setelah simpan lokal; bila Mills Setting immediate_sync_enabled aktif, record langsung di-POST ke server dan penolakan 422 PERIOD_CLOSED ditelan diam-diam. Didokumentasikan sebagai jalur tulis server, bukan sebagai 'tidak ada panggilan server'.
- Tidak menambah endpoint ke api_contracts (endpoints tetap kosong, sesuai instruksi) meskipun ada jalur POST write-through.
- Dikonfirmasi dari kode: form hanya membuka draft (draft_ongoing/draft_paused) — Data Preview mengarahkan record saved/synced ke layar preview, bukan form — sehingga tidak ada jalur edit/PATCH record tersinkron.
- Kalimat edge case/aturan/catatan dirumuskan sendiri; 'pemulihan = Admin membuka kembali baris stasiun di screen-142 lalu sinkron ulang' mengikuti brief.

## v3 — 2026-10-03

Pembersihan teks spec usang / sinkronisasi dengan perbaikan 2026-10-03.
- Edge case usecase-141 ditulis ulang: penolakan 4xx write-through kini tampil lewat ConfirmDialog 'Tersimpan, tetapi ditolak server' lalu ke Monitor; offline/5xx tetap diam.
- implementation_notes[0] ('tidak ada panggilan API') diganti: SQLite lokal + sinkron manual Station List (STATION_PUSH_CONFIGS) + write-through bila immediate_sync_enabled aktif; Pause/Clear/Back tidak memanggil server.
- Catatan REVISI kunci periode: frasa 'kegagalannya diam' dipersempit menjadi offline/5xx saja; frasa 'kode tidak diubah' dihapus karena kode form berubah 2026-10-03.

## v4 — 2026-10-04

Penyeragaman checkbox verifikasi (keputusan user 2026-10-04).
- business_logic #8/#9: Checked By hanya dirender untuk supervisor (v-if="isSupervisor"), Acknowledged By hanya untuk mill_management (v-if="isMillManagement"); peran lain tidak melihat checkbox sama sekali (sebelumnya: tampil tapi disabled).
- edge_case_handling (Checked By / Acknowledged By oleh peran lain): "disabled/read-only" → "tidak dirender (disembunyikan, bukan disabled)".
- unit_test_cases: kasus "disables Checked By/Acknowledged By" diganti "not rendered"; kasus Acknowledged By kini memeriksa render untuk mill_management (Checked By tidak dirender).
- test_scenarios: assert operator "remain empty/disabled" → checkbox tidak dirender; skenario "Checked By Khusus Supervisor" → checkbox tidak ditampilkan (count 0).
- Selaras konvensi 2026-09-14 form stasiun lain; kode dan test sudah diubah lebih dulu.
- actor_permissions: ditambah entri actor-mill-management (can_access true; akses form seperti Operator/Supervisor + checkbox Acknowledged By, tanpa Checked By) — sinkronisasi dokumentasi, bukan perubahan akses: route hanya mensyaratkan login (router.beforeEach), tanpa pembatasan role.
