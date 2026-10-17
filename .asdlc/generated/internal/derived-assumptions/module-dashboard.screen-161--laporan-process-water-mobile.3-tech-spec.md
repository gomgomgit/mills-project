# Derived Assumptions — module-dashboard.screen-161--laporan-process-water-mobile.3-tech-spec

## v1 — 2026-10-17

### Yang terverifikasi, bukan diasumsikan

- **Nol pelebaran akses.** Saya periksa `routes/api.php` langsung: grup `process-water-reports/*` memakai `role:supervisor,mill_management,admin,operator`, dan `options-for-report` memakai `role:operator,supervisor,mill_management,admin`. Operator sudah diterima di keempatnya. Klaim "nol endpoint baru, nol perubahan backend" karena itu bukan harapan.
- **Fixture "stasiun belum berlaporan" di `ReportingPilihStasiunView.spec.ts` saat ini `kernel-dispatch`** — bukan `process-water`. Membangun layar ini tidak memindahkannya. Diperiksa, tidak diasumsikan.

### Yang saya turunkan

- **Blok kartu diturunkan dari field `group` respons, bukan dari daftar nama kolom di klien** ← sembilan kartu yang ditulis manual adalah sembilan tempat satuan bisa terlewat; satu loop hanya punya satu.
- **Satuan dicetak dari field `unit`, dan `unit` null tidak mencetak apa pun** ← bukan '-', bukan satuan karangan. Kedua kartu pH justru yang null.
- **Dua test TERPISAH untuk satuan kedua kartu Dosis Kimia, bukan satu atas bloknya** ← ini risiko paling tajam di layar: alum kg/jam dan polimer g/jam berskala beda seribu kali dan di ponsel tak pernah berdampingan. Satu asersi atas blok akan hijau walau satu kartu kehilangan satuannya.
- **Ketiadaan sebaran diasersi atas TESTID dan JUDUL, bukan atas prosa** ← layar ini memuat kalimat panjang yang MENYEBUT kata "sebaran" untuk menerangkan ketiadaannya. Pemindaian prosa akan memerah terhadap layar yang justru benar.
- **`_note_filled_rule` dan `_note_absences` dirender VERBATIM dari server** ← kalimat yang disusun dua kali di dua platform adalah kalimat yang akan berselisih.
- **Penandaan shift sengaja TIDAK dipakai layar ini** meski `shift_id` sudah mendarat ← payload `/summary` belum mengelompokkan menurut shift, dan menurunkannya sepihak di klien melanggar "ponsel tidak menghitung apa pun". Pengelompokan per shift menyentuh web DAN mobile sekaligus.

### Catatan tentang cara test spec ini ditulis

Daftar 58 unit case dan 25 skenario diturunkan `test-spec-writer-agent`, lalu ditranskripsikan agen terpisah lewat `artifact__patch`. Dua hal yang muncul dari situ dan layak dicatat:

1. **Sumbernya memakai bentuk datar** (`endpoint: "GET /api/..."`, `request_example` sebagai string) sementara template menuntut objek. Konversinya mekanis dan lossless, tetapi 10 dari 31 langkah membawa anotasi di luar kurung yang tidak punya tempat di objek — disimpan di kunci `_note` agar tidak hilang. Kunci itu **bukan dari sumber**; ia mengikuti konvensi `_note_*` yang sudah ada di payload proyek ini.
2. **Satu typo (`/summero`) sengaja dibiarkan apa adanya oleh transkriptor** alih-alih "diperbaiki diam-diam", lalu dilaporkan. Saya yang memperbaikinya. Itu perilaku yang benar: transkriptor yang memperbaiki isi sambil menyalin adalah transkriptor yang perubahannya tidak terlihat siapa pun.
