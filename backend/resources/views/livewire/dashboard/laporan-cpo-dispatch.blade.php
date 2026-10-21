{{--
    screen-166--laporan-cpo-dispatch-web — Laporan Periode CPO Dispatch.

    Satu baris detail = SATU PENGIRIMAN (satu tanker yang datang, diisi, dan
    berangkat). Kerangka dan kosakata kelasnya mengikuti screen-164 (Laporan
    Kernel Dispatch); yang BARU di layar ini ada empat, dan masing-masing punya
    bagiannya sendiri:

    1. SEBARAN MENURUT JAM MASUK (24 kolom) dan 2. WAKTU PUTAR TANKER — satu-
       satunya laporan pengiriman yang dapat menjawab pertanyaan waktu, karena
       hanya tabel ini yang membawa jam masuk dan jam keluar. Kedua bagian TETAP
       DIRENDER meskipun tak satu jam pun tercatat: sebaran 24 kolom kosong dan
       waktu putar '—', masing-masing dengan kalimat yang menyatakannya — bukan
       dihilangkan, karena bagian yang hilang terbaca sebagai bagian yang belum
       dibuat.

    3. EMPAT PENYEBUT YANG BERBEDA dicetak di kartunya masing-masing: jumlah
       pengiriman, pengiriman bernetto, jam masuk tercatat, dan kedua-jam
       tercatat — ditambah penyebut tiap ukuran mutu. Kalimat yang menamainya
       datang UTUH dari payload (`_note_four_denominators`).

    4. TIDAK ADA REKAP BERLABEL: stasiun ini tanpa kolom berpilihan tertutup,
       dan ketiadaannya dinyatakan utuh dari payload (`_note_no_labeled_recap`)
       karena pembaca yang datang dari Laporan Kernel Dispatch akan mencarinya.
       Tiga rekap teks bebas (pengangkut, tangki sumber, pembeli tujuan) ditulis
       SEKALI lalu di-loop.

    JEBAKAN TENGAH MALAM: jam dicatat tanpa tanggal, sehingga jam keluar yang
    lebih kecil dari jam masuk dibaca hari berikutnya dan jumlahnya
    (`crossed_midnight_count`) DIRENDER SELALU, juga ketika nol — angka yang hanya
    muncul ketika tidak nol tidak membuktikan pembacaannya berjalan. Waktu putar
    yang tampak tidak wajar TIDAK ditandai apa pun (tanpa warna, lencana, atau
    hitungan pelanggaran); layar hanya menyatakan bahwa angka seperti itu patut
    diperiksa pembaca.

    DOBI INDEKS TANPA SATUAN: tidak pernah diberi '%', dan prosanya dirender di
    dalam kartunya sendiri. Kolom `shift` tidak ditampilkan di layar (hanya di
    ekspor).

    Kosakata kelasnya `md-*` (dashboard/partials/report-styles.blade.php), dipakai
    APA ADANYA — layar ini TIDAK menambah satu kelas pun. Sebaran jam memakai
    `md-trendchart*` seperti laporan Weighbridge, yang menggulir DI DALAM
    kartunya (overflow-x: auto) sehingga halaman tidak bergeser mendatar.

    CSS TIDAK di-include di sini (lihat dashboard/laporan-cpo-dispatch.blade.php).
    Bacaan saja: tidak ada tombol/field yang mengubah data stasiun.
--}}
@php
    // Angka dengan jumlah desimal tetap. null SELALU '—', TIDAK PERNAH 0: nol
    // berarti 'terukur dan hasilnya nol', '—' berarti 'tidak pernah terukur'.
    $nilai = function ($value, int $digits = 2) {
        if ($value === null) {
            return '—';
        }

        return number_format((float) $value, $digits, ',', '.');
    };

    $cacah = fn ($value) => number_format((float) $value, 0, ',', '.');

    // Jam bulat 'HH:00'; null (tidak ada jam tercatat) '—', BUKAN '00:00'.
    $jam = fn ($hour) => $hour === null ? '—' : sprintf('%02d:00', (int) $hour);

    // Menit → '1 jam 15 menit'. Hanya terjemahan tampilan dari menit yang
    // diterbitkan service; tidak ada angka baru dihitung di sini. null → '—'.
    $durasi = function ($minutes) {
        if ($minutes === null) {
            return '—';
        }

        $minutes = (int) round((float) $minutes);

        return intdiv($minutes, 60) > 0
            ? intdiv($minutes, 60).' jam '.($minutes % 60).' menit'
            : $minutes.' menit';
    };

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

    // Desimal kartu mutu. TIGA, sama dengan pembulatan service.
    $digitMutu = 3;

    $dispatches = $summary['dispatches'] ?? null;
    $quality = $summary['quality'] ?? null;
    $turnaround = $summary['turnaround'] ?? null;
    $hourly = $summary['hourly'] ?? null;
    $compliance = $summary['compliance'] ?? null;
    $daily = $summary['daily'] ?? [];
    $dailyTotal = $summary['daily_total'] ?? null;
    $completeness = $summary['completeness'] ?? null;
    $textEntries = $summary['text_entries'] ?? ['findings' => []];
    $draftDispatchCount = $summary['draft_dispatch_count'] ?? 0;

    // Kelompok jam tersibuk, dibaca dari bucket yang sudah diterbitkan — bukan
    // dihitung ulang. null bila tak ada jam tercatat.
    $bucketTersibuk = $hourly === null || $hourly['busiest_hour'] === null
        ? null
        : collect($hourly['buckets'])->firstWhere('hour', $hourly['busiest_hour']);
    $maksJam = $hourly === null ? 0 : max(array_map(fn ($row) => $row['dispatch_count'], $hourly['buckets']) ?: [0]);

    // TIGA REKAP TEKS BEBAS — markup tabelnya ditulis SEKALI lalu di-loop,
    // bukan disalin tiga kali.
    $rekapTeks = [
        [
            'key' => 'by_transport_company',
            'testid' => 'by-transport-company',
            'judul' => 'Rekap per Perusahaan Pengangkut',
            'kolom' => 'transport_company',
            'label' => 'Perusahaan pengangkut',
            'kosong' => 'perusahaan pengangkut',
        ],
        [
            'key' => 'by_storage_tank_source',
            'testid' => 'by-storage-tank-source',
            'judul' => 'Rekap per Tangki Sumber',
            'kolom' => 'storage_tank_source',
            'label' => 'Tangki sumber',
            'kosong' => 'tangki sumber',
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

    // Periode masih berjalan / belum mulai, dari $countedUntil yang diserahkan
    // komponen (bukan now() di blade).
    $periodeBerjalan = $selectedPeriod !== null && $countedUntil < $selectedPeriod['end_date'];
    $periodeBelumMulai = $completeness !== null && (int) $completeness['days_counted'] === 0;

    $ikonInfo = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>';
    $ikonJam = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
@endphp

<div class="md" data-testid="laporan-cpo-dispatch">

    {{-- ============ 1. Hero: periode yang sedang dibaca ============ --}}
    <section class="md-hero" data-testid="report-hero">
        <div class="md-hero__main">
            <p class="md-hero__eyebrow">Laporan Periode &middot; Stasiun CPO Dispatch</p>
            <h1 class="md-hero__title">{{ $selectedPeriod['name'] ?? 'Laporan CPO Dispatch' }}</h1>
            <p class="md-hero__subtitle" data-testid="hero-subtitle">
                @if ($selectedPeriod)
                    {{ $summary['business_unit']['name'] ?? $businessUnitName }}
                    @if ($selectedProductionLine) &middot; {{ $selectedProductionLine['name'] }} @endif
                    &middot; {{ $tgl($selectedPeriod['start_date']) }} &ndash; {{ $tgl($selectedPeriod['end_date']) }}
                @else
                    Pengiriman minyak sawit mentah (CPO) yang keluar sepanjang satu Periode Pelaporan pada satu Production Line &mdash; satu baris detail adalah satu pengiriman (satu tanker), dengan tonase, mutu, sebaran jam masuk, dan waktu putar dibaca bersama
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
        Hanya periode yang mencakup CPO Dispatch yang ditampilkan, dan periode tertutup tetap dapat dibaca serta diekspor. Angka disaring menurut Production Line yang melekat pada record itu sendiri, dan keanggotaan periode mengikuti tanggal record induknya. Ekspor mengunduh rincian satu baris per pengiriman dengan kolom konteks diulang di setiap baris, memuat seluruh kolom detail termasuk shift, jam masuk dan jam keluar apa adanya, nomor surat jalan, nomor tanker, nama pengemudi, kedua nomor segel dan operator timbang.
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
                menampilkan daftar Production Line, daftar Periode Pelaporan, dan laporan CPO
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
                Laporan CPO Dispatch menghasilkan angka gabungan per line, dan mencampur beberapa
                production line membuat angkanya tidak bisa ditindaklanjuti siapa pun. Pilih satu
                production line pada pemilih di atas untuk menampilkan laporannya. Selama belum dipilih,
                layar ini tidak menampilkan satu angka pun &mdash; termasuk tidak menampilkan angka
                seluruh mill sebagai penggantinya.
            </p>
        </div>
    @elseif ($periods === [])
        {{-- Empty state (d): mill belum punya Periode Pelaporan yang mencakup
             CPO Dispatch. Yang kosong di sini adalah PEMILIHNYA — dibedakan
             dari 'periode tanpa data' di bawah. Bukan 404. --}}
        <div class="md-empty" data-testid="no-period-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
            </span>
            <p class="md-empty__title">Belum ada Periode Pelaporan</p>
            <p class="md-empty__text">
                Mill ini belum memiliki Periode Pelaporan yang mencakup stasiun CPO Dispatch.
                Hubungi Admin agar membuatnya terlebih dahulu di layar Kelola Periode Pelaporan, lalu
                laporan periode akan tampil di sini.
            </p>
        </div>
    @elseif ($summary !== null)

        {{-- ============ 3. Peringatan pokok ============ --}}
        <div class="md-explain" data-testid="hybrid-note">
            {!! $ikonInfo !!}
            <span>
                Satuan hitung laporan ini adalah <b>satu pengiriman</b> &mdash; satu tanker yang datang,
                diisi, dan berangkat &mdash; bukan satu pembacaan per slot waktu. Inilah <b>satu-satunya
                laporan pengiriman yang dapat menjawab pertanyaan waktu</b>, karena hanya catatan stasiun
                ini yang membawa <b>jam masuk</b> dan <b>jam keluar</b> tanker: dari keduanya lahir
                <b>sebaran pengiriman menurut jam masuk</b> (kapan jembatan timbang padat) dan <b>waktu
                putar tanker</b> (berapa lama satu tanker berada di dalam pabrik).
                <small>
                    Jam dicatat <b>tanpa tanggal</b>, jadi jam keluar yang lebih kecil dari jam masuk
                    dibaca sebagai hari berikutnya &mdash; dan jumlah pengiriman yang dibaca demikian
                    dinyatakan di kartunya sendiri. Berat bersih <b>diturunkan server</b> (bruto dikurangi
                    tara) dan dibaca apa adanya; keempat nilai mutu <b>diisi dari hasil uji</b> dan tak
                    satu pun wajib, sehingga tiap ukuran punya penyebutnya sendiri.
                </small>
            </span>
        </div>

        @if ((int) $dispatches['dispatch_count'] === 0)
            {{-- Keadaan KOSONG yang NYATA hari ini (tabel detail ini nol baris di
                 dev). Kerangka di bawah TETAP dirender utuh — kartu tonase dan
                 mutu '—', sebaran 24 jam kosong, waktu putar '—' — dan kalimat
                 ini menyebut periode DAN line-nya supaya jelas yang kosong adalah
                 pilihan ini, bukan layarnya. --}}
            <div class="md-empty" data-testid="empty-state">
                <span class="md-empty__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 17h-4V5h12v12h-3"/><path d="M10 9H6L3 12v5h2"/><circle cx="7.5" cy="17.5" r="2"/><circle cx="17.5" cy="17.5" r="2"/></svg>
                </span>
                <p class="md-empty__title">Belum ada pengiriman pada periode dan line ini</p>
                <p class="md-empty__text">
                    Tidak ada satu pengiriman CPO pun pada periode {{ $summary['period']['name'] }}
                    di line {{ $summary['production_line']['name'] }}
                    ({{ $tgl($summary['period']['start_date']) }} &ndash; {{ $tgl($summary['period']['end_date']) }}).
                    Seluruh angka di bawah karena itu ditampilkan sebagai tanda pisah atau nol pengiriman,
                    dan kedua bagian waktu &mdash; sebaran menurut jam masuk dan waktu putar tanker &mdash;
                    tetap ditampilkan dalam keadaan kosong.
                </p>
            </div>
        @endif

        {{-- ============ 4 + 5. SATU KOTAK: tonase + EMPAT UKURAN MUTU ============
             Tonase dan mutu dibaca bersama, jadi keduanya di dalam satu container. --}}
        <section class="md-card" data-testid="dispatch-quality-card">
            <header class="md-card__head">
                <h3>Pengiriman CPO Periode Ini</h3>
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
                    <p class="md-kpi__foot"><small>Penyebut (a). Satu pengiriman = satu baris detail, bukan satu record. Angka ini menghitung seluruh pengiriman, termasuk yang penimbangannya belum selesai, yang belum punya jam, dan yang belum punya satu hasil uji pun.</small></p>
                </article>

                <article class="md-kpi" data-testid="kartu-total-netto">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Total Berat Bersih CPO</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18"/><path d="M5 7h14"/><path d="M5 7l-3 7h6z"/><path d="M19 7l-3 7h6z"/><path d="M8 21h8"/></svg></span>
                    </div>
                    <p class="md-kpi__value" data-testid="net-weight-total">{{ $nilai($dispatches['net_weight_total'], 1) }} <span>MT</span></p>
                    <p class="md-kpi__meta">dari <b>{{ $cacah($dispatches['net_weight_dispatch_count']) }} pengiriman</b> yang nettonya benar-benar terisi</p>
                    <p class="md-kpi__foot">{{ $cacah($dispatches['missing_net_weight_dispatch_count']) }} pengiriman <b>tidak ikut dijumlahkan</b> karena nettonya belum ada</p>
                    <p class="md-kpi__foot"><small>Penyebut (b) dicetak berdampingan dengan totalnya. Ketika tak satu pun pengiriman bertimbang, angka di atas dicetak sebagai tanda pisah dan <b>tidak pernah sebagai nol</b>: nol berarti tidak ada CPO terangkut, tanda pisah berarti tidak diketahui.</small></p>
                </article>

                <article class="md-kpi" data-testid="kartu-rata-netto">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Rata-rata Netto per Pengiriman</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 18h16"/><path d="M4 14l5-5 4 4 7-7"/></svg></span>
                    </div>
                    <p class="md-kpi__value" data-testid="net-weight-avg">{{ $nilai($dispatches['net_weight_avg'], 2) }} <span>MT</span></p>
                    <p class="md-kpi__meta">penyebutnya <b data-testid="net-weight-dispatch-count">{{ $cacah($dispatches['net_weight_dispatch_count']) }}</b> pengiriman, bukan {{ $cacah($dispatches['dispatch_count']) }}</p>
                    <p class="md-kpi__foot">{{ $nilai($dispatches['net_weight_total'], 1) }} MT &divide; {{ $cacah($dispatches['net_weight_dispatch_count']) }} pengiriman</p>
                    <p class="md-kpi__foot"><small>Membaginya dengan seluruh pengiriman periode menghasilkan rata-rata yang turun secara palsu hanya karena ada penimbangan yang belum selesai. Pengiriman bernetto kosong <b>tidak menurunkan rata-rata, ia hilang darinya</b>.</small></p>
                </article>

                <article class="md-kpi" data-testid="kartu-penimbangan-belum-selesai">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Penimbangan Belum Selesai</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg></span>
                    </div>
                    <p class="md-kpi__value" data-testid="missing-net-weight-dispatch-count">{{ $cacah($dispatches['missing_net_weight_dispatch_count']) }} <span>pengiriman</span></p>
                    <p class="md-kpi__meta">netto kosong karena <b>bruto atau tara belum terisi</b> &mdash; <b>penimbangan belum selesai</b>, bukan nol</p>
                    <p class="md-kpi__foot">ikut jumlah pengiriman &mdash; <b>tidak</b> ikut total maupun rata-rata netto</p>
                    <p class="md-kpi__foot"><small>Berat bersih adalah kolom <b>turunan server</b>, jadi kosongnya bukan &lsquo;lupa diisi&rsquo; melainkan tanker yang sudah ditimbang kotor tetapi belum kembali ditimbang kosong, atau sebaliknya. Ia <b>tidak berhubungan</b> dengan jam maupun hasil uji mutu: itu kolom yang berbeda, dan satu pengiriman boleh punya salah satunya saja.</small></p>
                </article>
            </div>

            {{-- ---- EMPAT KARTU MUTU, DI DALAM KOTAK YANG SAMA ----
                 Daftar kolomnya datang dari CpoDispatchReportService::QUALITY_MEASURES
                 lewat $qualityMeasures, tidak ditulis ulang di sini. Tiap kartu
                 memeriksa measured_count-NYA SENDIRI — tidak ada penjaga global. --}}
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
                        {{-- null SELALU '—', TIDAK PERNAH 0. Satuan hanya dicetak
                             bila ada: DOBI indeks tanpa satuan, tidak pernah '%'. --}}
                        <p class="md-kpi__value">
                            <span data-testid="quality-avg">{{ $nilai($m['avg'], $digitMutu) }}</span>
                            <span>{{ $ukuran['satuan'] !== null ? $ukuran['satuan'].' ' : '' }}rata-rata</span>
                        </p>
                        <p class="md-kpi__meta" data-testid="quality-range">
                            terendah <span data-testid="quality-min">{{ $nilai($m['min'], $digitMutu) }}</span>
                            &middot; tertinggi <span data-testid="quality-max">{{ $nilai($m['max'], $digitMutu) }}</span>
                        </p>
                        <p class="md-kpi__foot">
                            dari <b data-testid="quality-measured-count">{{ $cacah($m['measured_count']) }}</b>
                            pengiriman yang {{ $ukuran['pendek'] }}-nya diuji
                            <small>(penyebut ukuran ini sendiri &mdash; pengiriman yang tidak diujikan kolom ini <b>tidak mengukurnya nol</b>, ia tidak mengukurnya sama sekali)</small>
                        </p>
                        @if (isset($m['_note']))
                            {{-- Prosa milik blok ini di payload (DOBI), dirender
                                 UTUH DI DALAM kartunya supaya tak terpisah dari angkanya. --}}
                            <p class="md-kpi__foot" data-testid="quality-measure-note"><small>{{ $m['_note'] }}</small></p>
                        @endif
                        {{-- PENJAGA PER UKURAN. --}}
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
                {!! $ikonInfo !!}
                <span>{{ $quality['_note'] }}</span>
            </div>
        </section>

        {{-- ============ 6. EMPAT PENYEBUT, dinamai utuh dari payload ============ --}}
        <div class="md-explain" data-testid="four-denominators-note">
            {!! $ikonInfo !!}
            <span>{{ $summary['_note_four_denominators'] }}</span>
        </div>

        {{-- ============ 7. WAKTU PUTAR TANKER ============
             Dirender SELALU, juga ketika tak satu pengiriman pun punya kedua jam
             (min/avg/max '—', measured_count 0). crossed_midnight_count dicetak
             SEBAGAI NOL ketika tak ada yang menyeberang — bukan disembunyikan.
             Tanpa penandaan nilai tak wajar apa pun. --}}
        <section class="md-card" data-testid="turnaround-card">
            <header class="md-card__head">
                <h3>Waktu Putar Tanker</h3>
                <span class="md-card__hint">dari jam masuk ke jam keluar &mdash; hanya pengiriman yang <b>kedua jamnya</b> tercatat</span>
            </header>

            <div class="md-kpis md-kpis--3">
                <article class="md-kpi" data-testid="kpi-turnaround-min">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Waktu Putar Tercepat</span>
                        <span class="md-kpi__icon">{!! $ikonJam !!}</span>
                    </div>
                    <p class="md-kpi__value" data-testid="turnaround-min">{{ $nilai($turnaround['min_minutes'], 0) }} <span>menit</span></p>
                    <p class="md-kpi__meta">{{ $durasi($turnaround['min_minutes']) }}</p>
                    <p class="md-kpi__foot">dari <b>{{ $cacah($turnaround['measured_count']) }}</b> pengiriman yang kedua jamnya tercatat</p>
                </article>
                <article class="md-kpi" data-testid="kpi-turnaround-avg">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Rata-rata Waktu Putar</span>
                        <span class="md-kpi__icon">{!! $ikonJam !!}</span>
                    </div>
                    <p class="md-kpi__value" data-testid="turnaround-avg">{{ $nilai($turnaround['avg_minutes'], 1) }} <span>menit</span></p>
                    <p class="md-kpi__meta">{{ $durasi($turnaround['avg_minutes']) }}</p>
                    <p class="md-kpi__foot">penyebutnya <b>{{ $cacah($turnaround['measured_count']) }}</b> pengiriman, bukan {{ $cacah($dispatches['dispatch_count']) }}</p>
                </article>
                <article class="md-kpi" data-testid="kpi-turnaround-max">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Waktu Putar Terlama</span>
                        <span class="md-kpi__icon">{!! $ikonJam !!}</span>
                    </div>
                    <p class="md-kpi__value" data-testid="turnaround-max">{{ $nilai($turnaround['max_minutes'], 0) }} <span>menit</span></p>
                    <p class="md-kpi__meta">{{ $durasi($turnaround['max_minutes']) }}</p>
                    <p class="md-kpi__foot">dilaporkan <b>apa adanya</b> &mdash; tidak dipotong dan tidak ditandai</p>
                </article>
            </div>

            <div class="md-kpis md-kpis--3">
                <article class="md-kpi" data-testid="kpi-turnaround-measured">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Waktu Putar Terhitung</span>
                        <span class="md-kpi__icon">{!! $ikonJam !!}</span>
                    </div>
                    <p class="md-kpi__value" data-testid="turnaround-measured-count">{{ $cacah($turnaround['measured_count']) }} <span>dari {{ $cacah($dispatches['dispatch_count']) }} pengiriman</span></p>
                    <p class="md-kpi__meta">jam masuk <b>dan</b> jam keluarnya tercatat &mdash; penyebut (d)</p>
                    <p class="md-kpi__foot">nol menit (jam masuk sama dengan jam keluar) ikut terhitung di sini</p>
                </article>
                <article class="md-kpi" data-testid="kpi-crossed-midnight">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Dibaca Menyeberang Tengah Malam</span>
                        <span class="md-kpi__icon">{!! $ikonJam !!}</span>
                    </div>
                    <p class="md-kpi__value" data-testid="crossed-midnight-count">{{ $cacah($turnaround['crossed_midnight_count']) }} <span>pengiriman</span></p>
                    <p class="md-kpi__meta">jam keluarnya <b>lebih kecil</b> daripada jam masuk, dibaca sebagai <b>hari berikutnya</b></p>
                    <p class="md-kpi__foot">waktu putar pengiriman ini lahir dari <b>penafsiran</b>, bukan pembacaan langsung &mdash; nol pun tetap dicetak</p>
                </article>
                <article class="md-kpi" data-testid="kpi-turnaround-uncomputable">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Waktu Putar Tak Dapat Dihitung</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M4.9 4.9l14.2 14.2"/></svg></span>
                    </div>
                    <p class="md-kpi__value" data-testid="turnaround-uncomputable-count">{{ $cacah($turnaround['uncomputable_count']) }} <span>pengiriman</span></p>
                    <p class="md-kpi__meta">salah satu jamnya <b>tidak tercatat</b></p>
                    <p class="md-kpi__foot">terhitung + tak dapat dihitung = {{ $cacah($dispatches['dispatch_count']) }} pengiriman</p>
                </article>
            </div>

            @if ((int) $turnaround['measured_count'] === 0)
                <p class="md-filters__hint" data-testid="turnaround-none">
                    Tidak ada satu pengiriman pun yang jam masuk <b>dan</b> jam keluarnya tercatat pada periode
                    dan line ini, jadi waktu putar tercepat, rata-rata, dan terlama ditulis sebagai tanda pisah
                    &mdash; bukan nol. Bagian ini tetap ditampilkan supaya ketiadaannya terlihat.
                </p>
            @endif

            {{-- Prosa milik payload, dirender UTUH. --}}
            <div class="md-explain" data-testid="turnaround-note">
                {!! $ikonInfo !!}
                <span>{{ $turnaround['_note'] }}</span>
            </div>
        </section>

        {{-- ============ 8. SEBARAN MENURUT JAM MASUK ============
             24 kolom SELALU digambar (jam nol diredupkan, bukan dihilangkan) dan
             kelompok 'jam tidak tercatat' berdiri sendiri. Dikelompokkan menurut
             JAM MASUK saja. Menggulir DI DALAM kartunya. --}}
        <section class="md-card" data-testid="hourly-card">
            <header class="md-card__head">
                <h3>Sebaran Pengiriman menurut Jam Masuk</h3>
                <span class="md-card__hint">00&ndash;23, dijumlahkan atas seluruh hari periode &middot; berdasarkan <b>jam masuk</b> tanker</span>
            </header>

            <div class="md-kpis md-kpis--4">
                <article class="md-kpi" data-testid="kpi-busiest-hour">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Jam Masuk Tersibuk</span>
                        <span class="md-kpi__icon">{!! $ikonJam !!}</span>
                    </div>
                    <p class="md-kpi__value" data-testid="busiest-hour">{{ $jam($hourly['busiest_hour']) }}</p>
                    <p class="md-kpi__meta">
                        @if ($bucketTersibuk !== null)
                            {{ $cacah($bucketTersibuk['dispatch_count']) }} pengiriman masuk pada jam itu
                        @else
                            tak ada satu jam masuk pun tercatat
                        @endif
                    </p>
                    <p class="md-kpi__foot">ditulis sebagai tanda pisah &mdash; bukan 00:00 &mdash; bila tak ada jam tercatat</p>
                </article>
                <article class="md-kpi" data-testid="kpi-empty-hours">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Jam Tanpa Pengiriman</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M4.9 4.9l14.2 14.2"/></svg></span>
                    </div>
                    <p class="md-kpi__value" data-testid="empty-hour-count">{{ $cacah($hourly['empty_hour_count']) }} <span>dari 24 jam</span></p>
                    <p class="md-kpi__meta">tak satu tanker pun masuk pada jam-jam itu sepanjang seluruh periode</p>
                    <p class="md-kpi__foot">24 jam selalu digambar utuh &mdash; jam kosong terbaca sebagai kosong</p>
                </article>
                <article class="md-kpi" data-testid="kpi-hourly-recorded">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Jam Masuk Tercatat</span>
                        <span class="md-kpi__icon">{!! $ikonJam !!}</span>
                    </div>
                    <p class="md-kpi__value" data-testid="hourly-recorded-count">{{ $cacah($hourly['recorded_count']) }} <span>dari {{ $cacah($dispatches['dispatch_count']) }} pengiriman</span></p>
                    <p class="md-kpi__meta">tersebar pada kedua puluh empat kolom di bawah &mdash; penyebut (c)</p>
                    <p class="md-kpi__foot">jam keluar <b>tidak pernah</b> dipakai sebagai pengganti jam masuk</p>
                </article>
                <article class="md-kpi" data-testid="kpi-hourly-not-recorded">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Jam Masuk Tidak Tercatat</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg></span>
                    </div>
                    <p class="md-kpi__value" data-testid="hourly-not-recorded-count">{{ $cacah($hourly['not_recorded_count']) }} <span>pengiriman</span></p>
                    <p class="md-kpi__meta">kelompok <b>jam tidak tercatat</b> &mdash; berdiri sendiri, tidak masuk kolom jam mana pun</p>
                    <p class="md-kpi__foot">24 kolom + kelompok ini = {{ $cacah($dispatches['dispatch_count']) }} pengiriman</p>
                </article>
            </div>

            @if ((int) $hourly['recorded_count'] === 0)
                <p class="md-filters__hint" data-testid="hourly-none-recorded">
                    Tidak ada satu jam masuk pun yang tercatat pada periode dan line ini, jadi kedua puluh
                    empat kolom di bawah kosong dan jam tersibuk ditulis sebagai tanda pisah. Sebaran tetap
                    digambar supaya ketiadaan data terlihat.
                </p>
            @endif

            <div class="md-trendchart" data-testid="hourly-chart">
                @foreach ($hourly['buckets'] as $row)
                    {{-- Jam bernilai nol TETAP digambar, diredupkan — grafik
                         harus tetap 24 kolom. --}}
                    <div class="md-trendchart__col @if ($row['dispatch_count'] === 0) md-trendchart__col--off @endif"
                         data-testid="hourly-col-{{ $row['hour'] }}"
                         title="Jam {{ sprintf('%02d', $row['hour']) }} &mdash; {{ $cacah($row['dispatch_count']) }} pengiriman">
                        <span class="md-trendchart__val">{{ $cacah($row['dispatch_count']) }}</span>
                        <div class="md-trendchart__bar" style="height: {{ round(100 * $row['dispatch_count'] / max($maksJam, 1), 1) }}%"></div>
                        <span class="md-trendchart__lbl">{{ sprintf('%02d', $row['hour']) }}</span>
                    </div>
                @endforeach
            </div>
            <p class="md-scrollhint" data-testid="hourly-scroll-hint">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M15 8l4 4-4 4M9 8l-4 4 4 4"/></svg>
                Grafik ini menggulir mendatar di dalam kartunya sendiri &mdash; halaman tidak pernah
                menggulir mendatar.
            </p>

            {{-- Prosa milik payload, dirender UTUH. --}}
            <div class="md-explain" data-testid="hourly-note">
                {!! $ikonInfo !!}
                <span>{{ $hourly['_note'] }}</span>
            </div>
        </section>

        {{-- ============ 9. Tiga rekap teks bebas ============ --}}
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
                                     posisinya ditentukan tonasenya. --}}
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
                    {!! $ikonInfo !!}
                    <span>
                        <code>{{ $rekap['kolom'] }}</code> adalah kolom <b>teks bebas</b> pada tabel
                        detail &mdash; bukan relasi, bukan daftar nilai tertutup &mdash; jadi nilainya
                        dikelompokkan <b>apa adanya</b>, tanpa satu join pun dan tanpa pelabelan.
                        Seluruh nilai ditampilkan, termasuk yang hanya muncul sekali: rekap ini
                        <b>tidak pernah melipat</b> nilai berfrekuensi rendah menjadi
                        &lsquo;lainnya&rsquo;. Baris <b>tidak tercatat</b> berdiri sendiri dan tidak
                        dibuang dari jumlah, sehingga kolom Pengiriman menjumlah tepat ke
                        {{ $cacah($dispatches['dispatch_count']) }} pengiriman.
                        <small>
                            Diurutkan dari <b>total netto terbesar</b>, lalu jumlah pengiriman, lalu
                            nilainya sebagai pemutus seri. Nilai yang berbeda hanya pada besar-kecil
                            huruf tetap dua baris berbeda &mdash; kemungkinan itu dibiarkan terlihat,
                            karena ia tanda kolom tersebut perlu data master.
                        </small>
                    </span>
                </div>
            </details>
        @endforeach

        {{-- ============ 10. PERNYATAAN: tidak ada rekap berlabel ============
             Dirender UTUH dari payload: pembaca yang datang dari Laporan Kernel
             Dispatch akan mencari rekap itu dan perlu tahu mengapa ia tidak ada. --}}
        <div class="md-explain" data-testid="no-labeled-recap-note">
            {!! $ikonInfo !!}
            <span>{{ $summary['_note_no_labeled_recap'] }}</span>
        </div>

        {{-- ============ 11. Jejak kepatuhan ============
             TIGA PENGHITUNG; kedua penghitung segel TIDAK PERNAH DIJUMLAHKAN. --}}
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
            <div class="md-explain" data-testid="compliance-note">
                {!! $ikonInfo !!}
                <span>{{ $compliance['_note'] }}</span>
            </div>
        </section>

        {{-- ============ 12. PERNYATAAN: tanpa penandaan nilai mutu di luar batas ============
             Kelasnya .md-explain, BUKAN .md-threshold. --}}
        <div class="md-explain" data-testid="no-quality-thresholds-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 18h16"/><path d="M4 14l5-5 4 4 7-7"/></svg>
            <span>{{ $summary['_note_no_quality_thresholds'] }}</span>
        </div>

        {{-- ============ 13. PERNYATAAN: kolom shift tidak ditampilkan ============ --}}
        <div class="md-explain" data-testid="shift-not-published-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M4.9 4.9l14.2 14.2"/></svg>
            <span>{{ $summary['_note_shift_not_published'] }}</span>
        </div>

        {{-- ============ 14. Rekap harian ============
             <details> bawaan peramban: membukanya tidak memicu permintaan ke
             server. Tabelnya menggulir mendatar DI DALAM kartunya sendiri. --}}
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
                                    Tidak ada satu pengiriman pun pada periode
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
                {!! $ikonInfo !!}
                <span>
                    Baris total di kaki tabel ini <b>dijumlahkan dari baris-baris harian di atasnya</b>,
                    bukan disalin dari kartu tonase di bagian atas layar. Kolom <b>Pengiriman netto
                    terisi</b> dicetak pada setiap baris karena rata-rata harian berdiri di atas
                    penyebutnya sendiri; hari yang punya pengiriman tetapi tak satu pun nettonya terisi
                    mencetak tanda pisah, bukan nol. Hari tanpa pengiriman tidak mendapat baris sama sekali.
                    Keanggotaan periode ditentukan oleh <b>tanggal record induk</b>, jadi rekap harian
                    dapat memuat tanggal kejadian di luar rentang periode; itu dibiarkan apa adanya
                    supaya total cocok dengan rincian hariannya.
                </span>
            </div>
        </details>

        {{-- ============ 15. Rekap Temuan ============
             SATU DAFTAR (tidak ada kolom catatan kedua). Daftar kosong dicetak
             sebagai PERNYATAAN 'tidak ada', bukan dihilangkan. --}}
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
                    dengan keterangan ini, bukan dihilangkan dari layar.
                </p>
            @endif
            <div class="md-explain" data-testid="findings-note">
                {!! $ikonInfo !!}
                <span>
                    Isinya diterbitkan <b>sebagai teks beserta tanggalnya</b> &mdash; tidak pernah
                    disebar menjadi kategori, tidak pernah dihitung sebagai metrik, dan tidak pernah
                    digrafikkan, karena <code>findings</code> adalah kolom teks bebas. Hanya pengiriman
                    yang kolomnya <b>tidak kosong</b> yang muncul di sini.
                    <small>
                        Stasiun ini punya <b>satu</b> kolom teks kejadian, bukan dua: tidak ada kolom
                        catatan terpisah pada baris detailnya, jadi tidak ada bagian kedua yang hilang.
                    </small>
                </span>
            </div>
        </section>

        {{-- ============ 16. Kelengkapan pencatatan ============ --}}
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
                    {{-- percent NULL (periode belum mulai) → '—', BUKAN 0,0%. --}}
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
                {!! $ikonInfo !!}
                <span>
                    Kelengkapan mengukur <b>hari</b>, bukan kolom: berapa hari periode yang sudah lewat
                    punya minimal satu pengiriman. Rentang periode <b>inklusif di kedua ujung</b>. Angka ini
                    <b>tidak</b> mengukur kelengkapan jam maupun hasil uji mutu &mdash; itu diukur oleh
                    penyebut jam masuk, penyebut waktu putar, dan keempat penyebut mutu, masing-masing
                    untuk kolomnya sendiri.
                </span>
            </div>
        </section>

        {{-- ============ 17. Pengiriman draft ============ --}}
        <section class="md-card" data-testid="draft-card">
            <header class="md-card__head">
                <h3>Pengiriman Berstatus Draft</h3>
                <span class="md-card__hint">ikut terhitung di seluruh angka di layar ini, termasuk waktu putar dan sebaran jam</span>
            </header>
            <div class="md-kpis md-kpis--3">
                <article class="md-kpi" data-testid="kpi-draft-dispatch-count">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Pengiriman Draft</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3v5h5"/><path d="M19 9v10a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7z"/><path d="M9 13h6M9 17h4"/></svg></span>
                    </div>
                    <p class="md-kpi__value" data-testid="draft-dispatch-count">{{ $cacah($draftDispatchCount) }} <span>dari {{ $cacah($dispatches['dispatch_count']) }} pengiriman</span></p>
                    <p class="md-kpi__meta">status <b>Draft</b> pada record induknya</p>
                    <p class="md-kpi__foot"><b>ikut terhitung</b> di jumlah pengiriman, total netto, rata-rata, keempat ukuran mutu beserta penyebutnya, waktu putar, sebaran jam, ketiga rekap, jejak kepatuhan, dan rekap harian</p>
                </article>
            </div>
            <div class="md-explain" data-testid="draft-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg>
                <span>
                    Pengiriman berstatus draft <b>tetap ikut terhitung</b> dalam seluruh angka di layar
                    ini, dan jumlahnya dinyatakan di sini supaya pembaca tahu <b>sebagian angkanya belum
                    final</b>. Aturan ini mengikuti seluruh laporan stasiun yang sudah ada, yang tak satu
                    pun menyaring status record. Pembaca diberi tahu, bukan dilindungi.
                </span>
            </div>
        </section>
    @endif
    </div>
</div>
