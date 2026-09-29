{{--
    Laporan harian mill (tampilan DEMO, data DUMMY) — isi utama menu Dashboard.
    Mereproduksi contoh laporan harian CPO Mill Demo (2026-09-17): ringkasan visual
    di atas, tabel lengkap format laporan di bawah (bisa dibuka/tutup). Semua angka
    hard-coded; pemetaan ke tabel stasiun ada di docs/analisis-laporan-harian-mill.pdf.
--}}
@php
    $panels = [
        [
            'title' => 'Product Arrival',
            'head' => [
                [['Product', 1, 2], ['Actual', 3, 1], ['Budget', 2, 1]],
                [['Today', 1, 1], ['MTD', 1, 1], ['YTD', 1, 1], ['CM', 1, 1], ['YTM', 1, 1]],
            ],
            'rows' => [
                ['FFB Inti (MT)', '412.38', '6,184.20', '52,917.45', '12,500.00', '58,000.00'],
                ['FFB Plasma (MT)', '187.62', '2,806.15', '24,110.90', '5,500.00', '26,000.00'],
                ['Total FFB (MT)', '600.00', '8,990.35', '77,028.35', '18,000.00', '84,000.00'],
                ['CPO (MT)', '0.00', '45.20', '312.80', '-', '-'],
                ['Shell (MT)', '12.50', '98.40', '845.10', '-', '-'],
            ],
            'total' => [2],
        ],
        [
            'title' => 'Product Dispatched',
            'head' => [
                [['Product', 1, 1], ['As Fuel', 1, 1], ['Today', 1, 1], ['MTD', 1, 1], ['YTD', 1, 1]],
            ],
            'rows' => [
                ['CPO (MT)', '-', '120.45', '1,805.30', '16,240.10'],
                ['Kernel (MT)', '-', '28.10', '402.75', '3,618.40'],
                ['EB (MT)', '85.00', '0.00', '310.00', '2,940.00'],
                ['Acid Oil (MT)', '-', '0.00', '4.20', '38.90'],
                ['Fibre (MT)', '72.30', '0.00', '0.00', '0.00'],
                ['Shell (MT)', '18.40', '15.00', '210.60', '1,905.20'],
                ['Solid (MT)', '-', '6.20', '92.40', '830.75'],
            ],
        ],
        [
            'title' => 'Production',
            'head' => [
                [['Product', 1, 2], ['Produced (MT)', 3, 1], ['Yield (%)', 3, 1]],
                [['Today', 1, 1], ['MTD', 1, 1], ['YTD', 1, 1], ['Today', 1, 1], ['MTD', 1, 1], ['YTD', 1, 1]],
            ],
            'rows' => [
                ['CPO', '128.40', '1,933.55', '16,487.20', '21.40', '21.51', '21.40'],
                ['Kernel', '31.20', '466.80', '3,974.60', '5.20', '5.19', '5.16'],
                ['EB', '126.00', '1,852.30', '15,790.40', '21.00', '20.60', '20.50'],
                ['Fibre', '75.60', '1,114.80', '9,473.50', '12.60', '12.40', '12.30'],
                ['Shell', '33.00', '485.20', '4,159.30', '5.50', '5.40', '5.40'],
            ],
        ],
        [
            'title' => 'FFB Stock (MT)',
            'head' => [
                [['Location', 1, 1], ['Today', 1, 1], ['Yesterday', 1, 1]],
            ],
            'rows' => [
                ['Loading Ramp #1', '85.20', '92.40'],
                ['Loading Ramp #2', '64.80', '71.10'],
                ['Sterilized Cages', '42.00', '38.50'],
                ['Unsterilized Cages', '28.00', '31.50'],
                ['Total Unprocessed FFB', '220.00', '233.50'],
                ['FFB in Parking Area', '35.60', '48.20'],
                ['Total Available FFB', '255.60', '281.70'],
            ],
            'total' => [4, 6],
        ],
        [
            'title' => 'Inventory Level (MT)',
            'head' => [
                [['Product', 1, 1], ['Today', 1, 1], ['Yesterday', 1, 1], ['Adjustment', 1, 1]],
            ],
            'rows' => [
                ['CPO', '2,418.35', '2,410.40', '0.00'],
                ['Kernel', '612.80', '609.70', '-2.50'],
                ['EB', '1,540.00', '1,499.00', '0.00'],
                ['Acid Oil', '18.40', '18.40', '0.00'],
                ['Fibre', '96.20', '92.90', '0.00'],
                ['Shell', '410.60', '411.00', '0.00'],
                ['Solid', '58.30', '64.10', '0.40'],
            ],
        ],
        [
            'title' => 'Milling Summary',
            'head' => [
                [['Item', 1, 1], ['Today', 1, 1], ['MTD', 1, 1], ['YTD', 1, 1]],
            ],
            'groups' => [
                'Line 1' => [
                    ['Cages Tipped', '64', '958', '8,210'],
                    ['FFB Crushed (MT)', '316.80', '4,742.10', '40,639.50'],
                    ['Avg Cage Weight (MT)', '4.95', '4.95', '4.95'],
                    ['Throughput (MT/hr)', '28.80', '28.60', '28.40'],
                ],
                'Line 2' => [
                    ['Cages Tipped', '58', '872', '7,478'],
                    ['FFB Crushed (MT)', '283.20', '4,248.25', '36,388.85'],
                    ['Avg Cage Weight (MT)', '4.88', '4.87', '4.87'],
                    ['Throughput (MT/hr)', '27.00', '26.90', '26.70'],
                ],
            ],
            'rows' => [
                ['Total FFB Crushed (MT)', '600.00', '8,990.35', '77,028.35'],
            ],
            'total' => [0],
        ],
        [
            'title' => 'Hours per Line',
            'head' => [
                [['Item', 1, 2], ['Hours', 3, 1], ['Percentage (%)', 3, 1]],
                [['Tdy', 1, 1], ['MTD', 1, 1], ['YTD', 1, 1], ['Tdy', 1, 1], ['MTD', 1, 1], ['YTD', 1, 1]],
            ],
            'groups' => [
                'Line 1' => [
                    ['Available', '24.00', '360.00', '3,096.00', '100.00', '100.00', '100.00'],
                    ['CDT (Crop Down Time)', '8.50', '140.20', '1,212.40', '35.42', '38.94', '39.16'],
                    ['SDT (Scheduled Down Time)', '1.50', '22.00', '190.50', '6.25', '6.11', '6.15'],
                    ['EDT (Equipment Down Time)', '3.00', '32.00', '262.00', '12.50', '8.89', '8.46'],
                    ['Milling', '11.00', '165.80', '1,431.10', '45.83', '46.06', '46.22'],
                ],
                'Line 2' => [
                    ['Available', '24.00', '360.00', '3,096.00', '100.00', '100.00', '100.00'],
                    ['CDT (Crop Down Time)', '9.00', '146.00', '1,250.00', '37.50', '40.56', '40.37'],
                    ['SDT (Scheduled Down Time)', '1.50', '22.00', '190.50', '6.25', '6.11', '6.15'],
                    ['EDT (Equipment Down Time)', '3.00', '34.10', '292.30', '12.50', '9.47', '9.44'],
                    ['Milling', '10.50', '157.90', '1,363.20', '43.75', '43.86', '44.03'],
                ],
            ],
        ],
        [
            'title' => 'Product Quality',
            'head' => [
                [['Item', 1, 1], ['Tdy', 1, 1], ['MTD', 1, 1], ['YTD', 1, 1], ['Stock (MT)', 1, 1]],
            ],
            'groups' => [
                'CPO Tank 1' => [['FFA (%)', '3.42', '3.51', '3.60', '612.40'], ['Moisture (%)', '0.18', '0.19', '0.20', '']],
                'CPO Tank 2' => [['FFA (%)', '3.55', '3.48', '3.58', '598.10'], ['Moisture (%)', '0.19', '0.18', '0.19', '']],
                'CPO Tank 3' => [['FFA (%)', '3.61', '3.57', '3.62', '604.25'], ['Moisture (%)', '0.20', '0.19', '0.20', '']],
                'CPO Tank 4' => [['FFA (%)', '3.38', '3.45', '3.55', '603.60'], ['Moisture (%)', '0.17', '0.18', '0.19', '']],
                'Kernel On' => [['Admixture (%)', '6.20', '6.35', '6.40', ''], ['Moisture (%)', '7.10', '7.05', '7.00', '']],
                'PK Bulk Silo 1' => [['Admixture (%)', '6.05', '6.20', '6.30', '308.40'], ['Moisture (%)', '6.90', '6.95', '6.98', '']],
                'PK Bulk Silo 2' => [['Admixture (%)', '6.15', '6.25', '6.32', '304.40'], ['Moisture (%)', '7.00', '6.98', '7.01', '']],
            ],
        ],
        [
            'title' => 'Energy Used',
            'head' => [
                [['Item', 1, 1], ['Tdy', 1, 1], ['MTD', 1, 1], ['YTD', 1, 1]],
            ],
            'rows' => [
                ['Power Mill (kWh)', '11,400', '170,820', '1,463,540'],
                ['Power / t FFB (kWh/t)', '19.00', '19.00', '19.00'],
                ['Heat (MCal)', '312,000', '4,674,980', '40,054,740'],
                ['Heat / t FFB (kCal/t)', '520,000', '520,000', '520,000'],
            ],
        ],
        [
            'title' => 'Water Used (M3)',
            'head' => [
                [['Item', 1, 1], ['Tdy', 1, 1], ['MTD', 1, 1], ['YTD', 1, 1]],
            ],
            'rows' => [
                ['Process', '480.00', '7,192.30', '61,622.70'],
                ['Boiler', '360.00', '5,394.20', '46,217.00'],
                ['For Mill Operation', '840.00', '12,586.50', '107,839.70'],
                ['Usage (M3/Ton FFB)', '1.40', '1.40', '1.40'],
            ],
            'total' => [2],
        ],
        [
            'title' => 'Today Reliability',
            'head' => [
                [['Line', 1, 2], ['EE (%)', 1, 2], ['BE (%)', 2, 1], ['Days', 2, 1]],
                [['MTD', 1, 1], ['YTD', 1, 1], ['MTD', 1, 1], ['Total', 1, 1]],
            ],
            'rows' => [
                ['Line 1', '78.57', '83.82', '84.52', '15', '15'],
                ['Line 2', '77.78', '82.24', '82.35', '15', '15'],
            ],
        ],
        [
            'title' => 'OER (%)',
            'head' => [
                [['Item', 1, 1], ['Today', 1, 1], ['MTD', 1, 1], ['YTD', 1, 1]],
            ],
            'rows' => [
                ['Theoretical OER', '22.10', '22.05', '21.98'],
                ['DCR OER', '21.40', '21.51', '21.40'],
                ['Difference', '-0.70', '-0.54', '-0.58'],
            ],
            'total' => [2],
        ],
    ];

    $explanations = [
        'Line 1' => 'EDT 3 jam — penggantian bearing screw press No. 2 (08:15–11:15). Throughput kembali normal setelah perbaikan.',
        'Line 2' => 'CDT 9 jam — menunggu buah masuk dari afdeling Plasma akibat jalan rusak. Tidak ada kerusakan mesin.',
    ];

