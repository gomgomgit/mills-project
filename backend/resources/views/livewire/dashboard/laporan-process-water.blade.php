{{--
    Laporan Periode Process Water (web) — screen-160--laporan-process-water-web.

    APA YANG DILAPORKAN HALAMAN INI: APAKAH AIR YANG MASUK KE BOILER LAYAK
    DIPAKAI sepanjang satu Periode Pelaporan pada SATU production line. Ini
    laporan KONDISI, bukan laporan keluaran. Satu record = satu hari kerja satu
    unit Process Water; satu baris = satu slot waktu dengan 9 kolom ukur, 2
    kolom teks bebas, dan 2 kolom identitas.

    TUJUH HAL YANG MENENTUKAN TATA LETAKNYA:

    1. KELENGKAPAN PENCATATAN DIRENDER PALING ATAS, lengkap dengan ketiga angka
       pembentuk penyebutnya (unit Process Water yang benar-benar punya record,
       hari yang dihitung, 24 slot kanonis per hari). Periode yang terisi
       seperlima pun menghasilkan rata-rata yang terlihat rapi, dan pembaca
       harus melihat itu SEBELUM mempercayainya — kelengkapan di sini adalah
       ISI laporan, bukan metadata. Ketika tidak ada satu slot pun yang
       diharapkan (belum ada unit tercatat, atau periode belum mulai),
       persennya dirender sebagai tanda pisah dengan keterangan "belum ada slot
       yang diharapkan" — bukan nol persen, yang akan terbaca seolah pencatatan
       gagal.

    2. BARIS TERISI DIHITUNG ATAS SEBELAS KOLOM, BUKAN TIGA BELAS, dan
       keterangan itu dirender UTUH di blok kelengkapan. Baris detail stasiun
       ini membawa tiga belas kolom, tetapi `shift` dan `inspector_id` adalah
       kolom IDENTITAS, bukan kolom pembacaan — dan itu dinyatakan eksplisit di
       docblock ProcessWaterRecordService, bukan ditebak. Dua akibatnya
       BERLAWANAN dan keduanya harus dapat dibedakan: slot yang hanya mengisi
       kolom TEKS BEBAS tetap terhitung terisi (kedua kolom teks termasuk
       kesebelas kolom pembacaan) tanpa menambah penyebut kartu mana pun,
       sementara slot yang hanya mengisi kedua kolom identitas TIDAK terhitung.
       Akibat yang pertama itulah sebabnya jumlah slot terisi dapat MELEBIHI
       penyebut setiap kartu metrik — dan itu DINYATAKAN di layar, bukan
       dikoreksi.

    3. SEMBILAN KARTU MEMBAWA PENYEBUTNYA MASING-MASING, dan MARKUP KARTUNYA
       DITULIS SEKALI lalu diulang lewat perulangan atas field `group` DARI
       RESPONS — bukan atas daftar nama kolom yang dipaku di template. Penyebut
       yang SAMA pada sembilan kartu adalah cacat utama yang dicari uji layar
       ini. Keuntungan kedua: kolom baru dari migrasi kelak masuk ke blok yang
       benar tanpa menyunting berkas ini.

    4. EMPAT BLOK BERJUDUL MENGIKUTI RANTAI PENGOLAHAN AIR, bukan urutan kolom
       di basis data: 1 Aliran Air → 2 Softener → 3 Dosis Kimia → 4 Air Umpan
       Boiler. Urutannya bekerja: pembaca yang menemukan angka air umpan boiler
       yang tidak ia harapkan dapat BERGERAK MUNDUR ke tahap sebelumnya tanpa
       perlu tahu nama kolom mana pun. Tidak satu pun angka dihitung per blok
       dan tidak ada total per blok — satuan di dalam satu blok pun berbeda
       (alum kg/jam melawan polimer g/jam).

    5. TIDAK ADA SATU PUN BLOK SEBARAN KATEGORIS DI HALAMAN INI, dan itu
       PEMBEDA TERPENTINGNYA dari Engine Room dan Effluent Plant yang keduanya
       memang punya. Stasiun ini tidak punya satu pun kolom berenum.
       `action_taken_status` bertipe teks bebas MESKI NAMANYA MENYEBUT 'STATUS'
       — diverifikasi dari tiga arah: formulir web merendernya
       <input type="text"> bersebelahan dengan `findings`, label formulir
       mobile berbunyi 'Action Taken/Status' DENGAN GARIS MIRING sehingga
       'status' adalah paruh kedua sebuah frasa, dan nol entri di
       Display::OPTION_LABELS. Ia karena itu dirender sebagai REKAP TEKS, satu
       entri per slot, dan isi yang berulang sama persis TIDAK dikelompokkan.

    6. REKAP PER INSPECTOR DIRENDER DI LAYAR, dengan kode MENTAH, dan
       keterangannya menyatakan bahwa rekap itu ada UNTUK MENELUSURI SLOT YANG
       BELUM TERISI — BUKAN UNTUK MENILAI ORANG. `inspector_id` tidak punya
       relasi ke tabel users, jadi tidak ada nama yang dapat dipulangkan dan
       tidak ada pemetaan untuk ditebak. Menampilkannya di layar adalah
       penyimpangan SADAR dari preseden Storage Tank, diminta pemilik produk
       2026-10-16.

    7. DAN TIGA KETIADAAN DINYATAKAN, bukan dibiarkan sebagai lubang: tidak ada
       bagian downtime (stasiun ini tidak mencatatnya), tidak ada kolom `shift`
       (kosong di seluruh baris dan dijadwalkan diganti relasi ke master
       Shift), dan TIDAK ADA PENANDAAN NILAI DI LUAR BATAS pada metrik mana pun
       — dengan pernyataan terakhir dirender UTUH tanpa diringkas, menempel
       pada blok Air Umpan Boiler. Justru di blok itulah godaan menandai paling
       besar, karena pH dan TDS air umpan boiler punya batas yang dikenal
       SANGAT luas di luar aplikasi ini. Kotak keterangannya memakai kelas
       `.md-explain`, bukan `.md-threshold`: ketiadaan penandaan diasersi
       MENURUT NAMA KELAS pada HTML ter-render.

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
    $catatanTindakan = $summary['text_entries']['action_taken_status'] ?? [];
    $temuan = $summary['text_entries']['findings'] ?? [];
    $inspectorRecap = $summary['inspector_recap'] ?? ['entries' => [], 'not_recorded' => 0, 'recorded_slots' => 0];
    $dailyTrend = $summary['daily_trend'] ?? [];
    $byUnit = $summary['by_unit'] ?? [];
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
    // DUA SATUAN YANG SENGAJA TIDAK DISAMAKAN: alum kg/jam melawan polimer
    // g/jam. Dosis polimer memang tiga orde lebih kecil, dan menuliskannya
    // sebagai 0,42 kg/jam akan menyembunyikan ketelitian yang dicatat di
    // lapangan. Keduanya juga tidak dijumlahkan — "total dosis kimia" bukan
    // besaran yang punya arti. Kedua kolom pH sengaja TANPA satuan: pH tidak
    // punya satuan.
    $metrikMeta = [
        'raw_water_flow_m3h' => ['label' => 'Aliran Air Baku', 'pendek' => 'Aliran Air Baku (m³/jam)', 'satuan' => 'm³/jam', 'digits' => 1],
        'clarified_water_flow_m3h' => ['label' => 'Aliran Air Jernih', 'pendek' => 'Aliran Air Jernih (m³/jam)', 'satuan' => 'm³/jam', 'digits' => 1],
        'softener_inlet_ph' => ['label' => 'pH Masuk Softener', 'pendek' => 'pH Masuk Softener', 'satuan' => '', 'digits' => 2],
        'softener_outlet_hardness_ppm' => ['label' => 'Kesadahan Keluaran Softener', 'pendek' => 'Kesadahan Keluaran Softener (ppm)', 'satuan' => 'ppm', 'digits' => 1],
        'alum_dosing_kgh' => ['label' => 'Dosis Alum', 'pendek' => 'Dosis Alum (kg/jam)', 'satuan' => 'kg/jam', 'digits' => 1],
        'polymer_dosing_gh' => ['label' => 'Dosis Polimer', 'pendek' => 'Dosis Polimer (g/jam)', 'satuan' => 'g/jam', 'digits' => 0],
        'boiler_feed_tank_temp_c' => ['label' => 'Suhu Tangki Air Umpan', 'pendek' => 'Suhu Tangki Air Umpan (°C)', 'satuan' => '°C', 'digits' => 1],
        'boiler_feed_water_ph' => ['label' => 'pH Air Umpan Boiler', 'pendek' => 'pH Air Umpan Boiler', 'satuan' => '', 'digits' => 2],
        'boiler_feed_tds_ppm' => ['label' => 'TDS Air Umpan Boiler', 'pendek' => 'TDS Air Umpan Boiler (ppm)', 'satuan' => 'ppm', 'digits' => 0],
    ];

    // EMPAT BLOK BERJUDUL, disusun sebagai baris-baris tata letak MENGIKUTI
    // RANTAI PENGOLAHAN AIR. Kolom tiap blok TIDAK didaftar di sini — ia
    // diturunkan dari field `group` pada respons (lihat $kolomGrup), supaya
    // kolom baru dari migrasi kelak masuk ke blok yang benar tanpa menyunting
    // berkas ini.
    $barisBlok = [
        ['row' => '', 'blok' => [
            ['group' => 'water_flow', 'testid' => 'metric-block-water-flow', 'judul' => '1 &middot; Aliran Air', 'hint' => 'hulu rantai pengolahan', 'grid' => 'md-kpis--2', 'catatan' => 'rantai'],
        ]],
        ['row' => 'md-row--2', 'blok' => [
            ['group' => 'softener', 'testid' => 'metric-block-softener', 'judul' => '2 &middot; Softener', 'hint' => 'pH di masukan, kesadahan di keluaran', 'grid' => 'md-kpis--2', 'catatan' => 'softener'],
            ['group' => 'chemical_dosing', 'testid' => 'metric-block-chemical-dosing', 'judul' => '3 &middot; Dosis Kimia', 'hint' => 'dua satuan berbeda', 'grid' => 'md-kpis--2', 'catatan' => 'dosis'],
        ]],
        ['row' => '', 'blok' => [
            ['group' => 'boiler_feed', 'testid' => 'metric-block-boiler-feed', 'judul' => '4 &middot; Air Umpan Boiler', 'hint' => 'hilir rantai &mdash; pertanyaan pokok stasiun ini', 'grid' => 'md-kpis--3', 'catatan' => 'ambang'],
        ]],
    ];

    // Kolom-kolom sebuah blok, DITURUNKAN DARI RESPONS: setiap entri `metrics`
    // membawa `group`-nya sendiri. Inilah yang membuat kartunya satu markup dan
    // bukan sembilan salinan.
    $kolomGrup = fn (string $group) => array_keys(array_filter(
        $metrics,
        fn ($metric) => ($metric['group'] ?? null) === $group,
    ));

    // Porsi sebuah baris rekap per inspector. Penyebutnya SLOT TERCATAT, bukan
    // slot yang diharapkan — yang kedua akan mencampur slot tak tersentuh ke
    // dalam porsi slot yang justru tercatat.
    $porsi = function (int $count, int $recordedSlots) {
        if ($recordedSlots === 0) {
            return '—';
        }

        return number_format(100 * $count / $recordedSlots, 1, ',', '.').'%';
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

    // PENYEBUT TERBESAR di antara kesembilan kartu, dipakai keterangan
    // kelengkapan untuk MENYATAKAN bahwa jumlah slot terisi dapat
    // melebihinya — bukan untuk mengoreksi apa pun.
    $penyebutTerbesar = $metrics === [] ? 0 : max(array_map(
        fn ($metric) => (int) ($metric['reading_count'] ?? 0),
        $metrics,
    ));
@endphp

<div class="md" data-testid="laporan-process-water">

    {{-- ============ 1. Hero: periode yang sedang dibaca ============ --}}
    <section class="md-hero" data-testid="report-hero">
        <div class="md-hero__main">
            <p class="md-hero__eyebrow">Laporan Periode &middot; Stasiun Process Water</p>
            <h1 class="md-hero__title">{{ $selectedPeriod['name'] ?? 'Laporan Periode Process Water' }}</h1>
            <p class="md-hero__subtitle">
                @if ($selectedPeriod)
                    {{ $summary['business_unit']['name'] ?? $businessUnitName }}
                    @if ($selectedProductionLine) &middot; {{ $selectedProductionLine['name'] }} @endif
                    &middot; {{ $tgl($selectedPeriod['start_date']) }} &ndash; {{ $tgl($selectedPeriod['end_date']) }}
                @else
                    Kelayakan air umpan boiler sepanjang satu Periode Pelaporan pada satu production line
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
                        {{ $cacah($coverage['process_water_unit_count']) }} unit Process Water
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
        Hanya periode yang mencakup Process Water yang ditampilkan, dan periode tertutup tetap dapat dibaca serta diekspor. Angka disaring menurut Production Line yang melekat pada record itu sendiri, dan keanggotaan periode mengikuti tanggal recordnya. Mengganti mill mengosongkan pilihan periode DAN production line, bukan menggantinya diam-diam. Ekspor memuat satu baris per slot waktu beserta ke-13 kolom detail &mdash; kolom shift dan inspector_id IKUT, karena ekspor adalah rincian mentah.
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
                Laporan Process Water menghasilkan angka gabungan per line, dan mencampur beberapa
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
             Process Water. Bukan galat — cukup arahkan ke layar yang
             membuatnya. Pemilih periode sengaja tetap kosong dan TIDAK diisi
             periode milik jenis stasiun lain. --}}
        <div class="md-empty" data-testid="no-period-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
            </span>
            <p class="md-empty__title">Belum ada Periode Pelaporan</p>
            <p class="md-empty__text">
                Mill ini belum memiliki Periode Pelaporan yang mencakup stasiun Process Water.
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
                <span class="md-card__hint">baca ini lebih dulu &mdash; sembilan metrik di bawah bersandar padanya</span>
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
                        Belum ada slot yang diharapkan &mdash; belum ada unit Process Water yang
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
                    Slot yang diharapkan = {{ $cacah($coverage['process_water_unit_count']) }} unit
                    Process Water &times; {{ $cacah($coverage['days_counted']) }} hari{{ $coverage['period_running'] && (int) $coverage['days_counted'] > 0 ? ' (sampai hari ini)' : '' }}
                    &times; {{ $cacah($coverage['slots_per_unit_per_day']) }} slot
                    = {{ $cacah($coverage['expected_slots']) }}.
                    Jumlah unit adalah unit yang BENAR-BENAR punya record pada periode dan line ini,
                    bukan jumlah stasiun terdaftar; angka
                    {{ $cacah($coverage['slots_per_unit_per_day']) }} slot per hari diambil dari kisi
                    slot kanonis layar input itu sendiri (07:00 sampai 06:00 keesokan hari), bukan
                    dikarang di sini.
                </p>
            </div>

            {{-- ATURAN SEBELAS-BUKAN-TIGA-BELAS, DIRENDER UTUH. Ini keterangan
                 terpenting di layar ini: tiga belas kolom adalah yang tampak
                 wajar dari skema, dan sebelas adalah yang benar. --}}
            <div class="md-explain" data-testid="eleven-columns-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
                <span>
                    <b>Satu slot dianggap terisi bila minimal satu dari SEBELAS kolom pembacaan
                    terisi &mdash; bukan tiga belas.</b> Baris detail stasiun ini membawa tiga belas
                    kolom, tetapi <code>shift</code> dan <code>inspector_id</code> adalah <b>kolom
                    identitas, bukan kolom pembacaan</b>, dan keduanya <b>dikecualikan</b> dari
                    pemeriksaan itu. Pengecualian tersebut dinyatakan eksplisit di docblock service
                    dan <b>dipinjam</b> dari layar input itu sendiri, bukan diturunkan ulang di sini,
                    supaya laporan dan formulir tidak pernah berbeda pendapat tentang apa yang
                    tercatat. Ini <b>berbeda dari Engine Room</b>, yang menghitung seluruh dua puluh
                    tujuh kolomnya karena tidak punya kolom identitas untuk dikecualikan.
                    <small>
                        <b>Dua akibatnya berlawanan dan keduanya harus dibedakan.</b> Satu slot yang
                        hanya mengisi <code>shift</code> dan/atau <code>inspector_id</code>
                        <b>tidak</b> terhitung terisi. Sebaliknya, satu slot yang hanya mengisi kolom
                        <b>teks bebas</b> tanpa satu pun kolom ukur <b>tetap</b> terhitung terisi,
                        karena kedua kolom teks termasuk dalam kesebelas kolom pembacaan &mdash;
                        sesuatu memang ditulis di slot itu. Karena itulah
                        <b>jumlah slot terisi dapat MELEBIHI penyebut setiap kartu metrik</b>: pada
                        periode ini {{ $cacah($coverage['filled_slots']) }} slot terisi, sementara
                        penyebut terbesar di antara sembilan metrik
                        {{ $cacah($penyebutTerbesar) }} pembacaan. Selisih itu <b>benar dan tidak
                        dikoreksi</b> &mdash; yang dilampaui adalah penyebut metrik, bukan penyebut
                        kelengkapan. Kesembilan kolom ukur nullable dan terisi saling bebas, jadi
                        <b>setiap metrik menerbitkan penyebutnya sendiri</b>; satu penyebut bersama
                        akan salah untuk setidaknya delapan di antaranya. Record berstatus draft IKUT
                        terhitung pada seluruh angka laporan ini.
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
                    Tidak ada satu pun slot Process Water yang terisi pada rentang periode ini di
                    {{ $selectedProductionLine['name'] ?? 'production line ini' }}, sehingga seluruh
                    terendah, rata-rata dan tertinggi ditampilkan sebagai tidak tersedia &mdash; bukan
                    sebagai nol. Kartu parameter di bawah tetap digambar lengkap dengan penyebut nol
                    supaya terlihat bahwa parameternya ada dan tidak terukur, sementara tren harian,
                    rekap per inspector, rekap per unit dan rekap harian tidak digambar.
                </p>
            </div>
        @endif

        {{-- ============ 4. Sembilan kartu metrik, empat blok ============
             PENGELOMPOKANNYA MURNI TATA LETAK: tidak satu pun angka dihitung
             per blok dan tidak ada total per blok, karena satuan di dalam satu
             blok pun berbeda. URUTAN BLOKNYA MENGIKUTI RANTAI PENGOLAHAN AIR.

             MARKUP KARTUNYA DITULIS SEKALI DI SINI dan dipakai sembilan kali
             lewat perulangan atas field `group` DARI RESPONS, bukan disalin per
             blok dan bukan atas daftar nama kolom yang dipaku: kesembilan kartu
             HARUS mencetak penyebutnya masing-masing, dan satu salinan markup
             berarti tidak ada satu blok pun yang bisa diam-diam kehilangan
             penyebutnya atau mewarisi penyebut blok lain. --}}
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
                                         untuk kesembilan metrik akan
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

                        @if ($blok['catatan'] === 'rantai')
                            <div class="md-explain" data-testid="treatment-chain-note">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h11l-3-3M17 17H6l3 3"/></svg>
                                <span>
                                    Kesembilan kartu dikelompokkan menjadi <b>empat blok berjudul yang
                                    mengikuti rantai pengolahan air</b> &mdash; <b>1 Aliran Air
                                    &rarr; 2 Softener &rarr; 3 Dosis Kimia &rarr; 4 Air Umpan
                                    Boiler</b> &mdash; bukan mengikuti urutan kolom di basis data.
                                    <small>
                                        Urutannya bekerja: pembaca yang menemukan angka air umpan
                                        boiler yang tidak ia harapkan dapat <b>bergerak mundur ke
                                        tahap sebelumnya</b> &mdash; dosis kimia, lalu softener, lalu
                                        aliran air baku &mdash; tanpa perlu tahu nama kolom mana pun.
                                        Urutan kolom di basis data tidak punya arti operasional dan
                                        tidak dipakai di layar ini. Pengelompokannya <b>murni tata
                                        letak</b>: tidak satu pun angka dihitung per blok dan tidak
                                        ada total per blok.
                                    </small>
                                </span>
                            </div>
                        @elseif ($blok['catatan'] === 'softener')
                            <div class="md-explain" data-testid="softener-note">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                                <span>
                                    Kedua kartu blok ini mengukur <b>dua hal yang berbeda di dua titik
                                    yang berbeda</b> &mdash; pH di <b>MASUKAN</b> softener, kesadahan
                                    di <b>KELUARAN</b> &mdash; jadi keduanya tidak saling
                                    menggantikan dan <b>penyebutnya tidak dipinjamkan</b>.
                                    <small>
                                        Sebuah kolom yang tidak pernah diisi sepanjang periode tetap
                                        punya kartunya, dengan &lsquo;tidak tersedia&rsquo; dan
                                        <b>nol pembacaan</b> &mdash; bukan dihapus dari layar. Kartu
                                        yang hilang tidak dapat dibedakan dari kartu yang belum pernah
                                        ada; kartu berpenyebut nol menyatakan dengan tepat bahwa
                                        <b>kolomnya ada dan belum terisi</b>.
                                    </small>
                                </span>
                            </div>
                        @elseif ($blok['catatan'] === 'dosis')
                            <div class="md-explain" data-testid="dosing-unit-note">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3h6v6l4 9a2 2 0 0 1-1.8 3H6.8A2 2 0 0 1 5 18l4-9z"/><path d="M7 15h10"/></svg>
                                <span>
                                    <b>Dua satuan berbeda dalam satu blok, dan keduanya ditulis apa
                                    adanya:</b> alum dalam <b>kg/jam</b>, polimer dalam <b>g/jam</b>.
                                    <small>
                                        Keduanya <b>tidak</b> disamakan ke satu satuan demi kerapian
                                        &mdash; besaran dosis polimer memang tiga orde lebih kecil
                                        daripada alum, dan menuliskannya sebagai 0,42 kg/jam akan
                                        menyembunyikan ketelitian yang dicatat di lapangan. Keduanya
                                        juga <b>tidak dijumlahkan</b>: &lsquo;total dosis kimia&rsquo;
                                        bukan besaran yang punya arti.
                                    </small>
                                </span>
                            </div>
                        @elseif ($blok['catatan'] === 'ambang')
                            {{-- PERNYATAAN KETIADAAN PENANDAAN DI LUAR BATAS,
                                 DIRENDER UTUH DAN MENEMPEL PADA BLOK INI —
                                 bukan sebagai catatan kaki halaman. Justru di
                                 blok inilah godaan menandai paling besar, karena
                                 pH dan TDS air umpan boiler dikenal sangat luas
                                 di luar aplikasi ini. Kotaknya memakai
                                 `.md-explain` dan BUKAN `.md-threshold`: nol
                                 elemen berkelas penanda ambang adalah invarian
                                 yang diasersi menurut nama kelas. --}}
                            <div class="md-explain" data-testid="no-threshold-note">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l9 4v5c0 5-3.8 8.4-9 9.9C6.8 20.4 3 17 3 12V7z"/><path d="M12 8v4M12 15h.01"/></svg>
                                <span>
                                    <b>TIDAK ADA satu pun nilai di layar ini yang ditandai di luar
                                    batas, dan pernyataan ini dirender UTUH tanpa diringkas.</b>
                                    Stasiun Process Water <b>tidak punya tabel master target</b>
                                    &mdash; tidak ada satu baris acuan yang bisa dibandingkan dengan
                                    satu pun dari sembilan metrik di atas, baik per unit maupun per
                                    line. Dan justru di sinilah godaan menandai paling besar di antara
                                    seluruh laporan stasiun: <b>pH air umpan boiler dan TDS air umpan
                                    boiler punya batas yang dikenal SANGAT luas DI LUAR aplikasi
                                    ini</b> &mdash; setiap pedoman air umpan boiler memuat angkanya,
                                    dan hampir setiap pembaca laporan ini sudah membawa angka itu di
                                    kepalanya.
                                    <small>
                                        Menurunkan ambang dari <b>data periode ini sendiri</b> &mdash;
                                        rata-rata &plusmn; dua simpangan baku, atau persentil atas
                                        pembacaan yang ada &mdash; akan menghasilkan garis yang
                                        terlihat berwibawa, lalu <b>dibaca sebagai batas keselamatan
                                        boiler</b>, padahal ia hanya <b>turunan statistik</b> dari
                                        pencatatan yang kelengkapannya sendiri sedang dinilai di blok
                                        paling atas halaman ini. Ambang semacam itu akan ikut bergeser
                                        setiap kali kelengkapan pencatatan berubah, dan itu bukan
                                        sifat yang boleh dimiliki sebuah batas keselamatan. Karena itu
                                        kesembilan angka ditampilkan <b>apa adanya</b>: tanpa warna
                                        peringatan, tanpa ikon, tanpa kata &lsquo;tinggi&rsquo; atau
                                        &lsquo;rendah&rsquo;, dan tanpa satu pun sel yang ditandai
                                        &mdash; <b>termasuk nilai yang jauh di luar batas yang dikenal
                                        umum</b>, yang tetap tampil polos di layar ini dan di berkas
                                        ekspor. Penilaian layak atau tidak layak tetap di tangan
                                        pembacanya, dan laporan ini tidak berpura-pura memilikinya.
                                        Bila kelak batas air umpan boiler dimasukkan sebagai master
                                        target, laporan ini perlu ditinjau ulang &mdash; dan
                                        peninjauan itu menyangkut keselamatan peralatan, bukan sekadar
                                        tampilan.
                                    </small>
                                </span>
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>
        @endforeach

        @if ($hasData)
            {{-- ============ 5. Tren harian aliran air baku ============
                 SATU seri, dan seri itu sebuah RATA-RATA per tanggal — sah
                 karena aliran adalah besaran sesaat per slot, bukan angka meter
                 kumulatif. Aliran air baku yang digrafikkan karena ia hulu
                 seluruh rantai dan punya penyebut terbesar, sehingga bentuk
                 grafiknya paling sedikit dipengaruhi slot yang tidak terisi. --}}
            @foreach ([
                ['column' => 'raw_water_flow_m3h', 'testid' => 'daily-trend-raw-water-flow', 'judul' => 'Tren Harian Aliran Air Baku'],
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
                            {{-- Titik tanpa pembacaan bernilai tidak tersedia,
                                 BUKAN nol — batangnya karena itu kosong dan
                                 bukan batang nol yang terbaca sebagai hasil
                                 ukur. Tanpa modifier warna apa pun: tidak ada
                                 batang yang ditandai "rendah", "tinggi", maupun
                                 di luar batas di layar ini. --}}
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
                            <small>Sumbu tegak tidak dimulai dari nol &mdash; batang membandingkan tanggal, bukan besaran mutlak. Tidak ada rentang aman yang digambar di sini dan tidak ada batang yang ditandai.</small>
                        </li>
                    </ul>
                    <div class="md-explain" data-testid="{{ $tren['testid'] }}-note">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                        <span>
                            Seri ini memuat <b>rata-rata aliran air baku per tanggal</b> &mdash; sah
                            dirata-ratakan karena aliran adalah <b>besaran sesaat per slot</b>, bukan
                            angka meter kumulatif.
                            <small>
                                Aliran air baku dipilih untuk digrafikkan karena ia <b>hulu seluruh
                                rantai</b> dan punya penyebut terbesar di antara kesembilan metrik,
                                jadi bentuk grafiknya paling sedikit dipengaruhi oleh slot yang tidak
                                terisi. Tanggal yang tidak punya pembacaan sama sekali <b>tidak
                                digambar</b> &mdash; bukan digambar sebagai batang nol, yang akan
                                terbaca sebagai hasil pengukuran.
                            </small>
                        </span>
                    </div>
                </section>
            @endforeach
        @endif

        {{-- ============ 6. Rekap Catatan Tindakan — TEKS, bukan sebaran ============
             DUA BAGIAN TERPISAH dengan Rekap Temuan di bawahnya, dan itu
             disengaja: satu menjawab apa yang dikerjakan, satu menjawab apa yang
             ditemukan. --}}
        <section class="md-card" data-testid="action-taken-entries">
            <header class="md-card__head">
                <h3>Rekap Catatan Tindakan</h3>
                <span class="md-card__hint">
                    {{ $cacah(count($catatanTindakan)) }} slot mengisi kolom <code>action_taken_status</code> sepanjang periode
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
                    <b><code>action_taken_status</code> diterbitkan sebagai rekap TEKS &mdash; meski
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

        {{-- ============ 7. Rekap Temuan — bagian TERPISAH ============ --}}
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
            {{-- ============ 8. Rekap per Inspector ============
                 DI LAYAR, dengan kode MENTAH. Penyimpangan sadar dari preseden
                 Storage Tank (yang menerbitkan kolom sejenis hanya di ekspor),
                 diminta pemilik produk 2026-10-16. Entrinya diurutkan menurut
                 KODE, bukan menurut jumlah slot: mengurutkan menurut jumlah akan
                 membuat tabel ini terbaca sebagai papan peringkat. --}}
            <details class="md-details" open data-testid="inspector-recap-details">
                <summary data-testid="inspector-recap-toggle">
                    Rekap per Inspector
                    <small>
                        {{ $cacah(count($inspectorRecap['entries'])) }} inspector + satu baris
                        &lsquo;tidak tercatat&rsquo; &middot; dasar
                        {{ $cacah($inspectorRecap['recorded_slots']) }} slot terisi &middot; klik untuk
                        membuka atau menutup
                    </small>
                </summary>
                <div class="md-recap">
                    <table class="md-table" data-testid="inspector-recap-table">
                        <thead>
                            <tr>
                                <th scope="col">inspector_id</th>
                                <th scope="col">Slot terisi</th>
                                <th scope="col">Porsi dari slot terisi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- KODE MENTAH APA ADANYA. Tidak ada join ke tabel
                                 users dan tidak ada percobaan memulangkan nama —
                                 kolomnya teks bebas tanpa relasi, jadi tidak ada
                                 pemetaan untuk ditebak. --}}
                            @foreach ($inspectorRecap['entries'] as $entry)
                                <tr data-testid="inspector-row" data-inspector-id="{{ $entry['inspector_id'] }}">
                                    <td><code>{{ $entry['inspector_id'] }}</code></td>
                                    <td>{{ $cacah($entry['filled_slots']) }}</td>
                                    <td class="is-muted">{{ $porsi((int) $entry['filled_slots'], (int) $inspectorRecap['recorded_slots']) }}</td>
                                </tr>
                            @endforeach
                            {{-- BARISNYA SENDIRI, SELALU, dan tidak pernah
                                 digabungkan ke salah satu inspector. --}}
                            <tr data-testid="inspector-not-recorded">
                                <td class="is-muted">tidak tercatat</td>
                                <td class="is-muted">{{ $cacah($inspectorRecap['not_recorded']) }}</td>
                                <td class="is-muted">{{ $porsi((int) $inspectorRecap['not_recorded'], (int) $inspectorRecap['recorded_slots']) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr data-testid="inspector-recorded-total">
                                <td>Seluruh slot terisi</td>
                                <td>{{ $cacah($inspectorRecap['recorded_slots']) }}</td>
                                <td>{{ (int) $inspectorRecap['recorded_slots'] === 0 ? '—' : '100,0%' }}</td>
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
                            dua inspector lewat angka ini akan salah. Laporan ini karena itu
                            <b>tidak</b> mengurutkan menurut jumlah, <b>tidak</b> memberi peringkat,
                            dan <b>tidak</b> menandai siapa pun; barisnya diurutkan menurut
                            <b>kodenya</b>. Porsi tiap baris memakai <b>jumlah slot terisi</b> sebagai
                            penyebut, bukan jumlah slot yang diharapkan &mdash; yang kedua akan
                            mencampur slot tak tersentuh ke dalam porsi slot yang justru tercatat.
                        </small>
                    </span>
                </div>
                <div class="md-explain" data-testid="inspector-recap-raw-code-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                    <span>
                        Baris <b>&lsquo;tidak tercatat&rsquo;</b> memuat slot yang <b>terisi</b> tetapi
                        kolom inspector-nya kosong. Slot itu <b>tetap terhitung</b> pada kelengkapan di
                        atas, karena <code>inspector_id</code> kolom identitas dan tidak ikut
                        menentukan apakah sebuah slot terisi. Baris itu <b>tidak pernah</b> digabungkan
                        ke salah satu inspector.
                        <small>
                            <code>inspector_id</code> bertipe <b>string polos, bukan relasi ke tabel
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

            {{-- ============ 9. Rekap per unit Process Water ============
                 9 kolom rata-rata + unit + hari + slot = 12 kolom. Ia menggulir
                 mendatar DI DALAM kartunya sendiri lewat
                 .md-recap { overflow-x: auto }, dan halaman sendiri tidak pernah
                 menggulir mendatar. --}}
            <details class="md-details" open data-testid="by-unit-details">
                <summary data-testid="by-unit-toggle">
                    Rekap per Unit Process Water
                    <small>
                        {{ $cacah(count($byUnit)) }} unit &middot; angka periode di atas menggabungkan
                        seluruhnya &middot; 12 kolom, menggulir mendatar di dalam kartunya &middot;
                        klik untuk membuka atau menutup
                    </small>
                </summary>
                <p class="md-scrollhint" data-testid="by-unit-scroll-hint">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M15 8l4 4-4 4M9 8l-4 4 4 4"/></svg>
                    Tabel ini membawa <b>9 kolom angka</b> (12 kolom seluruhnya) dan menggulir mendatar
                    di dalam kartunya sendiri &mdash; halaman tidak pernah menggulir mendatar.
                </p>
                <div class="md-recap">
                    <table class="md-table" data-testid="by-unit-table">
                        <thead>
                            <tr>
                                <th scope="col">Unit Process Water</th>
                                <th scope="col">Hari tercatat</th>
                                <th scope="col">Slot terisi</th>
                                @foreach ($metrikMeta as $meta)
                                    <th scope="col">{{ $meta['pendek'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            {{-- UNIT TANPA SATU PEMBACAAN PUN TETAP DIRENDER,
                                 dengan slot terisi nol dan rata-rata tidak
                                 tersedia: membuangnya akan menyembunyikan justru
                                 unit yang tidak pernah ditulis — yang paling
                                 perlu dilihat — dan unit itu tetap ikut menambah
                                 jumlah unit pada penyebut kelengkapan. --}}
                            @foreach ($byUnit as $row)
                                <tr data-testid="by-unit-row">
                                    <td>{{ ($row['process_water_id'] ?? '') !== '' ? $row['process_water_id'] : 'Belum diisi' }}</td>
                                    <td>{{ $cacah($row['days_with_rows'] ?? 0) }}</td>
                                    <td data-testid="by-unit-reading-rows">{{ $cacah($row['reading_rows']) }}</td>
                                    @foreach ($metrikMeta as $column => $meta)
                                        <td @class(['is-muted' => ($row['metrics'][$column]['avg'] ?? null) === null])>
                                            {{ $nilai($row['metrics'][$column]['avg'] ?? null, $meta['digits']) }}
                                            <small class="is-muted">(n={{ $cacah($row['metrics'][$column]['reading_count'] ?? 0) }})</small>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            {{-- GABUNGAN SELURUH UNIT, dihitung ulang atas
                                 seluruh slot periode — bukan rata-rata dari
                                 rata-rata per unit, yang akan memberi bobot sama
                                 kepada unit berisi dua slot dan unit berisi dua
                                 ratus. Jumlah slot di sini sama dengan jumlah
                                 slot terisi pada kelengkapan di atas, dan kolom
                                 slot terisi per unit menjumlah ke angka itu. --}}
                            <tr data-testid="by-unit-row-total">
                                <td>Gabungan {{ $cacah($coverage['process_water_unit_count']) }} unit</td>
                                <td>{{ $cacah($total['days_with_records']) }}</td>
                                <td>{{ $cacah($total['reading_rows']) }}</td>
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
                <div class="md-explain" data-testid="by-unit-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                    <span>
                        Angka periode di seluruh kartu di atas <b>menggabungkan seluruh unit</b>; tabel
                        ini menunjukkan sebarannya. Unit yang tidak punya pembacaan sama sekali
                        <b>tetap muncul</b> dengan jumlah pembacaan nol, dan ia tetap ikut menambah
                        jumlah unit pada penyebut kelengkapan.
                        <small>
                            <code>process_water_id</code> adalah <b>NAMA UNIT</b> yang diketik di layar
                            input, bukan kunci baris. Baris gabungan dihitung ulang atas seluruh slot
                            periode, <b>bukan</b> dengan merata-ratakan nilai per unit yang sudah
                            dibulatkan.
                        </small>
                    </span>
                </div>
            </details>

            {{-- ============ 10. Rekap harian (dapat ditutup) ============
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
                                 ber-record pada baris gabungan rekap per
                                 unit. --}}
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
                            tanggal adalah rata-rata harian metrik itu sendiri. Tabel ini membawa 9
                            kolom angka dan menggulir mendatar <b>di dalam kartunya sendiri</b>;
                            halaman tidak pernah menggulir mendatar.
                        </small>
                    </span>
                </div>
            </details>
        @endif

        {{-- ============ 11. Yang TIDAK diterbitkan — dan alasannya ============
             Ketiadaan yang disengaja, dinyatakan supaya tidak terbaca sebagai
             kelalaian: bagian yang hilang tanpa keterangan tidak dapat
             dibedakan dari bagian yang gagal dimuat. --}}
        <section class="md-card" data-testid="not-published">
            <header class="md-card__head">
                <h3>Yang Tidak Diterbitkan di Layar Ini</h3>
                <span class="md-card__hint">ketiadaan yang disengaja, dinyatakan supaya tidak terbaca sebagai kelalaian</span>
            </header>
            <div class="md-explain" data-testid="no-downtime-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/></svg>
                <span>
                    <b>Layar ini tidak memuat bagian downtime, dan ketiadaannya disengaja.</b> Stasiun
                    Process Water <b>tidak mencatat lama berhenti sama sekali</b> &mdash; tidak ada satu
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
                    <b>Kolom <code>shift</code> tidak diterbitkan di layar ini, dan ketiadaannya
                    disengaja.</b> Kolom teks bebas itu ada di baris detail tetapi <b>kosong di seluruh
                    baris</b>, dan ia <b>sudah dijadwalkan diganti relasi ke master Shift</b> pada
                    putaran penandaan record. Menerbitkannya sekarang berarti menerbitkan kolom kosong
                    yang <b>akan berubah arti</b> begitu relasi itu masuk &mdash; pembaca yang hari ini
                    belajar membacanya sebagai teks bebas harus belajar ulang besok, dan itu lebih
                    buruk daripada tidak menerbitkannya. Karena itu <b>laporan per shift datang bersama
                    putaran penandaan itu</b>, bukan sebelumnya.
                    <small>
                        Konsekuensinya sudah tampak di blok kelengkapan di atas: <code>shift</code> dan
                        <code>inspector_id</code> adalah dua kolom yang dikecualikan dari pemeriksaan
                        slot terisi, sehingga baris detail membawa tiga belas kolom tetapi yang dihitung
                        hanya sebelas. <b>Keduanya tetap ikut di berkas ekspor</b>, karena ekspor adalah
                        rincian mentah &mdash; mengecualikan sebuah kolom dari pemeriksaan slot terisi
                        bukan alasan membuangnya dari berkas.
                    </small>
                </span>
            </div>
        </section>
    @endif
    </div>
</div>
