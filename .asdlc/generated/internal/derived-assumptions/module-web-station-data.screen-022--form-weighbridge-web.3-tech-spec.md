# Derived Assumptions Log — module-web-station-data.screen-022--form-weighbridge-web.3-tech-spec

## v2 — 2026-10-03

Sinkronisasi spec dengan kunci periode usecase-141 yang sudah diimplementasikan (49dc0c5, 0594c63); kode tidak diubah.
- Kondisi error_codes PERIOD_CLOSED untuk create juga mencakup 'tanggal kejadian kosong' (perilaku trait: tanggal kosong ditolak, bukan ditempatkan ke periode hari ini) — dirumuskan agen.
- Urutan pemeriksaan di business_logic (validasi field -> resolve station -> kunci periode pada create; findOrFail -> cek akses -> validasi -> kunci periode pada update) diambil dari kode service, bukan dari usecase-141; akibatnya VALIDATION_ERROR/NO_ACTIVE_*_STATION mendahului PERIOD_CLOSED.
- Nomor langkah business_logic baru = lanjutan penomoran yang ada; kutipan pesan di edge_case_handling disingkat ('...') dari string asli EnforcesPeriodLock::periodLockReason().
- Rumusan given/expect 3 unit_test_cases (termasuk contoh rentang 2026-10-01..2026-10-31) dipilih agen; kasus-kasus ini SPESIFIKASI — belum ada sebagai test khusus stasiun ini (test per-layar hanya membuka periode sebagai prasyarat), dicatat terus terang di implementation_notes.
- Sitasi test: EnforcesPeriodLockTest.php dan KelolaPeriodePelaporanTest.php (jalur Sterilizer) dipilih sebagai bukti cakupan; file test per-layar disebut hanya sebagai pemakai prasyarat openPeriodFor()/openPeriodForStation().
- Tanggal kejadian Weighbridge = bagian tanggal dari record_datetime (trait menormalkan ke Y-m-d); contoh batas inklusif memakai jam 08:00/17:00.

## v3 — 2026-10-03

Pembersihan teks spec usang / sinkronisasi dengan perbaikan 2026-10-03.
- Body POST /api/weighbridge-records kini mendokumentasikan production_line_id sebagai 'wajib secara praktis' (bukan required di validator): kosong/tidak dikenal menghasilkan 422 NO_ACTIVE_WEIGHBRIDGE_STATION, bukan VALIDATION_ERROR — dibaca dari resolveActiveStationForActor() yang mengembalikan null.
- Error code 403 FORBIDDEN (production line milik mill lain) dan edge case-nya ditambahkan karena terbukti di ScopesToActorMill::assertMillWritable(); tidak tercantum di versi spec sebelumnya.
- request_example test_scenarios diganti ke production_line_id; scenario_ref 'Business Unit Tanpa Station Weighbridge Aktif' sengaja TIDAK diganti agar tetap cocok dengan nama BDD Phase 2.
- business_rules_applied 'Business Unit tidak berubah' diperluas menjadi 'Business Unit / Production Line (station)'.
