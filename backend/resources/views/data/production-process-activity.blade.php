<x-layouts.app title="Production Process Activity">
    <x-slot:styles>
        <style>
            /* ────────────────────────────────────────────────────────────
               PAPAN STATUS, BUKAN PELUNCUR (2026-10-07)

               Sampai tanggal itu halaman ini 18 tile merah identik tanpa satu
               angka pun. Sekarang tiap tile membawa jumlah record hari ini dan
               kapan stasiunnya terakhir menerima input, dan WARNA MEMBAYAR
               TEMPATNYA: ia menandai keadaan, bukan menjadi latar bawaan.

               MERAH TETAP MERAH, DAN ITU KEPUTUSAN YANG DIPERIKSA, bukan sisa
               yang terlewat. Komentar screen-140 (/reports) menyatakan
               pembedaannya dengan tegas: hijau merek untuk keluarga LAPORAN,
               merah station-red untuk keluarga INPUT DATA, supaya kedua grid
               yang berbagi .station-grid/.station-tile tetap dapat dibedakan.
               Mengganti hue di sini menjadi hijau akan menghapus pembeda itu
               dan membuat grid input-data tak dapat dibedakan dari grid
               laporan. Yang berubah adalah KE MANA merah dipakai.

               SKEMANYA DIBALIK. Dulu: 18 tile merah, semuanya. Sekarang:
                 - belum ada input hari ini -> MERAH, karena itulah yang
                   menuntut perhatian, dan merah adalah warna keluarga ini
                 - sudah ada input hari ini -> tenang, dengan jumlahnya
                 - stasiun nonaktif di master -> kelabu, tanpa tautan
               Memindai halaman kini menjawab satu pertanyaan tanpa membaca:
               yang merah belum diisi hari ini.

               TOKEN, BUKAN LITERAL. Blok :root lokal yang dulu di sini
               menduplikasi --color-station-red, --color-surface, dan
               --radius-card yang SUDAH ada di partials/design-tokens.blade.php
               — termasuk mengulang nilai heksanya apa adanya. Duplikatnya
               dihapus; sesudah ini nilai itu hidup di tepat SATU berkas,
               yaitu berkas tokennya, dan sengaja tidak dikutip lagi di sini
               supaya pencarian atasnya hanya mendarat di definisinya. Token
               itu sendiri TIDAK dihapus: .badge-ongoing dan .badge-paused di
               partials/components-styles.blade.php juga memakainya.
               ──────────────────────────────────────────────────────────── */

            .ppa-head {
                display: flex;
                flex-wrap: wrap;
                align-items: baseline;
                gap: 8px 14px;
                margin-bottom: 6px;
            }

            .ppa-head__count {
                font-size: 15px;
                font-weight: 600;
                color: var(--color-text);
            }

            .ppa-head__scope {
                font-size: 12px;
                color: var(--color-text-muted);
            }

            /* Dua jendela waktu yang BERBEDA cakupannya, dan keterangan ini
               ada supaya tidak dibaca sebagai satu. "0 hari ini" di samping
               "input terakhir 3 menit lalu" terbaca seperti bug kalau
               pembacanya tidak diberi tahu bahwa yang kedua tidak dibatasi
               tanggal. */
            .ppa-legend {
                margin: 0 0 16px;
                font-size: 12px;
                line-height: 1.6;
                color: var(--color-text-muted);
                max-width: 760px;
            }

            /* Tiga kolom di setiap lebar — sama seperti sebelumnya dan sama
               seperti StationGrid.vue mobile. max-width dinaikkan 640 -> 900
               karena tile sekarang membawa dua baris status di bawah namanya;
               pada 640px baris itu terpotong-potong di layar lebar, yang
               justru membuang ruang yang tersedia. */
            .station-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 16px;
                max-width: 900px;
            }

            @media (max-width: 767px) {
                .station-grid {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                    max-width: none;
                }
            }

            .station-tile {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: flex-start;
                gap: 6px;
                min-height: 44px;
                padding: 16px 12px;
                border: 1px solid transparent;
                border-radius: var(--radius-card);
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
                font-size: 13px;
                font-weight: 600;
                text-align: center;
                text-decoration: none;
            }

            .station-tile svg {
                width: 22px;
                height: 22px;
            }

            /* BELUM ADA INPUT HARI INI — satu-satunya keadaan yang berwarna
               penuh, karena satu-satunya yang menuntut tindakan. */
            .station-tile.is-pending {
                background: var(--color-station-red);
                border-color: var(--color-station-red);
                color: #fff;
            }

            .station-tile.is-pending:hover {
                opacity: 0.92;
            }

            /* SUDAH ADA INPUT HARI INI — tenang. Garis tepi hijau merek
               cukup untuk menyatakan "beres" tanpa 18 tile berteriak. */
            .station-tile.is-done {
                background: var(--color-background);
                border-color: color-mix(in srgb, var(--color-brand) 40%, transparent);
                color: var(--color-text);
            }

            .station-tile.is-done:hover {
                border-color: var(--color-brand);
                background: color-mix(in srgb, var(--color-brand) 6%, transparent);
            }

            /* NONAKTIF DI MASTER — kelabu, tanpa tautan. Kelas ini TERJANGKAU
               KEMBALI sejak grid digerakkan station_types.is_active; sebelum
               itu ia CSS mati (18 tile selalu aktif, di-hardcode). Itulah
               sebabnya ia tidak dihapus bersama .placeholder-label. */
            .station-tile.is-inactive {
                background: var(--color-surface);
                border-color: var(--color-border);
                color: var(--color-text-muted);
                box-shadow: none;
                font-weight: 500;
                cursor: default;
            }

            .station-tile__name {
                line-height: 1.3;
            }

            .station-tile__stat {
                font-size: 11px;
                font-weight: 600;
                line-height: 1.4;
            }

            .station-tile__last {
                font-size: 10px;
                font-weight: 400;
                line-height: 1.4;
                opacity: 0.85;
            }

            .station-tile.is-done .station-tile__stat {
                color: var(--color-brand);
            }

            .station-tile.is-done .station-tile__last,
            .station-tile.is-inactive .station-tile__last {
                color: var(--color-text-muted);
            }
        </style>
    </x-slot:styles>

    {{--
        GRID DIGERAKKAN DATA (2026-10-07). Nama dan keaktifan stasiun dibaca
        dari master `station_types` lewat ProductionProcessActivityService —
        bukan lagi 18 blok literal. Dua sumber kebenaran yang dulu ada di sini
        hilang bersamanya: stasiun yang di-rename atau dinonaktifkan di master
        dulu mengubah /reports dan TIDAK mengubah halaman ini.

        TIGA FAKTA YANG MASIH MENGIKAT, dan hanya tiga — riwayat promosi
        placeholder 2026-08/09 serta mekanisme hide yang dihapus 2026-09-16
        sudah dibuang dari komentar ini karena tak satu pun masih berlaku:

        1. URUTAN TILE literal di ProductionProcessActivityService::TILE_ORDER,
           BUKAN station_types.sort_order. Ia urutan kustom permintaan produk
           (2026-09-01) yang dicerminkan ORDER BY berbasis CASE pada mobile
           stationRepo.ts, diverifikasi masih identik 2026-10-07. sort_order
           adalah urutan /reports dan memang berbeda.

        2. IKON di resources/views/data/partials/station-icon.blade.php, tetap
           di kode karena pasangannya ACTIVE_ICONS/PLACEHOLDER_ICONS pada
           mobile StationGrid.vue — supaya satu stasiun tidak bergambar beda di
           web dan di ponsel.

        3. .station-grid/.station-tile sengaja berbagi nama dengan grid
           /reports (screen-140). Bedanya warna: merah station-red untuk
           keluarga INPUT DATA ini, hijau merek untuk keluarga LAPORAN.
    --}}
    @php
        $activity = app(\App\Services\ProductionProcessActivityService::class);
        $tiles = $activity->tiles();
        $headline = $activity->todayHeadline($tiles);
    @endphp

    <div class="ppa-head">
        <span class="ppa-head__count" data-testid="ppa-headline">
            {{ $headline['with_input'] }} dari {{ $headline['active_total'] }} stasiun sudah ada input hari ini
        </span>
        <span class="ppa-head__scope" data-testid="ppa-scope">
            @if ($activity->isAllMills())
                Seluruh mill
            @else
                Mill akun Anda
            @endif
        </span>
    </div>

    <p class="ppa-legend" data-testid="ppa-legend">
        Angka <strong>hari ini</strong> menghitung record yang tanggalnya hari ini.
        <strong>Input terakhir</strong> adalah saat stasiun itu terakhir menerima record,
        tanpa dibatasi tanggal &mdash; jadi sebuah stasiun bisa menunjukkan nol hari ini
        sekaligus input terakhir beberapa menit lalu, dan keduanya benar.
        Tile merah berarti belum ada input hari ini; semua line pada mill ikut terhitung.
    </p>

    <div class="station-grid" data-testid="station-grid">
        @foreach ($tiles as $tile)
            @php
                $stateClass = ! $tile['is_active']
                    ? 'is-inactive'
                    : ($tile['has_input_today'] ? 'is-done' : 'is-pending');
            @endphp

            @if ($tile['route'] !== null)
                <a href="{{ route($tile['route']) }}"
                   class="station-tile {{ $stateClass }}"
                   data-testid="station-tile-{{ $tile['code'] }}">
                    @include('data.partials.station-icon', ['code' => $tile['code']])
                    <span class="station-tile__name">{{ $tile['name'] }}</span>
                    <span class="station-tile__stat" data-testid="station-today-{{ $tile['code'] }}">
                        @if ($tile['has_input_today'])
                            {{ $tile['today_count'] }} record hari ini
                        @else
                            {{-- NOL DIRENDER SEBAGAI PERNYATAAN, bukan sebagai
                                 angka 0: "0" di samping label terbaca sebagai
                                 nol yang terukur, padahal yang benar adalah
                                 belum ada yang mengisinya. --}}
                            Belum ada input hari ini
                        @endif
                    </span>
                    <span class="station-tile__last" data-testid="station-last-{{ $tile['code'] }}">
                        @if ($tile['last_input_at'] !== null)
                            Input terakhir {{ \Illuminate\Support\Carbon::parse($tile['last_input_at'])->diffForHumans() }}
                        @else
                            Belum pernah ada input
                        @endif
                    </span>
                </a>
            @else
                <span class="station-tile {{ $stateClass }}"
                      aria-disabled="true"
                      data-testid="station-tile-{{ $tile['code'] }}">
                    @include('data.partials.station-icon', ['code' => $tile['code']])
                    <span class="station-tile__name">{{ $tile['name'] }}</span>
                    <span class="station-tile__stat" data-testid="station-today-{{ $tile['code'] }}">
                        @if (! $tile['is_active'])
                            Tidak dioperasikan
                        @else
                            Layar datanya belum ada
                        @endif
                    </span>
                    <span class="station-tile__last" data-testid="station-last-{{ $tile['code'] }}">
                        @if ($tile['last_input_at'] !== null)
                            Input terakhir {{ \Illuminate\Support\Carbon::parse($tile['last_input_at'])->diffForHumans() }}
                        @else
                            Belum pernah ada input
                        @endif
                    </span>
                </span>
            @endif
        @endforeach
    </div>
</x-layouts.app>
