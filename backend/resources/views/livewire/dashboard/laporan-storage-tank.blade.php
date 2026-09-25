{{--
    screen-133--laporan-storage-tank-web — Laporan Periode Storage Tank.

    Susunan mengikuti mock .asdlc/generated/2-business-spec/screens/html/
    screen-133--laporan-storage-tank-web.html: hero, baris filter, kartu
    KELENGKAPAN PENCATATAN (sengaja di ATAS seluruh angka lain), 3 kartu stok
    (awal, akhir, pergerakan bersih), 4 kartu mutu minyak (FFA, kadar air,
    kotoran, DOBI), kartu suhu rata-rata berdampingan dengan tren harian stok,
    SATU grafik garis tiga seri untuk tren mutu, rekap per tangki, lalu rekap
    harian yang dapat dibuka/tutup. Yang ditiru adalah SUSUNAN dan kepadatan
    informasinya — kosakata kelasnya `md-*` milik aplikasi
    (dashboard/partials/report-styles.blade.php).

    CSS TIDAK di-include di sini: partial report-styles memancarkan <style>
    dan Livewire 3 memasang wire:id pada elemen ter-render PERTAMA, sehingga
    menaruhnya di dalam/di atas root komponen mematikan seluruh wire:model.
    Partial itu dimuat lewat <x-slot:styles> di
    dashboard/laporan-storage-tank.blade.php.

    EMPAT HAL YANG MEMBEDAKAN LAYAR INI DARI LAPORAN STASIUN LAIN:

    1. STOK BUKAN AGREGASI, melainkan PERBANDINGAN DUA PEMBACAAN: yang
       pertama dan yang terakhir di dalam periode, PER TANGKI, diurutkan
       menurut (tanggal, slot waktu). Bukan nilai terendah dan tertinggi, dan
       bukan pula baris pertama/terakhir menurut urutan penyimpanan. Karena
       itu TANGGAL kedua pembacaan itu dirender berdampingan dengan angkanya
       di SETIAP tempat angkanya muncul — stok awal yang baru terambil pada
       hari ketiga berarti dua hari pertama tidak tercatat, dan tanpa
       tanggalnya pembaca tidak punya cara mengetahui itu.

    2. PERGERAKAN DIHITUNG PER TANGKI LALU DIJUMLAHKAN, bukan dari selisih
       stok gabungan. Tabel rekap per tangki — yang kolomnya persis dijumlah
       menjadi angka pada kartu — dirender tepat di bawahnya supaya keduanya
       dapat dicocokkan. Tangki dengan SATU pembacaan stok ditulis "tidak
       dapat dihitung", BUKAN 0: nol berarti "stok tidak berubah", klaim yang
       berbeda dan lebih kuat. Pergerakan negatif ditulis apa adanya dengan
       tanda minus dan warna teks biasa — minyak dikirim keluar adalah
       keadaan yang wajar.

    3. SUHU RATA-RATA DIAMBIL DARI KOLOM YANG DICATAT OPERATOR, tidak pernah
       dihitung ulang dari suhu atas/tengah/bawah. Bila kolom itu kosong,
       kartunya berbunyi tidak tersedia — layar tidak menurunkan angka apa pun
       dari ketiga suhu posisi.

    4. FFA, KADAR AIR, DAN DOBI PADA SATU GRAFIK, karena yang menunjukkan
       mutu memburuk adalah ARAH KETIGANYA BERSAMAAN. Skalanya tidak
       sebanding (DOBI ~2-4 tanpa satuan, FFA ~3-5%, kadar air ~0,1-0,3%),
       jadi tiap seri DINORMALKAN menjadi indeks terhadap rata-rata periode
       metrik itu sendiri (100 = rata-rata), dan hal itu DINYATAKAN di judul
       kartu, di tiap baris legenda, dan di kotak keterangannya. Nilai
       aslinya tetap lengkap pada rekap harian, sehingga normalisasi ini
       tidak menyembunyikan apa pun.

    TIDAK ADA PENANDAAN NILAI DI LUAR BATAS di layar ini — tanpa kartu
    ambang, tanpa warna aman/bahaya, tanpa outlier, tanpa IQR, dan tanpa
    warna peringatan pada pergerakan negatif. Storage Tank tidak punya master
    target operasional (tidak ada StorageTankOperationalTarget), jadi ambang
    apa pun di sini hanyalah turunan statistik dari data periode itu sendiri.
    FFA dan kadar air justru PUNYA batas mutu yang lazim dikenal di industri,
    dan itulah yang membuat penandaan di sini berbahaya: angka turunan akan
    dibaca sebagai batas mutu resmi padahal sistem ini tidak pernah
    mencatatnya. Penilaian ada pada pembaca. Kartu yang memuat nilai ekstrem
    memakai atribut kelas yang SAMA PERSIS dengan kartu biasa — tidak ada
    satu pun cabang kondisional pada atribut class. Kotak keterangan di bawah
    memakai kelas `.md-explain`, bukan `.md-threshold`, karena ketiadaan
    penandaan diasersi MENURUT NAMA pada HTML ter-render. Alasan lengkapnya
    ada di docblock App\Services\StorageTankReportService.

    Bacaan saja: tidak ada satu pun tombol/field yang mengubah data stasiun.
--}}
@php
    // Angka dengan jumlah desimal tetap. null SELALU menjadi "tidak
    // tersedia", TIDAK PERNAH 0 — nol berarti "terukur dan hasilnya nol",
    // sedangkan tidak tersedia berarti "tidak pernah diukur". Aturan ini
    // berlaku di seluruh halaman: kartu, tabel, maupun kaki tabel.
    //
    // Nol di belakang koma dipangkas (dengan minimal satu desimal tetap
    // dipertahankan) supaya satu pemformat melayani metrik berskala sangat
    // berbeda tanpa berbohong: kadar air 0,207% tetap tiga desimal,
    // sementara rata-rata 0,2% tidak ditulis 0,200 seolah diukur sampai
    // tiga desimal.
    $nilai = function ($value, int $digits = 1) {
        if ($value === null) {
            return 'tidak tersedia';
        }

        $teks = number_format((float) $value, $digits, ',', '.');

        if ($digits > 1 && str_contains($teks, ',')) {
            $teks = rtrim($teks, '0');

            if (str_ends_with($teks, ',')) {
                $teks .= '0';
            }
        }

        return $teks;
    };

    // Pergerakan stok: tanda ditulis EKSPLISIT (+ / -) karena yang dibaca
    // adalah arahnya. Nilai negatif TIDAK diambil nilai absolutnya, TIDAK
    // dibulatkan ke nol, dan TIDAK diberi kelas peringatan apa pun.
    // Dua keadaan yang SENGAJA dibedakan dan tidak boleh menjadi nol:
    //   - tangki punya pembacaan stok tetapi hanya SATU  -> "tidak dapat
    //     dihitung" (ada angkanya, tetapi selisih dua pembacaan tidak ada);
    //   - tidak ada pembacaan stok sama sekali            -> "tidak tersedia".
    // Keduanya berbeda dari 0, yang berarti "stok tidak berubah" — klaim yang
    // lebih kuat dan tidak pernah diukur.
    $pergerakan = function ($value, bool $computable = true, int $digits = 1) use ($nilai) {
        if ($value === null) {
            return $computable ? 'tidak tersedia' : 'tidak dapat dihitung';
        }

        $angka = $nilai(abs((float) $value), $digits);

        if ((float) $value > 0) {
            return '+'.$angka;
        }

        if ((float) $value < 0) {
            return '-'.$angka;
        }

        return $angka;
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

    // "01 Sep 06:00" — tanggal DAN jam pembacaan, karena keduanya bersama
    // yang menjawab "rentang waktu apa yang sebenarnya diselisihkan".
    $tglJam = function (?string $at) use ($bulan) {
        if ($at === null || $at === '') {
            return 'tidak tersedia';
        }

        [$y, $m, $d] = array_pad(explode('-', substr($at, 0, 10)), 3, '');
        $jam = trim(substr($at, 10));

        return trim($d.' '.($bulan[$m] ?? $m).' '.$jam);
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
    $stock = $summary['stock'] ?? null;
    $metrics = $summary['metrics'] ?? [];
    $byTank = $summary['by_tank'] ?? [];
    $daily = $summary['daily'] ?? [];
    $total = $summary['total'] ?? null;
    $hasData = (bool) ($summary['has_data'] ?? false);
    $periodStart = $summary['period']['start_date'] ?? null;
    $periodEnd = $summary['period']['end_date'] ?? null;

    // ---------------------------------------------------------------
    // Geometri grafik garis (kosakata md-lc*, diperkenalkan screen-132).
    // Dipakai DUA KALI di halaman ini: tren stok (satu seri) dan tren mutu
    // (tiga seri).
    // ---------------------------------------------------------------
    $lcJumlah = count($daily);

    $lcX = function (int $i) use ($lcJumlah) {
        if ($lcJumlah <= 1) {
            return 396.0;
        }

        return round(46 + 700 * $i / ($lcJumlah - 1), 1);
    };

    $lcY = function ($value, float $lo, float $hi) {
        if ($hi <= $lo) {
            return 152.0;
        }

        return round(260 - 216 * (((float) $value) - $lo) / ($hi - $lo), 1);
    };

    // Sumbu yang TIDAK dimulai dari nol, dengan bantalan 12% di kedua ujung.
    // Semata skala tampilan: tidak ada satu pun nilai yang ditandai
    // aman/bahaya karenanya, dan pilihannya dinyatakan pada legenda kartunya.
    $lcSumbu = function (array $values, float $minPad) {
        if ($values === []) {
            return [0.0, 0.0];
        }

        $min = min($values);
        $maks = max($values);
        $pad = max($minPad, ($maks - $min) * 0.12);
        $lo = $min - $pad;
        $hi = $maks + $pad;

        if ($hi <= $lo) {
            $lo -= $minPad;
            $hi += $minPad;
        }

        return [$lo, $hi];
    };

    // ---------------------------------------------------------------
    // Tren harian stok total.
    // ---------------------------------------------------------------
    $stokHarian = array_values(array_filter(
        array_map(fn ($row) => $row['stock_total_mt'], $daily),
        fn ($v) => $v !== null,
    ));
    $adaGrafikStok = $stokHarian !== [];
    [$stokLo, $stokHi] = $lcSumbu($stokHarian, 5.0);
    $stokMin = $adaGrafikStok ? min($stokHarian) : null;
    $stokMaks = $adaGrafikStok ? max($stokHarian) : null;

    // ---------------------------------------------------------------
    // Tren mutu minyak — TIGA SERI, SATU BIDANG GAMBAR, DINORMALKAN.
    //
    // Skala ketiganya tidak sebanding, jadi yang digambar bukan nilai
    // aslinya melainkan INDEKS terhadap rata-rata periode metrik itu
    // sendiri: indeks = nilai / rata-rata x 100, sehingga 100 berarti sama
    // dengan rata-rata periode dan 110 berarti 10% di atasnya. Yang
    // dibandingkan adalah BESAR PERUBAHAN RELATIF, bukan nilai mutlaknya —
    // dan nilai mutlaknya tetap tersedia lengkap pada rekap harian di bawah.
    //
    // Seri yang rata-ratanya null atau nol tidak dapat dinormalkan dan
    // sengaja tidak digambar, bukan digambar dengan indeks palsu.
    // ---------------------------------------------------------------
    $seriMutu = [
        ['cls' => 's1', 'kolom' => 'ffa_avg', 'metrik' => 'ffa_percent', 'label' => 'FFA', 'satuan' => '%', 'digits' => 2, 'pola' => 'garis utuh'],
        ['cls' => 's2', 'kolom' => 'moisture_avg', 'metrik' => 'moisture_content_percent', 'label' => 'Kadar Air', 'satuan' => '%', 'digits' => 3, 'pola' => 'garis putus-putus'],
        ['cls' => 's3', 'kolom' => 'dobi_avg', 'metrik' => 'dobi_index', 'label' => 'DOBI', 'satuan' => '', 'digits' => 2, 'pola' => 'garis titik-titik'],
    ];

    $indeksMutu = [];
    $semuaIndeks = [];

    foreach ($seriMutu as $i => $s) {
        $rataMetrik = $metrics[$s['metrik']]['avg'] ?? null;
        $indeksMutu[$i] = [];

        if ($rataMetrik === null || (float) $rataMetrik == 0.0) {
            continue;
        }

        foreach ($daily as $j => $row) {
            if (($row[$s['kolom']] ?? null) === null) {
                continue;
            }

            $indeks = round(100 * (float) $row[$s['kolom']] / (float) $rataMetrik, 1);
            $indeksMutu[$i][$j] = $indeks;
            $semuaIndeks[] = $indeks;
        }
    }

    $adaGrafikMutu = $semuaIndeks !== [];
    [$mutuLo, $mutuHi] = $lcSumbu($semuaIndeks, 2.0);
@endphp

<div class="md" data-testid="laporan-storage-tank">

    {{-- ============ 1. Hero: periode yang sedang dibaca ============ --}}
    <section class="md-hero" data-testid="report-hero">
        <div class="md-hero__main">
            <p class="md-hero__eyebrow">Laporan Periode &middot; Stasiun Storage Tank</p>
            <h1 class="md-hero__title">{{ $selectedPeriod['name'] ?? 'Laporan Storage Tank' }}</h1>
            <p class="md-hero__subtitle">
                @if ($selectedPeriod)
                    {{ $summary['period']['business_unit_name'] ?? '' }}
                    @if (! empty($summary['period']['business_unit_name'])) &middot; @endif
                    {{ $tgl($selectedPeriod['start_date']) }} &ndash; {{ $tgl($selectedPeriod['end_date']) }}
                @else
                    Stok awal, stok akhir, pergerakan per tangki, dan mutu minyak sepanjang satu Periode Pelaporan
                @endif
            </p>
        </div>
        @if ($selectedPeriod)
            <div class="md-hero__meta">
                <span class="md-chip md-chip--date" data-testid="hero-range">
                    {{ $tgl($selectedPeriod['start_date']) }} &ndash; {{ $tgl($selectedPeriod['end_date']) }}
                </span>
                @if ($coverage !== null)
                    <span class="md-chip md-chip--date" data-testid="hero-tank-count">
                        {{ $cacah($coverage['tank_count']) }} tangki
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
                        wire:model.live="businessUnitId" data-testid="mill-select">
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
                {{-- Saat mill belum punya periode yang mencakup Storage Tank,
                     pemilih ini sengaja dirender TANPA satu pun <option> —
                     bukan dengan option semu "belum ada periode" — dan
                     arahannya ditulis terpisah sebagai no-period-hint. --}}
                <select id="period-select" class="md-field__control"
                        wire:model.live="periodId" data-testid="period-select">
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
                        wire:click="exportCsv('csv')" data-testid="export-button">
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
            Daftar periode hanya memuat periode yang mencakup Storage Tank, yaitu periode berjenis
            Storage Tank maupun periode yang berlaku untuk semua jenis stasiun. Periode tertutup tetap
            dapat dilihat dan diekspor.
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
        <div class="md-empty" data-testid="mill-select-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
            </span>
            <p class="md-empty__title">Pilih mill terlebih dahulu</p>
            <p class="md-empty__text">
                Sebagai Admin Anda tidak terikat pada satu mill. Pilih mill pada pemilih di atas
                untuk menampilkan daftar Periode Pelaporan dan laporan Storage Tank-nya.
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
    @elseif ($periods === [])
        {{-- Empty state (d): mill belum punya Periode Pelaporan yang mencakup
             Storage Tank. Bukan 404 — cukup arahkan ke layar yang
             membuatnya. --}}
        <div class="md-empty" data-testid="no-period-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
            </span>
            <p class="md-empty__title">Belum ada Periode Pelaporan</p>
            <p class="md-empty__text">
                Mill ini belum memiliki Periode Pelaporan yang mencakup stasiun Storage Tank.
                Hubungi Admin agar membuatnya terlebih dahulu di layar Kelola Periode Pelaporan,
                lalu laporan periode akan tampil di sini.
            </p>
        </div>
    @elseif ($summary !== null)

        {{-- ============ 3. Kelengkapan pencatatan ============
             SENGAJA DI ATAS SELURUH ANGKA LAIN. Di layar ini kelengkapan
             bukan sekadar konteks: stok awal dan akhir adalah PERBANDINGAN
             DUA PEMBACAAN, sehingga kelengkapanlah yang menentukan seberapa
             jauh kedua pembacaan itu mewakili ujung-ujung periodenya. Ini
             bagian isi laporan, bukan catatan kaki. --}}
        <section class="md-card" data-testid="coverage-card">
            <header class="md-card__head">
                <h3>Kelengkapan Pencatatan</h3>
                <span class="md-card__hint">Baca ini lebih dulu &mdash; kelengkapanlah yang menentukan seberapa jauh stok awal dan akhir mewakili ujung periodenya</span>
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
                    <span class="md-budget__pct" data-testid="coverage-percent">{{ $nilai($coverage['coverage_percent'], 2) }}%</span>
                    <div class="md-bar md-bar--lg"><span style="width: {{ min(100, max(0, (float) $coverage['coverage_percent'])) }}%"></span></div>
                </div>
            </div>
            @if ($coverage['expected_slots'] > $coverage['filled_slots'])
                {{-- Penekanan atas slot yang TIDAK terisi. Ini bukan penandaan
                     nilai di luar batas: tidak ada satu pun nilai pengukuran
                     yang dinilai di sini, yang dinyatakan hanyalah berapa
                     banyak slot yang tidak pernah dicatat dan apa artinya. --}}
                <div class="md-explain" data-testid="low-coverage-emphasis">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                    <span>
                        <b>Stok awal dan akhir bukan hasil penjumlahan banyak pembacaan, melainkan perbandingan dua pembacaan</b> &mdash;
                        yang pertama dan yang terakhir di dalam periode, per tangki. Karena itu yang menentukan bukan
                        berapa banyak data yang ada, melainkan <b>kapan pembacaan pertama dan terakhirnya diambil</b>,
                        dan tanggal keduanya ikut ditampilkan di setiap tempat angkanya muncul.
                        {{ $cacah($coverage['expected_slots'] - $coverage['filled_slots']) }} slot tidak terisi &mdash;
                        slot kosong tidak pernah dihitung sebagai 0 dan tidak masuk penyebut rata-rata mana pun.
                        <small>
                            Slot yang diharapkan = {{ $cacah($coverage['tank_count']) }} tangki
                            &times; {{ $cacah($coverage['days_in_period']) }} hari
                            &times; {{ $cacah($coverage['slots_per_tank_per_day']) }} slot.
                        </small>
                    </span>
                </div>
            @endif
        </section>

        {{-- ============ 4. Stok awal, stok akhir, pergerakan bersih ============
             Ketiga kartu memakai atribut kelas yang SAMA PERSIS, termasuk
             kartu pergerakan saat nilainya negatif. --}}
        <section class="md-kpis md-kpis--3" data-testid="report-stock">
            <article class="md-kpi" data-testid="stock-opening-card">
                <div class="md-kpi__top">
                    <span class="md-kpi__label">Stok Awal Periode</span>
                    <span class="md-kpi__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M5 9h14"/><path d="M9 14h6"/></svg>
                    </span>
                </div>
                <p class="md-kpi__value">
                    <span data-testid="stock-opening-mt">{{ $nilai($stock['opening_mt'], 1) }}</span>
                    <span>MT</span>
                </p>
                {{-- TANGGAL PEMBACAAN, di dalam kartu yang sama. Stok awal yang
                     baru terambil pada hari ketiga periode berarti dua hari
                     pertama tidak tercatat, dan angka pergerakannya menutupi
                     kurun yang lebih pendek daripada periodenya. --}}
                <p class="md-kpi__meta">
                    pembacaan pertama <span data-testid="opening-at">{{ $tglJam($stock['opening_at']) }}</span>
                </p>
                <p class="md-kpi__foot">
                    jumlah stok awal <b>{{ $cacah($coverage['tank_count']) }}</b> tangki, masing-masing dari pembacaan stok pertamanya sendiri
                    <small>Yang ditampilkan adalah pembacaan paling awal di antara seluruh tangki; tanggal tiap tangki ada pada rekap per tangki.</small>
                </p>
            </article>

            <article class="md-kpi" data-testid="stock-closing-card">
                <div class="md-kpi__top">
                    <span class="md-kpi__label">Stok Akhir Periode</span>
                    <span class="md-kpi__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M5 15h14"/><path d="M9 19h6"/></svg>
                    </span>
                </div>
                <p class="md-kpi__value">
                    <span data-testid="stock-closing-mt">{{ $nilai($stock['closing_mt'], 1) }}</span>
                    <span>MT</span>
                </p>
                <p class="md-kpi__meta">
                    pembacaan terakhir <span data-testid="closing-at">{{ $tglJam($stock['closing_at']) }}</span>
                </p>
                <p class="md-kpi__foot">
                    jumlah stok akhir <b>{{ $cacah($coverage['tank_count']) }}</b> tangki
                    <small>Bukan nilai terendah atau tertinggi, dan bukan pula baris terakhir menurut urutan penyimpanan &mdash; melainkan pembacaan paling akhir menurut tanggal lalu slot waktu.</small>
                </p>
            </article>

            <article class="md-kpi" data-testid="stock-movement-card">
                <div class="md-kpi__top">
                    <span class="md-kpi__label">Pergerakan Bersih</span>
                    <span class="md-kpi__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M4 17h16"/><path d="M8 3L4 7l4 4"/><path d="M16 13l4 4-4 4"/></svg>
                    </span>
                </div>
                {{-- Tanda minus ditulis apa adanya dengan warna teks biasa.
                     Tidak ada kelas peringatan, tidak ada ikon, tidak ada
                     pembulatan ke nol, dan tidak ada nilai absolut. --}}
                <p class="md-kpi__value">
                    <span data-testid="stock-movement-mt">{{ $pergerakan($stock['movement_mt'], true, 1) }}</span>
                    <span>MT</span>
                </p>
                <p class="md-kpi__meta">
                    dijumlahkan dari pergerakan tiap tangki &middot;
                    <span data-testid="tanks-with-movement">{{ $cacah($stock['tanks_with_movement']) }}</span> tangki terhitung,
                    <span data-testid="tanks-without-movement">{{ $cacah($stock['tanks_without_movement']) }}</span> tangki tidak dapat dihitung
                </p>
                <p class="md-kpi__foot">
                    <b>Bukan</b> selisih stok gabungan
                    <small>
                        Pergerakan dihitung PER TANGKI lalu dijumlahkan. Menghitungnya dari stok gabungan akan salah
                        bila jumlah tangki yang tercatat berbeda antara awal dan akhir periode &mdash; ia mencampurkan
                        perubahan stok dengan perubahan cakupan pencatatan. Nilai negatif berarti stok berkurang,
                        keadaan yang wajar dan bukan kesalahan. Tangki dengan satu pembacaan tidak menyumbang apa pun
                        di sini, karena pergerakannya tidak dapat dihitung &mdash; itu bukan nol.
                    </small>
                </p>
            </article>
        </section>

        {{-- ============ 5. Mutu minyak ============
             Setiap kartu membawa JUMLAH PEMBACAANNYA SENDIRI: kesepuluh metrik
             punya penyebut yang berbeda-beda karena satu baris boleh mengisi
             FFA dan mengosongkan DOBI. Tidak ada satu label jumlah pembacaan
             yang berlaku untuk semuanya.

             Seluruh kartu di bawah memakai atribut kelas yang SAMA PERSIS,
             termasuk kartu yang kebetulan memuat nilai ekstrem: tidak ada satu
             pun cabang kondisional pada atribut class di layar ini. --}}
        <section class="md-kpis md-kpis--4" data-testid="report-quality">
            @foreach ([
                ['key' => 'ffa_percent', 'testid' => 'metric-card-ffa', 'slug' => 'ffa', 'label' => 'FFA', 'satuan' => '% rata-rata', 'digits' => 2,
                 'foot' => 'Kadar asam lemak bebas. Penyebutnya hanya baris yang kolom FFA-nya terisi.'],
                ['key' => 'moisture_content_percent', 'testid' => 'metric-card-moisture', 'slug' => 'moisture', 'label' => 'Kadar Air', 'satuan' => '% rata-rata', 'digits' => 3,
                 'foot' => 'Satu baris dapat mencatat FFA tetapi mengosongkan kadar air, jadi penyebutnya berbeda dari kartu di sebelahnya.'],
                ['key' => 'impurities_dirt_percent', 'testid' => 'metric-card-impurities', 'slug' => 'impurities', 'label' => 'Kotoran', 'satuan' => '% rata-rata', 'digits' => 3,
                 'foot' => 'Termasuk kolom yang paling sering dikosongkan, jadi penyebutnya biasanya paling kecil di antara metrik mutu.'],
                ['key' => 'dobi_index', 'testid' => 'metric-card-dobi', 'slug' => 'dobi', 'label' => 'DOBI', 'satuan' => 'rata-rata', 'digits' => 2,
                 'foot' => 'DOBI tidak bersatuan persen dan skalanya berbeda dari ketiga metrik lain &mdash; itulah sebabnya grafik tren di bawah dinormalkan.'],
            ] as $kartu)
                @php $m = $metrics[$kartu['key']]; @endphp
                <article class="md-kpi" data-testid="{{ $kartu['testid'] }}">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">{{ $kartu['label'] }}</span>
                        <span class="md-kpi__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21a6 6 0 0 0 6-6c0-4.5-6-11-6-11S6 10.5 6 15a6 6 0 0 0 6 6z"/></svg>
                        </span>
                    </div>
                    {{-- null ditulis "tidak tersedia", TIDAK PERNAH 0 maupun 0,00. --}}
                    <p class="md-kpi__value">
                        <span data-testid="metric-{{ $kartu['slug'] }}-avg">{{ $nilai($m['avg'], $kartu['digits']) }}</span>
                        <span>{{ $kartu['satuan'] }}</span>
                    </p>
                    <p class="md-kpi__meta">
                        terendah <span data-testid="metric-{{ $kartu['slug'] }}-min">{{ $nilai($m['min'], $kartu['digits']) }}</span>
                        &middot; tertinggi <span data-testid="metric-{{ $kartu['slug'] }}-max">{{ $nilai($m['max'], $kartu['digits']) }}</span>
                    </p>
                    <p class="md-kpi__foot">
                        dari <b data-testid="metric-{{ $kartu['slug'] }}-reading-count">{{ $cacah($m['reading_count']) }}</b> pembacaan
                        <small>{!! $kartu['foot'] !!}</small>
                    </p>
                </article>
            @endforeach
        </section>

        @if (! $hasData)
            {{-- Empty state (e): periode valid tetapi tidak memuat satu pun
                 pembacaan terisi. Seluruh angka di atas sudah tampil sebagai
                 "tidak tersedia" (bukan nol); kartu suhu, kedua grafik, rekap
                 per tangki, dan rekap harian TIDAK digambar sama sekali —
                 grafik kosong akan terbaca sebagai garis datar yang terukur. --}}
            <div class="md-empty" data-testid="empty-state">
                <span class="md-empty__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19h16M7 16V9M12 16V5M17 16v-4"/></svg>
                </span>
                <p class="md-empty__title">Belum ada data pada periode ini</p>
                <p class="md-empty__text">
                    Tidak ada satu pun pembacaan Storage Tank tercatat pada rentang tanggal periode ini,
                    sehingga seluruh angka ditampilkan sebagai tidak tersedia &mdash; bukan sebagai nol &mdash;
                    dan kedua grafik, rekap per tangki, serta rekap harian tidak digambar.
                </p>
            </div>
        @else

            {{-- ============ 6. Suhu rata-rata + tren harian stok ============ --}}
            <div class="md-row md-row--2">
                <section class="md-card" data-testid="metric-card-temperature">
                    <header class="md-card__head">
                        <h3>Suhu Rata-rata Minyak</h3>
                        <span class="md-card__hint">Sepanjang periode, dalam &deg;C</span>
                    </header>
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Suhu rata-rata minyak sepanjang periode</span>
                        <span class="md-kpi__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 14.8V4a2 2 0 1 0-4 0v10.8a4 4 0 1 0 4 0z"/></svg>
                        </span>
                    </div>
                    <p class="md-kpi__value">
                        <span data-testid="metric-temperature-avg">{{ $nilai($metrics['average_temperature_c']['avg'], 1) }}</span>
                        <span>&deg;C rata-rata</span>
                    </p>
                    <p class="md-kpi__meta">
                        terendah <span data-testid="metric-temperature-min">{{ $nilai($metrics['average_temperature_c']['min'], 1) }}</span>
                        &middot; tertinggi <span data-testid="metric-temperature-max">{{ $nilai($metrics['average_temperature_c']['max'], 1) }}</span>
                    </p>
                    <p class="md-kpi__foot">
                        dari <b data-testid="metric-temperature-reading-count">{{ $cacah($metrics['average_temperature_c']['reading_count']) }}</b> pembacaan
                    </p>
                    {{-- Kotak KETERANGAN (.md-explain), bukan ambang. --}}
                    <div class="md-explain" data-testid="temperature-source-note">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                        <span>
                            Angka ini berasal dari <b>kolom suhu rata-rata yang dicatat Operator</b>, bukan dihitung ulang
                            dari suhu atas, tengah, dan bawah. Menghitungnya ulang akan menciptakan kebenaran kedua yang
                            menyimpang diam-diam dari yang dilihat Operator di layar input, dan selisihnya tidak akan
                            pernah terlihat karena keduanya sama-sama masuk akal. Bila kolom itu kosong, kartu ini
                            berbunyi tidak tersedia &mdash; layar tidak menurunkan angka apa pun dari ketiga suhu posisi.
                            Suhu atas, tengah, dan bawah tetap metrik tersendiri dengan penyebutnya masing-masing,
                            dan angkanya sengaja tidak ditampilkan di sini agar tidak terbaca sebagai pembanding
                            bagi kartu ini.
                        </span>
                    </div>
                </section>

                <section class="md-card" data-testid="stock-trend-card">
                    <header class="md-card__head">
                        <h3>Tren Harian Stok Total</h3>
                        <span class="md-card__hint">
                            Stok seluruh tangki per tanggal, dalam MT &middot; {{ count($daily) }} tanggal berdata &middot; sumbu tegak tidak dimulai dari nol
                        </span>
                    </header>
                    @if ($adaGrafikStok)
                        <div class="md-lc" data-testid="stock-trend-chart">
                            <svg class="md-lc__svg" viewBox="0 0 760 300" role="img"
                                 aria-label="Tren harian stok total seluruh tangki sepanjang {{ count($daily) }} tanggal, dalam metrik ton">
                                @for ($k = 0; $k < 5; $k++)
                                    @php
                                        $tickNilai = $stokLo + ($stokHi - $stokLo) * $k / 4;
                                        $tickY = round(260 - 216 * $k / 4, 1);
                                    @endphp
                                    <line class="md-lc__grid" x1="46" y1="{{ $tickY }}" x2="746" y2="{{ $tickY }}"/>
                                    <text class="md-lc__ytick" x="38" y="{{ $tickY + 4 }}" text-anchor="end">{{ $nilai($tickNilai, 0) }}</text>
                                @endfor
                                <line class="md-lc__axis" x1="46" y1="260" x2="746" y2="260"/>
                                @foreach ($daily as $i => $row)
                                    <text class="md-lc__xtick" x="{{ $lcX($i) }}" y="280" text-anchor="middle">{{ $tglAngka($row['date']) }}</text>
                                @endforeach
                                @php
                                    $titikStok = [];
                                    foreach ($daily as $i => $row) {
                                        if (($row['stock_total_mt'] ?? null) !== null) {
                                            $titikStok[] = [$lcX($i), $lcY($row['stock_total_mt'], $stokLo, $stokHi)];
                                        }
                                    }
                                @endphp
                                @if (count($titikStok) > 1)
                                    <polyline class="md-lc__line md-lc__line--s1"
                                              points="{{ collect($titikStok)->map(fn ($p) => $p[0].','.$p[1])->implode(' ') }}"/>
                                @endif
                                @foreach ($titikStok as $p)
                                    <circle class="md-lc__dot md-lc__dot--s1" cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="3"/>
                                @endforeach
                            </svg>
                        </div>
                        <ul class="md-legend" data-testid="stock-trend-legend">
                            <li class="md-legend__item">
                                <span class="md-legend__swatch md-legend__swatch--s1"></span>
                                Stok total {{ $cacah($coverage['tank_count']) }} tangki &mdash;
                                terendah {{ $nilai($stokMin, 1) }} MT &middot; tertinggi {{ $nilai($stokMaks, 1) }} MT
                            </li>
                            <li class="md-legend__item">
                                <small>
                                    <b>Sumbu tegak tidak dimulai dari nol</b> ({{ $nilai($stokLo, 0) }} &ndash; {{ $nilai($stokHi, 0) }} MT) &mdash;
                                    pergerakan sepanjang periode jauh lebih kecil daripada stoknya, sehingga pada sumbu yang
                                    mulai dari nol garisnya akan terlihat datar sama sekali. Yang dibaca di sini adalah arah
                                    perubahannya, bukan besaran mutlaknya. Tiap tanggal memakai pembacaan stok terakhir tiap
                                    tangki pada tanggal itu, lalu dijumlahkan.
                                </small>
                            </li>
                        </ul>
                    @else
                        <p class="md-filters__hint" data-testid="stock-trend-unavailable">
                            Tidak ada satu pun pembacaan stok tercatat pada periode ini, jadi grafiknya tidak digambar
                            &mdash; grafik kosong akan terbaca sebagai garis datar yang terukur.
                        </p>
                    @endif
                </section>
            </div>

            {{-- ============ 7. SATU grafik untuk FFA, kadar air, dan DOBI ============
                 Satu bidang gambar, karena yang menunjukkan mutu memburuk
                 adalah ARAH KETIGANYA BERSAMAAN, bukan salah satunya
                 sendirian. Elemen quality-trend-chart hanya ada SATU di
                 halaman ini dan tidak ada grafik mutu per metrik di mana pun.

                 SKALANYA TIDAK SEBANDING, jadi yang digambar adalah INDEKS
                 terhadap rata-rata periode tiap metrik (100 = rata-rata). Hal
                 itu dinyatakan di judul kartu, di TIAP baris legenda, dan di
                 kotak keterangan. Pembeda antar seri dua lapis: warna DAN
                 pola garis. --}}
            <section class="md-card" data-testid="quality-trend-card">
                <header class="md-card__head">
                    <h3>Tren Mutu Minyak</h3>
                    <span class="md-card__hint">
                        FFA, kadar air, dan DOBI pada satu bidang gambar &middot; {{ count($daily) }} tanggal berdata &middot;
                        sumbu tegak dinormalkan menjadi indeks terhadap rata-rata periode tiap metrik
                    </span>
                </header>
                @if ($adaGrafikMutu)
                    <div class="md-lc" data-testid="quality-trend-chart">
                        <svg class="md-lc__svg" viewBox="0 0 760 300" role="img"
                             aria-label="Tren harian mutu minyak: FFA, kadar air, dan DOBI sepanjang {{ count($daily) }} tanggal, ketiganya dinormalkan menjadi indeks terhadap rata-rata periodenya sendiri">
                            @for ($k = 0; $k < 5; $k++)
                                @php
                                    $tickNilai = $mutuLo + ($mutuHi - $mutuLo) * $k / 4;
                                    $tickY = round(260 - 216 * $k / 4, 1);
                                @endphp
                                <line class="md-lc__grid" x1="46" y1="{{ $tickY }}" x2="746" y2="{{ $tickY }}"/>
                                <text class="md-lc__ytick" x="38" y="{{ $tickY + 4 }}" text-anchor="end">{{ $nilai($tickNilai, 0) }}</text>
                            @endfor
                            <line class="md-lc__axis" x1="46" y1="260" x2="746" y2="260"/>
                            @foreach ($daily as $i => $row)
                                <text class="md-lc__xtick" x="{{ $lcX($i) }}" y="280" text-anchor="middle">{{ $tglAngka($row['date']) }}</text>
                            @endforeach

                            @foreach ($seriMutu as $idx => $s)
                                @php
                                    $titik = [];
                                    foreach ($indeksMutu[$idx] as $j => $indeks) {
                                        $titik[] = [$lcX($j), $lcY($indeks, $mutuLo, $mutuHi)];
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
                    <ul class="md-legend" data-testid="quality-trend-legend">
                        @foreach ($seriMutu as $s)
                            <li class="md-legend__item">
                                <span class="md-legend__swatch md-legend__swatch--{{ $s['cls'] }}"></span>
                                {{ $s['label'] }} ({{ $s['pola'] }}) &mdash; dinormalkan,
                                rata-rata periode {{ $nilai($metrics[$s['metrik']]['avg'], $s['digits']) }}{{ $s['satuan'] }}
                                = indeks 100, {{ $cacah($metrics[$s['metrik']]['reading_count']) }} pembacaan
                            </li>
                        @endforeach
                        <li class="md-legend__item">
                            <small>
                                <b>Sumbu tegak bukan nilai asli, melainkan indeks yang dinormalkan terhadap rata-rata
                                periode metrik itu sendiri</b> (100 = rata-rata periode metrik itu, 110 = 10% di atasnya),
                                rentang {{ $nilai($mutuLo, 0) }} &ndash; {{ $nilai($mutuHi, 0) }}.
                                Nilai aslinya ada lengkap pada rekap harian di bawah.
                            </small>
                        </li>
                    </ul>
                    <div class="md-explain" data-testid="quality-trend-note">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                        <span>
                            Ketiga metrik sengaja berbagi satu bidang gambar karena
                            <b>yang menunjukkan mutu memburuk adalah ARAH KETIGANYA BERSAMAAN</b>, bukan salah satunya
                            sendirian: FFA naik dan kadar air naik sementara DOBI turun.
                            <b>Skalanya tidak sebanding</b> &mdash; DOBI berkisar 2&ndash;4 tanpa satuan, FFA 3&ndash;5%,
                            kadar air 0,1&ndash;0,3%. Menempelkan ketiganya pada satu sumbu nilai akan membuat kadar air
                            tergambar sebagai garis datar di dasar dan perubahannya hilang. Karena itu
                            <b>tiap seri dinormalkan menjadi indeks terhadap rata-ratanya sendiri</b>; yang dibandingkan
                            adalah besar perubahan relatif, bukan nilai mutlaknya, dan nilai mutlaknya tetap tersedia
                            lengkap pada rekap harian.
                            <b>Tidak ada nilai yang ditandai di luar batas di layar ini</b> &mdash; Storage Tank tidak punya
                            master target operasional, dan menurunkan ambang mutu dari data periode itu sendiri berisiko
                            dibaca sebagai batas mutu resmi padahal bukan.
                        </span>
                    </div>
                @else
                    <p class="md-filters__hint" data-testid="quality-trend-unavailable">
                        Tidak ada satu pun pembacaan FFA, kadar air, maupun DOBI pada periode ini, jadi grafiknya tidak
                        digambar &mdash; grafik kosong akan terbaca sebagai garis datar yang terukur.
                    </p>
                @endif
            </section>

            {{-- ============ 8. Rekap per tangki ============
                 Tabel inilah asal angka pergerakan bersih di atas: kolom
                 Pergerakan dijumlahkan menjadi angka pada kartunya, dan
                 keduanya sengaja dapat dicocokkan pembaca. --}}
            <section class="md-card" data-testid="by-tank-card">
                <header class="md-card__head">
                    <h3>Rekap per Tangki</h3>
                    <span class="md-card__hint">
                        Pergerakan dihitung per tangki lalu dijumlahkan &mdash; kolom Pergerakan di bawah adalah asal angka pada kartu Pergerakan Bersih
                    </span>
                </header>
                <div class="md-recap">
                    <table class="md-table" data-testid="by-tank-table">
                        <thead>
                            <tr>
                                <th scope="col">Tangki</th>
                                <th scope="col">Stok Awal (MT)</th>
                                <th scope="col">Waktu Awal</th>
                                <th scope="col">Stok Akhir (MT)</th>
                                <th scope="col">Waktu Akhir</th>
                                <th scope="col">Pergerakan (MT)</th>
                                <th scope="col">FFA rata2 (%)</th>
                                <th scope="col">Suhu rata2 (&deg;C)</th>
                                <th scope="col">Jumlah pembacaan</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Tangki yang punya record tetapi TIDAK SATU PUN
                                 pembacaan stoknya terisi TETAP muncul di sini
                                 dengan stok dan pergerakan tidak tersedia.
                                 Menghilangkannya akan menyembunyikan tangki
                                 yang justru tidak pernah diukur. --}}
                            @foreach ($byTank as $tank)
                                <tr data-testid="by-tank-row-{{ $tank['storage_tank_id'] }}">
                                    <td>
                                        {{ $tank['storage_tank_id'] }}
                                        @if ($tank['opening_at'] !== null && $periodStart !== null && substr($tank['opening_at'], 0, 10) > $periodStart)
                                            <small>baru tercatat sejak {{ $tglPendek(substr($tank['opening_at'], 0, 10)) }}</small>
                                        @endif
                                        @if ($tank['closing_at'] !== null && $periodEnd !== null && substr($tank['closing_at'], 0, 10) < $periodEnd)
                                            <small>terakhir tercatat {{ $tglPendek(substr($tank['closing_at'], 0, 10)) }}</small>
                                        @endif
                                    </td>
                                    <td @class(['is-muted' => $tank['opening_mt'] === null])>{{ $nilai($tank['opening_mt'], 1) }}</td>
                                    <td class="is-muted">{{ $tglJam($tank['opening_at']) }}</td>
                                    <td @class(['is-muted' => $tank['closing_mt'] === null])>{{ $nilai($tank['closing_mt'], 1) }}</td>
                                    <td class="is-muted">{{ $tglJam($tank['closing_at']) }}</td>
                                    {{-- "tidak dapat dihitung", TIDAK PERNAH 0:
                                         nol berarti "stok tidak berubah", klaim
                                         yang berbeda dan lebih kuat. Sel ini
                                         tidak pernah membawa kelas peringatan,
                                         termasuk saat nilainya negatif. --}}
                                    <td @class(['is-muted' => ! $tank['movement_computable']])>{{ $tank['opening_mt'] === null ? 'tidak tersedia' : $pergerakan($tank['movement_mt'], $tank['movement_computable'], 1) }}</td>
                                    <td @class(['is-muted' => $tank['ffa_avg'] === null])>{{ $nilai($tank['ffa_avg'], 2) }}</td>
                                    <td @class(['is-muted' => $tank['average_temperature_avg'] === null])>{{ $nilai($tank['average_temperature_avg'], 1) }}</td>
                                    <td>{{ $cacah($tank['reading_count']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr data-testid="by-tank-row-total">
                                <td>Gabungan {{ $cacah($coverage['tank_count']) }} tangki</td>
                                <td>{{ $nilai($stock['opening_mt'], 1) }}</td>
                                <td class="is-muted">{{ $tglJam($stock['opening_at']) }} <small>(paling awal)</small></td>
                                <td>{{ $nilai($stock['closing_mt'], 1) }}</td>
                                <td class="is-muted">{{ $tglJam($stock['closing_at']) }} <small>(paling akhir)</small></td>
                                <td>{{ $pergerakan($stock['movement_mt'], true, 1) }}</td>
                                <td>{{ $nilai($metrics['ffa_percent']['avg'], 2) }}</td>
                                <td>{{ $nilai($metrics['average_temperature_c']['avg'], 1) }}</td>
                                <td>{{ $cacah($total['reading_rows']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="md-explain" data-testid="by-tank-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                    <span>
                        Pergerakan tiap baris = <b>Stok Akhir &minus; Stok Awal</b> tangki itu, bukan selisih terendah dan
                        tertinggi dan bukan pula selisih stok gabungan. Waktu Awal dan Waktu Akhir sengaja berdampingan
                        dengan angkanya supaya terbaca <b>rentang waktu apa yang sebenarnya diselisihkan</b>: pembacaan
                        pertama sebuah tangki yang baru terambil beberapa hari setelah periode dimulai berarti hari-hari
                        sebelumnya tidak tercatat. Tangki dengan satu pembacaan stok berbunyi <b>tidak dapat
                        dihitung</b> &mdash; bukan nol &mdash; dan tidak menyumbang pada Pergerakan Bersih di atas.
                        <small>
                            Kolom Jumlah pembacaan adalah banyaknya slot terisi tangki itu. Setiap metrik punya
                            penyebutnya sendiri, jadi FFA rata2 dan Suhu rata2 pada baris yang sama dapat berasal dari
                            jumlah pembacaan yang berbeda.
                        </small>
                    </span>
                </div>
            </section>

            {{-- ============ 9. Rekap harian (dapat dibuka/tutup) ============
                 Periode panjang menghasilkan puluhan baris, jadi tabelnya dapat
                 ditutup agar angka utama dan kedua grafik tetap terbaca tanpa
                 gulir panjang. Tombol, bukan <details> bawaan: keadaannya harus
                 satu sumber (properti Livewire) agar tabel benar-benar hilang
                 dari DOM saat ditutup, bukan sekadar tersembunyi.

                 NILAI ASLI ketiga metrik mutu ada di sini, lengkap — itulah
                 yang membuat normalisasi pada grafik di atas tidak
                 menyembunyikan apa pun. --}}
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
                                    <th scope="col">Tanggal</th>
                                    <th scope="col">Slot Terisi</th>
                                    <th scope="col">Stok Total (MT)</th>
                                    <th scope="col">FFA rata2 (%)</th>
                                    <th scope="col">Kadar Air rata2 (%)</th>
                                    <th scope="col">Kotoran rata2 (%)</th>
                                    <th scope="col">DOBI rata2</th>
                                    <th scope="col">Suhu rata2 (&deg;C)</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Tanggal ber-record yang seluruh barisnya
                                     kosong untuk sebuah metrik menghasilkan
                                     "tidak tersedia" pada kolom itu, tetapi
                                     tanggal itu TETAP terhitung sebagai hari
                                     ber-record. Tanggal tanpa record sama
                                     sekali tidak mendapat baris: baris nol
                                     akan terbaca sebagai "terukur nol". --}}
                                @foreach ($daily as $row)
                                    <tr data-testid="daily-row-{{ $row['date'] }}">
                                        <td>{{ $tgl($row['date']) }}</td>
                                        <td>{{ $cacah($row['filled_slots']) }}</td>
                                        <td @class(['is-muted' => $row['stock_total_mt'] === null])>{{ $nilai($row['stock_total_mt'], 1) }}</td>
                                        <td @class(['is-muted' => $row['ffa_avg'] === null])>{{ $nilai($row['ffa_avg'], 2) }}</td>
                                        <td @class(['is-muted' => $row['moisture_avg'] === null])>{{ $nilai($row['moisture_avg'], 3) }}</td>
                                        <td @class(['is-muted' => $row['impurities_avg'] === null])>{{ $nilai($row['impurities_avg'], 3) }}</td>
                                        <td @class(['is-muted' => $row['dobi_avg'] === null])>{{ $nilai($row['dobi_avg'], 2) }}</td>
                                        <td @class(['is-muted' => $row['temperature_avg'] === null])>{{ $nilai($row['temperature_avg'], 1) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr data-testid="daily-row-total">
                                    <td>Total periode</td>
                                    <td>{{ $cacah($coverage['filled_slots']) }} <small>dari {{ $cacah($coverage['expected_slots']) }}</small></td>
                                    <td>{{ $pergerakan($stock['movement_mt'], true, 1) }} <small>(pergerakan)</small></td>
                                    <td>{{ $nilai($metrics['ffa_percent']['avg'], 2) }} <small>(rata-rata)</small></td>
                                    <td>{{ $nilai($metrics['moisture_content_percent']['avg'], 3) }} <small>(rata-rata)</small></td>
                                    <td>{{ $nilai($metrics['impurities_dirt_percent']['avg'], 3) }} <small>(rata-rata)</small></td>
                                    <td>{{ $nilai($metrics['dobi_index']['avg'], 2) }} <small>(rata-rata)</small></td>
                                    <td>{{ $nilai($metrics['average_temperature_c']['avg'], 1) }} <small>(rata-rata)</small></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </section>
        @endif
    @endif
</div>
