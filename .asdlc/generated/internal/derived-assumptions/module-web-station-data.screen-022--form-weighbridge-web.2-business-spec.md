# Derived Assumptions Log — module-web-station-data.screen-022--form-weighbridge-web.2-business-spec

## v2 — 2026-10-03

Pembersihan teks spec usang / sinkronisasi dengan perbaikan 2026-10-03.
- Business Unit digambarkan hanya sebagai pemilih daftar Production Line (dibatasi ke mill pengguna); station di-resolve dari Production Line — sesuai FormWeighbridge (Livewire) dan WeighbridgeRecordService::create().
- Edge case 'Production Line milik mill lain ditolak' ditambahkan dalam kalimat edge_cases[2] (403 di backend).

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (form-weighbridge.blade.php, FormWeighbridge.php, WeighbridgeRecordService.php, AppTime.php).
- information_displayed[0] = line tanpa opsi tampil sebagai teks penjelas; mode edit menampilkan BU + Production Line read-only ← blade production-line-empty / production-line-readonly.
- information_displayed[7] = Net Weight teks (format angka Indonesia) ← blade Display::number.
- business_rules[3] = default jam WIB, tanggal tidak boleh melewati besok ← assertEventDateNotTooFarAhead, config app.timezone.
- business_rules[6] = Net Weight teks, bukan input nonaktif ← blade; WebFormNoDisabledFieldTest.
- business_rules += jam WIB untuk waktu ber-zona dari mobile ← AppTime::normalizeClientDateTime.
- edge_cases += tanggal > besok; id bukan UUID → 'Record tidak ditemukan.' ← GuardsRecordIdShape.
