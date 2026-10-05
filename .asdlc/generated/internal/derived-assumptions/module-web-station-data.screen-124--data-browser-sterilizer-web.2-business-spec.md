
## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (resources/views/livewire/data/data-browser-sterilizer.blade.php, app/Support/Display.php, app/Services/SterilizerRecordService.php::export(), app/Support/SheetWriter.php, app/Support/ExportValue.php).
- information_displayed[0] = status berlabel Draft/Dijeda/Tersimpan/Tersinkron ← blade memakai Display::status()
- business_rules += label status Indonesia ← Display::STATUS_LABELS
- business_rules += Ekspor Excel .xlsx sungguhan; status Indonesia, 8 jam siklus HH:MM tanpa detik, Checked by SPV Ya/Tidak (ExportValue::time/yesNo/status); sel kosong tetap kosong ← export() via SheetWriter + ExportValue

## v3 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit 658cedc, c321f32), code is truth (backend/resources/views/livewire/data/data-browser-*.blade.php, app/Livewire/Data/DataBrowser*.php, app/Livewire/Concerns/HasFilterReset.php, components/filter/*.blade.php, components/loading-assets.blade.php).
- information_displayed[1] ← bar filter bersama x-filter.bar (658cedc): rentang tanggal satu field, Business Unit pemilih khusus Admin / keterangan mill bagi akun terikat, Production Line, ringkasan 'N data', badge 'N filter aktif', Reset filter. Production Line ditulis karena field itu memang ada di bar (sudah ada sejak 2026-09-28 tetapi belum tercantum di entri ini)
- available_actions[2].description ← status sibuk tautan ekspor (c321f32: a[data-export-link] + x-busy-label 'Mengekspor…', SignalDownloadReady/ms_download, MAX_WAIT 60 dtk, klik kedua diabaikan)
- available_actions[+] 'Reset filter' ← HasFilterReset::resetFilters()/activeFilterCount() + tombol filter-reset di x-filter.bar (tampil hanya bila active > 0; non-Admin: business_unit_id dikecualikan dari hitungan, render() memaku ulang mill)
- edge_cases[0] ← opsi reset kini berada di bar filter (bukan di dalam empty state); empty state tetap pesan 'Tidak ada data'
