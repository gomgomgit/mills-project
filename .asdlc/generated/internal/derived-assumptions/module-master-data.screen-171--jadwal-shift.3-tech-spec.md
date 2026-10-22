
## v1 — 2026-10-22

- route = /master-data/shift-schedules (nama 'master-data.shift-schedules') ← mengikuti pola /master-data/{resource} dari Kelola Shift (screen-170); user hanya menyebut "layar terpisah"
- auth = role:admin,supervisor,mill_management; tujuh role setara Admin lolos lewat role efektif ← konsekuensi cast EffectiveUserRole + jawaban user soal pengatur
- lingkup mill Supervisor/Mill Management ditegakkan di service (bukan hanya UI) ← keputusan agen; user hanya bilang "mill sendiri" lewat aturan bisnis
- entitas baru `shift-assignment` (shift_id, user_id, work_date; UNIQUE ketiganya) ← diturunkan dari "per bulan, tiap hari beda orang, banyak orang per shift"; Production Line/Mill tidak disimpan di baris, diturunkan lewat shift
- pengeluaran dari sel = hapus FISIK, tanpa soft delete/jejak perubahan ← keputusan agen; histori yang dijaga hanya shift tertutup & user nonaktif
- kolom shift tertutup memakai tanggal deleted_at di zona waktu aplikasi sebagai closed_from ← keputusan agen
- 7 endpoint di bawah /api/shift-schedules (options x2, grid, candidates, PUT cell, POST apply, POST copy-previous) ← bentuk API diturunkan agen
- PUT cell memakai semantik "ganti seluruh isi sel" (bukan tambah/hapus per orang) dengan loaded_user_ids untuk deteksi perubahan orang lain ← keputusan agen; konflik = last-write-wins + changed_by_others
- validasi calon semua-atau-tidak-sama-sekali (NOT_A_CANDIDATE) ← keputusan agen
- apply/copy-previous punya dry_run; menimpa butuh confirm_overwrite (409 CONFIRMATION_REQUIRED) ← keputusan agen
- calon peserta diukur dari role TERPILIH (getRawOriginal/assignedRole), bukan role efektif ← konsekuensi role setara Admin; Engineer dkk. bukan calon
- test scenarios derived: 29 unit, 27 API, 27 component, 27 browser ← diturunkan test-spec-writer-agent dari BDD Phase 2

## v2 — 2026-10-22

- tidak ada perubahan API/entitas; kalender dan tabel dirender dari respons GET /api/shift-schedules yang sama ← keputusan agen
- viewMode/openDate/openShiftId adalah state Livewire; viewMode diingat via localStorage (try/catch) bukan di server ← keputusan agen
- seluruh sel/kotak adalah satu target klik (elemen aksesibel), bukan tombol 'Atur' ← penerjemahan permintaan user
- sel shift tertutup membuka kartu mode-histori ← keputusan agen
- menyimpan di kartu memanggil PUT cell per shift (bukan sekaligus) ← keputusan agen; menjaga shift lain tak tertimpa
- tes lama (Livewire 63, Playwright 32) sebagian akan gagal dan harus diperbarui mengikuti perilaku baru ← konsekuensi perubahan
- 10 skenario uji baru ditambahkan (api/component/browser) ← diturunkan agen dari 10 skenario BDD baru
