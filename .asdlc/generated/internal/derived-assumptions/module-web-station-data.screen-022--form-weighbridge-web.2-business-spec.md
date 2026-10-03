# Derived Assumptions Log — module-web-station-data.screen-022--form-weighbridge-web.2-business-spec

## v2 — 2026-10-03

Pembersihan teks spec usang / sinkronisasi dengan perbaikan 2026-10-03.
- Business Unit digambarkan hanya sebagai pemilih daftar Production Line (dibatasi ke mill pengguna); station di-resolve dari Production Line — sesuai FormWeighbridge (Livewire) dan WeighbridgeRecordService::create().
- Edge case 'Production Line milik mill lain ditolak' ditambahkan dalam kalimat edge_cases[2] (403 di backend).
