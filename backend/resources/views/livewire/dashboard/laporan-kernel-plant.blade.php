{{--
    Laporan Periode Kernel Plant (web) — screen-154--laporan-kernel-plant-web.

    APA YANG DILAPORKAN HALAMAN INI: KONDISI OPERASI, bukan output. Satu
    record = satu hari kerja satu unit kernel plant; satu baris = satu slot
    waktu dengan TUJUH kolom ukur. Yang dicari pembaca bukan total melainkan
    KESTABILAN terhadap standar operasionalnya, sehingga intinya adalah
    min/rata-rata/maks per parameter.

    ENAM HAL YANG MENENTUKAN TATA LETAKNYA:

    1. CAKUPAN PENCATATAN DIRENDER PALING ATAS, lengkap dengan ketiga angka
       pembentuk penyebutnya (unit kernel plant yang benar-benar beroperasi,
       hari yang dihitung, 24 slot kanonis per hari). Periode yang terisi
       seperlima pun menghasilkan rata-rata yang terlihat rapi, dan pembaca
       harus melihat itu SEBELUM mempercayainya.

    2. SETIAP RATA-RATA MEMBAWA PENYEBUTNYA SENDIRI, berformat "N dari M
       slot". N adalah milik baris itu sendiri (jumlah slot terisi yang kolom
       INI-nya tidak null) dan berbeda-beda antar baris, karena ketujuh kolom
       ukur nullable dan terisi saling bebas. M SENGAJA SAMA untuk ketujuh
       baris: ia adalah jumlah slot terisi pada cakupan. Skema ini TIDAK PUNYA
       cara mengetahui M per kolom — ketujuh kolom ukur ada pada SETIAP baris
       detail, jadi "unit mana mengukur parameter mana" bukan fakta yang
       tersimpan di mana pun, dan M per kolom hanya bisa lahir dari karangan
       (mock HTML layar ini sempat melakukannya; itu cacat mock, bukan acuan).
       Bacaannya pun lebih berguna begini: "dari seluruh slot yang benar-benar
       diisi orang, sebanyak N di antaranya membawa parameter ini". Slot yang
       tidak mencatat sebuah kolom TIDAK mengukurnya nol — ia tidak
       mengukurnya sama sekali. Jumlah slot terisi pada cakupan pun dapat
       LEBIH BESAR daripada N baris mana pun, karena slot dihitung terisi bila
       salah satu dari SEMBILAN kolom bacaan terisi — ketujuh kolom ukur,
       menit downtime, atau temuan. Itu benar, bukan ketidaksesuaian, dan yang
       dilampaui adalah N, bukan M.

    3. DUA KOLOM TARGET, BUKAN TIGA, DAN KEDUANYA DI BARIS YANG SAMA DENGAN
       ANGKANYA. Master Kernel Plant berbentuk TIGA kolom seperti
       Threshing/Pressing (equipment_parameter / target_benchmark /
       corrective_action_plan), BUKAN bentuk empat kolom milik Depricarping:
       di sini tidak ada `critical_limit` dan tidak ada
       `operational_consequence_justification`, jadi tabelnya TUJUH kolom —
       satu lebih sedikit daripada Depricarping — dan tidak ada satu sel pun
       yang dirender untuk kolom yang masternya tidak punya. `target_benchmark`
       menjawab "ke mana seharusnya" ("20 - 25 Amps (Nut Breakage >95%)") dan
       `corrective_action_plan` menjawab "apa yang dilakukan bila tidak"
       ("Adjust rotor-vane clearance if uncracked nut rate >5%."). Keduanya
       dirender VERBATIM, termasuk keterangan dalam tanda kurung yang membawa
       separuh maknanya.

    4. DOWNTIME DI SINI ADALAH ANGKA, PADA BLOKNYA SENDIRI, karena
       kernel_plant_details.downtime_minutes adalah kolom INTEGER. Total menit,
       jumlah slot yang MENCATATNYA, dan rata-rata per slot PENCATAT — dengan
       penyebut itu tercetak di sebelahnya, karena rata-rata atas seluruh slot
       terisi akan mengecil justru seiring bertambahnya slot yang tidak
       dicatat. Halaman menyatakan bahwa slot tanpa
       catatan BUKAN nol menit, dan bahwa parameter ini tidak punya standar
       pada master sama sekali (has_standard selalu false) — tanpa itu, sel
       target yang kosong terbaca sebagai master yang belum diisi. Total yang
       null dirender sebagai tanda pisah, sementara nol yang BENAR-BENAR
       tercatat dirender sebagai nol: keduanya kenyataan yang berbeda, dan
       jumlah slot pencatat ikut menghitung nol yang tercatat itu. Teks
       bebasnya ada di blok TERPISAH (Temuan): satu menjawab berapa lama, satu
       menjawab apa yang terlihat, dan menggabungkannya membuat slot yang
       punya keduanya terhitung dua kali pada satu pengertian.

    5. DAN TIDAK ADA SATU NILAI PUN YANG DITANDAI DI LUAR BATAS. Tanpa kartu
       ambang, tanpa warna aman/bahaya, tanpa severity. Kedua kolom target
       adalah teks bebas yang bentuknya TIDAK DIBATASI SKEMA, dan di sini satu
       sel saja sudah mematahkan pengurai apa pun: "20 - 25 Amps (Nut Breakage
       >95%)" memuat DUA angka dengan SATUAN BERBEDA dan ARAH PEMBANDING
       BERLAWANAN — sebuah rentang yang harus dimasuki, dan sebuah persentase
       yang harus dilampaui. "70°C - 80°C (Top/Middle zones)" menyebut ZONA
       yang tidak punya kolom sama sekali, dan standar kadar air akhir
       mencampur batasnya dengan alasan batas itu ada ("(Prevents mold
       growth)") di dalam sel yang sama. Contoh-contohnya sengaja tidak
       dikutip dengan angka persennya di dalam prosa halaman ini: uji
       memeriksa ketiadaan teks persen tertentu pada sel cakupan, dan prosa
       yang mengutip angka master akan mencemari pemeriksaan itu. Nilainya ditetapkan lewat seeder, dan
       satu seeder yang dijalankan atau satu suntingan langsung ke basis data
       dapat memperkenalkan bentuk baru tanpa satu pun uji menangkapnya;
       pengurai yang lalu gagal akan BERHENTI MEMPERINGATKAN tanpa satu pun
       galat — dan peringatan yang hilang terbaca sebagai "semuanya aman",
       arah kegagalan terburuk untuk indikator mutu. Ditambah lagi warna
       membawa makna melampaui statistik, sehingga angka merah yang diturunkan
       dari prosa menyatakan pelanggaran yang tidak pernah ditetapkan siapa
       pun. Kotak keterangannya memakai kelas `.md-explain`, dan tidak satu
       kelas penanda pun dipakai di mana pun pada halaman ini, karena
       ketiadaan penandaan diasersi MENURUT NAMA KELAS pada HTML ter-render —
       kalimat penjelasnya sendiri memuat frasa "di luar batas", jadi asersi
       atas frasa akan selalu hijau.

    6. SATU STANDAR MASTER TIDAK PUNYA PENGUKURAN DI MANA PUN, dan itu
       diterbitkan alih-alih dibuang: "Final Kernel Dirt" tidak punya kolom di
       kernel_plant_details maupun di tabel lain. Bagian
       standar-tanpa-pengukuran karena itu DIGAMBAR WALAU ISINYA KOSONG,
       dengan keterangannya sendiri: bagian yang hilang ketika kosong tidak
       dapat dibedakan dari bagian yang belum pernah dibuat — dan di sini
       bagian itulah yang juga memperlihatkan nama parameter master yang
       disunting tangan sehingga pasangannya terlepas.

    SATU STANDAR MENGATUR DUA KOLOM, DUA KALI — bukan sekali. Master hanya
    memuat satu baris "Ripple Mill (Cracker)" sementara tabel detail punya
    ripple_mill_1_amps dan ripple_mill_2_amps, dan satu baris "Kernel Silo 1 &
    2" sementara tabel detail punya kernel_silo_1_temp_c dan
    kernel_silo_2_temp_c. Depricarping hanya punya SATU pasangan seperti ini
    (Nut Silo), jadi markup yang mengistimewakan satu pasangan lolos di sana
    dan SALAH di sini. Keempat baris tetap berdiri sendiri dengan penyebut
    masing-masing — empat mesin fisik, dan merata-ratakan satu pasangan akan
    menyembunyikan ketidakseimbangan beban yang justru menjadi alasan
    parameter itu diukur — tetapi tanpa keterangan itu standar yang identik
    tercetak dua kali berturut-turut terbaca seperti data yang terduplikasi,
    dan seseorang akan "membersihkannya". Penandanya DITURUNKAN dari
    `target.shares_standard_with`, tidak dipaku, supaya ripple mill atau silo
    ketiga tetap ditandai benar tanpa menyentuh berkas ini.

    TEMUAN DIKELOMPOKKAN HARFIAH, dan halaman ini menyatakannya. Dua ejaan
    untuk satu hal muncul sebagai dua baris; tanpa keterangan itu, hal
    tersebut terbaca sebagai cacat laporan alih-alih sebagai bentuk datanya.

    PERSEN CAKUPAN TANPA PENYEBUT dirender "—", bukan angka: persentase nol
    mengklaim ada yang diukur dan hasilnya nol. Halaman ini sengaja tidak
    menuliskan contoh persentase nol itu sebagai teks di mana pun, karena uji
    mengasersikan ketiadaannya pada keluaran render.

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
    $byKernelPlant = $summary['by_kernel_plant'] ?? [];
    $daily = $summary['daily'] ?? [];
    $dailyTotal = $summary['daily_total'] ?? null;
    $downtime = $summary['downtime'] ?? null;
    $findings = $summary['findings'] ?? [];
    $allTargetsMeasured = (bool) ($summary['all_targets_measured'] ?? false);
    $total = $summary['total'] ?? null;

    // Label kolom untuk keterangan "standarnya satu" pada kedua pasangan.
    // Dibangun dari `metrics` yang sedang dirender, BUKAN dari konstanta
    // service: ketujuh kolom selalu hadir di situ lengkap dengan labelnya,
    // jadi tiap anggota pasangan pasti ditemukan, dan blade ini tidak perlu
    // mengenal satu pun konstanta PHP. Satu perubahan label karena itu tidak
    // mungkin meninggalkan dua ejaan di layar.
    $labelPerKolom = collect($metrics)->mapWithKeys(
        fn ($metric) => [$metric['column'] => $metric['label']]
    )->all();
    $labelKolom = fn (string $column) => $labelPerKolom[$column] ?? $column;

    // "Ada data" = ada minimal satu slot terisi. Dipakai hanya untuk
    // memutuskan apakah tabel dan rekap digambar: tabel kosong akan terbaca
    // sebagai hasil pengukuran bernilai nol, padahal tidak ada yang diukur.
    $hasData = (bool) ($summary['has_data'] ?? false);
