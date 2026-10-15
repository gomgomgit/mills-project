# Derived Assumptions — module-master-data.screen-170--kelola-shift.2-business-spec

## v1 — 2026-10-15

Layar pertama dari fitur Shift per Production Line. Keputusan user dinyatakan eksplisit dalam diskusi panjang sebelum scope ditulis: shift per production line (bukan per mill) · celah diizinkan tetapi ditandai · jendela sinkronisasi per mill sehingga TIDAK ada di layar ini · tata letak timeline-plus-tabel · `shift_id` pada record bermakna "shift saat data diinput di perangkat" (putaran berikutnya).

Yang di bawah ini turunan agent:

- **Admin only** ← user tidak menyebut perannya. Diikuti dari pola seluruh master di `module-master-data`; `screen-128--kelola-periode-pelaporan` juga Admin-only.
- **Nama shift unik PER LINE, dan dua line boleh punya nama sama** ← turunan agent, meniru aturan "nama periode unik dalam satu Business Unit" di screen-128. Konsekuensinya dinyatakan sebagai hal normal: 'Pagi' di Line 1 dan 'Pagi' di Line 2 adalah dua shift berbeda.
- **Tumpang tindih ditolak dengan batas INKLUSIF** ← turunan agent; pola dan batasnya dibawa dari aturan tumpang tindih rentang tanggal screen-128 yang sudah terbukti. Akibat yang disengaja: shift yang berakhir tepat pada jam mulai shift lain TIDAK bertindih — itu pergantian shift normal.
- **Jam mulai = jam selesai DITOLAK** ← turunan agent. Shift berdurasi nol tidak dapat menampung record; shift 24 jam penuh harus ditulis sebagai satu shift yang menutup seluruh hari, bukan sebagai jam berimpit.
- **Pesan penolakan menyebut shift mana dan rentang jam berapa** ← turunan agent; pesan yang hanya berbunyi 'jam bertindih' memaksa Admin mencari sendiri penyebabnya di antara belasan baris.
- **Shift yang sedang diubah tidak dihitung bertindih dengan dirinya sendiri**, dan **memindahkan shift ke line lain memeriksa ulang terhadap line TUJUAN** ← keduanya turunan agent; keduanya kelas kesalahan yang mudah terlewat pada validasi tumpang tindih.
- **Union yang tidak dimulai 07:00 DITANDAI, tidak ditolak** ← dasarnya terverifikasi (kisi `(7+i)%24` berjalan 07:00..06:00, jadi hari produksi dimulai 07:00), tetapi "tandai jangan tolak" pilihan agent: menolaknya memaksa pabrik yang shift-nya memang tidak selaras mengarang jadwal yang tidak mereka jalankan.
- **Timeline dimulai 07:00, bukan 00:00** ← turunan agent dari fakta yang sama. Timeline yang dimulai tengah malam akan membelah shift malam menjadi dua potongan di kedua ujung padahal ia satu shift utuh — dan itu justru bentuk shift yang paling umum.
- **Timeline mengikuti filter Production Line** ← turunan agent. Dasarnya terverifikasi: docblock `StationReportService` menyatakan satu mill bisa punya belasan line. Dengan 'semua line' dipilih, halaman memang menjadi panjang — diterima karena itu tindakan audit yang disengaja.
- **Urutan tampil dari jam mulai, bukan kolom urutan manual maupun abjad** ← turunan agent; satu sumber kebenaran yang tidak bisa berselisih dengan jam shift-nya sendiri.
- **Urutan baris tabel dan urutan blok timeline WAJIB sama** ← turunan agent. Dua urutan berbeda untuk data yang sama adalah cara tercepat membuat pembaca berhenti mempercayai keduanya.
- **Pratinjau timeline hidup di dalam form** ← turunan agent, dan ini alasan utama memilih tata letak bertimeline: tumpang tindih dan celah adalah cacat BERBENTUK, jadi pratinjau membuatnya terlihat sebelum Simpan ditekan. Komponen yang sama dipakai dua kali.
- **Pratinjau menahan diri saat jam masih separuh diketik** ← turunan agent; menggambar blok dari nilai belum valid lebih menyesatkan daripada tidak menggambar.
- **Line tanpa shift = 'belum diatur', BUKAN 'celah 24 jam'** ← turunan agent. Keduanya berbeda artinya: yang pertama belum dikerjakan, yang kedua keputusan yang mungkin salah.
- **Status cakupan dinyatakan dengan KATA, bukan hanya warna** ← turunan agent; warna saja tidak terbaca oleh semua orang dan tidak terbaca sama sekali saat dicetak.
- **Jumlah jam yang tidak tercakup disebut angkanya** ← turunan agent; 'ada celah' tanpa angka tidak dapat ditindaklanjuti.
- **Layar menyebutkan DI MANA jendela sinkronisasi diatur** ← turunan agent. User hanya memutuskan setelan itu per mill; menyebut tempatnya mencegah Admin yang mencarinya di sini menyimpulkan fiturnya belum ada.
- **Seluruh teks minimal 14px, termasuk label jam pada sumbu timeline; bila ruang kurang label DIJARANGKAN bukan diperkecil** ← turunan agent, mengikuti `uiux_spec.accessibility.text_content.min_font_size`. Dicatat eksplisit karena ambang itu justru dilanggar CSS laporan bersama hari ini (lihat known_issue screen-158), dan layar baru tidak boleh mengulanginya.
- **Field waktu menerima pengetikan manual MAUPUN picker, tanpa input dinonaktifkan** ← aturan user yang sudah berlaku di proyek ini, dicatat agar tidak terlewat. Lihat [[feedback-web-input-conventions]].
- **Konfirmasi hapus inline menyebut nama shift dan line-nya**, dan akibat pada cakupan ditampilkan sesudahnya ← turunan agent, mengikuti pola konfirmasi inline screen-128.
- `test_priority` = `high` · 19 info · 6 aksi · 23 aturan · 21 edge case · 4 pertanyaan terbuka · 22 alternative flow · 28 bdd scenario ← seluruhnya turunan agent.

