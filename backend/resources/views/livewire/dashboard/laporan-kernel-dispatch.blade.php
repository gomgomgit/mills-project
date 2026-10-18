{{--
    screen-164--laporan-kernel-dispatch-web — Laporan Periode Kernel Dispatch.

    LAPORAN HIBRIDA PERTAMA DI PROYEK INI, dan susunannya mengikuti itu: ia
    menggabungkan HITUNGAN TRANSAKSI (preseden screen-162, Solid Waste Disposal)
    dengan UKURAN MUTU min/avg/max berpenyebut-sendiri (preseden screen-160,
    Process Water). Urutan layar: hero, baris filter, peringatan pokok
    'per-transaksi DAN bermutu', SATU KOTAK berisi empat kartu tonase +
    EMPAT KARTU MUTU berdampingan, tiga rekap teks bebas, rekap status pelepasan
    gerbang beserta angka 'tidak dilepas' yang ditonjolkan, tiga penghitung
    jejak kepatuhan, pernyataan 'tidak ada sebaran per jam dan tidak ada waktu
    putar kendaraan', pernyataan 'tidak ada penandaan mutu di luar batas',
    pernyataan 'kolom shift tidak ditampilkan', rekap harian, rekap temuan,
    kelengkapan pencatatan, lalu pengiriman draft.

    Kosakata kelasnya `md-*` milik aplikasi
    (dashboard/partials/report-styles.blade.php), dipakai APA ADANYA — layar ini
    TIDAK menambah satu kelas pun. Yang baru di sini adalah SUSUNANNYA, bukan
    kosakatanya: kartu mutu memakai bentuk yang sama dengan screen-160 (nilai
    rata-rata di `.md-kpi__value`, terendah/tertinggi di `.md-kpi__meta`,
    penyebutnya di `.md-kpi__foot`), dan kartu tonase memakai bentuk yang sama
    dengan screen-162.

    CSS TIDAK di-include di sini: partial report-styles memancarkan <style> dan
    Livewire 3 memasang wire:id pada elemen ter-render PERTAMA, sehingga
    menaruhnya di dalam/di atas root komponen mematikan seluruh wire:model.
    Partial itu dimuat lewat <x-slot:styles> di
    dashboard/laporan-kernel-dispatch.blade.php.

    SEMBILAN HAL YANG MEMBEDAKAN LAYAR INI DARI LAPORAN STASIUN LAIN:

    1. SATU BARIS DETAIL ADALAH SATU PENGIRIMAN — satu kendaraan yang berangkat
       membawa inti ke pembeli — bukan satu pembacaan per slot waktu. Stasiun ini
       tidak punya grid slot waktu, dan rekap harian HANYA memuat tanggal yang
       punya pengiriman: hari tanpa pengiriman adalah keadaan normal, dan baris
       nol untuk 22 hari akan mengubur 9 baris yang berarti.

    2. TONASE DAN MUTU DIRENDER DI DALAM SATU KOTAK. Keduanya hampir selalu
       dibaca bersama — tonase tinggi dengan kadar air tinggi adalah kabar buruk
       yang menyamar sebagai kabar baik — jadi memisahkannya ke dua bagian yang
       berjauhan akan membuat pembaca menyimpulkan dari satu angka saja. Hubungan
       DOM itu (keempat kartu mutu berada di dalam container `.md-card` yang sama
       dengan keempat kartu tonase) adalah bagian dari kontrak layar ini, bukan
       kebetulan tata letak.

    3. KEEMPAT KARTU MUTU MENCETAK PENYEBUTNYA MASING-MASING, DI DALAM KARTUNYA
       SENDIRI. Keempat kolom mutu nullable INDEPENDEN: satu pengiriman boleh
       punya hasil uji kadar air tanpa ALB. Satu penyebut bersama akan tampak
       rapi, lolos review, dan salah untuk setidaknya tiga dari empat — ia
       mengempiskan ukuran yang jarang diuji sambil tetap menghasilkan angka
       yang terlihat sah. `quality._note` dirender UTUH supaya pembaca yang
       melihat dua penyebut berbeda tidak menyimpulkan ada data yang hilang.

    4. PENJAGA 'TIDAK ADA DATA' PUN PER UKURAN. Ukuran yang measured_count-nya 0
       mencetak '—' untuk min/avg/max SEMENTARA ketiga ukuran lain tetap
       berangka. Satu penjaga global 'ada data mutu atau tidak' akan
       mengosongkan keempat kartu sekaligus, dan itu cacat yang paling mudah
       ditulis di layar ini. Karena itu setiap kartu memeriksa
       measured_count-NYA SENDIRI dan tidak pernah memeriksa kartu lain.

    5. 'PENIMBANGAN BELUM SELESAI' ADALAH KARTUNYA SENDIRI, bukan catatan kaki,
       dan kalimatnya berbunyi DEMIKIAN — bukan 'data tidak lengkap'. Netto
       adalah kolom TURUNAN service (bruto − tara), jadi null-nya berarti
       kendaraan sudah ditimbang kotor tetapi belum kembali ditimbang kosong,
       atau sebaliknya. Angka berat yang kosong dicetak '—', NEVER '0,00'.

    6. TIGA REKAP TEKS BEBAS PER NILAI APA ADANYA: nol join, nol pemetaan, nol
       penerjemahan. `silo_source_id` TERMASUK — ia teks bebas meski namanya
       berakhiran `_id`, tidak ada tabel silo di satu migrasi pun, dan form
       merendernya `<input type="text">`. Nilai yang berbeda hanya pada
       besar-kecil huruf TETAP dua baris berbeda.

    7. REKAP KEEMPAT BERBEDA SIFATNYA: `by_gate_status` satu-satunya rekap yang
       nilainya berasal dari pilihan TERTUTUP. Payload membawa KODE MENTAH dan
       LABELNYA DITURUNKAN DI SINI lewat `\App\Support\Display::option()` —
       pemetaannya TIDAK disalin ke blade ini (keputusan proyek 2026-10-14:
       blade MENDELEGASIKAN, jangan menyalin). Nilai di luar kedua pilihan yang
       dikenal dicetak APA ADANYA karena option() jatuh ke nilai mentah, dan itu
       yang diinginkan: barisnya sendiri, bukan dibuang.

    8. 'TIDAK DILEPAS' DAN 'TIDAK TERCATAT' ADALAH DUA KEADAAN BERBEDA, dan
       layar ini menolak menggabungkannya. `not_released_count` ditonjolkan
       sebagai kartunya sendiri dan menghitung HANYA 'not_released' persis;
       baris status yang kosong muncul sebagai 'tidak tercatat' di dalam
       rekapnya dan TIDAK ikut angka itu. Menggabungkan keduanya akan MENUDUH
       pengiriman yang sekadar belum diisi statusnya — dan tuduhan itu sampai ke
       orang sungguhan.

    9. TIGA KETIADAAN DINYATAKAN UTUH DI LAYAR, dirender langsung dari payload
       (`_note_no_hourly`, `_note_no_quality_thresholds`,
       `_note_shift_not_published`) dan tidak diringkas: sebaran per jam DAN
       waktu putar kendaraan TIDAK DAPAT dibuat (baris detail hanya membawa
       `event_date`, tanpa jam masuk maupun jam keluar — dan di CPO Dispatch
       keduanya JUSTRU ADA karena tabel itu punya time_in/time_out), tidak ada
       penandaan nilai mutu di luar batas (stasiun ini tidak punya master ambang
       mutu), dan kolom `shift` tidak ditampilkan meski tetap ada di ekspor.
       Blok yang hilang tanpa penjelasan selalu dibaca sebagai blok yang belum
       dibuat.

    SEGEL ATAS DAN SEGEL BAWAH ADALAH DUA PENGHITUNG, TIDAK PERNAH SATU: satu
    kendaraan yang kehilangan satu segel adalah keadaan yang berbeda dari dua
    kendaraan yang masing-masing kehilangan satu, dan satu angka gabungan
    menghapus perbedaan itu. Satu pengiriman yang kehilangan keduanya terhitung
    di KEDUANYA — dua fakta, bukan penghitungan ganda.

    SATUAN BERATNYA MT (metrik ton) dan satuan mutunya PERSEN, keduanya apa
    adanya dari kolomnya tanpa konversi — label yang sama dipakai form input
    (form-kernel-dispatch), layar Detail (detail-kernel-dispatch) dan Data
    Browser untuk kolom yang sama.

    Bacaan saja: tidak ada satu pun tombol/field di layar ini yang mengubah data
    stasiun.
--}}
@php
    // Angka dengan jumlah desimal tetap. null SELALU menjadi '—', TIDAK PERNAH
    // 0 — nol berarti 'terukur dan hasilnya nol', sedangkan '—' berarti 'tidak
    // pernah terukur'. Aturan ini berlaku di seluruh halaman: kartu tonase,
    // kartu mutu, tabel, maupun kaki tabel. '0,00 MT' pada kartu berat dan
    // '0,000 %' pada kartu mutu adalah cacat yang uji layar ini mencarinya.
    $nilai = function ($value, int $digits = 2) {
        if ($value === null) {
            return '—';
        }

        return number_format((float) $value, $digits, ',', '.');
    };

    $cacah = fn ($value) => number_format((float) $value, 0, ',', '.');

    $bulan = ['01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', '05' => 'Mei', '06' => 'Jun',
              '07' => 'Jul', '08' => 'Agu', '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'];

    $tgl = function (?string $date) use ($bulan) {
        if ($date === null || $date === '') {
            return 'tanpa tanggal kejadian';
        }

        [$y, $m, $d] = array_pad(explode('-', substr($date, 0, 10)), 3, '');

        return $d.' '.($bulan[$m] ?? $m).' '.$y;
    };

    $statusLabel = fn (?string $status) => match ($status) {
        'draft' => 'Draft',
        'open' => 'Terbuka',
        'closed' => 'Tertutup',
        default => (string) $status,
    };

    // Desimal kartu mutu. TIGA, sama dengan pembulatan service: kadar kotoran
    // bergerak di orde perseratus persen, dan dua desimal akan melipat dua
    // nilai yang berbeda menjadi satu angka yang sama.
    $digitMutu = 3;

    $dispatches = $summary['dispatches'] ?? null;
    $quality = $summary['quality'] ?? null;
    $compliance = $summary['compliance'] ?? null;
    $daily = $summary['daily'] ?? [];
    $dailyTotal = $summary['daily_total'] ?? null;
    $completeness = $summary['completeness'] ?? null;
    $textEntries = $summary['text_entries'] ?? ['findings' => []];
    $draftDispatchCount = $summary['draft_dispatch_count'] ?? 0;
    $undatedDispatchCount = $summary['undated_dispatch_count'] ?? 0;
    $notReleasedCount = $summary['not_released_count'] ?? 0;

    // KETIGA REKAP TEKS BEBAS — markup tabelnya ditulis SEKALI lalu di-loop,
    // bukan disalin tiga kali: tiga tabel hasil salin-tempel adalah tempat
    // paling mudah sebuah kolom (atau sebuah penyebut) tertinggal pada salah
    // satunya saja. Rekap by_gate_status TIDAK ada di daftar ini karena ia
    // bersifat lain: nilainya pilihan tertutup dan labelnya didelegasikan ke
    // Display::option(), jadi ia punya bagiannya sendiri di bawah.
    $rekapTeks = [
        [
            'key' => 'by_transporter',
            'testid' => 'by-transporter',
            'judul' => 'Rekap per Pengangkut/Kontraktor',
            'kolom' => 'transporter_contractor',
            'label' => 'Pengangkut/kontraktor',
            'kosong' => 'pengangkut/kontraktor',
        ],
        [
            'key' => 'by_silo_source',
            'testid' => 'by-silo-source',
            'judul' => 'Rekap per Silo Sumber',
            'kolom' => 'silo_source_id',
            'label' => 'Silo sumber',
            'kosong' => 'silo sumber',
        ],
        [
            'key' => 'by_destination_buyer',
            'testid' => 'by-destination-buyer',
            'judul' => 'Rekap per Pembeli Tujuan',
            'kolom' => 'destination_buyer',
            'label' => 'Pembeli tujuan',
            'kosong' => 'pembeli tujuan',
        ],
    ];

    // Periode masih berjalan / belum mulai. DIHITUNG DARI $countedUntil yang
    // diserahkan komponen, bukan dari now() di dalam blade: satu-satunya tempat
    // halaman ini menyentuh waktu sekarang ada di komponennya, sehingga
    // keterangan 'dihitung sampai ...' dapat diuji tanpa menyentuh DOM.
    $periodeBerjalan = $selectedPeriod !== null && $countedUntil < $selectedPeriod['end_date'];
    $periodeBelumMulai = $completeness !== null && (int) $completeness['days_counted'] === 0;