@endphp
@php
    // Ringkasan visual — diturunkan dari angka dummy yang sama dengan tabel di bawah.
    /*
       Setiap kartu memakai barometer yang sama: satu bar progres terhadap acuan
       tetap (budget / kapasitas / nilai teoritis) DAN satu badge deviasi terhadap
       pembanding waktu. Keduanya wajib ada di setiap kartu — lihat
       DashboardHomeTest 'every KPI card carries both a progress bar and a deviation'.

       Angka tetap dummy, tetapi diturunkan dari panel tabel di bawah agar konsisten:
         FFB Diterima  MTD 8,990.35 / budget 18,000                       = 49.9%
         FFB Diolah    600.00 / (21.50 jam × 30 MT/jam = 645.00)           = 93.0%
         CPO           OER 21.40 / OER teoritis 22.10                     = 96.8%
         Kernel        KER 5.20 / KER teoritis 5.50                       = 94.5%
         Stok CPO      2,418.35 / kapasitas 4 tangki 3,000                = 80.6%
         Jam Olah      21.50 / tersedia 2 line × 24.00 = 48.00            = 44.8%
       Rata-rata harian MTD memakai 15 hari (panel 'Today Reliability').
       EE tidak lagi ditaruh di kartu Jam Olah — sudah tampil per line di blok
       'Distribusi Jam per Line'.
    */
    $kpis = [
        ['label' => 'FFB Diterima', 'value' => '600.00', 'unit' => 'MT', 'meta' => 'MTD 8,990.35 MT', 'icon' => 'truck',
            'progress' => 49.9, 'progressLabel' => '49.9% dari budget bulan ini (18,000 MT)',
            'trend' => ['+0.1%', 'up'], 'trendLabel' => 'vs rata-rata harian MTD (599.36 MT)'],
        ['label' => 'FFB Diolah', 'value' => '600.00', 'unit' => 'MT', 'meta' => 'Rata-rata 27.90 MT/jam', 'icon' => 'factory',
            'progress' => 93.0, 'progressLabel' => '93.0% dari kapasitas 645.00 MT saat jam olah',
            'trend' => ['+2.1%', 'up'], 'trendLabel' => 'vs kemarin (587.66 MT)'],
        ['label' => 'CPO Diproduksi', 'value' => '128.40', 'unit' => 'MT', 'meta' => 'OER 21.40%', 'icon' => 'drop',
            'progress' => 96.8, 'progressLabel' => '96.8% dari OER teoritis 22.10%',
            'trend' => ['-0.11', 'down'], 'trendLabel' => 'poin OER vs MTD (21.51%)'],
        ['label' => 'Kernel Diproduksi', 'value' => '31.20', 'unit' => 'MT', 'meta' => 'KER 5.20%', 'icon' => 'seed',
            'progress' => 94.5, 'progressLabel' => '94.5% dari KER teoritis 5.50%',
            'trend' => ['+0.01', 'up'], 'trendLabel' => 'poin KER vs MTD (5.19%)'],
        ['label' => 'Stok CPO', 'value' => '2,418.35', 'unit' => 'MT', 'meta' => 'Kemarin 2,410.40 MT', 'icon' => 'tank',
            'progress' => 80.6, 'progressLabel' => '80.6% dari kapasitas 4 tangki (3,000 MT)',
            'trend' => ['+7.95 MT', 'up'], 'trendLabel' => 'vs kemarin'],
        ['label' => 'Jam Olah', 'value' => '21.50', 'unit' => 'jam', 'meta' => 'Line 1 11.00 · Line 2 10.50', 'icon' => 'clock',
            'progress' => 44.8, 'progressLabel' => '44.8% dari 48.00 jam tersedia (2 line)',
            'trend' => ['-0.4%', 'down'], 'trendLabel' => 'vs rata-rata harian MTD (21.58 jam)'],
    ];

    $arrivalBudget = [
        ['label' => 'FFB Inti', 'actual' => 6184.20, 'budget' => 12500],
        ['label' => 'FFB Plasma', 'actual' => 2806.15, 'budget' => 5500],
        ['label' => 'Total FFB', 'actual' => 8990.35, 'budget' => 18000],
    ];

    $ffbStock = [
        ['label' => 'Loading Ramp #1', 'value' => 85.20, 'color' => '#249360'],
        ['label' => 'Loading Ramp #2', 'value' => 64.80, 'color' => '#5bb88a'],
        ['label' => 'Sterilized Cages', 'value' => 42.00, 'color' => '#f59e0b'],
        ['label' => 'Unsterilized Cages', 'value' => 28.00, 'color' => '#fbbf24'],
        ['label' => 'Parking Area', 'value' => 35.60, 'color' => '#64748b'],
    ];
    $ffbStockTotal = array_sum(array_column($ffbStock, 'value'));
    $stops = []; $acc = 0;
    foreach ($ffbStock as $s) {
        $from = $acc; $acc += $s['value'] / $ffbStockTotal * 100;
        $stops[] = "{$s['color']} {$from}% {$acc}%";
    }
    $donut = 'conic-gradient('.implode(', ', $stops).')';

    $hourSegments = ['Milling' => '#249360', 'CDT' => '#f59e0b', 'SDT' => '#60a5fa', 'EDT' => '#ef4444'];
    $lines = [
        ['name' => 'Line 1', 'cages' => 64, 'crushed' => 316.80, 'avgCage' => 4.95, 'throughput' => 28.80, 'ee' => 78.57,
            'hours' => ['Milling' => 11.00, 'CDT' => 8.50, 'SDT' => 1.50, 'EDT' => 3.00]],
        ['name' => 'Line 2', 'cages' => 58, 'crushed' => 283.20, 'avgCage' => 4.88, 'throughput' => 27.00, 'ee' => 77.78,
            'hours' => ['Milling' => 10.50, 'CDT' => 9.00, 'SDT' => 1.50, 'EDT' => 3.00]],
    ];

    $tanks = [
        ['name' => 'CPO Tank 1', 'stock' => 612.40, 'cap' => 1000, 'a' => ['FFA', 3.42, 5.00], 'b' => ['Moist', 0.18, 0.20]],
        ['name' => 'CPO Tank 2', 'stock' => 598.10, 'cap' => 1000, 'a' => ['FFA', 3.55, 5.00], 'b' => ['Moist', 0.19, 0.20]],
        ['name' => 'CPO Tank 3', 'stock' => 604.25, 'cap' => 1000, 'a' => ['FFA', 3.61, 5.00], 'b' => ['Moist', 0.20, 0.20]],
        ['name' => 'CPO Tank 4', 'stock' => 603.60, 'cap' => 1000, 'a' => ['FFA', 3.38, 5.00], 'b' => ['Moist', 0.17, 0.20]],
        ['name' => 'PK Silo 1', 'stock' => 308.40, 'cap' => 500, 'a' => ['Admix', 6.05, 6.10], 'b' => ['Moist', 6.90, 7.00]],
        ['name' => 'PK Silo 2', 'stock' => 304.40, 'cap' => 500, 'a' => ['Admix', 6.15, 6.10], 'b' => ['Moist', 7.00, 7.00]],
    ];

    $utilities = [
        ['label' => 'Listrik Mill', 'value' => '11,400', 'unit' => 'kWh', 'ratio' => '19.00 kWh/t FFB', 'icon' => 'bolt'],
        ['label' => 'Panas', 'value' => '312,000', 'unit' => 'MCal', 'ratio' => '520,000 kCal/t FFB', 'icon' => 'flame'],
        ['label' => 'Air Proses', 'value' => '480', 'unit' => 'm³', 'ratio' => 'Boiler 360 m³', 'icon' => 'water'],
        ['label' => 'Pemakaian Air', 'value' => '1.40', 'unit' => 'm³/t', 'ratio' => 'Total 840 m³', 'icon' => 'water'],
    ];

    $oer = ['theoretical' => 22.10, 'actual' => 21.40, 'mtd' => 21.51, 'ytd' => 21.40];

    $icons = [
        'truck' => '<path d="M1 6h11v9H1z"/><path d="M12 9h4l3 3v3h-7z"/><circle cx="5" cy="16" r="1.6"/><circle cx="15" cy="16" r="1.6"/>',
        'factory' => '<path d="M2 18V8l5 3V8l5 3V4h6v14z"/><line x1="2" y1="18" x2="18" y2="18"/>',
        'drop' => '<path d="M10 2s6 6.5 6 10.5A6 6 0 0 1 4 12.5C4 8.5 10 2 10 2z"/>',
        'seed' => '<ellipse cx="10" cy="10" rx="5" ry="7"/><path d="M10 3v14"/>',
        'tank' => '<ellipse cx="10" cy="4.5" rx="6" ry="2.5"/><path d="M4 4.5v11c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5v-11"/><path d="M4 10c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5"/>',
        'clock' => '<circle cx="10" cy="10" r="8"/><polyline points="10 5 10 10 13 12"/>',
        'bolt' => '<polygon points="11 2 4 11 10 11 9 18 16 9 10 9"/>',
        'flame' => '<path d="M10 18a5 5 0 0 1-5-5c0-3 2-4.5 3-7 1.5 1.5 2 3 2 4 1-1 1.5-2.5 1.5-4 2.5 2 3.5 4.5 3.5 7a5 5 0 0 1-5 5z"/>',
        'water' => '<path d="M3 13c1.2 0 1.8-1 3.5-1s2.3 1 3.5 1 1.8-1 3.5-1 2.3 1 3.5 1"/><path d="M3 17c1.2 0 1.8-1 3.5-1s2.3 1 3.5 1 1.8-1 3.5-1 2.3 1 3.5 1"/><path d="M10 2s3 3.2 3 5.2a3 3 0 0 1-6 0C7 5.2 10 2 10 2z"/>',
    ];
