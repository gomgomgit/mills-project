## v1 — 2026-10-24
- testid prefix = `cd-` (turunan `kd-` milik screen-165) ← spec tak menetapkan penamaan testid
- waktu putar ditampilkan dalam menit saja ← 'jam + menit' butuh pembagian di klien, dilarang spec
- pil satuan 'menit' hanya muncul bila angkanya ada ← spec tak mengatur
- `<progress max>` = isi kelompok jam tersibuk lewat busiest_hour; bila null max 1 dan semua 0 ← spec hanya meminta bilah sepanjang nilai
- kepala kartu sebaran jam di luar bagian terlipat; kartu terbuka sebagai bawaan ← spec tak menyebut keadaan awal
- 'Penimbangan Belum Selesai' dipindah sesudah kartu waktu putar; 'Empat Penyebut' sesudah sebaran jam ← tiga kartu utama harus berurutan
- cpo-dispatch pada st-4 spec picker → 'zz-stasiun-fiktif-tiga' ← 'zz-stasiun-fiktif' sudah dipakai st-2, testid bentrok

## v2 — 2026-10-24
- kasus unit diturunkan sendiri dari edge_case_handling + implementation_notes ← tech spec tak punya unit_test_cases/test_scenarios
- audit sumber melarang Date, Intl.DateTimeFormat, reduce, sort, Math.min/max tetapi mengizinkan Intl.NumberFormat ← pemformatan angka bukan aritmetika jam
- prosa DOBI diasersi lewat salinan kartu tanpa elemen prosa ← prosa server memuat kata "%"
- skenario browser memakai profil "tanpa jam" ← fixture e2e punya time_in/time_out NULL
