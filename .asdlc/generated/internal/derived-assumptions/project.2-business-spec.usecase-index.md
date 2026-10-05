# Derived Assumptions Log — project.2-business-spec.usecase-index

## v1 — 2026-08-14

- usecases = usecase overview text for all 32 usecases proposed by agent per module, accepted without change ← usecase descriptions are agent's synthesis from PRD/UIUX context, not verbatim user statements

## v13 — 2026-09-22

- usecases.140--tutup-buka-periode-pelaporan = dipisah menjadi usecase tersendiri dari usecase-128 ← agent judged closing/reopening a period to be a distinct business action (Admin-only, irreversible consequences) rather than part of ordinary CRUD; user did not ask for the split
- usecases.141--kunci-input-periode-tertutup = usecase lintas-layar yang menempel pada 15 layar Form & Data Preview kelima stasiun ← user stated the rule ("periode ditutup = tidak boleh masuk/diubah/diverifikasi"); expressing it as a usecase spanning existing screens, and the choice of which 15 screens it touches, is the agent's
- usecases.129–139 = 1 usecase per layar laporan, mengikuti pola 1:1 yang berlaku di seluruh index ← agent followed the existing convention; user did not specify usecase granularity

## v15 — 2026-09-27

- `usecases[usecase-140--tutup-buka-periode-pelaporan].screen_ids` → `screen-142` ← user menyatakan aksi per stasiun pindah ke halaman detail; pemindahan pemilik usecase-nya adalah konsekuensi mekanis yang tidak ia sebut. Tanpa ini usecase akan menunjuk layar yang tidak lagi memuat aksinya.
- `usecases[usecase-144--buka-periode-pelaporan].screen_ids` → `screen-142` ← alasan sama.
- `usecases[+] = usecase-145--lihat-detail-periode-pelaporan` ← tidak diminta. Membuka periode untuk membaca daftar stasiun beserta statusnya adalah kemampuan baru yang tidak tercakup usecase mana pun: usecase-128 adalah CRUD periode, 140 dan 144 adalah aksi status. Tanpa usecase sendiri, layar 142 akan punya alur baca yang tidak pernah ter-spec.
- `usecase-128--kelola-periode-pelaporan` SENGAJA tetap di screen-128 ← CRUD periode (tambah/ubah/hapus) tidak pindah; user menetapkan Edit/Hapus tetap ada di daftar maupun di detail, dan pemilik usecase-nya tetap layar daftar.

## v20 — 2026-10-05

- usecases[usecase-148--lihat-data-saya] = satu usecase untuk daftar + detail read-only ← pengelompokan oleh agen

## v21 — 2026-10-05
- usecases[usecase-148--lihat-data-saya].name = "Lihat Data Saya (Web, Operator & Supervisor)" ← mengikuti keputusan USER (Supervisor ikut)
