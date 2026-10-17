# Derived Assumptions — module-dashboard.screen-161--laporan-process-water-mobile.2-business-spec

## v1 — 2026-10-17

Layar ini pasangan mobile screen-160, dan sebagian besar isinya BUKAN asumsi: ia disalin dari spec web yang sudah disetujui, dengan endpoint yang sama persis. Yang di bawah ini benar-benar saya turunkan.

### Satu benturan yang saya putuskan, dan saya memilih melawan screen-index

**EMPAT blok berjudul, bukan tiga.** `screen-index` (ditulis saat penentuan scope, sebelum spec web ada) menyebut *"tiga blok (Aliran, Softener & Dosing, Air Umpan Boiler)"*. Spec web yang ditulis kemudian memakai **empat**: Aliran Air, Softener, Dosis Kimia, Air Umpan Boiler.

Saya ikut yang empat. Alasannya bukan "yang lebih baru menang", melainkan prinsip yang sudah dinyatakan screen-159: *"pengelompokan yang sama dengan web, supaya gulirnya punya penanda tempat"*. Pembaca yang berpindah ponsel↔web tidak boleh perlu mempelajari ulang peta halaman. Sembilan kartu dalam empat blok juga proporsional — Engine Room mobile membawa 23 kartu dalam 6 blok.

Konsekuensi yang saya terima: deskripsi `screen-index` untuk layar ini kini menyimpang dari spec-nya. Saya **tidak** menyunting screen-index, karena menulis ulang sketsa scope agar cocok dengan hasilnya menghapus jejak bahwa keputusannya pernah diambil.

### Asumsi lain

- **Satuan pada kartu dosis diangkat menjadi aturan mengikat**, bukan sekadar disebut ← di web alum (kg/jam) dan polimer (g/jam) berdampingan sehingga satuannya terbaca sekaligus; di ponsel pembaca melihat satu kartu pada satu waktu. Angka 2,5 tanpa satuan pada kartu kedua akan dibaca dengan skala kartu pertama, dan kedua skala itu berbeda **seribu kali**.
- **pH SENGAJA tanpa satuan**, dan itu dinyatakan sebagai aturan ← supaya "keseragaman" tidak memancing seseorang mengarang satuan untuknya. pH memang besaran tanpa satuan.
- **"Layar ini TIDAK punya satu pun daftar sebaran" dinyatakan eksplisit** ← layar yang persis sebelumnya (Engine Room mobile) punya DUA, dan bentuknya paling mudah disalin dari tetangga terdekat. Ketiadaan yang tidak dinyatakan akan terbaca sebagai kelalaian lalu "dilengkapi".
- **Pasangan berlawanan slot-terisi diangkat jadi dua edge case terpisah** ← teks-bebas-saja TERHITUNG, identitas-saja TIDAK. Satu kalimat yang menyebut keduanya akan dibaca sebagai satu aturan; dua kasus memaksa keduanya diuji.
- **Rekap per inspector menumpuk jadi kartu**, bukan tabel bergulir ← konvensi `v-stacked-table` dari temuan QA LP-M-02/LP-M-03.
- **Pertanyaan terbuka screen-160 soal "apakah perlu menunggu penandaan shift" saya tandai SUDAH TIDAK BERLAKU** ← `shift_id` sudah mendarat (4ece80d) beserta tampilannya (563ae9e). Yang belum ada adalah pengelompokan laporan per shift, dan itu berlaku SAMA untuk web dan mobile — jadi keduanya akan ditinjau bersama, bukan satu per satu. Dicatat supaya tidak terbaca sebagai utang khusus layar ini.
