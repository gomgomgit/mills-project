
## v2 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (ManagementReportService.php, Livewire/Dashboard/ManagementReport.php, management-report.blade.php, Api/ManagementReportController.php).
- description = breakdown harian per SATU production line wajib, WB masuk/keluar terpisah, ekspor .xlsx sungguhan ← resolveProductionLine + weighbridgeSummary receive/dispatch + SheetWriter
- information_displayed[0] = pemilih Production Line wajib (opsi mill pengguna, query string production_line_id) + filter tanggal ← #[Url] productionLineId, productionLineOptions()
- information_displayed[1] = kolom WB Masuk/Keluar (Jumlah Trip, Berat Bersih), Grading (Jumlah Record, Netto, Jumlah Tandan), Cages (Jumlah Record, Lori Ditumpahkan), format Indonesia ← blade thead + Display::number/date
- information_displayed[3] = tombol ekspor hanya bila line dipilih & rentang valid ← @if ($breakdown !== null)
- information_displayed += petunjuk pilih line, baris konteks (line · rentang), catatan arus tidak dijumlahkan ← blade select-production-line-hint / report-context / report-note
- available_actions += "Pilih Production Line"; available_actions[1] = ekspor CSV/xlsx sungguhan, kolom Production Line tiap baris ← exportUrl() + export()
- business_rules += line wajib & line mill lain diabaikan; filter production_line_id milik record; WB receive/dispatch tak pernah dijumlah (type non-dispatch/NULL = masuk); ekspor xlsx + kolom Production Line + baris Total ← service docblocks & code
- edge_cases[1] = rentang tidak valid: hanya pesan galat, tanpa tabel/'belum ada data'/Total/ekspor ← render() breakdown=null pada InvalidDateRangeException
- edge_cases += line belum dipilih → petunjuk; line mill lain di query string → reset 'belum memilih' ← render()

## v3 — 2026-10-05

Sumber: artifact-sync round 3 2026-10-05 (commit 658cedc, c321f32), code is truth (livewire/dashboard/management-report.blade.php, components/filter/*, loading-assets.blade.php).
- information_displayed[0] ← x-filter.bar (658cedc): Mill statis 'Mill mengikuti akun Anda', Production Line wajib (badge Wajib, opsi 'Pilih Production Line'), rentang tanggal satu field; tanpa Reset filter (komentar blade)
- information_displayed[3] ← tombol ekspor di slot actions bar filter; tautan a[data-export-link] + busy-label 'Mengekspor…' sampai cookie ms_download (c321f32)
