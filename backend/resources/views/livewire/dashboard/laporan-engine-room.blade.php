{{--
    Laporan Periode Engine Room (web) — screen-158--laporan-engine-room-web.

    APA YANG DILAPORKAN HALAMAN INI: APAKAH PABRIK PUNYA TENAGA, DAN APAKAH
    TENAGANYA STABIL sepanjang satu Periode Pelaporan pada SATU production
    line. Ini laporan KONDISI, bukan laporan keluaran. Satu record = satu hari
    kerja satu unit Engine Room; satu baris = satu slot waktu dengan 23 kolom
    ukur, 2 kolom status kategoris, dan 2 kolom teks bebas.

    INI LAPORAN TERBESAR SEJAUH INI, dan ukurannya sendiri yang menentukan
    bentuknya. DELAPAN HAL YANG MENENTUKAN TATA LETAKNYA:

    1. KELENGKAPAN PENCATATAN DIRENDER PALING ATAS, lengkap dengan ketiga
       angka pembentuk penyebutnya (unit Engine Room yang benar-benar punya
       record, hari yang dihitung, 24 slot kanonis per hari). Periode yang
       terisi seperlima pun menghasilkan rata-rata yang terlihat rapi, dan
       pembaca harus melihat itu SEBELUM mempercayainya — kelengkapan di sini
       adalah ISI laporan, bukan metadata. Ketika tidak ada satu slot pun yang
       diharapkan (belum ada unit tercatat, atau periode belum mulai),
       persennya dirender sebagai tanda pisah dengan keterangan "belum ada
       slot yang diharapkan" — bukan nol persen, yang akan terbaca seolah
       pencatatan gagal.

    2. DUA PULUH TIGA KARTU MEMBAWA PENYEBUTNYA MASING-MASING, dan MARKUP
       KARTUNYA DITULIS SEKALI lalu diulang lewat perulangan atas field
       `group` DARI RESPONS — bukan atas daftar nama kolom yang dipaku di
       template. Dua puluh tiga kartu yang ditulis manual adalah dua puluh
       tiga kesempatan kehilangan penyebut atau mewarisi penyebut kartu lain,
       dan penyebut yang SAMA pada 23 kartu adalah cacat utama yang dicari uji
       layar ini. Keuntungan kedua: kolom baru dari migrasi kelak masuk ke
       blok yang benar tanpa menyunting berkas ini.

    3. ENAM BLOK BERJUDUL, MURNI TATA LETAK: Turbin Uap, Genset 1, Genset 2,
       Kelistrikan Tersinkron, Kompresor, Penunjang. Tidak satu pun angka
       dihitung per blok dan tidak ada total per blok — satuan di dalam satu
       blok pun berbeda. Dua puluh tiga kartu seragam tanpa batas tidak
       terbaca; itu satu-satunya alasan blok ini ada.

    4. DUA PASANGAN YANG HARUS TETAP TERPISAH, dan ini pembeda terpentingnya.
       Genset 1 dan Genset 2 membawa EMPAT KOLOM YANG SAMA PERSIS — beban kW,
       ampere, suhu jacket water, tekanan lube oil. Angkanya TIDAK PERNAH
       dirata-ratakan menjadi satu dan selisihnya TIDAK ditandai sebagai
       penyimpangan: rata-rata gabungan menyembunyikan ketidakseimbangan beban
       yang justru menjadi alasan parameter itu diukur. Satu genset menanggung
       80% beban sementara yang lain menganggur adalah TEMUAN; rata-rata
       keduanya menyembunyikannya sebagai 'normal'. Keterangan di kedua blok
       menyebut kesejajaran FISIK kedua mesin dan BUKAN standar bersama —
       berbeda dari pasangan ripple mill / kernel silo di Kernel Plant yang
       memang berbagi satu baris master target; stasiun ini tidak punya master
       target sama sekali.

    5. DUA KOLOM STATUS BERNILAI TIGA, bukan dua, dan 'STANDBY' BUKAN 'OFF':
       genset siaga siap menanggung beban, genset mati tidak. Melipat standby
       ke off akan melaporkan pembangkit mati yang tidak pernah terjadi — dan
       itu kesimpulan yang salah tentang kesiapan pabrik. Nilai dikenal yang
       tidak pernah terjadi tetap tampil sebagai baris NOL ("tidak pernah
       siaga" harus terbaca berbeda dari "tidak dilaporkan"), "tidak tercatat"
       adalah barisnya sendiri, dan porsinya memakai jumlah SLOT TERCATAT
       sebagai penyebut.

    6. SATU KOLOM YANG TIDAK DIPERLAKUKAN SEPERTI DUA PULUH DUA LAINNYA:
       ekspor energi harian. Nama kolomnya menyebut 'daily' sementara ia
       disimpan PER SLOT WAKTU, dan ketidakcocokan itu belum terjelaskan di
       sumber mana pun. Karena itu laporan ini TIDAK menerbitkan rata-rata
       periode untuknya; ia menerbitkan nilai TERAKHIR hari itu sebagai seri
       per tanggal — perlakuan yang aman di bawah kedua bacaan semantik — dan
       menyatakan ketidakpastiannya di layar alih-alih memilih satu bacaan
       diam-diam. Serinya diletakkan BERDAMPINGAN dengan tren beban total
       justru supaya perbedaan agregasinya dapat dinyatakan.

    7. TIDAK ADA BAGIAN DOWNTIME, dan ketiadaan itu DINYATAKAN alih-alih
       dibiarkan sebagai lubang: engine_room_details tidak punya satu pun
       kolom downtime. Bagian yang hilang tanpa keterangan tidak dapat
       dibedakan dari bagian yang gagal dimuat.

    8. DAN TIDAK ADA PENANDAAN NILAI DI LUAR BATAS PADA METRIK MANA PUN,
       dengan pernyataannya dirender UTUH tanpa diringkas, menempel pada blok
       Kelistrikan Tersinkron. Stasiun ini tidak punya tabel master target,
       sementara frekuensi sistem 50 Hz, power factor, dan tegangan busbar
       nominal punya batas yang dikenal SANGAT luas di luar aplikasi —
       sehingga godaan menandai penyimpangan paling besar justru di sini.
       Kotak keterangannya memakai kelas `.md-explain`, bukan `.md-threshold`:
       ketiadaan penandaan diasersi MENURUT NAMA KELAS pada HTML ter-render,
       jadi memakai kelas bernama 'threshold' untuk menjelaskan bahwa tidak
       ada ambang akan memerahkan asersi itu justru karena namanya.

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

    // ------------------------------------------------------------------
    // EKSPOR ENERGI HARIAN — kunci terpisah, perlakuan terpisah.
    //
    // TIDAK ADA rata-rata periode untuk kolom ini, dan itu disengaja.
    // Yang ada: nilai TERAKHIR per tanggal. Seluruh angka di kartunya dan
    // di serinya diturunkan dari seri itu, bukan dari `metrics` — kolom ini
    // memang tidak ada di sana.
    // ------------------------------------------------------------------
    $energyExport = $summary['daily_energy_export'] ?? ['series' => [], 'has_data' => false];
    $energySeries = $energyExport['series'] ?? [];
    $energyHasData = (bool) ($energyExport['has_data'] ?? false);
    $energyNilai = array_values(array_filter(
        array_map(fn ($point) => $point['last_value'], $energySeries),
        fn ($value) => $value !== null,
    ));
    $energyMin = $energyNilai === [] ? null : min($energyNilai);
    $energyMaks = $energyNilai === [] ? null : max($energyNilai);
    // Titik TERAKHIR seri = nilai terakhir tanggal terakhir yang berdata.
    $energyTerakhir = $energySeries === [] ? null : $energySeries[count($energySeries) - 1];
    // Penyebut kolom ini: jumlah slot yang mengisinya sepanjang periode.
    $energyPembacaan = array_sum(array_map(fn ($point) => (int) $point['reading_count'], $energySeries));
    // Peta tanggal => titik, dipakai kolom ekspor energi pada rekap harian.
    $energyPerTanggal = [];
    foreach ($energySeries as $point) {
        $energyPerTanggal[$point['date']] = $point;
    }

    // Label + satuan + jumlah desimal tiap kolom ukur. Dipakai kartu metrik,
    // rekap per unit, dan rekap harian — satu sumber, sehingga satu perubahan
    // label tidak mungkin meninggalkan dua ejaan di layar. Kedua kolom status
    // dan kedua kolom teks bebas TIDAK ADA di daftar ini dan memang tidak
    // boleh ada.
    //
    // LABELNYA DITULIS MANUSIAWI, TIDAK DITURUNKAN MEKANIS DARI NAMA KOLOM,
    // dan dua di antaranya tidak boleh diturunkan sama sekali:
    //   - pasangan kompresor dinamai TIDAK KONSISTEN di basis data
    //     (`air_compressor_1_pressure_bar` melawan `compressor_2_pressure_bar`);
    //   - `fuel_tank_level` satu-satunya kolom numerik stasiun ini TANPA
    //     satuan di namanya, jadi labelnya TIDAK MENGARANG satuan — persen
    //     atau liter belum jelas, dan keduanya menghasilkan pembacaan yang
    //     sangat berbeda.
    $metrikMeta = [
        'steam_turbine_inlet_pressure_bar' => ['label' => 'Tekanan Masuk', 'pendek' => 'Tekanan Masuk Turbin (bar)', 'satuan' => 'bar', 'digits' => 1],
        'steam_turbine_inlet_temp_c' => ['label' => 'Suhu Masuk', 'pendek' => 'Suhu Masuk Turbin (°C)', 'satuan' => '°C', 'digits' => 0],
        'steam_turbine_exhaust_pressure_bar' => ['label' => 'Tekanan Buang', 'pendek' => 'Tekanan Buang (bar)', 'satuan' => 'bar', 'digits' => 2],
        'steam_turbine_rpm' => ['label' => 'Putaran', 'pendek' => 'Putaran (rpm)', 'satuan' => 'rpm', 'digits' => 0],
        'steam_turbine_alternator_bearing_temp_1_c' => ['label' => 'Suhu Bearing Alternator 1', 'pendek' => 'Bearing Alt. 1 (°C)', 'satuan' => '°C', 'digits' => 1],
        'steam_turbine_alternator_bearing_temp_2_c' => ['label' => 'Suhu Bearing Alternator 2', 'pendek' => 'Bearing Alt. 2 (°C)', 'satuan' => '°C', 'digits' => 1],
        'diesel_gen_1_load_kw' => ['label' => 'Beban', 'pendek' => 'G1 Beban (kW)', 'satuan' => 'kW', 'digits' => 0],
        'diesel_gen_1_amperage_a' => ['label' => 'Ampere', 'pendek' => 'G1 Ampere (A)', 'satuan' => 'A', 'digits' => 0],
        'diesel_gen_1_jacket_water_temp_c' => ['label' => 'Suhu Jacket Water', 'pendek' => 'G1 Jacket Water (°C)', 'satuan' => '°C', 'digits' => 1],
        'diesel_gen_1_lube_oil_pressure_bar' => ['label' => 'Tekanan Lube Oil', 'pendek' => 'G1 Lube Oil (bar)', 'satuan' => 'bar', 'digits' => 2],
        'diesel_gen_2_load_kw' => ['label' => 'Beban', 'pendek' => 'G2 Beban (kW)', 'satuan' => 'kW', 'digits' => 0],
        'diesel_gen_2_amperage_a' => ['label' => 'Ampere', 'pendek' => 'G2 Ampere (A)', 'satuan' => 'A', 'digits' => 0],
        'diesel_gen_2_jacket_water_temp_c' => ['label' => 'Suhu Jacket Water', 'pendek' => 'G2 Jacket Water (°C)', 'satuan' => '°C', 'digits' => 1],
        'diesel_gen_2_lube_oil_pressure_bar' => ['label' => 'Tekanan Lube Oil', 'pendek' => 'G2 Lube Oil (bar)', 'satuan' => 'bar', 'digits' => 2],
        'electrical_sync_total_factory_load_kw' => ['label' => 'Beban Total Pabrik', 'pendek' => 'Beban Total (kW)', 'satuan' => 'kW', 'digits' => 0],
        'electrical_sync_system_frequency_hz' => ['label' => 'Frekuensi Sistem', 'pendek' => 'Frekuensi (Hz)', 'satuan' => 'Hz', 'digits' => 2],
        'electrical_sync_power_factor' => ['label' => 'Power Factor', 'pendek' => 'Power Factor', 'satuan' => '', 'digits' => 3],
        'electrical_sync_busbar_voltage_v' => ['label' => 'Tegangan Busbar', 'pendek' => 'Tegangan Busbar (V)', 'satuan' => 'V', 'digits' => 0],
        'air_compressor_1_pressure_bar' => ['label' => 'Tekanan Kompresor Udara 1', 'pendek' => 'Kompresor Udara 1 (bar)', 'satuan' => 'bar', 'digits' => 1],
        'compressor_2_pressure_bar' => ['label' => 'Tekanan Kompresor 2', 'pendek' => 'Kompresor 2 (bar)', 'satuan' => 'bar', 'digits' => 1],
        'battery_charger_ups_voltage_v' => ['label' => 'Tegangan UPS / Battery Charger', 'pendek' => 'Tegangan UPS (V)', 'satuan' => 'V', 'digits' => 1],
        'fuel_tank_level' => ['label' => 'Level Tangki Bahan Bakar', 'pendek' => 'Level Tangki', 'satuan' => '', 'digits' => 0],
    ];

    // Ekspor energi harian punya metanya sendiri, DI LUAR $metrikMeta, karena
    // ia bukan bagian dari `metrics`: tidak ada rata-rata periode untuknya.
    $energyMeta = ['label' => 'Ekspor Energi Harian', 'pendek' => 'Ekspor Energi (kWh)', 'satuan' => 'kWh', 'digits' => 0];

    // ENAM BLOK BERJUDUL, disusun sebagai baris-baris tata letak. Kolom tiap
    // blok TIDAK didaftar di sini — ia diturunkan dari field `group` pada
    // respons (lihat $kolomGrup), supaya kolom baru dari migrasi kelak masuk
    // ke blok yang benar tanpa menyunting berkas ini.
    $barisBlok = [
        ['row' => '', 'blok' => [
            ['group' => 'steam_turbine', 'testid' => 'metric-block-steam-turbine', 'judul' => 'Turbin Uap', 'hint' => 'masing-masing dengan penyebutnya sendiri', 'grid' => 'md-kpis--3', 'catatan' => null],
        ]],
        ['row' => 'md-row--2', 'blok' => [
            ['group' => 'diesel_gen_1', 'testid' => 'metric-block-diesel-gen-1', 'judul' => 'Genset 1', 'hint' => 'mesin fisik tersendiri', 'grid' => 'md-kpis--2', 'catatan' => 'genset'],
            ['group' => 'diesel_gen_2', 'testid' => 'metric-block-diesel-gen-2', 'judul' => 'Genset 2', 'hint' => 'kolom yang sama persis dengan Genset 1 &middot; mesin fisik tersendiri', 'grid' => 'md-kpis--2', 'catatan' => 'genset'],
        ]],
        ['row' => '', 'blok' => [
            ['group' => 'electrical_sync', 'testid' => 'metric-block-electrical-sync', 'judul' => 'Kelistrikan Tersinkron', 'hint' => 'angka sistem, bukan angka per mesin', 'grid' => 'md-kpis--4', 'catatan' => 'ambang'],
        ]],
        ['row' => 'md-row--2', 'blok' => [
            ['group' => 'compressor', 'testid' => 'metric-block-compressor', 'judul' => 'Kompresor', 'hint' => 'dua metrik tekanan', 'grid' => 'md-kpis--2', 'catatan' => 'kompresor'],
            ['group' => 'auxiliary', 'testid' => 'metric-block-auxiliary', 'judul' => 'Penunjang', 'hint' => 'satu di antaranya diperlakukan berbeda', 'grid' => 'md-kpis--3', 'catatan' => 'penunjang'],
        ]],
    ];

    // Kolom-kolom sebuah blok, DITURUNKAN DARI RESPONS: setiap entri `metrics`
    // membawa `group`-nya sendiri. Inilah yang membuat kartunya satu markup
    // dan bukan dua puluh tiga salinan.
    $kolomGrup = fn (string $group) => array_keys(array_filter(
        $metrics,
        fn ($metric) => ($metric['group'] ?? null) === $group,
    ));

    // Label manusiawi tiap nilai status — DIDELEGASIKAN ke Display::option(),
    // bukan disalin ke sini. Keputusan pemilik produk 2026-10-14: satu nilai,
    // satu label di seluruh produk — layar Detail (screen-107), formulir,
    // ekspor Data Browser, dan ekspor laporan ini SEMUANYA bersandar pada
    // Display::OPTION_LABELS (Run / Standby / Off). Mendelegasikan, bukan
    // menyalin, membuat penyimpangan tidak dapat terulang tanpa menyentuh
    // Display sendiri — dan layar laporan tidak menerjemahkannya ke Bahasa
    // Indonesia.
    //
    // Display::option() mencetak nilai di luar OPTION_LABELS APA ADANYA
    // (OPTION_LABELS[$raw] ?? $raw), jadi nilai baru dari migrasi kelak tetap
    // terlihat alih-alih hilang.
    $statusTeks = fn (string $value) => \App\Support\Display::option($value);

    $blokStatus = [
        ['column' => 'diesel_gen_1_status', 'testid' => 'status-distribution-diesel-gen-1', 'judul' => 'Sebaran Status Genset 1'],
        ['column' => 'diesel_gen_2_status', 'testid' => 'status-distribution-diesel-gen-2', 'judul' => 'Sebaran Status Genset 2'],
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

<div class="md" data-testid="laporan-engine-room">

    {{-- ============ 1. Hero: periode yang sedang dibaca ============ --}}
    <section class="md-hero" data-testid="report-hero">
        <div class="md-hero__main">
            <p class="md-hero__eyebrow">Laporan Periode &middot; Stasiun Engine Room</p>
            <h1 class="md-hero__title">{{ $selectedPeriod['name'] ?? 'Laporan Periode Engine Room' }}</h1>
            <p class="md-hero__subtitle">
                @if ($selectedPeriod)
                    {{ $summary['business_unit']['name'] ?? $businessUnitName }}
                    @if ($selectedProductionLine) &middot; {{ $selectedProductionLine['name'] }} @endif
                    &middot; {{ $tgl($selectedPeriod['start_date']) }} &ndash; {{ $tgl($selectedPeriod['end_date']) }}
                @else
                    Pembangkit dan kelistrikan pabrik sepanjang satu Periode Pelaporan pada satu production line
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
                        {{ $cacah($coverage['engine_room_unit_count']) }} unit Engine Room
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
        Hanya periode yang mencakup Engine Room yang ditampilkan, dan periode tertutup tetap dapat dibaca serta diekspor. Angka disaring menurut Production Line yang melekat pada record itu sendiri, dan keanggotaan periode mengikuti tanggal recordnya. Mengganti mill mengosongkan pilihan periode DAN production line, bukan menggantinya diam-diam. Ekspor memuat satu baris per slot waktu beserta ke-27 kolom pembacaan; kedua kolom status ditulis Run / Standby / Off, sama dengan layar Detail.
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
                menampilkan daftar Production Line, daftar Periode Pelaporan, dan laporan Engine
                Room-nya.
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
                Laporan Engine Room menghasilkan angka gabungan per line, dan mencampur beberapa
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
             Engine Room. Bukan galat — cukup arahkan ke layar yang
             membuatnya. Pemilih periode sengaja tetap kosong dan TIDAK diisi
             periode milik jenis stasiun lain. --}}
        <div class="md-empty" data-testid="no-period-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
            </span>
            <p class="md-empty__title">Belum ada Periode Pelaporan</p>
            <p class="md-empty__text">
                Mill ini belum memiliki Periode Pelaporan yang mencakup stasiun Engine Room.
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
                <span class="md-card__hint">baca ini lebih dulu &mdash; 23 metrik di bawah bersandar padanya</span>
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
                        Belum ada slot yang diharapkan &mdash; belum ada unit Engine Room yang tercatat
                        pada periode dan line ini, atau periodenya belum mulai. Persennya karena itu
                        dirender sebagai tanda pisah, bukan sebagai nol persen yang akan terbaca
                        seolah pencatatan gagal.
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
                    Slot yang diharapkan = {{ $cacah($coverage['engine_room_unit_count']) }} unit
                    Engine Room &times; {{ $cacah($coverage['days_counted']) }} hari{{ $coverage['period_running'] && (int) $coverage['days_counted'] > 0 ? ' (sampai hari ini)' : '' }}
                    &times; {{ $cacah($coverage['slots_per_unit_per_day']) }} slot
                    = {{ $cacah($coverage['expected_slots']) }}.
                    Jumlah unit adalah unit yang BENAR-BENAR punya record pada periode dan line ini,
                    bukan jumlah stasiun terdaftar; angka
                    {{ $cacah($coverage['slots_per_unit_per_day']) }} slot per hari diambil dari kisi
                    slot kanonis layar input itu sendiri (07:00 sampai 06:00 keesokan hari), bukan
                    dikarang di sini.
                </p>
            </div>
            <div class="md-explain" data-testid="coverage-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                <span>
                    <b>Seluruh angka di bawah dihitung dari pembacaan yang ada</b>, bukan dari periode
                    penuh: slot yang tidak terisi <b>tidak</b> diperlakukan sebagai nol, melainkan
                    tidak ikut dihitung sama sekali.
                    <small>
                        Sebuah slot dihitung <b>terisi</b> bila minimal satu dari <b>dua puluh
                        tujuh</b> kolom bacaannya diisi &mdash; keduapuluh tiga kolom ukur, kedua
                        kolom status genset, atau <b>kedua kolom teks bebas</b>. Definisinya dipinjam
                        dari layar input itu sendiri, bukan diturunkan ulang di sini, supaya laporan
                        dan formulir tidak pernah berbeda pendapat tentang apa yang tercatat. Karena
                        itu jumlah slot terisi dapat <b>lebih besar</b> daripada jumlah pembacaan
                        metrik mana pun: slot yang hanya mengisi catatan tindakan perawatan atau
                        temuan tetap tercatat dan tidak menambah penyebut satu metrik pun. Itu
                        <b>benar</b>, bukan ketidaksesuaian yang perlu &ldquo;diperbaiki&rdquo; —
                        yang dilampaui adalah penyebut metrik, bukan penyebut kelengkapan. Keduapuluh
                        tiga kolom ukur nullable dan terisi saling bebas, jadi <b>setiap metrik
                        menerbitkan penyebutnya sendiri</b>; satu penyebut bersama akan salah untuk
                        setidaknya dua puluh dua di antaranya. Record berstatus draft IKUT terhitung
                        pada seluruh angka laporan ini.
                    </small>
                </span>
            </div>
        </section>

        @if (! $hasData)
            {{-- Empty state (e): periode + line valid tetapi tanpa satu slot
                 terisi. Kelengkapan di atas sudah menampilkannya; kartu
                 metrik TETAP digambar dengan nilai tidak tersedia dan
                 penyebut nol — kartu yang hilang terbaca sebagai "parameter
                 itu tidak ada" — sementara kedua seri harian, rekap per unit
                 dan rekap harian TIDAK digambar: grafik kosong terbaca
                 sebagai garis datar hasil pengukuran. --}}
            <div class="md-empty" data-testid="empty-state">
                <span class="md-empty__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19h16M7 16V9M12 16V5M17 16v-4"/></svg>
                </span>
                <p class="md-empty__title">Belum ada data pada periode dan line ini</p>
                <p class="md-empty__text">
                    Tidak ada satu pun slot Engine Room yang terisi pada rentang periode ini di
                    {{ $selectedProductionLine['name'] ?? 'production line ini' }}, sehingga seluruh
                    terendah, rata-rata dan tertinggi ditampilkan sebagai tidak tersedia &mdash; bukan
                    sebagai nol. Kartu parameter di bawah tetap digambar lengkap dengan penyebut nol
                    supaya terlihat bahwa parameternya ada dan tidak terukur, sementara kedua seri
                    harian, rekap per unit dan rekap harian tidak digambar.
                </p>
            </div>
        @endif

        {{-- ============ 4. Dua puluh tiga kartu metrik, enam blok ============
             PENGELOMPOKANNYA MURNI TATA LETAK: tidak satu pun angka dihitung
             per blok dan tidak ada total per blok, karena satuan di dalam satu
             blok pun berbeda.

             MARKUP KARTUNYA DITULIS SEKALI DI SINI dan dipakai dua puluh dua
             kali lewat perulangan atas field `group` DARI RESPONS, bukan
             disalin per blok dan bukan atas daftar nama kolom yang dipaku:
             kedua puluh tiga kartu HARUS mencetak penyebutnya masing-masing,
             dan satu salinan markup berarti tidak ada satu blok pun yang bisa
             diam-diam kehilangan penyebutnya atau mewarisi penyebut blok lain.
             Kartu ke-23 (ekspor energi harian) punya markupnya sendiri karena
             ia memang bukan rata-rata periode. --}}
        @foreach ($barisBlok as $baris)
            <div class="md-row {{ $baris['row'] }}">
                @foreach ($baris['blok'] as $blok)
                    @php $kolom = $kolomGrup($blok['group']); @endphp
                    <section class="md-card" data-testid="{{ $blok['testid'] }}" data-metric-group="{{ $blok['group'] }}">
                        <header class="md-card__head">
                            <h3>{{ $blok['judul'] }}</h3>
                            <span class="md-card__hint">
                                {{ $cacah(count($kolom) + ($blok['group'] === 'auxiliary' ? 1 : 0)) }} metrik &middot; {!! $blok['hint'] !!}
                            </span>
                        </header>
                        <div class="md-kpis {{ $blok['grid'] }}">
                            @foreach ($kolom as $column)
                                @php
                                    // Kolom yang belum punya label manusiawi —
                                    // misalnya kolom baru dari migrasi —
                                    // tetap dirender memakai nama kolomnya,
                                    // bukan hilang dari layar.
                                    $meta = $metrikMeta[$column] ?? ['label' => $column, 'pendek' => $column, 'satuan' => '', 'digits' => 2];
                                    $metric = $metrics[$column];
                                @endphp
                                <article class="md-kpi" data-testid="metric-card" data-metric="{{ $column }}">
                                    <div class="md-kpi__top">
                                        <span class="md-kpi__label">{{ $meta['label'] }}</span>
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
                                         untuk kedua puluh tiga metrik akan
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

                            @if ($blok['group'] === 'auxiliary')
                                {{-- KARTU KE-23, DAN SATU-SATUNYA YANG TIDAK
                                     MENERBITKAN RATA-RATA PERIODE. Nilainya
                                     adalah nilai TERAKHIR hari terakhir yang
                                     berdata, dan terendah/tertinggi di bawahnya
                                     adalah terendah/tertinggi di antara nilai
                                     TERAKHIR tiap tanggal — bukan rata-rata,
                                     bukan nilai per slot. Alasannya ada di
                                     keterangan blok ini dan di kartu serinya. --}}
                                <article class="md-kpi" data-testid="energy-export-card" data-metric="daily_energy_export_kwh">
                                    <div class="md-kpi__top">
                                        <span class="md-kpi__label">{{ $energyMeta['label'] }}</span>
                                        <span class="md-pill" data-testid="energy-export-badge">nilai terakhir</span>
                                    </div>
                                    <p class="md-kpi__value">
                                        <span data-testid="energy-export-last">{{ $nilai($energyTerakhir['last_value'] ?? null, $energyMeta['digits']) }}</span>
                                        <span>
                                            {{ $energyMeta['satuan'] }}
                                            @if ($energyTerakhir !== null)
                                                nilai terakhir {{ $tglPendek($energyTerakhir['date']) }} {{ $energyTerakhir['time_slot'] }}
                                            @else
                                                nilai terakhir
                                            @endif
                                        </span>
                                    </p>
                                    <p class="md-kpi__meta" data-testid="energy-export-range">
                                        terendah harian {{ $nilai($energyMin, $energyMeta['digits']) }} &middot;
                                        tertinggi harian {{ $nilai($energyMaks, $energyMeta['digits']) }}
                                    </p>
                                    <p class="md-kpi__foot" data-testid="energy-export-denominator">
                                        dari <b>{{ $cacah($energyPembacaan) }}</b> pembacaan
                                        <small><b>Tanpa rata-rata periode &mdash; disengaja.</b> Terendah dan tertinggi di atas adalah nilai TERAKHIR tiap hari, bukan rata-rata. Lihat seri hariannya dan alasannya di bawah.</small>
                                    </p>
                                </article>
                            @endif
                        </div>

                        @if ($blok['catatan'] === 'genset')
                            {{-- KETERANGAN YANG SAMA DI KEDUA BLOK GENSET, dan
                                 itu disengaja: pembaca yang hanya melihat satu
                                 blok tetap harus membacanya. Ia menyebut
                                 kesejajaran FISIK kedua mesin dan TIDAK
                                 menyebut standar bersama — stasiun ini tidak
                                 punya master target sama sekali, berbeda dari
                                 pasangan ripple mill / kernel silo di Kernel
                                 Plant yang memang berbagi satu baris master. --}}
                            <div class="md-explain" data-testid="genset-parallel-note">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h11l-3-3M17 17H6l3 3"/></svg>
                                <span>
                                    <b>Genset 1 dan Genset 2 adalah dua mesin fisik yang berbeda.</b>
                                    Keempat kolomnya sejajar <b>secara fisik</b> &mdash; beban, ampere,
                                    suhu jacket water, tekanan lube oil &mdash; tetapi angkanya
                                    <b>sengaja tidak dirata-ratakan menjadi satu</b>, dan <b>selisih di
                                    antara keduanya tidak ditandai sebagai penyimpangan</b>.
                                    <small>
                                        Ketidakseimbangan beban adalah <b>temuan, bukan galat</b>: satu
                                        genset menanggung hampir seluruh beban sementara yang lain
                                        menganggur adalah hal yang perlu dilihat, dan rata-rata
                                        gabungan justru menyembunyikannya sebagai
                                        &ldquo;normal&rdquo;. Kesejajaran yang dinyatakan di sini
                                        adalah kesejajaran FISIK kedua mesin &mdash; <b>bukan standar
                                        bersama</b>: stasiun ini tidak punya tabel master target sama
                                        sekali, jadi tidak ada satu baris acuan yang keduanya bagi
                                        bersama. Genset yang tidak pernah menyala sepanjang periode
                                        menghasilkan empat kartu tidak tersedia dengan penyebut nol,
                                        dan itu keadaan operasional yang sah &mdash; bukan galat.
                                    </small>
                                </span>
                            </div>
                        @elseif ($blok['catatan'] === 'ambang')
                            {{-- PERNYATAAN KETIADAAN PENANDAAN DI LUAR BATAS,
                                 DIRENDER UTUH DAN MENEMPEL PADA BLOK INI —
                                 bukan sebagai catatan kaki halaman. Justru di
                                 blok inilah godaan menandai paling besar,
                                 karena frekuensi 50 Hz, power factor, dan
                                 tegangan busbar nominal dikenal sangat luas di
                                 luar aplikasi ini. Kotaknya memakai
                                 `.md-explain` dan BUKAN `.md-threshold`: nol
                                 elemen berkelas penanda ambang adalah invarian
                                 yang diasersi menurut nama kelas. --}}
                            <div class="md-explain" data-testid="no-threshold-note">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l9 4v5c0 5-3.8 8.4-9 9.9C6.8 20.4 3 17 3 12V7z"/><path d="M12 8v4M12 15h.01"/></svg>
                                <span>
                                    <b>TIDAK ADA penandaan nilai di luar batas pada metrik mana pun di
                                    layar ini, dan pernyataan ini dirender utuh tanpa diringkas.</b>
                                    Stasiun Engine Room tidak punya tabel master target. Yang membuat
                                    pernyataan ini lebih keras daripada di laporan mana pun sebelumnya:
                                    beberapa metrik di blok ini punya batas yang <b>dikenal sangat luas
                                    di luar aplikasi</b> &mdash; frekuensi sistem 50 Hz, power factor,
                                    dan tegangan busbar nominal &mdash; sehingga godaan menandai
                                    penyimpangan paling besar justru di sini.
                                    <small>
                                        Laporan ini tetap <b>tidak menurunkan satu pun ambang dari data
                                        periode itu sendiri</b>, dan tidak menyatakan satu nilai pun
                                        berada di luar batas. Alasannya bukan selera tampilan: ambang
                                        yang diturunkan dari sebaran periode hanyalah <b>turunan
                                        statistik</b> atas data yang justru sedang dinilainya,
                                        sementara pembaca akan membacanya sebagai <b>batas keselamatan
                                        kelistrikan</b>. Nilai yang jauh dari nominal ditampilkan apa
                                        adanya, tanpa satu kelas atau warna peringatan. Bila kelak
                                        batas operasional kelistrikan dimasukkan sebagai master target,
                                        laporan ini perlu ditinjau ulang &mdash; dan peninjauan itu
                                        menyangkut keselamatan peralatan, bukan sekadar tampilan.
                                    </small>
                                </span>
                            </div>
                        @elseif ($blok['catatan'] === 'kompresor')
                            <div class="md-explain" data-testid="compressor-label-note">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                                <span>
                                    Label kedua kartu ini <b>ditulis manusiawi dan tidak diturunkan
                                    mekanis dari nama kolom</b>, karena pasangannya dinamai tidak
                                    konsisten di basis data: <code>air_compressor_1_pressure_bar</code>
                                    melawan <code>compressor_2_pressure_bar</code>.
                                    <small>
                                        Apakah &lsquo;compressor 2&rsquo; juga kompresor udara atau
                                        kompresor jenis lain masih pertanyaan terbuka, jadi laporan ini
                                        tidak menyamakan keduanya, tidak menggabungkan angkanya, dan
                                        tidak membandingkannya.
                                    </small>
                                </span>
                            </div>
                        @elseif ($blok['catatan'] === 'penunjang')
                            <div class="md-explain" data-testid="auxiliary-label-note">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="17" rx="2"/><path d="M4 13h16"/></svg>
                                <span>
                                    <b>Level tangki bahan bakar ditulis tanpa satuan</b>, dan itu
                                    disengaja: kolomnya satu-satunya kolom numerik stasiun ini yang
                                    tidak membawa satuan di namanya &mdash; dua puluh dua lainnya
                                    membawa <code>_bar</code>, <code>_c</code>, <code>_kw</code>,
                                    <code>_a</code>, <code>_hz</code>, <code>_v</code>, atau
                                    <code>_kwh</code>.
                                    <small>
                                        Persen atau liter belum jelas, dan keduanya menghasilkan
                                        pembacaan yang sangat berbeda: level 90 berarti hampir penuh
                                        bila persen, dan hampir kosong bila liter pada tangki besar.
                                        Laporan ini tidak mengarang satuannya sampai pertanyaan itu
                                        dijawab. <b>Ekspor energi harian pun diperlakukan berbeda</b>
                                        dari kedua kartu di sebelahnya &mdash; ia tidak punya rata-rata
                                        periode sama sekali; lihat kartu serinya di bawah.
                                    </small>
                                </span>
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>
        @endforeach

        {{-- ============ 5. Dua sebaran status kategoris — TIGA nilai ============ --}}
        <div class="md-row md-row--2">
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
                                     yang tidak pernah ada di basis data, dan
                                     "tidak pernah siaga" harus terbaca berbeda
                                     dari "tidak dilaporkan". Perulangannya atas
                                     known_values, BUKAN atas kunci counts yang
                                     ada. --}}
                                @foreach ($dist['known_values'] as $value)
                                    <tr data-testid="status-row" data-status-value="{{ $value }}">
                                        <td>{{ $statusTeks($value) }}</td>
                                        <td>{{ $cacah($dist['counts'][$value] ?? 0) }}</td>
                                        <td class="is-muted">{{ $porsi((int) ($dist['counts'][$value] ?? 0), (int) $dist['recorded_slots']) }}</td>
                                    </tr>
                                @endforeach
                                {{-- BARISNYA SENDIRI, SELALU. Slot yang tidak
                                     mencatat kolom ini TIDAK PERNAH
                                     diperlakukan sebagai Run, Standby, maupun
                                     Off. --}}
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
                </section>
            @endforeach
        </div>

        {{-- Keterangan bersama kedua sebaran. 'STANDBY' BUKAN 'OFF' dirender
             sebagai pernyataannya sendiri, karena melipat keduanya adalah
             kesalahan yang akibatnya paling besar di layar ini: ia melaporkan
             pembangkit mati yang tidak pernah terjadi. --}}
        <div class="md-explain" data-testid="standby-not-off-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h11l-3-3M17 17H6l3 3"/></svg>
            <span>
                <b>&ldquo;Standby&rdquo; BUKAN &ldquo;Off&rdquo;.</b> Genset siaga siap menanggung
                beban; genset mati tidak. Melipat Standby ke Off akan melaporkan pembangkit mati yang
                tidak pernah terjadi &mdash; dan itu kesimpulan yang salah tentang kesiapan pabrik.
                <small>
                    Label <b>Run / Standby / Off</b> dipakai apa adanya dari
                    <code>Display::OPTION_LABELS</code>, sama dengan layar Detail, formulir, dan kedua
                    berkas ekspor; layar laporan <b>tidak menerjemahkannya sendiri</b>, karena satu
                    nilai hanya boleh punya satu label di seluruh produk.
                </small>
            </span>
        </div>

        <div class="md-explain" data-testid="status-distribution-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
            <span>
                <b>Kedua kolom di atas kategoris</b>: terendah, rata-rata dan tertinggi tidak punya
                arti untuknya, jadi keduanya diterbitkan sebagai sebaran jumlah slot per nilai &mdash;
                tidak satu pun dari keduanya muncul di kartu metrik maupun di seri harian.
                <small>
                    Sebaran disusun dengan <b>mengelompokkan nilai yang benar-benar ada</b>, lalu
                    melengkapi nilai yang dikenal tetapi tidak muncul dengan jumlah <b>nol</b> &mdash;
                    itulah sebabnya sebuah nilai yang tidak pernah terjadi sepanjang periode tetap
                    tampil sebagai baris nol, bukan hilang dari daftar. Pengelompokan harfiah tetap
                    dipakai supaya menambah nilai baru lewat migrasi kelak tidak diam-diam
                    menghilangkan satu kelompok. <b>&ldquo;Tidak tercatat&rdquo; adalah keadaan
                    tersendiri</b> dan tidak pernah diperlakukan sebagai Run, Standby, maupun Off:
                    &ldquo;tidak kami tulis&rdquo; dan &ldquo;memang tidak jalan&rdquo; adalah dua
                    kenyataan yang berbeda akibatnya. <b>Porsinya memakai jumlah slot tercatat sebagai
                    penyebut</b>, bukan jumlah slot yang diharapkan &mdash; yang kedua akan mencampur
                    slot tak tersentuh ke dalam porsi sebuah nilai yang justru tercatat.
                </small>
            </span>
        </div>

        <div class="md-explain" data-testid="no-downtime-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
            <span>
                <b>Stasiun ini tidak mencatat lama berhenti sama sekali</b>, jadi laporan ini memang
                tidak punya bagiannya &mdash; tidak ada kolomnya di formulir Engine Room, dan
                ketiadaannya disengaja, bukan terlewat atau gagal dimuat.
                <small>
                    Dinyatakan di sini justru karena bagian yang hilang tanpa keterangan tidak dapat
                    dibedakan dari bagian yang gagal dimuat &mdash; dan karena laporan stasiun lain
                    memilikinya, sehingga pembaca yang datang dari sana akan mencarinya.
                </small>
            </span>
        </div>

        {{-- ============ 6. Rekap Catatan Tindakan Perawatan ============
             DUA BAGIAN TERPISAH dengan Rekap Temuan di bawahnya, dan itu
             disengaja: satu menjawab apa yang dikerjakan, satu menjawab apa
             yang ditemukan. --}}
        <section class="md-card" data-testid="maintenance-remarks">
            <header class="md-card__head">
                <h3>Rekap Catatan Tindakan Perawatan</h3>
                <span class="md-card__hint">
                    {{ $cacah(count($maintenanceRemarks)) }} slot mengisi kolom catatan tindakan perawatan sepanjang periode
                </span>
            </header>
            @if ($maintenanceRemarks === [])
                <p class="md-card__hint" data-testid="maintenance-remarks-empty">
                    Tidak ada satu pun catatan tindakan perawatan tercatat pada periode dan line ini.
                    Ini dinyatakan sebagai keterangan, bukan tabel kosong tanpa penjelasan &mdash;
                    tabel kosong tidak membedakan &ldquo;tidak ada perawatan&rdquo; dari &ldquo;tidak
                    ada yang mencatatnya&rdquo;.
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
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16v11H9l-5 4z"/></svg>
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
                    sebagai keterangan tersendiri, bukan digabung ke bagian catatan tindakan perawatan
                    dan bukan tabel kosong tanpa penjelasan.
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
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16v11H9l-5 4z"/></svg>
                <span>
                    Daftar ini menjawab <b>apa yang ditemukan</b> &mdash; pertanyaan yang berbeda dari
                    Rekap Catatan Tindakan Perawatan di atasnya, dan karena itu keduanya <b>dua bagian
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

            {{-- ============ 8. Dua seri harian dengan agregasi BERBEDA ============
                 Diletakkan berdampingan justru supaya perbedaan agregasinya
                 dapat dinyatakan: yang kiri memuat nilai TERAKHIR hari itu,
                 yang kanan memuat RATA-RATA hari itu. Bentuk batangnya bisa
                 mirip, tetapi angkanya menjawab dua pertanyaan berbeda. --}}
            <div class="md-row md-row--2">
                {{-- 8a. Seri ekspor energi — NILAI TERAKHIR hari itu. --}}
                <section class="md-card" data-testid="daily-energy-export">
                    <header class="md-card__head">
                        <h3>Seri Harian Ekspor Energi</h3>
                        <span class="md-card__hint">
                            <b>Nilai TERAKHIR hari itu</b>, dalam {{ $energyMeta['satuan'] }} &middot;
                            {{ $cacah(count($energySeries)) }} tanggal berdata
                        </span>
                    </header>
                    @if (! $energyHasData)
                        {{-- Kolom ini tidak pernah terisi sepanjang periode:
                             dinyatakan sebagai tidak ada data, dan TIDAK
                             digambar sebagai garis nol — garis nol mengklaim
                             pengukuran yang tidak pernah dilakukan. --}}
                        <p class="md-card__hint" data-testid="daily-energy-export-empty">
                            Kolom ekspor energi harian tidak terisi pada satu slot pun sepanjang
                            periode dan line ini, jadi serinya tidak menampilkan satu titik pun
                            &mdash; bukan garis nol, yang akan terbaca sebagai hasil pengukuran.
                        </p>
                    @else
                        <div class="md-trendchart md-trendchart--days">
                            @foreach ($energySeries as $point)
                                {{-- Tanpa modifier warna apa pun: tidak ada
                                     batang yang ditandai "rendah", "tinggi",
                                     maupun di luar batas di layar ini. Tiap
                                     titik membawa SLOT ASALNYA supaya pembaca
                                     dapat memeriksa nilai mana yang dipakai. --}}
                                <div class="md-trendchart__col" data-testid="daily-energy-export-col-{{ $point['date'] }}" data-time-slot="{{ $point['time_slot'] }}">
                                    <span class="md-trendchart__val">{{ $nilai($point['last_value'], $energyMeta['digits']) }}</span>
                                    <div class="md-trendchart__bar" style="height: {{ $tinggiBatang($point['last_value'], $energyMin, $energyMaks) }}%"></div>
                                    <span class="md-trendchart__lbl">{{ $tglPendek($point['date']) }}</span>
                                </div>
                            @endforeach
                        </div>
                        @if (count($energySeries) > 10)
                            <p class="md-scrollhint" data-testid="daily-energy-export-scroll-hint">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M15 8l4 4-4 4M9 8l-4 4 4 4"/></svg>
                                Geser mendatar untuk melihat seluruh tanggal &mdash; tiap kolom selebar
                                labelnya supaya label tanggal tidak bertumpuk.
                            </p>
                        @endif
                        <ul class="md-legend">
                            <li class="md-legend__item">
                                Terendah harian {{ $nilai($energyMin, $energyMeta['digits']) }} &middot;
                                tertinggi harian {{ $nilai($energyMaks, $energyMeta['digits']) }}
                                <small>(di antara nilai TERAKHIR tiap tanggal)</small>
                            </li>
                            <li class="md-legend__item">
                                Dari {{ $cacah($energyPembacaan) }} pembacaan sepanjang periode
                                <small>(tanpa rata-rata periode &mdash; lihat keterangan di bawah)</small>
                            </li>
                            <li class="md-legend__item">
                                @if ($energyTerakhir !== null)
                                    Tiap titik membawa slot asal nilainya; titik terakhir berasal dari
                                    {{ $tgl($energyTerakhir['date']) }} slot {{ $energyTerakhir['time_slot'] }}
                                @endif
                                <small>Sumbu tegak tidak dimulai dari nol &mdash; batang membandingkan tanggal, bukan besaran mutlak. Tidak ada batang yang ditandai di luar batas.</small>
                            </li>
                        </ul>
                    @endif
                    <div class="md-explain" data-testid="daily-energy-export-note">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 12l4-2.5"/><path d="M12 3v2M3 12h2M19 12h2"/></svg>
                        <span>
                            <b>Seri ini berisi nilai TERAKHIR hari itu, bukan rata-rata &mdash; dan
                            tidak ada rata-rata periode untuk kolom ini sama sekali.</b> Sebabnya: nama
                            kolomnya, <code>daily_energy_export_kwh</code>, menyebut
                            <b>&lsquo;daily&rsquo;</b> sementara ia disimpan <b>per slot waktu</b>, dan
                            ketidakcocokan itu belum terjelaskan di sumber mana pun &mdash; tidak di
                            kode, tidak di keenam spec layar Engine Room yang sudah ada.
                            <small>
                                Dua bacaan sama-sama masuk akal: (a) ia angka meter <b>kumulatif
                                harian</b> yang dicatat ulang tiap slot, atau (b) ia energi yang
                                diekspor <b>pada slot itu</b> dan kata &lsquo;daily&rsquo; salah nama.
                                Di bawah bacaan (a), merata-ratakan atau menjumlahkannya antar 24 slot
                                menghasilkan angka yang <b>terlihat sah dan sepenuhnya salah</b>.
                                Nilai terakhir per tanggal aman di bawah <i>kedua</i> bacaan, jadi
                                itulah yang diterbitkan &mdash; dan ketidakpastiannya dinyatakan di
                                sini alih-alih dipilih diam-diam. <b>&ldquo;Terakhir&rdquo; diambil
                                dari posisi slot pada kisi kanonis</b> (07:00 sampai 06:00 keesokan
                                hari), bukan dari perbandingan angka jam: 06:00 adalah slot paling
                                AKHIR, bukan paling awal. Nilai yang <b>turun</b> dibanding slot
                                sebelumnya pada hari yang sama ditampilkan apa adanya tanpa dikoreksi
                                dan tanpa penanda galat &mdash; keadaan itu mustahil bila kolom ini
                                angka meter kumulatif, jadi ia justru bukti yang berguna untuk
                                menjawab pertanyaan terbuka ini.
                            </small>
                        </span>
                    </div>
                </section>

                {{-- 8b. Tren beban total pabrik — RATA-RATA hari itu. --}}
                @foreach ([
                    ['column' => 'electrical_sync_total_factory_load_kw', 'testid' => 'daily-trend-total-factory-load', 'judul' => 'Tren Harian Beban Total Pabrik'],
                ] as $tren)
                    @php
                        $titik = $dailyTrend[$tren['column']] ?? [];
                        $metaTren = $metrikMeta[$tren['column']];
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
                                <b>Rata-rata per tanggal</b>, dalam {{ $metaTren['satuan'] }} &middot;
                                {{ $cacah(count($titik)) }} tanggal berdata
                            </span>
                        </header>
                        <div class="md-trendchart md-trendchart--days">
                            @foreach ($titik as $row)
                                {{-- Titik tanpa pembacaan bernilai tidak
                                     tersedia, BUKAN nol — batangnya karena itu
                                     kosong dan bukan batang nol yang terbaca
                                     sebagai hasil ukur. --}}
                                <div class="md-trendchart__col" data-testid="{{ $tren['testid'] }}-col-{{ $row['date'] }}">
                                    <span class="md-trendchart__val">{{ $nilai($row['avg'], $metaTren['digits']) }}</span>
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
                                Terendah {{ $nilai($minTren, $metaTren['digits']) }} &middot;
                                tertinggi {{ $nilai($maksTren, $metaTren['digits']) }}
                                <small>(dari rata-rata harian)</small>
                            </li>
                            <li class="md-legend__item">
                                Rata-rata periode {{ $nilai($metrikTren['avg'], $metaTren['digits']) }}
                                <small>(berbobot {{ $cacah($metrikTren['reading_count']) }} pembacaan, dihitung ulang atas seluruh slot &mdash; bukan rata-rata dari rata-rata harian)</small>
                            </li>
                            <li class="md-legend__item">
                                <small>Sumbu tegak tidak dimulai dari nol &mdash; batang membandingkan tanggal, bukan besaran mutlak. Tidak ada rentang aman yang digambar di sini.</small>
                            </li>
                        </ul>
                        <div class="md-explain" data-testid="aggregation-difference-note">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                            <span>
                                <b>Agregasi kedua seri ini BERBEDA, dan perbedaan itu dinyatakan supaya
                                keduanya tidak terbaca sejenis.</b> Seri di sebelahnya memuat <b>nilai
                                terakhir</b> hari itu; seri ini memuat <b>rata-rata</b> hari itu.
                                <small>
                                    Bentuk batangnya bisa mirip, tetapi angkanya menjawab dua
                                    pertanyaan yang berbeda &mdash; satu &ldquo;berapa angka meter pada
                                    akhir hari&rdquo;, satu &ldquo;seberapa berat pabrik hari
                                    itu&rdquo;. Rata-rata di sini sah karena beban total pabrik memang
                                    besaran sesaat per slot, bukan angka kumulatif.
                                </small>
                            </span>
                        </div>
                    </section>
                @endforeach
            </div>

            {{-- ============ 9. Rekap per unit Engine Room ============
                 23 kolom rata-rata/nilai + unit + hari + slot = 26 kolom, rekap
                 TERLEBAR di antara seluruh laporan web. Ia menggulir mendatar
                 DI DALAM kartunya sendiri lewat .md-recap { overflow-x: auto },
                 dan halaman sendiri tidak pernah menggulir mendatar. --}}
            <section class="md-card" data-testid="by-unit">
                <header class="md-card__head">
                    <h3>Rekap per Unit Engine Room</h3>
                    <span class="md-card__hint">
                        angka periode di atas menggabungkan seluruh unit &mdash; tabel ini menunjukkan
                        sebarannya &middot; engine_room_id adalah NAMA UNIT yang diketik di layar
                        input, bukan kunci baris
                    </span>
                </header>
                <p class="md-scrollhint" data-testid="by-unit-scroll-hint">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M15 8l4 4-4 4M9 8l-4 4 4 4"/></svg>
                    Tabel ini membawa <b>23 kolom angka</b> (26 kolom seluruhnya) &mdash; rekap
                    terlebar di antara seluruh laporan web, jadi ia menggulir mendatar di dalam
                    kartunya sendiri dan halaman tidak pernah menggulir mendatar.
                </p>
                <div class="md-recap">
                    <table class="md-table" data-testid="by-unit-table">
                        <thead>
                            <tr>
                                <th scope="col">Unit Engine Room</th>
                                <th scope="col">Hari tercatat</th>
                                <th scope="col">Slot terisi</th>
                                @foreach ($metrikMeta as $meta)
                                    <th scope="col">{{ $meta['pendek'] }}</th>
                                @endforeach
                                <th scope="col">{{ $energyMeta['pendek'] }} <small>nilai terakhir</small></th>
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
                                    <td>{{ ($row['engine_room_id'] ?? '') !== '' ? $row['engine_room_id'] : 'Belum diisi' }}</td>
                                    <td>{{ $cacah($row['days_with_rows'] ?? 0) }}</td>
                                    <td data-testid="by-unit-reading-rows">{{ $cacah($row['reading_rows']) }}</td>
                                    @foreach ($metrikMeta as $column => $meta)
                                        <td @class(['is-muted' => ($row['metrics'][$column]['avg'] ?? null) === null])>
                                            {{ $nilai($row['metrics'][$column]['avg'] ?? null, $meta['digits']) }}
                                            <small class="is-muted">(n={{ $cacah($row['metrics'][$column]['reading_count'] ?? 0) }})</small>
                                        </td>
                                    @endforeach
                                    {{-- NILAI TERAKHIR unit ini, bukan
                                         rata-ratanya: kolom ini tidak pernah
                                         dirata-rata, di bucket mana pun. --}}
                                    <td @class(['is-muted' => ($row['energy_export']['last_value'] ?? null) === null])>
                                        {{ $nilai($row['energy_export']['last_value'] ?? null, $energyMeta['digits']) }}
                                        <small class="is-muted">(n={{ $cacah($row['energy_export']['reading_count'] ?? 0) }})</small>
                                    </td>
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
                                <td>Gabungan {{ $cacah($coverage['engine_room_unit_count']) }} unit</td>
                                <td>{{ $cacah($total['days_with_records']) }}</td>
                                <td>{{ $cacah($total['reading_rows']) }}</td>
                                @foreach ($metrikMeta as $column => $meta)
                                    <td @class(['is-muted' => $metrics[$column]['avg'] === null])>
                                        {{ $nilai($metrics[$column]['avg'], $meta['digits']) }}
                                        <small class="is-muted">(n={{ $cacah($metrics[$column]['reading_count']) }})</small>
                                    </td>
                                @endforeach
                                <td @class(['is-muted' => ($energyTerakhir['last_value'] ?? null) === null])>
                                    {{ $nilai($energyTerakhir['last_value'] ?? null, $energyMeta['digits']) }}
                                    <small class="is-muted">(n={{ $cacah($energyPembacaan) }})</small>
                                </td>
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
                        minimal satu slot terisi &middot; 23 kolom angka, menggulir mendatar di dalam
                        kartunya &middot; klik untuk membuka atau menutup
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
                                <th scope="col">{{ $energyMeta['pendek'] }} <small>nilai terakhir</small></th>
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
                                    {{-- KOLOM INI BUKAN RATA-RATA: nilai
                                         TERAKHIR tanggal itu, diambil dari seri
                                         yang sama dengan grafiknya di atas. --}}
                                    <td @class(['is-muted' => ($energyPerTanggal[$row['date']]['last_value'] ?? null) === null])>
                                        {{ $nilai($energyPerTanggal[$row['date']]['last_value'] ?? null, $energyMeta['digits']) }}
                                    </td>
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
                                {{-- SENGAJA TANDA PISAH: kolom ekspor energi
                                     TIDAK punya rata-rata periode, dan sel
                                     total yang berisi angka akan terbaca
                                     sebagai rata-rata itu. --}}
                                <td class="is-muted" data-testid="daily-total-energy-export">
                                    &mdash;
                                    <small class="is-muted">(n={{ $cacah($energyPembacaan) }})</small>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="md-explain" data-testid="daily-total-energy-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                    <span>
                        Baris total periode <b>tidak</b> menerbitkan rata-rata untuk kolom ekspor
                        energi &mdash; kolom itu hanya punya nilai per tanggal, dan sel totalnya
                        sengaja tanda pisah. Kolom per tanggalnya pun <b>nilai terakhir hari itu</b>,
                        bukan rata-rata seperti kolom-kolom lain di baris yang sama.
                    </span>
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
