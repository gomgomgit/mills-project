{{--
    Laporan Periode Process Quality Control (web) — screen-168--laporan-process-quality-control-web.

    APA YANG DILAPORKAN HALAMAN INI: BERAPA BANYAK MINYAK YANG HILANG, dan
    APAKAH MUTU CPO YANG DIJUAL MASIH DI DALAM SPESIFIKASI, sepanjang satu
    Periode Pelaporan pada SATU production line. Ini laporan KONDISI, bukan
    laporan keluaran. Satu record = satu hari kerja satu unit Process QC; satu
    baris = satu slot waktu dengan 12 kolom ukur, 2 kolom teks bebas, dan 2
    kolom identitas.

    INI LAPORAN STASIUN YANG TERAKHIR: sesudahnya seluruh delapan belas jenis
    stasiun di pabrik punya laporan periodenya sendiri.

    TUJUH HAL YANG MENENTUKAN TATA LETAKNYA:

    1. KELENGKAPAN PENCATATAN DIRENDER PALING ATAS, lengkap dengan ketiga angka
       pembentuk penyebutnya (unit Process QC yang benar-benar punya record,
       hari yang dihitung, 24 slot kanonis per hari). Periode yang terisi
       seperlima pun menghasilkan rata-rata yang terlihat rapi, dan pembaca
       harus melihat itu SEBELUM mempercayainya — kelengkapan di sini adalah
       ISI laporan, bukan metadata. Ketika tidak ada satu slot pun yang
       diharapkan (belum ada unit tercatat, atau periode belum mulai),
       persennya dirender sebagai tanda pisah dengan keterangan "belum ada slot
       yang diharapkan" — bukan nol persen, yang akan terbaca seolah pencatatan
       gagal.

    2. BARIS TERISI DIHITUNG ATAS EMPAT BELAS KOLOM, BUKAN ENAM BELAS, dan
       keterangan itu dirender UTUH di blok kelengkapan. Baris detail stasiun
       ini membawa enam belas kolom, tetapi `shift` dan `qc_inspector_id` adalah
       kolom IDENTITAS, bukan kolom pembacaan — dan itu dinyatakan eksplisit di
       docblock ProcessQualityControlRecordService, bukan ditebak. Dua akibatnya
       BERLAWANAN dan keduanya harus dapat dibedakan: slot yang hanya mengisi
       kolom TEKS BEBAS tetap terhitung terisi (kedua kolom teks termasuk
       keempat belas kolom pembacaan) tanpa menambah penyebut kartu mana pun,
       sementara slot yang hanya mengisi kedua kolom identitas TIDAK terhitung
       — walau kode inspekturnya TETAP masuk rekap per-inspektur, karena rekap
       menghitung ATRIBUSI sementara kelengkapan menghitung PEMBACAAN.
       Akibat yang pertama itulah sebabnya jumlah slot terisi dapat MELEBIHI
       penyebut setiap kartu metrik — dan kedua angka itu dicetak BERDAMPINGAN
       beserta kalimatnya, TANPA SYARAT: kalimat yang muncul bersyarat tidak
       pernah terbaca oleh orang yang paling membutuhkannya.

    3. DUA BELAS KARTU MEMBAWA PENYEBUTNYA MASING-MASING, dan MARKUP KARTUNYA
       DITULIS SEKALI lalu diulang lewat perulangan atas field `group` DARI
       RESPONS — bukan atas daftar nama kolom yang dipaku di template. Penyebut
       yang SAMA pada dua belas kartu adalah cacat utama yang dicari uji layar
       ini. Keuntungan kedua: kolom baru dari migrasi kelak masuk ke blok yang
       benar tanpa menyunting berkas ini.

    4. LIMA BLOK BERJUDUL MENGIKUTI JALUR FISIK MINYAK, bukan abjad dan bukan
       urutan kolom di basis data: 1 Fruit Press → 2 Purifier & Clarification
       Balance → 3 Vacuum Drying Station → 4 Decanter/Centrifuge → 5 Final
       Storage. Dua belas angka yang berdiri sendiri tidak dapat dibaca; lima
       blok yang masing-masing menjawab satu pertanyaan tentang satu tahap
       dapat. Urutannya bekerja: pembaca yang menemukan angka mutu akhir yang
       tidak ia harapkan dapat BERGERAK MUNDUR satu tahap demi satu tahap tanpa
       perlu tahu nama kolom mana pun. Tidak satu pun angka dihitung per blok
       dan tidak ada total per blok — satuan di dalam satu blok pun berbeda
       (laju umpan MT/jam bersebelahan dengan oil loss persen). KELIMA BLOK
       TETAP DIRENDER meski seluruh ukurannya kosong: blok yang hilang akan
       dibaca sebagai tahap yang tidak ada.

    5. SATUAN DIAMBIL DARI RESPONS, dan SATU DARI DUA BELAS SENGAJA TANPA
       SATUAN: Indeks DOBI adalah indeks tanpa dimensi, bukan persentase.
       Elemen satuan karena itu dirender HANYA ketika respons membawa satuan;
       `unit ?? ''` akan tetap merender elemen hampa dan dari situ tanda persen
       bisa mendarat di kartu DOBI — kesalahan yang akan terlihat benar, karena
       ketiga kartu tetangganya di blok yang sama memang berpersen.

    6. TIDAK ADA SATU PUN BLOK SEBARAN KATEGORIS DI HALAMAN INI, dan itu
       PEMBEDA PENTINGNYA dari Engine Room dan Effluent Plant yang keduanya
       memang punya. Stasiun ini TIDAK PUNYA SATU PUN kolom berpilihan tertutup
       — tak satu pun dari keenam belas kolom barisnya, dan kedua kolom
       identitasnya pun teks bebas. Kedua kolom catatan karena itu dirender
       sebagai DUA REKAP TEKS TERPISAH, satu entri per slot, dan isi yang
       berulang sama persis TIDAK dikelompokkan — mengelompokkan teks bebas
       berarti memperlakukan prosa sebagai enum.

    7. DAN EMPAT KETIADAAN DINYATAKAN, bukan dibiarkan sebagai lubang: tidak
       ada rekap berlabel (nol kolom berenum), tidak ada bagian downtime
       (stasiun ini tidak mencatatnya), tidak ada kolom `shift` di layar (ia
       tetap ada di berkas ekspor), dan TIDAK ADA PENANDAAN NILAI DI LUAR BATAS
       pada metrik mana pun — dengan pernyataan terakhir dirender UTUH tanpa
       diringkas, menempel pada blok Final Storage. Justru di blok itulah godaan
       menandai paling besar, karena keempat angka itu ADALAH parameter mutu CPO
       yang gunanya dibandingkan dengan batas, dan batasnya hidup DI LUAR
       aplikasi ini (spesifikasi pembeli dan standar mutu CPO). Kotak
       keterangannya memakai kelas `.md-explain`, bukan `.md-threshold`:
       ketiadaan penandaan diasersi MENURUT NAMA KELAS pada HTML ter-render.

    Bacaan saja: tidak ada satu pun tombol/field yang mengubah data stasiun.
--}}
@php
    // Angka dengan jumlah desimal tetap. null SELALU menjadi "tidak tersedia",
    // TIDAK PERNAH 0 — nol berarti "terukur dan hasilnya nol", sedangkan tidak
    // tersedia berarti "tidak pernah diukur".
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
    // DUA DAFTAR TERPISAH, dibaca sebagai dua variabel terpisah dan TIDAK
    // PERNAH disatukan menjadi satu daftar di sini.
    $catatanTindakan = $summary['text_entries']['qc_engineering_corrective_actions'] ?? [];
    $temuan = $summary['text_entries']['findings'] ?? [];
    $inspectorRecap = $summary['inspector_recap'] ?? ['entries' => [], 'not_recorded' => 0, 'attributed_slots' => 0];
    // SLOT MILIK CATATAN DRAFT — ikut terhitung di seluruh angka, dan jumlahnya
    // dinyatakan supaya pembaca tahu seberapa besar bagian yang belum final.
    $draftSlotCount = (int) ($summary['draft_slot_count'] ?? 0);
    $daily = $summary['daily'] ?? [];
    $dailyTotal = $summary['daily_total'] ?? null;
    $total = $summary['total'] ?? null;

    // "Ada data" = ada minimal satu slot terisi. Dipakai untuk MENOLAK
    // menggambar grafik dan tabel kosong: grafik kosong terbaca sebagai garis
    // datar hasil pengukuran, dan tabel kosong sebagai hasil ukur bernilai nol
    // — padahal tidak ada yang diukur.
    $hasData = (bool) ($summary['has_data'] ?? false);

    // Label + satuan + jumlah desimal tiap kolom ukur. Dipakai kartu metrik,
    // rekap per unit, dan rekap harian — satu sumber, sehingga satu perubahan
    // label tidak mungkin meninggalkan dua ejaan di layar. Kedua kolom teks
    // bebas dan kedua kolom identitas TIDAK ADA di daftar ini dan memang tidak
    // boleh ada.
    //
    // SATUAN DIAMBIL DARI RESPONS, bukan dari daftar ini: setiap entri
    // `metrics` membawa `unit`-nya sendiri, dan `unit` BERNILAI NULL tepat satu
    // kali — pada Indeks DOBI, yang memang bukan besaran bersatuan. Elemen
    // satuan karena itu dirender HANYA ketika unit tidak null; `unit ?? ''`
    // akan tetap merender elemen hampa dan membuka jalan bagi tanda persen
    // mendarat di kartu DOBI. Daftar di bawah hanya menyimpan LABEL dan jumlah
    // desimal. Kedua kolom teks bebas dan kedua kolom identitas TIDAK ADA di
    // daftar ini dan memang tidak boleh ada.
    //
    // Label pendek dipakai header tabel rekap harian; satuannya ikut di sana
    // karena header tabel tidak punya tempat untuk elemen satuan tersendiri —
    // dan 'Indeks DOBI' tetap tanpa satuan di sana juga.
    $metrikMeta = [
        'fruit_press_oil_loss_in_sludge_percent' => ['label' => 'Oil Loss di Sludge', 'pendek' => 'Oil Loss di Sludge (%)', 'digits' => 2],
        'fruit_press_oil_loss_in_fibre_percent' => ['label' => 'Oil Loss di Fibre', 'pendek' => 'Oil Loss di Fibre (%)', 'digits' => 2],
        'purifier_clarification_balance_inlet_temp_c' => ['label' => 'Suhu Masuk Purifier', 'pendek' => 'Suhu Masuk Purifier (°C)', 'digits' => 1],
        'purifier_clarification_balance_backpressure_bar' => ['label' => 'Tekanan Balik Purifier', 'pendek' => 'Tekanan Balik Purifier (Bar)', 'digits' => 2],
        'vacuum_drying_station_drier_temp_c' => ['label' => 'Suhu Pengering', 'pendek' => 'Suhu Pengering (°C)', 'digits' => 1],
        'vacuum_drying_station_vacuum_pressure_bar' => ['label' => 'Tekanan Vakum', 'pendek' => 'Tekanan Vakum (Bar)', 'digits' => 2],
        'decanter_centrifuge_feed_rate_mth' => ['label' => 'Laju Umpan Decanter', 'pendek' => 'Laju Umpan Decanter (MT/jam)', 'digits' => 2],
        'decanter_centrifuge_oil_loss_in_cake_percent' => ['label' => 'Oil Loss di Cake', 'pendek' => 'Oil Loss di Cake (%)', 'digits' => 2],
        'final_storage_ffa_percent' => ['label' => 'ALB (FFA)', 'pendek' => 'ALB / FFA (%)', 'digits' => 2],
        'final_storage_moisture_content_percent' => ['label' => 'Kadar Air', 'pendek' => 'Kadar Air (%)', 'digits' => 2],
        'final_storage_impurities_dirt_percent' => ['label' => 'Kadar Kotoran', 'pendek' => 'Kadar Kotoran (%)', 'digits' => 2],
        // TANPA SATUAN, di kartu maupun di header tabel.
        'final_storage_dobi_index' => ['label' => 'Indeks DOBI', 'pendek' => 'Indeks DOBI', 'digits' => 2],
    ];

    // LIMA BLOK BERJUDUL, disusun sebagai baris-baris tata letak MENGIKUTI
    // JALUR FISIK MINYAK. Kolom tiap blok TIDAK didaftar di sini — ia
    // diturunkan dari field `group` pada respons (lihat $kolomGrup), supaya
    // kolom baru dari migrasi kelak masuk ke blok yang benar tanpa menyunting
    // berkas ini, dan supaya dua belas kartu tetap satu markup alih-alih dua
    // belas salinan yang masing-masing dapat kehilangan penyebutnya.
    //
    // KELIMA BLOK SELALU DIRENDER, termasuk blok yang seluruh ukurannya kosong:
    // blok yang hilang akan dibaca sebagai TAHAP YANG TIDAK ADA.
    $barisBlok = [
        ['row' => '', 'blok' => [
            ['group' => 'fruit_press', 'testid' => 'metric-block-fruit-press', 'judul' => '1 &middot; Fruit Press', 'hint' => 'kerugian minyak &mdash; alasan utama laporan ini dibuka', 'grid' => 'md-kpis--2', 'catatan' => 'jalur'],
        ]],
        ['row' => 'md-row--2', 'blok' => [
            ['group' => 'purifier_clarification_balance', 'testid' => 'metric-block-purifier', 'judul' => '2 &middot; Purifier &amp; Clarification Balance', 'hint' => 'kondisi proses: suhu dan tekanan', 'grid' => 'md-kpis--2', 'catatan' => 'satuan'],
            ['group' => 'vacuum_drying_station', 'testid' => 'metric-block-vacuum-drying', 'judul' => '3 &middot; Vacuum Drying Station', 'hint' => 'suhu pengering dan tekanan vakum', 'grid' => 'md-kpis--2', 'catatan' => null],
        ]],
        ['row' => '', 'blok' => [
            ['group' => 'decanter_centrifuge', 'testid' => 'metric-block-decanter', 'judul' => '4 &middot; Decanter/Centrifuge', 'hint' => 'laju umpan dan kerugian minyak ketiga', 'grid' => 'md-kpis--2', 'catatan' => null],
        ]],
        ['row' => '', 'blok' => [
            ['group' => 'final_storage', 'testid' => 'metric-block-final-storage', 'judul' => '5 &middot; Final Storage', 'hint' => 'mutu akhir &mdash; angka yang masuk kontrak penjualan', 'grid' => 'md-kpis--4', 'catatan' => 'mutu'],
        ]],
    ];

    // Kolom-kolom sebuah blok, DITURUNKAN DARI RESPONS: setiap entri `metrics`
    // membawa `group`-nya sendiri. Inilah yang membuat kartunya satu markup dan
    // bukan dua belas salinan.
    $kolomGrup = fn (string $group) => array_keys(array_filter(
        $metrics,
        fn ($metric) => ($metric['group'] ?? null) === $group,
    ));

    // Porsi sebuah baris rekap per-inspektur. Penyebutnya SLOT TERATRIBUSI
    // (inspector_recap.attributed_slots), bukan slot terisi dan bukan slot yang
    // diharapkan: rekap ini menghitung ATRIBUSI sementara kelengkapan
    // menghitung PEMBACAAN, dan kedua angka itu berbeda nilainya pada slot yang
    // hanya mengisi kedua kolom identitas.
    $porsi = function (int $count, int $attributedSlots) {
        if ($attributedSlots === 0) {
            return '—';
        }

        return number_format(100 * $count / $attributedSlots, 1, ',', '.').'%';
    };

    // Tinggi batang tren = 28% + 72% x (nilai - terendah) / (tertinggi -
    // terendah). Sumbu tegak SENGAJA tidak dimulai dari nol — batang
    // membandingkan tanggal satu sama lain, bukan besaran mutlak — dan hal itu
    // dinyatakan di kartunya sendiri. Ini semata skala tampilan; tidak ada
    // nilai yang ditandai aman/bahaya karenanya.
    $tinggiBatang = function ($value, $min, $max) {
        if ($value === null || $min === null || $max === null) {
            return 0;
        }

        if ((float) $max <= (float) $min) {
            return 100;
        }

        return round(28 + 72 * ((float) $value - (float) $min) / ((float) $max - (float) $min), 1);
    };

    // PENYEBUT TERBESAR di antara kedua belas kartu, dipakai keterangan
    // kelengkapan untuk MENYATAKAN bahwa jumlah slot terisi dapat
    // melebihinya — bukan untuk mengoreksi apa pun.
    // PENYEBUT TERBESAR DI ANTARA KEDUA BELAS METRIK, dibaca dari respons
    // (coverage.max_metric_reading_count) dan TIDAK dihitung ulang di sini:
    // angka yang dicetak berdampingan dengan slot terisi adalah bagian dari
    // aturan laporan, bukan keputusan tampilan. Fallback menghitungnya hanya
    // bila kuncinya belum ada pada payload lama.
    $penyebutTerbesar = (int) ($coverage['max_metric_reading_count']
        ?? ($metrics === [] ? 0 : max(array_map(
            fn ($metric) => (int) ($metric['reading_count'] ?? 0),
            $metrics,
        ))));
