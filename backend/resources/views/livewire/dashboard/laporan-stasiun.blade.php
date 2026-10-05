{{--
    screen-140--laporan-stasiun-web — Laporan Stasiun (pemilih stasiun).

    Susunan mengikuti mock .asdlc/generated/2-business-spec/screens/html/
    screen-140--laporan-stasiun-web.html: hero, Langkah 1 (Mill), Langkah 2
    (grid stasiun), legenda. Kosakata kelasnya memakai `md-*` milik aplikasi
    (dashboard/partials/report-styles.blade.php), bukan token `--color-*`
    milik mock.

    Grid stasiun memakai .station-grid / .station-tile — bahasa visual yang
    sama dengan data/production-process-activity.blade.php, IKON PUN SALINAN
    PERSIS dari sana agar satu stasiun tidak bergambar beda di dua grid.
    Bedanya hanya warna tile aktif: hijau merek (keluarga LAPORAN), bukan
    merah station-red (keluarga input data).

    Bacaan saja: tidak ada satu pun tombol/field yang mengubah data.
--}}
@php
    /**
     * station_types.code => ikon SVG, disalin apa adanya dari
     * data/production-process-activity.blade.php. Kode yang tidak ada di
     * peta ini (mis. jenis stasiun baru yang ditambahkan lewat master)
     * tetap dapat tile — hanya memakai ikon umum, sehingga menambah jenis
     * stasiun tidak pernah merusak halaman ini.
     */
    $stationIcons = [
        'weighbridge' => '<circle cx="12" cy="13" r="8"></circle><path d="M12 9v4l3 2"></path><path d="M9 3h6"></path>',
        'grading' => '<rect x="3" y="4" width="18" height="4"></rect><rect x="3" y="10" width="18" height="4"></rect><rect x="3" y="16" width="18" height="4"></rect>',
        'cages-track' => '<rect x="3" y="7" width="18" height="13" rx="1"></rect><path d="M3 11h18"></path><path d="M8 7V4h8v3"></path>',
        'sterilizer' => '<rect x="4" y="4" width="16" height="16" rx="2"></rect><line x1="8" y1="9" x2="16" y2="9"></line><line x1="8" y1="13" x2="16" y2="13"></line>',
        'threshing' => '<circle cx="12" cy="12" r="9"></circle><line x1="8" y1="12" x2="16" y2="12"></line>',
        'pressing' => '<path d="M4 20V10l8-6 8 6v10"></path><line x1="12" y1="14" x2="12" y2="20"></line>',
        'depricarping' => '<rect x="4" y="4" width="16" height="16" rx="8"></rect><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line>',
        'kernel-plant' => '<rect x="5" y="3" width="14" height="18" rx="1"></rect><line x1="9" y1="8" x2="15" y2="8"></line><line x1="9" y1="12" x2="15" y2="12"></line>',
        'clarification' => '<circle cx="12" cy="12" r="8"></circle><circle cx="12" cy="12" r="3"></circle>',
        'effluent-plant' => '<circle cx="12" cy="12" r="9"></circle><path d="M8 12h8M12 8v8"></path>',
        'storage-tank' => '<rect x="4" y="6" width="16" height="14" rx="1"></rect><path d="M8 6V4h8v2"></path>',
        'engine-room' => '<rect x="6" y="2" width="12" height="20" rx="1"></rect><line x1="6" y1="8" x2="18" y2="8"></line><line x1="6" y1="14" x2="18" y2="14"></line>',
        'boiler-room' => '<path d="M6 21V9a6 6 0 0 1 12 0v12"></path><line x1="6" y1="15" x2="18" y2="15"></line>',
        'process-water' => '<path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"></path>',
        'solid-waste-disposal' => '<path d="M4 7h16"></path><path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"></path><path d="M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line>',
        'kernel-dispatch' => '<rect x="3" y="9" width="12" height="10" rx="1"></rect><path d="M15 12h6"></path><path d="M18 9l3 3-3 3"></path>',
        'cpo-dispatch' => '<rect x="3" y="5" width="10" height="14" rx="2"></rect><line x1="3" y1="10" x2="13" y2="10"></line><line x1="3" y1="14" x2="13" y2="14"></line><path d="M15 12h6"></path><path d="M18 9l3 3-3 3"></path>',
        'process-quality-control' => '<path d="M12 3l7 3v6c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6z"></path><path d="M9 12l2 2 4-4"></path>',
    ];

    $fallbackIcon = '<rect x="4" y="4" width="16" height="16" rx="2"></rect><line x1="9" y1="12" x2="15" y2="12"></line>';

    $availableCount = collect($stations)->where('report_available', true)->count();
