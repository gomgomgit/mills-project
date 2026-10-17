# Derived Assumptions — module-dashboard.screen-161--laporan-process-water-mobile.4-implement

## v1 — 2026-10-18

- `status` = `complete` ← diturunkan dari hasil uji, bukan dinyatakan pengguna: unit 44, komponen 111, browser 36, nol gagal. Coverage TIDAK diturunkan menjadi alasan status `partial` — nol yang tidak terukur bukan nol yang nyata.
- Body `/summary` diteruskan APA ADANYA oleh repo, dan view MEMILIH antar dua kosakata kunci ← keputusan agen; tech-spec menamai beberapa kunci berbeda dari endpoint nyata, dan pengguna tidak diminta memutuskan mana yang benar. Endpoint sengaja TIDAK diubah karena ia sudah dipakai screen-160 yang hijau.
- Satuan metrik dari tabel statis `METRIC_META` yang disalin verbatim dari blade web, dengan nilai server menang bila ada ← keputusan agen; payload nyata tidak punya field `unit` sama sekali, sehingga "satuan dari respons" tidak dapat dipenuhi sendirian.
- Nilai `digits` per metrik (1,1,2,1,1,0,1,2,0) disalin dari web ← tidak disebut di artefak mana pun; diambil supaya polimer dan TDS tampil tanpa desimal persis seperti di web.
- Alias kunci metrik menunjuk objek yang SAMA ← keputusan agen, supaya dua ejaan tidak dapat menyimpang.
- Luring MENUTUP angkanya (nol kartu metrik), bukan sekadar menambah spanduk ← penyimpangan sadar dari preseden Engine Room; pengguna tidak membahas perilaku luring layar ini.
- `has_data` tidak dipakai sebagai gerbang render ← kebalikan dari Engine Room; diturunkan dari ketiadaannya di skema tech-spec.
- Ketiga rekap terlipat secara bawaan (CollapsibleSection + `v-show`) ← keputusan tampilan agen untuk layar sempit; tidak diminta.
- Contoh "belum ada laporan mobile" di `ReportingPilihStasiunView.spec.ts` dipindahkan ke `solid-waste-disposal` ← keputusan agen menurut konvensi berkas itu sendiri (sort_order tertinggi yang belum dipakai). CATATAN: pilihan ini berumur pendek — screen-163 akan memerahkannya lagi, dan sesudahnya kolam kandidat habis sehingga kode FIKTIF yang harus disuntikkan.
- Stub CSV uji browser disalin verbatim dari `ProcessWaterReportService::EXPORT_HEADER` ← KOREKSI saya atas stub yang dikarang agen (22 kolom vs 21 yang nyata). Yang menentukan bukan konsistensi internal spec melainkan kesetiaan pada ekspor sungguhan, sebab layar mengunduh dari endpoint asli.
- Uji luring memalsukan `navigator.onLine` lewat `addInitScript`, bukan `context.setOffline()` ← KOREKSI saya; `setOffline` + `reload()` memutus pengambilan dokumen dari Vite sehingga aplikasinya tidak pernah dijalankan dan yang teruji bukan layar ini.
- Tidak ada integration test ← diturunkan dari nol kontrak backend baru, bukan dari keputusan melewatkannya.
