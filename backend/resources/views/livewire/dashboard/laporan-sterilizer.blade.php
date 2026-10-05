{{--
    screen-129--laporan-sterilizer-web — Laporan Periode Sterilizer.

    Susunan mengikuti mock .asdlc/generated/2-business-spec/screens/html/
    screen-129--laporan-sterilizer-web.html: hero, filter bar, 4 kartu KPI,
    tren harian, distribusi durasi, perbandingan antar unit, siklus
    menyimpang + kotak ambang, lalu tabel rekap harian yang dapat
    dibuka/tutup. Yang ditiru adalah SUSUNAN dan kepadatan informasinya —
    kosakata kelasnya memakai `md-*` milik aplikasi (dashboard/partials/
    report-styles.blade.php), bukan `rp-*` milik mock.

    Bacaan saja: tidak ada satu pun tombol/field yang mengubah data.
--}}
@php
    $num = fn ($value) => number_format((float) $value, 0, ',', '.');

    // Menit tampil tanpa koma bila bulat (100), dengan satu desimal bila
    // tidak (89,6) — angka laporan harus terbaca, bukan selalu berkoma.
    $mnt = function ($value) {
        if ($value === null) {
            return '–';
        }

        return fmod((float) $value, 1.0) === 0.0
            ? number_format((float) $value, 0, ',', '.')
            : number_format((float) $value, 1, ',', '.');
    };

    $pct = fn ($value) => $mnt($value).'%';

    $bulan = ['01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', '05' => 'Mei', '06' => 'Jun',
              '07' => 'Jul', '08' => 'Agu', '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'];

    $tgl = function (?string $date) use ($bulan) {
        if ($date === null || $date === '') {
            return '–';
        }

        [$y, $m, $d] = array_pad(explode('-', substr($date, 0, 10)), 3, '');

        return $d.' '.($bulan[$m] ?? $m).' '.$y;
    };

    $tglPendek = function (?string $date) use ($bulan) {
        [$y, $m, $d] = array_pad(explode('-', substr((string) $date, 0, 10)), 3, '');

        return $d.' '.($bulan[$m] ?? $m);
    };

    $statusLabel = fn (?string $status) => match ($status) {
        'draft' => 'Draft',
        'open' => 'Terbuka',
        'closed' => 'Tertutup',
        default => (string) $status,
    };

    $kpi = $summary['kpi'] ?? null;
    $daily = $summary['daily'] ?? [];
    $byUnit = $summary['by_unit'] ?? [];
    $outliers = $summary['outliers'] ?? null;
    $total = $summary['total'] ?? null;
    $hasData = $kpi !== null && $kpi['total_cycles'] > 0;
@endphp