@endphp

<div class="md" data-testid="laporan-kernel-dispatch">

    {{-- ============ 1. Hero: periode yang sedang dibaca ============ --}}
    <section class="md-hero" data-testid="report-hero">
        <div class="md-hero__main">
            <p class="md-hero__eyebrow">Laporan Periode &middot; Stasiun Kernel Dispatch</p>
            <h1 class="md-hero__title">{{ $selectedPeriod['name'] ?? 'Laporan Kernel Dispatch' }}</h1>
            <p class="md-hero__subtitle" data-testid="hero-subtitle">
                @if ($selectedPeriod)
                    {{ $summary['business_unit']['name'] ?? $businessUnitName }}
                    @if ($selectedProductionLine) &middot; {{ $selectedProductionLine['name'] }} @endif
                    &middot; {{ $tgl($selectedPeriod['start_date']) }} &ndash; {{ $tgl($selectedPeriod['end_date']) }}
                @else
                    Pengiriman inti (kernel) yang keluar sepanjang satu Periode Pelaporan pada satu Production Line &mdash; satu baris detail adalah satu pengiriman, dengan tonase dan mutunya dibaca berdampingan
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

    {{-- ============ 2. Baris filter ============
         Filter bar bersama (components/report-filter-bar.blade.php):
         Mill (Admin) / keterangan mill, Production Line, Periode, ekspor.
         Tombol ekspornya memanggil exportCsv('csv') dan exportCsv('excel') —
         komponen menerjemahkan 'excel' menjadi 'xlsx' sebelum sampai ke service,
         karena kontrak API layar ini memakai daftar tertutup {csv, xlsx}. --}}
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
        Hanya periode yang mencakup Kernel Dispatch yang ditampilkan, dan periode tertutup tetap dapat dibaca serta diekspor. Angka disaring menurut Production Line yang melekat pada record itu sendiri, dan keanggotaan periode mengikuti tanggal record induknya. Ekspor mengunduh rincian satu baris per pengiriman dengan kolom konteks diulang di setiap baris, memuat seluruh kolom detail termasuk shift, nomor tiket timbang, nomor surat jalan, nomor kendaraan, nama pengemudi, kedua nomor segel dan kode operator timbang.
    </x-report-filter-bar>

    {{-- Area hasil laporan: diredupkan + spinner selama mill / line / periode
         berganti (components/loading-assets, .ld-region). .md-body meneruskan
         jarak antarkartu .md, jadi tata letak tidak berubah. --}}
    <div class="md-body ld-region" wire:loading.delay.short.class="ld-region--busy" wire:loading.delay.short.attr="aria-busy" wire:target="businessUnitId,productionLineId,periodId">
    @if ($hasNoMillForAccount)
        {{-- Empty state (a): akun terikat mill tetapi users.business_unit_id
             kosong. GAGAL TERTUTUP — pemilih Mill TIDAK ditawarkan sebagai
             gantinya, karena menawarkan daftar seluruh mill kepada peran yang
             seharusnya terikat satu mill justru mengubah data master yang rusak
             menjadi kebocoran lintas mill. Ini master yang rusak, bukan izin
             yang kurang, dan kalimatnya mengatakan demikian. --}}
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
        {{-- Empty state (b): Admin belum memilih mill. Tidak ada mill default
             dan tidak ada 'mill pertama' — memilihkan mill untuk Admin berarti
             menampilkan angka milik pabrik yang tidak ia maksud. --}}
        <div class="md-empty" data-testid="mill-select-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
            </span>
            <p class="md-empty__title">Pilih mill terlebih dahulu</p>
            <p class="md-empty__text">
                Sebagai Admin Anda tidak terikat pada satu mill. Pilih mill pada pemilih di atas untuk
                menampilkan daftar Production Line, daftar Periode Pelaporan, dan laporan Kernel
                Dispatch-nya.
            </p>
        </div>
    @elseif ($forbidden)
        {{-- Empty state (c): period_id yang diminta bukan milik mill ini.
             Ditolak SECARA TERLIHAT dan tanpa satu angka pun dari mill lain.
             Berbeda dari business_unit_id yang diabaikan diam-diam: di sana
             tidak ada yang ditolak karena parameternya memang tidak pernah
             dipakai, sedangkan di sini ada pegangan nyata ke data mill lain. --}}
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
             yang ditampilkan, dan TIDAK ADA angka seluruh line maupun seluruh
             mill yang dipasang sebagai gantinya — agregat lintas line adalah
             angka yang tidak dimiliki siapa pun. Kedua tombol ekspor pun belum
             dirender sama sekali. --}}
        <div class="md-empty" data-testid="select-production-line-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h16"/><circle cx="8" cy="7" r="1.6"/><circle cx="14" cy="12" r="1.6"/><circle cx="10" cy="17" r="1.6"/></svg>
            </span>
            <p class="md-empty__title">Pilih production line terlebih dahulu</p>
            <p class="md-empty__text">
                Laporan Kernel Dispatch menghasilkan angka gabungan per line, dan mencampur beberapa
                production line membuat angkanya tidak bisa ditindaklanjuti siapa pun. Pilih satu
                production line pada pemilih di atas untuk menampilkan laporannya. Selama belum dipilih,
                layar ini tidak menampilkan satu angka pun &mdash; termasuk tidak menampilkan angka
                seluruh mill sebagai penggantinya.
            </p>
        </div>
    @elseif ($periods === [])
        {{-- Empty state (d): mill belum punya Periode Pelaporan yang mencakup
             Kernel Dispatch. Yang kosong di sini adalah PEMILIHNYA — dibedakan
             dari 'periode tanpa data' di bawah. Bukan 404. --}}
        <div class="md-empty" data-testid="no-period-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
            </span>
            <p class="md-empty__title">Belum ada Periode Pelaporan</p>
            <p class="md-empty__text">
                Mill ini belum memiliki Periode Pelaporan yang mencakup stasiun Kernel Dispatch.
                Hubungi Admin agar membuatnya terlebih dahulu di layar Kelola Periode Pelaporan, lalu
                laporan periode akan tampil di sini.
            </p>
        </div>
    @elseif ($summary !== null)

        {{-- ============ 3. Peringatan pokok ============ --}}
        <div class="md-explain" data-testid="hybrid-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
            <span>
                Laporan ini menjawab <b>tiga pertanyaan yang berbeda sifatnya</b>: berapa banyak inti
                terkirim, <b>sebaik apa mutunya</b>, dan apakah dokumen angkutnya lengkap. Satuan
                hitungnya <b>satu pengiriman</b> &mdash; satu baris catatan, satu kendaraan yang
                berangkat membawa inti ke pembeli &mdash; bukan satu pembacaan per slot waktu; stasiun
                ini tidak punya grid slot waktu. Tonase dan mutu dirender <b>di dalam satu kotak</b>
                karena keduanya hampir selalu dibaca bersama: <b>tonase tinggi dengan kadar air tinggi
                adalah kabar buruk yang menyamar sebagai kabar baik</b>, dan memisahkan keduanya ke dua
                bagian yang berjauhan membuat pembaca menyimpulkan dari satu angka saja.
                <small>
                    Berat bersih <b>diturunkan server</b> sebagai bruto dikurangi tara; ia tidak pernah
                    diisi manusia, dan laporan ini membacanya apa adanya tanpa menghitungnya ulang.
                    Keempat nilai mutu sebaliknya <b>diisi dari hasil uji laboratorium</b>, dan tidak
                    satu pun dari keempatnya wajib &mdash; itulah sebab tiap ukuran punya penyebutnya
                    sendiri.
                </small>
            </span>
        </div>

        {{-- ============ 4 + 5. SATU KOTAK: tonase + EMPAT UKURAN MUTU ============
             Keduanya DI DALAM SATU container dengan sengaja (docblock poin 2):
             angka tonase tidak boleh terbaca tanpa mutunya di sampingnya, dan
             sebaliknya. --}}
        <section class="md-card" data-testid="dispatch-quality-card">
            <header class="md-card__head">
                <h3>Pengiriman Inti Periode Ini</h3>
                <span class="md-card__hint">tonase dan mutu sengaja berada di <b>satu kotak</b> &mdash; keduanya dibaca bersama, bukan terpisah</span>
            </header>

            <div class="md-kpis md-kpis--4">
                <article class="md-kpi" data-testid="kpi-dispatch-count">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Jumlah Pengiriman</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 17h-4V5h12v12h-3"/><path d="M10 9H6L3 12v5h2"/><circle cx="7.5" cy="17.5" r="2"/><circle cx="17.5" cy="17.5" r="2"/></svg></span>
                    </div>
                    <p class="md-kpi__value" data-testid="dispatch-count">{{ $cacah($dispatches['dispatch_count']) }} <span>pengiriman</span></p>
                    <p class="md-kpi__meta">sepanjang {{ $tgl($summary['period']['start_date']) }} &ndash; {{ $tgl($summary['period']['end_date']) }}</p>
                    <p class="md-kpi__foot">{{ $cacah($draftDispatchCount) }} di antaranya masih <b>draft</b> dan ikut terhitung</p>
                    <p class="md-kpi__foot"><small>Satu pengiriman = satu baris detail, bukan satu record. Angka ini menghitung seluruh pengiriman, termasuk yang penimbangannya belum selesai dan termasuk yang belum punya satu hasil uji pun.</small></p>
                </article>

                <article class="md-kpi" data-testid="kartu-total-netto">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Total Berat Bersih Inti</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18"/><path d="M5 7h14"/><path d="M5 7l-3 7h6z"/><path d="M19 7l-3 7h6z"/><path d="M8 21h8"/></svg></span>
                    </div>
                    <p class="md-kpi__value" data-testid="net-weight-total">{{ $nilai($dispatches['net_weight_total'], 1) }} <span>MT</span></p>
                    <p class="md-kpi__meta">dari <b>{{ $cacah($dispatches['net_weight_dispatch_count']) }} pengiriman</b> yang nettonya benar-benar terisi</p>
                    <p class="md-kpi__foot">{{ $cacah($dispatches['missing_net_weight_dispatch_count']) }} pengiriman <b>tidak ikut dijumlahkan</b> karena nettonya belum ada</p>
                    <p class="md-kpi__foot"><small>Penyebutnya dicetak berdampingan dengan totalnya supaya pembaca tahu total ini berdiri di atas berapa pengiriman. Ketika tidak ada satu pun pengiriman bertimbang, angka di atas dicetak sebagai tanda pisah dan <b>tidak pernah sebagai nol</b>: nol berarti tidak ada inti terangkut, sedangkan tanda pisah berarti tidak diketahui.</small></p>
                </article>

                {{-- PENYEBUT RATA-RATA DICETAK DI DALAM KARTU INI, bukan di
                     tempat lain di halaman: rata-rata dari 6 pengiriman tidak
                     boleh terlihat sama meyakinkan dengan rata-rata dari 600,
                     dan angka penyebutnya harus dapat disasarkan ke kartunya. --}}
                <article class="md-kpi" data-testid="kartu-rata-netto">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Rata-rata Netto per Pengiriman</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 18h16"/><path d="M4 14l5-5 4 4 7-7"/></svg></span>
                    </div>
                    <p class="md-kpi__value" data-testid="net-weight-avg">{{ $nilai($dispatches['net_weight_avg'], 2) }} <span>MT</span></p>
                    <p class="md-kpi__meta">penyebutnya <b data-testid="net-weight-dispatch-count">{{ $cacah($dispatches['net_weight_dispatch_count']) }}</b> pengiriman, bukan {{ $cacah($dispatches['dispatch_count']) }}</p>
                    <p class="md-kpi__foot">{{ $nilai($dispatches['net_weight_total'], 1) }} MT &divide; {{ $cacah($dispatches['net_weight_dispatch_count']) }} pengiriman</p>
                    <p class="md-kpi__foot"><small>Membaginya dengan seluruh pengiriman periode akan menghasilkan rata-rata yang turun secara palsu hanya karena ada penimbangan yang belum selesai. Pengiriman yang nettonya kosong <b>tidak menurunkan rata-rata, ia hilang darinya</b>.</small></p>
                </article>

                {{-- KARTUNYA SENDIRI, BUKAN CATATAN KAKI, dan kalimatnya
                     berbunyi 'penimbangan belum selesai' — bukan 'data tidak
                     lengkap'. Netto adalah kolom turunan service, jadi
                     kosongnya berarti pekerjaan yang menggantung. --}}
                <article class="md-kpi" data-testid="kartu-penimbangan-belum-selesai">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Penimbangan Belum Selesai</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg></span>
                    </div>
                    <p class="md-kpi__value" data-testid="missing-net-weight-dispatch-count">{{ $cacah($dispatches['missing_net_weight_dispatch_count']) }} <span>pengiriman</span></p>
                    <p class="md-kpi__meta">netto kosong karena <b>bruto atau tara belum terisi</b> &mdash; <b>penimbangan belum selesai</b>, bukan nol</p>
                    <p class="md-kpi__foot">ikut jumlah pengiriman &mdash; <b>tidak</b> ikut total maupun rata-rata netto</p>
                    <p class="md-kpi__foot"><small>Berat bersih adalah kolom <b>turunan server</b> (bruto dikurangi tara), jadi kosongnya bukan &lsquo;lupa diisi&rsquo; melainkan kendaraan yang sudah ditimbang kotor tetapi belum kembali ditimbang kosong, atau sebaliknya. Ia <b>tidak berhubungan</b> dengan kekosongan hasil uji mutu: berat dan mutu adalah kolom yang berbeda, dan satu pengiriman boleh punya salah satunya saja.</small></p>
                </article>
            </div>

            {{-- ---- EMPAT KARTU MUTU, DI DALAM KOTAK YANG SAMA ----
                 Masing-masing dengan PENYEBUTNYA SENDIRI dan dengan PENJAGANYA
                 SENDIRI. Daftar kolomnya datang dari
                 KernelDispatchReportService::QUALITY_MEASURES lewat
                 $qualityMeasures — tidak ditulis ulang di sini, karena empat
                 kartu yang ditulis tangan adalah empat kesempatan kehilangan
                 sebuah penyebut atau mewarisi penyebut kartu lain. --}}
            <p class="md-card__hint" data-testid="quality-sub">
                Mutu per pengiriman &mdash; <b>empat ukuran, empat penyebut</b>, dibaca berdampingan dengan tonase di atas
            </p>

            <div class="md-kpis md-kpis--4">
                @foreach ($qualityMeasures as $ukuran)
                    @php $m = $quality[$ukuran['key']]; @endphp
                    <article class="md-kpi" data-testid="quality-card" data-measure="{{ $ukuran['key'] }}">
                        <div class="md-kpi__top">
                            <span class="md-kpi__label">{{ $ukuran['label'] }}</span>
                            <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3h6"/><path d="M10 3v7.5L5.5 18A2 2 0 0 0 7.2 21h9.6a2 2 0 0 0 1.7-3L14 10.5V3"/><path d="M7 15h10"/></svg></span>
                        </div>
                        {{-- null SELALU '—', TIDAK PERNAH 0: nol berarti diuji
                             dan hasilnya nol, '—' berarti tidak pernah diuji. --}}
                        <p class="md-kpi__value">
                            <span data-testid="quality-avg">{{ $nilai($m['avg'], $digitMutu) }}</span>
                            <span>{{ $ukuran['satuan'] }} rata-rata</span>
                        </p>
                        <p class="md-kpi__meta" data-testid="quality-range">
                            terendah <span data-testid="quality-min">{{ $nilai($m['min'], $digitMutu) }}</span>
                            &middot; tertinggi <span data-testid="quality-max">{{ $nilai($m['max'], $digitMutu) }}</span>
                        </p>
                        {{-- PENYEBUT MILIK KARTU INI SENDIRI, bukan penyebut
                             bersama. Satu penyebut bersama untuk keempat ukuran
                             akan mengempiskan ukuran yang jarang diuji dan tetap
                             menghasilkan angka yang terlihat sah — cacat utama
                             yang dicari uji layar ini. --}}
                        <p class="md-kpi__foot">
                            dari <b data-testid="quality-measured-count">{{ $cacah($m['measured_count']) }}</b>
                            pengiriman yang {{ $ukuran['pendek'] }}-nya diuji
                            <small>(penyebut ukuran ini sendiri &mdash; pengiriman yang tidak diujikan kolom ini <b>tidak mengukurnya nol</b>, ia tidak mengukurnya sama sekali)</small>
                        </p>
                        {{-- PENJAGA PER UKURAN. Ukuran ini kosong TIDAK
                             mengosongkan ketiga ukuran lain, dan sebaliknya. --}}
                        @if ((int) $m['measured_count'] === 0)
                            <p class="md-kpi__foot" data-testid="quality-unmeasured">
                                <b>Belum ada satu hasil uji pun</b> untuk {{ $ukuran['pendek'] }} pada periode dan
                                line ini, jadi ketiga angkanya ditulis sebagai tanda pisah. Ukuran mutu yang lain
                                <b>tetap menampilkan angkanya</b> &mdash; yang kosong adalah satu kolom, bukan
                                seluruh mutu periode ini.
                            </p>
                        @endif
                    </article>
                @endforeach
            </div>

            {{-- Prosa milik payload, dirender UTUH. --}}
            <div class="md-explain" data-testid="quality-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                <span>{{ $quality['_note'] }}</span>
            </div>

            {{-- undated_dispatch_count DICETAK HANYA KETIKA > 0, berdampingan
                 dengan totalnya beserta kalimat yang menyatakan hubungan
                 keduanya: ia satu-satunya sebab penjumlahan rekap harian boleh
                 lebih kecil daripada jumlah pengiriman.

                 MENGAPA BERSYARAT, BUKAN SELALU. `event_date` adalah kolom NOT NULL
                 (migrasi 2026_08_31_000007 menuliskan `$table->date('event_date');`
                 tanpa `->nullable()`), jadi pengiriman tak bertanggal DITOLAK di
                 tingkat skema dan angka ini selalu 0 dalam praktik. Mencetak "0 di
                 antaranya tidak punya tanggal kejadian" pada setiap laporan berarti
                 mengarang kekhawatiran atas keadaan yang dilarang database — dan
                 pembaca yang melihatnya akan mencari masalah yang tidak ada. Ketika
                 angkanya 0, penjumlahan rekap harian memang SAMA DENGAN jumlah
                 pengiriman, sehingga tidak ada selisih yang perlu dijelaskan.

                 Penghitungnya tetap dipertahankan di payload dan cabang agregasinya
                 tetap diuji, supaya invariannya tetap benar bila kolom itu suatu saat
                 dilonggarkan. --}}
            @if ($undatedDispatchCount > 0)
            <div class="md-explain" data-testid="undated-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
                <span>
                    Dari <b>{{ $cacah($dispatches['dispatch_count']) }} pengiriman</b> periode ini,
                    <b data-testid="undated-dispatch-count">{{ $cacah($undatedDispatchCount) }}</b> di
                    antaranya <b>tidak punya tanggal kejadian</b> sehingga tidak dapat ditempatkan pada
                    hari mana pun. Pengiriman seperti itu <b>tetap ikut</b> di jumlah pengiriman, di
                    total serta rata-rata netto, di keempat penyebut mutu, dan di keempat rekap &mdash;
                    tetapi <b>tidak ikut</b> di rekap harian. Itulah satu-satunya sebab penjumlahan
                    rekap harian ({{ $cacah($dailyTotal['dispatch_count']) }} pengiriman) boleh lebih
                    kecil daripada jumlah pengiriman periode, dan selisihnya tepat sebesar angka itu.
                </span>
            </div>
            @endif
        </section>

        {{-- ============ 6. Tiga rekap teks bebas ============ --}}
        @foreach ($rekapTeks as $rekap)
            @php $entri = $summary[$rekap['key']]; @endphp
            <details class="md-details" open data-testid="{{ $rekap['testid'] }}-details">
                <summary data-testid="{{ $rekap['testid'] }}-toggle">
                    {{ $rekap['judul'] }} (<code>{{ $rekap['kolom'] }}</code>)
                    <small>
                        {{ $cacah(count($entri)) }} baris &middot; diurutkan dari total netto terbesar
                        &middot; klik untuk membuka atau menutup
                    </small>
                </summary>
                <div class="md-recap">
                    <table class="md-table" data-testid="{{ $rekap['testid'] }}-table">
                        <thead>
                            <tr>
                                <th scope="col">{{ $rekap['label'] }}</th>
                                <th scope="col">Pengiriman</th>
                                <th scope="col">Total netto (MT)</th>
                                <th scope="col">Pengiriman netto terisi</th>
                                <th scope="col">Rata-rata/pengiriman (MT)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($entri as $row)
                                {{-- Baris value NULL TIDAK PERNAH DIBUANG, dan
                                     posisinya ditentukan tonasenya — bukan dipaku
                                     di baris terakhir. Membuangnya membuat kolom
                                     Pengiriman berhenti menjumlah ke jumlah
                                     pengiriman periode. --}}
                                <tr data-testid="{{ $row['value'] === null ? $rekap['testid'].'-row-null' : $rekap['testid'].'-row' }}">
                                    <td @class(['is-muted' => $row['value'] === null])>
                                        @if ($row['value'] === null)
                                            <b>tidak tercatat</b> <small>(kolom {{ $rekap['kosong'] }} kosong pada pengiriman itu)</small>
                                        @else
                                            {{ $row['value'] }}
                                        @endif
                                    </td>
                                    <td>{{ $cacah($row['dispatch_count']) }}</td>
                                    <td @class(['is-muted' => $row['net_weight_total'] === null])>{{ $nilai($row['net_weight_total'], 1) }}</td>
                                    <td class="is-muted">{{ $cacah($row['net_weight_dispatch_count']) }}</td>
                                    <td @class(['is-muted' => $row['net_weight_avg'] === null])>{{ $nilai($row['net_weight_avg'], 2) }}</td>
                                </tr>
                            @empty
                                <tr data-testid="{{ $rekap['testid'] }}-empty">
                                    <td colspan="5" class="is-muted">
                                        Tidak ada satu pengiriman pun pada periode {{ $summary['period']['name'] }}
                                        di line {{ $summary['production_line']['name'] }}, jadi rekap ini belum punya baris.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr data-testid="{{ $rekap['testid'] }}-row-total">
                                <td>Total periode</td>
                                <td>{{ $cacah($dispatches['dispatch_count']) }}</td>
                                <td>{{ $nilai($dispatches['net_weight_total'], 1) }}</td>
                                <td>{{ $cacah($dispatches['net_weight_dispatch_count']) }}</td>
                                <td>{{ $nilai($dispatches['net_weight_avg'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="md-explain" data-testid="{{ $rekap['testid'] }}-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                    <span>
                        <code>{{ $rekap['kolom'] }}</code> adalah kolom <b>teks bebas</b> pada tabel
                        detail &mdash; bukan relasi, bukan daftar nilai tertutup &mdash; jadi nilainya
                        dikelompokkan <b>apa adanya</b>, tanpa satu join pun dan tanpa pelabelan.
                        Seluruh nilai ditampilkan, termasuk yang hanya muncul sekali: rekap ini
                        <b>tidak pernah melipat</b> nilai berfrekuensi rendah menjadi
                        &lsquo;lainnya&rsquo;. Baris <b>tidak tercatat</b> berdiri sendiri dan tidak
                        dibuang dari jumlah, sehingga kolom Pengiriman menjumlah tepat ke
                        {{ $cacah($dispatches['dispatch_count']) }} pengiriman.
                        @if ($rekap['key'] === 'by_silo_source')
                            <b>Nama kolomnya menyesatkan</b>: meski berakhiran <code>_id</code>, silo
                            sumber adalah teks yang <b>diketik</b> &mdash; tidak ada tabel master silo
                            di basis data ini, dan form input merendernya sebagai kotak teks. Karena itu
                            nilainya tidak pernah dicari namanya, bahkan ketika ia berbentuk seperti
                            sebuah id.
                        @endif
                        <small>
                            Diurutkan dari <b>total netto terbesar</b>, lalu jumlah pengiriman, lalu
                            nilainya sebagai pemutus seri. Nilai yang berbeda hanya pada besar-kecil
                            huruf tetap dua baris berbeda. Bila kelak kolom ini berisi ratusan nilai
                            karena diketik bebas, rekapnya menjadi daftar panjang berjumlah satu-satu
                            &mdash; gejala itu sengaja dibiarkan <b>terlihat</b>, karena ia tanda kolom
                            tersebut perlu data master.
                        </small>
                    </span>
                </div>
            </details>
        @endforeach

        {{-- ============ 7. Status pelepasan gerbang ============
             SATU-SATUNYA REKAP DI LAYAR INI yang nilainya berasal dari pilihan
             TERTUTUP, dan karena itu satu-satunya yang labelnya DIDELEGASIKAN
             ke kosakata bersama lewat Display::option(). Pemetaan 'released' →
             'Released' TIDAK dituliskan di sini. --}}
        <section class="md-card" data-testid="gate-status-card">
            <header class="md-card__head">
                <h3>Status Pelepasan Gerbang</h3>
                <span class="md-card__hint">satu-satunya kolom berpilihan <b>tertutup</b> di stasiun ini &mdash; label diambil dari kosakata bersama, bukan diketik ulang di sini</span>
            </header>

            {{-- DITONJOLKAN TERSENDIRI, bukan sekadar satu baris di dalam
                 rekapnya: inti yang berangkat tanpa pelepasan gerbang adalah
                 kejadian yang perlu DILIHAT, bukan baris yang perlu dihitung. --}}
            <div class="md-kpis md-kpis--2">
                <article class="md-kpi" data-testid="kpi-not-released">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Pengiriman Tidak Dilepas</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></span>
                    </div>
                    <p class="md-kpi__value" data-testid="not-released-count">{{ $cacah($notReleasedCount) }} <span>dari {{ $cacah($dispatches['dispatch_count']) }} pengiriman</span></p>
                    <p class="md-kpi__meta">berstatus <b>{{ \App\Support\Display::option('not_released') }}</b> pada kolom status pelepasan gerbang</p>
                    <p class="md-kpi__foot">angka ini <b>tidak</b> memuat pengiriman yang statusnya <b>tidak tercatat</b></p>
                    <p class="md-kpi__foot"><small>&lsquo;Tidak tercatat&rsquo; dan &lsquo;tidak dilepas&rsquo; adalah <b>dua keadaan yang berbeda</b>, dan layar ini menolak menggabungkannya: menggabungkannya berarti <b>menuduh</b> pengiriman yang sekadar belum diisi statusnya, dan tuduhan itu sampai ke orang. Pengiriman tanpa status tercatat punya barisnya sendiri di rekap di bawah.</small></p>
                </article>
                <article class="md-kpi" data-testid="kpi-gate-status-recorded">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Status Gerbang Tercatat</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                    </div>
                    @php
                        // Baris status yang KOSONG, dibaca dari rekapnya sendiri
                        // — bukan dihitung ulang dari angka lain: satu definisi,
                        // satu jawaban.
                        $barisTanpaStatus = collect($summary['by_gate_status'])->firstWhere('value', null);
                        $tanpaStatus = (int) ($barisTanpaStatus['dispatch_count'] ?? 0);
                    @endphp
                    <p class="md-kpi__value" data-testid="gate-status-recorded-count">{{ $cacah($dispatches['dispatch_count'] - $tanpaStatus) }} <span>pengiriman</span></p>
                    <p class="md-kpi__meta">{{ $cacah($tanpaStatus) }} pengiriman statusnya <b>tidak tercatat</b> &mdash; keadaan yang berbeda dari tidak dilepas</p>
                    <p class="md-kpi__foot">rekap di bawah menjumlah tepat ke {{ $cacah($dispatches['dispatch_count']) }} pengiriman, baris tidak tercatat termasuk</p>
                </article>
            </div>

            <div class="md-recap">
                <table class="md-table" data-testid="by-gate-status-table">
                    <thead>
                        <tr>
                            <th scope="col">Status pelepasan gerbang</th>
                            <th scope="col">Pengiriman</th>
                            <th scope="col">Total netto (MT)</th>
                            <th scope="col">Pengiriman netto terisi</th>
                            <th scope="col">Rata-rata/pengiriman (MT)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($summary['by_gate_status'] as $row)
                            <tr data-testid="{{ $row['value'] === null ? 'by-gate-status-row-null' : 'by-gate-status-row' }}"
                                @if ($row['value'] !== null) data-gate-status="{{ $row['value'] }}" @endif>
                                <td @class(['is-muted' => $row['value'] === null])>
                                    @if ($row['value'] === null)
                                        <b>tidak tercatat</b> <small>(kolom status pelepasan gerbang kosong pada pengiriman itu)</small>
                                    @else
                                        {{-- LABEL DIDELEGASIKAN, TIDAK DISALIN.
                                             Display::option() memulangkan label
                                             untuk nilai yang dikenal dan NILAI
                                             MENTAHNYA untuk yang tidak — dan
                                             itulah yang diinginkan: nilai di luar
                                             kedua pilihan form (service
                                             menyimpannya tanpa daftar tertutup)
                                             mendapat barisnya sendiri apa adanya,
                                             bukan dibuang dan bukan dipaksa masuk
                                             salah satu pilihan. --}}
                                        {{ \App\Support\Display::option($row['value']) }}
                                    @endif
                                </td>
                                <td>{{ $cacah($row['dispatch_count']) }}</td>
                                <td @class(['is-muted' => $row['net_weight_total'] === null])>{{ $nilai($row['net_weight_total'], 1) }}</td>
                                <td class="is-muted">{{ $cacah($row['net_weight_dispatch_count']) }}</td>
                                <td @class(['is-muted' => $row['net_weight_avg'] === null])>{{ $nilai($row['net_weight_avg'], 2) }}</td>
                            </tr>
                        @empty
                            <tr data-testid="by-gate-status-empty">
                                <td colspan="5" class="is-muted">
                                    Tidak ada satu pengiriman pun pada periode {{ $summary['period']['name'] }}
                                    di line {{ $summary['production_line']['name'] }}, jadi rekap ini belum punya baris.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr data-testid="by-gate-status-row-total">
                            <td>Total periode</td>
                            <td>{{ $cacah($dispatches['dispatch_count']) }}</td>
                            <td>{{ $nilai($dispatches['net_weight_total'], 1) }}</td>
                            <td>{{ $cacah($dispatches['net_weight_dispatch_count']) }}</td>
                            <td>{{ $nilai($dispatches['net_weight_avg'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="md-explain" data-testid="gate-status-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                <span>
                    Ini <b>satu-satunya rekap di layar ini</b> yang nilainya berasal dari
                    <b>pilihan tertutup</b>, bukan dari teks yang diketik: form input merendernya sebagai
                    daftar pilihan dengan tepat dua isian. Labelnya karena itu <b>diambil dari kosakata
                    bersama aplikasi</b> dan tidak diketik ulang di layar ini &mdash; satu nilai, satu
                    label, di mana pun ia dibaca: layar Detail, layar laporan ini, dan berkas ekspor.
                    <small>
                        Penyimpanan kolom ini <b>tidak membatasi</b> isinya pada kedua pilihan itu, jadi
                        nilai yang datang dari jalur selain form web <b>mungkin ada</b>. Nilai semacam
                        itu ditampilkan <b>apa adanya</b> dengan barisnya sendiri &mdash; tidak dibuang
                        dan tidak dipaksa masuk ke salah satu pilihan &mdash; justru supaya kolom
                        Pengiriman tetap menjumlah ke jumlah pengiriman periode dan keberadaannya
                        terlihat oleh orang yang dapat memperbaikinya. Baris <b>tidak tercatat</b> juga
                        tidak pernah dibuang, dan ia <b>tidak</b> ikut angka &lsquo;tidak dilepas&rsquo;
                        di atas.
                    </small>
                </span>
            </div>
        </section>

        {{-- ============ 8. Jejak kepatuhan ============
             BLOK TERSENDIRI, sengaja TIDAK di dalam kotak tonase/mutu: ini
             dokumen angkut yang tidak terbit, bukan ukuran produksi. TIGA
             PENGHITUNG, dan kedua penghitung segel TIDAK PERNAH DIJUMLAHKAN. --}}
        <section class="md-card" data-testid="compliance-card">
            <header class="md-card__head">
                <h3>Jejak Kepatuhan Dokumen Angkut</h3>
                <span class="md-card__hint">penghitung <b>kehadiran</b>, bukan ukuran &mdash; tidak pernah dirata-rata, diurutkan, maupun digrafikkan</span>
            </header>
            <div class="md-kpis md-kpis--3">
                <article class="md-kpi" data-testid="kpi-missing-waybill">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Tanpa Nomor Surat Jalan</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3v5h5"/><path d="M19 9v10a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7z"/><path d="M9 13h6M9 17h4"/></svg></span>
                    </div>
                    <p class="md-kpi__value" data-testid="missing-waybill-count">{{ $cacah($compliance['missing_waybill_count']) }} <span>dari {{ $cacah($dispatches['dispatch_count']) }} pengiriman</span></p>
                    <p class="md-kpi__meta">{{ $cacah($dispatches['dispatch_count'] - $compliance['missing_waybill_count']) }} pengiriman punya nomor surat jalan</p>
                    <p class="md-kpi__foot">nol pun <b>tetap dicetak</b> &mdash; nol itulah yang memberi tahu pemeriksaannya berjalan</p>
                </article>
                <article class="md-kpi" data-testid="kpi-missing-seal-top">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Tanpa Nomor Segel Atas</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="9" r="5"/><path d="M9.5 13.5 8 21l4-2 4 2-1.5-7.5"/></svg></span>
                    </div>
                    <p class="md-kpi__value" data-testid="missing-seal-top-count">{{ $cacah($compliance['missing_seal_top_count']) }} <span>dari {{ $cacah($dispatches['dispatch_count']) }} pengiriman</span></p>
                    <p class="md-kpi__meta">dihitung <b>terpisah</b> dari segel bawah &mdash; dua angka, tidak pernah satu</p>
                    <p class="md-kpi__foot">nol pun <b>tetap dicetak</b> &mdash; nol itulah yang memberi tahu pemeriksaannya berjalan</p>
                </article>
                <article class="md-kpi" data-testid="kpi-missing-seal-bottom">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Tanpa Nomor Segel Bawah</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="15" r="5"/><path d="M14.5 10.5 16 3l-4 2-4-2 1.5 7.5"/></svg></span>
                    </div>
                    <p class="md-kpi__value" data-testid="missing-seal-bottom-count">{{ $cacah($compliance['missing_seal_bottom_count']) }} <span>dari {{ $cacah($dispatches['dispatch_count']) }} pengiriman</span></p>
                    <p class="md-kpi__meta">dihitung <b>terpisah</b> dari segel atas &mdash; keduanya <b>tidak pernah dijumlahkan</b></p>
                    <p class="md-kpi__foot">satu pengiriman yang kehilangan <b>kedua</b> segel terhitung di <b>kedua</b> kartu &mdash; dua fakta, bukan penghitungan ganda</p>
                </article>
            </div>
            {{-- Prosa milik payload, dirender apa adanya. --}}
            <div class="md-explain" data-testid="compliance-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                <span>{{ $compliance['_note'] }}</span>
            </div>
        </section>

        {{-- ============ 9. PERNYATAAN: tidak ada sebaran per jam dan tidak ada waktu putar kendaraan ============
             Dirender UTUH dari payload, tidak diringkas: prosa ini ada justru
             untuk mencegah seseorang menambahkannya kemudian dengan menebak jam
             dari created_at — yang berarti menebak dari waktu input, bukan waktu
             kejadian. Kelasnya .md-explain, BUKAN .md-threshold. --}}
        <div class="md-explain" data-testid="no-hourly-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            <span>{{ $summary['_note_no_hourly'] }}</span>
        </div>

        {{-- ============ 10. PERNYATAAN: tanpa penandaan nilai mutu di luar batas ============
             Kelasnya .md-explain, BUKAN .md-threshold: ketiadaan penandaan
             diasersi MENURUT NAMA KELAS pada HTML ter-render, sehingga kelas
             bernama 'threshold' pada kotak yang menyatakan tidak ada ambang akan
             memerahkan asersi itu justru karena namanya. --}}
        <div class="md-explain" data-testid="no-quality-thresholds-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 18h16"/><path d="M4 14l5-5 4 4 7-7"/></svg>
            <span>{{ $summary['_note_no_quality_thresholds'] }}</span>
        </div>

        {{-- ============ 11. PERNYATAAN: kolom shift tidak ditampilkan ============ --}}
        <div class="md-explain" data-testid="shift-not-published-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M4.9 4.9l14.2 14.2"/></svg>
            <span>{{ $summary['_note_shift_not_published'] }}</span>
        </div>

        {{-- ============ 12. Rekap harian ============
             <details> BAWAAN PERAMBAN, bukan tombol Livewire: menutup atau
             membukanya karena itu TIDAK memicu satu permintaan pun ke server dan
             NOL kueri basis data — seluruh angka halaman ini dihitung di
             service, bukan dari baris yang sedang terlihat. Periode sepanjang
             satu bulan menghasilkan puluhan baris, dan tabelnya menggulir
             mendatar DI DALAM kartunya sendiri (.md-recap { overflow-x: auto }),
             sehingga halaman sendiri tidak pernah menggulir mendatar. --}}
        <details class="md-details" open data-testid="daily-recap-details">
            <summary data-testid="daily-recap-toggle">
                Rekap Harian
                <small>
                    {{ $cacah(count($daily)) }} baris &middot; satu baris per <b>hari berpengiriman</b>,
                    bukan per hari kalender &middot; klik untuk membuka atau menutup
                </small>
            </summary>
            <div class="md-recap">
                <table class="md-table" data-testid="daily-table">
                    <thead>
                        <tr>
                            <th scope="col">Tanggal</th>
                            <th scope="col">Pengiriman</th>
                            <th scope="col">Total netto (MT)</th>
                            <th scope="col">Pengiriman netto terisi</th>
                            <th scope="col">Rata-rata/pengiriman (MT)</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Tanggal tanpa satu pengiriman pun TIDAK mendapat
                             baris: baris nol akan terbaca sebagai 'terukur nol',
                             padahal hari tanpa pengiriman adalah keadaan normal
                             di stasiun ini. --}}
                        @forelse ($daily as $row)
                            <tr data-testid="daily-row-{{ $row['date'] }}">
                                <td>{{ $tgl($row['date']) }}</td>
                                <td>{{ $cacah($row['dispatch_count']) }}</td>
                                <td @class(['is-muted' => $row['net_weight_total'] === null])>{{ $nilai($row['net_weight_total'], 1) }}</td>
                                <td class="is-muted">{{ $cacah($row['net_weight_dispatch_count']) }}</td>
                                <td @class(['is-muted' => $row['net_weight_avg'] === null])>{{ $nilai($row['net_weight_avg'], 2) }}</td>
                            </tr>
                        @empty
                            <tr data-testid="daily-empty">
                                <td colspan="5" class="is-muted">
                                    Tidak ada satu pengiriman bertanggal pun pada periode
                                    {{ $summary['period']['name'] }} di line
                                    {{ $summary['production_line']['name'] }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr data-testid="daily-row-total">
                            <td>Total harian</td>
                            <td>{{ $cacah($dailyTotal['dispatch_count']) }}</td>
                            <td>{{ $nilai($dailyTotal['net_weight_total'], 1) }}</td>
                            <td>{{ $cacah($dailyTotal['net_weight_dispatch_count']) }}</td>
                            <td>{{ $nilai($dailyTotal['net_weight_avg'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="md-scrollhint" data-testid="daily-scroll-hint">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M15 8l4 4-4 4M9 8l-4 4 4 4"/></svg>
                Tabel ini menggulir mendatar di dalam kartunya sendiri &mdash; halaman tidak pernah
                menggulir mendatar.
            </p>
            <div class="md-explain" data-testid="daily-total-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                <span>
                    Baris total di kaki tabel ini <b>dijumlahkan dari baris-baris harian di atasnya</b>,
                    bukan disalin dari kartu tonase di bagian atas layar. Keduanya memang dapat berbeda,
                    tepatnya sebesar jumlah pengiriman yang tidak punya tanggal kejadian &mdash;
                    pengiriman semacam itu ikut di jumlah pengiriman tetapi tidak dapat ditempatkan pada
                    hari mana pun. Perbedaan itu adalah <b>informasi</b>; menyalin akan
                    menyembunyikannya, dan selisih yang tak dijelaskan tidak dapat dibedakan dari cacat.
                    <small>
                        Kolom <b>Pengiriman netto terisi</b> ikut dicetak pada setiap baris karena
                        rata-rata harian berdiri di atas penyebutnya sendiri, bukan di atas jumlah
                        pengiriman hari itu. Hari yang punya pengiriman tetapi tak satu pun nettonya
                        terisi mencetak tanda pisah, bukan nol.
                    </small>
                </span>
            </div>
        </details>

        {{-- ============ 13. Rekap Temuan ============
             SATU DAFTAR, bukan dua: stasiun ini TIDAK punya kolom catatan kedua,
             berbeda dari Solid Waste Disposal yang punya `remarks` juga. Daftar
             yang kosong tetap dicetak sebagai PERNYATAAN 'tidak ada', bukan
             dihilangkan dari layar dan bukan tabel kosong tanpa penjelasan. --}}
        <section class="md-card" data-testid="findings-card">
            <header class="md-card__head">
                <h3>Rekap Temuan</h3>
                <span class="md-card__hint">{{ $cacah(count($textEntries['findings'])) }} pengiriman mengisi kolom <code>findings</code> sepanjang periode &middot; diterbitkan sebagai teks</span>
            </header>
            @if ($textEntries['findings'] !== [])
                <div class="md-notes">
                    @foreach ($textEntries['findings'] as $entri)
                        <div class="md-notes__item" data-testid="findings-entry">
                            <span class="md-pill">{{ $tgl($entri['date']) }}</span>
                            <p>{{ $entri['value'] }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="md-filters__hint" data-testid="findings-empty">
                    Tidak ada satu temuan pun yang dicatat pada periode dan line ini &mdash; kolom
                    <code>findings</code> kosong pada seluruh pengiriman. Bagian ini tetap ditampilkan
                    dengan keterangan ini, bukan dihilangkan dari layar dan bukan tabel kosong tanpa
                    penjelasan.
                </p>
            @endif
            <div class="md-explain" data-testid="findings-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                <span>
                    Bagian ini menjawab <b>apa yang ditemukan</b>. Isinya diterbitkan <b>sebagai teks
                    beserta tanggalnya</b> &mdash; tidak pernah disebar menjadi kategori, tidak pernah
                    dihitung sebagai metrik, dan tidak pernah digrafikkan, karena
                    <code>findings</code> adalah kolom teks bebas dan menyebarnya akan memperlakukan
                    prosa sebagai enum. Hanya pengiriman yang kolomnya <b>tidak kosong</b> yang muncul
                    di sini.
                    <small>
                        Stasiun ini punya <b>satu</b> kolom teks kejadian, bukan dua: tidak ada kolom
                        catatan terpisah pada baris detailnya, jadi tidak ada bagian kedua yang hilang
                        dari layar ini.
                    </small>
                </span>
            </div>
        </section>

        {{-- ============ 14. Kelengkapan pencatatan ============
             BAGIAN DARI LAPORAN, BUKAN CATATAN KAKI: pembaca perlu tahu
             seberapa jauh angka di atas dapat diandalkan sebelum
             menindaklanjutinya. --}}
        <section class="md-card" data-testid="completeness-card">
            <header class="md-card__head">
                <h3>Kelengkapan Pencatatan</h3>
                <span class="md-card__hint">penyebutnya <b>hari periode yang sudah lewat</b>, bukan seluruh panjang periode</span>
            </header>
            <div class="md-budget">
                <div class="md-budget__row">
                    <div class="md-budget__label">
                        <span>Hari dengan minimal satu pengiriman</span>
                        <span class="md-budget__nums">
                            <strong data-testid="days-with-dispatch">{{ $cacah($completeness['days_with_dispatch']) }}</strong>
                            dari <span data-testid="days-counted">{{ $cacah($completeness['days_counted']) }}</span>
                            hari yang sudah lewat
                        </span>
                    </div>
                    {{-- percent NULL (periode belum mulai) → '—', BUKAN 0,0%:
                         nol persen berarti ada hari yang diharapkan dan tak satu
                         pun terisi, sedangkan '—' berarti belum ada hari yang
                         diharapkan sama sekali. Nilainya datang dari payload;
                         blade tidak menghitungnya ulang. --}}
                    <span class="md-budget__pct" data-testid="days-with-dispatch-percent">{{ $completeness['percent'] === null ? '—' : $nilai($completeness['percent'], 1).'%' }}</span>
                    <div class="md-bar md-bar--lg"><span style="width: {{ min(100, max(0, (float) ($completeness['percent'] ?? 0))) }}%"></span></div>
                </div>
                @if ($periodeBelumMulai)
                    <p class="md-budget__note" data-testid="period-not-started-note">
                        Periode ini <b>belum dimulai</b> ({{ $tgl($summary['period']['start_date']) }}),
                        jadi belum ada satu hari pun yang diharapkan terisi dan kelengkapannya ditulis
                        sebagai tanda pisah &mdash; <b>bukan sebagai nol persen</b>, yang akan terbaca sebagai kelalaian pencatatan.
                    </p>
                @elseif ($periodeBerjalan)
                    <p class="md-budget__note" data-testid="period-running-note">
                        Dihitung sampai {{ $tgl($countedUntil) }}, periode masih berjalan
                        ({{ $cacah($completeness['days_counted']) }} dari
                        {{ $cacah($completeness['days_in_period']) }} hari periode sudah lewat) &mdash;
                        hari yang belum terjadi tidak mungkin tercatat dan karena itu tidak ikut
                        menghukum angka kelengkapan.
                    </p>
                @endif
            </div>
            <div class="md-explain" data-testid="completeness-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                <span>
                    Kelengkapan mengukur <b>hari</b>, bukan kolom: berapa hari periode yang sudah lewat
                    punya minimal satu pengiriman. Rentang periode <b>inklusif di kedua ujung</b>, jadi
                    hari pertama dan hari terakhir ikut dihitung. Hari tanpa satu pengiriman pun
                    <b>bukan hal yang sama</b> dengan hari berpengiriman nol MT: hari semacam itu tidak
                    mendapat baris pada rekap harian sama sekali.
                    <small>
                        Angka ini <b>tidak</b> mengukur kelengkapan hasil uji mutu &mdash; itu diukur
                        oleh keempat penyebut mutu, masing-masing untuk kolomnya sendiri. Keanggotaan
                        periode ditentukan oleh <b>tanggal record induk</b>, bukan tanggal kejadian tiap
                        pengiriman dan bukan waktu baris dibuat &mdash; keduanya dapat berbeda, dan
                        konsekuensinya rekap harian dapat memuat tanggal di luar rentang periode. Itu
                        dibiarkan apa adanya: menyembunyikannya akan membuat total tidak cocok dengan
                        rincian hariannya.
                    </small>
                </span>
            </div>
        </section>

        {{-- ============ 15. Pengiriman draft ============ --}}
        <section class="md-card" data-testid="draft-card">
            <header class="md-card__head">
                <h3>Pengiriman Berstatus Draft</h3>
                <span class="md-card__hint">ikut terhitung di seluruh angka di layar ini, termasuk keempat penyebut mutu</span>
            </header>
            <div class="md-kpis md-kpis--3">
                <article class="md-kpi" data-testid="kpi-draft-dispatch-count">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Pengiriman Draft</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3v5h5"/><path d="M19 9v10a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7z"/><path d="M9 13h6M9 17h4"/></svg></span>
                    </div>
                    <p class="md-kpi__value" data-testid="draft-dispatch-count">{{ $cacah($draftDispatchCount) }} <span>dari {{ $cacah($dispatches['dispatch_count']) }} pengiriman</span></p>
                    <p class="md-kpi__meta">status <b>Draft</b> pada record induknya</p>
                    <p class="md-kpi__foot"><b>ikut terhitung</b> di jumlah pengiriman, total netto, rata-rata, keempat ukuran mutu beserta penyebutnya, keempat rekap, jejak kepatuhan, dan rekap harian</p>
                </article>
            </div>
            <div class="md-explain" data-testid="draft-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg>
                <span>
                    Pengiriman berstatus draft <b>tetap ikut terhitung</b> dalam seluruh angka di layar
                    ini, dan jumlahnya dinyatakan di sini supaya pembaca tahu <b>sebagian angkanya belum
                    final</b>. Aturan ini mengikuti seluruh laporan stasiun yang sudah ada, yang tak satu
                    pun menyaring status record: laporan yang berhitung dengan aturan berbeda antar
                    stasiun lebih menyesatkan daripada laporan yang memasukkan data belum selesai secara
                    terbuka. Bila <b>seluruh</b> pengiriman periode berstatus draft, angka tetap dihitung
                    penuh dan jumlah draftnya dinyatakan. Pembaca diberi tahu, bukan dilindungi.
                </span>
            </div>
        </section>
    @endif
    </div>
</div>
