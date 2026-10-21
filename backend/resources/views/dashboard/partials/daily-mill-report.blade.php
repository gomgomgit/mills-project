{{--
    Laporan harian mill (tampilan DEMO, data DUMMY) — isi utama menu Dashboard.
    Mereproduksi format laporan harian CPO Mill Demo: ringkasan visual di atas,
    tabel lengkap format laporan di bawah (bisa dibuka/tutup). Pemetaan ke tabel
    stasiun ada di docs/analisis-laporan-harian-mill.pdf.

    Angkanya dummy, tetapi TIDAK saling bebas: setiap angka adalah masukan
    terukur atau turunan dari masukan itu, sekali saja — lihat blok di bawah.
--}}
@php
    /*
       ────────────────────────────────────────────────────────────────────────
       SUMBER TUNGGAL. Setiap angka di halaman ini salah satu dari dua hal:
       MASUKAN — yang diukur di lapangan (timbangan, cacahan lori, jam, hasil
       lab, stok fisik) — atau TURUNAN dari masukan itu. Tidak ada angka yang
       ditulis di dua tempat.

       Alasannya bukan kerapian. Sampai 2026-10-20 ringkasan kartu dan tabel
       laporan menulis ulang angka yang sama, dan sembilan di antaranya sudah
       berselisih tanpa ada yang menangkapnya dari layar:

         1. Throughput MTD meleset 0.18 MT/jam dari Crushed / Milling Hours
            (kedua line); Today Line 2 dan kedua YTD juga tidak pas.
         2. Kartu Milling Hours membandingkan 21.50 jam terhadap "rata-rata
            harian MTD 21.58" — nilai benarnya 21.45 — sehingga badge-nya
            menunjuk TURUN 0.4% padahal naik 0.2%.
         3. Penerimaan FFB persis sama dengan FFB diolah di KETIGA periode,
            padahal stok FFB tersedia turun 26.10 MT hari itu. Mustahil, dan
            justru relasi yang paling cepat terbaca palsu.
         4. Penyesuaian stok Kernel -2.50 MT tampil di kolomnya tetapi tidak
            pernah ikut terhitung ke stok hari ini.
         5. Pembelian shell 12.50 MT (Product Arrival) hilang dari neraca shell.
         6. Kolom 'Days' panel Reliability berbunyi 15 / 15; yang kedua adalah
            panjang bulan, bukan hari berjalan lagi.
         7. Tanggal hero diambil dari now()->subDay() sementara hari MTD dan
            garis pro-rata budget dipatok 15: laporan bisa bertanggal 19 tetapi
            mengaku baru 15 hari berjalan.
         8. Kartu Stok CPO memakai penyebut "kapasitas 4 tangki (3,000 MT)"
            sementara blok Kualitas & Stok Tangki menggambar 4 x 1,000 MT.
         9. Catatan operasional menyebut CDT Line 2 9 jam; tabel jamnya 7.50.

       Angkanya tetap dummy. Yang hilang adalah kemungkinan angka dummy itu
       saling bertentangan.
       ────────────────────────────────────────────────────────────────────────
    */

    /* ── Hari pelaporan ──────────────────────────────────────────────────── */
    /*
       Tanggal di hero, kolom 'Days', Available = 24 jam x hari, dan garis
       pro-rata budget semuanya lahir dari SATU masukan: jumlah hari berjalan
       dalam bulan. Tanggal laporan menyusul dari sana — tanggal ke-$mtdDays
       pada bulan berjalan, atau bulan sebelumnya kalau hari itu belum lewat,
       supaya laporan HARIAN tidak pernah bertanggal di masa depan.

       Total MTD di bawah ditulis sebagai total 15 hari. Kalau $mtdDays diubah,
       total MTD itu harus ikut diubah — hanya Available MTD yang menyesuaikan
       diri sendiri.
    */
    $mtdDays    = 15;
    $reportBase = now()->day >= $mtdDays ? now() : now()->subMonthNoOverflow();
    $reportDate = $reportBase->copy()->startOfMonth()->addDays($mtdDays - 1);
    $ytdDays    = $reportDate->dayOfYear;
    $monthDays  = $reportDate->daysInMonth;

    $periods    = ['Today', 'MTD', 'YTD'];
    $periodDays = ['Today' => 1, 'MTD' => $mtdDays, 'YTD' => $ytdDays];

    $num0 = fn (float $v) => number_format($v, 0);
    $num2 = fn (float $v) => number_format($v, 2);
    $pct  = fn (float $v, float $of) => number_format($of > 0 ? $v / $of * 100 : 0, 2);

    /*
       MTD sebagai total, YTD sebagai LAJU HARIAN. YTD harus laju karena
       $ytdDays bergerak tiap bulan: total YTD yang ditulis tangan akan langsung
       berselisih dengan Available YTD (24 jam x $ytdDays) begitu bulan berganti,
       dan itulah yang dulu terjadi — Available YTD 3,096 jam setara 129 hari
       pada laporan yang bertanggal Oktober.
    */
    $overPeriods = fn (array $in) => [
        'Today' => $in['Today'],
        'MTD'   => $in['MTD'],
        'YTD'   => $in['rateYTD'] * $ytdDays,
    ];

    /* ── Jam per line ────────────────────────────────────────────────────── */
    // MASUKAN: jam mengolah dan tiga jenis downtime. Available TIDAK ditulis —
    // ia 24 jam x jumlah hari periode.
    //     Total Downtime          = CDT + SDT + EDT
    //     Available Milling Hours = Available - Total Downtime
    //     Milling Hours           = jam benar-benar mengolah (<= Available Milling Hours)
    // Singkatan MDH dan AMH sengaja TIDAK dipakai — keduanya tidak lazim di
    // mill, tidak seperti CDT/SDT/EDT yang memang istilah baku.
    $hoursInput = [
        'Line 1' => [
            'milling' => ['Today' => 11.00, 'MTD' => 164.80, 'rateYTD' => 10.44],
            'CDT'     => ['Today' =>  7.00, 'MTD' => 140.20, 'rateYTD' =>  9.30],
            'SDT'     => ['Today' =>  1.50, 'MTD' =>  22.00, 'rateYTD' =>  1.46],
            'EDT'     => ['Today' =>  3.00, 'MTD' =>  32.00, 'rateYTD' =>  2.08],
        ],
        'Line 2' => [
            'milling' => ['Today' => 10.50, 'MTD' => 156.90, 'rateYTD' =>  9.92],
            'CDT'     => ['Today' =>  7.50, 'MTD' => 146.00, 'rateYTD' =>  9.65],
            'SDT'     => ['Today' =>  1.50, 'MTD' =>  22.00, 'rateYTD' =>  1.46],
            'EDT'     => ['Today' =>  3.00, 'MTD' =>  34.10, 'rateYTD' =>  2.20],
        ],
    ];
    $hoursByLine = [];
    foreach ($hoursInput as $lineName => $items) {
        $line = ['available' => []];
        foreach ($periods as $p) {
            $line['available'][$p] = 24.0 * $periodDays[$p];
        }
        foreach ($items as $key => $in) {
            $line[$key] = $overPeriods($in);
        }
        $hoursByLine[$lineName] = $line;
    }

    $mdhFor = fn (array $l, string $p) => $l['CDT'][$p] + $l['SDT'][$p] + $l['EDT'][$p];
    $amhFor = fn (array $l, string $p) => $l['available'][$p] - $mdhFor($l, $p);

    $millingHoursTotal = [];
    $availableTotal    = [];
    foreach ($periods as $p) {
        $millingHoursTotal[$p] = array_sum(array_map(fn ($l) => $l['milling'][$p], $hoursByLine));
        $availableTotal[$p]    = array_sum(array_map(fn ($l) => $l['available'][$p], $hoursByLine));
    }

    /* ── Milling per line ───────────────────────────────────────────────── */
    // MASUKAN: cacahan lori dan berat FFB dari timbangan.
    // TURUNAN: Avg Cage Weight = crushed / cages, Throughput = crushed / jam
    // mengolah. Keduanya dulu ditulis tangan, dan throughput-lah yang melenceng.
    $millingInput = [
        'Line 1' => [
            'cages'   => ['Today' =>     64, 'MTD' =>     958, 'rateYTD' =>  60.10],
            'crushed' => ['Today' => 316.80, 'MTD' => 4742.10, 'rateYTD' => 297.55],
        ],
        'Line 2' => [
            'cages'   => ['Today' =>     58, 'MTD' =>     872, 'rateYTD' =>  54.80],
            'crushed' => ['Today' => 283.20, 'MTD' => 4248.25, 'rateYTD' => 266.85],
        ],
    ];
    $milling = [];
    foreach ($millingInput as $lineName => $in) {
        $cages = $overPeriods($in['cages']);
        $milling[$lineName] = [
            'cages'   => array_map(fn ($v) => (int) round($v), $cages),
            'crushed' => $overPeriods($in['crushed']),
        ];
    }
    $crushedTotal = [];
    foreach ($periods as $p) {
        $crushedTotal[$p] = array_sum(array_map(fn ($m) => $m['crushed'][$p], $milling));
    }

    /* ── Produksi ───────────────────────────────────────────────────────── */
    // MASUKAN: tonase produk. Yield TURUNAN = produk / FFB diolah, jadi kolom
    // Yield (%) selalu persis pembagian dua kolom di sebelahnya.
    $produced = array_map($overPeriods, [
        'CPO'    => ['Today' => 128.40, 'MTD' => 1933.55, 'rateYTD' => 120.78],
        'Kernel' => ['Today' =>  31.20, 'MTD' =>  466.80, 'rateYTD' =>  29.12],
        'EB'     => ['Today' => 126.00, 'MTD' => 1852.30, 'rateYTD' => 115.70],
        'Fibre'  => ['Today' =>  75.60, 'MTD' => 1114.80, 'rateYTD' =>  69.42],
        'Shell'  => ['Today' =>  33.00, 'MTD' =>  485.20, 'rateYTD' =>  30.48],
    ]);
    $yieldOf = fn (string $product, string $p) => $produced[$product][$p] / $crushedTotal[$p] * 100;

    /* ── Pengiriman dan pemakaian sendiri ───────────────────────────────── */
    // MASUKAN. Kolom 'As Fuel' = dipakai sendiri sebagai bahan bakar boiler,
    // bukan dikirim keluar; keduanya mengurangi stok dan ikut ke neraca.
    $dispatchInput = [
        'CPO'      => ['fuel' => null,  'Today' => 120.45, 'MTD' => 1805.30, 'rateYTD' => 125.90],
        'Kernel'   => ['fuel' => null,  'Today' =>  28.10, 'MTD' =>  402.75, 'rateYTD' =>  28.05],
        'EB'       => ['fuel' => 85.00, 'Today' =>   0.00, 'MTD' =>  310.00, 'rateYTD' =>  22.79],
        'Acid Oil' => ['fuel' => null,  'Today' =>   0.00, 'MTD' =>    4.20, 'rateYTD' =>   0.30],
        'Fibre'    => ['fuel' => 72.30, 'Today' =>   0.00, 'MTD' =>    0.00, 'rateYTD' =>   0.00],
        'Shell'    => ['fuel' => 18.40, 'Today' =>  15.00, 'MTD' =>  210.60, 'rateYTD' =>  14.77],
        'Solid'    => ['fuel' => null,  'Today' =>   6.20, 'MTD' =>   92.40, 'rateYTD' =>   6.44],
    ];
    $dispatched = array_map($overPeriods, $dispatchInput);
    $fuelUse    = array_map(fn ($d) => $d['fuel'], $dispatchInput);

    /* ── Stok FFB dan penerimaan ────────────────────────────────────────── */
    /*
       MASUKAN: stok fisik per lokasi (hari ini dan kemarin) plus posisi awal
       bulan dan awal tahun. Penerimaan FFB TURUNAN dari neraca:

           penerimaan = FFB diolah + (stok akhir - stok awal periode)

       Dulu penerimaan ditulis tangan dan kebetulan PERSIS sama dengan FFB
       diolah di ketiga periode. Akibatnya dua kartu teratas memajang 600.00 MT
       yang sama dua kali, padahal stok FFB hari itu turun 26.10 MT — sekarang
       kedua kartu itu bercerita: buah masuk 573.90 MT, diolah 600.00 MT,
       selisihnya diambil dari stok ramp.
    */
    $ffbStockInput = [
        ['label' => 'Loading Ramp #1',    'legend' => 'Loading Ramp #1', 'unprocessed' => true,  'today' => 85.20, 'yesterday' => 92.40, 'color' => '#249360'],
        ['label' => 'Loading Ramp #2',    'legend' => 'Loading Ramp #2', 'unprocessed' => true,  'today' => 64.80, 'yesterday' => 71.10, 'color' => '#5bb88a'],
        ['label' => 'Sterilized',         'legend' => 'Sterilized',      'unprocessed' => true,  'today' => 42.00, 'yesterday' => 38.50, 'color' => '#f59e0b'],
        ['label' => 'Unsterilized',       'legend' => 'Unsterilized',    'unprocessed' => true,  'today' => 28.00, 'yesterday' => 31.50, 'color' => '#fbbf24'],
        ['label' => 'FFB in Parking Area','legend' => 'Parking Area',    'unprocessed' => false, 'today' => 35.60, 'yesterday' => 48.20, 'color' => '#64748b'],
    ];
    $ffbStockMonthStart = 318.40;   // posisi stok FFB tersedia pada 1 bulan laporan
    $ffbStockYearStart  = 184.20;   // posisi stok FFB tersedia pada 1 Januari

    $sumStock = fn (string $when, ?bool $unprocessedOnly = null) => array_sum(array_map(
        fn ($s) => $unprocessedOnly === null || $s['unprocessed'] === $unprocessedOnly ? $s[$when] : 0.0,
        $ffbStockInput,
    ));
    $ffbUnprocessed = ['today' => $sumStock('today', true), 'yesterday' => $sumStock('yesterday', true)];
    $ffbAvailable   = ['today' => $sumStock('today'),       'yesterday' => $sumStock('yesterday')];

    $ffbArrival = [
        'Today' => $crushedTotal['Today'] + ($ffbAvailable['today'] - $ffbAvailable['yesterday']),
        'MTD'   => $crushedTotal['MTD']   + ($ffbAvailable['today'] - $ffbStockMonthStart),
        'YTD'   => $crushedTotal['YTD']   + ($ffbAvailable['today'] - $ffbStockYearStart),
    ];
    $plasmaShare = 0.3127;   // porsi buah dari kebun Plasma; sisanya kebun Inti
    $ffbPlasma = array_map(fn ($v) => round($v * $plasmaShare, 2), $ffbArrival);
    $ffbInti   = array_map(fn ($p, $t) => $t - $p, $ffbPlasma, $ffbArrival);
    $ffbInti   = array_combine($periods, $ffbInti);

    // Budget bulan berjalan = MASUKAN; budget sampai bulan ini TURUNAN dari
    // laju hariannya, supaya tidak berselisih dengan $ytdDays.
    $budgetMonth = ['Inti' => 12500.00, 'Plasma' => 5500.00];
    // Dibulatkan per komponen, baru dijumlahkan: kalau totalnya dijumlahkan dari
    // nilai mentah, baris Total bisa meleset sesen dari Inti + Plasma di layar.
    $budgetYtm   = array_map(fn ($b) => round($b / $monthDays * $ytdDays, 2), $budgetMonth);

    // Produk yang DIBELI, bukan diproduksi — ikut menambah stok.
    $purchased = array_map($overPeriods, [
        'CPO'   => ['Today' =>  0.00, 'MTD' => 45.20, 'rateYTD' => 2.42],
        'Shell' => ['Today' => 12.50, 'MTD' => 98.40, 'rateYTD' => 6.55],
    ]);

    /* ── Mutu dan isi tangki ────────────────────────────────────────────── */
    // SUMBER TUNGGAL untuk tiga tempat: blok kartu 'Kualitas & Stok Tangki',
    // panel tabel 'Product Quality', dan stok CPO/Kernel di 'Inventory Level'.
    // 'Kernel On' adalah mutu kernel di jalur, bukan wadah — tanpa stok.
    $tanks = [
        ['label' => 'CPO Tank 1', 'short' => 'CPO Tank 1', 'product' => 'CPO', 'stock' => 612.40, 'cap' => 1000.00, 'params' => [
            ['short' => 'FFA',   'panel' => 'FFA (%)',      'limit' => 5.00, 'Today' => 3.42, 'MTD' => 3.51, 'YTD' => 3.60],
            ['short' => 'Moist', 'panel' => 'Moisture (%)', 'limit' => 0.20, 'Today' => 0.18, 'MTD' => 0.19, 'YTD' => 0.20],
        ]],
        ['label' => 'CPO Tank 2', 'short' => 'CPO Tank 2', 'product' => 'CPO', 'stock' => 598.10, 'cap' => 1000.00, 'params' => [
            ['short' => 'FFA',   'panel' => 'FFA (%)',      'limit' => 5.00, 'Today' => 3.55, 'MTD' => 3.48, 'YTD' => 3.58],
            ['short' => 'Moist', 'panel' => 'Moisture (%)', 'limit' => 0.20, 'Today' => 0.19, 'MTD' => 0.18, 'YTD' => 0.19],
        ]],
        ['label' => 'CPO Tank 3', 'short' => 'CPO Tank 3', 'product' => 'CPO', 'stock' => 604.25, 'cap' => 1000.00, 'params' => [
            ['short' => 'FFA',   'panel' => 'FFA (%)',      'limit' => 5.00, 'Today' => 3.61, 'MTD' => 3.57, 'YTD' => 3.62],
            ['short' => 'Moist', 'panel' => 'Moisture (%)', 'limit' => 0.20, 'Today' => 0.20, 'MTD' => 0.19, 'YTD' => 0.20],
        ]],
        ['label' => 'CPO Tank 4', 'short' => 'CPO Tank 4', 'product' => 'CPO', 'stock' => 603.60, 'cap' => 1000.00, 'params' => [
            ['short' => 'FFA',   'panel' => 'FFA (%)',      'limit' => 5.00, 'Today' => 3.38, 'MTD' => 3.45, 'YTD' => 3.55],
            ['short' => 'Moist', 'panel' => 'Moisture (%)', 'limit' => 0.20, 'Today' => 0.17, 'MTD' => 0.18, 'YTD' => 0.19],
        ]],
        ['label' => 'Kernel On', 'short' => null, 'product' => null, 'stock' => null, 'cap' => null, 'params' => [
            ['short' => 'Admix', 'panel' => 'Admixture (%)', 'limit' => 6.10, 'Today' => 6.20, 'MTD' => 6.35, 'YTD' => 6.40],
            ['short' => 'Moist', 'panel' => 'Moisture (%)',  'limit' => 7.00, 'Today' => 7.10, 'MTD' => 7.05, 'YTD' => 7.00],
        ]],
        ['label' => 'PK Bulk Silo 1', 'short' => 'PK Silo 1', 'product' => 'Kernel', 'stock' => 308.40, 'cap' => 500.00, 'params' => [
            ['short' => 'Admix', 'panel' => 'Admixture (%)', 'limit' => 6.10, 'Today' => 6.05, 'MTD' => 6.20, 'YTD' => 6.30],
            ['short' => 'Moist', 'panel' => 'Moisture (%)',  'limit' => 7.00, 'Today' => 6.90, 'MTD' => 6.95, 'YTD' => 6.98],
        ]],
        ['label' => 'PK Bulk Silo 2', 'short' => 'PK Silo 2', 'product' => 'Kernel', 'stock' => 304.40, 'cap' => 500.00, 'params' => [
            ['short' => 'Admix', 'panel' => 'Admixture (%)', 'limit' => 6.10, 'Today' => 6.15, 'MTD' => 6.25, 'YTD' => 6.32],
            ['short' => 'Moist', 'panel' => 'Moisture (%)',  'limit' => 7.00, 'Today' => 7.00, 'MTD' => 6.98, 'YTD' => 7.01],
        ]],
    ];
    $tankCards  = array_values(array_filter($tanks, fn ($t) => $t['short'] !== null));
    $stockIn    = fn (string $product) => array_sum(array_map(fn ($t) => $t['product'] === $product ? $t['stock'] : 0.0, $tanks));
    $capacityOf = fn (string $product) => array_sum(array_map(fn ($t) => $t['product'] === $product ? $t['cap'] : 0.0, $tanks));

    /* ── Stok produk ────────────────────────────────────────────────────── */
    /*
       Stok CPO dan Kernel hari ini TURUNAN dari isi tangki dan silo di atas —
       dua tempat yang dulu menulis angka yang sama.

       Kolom 'Yesterday' juga TURUNAN, dari neraca yang dibalik:

           kemarin = hari ini - diproduksi - dibeli + dikirim + bahan bakar - penyesuaian

       Dulu ketiga kolom ditulis tangan dan dua barisnya tidak menutup:
       penyesuaian Kernel -2.50 MT tidak pernah ikut terhitung, dan pembelian
       shell 12.50 MT hilang. Dengan 'Yesterday' diturunkan, kolom itu tidak
       bisa lagi tidak menutup — lima dari tujuh barisnya tetap pada angka
       semula, dua yang berubah memang yang dulu timpang.
    */
    $inventoryToday = [
        'CPO'      => $stockIn('CPO'),
        'Kernel'   => $stockIn('Kernel'),
        'EB'       => 1540.00,
        'Acid Oil' => 18.40,
        'Fibre'    => 96.20,
        'Shell'    => 410.60,
        'Solid'    => 58.30,
    ];
    $adjustment = ['CPO' => 0.00, 'Kernel' => -2.50, 'EB' => 0.00, 'Acid Oil' => 0.00, 'Fibre' => 0.00, 'Shell' => 0.00, 'Solid' => 0.40];
    $inventoryYesterday = [];
    foreach ($inventoryToday as $product => $today) {
        $inventoryYesterday[$product] = $today
            - ($produced[$product]['Today'] ?? 0.0)
            - ($purchased[$product]['Today'] ?? 0.0)
            + ($dispatched[$product]['Today'] ?? 0.0)
            + ($fuelUse[$product] ?? 0.0)
            - $adjustment[$product];
    }

    /* ── Energi dan air ─────────────────────────────────────────────────── */
    // MASUKAN: laju pemakaian per ton FFB diolah. Totalnya TURUNAN, jadi baris
    // rasio di tabel selalu persis sama dengan laju ini dan tidak bisa
    // berselisih dengan total FFB diolah.
    $energyRates = ['power' => 19.00, 'heat' => 520.00];           // kWh/t FFB, MCal/t FFB
    $waterRates  = ['Process' => 0.80, 'Boiler' => 0.60];          // m3/t FFB

    $energyUse = ['power' => [], 'heat' => []];
    $waterUse  = ['Process' => [], 'Boiler' => [], 'total' => []];
    foreach ($periods as $p) {
        $energyUse['power'][$p] = $energyRates['power'] * $crushedTotal[$p];
        $energyUse['heat'][$p]  = $energyRates['heat'] * $crushedTotal[$p];
        $waterUse['Process'][$p] = $waterRates['Process'] * $crushedTotal[$p];
        $waterUse['Boiler'][$p]  = $waterRates['Boiler'] * $crushedTotal[$p];
        $waterUse['total'][$p]   = $waterUse['Process'][$p] + $waterUse['Boiler'][$p];
    }

    /* ── OER ────────────────────────────────────────────────────────────── */
    // Theoretical OER = MASUKAN (analisa lab buah masuk). DCR OER adalah yield
    // CPO yang sama dengan panel Production — dulu ditulis dua kali.
    $oerTheoretical = ['Today' => 22.10, 'MTD' => 22.05, 'YTD' => 21.98];
    $kerTheoretical = 5.50;

    /* ── Panel tabel laporan harian ─────────────────────────────────────── */
    $overAll = fn (callable $f) => array_map($f, $periods);

    $hoursGroups = [];
    foreach ($hoursByLine as $lineName => $l) {
        $rows = [];
        foreach ([
            ['Available',                   fn ($p) => $l['available'][$p]],
            ['CDT (Commercial Down Time)',  fn ($p) => $l['CDT'][$p]],
            ['SDT (Scheduled Down Time)',   fn ($p) => $l['SDT'][$p]],
            ['EDT (Emergency Down Time)',   fn ($p) => $l['EDT'][$p]],
            ['Total Downtime',              fn ($p) => $mdhFor($l, $p)],
            ['Available Milling Hours',     fn ($p) => $amhFor($l, $p)],
            ['Milling Hours',               fn ($p) => $l['milling'][$p]],
        ] as [$label, $valueFor]) {
            $rows[] = array_merge(
                [$label],
                array_map(fn ($p) => $num2($valueFor($p)), $periods),
                array_map(fn ($p) => $pct($valueFor($p), $l['available'][$p]), $periods),
            );
        }
        $hoursGroups[$lineName] = $rows;
    }

    // Kolom 'Days': hari berjalan dalam bulan, lalu panjang bulan itu — pasangan
    // yang sama dengan garis pro-rata pada blok budget. Dulu keduanya 15.
    $reliabilityRows = [];
    foreach ($hoursByLine as $lineName => $l) {
        $reliabilityRows[] = array_merge(
            [$lineName],
            array_map(fn ($p) => $num2($amhFor($l, $p)), $periods),
            array_map(fn ($p) => $num2($mdhFor($l, $p)), $periods),
            [$num0($mtdDays), $num0($monthDays)],
        );
    }

    $millingGroups = [];
    foreach ($milling as $lineName => $m) {
        $l = $hoursByLine[$lineName];
        $millingGroups[$lineName] = [
            array_merge(['Cages Tipped'],         $overAll(fn ($p) => $num0($m['cages'][$p]))),
            array_merge(['FFB Crushed (MT)'],     $overAll(fn ($p) => $num2($m['crushed'][$p]))),
            array_merge(['Avg Cage Weight (MT)'], $overAll(fn ($p) => $num2($m['crushed'][$p] / $m['cages'][$p]))),
            array_merge(['Throughput (MT/hr)'],   $overAll(fn ($p) => $num2($m['crushed'][$p] / $l['milling'][$p]))),
        ];
    }

    $qualityGroups = [];
    foreach ($tanks as $t) {
        $qualityGroups[$t['label']] = array_map(
            fn ($param, $i) => array_merge(
                [$param['panel']],
                $overAll(fn ($p) => $num2($param[$p])),
                [$i === 0 && $t['stock'] !== null ? $num2($t['stock']) : ''],
            ),
            $t['params'],
            array_keys($t['params']),
        );
    }

    $panels = [
        [
            'title' => 'Product Arrival',
            'head' => [
                [['Product', 1, 2], ['Actual', 3, 1], ['Budget', 2, 1]],
                [['Today', 1, 1], ['MTD', 1, 1], ['YTD', 1, 1], ['CM', 1, 1], ['YTM', 1, 1]],
            ],
            'rows' => [
                array_merge(['FFB Inti (MT)'],   $overAll(fn ($p) => $num2($ffbInti[$p])),    [$num2($budgetMonth['Inti']),   $num2($budgetYtm['Inti'])]),
                array_merge(['FFB Plasma (MT)'], $overAll(fn ($p) => $num2($ffbPlasma[$p])),  [$num2($budgetMonth['Plasma']), $num2($budgetYtm['Plasma'])]),
                array_merge(['Total FFB (MT)'],  $overAll(fn ($p) => $num2($ffbArrival[$p])), [$num2(array_sum($budgetMonth)), $num2(array_sum($budgetYtm))]),
                array_merge(['CPO (MT)'],        $overAll(fn ($p) => $num2($purchased['CPO'][$p])),   ['-', '-']),
                array_merge(['Shell (MT)'],      $overAll(fn ($p) => $num2($purchased['Shell'][$p])), ['-', '-']),
            ],
            'total' => [2],
        ],
        [
            'title' => 'Product Dispatched',
            'head' => [
                [['Product', 1, 1], ['As Fuel', 1, 1], ['Today', 1, 1], ['MTD', 1, 1], ['YTD', 1, 1]],
            ],
            'rows' => array_map(
                fn ($product) => array_merge(
                    [$product.' (MT)', $fuelUse[$product] === null ? '-' : $num2($fuelUse[$product])],
                    $overAll(fn ($p) => $num2($dispatched[$product][$p])),
                ),
                array_keys($dispatchInput),
            ),
        ],
        [
            'title' => 'Production',
            'head' => [
                [['Product', 1, 2], ['Produced (MT)', 3, 1], ['Yield (%)', 3, 1]],
                [['Today', 1, 1], ['MTD', 1, 1], ['YTD', 1, 1], ['Today', 1, 1], ['MTD', 1, 1], ['YTD', 1, 1]],
            ],
            'rows' => array_map(
                fn ($product) => array_merge(
                    [$product],
                    $overAll(fn ($p) => $num2($produced[$product][$p])),
                    $overAll(fn ($p) => $num2($yieldOf($product, $p))),
                ),
                array_keys($produced),
            ),
        ],
        [
            'title' => 'FFB Stock (MT)',
            'head' => [
                [['Location', 1, 1], ['Today', 1, 1], ['Yesterday', 1, 1]],
            ],
            'rows' => [
                ...array_map(fn ($s) => [$s['label'], $num2($s['today']), $num2($s['yesterday'])], array_filter($ffbStockInput, fn ($s) => $s['unprocessed'])),
                ['Total Unprocessed FFB', $num2($ffbUnprocessed['today']), $num2($ffbUnprocessed['yesterday'])],
                ...array_map(fn ($s) => [$s['label'], $num2($s['today']), $num2($s['yesterday'])], array_filter($ffbStockInput, fn ($s) => ! $s['unprocessed'])),
                ['Total Available FFB', $num2($ffbAvailable['today']), $num2($ffbAvailable['yesterday'])],
            ],
            'total' => [4, 6],
        ],
        [
            'title' => 'Inventory Level (MT)',
            'head' => [
                [['Product', 1, 1], ['Today', 1, 1], ['Yesterday', 1, 1], ['Adjustment', 1, 1]],
            ],
            'rows' => array_map(
                fn ($product) => [$product, $num2($inventoryToday[$product]), $num2($inventoryYesterday[$product]), $num2($adjustment[$product])],
                array_keys($inventoryToday),
            ),
        ],
        [
            'title' => 'Milling Summary',
            'head' => [
                [['Item', 1, 1], ['Today', 1, 1], ['MTD', 1, 1], ['YTD', 1, 1]],
            ],
            'groups' => $millingGroups,
            'rows' => [
                array_merge(['Total FFB Crushed (MT)'], $overAll(fn ($p) => $num2($crushedTotal[$p]))),
            ],
            'total' => [0],
        ],
        [
            'title' => 'Hours per Line',
            'head' => [
                [['Item', 1, 2], ['Hours', 3, 1], ['Percentage (%)', 3, 1]],
                [['Today', 1, 1], ['MTD', 1, 1], ['YTD', 1, 1], ['Today', 1, 1], ['MTD', 1, 1], ['YTD', 1, 1]],
            ],
            'groups' => $hoursGroups,
        ],
        [
            'title' => 'Product Quality',
            'head' => [
                [['Item', 1, 1], ['Today', 1, 1], ['MTD', 1, 1], ['YTD', 1, 1], ['Stock (MT)', 1, 1]],
            ],
            'groups' => $qualityGroups,
        ],
        [
            'title' => 'Energy Used',
            'head' => [
                [['Item', 1, 1], ['Today', 1, 1], ['MTD', 1, 1], ['YTD', 1, 1]],
            ],
            'rows' => [
                array_merge(['Power Mill (kWh)'],         $overAll(fn ($p) => $num0($energyUse['power'][$p]))),
                array_merge(['Power / t FFB (kWh/t)'],    $overAll(fn ($p) => $num2($energyUse['power'][$p] / $crushedTotal[$p]))),
                array_merge(['Heat (MCal)'],              $overAll(fn ($p) => $num0($energyUse['heat'][$p]))),
                array_merge(['Heat / t FFB (kCal/t)'],    $overAll(fn ($p) => $num0($energyUse['heat'][$p] / $crushedTotal[$p] * 1000))),
            ],
        ],
        [
            'title' => 'Water Used (M3)',
            'head' => [
                [['Item', 1, 1], ['Today', 1, 1], ['MTD', 1, 1], ['YTD', 1, 1]],
            ],
            'rows' => [
                array_merge(['Process'],                  $overAll(fn ($p) => $num2($waterUse['Process'][$p]))),
                array_merge(['Boiler'],                   $overAll(fn ($p) => $num2($waterUse['Boiler'][$p]))),
                array_merge(['For Mill Operation'],       $overAll(fn ($p) => $num2($waterUse['total'][$p]))),
                array_merge(['Usage (M3/Ton FFB)'],       $overAll(fn ($p) => $num2($waterUse['total'][$p] / $crushedTotal[$p]))),
            ],
            'total' => [2],
        ],
        /*
           Dulu 'Today Reliability' dengan kolom EE (%) dan BE (%). Dua cacat
           sekaligus: judulnya berbunyi 'Today' padahal kolomnya MTD/YTD, dan
           ketiga angkanya ternyata metrik yang SAMA pada tiga periode berbeda
           — bukan dua metrik EE dan BE. Terbukti: semuanya sama dengan
           Milling / (Available - CDT - SDT), cocok pada kedua line.

           Diganti Available Milling Hours dan Total Downtime per periode (user
           2026-09-30), diturunkan dari $hoursByLine sehingga tidak dapat
           berselisih dengan panel 'Hours per Line' maupun grafik jam.
        */
        [
            'title' => 'Reliability per Line',
            'head' => [
                [['Line', 1, 2], ['Available Milling Hours (jam)', 3, 1], ['Total Downtime (jam)', 3, 1], ['Days', 2, 1]],
                [['Today', 1, 1], ['MTD', 1, 1], ['YTD', 1, 1],
                 ['Today', 1, 1], ['MTD', 1, 1], ['YTD', 1, 1],
                 ['MTD', 1, 1], ['Total', 1, 1]],
            ],
            'rows' => $reliabilityRows,
        ],
        [
            'title' => 'OER (%)',
            'head' => [
                [['Item', 1, 1], ['Today', 1, 1], ['MTD', 1, 1], ['YTD', 1, 1]],
            ],
            'rows' => [
                array_merge(['Theoretical OER'], $overAll(fn ($p) => $num2($oerTheoretical[$p]))),
                array_merge(['DCR OER'],         $overAll(fn ($p) => $num2($yieldOf('CPO', $p)))),
                array_merge(['Difference'],      $overAll(fn ($p) => $num2($yieldOf('CPO', $p) - $oerTheoretical[$p]))),
            ],
            'total' => [2],
        ],
    ];

    // Jam pada catatan ikut $hoursByLine, bukan ditulis ulang di prosa: dulu
    // catatan Line 2 berbunyi 'CDT 9 jam' sementara tabelnya 7.50 jam.
    $explanations = [
        'Line 1' => 'EDT '.$num2($hoursByLine['Line 1']['EDT']['Today']).' jam — penggantian bearing screw press No. 2 (08:15–11:15). Throughput kembali normal setelah perbaikan.',
        'Line 2' => 'CDT '.$num2($hoursByLine['Line 2']['CDT']['Today']).' jam — menunggu buah masuk dari afdeling Plasma akibat jalan rusak. Tidak ada kerusakan mesin.',
    ];
