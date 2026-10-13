{{--
    Laporan Periode Effluent Plant (web) — screen-156--laporan-effluent-plant-web.

    APA YANG DILAPORKAN HALAMAN INI: KONDISI PENGOLAHAN LIMBAH sepanjang satu
    Periode Pelaporan pada SATU production line. Satu record = satu hari kerja
    satu unit Effluent Plant; satu baris = satu slot waktu dengan 14 kolom
    ukur, 3 kolom status kategoris, dan 2 kolom teks bebas. Yang dicari
    pembaca bukan total produksi melainkan kestabilan — karena itu intinya
    min/rata-rata/maks per parameter, masing-masing dengan penyebutnya
    sendiri.

    TUJUH HAL YANG MENENTUKAN TATA LETAKNYA:

    1. KELENGKAPAN PENCATATAN DIRENDER PALING ATAS, lengkap dengan ketiga
       angka pembentuk penyebutnya (unit Effluent Plant yang benar-benar
       punya record, hari yang dihitung, 24 slot kanonis per hari). Periode
       yang terisi seperlima pun menghasilkan rata-rata yang terlihat rapi,
       dan pembaca harus melihat itu SEBELUM mempercayainya — kelengkapan di
       sini adalah ISI laporan, bukan metadata. Ketika tidak ada satu slot
       pun yang diharapkan (belum ada unit tercatat, atau periode belum
       mulai), persennya dirender sebagai tanda pisah dengan keterangan
       "belum ada slot yang diharapkan" — bukan sebagai nol persen, yang akan
       terbaca seolah pencatatan gagal.

    2. KEEMPAT BELAS METRIK MEMBAWA PENYEBUTNYA MASING-MASING. Keempat belas
       kolom ukur nullable dan terisi saling bebas, jadi tiap kartu mencetak
       jumlah pembacaan yang MILIKNYA SENDIRI. Satu penyebut bersama untuk
       keempat belasnya akan mengempiskan metrik yang jarang diisi dan tetap
       menghasilkan angka yang terlihat sah — itu cacat utama yang dicari uji
       layar ini.

    3. TIGA METRIK LABORATORIUM (BOD / COD / TSS buangan akhir) ditandai
       sebagai nilai laboratorium DI TEMPAT ANGKANYA, dan penyebutnya yang
       jauh lebih kecil dinyatakan sebagai keadaan NORMAL. Ketiganya hasil
       uji laboratorium beberapa hari sekali, bukan bacaan instrumen per
       slot. Tanpa penanda itu, penyebut belasan di sebelah penyebut ratusan
       terbaca sebagai data yang hilang, dan seseorang akan "memperbaikinya"
       menjadi penyebut bersama.

    4. TIGA KOLOM STATUS ADALAH SEBARAN, BUKAN RATA-RATA. Terendah,
       rata-rata, dan tertinggi tidak punya arti untuk kolom kategoris, jadi
       ketiganya diterbitkan sebagai jumlah slot per nilai. Nilai yang
       dikenal tetapi tidak pernah terjadi tetap tampil sebagai baris NOL —
       baris yang hilang tidak dapat dibedakan dari nilai yang tidak pernah
       ada. "Tidak tercatat" adalah barisnya sendiri dan TIDAK PERNAH
       diperlakukan sebagai "Mati" atau "Berhenti". Porsinya memakai jumlah
       SLOT TERCATAT sebagai penyebut, bukan slot yang diharapkan.

    5. DUA REKAP TEKS ADALAH DUA BAGIAN TERPISAH. Catatan Perawatan menjawab
       APA YANG DIKERJAKAN, Temuan menjawab APA YANG DITEMUKAN. Satu slot
       yang mengisi kedua kolom muncul di KEDUA rekap, dan itu benar:
       menyatukan keduanya akan menghitung slot itu dua kali di dalam satu
       daftar. Keduanya tidak pernah dirata-rata maupun digrafikkan.

    6. TIDAK ADA BAGIAN DOWNTIME, dan ketiadaan itu DINYATAKAN alih-alih
       dibiarkan sebagai lubang: effluent_plant_details tidak punya satu pun
       kolom downtime. Bagian yang hilang tanpa keterangan tidak dapat
       dibedakan dari bagian yang gagal dimuat, dan seseorang akan
       "melengkapinya".

    7. DAN LAPORAN INI TIDAK MENYATAKAN KEPATUHAN LINGKUNGAN. pH, BOD, COD
       dan TSS buangan akhir adalah parameter yang batas regulasinya
       ditetapkan DI LUAR aplikasi ini, dan Effluent Plant tidak punya tabel
       master target operasional. Pernyataan itu dirender DI DEKAT blok
       Buangan Akhir — bukan sebagai catatan kaki halaman — karena angka
       lingkungan tanpa batas hukumnya mengundang pembaca memasok batas itu
       dari ingatan. Tidak ada label 'patuh'/'tidak patuh' di mana pun, dan
       tidak satu nilai pun ditandai di luar batas. Kotak keterangannya
       memakai kelas `.md-explain`, bukan `.md-threshold`: ketiadaan
       penandaan diasersi MENURUT NAMA KELAS pada HTML ter-render, jadi
       memakai kelas bernama 'threshold' untuk menjelaskan bahwa tidak ada
       ambang akan memerahkan asersi itu justru karena namanya.

    JUMLAH SLOT BERGANGGUAN pada Biogas Flare ditonjolkan tersendiri, karena
    flare yang bergangguan berarti gas tidak terbakar sebagaimana mestinya.
    Penonjolan itu BUKAN penandaan di luar batas: ia menghitung sebuah nilai
    yang memang tercatat, tidak menilai angka ukur terhadap sebuah ambang.

    PENGELOMPOKAN KARTU MENJADI ENAM BLOK (Kolam Anaerobik 1, Kolam Anaerobik
    2, Cooling Pond, Aliran, Bahan Kimia, Buangan Akhir) MURNI TATA LETAK:
    tidak satu pun angka dihitung per blok dan tidak ada total per blok,
    karena satuan di dalam satu blok pun berbeda (pH tanpa satuan, suhu °C).

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

    $coverage = $summary['coverage'] ?? null;
    $metrics = $summary['metrics'] ?? [];
    $statusDistribution = $summary['status_distribution'] ?? [];
    $maintenanceRemarks = $summary['maintenance_remarks'] ?? [];
    $temuan = $summary['findings'] ?? [];
    $dailyTrend = $summary['daily_trend'] ?? [];
    $byUnit = $summary['by_unit'] ?? [];
    $daily = $summary['daily'] ?? [];
    $dailyTotal = $summary['daily_total'] ?? null;
    $total = $summary['total'] ?? null;

    // "Ada data" = ada minimal satu slot terisi. Dipakai untuk MENOLAK
    // menggambar grafik dan tabel kosong: grafik kosong terbaca sebagai garis
    // datar hasil pengukuran, dan tabel kosong sebagai hasil ukur bernilai
    // nol — padahal tidak ada yang diukur.
    $hasData = (bool) ($summary['has_data'] ?? false);

    // Label + satuan + jumlah desimal tiap kolom ukur. Dipakai kartu metrik,
    // rekap per unit, dan rekap harian — satu sumber, sehingga satu
    // perubahan label tidak mungkin meninggalkan dua ejaan di layar. Ketiga
    // kolom status dan kedua kolom teks bebas TIDAK ADA di daftar ini dan
    // memang tidak boleh ada.
    $metrikMeta = [
        'anaerobic_pond_1_ph' => ['label' => 'pH', 'panjang' => 'pH Kolam Anaerobik 1', 'pendek' => 'pH AN-1', 'satuan' => '', 'digits' => 2],
        'anaerobic_pond_1_temp_c' => ['label' => 'Suhu', 'panjang' => 'Suhu Kolam Anaerobik 1', 'pendek' => 'Suhu AN-1 (°C)', 'satuan' => '°C', 'digits' => 1],
        'anaerobic_pond_2_ph' => ['label' => 'pH', 'panjang' => 'pH Kolam Anaerobik 2', 'pendek' => 'pH AN-2', 'satuan' => '', 'digits' => 2],
        'anaerobic_pond_2_temp_c' => ['label' => 'Suhu', 'panjang' => 'Suhu Kolam Anaerobik 2', 'pendek' => 'Suhu AN-2 (°C)', 'satuan' => '°C', 'digits' => 1],
        'cooling_pond_ph' => ['label' => 'pH', 'panjang' => 'pH Cooling Pond', 'pendek' => 'pH CP', 'satuan' => '', 'digits' => 2],
        'cooling_pond_temp_c' => ['label' => 'Suhu', 'panjang' => 'Suhu Cooling Pond', 'pendek' => 'Suhu CP (°C)', 'satuan' => '°C', 'digits' => 1],
        'biogas_flow_rate_m3h' => ['label' => 'Laju Biogas', 'panjang' => 'Laju Biogas', 'pendek' => 'Biogas (m³/jam)', 'satuan' => 'm³/jam', 'digits' => 0],
        'raw_pome_feed_rate_m3h' => ['label' => 'Laju POME Masuk', 'panjang' => 'Laju POME Masuk', 'pendek' => 'POME Masuk (m³/jam)', 'satuan' => 'm³/jam', 'digits' => 1],
        'effluent_discharge_flow_rate_m3h' => ['label' => 'Laju Buangan Keluar', 'panjang' => 'Laju Buangan Keluar', 'pendek' => 'Buangan Keluar (m³/jam)', 'satuan' => 'm³/jam', 'digits' => 1],
        'final_discharge_ph' => ['label' => 'pH Buangan Akhir', 'panjang' => 'pH Buangan Akhir', 'pendek' => 'pH Buangan', 'satuan' => '', 'digits' => 2],
        'final_discharge_bod_mgl_lab' => ['label' => 'BOD lab', 'panjang' => 'BOD Buangan Akhir', 'pendek' => 'BOD (mg/L) lab', 'satuan' => 'mg/L', 'digits' => 0],
        'final_discharge_cod_mgl_lab' => ['label' => 'COD lab', 'panjang' => 'COD Buangan Akhir', 'pendek' => 'COD (mg/L) lab', 'satuan' => 'mg/L', 'digits' => 0],
        'final_discharge_tss_mgl_lab' => ['label' => 'TSS lab', 'panjang' => 'TSS Buangan Akhir', 'pendek' => 'TSS (mg/L) lab', 'satuan' => 'mg/L', 'digits' => 0],
        'chemical_consumed_kgl' => ['label' => 'Konsumsi', 'panjang' => 'Konsumsi Bahan Kimia', 'pendek' => 'Bahan Kimia (kg/L)', 'satuan' => 'kg/L', 'digits' => 1],
    ];

    // Label manusiawi tiap nilai status. Ketiga kolom status memakai himpunan
    // nilai tertutup di basis data ($table->enum -> CHECK constraint), tetapi
    // nilai di luar peta ini tetap dicetak APA ADANYA, bukan disembunyikan:
    // nilai baru dari migrasi kelak harus terlihat, bukan hilang.
    $labelStatus = [
        'on' => 'Nyala', 'off' => 'Mati', 'fault' => 'Gangguan',
        'run' => 'Jalan', 'stop' => 'Berhenti',
    ];
    $statusTeks = fn (string $value) => $labelStatus[$value] ?? $value;

    $blokStatus = [
        ['column' => 'biogas_flare_status', 'testid' => 'status-distribution-biogas-flare', 'judul' => 'Sebaran Biogas Flare'],
        ['column' => 'dosing_pump_1_status', 'testid' => 'status-distribution-dosing-pump-1', 'judul' => 'Sebaran Pompa Dosing 1'],
        ['column' => 'sludge_dewatering_status', 'testid' => 'status-distribution-sludge-dewatering', 'judul' => 'Sebaran Sludge Dewatering'],
    ];

    // Porsi sebuah nilai status. Penyebutnya SLOT TERCATAT, bukan slot yang
    // diharapkan — yang kedua akan mencampur slot tak tersentuh ke dalam
    // porsi sebuah nilai yang justru tercatat.
    $porsi = function (int $count, int $recordedSlots) {
        if ($recordedSlots === 0) {
            return '—';
        }

        return number_format(100 * $count / $recordedSlots, 1, ',', '.').'%';
    };

    // Tinggi batang tren = 28% + 72% x (nilai - terendah) / (tertinggi -
    // terendah). Sumbu tegak SENGAJA tidak dimulai dari nol — batang
    // membandingkan tanggal satu sama lain, bukan besaran mutlak — dan hal
    // itu dinyatakan di kartunya sendiri. Ini semata skala tampilan; tidak
    // ada nilai yang ditandai aman/bahaya karenanya.
    $tinggiBatang = function ($value, $min, $max) {
        if ($value === null || $min === null || $max === null) {
            return 0;
        }

        if ((float) $max <= (float) $min) {
            return 100;
        }

        return round(28 + 72 * ((float) $value - (float) $min) / ((float) $max - (float) $min), 1);
    };
@endphp

<div class="md" data-testid="laporan-effluent-plant">

    {{-- ============ 1. Hero: periode yang sedang dibaca ============ --}}
    <section class="md-hero" data-testid="report-hero">
        <div class="md-hero__main">
            <p class="md-hero__eyebrow">Laporan Periode &middot; Stasiun Effluent Plant</p>
            <h1 class="md-hero__title">{{ $selectedPeriod['name'] ?? 'Laporan Periode Effluent Plant' }}</h1>
            <p class="md-hero__subtitle">
                @if ($selectedPeriod)
                    {{ $summary['business_unit']['name'] ?? $businessUnitName }}
                    @if ($selectedProductionLine) &middot; {{ $selectedProductionLine['name'] }} @endif
                    &middot; {{ $tgl($selectedPeriod['start_date']) }} &ndash; {{ $tgl($selectedPeriod['end_date']) }}
                @else
                    Kondisi pengolahan limbah stasiun Effluent Plant sepanjang satu Periode Pelaporan pada satu production line
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
                @if ($summary !== null)
                    <span class="md-chip md-chip--date" data-testid="hero-unit-count">
                        {{ $cacah($coverage['effluent_plant_unit_count']) }} unit Effluent Plant
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

    {{-- ============ 2. Baris filter ============
         Tombol ekspor baru dirender setelah ada summary — yaitu setelah mill,
         production line DAN periode terpilih. Sebelum itu tidak ada yang bisa
         diekspor, dan tombol yang ada tetapi tidak berguna adalah tombol yang
         akan diklik. --}}
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
        Hanya periode yang mencakup Effluent Plant yang ditampilkan, dan periode tertutup tetap dapat dibaca serta diekspor. Angka disaring menurut Production Line yang melekat pada record itu sendiri, dan keanggotaan periode mengikuti tanggal recordnya. Mengganti mill mengosongkan pilihan periode DAN production line, bukan menggantinya diam-diam.
    </x-report-filter-bar>

    <div class="md-body ld-region" wire:loading.delay.short.class="ld-region--busy" wire:loading.delay.short.attr="aria-busy" wire:target="businessUnitId,productionLineId,periodId">
    @if ($hasNoMillForAccount)
        {{-- Empty state (a): akun terikat mill tetapi users.business_unit_id
             kosong. GAGAL TERTUTUP — pemilih Mill TIDAK ditawarkan sebagai
             gantinya, dan daftar seluruh mill tidak pernah dibaca untuk
             sampai ke sini. --}}
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
        {{-- Empty state (b): Admin belum memilih mill. Tidak ada satu kueri
             record pun yang dijalankan. --}}
        <div class="md-empty" data-testid="mill-select-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
            </span>
            <p class="md-empty__title">Pilih mill terlebih dahulu</p>
            <p class="md-empty__text">
                Sebagai Admin Anda tidak terikat pada satu mill. Pilih mill pada pemilih di atas untuk
                menampilkan daftar Production Line, daftar Periode Pelaporan, dan laporan Effluent
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
             gantinya. Tombol ekspor pun belum dirender. Keterangan di bawah
             sengaja tidak memuat satu digit pun. --}}
        <div class="md-empty" data-testid="select-production-line-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h16"/><circle cx="8" cy="7" r="1.6"/><circle cx="14" cy="12" r="1.6"/><circle cx="10" cy="17" r="1.6"/></svg>
            </span>
            <p class="md-empty__title">Pilih production line terlebih dahulu</p>
            <p class="md-empty__text">
                Laporan Effluent Plant menghasilkan angka gabungan per line, dan mencampur beberapa
                production line membuat angkanya tidak bisa ditindaklanjuti. Pilih satu production line
                pada pemilih di atas untuk menampilkan laporannya. Selama belum dipilih, layar ini
                tidak menampilkan satu angka pun dan tombol ekspor belum dirender &mdash; termasuk
                tidak menampilkan angka seluruh mill sebagai penggantinya. Pilihan itu tidak pernah
                dibuat untuk Anda, bahkan ketika mill ini hanya punya satu line: memilihkannya akan
                membuat Anda menyangka sedang melihat seluruh mill.
            </p>
        </div>
    @elseif ($periods === [])
        {{-- Empty state (d): mill belum punya Periode Pelaporan yang mencakup
             Effluent Plant. Bukan galat — cukup arahkan ke layar yang
             membuatnya. Pemilih periode sengaja tetap kosong dan TIDAK diisi
             periode milik jenis stasiun lain. --}}
        <div class="md-empty" data-testid="no-period-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
            </span>
            <p class="md-empty__title">Belum ada Periode Pelaporan</p>
            <p class="md-empty__text">
                Mill ini belum memiliki Periode Pelaporan yang mencakup stasiun Effluent Plant.
                Hubungi Admin agar membuatnya terlebih dahulu di layar Kelola Periode Pelaporan, lalu
                laporan periode akan tampil di sini.
            </p>
        </div>
    @elseif ($summary !== null)

        {{-- ============ 3. Kelengkapan pencatatan — PALING ATAS ============
             Bukan catatan kaki: periode yang terisi seperlima pun
             menghasilkan rata-rata yang terlihat rapi, jadi pembaca harus
             melihat kelengkapannya lebih dulu. --}}
        <section class="md-card" data-testid="coverage">
            <header class="md-card__head">
                <h3>Kelengkapan Pencatatan</h3>
                <span class="md-card__hint">baca ini lebih dulu &mdash; seluruh angka di bawah bersandar padanya</span>
            </header>
            <div class="md-budget">
                <div class="md-budget__row">
                    <div class="md-budget__label">
                        <span>Slot waktu terisi sepanjang periode</span>
                        <span class="md-budget__nums" data-testid="coverage-slots">
                            <strong>{{ $cacah($coverage['filled_slots']) }}</strong>
                            dari {{ $cacah($coverage['expected_slots']) }} slot
                        </span>
                    </div>
                    {{-- Persen TANPA penyebut dirender sebagai tanda pisah,
                         bukan angka: persentase nol mengklaim ada yang
                         diharapkan lalu tidak tercatat, padahal belum ada
                         satu slot pun yang diharapkan. --}}
                    <span class="md-budget__pct" data-testid="coverage-percent">
                        @if ((int) $coverage['expected_slots'] === 0)
                            &mdash;
                        @else
                            {{ $nilai($coverage['coverage_percent'], 1) }}%
                        @endif
                    </span>
                    <div class="md-bar md-bar--lg">
                        <span style="width: {{ (int) $coverage['expected_slots'] === 0 ? 0 : min(100, (float) $coverage['coverage_percent']) }}%"></span>
                    </div>
                </div>
                @if ((int) $coverage['expected_slots'] === 0)
                    <p class="md-budget__note" data-testid="coverage-no-expected-note">
                        Belum ada slot yang diharapkan &mdash; belum ada unit Effluent Plant yang
                        tercatat pada periode dan line ini, atau periodenya belum mulai. Persennya
                        karena itu dirender sebagai tanda pisah, bukan sebagai nol persen yang akan
                        terbaca seolah pencatatan gagal.
                    </p>
                @elseif ((int) $coverage['days_counted'] === 0)
                    <p class="md-budget__note" data-testid="coverage-not-started-note">
                        Periode belum mulai, sehingga belum ada hari yang dapat dijadikan pembagi.
                    </p>
                @elseif ($coverage['period_running'])
                    {{-- URUTANNYA PENTING: days_counted === 0 DIPERIKSA LEBIH
                         DULU di atas. ReportPeriodDays::isRunning() juga true
                         untuk periode yang BELUM MULAI, jadi memeriksa
                         period_running lebih dulu akan menjelaskan periode
                         yang belum mulai sebagai "sedang berjalan". Keterangan
                         ini menyebut HARI TERAKHIR YANG TERHITUNG, bukan
                         tanggal akhir periode: tanggal akhir yang belum tiba
                         akan terbaca seperti hari yang sudah ikut dihitung. --}}
                    <p class="md-budget__note" data-testid="coverage-running-note">
                        Dihitung sampai {{ $tgl($countedUntil) }}, periode masih berjalan
                        ({{ $cacah($coverage['days_counted']) }} dari
                        {{ $cacah($coverage['days_in_period']) }} hari periode sudah lewat). Hari yang
                        belum terjadi tidak mungkin tercatat, jadi tidak ikut menjadi penyebut.
                    </p>
                @endif
                <p class="md-card__hint" data-testid="coverage-formula">
                    Slot yang diharapkan = {{ $cacah($coverage['effluent_plant_unit_count']) }} unit
                    Effluent Plant &times; {{ $cacah($coverage['days_counted']) }} hari{{ $coverage['period_running'] && (int) $coverage['days_counted'] > 0 ? ' (sampai hari ini)' : '' }}
                    &times; {{ $cacah($coverage['slots_per_unit_per_day']) }} slot
                    = {{ $cacah($coverage['expected_slots']) }}.
                    Jumlah unit adalah unit yang BENAR-BENAR punya record pada periode dan line ini,
                    bukan jumlah stasiun terdaftar; angka
                    {{ $cacah($coverage['slots_per_unit_per_day']) }} slot per hari diambil dari kisi
                    slot kanonis layar input itu sendiri, bukan dikarang di sini.
                </p>
            </div>
            <div class="md-explain" data-testid="coverage-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                <span>
                    <b>Seluruh angka di bawah dihitung dari pembacaan yang ada</b>, bukan dari periode
                    penuh: slot yang tidak terisi <b>tidak</b> diperlakukan sebagai nol, melainkan
                    tidak ikut dihitung sama sekali.
                    <small>
                        Sebuah slot dihitung <b>terisi</b> bila minimal satu dari <b>sembilan belas</b>
                        kolom bacaannya diisi &mdash; keempat belas kolom ukur, ketiga kolom status,
                        atau kedua kolom teks bebas. Definisinya dipinjam dari layar input itu sendiri,
                        bukan diturunkan ulang di sini, supaya laporan dan formulir tidak pernah
                        berbeda pendapat tentang apa yang tercatat. Karena itu jumlah slot terisi dapat
                        <b>lebih besar</b> daripada jumlah pembacaan metrik mana pun: slot yang hanya
                        mengisi temuan tetap tercatat dan tidak menambah penyebut satu metrik pun. Itu
                        benar, bukan ketidaksesuaian yang perlu &ldquo;diperbaiki&rdquo;. Record
                        berstatus draft IKUT terhitung pada seluruh angka laporan ini.
                    </small>
                </span>
            </div>
        </section>

        @if (! $hasData)
            {{-- Empty state (e): periode + line valid tetapi tanpa satu slot
                 terisi. Kelengkapan di atas sudah menampilkannya; kartu
                 metrik TETAP digambar dengan nilai tidak tersedia dan
                 penyebut nol — kartu yang hilang terbaca sebagai "parameter
                 itu tidak ada" — sementara kedua grafik tren, rekap per unit
                 dan rekap harian TIDAK digambar: grafik kosong terbaca
                 sebagai garis datar hasil pengukuran. --}}
            <div class="md-empty" data-testid="empty-state">
                <span class="md-empty__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19h16M7 16V9M12 16V5M17 16v-4"/></svg>
                </span>
                <p class="md-empty__title">Belum ada data pada periode dan line ini</p>
                <p class="md-empty__text">
                    Tidak ada satu pun slot Effluent Plant yang terisi pada rentang periode ini di
                    {{ $selectedProductionLine['name'] ?? 'production line ini' }}, sehingga seluruh
                    terendah, rata-rata dan tertinggi ditampilkan sebagai tidak tersedia &mdash; bukan
                    sebagai nol. Kartu parameter di bawah tetap digambar lengkap dengan penyebut nol
                    supaya terlihat bahwa parameternya ada dan tidak terukur, sementara kedua grafik
                    tren, rekap per unit dan rekap harian tidak digambar.
                </p>
            </div>
        @endif

        {{-- ============ 4. Keempat belas kartu metrik, enam blok ============
             PENGELOMPOKANNYA MURNI TATA LETAK: tidak satu pun angka dihitung
             per blok dan tidak ada total per blok, karena satuan di dalam satu
             blok pun berbeda (pH tanpa satuan, suhu °C).

             MARKUP KARTUNYA DITULIS SEKALI DI SINI dan dipakai keempat belas
             kali lewat perulangan, bukan disalin per blok: keempat belas kartu
             HARUS mencetak penyebutnya masing-masing, dan satu salinan markup
             berarti tidak ada satu blok pun yang bisa diam-diam kehilangan
             penyebutnya atau mewarisi penyebut blok lain. --}}
        @foreach ([
            ['row' => 'md-row--3', 'blok' => [
                ['testid' => 'metric-block-anaerobic-pond-1', 'judul' => 'Kolam Anaerobik 1', 'hint' => 'kolam pertama rangkaian anaerobik', 'grid' => 'md-kpis--2', 'kolom' => ['anaerobic_pond_1_ph', 'anaerobic_pond_1_temp_c'], 'compliance' => false],
                ['testid' => 'metric-block-anaerobic-pond-2', 'judul' => 'Kolam Anaerobik 2', 'hint' => 'kolam kedua rangkaian anaerobik', 'grid' => 'md-kpis--2', 'kolom' => ['anaerobic_pond_2_ph', 'anaerobic_pond_2_temp_c'], 'compliance' => false],
                ['testid' => 'metric-block-cooling-pond', 'judul' => 'Cooling Pond', 'hint' => 'kolam pendingin sebelum buangan akhir', 'grid' => 'md-kpis--2', 'kolom' => ['cooling_pond_ph', 'cooling_pond_temp_c'], 'compliance' => false],
            ]],
            ['row' => 'md-row--2-1', 'blok' => [
                ['testid' => 'metric-block-flow', 'judul' => 'Aliran', 'hint' => 'laju sepanjang periode — bukan jumlah produksi', 'grid' => 'md-kpis--3', 'kolom' => ['biogas_flow_rate_m3h', 'raw_pome_feed_rate_m3h', 'effluent_discharge_flow_rate_m3h'], 'compliance' => false],
                ['testid' => 'metric-block-chemical', 'judul' => 'Bahan Kimia', 'hint' => 'konsumsi per slot waktu', 'grid' => 'md-kpis--2', 'kolom' => ['chemical_consumed_kgl'], 'compliance' => false],
            ]],
            ['row' => '', 'blok' => [
                ['testid' => 'metric-block-final-discharge', 'judul' => 'Buangan Akhir', 'hint' => 'pH per slot waktu · BOD / COD / TSS dari hasil laboratorium', 'grid' => 'md-kpis--4', 'kolom' => ['final_discharge_ph', 'final_discharge_bod_mgl_lab', 'final_discharge_cod_mgl_lab', 'final_discharge_tss_mgl_lab'], 'compliance' => true],
            ]],
        ] as $baris)
            <div class="md-row {{ $baris['row'] }}">
                @foreach ($baris['blok'] as $blok)
                    <section class="md-card" data-testid="{{ $blok['testid'] }}">
                        <header class="md-card__head">
                            <h3>{{ $blok['judul'] }}</h3>
                            <span class="md-card__hint">{{ $blok['hint'] }}</span>
                        </header>
                        <div class="md-kpis {{ $blok['grid'] }}">
                            @foreach ($blok['kolom'] as $column)
                                @php
                                    $meta = $metrikMeta[$column];
                                    $metric = $metrics[$column];
                                @endphp
                                <article class="md-kpi" data-testid="metric-card" data-metric="{{ $column }}">
                                    <div class="md-kpi__top">
                                        <span class="md-kpi__label">{{ $meta['label'] }}</span>
                                        {{-- PENANDA NILAI LABORATORIUM, di
                                             tempat angkanya. Penyebut ketiga
                                             metrik ini memang jauh lebih kecil,
                                             dan itu keadaan NORMAL: hasil uji
                                             laboratorium diambil beberapa hari
                                             sekali, bukan per slot waktu. Tanpa
                                             penanda ini penyebut belasan di
                                             sebelah penyebut ratusan terbaca
                                             sebagai data yang hilang, dan
                                             seseorang akan "memperbaikinya"
                                             menjadi penyebut bersama. Diturunkan
                                             dari `is_lab` pada respons, tidak
                                             dipaku di markup. --}}
                                        @if ($metric['is_lab'])
                                            <span class="md-pill" data-testid="metric-lab-badge">nilai laboratorium</span>
                                        @endif
                                    </div>
                                    {{-- null SELALU "tidak tersedia", TIDAK
                                         PERNAH 0: nol berarti terukur dan
                                         hasilnya nol, tidak tersedia berarti
                                         tidak pernah diukur. --}}
                                    <p class="md-kpi__value">
                                        <span data-testid="metric-avg">{{ $nilai($metric['avg'], $meta['digits']) }}</span>
                                        <span>{{ $meta['satuan'] !== '' ? $meta['satuan'].' rata-rata' : 'rata-rata' }}</span>
                                    </p>
                                    <p class="md-kpi__meta" data-testid="metric-range">
                                        terendah {{ $nilai($metric['min'], $meta['digits']) }} &middot;
                                        tertinggi {{ $nilai($metric['max'], $meta['digits']) }}
                                    </p>
                                    {{-- PENYEBUT MILIK KARTU INI SENDIRI, bukan
                                         penyebut bersama. Satu penyebut bersama
                                         untuk keempat belas metrik akan
                                         mengempiskan metrik yang jarang diisi
                                         dan tetap menghasilkan angka yang
                                         terlihat sah — cacat utama yang dicari
                                         uji layar ini. --}}
                                    <p class="md-kpi__foot" data-testid="metric-denominator">
                                        dari <b>{{ $cacah($metric['reading_count']) }}</b> pembacaan
                                        <small>(slot yang mengisi kolom ini sendiri; slot yang tidak mengisinya tidak mengukurnya nol — ia tidak mengukurnya sama sekali)</small>
                                    </p>
                                </article>
                            @endforeach
                        </div>
                        @if ($blok['compliance'])
                            {{-- PERNYATAAN KEPATUHAN LINGKUNGAN DIRENDER DI
                                 DALAM BLOK INI, tepat di bawah keempat
                                 angkanya — bukan sebagai catatan kaki halaman.
                                 Angka lingkungan tanpa batas hukumnya
                                 mengundang pembaca memasok batas itu dari
                                 ingatan, dan catatan kaki di ujung halaman
                                 dibaca terakhir atau tidak dibaca sama sekali. --}}
                            <div class="md-explain" data-testid="environmental-compliance-note">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                                <span>
                                    <b>Laporan ini tidak menyatakan kepatuhan lingkungan.</b> pH, BOD,
                                    COD dan TSS buangan akhir adalah parameter yang <b>batas
                                    regulasinya ditetapkan di luar aplikasi ini</b>, dan Effluent Plant
                                    tidak punya tabel master target operasional. Angka di atas
                                    ditampilkan apa adanya: laporan ini tidak menyatakan patuh maupun
                                    tidak patuh, dan tidak menandai satu pun nilai sebagai di luar
                                    batas.
                                    <small>
                                        Ambang yang diturunkan dari data periode itu sendiri berisiko
                                        terbaca sebagai batas aman padahal ia hanya turunan statistik
                                        atas data yang justru sedang dinilainya; penilaiannya ada pada
                                        manusia yang memegang baku mutunya.
                                        <b>Penyebut ketiga nilai laboratorium memang jauh lebih
                                        kecil</b> daripada penyebut pH buangan akhir, dan itu keadaan
                                        normal: BOD, COD dan TSS diperiksa laboratorium beberapa hari
                                        sekali, bukan per slot waktu. Rata-rata dari belasan pembacaan
                                        tidak boleh terlihat sama meyakinkan dengan rata-rata dari
                                        ratusan pembacaan, dan itulah sebabnya tiap kartu mencetak
                                        penyebutnya sendiri alih-alih berbagi satu penyebut.
                                    </small>
                                </span>
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>
        @endforeach

        {{-- ============ 5. Tiga sebaran status kategoris ============ --}}
        <div class="md-row md-row--3">
            @foreach ($blokStatus as $blok)
                @php
                    $dist = $statusDistribution[$blok['column']] ?? ['known_values' => [], 'counts' => [], 'not_recorded' => 0, 'recorded_slots' => 0];
                @endphp
                <section class="md-card" data-testid="{{ $blok['testid'] }}">
                    <header class="md-card__head">
                        <h3>{{ $blok['judul'] }}</h3>
                        <span class="md-card__hint">
                            {{ $cacah(count($dist['known_values'])) }} nilai dikenal &middot;
                            dasar {{ $cacah($dist['recorded_slots']) }} slot tercatat
                        </span>
                    </header>
                    <div class="md-recap">
                        <table class="md-table" data-testid="{{ $blok['testid'] }}-table">
                            <thead>
                                <tr>
                                    <th scope="col">Nilai</th>
                                    <th scope="col">Jumlah slot</th>
                                    <th scope="col">Porsi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- NILAI DIKENAL YANG TIDAK PERNAH MUNCUL
                                     TETAP DIRENDER DENGAN NOL. Baris yang
                                     hilang tidak dapat dibedakan dari nilai
                                     yang tidak pernah ada di basis data. --}}
                                @foreach ($dist['known_values'] as $value)
                                    <tr data-testid="status-row" data-status-value="{{ $value }}">
                                        <td>{{ $statusTeks($value) }}</td>
                                        <td>{{ $cacah($dist['counts'][$value] ?? 0) }}</td>
                                        <td class="is-muted">{{ $porsi((int) ($dist['counts'][$value] ?? 0), (int) $dist['recorded_slots']) }}</td>
                                    </tr>
                                @endforeach
                                {{-- BARISNYA SENDIRI, SELALU. Slot yang tidak
                                     mencatat kolom ini TIDAK PERNAH
                                     diperlakukan sebagai 'Mati' maupun
                                     'Berhenti': melakukannya akan melaporkan
                                     flare mati atau pompa berhenti yang tidak
                                     pernah terjadi. --}}
                                <tr data-testid="status-not-recorded">
                                    <td>Tidak tercatat</td>
                                    <td>{{ $cacah($dist['not_recorded']) }}</td>
                                    <td class="is-muted">{{ $porsi((int) $dist['not_recorded'], (int) $dist['recorded_slots']) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr data-testid="status-recorded-total">
                                    <td>Seluruh slot tercatat</td>
                                    <td>{{ $cacah($dist['recorded_slots']) }}</td>
                                    <td>{{ (int) $dist['recorded_slots'] === 0 ? '—' : '100,0%' }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    @if ($blok['column'] === 'biogas_flare_status')
                        {{-- JUMLAH SLOT BERGANGGUAN DITONJOLKAN TERSENDIRI:
                             flare yang bergangguan berarti gas tidak terbakar
                             sebagaimana mestinya pada slot-slot itu.
                             Penonjolan ini BUKAN penandaan di luar batas — ia
                             menghitung sebuah nilai yang memang tercatat,
                             tidak menilai angka ukur terhadap sebuah ambang,
                             dan karena itu tidak memakai satu pun kelas
                             peringatan. --}}
                        <p class="md-card__hint" data-testid="biogas-flare-fault-count">
                            <b>{{ $cacah($dist['counts']['fault'] ?? 0) }} slot bergangguan</b> &mdash;
                            pada slot-slot itu gas tidak terbakar sebagaimana mestinya, jadi jumlahnya
                            dinyatakan tersendiri di samping sebarannya.
                        </p>
                    @endif
                </section>
            @endforeach
        </div>

        {{-- Keterangan bersama ketiga sebaran, DAN pernyataan ketiadaan
             bagian downtime. Keduanya ada di satu kotak karena keduanya
             menjawab pertanyaan yang sama bentuknya: mengapa sesuatu yang
             pembaca cari tidak ada di layar ini. --}}
        <div class="md-explain" data-testid="status-distribution-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
            <span>
                <b>Ketiga kolom di atas kategoris</b>: terendah, rata-rata dan tertinggi tidak punya
                arti untuknya, jadi ketiganya diterbitkan sebagai sebaran jumlah slot per nilai &mdash;
                tidak satu pun dari ketiganya muncul di kartu metrik maupun di grafik tren.
                <small>
                    Sebaran disusun dengan <b>mengelompokkan nilai yang benar-benar ada</b>, lalu
                    melengkapi nilai yang dikenal tetapi tidak muncul dengan jumlah <b>nol</b> &mdash;
                    itulah sebabnya sebuah nilai yang tidak pernah terjadi sepanjang periode tetap
                    tampil sebagai baris nol, bukan hilang dari daftar. <b>&ldquo;Tidak
                    tercatat&rdquo; adalah keadaan tersendiri</b> dan tidak pernah diperlakukan sebagai
                    &ldquo;Mati&rdquo; atau &ldquo;Berhenti&rdquo;: &ldquo;tidak kami tulis&rdquo; dan
                    &ldquo;memang tidak jalan&rdquo; adalah dua kenyataan yang berbeda akibatnya.
                    <b>Porsinya memakai jumlah slot tercatat sebagai penyebut</b>, bukan jumlah slot
                    yang diharapkan &mdash; yang kedua akan mencampur slot tak tersentuh ke dalam porsi
                    sebuah nilai yang justru tercatat.
                </small>
            </span>
        </div>

        <div class="md-explain" data-testid="no-downtime-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
            <span>
                <b>Stasiun ini tidak mencatat lama berhenti sama sekali</b>, jadi laporan ini memang
                tidak punya bagiannya &mdash; tidak ada kolomnya di formulir Effluent Plant, dan
                ketiadaannya disengaja, bukan terlewat atau gagal dimuat.
                <small>
                    Dinyatakan di sini justru karena bagian yang hilang tanpa keterangan tidak dapat
                    dibedakan dari bagian yang gagal dimuat &mdash; dan karena laporan stasiun lain
                    memilikinya, sehingga pembaca yang datang dari sana akan mencarinya.
                    <b>Tidak ada pula satu nilai yang ditandai di luar batas</b> di halaman ini:
                    Effluent Plant tidak punya tabel master target operasional, jadi tidak ada satu
                    angka pun yang bisa dibandingkan terhadap standar yang ditetapkan siapa pun.
                </small>
            </span>
        </div>

        {{-- ============ 6. Rekap Catatan Perawatan ============
             DUA BAGIAN TERPISAH dengan Rekap Temuan di bawahnya, dan itu
             disengaja: satu menjawab apa yang dikerjakan, satu menjawab apa
             yang ditemukan. --}}
        <section class="md-card" data-testid="maintenance-remarks">
            <header class="md-card__head">
                <h3>Rekap Catatan Perawatan</h3>
                <span class="md-card__hint">
                    {{ $cacah(count($maintenanceRemarks)) }} slot mengisi kolom catatan perawatan sepanjang periode
                </span>
            </header>
            @if ($maintenanceRemarks === [])
                <p class="md-card__hint" data-testid="maintenance-remarks-empty">
                    Tidak ada satu pun catatan perawatan tercatat pada periode dan line ini. Ini
                    dinyatakan sebagai keterangan, bukan tabel kosong tanpa penjelasan &mdash; tabel
                    kosong tidak membedakan &ldquo;tidak ada perawatan&rdquo; dari &ldquo;tidak ada
                    yang mencatatnya&rdquo;.
                </p>
            @else
                <div class="md-notes">
                    @foreach ($maintenanceRemarks as $entry)
                        <div class="md-notes__item" data-testid="maintenance-remark-row">
                            <span class="md-pill">{{ $tgl($entry['date']) }} &middot; {{ $entry['time_slot'] }}</span>
                            <p>{{ $entry['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
            <div class="md-explain" data-testid="maintenance-remarks-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                <span>
                    Daftar ini menjawab <b>apa yang dikerjakan</b>. Isinya ditampilkan apa adanya
                    beserta tanggal dan slot waktunya &mdash; <b>tidak pernah dirata-rata maupun
                    digrafikkan</b>, karena teks bebas tidak punya rata-rata.
                </span>
            </div>
        </section>

        {{-- ============ 7. Rekap Temuan ============ --}}
        <section class="md-card" data-testid="findings">
            <header class="md-card__head">
                <h3>Rekap Temuan</h3>
                <span class="md-card__hint">
                    {{ $cacah(count($temuan)) }} slot mengisi kolom temuan sepanjang periode
                </span>
            </header>
            @if ($temuan === [])
                <p class="md-card__hint" data-testid="findings-empty">
                    Tidak ada satu pun temuan tercatat pada periode dan line ini. Ini dinyatakan
                    sebagai keterangan tersendiri, bukan digabung ke bagian catatan perawatan dan
                    bukan tabel kosong tanpa penjelasan.
                </p>
            @else
                <div class="md-notes">
                    @foreach ($temuan as $entry)
                        <div class="md-notes__item" data-testid="finding-row">
                            <span class="md-pill">{{ $tgl($entry['date']) }} &middot; {{ $entry['time_slot'] }}</span>
                            <p>{{ $entry['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
            <div class="md-explain" data-testid="findings-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                <span>
                    Daftar ini menjawab <b>apa yang ditemukan</b> &mdash; pertanyaan yang berbeda dari
                    Rekap Catatan Perawatan di atasnya, dan karena itu keduanya <b>dua bagian
                    terpisah</b> yang tidak pernah disatukan.
                    <small>
                        Satu slot yang mengisi <b>kedua</b> kolom muncul pada <b>kedua</b> rekap, dan
                        itu benar &mdash; bukan penghitungan ganda. Menyatukan keduanya menjadi satu
                        daftarlah yang akan menghitung slot itu dua kali, di dalam satu pengertian yang
                        sama.
                    </small>
                </span>
            </div>
        </section>

        @if ($hasData)

            {{-- ============ 8. Dua tren harian berdampingan ============
                 HANYA DUA: laju POME masuk dan pH buangan akhir. Ketiga nilai
                 laboratorium SENGAJA tidak digrafikkan harian — dengan
                 penyebut sekecil itu garisnya akan lebih banyak bolongnya
                 daripada datanya, dan garis yang menyambung dua titik
                 berjauhan menyiratkan pengukuran yang tidak pernah
                 dilakukan. --}}
            <div class="md-row md-row--2">
                @foreach ([
                    ['column' => 'raw_pome_feed_rate_m3h', 'testid' => 'daily-trend-raw-pome-feed', 'judul' => 'Tren Harian Laju POME Masuk', 'satuan' => 'm³/jam', 'digits' => 1],
                    ['column' => 'final_discharge_ph', 'testid' => 'daily-trend-final-discharge-ph', 'judul' => 'Tren Harian pH Buangan Akhir', 'satuan' => '', 'digits' => 2],
                ] as $tren)
                    @php
                        $titik = $dailyTrend[$tren['column']] ?? [];
                        $nilaiTren = array_values(array_filter(
                            array_map(fn ($row) => $row['avg'], $titik),
                            fn ($value) => $value !== null,
                        ));
                        $minTren = $nilaiTren === [] ? null : min($nilaiTren);
                        $maksTren = $nilaiTren === [] ? null : max($nilaiTren);
                        $metrikTren = $metrics[$tren['column']];
                    @endphp
                    <section class="md-card" data-testid="{{ $tren['testid'] }}">
                        <header class="md-card__head">
                            <h3>{{ $tren['judul'] }}</h3>
                            <span class="md-card__hint">
                                Rata-rata per tanggal{{ $tren['satuan'] !== '' ? ', dalam '.$tren['satuan'] : '' }}
                                &middot; {{ $cacah(count($titik)) }} tanggal berdata
                            </span>
                        </header>
                        <div class="md-trendchart md-trendchart--days">
                            @foreach ($titik as $row)
                                {{-- Tanpa modifier warna apa pun: tidak ada
                                     batang yang ditandai "rendah", "tinggi",
                                     maupun di luar batas di layar ini. Titik
                                     tanpa pembacaan bernilai tidak tersedia,
                                     BUKAN nol — batangnya karena itu kosong
                                     dan bukan batang nol yang terbaca sebagai
                                     hasil ukur. --}}
                                <div class="md-trendchart__col" data-testid="{{ $tren['testid'] }}-col-{{ $row['date'] }}">
                                    <span class="md-trendchart__val">{{ $nilai($row['avg'], $tren['digits']) }}</span>
                                    <div class="md-trendchart__bar" style="height: {{ $tinggiBatang($row['avg'], $minTren, $maksTren) }}%"></div>
                                    <span class="md-trendchart__lbl">{{ $tglPendek($row['date']) }}</span>
                                </div>
                            @endforeach
                        </div>
                        @if (count($titik) > 10)
                            <p class="md-scrollhint" data-testid="{{ $tren['testid'] }}-scroll-hint">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M15 8l4 4-4 4M9 8l-4 4 4 4"/></svg>
                                Geser mendatar untuk melihat seluruh tanggal &mdash; tiap kolom selebar
                                labelnya supaya label tanggal tidak bertumpuk.
                            </p>
                        @endif
                        <ul class="md-legend">
                            <li class="md-legend__item">
                                Terendah {{ $nilai($minTren, $tren['digits']) }} &middot;
                                tertinggi {{ $nilai($maksTren, $tren['digits']) }}
                                <small>(dari rata-rata harian)</small>
                            </li>
                            <li class="md-legend__item">
                                Rata-rata periode {{ $nilai($metrikTren['avg'], $tren['digits']) }}
                                <small>(berbobot {{ $cacah($metrikTren['reading_count']) }} pembacaan, dihitung ulang atas seluruh slot &mdash; bukan rata-rata dari rata-rata harian)</small>
                            </li>
                            <li class="md-legend__item">
                                <small>Sumbu tegak tidak dimulai dari nol &mdash; batang membandingkan tanggal, bukan besaran mutlak. Tidak ada rentang aman yang digambar di sini.</small>
                            </li>
                        </ul>
                    </section>
                @endforeach
            </div>

            {{-- ============ 9. Rekap per unit Effluent Plant ============ --}}
            <section class="md-card" data-testid="by-unit">
                <header class="md-card__head">
                    <h3>Rekap per Unit Effluent Plant</h3>
                    <span class="md-card__hint">
                        angka periode di atas menggabungkan seluruh unit &mdash; tabel ini menunjukkan
                        sebarannya &middot; effluent_plant_id adalah NAMA UNIT yang diketik di layar
                        input, bukan kunci baris
                    </span>
                </header>
                <div class="md-recap">
                    <table class="md-table" data-testid="by-unit-table">
                        <thead>
                            <tr>
                                <th scope="col">Unit Effluent Plant</th>
                                <th scope="col">Slot terisi</th>
                                @foreach ($metrikMeta as $meta)
                                    <th scope="col">{{ $meta['pendek'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            {{-- UNIT TANPA SATU PEMBACAAN PUN TETAP DIRENDER,
                                 dengan slot terisi nol dan rata-rata tidak
                                 tersedia: membuangnya akan menyembunyikan
                                 justru unit yang tidak pernah ditulis — yang
                                 paling perlu dilihat — dan unit itu tetap ikut
                                 menambah jumlah unit pada penyebut
                                 kelengkapan. --}}
                            @foreach ($byUnit as $row)
                                <tr data-testid="by-unit-row">
                                    <td>{{ ($row['effluent_plant_id'] ?? '') !== '' ? $row['effluent_plant_id'] : 'Belum diisi' }}</td>
                                    <td data-testid="by-unit-reading-rows">{{ $cacah($row['reading_rows']) }}</td>
                                    @foreach ($metrikMeta as $column => $meta)
                                        <td @class(['is-muted' => ($row['metrics'][$column]['avg'] ?? null) === null])>
                                            {{ $nilai($row['metrics'][$column]['avg'] ?? null, $meta['digits']) }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            {{-- GABUNGAN SELURUH UNIT, dihitung ulang atas
                                 seluruh slot periode — bukan rata-rata dari
                                 rata-rata per unit, yang akan memberi bobot
                                 sama kepada unit berisi dua slot dan unit
                                 berisi dua ratus. Jumlah slot di sini sama
                                 dengan jumlah slot terisi pada kelengkapan di
                                 atas, dan kolom slot terisi per unit menjumlah
                                 ke angka itu. --}}
                            <tr data-testid="by-unit-row-total">
                                <td>Gabungan {{ $cacah($coverage['effluent_plant_unit_count']) }} unit</td>
                                <td>{{ $cacah($total['reading_rows']) }}</td>
                                @foreach ($metrikMeta as $column => $meta)
                                    <td @class(['is-muted' => $metrics[$column]['avg'] === null])>
                                        {{ $nilai($metrics[$column]['avg'], $meta['digits']) }}
                                    </td>
                                @endforeach
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            {{-- ============ 10. Rekap harian (dapat ditutup) ============
                 Periode panjang menghasilkan puluhan baris, jadi tabelnya
                 dapat ditutup agar kelengkapan dan kartu metrik tetap
                 terbaca. MENUTUPNYA TIDAK MENGUBAH SATU ANGKA PUN: seluruh
                 angka di halaman ini dihitung di service, bukan dari baris
                 yang sedang terlihat. --}}
            <details class="md-details" data-testid="daily-recap-details">
                <summary data-testid="daily-recap-toggle">
                    Rekap Harian
                    <small>
                        {{ $cacah(count($daily)) }} baris &middot; satu baris per tanggal yang punya
                        minimal satu slot terisi &middot; klik untuk membuka atau menutup
                    </small>
                </summary>
                <div class="md-recap">
                    <table class="md-table" data-testid="daily-table">
                        <thead>
                            <tr>
                                <th scope="col">Tanggal</th>
                                <th scope="col">Slot terisi</th>
                                @foreach ($metrikMeta as $meta)
                                    <th scope="col">{{ $meta['pendek'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Tanggal tanpa satu slot terisi TIDAK mendapat
                                 baris: baris nol akan terbaca sebagai
                                 "terukur nol". Tanggal itu tetap terhitung
                                 sebagai hari ber-record pada kelengkapan
                                 record di bawah. --}}
                            @foreach ($daily as $row)
                                <tr data-testid="daily-row-{{ $row['date'] }}">
                                    <td>{{ $tgl($row['date']) }}</td>
                                    <td>{{ $cacah($row['reading_rows']) }}</td>
                                    @foreach ($metrikMeta as $column => $meta)
                                        <td @class(['is-muted' => ($row['metrics_avg'][$column]['avg'] ?? null) === null])>
                                            {{ $nilai($row['metrics_avg'][$column]['avg'] ?? null, $meta['digits']) }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            {{-- TOTAL PERIODE DIHITUNG ULANG ATAS SELURUH
                                 SLOT, bukan merata-ratakan rata-rata harian:
                                 rata-rata dari rata-rata memberi bobot sama
                                 pada hari berisi dua slot dan hari berisi dua
                                 puluh empat slot. Tiap sel membawa jumlah
                                 pembacaannya sendiri. --}}
                            <tr data-testid="daily-row-total">
                                <td>Total periode</td>
                                <td>{{ $cacah($dailyTotal['reading_rows']) }}</td>
                                @foreach ($metrikMeta as $column => $meta)
                                    <td @class(['is-muted' => $metrics[$column]['avg'] === null])>
                                        {{ $nilai($metrics[$column]['avg'], $meta['digits']) }}
                                        <small class="is-muted">(n={{ $cacah($metrics[$column]['reading_count']) }})</small>
                                    </td>
                                @endforeach
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </details>

            {{-- ============ 11. Kelengkapan record ============ --}}
            <section class="md-card" data-testid="completeness">
                <header class="md-card__head">
                    <h3>Kelengkapan Record</h3>
                    <span class="md-card__hint">seluruh angka di bawah IKUT terhitung pada laporan &mdash; tidak satu pun menjadi penyaring</span>
                </header>
                <div class="md-kpis md-kpis--2">
                    <article class="md-kpi" data-testid="days-with-records">
                        <div class="md-kpi__top">
                            <span class="md-kpi__label">Hari Ber-record</span>
                        </div>
                        <p class="md-kpi__value">{{ $cacah($total['days_with_records']) }} <span>hari</span></p>
                        <p class="md-kpi__foot">
                            sebuah hari yang punya record tetapi seluruh slotnya kosong tetap terhitung
                            di sini &mdash; ia tetap hari yang dicatat orang &mdash; walau tidak
                            mendapat baris pada rekap harian
                        </p>
                    </article>
                    <article class="md-kpi" data-testid="total-reading-rows">
                        <div class="md-kpi__top">
                            <span class="md-kpi__label">Slot Terisi</span>
                        </div>
                        <p class="md-kpi__value">{{ $cacah($total['reading_rows']) }} <span>slot</span></p>
                        <p class="md-kpi__foot">
                            satu definisi, satu jawaban: angka ini sama dengan slot terisi pada
                            kelengkapan di atas, dan baik jumlah slot per unit maupun jumlah slot per
                            hari menjumlah ke angka yang sama
                        </p>
                    </article>
                </div>
            </section>
        @endif
    @endif
    </div>
</div>
