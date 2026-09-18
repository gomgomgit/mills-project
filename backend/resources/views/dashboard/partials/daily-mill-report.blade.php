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
    $kpis = [
        ['label' => 'FFB Diterima', 'value' => '600.00', 'unit' => 'MT', 'meta' => 'MTD 8,990.35 MT', 'progress' => 49.9, 'progressLabel' => '49.9% dari budget bulan ini', 'icon' => 'truck'],
        ['label' => 'FFB Diolah', 'value' => '600.00', 'unit' => 'MT', 'meta' => 'Rata-rata 27.90 MT/jam', 'trend' => ['+2.1%', 'up'], 'trendLabel' => 'vs kemarin', 'icon' => 'factory'],
        ['label' => 'CPO Diproduksi', 'value' => '128.40', 'unit' => 'MT', 'meta' => 'OER 21.40%', 'trend' => ['-0.70', 'down'], 'trendLabel' => 'vs OER teoritis', 'icon' => 'drop'],
        ['label' => 'Kernel Diproduksi', 'value' => '31.20', 'unit' => 'MT', 'meta' => 'KER 5.20%', 'trend' => ['+0.01', 'up'], 'trendLabel' => 'vs MTD', 'icon' => 'seed'],
        ['label' => 'Stok CPO', 'value' => '2,418.35', 'unit' => 'MT', 'meta' => 'Kemarin 2,410.40 MT', 'trend' => ['+7.95', 'up'], 'trendLabel' => 'MT', 'icon' => 'tank'],
        ['label' => 'Jam Olah', 'value' => '21.50', 'unit' => 'jam', 'meta' => 'Line 1 11.00 · Line 2 10.50', 'trend' => ['EE 78.2%', 'flat'], 'trendLabel' => 'MTD', 'icon' => 'clock'],
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
                @isset($kpi['progress'])
                    <div class="md-bar"><span style="width: {{ $kpi['progress'] }}%"></span></div>
                    <p class="md-kpi__foot">{{ $kpi['progressLabel'] }}</p>
                @else
                    <p class="md-kpi__foot"><span class="md-trend md-trend--{{ $kpi['trend'][1] }}">{{ $kpi['trend'][0] }}</span> {{ $kpi['trendLabel'] }}</p>
                @endisset
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

    <style>
        .md { --md-brand: #249360; --md-brand-dark: #1a6f48; --md-brand-soft: #e8f5ee; --md-ink: #0f172a; --md-muted: #64748b; --md-line: #e2e8f0; --md-card: #ffffff;
              display: flex; flex-direction: column; gap: 20px; margin-bottom: 32px; color: var(--md-ink); }
        .md small { font-weight: 500; color: var(--md-muted); }

        .md-hero { position: relative; overflow: hidden; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 16px;
                   padding: 24px 28px; border-radius: 16px; color: #fff;
                   background: radial-gradient(120% 140% at 100% 0%, #3fb57c 0%, rgba(63,181,124,0) 55%), linear-gradient(135deg, #1a6f48 0%, #249360 60%, #2ea56d 100%); }
        .md-hero::after { content: ''; position: absolute; right: -60px; bottom: -80px; width: 240px; height: 240px; border-radius: 50%; border: 36px solid rgba(255,255,255,.07); }
        .md-hero__eyebrow { margin: 0 0 6px; font-size: 12px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; opacity: .85; }
        .md-hero__title { margin: 0; font-size: 26px; font-weight: 700; letter-spacing: -.01em; }
        .md-hero__subtitle { margin: 6px 0 0; font-size: 14px; opacity: .85; }
        .md-hero__meta { position: relative; z-index: 1; display: flex; flex-wrap: wrap; gap: 8px; }
        .md-chip { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 999px; font-size: 13px; font-weight: 600; }
        .md-chip svg { width: 15px; height: 15px; }
        .md-chip--date { background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.28); }
        .md-chip--dummy { background: #fef3c7; color: #92400e; text-transform: uppercase; letter-spacing: .05em; font-size: 11px; }

        .md-kpis { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 14px; }
        .md-kpi { background: var(--md-card); border: 1px solid var(--md-line); border-radius: 14px; padding: 16px; box-shadow: 0 1px 2px rgba(15,23,42,.04); transition: box-shadow .15s, transform .15s; min-width: 0; }
        .md-kpi:hover { box-shadow: 0 8px 20px rgba(15,23,42,.08); transform: translateY(-1px); }
        .md-kpi__top { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .md-kpi__label { font-size: 13px; font-weight: 600; color: var(--md-muted); }
        .md-kpi__icon { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 10px; background: var(--md-brand-soft); color: var(--md-brand); flex-shrink: 0; }
        .md-kpi__icon svg { width: 18px; height: 18px; }
        .md-kpi__value { margin: 10px 0 2px; font-size: 24px; font-weight: 700; letter-spacing: -.02em; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .md-kpi__value span { font-size: 13px; font-weight: 600; color: var(--md-muted); }
        .md-kpi__meta { margin: 0 0 10px; font-size: 12px; color: var(--md-muted); }
        .md-kpi__foot { margin: 6px 0 0; font-size: 12px; color: var(--md-muted); }

        .md-trend { display: inline-flex; align-items: center; gap: 2px; padding: 1px 7px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .md-trend--up { background: #dcfce7; color: #15803d; }
        .md-trend--up::before { content: '▲'; font-size: 8px; }
        .md-trend--down { background: #fee2e2; color: #b91c1c; }
        .md-trend--down::before { content: '▼'; font-size: 8px; }
        .md-trend--flat { background: #e0f2fe; color: #0369a1; }

        .md-bar { position: relative; height: 8px; border-radius: 999px; background: #edf2f0; }
        .md-bar > span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #2ea56d, #249360); }
        .md-bar > em { position: absolute; top: -4px; bottom: -4px; width: 2px; background: #0f172a; opacity: .45; border-radius: 2px; }
        .md-bar--lg { height: 12px; }

        .md-row { display: grid; gap: 20px; align-items: stretch; }
        .md-row--2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .md-row--2-1 { grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); }
        .md-card { background: var(--md-card); border: 1px solid var(--md-line); border-radius: 16px; padding: 20px; box-shadow: 0 1px 2px rgba(15,23,42,.04); min-width: 0; }
        .md-card__head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 4px 12px; margin-bottom: 16px; }
        .md-card__head h3 { margin: 0; font-size: 16px; font-weight: 700; }
        .md-card__hint { font-size: 12px; color: var(--md-muted); }

        .md-budget { display: flex; flex-direction: column; gap: 16px; }
        .md-budget__row { display: grid; grid-template-columns: 1fr auto; grid-template-areas: 'label pct' 'bar bar'; gap: 6px 12px; }
        .md-budget__label { grid-area: label; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 4px 12px; font-size: 14px; font-weight: 600; }
        .md-budget__nums { font-size: 13px; font-weight: 500; color: var(--md-muted); font-variant-numeric: tabular-nums; }
        .md-budget__nums strong { color: var(--md-ink); }
        .md-budget__row .md-bar { grid-area: bar; }
        .md-budget__pct { grid-area: pct; font-size: 14px; font-weight: 700; color: var(--md-brand-dark); font-variant-numeric: tabular-nums; }
        .md-budget__row--total { padding-top: 14px; border-top: 1px dashed var(--md-line); }
        .md-budget__row--total .md-bar > span { background: linear-gradient(90deg, #1a6f48, #0f4f33); }
        .md-note { margin: 0; font-size: 12px; color: var(--md-muted); display: flex; align-items: center; gap: 6px; }
        .md-note__tick { display: inline-block; width: 2px; height: 12px; background: #0f172a; opacity: .45; }

        .md-donut-wrap { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; justify-content: center; }
        .md-donut { width: 160px; height: 160px; border-radius: 50%; display: grid; place-items: center; flex-shrink: 0; }
        .md-donut__hole { width: 104px; height: 104px; border-radius: 50%; background: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .md-donut__hole strong { font-size: 22px; font-variant-numeric: tabular-nums; }
        .md-donut__hole span { font-size: 11px; color: var(--md-muted); }
        .md-legend { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 170px; }
        .md-legend li { display: flex; align-items: center; gap: 8px; font-size: 13px; }
        .md-legend i { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
        .md-legend span { flex: 1; color: #334155; }
        .md-legend b { font-variant-numeric: tabular-nums; }
        .md-legend--inline { flex-direction: row; flex-wrap: wrap; gap: 8px 16px; margin-top: 4px; }
        .md-legend--inline span { flex: none; font-size: 12px; color: var(--md-muted); }

        .md-lines { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .md-line { border: 1px solid var(--md-line); border-radius: 12px; padding: 14px; background: linear-gradient(180deg, #fbfdfc, #fff); }
        .md-line__head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; gap: 8px; }
        .md-pill { display: inline-block; padding: 3px 10px; border-radius: 999px; background: var(--md-brand-soft); color: var(--md-brand-dark); font-size: 12px; font-weight: 700; white-space: nowrap; }
        .md-line__stats { margin: 0 0 12px; display: grid; gap: 8px; }
        .md-line__stats div { display: flex; justify-content: space-between; gap: 8px; }
        .md-line__stats dt { font-size: 13px; color: var(--md-muted); }
        .md-line__stats dd { margin: 0; font-size: 14px; font-weight: 700; font-variant-numeric: tabular-nums; }

        .md-hours { display: flex; flex-direction: column; gap: 18px; }
        .md-hours__label { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 14px; }
        .md-hours__label span { font-size: 12px; color: var(--md-muted); font-weight: 600; }
        .md-stack { display: flex; height: 30px; border-radius: 8px; overflow: hidden; background: #edf2f0; }
        .md-stack span { display: grid; place-items: center; color: #fff; font-size: 12px; font-weight: 700; font-variant-numeric: tabular-nums; }

        .md-tanks { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
        .md-tank { display: flex; gap: 14px; padding: 14px; border: 1px solid var(--md-line); border-radius: 12px; min-width: 0; }
        .md-tank--warn { border-color: #fcd34d; background: #fffdf5; }
        .md-tank__gauge { position: relative; width: 34px; height: 76px; border-radius: 8px; background: #edf2f0; overflow: hidden; flex-shrink: 0; }
        .md-tank__gauge span { position: absolute; left: 0; right: 0; bottom: 0; background: linear-gradient(180deg, #3fb57c, #1a6f48); }
        .md-tank--warn .md-tank__gauge span { background: linear-gradient(180deg, #fbbf24, #d97706); }
        .md-tank__gauge b { position: absolute; inset: 0; display: grid; place-items: center; font-size: 10px; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,.35); }
        .md-tank__body { flex: 1; min-width: 0; }
        .md-tank__head { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .md-tank__head strong { font-size: 14px; }
        .md-status { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: #dcfce7; color: #15803d; }
        .md-status--warn { background: #fef3c7; color: #b45309; }
        .md-tank__stock { margin: 4px 0 8px; font-size: 18px; font-weight: 700; font-variant-numeric: tabular-nums; }
        .md-tank__params { display: flex; flex-wrap: wrap; gap: 6px; }
        .md-param { font-size: 12px; padding: 3px 8px; border-radius: 8px; background: #f1f5f9; color: #334155; white-space: nowrap; }
        .md-param b { font-variant-numeric: tabular-nums; }
        .md-param--warn { background: #fef3c7; color: #92400e; }

        .md-utils { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .md-util { display: flex; gap: 12px; align-items: flex-start; padding: 14px; border-radius: 12px; background: #f8faf9; }
        .md-util__icon { display: grid; place-items: center; width: 38px; height: 38px; border-radius: 10px; background: #fff; color: var(--md-brand); box-shadow: 0 1px 2px rgba(15,23,42,.06); flex-shrink: 0; }
        .md-util__icon svg { width: 20px; height: 20px; }
        .md-util__label { margin: 0; font-size: 12px; color: var(--md-muted); font-weight: 600; }
        .md-util__value { margin: 2px 0; font-size: 20px; font-weight: 700; font-variant-numeric: tabular-nums; }
        .md-util__ratio { margin: 0; font-size: 12px; color: var(--md-muted); }

        .md-oer__big { margin: 0; font-size: 44px; font-weight: 700; letter-spacing: -.03em; color: var(--md-brand-dark); line-height: 1; font-variant-numeric: tabular-nums; }
        .md-oer__big span { font-size: 22px; }
        .md-oer__delta { margin: 8px 0 18px; font-size: 13px; color: var(--md-muted); }
        .md-oer__compare { display: flex; flex-direction: column; gap: 12px; }
        .md-oer__item { display: grid; grid-template-columns: 40px 1fr 56px; align-items: center; gap: 10px; font-size: 13px; }
        .md-oer__item > span { color: var(--md-muted); font-weight: 600; }
        .md-oer__item b { text-align: right; font-variant-numeric: tabular-nums; }

        .md-notes { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .md-notes__item { padding: 14px; border-left: 3px solid var(--md-brand); background: #f8faf9; border-radius: 0 10px 10px 0; }
        .md-notes__item p { margin: 8px 0 0; font-size: 14px; line-height: 1.55; color: #334155; }

        .md-details { background: var(--md-card); border: 1px solid var(--md-line); border-radius: 16px; overflow: hidden; }
        .md-details > summary { cursor: pointer; list-style: none; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 6px 12px; padding: 16px 20px; font-size: 16px; font-weight: 700; }
        .md-details > summary::-webkit-details-marker { display: none; }
        .md-details > summary::after { content: ''; width: 9px; height: 9px; border-right: 2px solid var(--md-muted); border-bottom: 2px solid var(--md-muted); transform: rotate(45deg); margin-left: auto; transition: transform .15s; }
        .md-details[open] > summary::after { transform: rotate(-135deg); }
        .md-details > summary small { font-size: 12px; }
        .md-details[open] > summary { border-bottom: 1px solid var(--md-line); }
        .md-details .dmr__grid { padding: 20px; background: #f8faf9; }

        .dmr__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); grid-auto-flow: row dense; gap: 16px; align-items: start; }
        .dmr-panel { background: #fff; border: 1px solid var(--md-line); border-radius: 12px; overflow: hidden; min-width: 0; }
        .dmr-panel--wide { grid-column: 1 / -1; }
        .dmr-panel__title { margin: 0; padding: 10px 14px; font-size: 14px; font-weight: 700; color: var(--md-brand-dark); background: var(--md-brand-soft); border-bottom: 1px solid #cfe8da; }
        .dmr-panel__scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .dmr-table { width: 100%; border-collapse: collapse; font-size: 13px; font-variant-numeric: tabular-nums; }
        .dmr-table th, .dmr-table td { padding: 7px 10px; border-bottom: 1px solid #f0f1f3; white-space: nowrap; }
        .dmr-table th { background: #fafbfb; color: var(--md-muted); font-weight: 600; font-size: 12px; text-align: center; }
        .dmr-table td { text-align: right; }
        .dmr-table td:first-child { text-align: left; color: #334155; }
        .dmr-table tbody tr:hover td { background: #f8faf9; }
        .dmr-table__group td { background: #f1f5f3 !important; font-weight: 700; text-align: left !important; color: var(--md-ink) !important; }
        .dmr-table__total td { font-weight: 700; border-top: 1px solid #cbd5e1; }
        .dmr-table__neg { color: #b91c1c !important; }


        @media (max-width: 1280px) {
            .md-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .md-tanks { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 1024px) {
            .md-row--2, .md-row--2-1 { grid-template-columns: minmax(0, 1fr); }
            .dmr__grid { grid-template-columns: minmax(0, 1fr); }
        }
        @media (max-width: 767px) {
            .md { gap: 14px; }
            .md-hero { padding: 18px; border-radius: 12px; }
            .md-hero__title { font-size: 20px; }
            .md-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
            .md-kpi { padding: 12px; }
            .md-kpi__value { font-size: 19px; }
            .md-kpi__icon { width: 28px; height: 28px; }
            .md-card { padding: 16px; border-radius: 12px; }
            .md-lines, .md-tanks, .md-utils, .md-notes { grid-template-columns: minmax(0, 1fr); }
            .md-details .dmr__grid { padding: 12px; }
            .dmr-table { font-size: 12px; }
        }
    </style>
</section>
