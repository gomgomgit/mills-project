# Derived Assumptions — module-dashboard.screen-162--laporan-solid-waste-disposal-web.4-implement

## v1 — 2026-10-18

- `status` = `complete` ← diturunkan dari hasil uji, bukan dinyatakan pengguna: backend 6016 lolos / 0 gagal (naik dari baseline 5926), unit service 59, komponen Livewire 31, browser 18. Coverage tidak terukur (tanpa xdebug/pcov) dan TIDAK dipakai menurunkan status — nol yang tidak terukur bukan nol yang nyata.
- Contoh "stasiun belum berlaporan" di `StationReportServiceTest` → KODE FIKTIF `zz-stasiun-fiktif` (sort_order 999) ← keputusan saya; berkas itu menyemai masternya sendiri sehingga kode fiktif tersedia, dan itu membuatnya pemindahan TERAKHIR di sana.
- Contoh yang sama di `e2e-web/tests/laporan-stasiun.spec.ts` dan `tests/Feature/Livewire/LaporanStasiunTest.php` → `process-quality-control` (180) ← keduanya membaca master station_types yang NYATA, sehingga kode fiktif tidak tersedia; 180 dipilih karena ia yang terakhir dibangun (screen-168), jadi tidak basi lagi sebelum itu.
- Berkas ketiga (`LaporanStasiunTest.php`) TIDAK terduga ← perkiraan awal saya menyebut dua tempat riak; yang ketiga ditemukan hanya oleh menjalankan suite penuh, dengan tujuh test memerah. Grep atas kode stasiun menemukan dua; suite menemukan tiga.
- `undated_load_count` dicetak HANYA ketika > 0 ← KOREKSI saya atas spec v1 yang menuntutnya selalu dicetak. Sebabnya ditemukan agen implementasi, bukan saya: `event_date` NOT NULL di migrasi, jadi keadaannya mustahil dan baris tetap untuk itu adalah kekhawatiran yang dikarang.
- Cabang `undated_load_count > 0` diuji lewat service-ganda in-memory ← keputusan agen; factory tidak dapat menyimpan baris ber-`event_date` null, jadi satu-satunya cara menguji cabangnya tanpa melonggarkan skema.
- Urutan rekap: `net_weight_total` DESC → `load_count` DESC → `value` ASC, null sebagai 0.0 untuk pengurutan saja ← disalin dari `WeighbridgeReportService::groupedBreakdownOf()`. Preseden kode yang memutuskan, bukan penalaran saya; tech-spec v1 saya yang salah.
- `net_weight_mt` dibaca apa adanya, tidak dihitung ulang dari bruto−tara ← diturunkan dari data nyata di DB e2e (net = 5 dengan bruto dan tara keduanya null), bukan dari pernyataan pengguna.
- Asersi ketiadaan blok `hourly` atas NAMA KUNCI payload lewat penelusur rekursif ← keputusan saya; `_note_no_hourly` memuat kata itu sehingga asersi berbasis prosa akan cocok dengan kalimat yang menyatakan ketiadaannya.
- Urutan asersi browser dibatasi oleh fixture: posisi baris null TIDAK diasersikan di browser ← keputusan saya setelah memeriksa isi fixture e2e (keempat kolom kategoris null → satu baris per rekap → asersi posisi vakum).
- Jumlah muatan di browser diasersikan ≥ 1, bukan persis ← `form-solid-waste-disposal.spec.ts` menulis ke DB e2e yang sama.