<div class="md" data-testid="laporan-sterilizer">

    {{-- ============ 1. Hero: periode yang sedang dibaca ============ --}}
    <section class="md-hero" data-testid="report-hero">
        <div class="md-hero__text">
            <p class="md-hero__eyebrow">Laporan Periode &middot; Stasiun Sterilizer</p>
            <h1 class="md-hero__title">{{ $selectedPeriod['name'] ?? 'Laporan Sterilizer' }}</h1>
            <p class="md-hero__subtitle">
                @if ($selectedPeriod)
                    {{ $summary['period']['business_unit_name'] ?? '' }}
                    @if (! empty($summary['period']['business_unit_name'])) &middot; @endif
                    {{-- Nama line di hero, sama seperti Laporan Weighbridge
                         (temuan audit 2026-10-04 #10). --}}
                    @if ($selectedProductionLine) {{ $selectedProductionLine['name'] }} &middot; @endif
                    {{ $tgl($selectedPeriod['start_date']) }} &ndash; {{ $tgl($selectedPeriod['end_date']) }}
                @else
                    Rangkuman kinerja perebusan sepanjang satu Periode Pelaporan
                @endif
            </p>
        </div>
        @if ($selectedPeriod)
            <div class="md-hero__meta">
                <span class="md-chip md-chip--date" data-testid="hero-range">
                    {{ $tgl($selectedPeriod['start_date']) }} &ndash; {{ $tgl($selectedPeriod['end_date']) }}
                </span>
                <span class="md-chip md-chip--status {{ $selectedPeriod['status'] === 'closed' ? 'md-chip--closed' : '' }} {{ $selectedPeriod['status'] === 'draft' ? 'md-chip--draft' : '' }}"
                      data-testid="hero-status">
                    {{ $statusLabel($selectedPeriod['status']) }}
                </span>
            </div>
        @endif
    </section>

    {{-- ============ 2. Filter bar ============ --}}
    {{-- Filter bar bersama (components/report-filter-bar.blade.php):
         Mill (Admin) / keterangan mill, Production Line, Periode, ekspor. --}}
    <x-report-filter-bar
        :is-admin="$isAdmin"
        :business-unit-options="$businessUnitOptions"
        :business-unit-id="$businessUnitId"
        mill-testid="mill-select"
        :mill-name="$businessUnitName"
        mill-name-testid="mill-current"
        :show-line="! $needsMillSelection"
        :production-line-options="$productionLineOptions"
        :selected-line-id="$selectedProductionLine['id'] ?? null"
        :selected-line-name="$selectedProductionLine['name'] ?? null"
        :show-period="! $needsMillSelection"
        :periods="$periods"
        :period-id="$selectedPeriod['id'] ?? null"
        :selected-period-status="$selectedPeriod['status'] ?? null"
        period-testid="period-select"
        :period-placeholder-when-empty="true"
        :export-action="$summary !== null ? 'export' : null"
        export-csv-testid="export-csv"
        export-excel-testid="export-excel">
        Hanya periode yang mencakup Sterilizer yang ditampilkan.
    </x-report-filter-bar>

    @if ($needsMillSelection)
        {{-- Empty state (a): Admin belum memilih mill. Admin tidak terikat
             satu mill, jadi tanpa pemilihan tidak ada data yang bisa
             ditampilkan — halaman meminta pemilihan, bukan menampilkan
             laporan kosong yang terbaca seperti "tidak ada data". --}}
        <div class="md-empty" data-testid="empty-select-mill">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
            </span>
            <p class="md-empty__title">Pilih mill terlebih dahulu</p>
            <p class="md-empty__text">
                Sebagai Admin Anda tidak terikat pada satu mill. Pilih mill pada pemilih di atas
                untuk menampilkan daftar Periode Pelaporan dan laporan Sterilizer-nya.
            </p>
        </div>
    @elseif ($needsProductionLineSelection)
        {{-- Mill sudah pasti, production line belum. Sama persis dengan
             keadaan "Admin belum memilih mill" di atas: tidak ada satu angka
             pun yang ditampilkan, karena angka gabungan lintas line tidak
             bisa ditindaklanjuti. Daftar Periode Pelaporan di atas TIDAK
             berubah — periode tetap per mill; yang tersaring adalah
             datanya. --}}
        <div class="md-empty" data-testid="select-production-line-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h16"/><circle cx="8" cy="7" r="1.6"/><circle cx="14" cy="12" r="1.6"/><circle cx="10" cy="17" r="1.6"/></svg>
            </span>
            <p class="md-empty__title">Pilih production line terlebih dahulu</p>
            <p class="md-empty__text">
                Laporan Sterilizer menghasilkan angka gabungan, dan mencampur beberapa
                production line membuat angkanya tidak bisa ditindaklanjuti. Pilih satu
                production line pada pemilih di atas untuk menampilkan laporannya.
            </p>
        </div>
    @elseif ($periods === [])
        {{-- Empty state (b): mill belum punya Periode Pelaporan yang mencakup
             Sterilizer. Bukan 404 — cukup arahkan ke layar yang membuatnya. --}}
        <div class="md-empty" data-testid="empty-no-periods">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
            </span>
            <p class="md-empty__title">Belum ada Periode Pelaporan</p>
            <p class="md-empty__text">
                Mill ini belum memiliki Periode Pelaporan yang mencakup stasiun Sterilizer.
                Minta Admin membuatnya terlebih dahulu di layar Kelola Periode Pelaporan,
                lalu laporan periode akan tampil di sini.
            </p>
        </div>
    @elseif ($summary !== null)

        {{-- ============ 3. Empat kartu KPI ============ --}}
        <section class="md-kpis md-kpis--4" data-testid="report-kpis">
            <article class="md-kpi" data-testid="kpi-total-cycles">
                <div class="md-kpi__top">
                    <span class="md-kpi__label">Total Siklus Rebus</span>
                    <span class="md-kpi__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.6-6.4"/><polyline points="21 3 21 8 16 8"/></svg>
                    </span>
                </div>
                <p class="md-kpi__value">{{ $num($kpi['total_cycles']) }} <span>siklus</span></p>
                <p class="md-kpi__meta">
                    @if (count($daily) > 0)
                        Rata-rata {{ $mnt(round($kpi['total_cycles'] / count($daily), 1)) }} siklus per hari berdata
                    @else
                        Belum ada tanggal dengan siklus tercatat
                    @endif
                </p>
                <div class="md-bar"><span style="width: {{ $kpi['total_cycles'] > 0 ? 100 : 0 }}%"></span></div>
                <p class="md-kpi__foot">{{ count($daily) }} tanggal memuat siklus dalam periode ini</p>
            </article>

            <article class="md-kpi" data-testid="kpi-total-cages">
                <div class="md-kpi__top">
                    <span class="md-kpi__label">Total Lori Direbus</span>
                    <span class="md-kpi__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="9" rx="1.5"/><circle cx="8" cy="19" r="2"/><circle cx="16" cy="19" r="2"/></svg>
                    </span>
                </div>
                <p class="md-kpi__value">{{ $num($kpi['total_cages']) }} <span>lori</span></p>
                <p class="md-kpi__meta">
                    @if ($kpi['total_cycles'] > 0)
                        Rata-rata {{ $mnt(round($kpi['total_cages'] / $kpi['total_cycles'], 1)) }} lori per siklus
                    @else
                        Belum ada siklus untuk dihitung
                    @endif
                </p>
                <div class="md-bar"><span style="width: {{ $kpi['total_cages'] > 0 ? 100 : 0 }}%"></span></div>
                <p class="md-kpi__foot">Diambil dari isian jumlah lori tiap siklus</p>
            </article>

            <article class="md-kpi" data-testid="kpi-avg-duration">
                <div class="md-kpi__top">
                    <span class="md-kpi__label">Durasi Rata-rata</span>
                    <span class="md-kpi__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15.5 14"/></svg>
                    </span>
                </div>
                <p class="md-kpi__value">{{ $mnt($kpi['avg_duration_minutes']) }} <span>menit</span></p>
                <p class="md-kpi__meta">
                    @if ($kpi['min_duration_minutes'] !== null)
                        Rentang {{ $mnt($kpi['min_duration_minutes']) }} &ndash; {{ $mnt($kpi['max_duration_minutes']) }} menit
                    @else
                        Belum ada siklus yang durasinya terisi
                    @endif
                </p>
                <div class="md-bar"><span style="width: {{ $kpi['avg_duration_minutes'] !== null ? 100 : 0 }}%"></span></div>
                {{-- WAJIB tampil di dekat kartu durasi: siklus tanpa durasi
                     tetap dihitung pada Total Siklus tetapi dikeluarkan dari
                     rata-rata, sehingga angka di atas tidak boleh dibaca
                     seolah mencakup seluruh siklus. --}}
                <p class="md-kpi__foot" data-testid="cycles-without-duration">
                    Dihitung dari {{ $num($kpi['total_cycles'] - $kpi['cycles_without_duration']) }} siklus
                    &middot; {{ $num($kpi['cycles_without_duration']) }} siklus tanpa durasi dikeluarkan
                </p>
            </article>

            <article class="md-kpi" data-testid="kpi-triple-peak">
                <div class="md-kpi__top">
                    <span class="md-kpi__label">Kepatuhan Triple-Peak</span>
                    <span class="md-kpi__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 17 7 9 11 15 15 6 18 12 21 8"/></svg>
                    </span>
                </div>
                <p class="md-kpi__value">{{ $mnt($kpi['triple_peak_compliance_percent']) }} <span>%</span></p>
                <p class="md-kpi__meta">
                    {{ $num($total['triple_peak_complete']) }} dari {{ $num($kpi['total_cycles']) }} siklus lengkap 3 puncak &amp; 3 buang
                </p>
                <div class="md-bar"><span style="width: {{ min(100, max(0, (float) $kpi['triple_peak_compliance_percent'])) }}%"></span></div>
                <p class="md-kpi__foot">
                    {{ $num($kpi['total_cycles'] - $total['triple_peak_complete']) }} siklus belum lengkap keenam waktunya
                </p>
            </article>
        </section>

        @if (! $hasData)
            {{-- Empty state (c): periode valid tetapi tidak memuat satu pun
                 siklus. KPI tetap tampil bernilai nol; grafik TIDAK dipaksa
                 menggambar batang kosong. --}}
            <div class="md-empty" data-testid="empty-no-data">
                <span class="md-empty__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19h16M7 16V9M12 16V5M17 16v-4"/></svg>
                </span>
                <p class="md-empty__title">Belum ada data pada periode ini</p>
                <p class="md-empty__text">
                    Seluruh angka utama ditampilkan nol karena belum ada data pada periode ini —
                    tidak ada satu pun siklus rebus tercatat pada rentang tanggalnya, sehingga
                    grafik dan tabel rekap tidak digambar.
                </p>
            </div>
        @else

            {{-- ============ 4. Tren harian siklus ============ --}}
            @php
                $maxDailyCycles = max(array_map(fn ($row) => $row['cycles'], $daily));
                $avgDailyCycles = $kpi['total_cycles'] / max(count($daily), 1);
            @endphp
            <section class="md-card" data-testid="daily-trend">
                <header class="md-card__head">
                    <h3>Tren Harian Siklus Rebus</h3>
                    <span class="md-card__hint">
                        Jumlah siklus per tanggal kejadian &middot; batang oranye = di bawah rata-rata harian
                        ({{ $mnt(round($avgDailyCycles, 1)) }} siklus)
                    </span>
                </header>
                <div class="md-trendchart md-trendchart--days">
                    @foreach ($daily as $row)
                        <div class="md-trendchart__col {{ $row['cycles'] < $avgDailyCycles ? 'md-trendchart__col--low' : '' }}"
                             data-testid="daily-trend-col-{{ $row['date'] }}">
                            <span class="md-trendchart__val">{{ $num($row['cycles']) }}</span>
                            <div class="md-trendchart__bar" style="height: {{ round(100 * $row['cycles'] / max($maxDailyCycles, 1), 1) }}%"></div>
                            <span class="md-trendchart__lbl">{{ $tglPendek($row['date']) }}</span>
                        </div>
                    @endforeach
                </div>
                @if (count($daily) > 10)
                    <p class="md-scrollhint" data-testid="scroll-hint">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M15 8l4 4-4 4M9 8l-4 4 4 4"/></svg>
                        Geser mendatar untuk melihat seluruh tanggal.
                    </p>
                @endif
                <ul class="md-legend md-legend--inline">
                    <li><span>{{ count($daily) }} tanggal berdata &middot; tertinggi {{ $num($maxDailyCycles) }} siklus/hari</span></li>
                </ul>
            </section>

            <div class="md-row md-row--2">
                {{-- ============ 5. Distribusi durasi per hari ============ --}}
                @php
                    $durationDays = array_values(array_filter($daily, fn ($row) => $row['avg_duration'] !== null));
                    $scaleMin = $kpi['min_duration_minutes'];
                    $scaleMax = $kpi['max_duration_minutes'];
                    $scaleSpan = ($scaleMin === null || $scaleMax === null) ? 0 : max($scaleMax - $scaleMin, 0);
                @endphp
                <section class="md-card" data-testid="duration-distribution">
                    <header class="md-card__head">
                        <h3>Distribusi Durasi Perebusan</h3>
                        <span class="md-card__hint">Per hari &middot; tersingkat &middot; rata-rata &middot; terlama (menit)</span>
                    </header>
                    @if ($durationDays === [])
                        <p class="md-card__hint">
                            Belum ada siklus berdurasi pada periode ini, sehingga distribusi durasi tidak dapat digambar.
                        </p>
                    @else
                        <div class="md-dist">
                            @foreach ($durationDays as $row)
                                @php
                                    $left = $scaleSpan > 0 ? 100 * ($row['min_duration'] - $scaleMin) / $scaleSpan : 0;
                                    $width = $scaleSpan > 0 ? 100 * ($row['max_duration'] - $row['min_duration']) / $scaleSpan : 100;
                                    $avgLeft = $scaleSpan > 0 ? 100 * ($row['avg_duration'] - $scaleMin) / $scaleSpan : 50;
                                @endphp
                                <div class="md-dist__row" data-testid="duration-row-{{ $row['date'] }}">
                                    <span class="md-dist__day">{{ $tglPendek($row['date']) }}</span>
                                    <div class="md-dist__track">
                                        <span class="md-dist__range" style="left: {{ round($left, 1) }}%; width: {{ round(max($width, 2), 1) }}%"></span>
                                        <span class="md-dist__avg" style="left: {{ round(min($avgLeft, 99), 1) }}%"></span>
                                    </div>
                                    <span class="md-dist__nums">
                                        {{ $mnt($row['min_duration']) }} &middot; <b>{{ $mnt($row['avg_duration']) }}</b> &middot; {{ $mnt($row['max_duration']) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                        <ul class="md-legend md-legend--inline">
                            <li><span>Skala {{ $mnt($scaleMin) }} &ndash; {{ $mnt($scaleMax) }} menit</span></li>
                            <li><span>{{ $num($kpi['cycles_without_duration']) }} siklus tanpa durasi tidak ikut dihitung</span></li>
                        </ul>
                    @endif
                </section>

                {{-- ============ 6. Perbandingan antar unit Sterilizer ============ --}}
                @php
                    $maxUnitCycles = $byUnit === [] ? 0 : max(array_map(fn ($row) => $row['cycles'], $byUnit));
                @endphp
                <section class="md-card" data-testid="by-unit">
                    <header class="md-card__head">
                        <h3>Perbandingan Unit Sterilizer</h3>
                        <span class="md-card__hint">Batang = porsi siklus terhadap unit tersibuk</span>
                    </header>
                    <div class="md-units">
                        @foreach ($byUnit as $unit)
                            <article class="md-unit" data-testid="unit-{{ $unit['sterilizer_no'] }}">
                                <div class="md-unit__top">
                                    <span class="md-unit__label">{{ $unit['sterilizer_no'] !== '' ? $unit['sterilizer_no'] : 'Tanpa nomor' }}</span>
                                    <span class="md-pill">{{ $num($unit['cycles']) }} siklus</span>
                                </div>
                                <div class="md-unit__stats">
                                    <span>Lori <b>{{ $num($unit['cages']) }}</b></span>
                                    <span>Durasi rata-rata <b>{{ $mnt($unit['avg_duration']) }} mnt</b></span>
                                    <span>Triple-peak <b>{{ $num($unit['triple_peak_complete']) }}/{{ $num($unit['cycles']) }}</b></span>
                                </div>
                                <div class="md-bar md-bar--lg"><span style="width: {{ round(100 * $unit['cycles'] / max($maxUnitCycles, 1), 1) }}%"></span></div>
                            </article>
                        @endforeach
                    </div>
                </section>
            </div>

            {{-- ============ 7. Siklus menyimpang + kotak ambang ============ --}}
            <section class="md-card" data-testid="outliers">
                <header class="md-card__head">
                    <h3>Siklus di Luar Durasi Normal</h3>
                    <span class="md-card__hint">
                        {{ $num(count($outliers['items'])) }} siklus dari {{ $num($outliers['sample_size']) }} siklus berdurasi
                    </span>
                </header>

                @if ($outliers['insufficient_data'])
                    {{-- Di bawah min_sample_size kuartil tidak bermakna; menandai
                         pencilan dari sampel sekecil itu lebih menyesatkan
                         daripada tidak menandai sama sekali. --}}
                    <p class="md-card__hint" data-testid="outliers-insufficient">
                        Datanya terlalu sedikit untuk menilai penyimpangan: baru
                        {{ $num($outliers['sample_size']) }} siklus yang durasinya terisi, sedangkan
                        perhitungan ambang membutuhkan minimal {{ $num($outliers['min_sample_size']) }} siklus.
                        Ambang batas belum dihitung untuk periode ini.
                    </p>
                @else
                    @if ($outliers['items'] === [])
                        {{-- Durasi seragam (IQR = 0) atau seluruhnya di dalam
                             ambang: kartu MENYATAKAN hal itu dan tetap
                             menampilkan ambangnya, bukan tampil kosong. --}}
                        <p class="md-card__hint" data-testid="outliers-none">
                            Tidak ada siklus di luar kebiasaan. Seluruh siklus berdurasi berada di dalam
                            ambang wajar periode ini, sehingga tidak ada siklus di luar kebiasaan yang perlu ditinjau.
                        </p>
                    @else
                        <div class="md-outliers">
                            @foreach ($outliers['items'] as $item)
                                <article class="md-outlier" data-testid="outlier-item">
                                    <span class="md-outlier__icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg>
                                    </span>
                                    <div class="md-outlier__body">
                                        <p class="md-outlier__title">
                                            {{ $tgl($item['date']) }} &middot;
                                            {{ $item['sterilizer_no'] !== '' ? $item['sterilizer_no'] : 'Tanpa nomor' }}
                                        </p>
                                        <p class="md-outlier__meta">
                                            {{ $num($item['number_of_cages']) }} lori
                                            @if ($item['cages_status']) &middot; {{ $item['cages_status'] }} @endif
                                            @if ($item['close_door_time'] && $item['open_door_time'])
                                                &middot; pintu tutup {{ substr($item['close_door_time'], 0, 5) }},
                                                pintu buka {{ substr($item['open_door_time'], 0, 5) }}
                                            @endif
                                            &middot;
                                            @if ($item['duration_minutes'] > $outliers['upper_bound'])
                                                {{ $mnt(round($item['duration_minutes'] - $outliers['upper_bound'], 1)) }} menit di atas ambang atas
                                            @else
                                                {{ $mnt(round($outliers['lower_bound'] - $item['duration_minutes'], 1)) }} menit di bawah ambang bawah
                                            @endif
                                        </p>
                                    </div>
                                    <p class="md-outlier__value">{{ $num($item['duration_minutes']) }} <span>mnt</span></p>
                                </article>
                            @endforeach
                        </div>
                    @endif

                    {{-- Kotak ambang SELALU tampil saat ambang terhitung — angka
                         yang muncul (atau tidak muncul) di atas harus dapat
                         dipertanggungjawabkan pembacanya. --}}
                    <div class="md-threshold" data-testid="outlier-threshold">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 2px"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/></svg>
                        <span>
                            <b>Ambang batas yang dipakai:</b> metode kuartil (Tukey) &mdash; kuartil bawah
                            {{ $mnt($outliers['q1'] ?? null) }} dan kuartil atas {{ $mnt($outliers['q3'] ?? null) }} menit,
                            jarak antarkuartil {{ $mnt($outliers['iqr'] ?? null) }} menit, sehingga ambang batas wajar adalah
                            <b>{{ $mnt($outliers['lower_bound']) }} &ndash; {{ $mnt($outliers['upper_bound']) }} menit</b>
                            (kuartil &plusmn; 1,5 &times; jarak antarkuartil).
                            Ambang dihitung dari sebaran durasi periode ini sendiri, bukan dari nilai baku tetap.
                            Metode kuartil dipakai, bukan rata-rata &plusmn; simpangan baku, karena simpangan baku
                            ikut membesar oleh siklus menyimpang itu sendiri sehingga justru menyembunyikannya.
                            {{ $num($kpi['cycles_without_duration']) }} siklus tanpa durasi tidak ikut dinilai.
                        </span>
                    </div>
                @endif
            </section>

            {{-- ============ 8. Tabel rekap harian ============ --}}
            <details class="md-details" data-testid="recap-details" @if ($showRecap) open @endif>
                <summary wire:click="toggleRekapHarian" data-testid="recap-toggle">
                    Rekap Harian Sterilizer
                    <small>{{ count($daily) }} tanggal &middot; klik untuk {{ $showRecap ? 'menyembunyikan' : 'menampilkan' }}</small>
                </summary>
                <div class="md-recap">
                    <table class="md-table" data-testid="recap-table">
                        <thead>
                            <tr>
                                <th scope="col">Tanggal</th>
                                <th scope="col">Siklus</th>
                                <th scope="col">Lori</th>
                                <th scope="col">Lori/Siklus</th>
                                <th scope="col">Durasi Min</th>
                                <th scope="col">Durasi Rata-rata</th>
                                <th scope="col">Durasi Maks</th>
                                <th scope="col">Triple-Peak Lengkap</th>
                                <th scope="col">Tanpa Durasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($daily as $row)
                                <tr data-testid="recap-row-{{ $row['date'] }}">
                                    <td>{{ $tgl($row['date']) }}</td>
                                    <td>{{ $num($row['cycles']) }}</td>
                                    <td>{{ $num($row['cages']) }}</td>
                                    <td>{{ $mnt(round($row['cages'] / max($row['cycles'], 1), 1)) }}</td>
                                    <td>{{ $mnt($row['min_duration']) }}</td>
                                    <td>{{ $mnt($row['avg_duration']) }}</td>
                                    <td>{{ $mnt($row['max_duration']) }}</td>
                                    <td>{{ $num($row['triple_peak_complete']) }} ({{ $pct(round(100 * $row['triple_peak_complete'] / max($row['cycles'], 1), 1)) }})</td>
                                    <td @class(['is-muted' => $row['cycles_without_duration'] === 0])>
                                        {{ $row['cycles_without_duration'] === 0 ? '–' : $num($row['cycles_without_duration']) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr data-testid="recap-row-total">
                                <td>TOTAL PERIODE</td>
                                <td>{{ $num($total['cycles']) }}</td>
                                <td>{{ $num($total['cages']) }}</td>
                                <td>{{ $mnt(round($total['cages'] / max($total['cycles'], 1), 1)) }}</td>
                                <td>{{ $mnt($total['min_duration']) }}</td>
                                <td>{{ $mnt($total['avg_duration']) }}</td>
                                <td>{{ $mnt($total['max_duration']) }}</td>
                                <td>{{ $num($total['triple_peak_complete']) }} ({{ $pct($kpi['triple_peak_compliance_percent']) }})</td>
                                <td>{{ $num($total['cycles_without_duration']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </details>
        @endif
    @endif
</div>
