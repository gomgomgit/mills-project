# Derived Assumptions Log — module-web-station-data.screen-016--data-browser-weighbridge-web.2-business-spec

## v1 — 2026-08-16

- business_rules = ["Data mencakup hasil sync mobile & input web", "Ekspor mengikuti filter aktif", "Daftar termuat <=2 detik untuk dataset normal"] ← proposed by agent in draft, accepted without correction
- edge_cases = ["Tidak ada data sesuai filter", "Rentang tanggal tidak valid", "Ekspor gagal/dataset terlalu besar"] ← proposed by agent in draft, accepted without correction

## v3 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (resources/views/livewire/data/data-browser-weighbridge.blade.php, app/Support/Display.php, app/Services/WeighbridgeRecordService.php::export(), app/Support/SheetWriter.php).
- information_displayed[0] = net weight berformat id-ID, status berlabel Draft/Dijeda/Tersimpan/Tersinkron ← blade kini memakai Display::number($v, 2) dan Display::status()
- business_rules += label status Indonesia + angka id-ID di tabel ← Display::STATUS_LABELS dipakai blade
- business_rules += Ekspor Excel = .xlsx sungguhan; judul kolom = label Detail (+ Checked By/Acknowledged By), status Indonesia, waktu tanpa detik, sel kosong tetap kosong ← WeighbridgeRecordService::export() via SheetWriter; "sel kosong tetap kosong/angka tanpa pemisah ribuan" disimpulkan dari nilai mentah yang dikirim ke SheetWriter (⚠ inferensi, bukan konstanta eksplisit)

## v4 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit 658cedc, c321f32), code is truth (backend/resources/views/livewire/data/data-browser-*.blade.php, app/Livewire/Data/DataBrowser*.php, app/Livewire/Concerns/HasFilterReset.php, components/filter/*.blade.php, components/loading-assets.blade.php).
- information_displayed[1] ← bar filter bersama x-filter.bar (658cedc): rentang tanggal satu field, Business Unit pemilih khusus Admin / keterangan mill bagi akun terikat, Production Line, ringkasan 'N data', badge 'N filter aktif', Reset filter. Production Line ditulis karena field itu memang ada di bar (sudah ada sejak 2026-09-28 tetapi belum tercantum di entri ini)
- available_actions[2].description ← status sibuk tautan ekspor (c321f32: a[data-export-link] + x-busy-label 'Mengekspor…', SignalDownloadReady/ms_download, MAX_WAIT 60 dtk, klik kedua diabaikan)
- available_actions[+] 'Reset filter' ← HasFilterReset::resetFilters()/activeFilterCount() + tombol filter-reset di x-filter.bar (tampil hanya bila active > 0; non-Admin: business_unit_id dikecualikan dari hitungan, render() memaku ulang mill)
- edge_cases[0] ← opsi reset kini berada di bar filter (bukan di dalam empty state); empty state tetap pesan 'Tidak ada data'
