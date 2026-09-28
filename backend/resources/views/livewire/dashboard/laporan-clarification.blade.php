{{--
    screen-132--laporan-clarification-web — Laporan Periode Clarification.

    Susunan mengikuti mock .asdlc/generated/2-business-spec/screens/html/
    screen-132--laporan-clarification-web.html: hero, baris filter, kartu
    KELENGKAPAN PENCATATAN (sengaja di ATAS seluruh angka lain), 3 kartu
    (produksi turunan, total downtime, laju produksi), 4 kartu (tiga suhu
    tangki + level buffer), SATU grafik garis tiga seri untuk ketiga suhu,
    tren harian produksi berdampingan dengan rekap per unit, lalu rekap
    harian yang dapat dibuka/tutup. Yang ditiru adalah SUSUNAN dan kepadatan
    informasinya — kosakata kelasnya `md-*` milik aplikasi
    (dashboard/partials/report-styles.blade.php).

    CSS TIDAK di-include di sini: partial report-styles memancarkan <style>
    dan Livewire 3 memasang wire:id pada elemen ter-render PERTAMA, sehingga
    menaruhnya di dalam/di atas root komponen mematikan seluruh wire:model.
    Partial itu dimuat lewat <x-slot:styles> di
    dashboard/laporan-clarification.blade.php.

    TIGA HAL YANG MEMBEDAKAN LAYAR INI DARI LAPORAN STASIUN LAIN:

    1. PRODUKSI DITURUNKAN, BUKAN DICATAT. Tidak ada kolom produksi di
       skema; total ton adalah JUMLAH laju (ton/jam) atas baris yang
       lajunya terisi, karena satu pembacaan berlaku untuk satu jam slotnya.
       Karena itu JUMLAH PEMBACAAN dirender DI DALAM kartu yang sama, tepat
       di sebelah angkanya — produksi dari 4 pembacaan dan produksi dari 400
       pembacaan tidak boleh terlihat sama meyakinkan. Jam tanpa catatan
       laju BUKAN jam berproduksi nol; ia tidak menyumbang 0,0 dan tidak
       masuk penyebut rata-rata.

    2. LAJU DAN DOWNTIME TIDAK SALING MENGURANGI, jadi total downtime
       WAJIB berdampingan dengan produksi. Pertanyaan apakah downtime harus
       dikurangkan masih TERBUKA dan menunggu pemilik proses — lihat
       App\Services\ClarificationReportService::productionOf().

    3. KETIGA SUHU TANGKI PADA SATU GRAFIK dengan SATU sumbu, karena yang
       dibaca adalah SELISIH antar tangki. Tiga grafik terpisah memenuhi
       kalimat "tren suhu antar tangki" secara harfiah sambil menghilangkan
       maksudnya. Pembeda antar seri dua lapis: warna DAN pola garis.

    TIDAK ADA PENANDAAN NILAI DI LUAR BATAS di layar ini — tanpa kartu
    ambang, tanpa warna aman/bahaya, tanpa outlier, tanpa IQR. Clarification
    tidak punya master target operasional (tidak ada
    ClarificationOperationalTarget), jadi ambang apa pun di sini hanyalah
    turunan statistik dari data periode itu sendiri, dan angka suhu tangki
    yang diwarnai merah akan dibaca sebagai batas PROSES. Penilaian ada pada
    pembaca. Kartu yang memuat nilai ekstrem memakai atribut kelas yang
    SAMA PERSIS dengan kartu biasa. Alasan lengkapnya ada di docblock
    App\Services\ClarificationReportService.

    Bacaan saja: tidak ada satu pun tombol/field yang mengubah data stasiun.
--}}
@php
    // Angka dengan jumlah desimal tetap. null SELALU menjadi tanda pisah,
    // TIDAK PERNAH 0 — nol berarti "terukur dan hasilnya nol", tanda pisah
    // berarti "tidak pernah diukur". Aturan ini yang membedakan downtime
    // nol dari downtime tidak tercatat di seluruh halaman.
    $nilai = function ($value, int $digits = 1) {
        if ($value === null) {
            return '–';
        }

        return number_format((float) $value, $digits, ',', '.');
    };

    $cacah = fn ($value) => number_format((float) $value, 0, ',', '.');

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

    $coverage = $summary['coverage'] ?? null;
    $production = $summary['production'] ?? null;
    $downtime = $summary['downtime'] ?? null;
    $metrics = $summary['metrics'] ?? [];
    $daily = $summary['daily'] ?? [];
    $byUnit = $summary['by_unit'] ?? [];
    $total = $summary['total'] ?? null;
    $hasData = (bool) ($summary['has_data'] ?? false);

    // ---------------------------------------------------------------
    // Grafik garis tiga seri: ketiga suhu tangki pada SATU bidang gambar.
    // Urutan gambar sengaja sludge (paling bawah) -> clarification
    // (tengah) -> minyak (paling atas), sama seperti mock, supaya garis
    // yang saling menimpa tetap terbaca.
    // ---------------------------------------------------------------
    $seri = [
        ['cls' => 's1', 'kolom' => 'clarification_tank_temp_avg', 'metrik' => 'clarification_tank_temp_c', 'label' => 'Tangki Clarification', 'pola' => 'garis utuh'],
        ['cls' => 's2', 'kolom' => 'oil_tank_temperature_avg', 'metrik' => 'oil_tank_temperature_c', 'label' => 'Tangki Minyak', 'pola' => 'garis putus'],
        ['cls' => 's3', 'kolom' => 'sludge_tank_temp_avg', 'metrik' => 'sludge_tank_temp_c', 'label' => 'Tangki Sludge', 'pola' => 'garis titik'],
    ];
    $urutGambar = [2, 0, 1]; // sludge, clarification, minyak

    $semuaSuhu = [];
    foreach ($seri as $s) {
        foreach ($daily as $row) {
            if (($row[$s['kolom']] ?? null) !== null) {
                $semuaSuhu[] = (float) $row[$s['kolom']];
            }
        }
    }

    $adaGrafikSuhu = $semuaSuhu !== [];
    $lcLo = $lcHi = 0.0;

    if ($adaGrafikSuhu) {
        $lcMin = min($semuaSuhu);
        $lcMaks = max($semuaSuhu);
        // SUMBU TEGAK TIDAK DIMULAI DARI NOL, dan itu dinyatakan pada kartunya
        // sendiri: yang dibaca adalah JARAK antar tangki, bukan besaran
        // mutlaknya. Ini semata skala tampilan — tidak ada nilai yang ditandai
        // aman/bahaya karenanya.
        $lcPad = max(0.5, ($lcMaks - $lcMin) * 0.12);
        $lcLo = $lcMin - $lcPad;
        $lcHi = $lcMaks + $lcPad;

        if ($lcHi <= $lcLo) {
            $lcLo -= 1;
            $lcHi += 1;
        }
    }

    $lcJumlah = count($daily);
    $lcX = function (int $i) use ($lcJumlah) {
        if ($lcJumlah <= 1) {
            return 396.0;
        }

        return round(46 + 700 * $i / ($lcJumlah - 1), 1);
    };
    $lcY = function ($value) use ($lcLo, $lcHi) {
        return round(260 - 216 * (((float) $value) - $lcLo) / ($lcHi - $lcLo), 1);
    };

    // Tinggi batang tren produksi = nilai / tertinggi x 100%. SUMBU DIMULAI
    // DARI NOL di sini (berbeda dengan grafik suhu) karena yang dibandingkan
    // adalah besaran produksi, bukan jarak antar seri.
    $produksiHarian = array_values(array_filter(
        array_map(fn ($row) => $row['production_ton'], $daily),
        fn ($v) => $v !== null,
    ));
    $produksiMaks = $produksiHarian === [] ? null : max($produksiHarian);
    $produksiMin = $produksiHarian === [] ? null : min($produksiHarian);

    $tinggiBatang = function ($value) use ($produksiMaks) {
        if ($value === null || $produksiMaks === null || (float) $produksiMaks <= 0) {
            return 0;
        }

        return round(100 * (float) $value / (float) $produksiMaks, 1);
    };
