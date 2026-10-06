{{--
    Laporan Pressing (web) — screen-150--laporan-pressing-web.

    APA YANG DILAPORKAN HALAMAN INI: KONDISI OPERASI, bukan output. Satu
    record = satu hari kerja satu presser; satu baris = satu slot waktu
    dengan lima kolom ukur. Yang dicari pembaca bukan total melainkan
    KESTABILAN terhadap rentang kerjanya, sehingga intinya adalah
    min/rata-rata/maks per parameter.

    LIMA HAL YANG MENENTUKAN TATA LETAKNYA:

    1. CAKUPAN PENCATATAN DIRENDER PALING ATAS, lengkap dengan ketiga angka
       pembentuk penyebutnya (presser yang benar-benar beroperasi, hari yang
       dihitung, 24 slot kanonis per hari). Periode yang terisi seperlima pun
       menghasilkan rata-rata yang terlihat rapi, dan pembaca harus melihat
       itu SEBELUM mempercayainya.

    2. SETIAP RATA-RATA MEMBAWA PENYEBUTNYA SENDIRI. Kelima kolom ukur
       nullable dan terisi saling bebas, jadi tiap baris mencetak jumlah slot
       yang membentuk angkanya. Slot yang tidak mencatat sebuah kolom TIDAK
       mengukurnya nol — ia tidak mengukurnya sama sekali.

    3. DUA KOLOM TARGET, DUA PERTANYAAN YANG BERBEDA, DAN KEDUANYA DI BARIS
       YANG SAMA. `target_operating_range` menjawab "ke mana seharusnya"
       ("90C - 95C", "35 - 45 Amperes"); `critical_trigger_action_limit`
       menjawab "kapan harus bertindak, dan apa akibatnya kalau tidak"
       ("< 85C (Leads to poor oil liberation)"). Menampilkan salah satunya
       saja menghapus separuh informasi yang dipakai pembaca untuk
       memutuskan. Jumlah kolom tabelnya sama dengan tabel laporan Threshing;
       yang berbeda adalah ARTI kedua kolom terakhir, bukan lebarnya.
       Keduanya dirender VERBATIM, termasuk tanda kurung penjelasnya — di
       situlah akibat penyimpangan dinyatakan.

    4. DAN TIDAK ADA SATU NILAI PUN YANG DITANDAI DI LUAR BATAS. Tanpa kartu
       ambang, tanpa warna aman/bahaya, tanpa severity. Di sini sebabnya
       harus lebih tajam daripada pada Threshing, karena
       critical_trigger_action_limit JUSTRU membawa pembanding yang teratur
       pada lima dari tujuh parameter ("< 85C", "> 50 Amps", "> 60 Bar").
       Tiga hal yang menahannya: kedua kolom itu teks bebas dan TIDAK ADA APA
       PUN PADA SKEMA yang membatasi bentuknya — nilainya ditetapkan lewat
       seeder, dan satu seeder yang dijalankan atau satu suntingan langsung ke
       basis data dapat memperkenalkan bentuk baru tanpa satu pun uji
       menangkapnya; pengurai yang lalu gagal akan BERHENTI MEMPERINGATKAN
       tanpa satu pun galat — dan peringatan yang hilang terbaca sebagai
       "semuanya aman";
       satu nilai pada master sudah tidak dapat diurai tanpa menebak
       ("< 10% to 12%"); dan beberapa rentang membawa pernyataan ketiga di
       dalam tanda kurung ("75% - 80% (Minimum 3/4 full)"). Kotak
       keterangannya memakai kelas `.md-explain`, BUKAN `.md-threshold`,
       karena ketiadaan penandaan diasersi MENURUT NAMA KELAS pada HTML
       ter-render.

    5. DUA TARGET TANPA PENGUKURAN TETAP DITAMPILKAN, dan di sini jumlahnya
       dua — bukan satu seperti pada Threshing. Master memuat TUJUH parameter
       sementara formulir mengukur lima, jadi "Nut Breakage Rate" dan "Press
       Cake Moisture" punya rentang kerja dan batas tindakan tanpa satu pun
       pembacaan DI MANA PUN pada skema ini, bukan hanya di pressing_details.
       Keduanya pun justru parameter yang paling menentukan mutu
       pengepresan. Standar yang tidak pernah diukur TERBACA SEPERTI
       TERPENUHI padahal ia sekadar tidak ada.

    ALASAN DOWNTIME DIKELOMPOKKAN HARFIAH, dan halaman ini menyatakannya. Dua
    ejaan untuk satu sebab muncul sebagai dua baris; tanpa keterangan itu, hal
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
    $downtimeReasons = $summary['downtime_reasons'] ?? [];
    $total = $summary['total'] ?? null;

    // "Ada data" = ada minimal satu slot terisi. Dipakai hanya untuk
    // memutuskan apakah tabel dan rekap digambar: tabel kosong akan terbaca
    // sebagai hasil pengukuran bernilai nol, padahal tidak ada yang diukur.
    $hasData = (bool) ($summary['has_data'] ?? false);
@endphp

<div class="md" data-testid="laporan-pressing">

    {{-- ============ 1. Hero: periode yang sedang dibaca ============ --}}
    <section class="md-hero" data-testid="report-hero">
        <div class="md-hero__main">
            <p class="md-hero__eyebrow">Laporan Periode &middot; Stasiun Pressing</p>
            <h1 class="md-hero__title">{{ $selectedPeriod['name'] ?? 'Laporan Pressing' }}</h1>
            <p class="md-hero__subtitle">
                @if ($selectedPeriod)
                    {{ $summary['business_unit']['name'] ?? $businessUnitName }}
                    @if ($selectedProductionLine) &middot; {{ $selectedProductionLine['name'] }} @endif
                    &middot; {{ $tgl($selectedPeriod['start_date']) }} &ndash; {{ $tgl($selectedPeriod['end_date']) }}
                @else
                    Kondisi operasi stasiun Pressing sepanjang satu Periode Pelaporan, dibandingkan dengan standar operasionalnya
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
        Hanya periode yang mencakup Pressing yang ditampilkan. Angka disaring menurut Production Line yang melekat pada record itu sendiri, dan keanggotaan periode mengikuti tanggal recordnya.
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
                menampilkan daftar Production Line, daftar Periode Pelaporan, dan laporan Pressing-nya.
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
                Laporan Pressing menghasilkan angka gabungan per line, dan mencampur beberapa production
                line membuat angkanya tidak bisa ditindaklanjuti. Pilih satu production line pada pemilih
                di atas untuk menampilkan laporannya. Selama belum dipilih, layar ini tidak menampilkan
                satu angka pun &mdash; termasuk tidak menampilkan angka seluruh mill sebagai
                penggantinya.
            </p>
        </div>
    @elseif ($periods === [])
        {{-- Empty state (d): mill belum punya Periode Pelaporan yang mencakup
             Pressing. Bukan 404 — cukup arahkan ke layar yang membuatnya. --}}
        <div class="md-empty" data-testid="no-period-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
            </span>
            <p class="md-empty__title">Belum ada Periode Pelaporan</p>
            <p class="md-empty__text">
                Mill ini belum memiliki Periode Pelaporan yang mencakup stasiun Pressing. Hubungi Admin
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
                    Tidak ada satu pun slot Pressing yang terisi pada rentang periode ini di
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
                                {{-- DUA KOLOM TARGET, dua pertanyaan yang
                                     berbeda. Menggabungkannya menjadi satu
                                     kolom akan menghapus perbedaan itu. --}}
                                <th scope="col">Rentang kerja</th>
                                <th scope="col">Batas tindakan</th>
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
                                    {{-- Keduanya VERBATIM, termasuk tanda
                                         kurung penjelasnya: di situlah akibat
                                         penyimpangan dinyatakan, dan
                                         memotongnya akan membuang
                                         satu-satunya tempat itu tertulis. --}}
                                    <td @class(['is-muted' => $metric['target']['target_operating_range'] === null])
                                        data-testid="metric-target-range">
                                        {{ $metric['target']['target_operating_range'] ?? 'rentang kerja belum terisi pada master' }}
                                    </td>
                                    <td @class(['is-muted' => $metric['target']['critical_trigger_action_limit'] === null])
                                        data-testid="metric-action-limit">
                                        {{ $metric['target']['critical_trigger_action_limit'] ?? 'belum terisi' }}
                                    </td>
                                </tr>
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
                <b>Rentang kerja dan batas tindakan ditampilkan, tetapi laporan ini tidak menilai satu
                angka pun.</b> Tidak ada warna peringatan, tidak ada severity, dan tidak ada penanda di
                luar batas &mdash; penilaiannya milik Anda, bukan milik layar ini. Keduanya menjawab
                pertanyaan yang berbeda: <b>rentang kerja</b> adalah ke mana angkanya seharusnya,
                <b>batas tindakan</b> adalah kapan seseorang harus bertindak dan apa akibatnya bila
                tidak &mdash; keterangan di dalam tanda kurung itu bagian dari isinya, bukan hiasan.
                <small>
                    <b>Mengapa tidak ditandai otomatis padahal batas tindakannya terlihat berupa angka.</b>
                    Keduanya <b>teks bebas</b>, dan tidak ada apa pun pada skema yang membatasi
                    bentuknya &mdash; nilainya hari ini ditetapkan lewat seeder, dan satu seeder yang
                    dijalankan atau satu suntingan langsung ke basis data dapat memperkenalkan bentuk
                    baru tanpa satu pun uji menangkapnya. Pengurai yang lalu gagal akan <b>berhenti
                    memperingatkan tanpa satu pun galat</b> &mdash; dan peringatan yang hilang terbaca
                    sebagai &ldquo;semuanya aman&rdquo;, arah kegagalan terburuk untuk indikator mutu.
                    Ditambah satu hal yang bukan soal teknis: <b>warna membawa makna melampaui
                    statistik</b>, sehingga angka merah pada laporan periode terbaca sebagai
                    pelanggaran yang tidak pernah ditetapkan siapa pun.
                    Satu nilai pada master pun sudah tidak dapat diurai tanpa menebak hari ini
                    (&ldquo;&lt; 10% to 12%&rdquo;), dan beberapa rentang membawa pernyataan ketiga di
                    dalam tanda kurung (&ldquo;75% - 80% (Minimum 3/4 full)&rdquo;). Bila penandaan
                    diinginkan, cara yang jujur adalah memecah master menjadi kolom <b>batas
                    numerik</b> di samping teksnya &mdash; bukan menguraikan teksnya saat render.
                    <b>Kedua kolom target berlaku umum untuk seluruh mill</b> &mdash; master target
                    Pressing tidak dipecah per mill.
                    <b>Penyebut</b> tiap baris adalah jumlah slot yang benar-benar mencatat kolom itu,
                    dan sengaja dapat berbeda antar baris: kelima kolom nullable dan terisi saling
                    bebas, jadi satu penyebut bersama akan salah untuk setidaknya empat di antaranya.
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
                        <b>Master target operasional Pressing belum terisi</b>, jadi kolom rentang kerja
                        dan batas tindakan pada tabel di atas kosong. Seluruh angka hasil ukur tetap
                        ditampilkan apa adanya &mdash; master yang belum diisi tidak menghapus pengukuran
                        yang sudah terjadi.
                    </span>
                </div>
            @elseif ($targetsWithoutMetric !== [])
                <section class="md-card" data-testid="targets-without-metric">
                    <header class="md-card__head">
                        <h3>Standar yang Belum Diukur Sistem</h3>
                        <span class="md-card__hint">ada standarnya, tidak ada kolom pengukurannya &middot; ditampilkan sebagai temuan, bukan disembunyikan</span>
                    </header>
                    <div class="md-recap">
                        <table class="md-table" data-testid="targets-without-metric-table">
                            <thead>
                                <tr>
                                    <th scope="col">Parameter</th>
                                    <th scope="col">Rentang kerja</th>
                                    <th scope="col">Batas tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($targetsWithoutMetric as $target)
                                    <tr data-testid="targets-without-metric-row">
                                        <td>{{ $target['parameter_metric'] }}</td>
                                        <td>{{ $target['target_operating_range'] }}</td>
                                        <td>{{ $target['critical_trigger_action_limit'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="md-explain" data-testid="targets-without-metric-note">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                        <span>
                            Parameter di atas punya rentang kerja dan batas tindakan pada master target,
                            tetapi <b>tidak punya kolom pengukuran di mana pun pada sistem ini</b> &mdash;
                            bukan hanya tidak ada di formulir Pressing. Yang terdekat, kadar air fibre
                            pada stasiun Depricarping, adalah bahan lain di stasiun lain.
                            Keduanya pun justru <b>parameter yang paling menentukan mutu pengepresan</b>.
                            Ditampilkan justru karena itu: <b>standar yang tidak pernah diukur terbaca
                            seperti terpenuhi</b>, padahal ia sekadar tidak ada. Perlu diputuskan mana
                            yang benar &mdash; menambahkan kedua kolom pengukurannya pada formulir
                            Pressing, memindahkannya ke Process Quality Control yang sudah mengukur oil
                            loss, atau mencabut keduanya dari master.
                        </span>
                    </div>
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

            {{-- ============ 7. Alasan downtime ============ --}}
            <section class="md-card" data-testid="downtime">
                <header class="md-card__head">
                    <h3>Alasan Downtime</h3>
                    <span class="md-card__hint">diurutkan dari yang terbanyak &middot; dikelompokkan HARFIAH, tanpa penyeragaman ejaan maupun huruf besar-kecil</span>
                </header>
                @if ($downtimeReasons === [])
                    <p class="md-card__hint" data-testid="downtime-empty">
                        Tidak ada satu pun alasan downtime tercatat pada periode dan line ini. Ini
                        dinyatakan sebagai keterangan, bukan tabel kosong tanpa penjelasan &mdash; tabel
                        kosong tidak membedakan &ldquo;tidak ada downtime&rdquo; dari &ldquo;tidak ada
                        yang mencatatnya&rdquo;.
                    </p>
                @else
                    <div class="md-recap">
                        <table class="md-table" data-testid="downtime-table">
                            <thead>
                                <tr>
                                    <th scope="col">Alasan (apa adanya)</th>
                                    <th scope="col">Slot</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($downtimeReasons as $row)
                                    <tr data-testid="downtime-row">
                                        <td>{{ $row['reason'] }}</td>
                                        <td>{{ $cacah($row['slot_count']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="md-explain" data-testid="downtime-note">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                        <span>
                            Alasan downtime adalah <b>teks bebas</b>, dan dikelompokkan
                            <b>harfiah</b>: tanpa penyeragaman ejaan dan tanpa penyeragaman huruf
                            besar-kecil. Dua ejaan untuk sebab yang sama karena itu muncul sebagai
                            <b>dua baris</b> &mdash; itu bentuk datanya, bukan cacat laporan ini.
                            Menyeragamkannya justru akan menggabungkan sebab yang penulisnya memang
                            maksudkan berbeda.
                            <small>
                                Slot yang mencatat alasan downtime <b>tetap ikut</b> pada min, rata-rata
                                dan maks bila kolom ukurnya juga terisi &mdash; downtime adalah keterangan
                                tambahan pada slot itu, bukan penyaring.
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