@endphp

<div class="md" data-testid="laporan-kernel-plant">

    {{-- ============ 1. Hero: periode yang sedang dibaca ============ --}}
    <section class="md-hero" data-testid="report-hero">
        <div class="md-hero__main">
            <p class="md-hero__eyebrow">Laporan Periode &middot; Stasiun Kernel Plant</p>
            <h1 class="md-hero__title">{{ $selectedPeriod['name'] ?? 'Laporan Periode Kernel Plant' }}</h1>
            <p class="md-hero__subtitle">
                @if ($selectedPeriod)
                    {{ $summary['business_unit']['name'] ?? $businessUnitName }}
                    @if ($selectedProductionLine) &middot; {{ $selectedProductionLine['name'] }} @endif
                    &middot; {{ $tgl($selectedPeriod['start_date']) }} &ndash; {{ $tgl($selectedPeriod['end_date']) }}
                @else
                    Kondisi operasi stasiun Kernel Plant sepanjang satu Periode Pelaporan, dibandingkan dengan standar operasionalnya
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
        Hanya periode yang mencakup Kernel Plant yang ditampilkan. Angka disaring menurut Production Line yang melekat pada record itu sendiri, dan keanggotaan periode mengikuti tanggal recordnya.
    </x-report-filter-bar>

    <div class="md-body ld-region" wire:loading.delay.short.class="ld-region--busy" wire:loading.delay.short.attr="aria-busy" wire:target="businessUnitId,productionLineId,periodId">
    @if ($hasNoMillForAccount)
        {{-- Empty state (a): akun terikat mill tetapi users.business_unit_id
             kosong. GAGAL TERTUTUP — pemilih Mill TIDAK ditawarkan sebagai
             gantinya, dan tidak satu query pun dijalankan untuk sampai ke
             sini. Keadaannya DISEBUTKAN SEBABNYA: hasil kosong tanpa
             keterangan akan terbaca sebagai "mill ini tidak punya data". --}}
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
                menampilkan daftar Production Line, daftar Periode Pelaporan, dan laporan Kernel
                Plant-nya.
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
        {{-- Mill sudah pasti, production line belum. Tidak ada satu angka pun
             di dalam area ini — bukan nol, bukan persentase nol, bukan
             penyebut kosong — dan TIDAK ADA angka seluruh mill sebagai
             gantinya. Keterangan di bawah pun sengaja tidak memuat satu digit
             pun, karena uji memeriksa ketiadaan teks angka pada area laporan,
             bukan ketiadaan satu elemen tertentu. --}}
        <div class="md-empty" data-testid="select-production-line-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h16"/><circle cx="8" cy="7" r="1.6"/><circle cx="14" cy="12" r="1.6"/><circle cx="10" cy="17" r="1.6"/></svg>
            </span>
            <p class="md-empty__title">Pilih production line terlebih dahulu</p>
            <p class="md-empty__text">
                Laporan Kernel Plant menghasilkan angka gabungan per line, dan mencampur beberapa
                production line membuat angkanya tidak bisa ditindaklanjuti. Pilih satu production line
                pada pemilih di atas untuk menampilkan laporannya. Selama belum dipilih, layar ini
                tidak menampilkan satu angka pun &mdash; termasuk tidak menampilkan angka seluruh mill
                sebagai penggantinya. Pilihan itu tidak pernah dibuat untuk Anda, bahkan ketika mill
                ini hanya punya satu line: memilihkannya akan membuat Anda menyangka sedang melihat
                seluruh mill.
            </p>
        </div>
    @elseif ($periods === [])
        {{-- Empty state (d): mill belum punya Periode Pelaporan yang mencakup
             Kernel Plant. Bukan galat — cukup arahkan ke layar yang
             membuatnya. Pemilih periode sengaja tetap kosong dan TIDAK diisi
             periode milik jenis stasiun lain. --}}
        <div class="md-empty" data-testid="no-period-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
            </span>
            <p class="md-empty__title">Belum ada Periode Pelaporan</p>
            <p class="md-empty__text">
                Mill ini belum memiliki Periode Pelaporan yang mencakup stasiun Kernel Plant. Hubungi
                Admin agar membuatnya terlebih dahulu di layar Kelola Periode Pelaporan, lalu laporan
                periode akan tampil di sini.
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
                    {{-- Persen TANPA penyebut dirender '—', bukan angka:
                         persentase nol mengklaim ada yang diukur dan hasilnya
                         nol. --}}
                    <p class="md-kpi__meta" data-testid="coverage-percent">
                        @if ($coverage['coverage_percent'] === null)
                            &mdash;
                        @else
                            {{ $nilai($coverage['coverage_percent'], 1) }}%
                        @endif
                    </p>
                    <p class="md-kpi__foot">slot dihitung terisi bila minimal satu dari sembilan kolom bacaan diisi &mdash; ketujuh kolom ukur, menit downtime, atau temuan; definisinya dipinjam dari layar input itu sendiri, bukan diturunkan ulang di sini</p>
                </article>
                <article class="md-kpi" data-testid="coverage-denominator">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Pembentuk Penyebut</span>
                    </div>
                    <p class="md-kpi__value">
                        {{ $cacah($coverage['kernel_plant_count']) }}
                        <span>unit &times; {{ $cacah($coverage['days_counted']) }} hari &times; {{ $cacah($coverage['slots_per_kernel_plant_per_day']) }} slot</span>
                    </p>
                    {{-- URUTANNYA PENTING: days_counted === 0 DIPERIKSA LEBIH
                         DULU. ReportPeriodDays::isRunning() juga true untuk
                         periode yang BELUM MULAI (seluruh rentangnya masih di
                         masa depan), jadi memeriksa period_running lebih dulu
                         akan menjelaskan periode yang belum mulai sebagai
                         "sedang berjalan" — padahal tidak ada satu hari pun
                         yang dihitung. Keterangan "sedang berjalan" menyebut
                         HARI TERAKHIR YANG TERHITUNG, bukan tanggal akhir
                         periode: tanggal akhir yang belum tiba akan terbaca
                         seperti hari yang sudah ikut dihitung. --}}
                    @if ((int) $coverage['days_counted'] === 0)
                        <p class="md-kpi__meta" data-testid="coverage-not-started-note">
                            periode BELUM MULAI, sehingga belum ada hari yang dapat dijadikan pembagi &mdash; persennya dirender sebagai &ldquo;&mdash;&rdquo;, bukan sebagai angka nol
                        </p>
                    @elseif ($coverage['period_running'])
                        <p class="md-kpi__meta" data-testid="coverage-running-note">
                            dihitung sampai {{ $tgl($countedUntil) }} &mdash; periode masih berjalan, dan hari yang belum terjadi tidak mungkin tercatat
                        </p>
                    @endif
                    <p class="md-kpi__foot">jumlah unit adalah unit kernel plant yang BENAR-BENAR punya record pada periode ini, bukan jumlah stasiun terdaftar; 24 slot kanonis diambil dari grid layar input itu sendiri</p>
                </article>
                <article class="md-kpi" data-testid="coverage-days">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Hari Dihitung</span>
                    </div>
                    <p class="md-kpi__value">
                        {{ $cacah($coverage['days_counted']) }}
                        <span>dari {{ $cacah($coverage['days_in_period']) }} hari periode</span>
                    </p>
                    <p class="md-kpi__foot">record berstatus draft IKUT terhitung pada seluruh angka laporan ini</p>
                </article>
            </div>
        </section>

        @if (! $hasData)
            {{-- Empty state (e): periode + line valid tetapi tanpa satu slot
                 terisi. Cakupan di atas sudah menampilkannya; rekap per unit,
                 rekap harian, blok downtime dan daftar temuan TIDAK digambar
                 — tabel kosong akan terbaca sebagai hasil pengukuran bernilai
                 nol. TABEL PARAMETER TETAP DIGAMBAR, lengkap dengan ketujuh
                 barisnya: baris yang hilang terbaca sebagai "parameter itu
                 tidak ada", dan jumlah barisnya diasersi tepat tujuh. --}}
            <div class="md-empty" data-testid="empty-state">
                <span class="md-empty__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19h16M7 16V9M12 16V5M17 16v-4"/></svg>
                </span>
                <p class="md-empty__title">Belum ada data pada periode dan line ini</p>
                <p class="md-empty__text">
                    Tidak ada satu pun slot Kernel Plant yang terisi pada rentang periode ini di
                    {{ $selectedProductionLine['name'] ?? 'production line ini' }}, sehingga seluruh
                    min, rata-rata dan maks ditampilkan sebagai tidak tersedia &mdash; bukan sebagai
                    nol. Tabel parameter di bawah tetap memuat ketujuh barisnya dengan penyebut nol
                    supaya terlihat bahwa parameternya ada dan tidak terukur, sementara rekap per
                    unit, rekap harian, blok downtime dan daftar temuan tidak digambar.
                </p>
            </div>
        @endif

            {{-- ============ 4. Tabel parameter + standar operasionalnya ============
                 Satu baris memuat angka DAN standarnya DAN rencana tindakannya,
                 karena di situlah penilaian manusia benar-benar terjadi. --}}
            <section class="md-card" data-testid="metrics">
                <header class="md-card__head">
                    <h3>Parameter Operasi &amp; Standarnya</h3>
                    <span class="md-card__hint">min / rata-rata / maks per kolom ukur &middot; tiap angka membawa penyebutnya sendiri &middot; target dan rencana tindakan berdampingan &middot; tidak ada nilai yang ditandai di luar batas</span>
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
                                {{-- DUA KOLOM TARGET, bukan tiga: master
                                     Kernel Plant berbentuk tiga kolom seperti
                                     Threshing/Pressing, jadi tidak ada batas
                                     kritis maupun akibat operasional untuk
                                     dirender. Sel untuk kolom yang tidak ada
                                     sengaja tidak dibuat sama sekali. --}}
                                <th scope="col">Target / benchmark</th>
                                <th scope="col">Rencana tindakan koreksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Baris parameter TANPA satu pun pembacaan TETAP
                                 dirender: baris yang hilang terbaca sebagai
                                 "tidak ada parameter ini", padahal yang benar
                                 adalah "tidak ada yang mengukurnya". Ketujuh
                                 baris selalu ada, juga ketika sebuah ripple
                                 mill atau sebuah silo tidak pernah dicatat
                                 sekali pun. --}}
                            @foreach ($metrics as $metric)
                                <tr data-testid="metric-row">
                                    <td>
                                        {{ $metric['label'] }} <small class="is-muted">({{ $metric['unit'] }})</small>
                                        {{-- Nama parameter pada master, dicetak
                                             kecil di bawah label kolomnya: ia
                                             yang menjelaskan mengapa dua baris
                                             berbeda dapat membawa target yang
                                             sama persis. --}}
                                        <small class="is-muted" data-testid="metric-master-parameter">
                                            standar master:
                                            {{ $metric['target']['equipment_parameter'] ?? 'tidak terpeta ke satu pun baris master' }}
                                        </small>
                                    </td>
                                    <td @class(['is-muted' => $metric['min'] === null])>{{ $nilai($metric['min'], 2) }}</td>
                                    <td @class(['is-muted' => $metric['avg'] === null])>{{ $nilai($metric['avg'], 2) }}</td>
                                    <td @class(['is-muted' => $metric['max'] === null])>{{ $nilai($metric['max'], 2) }}</td>
                                    {{-- PENYEBUT, dicetak di sebelah angkanya.
                                         N milik baris ini sendiri; M sama untuk
                                         ketujuh baris dan sengaja demikian —
                                         skema tidak punya cara mengetahui M per
                                         kolom, jadi M per kolom hanya bisa
                                         dikarang. Slot yang tidak mencatat kolom
                                         ini tidak mengukurnya nol, ia tidak
                                         mengukurnya sama sekali. --}}
                                    <td class="is-muted" data-testid="metric-denominator">
                                        {{ $cacah($metric['filled_slot_count']) }} dari {{ $cacah($coverage['filled_slots']) }} slot
                                    </td>
                                    {{-- Keduanya VERBATIM, termasuk keterangan
                                         dalam tanda kurung. --}}
                                    <td @class(['is-muted' => $metric['target']['target_benchmark'] === null])
                                        data-testid="metric-target-benchmark">
                                        {{ $metric['target']['target_benchmark'] ?? 'target belum terisi pada master' }}
                                    </td>
                                    <td @class(['is-muted' => $metric['target']['corrective_action_plan'] === null])
                                        data-testid="metric-corrective-action">
                                        {{ $metric['target']['corrective_action_plan'] ?? 'belum terisi' }}
                                    </td>
                                </tr>
                                @if ($metric['target']['shares_standard_with'] !== [])
                                    {{-- SATU STANDAR, DUA KOLOM — dan di layar
                                         ini terjadi DUA KALI, pada pasangan
                                         ripple mill dan pada pasangan kernel
                                         silo. Tanpa baris ini standar yang
                                         identik tercetak dua kali berturut-turut
                                         terbaca seperti data yang terduplikasi,
                                         dan seseorang akan "membersihkannya".
                                         Daftar pasangannya diturunkan dari
                                         respons, tidak dipaku, jadi mesin
                                         ketiga pun ditandai benar. --}}
                                    <tr data-testid="metric-shared-standard-row">
                                        <td colspan="7" class="is-muted">
                                            <small data-testid="metric-shared-standard">
                                                Standar baris ini sama dengan
                                                @foreach ($metric['target']['shares_standard_with'] as $sibling)
                                                    <b>{{ $labelKolom($sibling) }}</b>@if (! $loop->last), @endif
                                                @endforeach
                                                {{-- DUA KALIMAT, DAN PERCABANGANNYA PERLU. Ketika nama
                                                     parameter pada master disunting sehingga tak lagi cocok
                                                     dengan peta kolom, equipment_parameter bernilai null;
                                                     mencetaknya apa adanya menghasilkan "master hanya memuat
                                                     satu baris "" untuk keduanya" — tanda kutip kosong yang
                                                     mengklaim sebuah baris master yang justru sudah tidak
                                                     ada, tepat pada keadaan layar yang aturan "suntingan
                                                     harus terlihat" dibangun untuk itu. Fallback saja tidak
                                                     cukup di sini: kalimatnya SENDIRI menjadi tidak benar,
                                                     jadi yang berganti adalah pernyataannya, bukan hanya
                                                     nilainya. Sel seagam di baris parameter (metric-master-
                                                     parameter) sudah menjaga null dengan cara yang sama. --}}
                                                @if ($metric['target']['equipment_parameter'] !== null)
                                                    &mdash; master hanya memuat satu baris
                                                    &ldquo;{{ $metric['target']['equipment_parameter'] }}&rdquo; untuk keduanya.
                                                @else
                                                    &mdash; dan keduanya kini <b>tidak terpeta ke satu pun baris
                                                    master</b>, jadi standarnya tidak lagi tercetak di sini.
                                                    Standar yang terlepas itu muncul pada bagian
                                                    &ldquo;standar tanpa pengukuran&rdquo; di bawah.
                                                @endif
                                                <b>Angkanya TIDAK dirata-ratakan menjadi satu</b>: keduanya mesin
                                                fisik yang berbeda, dan rata-rata gabungan akan menyembunyikan
                                                ketidakseimbangan beban yang justru menjadi alasan parameter ini
                                                diukur.
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
                 yang belum selesai, dan seseorang akan "melengkapinya". --}}
            <div class="md-explain" data-testid="no-flagging-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
            <span>
                <b>Target dan rencana tindakan ditampilkan, tetapi laporan ini tidak menilai satu
                angka pun.</b> Tidak ada warna peringatan, tidak ada severity, tidak ada deteksi
                pencilan, dan tidak ada penanda di luar batas &mdash; penilaiannya milik Anda, bukan
                milik layar ini. Keduanya menjawab pertanyaan yang berbeda: <b>target / benchmark</b>
                adalah ke mana angkanya seharusnya, dan <b>rencana tindakan koreksi</b> adalah apa
                yang dilakukan ketika angkanya tidak di sana. Master Kernel Plant hanya punya kedua
                kolom itu &mdash; tidak ada batas kritis terpisah seperti pada master Depricarping
                &mdash; jadi tabel di atas sengaja tidak memuat sel untuk kolom yang tidak ada.
                <small>
                    <b>Mengapa tidak ditandai otomatis.</b> Kedua kolom itu <b>teks bebas</b>, dan
                    tidak ada apa pun pada skema yang membatasi bentuknya. Di stasiun ini satu sel
                    saja sudah mematahkan pengurai apa pun:
                    &ldquo;20 - 25 Amps (Nut Breakage &gt;95%)&rdquo; memuat <b>dua angka dengan
                    satuan berbeda dan arah pembanding berlawanan</b> &mdash; sebuah rentang yang
                    harus dimasuki, dan sebuah persentase yang harus dilampaui &mdash; di dalam satu
                    sel yang sama. &ldquo;70&deg;C - 80&deg;C (Top/Middle zones)&rdquo; menyebut
                    <b>zona</b> yang tidak punya kolom sama sekali, dan standar kadar air akhir
                    mencampur <b>batas dengan alasan batas itu ada</b>
                    (&ldquo;(Prevents mold growth)&rdquo;) di dalam sel yang sama.
                    Nilainya hari ini ditetapkan lewat seeder, dan satu seeder yang dijalankan atau
                    satu suntingan langsung ke basis data dapat memperkenalkan bentuk baru tanpa satu
                    pun uji menangkapnya. Pengurai yang lalu gagal akan <b>berhenti memperingatkan
                    tanpa satu pun galat</b> &mdash; dan peringatan yang hilang terbaca sebagai
                    &ldquo;semuanya aman&rdquo;, arah kegagalan terburuk untuk indikator mutu. Di
                    atas itu, warna membawa makna melampaui statistik: angka merah yang diturunkan
                    dari prosa menyatakan pelanggaran yang tidak pernah ditetapkan siapa pun. Bila
                    penandaan diinginkan, cara yang jujur adalah memecah master menjadi kolom
                    <b>batas numerik</b> (minimum, maksimum, arah pembanding, satuan) di samping
                    teksnya &mdash; bukan menguraikan teksnya saat render.
                    <b>Kedua kolom target berlaku umum untuk seluruh mill</b> &mdash; master target
                    Kernel Plant tidak dipecah per mill.
                    <b>Penyebut</b> tiap baris berformat &ldquo;N dari M slot&rdquo;: N adalah jumlah
                    slot yang benar-benar mencatat kolom itu dan sengaja berbeda antar baris, karena
                    ketujuh kolom nullable dan terisi saling bebas. <b>M sama untuk ketujuh
                    baris</b> &mdash; jumlah slot terisi pada cakupan &mdash; karena ketujuh kolom
                    ukur ada pada <b>setiap</b> baris detail, sehingga skema ini tidak punya cara
                    mengetahui unit mana mengukur parameter mana; M yang berbeda per kolom hanya bisa
                    dikarang. Jumlah slot terisi pada cakupan pun dapat <b>lebih besar</b> daripada N
                    baris mana pun, karena slot dihitung terisi bila salah satu dari <b>sembilan</b>
                    kolom bacaan terisi &mdash; termasuk bila yang terisi hanya temuan. Yang
                    dilampaui adalah N, bukan M, dan itu benar &mdash; bukan ketidaksesuaian yang
                    perlu &ldquo;diperbaiki&rdquo;.
                </small>
            </span>
        </div>

        {{-- ============ 5. Standar tanpa pengukuran ============
                 Dipublikasikan, bukan dibuang: standar yang tidak pernah diukur
                 terbaca seperti terpenuhi padahal ia sekadar tidak ada. --}}
            @if ($targetsMasterEmpty)
                <div class="md-explain" data-testid="targets-master-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                    <span>
                        <b>Master target operasional Kernel Plant belum terisi</b>, jadi kedua kolom
                        target pada tabel di atas kosong. Seluruh angka hasil ukur tetap ditampilkan apa
                        adanya &mdash; master yang belum diisi tidak menghapus pengukuran yang sudah
                        terjadi.
                    </span>
                </div>
            @else
                {{-- BAGIAN INI DIGAMBAR WALAU ISINYA KOSONG. Bagian yang hilang
                     ketika kosong tidak dapat dibedakan dari bagian yang belum
                     pernah dibuat — pembaca harus tahu pemeriksaannya dilakukan
                     dan hasilnya nihil. Di layar ini bagian itu normalnya memuat
                     "Final Kernel Dirt", dan ialah juga tempat nama parameter
                     master yang disunting tangan menjadi terlihat. --}}
                <section class="md-card" data-testid="targets-without-metric">
                    <header class="md-card__head">
                        <h3>Standar Tanpa Pengukuran</h3>
                        <span class="md-card__hint">ada standarnya, tidak ada kolom pengukurannya &middot; ditampilkan sebagai temuan, bukan disembunyikan</span>
                    </header>
                    @if ($allTargetsMeasured)
                        <p class="md-card__hint" data-testid="targets-all-measured">
                            Seluruh standar pada master target Kernel Plant sudah punya kolom
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
                                        <th scope="col">Target / benchmark</th>
                                        <th scope="col">Rencana tindakan koreksi</th>
                                        {{-- ALASANNYA IKUT DICETAK, bukan hanya
                                             namanya: "tidak ada kolomnya"
                                             menuntut kolom baru, sementara nama
                                             master yang tidak lagi cocok
                                             menuntut keputusan penamaan. Dua
                                             tindakan yang berbeda, dan keduanya
                                             sampai di sini lewat alasan yang
                                             sama dari API. --}}
                                        <th scope="col">Mengapa belum terukur</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($targetsWithoutMetric as $target)
                                        <tr data-testid="targets-without-metric-row">
                                            <td>{{ $target['equipment_parameter'] }}</td>
                                            <td>{{ $target['target_benchmark'] }}</td>
                                            <td>{{ $target['corrective_action_plan'] }}</td>
                                            <td data-testid="targets-without-metric-reason">
                                                <b>Tidak ada kolom pengukurannya</b> di skema ini
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="md-explain" data-testid="targets-without-metric-note">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                            <span>
                                <b>Parameter di atas punya standar pada master tetapi tidak punya
                                kolom pengukuran pada formulir Kernel Plant.</b> Ditampilkan justru
                                karena itu: <b>standar yang tidak pernah diukur terbaca seperti
                                terpenuhi</b>, padahal ia sekadar tidak ada.
                                <small>
                                    <b>&ldquo;Final Kernel Dirt&rdquo; adalah penghuni normal daftar
                                    ini</b> &mdash; satu-satunya baris master yang tidak punya kolom
                                    pengukuran di mana pun pada skema ini: tidak di
                                    kernel_plant_details, tidak di tabel lain.
                                    Baris di sini juga muncul bila <b>nama parameter pada master
                                    diubah ejaannya</b> &mdash; pemetaan kolom ke parameter bersifat
                                    tetap dan tidak mencocokkan teks, jadi ejaan yang berubah membuat
                                    pasangannya terlepas. Angka hasil ukurnya tetap utuh, dan
                                    keterlepasan itu <b>sengaja terlihat di sini</b> alih-alih
                                    diam-diam menghapus standar dari tabel di atas.
                                    Daftar ini dibandingkan terhadap <b>himpunan nama parameter</b>
                                    yang terpeta, bukan terhadap jumlah entri pemetaan: dua pasangan
                                    kolom berbagi satu standar masing-masing, jadi tujuh entri
                                    pemetaan hanya menyebut lima nama parameter. Membandingkan jumlah
                                    akan membuat daftar ini terlihat kosong justru ketika ada standar
                                    yang tertinggal.
                                </small>
                            </span>
                        </div>
                    @endif
                </section>
            @endif

        @if ($hasData)

            {{-- ============ 6. Rekap per unit kernel plant ============ --}}
            <section class="md-card" data-testid="by-kernel-plant">
                <header class="md-card__head">
                    <h3>Rekap per Unit Kernel Plant</h3>
                    <span class="md-card__hint">kernel_plant_id adalah NAMA UNIT yang diketik di layar input, bukan kunci baris &middot; nama yang sama pada dua tanggal adalah satu unit dengan dua hari pencatatan</span>
                </header>
                <div class="md-recap">
                    <table class="md-table" data-testid="by-kernel-plant-table">
                        <thead>
                            <tr>
                                <th scope="col">Unit</th>
                                <th scope="col">Hari</th>
                                <th scope="col">Slot terisi</th>
                                <th scope="col">Downtime (menit)</th>
                                @foreach ($metrics as $metric)
                                    <th scope="col">{{ $metric['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($byKernelPlant as $row)
                                <tr data-testid="by-kernel-plant-row">
                                    <td>
                                        {{ ($row['kernel_plant_name'] ?? '') !== ''
                                            ? $row['kernel_plant_name']
                                            : (($row['kernel_plant_id'] ?? '') !== '' ? $row['kernel_plant_id'] : 'Belum diisi') }}
                                    </td>
                                    <td>{{ $cacah($row['day_count']) }}</td>
                                    <td>{{ $cacah($row['filled_slot_count']) }}</td>
                                    {{-- null, BUKAN 0: unit yang tidak satu pun
                                         slotnya mencatat downtime bukan unit yang
                                         tidak pernah berhenti. --}}
                                    <td @class(['is-muted' => $row['downtime_minutes'] === null])
                                        data-testid="by-kernel-plant-downtime">
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
                 kernel_plant_details.downtime_minutes adalah kolom INTEGER,
                 jadi "berapa lama stasiun berhenti" dapat dijawab di sini. --}}
            <section class="md-card" data-testid="downtime">
                <header class="md-card__head">
                    <h3>Downtime Periode</h3>
                    <span class="md-card__hint">dijumlahkan atas slot yang MENCATATNYA saja &middot; slot tanpa catatan bukan nol menit &middot; parameter ini tidak punya standar pada master</span>
                </header>
                <div class="md-kpis md-kpis--3">
                    <article class="md-kpi" data-testid="downtime-total">
                        <div class="md-kpi__top">
                            <span class="md-kpi__label">Total Berhenti</span>
                        </div>
                        {{-- TANDA PISAH untuk null, angka untuk nol yang
                             tercatat. Total null berarti tidak ada yang
                             mencatatnya; total nol berarti seseorang menyatakan
                             stasiun tidak berhenti. Mencetak angka nol untuk
                             keadaan pertama akan terbaca seperti "stasiun tidak
                             pernah berhenti". --}}
                        <p class="md-kpi__value">
                            @if ($downtime['total_minutes'] === null)
                                &mdash;
                            @else
                                {{ $cacah($downtime['total_minutes']) }} <span>menit</span>
                            @endif
                        </p>
                        <p class="md-kpi__foot">dijumlahkan hanya atas slot yang mencatat menit downtime</p>
                    </article>
                    <article class="md-kpi" data-testid="downtime-recorded-slots">
                        <div class="md-kpi__top">
                            <span class="md-kpi__label">Slot yang Mencatat</span>
                        </div>
                        <p class="md-kpi__value">{{ $cacah($downtime['recorded_slot_count']) }} <span>slot</span></p>
                        <p class="md-kpi__foot">menghitung juga slot yang mencatat nol menit &mdash; &ldquo;tercatat nol&rdquo; dan &ldquo;tidak dicatat&rdquo; dua kenyataan yang berbeda; inilah <b>penyebut</b> rata-rata di sebelah</p>
                    </article>
                    <article class="md-kpi" data-testid="downtime-average">
                        <div class="md-kpi__top">
                            <span class="md-kpi__label">Rata-rata per Slot Pencatat</span>
                        </div>
                        {{-- Penyebutnya slot yang MENCATAT, bukan seluruh slot
                             terisi. Memakai seluruh slot terisi akan membuat
                             angkanya mengecil justru seiring bertambahnya slot
                             yang tidak dicatat. Null ketika tak satu slot pun
                             mencatat — bukan nol menit. --}}
                        <p class="md-kpi__value">
                            @if ($downtime['avg_minutes_per_recorded_slot'] === null)
                                &mdash;
                            @else
                                {{ $nilai($downtime['avg_minutes_per_recorded_slot'], 1) }} <span>menit</span>
                            @endif
                        </p>
                        <p class="md-kpi__foot">penyebutnya slot yang <b>mencatat</b>, bukan seluruh slot terisi</p>
                    </article>
                </div>
                @if ($downtime['total_minutes'] === null)
                    <p class="md-card__hint" data-testid="downtime-empty">
                        <b>Belum ada satu slot pun yang mencatat menit downtime</b> pada periode dan
                        line ini. Totalnya karena itu dirender sebagai tanda pisah, bukan sebagai
                        angka nol &mdash; total nol terbaca seperti &ldquo;stasiun tidak pernah
                        berhenti&rdquo;, padahal yang benar adalah &ldquo;tidak ada yang
                        mencatatnya&rdquo;.
                    </p>
                @endif
                <div class="md-explain" data-testid="downtime-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                    <span>
                        <b>Slot yang tidak mencatat menit downtime tidak dihitung sebagai nol
                        menit.</b> Memperlakukannya nol akan membuat totalnya tetap sementara
                        kepercayaannya merosot &mdash; laporan akan tampak lebih baik karena lebih
                        sedikit yang ditulis. Sebaliknya, nilai <b>nol yang benar-benar tercatat IKUT
                        dihitung</b>, baik pada totalnya maupun pada jumlah slot pencatatnya, karena
                        nol di situ adalah pernyataan seseorang bahwa stasiun tidak berhenti pada
                        slot itu.
                        <small>
                            <b>Parameter ini tidak punya baris pada master target</b>, jadi tidak ada
                            target maupun rencana tindakan yang dapat ditampilkan bersamanya.
                            Dinyatakan agar tidak terbaca sebagai master yang belum terisi.
                            Total menit baru bermakna dibandingkan antar periode bila
                            <b>proporsi slot yang mencatatnya serupa</b> &mdash; itulah sebabnya
                            jumlah slot pencatat ikut ditampilkan di sebelah totalnya &mdash; dan
                            sebabnya rata-rata di samping memakai <b>slot pencatat</b> sebagai
                            penyebutnya, bukan seluruh slot terisi.
                            Slot yang mencatat downtime <b>tetap ikut</b> pada min, rata-rata dan
                            maks bila kolom ukurnya juga terisi; downtime adalah keterangan tambahan
                            pada slot itu, bukan penyaring.
                        </small>
                    </span>
                </div>
            </section>

            {{-- ============ 7b. Rekap temuan ============
                 SENGAJA TERPISAH dari blok di atas: satu menjawab "berapa
                 lama", satu menjawab "apa yang terlihat". Satu slot dapat
                 memuat keduanya, dan menggabungkannya membuat slot itu
                 terhitung dua kali pada satu pengertian. --}}
            <section class="md-card" data-testid="findings">
                <header class="md-card__head">
                    <h3>Temuan Lapangan</h3>
                    <span class="md-card__hint">diurutkan dari yang terbanyak &middot; dikelompokkan HARFIAH, tanpa penyeragaman ejaan, huruf besar-kecil, maupun spasi</span>
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
                            penyeragaman ejaan, tanpa penyeragaman huruf besar-kecil, dan tanpa
                            penyeragaman spasi. Dua ejaan untuk hal yang sama karena itu muncul
                            sebagai <b>dua baris</b> &mdash; itu bentuk datanya, bukan cacat laporan
                            ini. Menyeragamkannya justru akan menggabungkan hal yang penulisnya
                            mungkin memang maksudkan berbeda.
                            <small>
                                Urutannya jumlah slot terbanyak lebih dulu, lalu teksnya menaik
                                sebagai pemutus seri, supaya urutan yang sama keluar pada setiap
                                render.
                                Temuan <b>tidak digabungkan</b> dengan menit downtime di atas: satu
                                menjawab berapa lama stasiun berhenti, satu menjawab apa yang
                                terlihat. Satu slot dapat memuat keduanya, dan menggabungkannya akan
                                membuat slot itu terhitung dua kali pada satu pengertian.
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
                        <p class="md-kpi__foot">satu record = satu hari kerja satu unit kernel plant</p>
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
                 Membuka atau menutupnya TIDAK mengubah satu angka pun &mdash;
                 termasuk tidak mengubah baris total periode, yang dihitung di
                 service, bukan dari baris yang sedang terlihat. --}}
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
                                     pada hari berisi dua slot dan hari berisi
                                     dua puluh empat slot. --}}
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
                <p class="md-card__hint" data-testid="daily-total-note">
                    Baris <b>total periode</b> dihitung ulang dari seluruh slot terisi pada periode
                    ini, <b>bukan</b> dengan merata-ratakan rata-rata harian di atasnya: merata-ratakan
                    rata-rata memberi bobot yang sama pada hari yang hanya berisi dua slot dan hari
                    yang terisi penuh, sehingga angkanya akan berbeda dari kenyataan tanpa satu pun
                    tanda bahwa ada yang salah. Karena itu pula total periode tidak berubah ketika
                    rekap harian ditutup.
                </p>
            </section>
        @endif
    @endif
    </div>
</div>