@endphp

<div class="md" data-testid="laporan-clarification">

    {{-- ============ 1. Hero: periode yang sedang dibaca ============ --}}
    <section class="md-hero" data-testid="report-hero">
        <div class="md-hero__main">
            <p class="md-hero__eyebrow">Laporan Periode &middot; Stasiun Clarification</p>
            <h1 class="md-hero__title">{{ $selectedPeriod['name'] ?? 'Laporan Clarification' }}</h1>
            <p class="md-hero__subtitle">
                @if ($selectedPeriod)
                    {{ $summary['period']['business_unit_name'] ?? '' }}
                    @if (! empty($summary['period']['business_unit_name'])) &middot; @endif
                    {{ $tgl($selectedPeriod['start_date']) }} &ndash; {{ $tgl($selectedPeriod['end_date']) }}
                @else
                    Produksi minyak murni, downtime, dan suhu antar tangki sepanjang satu Periode Pelaporan
                @endif
            </p>
        </div>
        @if ($selectedPeriod)
            <div class="md-hero__meta">
                <span class="md-chip md-chip--date" data-testid="hero-range">
                    {{ $tgl($selectedPeriod['start_date']) }} &ndash; {{ $tgl($selectedPeriod['end_date']) }}
                </span>
                @if ($coverage !== null)
                    <span class="md-chip md-chip--date" data-testid="hero-unit-count">
                        {{ $cacah($coverage['unit_count']) }} unit clarification
                    </span>
                @endif
                {{-- Status periode ditampilkan, tetapi TIDAK membatasi apa pun:
                     periode tertutup tetap dapat dibaca dan diekspor penuh. --}}
                <span class="md-chip md-chip--status {{ $selectedPeriod['status'] === 'closed' ? 'md-chip--closed' : '' }} {{ $selectedPeriod['status'] === 'draft' ? 'md-chip--draft' : '' }}"
                      data-testid="period-status">
                    {{ $statusLabel($selectedPeriod['status']) }}
                </span>
            </div>
        @endif
    </section>

    {{-- ============ 2. Baris filter ============ --}}
    <section class="md-filters" data-testid="report-filters">
        @if ($isAdmin)
            {{-- Pemilih Mill HANYA untuk Admin: Supervisor dan Mill Management
                 terkunci pada millnya sendiri, sehingga pemilih ini tidak
                 dirender sama sekali bagi mereka — termasuk bagi akun terikat
                 yang millnya kosong, yang justru paling tidak boleh ditawari
                 daftar seluruh mill. --}}
            <div class="md-field">
                <label class="md-field__label" for="business-unit-select">Mill (Business Unit)</label>
                <select id="business-unit-select" class="md-field__control"
                        wire:model.live="businessUnitId" data-testid="mill-selector">
                    <option value="">&mdash; Pilih Mill &mdash;</option>
                    @foreach ($businessUnitOptions as $option)
                        <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                    @endforeach
                </select>
            </div>
        @elseif (! $hasNoMillForAccount)
            {{-- Keterangan, bukan pemilih: mill akun tidak dapat diubah dari
                 layar ini, dan memaksa properti/query string ke mill lain
                 tidak mengubah satu angka pun. --}}
            <p class="md-millcurrent" data-testid="mill-name">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V8l5-3v16M14 21V11l5-3v13"/><path d="M9 12h.01M9 16h.01"/></svg>
                Mill: <strong>{{ $businessUnitName }}</strong>
            </p>
        @endif

        {{-- Pemilih Production Line, TANPA opsi "semua" — dan itu berbeda
             dari Data Browser dengan sengaja. Laporan menghasilkan ANGKA
             GABUNGAN: "Total 1.200" yang mencampur belasan line bukan angka
             yang bisa ditindaklanjuti siapa pun, jadi tidak ada satu pun
             pilihan di sini yang berarti "semua line". Opsinya hanya line di
             dalam mill yang berlaku. --}}
        @if (! $needsMillSelection && ! $hasNoMillForAccount)
            <div class="md-field">
                <label class="md-field__label" for="production-line-select">Production Line</label>
                <select id="production-line-select" class="md-field__control"
                        wire:model.live="productionLineId" data-testid="production-line-select">
                    <option value="">&mdash; Pilih Production Line &mdash;</option>
                    @foreach ($productionLineOptions as $option)
                        {{-- @selected WAJIB dirender di server. Livewire 3 tidak
                             menulis balik nilai <select> dari state komponen pada
                             paint pertama: DOM yang dikirim server-lah sumber
                             kebenarannya. Tanpa ini, pengguna yang tiba lewat
                             tautan tile akan melihat "Pilih Production Line"
                             sementara angkanya sudah milik line itu — dua
                             pernyataan yang saling bertentangan di satu layar. --}}
                        <option value="{{ $option['id'] }}"
                                @selected(($selectedProductionLine['id'] ?? null) === $option['id'])>{{ $option['name'] }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if ($selectedProductionLine !== null)
            {{-- Line yang sedang dibaca, dinamai di layar. Seluruh angka di
                 bawah milik line ini saja. --}}
            <div class="md-millcurrent" data-testid="production-line-current">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h16"/><circle cx="8" cy="7" r="1.6"/><circle cx="14" cy="12" r="1.6"/><circle cx="10" cy="17" r="1.6"/></svg>
                Line aktif: <strong>{{ $selectedProductionLine['name'] }}</strong>
            </div>
        @endif

        @if (! $needsMillSelection && ! $hasNoMillForAccount)
            <div class="md-field">
                <label class="md-field__label" for="period-select">Periode Pelaporan</label>
                {{-- Saat mill belum punya periode yang mencakup Clarification,
                     pemilih ini sengaja dirender TANPA satu pun <option> —
                     bukan dengan option semu "belum ada periode" — dan
                     arahannya ditulis terpisah sebagai no-period-hint. --}}
                <select id="period-select" class="md-field__control"
                        wire:model.live="periodId" data-testid="period-selector">
                    @foreach ($periods as $period)
                        <option value="{{ $period['id'] }}">
                            {{ $period['name'] }}
                            ({{ $tgl($period['start_date']) }} &ndash; {{ $tgl($period['end_date']) }})
                            &mdash; {{ $statusLabel($period['status']) }}
                            &middot; {{ $period['station_type_label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        @if ($summary !== null)
            <div class="md-filters__actions">
                {{-- Status periode (Draft/Terbuka/Tertutup) TIDAK membatasi
                     ekspor — kunci periode mengatur penulisan data, bukan
                     pembacaan laporan, jadi tombol tidak pernah disabled. --}}
                <button type="button" class="md-btn md-btn--primary"
                        wire:click="exportCsv('csv')" data-testid="export-csv-button">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5M12 15V3"/></svg>
                    Ekspor CSV
                </button>
                <button type="button" class="md-btn"
                        wire:click="exportCsv('excel')" data-testid="export-excel-button">
                    Ekspor Excel
                </button>
            </div>
        @endif

        <p class="md-filters__hint">
            Daftar periode hanya memuat periode yang mencakup Clarification, yaitu periode berjenis
            Clarification maupun periode yang berlaku untuk semua jenis stasiun.
            @if ($isAdmin)
                Pemilih Mill hanya tampil untuk Admin, satu-satunya peran yang tidak terikat satu mill.
            @else
                Akun ini terikat pada satu mill, jadi mill ditampilkan sebagai keterangan &mdash; bukan pemilih.
            @endif
        </p>
    </section>

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
                Laporan ini selalu dibaca dalam konteks satu mill, sedangkan akun Anda belum
                terhubung ke mill mana pun. Hubungi Admin untuk menghubungkan akun Anda ke mill
                yang benar. Daftar seluruh mill sengaja tidak ditawarkan di sini.
            </p>
        </div>
    @elseif ($needsMillSelection)
        {{-- Empty state (b): Admin belum memilih mill. Admin tidak terikat
             satu mill, jadi tanpa pemilihan tidak ada data yang bisa
             ditampilkan — halaman meminta pemilihan, bukan menampilkan
             laporan kosong yang terbaca seperti "tidak ada data". --}}
        <div class="md-empty" data-testid="mill-required-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
            </span>
            <p class="md-empty__title">Pilih mill terlebih dahulu</p>
            <p class="md-empty__text">
                Sebagai Admin Anda tidak terikat pada satu mill. Pilih mill pada pemilih di atas
                untuk menampilkan daftar Periode Pelaporan dan laporan Clarification-nya.
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
                Laporan Clarification menghasilkan angka gabungan, dan mencampur beberapa
                production line membuat angkanya tidak bisa ditindaklanjuti. Pilih satu
                production line pada pemilih di atas untuk menampilkan laporannya.
            </p>
        </div>
    @elseif ($periods === [])
        {{-- Empty state (d): mill belum punya Periode Pelaporan yang mencakup
             Clarification. Bukan 404 — cukup arahkan ke layar yang
             membuatnya. --}}
        <div class="md-empty" data-testid="no-period-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
            </span>
            <p class="md-empty__title">Belum ada Periode Pelaporan</p>
            <p class="md-empty__text">
                Mill ini belum memiliki Periode Pelaporan yang mencakup stasiun Clarification.
                Minta Admin membuatnya terlebih dahulu di layar Kelola Periode Pelaporan,
                lalu laporan periode akan tampil di sini.
            </p>
        </div>
    @elseif ($summary !== null)

        {{-- ============ 3. Kelengkapan pencatatan ============
             SENGAJA DI ATAS SELURUH ANGKA LAIN, dan di layar ini bukan
             sekadar konteks: produksi DITURUNKAN dari pembacaan yang ada,
             sehingga pencatatan yang bolong langsung MENURUNKAN ANGKA
             PRODUKSINYA — bukan sekadar menurunkan keyakinan atasnya. Ini
             bagian isi laporan, bukan catatan kaki. --}}
        <section class="md-card" data-testid="recording-coverage">
            <header class="md-card__head">
                <h3>Kelengkapan Pencatatan</h3>
                <span class="md-card__hint">Baca ini lebih dulu &mdash; produksi di bawah diturunkan dari slot yang terisi saja</span>
            </header>
            <div class="md-budget">
                <div class="md-budget__row">
                    <div class="md-budget__label">
                        <span>Slot waktu terisi sepanjang periode</span>
                        <span class="md-budget__nums">
                            <strong data-testid="coverage-filled-slots">{{ $cacah($coverage['filled_slots']) }}</strong>
                            dari <span data-testid="coverage-expected-slots">{{ $cacah($coverage['expected_slots']) }}</span> slot
                        </span>
                    </div>
                    <span class="md-budget__pct" data-testid="coverage-percent">{{ $nilai($coverage['coverage_percent'], 1) }}%</span>
                    <div class="md-bar md-bar--lg"><span style="width: {{ min(100, max(0, (float) $coverage['coverage_percent'])) }}%"></span></div>
                </div>
            </div>
            @if ($coverage['expected_slots'] > $coverage['filled_slots'])
                {{-- Penekanan atas slot yang TIDAK terisi. Ini bukan penandaan
                     nilai di luar batas: tidak ada satu pun nilai pengukuran
                     yang dinilai di sini, yang dinyatakan hanyalah berapa
                     banyak slot yang tidak pernah dicatat dan apa artinya. --}}
                <div class="md-threshold" data-testid="low-coverage-emphasis">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                    <span>
                        <b>{{ $cacah($coverage['expected_slots'] - $coverage['filled_slots']) }} slot yang tidak terisi bukan berarti berproduksi nol</b> &mdash;
                        slot kosong tidak dihitung sebagai 0,0 dan tidak masuk penyebut rata-rata mana pun.
                        Karena produksi di layar ini <b>diturunkan dari pembacaan laju yang ada</b>, pencatatan
                        yang bolong menurunkan angka produksinya secara langsung.
                        <small>
                            Slot yang diharapkan = {{ $cacah($coverage['unit_count']) }} unit clarification
                            &times; {{ $cacah($coverage['days_in_period']) }} hari
                            &times; {{ $cacah($coverage['slots_per_unit_per_day']) }} slot.
                        </small>
                    </span>
                </div>
            @endif
        </section>

        {{-- ============ 4. Produksi turunan + downtime + laju ============ --}}
        <section class="md-kpis md-kpis--3" data-testid="report-production">
            <article class="md-kpi" data-testid="production-card">
                <div class="md-kpi__top">
                    <span class="md-kpi__label">Produksi Minyak Murni</span>
                    <span class="md-kpi__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3s5 5.2 5 9a5 5 0 0 1-10 0c0-3.8 5-9 5-9z"/></svg>
                    </span>
                </div>
                {{-- JUMLAH PEMBACAAN DI DALAM KARTU YANG SAMA, tepat di
                     sebelah angkanya — bukan di catatan kaki halaman. Angka
                     ini DITURUNKAN, jadi penyebutnya adalah bagian dari
                     angkanya. --}}
                <p class="md-kpi__value">
                    <span data-testid="summary-production-total">{{ $nilai($production['total_ton'], 1) }}</span>
                    <span>ton &middot; dari <b data-testid="summary-production-reading-count">{{ $cacah($production['reading_count']) }}</b> pembacaan laju</span>
                </p>
                <p class="md-kpi__meta">
                    rata-rata <span data-testid="production-avg-per-day">{{ $nilai($production['avg_per_day_ton'], 1) }}</span> ton/hari ber-record
                </p>
                <p class="md-kpi__foot" data-testid="production-derived-note">
                    Diturunkan, bukan dicatat: total ini adalah <b>jumlah laju produksi per jam</b>
                    (ton/jam &times; 1 jam) atas slot yang lajunya terisi. Skema tidak punya kolom produksi.
                    <small>Jam tanpa catatan laju tidak dihitung sebagai berproduksi nol.</small>
                </p>
            </article>

            <article class="md-kpi" data-testid="downtime-card">
                <div class="md-kpi__top">
                    <span class="md-kpi__label">Total Downtime</span>
                    <span class="md-kpi__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    </span>
                </div>
                {{-- NOL BERBEDA DARI TIDAK TERCATAT. Saat reading_count 0,
                     angka ini ditulis sebagai "tidak tercatat" — bukan 0 —
                     dan jumlah pembacaannya tetap dirender di sebelahnya
                     supaya kedua keadaan itu terbaca berbeda. --}}
                <p class="md-kpi__value">
                    <span data-testid="summary-downtime-total">{{ $downtime['reading_count'] === 0 ? 'tidak tercatat' : $cacah($downtime['total_mins']) }}</span>
                    <span>
                        @if ($downtime['reading_count'] > 0) menit @endif
                        &middot; dari <b data-testid="summary-downtime-reading-count">{{ $cacah($downtime['reading_count']) }}</b> pembacaan
                    </span>
                </p>
                <p class="md-kpi__meta">
                    rata-rata <span data-testid="downtime-avg-per-day">{{ $nilai($downtime['avg_per_day_mins'], 1) }}</span> menit/hari
                    &middot; tercatat pada <span data-testid="downtime-hours-with-downtime">{{ $cacah($downtime['hours_with_downtime']) }}</span> jam
                </p>
                <p class="md-kpi__foot" data-testid="downtime-production-note">
                    <b>Downtime dan laju tidak saling mengurangi.</b> Jam yang mencatat laju 10,0 ton/jam
                    sekaligus downtime 20 menit tetap menyumbang 10,0 ton pada produksi di sebelah,
                    mengikuti rumus yang ditetapkan pada penentuan cakupan.
                    <small>Karena itu kedua angka ini wajib dibaca berdampingan.</small>
                </p>
            </article>

            <article class="md-kpi" data-testid="production-rate-card">
                <div class="md-kpi__top">
                    <span class="md-kpi__label">Laju Produksi</span>
                    <span class="md-kpi__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/><path d="M13.4 10.6 19 5"/><path d="M4 20a9 9 0 1 1 16 0"/></svg>
                    </span>
                </div>
                <p class="md-kpi__value">
                    <span data-testid="production-rate-avg">{{ $nilai($production['avg_rate_ton_hour'], 2) }}</span>
                    <span>ton/jam rata-rata</span>
                </p>
                <p class="md-kpi__meta">
                    terendah <span data-testid="production-rate-min">{{ $nilai($production['min_rate_ton_hour'], 2) }}</span>
                    &middot; tertinggi <span data-testid="production-rate-max">{{ $nilai($production['max_rate_ton_hour'], 2) }}</span>
                </p>
                <p class="md-kpi__foot">
                    dari <b data-testid="production-rate-reading-count">{{ $cacah($production['reading_count']) }}</b> pembacaan laju
                    <small>Penyebutnya hanya slot yang lajunya terisi &mdash; bukan seluruh slot terisi.</small>
                </p>
            </article>
        </section>

        {{-- ============ 5. Tiga suhu tangki + level buffer ============
             Setiap kartu membawa JUMLAH PEMBACAANNYA SENDIRI: keenam metrik
             punya penyebut yang berbeda-beda karena satu baris boleh mengisi
             suhu sludge dan mengosongkan laju. Tidak ada satu label jumlah
             pembacaan yang berlaku untuk semuanya.

             Seluruh kartu di bawah memakai atribut kelas yang SAMA PERSIS,
             termasuk kartu yang kebetulan memuat nilai ekstrem. --}}
        <section class="md-kpis md-kpis--4" data-testid="report-metrics">
            @foreach ([
                ['key' => 'clarification_tank_temp_c', 'testid' => 'clarification-temp', 'label' => 'Suhu Tangki Clarification', 'satuan' => '&deg;C rata-rata', 'digits' => 1],
                ['key' => 'oil_tank_temperature_c', 'testid' => 'oil-temp', 'label' => 'Suhu Tangki Minyak', 'satuan' => '&deg;C rata-rata', 'digits' => 1],
                ['key' => 'sludge_tank_temp_c', 'testid' => 'sludge-temp', 'label' => 'Suhu Tangki Sludge', 'satuan' => '&deg;C rata-rata', 'digits' => 1],
                ['key' => 'buffer_tank_level_percent', 'testid' => 'buffer-level', 'label' => 'Level Buffer Tank', 'satuan' => '% rata-rata', 'digits' => 1],
            ] as $kartu)
                @php $m = $metrics[$kartu['key']]; @endphp
                <article class="md-kpi" data-testid="{{ $kartu['testid'] }}-summary">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">{{ $kartu['label'] }}</span>
                        <span class="md-kpi__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13.5V5a2 2 0 1 1 4 0v8.5a4 4 0 1 1-4 0z"/></svg>
                        </span>
                    </div>
                    {{-- null ditulis sebagai tanda pisah, TIDAK PERNAH 0. --}}
                    <p class="md-kpi__value">
                        <span data-testid="{{ $kartu['testid'] }}-avg">{{ $nilai($m['avg'], $kartu['digits']) }}</span>
                        <span>{!! $kartu['satuan'] !!}</span>
                    </p>
                    <p class="md-kpi__meta">
                        terendah <span data-testid="{{ $kartu['testid'] }}-min">{{ $nilai($m['min'], $kartu['digits']) }}</span>
                        &middot; tertinggi <span data-testid="{{ $kartu['testid'] }}-max">{{ $nilai($m['max'], $kartu['digits']) }}</span>
                    </p>
                    <p class="md-kpi__foot">
                        dari <b data-testid="{{ $kartu['testid'] }}-reading-count">{{ $cacah($m['reading_count']) }}</b> pembacaan
                    </p>
                </article>
            @endforeach
        </section>

        @if (! $hasData)
            {{-- Empty state (e): periode valid tetapi tidak memuat satu pun
                 pembacaan terisi. Seluruh angka di atas sudah tampil sebagai
                 tanda pisah (bukan nol); grafik suhu, tren produksi, rekap
                 per unit, dan rekap harian TIDAK digambar sama sekali —
                 grafik kosong akan terbaca sebagai garis datar yang terukur. --}}
            <div class="md-empty" data-testid="no-data-notice">
                <span class="md-empty__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19h16M7 16V9M12 16V5M17 16v-4"/></svg>
                </span>
                <p class="md-empty__title">Belum ada data pada periode ini</p>
                <p class="md-empty__text">
                    Tidak ada satu pun pembacaan Clarification tercatat pada rentang tanggal periode ini,
                    sehingga seluruh angka ditampilkan sebagai tidak tersedia &mdash; bukan sebagai nol &mdash;
                    dan grafik suhu, tren produksi, rekap per unit, serta rekap harian tidak digambar.
                </p>
            </div>
        @else

            {{-- ============ 6. SATU grafik untuk KETIGA suhu tangki ============
                 Satu bidang gambar, satu sumbu. Yang dibaca adalah SELISIH
                 antar tangki — jarak yang terjaga antara tangki minyak di
                 atas, tangki clarification di tengah, dan tangki sludge di
                 bawah itulah tanda proses pemisahan berjalan. Tiga grafik
                 terpisah akan menghilangkan maksud itu, jadi elemen
                 tank-temperature-chart hanya ada SATU di halaman ini dan
                 tidak ada grafik suhu per tangki di mana pun. --}}
            <section class="md-card" data-testid="tank-temperature-card">
                <header class="md-card__head">
                    <h3>Tren Suhu Antar Tangki</h3>
                    <span class="md-card__hint">
                        Rata-rata per tanggal, dalam &deg;C &middot; {{ count($daily) }} tanggal berdata &middot; ketiga tangki pada satu bidang gambar
                    </span>
                </header>
                @if ($adaGrafikSuhu)
                    <div class="md-lc" data-testid="tank-temperature-chart">
                        <svg class="md-lc__svg" viewBox="0 0 760 300" role="img"
                             aria-label="Tren harian suhu tangki clarification, tangki minyak, dan tangki sludge sepanjang {{ count($daily) }} tanggal">
                            @for ($k = 0; $k < 5; $k++)
                                @php
                                    $tickNilai = $lcLo + ($lcHi - $lcLo) * $k / 4;
                                    $tickY = round(260 - 216 * $k / 4, 1);
                                @endphp
                                <line class="md-lc__grid" x1="46" y1="{{ $tickY }}" x2="746" y2="{{ $tickY }}"/>
                                <text class="md-lc__ytick" x="38" y="{{ $tickY + 4 }}" text-anchor="end">{{ $nilai($tickNilai, 0) }}</text>
                            @endfor
                            <line class="md-lc__axis" x1="46" y1="260" x2="746" y2="260"/>
                            @foreach ($daily as $i => $row)
                                <text class="md-lc__xtick" x="{{ $lcX($i) }}" y="280" text-anchor="middle">{{ $tglAngka($row['date']) }}</text>
                            @endforeach

                            {{-- Digambar sludge -> clarification -> minyak,
                                 sesuai urutan tingginya, supaya garis yang
                                 bersilangan tetap terbaca. Setiap seri
                                 dibedakan DUA LAPIS: warna (md-lc__line--sN)
                                 DAN pola garis (utuh / putus / titik). --}}
                            @foreach ($urutGambar as $idx)
                                @php
                                    $s = $seri[$idx];
                                    $titik = [];
                                    foreach ($daily as $i => $row) {
                                        if (($row[$s['kolom']] ?? null) !== null) {
                                            $titik[] = [$lcX($i), $lcY($row[$s['kolom']])];
                                        }
                                    }
                                @endphp
                                @if (count($titik) > 1)
                                    <polyline class="md-lc__line md-lc__line--{{ $s['cls'] }}"
                                              points="{{ collect($titik)->map(fn ($p) => $p[0].','.$p[1])->implode(' ') }}"/>
                                @endif
                                @foreach ($titik as $p)
                                    <circle class="md-lc__dot md-lc__dot--{{ $s['cls'] }}" cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="3"/>
                                @endforeach
                            @endforeach
                        </svg>
                    </div>
                    <ul class="md-legend">
                        @foreach ($seri as $s)
                            <li class="md-legend__item">
                                <span class="md-legend__swatch md-legend__swatch--{{ $s['cls'] }}"></span>
                                {{ $s['label'] }} ({{ $s['pola'] }}) &mdash;
                                rata-rata {{ $nilai($metrics[$s['metrik']]['avg'], 1) }} &deg;C,
                                {{ $cacah($metrics[$s['metrik']]['reading_count']) }} pembacaan
                            </li>
                        @endforeach
                    </ul>
                    {{-- Kotak KETERANGAN, bukan ambang. Dipakai justru untuk
                         menyatakan bahwa tidak ada ambang di layar ini. --}}
                    <div class="md-threshold" data-testid="tank-gap-note">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                        <span>
                            Ketiga suhu sengaja berbagi satu bidang gambar karena
                            <b>yang dibaca adalah SELISIH antar tangki, bukan nilai masing-masing</b> &mdash;
                            jarak yang terjaga antara tangki minyak, tangki clarification, dan tangki sludge
                            itulah tanda proses pemisahan berjalan sebagaimana mestinya. Seri dibedakan oleh
                            warna <b>dan</b> pola garis, sehingga tetap terbaca tanpa mengandalkan warna.
                            <b>Tidak ada nilai yang ditandai di luar batas di layar ini</b> &mdash; Clarification
                            tidak punya master target operasional, dan menurunkan ambang dari data periode itu
                            sendiri berisiko dibaca sebagai batas proses padahal bukan.
                            <small>Sumbu tegak tidak dimulai dari nol &mdash; grafik membandingkan jarak antar tangki, bukan besaran mutlak.</small>
                        </span>
                    </div>
                @else
                    <p class="md-filters__hint" data-testid="tank-temperature-unavailable">
                        Tidak ada satu pun suhu tangki tercatat pada periode ini, jadi grafiknya tidak digambar
                        &mdash; grafik kosong akan terbaca sebagai garis datar yang terukur.
                    </p>
                @endif
            </section>

            {{-- ============ 7 & 8. Tren produksi + rekap per unit ============ --}}
            <div class="md-row md-row--2">
                <section class="md-card" data-testid="daily-trend">
                    <header class="md-card__head">
                        <h3>Tren Harian Produksi</h3>
                        <span class="md-card__hint">
                            Minyak murni per tanggal, dalam ton &middot; {{ count($daily) }} tanggal berdata
                        </span>
                    </header>
                    <div class="md-trendchart">
                        @foreach ($daily as $row)
                            {{-- Tanpa modifier warna apa pun: tidak ada batang
                                 yang ditandai "rendah" atau "tinggi" di layar
                                 ini. Tanggal yang lajunya tidak pernah dicatat
                                 ditulis sebagai tanda pisah, bukan 0,0. --}}
                            <div class="md-trendchart__col" data-testid="daily-production-col-{{ $row['date'] }}">
                                <span class="md-trendchart__val">{{ $nilai($row['production_ton'], 1) }}</span>
                                <div class="md-trendchart__bar" style="height: {{ $tinggiBatang($row['production_ton']) }}%"></div>
                                <span class="md-trendchart__lbl">{{ $tglPendek($row['date']) }}</span>
                            </div>
                        @endforeach
                    </div>
                    <ul class="md-legend">
                        <li class="md-legend__item">
                            Terendah {{ $nilai($produksiMin, 1) }} ton &middot; tertinggi {{ $nilai($produksiMaks, 1) }} ton
                        </li>
                        <li class="md-legend__item">
                            <small>
                                Sumbu tegak dimulai dari nol. Tanggal tanpa satu pun pembacaan laju ditulis
                                sebagai tidak tersedia, bukan sebagai nol ton.
                            </small>
                        </li>
                    </ul>
                </section>

                <section class="md-card" data-testid="per-unit-recap">
                    <header class="md-card__head">
                        <h3>Rekap per Unit Clarification</h3>
                        <span class="md-card__hint">
                            Angka periode di atas menggabungkan seluruh unit &mdash; tabel ini menunjukkan sebarannya
                        </span>
                    </header>
                    {{-- .md-card > .md-recap: tabel ini menggulir mendatar DI
                         DALAM kartunya sendiri, sehingga halaman tidak pernah
                         punya gulir horizontal. --}}
                    <div class="md-recap">
                        <table class="md-table" data-testid="by-unit-table">
                            <thead>
                                <tr>
                                    <th scope="col">Unit Clarification</th>
                                    <th scope="col">Produksi (ton)</th>
                                    <th scope="col">Laju rata-rata (ton/jam)</th>
                                    <th scope="col">Suhu Clarification (&deg;C)</th>
                                    <th scope="col">Suhu Minyak (&deg;C)</th>
                                    <th scope="col">Suhu Sludge (&deg;C)</th>
                                    <th scope="col">Downtime (menit)</th>
                                    <th scope="col">Jumlah pembacaan</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Unit yang punya record tetapi NOL pembacaan
                                     terisi TETAP muncul di sini dengan jumlah
                                     pembacaan 0 dan seluruh nilai tidak
                                     tersedia. Menghilangkannya akan
                                     menyembunyikan unit yang justru tidak
                                     pernah dicatat. --}}
                                @foreach ($byUnit as $unit)
                                    <tr data-testid="by-unit-row-{{ $unit['clarification_id'] }}">
                                        <td>{{ $unit['clarification_id'] }}</td>
                                        <td @class(['is-muted' => $unit['production_ton'] === null])>{{ $nilai($unit['production_ton'], 1) }}</td>
                                        <td @class(['is-muted' => $unit['rate_avg'] === null])>{{ $nilai($unit['rate_avg'], 2) }}</td>
                                        <td @class(['is-muted' => $unit['clarification_tank_temp_avg'] === null])>{{ $nilai($unit['clarification_tank_temp_avg'], 1) }}</td>
                                        <td @class(['is-muted' => $unit['oil_tank_temperature_avg'] === null])>{{ $nilai($unit['oil_tank_temperature_avg'], 1) }}</td>
                                        <td @class(['is-muted' => $unit['sludge_tank_temp_avg'] === null])>{{ $nilai($unit['sludge_tank_temp_avg'], 1) }}</td>
                                        <td @class(['is-muted' => $unit['downtime_mins'] === null])>{{ $nilai($unit['downtime_mins'], 0) }}</td>
                                        <td>{{ $cacah($unit['reading_count']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr data-testid="by-unit-row-total">
                                    <td>Gabungan {{ $cacah($coverage['unit_count']) }} unit</td>
                                    <td>{{ $nilai($production['total_ton'], 1) }}</td>
                                    <td>{{ $nilai($production['avg_rate_ton_hour'], 2) }}</td>
                                    <td>{{ $nilai($metrics['clarification_tank_temp_c']['avg'], 1) }}</td>
                                    <td>{{ $nilai($metrics['oil_tank_temperature_c']['avg'], 1) }}</td>
                                    <td>{{ $nilai($metrics['sludge_tank_temp_c']['avg'], 1) }}</td>
                                    <td>{{ $downtime['reading_count'] === 0 ? '–' : $cacah($downtime['total_mins']) }}</td>
                                    <td>{{ $cacah($total['reading_rows']) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            </div>

            {{-- ============ 9. Rekap harian (dapat dibuka/tutup) ============
                 Periode panjang menghasilkan puluhan baris, jadi tabelnya dapat
                 ditutup agar angka utama dan tren tetap terbaca tanpa gulir
                 panjang. Tombol, bukan <details> bawaan: keadaannya harus satu
                 sumber (properti Livewire) agar tabel benar-benar hilang dari
                 DOM saat ditutup, bukan sekadar tersembunyi. --}}
            <section class="md-card" data-testid="daily-recap-card">
                <header class="md-card__head">
                    <h3>Rekap Harian</h3>
                    <button type="button" class="md-btn"
                            wire:click="toggleDailyRecap" data-testid="daily-recap-toggle">
                        {{ $dailyRecapOpen ? 'Tutup rekap harian' : 'Buka rekap harian' }}
                        <small>({{ count($daily) }} baris)</small>
                    </button>
                </header>
                @if ($dailyRecapOpen)
                    <div class="md-recap">
                        <table class="md-table" data-testid="daily-recap-table">
                            <thead>
                                <tr>
                                    <th scope="col">Tanggal</th>
                                    <th scope="col">Slot Terisi</th>
                                    <th scope="col">Produksi (ton)</th>
                                    <th scope="col">Laju rata-rata (ton/jam)</th>
                                    <th scope="col">Suhu Clarification (&deg;C)</th>
                                    <th scope="col">Suhu Minyak (&deg;C)</th>
                                    <th scope="col">Suhu Sludge (&deg;C)</th>
                                    <th scope="col">Downtime (menit)</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Tanggal ber-record yang seluruh barisnya
                                     kosong untuk sebuah metrik menghasilkan
                                     tanda pisah pada kolom itu, tetapi tanggal
                                     itu TETAP terhitung sebagai hari
                                     ber-record. --}}
                                @foreach ($daily as $row)
                                    <tr data-testid="daily-recap-row-{{ $row['date'] }}">
                                        <td>{{ $tgl($row['date']) }}</td>
                                        <td>{{ $cacah($row['filled_slots']) }}</td>
                                        <td @class(['is-muted' => $row['production_ton'] === null])>{{ $nilai($row['production_ton'], 1) }}</td>
                                        <td @class(['is-muted' => $row['rate_avg'] === null])>{{ $nilai($row['rate_avg'], 2) }}</td>
                                        <td @class(['is-muted' => $row['clarification_tank_temp_avg'] === null])>{{ $nilai($row['clarification_tank_temp_avg'], 1) }}</td>
                                        <td @class(['is-muted' => $row['oil_tank_temperature_avg'] === null])>{{ $nilai($row['oil_tank_temperature_avg'], 1) }}</td>
                                        <td @class(['is-muted' => $row['sludge_tank_temp_avg'] === null])>{{ $nilai($row['sludge_tank_temp_avg'], 1) }}</td>
                                        <td @class(['is-muted' => $row['downtime_mins'] === null])>{{ $nilai($row['downtime_mins'], 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr data-testid="daily-recap-row-total">
                                    <td>Total periode</td>
                                    <td>{{ $cacah($coverage['filled_slots']) }} <small>dari {{ $cacah($coverage['expected_slots']) }}</small></td>
                                    <td>{{ $nilai($production['total_ton'], 1) }}</td>
                                    <td>{{ $nilai($production['avg_rate_ton_hour'], 2) }} <small>(rata-rata)</small></td>
                                    <td>{{ $nilai($metrics['clarification_tank_temp_c']['avg'], 1) }} <small>(rata-rata)</small></td>
                                    <td>{{ $nilai($metrics['oil_tank_temperature_c']['avg'], 1) }} <small>(rata-rata)</small></td>
                                    <td>{{ $nilai($metrics['sludge_tank_temp_c']['avg'], 1) }} <small>(rata-rata)</small></td>
                                    <td>{{ $downtime['reading_count'] === 0 ? '–' : $cacah($downtime['total_mins']) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </section>
        @endif
    @endif
</div>
