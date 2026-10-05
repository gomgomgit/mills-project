
## v1 — 2026-10-05
- usecase-148--lihat-data-saya = satu usecase untuk daftar + filter + detail read-only di layar yang sama (8 langkah main flow, 9 alternative flow, 12 BDD scenario) ← drafted from screen spec, not individually confirmed
- usecase-148.alternative_flows["Peran lain membuka Data Saya"] = Supervisor/Mill Management/Admin mendapat 403 ← agen; user hanya menyatakan layar khusus Operator
- usecase-148.bdd_scenarios["Upaya mengubah, memverifikasi, atau mengekspor data ditolak"] = permintaan paksa "ditolak" tanpa kode error spesifik ← bdd-spec-writer-agent; kodenya ditetapkan di Phase 3 (layar tanpa endpoint tulis → 404/405 bawaan rute, Livewire tanpa aksi tulis)
- usecase-148.preconditions["record sudah tersimpan di server"] = record Operator hanya terlihat setelah sync manual dari mobile ← agen, dari PRD (sync manual, tidak ada auto-sync)

## v2 — 2026-10-05
- usecase-148.actors = actor-station-operator + actor-supervisor ← USER (Checkpoint 3b)
- usecase-148.alternative_flows["Peran lain membuka Data Saya"] = kini hanya Mill Management & Admin (403) ← USER
- usecase-148.bdd_scenarios[+] = "Supervisor berhasil" dan "Supervisor tidak melihat record Operator-nya" ← diminta USER; given/when/then dirumuskan agen
- usecase-148.alternative_flows[+"Supervisor hanya melihat record buatannya sendiri"] = rumusan agen dari keputusan USER
- usecase-148.preconditions[record] = "diinput dari web, atau diinput lalu disinkronkan dari mobile" ← turunan agen: Supervisor juga menginput lewat Form web
