{{--
    Laporan Grading (web) — screen-146--laporan-grading-web.

    APA YANG DILAPORKAN HALAMAN INI, dan mengapa bentuknya berbeda dari enam
    laporan stasiun sebelumnya: yang diringkas di sini bukan throughput dan
    bukan pembacaan alat, melainkan MUTU PANEN. Satu baris = satu muatan truk
    yang disortir, dan yang dicari pembaca adalah komposisi kematangannya.

    EMPAT HAL YANG MENENTUKAN TATA LETAKNYA:

    1. SATUAN JANJANG DAN KILOGRAM TIDAK PERNAH DIJUMLAHKAN. Tiga belas
       parameter dihitung dalam janjang; tiga parameter brondolan ditimbang
       dalam kilogram. Keduanya dirender sebagai DUA TABEL TERPISAH, bukan satu
       tabel berkolom "satuan" — satu tabel mengundang pembaca menjumlahkan
       kolom kuantitasnya, dan jumlah itu bukan bilangan apa pun. Total tiap
       tabel sekaligus menjadi penyebut pangsa tabel itu sendiri.

    2. TIAP PARAMETER MENAMPILKAN DUA ANGKA YANG DAPAT BERTENTANGAN, DAN
       HALAMAN INI MENYATAKAN SEBABNYA. "Pangsa" berbobot menurut besar muatan;
       "Rata-rata %" memperlakukan tiap muatan sama. Satu muatan raksasa yang
       buruk mendominasi yang pertama tanpa menggerakkan yang kedua. Menerbitkan
       salah satunya saja menyembunyikan separuh kenyataan; menerbitkan keduanya
       tanpa keterangan hanya akan terbaca sebagai dua angka yang saling
       membantah — jadi kotak keterangan di antara kedua tabel itu menanggung
       beban, bukan hiasan.

    3. SETIAP RATA-RATA MEMBAWA PENYEBUTNYA SENDIRI. "Rata-rata %" dibagi
       jumlah muatan yang BENAR-BENAR MENCATAT parameter itu, dan jumlah itu
       dicetak di sebelahnya. Muatan yang tidak mencantumkan sebuah parameter
       TIDAK menilainya nol persen — ia tidak menilainya sama sekali.

    4. KELENGKAPAN PENCATATAN ADALAH BAGIAN LAPORAN, BUKAN CATATAN KAKI, dan
       keadaannya BERLAKU BERBEDA: muatan DRAFT ikut terhitung di seluruh
       angka; muatan TANPA SATU PUN BARIS PARAMETER ikut jumlah muatan dan
       netto tetapi tidak menyumbang apa pun pada kedua tabel parameter; muatan
       yang BELUM DIPERIKSA atau BELUM DISAHKAN tetap terhitung penuh. Semuanya
       dinyatakan di satu tempat, supaya selisih antara layar ini dan Data
       Browser dapat dijelaskan.

    TIDAK ADA PENGHITUNG "TANPA TANGGAL" di sini, dan ketiadaannya disengaja:
    grading_records.date adalah kolom NOT NULL, jadi setiap muatan selalu dapat
    ditempatkan pada sebuah periode. Laporan Weighbridge punya penghitung itu
    hanya karena penanda waktunya nullable.

    PERSENTASE DIBACA, TIDAK DIHITUNG ULANG. Nilainya ditetapkan layar input
    saat penyimpanan — berbasis netto untuk satuan kg, berbasis jumlah janjang
    untuk satuan janjang — dan diteruskan apa adanya, supaya angka di laporan
    tidak pernah berbeda dari angka yang dilihat petugas.

    SATUAN BERAT ADALAH KILOGRAM, apa adanya dari kolomnya, tanpa konversi —
    konvensi yang sama dengan form input dan Data Browser.

    TIDAK ADA PENANDAAN NILAI DI LUAR BATAS di layar ini — tanpa kartu ambang,
    tanpa warna aman/bahaya, tanpa outlier. Grading tidak punya master target
    mutu, dan menurunkan ambang dari data periode itu sendiri berisiko dibaca
    sebagai batas resmi padahal bukan. Kotak keterangan memakai kelas
    `.md-explain`, bukan `.md-threshold`, karena ketiadaan penandaan diasersi
    MENURUT NAMA pada HTML ter-render.

    Bacaan saja: tidak ada satu pun tombol/field yang mengubah data stasiun.
--}}
@php
    // Angka dengan jumlah desimal tetap. null SELALU menjadi "tidak
    // tersedia", TIDAK PERNAH 0 — nol berarti "terukur dan hasilnya nol",
    // sedangkan tidak tersedia berarti "tidak pernah diukur".
    $nilai = function ($value, int $digits = 2) {
        if ($value === null) {
            return 'tidak tersedia';
        }

        return number_format((float) $value, $digits, ',', '.');
    };

    $cacah = fn ($value) => number_format((float) $value, 0, ',', '.');

    $persen = fn ($value) => $value === null ? 'tidak tersedia' : number_format((float) $value, 2, ',', '.').'%';

    $bulan = ['01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', '05' => 'Mei', '06' => 'Jun',
              '07' => 'Jul', '08' => 'Agu', '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'];

    $tgl = function (?string $date) use ($bulan) {
        if ($date === null || $date === '') {
            return 'tidak tersedia';
        }

        [$y, $m, $d] = array_pad(explode('-', substr($date, 0, 10)), 3, '');

        return $d.' '.($bulan[$m] ?? $m).' '.$y;
    };

    $statusLabel = fn (?string $status) => match ($status) {
        'draft' => 'Draft',
        'open' => 'Terbuka',
        'closed' => 'Tertutup',
        default => (string) $status,
    };

    // ---------------------------------------------------------------
    // DUA KELOMPOK SATUAN, DIBACA TERPISAH DAN TIDAK PERNAH DIJUMLAHKAN.
    // Tidak ada satu pun variabel di bawah yang menjumlahkan $bunch dan $kg.
    // ---------------------------------------------------------------
    $bunch = $summary['bunch'] ?? null;
    $kg = $summary['kg'] ?? null;
    $daily = $summary['daily'] ?? [];
    $dailyTotal = $summary['daily_total'] ?? null;
    $completeness = $summary['completeness'] ?? null;
    $loadCount = $summary['load_count'] ?? 0;

    // Konfigurasi kedua tabel parameter. Satu @foreach atas DUA kelompok,
    // bukan satu tabel atas gabungan keduanya: strukturnya sendiri yang
    // mencegah terbentuknya kolom kuantitas gabungan.
    $kelompokParameter = [
        [
            'uom' => 'bunch',
            'blok' => $bunch,
            'judul' => 'Parameter Mutu — Satuan Janjang',
            'satuan' => 'janjang',
            'testid' => 'parameter-bunch',
            'hint' => 'tiga belas parameter kematangan, dihitung dalam janjang',
        ],
        [
            'uom' => 'kg',
            'blok' => $kg,
            'judul' => 'Parameter Mutu — Satuan Kilogram',
            'satuan' => 'kg',
            'testid' => 'parameter-kg',
            'hint' => 'tiga parameter brondolan, ditimbang dalam kilogram',
        ],
    ];

    // "Ada data" = ada muatan pada periode+line. Dipakai hanya untuk
    // memutuskan apakah tabel dan rekap digambar: tabel kosong akan terbaca
    // sebagai komposisi nol yang terukur, padahal tidak ada yang diukur.
    $hasData = $loadCount > 0;

    // Persen hari ber-muatan. Penyebutnya days_counted (berhenti di HARI INI
    // untuk periode berjalan), dan ia bisa 0 untuk periode yang belum mulai —
    // dalam keadaan itu jawabannya '—', BUKAN '0,0%'.
    $hariPersen = null;

    if ($completeness !== null && (int) $completeness['days_counted'] > 0) {
        $hariPersen = 100 * (int) $completeness['days_with_load'] / (int) $completeness['days_counted'];
    }
