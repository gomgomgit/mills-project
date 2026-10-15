# Derived Assumptions — module-dashboard.screen-159--laporan-engine-room-mobile.4-implement

## v1 — 2026-10-15

Nol perubahan backend. Yang tercatat di bawah hanya pilihan yang tidak ditetapkan spec, ditambah TIGA tempat di mana spec saya sendiri ternyata salah.

### Spec salah, implementasi benar — dan artefaknya SUDAH dikoreksi

- **Router mobile TIDAK punya daftar peran** ← spec versi pertama saya menulis "router menolak Mill Management dan Admin"; itu tidak dapat dipenuhi. Diverifikasi sendiri: `mobile/src/router/index.ts` hanya punya satu guard global atas `meta.public` yang memeriksa SESI, dan tidak ada satu rute pun di berkas itu yang membawa daftar peran. Yang benar-benar menahan kedua peran itu adalah otorisasi sisi server plus ketiadaan jalur mobile. Agen menolak memalsukan penolakan yang tidak ada, dan menggantinya dengan asersi kontrak sebenarnya yang MEMERAH bila penjaga peran kelak ditambahkan. Menambahkan penjaga itu adalah perubahan se-router yang menyentuh seluruh 13 layar laporan.
- **Porsi sebaran MEMANG dihitung di klien** ← diverifikasi `EngineRoomReportService` tidak menerbitkan field `share` sama sekali. Yang diikat aturan nol-aritmetika adalah PENYEBUTNYA: wajib `recorded_slots`, tidak boleh `expected_slots` (15/20 = 75,0%, bukan 15/240 = 6,3%). Satu-satunya pengecualian sah atas aturan itu di layar ini, sekarang dinyatakan di spec supaya tidak terbaca sebagai pelanggaran.
- **Rekap mobile MENUMPUK menjadi kartu, tidak menggulir mendatar** ← spec versi pertama saya menyalin aturan versi web. Konvensi mobile ditegakkan global oleh `v-stacked-table` + `src/assets/stacked-table.css` yang memaksa `.detail-table-wrap { overflow-x: visible }` di ≤640px, dan sebabnya tercatat di kepala berkas itu sebagai temuan QA LP-M-02/LP-M-03: pada 390px tabel 23 kolom menjadi ~1411px, dan menggulir mendatar di dalam halaman yang juga digulir tegak adalah gerakan yang paling mudah gagal di layar sentuh. Menambahkan `overflow-x: auto` dari style berlingkup akan MENGALAHKAN lembar bersama itu dan mengembalikan cacat QA tersebut. Satu-satunya elemen yang memang menggulir mendatar adalah `.chart-scroll`.

### Pilihan implementasi yang tidak dicakup spec

- **TIDAK ADA auto-pilih ketika line hanya satu** ← penyimpangan sengaja dari acuan Effluent Plant yang punya langkah keempat `lines.length === 1`; `business_logic` butir 5 hanya mendaftar tiga langkah. Konsekuensinya diambil secara sadar: pemilih line dirender setiap kali `productionLines.length > 0` (Effluent: hanya `> 1`), karena kalau tidak, mill berline-tunggal tidak akan pernah bisa memilih dan selamanya melihat nol angka.
- **`STATUS_VALUE_LABELS` dan `statusValueLabel()` diekspor dari REPO, bukan view** ← Effluent menyimpannya di view; memindahkannya ke repo memenuhi "delegasikan pencarian label, jangan sebarkan salinan" dan memungkinkan spec repo mengimpor petanya langsung.
- **Seri ekspor energi dirender sebagai tabel bertumpuk, bukan grafik batang** ← dan karena itu kolom itu TIDAK menampilkan min/maks/terakhir tingkat periode; menurunkannya akan menjadi aritmetika klien sekaligus keputusan "mana yang terakhir" yang kedua. Sel totalnya em dash.
- **Tinggi batang tren adalah skala tampilan klien** (`28% + 72%×(v−min)/(max−min)`) atas `daily_trend[].avg`, disalin dari blade, sama dengan dua belas sibling. Tidak pernah menyentuh seri energi.
- **422 dibedakan menurut PEMANGGIL, bukan kode HTTP** ← `/periods` dan `/summary` → mill-not-linked dengan arahan menghubungi Admin tanpa pesan jaringan; `/export` → pesan verbatim. **404 pada `/summary`** mengosongkan periode tanpa tombol coba lagi, karena mengulang id yang tidak ada tidak mungkin berhasil.
- **Keadaan luring lewat `navigator.onLine` + listener di `onMounted`/`onUnmounted`** ← tidak satu pun dari dua belas sibling punya ini; spec bisnis layar ini yang menuntutnya.
- **`CollapsibleSection` hanya diberi `title` + `data-testid`** ← view Effluent melewatkan prop `:subtitle` yang TIDAK ADA pada komponen itu; tidak disalin.
- **Metrik ber-`group` di luar keenam blok dikenal tidak dirender** ← disengaja: kolom di blok yang salah lebih buruk daripada kolom yang belum punya blok.
- **Tiap kartu membawa `data-metric="<kolom>"`** ← supaya asersi menyasar tepat, bukan lewat teks dua sampai tiga huruf.

### Pelajaran infrastruktur test yang berlaku untuk kembaran berikutnya

- **`vi.spyOn(window.localStorage, 'getItem')` diam-diam tidak berefek di jsdom** (ia Proxy) — spy-nya tidak pernah menyala dan test "mode privat" lolos lewat cabang yang bukan maksudnya. Pakai penukaran `Storage.prototype.getItem` dengan pemulihan di `finally`.
- **`<select>` dengan placeholder `:value="null"` tidak punya atribut `value`**, sehingga `element.value` jatuh ke TEKS option-nya — `toHaveValue('')` adalah merah palsu. Baca `option:checked` / `selectedOptions[0]`.
- **Penjaga klik-ganda ekspor perlu stub BERTUNDA di e2e** — dengan `page.route` yang membalas seketika, `exporting` sudah false ketika ketukan kedua mendarat → dua permintaan terkirim dan buktinya hijau tanpa membuktikan apa pun.
- Selektor prefiks seperti `[data-testid^="status-distribution-diesel-gen-"]` juga cocok dengan testid `-table` yang bersarang di dalamnya (4 kecocokan untuk 2 bagian).

Lihat [[feedback-vacuous-assertions]] dan [[project-station-report-addition-ripples]].
