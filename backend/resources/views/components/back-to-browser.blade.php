@props(['url', 'class' => ''])

{{--
    Tombol Back pada layar Detail stasiun — kembali ke Data Browser DENGAN
    filter dan halaman yang sama (temuan QA WB-DTL-05).

    Dulu tombol ini `route('data.<stasiun>')` apa adanya: tanpa query string,
    sehingga penguji yang menyaring "2026-09-01..2026-10-11", membuka halaman
    2, lalu masuk ke satu record, mendarat kembali di daftar yang sudah polos
    dan di halaman 1. Pada daftar beribu baris, itu berarti mencari ulang dari
    awal setiap kali satu record diperiksa.

    Caranya: KEMBALI KE ENTRI RIWAYAT SEBELUMNYA, bukan menyusun ulang URL-nya
    di sini. Filter Data Browser kini hidup di query string (#[Url] pada
    komponen DataBrowser*), jadi entri riwayat itu sendiri sudah membawa
    keadaan yang persis — termasuk nomor halaman dan pilihan line yang tidak
    diketahui layar Detail.

    history.back() hanya dipakai bila halaman sebelumnya MEMANG Data Browser
    stasiun ini. Kalau tidak (tautan langsung, bookmark, atau masuk dari layar
    lain), tombolnya tetap tautan biasa ke daftar — tanpa penjagaan itu Back
    bisa membawa pengguna keluar dari aplikasi.
--}}
<a
    href="{{ $url }}"
    @class([$class])
    data-testid="back-button"
    x-data="{
        kembali(e) {
            const daftar = new URL(@js($url), window.location.origin)
            const dari = document.referrer
            if (dari && dari.startsWith(window.location.origin + daftar.pathname)) {
                e.preventDefault()
                window.history.back()
            }
        }
    }"
    x-on:click="kembali($event)"
>{{ $slot->isEmpty() ? 'Back' : $slot }}</a>