@endphp

<section class="md" data-testid="daily-mill-report">
    <header class="md-hero">
        <div class="md-hero__text">
            <p class="md-hero__eyebrow">CPO Mill Demo · Laporan Harian</p>
            <h2 class="md-hero__title">Dashboard Operasional Mill</h2>
            <p class="md-hero__subtitle">Produksi, stok, kualitas, dan utilitas dalam satu layar</p>
        </div>
        <div class="md-hero__meta">
            <span class="md-chip md-chip--date">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="14" height="13" rx="2"/><line x1="3" y1="8" x2="17" y2="8"/><line x1="7" y1="2" x2="7" y2="5"/><line x1="13" y1="2" x2="13" y2="5"/></svg>
                {{ now()->subDay()->translatedFormat('l, d F Y') }}
            </span>
            <span class="md-chip md-chip--dummy" title="Semua angka pada bagian ini fiktif dan belum terhubung ke data stasiun">Data dummy</span>
        </div>
    </header>

    {{-- KPI utama --}}
    <div class="md-kpis">
        @foreach ($kpis as $kpi)
            <article class="md-kpi">
                <div class="md-kpi__top">
                    <span class="md-kpi__label">{{ $kpi['label'] }}</span>
                    <span class="md-kpi__icon"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$kpi['icon']] !!}</svg></span>
                </div>
                <p class="md-kpi__value">{{ $kpi['value'] }} <span>{{ $kpi['unit'] }}</span></p>
                <p class="md-kpi__meta">{{ $kpi['meta'] }}</p>
                {{-- Barometer seragam: bar progres + badge deviasi, keduanya di setiap kartu. --}}
                <div class="md-bar"><span style="width: {{ number_format($kpi['progress'], 1) }}%"></span></div>
                <p class="md-kpi__foot">{{ $kpi['progressLabel'] }}</p>
                <p class="md-kpi__foot md-kpi__foot--trend"><span class="md-trend md-trend--{{ $kpi['trend'][1] }}">{{ $kpi['trend'][0] }}</span> {{ $kpi['trendLabel'] }}</p>
            </article>
        @endforeach
    </div>

    <div class="md-row md-row--2-1">
        {{-- Penerimaan vs budget --}}
        <article class="md-card">
            <header class="md-card__head">
                <h3>Penerimaan FFB vs Budget</h3>
                <span class="md-card__hint">MTD terhadap budget bulan berjalan</span>
            </header>
            <div class="md-budget">
                @foreach ($arrivalBudget as $row)
                    @php $pct = round($row['actual'] / $row['budget'] * 100, 1); @endphp
                    <div class="md-budget__row{{ $loop->last ? ' md-budget__row--total' : '' }}">
                        <div class="md-budget__label">
                            <span>{{ $row['label'] }}</span>
                            <span class="md-budget__nums"><strong>{{ number_format($row['actual'], 2) }}</strong> / {{ number_format($row['budget'], 0) }} MT</span>
                        </div>
                        <div class="md-bar md-bar--lg"><span style="width: {{ min($pct, 100) }}%"></span><em style="left: 50%" title="Pro-rata hari ke-15"></em></div>
                        <span class="md-budget__pct">{{ $pct }}%</span>
                    </div>
                @endforeach
                <p class="md-note"><span class="md-note__tick"></span> Garis tegak = target pro-rata (hari ke-15 dari 30)</p>
            </div>
        </article>

        {{-- Stok FFB --}}
        <article class="md-card">
            <header class="md-card__head">
                <h3>Stok FFB</h3>
                <span class="md-card__hint">Posisi hari ini</span>
            </header>
            <div class="md-donut-wrap">
                <div class="md-donut" style="background: {{ $donut }}">
                    <div class="md-donut__hole">
                        <strong>{{ number_format($ffbStockTotal, 1) }}</strong>
                        <span>MT tersedia</span>
                    </div>
                </div>
                <ul class="md-legend">
                    @foreach ($ffbStock as $s)
                        <li><i style="background: {{ $s['color'] }}"></i><span>{{ $s['label'] }}</span><b>{{ number_format($s['value'], 1) }}</b></li>
                    @endforeach
                </ul>
            </div>
        </article>
    </div>

    <div class="md-row md-row--2">
        {{-- Performa per line --}}
        <article class="md-card">
            <header class="md-card__head">
                <h3>Milling per Line</h3>
                <span class="md-card__hint">Hari ini</span>
            </header>
            <div class="md-lines">
                @foreach ($lines as $line)
                    <div class="md-line">
                        <div class="md-line__head">
                            <strong>{{ $line['name'] }}</strong>
                            <span class="md-pill">{{ number_format($line['throughput'], 2) }} MT/jam</span>
                        </div>
                        <dl class="md-line__stats">
                            <div><dt>Cages Tipped</dt><dd>{{ $line['cages'] }}</dd></div>
                            <div><dt>FFB Crushed</dt><dd>{{ number_format($line['crushed'], 2) }} <small>MT</small></dd></div>
                            <div><dt>Avg Cage</dt><dd>{{ number_format($line['avgCage'], 2) }} <small>MT</small></dd></div>
                        </dl>
                        <div class="md-bar"><span style="width: {{ $line['crushed'] / 600 * 100 }}%"></span></div>
                        <p class="md-kpi__foot">{{ round($line['crushed'] / 600 * 100, 1) }}% dari total FFB diolah</p>
                    </div>
                @endforeach
            </div>
        </article>

        {{-- Distribusi jam --}}
        <article class="md-card">
            <header class="md-card__head">
                <h3>Distribusi Jam per Line</h3>
                <span class="md-card__hint">24 jam tersedia</span>
            </header>
            <div class="md-hours">
                @foreach ($lines as $line)
                    <div class="md-hours__row">
                        <div class="md-hours__label"><strong>{{ $line['name'] }}</strong><span>EE {{ $line['ee'] }}%</span></div>
                        <div class="md-stack">
                            @foreach ($line['hours'] as $key => $h)
                                <span style="width: {{ $h / 24 * 100 }}%; background: {{ $hourSegments[$key] }}" title="{{ $key }}: {{ number_format($h, 2) }} jam">{{ $h >= 2 ? number_format($h, 1) : '' }}</span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                <ul class="md-legend md-legend--inline">
                    <li><i style="background: #249360"></i><span>Milling</span></li>
                    <li><i style="background: #f59e0b"></i><span>CDT (Crop Down Time)</span></li>
                    <li><i style="background: #60a5fa"></i><span>SDT (Scheduled)</span></li>
                    <li><i style="background: #ef4444"></i><span>EDT (Equipment)</span></li>
                </ul>
            </div>
        </article>
    </div>

    {{-- Kualitas & stok tangki --}}
    <article class="md-card">
        <header class="md-card__head">
            <h3>Kualitas &amp; Stok Tangki</h3>
            <span class="md-card__hint">Nilai hari ini terhadap batas mutu</span>
        </header>
        <div class="md-tanks">
            @foreach ($tanks as $tank)
                @php
                    $fill = round($tank['stock'] / $tank['cap'] * 100);
                    $warn = $tank['a'][1] > $tank['a'][2] || $tank['b'][1] > $tank['b'][2];
                @endphp
                <div class="md-tank{{ $warn ? ' md-tank--warn' : '' }}">
                    <div class="md-tank__gauge"><span style="height: {{ $fill }}%"></span><b>{{ $fill }}%</b></div>
                    <div class="md-tank__body">
                        <div class="md-tank__head">
                            <strong>{{ $tank['name'] }}</strong>
                            <span class="md-status{{ $warn ? ' md-status--warn' : '' }}">{{ $warn ? 'Perhatian' : 'Normal' }}</span>
                        </div>
                        <p class="md-tank__stock">{{ number_format($tank['stock'], 2) }} <small>/ {{ number_format($tank['cap']) }} MT</small></p>
                        <div class="md-tank__params">
                            @foreach ([$tank['a'], $tank['b']] as [$param, $val, $limit])
                                <span @class(['md-param', 'md-param--warn' => $val > $limit])>{{ $param }} <b>{{ number_format($val, 2) }}%</b> <small>≤ {{ number_format($limit, 2) }}</small></span>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </article>

    <div class="md-row md-row--2-1">
        {{-- Utilitas --}}
        <article class="md-card">
            <header class="md-card__head">
                <h3>Energi &amp; Air</h3>
                <span class="md-card__hint">Hari ini</span>
            </header>
            <div class="md-utils">
                @foreach ($utilities as $u)
                    <div class="md-util">
                        <span class="md-util__icon"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$u['icon']] !!}</svg></span>
                        <div>
                            <p class="md-util__label">{{ $u['label'] }}</p>
                            <p class="md-util__value">{{ $u['value'] }} <small>{{ $u['unit'] }}</small></p>
                            <p class="md-util__ratio">{{ $u['ratio'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </article>

        {{-- OER --}}
        <article class="md-card">
            <header class="md-card__head">
                <h3>Oil Extraction Rate</h3>
                <span class="md-card__hint">DCR vs teoritis</span>
            </header>
            <div class="md-oer">
                <p class="md-oer__big">{{ number_format($oer['actual'], 2) }}<span>%</span></p>
                <p class="md-oer__delta"><span class="md-trend md-trend--down">{{ number_format($oer['actual'] - $oer['theoretical'], 2) }}</span> dari teoritis {{ number_format($oer['theoretical'], 2) }}%</p>
                <div class="md-oer__compare">
                    @foreach (['Today' => $oer['actual'], 'MTD' => $oer['mtd'], 'YTD' => $oer['ytd']] as $label => $val)
                        <div class="md-oer__item">
                            <span>{{ $label }}</span>
                            <div class="md-bar"><span style="width: {{ $val / 25 * 100 }}%"></span><em style="left: {{ $oer['theoretical'] / 25 * 100 }}%"></em></div>
                            <b>{{ number_format($val, 2) }}%</b>
                        </div>
                    @endforeach
                </div>
            </div>
        </article>
    </div>

    {{-- Catatan --}}
    <article class="md-card">
        <header class="md-card__head"><h3>Catatan Operasional</h3></header>
        <div class="md-notes">
            @foreach ($explanations as $line => $text)
                <div class="md-notes__item">
                    <span class="md-pill">{{ $line }}</span>
                    <p>{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </article>

    {{-- Tabel lengkap (format laporan harian) --}}
    <details class="md-details">
        <summary>
            <span>Tabel lengkap laporan harian</span>
            <small>{{ count($panels) }} tabel · Today / MTD / YTD</small>
        </summary>
        <div class="dmr__grid">
            @foreach ($panels as $panel)
                @php $colCount = collect($panel['head'][0])->sum(fn ($h) => $h[1]); @endphp
                <section class="dmr-panel{{ $colCount > 5 ? ' dmr-panel--wide' : '' }}">
                    <h4 class="dmr-panel__title">{{ $panel['title'] }}</h4>
                    <div class="dmr-panel__scroll">
                        <table class="dmr-table">
                            <thead>
                                @foreach ($panel['head'] as $headRow)
                                    <tr>
                                        @foreach ($headRow as [$label, $colspan, $rowspan])
                                            <th colspan="{{ $colspan }}" rowspan="{{ $rowspan }}">{{ $label }}</th>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </thead>
                            <tbody>
                                @foreach ($panel['groups'] ?? [] as $groupName => $groupRows)
                                    <tr class="dmr-table__group"><td colspan="{{ $colCount }}">{{ $groupName }}</td></tr>
                                    @foreach ($groupRows as $row)
                                        <tr>
                                            @foreach ($row as $cell)
                                                <td @class(['dmr-table__neg' => str_starts_with($cell, '-') && $cell !== '-'])>{{ $cell }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                @endforeach
                                @foreach ($panel['rows'] ?? [] as $i => $row)
                                    <tr @class(['dmr-table__total' => in_array($i, $panel['total'] ?? [], true)])>
                                        @foreach ($row as $cell)
                                            <td @class(['dmr-table__neg' => str_starts_with($cell, '-') && $cell !== '-'])>{{ $cell }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </div>
    </details>

    @include('dashboard.partials.report-styles')
</section>
