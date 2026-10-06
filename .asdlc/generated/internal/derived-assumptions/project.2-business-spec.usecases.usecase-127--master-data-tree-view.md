# Derived Assumptions — project.2-business-spec.usecases.usecase-127--master-data-tree-view

## v1 — 2026-10-06

- ARTEFAK INI BELUM PERNAH ADA. Id `usecase-127--master-data-tree-view` sudah terdaftar di `usecase-index` sejak v25, tetapi berkas usecase-nya tidak pernah ditulis (`artifact__read` mengembalikan `null`). Jadi v1 ini penulisan pertamanya, bukan pembaruan — dan id-nya dipertahankan apa adanya karena sudah terpublikasi di index.
- `name` diganti dari "Jelajahi Hierarki Master Data (Tree View)" menjadi "Lihat Seluruh Hierarki Master Data dalam Satu Halaman" ← pilihan agen, mengikuti kenyataan bahwa bentuk pohon ditinggalkan. Slug di dalam id-nya masih berbunyi `master-data-tree-view` dan TIDAK diubah; penggantian nama layar di `screen-index` dibiarkan sebagai open question untuk user.
- Seluruh `main_flow`, `alternative_flows`, dan `business_rules` diturunkan agen dari spec layar; user tidak membahas alur maupun kasus tepi.
- `bdd_scenarios` diturunkan `bdd-spec-writer-agent`.