### Empat pertanyaan terbuka, dan mengapa dua di antaranya mendesak

1. **Record yang sudah ditandai shift bila jam shift diubah atau shift dihapus.** Tidak dibutuhkan untuk membangun master, tetapi dibutuhkan SEBELUM penandaan record mendarat di putaran berikutnya — memilihnya belakangan berarti memilih di antara data yang sudah terkumpul.
2. **Pola bergilir atau hari libur.** Master ini sengaja dibangun untuk pola harian yang TETAP: satu baris per shift per line, berlaku sama setiap hari. Bila di lapangan ada pola berbeda untuk hari Minggu, atau pola dua-hari-masuk-dua-hari-libur, modelnya butuh dimensi tambahan — dan menambahkannya setelah master berisi data jauh lebih mahal.
3. Sejak kapan jadwal baru berlaku (berlaku surut atau tidak).
4. Perlu tidaknya penanda shift yang sedang berjalan saat ini.

### Koreksi di tempat, 2026-10-15 (ber-`ver` sama — belum ada node hilir saat ditambal)

Pertanyaan terbuka #2 (hari libur / pola bergilir / musiman) **TERJAWAB oleh pemilik produk**: jam mulai dan selesai tiap shift **sama persis setiap hari**, sepanjang tahun, tanpa pengecualian menurut hari maupun musim. Yang bergilir adalah **regu**, bukan batas jam — dan pergiliran regu tidak menyentuh master ini, karena shift "Pagi 07:00–15:00" tetap ada setiap hari siapa pun yang mengisinya.

Butirnya dipertahankan di `open_questions` dengan penanda TERJAWAB agar alasannya tidak hilang, dan konsekuensinya diangkat menjadi `business_rules` tersendiri. Yang dikunci oleh jawaban ini:

- Satu baris per shift per line **sudah cukup** — tidak perlu dimensi hari, tidak perlu masa berlaku
- Validasi tumpang tindih tetap **satu dimensi** (jam saja), tidak menjadi jam × rentang tanggal
- "Jam sekian itu shift apa pada line ini" selalu punya jawaban yang sama tanpa mengetahui tanggalnya — ini yang membuat penandaan `shift_id` pada record di putaran berikutnya dapat ditentukan hanya dari jam perangkat
- Penugasan regu per hari, bila kelak dibutuhkan, adalah **entitas tersendiri** dan bukan perluasan tabel shift

Tiga pertanyaan terbuka tersisa: akibat pada record bila jam shift diubah/dihapus (mendesak sebelum penandaan record mendarat), sejak kapan jadwal baru berlaku, dan perlu tidaknya penanda shift aktif saat ini.
