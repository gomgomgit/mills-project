# Derived Assumptions Log — module-mobile-station-ops.screen-013--data-preview-weighbridge.4-implement

## v1 — 2026-08-18

- Reuse getDraftById() yang sudah ada (screen-010) daripada menambah getRecordById() duplikat — fungsinya sudah persis "SELECT satu baris by id, null jika tidak ada"
- Route path pakai /stations/weighbridge/preview/:id? (optional trailing param) untuk mendamaikan path literal tech-spec (tanpa id) dengan business_logic yang butuh load-by-id
- StatusBadge.vue dipakai ulang via prop status+label override untuk merepresentasikan saved/synced/paused (StatusBadge aslinya hanya model none/ongoing/paused dari screen-005)

## Temuan gap lintas-screen (jujur diungkap, bukan disembunyikan)
MonitorWeighbridgeView.vue (screen-007) tombol "Buka Data Preview" navigasi ke data-preview-weighbridge TANPA parameter id sama sekali — sehingga entry point itu akan selalu memicu state "record not found" sampai screen-007 direvisi untuk mengirim params: { id: currentDraft.id }. Route sendiri sudah benar terdaftar dan tidak error, hanya belum dipakai dengan id oleh pemanggilnya.

## Catatan proses
Read/Edit tool tidak tersedia untuk sub-agent (3 screen berturut-turut). File router/index.ts dan DataPreviewWeighbridgeView.vue diverifikasi langsung dibaca penuh oleh command — isi benar, konsisten, tidak ada masalah.

## v3 — 2026-08-18

- getAllRecords(userId) dibuat terpisah dari getDrafts(userId) (screen-007) ← getDrafts() hanya draft_ongoing/draft_paused, getAllRecords() butuh semua status termasuk saved/synced untuk mode list
- Date filter dicocokkan terhadap arrival_datetime (bukan dispatch_datetime) ← arrival = "tanggal truk", dispatch = jam berangkat, bukan kandidat filter tanggal yang masuk akal
- Filter client-side (bukan re-query per keystroke) ← konsisten dengan keputusan yang sama di tech spec, volume data kecil
- Reset Filter button DITAMBAHKAN MANUAL oleh command (bukan oleh screen-impl-agent) setelah verifikasi independen menemukan gap terhadap business spec ("opsi reset filter" tidak diimplementasikan) ← perbaikan kecil, disclosed, diverifikasi ulang via full regresi setelah ditambahkan
- known_issue lama (Load Data tanpa id → selalu not-found) dinyatakan RESOLVED, bukan dihapus diam-diam — sekarang perilaku itu benar by design (list mode)

## v4 — 2026-08-18

- todayLocalDateString() dibangun manual (getFullYear/getMonth/getDate, bukan toISOString) ← toISOString() UTC-based bisa off-by-one-day tergantung timezone device
- onResetFilter() sengaja TIDAK diubah untuk reset ke hari ini (tetap ke kosong/tampilkan semua) ← di luar scope instruksi user ("filter tanggal auto untuk hari ini" hanya soal default awal, bukan perilaku tombol reset), dicatat sebagai known_issue/opsi follow-up bukan bug tersembunyi

## v5 — 2026-08-19

- detailTypeLabel/detailDatetimeLabel fallback ke label 'Receive'/'Tanggal & Waktu Arrival' saat weighbridge_type bernilai null (record legacy/belum diset) ← tidak dinyatakan eksplisit di tech spec, defensive default konsisten dengan weighbridge_type default 'receive' di Form Weighbridge (screen-010)
- Field Tipe Weighbridge & Tanggal/Tujuan Muatan ditambahkan sebagai FormField disabled biasa (bukan komponen khusus) ← konsisten dengan pola detail-mode read-only screen ini yang sudah ada sejak v1, tidak ada instruksi untuk styling berbeda
- Temuan tak terduga (out of scope, diungkap bukan disembunyikan): full-suite Playwright run menemukan tests/e2e/form-grading.spec.ts (screen-011, 2 test) JUGA gagal akibat shared schema rename yang sama (men-seed weighbridge_record.arrival_datetime untuk kebutuhan FK) — sebelumnya hanya form-weighbridge.spec.ts (screen-010) yang diketahui coordinator. Tidak diperbaiki (di luar scope directive "screen-013 ONLY"), dicatat sebagai known_issue major di 4-implement.
