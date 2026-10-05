{{--
    screen-143--laporan-weighbridge-web — Laporan Periode Weighbridge.

    Susunan mengikuti mock .asdlc/generated/2-business-spec/screens/html/
    screen-143--laporan-weighbridge-web.html: hero, baris filter, peringatan
    pokok "dua arus tidak pernah dijumlahkan", KELOMPOK ARUS MASUK (3 kartu),
    KELOMPOK ARUS KELUAR (3 kartu), kelengkapan pencatatan berdampingan dengan
    tabel "yang belum selesai & yang dikecualikan", empat kartu jam
    tersibuk/jam tanpa trip, dua grafik sebaran per jam (satu per arus),
    rekap per asal, rekap per tujuan, tren harian berat bersih dua garis, lalu
    rekap harian yang dapat dibuka/tutup. Yang ditiru adalah SUSUNAN dan
    kepadatan informasinya — kosakata kelasnya `md-*` milik aplikasi
    (dashboard/partials/report-styles.blade.php), yang dipakai APA ADANYA:
    mock memperkenalkan dua kosakata tambahan (`md-group*`, `md-hc*`) yang
    TIDAK ada di partial bersama, dan menambahkannya ke sana akan menyentuh
    keenam laporan lain yang memakai partial itu. Kelompok arus karena itu
    memakai `.md-card` + `.md-kpis--3`, dan sebaran per jam memakai
    `.md-trendchart` — grafik 24 kolom yang sudah dipakai Laporan Cages &
    Tracks untuk pertanyaan yang sama persis.

    CSS TIDAK di-include di sini: partial report-styles memancarkan <style>
    dan Livewire 3 memasang wire:id pada elemen ter-render PERTAMA, sehingga
    menaruhnya di dalam/di atas root komponen mematikan seluruh wire:model.
    Partial itu dimuat lewat <x-slot:styles> di
    dashboard/laporan-weighbridge.blade.php.

    EMPAT HAL YANG MEMBEDAKAN LAYAR INI DARI LAPORAN STASIUN LAIN:

    1. DUA ARUS YANG TIDAK PERNAH DIJUMLAHKAN. Laporan ini meringkas
       TRANSAKSI, bukan pembacaan berkala: satu baris = satu kali kendaraan
       ditimbang, dan tiap transaksi milik salah satu dari dua arus yang
       menjawab pertanyaan BERBEDA — arus masuk (FFB datang dari
       estate/supplier) dan arus keluar (kiriman meninggalkan pabrik). Karena
       itu TIDAK ADA satu pun angka di layar ini yang menjumlahkan keduanya:
       tidak ada total trip gabungan, tidak ada total berat gabungan, tidak
       ada rata-rata gabungan, tidak ada kolom harian gabungan, tidak ada
       batang bertumpuk, tidak ada garis ketiga pada grafik tren. Satuan
       muatannya pun tidak sebanding — satu kiriman keluar adalah satu truk
       tangki penuh, satu trip masuk adalah satu bak FFB.

    2. TIDAK ADA ANGKA LAMA KENDARAAN DI PABRIK DI MANA PUN, DAN ITU
       DISENGAJA. Tiap trip menyimpan TEPAT SATU penanda waktu sejak migrasi
       2026_08_19_000010 menggabungkan arrival_datetime dan dispatch_datetime
       menjadi satu `record_datetime` lalu MEMBUANG keduanya. Durasi menuntut
       DUA penanda waktu pada trip yang sama; datanya tidak ada, jadi metrik
       itu mustahil — dan angka durasi yang diturunkan dari satu penanda waktu
       akan terbaca sah padahal tidak punya dasar. Tidak ada kartu durasi,
       tidak ada kolom durasi, dan tidak pula tempat kosong yang menunggu
       diisi. SEBARAN TRIP PER JAM adalah penggantinya, disetujui user: ia
       dapat dihitung dari satu penanda waktu dan menjawab pertanyaan
       operasional yang berdekatan — kapan timbangan menumpuk.

    3. SETIAP METRIK PUNYA PENYEBUTNYA SENDIRI, DAN PENYEBUTNYA DITULIS DI
       SAMPING ANGKANYA. Berat bersih boleh kosong (penimbangan belum
       selesai), jadi total dan rata-rata hanya memakai trip yang beratnya
       terisi, sementara jumlah trip menghitung SELURUH trip arus itu. Trip
       yang beratnya kosong tidak menurunkan rata-rata, ia menghilang
       darinya — dan jumlahnya ditampilkan PER ARUS supaya kedua angka dapat
       dicocokkan pembaca.

    4. KELENGKAPAN PENCATATAN ADALAH BAGIAN LAPORAN, BUKAN CATATAN KAKI, dan
       ketiga keadaannya BERLAKU BERBEDA: trip DRAFT ikut terhitung di seluruh
       angka; trip yang BERATNYA KOSONG ikut jumlah trip tetapi tidak ikut
       total maupun rata-rata; trip yang PENANDA WAKTUNYA KOSONG sama sekali
       di luar laporan karena tanpa penanda waktu ia tidak dapat ditempatkan
       pada periode mana pun. Ketiganya dinyatakan di satu tempat, supaya
       selisih antara layar ini dan Data Browser dapat dijelaskan.

    SATUAN BERAT ADALAH KILOGRAM, apa adanya dari kolomnya, tanpa konversi.
    Mock menulis "ton"; yang berlaku adalah konvensi repo — form input
    (form-weighbridge) dan Data Browser (data-browser-weighbridge) keduanya
    memberi label (kg) pada kolom yang sama, dan mengonversi di satu layar
    saja akan membuat dua layar menyebut angka berbeda untuk baris yang sama.

    TIDAK ADA PENANDAAN NILAI DI LUAR BATAS di layar ini — tanpa kartu ambang,
    tanpa warna aman/bahaya, tanpa outlier, tanpa IQR. Weighbridge tidak punya
    master target operasional, dan menurunkan ambang dari data periode itu
    sendiri berisiko dibaca sebagai batas resmi padahal bukan. Nilai ekstrem
    tetap ikut total dan rata-rata seperti trip lain. Kotak keterangan memakai
    kelas `.md-explain`, bukan `.md-threshold`, karena ketiadaan penandaan
    diasersi MENURUT NAMA pada HTML ter-render.

    Bacaan saja: tidak ada satu pun tombol/field yang mengubah data stasiun.
--}}
@php
    // Angka dengan jumlah desimal tetap. null SELALU menjadi "tidak
    // tersedia", TIDAK PERNAH 0 — nol berarti "terukur dan hasilnya nol",
    // sedangkan tidak tersedia berarti "tidak pernah diukur". Aturan ini
    // berlaku di seluruh halaman: kartu, tabel, maupun kaki tabel.
    $nilai = function ($value, int $digits = 2) {
        if ($value === null) {
            return 'tidak tersedia';
        }

        return number_format((float) $value, $digits, ',', '.');
    };

    $cacah = fn ($value) => number_format((float) $value, 0, ',', '.');

    // "09.00" — jam ditulis sebagai jam dinding, bukan sebagai bilangan
    // telanjang, supaya tidak terbaca sebagai jumlah.
    $jam = fn ($hour) => $hour === null ? 'tidak tersedia' : sprintf('%02d.00', (int) $hour);

    $bulan = ['01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', '05' => 'Mei', '06' => 'Jun',
              '07' => 'Jul', '08' => 'Agu', '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'];

    $tgl = function (?string $date) use ($bulan) {
        if ($date === null || $date === '') {
            return 'tidak tersedia';
        }

        [$y, $m, $d] = array_pad(explode('-', substr($date, 0, 10)), 3, '');

        return $d.' '.($bulan[$m] ?? $m).' '.$y;
    };

    $tglAngka = function (?string $date) {
        [$y, $m, $d] = array_pad(explode('-', substr((string) $date, 0, 10)), 3, '');

        return $d;
    };

    $statusLabel = fn (?string $status) => match ($status) {
        'draft' => 'Draft',
        'open' => 'Terbuka',
        'closed' => 'Tertutup',
        default => (string) $status,
    };

    // ---------------------------------------------------------------
    // Dua kelompok arus, DIBACA TERPISAH DAN TIDAK PERNAH DIJUMLAHKAN.
    // Tidak ada satu pun variabel di bawah yang menjumlahkan $receive dan
    // $dispatch; yang paling dekat pun ($jumlahTripSemuaArus) hanya dipakai
    // untuk MENERANGKAN angka draft/undated yang memang dinyatakan sebagai
    // satu angka lintas arus oleh spec — dan itu pencacah KELENGKAPAN, bukan
    // metrik arus.
    // ---------------------------------------------------------------
    $receive = $summary['receive'] ?? null;
    $dispatch = $summary['dispatch'] ?? null;
    $daily = $summary['daily'] ?? [];
    $dailyTotal = $summary['daily_total'] ?? null;
    $completeness = $summary['completeness'] ?? null;
    $draftTripCount = $summary['draft_trip_count'] ?? 0;
    $undatedTripCount = $summary['undated_trip_count'] ?? 0;

    // "Ada data" = ada trip bertanggal pada SALAH SATU arus. Dipakai hanya
    // untuk memutuskan apakah grafik digambar: grafik kosong akan terbaca
    // sebagai aktivitas nol yang terukur, padahal tidak ada yang diukur.
    $hasData = (($receive['trip_count'] ?? 0) + ($dispatch['trip_count'] ?? 0)) > 0;

    // Kelompok tujuan yang BELUM DIISI (destination NULL) — satu kelompok
    // tersendiri yang tidak pernah dibuang, dan angkanya ikut dinyatakan di
    // bagian kelengkapan.
    $tanpaTujuan = collect($dispatch['by_destination'] ?? [])
        ->firstWhere('destination', null);
    $tanpaTujuanTrip = (int) ($tanpaTujuan['trip_count'] ?? 0);

    // ---------------------------------------------------------------
    // Geometri grafik garis (kosakata md-lc*, diperkenalkan screen-132).
    // DUA SERI PADA SATU BIDANG, TANPA SERI KETIGA: keduanya berbagi bidang
    // supaya BENTUKNYA dapat dibandingkan sepanjang tanggal yang sama, bukan
    // supaya dijumlahkan. Jarak tegak antara kedua garis BUKAN angka yang
    // laporan ini sediakan — ia bukan stok dan bukan selisih yang bermakna.
    // ---------------------------------------------------------------
    $lcJumlah = count($daily);

    $lcX = function (int $i) use ($lcJumlah) {
        if ($lcJumlah <= 1) {
            return 409.0;
        }

        return round(72 + 674 * $i / ($lcJumlah - 1), 1);
    };

    // SUMBU DIMULAI DARI NOL, sengaja: yang dibaca adalah besaran berat, dan
    // sumbu terpotong akan melebih-lebihkan naik-turunnya.
    $beratHarian = [];

    foreach ($daily as $row) {
        foreach (['receive_net_weight_total', 'dispatch_net_weight_total'] as $kolom) {
            if (($row[$kolom] ?? null) !== null) {
                $beratHarian[] = (float) $row[$kolom];
            }
        }
    }

    $adaGrafikTren = $beratHarian !== [];
    // Sumbu "angka bulat" (temuan audit 2026-10-04 #6): langkah 1/2/2,5/5
    // × 10^n dari nol, bukan maks × 1,12 dibagi empat (label 295.294 …).
    $sumbuTren = \App\Support\ChartAxis::nice(0.0, $adaGrafikTren ? max(max($beratHarian), 1.0) : 1.0);
    $trenHi = $sumbuTren['hi'];

    $lcY = function ($value) use ($trenHi) {
        return round(260 - 216 * ((float) $value) / $trenHi, 1);
    };

    // Titik grafik per arus. DUA keadaan yang SENGAJA dibedakan:
    //   - arus itu tidak punya trip sama sekali pada tanggal itu -> titik di
    //     NOL (nol trip memang nol berat, dan itu terukur);
    //   - arus itu punya trip tetapi tak satu pun beratnya terisi -> titik
    //     DILEWATI (beratnya tidak diketahui, dan nol akan mengarang).
    $titikArus = function (string $flow) use ($daily, $lcX, $lcY) {
        $titik = [];

        foreach ($daily as $i => $row) {
            $berat = $row[$flow.'_net_weight_total'] ?? null;

            if ($berat === null) {
                if ((int) ($row[$flow.'_trip_count'] ?? 0) === 0) {
                    $titik[] = [$lcX($i), $lcY(0)];
                }

                continue;
            }

            $titik[] = [$lcX($i), $lcY($berat)];
        }

        return $titik;
    };

    // Konfigurasi kedua grafik sebaran per jam. SATU GRAFIK PER ARUS, tidak
    // ditumpuk: menumpuknya akan memunculkan tinggi batang gabungan, yaitu
    // persis angka yang laporan ini dilarang punya.
    $sebaranJam = [
        ['flow' => 'receive', 'group' => $receive, 'label' => 'Arus masuk', 'satuan' => 'trip masuk'],
        ['flow' => 'dispatch', 'group' => $dispatch, 'label' => 'Arus keluar', 'satuan' => 'trip keluar'],
    ];
