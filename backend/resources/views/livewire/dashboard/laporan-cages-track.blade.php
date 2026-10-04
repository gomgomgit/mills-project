{{--
    screen-130--laporan-cages-track-web — Laporan Periode Cages & Tracks.

    Susunan mengikuti mock .asdlc/generated/2-business-spec/screens/html/
    screen-130--laporan-cages-track-web.html: hero, baris filter, 4 kartu
    KPI, 3 kartu KPI kedua, sebaran penumpahan per jam (kartu terpenting),
    tren harian + antrean lori tersisa berdampingan, lalu rekap harian yang
    dapat dibuka/tutup. Yang ditiru adalah SUSUNAN dan kepadatan
    informasinya — kosakata kelasnya `md-*` milik aplikasi
    (dashboard/partials/report-styles.blade.php).

    CSS TIDAK di-include di sini: partial report-styles memancarkan <style>
    dan Livewire 3 memasang wire:id pada elemen ter-render PERTAMA, sehingga
    menaruhnya di dalam/di atas root komponen mematikan seluruh wire:model.
    Partial itu dimuat lewat <x-slot:styles> di dashboard/laporan-cages-track.blade.php.

    Bacaan saja: tidak ada satu pun tombol/field yang mengubah data stasiun.
--}}
@php
    $num = fn ($value) => number_format((float) $value, 0, ',', '.');

    // Angka desimal tampil tanpa koma bila bulat (12), dengan satu desimal
    // bila tidak (11,5) — angka laporan harus terbaca, bukan selalu berkoma.
    // null SELALU menjadi tanda pisah, tidak pernah menjadi 0.
    $dec = function ($value) {
        if ($value === null) {
            return '–';
        }

        return fmod((float) $value, 1.0) === 0.0
            ? number_format((float) $value, 0, ',', '.')
            : number_format((float) $value, 1, ',', '.');
    };

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

    // Jam ditulis 09.00 — sama dengan notasi jam di layar input stasiun.
    $jam = fn ($hour) => $hour === null ? '–' : sprintf('%02d.00', (int) $hour);

    $statusLabel = fn (?string $status) => match ($status) {
        'draft' => 'Draft',
        'open' => 'Terbuka',
        'closed' => 'Tertutup',
        default => (string) $status,
    };

    $kpi = $summary['kpi'] ?? null;
    $hourly = $summary['hourly'] ?? [];
    $daily = $summary['daily'] ?? [];
    $queue = $summary['queue'] ?? null;
    $total = $summary['total'] ?? null;

    // Grafik digambar ketika periode punya tanggal ber-record. Periode yang
    // sama sekali tidak memuat record memberi daily = [], dan grafik kosong
    // TIDAK dipaksa digambar — kartu KPI tetap tampil bernilai nol.
    $hasData = $kpi !== null && count($daily) > 0;
@endphp

