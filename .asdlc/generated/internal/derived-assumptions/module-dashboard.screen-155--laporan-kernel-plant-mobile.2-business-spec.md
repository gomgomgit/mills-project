# Derived Assumptions — module-dashboard.screen-155--laporan-kernel-plant-mobile.2-business-spec

## v1 — 2026-10-07

Autonomy `autopilot`: seluruh isi spec ini rumusan agent. User menetapkan satu hal — "lanjut
screen-155" — dan sebelumnya memilih melewati checkpoint pra-implementasi untuk rangkaian ini.

- Seluruh isi diturunkan dari screen-153 (Laporan Depricarping Mobile) sebagai model, lalu disesuaikan dengan fakta Kernel Plant ← keputusan agent. Depricarping mobile dipilih karena ia satu-satunya kembaran mobile yang sudah punya ketiganya: downtime numerik, findings teks bebas, dan kolom berbagi standar.

- TIGA PERBEDAAN yang membuat layar ini bukan salinan, dan ketiganya turunan agent dari pembacaan skema, bukan dinyatakan user: (1) DUA pasangan berbagi standar, bukan satu — di layar sempit keempat kartu bertumpuk berurutan sehingga target identik muncul dua kali, lalu dua kali lagi, dan pola itu terbaca sebagai data terduplikasi jauh lebih kuat daripada di tabel web; (2) DUA kolom target, bukan tiga — master Kernel Plant tak punya kolom batas kritis sama sekali, dan tidak ada medan yang dikarang sebagai keterangan selalu-kosong agar "sebangun"; (3) bagian standar-tanpa-pengukuran NORMALNYA BERISI satu baris ('Final Kernel Dirt'), kebalikan dari Depricarping yang normalnya kosong — jadi keadaan kosong justru yang luar biasa.

- `corrective_action_plan` dinyatakan sebagai kolom yang PALING BERISIKO dibuang demi ruang ← penilaian agent, bukan fakta yang dinyatakan: ia paling panjang teksnya, dan tanpanya dua parameter yang sama-sama melewati targetnya tampak menuntut tindakan yang sama padahal satu menuntut penyetelan rotor dan satu menuntut pemeriksaan elemen pemanas.

- 22 item informasi, 5 aksi, 22 aturan bisnis, 31 edge case = seluruhnya rumusan agent, diwarisi pola screen-153 lalu diperiksa terhadap bentuk respons screen-154 yang sebenarnya.

- TIGA pelajaran dari screen-154 dibawa masuk SEBELUM implementasi, supaya tidak terulang: penjaga null pada keterangan standar-bersama (defek yang baru ditutup di web, dan di sana fallback nilai tidak cukup karena kalimatnya sendiri menjadi tidak benar); `coverage_percent` tanda pisah hanya ketika PENYEBUT tak terbentuk (draf pertama spec web menyatakannya terbalik); dan M seragam pada ketujuh kartu, bukan per kolom (mock pertama mengarangnya).

- `test_priority: "high"` ← turunan agent, sejajar sepuluh laporan mobile lainnya.

- Aktor dibatasi Station Operator dan Supervisor ← turunan dari pola kembaran mobile. Mill Management dan Admin memakai layar web; layar ini tak punya pemilih mill sama sekali sehingga tak dapat melayani peran yang tidak terikat satu mill.

- Empat pertanyaan terbuka dirumuskan agent, dan satu di antaranya baru: apakah dua pasangan berbagi standar pada satu layar sempit cukup ditangani dengan keterangan, atau kedua anggota satu pasangan sebaiknya dibingkai bersama secara visual. Dipilih keterangan lebih dulu karena ia tidak menambah vokabulari tata letak baru.
