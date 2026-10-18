# Derived Assumptions — module-dashboard.screen-163--laporan-solid-waste-disposal-mobile.4-implement

## v1 — 2026-10-18

- `status` = `complete` ← diturunkan dari hasil uji: unit 48, komponen 89, browser 39, nol gagal; suite mobile 3602 lolos / 141 berkas. Coverage tidak terukur dan TIDAK dipakai menurunkan status.
- Satuan dipisah ke elemen sendiri yang tidak dirender saat nilai null ← keputusan agen, supaya '—' tidak pernah berdiri di samping satuan yatim.
- Pecahan Intl id-ID min 1 / max 2 desimal, bukan desimal tetap seperti blade web ← penyimpangan SADAR agen untuk lebar 390px; nilainya sama, tanpa nol berekor yang tak membawa informasi.
- 500 dan status tak tertangani → `network-error` + `retry-button`, bukan `error-message` ← preseden screen-161 sengaja tidak disalin, karena spesifikasi uji layar ini menuntut tombol Coba Lagi.
- Markup rekap ditulis sekali lalu di-loop atas deskriptor `RECAPS` ← keputusan agen; bentuk itu yang mencegah satu kolom hilang dari salah satu dari empat salinan, dengan biaya membuat asersi jumlah-literal milik screen-161 tidak dapat disalin.
- Dua kasus uji tech-spec diganti dengan kontrak yang sebenarnya ← `REPORT_ROUTES` dan peta ikon di `ReportingPilihStasiunView.vue` module-local (tidak diekspor), dan peta ikonnya bernama `TYPE_ICONS` bukan `STATION_ICONS`. Impor yang ditulis spec tidak akan compile. Invariannya diasersikan lewat DOM; ekspor TIDAK ditambahkan karena itu melampaui "tepat satu entri".
- `daily_total._note` dirender meskipun HILANG dari success_schema tech-spec ← ia ada di payload nyata (NOTE_DAILY_TOTAL di service). Tech-spec yang kurang, bukan payload-nya.
- Riak diperbaiki di LIMA tempat, dua di antaranya di luar daftar berkas ← `LaporanProcessWaterView.spec.ts` unit case 56 membaca spec tetangga dari disk dan pasti merah. Ditemukan hanya dengan menjalankan suite penuh, sama seperti pada screen-162.
- `zz-stasiun-fiktif` memakai cast `as StationType` ← union `StationType` tertutup; cast-nya memaksa penulis fixture baru sadar sedang memakai kode di luar master.

## Kesalahan saya, dan cara ia ketahuan

- `backend/app/Support/Display::OPTION_LABELS` punya CERMIN di `mobile/src/utils/optionLabel.ts`, dan `mobile/tests/optionLabel.spec.ts` MEMBACA berkas PHP dari disk lalu menuntut kedua peta IDENTIK. Saya menambahkan dua entri di sisi PHP saja pada commit 88d5904 → seluruh suite mobile memerah. Diperbaiki terpisah; suite kembali 3602/0.
- Agen pelaksana layar ini membuktikannya pre-existing di HEAD tanpa `git stash` dan SENGAJA tidak membelanjakan ronde auto-fix untuknya karena berisiko bentrok dengan pekerjaan paralel. Keputusan yang tepat, dan dilaporkan alih-alih didiamkan.
- Konsekuensi untuk spec: catatan tech-spec screen-165 saya yang menyuruh membuat "satu tabel pemetaan" di dalam view juga SALAH — mobile sudah punya helper bersama, jadi view harus MENDELEGASIKAN. Dua entri spesifikasi uji screen-165 yang dibangun di atas catatan salah itu ikut dikoreksi.