@endphp

<div class="md" data-testid="laporan-weighbridge">

    {{-- ============ 1. Hero: periode yang sedang dibaca ============ --}}
    <section class="md-hero" data-testid="report-hero">
        <div class="md-hero__main">
            <p class="md-hero__eyebrow">Laporan Periode &middot; Stasiun Weighbridge</p>
            <h1 class="md-hero__title">{{ $selectedPeriod['name'] ?? 'Laporan Weighbridge' }}</h1>
            <p class="md-hero__subtitle">
                @if ($selectedPeriod)
                    {{ $summary['business_unit']['name'] ?? $businessUnitName }}
                    @if ($selectedProductionLine) &middot; {{ $selectedProductionLine['name'] }} @endif
                    &middot; {{ $tgl($selectedPeriod['start_date']) }} &ndash; {{ $tgl($selectedPeriod['end_date']) }}
                @else
                    Trip masuk dan trip keluar sepanjang satu Periode Pelaporan, dipisah per arus dan tidak pernah dijumlahkan
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
    {{-- Filter bar bersama (components/report-filter-bar.blade.php):
         Mill (Admin) / keterangan mill, Production Line, Periode, ekspor. --}}
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
        Hanya periode yang mencakup Weighbridge yang ditampilkan. Angka disaring menurut Production Line yang melekat pada trip itu sendiri, dan keanggotaan periode mengikuti waktu penimbangan.
    </x-report-filter-bar>

    {{-- Area hasil laporan: diredupkan + spinner selama mill / line /
         periode berganti (components/loading-assets, .ld-region). .md-body
         meneruskan jarak antarkartu .md, jadi tata letak tidak berubah. --}}
    <div class="md-body ld-region" wire:loading.delay.short.class="ld-region--busy" wire:loading.delay.short.attr="aria-busy" wire:target="businessUnitId,productionLineId,periodId">
    @if ($hasNoMillForAccount)
        {{-- Empty state (a): akun terikat mill tetapi users.business_unit_id
             kosong. GAGAL TERTUTUP — pemilih Mill TIDAK ditawarkan sebagai
             gantinya, karena menawarkan daftar seluruh mill kepada peran yang
             seharusnya terikat satu mill justru mengubah data master yang
             rusak menjadi kebocoran lintas mill. --}}
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
                menampilkan daftar Production Line, daftar Periode Pelaporan, dan laporan
                Weighbridge-nya.
            </p>
        </div>
    @elseif ($forbidden)
        {{-- Empty state (c): period_id yang diminta bukan milik mill ini.
             Ditolak SECARA TERLIHAT dan tanpa satu angka pun dari mill lain.
             Berbeda dari business_unit_id yang diabaikan diam-diam: di sana
             tidak ada yang ditolak karena parameternya memang tidak pernah
             dipakai, sedangkan di sini ada pegangan nyata ke data mill
             lain. --}}
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
        {{-- Mill sudah pasti, production line belum. Sama persis dengan
             keadaan "Admin belum memilih mill" di atas: tidak ada satu angka
             pun yang ditampilkan, dan TIDAK ADA angka seluruh line maupun
             seluruh mill yang dipasang sebagai gantinya. --}}
        <div class="md-empty" data-testid="select-production-line-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h16"/><circle cx="8" cy="7" r="1.6"/><circle cx="14" cy="12" r="1.6"/><circle cx="10" cy="17" r="1.6"/></svg>
            </span>
            <p class="md-empty__title">Pilih production line terlebih dahulu</p>
            <p class="md-empty__text">
                Laporan Weighbridge menghasilkan angka gabungan per line, dan mencampur beberapa
                production line membuat angkanya tidak bisa ditindaklanjuti. Pilih satu production
                line pada pemilih di atas untuk menampilkan laporannya. Selama belum dipilih, layar
                ini tidak menampilkan satu angka pun &mdash; termasuk tidak menampilkan angka seluruh
                mill sebagai penggantinya.
            </p>
        </div>
    @elseif ($periods === [])
        {{-- Empty state (d): mill belum punya Periode Pelaporan yang mencakup
             Weighbridge. Bukan 404 — cukup arahkan ke layar yang
             membuatnya. --}}
        <div class="md-empty" data-testid="no-period-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
            </span>
            <p class="md-empty__title">Belum ada Periode Pelaporan</p>
            <p class="md-empty__text">
                Mill ini belum memiliki Periode Pelaporan yang mencakup stasiun Weighbridge. Hubungi
                Admin agar membuatnya terlebih dahulu di layar Kelola Periode Pelaporan, lalu laporan
                periode akan tampil di sini.
            </p>
        </div>
    @elseif ($summary !== null)

        {{-- ============ 3. Peringatan pokok: dua arus tidak pernah dijumlahkan ============ --}}
        <div class="md-explain" data-testid="no-sum-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
            <span>
                Laporan ini meringkas <b>transaksi penimbangan</b>, bukan pembacaan berkala: satu baris
                = satu kali kendaraan ditimbang. Karena itu seluruh angkanya dipisah menjadi
                <b>dua kelompok yang tidak pernah dijumlahkan</b> &mdash; <b>arus masuk</b> (FFB datang
                dari estate/supplier) dan <b>arus keluar</b> (kiriman meninggalkan pabrik menuju suatu
                tujuan). Keduanya memakai alat yang sama tetapi menjawab pertanyaan yang berbeda, jadi
                layar ini <b>tidak menyediakan satu pun angka gabungan keduanya</b>: tidak ada total
                trip gabungan, tidak ada total berat gabungan, tidak ada rata-rata gabungan, tidak ada
                kolom harian gabungan, dan tidak ada batang bertumpuk.
                <small>
                    Tidak ada pula angka <b>lama kendaraan berada di pabrik</b> di mana pun pada layar
                    ini maupun pada berkas yang diunduh, dan ketiadaannya disengaja: tiap trip hanya
                    menyimpan SATU penanda waktu &mdash; waktu kedatangan untuk arus masuk, waktu keluar
                    untuk arus keluar &mdash; sejak kedua kolom waktu yang lama digabung menjadi satu.
                    Durasi menuntut dua penanda waktu pada trip yang sama, dan data itu tidak ada.
                    Sebaran trip per jam di bawah adalah penggantinya.
                </small>
            </span>
        </div>

        {{-- ============ 4a. KELOMPOK: ARUS MASUK ============ --}}
        <section class="md-card" data-testid="flow-receive">
            <header class="md-card__head">
                <h3>Arus Masuk &mdash; FFB dari estate/supplier</h3>
                <span class="md-card__hint">penanda waktu tiap trip = waktu kedatangan &middot; angka di kelompok ini tidak pernah dijumlahkan dengan arus keluar</span>
            </header>
            <div class="md-kpis md-kpis--3">
                <article class="md-kpi" data-testid="kpi-receive-trip-count">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Jumlah Trip Masuk</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17h4V5H2v12h3"/><path d="M14 9h4l3 3v5h-2"/><circle cx="7.5" cy="17.5" r="2"/><circle cx="17.5" cy="17.5" r="2"/></svg></span>
                    </div>
                    <p class="md-kpi__value">{{ $cacah($receive['trip_count']) }} <span>trip</span></p>
                    <p class="md-kpi__meta">sepanjang {{ $tgl($summary['period']['start_date']) }} &ndash; {{ $tgl($summary['period']['end_date']) }}</p>
                    <p class="md-kpi__foot">menghitung SELURUH trip masuk bertanggal, termasuk yang beratnya belum terisi dan yang masih draft</p>
                    <p class="md-kpi__foot"><small>Angka ini TIDAK pernah dijumlahkan dengan jumlah trip arus keluar.</small></p>
                </article>
                <article class="md-kpi" data-testid="kpi-receive-net-weight-total">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Total Berat Bersih Masuk</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18"/><path d="M5 7h14"/><path d="M5 7l-3 7h6z"/><path d="M19 7l-3 7h6z"/><path d="M8 21h8"/></svg></span>
                    </div>
                    <p class="md-kpi__value">{{ $nilai($receive['net_weight_total'], 2) }} <span>kg</span></p>
                    <p class="md-kpi__meta">dari <b data-testid="receive-net-weight-trip-count">{{ $cacah($receive['net_weight_trip_count']) }}</b> trip yang beratnya terisi</p>
                    <p class="md-kpi__foot"><span data-testid="receive-missing-net-weight-inline">{{ $cacah($receive['missing_net_weight_trip_count']) }}</span> trip beratnya <b>belum terisi</b> &mdash; tidak ikut dijumlahkan</p>
                    <p class="md-kpi__foot"><small>Jumlah trip yang beratnya benar-benar terisi ditulis berdampingan dengan totalnya, supaya pembaca tahu total ini berdiri di atas berapa trip &mdash; bukan atas seluruh {{ $cacah($receive['trip_count']) }} trip.</small></p>
                </article>
                <article class="md-kpi" data-testid="kpi-receive-net-weight-avg">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Rata-rata Berat Bersih/Trip</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 18h16"/><path d="M4 14l5-5 4 4 7-7"/></svg></span>
                    </div>
                    <p class="md-kpi__value">{{ $nilai($receive['net_weight_avg'], 2) }} <span>kg</span></p>
                    <p class="md-kpi__meta">penyebutnya <b data-testid="receive-avg-denominator">{{ $cacah($receive['net_weight_trip_count']) }}</b> trip{{ $receive['net_weight_trip_count'] !== $receive['trip_count'] ? ', bukan '.$cacah($receive['trip_count']) : '' }}</p>
                    <p class="md-kpi__foot">{{ $nilai($receive['net_weight_total'], 2) }} kg &divide; {{ $cacah($receive['net_weight_trip_count']) }} trip</p>
                    <p class="md-kpi__foot"><small>Trip yang beratnya kosong tidak menurunkan rata-rata, ia menghilang darinya &mdash; membagi dengan seluruh trip akan menurunkan rata-rata secara palsu hanya karena ada penimbangan yang belum selesai.</small></p>
                </article>
            </div>
        </section>

        {{-- ============ 4b. KELOMPOK: ARUS KELUAR ============
             Kelompok ini TETAP dirender utuh bahkan bila periode tidak memuat
             satu kiriman keluar pun: nilainya dinyatakan tidak tersedia, bukan
             bagiannya disembunyikan. Bagian yang hilang terbaca sebagai "hal
             ini tidak ada", sedangkan bagian yang hadir berisi "tidak
             tersedia" terbaca sebagai "memang tidak ada kiriman" — dan hanya
             yang kedua yang benar. --}}
        <section class="md-card" data-testid="flow-dispatch">
            <header class="md-card__head">
                <h3>Arus Keluar &mdash; kiriman meninggalkan pabrik</h3>
                <span class="md-card__hint">penanda waktu tiap trip = waktu keluar &middot; angka di kelompok ini tidak pernah dijumlahkan dengan arus masuk</span>
            </header>
            <div class="md-kpis md-kpis--3">
                <article class="md-kpi" data-testid="kpi-dispatch-trip-count">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Jumlah Trip Keluar</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 17h-4V5h12v12h-3"/><path d="M10 9H6L3 12v5h2"/><circle cx="7.5" cy="17.5" r="2"/><circle cx="17.5" cy="17.5" r="2"/></svg></span>
                    </div>
                    <p class="md-kpi__value">{{ $cacah($dispatch['trip_count']) }} <span>trip</span></p>
                    <p class="md-kpi__meta">sepanjang {{ $tgl($summary['period']['start_date']) }} &ndash; {{ $tgl($summary['period']['end_date']) }}</p>
                    <p class="md-kpi__foot">menghitung SELURUH trip keluar bertanggal, termasuk yang beratnya belum terisi dan yang masih draft</p>
                    <p class="md-kpi__foot"><small>Angka ini TIDAK pernah dijumlahkan dengan jumlah trip arus masuk. Kelompok ini tetap ditampilkan bahkan bila periode tidak memuat satu kiriman keluar pun &mdash; nilainya dinyatakan tidak tersedia, bukan bagiannya disembunyikan.</small></p>
                </article>
                <article class="md-kpi" data-testid="kpi-dispatch-net-weight-total">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Total Berat Bersih Keluar</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18"/><path d="M5 7h14"/><path d="M5 7l-3 7h6z"/><path d="M19 7l-3 7h6z"/><path d="M8 21h8"/></svg></span>
                    </div>
                    <p class="md-kpi__value">{{ $nilai($dispatch['net_weight_total'], 2) }} <span>kg</span></p>
                    <p class="md-kpi__meta">dari <b data-testid="dispatch-net-weight-trip-count">{{ $cacah($dispatch['net_weight_trip_count']) }}</b> trip yang beratnya terisi</p>
                    <p class="md-kpi__foot"><span data-testid="dispatch-missing-net-weight-inline">{{ $cacah($dispatch['missing_net_weight_trip_count']) }}</span> trip beratnya <b>belum terisi</b> &mdash; tidak ikut dijumlahkan</p>
                    <p class="md-kpi__foot"><small>Satu trip dapat punya berat bersih tetapi tidak punya tujuan, atau sebaliknya. Karena itu tiap metrik dihitung dari trip yang metriknya benar-benar terisi, dengan penyebutnya masing-masing.</small></p>
                </article>
                <article class="md-kpi" data-testid="kpi-dispatch-net-weight-avg">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Rata-rata Berat Bersih/Trip</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 18h16"/><path d="M4 14l5-5 4 4 7-7"/></svg></span>
                    </div>
                    <p class="md-kpi__value">{{ $nilai($dispatch['net_weight_avg'], 2) }} <span>kg</span></p>
                    <p class="md-kpi__meta">penyebutnya <b data-testid="dispatch-avg-denominator">{{ $cacah($dispatch['net_weight_trip_count']) }}</b> trip{{ $dispatch['net_weight_trip_count'] !== $dispatch['trip_count'] ? ', bukan '.$cacah($dispatch['trip_count']) : '' }}</p>
                    <p class="md-kpi__foot">{{ $nilai($dispatch['net_weight_total'], 2) }} kg &divide; {{ $cacah($dispatch['net_weight_trip_count']) }} trip</p>
                    <p class="md-kpi__foot"><small>Rata-rata arus keluar bisa jauh berbeda dari arus masuk karena satuan muatannya memang berbeda &mdash; satu kiriman keluar adalah satu truk tangki penuh, satu trip masuk adalah satu bak FFB. Dua rata-rata ini tidak sebanding, dan justru itulah alasan keduanya tidak pernah disatukan.</small></p>
                </article>
            </div>
        </section>

        {{-- ============ 5. Kelengkapan pencatatan & yang belum selesai ============
             BAGIAN LAPORAN, BUKAN CATATAN KAKI, dan sengaja berdampingan
             dengan angka utama: pembaca perlu tahu seberapa jauh angka di atas
             dapat diandalkan sebelum menindaklanjutinya. --}}
        <div class="md-row md-row--2">
            <section class="md-card" data-testid="completeness-card">
                <header class="md-card__head">
                    <h3>Kelengkapan Pencatatan</h3>
                    <span class="md-card__hint">bagian dari laporan, bukan catatan kaki</span>
                </header>
                <div class="md-budget">
                    <div class="md-budget__row">
                        <div class="md-budget__label">
                            <span>Hari dengan minimal satu trip</span>
                            <span class="md-budget__nums">
                                <strong data-testid="days-with-trip">{{ $cacah($completeness['days_with_trip']) }}</strong>
                                dari <span data-testid="days-in-period">{{ $cacah($completeness['days_counted']) }}</span> hari
                            </span>
                        </div>
                        @php
                            // Penyebut = hari yang SUDAH terjadi (temuan audit
                            // 2026-10-04 #3); 0 hari (periode belum mulai) → "—",
                            // bukan 0% (null bukan 0).
                            $persenHari = $completeness['days_counted'] > 0
                                ? round(100 * $completeness['days_with_trip'] / $completeness['days_counted'], 1)
                                : null;
                        @endphp
                        <span class="md-budget__pct" data-testid="days-with-trip-percent">{{ $persenHari === null ? '—' : $nilai($persenHari, 1).'%' }}</span>
                        <div class="md-bar md-bar--lg"><span style="width: {{ min(100, max(0, (float) $persenHari)) }}%"></span></div>
                    </div>
                    @if ($completeness['period_running'])
                        <p class="md-budget__note" data-testid="period-running-note">
                            Dihitung sampai hari ini, periode masih berjalan
                            ({{ $cacah($completeness['days_counted']) }} dari {{ $cacah($completeness['days_in_period']) }} hari periode sudah lewat).
                        </p>
                    @endif
                </div>
                <div class="md-explain" data-testid="completeness-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                    <span>
                        Rentang periode <b>inklusif di kedua ujung</b>, jadi hari pertama dan hari terakhir
                        ikut dihitung. Hari tanpa satu trip pun <b>bukan hal yang sama</b> dengan hari
                        bertrip nol kilogram: hari semacam itu tidak mendapat baris pada rekap harian
                        dan <b>tidak mendapat titik</b> pada grafik trennya, bukan digambar sebagai 0 kg.
                        <small>
                            Keanggotaan periode ditentukan oleh <b>waktu penimbangan</b>, bukan waktu baris
                            dibuat maupun waktu sinkronisasi dari mobile &mdash; sebuah trip yang tersinkron
                            terlambat tetap milik periode tempat ia terjadi.
                        </small>
                    </span>
                </div>
            </section>

            <section class="md-card" data-testid="incomplete-card">
                <header class="md-card__head">
                    <h3>Yang Belum Selesai &amp; Yang Dikecualikan</h3>
                    <span class="md-card__hint">dinyatakan terbuka &mdash; selisih yang tidak dapat dijelaskan pembaca adalah selisih yang disembunyikan</span>
                </header>
                <div class="md-recap">
                    <table class="md-table" data-testid="incomplete-table">
                        <thead>
                            <tr>
                                <th scope="col">Keadaan</th>
                                <th scope="col">Jumlah</th>
                                <th scope="col">Pengaruh pada angka di atas</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- PER ARUS, dan sengaja TIDAK dijumlahkan menjadi
                                 satu angka: bagian data yang belum selesai pada
                                 tiap arus adalah dua fakta berbeda. --}}
                            <tr data-testid="incomplete-row-missing-weight-receive">
                                <td>Berat bersih <b>belum terisi</b> &mdash; arus masuk <small>(penimbangan belum selesai)</small></td>
                                <td data-testid="missing-net-weight-receive">{{ $cacah($receive['missing_net_weight_trip_count']) }} trip</td>
                                <td class="is-muted">ikut jumlah trip masuk, <b>tidak</b> ikut total &amp; rata-rata berat</td>
                            </tr>
                            <tr data-testid="incomplete-row-missing-weight-dispatch">
                                <td>Berat bersih <b>belum terisi</b> &mdash; arus keluar <small>(penimbangan belum selesai)</small></td>
                                <td data-testid="missing-net-weight-dispatch">{{ $cacah($dispatch['missing_net_weight_trip_count']) }} trip</td>
                                <td class="is-muted">ikut jumlah trip keluar, <b>tidak</b> ikut total &amp; rata-rata berat</td>
                            </tr>
                            <tr data-testid="incomplete-row-no-destination">
                                <td><b>Tujuan belum diisi</b> pada trip arus keluar</td>
                                <td data-testid="no-destination-dispatch-count">{{ $cacah($tanpaTujuanTrip) }} trip</td>
                                <td class="is-muted">dikelompokkan tersendiri pada rekap per tujuan, <b>tidak dibuang</b></td>
                            </tr>
                            <tr data-testid="incomplete-row-draft">
                                <td>Masih berstatus <b>draft</b></td>
                                <td data-testid="draft-trip-count">{{ $cacah($draftTripCount) }} trip</td>
                                <td class="is-muted"><b>ikut terhitung</b> di seluruh angka di atas</td>
                            </tr>
                            <tr data-testid="incomplete-row-undated">
                                <td><b>Penanda waktu kosong</b> <small>(tidak dapat ditempatkan pada periode mana pun)</small></td>
                                <td data-testid="undated-trip-count">{{ $cacah($undatedTripCount) }} trip</td>
                                <td class="is-muted"><b>TIDAK ikut terhitung di angka mana pun</b></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="md-explain" data-testid="incomplete-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg>
                    <span>
                        Baris-baris ini sengaja berada di satu tempat karena masing-masing
                        <b>berlaku berbeda</b>. Trip <b>draft ikut terhitung</b> di semua angka,
                        sama seperti di laporan stasiun lain. Trip yang
                        <b>beratnya belum terisi</b> ikut dihitung sebagai trip tetapi tidak ikut total
                        maupun rata-rata. Trip arus keluar yang <b>tujuannya belum diisi</b> tetap muncul
                        sebagai kelompoknya sendiri pada rekap per tujuan, supaya jumlah trip per tujuan
                        tetap menjumlah tepat ke jumlah trip keluar. Trip yang <b>penanda waktunya
                        kosong</b> sama sekali di luar laporan: tanpa penanda waktu ia tidak dapat
                        ditempatkan pada periode mana pun, jadi ia tidak ada di angka mana pun &mdash; dan
                        karena itu jumlahnya dinyatakan di sini, supaya selisih antara layar ini dan Data
                        Browser dapat dijelaskan.
                    </span>
                </div>
            </section>
        </div>

        @if (! $hasData)
            {{-- Empty state (e): periode + line valid tetapi tidak memuat satu
                 trip bertanggal pun. Seluruh angka di atas sudah tampil sebagai
                 "tidak tersedia" (bukan nol); keempat kartu jam, kedua grafik
                 sebaran, kedua rekap, tren harian dan rekap harian TIDAK
                 digambar sama sekali — grafik kosong akan terbaca sebagai
                 aktivitas nol yang terukur. --}}
            <div class="md-empty" data-testid="empty-state">
                <span class="md-empty__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19h16M7 16V9M12 16V5M17 16v-4"/></svg>
                </span>
                <p class="md-empty__title">Belum ada data pada periode dan line ini</p>
                <p class="md-empty__text">
                    Tidak ada satu pun trip Weighbridge bertanggal pada rentang periode ini di
                    {{ $selectedProductionLine['name'] ?? 'production line ini' }}, sehingga seluruh
                    total dan rata-rata ditampilkan sebagai tidak tersedia &mdash; bukan sebagai nol
                    &mdash; dan kedua grafik sebaran per jam, kedua rekap, tren harian serta rekap harian
                    tidak digambar.
                </p>
            </div>
        @else

            {{-- ============ 6. Jam tersibuk & jam tanpa trip, per arus ============ --}}
            <section class="md-kpis md-kpis--4" data-testid="hourly-kpis">
                <article class="md-kpi" data-testid="kpi-busiest-hour-receive">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Jam Tersibuk &mdash; Masuk</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
                    </div>
                    <p class="md-kpi__value">{{ $jam($receive['busiest_hour']) }}</p>
                    <p class="md-kpi__meta">{{ $cacah($receive['busiest_hour_trip_count']) }} trip masuk pada jam itu</p>
                    <p class="md-kpi__foot">jam dengan trip masuk terbanyak sepanjang periode</p>
                </article>
                <article class="md-kpi" data-testid="kpi-empty-hours-receive">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Jam Tanpa Trip &mdash; Masuk</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M4.9 4.9l14.2 14.2"/></svg></span>
                    </div>
                    <p class="md-kpi__value">{{ $cacah($receive['empty_hour_count']) }} <span>dari 24 jam</span></p>
                    <p class="md-kpi__meta">tak satu trip masuk pun pada jam-jam itu sepanjang seluruh periode</p>
                    <p class="md-kpi__foot">24 jam selalu digambar utuh &mdash; jam kosong terbaca sebagai kosong, bukan sebagai jam yang tidak ada</p>
                </article>
                <article class="md-kpi" data-testid="kpi-busiest-hour-dispatch">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Jam Tersibuk &mdash; Keluar</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
                    </div>
                    <p class="md-kpi__value">{{ $jam($dispatch['busiest_hour']) }}</p>
                    <p class="md-kpi__meta">{{ $cacah($dispatch['busiest_hour_trip_count']) }} trip keluar pada jam itu</p>
                    <p class="md-kpi__foot">jam dengan trip keluar terbanyak sepanjang periode</p>
                </article>
                <article class="md-kpi" data-testid="kpi-empty-hours-dispatch">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Jam Tanpa Trip &mdash; Keluar</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M4.9 4.9l14.2 14.2"/></svg></span>
                    </div>
                    <p class="md-kpi__value">{{ $cacah($dispatch['empty_hour_count']) }} <span>dari 24 jam</span></p>
                    <p class="md-kpi__meta">tak satu kiriman keluar pun pada jam-jam itu sepanjang seluruh periode</p>
                    <p class="md-kpi__foot">24 jam selalu digambar utuh &mdash; jam kosong terbaca sebagai kosong, bukan sebagai jam yang tidak ada</p>
                </article>
            </section>

            {{-- Sebaran trip per jam — SATU GRAFIK PER ARUS, tidak ditumpuk. --}}
            <section class="md-card" data-testid="hourly-distribution">
                <header class="md-card__head">
                    <h3>Sebaran Trip per Jam dalam Sehari</h3>
                    <span class="md-card__hint">00&ndash;23, dijumlahkan atas seluruh hari periode &middot; satu grafik per arus, tidak ditumpuk</span>
                </header>

                @foreach ($sebaranJam as $s)
                    @php
                        $jamRow = $s['group']['hourly'] ?? [];
                        $maksJam = max(array_map(fn ($row) => $row['trip_count'], $jamRow) ?: [0]);
                    @endphp
                    <p class="md-card__hint"><b>{{ $s['label'] }}</b> &mdash; {{ $cacah($s['group']['trip_count']) }} trip tersebar pada {{ $cacah(24 - $s['group']['empty_hour_count']) }} jam</p>
                    <div class="md-trendchart" data-testid="hourly-chart-{{ $s['flow'] }}">
                        @foreach ($jamRow as $row)
                            {{-- Jam bernilai nol TETAP digambar, diredupkan —
                                 bukan dihilangkan: grafik harus tetap 24 kolom
                                 agar bentuknya tidak berubah antar periode dan
                                 jam kosong terbaca sebagai kosong. --}}
                            <div class="md-trendchart__col @if ($row['trip_count'] === 0) md-trendchart__col--off @endif"
                                 data-testid="hourly-col-{{ $s['flow'] }}-{{ $row['hour'] }}"
                                 title="Jam {{ sprintf('%02d', $row['hour']) }} &mdash; {{ $cacah($row['trip_count']) }} {{ $s['satuan'] }}">
                                <span class="md-trendchart__val">{{ $cacah($row['trip_count']) }}</span>
                                <div class="md-trendchart__bar" style="height: {{ round(100 * $row['trip_count'] / max($maksJam, 1), 1) }}%"></div>
                                <span class="md-trendchart__lbl">{{ sprintf('%02d', $row['hour']) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endforeach

                <div class="md-explain" data-testid="no-duration-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                    <span>
                        <b>Lama kendaraan di pabrik TIDAK dilaporkan di layar ini, dan ketiadaannya
                        disengaja &mdash; bukan kelalaian.</b> Tiap trip hanya menyimpan <b>SATU</b> penanda
                        waktu &mdash; waktu kedatangan untuk arus masuk, waktu keluar untuk arus keluar
                        &mdash; sejak kedua kolom waktu yang lama digabung menjadi satu. Durasi menuntut
                        <b>dua</b> penanda waktu pada trip yang sama, dan data itu tidak ada; angka durasi
                        yang diturunkan dari satu penanda waktu akan terbaca sah padahal tidak punya dasar
                        sama sekali. <b>Sebaran per jam di atas adalah penggantinya</b> &mdash; ia memang
                        dapat dihitung dari satu penanda waktu, dan menjawab pertanyaan operasional yang
                        berdekatan: <b>kapan timbangan menumpuk</b>. Jumlah ke-24 kolom tiap grafik
                        menjumlah tepat ke jumlah trip bertanggal arus itu.
                        <small>
                            Kedua arus digambar pada dua grafik terpisah, bukan sebagai batang bertumpuk
                            pada satu grafik &mdash; batang bertumpuk akan memunculkan tinggi gabungan,
                            yaitu persis angka yang laporan ini tidak boleh punya.
                        </small>
                    </span>
                </div>
            </section>

            {{-- ============ 7a. Rekap arus masuk per estate/supplier ============ --}}
            <section class="md-card" data-testid="by-origin-card">
                <header class="md-card__head">
                    <h3>Rekap Arus Masuk per Estate/Supplier</h3>
                    <span class="md-card__hint">diurutkan dari berat bersih terbesar &middot; seluruh asal ditampilkan, tidak dipangkas dan tanpa kelompok &ldquo;lain-lain&rdquo;</span>
                </header>
                <div class="md-recap">
                    <table class="md-table" data-testid="by-origin-table">
                        <thead>
                            <tr>
                                <th scope="col">Asal (estate/supplier)</th>
                                <th scope="col">Trip</th>
                                <th scope="col">Berat bersih (kg)</th>
                                <th scope="col">Trip berat terisi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($receive['by_origin'] as $row)
                                <tr data-testid="by-origin-row">
                                    <td>{{ $row['estate_supplier'] === '' ? 'tidak tercatat' : $row['estate_supplier'] }}</td>
                                    <td>{{ $cacah($row['trip_count']) }}</td>
                                    <td @class(['is-muted' => $row['net_weight_total'] === null])>{{ $nilai($row['net_weight_total'], 2) }}</td>
                                    <td class="is-muted">{{ $cacah($row['net_weight_trip_count']) }}</td>
                                </tr>
                            @empty
                                <tr data-testid="by-origin-empty">
                                    <td colspan="4" class="is-muted">Tidak ada trip masuk pada periode dan line ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr data-testid="by-origin-row-total">
                                <td>Total arus masuk</td>
                                <td>{{ $cacah($receive['trip_count']) }}</td>
                                <td>{{ $nilai($receive['net_weight_total'], 2) }}</td>
                                <td>{{ $cacah($receive['net_weight_trip_count']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="md-explain" data-testid="by-origin-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    <span>
                        <b>Seluruh asal ditampilkan apa adanya</b>, termasuk penyumbang terkecil, dan
                        tidak ada kelompok &ldquo;lain-lain&rdquo; sama sekali &mdash; memangkas daftar
                        membuat penyumbang kecil lenyap dari laporan. Kolom Trip karena itu menjumlah
                        tepat ke {{ $cacah($receive['trip_count']) }} trip masuk pada kaki tabel, dan
                        kolom Trip berat terisi adalah penyebut tersendiri tiap baris: dua baris dengan
                        jumlah trip sama dapat punya jumlah trip berat terisi berbeda.
                    </span>
                </div>
            </section>

            {{-- ============ 7b. Rekap arus keluar per tujuan ============ --}}
            <section class="md-card" data-testid="by-destination-card">
                <header class="md-card__head">
                    <h3>Rekap Arus Keluar per Tujuan</h3>
                    <span class="md-card__hint">diurutkan dari berat bersih terbesar &middot; dengan kelompok tersendiri untuk tujuan yang belum diisi</span>
                </header>
                <div class="md-recap">
                    <table class="md-table" data-testid="by-destination-table">
                        <thead>
                            <tr>
                                <th scope="col">Tujuan</th>
                                <th scope="col">Trip</th>
                                <th scope="col">Berat bersih (kg)</th>
                                <th scope="col">Trip berat terisi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($dispatch['by_destination'] as $row)
                                {{-- Baris tujuan NULL TIDAK PERNAH DIBUANG:
                                     membuangnya membuat kolom Trip berhenti
                                     menjumlah ke total arus keluar, dan pembaca
                                     melihat selisih yang tidak dapat
                                     dijelaskan. --}}
                                <tr data-testid="{{ $row['destination'] === null ? 'by-destination-row-null' : 'by-destination-row' }}">
                                    <td @class(['is-muted' => $row['destination'] === null])>
                                        @if ($row['destination'] === null)
                                            <b>Belum diisi</b> <small>(tujuan tidak terisi pada trip)</small>
                                        @else
                                            {{ $row['destination'] }}
                                        @endif
                                    </td>
                                    <td>{{ $cacah($row['trip_count']) }}</td>
                                    <td @class(['is-muted' => $row['net_weight_total'] === null])>{{ $nilai($row['net_weight_total'], 2) }}</td>
                                    <td class="is-muted">{{ $cacah($row['net_weight_trip_count']) }}</td>
                                </tr>
                            @empty
                                <tr data-testid="by-destination-empty">
                                    <td colspan="4" class="is-muted">Tidak ada trip keluar pada periode dan line ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr data-testid="by-destination-row-total">
                                <td>Total arus keluar</td>
                                <td>{{ $cacah($dispatch['trip_count']) }}</td>
                                <td>{{ $nilai($dispatch['net_weight_total'], 2) }}</td>
                                <td>{{ $cacah($dispatch['net_weight_trip_count']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="md-explain" data-testid="by-destination-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg>
                    <span>
                        Baris <b>Belum diisi</b> memuat {{ $cacah($tanpaTujuanTrip) }} trip keluar yang
                        tujuannya belum terisi. Trip itu <b>tidak dibuang</b> dari rekap: membuangnya
                        membuat jumlah trip per tujuan tidak lagi menjumlah ke total arus keluar, dan
                        pembaca akan melihat selisih yang tidak dapat dijelaskan. Dengan baris itu, kolom
                        Trip menjumlah tepat ke {{ $cacah($dispatch['trip_count']) }} trip keluar.
                        Perhatikan pula bahwa trip tanpa tujuan <b>tetap dapat punya berat bersih</b>
                        &mdash; satu trip dapat terisi pada satu metrik dan kosong pada metrik lain, dan
                        tiap metrik dihitung dari trip yang metriknya sendiri terisi.
                    </span>
                </div>
            </section>

            {{-- ============ 8. Tren harian berat bersih: dua garis terpisah ============ --}}
            <section class="md-card" data-testid="daily-trend">
                <header class="md-card__head">
                    <h3>Tren Harian Berat Bersih</h3>
                    <span class="md-card__hint">dua garis terpisah pada satu bidang &middot; tidak ada garis ketiga yang menjumlahkan keduanya</span>
                </header>
                @if ($adaGrafikTren)
                    <div class="md-lc" data-testid="daily-trend-chart">
                        <svg class="md-lc__svg" viewBox="0 0 760 300" role="img"
                             aria-label="Tren harian berat bersih: dua garis terpisah, arus masuk dan arus keluar, sepanjang {{ count($daily) }} tanggal, dalam kilogram">
                            @foreach ($sumbuTren['ticks'] as $tickNilai)
                                @php
                                    $tickY = round(260 - 216 * $tickNilai / $trenHi, 1);
                                @endphp
                                <line class="md-lc__grid" x1="72" y1="{{ $tickY }}" x2="746" y2="{{ $tickY }}"/>
                                <text class="md-lc__ytick" x="64" y="{{ $tickY + 4 }}" text-anchor="end">{{ $nilai($tickNilai, $sumbuTren['decimals']) }}</text>
                            @endforeach
                            <line class="md-lc__axis" x1="72" y1="260" x2="746" y2="260"/>
                            @foreach ($daily as $i => $row)
                                <text class="md-lc__xtick" x="{{ $lcX($i) }}" y="280" text-anchor="middle">{{ $tglAngka($row['date']) }}</text>
                            @endforeach

                            @foreach ([['flow' => 'receive', 'cls' => 's1'], ['flow' => 'dispatch', 'cls' => 's2']] as $seri)
                                @php $titik = $titikArus($seri['flow']); @endphp
                                @if (count($titik) > 1)
                                    <polyline class="md-lc__line md-lc__line--{{ $seri['cls'] }}"
                                              points="{{ collect($titik)->map(fn ($p) => $p[0].','.$p[1])->implode(' ') }}"/>
                                @endif
                                @foreach ($titik as $p)
                                    <circle class="md-lc__dot md-lc__dot--{{ $seri['cls'] }}" cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="3"/>
                                @endforeach
                            @endforeach
                        </svg>
                    </div>
                    {{-- Petunjuk gulir — tampil HANYA bila kartu lebih sempit dari grafiknya
                         (container query di report-styles), temuan audit 2026-10-04 #7. --}}
                    <p class="md-scrollhint md-scrollhint--lc">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M15 8l4 4-4 4M9 8l-4 4 4 4"/></svg>
                        Geser mendatar untuk melihat seluruh tanggal.
                    </p>
                    <ul class="md-legend" data-testid="daily-trend-legend">
                        <li class="md-legend__item"><span class="md-legend__swatch md-legend__swatch--s1"></span>
                            Arus masuk &mdash; total periode {{ $nilai($receive['net_weight_total'], 2) }} kg dari {{ $cacah($receive['net_weight_trip_count']) }} trip terisi</li>
                        <li class="md-legend__item"><span class="md-legend__swatch md-legend__swatch--s2"></span>
                            Arus keluar &mdash; total periode {{ $nilai($dispatch['net_weight_total'], 2) }} kg dari {{ $cacah($dispatch['net_weight_trip_count']) }} trip terisi</li>
                        <li class="md-legend__item"><small>Sumbu tegak dalam kilogram, <b>dimulai dari nol</b>. Hanya tanggal yang punya trip yang digambar. Pada tanggal itu, arus yang tidak punya trip sama sekali tergambar sebagai titik pada nol; arus yang punya trip tetapi tak satu pun beratnya terisi sengaja <b>dilewati</b>, bukan digambar pada nol.</small></li>
                    </ul>
                    <div class="md-explain" data-testid="daily-trend-note">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                        <span>
                            Kedua arus berbagi satu bidang gambar supaya <b>bentuk</b> keduanya dapat
                            dibandingkan sepanjang tanggal yang sama &mdash; bukan supaya keduanya
                            dijumlahkan. <b>Jarak tegak antara kedua garis bukan sebuah angka yang laporan
                            ini sediakan</b>: ia bukan stok, bukan selisih yang bermakna, dan tidak muncul
                            di tabel mana pun. Yang dapat dibaca dari grafik ini adalah kapan
                            masing-masing arus naik dan turun.
                            <small>
                                Tidak ada nilai yang ditandai di luar batas di layar ini &mdash; Weighbridge
                                tidak punya master target operasional, dan menurunkan ambang dari data
                                periode itu sendiri berisiko dibaca sebagai batas resmi padahal bukan.
                                Nilai ekstrem tetap ikut total dan rata-rata seperti trip lain.
                            </small>
                        </span>
                    </div>
                @else
                    <p class="md-filters__hint" data-testid="daily-trend-unavailable">
                        Tidak ada satu pun trip yang beratnya terisi pada periode dan line ini, jadi
                        trennya tidak digambar &mdash; grafik kosong akan terbaca sebagai garis datar yang
                        terukur.
                    </p>
                @endif
            </section>

            {{-- ============ 9. Rekap harian (dapat dibuka/tutup) ============
                 Periode panjang menghasilkan puluhan baris, jadi tabelnya dapat
                 ditutup agar angka utama dan trennya tetap terbaca tanpa gulir
                 panjang. Tombol, bukan <details> bawaan: keadaannya harus satu
                 sumber (properti Livewire) agar tabel benar-benar hilang dari
                 DOM saat ditutup, bukan sekadar tersembunyi. Membuka atau
                 menutupnya TIDAK mengubah satu angka pun di halaman ini.

                 KEDUA ARUS TETAP DI KOLOM TERPISAH, termasuk pada baris kaki
                 tabel: tidak ada kolom gabungan dan tidak ada total menyeluruh
                 yang menjumlahkan keduanya. --}}
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
                                    <th scope="col" rowspan="2">Tanggal</th>
                                    <th scope="colgroup" colspan="2">Arus masuk</th>
                                    <th scope="colgroup" colspan="2">Arus keluar</th>
                                </tr>
                                <tr>
                                    <th scope="col">Trip</th>
                                    <th scope="col">Berat bersih (kg)</th>
                                    <th scope="col">Trip</th>
                                    <th scope="col">Berat bersih (kg)</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Tanggal tanpa trip sama sekali TIDAK mendapat
                                     baris: baris nol akan terbaca sebagai
                                     "terukur nol". Tanggal yang punya trip pada
                                     satu arus saja tetap mendapat baris, dengan
                                     kolom arus lainnya bernilai 0 trip dan berat
                                     tidak tersedia. --}}
                                @foreach ($daily as $row)
                                    <tr data-testid="daily-row-{{ $row['date'] }}">
                                        <td>{{ $tgl($row['date']) }}</td>
                                        <td @class(['is-muted' => $row['receive_trip_count'] === 0])>{{ $cacah($row['receive_trip_count']) }}</td>
                                        <td @class(['is-muted' => $row['receive_net_weight_total'] === null])>{{ $row['receive_net_weight_total'] === null ? '—' : $nilai($row['receive_net_weight_total'], 2) }}</td>
                                        <td @class(['is-muted' => $row['dispatch_trip_count'] === 0])>{{ $cacah($row['dispatch_trip_count']) }}</td>
                                        <td @class(['is-muted' => $row['dispatch_net_weight_total'] === null])>{{ $row['dispatch_net_weight_total'] === null ? '—' : $nilai($row['dispatch_net_weight_total'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr data-testid="daily-row-total">
                                    <td>Total periode</td>
                                    <td>{{ $cacah($dailyTotal['receive_trip_count']) }}</td>
                                    <td>{{ $nilai($dailyTotal['receive_net_weight_total'], 2) }}</td>
                                    <td>{{ $cacah($dailyTotal['dispatch_trip_count']) }}</td>
                                    <td>{{ $nilai($dailyTotal['dispatch_net_weight_total'], 2) }}</td>
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