@endphp

<div class="md" data-testid="laporan-grading">

    {{-- ============ 1. Hero: periode yang sedang dibaca ============ --}}
    <section class="md-hero" data-testid="report-hero">
        <div class="md-hero__main">
            <p class="md-hero__eyebrow">Laporan Periode &middot; Stasiun Grading</p>
            <h1 class="md-hero__title">{{ $selectedPeriod['name'] ?? 'Laporan Grading' }}</h1>
            <p class="md-hero__subtitle">
                @if ($selectedPeriod)
                    {{ $summary['business_unit']['name'] ?? $businessUnitName }}
                    @if ($selectedProductionLine) &middot; {{ $selectedProductionLine['name'] }} @endif
                    &middot; {{ $tgl($selectedPeriod['start_date']) }} &ndash; {{ $tgl($selectedPeriod['end_date']) }}
                @else
                    Komposisi mutu panen sepanjang satu Periode Pelaporan, dipisah per satuan dan tidak pernah dijumlahkan
                @endif
            </p>
        </div>
        @if ($selectedPeriod)
            <div class="md-hero__meta">
                <span class="md-chip md-chip--date" data-testid="hero-range">
                    {{ $tgl($selectedPeriod['start_date']) }} &ndash; {{ $tgl($selectedPeriod['end_date']) }}
                </span>
                @if ($selectedProductionLine)
                    <span class="md-chip md-chip--date" data-testid="hero-production-line">
                        {{ $selectedProductionLine['name'] }}
                    </span>
                @endif
                {{-- Status periode ditampilkan, tetapi TIDAK membatasi apa pun:
                     periode tertutup tetap dapat dibaca dan diekspor penuh. --}}
                <span class="md-chip md-chip--status {{ $selectedPeriod['status'] === 'closed' ? 'md-chip--closed' : '' }} {{ $selectedPeriod['status'] === 'draft' ? 'md-chip--draft' : '' }}"
                      data-testid="period-status-badge">
                    {{ $statusLabel($selectedPeriod['status']) }}
                </span>
            </div>
        @endif
    </section>

    {{-- ============ 2. Baris filter ============ --}}
    <x-report-filter-bar
        :is-admin="$isAdmin"
        :business-unit-options="$businessUnitOptions"
        :business-unit-id="$businessUnitId"
        mill-testid="mill-select"
        :mill-name="$hasNoMillForAccount ? null : $businessUnitName"
        mill-name-testid="mill-name"
        :show-line="! $needsMillSelection && ! $hasNoMillForAccount"
        :production-line-options="$productionLineOptions"
        :selected-line-id="$selectedProductionLine['id'] ?? null"
        :selected-line-name="$selectedProductionLine['name'] ?? null"
        :show-period="! $needsMillSelection && ! $hasNoMillForAccount"
        :periods="$periods"
        :period-id="$selectedPeriod['id'] ?? null"
        :selected-period-status="$selectedPeriod['status'] ?? null"
        period-testid="period-select"
        :period-placeholder-when-empty="false"
        :export-action="$summary !== null ? 'exportCsv' : null"
        export-csv-testid="export-button"
        export-excel-testid="export-excel-button">
        Hanya periode yang mencakup Grading yang ditampilkan. Angka disaring menurut Production Line yang melekat pada muatan itu sendiri, dan keanggotaan periode mengikuti tanggal penyortiran.
    </x-report-filter-bar>

    <div class="md-body ld-region" wire:loading.delay.short.class="ld-region--busy" wire:loading.delay.short.attr="aria-busy" wire:target="businessUnitId,productionLineId,periodId">
    @if ($hasNoMillForAccount)
        {{-- Empty state (a): akun terikat mill tetapi users.business_unit_id
             kosong. GAGAL TERTUTUP — pemilih Mill TIDAK ditawarkan sebagai
             gantinya. --}}
        <div class="md-empty" data-testid="no-mill-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
            </span>
            <p class="md-empty__title">Akun Anda belum terhubung ke mill</p>
            <p class="md-empty__text">
                Laporan ini selalu dibaca dalam konteks satu mill, sedangkan akun Anda belum terhubung
                ke mill mana pun. Hubungi Admin untuk menghubungkan akun Anda ke mill yang benar.
                Daftar seluruh mill sengaja tidak ditawarkan di sini.
            </p>
        </div>
    @elseif ($needsMillSelection)
        {{-- Empty state (b): Admin belum memilih mill. --}}
        <div class="md-empty" data-testid="mill-select-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
            </span>
            <p class="md-empty__title">Pilih mill terlebih dahulu</p>
            <p class="md-empty__text">
                Sebagai Admin Anda tidak terikat pada satu mill. Pilih mill pada pemilih di atas untuk
                menampilkan daftar Production Line, daftar Periode Pelaporan, dan laporan Grading-nya.
            </p>
        </div>
    @elseif ($forbidden)
        {{-- Empty state (c): period_id yang diminta bukan milik mill ini.
             Ditolak SECARA TERLIHAT dan tanpa satu angka pun dari mill lain. --}}
        <div class="md-empty" data-testid="forbidden-notice">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/></svg>
            </span>
            <p class="md-empty__title">Anda tidak memiliki akses ke periode ini</p>
            <p class="md-empty__text">
                Periode Pelaporan yang diminta bukan milik mill Anda, jadi tidak ada satu angka pun
                yang ditampilkan. Pilih periode dari daftar di atas untuk melanjutkan.
            </p>
        </div>
    @elseif ($needsProductionLineSelection)
        {{-- Mill sudah pasti, production line belum. Tidak ada satu angka pun,
             dan TIDAK ADA angka seluruh mill sebagai gantinya. --}}
        <div class="md-empty" data-testid="select-production-line-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h16"/><circle cx="8" cy="7" r="1.6"/><circle cx="14" cy="12" r="1.6"/><circle cx="10" cy="17" r="1.6"/></svg>
            </span>
            <p class="md-empty__title">Pilih production line terlebih dahulu</p>
            <p class="md-empty__text">
                Laporan Grading menghasilkan angka gabungan per line, dan mencampur beberapa production
                line membuat angkanya tidak bisa ditindaklanjuti. Pilih satu production line pada pemilih
                di atas untuk menampilkan laporannya. Selama belum dipilih, layar ini tidak menampilkan
                satu angka pun &mdash; termasuk tidak menampilkan angka seluruh mill sebagai
                penggantinya.
            </p>
        </div>
    @elseif ($periods === [])
        {{-- Empty state (d): mill belum punya Periode Pelaporan yang mencakup
             Grading. Bukan 404 — cukup arahkan ke layar yang membuatnya. --}}
        <div class="md-empty" data-testid="no-period-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
            </span>
            <p class="md-empty__title">Belum ada Periode Pelaporan</p>
            <p class="md-empty__text">
                Mill ini belum memiliki Periode Pelaporan yang mencakup stasiun Grading. Hubungi Admin
                agar membuatnya terlebih dahulu di layar Kelola Periode Pelaporan, lalu laporan periode
                akan tampil di sini.
            </p>
        </div>
    @elseif ($summary !== null)

        {{-- ============ 3. Peringatan pokok ============ --}}
        <div class="md-explain" data-testid="no-sum-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
            <span>
                Laporan ini meringkas <b>mutu panen</b>, bukan throughput: satu baris = satu muatan truk
                yang disortir, dan yang dicari pembaca adalah komposisi kematangannya. Karena itu rekap
                parameter dipisah menjadi <b>dua kelompok satuan yang tidak pernah dijumlahkan</b>
                &mdash; tiga belas parameter kematangan dihitung dalam <b>janjang</b>, tiga parameter
                brondolan ditimbang dalam <b>kilogram</b>. Jumlah janjang dan kilogram bukan bilangan apa
                pun, jadi layar ini <b>tidak menyediakan satu pun angka gabungan keduanya</b>: dua tabel
                terpisah, dua total, dan dua penyebut pangsa.
                <small>
                    <b>Persentase pada laporan ini DIBACA, tidak dihitung ulang.</b> Nilainya ditetapkan
                    layar input saat penyimpanan &mdash; berbasis netto untuk satuan kilogram, berbasis
                    jumlah janjang untuk satuan janjang &mdash; dan diteruskan apa adanya, supaya angka
                    di sini tidak pernah berbeda dari angka yang dilihat petugas yang menyortir.
                </small>
            </span>
        </div>

        {{-- ============ 4. Angka utama muatan ============ --}}
        <section class="md-kpis md-kpis--3" data-testid="headline-kpis">
            <article class="md-kpi" data-testid="kpi-load-count">
                <div class="md-kpi__top">
                    <span class="md-kpi__label">Jumlah Muatan Disortir</span>
                    <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17h4V5H2v12h3"/><path d="M14 9h4l3 3v5h-2"/><circle cx="7.5" cy="17.5" r="2"/><circle cx="17.5" cy="17.5" r="2"/></svg></span>
                </div>
                <p class="md-kpi__value">{{ $cacah($loadCount) }} <span>muatan</span></p>
                <p class="md-kpi__meta">seluruh muatan yang disortir pada periode dan line ini</p>
                <p class="md-kpi__foot">termasuk muatan draft dan muatan yang belum punya satu pun baris parameter</p>
            </article>
            <article class="md-kpi" data-testid="kpi-netto-total">
                <div class="md-kpi__top">
                    <span class="md-kpi__label">Total Netto</span>
                    <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v4M6 7h12l2 12H4L6 7z"/></svg></span>
                </div>
                <p class="md-kpi__value">{{ $nilai($summary['netto_total'], 2) }} <span>kg</span></p>
                <p class="md-kpi__meta">rata-rata <b data-testid="kpi-netto-avg">{{ $nilai($summary['netto_avg'], 2) }}</b> kg per muatan</p>
                <p class="md-kpi__foot">penyebut rata-rata = jumlah muatan ({{ $cacah($loadCount) }}); netto selalu terisi pada tiap muatan, jadi tidak ada penyebut tersendiri</p>
            </article>
            <article class="md-kpi" data-testid="kpi-bunch-total">
                <div class="md-kpi__top">
                    <span class="md-kpi__label">Total Jumlah Janjang</span>
                    <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M7 12h10"/></svg></span>
                </div>
                <p class="md-kpi__value">{{ $nilai($summary['bunch_total'], 0) }} <span>janjang</span></p>
                <p class="md-kpi__meta">rata-rata <b data-testid="kpi-bunch-avg">{{ $nilai($summary['bunch_avg'], 2) }}</b> janjang per muatan</p>
                <p class="md-kpi__foot">angka pada header muatan &mdash; bukan jumlah baris parameter ber-satuan janjang di bawah</p>
            </article>
        </section>

        @if (! $hasData)
            {{-- Empty state (e): periode + line valid tetapi tanpa satu muatan
                 pun. Seluruh angka di atas sudah tampil sebagai "tidak
                 tersedia" (bukan nol); kedua tabel parameter, rekap per asal
                 dan rekap harian TIDAK digambar — tabel kosong akan terbaca
                 sebagai komposisi nol yang terukur. --}}
            <div class="md-empty" data-testid="empty-state">
                <span class="md-empty__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19h16M7 16V9M12 16V5M17 16v-4"/></svg>
                </span>
                <p class="md-empty__title">Belum ada data pada periode dan line ini</p>
                <p class="md-empty__text">
                    Tidak ada satu pun muatan Grading pada rentang periode ini di
                    {{ $selectedProductionLine['name'] ?? 'production line ini' }}, sehingga seluruh total
                    dan rata-rata ditampilkan sebagai tidak tersedia &mdash; bukan sebagai nol &mdash; dan
                    kedua tabel parameter, rekap per estate/supplier serta rekap harian tidak digambar.
                </p>
            </div>
        @else

            {{-- ============ 5. Dua tabel parameter, satu per satuan ============
                 SATU @foreach ATAS DUA KELOMPOK, bukan satu tabel atas
                 gabungan keduanya: strukturnya sendiri yang mencegah
                 terbentuknya kolom kuantitas gabungan. --}}
            @foreach ($kelompokParameter as $kel)
                @php $blok = $kel['blok']; @endphp
                <section class="md-card" data-testid="{{ $kel['testid'] }}">
                    <header class="md-card__head">
                        <h3>{{ $kel['judul'] }}</h3>
                        <span class="md-card__hint">{{ $kel['hint'] }} &middot; pangsa dihitung terhadap total kelompok ini sendiri, tidak pernah terhadap kelompok satuan yang lain</span>
                    </header>

                    <p class="md-card__hint" data-testid="{{ $kel['testid'] }}-total">
                        Total kelompok ini: <b>{{ $nilai($blok['quantity_total'] ?? null, 2) }}</b> {{ $kel['satuan'] }}
                        dari <b>{{ $cacah($blok['parameter_count'] ?? 0) }}</b> parameter yang muncul pada periode ini.
                    </p>

                    <div class="md-recap">
                        <table class="md-table" data-testid="{{ $kel['testid'] }}-table">
                            <thead>
                                <tr>
                                    <th scope="col">Parameter mutu</th>
                                    <th scope="col">Kuantitas ({{ $kel['satuan'] }})</th>
                                    <th scope="col">Pangsa</th>
                                    <th scope="col">Rata-rata %</th>
                                    <th scope="col">Penyebut rata-rata</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($blok['rows'] ?? [] as $row)
                                    <tr data-testid="{{ $kel['testid'] }}-row">
                                        <td>{{ $row['name'] }}</td>
                                        <td>{{ $nilai($row['quantity_total'], 2) }}</td>
                                        <td @class(['is-muted' => $row['share_percent'] === null])>{{ $persen($row['share_percent']) }}</td>
                                        <td @class(['is-muted' => $row['avg_percentage'] === null])>{{ $persen($row['avg_percentage']) }}</td>
                                        {{-- PENYEBUT RATA-RATA, dicetak di sebelah
                                             rata-ratanya: muatan yang tidak
                                             mencantumkan parameter ini tidak
                                             menilainya nol, ia tidak menilainya
                                             sama sekali. --}}
                                        <td class="is-muted">{{ $cacah($row['load_count']) }} dari {{ $cacah($loadCount) }} muatan</td>
                                    </tr>
                                @empty
                                    <tr data-testid="{{ $kel['testid'] }}-empty">
                                        <td colspan="5" class="is-muted">
                                            Tidak ada satu pun baris parameter ber-satuan {{ $kel['satuan'] }} pada periode dan
                                            line ini. Kelompok ini tetap ditampilkan &mdash; bagian yang hilang akan terbaca
                                            sebagai &ldquo;tidak ada bagian ini&rdquo;, padahal yang benar adalah
                                            &ldquo;tidak ada isinya&rdquo;.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if (($blok['rows'] ?? []) !== [])
                                <tfoot>
                                    <tr data-testid="{{ $kel['testid'] }}-row-total">
                                        <td>Total {{ $kel['satuan'] }}</td>
                                        <td>{{ $nilai($blok['quantity_total'], 2) }}</td>
                                        <td>100,00%</td>
                                        {{-- SENGAJA KOSONG: rata-rata persentase
                                             tiap parameter punya penyebutnya
                                             sendiri, jadi tidak ada satu pun
                                             rata-rata kelompok yang sah untuk
                                             diletakkan di sini. --}}
                                        <td class="is-muted">&mdash;</td>
                                        <td class="is-muted">&mdash;</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </section>
            @endforeach

            {{-- Keterangan yang membuat kedua angka per parameter dapat
                 dibaca. Tanpa ini keduanya hanya terlihat saling membantah. --}}
            <div class="md-explain" data-testid="parameter-share-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                <span>
                    <b>Pangsa</b> dan <b>Rata-rata %</b> menjawab dua pertanyaan yang berbeda, dan
                    keduanya memang dapat berbeda jauh &mdash; itu bukan ketidakcocokan.
                    <b>Pangsa</b> membandingkan kuantitas parameter terhadap total periode, jadi ia
                    <b>berbobot menurut besar muatan</b>: satu muatan raksasa menentukan banyak hal.
                    <b>Rata-rata %</b> adalah rata-rata persentase per muatan, jadi <b>tiap muatan
                    berbobot sama</b>, sebesar apa pun ia. Satu muatan besar yang buruk mendominasi
                    pangsa tanpa menggerakkan rata-rata; selusin muatan kecil yang buruk menggerakkan
                    rata-rata tanpa menggerakkan pangsa.
                    <small>
                        Penyebut <b>Rata-rata %</b> adalah jumlah muatan yang <b>benar-benar mencatat</b>
                        parameter itu &mdash; dicetak pada kolom terakhir &mdash; bukan jumlah seluruh
                        muatan periode. Muatan yang tidak mencantumkan sebuah parameter TIDAK menilainya
                        nol persen; ia tidak menilainya sama sekali, dan memperlakukannya sebagai nol akan
                        menerbitkan penilaian yang tidak pernah dibuat petugas.
                        Tidak ada nilai yang ditandai di luar batas di layar ini &mdash; Grading tidak
                        punya master target mutu, dan menurunkan ambang dari data periode itu sendiri
                        berisiko dibaca sebagai batas resmi padahal bukan.
                    </small>
                </span>
            </div>

            {{-- ============ 6. Rekap per estate/supplier ============ --}}
            <section class="md-card" data-testid="by-estate-supplier">
                <header class="md-card__head">
                    <h3>Rekap per Estate/Supplier</h3>
                    <span class="md-card__hint">diurutkan dari netto terbesar &middot; seluruh asal ditampilkan, tidak dipangkas dan tanpa kelompok &ldquo;lain-lain&rdquo;</span>
                </header>
                <div class="md-recap">
                    <table class="md-table" data-testid="by-estate-supplier-table">
                        <thead>
                            <tr>
                                <th scope="col">Asal (estate/supplier)</th>
                                <th scope="col">Muatan</th>
                                <th scope="col">Netto (kg)</th>
                                <th scope="col">Jumlah janjang</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($summary['by_estate_supplier'] as $row)
                                <tr data-testid="by-estate-supplier-row">
                                    {{-- Asal yang belum diisi adalah KELOMPOK
                                         tersendiri, bukan baris yang dibuang:
                                         membuangnya membuat kolom muatan tidak
                                         lagi menjumlah ke angka utama. --}}
                                    <td>{{ $row['estate_supplier'] === '' ? 'tidak tercatat' : $row['estate_supplier'] }}</td>
                                    <td>{{ $cacah($row['load_count']) }}</td>
                                    <td>{{ $nilai($row['netto_total'], 2) }}</td>
                                    <td>{{ $nilai($row['bunch_total'], 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr data-testid="by-estate-supplier-row-total">
                                <td>Total periode</td>
                                <td>{{ $cacah($loadCount) }}</td>
                                <td>{{ $nilai($summary['netto_total'], 2) }}</td>
                                <td>{{ $nilai($summary['bunch_total'], 0) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            {{-- ============ 7. Kelengkapan pencatatan ============
                 Bagian laporan yang setara dengan angka utama, bukan catatan
                 kaki: pembaca memakainya untuk menilai seberapa jauh angka di
                 atas dapat diandalkan. --}}
            <section class="md-card" data-testid="completeness">
                <header class="md-card__head">
                    <h3>Kelengkapan Pencatatan</h3>
                    <span class="md-card__hint">penyingkapan, bukan penyaring &mdash; tidak satu pun angka di atas disaring oleh angka-angka di sini</span>
                </header>
                <div class="md-kpis md-kpis--3">
                    <article class="md-kpi" data-testid="completeness-days">
                        <div class="md-kpi__top">
                            <span class="md-kpi__label">Hari Ber-muatan</span>
                        </div>
                        <p class="md-kpi__value">
                            {{ $cacah($completeness['days_with_load']) }} <span>dari {{ $cacah($completeness['days_counted']) }} hari</span>
                        </p>
                        <p class="md-kpi__meta" data-testid="completeness-percent">
                            {{ $hariPersen === null ? '—' : number_format($hariPersen, 1, ',', '.').'%' }}
                        </p>
                        @if ($completeness['period_running'])
                            <p class="md-kpi__foot" data-testid="completeness-running-note">
                                Dihitung sampai hari ini, periode masih berjalan
                                ({{ $cacah($completeness['days_counted']) }} dari
                                {{ $cacah($completeness['days_in_period']) }} hari periode sudah lewat).
                            </p>
                        @elseif ((int) $completeness['days_counted'] === 0)
                            <p class="md-kpi__foot" data-testid="completeness-not-started-note">
                                Periode belum mulai, sehingga belum ada hari yang dapat dijadikan pembagi
                                &mdash; persennya &ldquo;&mdash;&rdquo;, bukan 0%.
                            </p>
                        @else
                            <p class="md-kpi__foot">Seluruh {{ $cacah($completeness['days_in_period']) }} hari periode sudah lewat.</p>
                        @endif
                    </article>
                    <article class="md-kpi" data-testid="loads-without-detail">
                        <div class="md-kpi__top">
                            <span class="md-kpi__label">Muatan Tanpa Baris Parameter</span>
                        </div>
                        <p class="md-kpi__value">{{ $cacah($summary['loads_without_detail']) }} <span>muatan</span></p>
                        <p class="md-kpi__meta">tercatat dan ditimbang, tetapi belum dinilai sama sekali</p>
                        <p class="md-kpi__foot">
                            Muatan ini <b>ikut</b> jumlah muatan, netto, dan jumlah janjang, tetapi
                            <b>tidak menyumbang apa pun</b> pada kedua tabel parameter &mdash; angka ini
                            satu-satunya cara menjelaskan selisih antara keduanya.
                        </p>
                    </article>
                    <article class="md-kpi" data-testid="draft-load-count">
                        <div class="md-kpi__top">
                            <span class="md-kpi__label">Muatan Draft</span>
                        </div>
                        <p class="md-kpi__value">{{ $cacah($summary['draft_load_count']) }} <span>muatan</span></p>
                        <p class="md-kpi__meta">dari {{ $cacah($loadCount) }} muatan pada periode dan line ini</p>
                        <p class="md-kpi__foot">Muatan draft <b>IKUT terhitung</b> pada seluruh angka di atas; jumlahnya dinyatakan di sini supaya terlihat seberapa besar laporan ini berdiri di atas data yang belum selesai.</p>
                    </article>
                </div>
                <div class="md-kpis md-kpis--3">
                    <article class="md-kpi" data-testid="loads-without-division">
                        <div class="md-kpi__top">
                            <span class="md-kpi__label">Muatan Tanpa Divisi</span>
                        </div>
                        <p class="md-kpi__value">{{ $cacah($summary['loads_without_division']) }} <span>muatan</span></p>
                        <p class="md-kpi__foot">divisi adalah kolom opsional; muatannya tetap terhitung penuh</p>
                    </article>
                    <article class="md-kpi" data-testid="loads-not-checked">
                        <div class="md-kpi__top">
                            <span class="md-kpi__label">Belum Diperiksa</span>
                        </div>
                        <p class="md-kpi__value">{{ $cacah($summary['loads_not_checked']) }} <span>muatan</span></p>
                        <p class="md-kpi__foot">status verifikasi adalah <b>kelengkapan, bukan penyaring</b> &mdash; muatan ini tetap terhitung penuh</p>
                    </article>
                    <article class="md-kpi" data-testid="loads-not-acknowledged">
                        <div class="md-kpi__top">
                            <span class="md-kpi__label">Belum Disahkan</span>
                        </div>
                        <p class="md-kpi__value">{{ $cacah($summary['loads_not_acknowledged']) }} <span>muatan</span></p>
                        <p class="md-kpi__foot">idem &mdash; dinyatakan supaya pembaca tahu seberapa jauh angka laporan sudah melewati pemeriksaan</p>
                    </article>
                </div>
                <div class="md-explain" data-testid="completeness-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                    <span>
                        <b>Tidak ada penghitung &ldquo;muatan tanpa tanggal&rdquo; di layar ini, dan
                        ketiadaannya disengaja.</b> Tanggal muatan Grading adalah kolom wajib, jadi setiap
                        muatan selalu dapat ditempatkan pada sebuah periode &mdash; berbeda dari laporan
                        Weighbridge, yang punya penghitung itu justru karena penanda waktunya boleh
                        kosong.
                    </span>
                </div>
            </section>

            {{-- ============ 8. Rekap harian (dapat dibuka/tutup) ============
                 Periode panjang menghasilkan puluhan baris, jadi tabelnya dapat
                 ditutup agar angka utama dan kedua tabel parameter tetap
                 terbaca. Tombol, bukan <details> bawaan: keadaannya harus satu
                 sumber (properti Livewire) agar tabel benar-benar hilang dari
                 DOM saat ditutup. Membuka atau menutupnya TIDAK mengubah satu
                 angka pun di halaman ini. --}}
            <section class="md-card" data-testid="daily-recap-card">
                <header class="md-card__head">
                    <h3>Rekap Harian</h3>
                    <button type="button" class="md-btn"
                            wire:click="toggleDailyRecap" data-testid="daily-toggle">
                        {{ $dailyRecapOpen ? 'Tutup rekap harian' : 'Buka rekap harian' }}
                        <small>({{ count($daily) }} baris)</small>
                    </button>
                </header>
                @if ($dailyRecapOpen)
                    <div class="md-recap">
                        <table class="md-table" data-testid="daily-table">
                            <thead>
                                <tr>
                                    <th scope="col">Tanggal</th>
                                    <th scope="col">Muatan</th>
                                    <th scope="col">Netto (kg)</th>
                                    <th scope="col">Jumlah janjang</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Tanggal tanpa muatan sama sekali TIDAK
                                     mendapat baris: baris nol akan terbaca
                                     sebagai "terukur nol". --}}
                                @foreach ($daily as $row)
                                    <tr data-testid="daily-row-{{ $row['date'] }}">
                                        <td>{{ $tgl($row['date']) }}</td>
                                        <td>{{ $cacah($row['load_count']) }}</td>
                                        <td>{{ $nilai($row['netto_total'], 2) }}</td>
                                        <td>{{ $nilai($row['bunch_total'], 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr data-testid="daily-row-total">
                                    <td>Total periode</td>
                                    <td>{{ $cacah($dailyTotal['load_count']) }}</td>
                                    <td>{{ $nilai($dailyTotal['netto_total'], 2) }}</td>
                                    <td>{{ $nilai($dailyTotal['bunch_total'], 0) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </section>
        @endif
    @endif
    </div>
</div>