<div class="md" data-testid="laporan-cages-track">

    {{-- ============ 1. Hero: periode yang sedang dibaca ============ --}}
    <section class="md-hero" data-testid="report-hero">
        <div class="md-hero__text">
            <p class="md-hero__eyebrow">Laporan Periode &middot; Stasiun Cages &amp; Tracks</p>
            <h1 class="md-hero__title">{{ $selectedPeriod['name'] ?? 'Laporan Cages & Tracks' }}</h1>
            <p class="md-hero__subtitle">
                @if ($selectedPeriod)
                    {{ $summary['period']['business_unit_name'] ?? '' }}
                    @if (! empty($summary['period']['business_unit_name'])) &middot; @endif
                    {{-- Nama line di hero, sama seperti Laporan Weighbridge
                         (temuan audit 2026-10-04 #10). --}}
                    @if ($selectedProductionLine) {{ $selectedProductionLine['name'] }} &middot; @endif
                    {{ $tgl($selectedPeriod['start_date']) }} &ndash; {{ $tgl($selectedPeriod['end_date']) }}
                @else
                    Pola penumpahan lori sepanjang satu Periode Pelaporan &mdash; kapan memuncak, kapan berhenti
                @endif
            </p>
        </div>
        @if ($selectedPeriod)
            <div class="md-hero__meta">
                <span class="md-chip md-chip--date" data-testid="hero-range">
                    {{ $tgl($selectedPeriod['start_date']) }} &ndash; {{ $tgl($selectedPeriod['end_date']) }}
                </span>
                <span class="md-chip md-chip--status {{ $selectedPeriod['status'] === 'closed' ? 'md-chip--closed' : '' }} {{ $selectedPeriod['status'] === 'draft' ? 'md-chip--draft' : '' }}"
                      data-testid="hero-status">
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
            <p class="md-millcurrent" data-testid="mill-current">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V8l5-3v16M14 21V11l5-3v13"/><path d="M9 12h.01M9 16h.01"/></svg>
                Mill: <strong>{{ $businessUnitName }}</strong>
            </p>
        @endif

        {{-- Pemilih Production Line, TANPA opsi "semua" — dan itu berbeda
             dari Data Browser dengan sengaja. Laporan menghasilkan ANGKA
             GABUNGAN: "Total 1.200" yang mencampur belasan line bukan angka
             yang bisa ditindaklanjuti siapa pun, jadi tidak ada satu pun
             pilihan di sini yang berarti "semua line". Opsinya hanya line di
             dalam mill yang berlaku. --}}
        @if (! $needsMillSelection && ! $hasNoMillForAccount)
            <div class="md-field">
                <label class="md-field__label" for="production-line-select">Production Line</label>
                <select id="production-line-select" class="md-field__control"
                        wire:model.live="productionLineId" data-testid="production-line-select">
                    <option value="">&mdash; Pilih Production Line &mdash;</option>
                    @foreach ($productionLineOptions as $option)
                        {{-- @selected WAJIB dirender di server. Livewire 3 tidak
                             menulis balik nilai <select> dari state komponen pada
                             paint pertama: DOM yang dikirim server-lah sumber
                             kebenarannya. Tanpa ini, pengguna yang tiba lewat
                             tautan tile akan melihat "Pilih Production Line"
                             sementara angkanya sudah milik line itu — dua
                             pernyataan yang saling bertentangan di satu layar. --}}
                        <option value="{{ $option['id'] }}"
                                @selected(($selectedProductionLine['id'] ?? null) === $option['id'])>{{ $option['name'] }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if ($selectedProductionLine !== null)
            {{-- Line yang sedang dibaca, dinamai di layar. Seluruh angka di
                 bawah milik line ini saja. --}}
            <div class="md-millcurrent" data-testid="production-line-current">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h16"/><circle cx="8" cy="7" r="1.6"/><circle cx="14" cy="12" r="1.6"/><circle cx="10" cy="17" r="1.6"/></svg>
                Line aktif: <strong>{{ $selectedProductionLine['name'] }}</strong>
            </div>
        @endif

        @if (! $needsMillSelection && ! $hasNoMillForAccount)
            <div class="md-field">
                <label class="md-field__label" for="period-select">Periode Pelaporan</label>
                <select id="period-select" class="md-field__control"
                        wire:model.live="periodId" data-testid="period-select">
                    @if ($periods === [])
                        <option value="">&mdash; Belum ada periode &mdash;</option>
                    @endif
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
                        wire:click="export('csv')" data-testid="export-csv">
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
            @if ($isAdmin)
                Pemilih Mill hanya tampil untuk Admin. Supervisor dan Mill Management langsung memakai mill masing-masing.
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
        <div class="md-empty" data-testid="no-mill-for-account">
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
        <div class="md-empty" data-testid="mill-required-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
            </span>
            <p class="md-empty__title">Pilih mill terlebih dahulu</p>
            <p class="md-empty__text">
                Sebagai Admin Anda tidak terikat pada satu mill. Pilih mill pada pemilih di atas
                untuk menampilkan daftar Periode Pelaporan dan laporan Cages &amp; Tracks-nya.
            </p>
        </div>
    @elseif ($needsProductionLineSelection)
        {{-- Mill sudah pasti, production line belum. Sama persis dengan
             keadaan "Admin belum memilih mill" di atas: tidak ada satu angka
             pun yang ditampilkan, karena angka gabungan lintas line tidak
             bisa ditindaklanjuti. Daftar Periode Pelaporan di atas TIDAK
             berubah — periode tetap per mill; yang tersaring adalah
             datanya. --}}
        <div class="md-empty" data-testid="select-production-line-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h16"/><circle cx="8" cy="7" r="1.6"/><circle cx="14" cy="12" r="1.6"/><circle cx="10" cy="17" r="1.6"/></svg>
            </span>
            <p class="md-empty__title">Pilih production line terlebih dahulu</p>
            <p class="md-empty__text">
                Laporan Cages &amp; Tracks menghasilkan angka gabungan, dan mencampur beberapa
                production line membuat angkanya tidak bisa ditindaklanjuti. Pilih satu
                production line pada pemilih di atas untuk menampilkan laporannya.
            </p>
        </div>
    @elseif ($periods === [])
        {{-- Empty state (c): mill belum punya Periode Pelaporan yang mencakup
             Cages & Tracks. Bukan 404 — cukup arahkan ke layar yang
             membuatnya. --}}
        <div class="md-empty" data-testid="no-periods">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
            </span>
            <p class="md-empty__title">Belum ada Periode Pelaporan</p>
            <p class="md-empty__text">
                Mill ini belum memiliki Periode Pelaporan yang mencakup stasiun Cages &amp; Tracks.
                Minta Admin membuatnya terlebih dahulu di layar Kelola Periode Pelaporan,
                lalu laporan periode akan tampil di sini.
            </p>
        </div>
    @elseif ($summary !== null)

        {{-- ============ 3. Empat kartu angka utama ============ --}}
        <section class="md-kpis md-kpis--4" data-testid="report-kpis">
            <article class="md-kpi" data-testid="kpi-total-tipped">
                <div class="md-kpi__top">
                    <span class="md-kpi__label">Total Lori Ditumpahkan</span>
                    <span class="md-kpi__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 12l9 4 9-4M3 17l9 4 9-4"/></svg>
                    </span>
                </div>
                <p class="md-kpi__value">{{ $num($kpi['total_cages_tipped']) }} <span>lori</span></p>
                <p class="md-kpi__meta">
                    Dihitung dari rincian per jam sepanjang {{ $num($kpi['days_with_records']) }} hari berdata
                </p>
                <div class="md-bar"><span style="width: {{ $kpi['total_cages_tipped'] > 0 ? 100 : 0 }}%"></span></div>
                {{-- Angka ringkasan pada record harian (cages_tipped) sengaja
                     tidak pernah ditampilkan: sumber angka penumpahan hanya
                     baris rincian per jam. --}}
                <p class="md-kpi__foot">Sumbernya baris rincian per jam, bukan angka ringkasan record harian</p>
            </article>

            <article class="md-kpi" data-testid="kpi-total-out">
                <div class="md-kpi__top">
                    <span class="md-kpi__label">Total Lori Keluar</span>
                    <span class="md-kpi__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 5l7 7-7 7"/><path d="M21 12H3"/></svg>
                    </span>
                </div>
                <p class="md-kpi__value">{{ $num($kpi['total_cages_out']) }} <span>lori</span></p>
                <p class="md-kpi__meta">
                    @php $selisih = $kpi['total_cages_out'] - $kpi['total_cages_tipped']; @endphp
                    {{-- 0 vs 0 bukan "sama banyak" — belum ada data sama
                         sekali (temuan audit 2026-10-04 #9). --}}
                    @if ($kpi['days_with_records'] === 0)
                        Belum ada record pada periode dan line ini
                    @elseif ($selisih === 0)
                        Sama banyak dengan lori yang ditumpahkan
                    @elseif ($selisih > 0)
                        {{ $num($selisih) }} lori lebih banyak daripada yang ditumpahkan
                    @else
                        {{ $num(abs($selisih)) }} lori lebih sedikit daripada yang ditumpahkan
                    @endif
                </p>
                <div class="md-bar"><span style="width: {{ $kpi['total_cages_out'] > 0 ? 100 : 0 }}%"></span></div>
                <p class="md-kpi__foot">Dijumlahkan per record harian, bukan per baris rincian jam</p>
            </article>

            <article class="md-kpi" data-testid="kpi-avg-per-day">
                <div class="md-kpi__top">
                    <span class="md-kpi__label">Rata-rata per Hari</span>
                    <span class="md-kpi__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 15l4-7 4 5 3-6 4 8h3"/></svg>
                    </span>
                </div>
                {{-- Penyebut 0 hari → "—", bukan "0 lori/hari": null bukan 0
                     (temuan audit 2026-10-04 #9). --}}
                @if ($kpi['days_with_records'] === 0)
                    <p class="md-kpi__value" data-testid="kpi-avg-per-day-empty">&ndash;</p>
                    <p class="md-kpi__meta">Belum ada hari ber-record untuk dijadikan pembagi</p>
                @else
                    <p class="md-kpi__value">{{ $dec($kpi['avg_cages_per_day']) }} <span>lori/hari</span></p>
                    <p class="md-kpi__meta">
                        {{ $num($kpi['total_cages_tipped']) }} lori dibagi {{ $num($kpi['days_with_records']) }} hari ber-record
                    </p>
                @endif
                <div class="md-bar"><span style="width: {{ $kpi['avg_cages_per_day'] > 0 ? 100 : 0 }}%"></span></div>
                {{-- Hari ber-record tanpa satu pun baris rincian TETAP masuk
                     penyebut — membuangnya membuat rata-rata harian terlihat
                     lebih baik daripada kenyataan. --}}
                <p class="md-kpi__foot">Hari ber-record tanpa penumpahan tetap ikut menjadi pembagi</p>
            </article>

            <article class="md-kpi" data-testid="kpi-peak-hour">
                <div class="md-kpi__top">
                    <span class="md-kpi__label">Jam Puncak Penumpahan</span>
                    <span class="md-kpi__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    </span>
                </div>
                <p class="md-kpi__value">
                    {{ $jam($kpi['peak_hour']) }}
                    @if ($kpi['peak_hour'] !== null)
                        <span>&middot; {{ $num($kpi['peak_hour_cages']) }} lori</span>
                    @endif
                </p>
                <p class="md-kpi__meta">
                    @if ($kpi['peak_hour'] === null)
                        Belum ada penumpahan yang dapat dinilai jam puncaknya
                    @else
                        {{ $dec(round(100 * $kpi['peak_hour_cages'] / max($kpi['total_cages_tipped'], 1), 1)) }}% dari seluruh penumpahan periode
                    @endif
                </p>
                <div class="md-bar"><span style="width: {{ $kpi['peak_hour'] !== null ? 100 : 0 }}%"></span></div>
                <p class="md-kpi__foot">Bila dua jam berjumlah sama, jam yang lebih awal yang ditampilkan</p>
            </article>
        </section>

        {{-- ============ 4. Tiga kartu angka kedua ============ --}}
        <section class="md-kpis md-kpis--3" data-testid="report-kpis-secondary">
            <article class="md-kpi" data-testid="kpi-idle-hours">
                <div class="md-kpi__top">
                    <span class="md-kpi__label">Jam Operasi Tanpa Penumpahan</span>
                    <span class="md-kpi__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9 9h6v6H9z"/></svg>
                    </span>
                </div>
                <p class="md-kpi__value">{{ $num($kpi['idle_operating_hours']) }} <span>jam</span></p>
                <p class="md-kpi__meta">Jam kosong DI DALAM jendela operasi tippler</p>
                <div class="md-bar"><span style="width: {{ $kpi['idle_operating_hours'] > 0 ? 100 : 0 }}%"></span></div>
                {{-- Menghitung seluruh 24 jam akan membuat mill satu shift
                     selalu terlihat menganggur 16 jam — angka yang benar
                     tetapi menyesatkan. --}}
                <p class="md-kpi__foot">Hanya dihitung di dalam jam operasi, bukan sepanjang 24 jam</p>
            </article>

            <article class="md-kpi" data-testid="kpi-longest-gap">
                <div class="md-kpi__top">
                    <span class="md-kpi__label">Jeda Penumpahan Terpanjang</span>
                    <span class="md-kpi__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4H6v16h4zM18 4h-4v16h4z"/></svg>
                    </span>
                </div>
                @if ($kpi['longest_gap_hours'] === null)
                    {{-- TIDAK DAPAT DIHITUNG, bukan nol: nol berarti "tidak ada
                         jeda", sedangkan di sini tidak ada satu pun tanggal
                         dengan dua jam penumpahan berbeda untuk diukur. --}}
                    <p class="md-kpi__value">&ndash;</p>
                    <p class="md-kpi__meta" data-testid="insufficient-gap">
                        Tidak ada jeda yang dapat dihitung: belum ada satu pun tanggal
                        dengan dua jam penumpahan berbeda pada periode ini.
                    </p>
                    <div class="md-bar"><span style="width: 0%"></span></div>
                @else
                    <p class="md-kpi__value">{{ $num($kpi['longest_gap_hours']) }} <span>jam</span></p>
                    <p class="md-kpi__meta">Terjadi pada {{ $tgl($kpi['longest_gap_date']) }}</p>
                    <div class="md-bar"><span style="width: 100%"></span></div>
                @endif
                <p class="md-kpi__foot">Diukur antar jam penumpahan dalam satu tanggal, tidak melintasi malam</p>
            </article>

            <article class="md-kpi" data-testid="kpi-tippler-duration">
                <div class="md-kpi__top">
                    <span class="md-kpi__label">Durasi Operasi Tippler Rata-rata</span>
                    <span class="md-kpi__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg>
                    </span>
                </div>
                @if ($kpi['avg_tippler_duration_hours'] === null)
                    {{-- null, BUKAN 0 — nol berarti "tippler tidak pernah
                         jalan", null berarti "tidak dapat dihitung". --}}
                    <p class="md-kpi__value">&ndash;</p>
                    <p class="md-kpi__meta">
                        Durasi operasi tidak tersedia: tidak ada satu pun tanggal yang
                        jendela operasi tippler-nya dapat dihitung.
                    </p>
                    <div class="md-bar"><span style="width: 0%"></span></div>
                @else
                    <p class="md-kpi__value">{{ $dec($kpi['avg_tippler_duration_hours']) }} <span>jam/hari</span></p>
                    <p class="md-kpi__meta">
                        Dihitung dari {{ $num($kpi['days_with_records'] - $kpi['days_without_valid_window']) }}
                        hari yang jendela operasinya pasti
                    </p>
                    <div class="md-bar"><span style="width: 100%"></span></div>
                @endif
                {{-- SELALU dirender berdampingan dengan angka di atas: rata-rata
                     durasi tidak boleh pernah terbaca tanpa tahu berapa hari
                     yang tidak ikut dihitung. --}}
                <p class="md-kpi__foot" data-testid="days-without-valid-window">
                    {{ $num($kpi['days_without_valid_window']) }} hari tanpa waktu berhenti tippler yang sah
                    tidak dihitung
                </p>
            </article>
        </section>

        @if (! $hasData)
            {{-- Empty state (d): periode valid tetapi tidak memuat satu pun
                 record. Kartu KPI tetap tampil bernilai nol; grafik TIDAK
                 dipaksa menggambar batang kosong. --}}
            <div class="md-empty" data-testid="empty-period">
                <span class="md-empty__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19h16M7 16V9M12 16V5M17 16v-4"/></svg>
                </span>
                <p class="md-empty__title">Belum ada data pada periode ini</p>
                <p class="md-empty__text">
                    Seluruh angka utama ditampilkan nol karena belum ada data pada periode ini &mdash;
                    tidak ada satu pun record Cages &amp; Tracks tercatat pada rentang tanggalnya,
                    sehingga grafik dan tabel rekap tidak digambar.
                </p>
            </div>
        @else

            {{-- ============ 5. Sebaran penumpahan per jam ============ --}}
            @php
                $maxHourly = max(array_map(fn ($row) => $row['cages'], $hourly));
                $windowHours = array_values(array_filter($hourly, fn ($row) => $row['within_operating_window']));
                $avgWindowHourly = count($windowHours) > 0
                    ? array_sum(array_map(fn ($row) => $row['cages'], $windowHours)) / count($windowHours)
                    : 0;
            @endphp
            <section class="md-card" data-testid="hourly-distribution">
                <header class="md-card__head">
                    <h3>Sebaran Penumpahan per Jam</h3>
                    <span class="md-card__hint">
                        Seluruh {{ $num($kpi['total_cages_tipped']) }} lori periode ini, dikelompokkan ke jam kejadiannya
                        @if ($kpi['peak_hour'] !== null)
                            &middot; puncak {{ $jam($kpi['peak_hour']) }} ({{ $num($kpi['peak_hour_cages']) }} lori)
                        @endif
                    </span>
                </header>
                {{-- SELALU 24 kolom, hour 0..23, walau sebagian atau seluruhnya
                     nol — agar bentuk grafik tidak berubah antar periode. --}}
                <div class="md-trendchart">
                    @foreach ($hourly as $row)
                        <div class="md-trendchart__col @if (! $row['within_operating_window']) md-trendchart__col--off @elseif ($row['cages'] < $avgWindowHourly) md-trendchart__col--low @endif"
                             data-testid="hourly-col-{{ $row['hour'] }}"
                             data-within-window="{{ $row['within_operating_window'] ? '1' : '0' }}">
                            <span class="md-trendchart__val">{{ $num($row['cages']) }}</span>
                            <div class="md-trendchart__bar" style="height: {{ round(100 * $row['cages'] / max($maxHourly, 1), 1) }}%"></div>
                            <span class="md-trendchart__lbl">{{ sprintf('%02d', $row['hour']) }}</span>
                        </div>
                    @endforeach
                </div>
                <ul class="md-legend">
                    <li class="md-legend__item"><span class="md-legend__swatch md-legend__swatch--active"></span> Jam operasi &mdash; penumpahan di atas rata-rata jam operasi</li>
                    <li class="md-legend__item"><span class="md-legend__swatch md-legend__swatch--low"></span> Jam operasi &mdash; di bawah rata-rata ({{ $dec(round($avgWindowHourly, 1)) }} lori/jam)</li>
                    <li class="md-legend__item"><span class="md-legend__swatch md-legend__swatch--off"></span> Di luar jendela operasi tippler &mdash; tidak pernah dihitung sebagai jam menganggur</li>
                    <li class="md-legend__item">Sumbu mendatar = jam kejadian, bukan tanggal</li>
                </ul>
            </section>

            <div class="md-row md-row--2-1">
                {{-- ============ 6. Tren harian ============ --}}
                @php
                    $maxDaily = max(array_map(fn ($row) => $row['cages_tipped'], $daily));
                    $avgDaily = $kpi['total_cages_tipped'] / max(count($daily), 1);
                @endphp
                <section class="md-card" data-testid="daily-trend">
                    <header class="md-card__head">
                        <h3>Tren Harian</h3>
                        <span class="md-card__hint">
                            Lori ditumpahkan per tanggal &middot; batang oranye = di bawah rata-rata
                            ({{ $dec(round($avgDaily, 1)) }} lori/hari)
                        </span>
                    </header>
                    <div class="md-trendchart md-trendchart--days">
                        @foreach ($daily as $row)
                            <div class="md-trendchart__col {{ $row['cages_tipped'] < $avgDaily ? 'md-trendchart__col--low' : '' }}"
                                 data-testid="daily-trend-col-{{ $row['date'] }}">
                                <span class="md-trendchart__val">{{ $num($row['cages_tipped']) }}</span>
                                <div class="md-trendchart__bar" style="height: {{ round(100 * $row['cages_tipped'] / max($maxDaily, 1), 1) }}%"></div>
                                <span class="md-trendchart__lbl">{{ $tglPendek($row['date']) }}</span>
                            </div>
                        @endforeach
                    </div>
                    @if (count($daily) > 10)
                        <p class="md-scrollhint" data-testid="scroll-hint">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M15 8l4 4-4 4M9 8l-4 4 4 4"/></svg>
                            Geser mendatar untuk melihat seluruh tanggal.
                        </p>
                    @endif
                    <ul class="md-legend">
                        <li class="md-legend__item">{{ count($daily) }} tanggal ber-record &middot; tertinggi {{ $num($maxDaily) }} lori/hari</li>
                        <li class="md-legend__item">Jumlah seluruh batang: {{ $num($total['cages_tipped']) }} lori</li>
                    </ul>
                </section>

                {{-- ============ 7. Antrean lori tersisa ============ --}}
                <section class="md-card" data-testid="queue-card">
                    <header class="md-card__head">
                        <h3>Antrean Lori Tersisa</h3>
                        <span class="md-card__hint">Potret per jam</span>
                    </header>
                    <div class="md-utils">
                        <div class="md-util" data-testid="queue-min">
                            <span class="md-util__icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7 7 7-7"/></svg>
                            </span>
                            <div>
                                <p class="md-util__label">Terendah</p>
                                <p class="md-util__value">{{ $dec($queue['min_remaining']) }} <span class="md-util__ratio">lori</span></p>
                                <p class="md-util__ratio">
                                    @if ($queue['min_remaining'] === null)
                                        Belum ada baris rincian per jam untuk dinilai
                                    @else
                                        Titik tersibuk periode ini &mdash; paling sedikit lori yang menganggur
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="md-util" data-testid="queue-avg">
                            <span class="md-util__icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l3-7 4 14 3-7h4"/></svg>
                            </span>
                            <div>
                                <p class="md-util__label">Rata-rata</p>
                                <p class="md-util__value">{{ $dec($queue['avg_remaining']) }} <span class="md-util__ratio">lori</span></p>
                                <p class="md-util__ratio">Rata-rata seluruh potret per jam sepanjang periode</p>
                            </div>
                        </div>
                    </div>
                    <div class="md-threshold">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 2px"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                        <span>
                            Angka ini adalah <b>potret per jam</b> &mdash; armada lori stasiun dikurangi lori yang
                            ditumpahkan pada jam itu &mdash; <b>bukan antrean yang menumpuk</b> dari jam ke jam.
                            Karena itu yang dilaporkan adalah nilai terendah dan rata-ratanya, dan
                            <b>tidak pernah jumlahnya</b>. Nilai yang makin rendah berarti makin sedikit lori
                            yang menganggur, yaitu saat stasiun paling sibuk.
                        </span>
                    </div>
                </section>
            </div>

            {{-- ============ 8. Rekap harian (tertutup secara bawaan) ============ --}}
            @php
                $windowedDaily = array_values(array_filter($daily, fn ($row) => $row['operating_hours'] !== null));
                $totalOperatingHours = array_sum(array_map(fn ($row) => $row['operating_hours'], $windowedDaily));
            @endphp
            <details class="md-details" data-testid="daily-recap" @if ($showRecap) open @endif>
                <summary wire:click="toggleRekapHarian" data-testid="recap-toggle">
                    Rekap Harian Cages &amp; Tracks
                    <small>{{ count($daily) }} baris &middot; klik untuk {{ $showRecap ? 'menyembunyikan' : 'menampilkan' }}</small>
                </summary>
                <div class="md-recap">
                    <table class="md-table" data-testid="recap-table">
                        <thead>
                            <tr>
                                <th scope="col">Tanggal</th>
                                <th scope="col">Lori Ditumpahkan</th>
                                <th scope="col">Lori Keluar</th>
                                <th scope="col">Jam Operasi</th>
                                <th scope="col">Jam Tanpa Penumpahan</th>
                                <th scope="col">Jeda Terpanjang</th>
                                <th scope="col">Antrean Terendah</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($daily as $row)
                                <tr data-testid="recap-row-{{ $row['date'] }}">
                                    <td>{{ $tgl($row['date']) }}</td>
                                    <td>{{ $num($row['cages_tipped']) }}</td>
                                    <td>{{ $num($row['cages_out']) }}</td>
                                    {{-- Tanggal yang jendelanya tidak dapat dihitung
                                         ditulis "tidak tercatat", TIDAK PERNAH 0 jam. --}}
                                    <td @class(['is-muted' => $row['operating_hours'] === null])>
                                        {{ $row['operating_hours'] === null ? 'tidak tercatat' : $dec($row['operating_hours']).' jam' }}
                                    </td>
                                    <td @class(['is-muted' => $row['idle_operating_hours'] === null])>
                                        {{ $row['idle_operating_hours'] === null ? 'tidak tercatat' : $num($row['idle_operating_hours']).' jam' }}
                                    </td>
                                    <td @class(['is-muted' => $row['longest_gap_hours'] === null])>
                                        {{ $row['longest_gap_hours'] === null ? 'tidak ada' : $num($row['longest_gap_hours']).' jam' }}
                                    </td>
                                    <td @class(['is-muted' => $row['min_remaining'] === null])>
                                        {{ $row['min_remaining'] === null ? '–' : $num($row['min_remaining']) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr data-testid="recap-row-total">
                                <td>TOTAL PERIODE</td>
                                <td>{{ $num($total['cages_tipped']) }}</td>
                                <td>{{ $num($total['cages_out']) }}</td>
                                <td>
                                    {{ $dec(round($totalOperatingHours, 1)) }} jam
                                    <small>({{ count($windowedDaily) }} hari)</small>
                                </td>
                                <td>{{ $num($kpi['idle_operating_hours']) }} jam</td>
                                <td>
                                    {{ $kpi['longest_gap_hours'] === null ? 'tidak ada' : $num($kpi['longest_gap_hours']).' jam' }}
                                    <small>(terpanjang)</small>
                                </td>
                                <td>
                                    {{ $queue['min_remaining'] === null ? '–' : $num($queue['min_remaining']) }}
                                    <small>(terendah)</small>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </details>
        @endif
    @endif
</div>
