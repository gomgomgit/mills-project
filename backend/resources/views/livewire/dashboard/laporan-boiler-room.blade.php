{{--
    screen-131--laporan-boiler-room-web — Laporan Periode Boiler Room.

    Susunan mengikuti mock .asdlc/generated/2-business-spec/screens/html/
    screen-131--laporan-boiler-room-web.html: hero, baris filter, kartu
    KELENGKAPAN PENCATATAN (sengaja di ATAS seluruh angka lain), 4 kartu
    metrik kondisi, 3 kartu (gas buang + dua perawatan), dua grafik tren
    berdampingan, rekap per unit boiler, lalu rekap harian yang dapat
    dibuka/tutup. Yang ditiru adalah SUSUNAN dan kepadatan informasinya —
    kosakata kelasnya `md-*` milik aplikasi
    (dashboard/partials/report-styles.blade.php).

    CSS TIDAK di-include di sini: partial report-styles memancarkan <style>
    dan Livewire 3 memasang wire:id pada elemen ter-render PERTAMA, sehingga
    menaruhnya di dalam/di atas root komponen mematikan seluruh wire:model.
    Partial itu dimuat lewat <x-slot:styles> di
    dashboard/laporan-boiler-room.blade.php.

    TIDAK ADA PENANDAAN NILAI DI LUAR BATAS di layar ini — tanpa warna
    peringatan, ikon, maupun label pelanggaran, dan tanpa kelas
    `md-trendchart__col--low` yang mewarnai batang. Boiler Room tidak punya
    master target operasional (tidak ada BoilerRoomOperationalTarget), jadi
    ambang apa pun di sini hanyalah turunan statistik dari data periode itu
    sendiri — dan angka tekanan boiler yang diwarnai merah akan dibaca
    sebagai batas KESELAMATAN. Penilaian ada pada pembaca. Alasan lengkapnya
    ada di docblock App\Services\BoilerRoomReportService.

    Bacaan saja: tidak ada satu pun tombol/field yang mengubah data stasiun.
--}}
@php
    // Angka dengan jumlah desimal tetap. null SELALU menjadi tanda pisah,
    // TIDAK PERNAH 0 — nol berarti "terukur dan hasilnya nol", tanda pisah
    // berarti "tidak pernah diukur".
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

    $statusLabel = fn (?string $status) => match ($status) {
        'draft' => 'Draft',
        'open' => 'Terbuka',
        'closed' => 'Tertutup',
        default => (string) $status,
    };

    $coverage = $summary['coverage'] ?? null;
    $metrics = $summary['metrics'] ?? [];
    $maintenance = $summary['maintenance'] ?? [];
    $daily = $summary['daily'] ?? [];
    $byUnit = $summary['by_unit'] ?? [];
    $total = $summary['total'] ?? null;
    $hasData = (bool) ($summary['has_data'] ?? false);

    // Lima metrik kondisi utama yang ditampilkan sebagai kartu, persis
    // seperti mock. Empat metrik numerik lainnya tetap ada pada payload API
    // (feed_water_temp_c, feed_water_tank_level_percent,
    // boiler_water_level_percent, dust_collector_differential_pressure_mmh2o)
    // tetapi tidak dijadikan kartu — information_displayed layar ini hanya
    // menyebut kelimanya.
    //
    // Ketiga kolom teks bebas (fuel_feed_rate, id_fan_load, sa_fan_load)
    // TIDAK ADA di daftar ini dan memang tidak boleh ada: satuannya bercampur
    // (Hz/%/ton), sehingga merata-ratakannya mustahil. Ketiganya hanya muncul
    // apa adanya pada ekspor CSV.
    $kartuMetrik = [
        ['key' => 'steam_pressure_bar', 'testid' => 'steam-pressure', 'label' => 'Tekanan Uap', 'satuan' => 'bar rata-rata', 'digits' => 1, 'sebutan' => 'tekanan'],
        ['key' => 'steam_temp_c', 'testid' => 'steam-temp', 'label' => 'Suhu Uap', 'satuan' => '&deg;C rata-rata', 'digits' => 1, 'sebutan' => 'suhu uap'],
        ['key' => 'water_tds_ppm', 'testid' => 'water-tds', 'label' => 'TDS Air', 'satuan' => 'ppm rata-rata', 'digits' => 0, 'sebutan' => 'TDS'],
        ['key' => 'water_ph', 'testid' => 'water-ph', 'label' => 'pH Air', 'satuan' => 'rata-rata', 'digits' => 1, 'sebutan' => 'pH'],
    ];

    $kartuGasBuang = ['key' => 'exhaust_gas_temp_c', 'testid' => 'exhaust-gas-temp', 'label' => 'Suhu Gas Buang', 'satuan' => '&deg;C rata-rata', 'digits' => 0, 'sebutan' => 'gas buang'];

    // Tinggi batang tren = 28% + 72% x (nilai - terendah) / (tertinggi -
    // terendah), sama dengan mock. Sumbu tegak SENGAJA tidak dimulai dari
    // nol — batang membandingkan tanggal satu sama lain, bukan besaran
    // mutlak — dan hal itu dinyatakan di kartunya sendiri. Ini semata skala
    // tampilan; tidak ada nilai yang ditandai aman/bahaya karenanya.
    $tinggiBatang = function ($value, $min, $max) {
        if ($value === null || $min === null || $max === null) {
            return 0;
        }

        if ((float) $max <= (float) $min) {
            return 100;
        }

        return round(28 + 72 * ((float) $value - (float) $min) / ((float) $max - (float) $min), 1);
    };

    $kolomTren = function (string $column) use ($daily) {
        return array_values(array_filter(
            array_map(fn ($row) => $row[$column], $daily),
            fn ($value) => $value !== null,
        ));
    };
