{{--
    Beranda Operator (/beranda, operator.home) — pendaratan sementara untuk
    login web Operator. Akses web Operator terbatas (keputusan produk
    2026-10-04); layar "lihat data sendiri" menyusul lewat alur ASDLC.
    Semua kelas di bawah didefinisikan di slot styles halaman ini.
--}}
<x-layouts.app title="Beranda">
    <x-slot:styles>
        <style>
            .operator-home {
                max-width: 640px;
                padding: 28px 24px;
                background: #fff;
                border: 1px solid var(--color-border);
                border-radius: 10px;
            }

            .operator-home__title {
                margin: 0 0 12px;
                font-size: 20px;
                font-weight: 700;
                color: var(--color-text);
            }

            .operator-home__text {
                margin: 0 0 12px;
                font-size: 14px;
                line-height: 1.6;
                color: var(--color-text-muted);
            }

            .operator-home__text:last-child {
                margin-bottom: 0;
            }
        </style>
    </x-slot:styles>

    <section class="operator-home" data-testid="operator-home">
        <h2 class="operator-home__title">Selamat datang, {{ auth()->user()->name }}</h2>
        <p class="operator-home__text">
            Akses web untuk peran Operator saat ini terbatas. Input data log sheet
            stasiun dilakukan melalui aplikasi mobile Mills Smart Log.
        </p>
        <p class="operator-home__text">
            Dari web Anda dapat mengganti password melalui menu Ganti Password, atau
            keluar melalui tombol Logout di kanan atas.
        </p>
    </section>
</x-layouts.app>
