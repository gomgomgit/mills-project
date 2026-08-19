# Derived Assumptions Log — project.2-business-spec.usecases.usecase-011--form-grading

## v2 — 2026-08-18

- Full revision per mock reference + Weighbridge Form v4 structural mirror (Pause/Clear/breadcrumb/hamburger, sectioned layout) ← direct user instruction ("ikuti mock persis" for fields, "pembagian form nya seperti pada weighbridge" for layout)
- bdd-spec-writer-agent flagged 4 existing scenarios ("success as Station Operator", "success as Supervisor", "Checked By Khusus Supervisor", "Checked By Hanya Untuk Supervisor") as now factually contradictory — all reference the old vehicle_number/driver_name/block header shape and/or Checked By's role-gated presence, but Checked By is now absent from the UI for both actors entirely ← command-level override: these 4 were DELETED rather than kept, replaced by 1 unified "success" scenario + 1 "Checked By Tidak Ditampilkan" scenario. Same override judgment call made repeatedly this session for Weighbridge and screen-008.
- 8 further new scenarios added (Belum Ada Data Weighbridge Lokal, Edit Manual Setelah Auto-fill, Hapus Baris Grading Detail, Pause Progress, Clear Draft, Tap Breadcrumb, Buka Menu Hamburger, WB Card No Harus Dipilih Dari Dropdown) ← derived fresh from the updated main_flow/alternative_flows, none existed before
- WB Card No dropdown scope = ALL local weighbridge_record rows regardless of status, ordered by arrival_datetime descending ← no explicit user instruction on filter scope; agent default, flagged for confirmation at the pre-implementation checkpoint

## v3 — 2026-08-19

- New alternative_flow "Quality Parameter Tidak Bisa Duplikat" + business rule ← direct user instruction ("quality parameter yang sudah ditambah di grading itu tidak akan muncul lagi di baris lain")
- "Hapus Baris Grading Detail" alt-flow extended with "parameter kembali tersedia" step ← logical consequence of the exclusion rule, not separately stated by user but necessary for the feature to be usable (otherwise a parameter could become permanently unavailable after being added/removed once)
- 2 new bdd_scenarios added (Quality Parameter Tidak Bisa Duplikat, Quality Parameter Tersedia Lagi Setelah Baris Dihapus)
