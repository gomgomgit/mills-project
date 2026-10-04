# Derived Assumptions Log — project.2-business-spec.usecases.usecase-128--kelola-periode-pelaporan

## v1 — 2026-09-22

- usecase-128 = seluruh isi (description, preconditions, 8 langkah main_flow, 6 alternative_flows, postconditions, 7 business_rules) ← drafted from the screen spec, not individually confirmed (autopilot). Only the ID and name were fixed earlier in bus-1-scope
- alternative_flows += "Periode sudah dihapus pengguna lain" ← agent-derived concurrency case, copied from the Kelola Production Line pattern; never discussed for periods
- bdd_scenarios = 9 skenario ← derived by bdd-spec-writer-agent from the flows and rules above; includes one the agent added beyond the flows: "pengguna non-Admin mencoba mengakses"

## v2 — 2026-09-27

- Cakupan usecase dipersempit: aksi tutup/buka/buka kembali dikeluarkan, navigasi ke layar detail dimasukkan ← konsekuensi langsung repoint usecase-index v16
- Seluruh jejak `station_type` (cakupan "opsional, kosong = semua jenis stasiun", keunikan nama per kombinasi mill + jenis stasiun, overlap terhadap periode wildcard) DIHAPUS dan diganti aturan per mill ← diverifikasi ke `PeriodService::validate()` dan `findOverlapping()`; artefak v1 masih menggambarkan kolom yang sudah dihapus dari database
- 4 bdd_scenario BARU: mill tanpa stasiun aktif, simpan ulang menambahkan baris stasiun, makna filter Status Stasiun, dan membuka detail dari daftar ← turunan agen; 9 skenario lama dipertahankan seluruhnya dengan rumusan yang disesuaikan
- alternative_flow "Memindahkan periode ke mill lain" ← turunan agen dari docblock `backfillStationRows()`

## v5 — 2026-10-05

Sumber: audit-fix 2026-10-04, code is truth (PeriodService::delete() guardAgainstFramedRecords(), PeriodHasRecordsException.php, AuditFix20261004Test [period-delete]).
- main_flow[10], postconditions[4] = hapus hanya bila tak ada stasiun tertutup DAN tak ada data stasiun di rentang (kontradiksi: dulu cukup "tanpa stasiun tertutup") ← guardAgainstFramedRecords().
- alternative_flows (+1), business_rules (+1), bdd_scenarios (+1) = penolakan hapus periode berisi data, pesan inline ← PeriodHasRecordsException via catch PeriodClosedImmutableException. ⚠ "tombol Hapus tidak dinonaktifkan" diinferensikan (is_immutable hanya dari baris closed).
