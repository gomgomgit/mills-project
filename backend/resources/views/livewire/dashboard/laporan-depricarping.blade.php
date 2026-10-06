{{--
    Laporan Depricarping (web) — screen-152--laporan-depricarping-web.

    APA YANG DILAPORKAN HALAMAN INI: KONDISI OPERASI, bukan output. Satu
    record = satu hari kerja satu presser; satu baris = satu slot waktu
    dengan TUJUH kolom ukur. Yang dicari pembaca bukan total melainkan
    KESTABILAN terhadap rentang targetnya, sehingga intinya adalah
    min/rata-rata/maks per parameter.

    ENAM HAL YANG MENENTUKAN TATA LETAKNYA:

    1. CAKUPAN PENCATATAN DIRENDER PALING ATAS, lengkap dengan ketiga angka
       pembentuk penyebutnya (presser yang benar-benar beroperasi, hari yang
       dihitung, 24 slot kanonis per hari). Periode yang terisi seperlima pun
       menghasilkan rata-rata yang terlihat rapi, dan pembaca harus melihat
       itu SEBELUM mempercayainya.

    2. SETIAP RATA-RATA MEMBAWA PENYEBUTNYA SENDIRI. Ketujuh kolom ukur
       nullable dan terisi saling bebas, jadi tiap baris mencetak jumlah slot
       yang membentuk angkanya. Slot yang tidak mencatat sebuah kolom TIDAK
       mengukurnya nol — ia tidak mengukurnya sama sekali. Jumlah slot terisi
       pada cakupan pun dapat LEBIH BESAR daripada penyebut kolom mana pun,
       karena slot dihitung terisi bila salah satu dari SEMBILAN kolom bacaan
       terisi — ketujuh kolom ukur, menit downtime, atau temuan. Itu benar,
       bukan ketidaksesuaian.

    3. TIGA KOLOM TARGET, TIGA PERTANYAAN YANG BERBEDA, KETIGANYA DI BARIS
       YANG SAMA. `target_range` menjawab "ke mana seharusnya"
       ("40 - 50 mmH2O"); `critical_limit` menjawab "kapan sudah terlalu
       jauh" ("< 35 or > 55 mmH2O"); `operational_consequence_justification`
       menjawab "apa yang dipertaruhkan" ("Direct operational revenue loss").
       Kolom ketiga itu TIDAK ADA pada master Threshing maupun Pressing,
       sehingga tabel di sini DELAPAN kolom — satu lebih banyak daripada
       keduanya. Kolom itu yang paling mudah dibuang demi ruang dan paling
       merugikan bila dibuang: tanpa itu tiga parameter yang sama-sama
       melewati batas tampak sama pentingnya. Ketiganya dirender VERBATIM.

    4. DOWNTIME DI SINI ADALAH ANGKA, PADA BLOKNYA SENDIRI — yang pertama di
       seluruh laporan stasiun, karena depricarping_details.downtime_minutes
       adalah kolom INTEGER sementara Threshing dan Pressing hanya punya teks.
       Total menit, jumlah slot yang mencatatnya, dan rata-rata per slot
       PENCATAT. Halaman menyatakan bahwa slot tanpa catatan BUKAN nol menit,
       dan bahwa parameter ini tidak punya standar pada master sama sekali —
       tanpa itu, sel target yang kosong terbaca sebagai master yang belum
       diisi. Bila tidak ada satu pun slot mencatat, blok itu MENYATAKANNYA
       alih-alih mencetak 0 menit, yang akan terbaca seperti "stasiun tidak
       pernah berhenti". Teks bebasnya ada di blok TERPISAH (Temuan): satu
       menjawab berapa lama, satu menjawab apa yang terlihat, dan
       menggabungkannya membuat slot yang punya keduanya terhitung dua kali.

    5. DAN TIDAK ADA SATU NILAI PUN YANG DITANDAI DI LUAR BATAS. Tanpa kartu
       ambang, tanpa warna aman/bahaya, tanpa severity. Di sini sebabnya
       harus paling tajam dari seluruh laporan yang sudah dibuat, karena
       `critical_limit` pada master Depricarping JUSTRU yang paling rapi
       bentuknya: lima dari enam membawa pembanding numerik eksplisit, sebagian
       dua sisi sekaligus ("< 35 or > 55 mmH2O", "< 55°C or > 75°C"). Tiga hal
       yang menahannya: kolomnya VARCHAR yang dapat disunting Admin/Mill
       Management kapan pun, sehingga pengurai yang gagal pada bentuk
       berikutnya akan BERHENTI MEMPERINGATKAN tanpa satu pun galat — dan
       peringatan yang hilang terbaca sebagai "semuanya aman"; bentuk dua sisi
       menuntut pengurai yang berbeda dari bentuk satu sisi dan satuannya ikut
       di dalam teks (mmH2O, %, °C, RPM, m/s); dan satu parameter ARAH
       ANGKANYA SENDIRI belum pasti (butir 6), sehingga menguraikan batasnya
       akan menghasilkan peringatan yang TERBALIK — lebih buruk daripada
       tidak ada peringatan. Kotak keterangannya memakai kelas `.md-explain`,
       BUKAN `.md-threshold`, karena ketiadaan penandaan diasersi MENURUT NAMA
       KELAS pada HTML ter-render — kalimat penjelasnya sendiri memuat frasa
       "di luar batas", jadi asersi atas frasa akan selalu hijau.

    6. SATU STANDAR TAMPIL TANPA ANGKA, DAN SATU ANGKA TAMPIL TANPA STANDAR —
       dengan sengaja, dan inilah temuan yang paling perlu dibaca manusia di
       halaman ini. Master menyebut standarnya "Kernel Loss in Fibre", target
       "< 0.50%" — sebuah KEHILANGAN, makin kecil makin baik. Kolom yang
       tersedia bernama kernel_recovery_in_fibre_percent, dan keempat layar
       input serta detail Depricarping melabelinya "Kernel Recovery in Fibre"
       — sebuah PEROLEHAN. Dua pembingkaian yang BERLAWANAN atas kuantitas
       yang sama, dan tidak ada apa pun di sistem yang menyelesaikannya
       (kolom itu belum pernah terisi satu nilai pun). Jadi angkanya tercetak
       di bawah label kolomnya sendiri dengan keterangan bahwa standarnya
       tidak dipasangkan, dan standarnya tercetak pada bagian
       standar-tanpa-pengukuran beserta alasannya. Memasangkannya akan
       membuat halaman ini menilai dengan arah TERBALIK tanpa ada yang
       menyadarinya.

       Bagian standar-tanpa-pengukuran itu DIGAMBAR WALAU KOSONG, dengan
       keterangannya sendiri: bagian yang hilang ketika kosong tidak dapat
       dibedakan dari bagian yang belum pernah dibuat.

    SATU STANDAR DAPAT MENGATUR DUA KOLOM, dan halaman ini menyatakannya.
    Master hanya memuat satu baris "Nut Silo Temperature" sementara tabel
    detail punya nut_silo_1_temp_c dan nut_silo_2_temp_c. Keduanya tetap dua
    baris dengan penyebut masing-masing — dua silo fisik, dan
    merata-ratakannya akan menyembunyikan silo yang menyimpang di belakang
    silo yang normal — tetapi tanpa keterangan itu standar yang identik
    tercetak dua kali berturut-turut terbaca seperti data yang terduplikasi,
    dan seseorang akan "membersihkannya".

    TEMUAN DIKELOMPOKKAN HARFIAH, dan halaman ini menyatakannya. Dua ejaan
    untuk satu hal muncul sebagai dua baris; tanpa keterangan itu, hal
    tersebut terbaca sebagai cacat laporan alih-alih sebagai bentuk datanya.

    PERSEN CAKUPAN TANPA PENYEBUT dirender "—", bukan "0,0%": 0% mengklaim
    ada yang diukur dan hasilnya nol.

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

    $coverage = $summary['coverage'] ?? null;
    $metrics = $summary['metrics'] ?? [];
    $targetsWithoutMetric = $summary['targets_without_metric'] ?? [];
    $targetsMasterEmpty = (bool) ($summary['targets_master_empty'] ?? false);
    $byPresser = $summary['by_presser'] ?? [];
    $daily = $summary['daily'] ?? [];
    $dailyTotal = $summary['daily_total'] ?? null;
    $downtime = $summary['downtime'] ?? null;
    $findings = $summary['findings'] ?? [];
    $allTargetsMeasured = (bool) ($summary['all_targets_measured'] ?? false);
    $total = $summary['total'] ?? null;

    // Label kolom untuk keterangan "standarnya satu" pada kedua nut silo.
    // Diambil dari METRIC_LABELS, bukan ditulis ulang di sini, supaya satu
    // perubahan label tidak meninggalkan dua ejaan di layar.
    $labelKolom = fn (string $column) => \App\Services\DepricarpingReportService::METRIC_LABELS[$column]['label'] ?? $column;

    // "Ada data" = ada minimal satu slot terisi. Dipakai hanya untuk
    // memutuskan apakah tabel dan rekap digambar: tabel kosong akan terbaca
    // sebagai hasil pengukuran bernilai nol, padahal tidak ada yang diukur.
    $hasData = (bool) ($summary['has_data'] ?? false);
@endphp

<div class="md" data-testid="laporan-depricarping">

    {{-- ============ 1. Hero: periode yang sedang dibaca ============ --}}
    <section class="md-hero" data-testid="report-hero">
        <div class="md-hero__main">
            <p class="md-hero__eyebrow">Laporan Periode &middot; Stasiun Depricarping</p>
            <h1 class="md-hero__title">{{ $selectedPeriod['name'] ?? 'Laporan Depricarping' }}</h1>
            <p class="md-hero__subtitle">
                @if ($selectedPeriod)
                    {{ $summary['business_unit']['name'] ?? $businessUnitName }}
                    @if ($selectedProductionLine) &middot; {{ $selectedProductionLine['name'] }} @endif
                    &middot; {{ $tgl($selectedPeriod['start_date']) }} &ndash; {{ $tgl($selectedPeriod['end_date']) }}
                @else
                    Kondisi operasi stasiun Depricarping sepanjang satu Periode Pelaporan, dibandingkan dengan standar operasionalnya
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
        Hanya periode yang mencakup Depricarping yang ditampilkan. Angka disaring menurut Production Line yang melekat pada record itu sendiri, dan keanggotaan periode mengikuti tanggal recordnya.
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
                menampilkan daftar Production Line, daftar Periode Pelaporan, dan laporan Depricarping-nya.
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
                Laporan Depricarping menghasilkan angka gabungan per line, dan mencampur beberapa production
                line membuat angkanya tidak bisa ditindaklanjuti. Pilih satu production line pada pemilih
                di atas untuk menampilkan laporannya. Selama belum dipilih, layar ini tidak menampilkan
                satu angka pun &mdash; termasuk tidak menampilkan angka seluruh mill sebagai
                penggantinya.
            </p>
        </div>
    @elseif ($periods === [])
        {{-- Empty state (d): mill belum punya Periode Pelaporan yang mencakup
             Depricarping. Bukan 404 — cukup arahkan ke layar yang membuatnya. --}}
        <div class="md-empty" data-testid="no-period-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
            </span>
            <p class="md-empty__title">Belum ada Periode Pelaporan</p>
            <p class="md-empty__text">
                Mill ini belum memiliki Periode Pelaporan yang mencakup stasiun Depricarping. Hubungi Admin
                agar membuatnya terlebih dahulu di layar Kelola Periode Pelaporan, lalu laporan periode
                akan tampil di sini.
            </p>
        </div>
    @elseif ($summary !== null)

        {{-- ============ 3. Cakupan pencatatan — PALING ATAS ============
             Bukan catatan kaki: periode yang terisi seperlima pun
             menghasilkan rata-rata yang terlihat rapi, jadi pembaca harus
             melihat cakupannya lebih dulu. --}}
        <section class="md-card" data-testid="coverage">
            <header class="md-card__head">
                <h3>Cakupan Pencatatan</h3>
                <span class="md-card__hint">dibaca lebih dulu, sebelum satu angka ukur pun dipercaya</span>
            </header>
            <div class="md-kpis md-kpis--3">
                <article class="md-kpi" data-testid="coverage-slots">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Slot Terisi</span>
                    </div>
                    <p class="md-kpi__value">
                        {{ $cacah($coverage['filled_slots']) }}
                        <span>dari {{ $cacah($coverage['expected_slots']) }} slot</span>
                    </p>
                    {{-- Persen TANPA penyebut dirender '—', bukan 0,0%:
                         0% mengklaim ada yang diukur dan hasilnya nol. --}}
                    <p class="md-kpi__meta" data-testid="coverage-percent">
                        @if ($coverage['coverage_percent'] === null)
                            &mdash;
                        @else
                            {{ $nilai($coverage['coverage_percent'], 1) }}%
                        @endif
                    </p>
                    <p class="md-kpi__foot">slot dihitung terisi bila minimal satu dari enam kolom bacaan diisi &mdash; termasuk bila yang diisi hanya alasan downtime</p>
                </article>
                <article class="md-kpi" data-testid="coverage-denominator">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Pembentuk Penyebut</span>
                    </div>
                    <p class="md-kpi__value">
                        {{ $cacah($coverage['presser_count']) }}
                        <span>presser &times; {{ $cacah($coverage['days_counted']) }} hari &times; {{ $cacah($coverage['slots_per_presser_per_day']) }} slot</span>
                    </p>
                    <p class="md-kpi__foot">jumlah presser adalah yang BENAR-BENAR beroperasi pada periode ini, bukan jumlah stasiun terdaftar; 24 slot kanonis diambil dari grid layar input itu sendiri</p>
                </article>
                <article class="md-kpi" data-testid="coverage-days">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Hari Dihitung</span>
                    </div>
                    <p class="md-kpi__value">
                        {{ $cacah($coverage['days_counted']) }}
                        <span>dari {{ $cacah($coverage['days_in_period']) }} hari periode</span>
                    </p>
                    {{-- URUTANNYA PENTING: days_counted === 0 DIPERIKSA LEBIH
                         DULU. ReportPeriodDays::isRunning() juga true untuk
                         periode yang BELUM MULAI (tanggal akhirnya masih di
                         masa depan), jadi memeriksa period_running lebih dulu
                         akan memberi periode yang belum mulai keterangan
                         "dihitung sampai hari ini" — padahal tidak ada satu
                         hari pun yang dihitung. --}}
                    @if ((int) $coverage['days_counted'] === 0)
                        <p class="md-kpi__meta" data-testid="coverage-not-started-note">
                            periode belum mulai, sehingga belum ada hari yang dapat dijadikan pembagi &mdash; persennya &ldquo;&mdash;&rdquo;, bukan 0%
                        </p>
                    @elseif ($coverage['period_running'])
                        <p class="md-kpi__meta" data-testid="coverage-running-note">
                            dihitung sampai hari ini &mdash; periode masih berjalan, dan hari yang belum terjadi tidak mungkin tercatat
                        </p>
                    @endif
                    <p class="md-kpi__foot">record berstatus draft IKUT terhitung pada seluruh angka laporan ini</p>
                </article>
            </div>
        </section>

        @if (! $hasData)
            {{-- Empty state (e): periode + line valid tetapi tanpa satu slot
                 terisi. Cakupan di atas sudah menampilkannya; tabel parameter,
                 rekap per presser, rekap harian dan daftar downtime TIDAK
                 digambar — tabel kosong akan terbaca sebagai hasil pengukuran
                 bernilai nol. --}}
            <div class="md-empty" data-testid="empty-state">
                <span class="md-empty__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19h16M7 16V9M12 16V5M17 16v-4"/></svg>
                </span>
                <p class="md-empty__title">Belum ada data pada periode dan line ini</p>
                <p class="md-empty__text">
                    Tidak ada satu pun slot Depricarping yang terisi pada rentang periode ini di
                    {{ $selectedProductionLine['name'] ?? 'production line ini' }}, sehingga seluruh
                    min, rata-rata dan maks ditampilkan sebagai tidak tersedia &mdash; bukan sebagai nol
                    &mdash; dan tabel parameter, rekap per presser, rekap harian serta daftar alasan
                    downtime tidak digambar.
                </p>
            </div>
        @else

            {{-- ============ 4. Tabel parameter + standar operasionalnya ============
                 Satu baris memuat angka DAN standarnya DAN rencana tindakannya,
                 karena di situlah penilaian manusia benar-benar terjadi. --}}
            <section class="md-card" data-testid="metrics">
                <header class="md-card__head">
                    <h3>Parameter Operasi &amp; Standarnya</h3>
                    <span class="md-card__hint">min / rata-rata / maks per kolom ukur &middot; tiap angka membawa penyebutnya sendiri &middot; rentang kerja dan batas tindakan berdampingan &middot; tidak ada nilai yang ditandai di luar batas</span>
                </header>
                <div class="md-recap">
                    <table class="md-table" data-testid="metrics-table">
                        <thead>
                            <tr>
                                <th scope="col">Parameter</th>
                                <th scope="col">Min</th>
                                <th scope="col">Rata-rata</th>
                                <th scope="col">Maks</th>
                                <th scope="col">Penyebut</th>
                                {{-- TIGA KOLOM TARGET, tiga pertanyaan yang
                                     berbeda. Kolom ketiga tidak ada pada
                                     master Threshing maupun Pressing, dan
                                     ialah yang paling mudah dibuang demi
                                     ruang serta paling merugikan bila
                                     dibuang: tanpa itu tiga parameter yang
                                     sama-sama melewati batas tampak sama
                                     pentingnya. --}}
                                <th scope="col">Rentang target</th>
                                <th scope="col">Batas kritis</th>
                                <th scope="col">Akibat bila dilewati</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Baris parameter TANPA satu pun pembacaan TETAP
                                 dirender: baris yang hilang terbaca sebagai
                                 "tidak ada parameter ini", padahal yang benar
                                 adalah "tidak ada yang mengukurnya". --}}
                            @foreach ($metrics as $metric)
                                <tr data-testid="metric-row">
                                    <td>{{ $metric['label'] }} <small class="is-muted">({{ $metric['unit'] }})</small></td>
                                    <td @class(['is-muted' => $metric['min'] === null])>{{ $nilai($metric['min'], 2) }}</td>
                                    <td @class(['is-muted' => $metric['avg'] === null])>{{ $nilai($metric['avg'], 2) }}</td>
                                    <td @class(['is-muted' => $metric['max'] === null])>{{ $nilai($metric['max'], 2) }}</td>
                                    {{-- PENYEBUT, dicetak di sebelah angkanya:
                                         slot yang tidak mencatat kolom ini tidak
                                         mengukurnya nol, ia tidak mengukurnya
                                         sama sekali. --}}
                                    <td class="is-muted" data-testid="metric-denominator">
                                        {{ $cacah($metric['filled_slot_count']) }} dari {{ $cacah($coverage['filled_slots']) }} slot
                                    </td>
                                    {{-- Ketiganya VERBATIM. --}}
                                    <td @class(['is-muted' => $metric['target']['target_range'] === null])
                                        data-testid="metric-target-range">
                                        @if ($metric['target']['unmapped_reason'] !== null)
                                            {{-- SENGAJA TIDAK DIPASANGKAN. Master
                                                 menyebut standarnya "Kernel Loss in
                                                 Fibre" (sebuah KEHILANGAN, target
                                                 "< 0.50%") sementara kolom ini dan
                                                 keempat layar input Depricarping
                                                 melabelinya PEROLEHAN. Memasangkannya
                                                 akan membuat layar ini menilai dengan
                                                 arah yang terbalik. --}}
                                            standar tidak dipasangkan &mdash; lihat catatan di bawah
                                        @else
                                            {{ $metric['target']['target_range'] ?? 'rentang target belum terisi pada master' }}
                                        @endif
                                    </td>
                                    <td @class(['is-muted' => $metric['target']['critical_limit'] === null])
                                        data-testid="metric-critical-limit">
                                        {{ $metric['target']['critical_limit'] ?? 'belum terisi' }}
                                    </td>
                                    <td @class(['is-muted' => $metric['target']['operational_consequence_justification'] === null])
                                        data-testid="metric-consequence">
                                        {{ $metric['target']['operational_consequence_justification'] ?? 'belum terisi' }}
                                    </td>
                                </tr>
                                @if ($metric['target']['shares_standard_with'] !== [])
                                    {{-- SATU STANDAR, DUA KOLOM. Tanpa baris ini
                                         standar yang identik tercetak dua kali
                                         berturut-turut terbaca seperti data yang
                                         terduplikasi, dan seseorang akan
                                         "membersihkannya". --}}
                                    <tr data-testid="metric-shared-standard-row">
                                        <td colspan="8" class="is-muted">
                                            <small data-testid="metric-shared-standard">
                                                Standar baris ini sama dengan
                                                @foreach ($metric['target']['shares_standard_with'] as $sibling)
                                                    <b>{{ $labelKolom($sibling) }}</b>@if (! $loop->last), @endif
                                                @endforeach
                                                &mdash; master hanya memuat satu baris
                                                &ldquo;{{ $metric['target']['parameter_metric'] }}&rdquo; untuk keduanya.
                                                Angkanya tetap dipisah karena keduanya silo yang berbeda.
                                            </small>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Keterangan yang membuat kehadiran standar TANPA penandaan dapat
                 dibaca. Tanpa ini, ketiadaan penandaan terbaca sebagai fitur
                 yang belum selesai. --}}
            <div class="md-explain" data-testid="no-flagging-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
            <span>
                <b>Rentang target, batas kritis, dan akibatnya ditampilkan, tetapi laporan ini tidak
                menilai satu angka pun.</b> Tidak ada warna peringatan, tidak ada severity, dan tidak
                ada penanda di luar batas &mdash; penilaiannya milik Anda, bukan milik layar ini.
                Ketiganya menjawab pertanyaan yang berbeda: <b>rentang target</b> adalah ke mana
                angkanya seharusnya, <b>batas kritis</b> adalah kapan ia sudah terlalu jauh, dan
                <b>akibat bila dilewati</b> adalah apa yang dipertaruhkan. Kolom ketiga itu tidak ada
                pada master Threshing maupun Pressing, dan tanpa itu tiga parameter yang sama-sama
                melewati batasnya tampak sama pentingnya &mdash; padahal tidak.
                <small>
                    <b>Mengapa tidak ditandai otomatis, padahal batas kritis di sini justru yang paling
                    rapi bentuknya.</b> Lima dari enam batas pada master Depricarping membawa pembanding
                    numerik yang jelas (&ldquo;&lt; 35 or &gt; 55 mmH2O&rdquo;, &ldquo;&gt; 1.00%&rdquo;,
                    &ldquo;&lt; 55&deg;C or &gt; 75&deg;C&rdquo;), jadi godaan menguraikannya nyata dan
                    penolakannya perlu beralasan. <b>Pertama</b>, kolom-kolom itu <b>teks bebas</b> yang
                    dapat disunting Admin atau Mill Management kapan pun, jadi pengurai yang gagal pada
                    bentuk berikutnya akan <b>berhenti memperingatkan tanpa satu pun galat</b> &mdash;
                    dan peringatan yang hilang terbaca sebagai &ldquo;semuanya aman&rdquo;, arah
                    kegagalan terburuk untuk indikator mutu. <b>Kedua</b>, bentuk dua sisi menuntut
                    pengurai yang berbeda dari bentuk satu sisi, dan <b>satuannya ikut di dalam
                    teks</b> &mdash; mmH2O, %, &deg;C, RPM, m/s; pengurai yang benar untuk keenamnya
                    hari ini adalah pengurai yang paling mungkin salah besok. <b>Ketiga</b>, satu
                    parameter <b>arah angkanya sendiri belum pasti</b> (lihat catatan di bawah),
                    sehingga menguraikan batasnya akan menghasilkan peringatan yang <b>terbalik</b>
                    &mdash; lebih buruk daripada tidak ada peringatan. Bila penandaan diinginkan, cara
                    yang jujur adalah memecah master menjadi kolom <b>batas numerik</b> (minimum,
                    maksimum, arah pembanding, satuan) di samping teksnya &mdash; bukan menguraikan
                    teksnya saat render.
                    <b>Ketiga kolom target berlaku umum untuk seluruh mill</b> &mdash; master target
                    Depricarping tidak dipecah per mill.
                    <b>Penyebut</b> tiap baris adalah jumlah slot yang benar-benar mencatat kolom itu,
                    dan sengaja dapat berbeda antar baris: ketujuh kolom nullable dan terisi saling
                    bebas, jadi satu penyebut bersama akan salah untuk setidaknya enam di antaranya.
                    Jumlah slot terisi pada cakupan pun dapat <b>lebih besar</b> daripada penyebut
                    kolom mana pun, karena slot dihitung terisi bila salah satu dari <b>sembilan</b>
                    kolom bacaan terisi &mdash; termasuk menit downtime dan temuan.
                </small>
            </span>
        </div>

        {{-- ============ 5. Target tanpa pengukuran ============
                 Dipublikasikan, bukan dibuang: standar yang tidak pernah diukur
                 terbaca seperti terpenuhi padahal ia sekadar tidak ada. --}}
            @if ($targetsMasterEmpty)
                <div class="md-explain" data-testid="targets-master-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                    <span>
                        <b>Master target operasional Depricarping belum terisi</b>, jadi ketiga kolom
                        target pada tabel di atas kosong. Seluruh angka hasil ukur tetap ditampilkan apa
                        adanya &mdash; master yang belum diisi tidak menghapus pengukuran yang sudah
                        terjadi.
                    </span>
                </div>
            @else
                {{-- BAGIAN INI DIGAMBAR WALAU ISINYA KOSONG. Bagian yang hilang
                     ketika kosong tidak dapat dibedakan dari bagian yang belum
                     pernah dibuat &mdash; dan di layar ini justru bagian inilah
                     yang memuat temuan yang paling perlu dibaca manusia. --}}
                <section class="md-card" data-testid="targets-without-metric">
                    <header class="md-card__head">
                        <h3>Standar yang Belum Diukur Sistem</h3>
                        <span class="md-card__hint">ada standarnya, pengukurannya belum dapat dipercaya &middot; ditampilkan sebagai temuan, bukan disembunyikan</span>
                    </header>
                    @if ($allTargetsMeasured)
                        <p class="md-card__hint" data-testid="targets-all-measured">
                            Seluruh standar pada master target Depricarping sudah punya kolom
                            pengukuran yang dipasangkan dengannya. Bagian ini tetap digambar supaya
                            keadaan &ldquo;tidak ada yang tertinggal&rdquo; dapat dibedakan dari
                            bagian yang belum pernah dibuat.
                        </p>
                    @else
                        <div class="md-recap">
                            <table class="md-table" data-testid="targets-without-metric-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Parameter</th>
                                        <th scope="col">Rentang target</th>
                                        <th scope="col">Batas kritis</th>
                                        <th scope="col">Akibat bila dilewati</th>
                                        {{-- ALASANNYA IKUT DICETAK, bukan hanya
                                             namanya: "tidak ada kolomnya" menuntut
                                             kolom baru, sementara "arah kolomnya
                                             belum pasti" menuntut keputusan
                                             penamaan. Dua tindakan yang berbeda. --}}
                                        <th scope="col">Mengapa belum terukur</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($targetsWithoutMetric as $target)
                                        <tr data-testid="targets-without-metric-row">
                                            <td>{{ $target['parameter_metric'] }}</td>
                                            <td>{{ $target['target_range'] }}</td>
                                            <td>{{ $target['critical_limit'] }}</td>
                                            <td>{{ $target['operational_consequence_justification'] }}</td>
                                            <td data-testid="targets-without-metric-reason">
                                                @if ($target['reason'] === \App\Services\DepricarpingReportService::UNMAPPED_DIRECTION_UNRESOLVED)
                                                    Ada kolomnya, <b>arahnya belum pasti</b>
                                                @else
                                                    <b>Tidak ada kolom pengukurannya</b> di skema
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="md-explain" data-testid="targets-without-metric-note">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                            <span>
                                <b>&ldquo;Kernel Loss in Fibre&rdquo; adalah temuan yang perlu
                                diputuskan manusia, bukan cacat laporan ini.</b> Master menyebut
                                standarnya sebuah <b>kehilangan</b> &mdash; target
                                &ldquo;&lt; 0.50%&rdquo;, batas &ldquo;&gt; 1.00%&rdquo;, makin kecil
                                makin baik. Kolom yang tersedia bernama
                                <code>kernel_recovery_in_fibre_percent</code>, dan keempat layar
                                input serta detail Depricarping melabelinya <b>perolehan</b> &mdash;
                                yang lazimnya makin besar makin baik. Dua pembingkaian yang
                                <b>berlawanan arah</b> atas kuantitas yang sama, dan hanya satu yang
                                bisa benar.
                                <small>
                                    Tidak ada apa pun di sistem yang menyelesaikannya: kolom itu
                                    <b>belum pernah terisi satu nilai pun</b>. Karena itu laporan ini
                                    <b>tidak memasangkan</b> angkanya dengan standar tersebut.
                                    Angkanya tetap dilaporkan di bawah label kolomnya sendiri, dan
                                    standarnya tercantum di sini &mdash; <b>laporan tidak pernah
                                    mencetak sebuah angka di bawah standar yang mungkin
                                    kebalikannya</b>, karena menebak akan membuat layar ini menilai
                                    dengan arah yang terbalik tanpa ada yang menyadarinya. Yang perlu
                                    diputuskan: apakah kolom dan labelnya diubah menjadi
                                    <i>loss</i> (bila yang diinput Operator memang kehilangan), atau
                                    standar pada master yang diubah menjadi <i>recovery</i> beserta
                                    rentang yang sesuai. Keduanya perubahan sederhana; yang tidak boleh
                                    dilakukan adalah membiarkannya sambil memasangkan angka dengan
                                    standarnya.
                                    Parameter yang muncul di sini dengan alasan <b>tidak ada kolom
                                    pengukurannya</b> adalah hal yang berbeda &mdash; termasuk bila
                                    nama parameter pada master diubah ejaannya, yang membuat
                                    pasangannya terlepas. Keterlepasan itu <b>sengaja terlihat di
                                    sini</b> alih-alih diam-diam menghapus standar dari tabel di atas.
                                </small>
                            </span>
                        </div>
                    @endif
                </section>
            @endif

            {{-- ============ 6. Rekap per presser ============ --}}
            <section class="md-card" data-testid="by-presser">
                <header class="md-card__head">
                    <h3>Rekap per Presser</h3>
                    <span class="md-card__hint">presser_id adalah NAMA UNIT, bukan kunci baris &middot; id yang sama pada dua tanggal adalah satu presser dengan dua hari pencatatan</span>
                </header>
                <div class="md-recap">
                    <table class="md-table" data-testid="by-presser-table">
                        <thead>
                            <tr>
                                <th scope="col">Presser</th>
                                <th scope="col">Hari</th>
                                <th scope="col">Slot terisi</th>
                                <th scope="col">Downtime (menit)</th>
                                @foreach ($metrics as $metric)
                                    <th scope="col">{{ $metric['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($byPresser as $row)
                                <tr data-testid="by-presser-row">
                                    <td>{{ $row['presser_id'] === '' ? 'Belum diisi' : $row['presser_id'] }}</td>
                                    <td>{{ $cacah($row['day_count']) }}</td>
                                    <td>{{ $cacah($row['filled_slot_count']) }}</td>
                                    {{-- null, BUKAN 0: presser yang tidak satu pun
                                         slotnya mencatat downtime bukan presser yang
                                         tidak pernah berhenti. --}}
                                    <td @class(['is-muted' => $row['downtime_minutes'] === null])
                                        data-testid="by-presser-downtime">
                                        {{ $row['downtime_minutes'] === null ? 'belum dicatat' : $cacah($row['downtime_minutes']) }}
                                    </td>
                                    @foreach ($metrics as $metric)
                                        <td @class(['is-muted' => ($row['averages'][$metric['column']] ?? null) === null])>
                                            {{ $nilai($row['averages'][$metric['column']] ?? null, 2) }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- ============ 7a. Downtime sebagai ANGKA ============
                 Bagian yang tidak punya padanan pada laporan Threshing maupun
                 Pressing: di sana downtime hanya ada sebagai teks, sehingga
                 "berapa lama stasiun berhenti" tidak pernah dapat dijawab.
                 depricarping_details.downtime_minutes adalah kolom INTEGER. --}}
            <section class="md-card" data-testid="downtime">
                <header class="md-card__head">
                    <h3>Downtime Periode</h3>
                    <span class="md-card__hint">dijumlahkan atas slot yang MENCATATNYA saja &middot; slot tanpa catatan bukan nol menit &middot; parameter ini tidak punya standar pada master</span>
                </header>
                @if ($downtime['total_minutes'] === null)
                    <p class="md-card__hint" data-testid="downtime-empty">
                        <b>Belum ada satu slot pun yang mencatat menit downtime</b> pada periode dan
                        line ini. Dinyatakan begini, bukan sebagai total 0 menit &mdash; total 0 menit
                        terbaca seperti &ldquo;stasiun tidak pernah berhenti&rdquo;, padahal yang benar
                        adalah &ldquo;tidak ada yang mencatatnya&rdquo;.
                    </p>
                @else
                    <div class="md-kpis md-kpis--3">
                        <article class="md-kpi" data-testid="downtime-total">
                            <div class="md-kpi__top">
                                <span class="md-kpi__label">Total Berhenti</span>
                            </div>
                            <p class="md-kpi__value">{{ $cacah($downtime['total_minutes']) }} <span>menit</span></p>
                            <p class="md-kpi__foot">dijumlahkan hanya atas slot yang mencatat menit downtime</p>
                        </article>
                        <article class="md-kpi" data-testid="downtime-recorded-slots">
                            <div class="md-kpi__top">
                                <span class="md-kpi__label">Slot yang Mencatat</span>
                            </div>
                            <p class="md-kpi__value">{{ $cacah($downtime['recorded_slot_count']) }} <span>slot</span></p>
                            <p class="md-kpi__foot">dari {{ $cacah($coverage['filled_slots']) }} slot terisi &mdash; inilah <b>penyebut</b> rata-rata di sebelah</p>
                        </article>
                        <article class="md-kpi" data-testid="downtime-average">
                            <div class="md-kpi__top">
                                <span class="md-kpi__label">Rata-rata per Slot Pencatat</span>
                            </div>
                            <p class="md-kpi__value">{{ $nilai($downtime['avg_minutes_per_recorded_slot'], 1) }} <span>menit</span></p>
                            <p class="md-kpi__foot">penyebutnya slot yang <b>mencatat</b>, bukan seluruh slot terisi</p>
                        </article>
                    </div>
                @endif
                <div class="md-explain" data-testid="downtime-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                    <span>
                        <b>Slot yang tidak mencatat menit downtime tidak dihitung sebagai nol
                        menit.</b> Memperlakukannya nol akan membuat rata-ratanya mengecil justru
                        seiring bertambahnya slot yang <i>tidak</i> dicatat &mdash; laporan akan
                        tampak lebih baik karena lebih sedikit yang ditulis. Sebaliknya, nilai
                        <b>0 yang benar-benar tercatat IKUT dihitung</b>, karena nol di situ adalah
                        pernyataan seseorang bahwa stasiun tidak berhenti pada slot itu.
                        <small>
                            <b>Parameter ini tidak punya baris pada master target</b>, jadi tidak ada
                            rentang maupun batas kritis yang dapat ditampilkan bersamanya. Dinyatakan
                            agar tidak terbaca sebagai master yang belum terisi.
                            Total menit baru bermakna dibandingkan antar periode bila
                            <b>proporsi slot yang mencatatnya serupa</b> &mdash; itulah sebabnya
                            jumlah slot pencatat ikut ditampilkan di sebelah totalnya.
                            Slot yang mencatat downtime <b>tetap ikut</b> pada min, rata-rata dan maks
                            bila kolom ukurnya juga terisi; downtime adalah keterangan tambahan pada
                            slot itu, bukan penyaring.
                        </small>
                    </span>
                </div>
            </section>

            {{-- ============ 7b. Rekap temuan ============
                 Paruh teks bebas dari apa yang dua laporan sebelumnya terbitkan
                 sebagai alasan downtime. SENGAJA TERPISAH dari blok di atas:
                 satu menjawab "berapa lama", satu menjawab "apa yang terlihat",
                 dan menggabungkannya membuat slot yang punya keduanya terhitung
                 dua kali pada satu pengertian. --}}
            <section class="md-card" data-testid="findings">
                <header class="md-card__head">
                    <h3>Temuan Lapangan</h3>
                    <span class="md-card__hint">diurutkan dari yang terbanyak &middot; dikelompokkan HARFIAH, tanpa penyeragaman ejaan maupun huruf besar-kecil</span>
                </header>
                @if ($findings === [])
                    <p class="md-card__hint" data-testid="findings-empty">
                        Tidak ada satu pun temuan tercatat pada periode dan line ini. Ini dinyatakan
                        sebagai keterangan, bukan tabel kosong tanpa penjelasan &mdash; tabel kosong
                        tidak membedakan &ldquo;tidak ada temuan&rdquo; dari &ldquo;tidak ada yang
                        mencatatnya&rdquo;.
                    </p>
                @else
                    <div class="md-recap">
                        <table class="md-table" data-testid="findings-table">
                            <thead>
                                <tr>
                                    <th scope="col">Temuan (apa adanya)</th>
                                    <th scope="col">Slot</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($findings as $row)
                                    <tr data-testid="findings-row">
                                        <td>{{ $row['finding'] }}</td>
                                        <td>{{ $cacah($row['slot_count']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="md-explain" data-testid="findings-note">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                        <span>
                            Temuan adalah <b>teks bebas</b>, dan dikelompokkan <b>harfiah</b>: tanpa
                            penyeragaman ejaan dan tanpa penyeragaman huruf besar-kecil. Dua ejaan
                            untuk hal yang sama karena itu muncul sebagai <b>dua baris</b> &mdash; itu
                            bentuk datanya, bukan cacat laporan ini. Menyeragamkannya justru akan
                            menggabungkan hal yang penulisnya memang maksudkan berbeda.
                            <small>
                                Temuan <b>tidak digabungkan</b> dengan menit downtime di atas: satu
                                menjawab berapa lama stasiun berhenti, satu menjawab apa yang terlihat.
                                Satu slot dapat memuat keduanya, dan menggabungkannya akan membuat slot
                                itu terhitung dua kali pada satu pengertian.
                            </small>
                        </span>
                    </div>
                @endif
            </section>

            {{-- ============ 8. Kelengkapan record ============ --}}
            <section class="md-card" data-testid="completeness">
                <header class="md-card__head">
                    <h3>Kelengkapan Record</h3>
                    <span class="md-card__hint">seluruh angka di bawah IKUT terhitung pada laporan &mdash; tidak satu pun menjadi penyaring</span>
                </header>
                <div class="md-kpis md-kpis--3">
                    <article class="md-kpi" data-testid="record-count">
                        <div class="md-kpi__top">
                            <span class="md-kpi__label">Jumlah Record</span>
                        </div>
                        <p class="md-kpi__value">{{ $cacah($total['record_count']) }} <span>record</span></p>
                        <p class="md-kpi__meta">pada <b data-testid="days-with-records">{{ $cacah($total['days_with_records']) }}</b> hari</p>
                        <p class="md-kpi__foot">satu record = satu hari kerja satu presser</p>
                    </article>
                    <article class="md-kpi" data-testid="draft-record-count">
                        <div class="md-kpi__top">
                            <span class="md-kpi__label">Record Draft</span>
                        </div>
                        <p class="md-kpi__value">{{ $cacah($total['draft_record_count']) }} <span>record</span></p>
                        <p class="md-kpi__foot">record draft <b>IKUT terhitung</b> pada seluruh angka di atas; jumlahnya dinyatakan supaya terlihat seberapa besar laporan ini berdiri di atas data yang belum selesai</p>
                    </article>
                    <article class="md-kpi" data-testid="records-not-verified">
                        <div class="md-kpi__top">
                            <span class="md-kpi__label">Belum Diperiksa / Disahkan</span>
                        </div>
                        <p class="md-kpi__value">
                            <span data-testid="records-not-checked">{{ $cacah($total['records_not_checked']) }}</span>
                            /
                            <span data-testid="records-not-acknowledged">{{ $cacah($total['records_not_acknowledged']) }}</span>
                            <span>record</span>
                        </p>
                        <p class="md-kpi__foot">status verifikasi adalah <b>kelengkapan, bukan penyaring</b> &mdash; record ini tetap terhitung penuh</p>
                    </article>
                </div>
            </section>

            {{-- ============ 9. Rekap harian (dapat dibuka/tutup) ============
                 Periode panjang menghasilkan puluhan baris, jadi tabelnya dapat
                 ditutup agar cakupan dan tabel parameter tetap terbaca. Tombol,
                 bukan <details> bawaan: keadaannya harus satu sumber (properti
                 Livewire) agar tabel benar-benar hilang dari DOM saat ditutup.
                 Membuka atau menutupnya TIDAK mengubah satu angka pun. --}}
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
                                    <th scope="col">Slot terisi</th>
                                    <th scope="col">Downtime (menit)</th>
                                    @foreach ($metrics as $metric)
                                        <th scope="col">{{ $metric['label'] }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Tanggal tanpa record sama sekali TIDAK
                                     mendapat baris: baris nol akan terbaca
                                     sebagai "terukur nol". --}}
                                @foreach ($daily as $row)
                                    <tr data-testid="daily-row-{{ $row['date'] }}">
                                        <td>{{ $tgl($row['date']) }}</td>
                                        <td>{{ $cacah($row['filled_slot_count']) }}</td>
                                        <td @class(['is-muted' => $row['downtime_minutes'] === null])>
                                            {{ $row['downtime_minutes'] === null ? 'belum dicatat' : $cacah($row['downtime_minutes']) }}
                                        </td>
                                        @foreach ($metrics as $metric)
                                            <td @class(['is-muted' => ($row['averages'][$metric['column']] ?? null) === null])>
                                                {{ $nilai($row['averages'][$metric['column']] ?? null, 2) }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                {{-- TOTAL PERIODE DIHITUNG ULANG ATAS SELURUH
                                     SLOT, bukan merata-ratakan rata-rata harian:
                                     rata-rata dari rata-rata memberi bobot sama
                                     pada hari yang jumlah slotnya berbeda. --}}
                                <tr data-testid="daily-row-total">
                                    <td>Total periode</td>
                                    <td>{{ $cacah($dailyTotal['filled_slot_count']) }}</td>
                                    <td @class(['is-muted' => $dailyTotal['downtime_minutes'] === null])>
                                        {{ $dailyTotal['downtime_minutes'] === null ? 'belum dicatat' : $cacah($dailyTotal['downtime_minutes']) }}
                                    </td>
                                    @foreach ($metrics as $metric)
                                        <td @class(['is-muted' => ($dailyTotal['averages'][$metric['column']] ?? null) === null])>
                                            {{ $nilai($dailyTotal['averages'][$metric['column']] ?? null, 2) }}
                                        </td>
                                    @endforeach
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