@endphp

<div class="md" data-testid="laporan-stasiun">

    {{-- ============ Hero ============ --}}
    <section class="md-hero" data-testid="report-hero">
        <div class="md-hero__text">
            <p class="md-hero__eyebrow">Laporan Periode &middot; Per Stasiun</p>
            <h1 class="md-hero__title">Laporan Stasiun</h1>
            <p class="md-hero__subtitle">
                Pilih mill lebih dulu, lalu pilih stasiun yang laporan periodenya ingin dibuka.
            </p>
        </div>
        @if ($businessUnit !== null)
            <div class="md-hero__meta">
                <span class="md-chip md-chip--date">{{ $businessUnit['name'] }}</span>
            </div>
        @endif
    </section>

    {{-- ============ Langkah 1 — Mill ============ --}}
    <section>
        <div class="md-step">
            <span class="md-step__num {{ $businessUnit !== null && ! $needsProductionLineSelection ? 'md-step__num--done' : '' }}">1</span>
            <span class="md-step__title">Mill &amp; Production Line</span>
        </div>

        {{-- Toolbar filter bersama (components/report-filter-bar.blade.php),
             sama dengan yang dipakai seluruh laporan per stasiun. Production
             Line TANPA opsi "semua": satu tile per JENIS stasiun hanya
             menunjuk stasiun pasti bila line-nya sudah dipilih. --}}
        <x-report-filter-bar
            :is-admin="$isAdmin"
            :business-unit-options="$businessUnitOptions"
            :business-unit-id="$businessUnitId"
            mill-testid="mill-select"
            :mill-name="$businessUnit['name'] ?? null"
            mill-name-testid="mill-current"
            :show-mill-for-admin="true"
            :show-line="$businessUnit !== null"
            :production-line-options="$productionLineOptions"
            :selected-line-id="$productionLine['id'] ?? null"
            :selected-line-name="$productionLine['name'] ?? null">
            @if ($isAdmin)
                Mengganti mill mengosongkan pilihan Production Line.
            @else
                Mill mengikuti akun Anda; Production Line dipilih di sini sebagai konteks kerja.
            @endif
        </x-report-filter-bar>

        @if ($millMissingForAccount)
            {{-- Gagal tertutup: akun terikat mill tetapi mill-nya kosong.
                 Daftar seluruh mill TIDAK pernah dibaca maupun ditawarkan. --}}
            <div class="md-empty" data-testid="no-mill-for-account" style="margin-top: 16px">
                <span class="md-empty__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="13"/><line x1="12" y1="16" x2="12" y2="16"/></svg>
                </span>
                <p class="md-empty__title">Akun belum terhubung ke mill</p>
                <p class="md-empty__text">Akun Anda belum terhubung ke mill. Hubungi Admin.</p>
            </div>
        @endif

        @if ($needsProductionLineSelection && ! $needsMillSelection && ! $millMissingForAccount)
            {{-- Mill sudah pasti, production line belum. Grid stasiun
                 ditahan sepenuhnya, dengan alasan yang sama seperti ia
                 ditahan sebelum mill pasti: tile membawa konteksnya ke layar
                 laporan, dan tile tanpa line akan mendaratkan pengguna di
                 laporan yang meminta memilih line lagi. --}}
            <div class="md-empty" style="margin-top: 16px" data-testid="select-production-line-hint">
                <span class="md-empty__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h16"/><circle cx="8" cy="7" r="1.6"/><circle cx="14" cy="12" r="1.6"/><circle cx="10" cy="17" r="1.6"/></svg>
                </span>
                <p class="md-empty__title">Production Line belum dipilih</p>
                <p class="md-empty__text" data-testid="production-line-required-hint">
                    Pilih production line terlebih dahulu untuk menampilkan stasiun.
                </p>
                @if ($productionLineOptions === [])
                    <p class="md-empty__text" data-testid="no-production-lines">
                        Mill ini belum memiliki satu pun Production Line. Minta Admin membuatnya
                        lebih dulu di layar Kelola Production Line.
                    </p>
                @endif
            </div>
        @endif

        @if ($needsMillSelection)
            <div class="md-empty" style="margin-top: 16px">
                <span class="md-empty__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V8l7-4 7 4v13"/><path d="M9 21v-5h6v5"/></svg>
                </span>
                <p class="md-empty__title">Mill belum dipilih</p>
                <p class="md-empty__text" data-testid="mill-required-hint">
                    Pilih mill terlebih dahulu untuk menampilkan stasiun.
                </p>
                @if ($businessUnitOptions === [])
                    <p class="md-empty__text" data-testid="no-business-units">
                        Master Business Unit masih kosong &mdash; belum ada satu pun mill yang dapat dipilih.
                        Isi master Business Unit lebih dulu.
                    </p>
                @endif
            </div>
        @endif
    </section>

    {{-- ============ Langkah 2 — Grid stasiun ============
         Ditahan sepenuhnya selama mill DAN production line belum ditetapkan:
         urutannya mengikat, mill dan line dulu baru stasiun. Sebuah tile
         membawa keduanya di report_path, jadi tile tanpa salah satunya akan
         mendaratkan pengguna di layar yang memintanya memilih lagi. --}}
    @if ($businessUnit !== null && ! $needsProductionLineSelection)
        <section class="ld-region" wire:loading.delay.short.class="ld-region--busy" wire:loading.delay.short.attr="aria-busy" wire:target="businessUnitId,productionLineId">
            <div class="md-step">
                <span class="md-step__num">2</span>
                <span class="md-step__title">Stasiun</span>
            </div>
            <p class="md-step__hint">
                Urut sesuai proses produksi. Stasiun yang laporannya belum tersedia tetap ditampilkan.
            </p>

            <div class="station-grid" data-testid="station-grid">
                @foreach ($stations as $station)
                    @php $icon = $stationIcons[$station['code']] ?? $fallbackIcon; @endphp

                    @if ($station['report_available'])
                        <a href="{{ $station['report_path'] }}"
                           class="station-tile active"
                           data-testid="station-tile-{{ $station['code'] }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">{!! $icon !!}</svg>
                            {{ $station['name'] }}
                        </a>
                    @else
                        {{-- Nonaktif: tanpa href, tanpa wire:click, aria-disabled.
                             Menekannya tidak mengirim permintaan apa pun dan tidak
                             memindahkan halaman — memang bukan tautan. --}}
                        <span class="station-tile disabled"
                              aria-disabled="true"
                              data-testid="station-tile-{{ $station['code'] }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">{!! $icon !!}</svg>
                            {{ $station['name'] }}
                            <span class="placeholder-label">Belum tersedia</span>
                        </span>
                    @endif
                @endforeach
            </div>

            @if ($stations === [])
                <p class="md-filters__hint" data-testid="no-station-types" style="margin-top: 12px">
                    Master Jenis Stasiun masih kosong &mdash; belum ada jenis stasiun yang dapat ditampilkan.
                    Tambahkan jenis stasiun pada master lebih dulu.
                </p>
            @else
                <div class="md-legend">
                    <span class="md-legend__item">
                        <span class="md-legend__swatch md-legend__swatch--active"></span>
                        Laporan tersedia ({{ $availableCount }})
                    </span>
                    <span class="md-legend__item">
                        <span class="md-legend__swatch md-legend__swatch--disabled"></span>
                        Belum tersedia ({{ count($stations) - $availableCount }})
                    </span>
                    <span class="md-legend__item">
                        Mill &amp; Production Line terpilih ikut terbawa ke layar laporan.
                    </span>
                </div>
            @endif
        </section>
    @endif

</div>