@endphp

<div class="md" data-testid="laporan-boiler-room">

    {{-- ============ 1. Hero: periode yang sedang dibaca ============ --}}
    <section class="md-hero" data-testid="report-hero">
        <div class="md-hero__main">
            <p class="md-hero__eyebrow">Laporan Periode &middot; Stasiun Boiler Room</p>
            <h1 class="md-hero__title">{{ $selectedPeriod['name'] ?? 'Laporan Boiler Room' }}</h1>
            <p class="md-hero__subtitle">
                @if ($selectedPeriod)
                    {{ $summary['period']['business_unit_name'] ?? '' }}
                    @if (! empty($summary['period']['business_unit_name'])) &middot; @endif
                    {{ $tgl($selectedPeriod['start_date']) }} &ndash; {{ $tgl($selectedPeriod['end_date']) }}
                @else
                    Kondisi Boiler Room sepanjang satu Periode Pelaporan &mdash; seberapa stabil, dan seberapa lengkap tercatat
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
                        {{ $cacah($coverage['boiler_unit_count']) }} unit boiler
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

        @if (! $needsMillSelection && ! $hasNoMillForAccount)
            <div class="md-field">
                <label class="md-field__label" for="period-select">Periode Pelaporan</label>
                {{-- Saat mill belum punya periode yang mencakup Boiler Room,
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
                        wire:click="export('csv')" data-testid="export-button">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5M12 15V3"/></svg>
                    Ekspor CSV
                </button>
                <button type="button" class="md-btn"
                        wire:click="export('excel')" data-testid="export-excel">
                    Ekspor Excel
                </button>
            </div>
        @endif

        <p class="md-filters__hint">
            Daftar periode hanya memuat periode yang mencakup Boiler Room, yaitu periode berjenis
            Boiler Room maupun periode yang berlaku untuk semua jenis stasiun.
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
        <div class="md-empty" data-testid="select-mill-first-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
            </span>
            <p class="md-empty__title">Pilih mill terlebih dahulu</p>
            <p class="md-empty__text">
                Sebagai Admin Anda tidak terikat pada satu mill. Pilih mill pada pemilih di atas
                untuk menampilkan daftar Periode Pelaporan dan laporan Boiler Room-nya.
            </p>
        </div>
    @elseif ($periods === [])
        {{-- Empty state (c): mill belum punya Periode Pelaporan yang mencakup
             Boiler Room. Bukan 404 — cukup arahkan ke layar yang
             membuatnya. --}}
        <div class="md-empty" data-testid="no-period-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
            </span>
            <p class="md-empty__title">Belum ada Periode Pelaporan</p>
            <p class="md-empty__text">
                Mill ini belum memiliki Periode Pelaporan yang mencakup stasiun Boiler Room.
                Minta Admin membuatnya terlebih dahulu di layar Kelola Periode Pelaporan,
                lalu laporan periode akan tampil di sini.
            </p>
        </div>
    @elseif ($summary !== null)

        {{-- ============ 3. Kelengkapan pencatatan ============
             SENGAJA DI ATAS SELURUH ANGKA LAIN. Periode yang hanya terisi 20%
             tetap menghasilkan rata-rata yang terlihat rapi, dan pembaca harus
             tahu itu SEBELUM mempercayainya. Ini bagian isi laporan, bukan
             catatan kaki. --}}
        <section class="md-card" data-testid="recording-coverage">
            <header class="md-card__head">
                <h3>Kelengkapan Pencatatan</h3>
                <span class="md-card__hint">Baca ini lebih dulu &mdash; seluruh angka di bawah bersandar padanya</span>
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
            <div class="md-threshold">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                <span>
                    Seluruh angka di bawah dihitung dari <b>pembacaan yang ada</b>, bukan dari periode penuh &mdash;
                    slot yang tidak terisi tidak diperlakukan sebagai nol, melainkan tidak ikut dihitung.
                    <small>
                        Slot yang diharapkan = {{ $cacah($coverage['boiler_unit_count']) }} unit boiler
                        &times; {{ $cacah($coverage['days_in_period']) }} hari
                        &times; {{ $cacah($coverage['slots_per_unit_per_day']) }} slot.
                    </small>
                </span>
            </div>
        </section>

        {{-- ============ 4. Empat metrik kondisi utama ============ --}}
        <section class="md-kpis md-kpis--4" data-testid="report-metrics">
            @foreach ($kartuMetrik as $kartu)
                @php $m = $metrics[$kartu['key']]; @endphp
                <article class="md-kpi" data-testid="metric-{{ $kartu['testid'] }}">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">{{ $kartu['label'] }}</span>
                        <span class="md-kpi__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/><path d="M13.4 10.6 19 5"/><path d="M4 20a9 9 0 1 1 16 0"/></svg>
                        </span>
                    </div>
                    {{-- null ditulis sebagai tanda pisah, TIDAK PERNAH 0. --}}
                    <p class="md-kpi__value">
                        <span data-testid="metric-{{ $kartu['testid'] }}-avg">{{ $nilai($m['avg'], $kartu['digits']) }}</span>
                        <span>{!! $kartu['satuan'] !!}</span>
                    </p>
                    <p class="md-kpi__meta">
                        terendah <span data-testid="metric-{{ $kartu['testid'] }}-min">{{ $nilai($m['min'], $kartu['digits']) }}</span>
                        &middot; tertinggi <span data-testid="metric-{{ $kartu['testid'] }}-max">{{ $nilai($m['max'], $kartu['digits']) }}</span>
                    </p>
                    {{-- Jumlah pembacaan SELALU berdampingan dengan angkanya:
                         rata-rata dari 3 pembacaan dan rata-rata dari 300
                         pembacaan tidak boleh terlihat sama meyakinkan. --}}
                    <p class="md-kpi__foot">
                        dari <b data-testid="metric-{{ $kartu['testid'] }}-reading-count">{{ $cacah($m['reading_count']) }}</b>
                        pembacaan {{ $kartu['sebutan'] }}
                    </p>
                </article>
            @endforeach
        </section>

        {{-- ============ 5. Gas buang + dua perawatan ============ --}}
        <section class="md-kpis md-kpis--3" data-testid="report-metrics-secondary">
            @php $m = $metrics[$kartuGasBuang['key']]; @endphp
            <article class="md-kpi" data-testid="metric-{{ $kartuGasBuang['testid'] }}">
                <div class="md-kpi__top">
                    <span class="md-kpi__label">{{ $kartuGasBuang['label'] }}</span>
                    <span class="md-kpi__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 21V8l4-5 4 5v13"/><path d="M14 13h4v8h-4"/><path d="M3 21h18"/></svg>
                    </span>
                </div>
                <p class="md-kpi__value">
                    <span data-testid="metric-{{ $kartuGasBuang['testid'] }}-avg">{{ $nilai($m['avg'], $kartuGasBuang['digits']) }}</span>
                    <span>{!! $kartuGasBuang['satuan'] !!}</span>
                </p>
                <p class="md-kpi__meta">
                    terendah <span data-testid="metric-{{ $kartuGasBuang['testid'] }}-min">{{ $nilai($m['min'], $kartuGasBuang['digits']) }}</span>
                    &middot; tertinggi <span data-testid="metric-{{ $kartuGasBuang['testid'] }}-max">{{ $nilai($m['max'], $kartuGasBuang['digits']) }}</span>
                </p>
                <p class="md-kpi__foot">
                    dari <b data-testid="metric-{{ $kartuGasBuang['testid'] }}-reading-count">{{ $cacah($m['reading_count']) }}</b>
                    pembacaan {{ $kartuGasBuang['sebutan'] }}
                </p>
            </article>

            @foreach ([['blowdown', 'Blowdown'], ['sootblowing', 'Sootblowing']] as [$rawat, $rawatLabel])
                @php $p = $maintenance[$rawat]; @endphp
                <article class="md-kpi" data-testid="maintenance-{{ $rawat }}">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">{{ $rawatLabel }}</span>
                        <span class="md-kpi__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22a5 5 0 0 0 5-5c0-4-5-9-5-9s-5 5-5 9a5 5 0 0 0 5 5z"/><path d="M12 2v3"/></svg>
                        </span>
                    </div>
                    <p class="md-kpi__value">
                        <span data-testid="maintenance-{{ $rawat }}-executed">{{ $cacah($p['executed']) }}</span>
                        <span>kali dilakukan</span>
                    </p>
                    <p class="md-kpi__meta">rata-rata {{ $nilai($p['avg_per_day'], 1) }}/hari ber-record</p>
                    {{-- TIGA KEADAAN, ditampilkan terpisah. "Tidak tercatat"
                         TIDAK PERNAH digabungkan ke "tidak dilakukan":
                         melaporkan kelalaian perawatan yang tidak pernah
                         terjadi jauh lebih mahal daripada mengaku tidak tahu.
                         Ketiganya berjumlah sama dengan jumlah baris
                         pembacaan. --}}
                    <p class="md-kpi__foot">
                        <b data-testid="maintenance-{{ $rawat }}-not-recorded">{{ $cacah($p['not_recorded']) }}</b>
                        pembacaan tidak tercatat &mdash; tidak dihitung sebagai tidak dilakukan
                    </p>
                    <p class="md-kpi__foot">
                        <small>
                            <span data-testid="maintenance-{{ $rawat }}-not-executed">{{ $cacah($p['not_executed']) }}</span>
                            pembacaan tercatat sebagai tidak dilakukan
                            @if ($p['all_unrecorded'])
                                &middot; seluruh pembacaan periode ini tidak tercatat, jadi angka 0 di atas
                                bukan berarti tidak pernah dilakukan
                            @endif
                        </small>
                    </p>
                </article>
            @endforeach
        </section>

        {{-- ============ 6. Label wajib: ekstrem vs rata-rata harian ============
             Angka terendah/tertinggi pada kartu di atas berasal dari PEMBACAAN
             MENTAH, sedangkan kolom rata-rata pada rekap di bawah adalah
             rata-rata HARIAN. Keduanya memang tidak dapat direkonsiliasi, dan
             tanpa label ini pembaca akan menyimpulkan laporannya rusak. --}}
        <div class="md-threshold" data-testid="raw-extremes-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
            <span>
                Nilai <b>terendah dan tertinggi</b> pada kartu di atas diambil dari <b>pembacaan mentah
                per slot waktu</b>, sedangkan kolom rata-rata pada tren harian dan rekap di bawah adalah
                <b>rata-rata per tanggal</b>. Karena itu keduanya <b>tidak dapat dicocokkan satu sama
                lain</b>, dan itu disengaja: tekanan yang anjlok pada satu slot tetap terlihat di kartu
                justru karena rata-rata harinya menyamarkannya.
                <small>Setiap metrik juga punya jumlah pembacaannya sendiri &mdash; sebuah baris dapat mengisi tekanan dan mengosongkan pH.</small>
            </span>
        </div>

        @if (! $hasData)
            {{-- Empty state (d): periode valid tetapi tidak memuat satu pun
                 pembacaan terisi. Seluruh metrik sudah tampil sebagai tanda
                 pisah (bukan nol) di atas; grafik, rekap per unit, dan rekap
                 harian TIDAK digambar sama sekali — grafik kosong akan
                 terbaca sebagai garis datar yang terukur. --}}
            <div class="md-empty" data-testid="empty-period-notice">
                <span class="md-empty__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19h16M7 16V9M12 16V5M17 16v-4"/></svg>
                </span>
                <p class="md-empty__title">Belum ada data pada periode ini</p>
                <p class="md-empty__text">
                    Tidak ada satu pun pembacaan Boiler Room tercatat pada rentang tanggal periode ini,
                    sehingga seluruh metrik ditampilkan sebagai tidak tersedia &mdash; bukan sebagai nol &mdash;
                    dan grafik tren, rekap per unit, serta rekap harian tidak digambar.
                </p>
            </div>
        @else

            {{-- ============ 7 & 8. Dua tren harian berdampingan ============ --}}
            <div class="md-row md-row--2">
                @foreach ([
                    ['column' => 'steam_pressure_avg', 'testid' => 'daily-trend-steam-pressure', 'judul' => 'Tren Harian Tekanan Uap', 'satuan' => 'bar', 'digits' => 1],
                    ['column' => 'steam_temp_avg', 'testid' => 'daily-trend-steam-temp', 'judul' => 'Tren Harian Suhu Uap', 'satuan' => '&deg;C', 'digits' => 1],
                ] as $tren)
                    @php
                        $nilaiTren = $kolomTren($tren['column']);
                        $minTren = $nilaiTren === [] ? null : min($nilaiTren);
                        $maksTren = $nilaiTren === [] ? null : max($nilaiTren);
                    @endphp
                    <section class="md-card" data-testid="{{ $tren['testid'] }}">
                        <header class="md-card__head">
                            <h3>{{ $tren['judul'] }}</h3>
                            <span class="md-card__hint">
                                Rata-rata per tanggal, dalam {!! $tren['satuan'] !!} &middot; {{ count($daily) }} tanggal berdata
                            </span>
                        </header>
                        <div class="md-trendchart">
                            @foreach ($daily as $row)
                                {{-- Tanpa modifier warna apa pun: tidak ada
                                     batang yang ditandai "rendah" atau
                                     "tinggi" di layar ini. --}}
                                <div class="md-trendchart__col" data-testid="{{ $tren['testid'] }}-col-{{ $row['date'] }}">
                                    <span class="md-trendchart__val">{{ $nilai($row[$tren['column']], $tren['digits']) }}</span>
                                    <div class="md-trendchart__bar" style="height: {{ $tinggiBatang($row[$tren['column']], $minTren, $maksTren) }}%"></div>
                                    <span class="md-trendchart__lbl">{{ $tglPendek($row['date']) }}</span>
                                </div>
                            @endforeach
                        </div>
                        <ul class="md-legend">
                            <li class="md-legend__item">
                                Terendah {{ $nilai($minTren, $tren['digits']) }} &middot; tertinggi {{ $nilai($maksTren, $tren['digits']) }}
                                <small>(dari rata-rata harian)</small>
                            </li>
                            <li class="md-legend__item">
                                <small>Sumbu tegak tidak dimulai dari nol &mdash; batang membandingkan tanggal, bukan besaran mutlak.</small>
                            </li>
                        </ul>
                    </section>
                @endforeach
            </div>

            {{-- ============ 9. Rekap per unit boiler ============ --}}
            <section class="md-card" data-testid="per-unit-recap">
                <header class="md-card__head">
                    <h3>Rekap per Unit Boiler</h3>
                    <span class="md-card__hint">
                        Angka periode di atas menggabungkan seluruh unit &mdash; tabel ini menunjukkan sebarannya
                    </span>
                </header>
                {{-- .md-card > .md-recap: tabel ini menggulir mendatar DI DALAM
                     kartunya sendiri, sehingga halaman tidak pernah punya
                     gulir horizontal. --}}
                <div class="md-recap">
                    <table class="md-table">
                        <thead>
                            <tr>
                                <th scope="col">Unit Boiler</th>
                                <th scope="col">Tekanan rata-rata (bar)</th>
                                <th scope="col">Suhu Uap rata-rata (&deg;C)</th>
                                <th scope="col">TDS rata-rata (ppm)</th>
                                <th scope="col">pH rata-rata</th>
                                <th scope="col">Blowdown</th>
                                <th scope="col">Sootblowing</th>
                                <th scope="col">Jumlah pembacaan</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Unit yang punya record tetapi NOL pembacaan
                                 terisi TETAP muncul di sini dengan jumlah
                                 pembacaan 0 dan seluruh rata-rata tidak
                                 tersedia. Menghilangkannya akan menyembunyikan
                                 unit yang justru tidak pernah dicatat. --}}
                            @foreach ($byUnit as $unit)
                                <tr data-testid="per-unit-row-{{ $unit['boiler_room_id'] }}">
                                    <td>{{ $unit['boiler_room_id'] }}</td>
                                    <td @class(['is-muted' => $unit['steam_pressure_avg'] === null])>{{ $nilai($unit['steam_pressure_avg'], 1) }}</td>
                                    <td @class(['is-muted' => $unit['steam_temp_avg'] === null])>{{ $nilai($unit['steam_temp_avg'], 1) }}</td>
                                    <td @class(['is-muted' => $unit['water_tds_avg'] === null])>{{ $nilai($unit['water_tds_avg'], 0) }}</td>
                                    <td @class(['is-muted' => $unit['water_ph_avg'] === null])>{{ $nilai($unit['water_ph_avg'], 1) }}</td>
                                    <td>{{ $cacah($unit['blowdown_executed']) }}</td>
                                    <td>{{ $cacah($unit['sootblowing_executed']) }}</td>
                                    <td>{{ $cacah($unit['reading_count']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr data-testid="per-unit-row-total">
                                <td>Gabungan {{ $cacah($coverage['boiler_unit_count']) }} unit</td>
                                <td>{{ $nilai($metrics['steam_pressure_bar']['avg'], 1) }}</td>
                                <td>{{ $nilai($metrics['steam_temp_c']['avg'], 1) }}</td>
                                <td>{{ $nilai($metrics['water_tds_ppm']['avg'], 0) }}</td>
                                <td>{{ $nilai($metrics['water_ph']['avg'], 1) }}</td>
                                <td>{{ $cacah($maintenance['blowdown']['executed']) }}</td>
                                <td>{{ $cacah($maintenance['sootblowing']['executed']) }}</td>
                                <td>{{ $cacah($total['reading_rows']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            {{-- ============ 10. Rekap harian (dapat dibuka/tutup) ============
                 Periode panjang menghasilkan puluhan baris, jadi tabelnya dapat
                 ditutup agar angka utama dan tren tetap terbaca tanpa gulir
                 panjang. Tombol, bukan <details> bawaan: keadaannya harus satu
                 sumber (properti Livewire) agar tabel benar-benar hilang dari
                 DOM saat ditutup, bukan sekadar tersembunyi. --}}
            <section class="md-card" data-testid="daily-recap-card">
                <header class="md-card__head">
                    <h3>Rekap Harian</h3>
                    <button type="button" class="md-btn"
                            wire:click="toggleRekapHarian" data-testid="daily-recap-toggle">
                        {{ $showRecap ? 'Tutup rekap harian' : 'Buka rekap harian' }}
                        <small>({{ count($daily) }} baris)</small>
                    </button>
                </header>
                @if ($showRecap)
                    <div class="md-recap" data-testid="daily-recap">
                        <table class="md-table">
                            <thead>
                                <tr>
                                    <th scope="col">Tanggal</th>
                                    <th scope="col">Slot Terisi</th>
                                    <th scope="col">Tekanan rata-rata (bar)</th>
                                    <th scope="col">Suhu Uap rata-rata (&deg;C)</th>
                                    <th scope="col">TDS rata-rata (ppm)</th>
                                    <th scope="col">pH rata-rata</th>
                                    <th scope="col">Gas Buang rata-rata (&deg;C)</th>
                                    <th scope="col">Blowdown</th>
                                    <th scope="col">Sootblowing</th>
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
                                        <td @class(['is-muted' => $row['steam_pressure_avg'] === null])>{{ $nilai($row['steam_pressure_avg'], 1) }}</td>
                                        <td @class(['is-muted' => $row['steam_temp_avg'] === null])>{{ $nilai($row['steam_temp_avg'], 1) }}</td>
                                        <td @class(['is-muted' => $row['water_tds_avg'] === null])>{{ $nilai($row['water_tds_avg'], 0) }}</td>
                                        <td @class(['is-muted' => $row['water_ph_avg'] === null])>{{ $nilai($row['water_ph_avg'], 1) }}</td>
                                        <td @class(['is-muted' => $row['exhaust_gas_temp_avg'] === null])>{{ $nilai($row['exhaust_gas_temp_avg'], 0) }}</td>
                                        <td>{{ $cacah($row['blowdown_executed']) }}</td>
                                        <td>{{ $cacah($row['sootblowing_executed']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr data-testid="daily-recap-row-total">
                                    <td>Total periode</td>
                                    <td>{{ $cacah($coverage['filled_slots']) }} <small>dari {{ $cacah($coverage['expected_slots']) }}</small></td>
                                    <td>{{ $nilai($metrics['steam_pressure_bar']['avg'], 1) }} <small>(rata-rata)</small></td>
                                    <td>{{ $nilai($metrics['steam_temp_c']['avg'], 1) }} <small>(rata-rata)</small></td>
                                    <td>{{ $nilai($metrics['water_tds_ppm']['avg'], 0) }} <small>(rata-rata)</small></td>
                                    <td>{{ $nilai($metrics['water_ph']['avg'], 1) }} <small>(rata-rata)</small></td>
                                    <td>{{ $nilai($metrics['exhaust_gas_temp_c']['avg'], 0) }} <small>(rata-rata)</small></td>
                                    <td>{{ $cacah($maintenance['blowdown']['executed']) }}</td>
                                    <td>{{ $cacah($maintenance['sootblowing']['executed']) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </section>
        @endif
    @endif
</div>