@endphp

<div class="md" data-testid="laporan-process-quality-control">

    {{-- ============ 1. Hero: periode yang sedang dibaca ============ --}}
    <section class="md-hero" data-testid="report-hero">
        <div class="md-hero__main">
            <p class="md-hero__eyebrow">Laporan Periode &middot; Stasiun Process Quality Control</p>
            <h1 class="md-hero__title">{{ $selectedPeriod['name'] ?? 'Laporan Periode Process Quality Control' }}</h1>
            <p class="md-hero__subtitle">
                @if ($selectedPeriod)
                    {{ $summary['business_unit']['name'] ?? $businessUnitName }}
                    @if ($selectedProductionLine) &middot; {{ $selectedProductionLine['name'] }} @endif
                    &middot; {{ $tgl($selectedPeriod['start_date']) }} &ndash; {{ $tgl($selectedPeriod['end_date']) }}
                @else
                    Kerugian minyak dan mutu akhir CPO sepanjang satu Periode Pelaporan pada satu production line
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
                        {{ $cacah($coverage['process_qc_unit_count']) }} unit Process Quality Control
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
        Hanya periode yang mencakup Process Quality Control yang ditampilkan, dan periode tertutup tetap dapat dibaca serta diekspor. Angka disaring menurut Production Line yang melekat pada record itu sendiri, dan keanggotaan periode mengikuti tanggal recordnya. Mengganti mill mengosongkan pilihan periode DAN production line, bukan menggantinya diam-diam. Ekspor memuat satu baris per slot waktu beserta ke-13 kolom detail &mdash; kolom shift dan qc_inspector_id IKUT, karena ekspor adalah rincian mentah.
    </x-report-filter-bar>

    <div class="md-body ld-region" wire:loading.delay.short.class="ld-region--busy" wire:loading.delay.short.attr="aria-busy" wire:target="businessUnitId,productionLineId,periodId">
    @if ($hasNoMillForAccount)
        {{-- Empty state (a): akun terikat mill tetapi users.business_unit_id
             kosong. GAGAL TERTUTUP — pemilih Mill TIDAK ditawarkan sebagai
             gantinya, dan daftar seluruh mill tidak pernah dibaca untuk sampai
             ke sini. --}}
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
                menampilkan daftar Production Line, daftar Periode Pelaporan, dan laporan Process
                Water-nya.
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
        {{-- Mill sudah pasti, production line belum. Tidak ada satu angka pun di
             dalam area ini — bukan nol, bukan persentase nol, bukan penyebut
             kosong — dan TIDAK ADA angka seluruh mill sebagai gantinya. Tombol
             ekspor pun belum dirender. Keterangan di bawah sengaja tidak memuat
             satu digit pun. --}}
        <div class="md-empty" data-testid="select-production-line-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h16"/><circle cx="8" cy="7" r="1.6"/><circle cx="14" cy="12" r="1.6"/><circle cx="10" cy="17" r="1.6"/></svg>
            </span>
            <p class="md-empty__title">Pilih production line terlebih dahulu</p>
            <p class="md-empty__text">
                Laporan Process Quality Control menghasilkan angka gabungan per line, dan mencampur beberapa
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
             Process Quality Control. Bukan galat — cukup arahkan ke layar yang
             membuatnya. Pemilih periode sengaja tetap kosong dan TIDAK diisi
             periode milik jenis stasiun lain. --}}
        <div class="md-empty" data-testid="no-period-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
            </span>
            <p class="md-empty__title">Belum ada Periode Pelaporan</p>
            <p class="md-empty__text">
                Mill ini belum memiliki Periode Pelaporan yang mencakup stasiun Process Quality Control.
                Hubungi Admin agar membuatnya terlebih dahulu di layar Kelola Periode Pelaporan, lalu
                laporan periode akan tampil di sini.
            </p>
        </div>
    @elseif ($summary !== null)

        {{-- ============ 3. Kelengkapan pencatatan — PALING ATAS ============
             Bukan catatan kaki: periode yang terisi seperlima pun menghasilkan
             rata-rata yang terlihat rapi, jadi pembaca harus melihat
             kelengkapannya lebih dulu. --}}
        <section class="md-card" data-testid="coverage">
            <header class="md-card__head">
                <h3>Kelengkapan Pencatatan</h3>
                <span class="md-card__hint">baca ini lebih dulu &mdash; dua belas metrik di bawah bersandar padanya</span>
            </header>
            <div class="md-budget">
                <div class="md-budget__row">
                    <div class="md-budget__label">
                        <span>Slot waktu terisi sepanjang periode</span>
                        {{-- KEDUA ANGKA BERDAMPINGAN, TANPA SYARAT: slot
                             terisi di sebelah penyebut terbesar di antara dua
                             belas metrik. Selisih keduanya selalu dicurigai
                             sebagai cacat bila tidak dijelaskan, dan kalimat
                             yang muncul bersyarat tidak pernah terbaca oleh
                             orang yang paling membutuhkannya. --}}
                        <span class="md-budget__nums" data-testid="coverage-slots">
                            <strong>{{ $cacah($coverage['filled_slots']) }}</strong>
                            dari {{ $cacah($coverage['expected_slots']) }} slot
                            &middot;
                            <span data-testid="coverage-max-denominator">penyebut metrik terbesar
                            {{ $cacah($penyebutTerbesar) }}</span>
                        </span>
                    </div>
                    {{-- Persen TANPA penyebut dirender sebagai tanda pisah,
                         bukan angka: persentase nol mengklaim ada yang
                         diharapkan lalu tidak tercatat, padahal belum ada satu
                         slot pun yang diharapkan. --}}
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
                        Belum ada slot yang diharapkan &mdash; belum ada unit Process Quality Control yang
                        tercatat pada periode dan line ini, atau periodenya belum mulai. Persennya
                        karena itu dirender sebagai tanda pisah, bukan sebagai nol persen yang akan
                        terbaca seolah pencatatan gagal.
                    </p>
                @elseif (($coverage['period_not_started'] ?? false) || (int) $coverage['days_counted'] === 0)
                    <p class="md-budget__note" data-testid="coverage-not-started-note">
                        Periode belum mulai, sehingga belum ada hari yang dapat dijadikan pembagi.
                    </p>
                @elseif ($coverage['period_running'])
                    {{-- URUTANNYA PENTING: days_counted === 0 DIPERIKSA LEBIH
                         DULU di atas. ReportPeriodDays::isRunning() juga true
                         untuk periode yang BELUM MULAI, jadi memeriksa
                         period_running lebih dulu akan menjelaskan periode yang
                         belum mulai sebagai "sedang berjalan". Keterangan ini
                         menyebut HARI TERAKHIR YANG TERHITUNG, bukan tanggal
                         akhir periode: tanggal akhir yang belum tiba akan
                         terbaca seperti hari yang sudah ikut dihitung. --}}
                    <p class="md-budget__note" data-testid="coverage-running-note">
                        Dihitung sampai {{ $tgl($countedUntil) }}, periode masih berjalan
                        ({{ $cacah($coverage['days_counted']) }} dari
                        {{ $cacah($coverage['days_in_period']) }} hari periode sudah lewat). Hari yang
                        belum terjadi tidak mungkin tercatat, jadi tidak ikut menjadi penyebut.
                    </p>
                @endif
                <p class="md-card__hint" data-testid="coverage-formula">
                    Slot yang diharapkan = {{ $cacah($coverage['process_qc_unit_count']) }} unit
                    Process Quality Control &times; {{ $cacah($coverage['days_counted']) }} hari{{ $coverage['period_running'] && (int) $coverage['days_counted'] > 0 ? ' (sampai hari ini)' : '' }}
                    &times; {{ $cacah($coverage['slots_per_unit_per_day']) }} slot
                    = {{ $cacah($coverage['expected_slots']) }}.
                    Jumlah unit adalah unit yang BENAR-BENAR punya record pada periode dan line ini,
                    bukan jumlah stasiun terdaftar; angka
                    {{ $cacah($coverage['slots_per_unit_per_day']) }} slot per hari diambil dari kisi
                    slot kanonis layar input itu sendiri (07:00 sampai 06:00 keesokan hari), bukan
                    dikarang di sini.
                </p>
            </div>

            {{-- ATURAN EMPAT-BELAS-BUKAN-ENAM-BELAS, DIRENDER UTUH. Ini
                 keterangan terpenting di layar ini: enam belas kolom adalah
                 yang tampak wajar dari skema, dan empat belas adalah yang
                 benar. --}}
            <div class="md-explain" data-testid="fourteen-columns-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
                <span>
                    <b>Satu slot dianggap terisi bila minimal satu dari EMPAT BELAS kolom pembacaan
                    terisi &mdash; bukan enam belas.</b> Baris detail stasiun ini membawa enam belas
                    kolom, tetapi <code>shift</code> dan <code>qc_inspector_id</code> adalah <b>kolom
                    identitas, bukan kolom pembacaan</b>, dan keduanya <b>dikecualikan</b> dari
                    pemeriksaan itu. Pengecualian tersebut dinyatakan eksplisit di docblock service
                    dan <b>dipinjam</b> dari layar input itu sendiri, bukan diturunkan ulang di sini,
                    supaya laporan dan formulir tidak pernah berbeda pendapat tentang apa yang
                    tercatat.
                    <small>
                        <b>Dua akibatnya berlawanan dan keduanya harus dibedakan.</b> Satu slot yang
                        hanya mengisi <code>shift</code> dan/atau <code>qc_inspector_id</code>
                        <b>tidak</b> terhitung terisi &mdash; tetapi kode inspekturnya
                        <b>tetap masuk</b> rekap per-inspektur di bawah, dan kedua hal itu tidak
                        bertentangan: rekap menghitung <b>atribusi</b>, kelengkapan menghitung
                        <b>pembacaan</b>. Sebaliknya, satu slot yang hanya mengisi kolom
                        <b>teks bebas</b> tanpa satu pun kolom ukur <b>tetap</b> terhitung terisi,
                        karena kedua kolom teks termasuk dalam keempat belas kolom pembacaan &mdash;
                        sesuatu memang ditulis di slot itu. Karena itulah
                        <b>jumlah slot terisi dapat MELEBIHI penyebut setiap kartu metrik</b>: pada
                        periode ini {{ $cacah($coverage['filled_slots']) }} slot terisi, sementara
                        penyebut terbesar di antara dua belas metrik
                        {{ $cacah($penyebutTerbesar) }} pembacaan. Selisih itu <b>benar dan tidak
                        dikoreksi</b> &mdash; yang dilampaui adalah penyebut metrik, bukan penyebut
                        kelengkapan. Kedua belas kolom ukur nullable dan terisi saling bebas, jadi
                        <b>setiap metrik menerbitkan penyebutnya sendiri</b>; satu penyebut bersama
                        akan salah untuk setidaknya sebelas di antaranya. Record berstatus draft IKUT
                        terhitung pada seluruh angka laporan ini &mdash;
                        <span data-testid="draft-slot-count">{{ $cacah($draftSlotCount) }} slot</span>
                        di antaranya berasal dari catatan yang belum final.
                    </small>
                </span>
            </div>

            <div class="md-explain" data-testid="coverage-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                <span>
                    <b>Seluruh angka di bawah dihitung dari pembacaan yang ada</b>, bukan dari periode
                    penuh: slot yang tidak terisi <b>tidak</b> diperlakukan sebagai nol, melainkan
                    tidak ikut dihitung sama sekali.
                    <small>
                        Bila periode ternyata tidak memuat <b>satu pun</b> pembacaan, seluruh angka di
                        bawah ditampilkan <b>tidak tersedia</b> dengan keterangan belum ada data
                        &mdash; bukan sebagai nol &mdash; dan <b>kartu kelengkapan ini tetap
                        ditampilkan</b>. Kelengkapan pencatatan adalah bagian dari laporan, bukan
                        catatan kaki, dan karena itu ia dirender <b>di atas</b> seluruh angka lain,
                        bukan di bawahnya.
                    </small>
                </span>
            </div>
        </section>

        @if (! $hasData)
            {{-- Empty state (e): periode + line valid tetapi tanpa satu slot
                 terisi. Kelengkapan di atas sudah menampilkannya; kartu metrik
                 TETAP digambar dengan nilai tidak tersedia dan penyebut nol —
                 kartu yang hilang terbaca sebagai "parameter itu tidak ada" —
                 sementara tren harian, rekap per inspector, rekap per unit dan
                 rekap harian TIDAK digambar: grafik kosong terbaca sebagai
                 garis datar hasil pengukuran. --}}
            <div class="md-empty" data-testid="empty-state">
                <span class="md-empty__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19h16M7 16V9M12 16V5M17 16v-4"/></svg>
                </span>
                <p class="md-empty__title">Belum ada data pada periode dan line ini</p>
                <p class="md-empty__text">
                    Tidak ada satu pun slot Process Quality Control yang terisi pada rentang periode ini di
                    {{ $selectedProductionLine['name'] ?? 'production line ini' }}, sehingga seluruh
                    terendah, rata-rata dan tertinggi ditampilkan sebagai tidak tersedia &mdash; bukan
                    sebagai nol. Kelima blok dan kedua belas kartu parameter di bawah tetap digambar
                    lengkap dengan penyebut nol supaya terlihat bahwa parameternya ada dan tidak
                    terukur, sementara rekap per-inspektur dan rekap harian tidak digambar.
                </p>
            </div>
        @endif

        {{-- ============ 4. Dua belas kartu metrik, lima blok ============
             PENGELOMPOKANNYA MURNI TATA LETAK: tidak satu pun angka dihitung
             per blok dan tidak ada total per blok, karena satuan di dalam satu
             blok pun berbeda. URUTAN BLOKNYA MENGIKUTI JALUR FISIK MINYAK, dan
             KELIMA BLOK SELALU DIRENDER — termasuk blok yang seluruh ukurannya
             kosong, karena blok yang hilang akan dibaca sebagai tahap yang
             tidak ada.

             MARKUP KARTUNYA DITULIS SEKALI DI SINI dan dipakai dua belas kali
             lewat perulangan atas field `group` DARI RESPONS, bukan disalin per
             blok dan bukan atas daftar nama kolom yang dipaku: kedua belas
             kartu HARUS mencetak penyebutnya masing-masing, dan satu salinan
             markup berarti tidak ada satu blok pun yang bisa diam-diam
             kehilangan penyebutnya atau mewarisi penyebut blok lain. --}}
        @foreach ($barisBlok as $baris)
            <div class="md-row {{ $baris['row'] }}">
                @foreach ($baris['blok'] as $blok)
                    @php $kolom = $kolomGrup($blok['group']); @endphp
                    <section class="md-card" data-testid="{{ $blok['testid'] }}" data-metric-group="{{ $blok['group'] }}">
                        <header class="md-card__head">
                            <h3>{!! $blok['judul'] !!}</h3>
                            <span class="md-card__hint">
                                {{ $cacah(count($kolom)) }} metrik &middot; {!! $blok['hint'] !!}
                            </span>
                        </header>
                        <div class="md-kpis {{ $blok['grid'] }}">
                            @foreach ($kolom as $column)
                                @php
                                    // Kolom yang belum punya label manusiawi —
                                    // misalnya kolom baru dari migrasi — tetap
                                    // dirender memakai nama kolomnya, bukan
                                    // hilang dari layar.
                                    $meta = $metrikMeta[$column] ?? ['label' => $column, 'pendek' => $column, 'digits' => 2];
                                    $metric = $metrics[$column];
                                    // SATUAN DARI RESPONS, dan NULL ADALAH
                                    // NILAI YANG SAH: Indeks DOBI adalah indeks
                                    // tanpa satuan, bukan persen. Jangan diganti
                                    // `?? ''` — string kosong tetap merender
                                    // elemen satuan yang hampa, dan dari situ
                                    // tanda persen bisa mendarat di kartu DOBI.
                                    $satuan = $metric['unit'] ?? null;
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
                                        @if ($satuan !== null)
                                            <span data-testid="metric-unit">{{ $satuan }}</span>
                                            <span>rata-rata</span>
                                        @else
                                            <span>rata-rata</span>
                                        @endif
                                    </p>
                                    <p class="md-kpi__meta" data-testid="metric-range">
                                        terendah {{ $nilai($metric['min'], $meta['digits']) }} &middot;
                                        tertinggi {{ $nilai($metric['max'], $meta['digits']) }}
                                    </p>
                                    {{-- PENYEBUT MILIK KARTU INI SENDIRI, bukan
                                         penyebut bersama. Satu penyebut bersama
                                         untuk kedua belas metrik akan
                                         mengempiskan metrik yang jarang diisi
                                         dan tetap menghasilkan angka yang
                                         terlihat sah — cacat utama yang dicari
                                         uji layar ini. --}}
                                    <p class="md-kpi__foot" data-testid="metric-denominator">
                                        dari <b>{{ $cacah($metric['reading_count']) }}</b> pembacaan
                                        <small>(slot yang mengisi kolom ini sendiri; slot yang tidak mengisinya tidak mengukurnya nol &mdash; ia tidak mengukurnya sama sekali)</small>
                                    </p>
                                </article>
                            @endforeach
                        </div>

                        @if ($blok['catatan'] === 'jalur')
                            <div class="md-explain" data-testid="oil-path-note">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h11l-3-3M17 17H6l3 3"/></svg>
                                <span>
                                    Kedua belas kartu dikelompokkan menjadi <b>lima blok berjudul yang
                                    mengikuti jalur fisik minyak</b> &mdash; <b>1 Fruit Press
                                    &rarr; 2 Purifier &amp; Clarification Balance &rarr; 3 Vacuum
                                    Drying Station &rarr; 4 Decanter/Centrifuge &rarr; 5 Final
                                    Storage</b> &mdash; bukan mengikuti abjad dan bukan mengikuti
                                    urutan kolom di basis data.
                                    <small>
                                        Dua belas angka yang berdiri sendiri tidak dapat dibaca; lima
                                        blok yang masing-masing menjawab <b>satu pertanyaan tentang
                                        satu tahap</b> dapat. Urutannya bekerja ke dua arah: pembaca
                                        yang menemukan angka mutu akhir yang tidak ia harapkan dapat
                                        <b>bergerak mundur satu tahap demi satu tahap</b> &mdash;
                                        decanter, lalu pengeringan vakum, lalu purifier, lalu fruit
                                        press &mdash; tanpa perlu tahu nama kolom mana pun.
                                        <b>Ketiga angka oil loss</b> (di sludge, di fibre, dan di cake
                                        decanter) adalah yang paling dicari manajemen, karena setiap
                                        persen adalah uang yang keluar dari pabrik; dua di antaranya
                                        ada di blok ini dan yang ketiga di blok Decanter/Centrifuge.
                                        Pengelompokannya <b>murni tata letak</b>: tidak satu pun angka
                                        dihitung per blok dan tidak ada total per blok. <b>Kelima blok
                                        tetap dirender</b> meskipun seluruh ukurannya kosong &mdash;
                                        blok yang hilang akan dibaca sebagai tahap yang tidak ada.
                                    </small>
                                </span>
                            </div>
                        @elseif ($blok['catatan'] === 'satuan')
                            <div class="md-explain" data-testid="unit-per-card-note">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3h6v6l4 9a2 2 0 0 1-1.8 3H6.8A2 2 0 0 1 5 18l4-9z"/><path d="M7 15h10"/></svg>
                                <span>
                                    <b>Satuan tercetak pada setiap kartu, dan satuan yang berbeda
                                    tidak disamakan demi kerapian:</b> suhu dalam <b>&deg;C</b>,
                                    tekanan dalam <b>Bar</b>, laju umpan dalam <b>MT/jam</b>, dan
                                    kerugian minyak serta mutu akhir dalam <b>persen</b>.
                                    <small>
                                        Satuan itu <b>diambil dari respons</b>, bukan dituliskan ulang
                                        di berkas tampilan ini, sehingga satu kolom punya satu satuan
                                        di seluruh produk. Besaran yang berbeda <b>tidak pernah
                                        dijumlahkan</b>: &lsquo;total kondisi proses&rsquo; bukan
                                        besaran yang punya arti, dan dua kartu dalam satu blok pun
                                        dapat bersatuan berbeda. Satu kolom yang tidak pernah diisi
                                        sepanjang periode <b>tetap punya kartunya</b>, dengan
                                        &lsquo;tidak tersedia&rsquo; dan <b>nol pembacaan</b> &mdash;
                                        bukan dihapus dari layar: kartu yang hilang tidak dapat
                                        dibedakan dari kartu yang belum pernah ada, sementara kartu
                                        berpenyebut nol menyatakan dengan tepat bahwa <b>kolomnya ada
                                        dan belum terisi</b>.
                                    </small>
                                </span>
                            </div>
                        @elseif ($blok['catatan'] === 'mutu')
                            {{-- DUA PERNYATAAN SEKALIGUS, MENEMPEL PADA BLOK
                                 MUTU AKHIR dan bukan sebagai catatan kaki
                                 halaman: (a) Indeks DOBI tanpa satuan, dan (b)
                                 nol penandaan nilai di luar batas. Justru di
                                 blok inilah godaan menandai paling besar, karena
                                 keempat angka ini ADALAH ukuran mutu yang gunanya
                                 dibandingkan dengan batas. Kotaknya memakai
                                 `.md-explain` dan BUKAN `.md-threshold`: nol
                                 elemen berkelas penanda ambang adalah invarian
                                 yang diasersi menurut nama kelas. --}}
                            <div class="md-explain" data-testid="dobi-no-unit-note">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                                <span>
                                    <b>Indeks DOBI sengaja TANPA satuan.</b> DOBI (Deterioration of
                                    Bleachability Index) adalah <b>indeks tanpa dimensi</b>, bukan
                                    persentase &mdash; ia satu-satunya dari dua belas ukuran di layar
                                    ini yang bukan besaran bersatuan.
                                    <small>
                                        Menampilkannya dengan tanda persen adalah kesalahan yang akan
                                        <b>terlihat benar</b> di halaman ini, karena ketiga kartu
                                        tetangganya di blok yang sama memang berpersen. Karena itu
                                        satuan dirender <b>hanya ketika respons memang membawa
                                        satuan</b>, dan kartu ini tidak membawanya.
                                    </small>
                                </span>
                            </div>
                            <div class="md-explain" data-testid="no-threshold-note">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l9 4v5c0 5-3.8 8.4-9 9.9C6.8 20.4 3 17 3 12V7z"/><path d="M12 8v4M12 15h.01"/></svg>
                                <span>
                                    <b>TIDAK ADA satu pun nilai di layar ini yang ditandai di luar
                                    batas, dan pernyataan ini dirender UTUH tanpa diringkas.</b>
                                    Stasiun Process Quality Control <b>tidak punya tabel master ambang
                                    mutu</b> &mdash; tidak ada satu baris acuan yang bisa dibandingkan
                                    dengan satu pun dari dua belas ukuran di atas, baik per unit
                                    maupun per line. Dan justru di sinilah godaan menandai paling
                                    besar di antara seluruh laporan stasiun: <b>keempat angka blok ini
                                    ADALAH parameter mutu CPO yang gunanya dibandingkan dengan
                                    batas</b>, dan batasnya hidup <b>DI LUAR aplikasi ini</b> &mdash;
                                    spesifikasi pembeli dan standar mutu CPO. Laporan ini karena itu
                                    <b>menerbitkan angkanya tanpa menyatakan memenuhi spesifikasi atau
                                    tidak</b>, aturan yang sama seperti laporan Effluent Plant, Kernel
                                    Dispatch, dan CPO Dispatch.
                                    <small>
                                        Menurunkan ambang dari <b>data periode ini sendiri</b> &mdash;
                                        rata-rata &plusmn; dua simpangan baku, atau persentil atas
                                        pembacaan yang ada &mdash; akan menghasilkan garis yang
                                        terlihat berwibawa, lalu <b>dibaca sebagai batas kontrak
                                        penjualan</b>, padahal ia hanya <b>turunan statistik</b> dari
                                        pencatatan yang kelengkapannya sendiri sedang dinilai di blok
                                        paling atas halaman ini. Ambang semacam itu akan ikut bergeser
                                        setiap kali kelengkapan pencatatan berubah, dan itu bukan
                                        sifat yang boleh dimiliki sebuah batas kontrak. Karena itu
                                        kedua belas angka ditampilkan <b>apa adanya</b>: tanpa warna
                                        peringatan, tanpa ikon, tanpa kata &lsquo;tinggi&rsquo; atau
                                        &lsquo;rendah&rsquo;, dan tanpa satu pun sel yang ditandai
                                        &mdash; <b>termasuk nilai yang jauh di luar nominal yang
                                        dikenal umum</b>, yang tetap tampil polos di layar ini dan di
                                        berkas ekspor. Penilaian memenuhi atau tidak memenuhi tetap di
                                        tangan pembacanya, dan laporan ini tidak berpura-pura
                                        memilikinya. Bila kelak ambang mutu dimasukkan sebagai master,
                                        laporan ini perlu ditinjau ulang &mdash; dan peninjauan itu
                                        akan berlaku untuk <b>tiga stasiun sekaligus</b>, bukan hanya
                                        yang ini.
                                    </small>
                                </span>
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>
        @endforeach


        {{-- ============ 5. Rekap Catatan Tindakan — TEKS, bukan sebaran ============
             DUA BAGIAN TERPISAH dengan Rekap Temuan di bawahnya, dan itu
             disengaja: satu menjawab apa yang dikerjakan, satu menjawab apa yang
             ditemukan. --}}
        <section class="md-card" data-testid="action-taken-entries">
            <header class="md-card__head">
                <h3>Rekap Catatan Tindakan</h3>
                <span class="md-card__hint">
                    {{ $cacah(count($catatanTindakan)) }} slot mengisi kolom <code>qc_engineering_corrective_actions</code> sepanjang periode
                </span>
            </header>
            @if ($catatanTindakan === [])
                <p class="md-card__hint" data-testid="action-taken-empty">
                    Tidak ada satu pun catatan tindakan tercatat pada periode dan line ini. Ini
                    dinyatakan sebagai keterangan tersendiri, bukan digabung ke bagian Rekap Temuan
                    dan bukan tabel kosong tanpa penjelasan &mdash; tabel kosong tidak membedakan
                    &ldquo;tidak ada tindakan&rdquo; dari &ldquo;tidak ada yang mencatatnya&rdquo;.
                </p>
            @else
                <div class="md-notes">
                    @foreach ($catatanTindakan as $entry)
                        {{-- SATU ENTRI PER SLOT. Isi yang berulang sama persis
                             TETAP dua baris, bukan satu baris berjumlah dua. --}}
                        <div class="md-notes__item" data-testid="action-taken-row">
                            <span class="md-pill">{{ $tgl($entry['date']) }} &middot; {{ $entry['time_slot'] }}</span>
                            <p>{{ $entry['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
            <div class="md-explain" data-testid="action-taken-is-text-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/></svg>
                <span>
                    <b><code>qc_engineering_corrective_actions</code> diterbitkan sebagai rekap TEKS &mdash; meski
                    namanya menyebut &lsquo;status&rsquo;.</b> Kolomnya <b>teks bebas tanpa himpunan
                    nilai tertutup</b>: formulir web merendernya sebagai kotak teks bersebelahan
                    dengan <code>findings</code> dan bukan sebagai pilihan, label formulir mobile
                    berbunyi &lsquo;Action Taken/Status&rsquo; <b>dengan garis miring</b> sehingga
                    kata &lsquo;status&rsquo; adalah paruh kedua sebuah frasa, dan tidak ada satu pun
                    entri label pilihan untuknya di seluruh sistem. Namanya tersingkat secara
                    menyesatkan, itu saja.
                    <small>
                        Dua kolom bernama serupa di stasiun lain memang bernilai tertutup
                        &mdash; <code>diesel_gen_1_status</code> di Engine Room dan
                        <code>biogas_flare_status</code> di Effluent Plant, keduanya Run / Standby /
                        Off &mdash; dan keduanya memang diterbitkan sebagai jumlah slot per nilai.
                        <b>Yang ini bukan</b>, dan <b>layar ini tidak punya satu pun blok semacam
                        itu</b>. Mengelompokkan teks bebas menghasilkan <b>satu
                        &lsquo;nilai&rsquo; per kalimat yang berbeda</b>, dan daftar kalimat berjumlah
                        satu-satu bukan data kategoris &mdash; ia hanya daftar yang sama, disusun
                        ulang sampai tampak seperti data kategoris. Karena itu isi yang <b>berulang
                        sama persis tetap menjadi baris-baris terpisah</b>, masing-masing dengan
                        tanggal dan slot waktunya: yang sama persis hari ini belum tentu sama persis
                        besok. Isinya tidak pernah dirata-rata maupun digrafikkan, karena teks bebas
                        tidak punya rata-rata.
                    </small>
                </span>
            </div>
        </section>

        {{-- ============ 6. Rekap Temuan — bagian TERPISAH ============ --}}
        <section class="md-card" data-testid="findings">
            <header class="md-card__head">
                <h3>Rekap Temuan</h3>
                <span class="md-card__hint">
                    {{ $cacah(count($temuan)) }} slot mengisi kolom <code>findings</code> sepanjang periode
                </span>
            </header>
            @if ($temuan === [])
                <p class="md-card__hint" data-testid="findings-empty">
                    Tidak ada satu pun temuan tercatat pada periode dan line ini. Ini dinyatakan
                    sebagai keterangan tersendiri, bukan digabung ke bagian Rekap Catatan Tindakan di
                    atasnya dan bukan tabel kosong tanpa penjelasan.
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
                    Bagian ini menjawab <b>apa yang ditemukan</b> &mdash; pertanyaan yang berbeda dari
                    Rekap Catatan Tindakan di atasnya, yang menjawab <b>apa yang dikerjakan</b>. Karena
                    itu keduanya <b>dua bagian terpisah</b> dan tidak pernah disatukan.
                    <small>
                        Satu slot yang mengisi <b>kedua</b> kolom muncul pada <b>kedua</b> rekap, dan
                        itu benar &mdash; <b>bukan penghitungan ganda</b>. Menyatukan keduanya menjadi
                        satu daftarlah yang akan menghitung slot itu dua kali, di dalam satu
                        pengertian yang sama. Keduanya tidak pernah dirata-rata maupun digrafikkan.
                        Bila kedua kolom teks kosong sepanjang periode, <b>kedua bagian</b>
                        menampilkan keterangannya masing-masing.
                    </small>
                </span>
            </div>
        </section>

        @if ($hasData)
            {{-- ============ 7. Rekap per Inspektur ============
                 DI LAYAR, dengan kode MENTAH. Penyimpangan sadar dari preseden
                 Storage Tank (yang menerbitkan kolom sejenis hanya di ekspor),
                 diminta pemilik produk 2026-10-16. Entrinya diurutkan menurut
                 KODE, bukan menurut jumlah slot: mengurutkan menurut jumlah akan
                 membuat tabel ini terbaca sebagai papan peringkat, dan posisi
                 sebuah kode akan berpindah antar periode.

                 DIHITUNG ATAS SELURUH BARIS, BUKAN HANYA YANG TERISI — itulah
                 yang membuat slot yang hanya mengisi kedua kolom identitas tetap
                 terhubung ke kode inspekturnya meski dihitung KOSONG oleh
                 kelengkapan. Rekap menghitung ATRIBUSI, kelengkapan menghitung
                 PEMBACAAN, dan karena itu `attributed_slots` BUKAN alias
                 `coverage.filled_slots`. --}}
            <details class="md-details" open data-testid="inspector-recap-details">
                <summary data-testid="inspector-recap-toggle">
                    Rekap per Inspector
                    <small>
                        {{ $cacah(count($inspectorRecap['entries'])) }} kode inspektur + satu baris
                        &lsquo;tidak tercatat&rsquo; &middot; dasar
                        {{ $cacah($inspectorRecap['attributed_slots']) }} slot teratribusi &middot; klik
                        untuk membuka atau menutup
                    </small>
                </summary>
                <div class="md-recap">
                    <table class="md-table" data-testid="inspector-recap-table">
                        <thead>
                            <tr>
                                <th scope="col">qc_inspector_id</th>
                                <th scope="col">Slot teratribusi</th>
                                <th scope="col">Porsi dari slot teratribusi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- KODE MENTAH APA ADANYA. Tidak ada join ke tabel
                                 users dan tidak ada percobaan memulangkan nama —
                                 kolomnya teks bebas tanpa relasi, jadi tidak ada
                                 pemetaan untuk ditebak — dan TIDAK ADA
                                 normalisasi huruf besar/kecil: 'qc-01' dan
                                 'QC-01' tetap DUA baris terpisah, karena teks
                                 bebas tidak menjamin keduanya orang yang sama
                                 dan tidak ada master inspektur untuk
                                 memastikannya.

                                 `data-inspector-code` memegang kodenya supaya
                                 asersi uji dapat menyasar BARISNYA secara eksak:
                                 kode sependek 'qc-01' akan cocok sebagai
                                 substring di dalam 'qc-010'. --}}
                            @foreach ($inspectorRecap['entries'] as $entry)
                                <tr data-testid="inspector-row" data-inspector-code="{{ $entry['qc_inspector_id'] }}">
                                    <td><code>{{ $entry['qc_inspector_id'] }}</code></td>
                                    <td>{{ $cacah($entry['slot_count']) }}</td>
                                    <td class="is-muted">{{ $porsi((int) $entry['slot_count'], (int) $inspectorRecap['attributed_slots']) }}</td>
                                </tr>
                            @endforeach
                            {{-- BARISNYA SENDIRI, SELALU, dan tidak pernah
                                 digabungkan ke salah satu inspector. --}}
                            <tr data-testid="inspector-not-recorded">
                                <td class="is-muted">tidak tercatat</td>
                                <td class="is-muted">{{ $cacah($inspectorRecap['not_recorded']) }}</td>
                                <td class="is-muted">{{ $porsi((int) $inspectorRecap['not_recorded'], (int) $inspectorRecap['attributed_slots']) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr data-testid="inspector-attributed-total">
                                <td>Seluruh slot teratribusi</td>
                                <td>{{ $cacah($inspectorRecap['attributed_slots']) }}</td>
                                <td>{{ (int) $inspectorRecap['attributed_slots'] === 0 ? '—' : '100,0%' }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="md-explain" data-testid="inspector-recap-purpose-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 5.3a3 3 0 0 1 0 5.4"/><path d="M18 20a6 6 0 0 0-3-5.2"/></svg>
                    <span>
                        <b>Rekap ini ada untuk menelusuri slot yang BELUM terisi &mdash; bukan untuk
                        menilai orang.</b> Kelengkapan pencatatan adalah pokok layar ini, dan slot yang
                        kosong selalu punya pemilik yang dapat ditanyai; itu seluruh gunanya.
                        <small>
                            Angka di kolom &lsquo;slot terisi&rsquo; <b>bukan nilai kinerja</b>: ia
                            tidak memperhitungkan panjang penugasan, jumlah unit yang dijaga, hari
                            libur, maupun slot yang memang bukan jatahnya &mdash; jadi membandingkan
                            dua kode lewat angka ini akan salah. Laporan ini karena itu
                            <b>tidak</b> mengurutkan menurut jumlah, <b>tidak</b> memberi peringkat,
                            dan <b>tidak</b> menandai siapa pun; barisnya diurutkan menurut
                            <b>kodenya</b> &mdash; sehingga posisi sebuah kode <b>tidak berpindah
                            antar periode</b>. Porsi tiap baris memakai <b>jumlah slot
                            teratribusi</b> sebagai penyebut, bukan jumlah slot terisi dan bukan
                            jumlah slot yang diharapkan.
                            <b>Rekap ini dihitung atas SELURUH baris, bukan hanya yang terisi</b>:
                            slot yang hanya mengisi giliran kerja dan kode inspektur dihitung
                            <b>KOSONG</b> di blok kelengkapan, tetapi kodenya <b>tetap muncul di
                            sini</b> &mdash; dan kedua hal itu tidak bertentangan, karena rekap
                            menghitung <b>atribusi</b> sementara kelengkapan menghitung
                            <b>pembacaan</b>. Dua angka yang berbeda, dan yang satu bukan pengganti
                            yang lain.
                        </small>
                    </span>
                </div>
                <div class="md-explain" data-testid="inspector-recap-raw-code-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                    <span>
                        Baris <b>&lsquo;tidak tercatat&rsquo;</b> memuat slot yang <b>terisi</b> tetapi
                        kolom inspector-nya kosong. Slot itu <b>tetap terhitung</b> pada kelengkapan di
                        atas, karena <code>qc_inspector_id</code> kolom identitas dan tidak ikut
                        menentukan apakah sebuah slot terisi. Baris itu <b>tidak pernah</b> digabungkan
                        ke salah satu inspector.
                        <small>
                            <code>qc_inspector_id</code> bertipe <b>string polos, bukan relasi ke tabel
                            User</b>, jadi kodenya ditampilkan <b>mentah apa adanya</b> dan laporan ini
                            tidak dapat memulangkan nama orangnya &mdash; tidak ada pemetaan untuk
                            ditebak, dan formatnya pun belum seragam di lapangan. Bila seluruh slot
                            terisi ternyata tidak mencatat inspector, rekap ini menampilkan satu baris
                            &lsquo;tidak tercatat&rsquo; berisi seluruh slot itu &mdash; bukan rekap
                            kosong; dan bila satu inspector mengisi seluruh periode, <b>satu baris saja
                            adalah hasil yang sah</b> dan tidak diberi penanda apa pun.
                        </small>
                    </span>
                </div>
            </details>


            {{-- ============ 8. Rekap harian (dapat ditutup) ============
                 Periode panjang menghasilkan puluhan baris, jadi tabelnya dapat
                 ditutup agar kelengkapan dan kartu metrik tetap terbaca.
                 MENUTUPNYA TIDAK MENGUBAH SATU ANGKA PUN: seluruh angka di
                 halaman ini dihitung di service, bukan dari baris yang sedang
                 terlihat. --}}
            <details class="md-details" data-testid="daily-recap-details">
                <summary data-testid="daily-recap-toggle">
                    Rekap Harian
                    <small>
                        {{ $cacah(count($daily)) }} baris &middot; satu baris per tanggal yang punya
                        minimal satu slot terisi &middot; 9 kolom angka, menggulir mendatar di dalam
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
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Tanggal tanpa satu slot terisi TIDAK mendapat
                                 baris: baris nol akan terbaca sebagai "terukur
                                 nol". Tanggal itu tetap terhitung sebagai hari
                                 ber-record pada total periode. --}}
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
                            {{-- TOTAL PERIODE DIHITUNG ULANG ATAS SELURUH SLOT,
                                 bukan merata-ratakan rata-rata harian: rata-rata
                                 dari rata-rata memberi bobot sama pada hari
                                 berisi dua slot dan hari berisi dua puluh empat
                                 slot. Tiap sel membawa jumlah pembacaannya
                                 sendiri, dan penyebut itu berbeda satu dari yang
                                 lain. --}}
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
                <div class="md-explain" data-testid="daily-recap-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                    <span>
                        Satu baris per tanggal, <b>ditutup secara bawaan</b> supaya halaman tetap
                        terbaca pada periode yang panjang &mdash; membuka dan menutupnya <b>murni
                        penyingkapan</b> dan tidak memicu permintaan data apa pun.
                        <small>
                            Baris <b>Total periode</b> menerbitkan rata-rata berbobot beserta penyebut
                            tiap metrik, dan penyebut itu <b>berbeda satu dari yang lain</b>. Angka per
                            tanggal adalah rata-rata harian metrik itu sendiri. Tabel ini membawa 12
                            kolom angka dan menggulir mendatar <b>di dalam kartunya sendiri</b>;
                            halaman tidak pernah menggulir mendatar.
                        </small>
                    </span>
                </div>
            </details>
        @endif

        {{-- ============ 9. Yang TIDAK diterbitkan — dan alasannya ============
             Ketiadaan yang disengaja, dinyatakan supaya tidak terbaca sebagai
             kelalaian: bagian yang hilang tanpa keterangan tidak dapat
             dibedakan dari bagian yang gagal dimuat. --}}
        <section class="md-card" data-testid="not-published">
            <header class="md-card__head">
                <h3>Yang Tidak Diterbitkan di Layar Ini</h3>
                <span class="md-card__hint">empat ketiadaan yang disengaja, dinyatakan supaya tidak terbaca sebagai kelalaian</span>
            </header>
            {{-- NOL REKAP BERLABEL, karena NOL KOLOM BERPILIHAN TERTUTUP.
                 Dinyatakan lebih dulu di antara keempat ketiadaan, karena ia
                 yang paling mudah terbaca sebagai bagian yang gagal dimuat:
                 pembaca yang datang dari laporan Engine Room atau Effluent
                 Plant akan mencari sebaran berlabel di halaman ini. --}}
            <div class="md-explain" data-testid="no-labeled-recap-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h10M4 18h6"/><path d="M17 15l4 4M21 15l-4 4"/></svg>
                <span>
                    <b>Tidak ada satu pun rekap berlabel di layar ini, dan ketiadaannya
                    disengaja.</b> Stasiun Process Quality Control <b>tidak punya SATU PUN kolom
                    berpilihan tertutup</b> &mdash; tak satu pun dari keenam belas kolom barisnya,
                    dan kedua kolom identitasnya (<code>shift</code> dan
                    <code>qc_inspector_id</code>) pun <b>teks bebas</b>, bukan enum.
                    <small>
                        Karena itu tidak ada nilai yang dapat dihitung per label, dan kedua kolom
                        catatan diterbitkan sebagai <b>dua daftar kejadian terpisah</b> di atas
                        &mdash; bukan sebagai sebaran. Mengelompokkan isi teks bebas yang berulang
                        menjadi &lsquo;jumlah per nilai&rsquo; berarti <b>memperlakukan prosa sebagai
                        enum</b>: setiap kalimat berbeda menjadi &lsquo;nilai&rsquo;-nya sendiri, dan
                        daftar kalimat yang masing-masing dihitung sekali bukan sebuah sebaran &mdash;
                        ia daftar yang sama, disusun ulang sampai terlihat seperti data kategoris.
                        Laporan stasiun lain memang punya sebaran berlabel, sehingga pembaca yang
                        datang dari sana akan mencarinya di sini.
                    </small>
                </span>
            </div>
            <div class="md-explain" data-testid="no-downtime-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/></svg>
                <span>
                    <b>Layar ini tidak memuat bagian downtime, dan ketiadaannya disengaja.</b> Stasiun
                    Process Quality Control <b>tidak mencatat lama berhenti sama sekali</b> &mdash; tidak ada satu
                    kolom pun yang bisa diterbitkan, jadi tidak ada blok yang dikosongkan maupun diisi
                    nol.
                    <small>
                        Dinyatakan di sini justru karena bagian yang hilang tanpa keterangan tidak
                        dapat dibedakan dari bagian yang gagal dimuat &mdash; dan karena laporan
                        stasiun lain memilikinya, sehingga pembaca yang datang dari sana akan
                        mencarinya. Berkas ekspor pun tidak punya kolomnya.
                    </small>
                </span>
            </div>
            <div class="md-explain" data-testid="no-shift-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>
                <span>
                    <b>Kolom <code>shift</code> tidak diterbitkan di layar ini, tetapi ADA di berkas
                    ekspor &mdash; dan keduanya disengaja.</b> Kolom teks bebas itu adalah
                    <b>kolom identitas</b>, bukan kolom pembacaan: ia tidak ikut menentukan apakah
                    sebuah slot terhitung terisi, dan ia bukan angka yang dapat dirata-ratakan.
                    <small>
                        Konsekuensinya sudah tampak di blok kelengkapan di atas: <code>shift</code> dan
                        <code>qc_inspector_id</code> adalah dua kolom yang dikecualikan dari pemeriksaan
                        slot terisi, sehingga baris detail membawa <b>enam belas</b> kolom tetapi yang
                        dihitung hanya <b>empat belas</b>. <b>Keduanya tetap ikut di berkas ekspor</b>,
                        karena ekspor adalah rincian mentah &mdash; mengecualikan sebuah kolom dari
                        pemeriksaan slot terisi <b>bukan</b> alasan membuangnya dari berkas, dan berkas
                        yang tidak dapat berdiri sebagai pengganti layar masukan tidak layak diekspor.
                        Dari keduanya, <code>qc_inspector_id</code> <b>memang</b> diterbitkan di layar
                        &mdash; pada rekap per-inspektur &mdash; karena ia menjawab
                        &lsquo;siapa yang dapat ditanyai tentang slot yang kosong&rsquo;, pertanyaan
                        pokok layar ini; <code>shift</code> tidak menjawab pertanyaan itu.
                    </small>
                </span>
            </div>
        </section>
    @endif
    </div>
</div>