@endphp
@php
    /*
       Ringkasan visual. SEMUA angka di blok ini turunan dari masukan di blok
       sebelumnya — tidak ada satu pun yang ditulis ulang. Dulu keenam kartu
       membawa nilai, persentase, pembanding, dan arah tren sebagai literal,
       dan dua di antaranya sudah melenceng: kartu Milling Hours membandingkan
       terhadap 21.58 jam (benarnya 21.45) sehingga badge-nya menunjuk turun
       padahal naik, dan kartu Stok CPO memakai penyebut 3,000 MT sementara
       blok tangki di bawahnya menggambar 4 x 1,000 MT.

       Setiap kartu memakai barometer yang sama: satu bar progres terhadap
       acuan tetap (budget / kapasitas / nilai teoritis) DAN satu badge deviasi
       terhadap pembanding waktu. Keduanya wajib ada di setiap kartu — lihat
       DashboardHomeTest 'every KPI card carries both a progress bar and a
       deviation'. Tren SEMUA kartu = nilai Today vs rata-rata harian MTD
       (keputusan user 2026-10-05).
    */
    $mtdAvg  = fn (float $total) => $total / $mtdDays;
    $trendVs = function (float $today, float $base) {
        $deviation = $base > 0 ? ($today / $base - 1) * 100 : 0.0;

        return [sprintf('%+.1f%%', $deviation), $deviation >= 0 ? 'up' : 'down'];
    };
    // Dipakai sebagai label bar DAN sebagai lebar bar, dari satu pembulatan:
    // kalau dibulatkan dua kali, bar dan angkanya bisa berselisih sedesimal.
    $progressLabel = fn (float $progress, string $against) => number_format($progress, 1).'% '.$against;

    $cpoStockMtdAvg = 2396.80;   // rata-rata posisi stok CPO harian MTD (dummy; tidak ada panel histori stok)
    $ratedPerLine   = 30.00;     // kapasitas terpasang per line, MT/jam
    $ratedToday     = $millingHoursTotal['Today'] * $ratedPerLine;
    $cpoTankCount   = count(array_filter($tanks, fn ($t) => $t['product'] === 'CPO'));
    $lineCount      = count($hoursByLine);

    $kpis = [
        [
            'label' => 'FFB Diterima', 'unit' => 'MT', 'icon' => 'truck', 'period' => 'Today',
            'value' => $num2($ffbArrival['Today']),
            'meta'  => 'MTD '.$num2($ffbArrival['MTD']).' MT',
            'progress' => $ffbArrival['MTD'] / array_sum($budgetMonth) * 100,
            'against'  => 'dari budget bulan ini ('.$num0(array_sum($budgetMonth)).' MT)',
            'today' => $ffbArrival['Today'], 'base' => $mtdAvg($ffbArrival['MTD']), 'baseUnit' => 'MT',
        ],
        [
            'label' => 'FFB Diolah', 'unit' => 'MT', 'icon' => 'factory', 'period' => 'Today',
            'value' => $num2($crushedTotal['Today']),
            'meta'  => 'Rata-rata '.$num2($crushedTotal['Today'] / $millingHoursTotal['Today']).' MT/jam',
            'progress' => $crushedTotal['Today'] / $ratedToday * 100,
            'against'  => 'dari kapasitas '.$num2($ratedToday).' MT saat milling hours',
            'today' => $crushedTotal['Today'], 'base' => $mtdAvg($crushedTotal['MTD']), 'baseUnit' => 'MT',
        ],
        [
            'label' => 'CPO Diproduksi', 'unit' => 'MT', 'icon' => 'drop', 'period' => 'Today',
            'value' => $num2($produced['CPO']['Today']),
            'meta'  => 'OER '.$num2($yieldOf('CPO', 'Today')).'%',
            'progress' => $yieldOf('CPO', 'Today') / $oerTheoretical['Today'] * 100,
            'against'  => 'dari OER teoritis '.$num2($oerTheoretical['Today']).'%',
            'today' => $produced['CPO']['Today'], 'base' => $mtdAvg($produced['CPO']['MTD']), 'baseUnit' => 'MT',
        ],
        [
            'label' => 'Kernel Diproduksi', 'unit' => 'MT', 'icon' => 'seed', 'period' => 'Today',
            'value' => $num2($produced['Kernel']['Today']),
            'meta'  => 'KER '.$num2($yieldOf('Kernel', 'Today')).'%',
            'progress' => $yieldOf('Kernel', 'Today') / $kerTheoretical * 100,
            'against'  => 'dari KER teoritis '.$num2($kerTheoretical).'%',
            'today' => $produced['Kernel']['Today'], 'base' => $mtdAvg($produced['Kernel']['MTD']), 'baseUnit' => 'MT',
        ],
        [
            'label' => 'Stok CPO', 'unit' => 'MT', 'icon' => 'tank', 'period' => 'Today',
            'value' => $num2($inventoryToday['CPO']),
            'meta'  => 'Kemarin '.$num2($inventoryYesterday['CPO']).' MT',
            'progress' => $inventoryToday['CPO'] / $capacityOf('CPO') * 100,
            'against'  => 'dari kapasitas '.$cpoTankCount.' tangki ('.$num0($capacityOf('CPO')).' MT)',
            'today' => $inventoryToday['CPO'], 'base' => $cpoStockMtdAvg, 'baseUnit' => 'MT',
        ],
        [
            // EE tidak ditaruh di kartu ini — sudah tampil per line di blok
            // 'Milling Hours per Line'.
            'label' => 'Milling Hours', 'unit' => 'jam', 'icon' => 'clock', 'period' => 'Today',
            'value' => $num2($millingHoursTotal['Today']),
            'meta'  => implode(' · ', array_map(
                fn ($name, $l) => $name.' '.$num2($l['milling']['Today']).' jam',
                array_keys($hoursByLine),
                $hoursByLine,
            )),
            'progress' => $millingHoursTotal['Today'] / $availableTotal['Today'] * 100,
            'against'  => 'dari '.$num2($availableTotal['Today']).' jam tersedia ('.$lineCount.' line)',
            'today' => $millingHoursTotal['Today'], 'base' => $mtdAvg($millingHoursTotal['MTD']), 'baseUnit' => 'jam',
        ],
    ];
    $kpis = array_map(fn ($k) => $k + [
        'progressText' => $progressLabel($k['progress'], $k['against']),
        'trend'        => $trendVs($k['today'], $k['base']),
        'trendLabel'   => 'vs rata-rata harian MTD ('.$num2($k['base']).' '.$k['baseUnit'].')',
    ], $kpis);

    $arrivalBudget = [
        ['label' => 'FFB Inti',   'actual' => $ffbInti['MTD'],    'budget' => $budgetMonth['Inti']],
        ['label' => 'FFB Plasma', 'actual' => $ffbPlasma['MTD'],  'budget' => $budgetMonth['Plasma']],
        ['label' => 'Total FFB',  'actual' => $ffbArrival['MTD'], 'budget' => array_sum($budgetMonth)],
    ];
    // Garis pro-rata memakai pasangan hari yang sama dengan kolom 'Days'.
    $proRataPct = $mtdDays / $monthDays * 100;

    // Satuan ikut per baris, bukan ditulis di template: kalau kelak ada pos stok
    // dengan satuan lain (lori, bukan MT), ia tidak akan diberi label 'MT' begitu saja.
    $ffbStock = array_map(fn ($s) => [
        'label' => $s['legend'], 'value' => $s['today'], 'unit' => 'MT', 'color' => $s['color'],
    ], $ffbStockInput);
    $ffbStockTotal = $ffbAvailable['today'];
    $stops = []; $acc = 0;
    foreach ($ffbStock as $s) {
        $from = $acc; $acc += $s['value'] / $ffbStockTotal * 100;
        $stops[] = "{$s['color']} {$from}% {$acc}%";
    }
    $donut = 'conic-gradient('.implode(', ', $stops).')';

    $hourSegments = ['Milling' => '#249360', 'Available' => '#64748b', 'CDT' => '#f59e0b', 'SDT' => '#60a5fa', 'EDT' => '#ef4444'];
    /*
       Jam grafik ditarik dari $hoursByLine periode 'Today' lewat $amhFor/$mdhFor
       — closure yang SAMA dengan kedua panel tabel, bukan salinannya. Sempat ada
       sepasang closure kembar di sini; akibatnya label grafik tetap benar saat
       rumus di sumbernya dirusak, sehingga kesalahan rumus tidak terdeteksi dari
       layar. Jangan definisikan ulang di sini.

       Segmen hijau = Milling, abu-abu = sisa Available Milling Hours yang tidak
       terpakai (keputusan user 2026-10-05), jadi hijau + abu-abu = Available
       Milling Hours dan batangnya selalu berjumlah persis Available.

       Milling dan Available TIDAK ditulis lagi di label baris: nilainya sudah
       tergambar sebagai kedua segmen itu pada batang tepat di bawahnya. Label
       hanya membawa Total Downtime — satu-satunya dari ketiganya yang tidak
       punya segmen sendiri, karena ia jumlah dari tiga segmen downtime.
    */
    $lines = [];
    foreach ($milling as $lineName => $m) {
        $l = $hoursByLine[$lineName];
        $lines[] = [
            'name'       => $lineName,
            'cages'      => $m['cages']['Today'],
            'crushed'    => $m['crushed']['Today'],
            'avgCage'    => $m['crushed']['Today'] / $m['cages']['Today'],
            'throughput' => $m['crushed']['Today'] / $l['milling']['Today'],
            // Pangsa terhadap total FFB diolah hari ini, bukan terhadap angka
            // 600 yang dulu ditulis langsung di template.
            'share'      => $m['crushed']['Today'] / $crushedTotal['Today'] * 100,
            // Penyebut lebar batang — dulu angka 24 yang ditulis langsung di
            // template, sehingga batang berhenti penuh begitu Available bukan 24.
            'available'  => $l['available']['Today'],
            'mdh'        => $mdhFor($l, 'Today'),
            'hours' => [
                'Milling'   => $l['milling']['Today'],
                'Available' => $amhFor($l, 'Today') - $l['milling']['Today'],
                'CDT'       => $l['CDT']['Today'],
                'SDT'       => $l['SDT']['Today'],
                'EDT'       => $l['EDT']['Today'],
            ],
        ];
    }
    $availablePerLine = $hoursByLine['Line 1']['available']['Today'];

    $utilities = [
        ['label' => 'Listrik Mill',  'value' => $num0($energyUse['power']['Today']), 'unit' => 'kWh',
         'ratio' => $num2($energyRates['power']).' kWh/t FFB', 'icon' => 'bolt'],
        ['label' => 'Panas',         'value' => $num0($energyUse['heat']['Today']),  'unit' => 'MCal',
         'ratio' => $num0($energyRates['heat'] * 1000).' kCal/t FFB', 'icon' => 'flame'],
        ['label' => 'Air Proses',    'value' => $num0($waterUse['Process']['Today']), 'unit' => 'm³',
         'ratio' => 'Boiler '.$num0($waterUse['Boiler']['Today']).' m³', 'icon' => 'water'],
        ['label' => 'Pemakaian Air', 'value' => $num2($waterUse['total']['Today'] / $crushedTotal['Today']), 'unit' => 'm³/t',
         'ratio' => 'Total '.$num0($waterUse['total']['Today']).' m³', 'icon' => 'water'],
    ];

    $oer = [
        'theoretical' => $oerTheoretical['Today'],
        'actual'      => $yieldOf('CPO', 'Today'),
        'mtd'         => $yieldOf('CPO', 'MTD'),
        'ytd'         => $yieldOf('CPO', 'YTD'),
    ];

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
                {{ $reportDate->translatedFormat('l, d F Y') }}
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
                    <span class="md-kpi__period">{{ $kpi['period'] }}</span>
                    <span class="md-kpi__icon"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$kpi['icon']] !!}</svg></span>
                </div>
                <p class="md-kpi__value">{{ $kpi['value'] }} <span>{{ $kpi['unit'] }}</span></p>
                <p class="md-kpi__meta">{{ $kpi['meta'] }}</p>
                {{-- Barometer seragam: bar progres + badge deviasi, keduanya di setiap kartu. --}}
                <div class="md-bar"><span style="width: {{ number_format($kpi['progress'], 1) }}%"></span></div>
                <p class="md-kpi__foot">{{ $kpi['progressText'] }}</p>
                <p class="md-kpi__foot md-kpi__foot--trend"><span class="md-trend md-trend--{{ $kpi['trend'][1] }}">{{ $kpi['trend'][0] }}</span> {{ $kpi['trendLabel'] }}</p>
            </article>
        @endforeach
    </div>

    <div class="md-row md-row--2-1">
        {{-- Penerimaan vs budget --}}
        <article class="md-card">
            <header class="md-card__head">
                <h3>Penerimaan FFB vs Budget</h3>
                <span class="md-card__hint">MTD · terhadap budget bulan berjalan</span>
            </header>
            <div class="md-budget">
                @foreach ($arrivalBudget as $row)
                    @php $budgetPct = $row['actual'] / $row['budget'] * 100; @endphp
                    <div class="md-budget__row{{ $loop->last ? ' md-budget__row--total' : '' }}">
                        <div class="md-budget__label">
                            <span>{{ $row['label'] }}</span>
                            <span class="md-budget__nums"><strong>{{ number_format($row['actual'], 2) }}</strong> / {{ number_format($row['budget'], 0) }} MT</span>
                        </div>
                        <div class="md-bar md-bar--lg"><span style="width: {{ min($budgetPct, 100) }}%"></span><em style="left: {{ $proRataPct }}%" title="Pro-rata hari ke-{{ $mtdDays }}"></em></div>
                        <span class="md-budget__pct">{{ number_format($budgetPct, 1) }}%</span>
                    </div>
                @endforeach
                <p class="md-note"><span class="md-note__tick"></span> Garis tegak = target pro-rata (hari ke-{{ $mtdDays }} dari {{ $monthDays }})</p>
            </div>
        </article>

        {{-- Stok FFB --}}
        <article class="md-card">
            <header class="md-card__head">
                <h3>Stok FFB</h3>
                <span class="md-card__hint">Today · posisi stok</span>
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
                        <li><i style="background: {{ $s['color'] }}"></i><span>{{ $s['label'] }}</span><b>{{ number_format($s['value'], 1) }} <small>{{ $s['unit'] }}</small></b></li>
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
                <span class="md-card__hint">Today</span>
            </header>
            <div class="md-lines">
                @foreach ($lines as $line)
                    <div class="md-line">
                        <div class="md-line__head">
                            <strong>{{ $line['name'] }}</strong>
                            <span class="md-pill">{{ number_format($line['throughput'], 2) }} MT/jam</span>
                        </div>
                        <dl class="md-line__stats">
                            <div><dt>Cages Tipped</dt><dd>{{ $line['cages'] }} <small>lori</small></dd></div>
                            <div><dt>FFB Crushed</dt><dd>{{ number_format($line['crushed'], 2) }} <small>MT</small></dd></div>
                            <div><dt>Avg Cage Weight</dt><dd>{{ number_format($line['avgCage'], 2) }} <small>MT</small></dd></div>
                        </dl>
                        <div class="md-bar"><span style="width: {{ $line['share'] }}%"></span></div>
                        <p class="md-kpi__foot">{{ number_format($line['share'], 1) }}% dari total FFB diolah</p>
                    </div>
                @endforeach
            </div>
        </article>

        {{-- Distribusi jam --}}
        <article class="md-card">
            <header class="md-card__head">
                <h3>Milling Hours per Line</h3>
                <span class="md-card__hint">Today · {{ $num0($availablePerLine) }} jam tersedia</span>
            </header>
            <div class="md-hours">
                @foreach ($lines as $line)
                    <div class="md-hours__row">
                        <div class="md-hours__label"><strong>{{ $line['name'] }}</strong><span title="Total downtime = CDT + SDT + EDT">Total downtime {{ number_format($line['mdh'], 2) }} jam</span></div>
                        <div class="md-stack">
                            @foreach ($line['hours'] as $key => $h)
                                {{-- Ambang 1 jam, bukan 2: segmen 1 jam ≈ 4% bar, cukup untuk "1.0" pada
                                     12px. Dulu 2, yang membuat SDT (1.50 jam) selalu kosong di layar
                                     padahal muat. Yang lebih sempit dari itu tetap dikosongkan dan
                                     angkanya hanya di tooltip — .md-stack span ber-overflow:hidden
                                     supaya tidak pernah tumpah ke segmen sebelahnya. --}}
                                <span style="width: {{ $h / $line['available'] * 100 }}%; background: {{ $hourSegments[$key] }}" title="{{ $key }}: {{ number_format($h, 2) }} jam">{{ $h >= 1 ? number_format($h, 1) : '' }}</span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                <ul class="md-legend md-legend--inline">
                    <li><i style="background: #249360"></i><span>Milling</span></li>
                    <li><i style="background: #64748b"></i><span>Available</span></li>
                    <li><i style="background: #f59e0b"></i><span>CDT (Commercial)</span></li>
                    <li><i style="background: #60a5fa"></i><span>SDT (Scheduled)</span></li>
                    <li><i style="background: #ef4444"></i><span>EDT (Emergency)</span></li>
                </ul>
            </div>
        </article>
    </div>

    {{-- Kualitas & stok tangki --}}
    <article class="md-card">
        <header class="md-card__head">
            <h3>Kualitas &amp; Stok Tangki</h3>
            <span class="md-card__hint">Today · terhadap batas mutu</span>
        </header>
        <div class="md-tanks">
            @foreach ($tankCards as $tank)
                @php
                    $fill = round($tank['stock'] / $tank['cap'] * 100);
                    // Ambang dibandingkan terhadap nilai Today — kolom yang sama
                    // yang dicetak di bawahnya, bukan nilai MTD/YTD.
                    $warn = collect($tank['params'])->contains(fn ($param) => $param['Today'] > $param['limit']);
                @endphp
                <div class="md-tank{{ $warn ? ' md-tank--warn' : '' }}">
                    <div class="md-tank__gauge"><span style="height: {{ $fill }}%"></span><b>{{ $fill }}%</b></div>
                    <div class="md-tank__body">
                        <div class="md-tank__head">
                            <strong>{{ $tank['short'] }}</strong>
                            <span class="md-status{{ $warn ? ' md-status--warn' : '' }}">{{ $warn ? 'Perhatian' : 'Normal' }}</span>
                        </div>
                        <p class="md-tank__stock">{{ number_format($tank['stock'], 2) }} <small>/ {{ number_format($tank['cap']) }} MT</small></p>
                        <div class="md-tank__params">
                            @foreach ($tank['params'] as $param)
                                <span @class(['md-param', 'md-param--warn' => $param['Today'] > $param['limit']])>{{ $param['short'] }} <b>{{ number_format($param['Today'], 2) }}%</b> <small>≤ {{ number_format($param['limit'], 2) }}%</small></span>
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
                <span class="md-card__hint">Today</span>
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
                <span class="md-card__hint">Today · DCR vs teoritis</span>
            </header>
            <div class="md-oer">
                <p class="md-oer__big">{{ number_format($oer['actual'], 2) }}<span>%</span></p>
                <p class="md-oer__delta"><span class="md-trend md-trend--down">{{ number_format($oer['actual'] - $oer['theoretical'], 2) }} poin</span> dari teoritis {{ number_format($oer['theoretical'], 2) }}%</p>
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
