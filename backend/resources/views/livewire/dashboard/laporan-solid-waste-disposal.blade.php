{{--
    screen-162--laporan-solid-waste-disposal-web — Laporan Periode Solid Waste
    Disposal.

    SUSUNAN MENGIKUTI MOCK .asdlc/generated/2-business-spec/screens/html/
    screen-162--laporan-solid-waste-disposal-web.html, dan urutan itu BERBEDA
    dengan urutan daftar di api_contracts — yang struktural dan tidak membawa
    maksud tampilan apa pun. Dua urutan berbeda untuk dua keperluan berbeda;
    keduanya sengaja. Urutan layar: hero, baris filter, peringatan pokok
    'per-transaksi dan tidak ada angka total tanpa rinciannya', SATU KOTAK berisi
    empat kartu angka pokok + rekap tujuan penggunaan akhir, tiga rekap
    kategoris lainnya, dua penghitung jejak kepatuhan, pernyataan 'sebaran per
    jam tidak dapat dibuat', pernyataan 'kolom shift tidak ditampilkan', rekap
    harian + tren harian, rekap temuan, rekap catatan, kelengkapan pencatatan,
    muatan draft, lalu pernyataan 'tidak ada downtime dan tidak ada penandaan di
    luar batas'.

    Yang ditiru dari mock adalah SUSUNAN, LABEL dan kepadatan informasinya —
    kosakata kelasnya `md-*` milik aplikasi
    (dashboard/partials/report-styles.blade.php), dipakai APA ADANYA. Mock
    memperkenalkan empat kosakata tambahan (`md-paired*`, `md-kpi--flag`,
    `md-cant*`, `md-no-sum`) yang TIDAK ada di partial bersama, dan
    menambahkannya ke sana akan menyentuh keenam belas laporan lain yang memuat
    partial itu — preseden screen-143, yang menolak `md-group*`/`md-hc*` milik
    mock-nya dengan alasan yang sama. Pemetaan kelasnya ada di docblock
    dashboard/laporan-solid-waste-disposal.blade.php.

    CSS TIDAK di-include di sini: partial report-styles memancarkan <style> dan
    Livewire 3 memasang wire:id pada elemen ter-render PERTAMA, sehingga
    menaruhnya di dalam/di atas root komponen mematikan seluruh wire:model.
    Partial itu dimuat lewat <x-slot:styles> di
    dashboard/laporan-solid-waste-disposal.blade.php.

    DELAPAN HAL YANG MEMBEDAKAN LAYAR INI DARI LAPORAN STASIUN LAIN:

    1. BENTUKNYA PER-TRANSAKSI, BUKAN BERGRID. Satu baris detail adalah SATU
       MUATAN KELUAR — satu kendaraan limbah padat yang meninggalkan pabrik —
       bukan satu pembacaan per slot waktu. Stasiun ini tidak punya grid slot
       waktu, jadi tidak ada kartu minimum/rata-rata/maksimum per slot, dan
       rekap harian HANYA memuat tanggal yang punya muatan: hari tanpa
       pengangkutan adalah keadaan normal, dan baris nol untuk 22 hari akan
       mengubur 9 baris yang berarti. Presedennya screen-143 (Weighbridge),
       BUKAN screen-160 (Process Water).

    2. TIDAK ADA SATU ANGKA 'LIMBAH KELUAR' YANG BERDIRI SENDIRI. Keempat kartu
       angka pokok dan rekap `purpose_end_use` berada DI DALAM SATU KOTAK
       (`.md-card` ber-testid `loads-card`), karena perbedaan antara limbah yang
       dibuang dan limbah yang dimanfaatkan kembali adalah informasi yang paling
       dicari — dan satu angka gabungan menghapusnya. Hubungan DOM itu bagian
       dari kontrak layar ini, bukan kebetulan tata letak.

    3. SETIAP RATA-RATA MENCETAK PENYEBUTNYA DI DALAM KARTUNYA SENDIRI, dan
       begitu pula setiap baris keempat rekap serta setiap baris rekap harian.
       Rata-rata 12 muatan dan rata-rata 1.200 muatan tidak boleh terlihat sama
       meyakinkan, dan sebuah baris rekap yang rata-ratanya aneh harus dapat
       diperiksa tanpa mengunduh ekspor.

    4. 'PENIMBANGAN BELUM SELESAI' ADALAH KARTUNYA SENDIRI, bukan catatan kaki,
       dan kalimatnya berbunyi DEMIKIAN — bukan 'data tidak lengkap'. Netto
       adalah kolom TURUNAN service (bruto − tara), jadi null-nya berarti
       kendaraan sudah ditimbang kotor tetapi belum kembali ditimbang kosong,
       atau sebaliknya: pekerjaan yang menggantung. Angka berat yang kosong
       dicetak '—', NEVER '0,00' — nol berarti 'tidak ada beban terangkut',
       sedangkan '—' berarti 'tidak diketahui'.

    5. KEEMPAT REKAP KATEGORIS PER NILAI APA ADANYA: nol join, nol pemetaan ke
       master, nol penerjemahan. Keempat kolomnya teks bebas pada tabel detail.
       Nilai yang berbeda hanya pada besar-kecil huruf TETAP dua baris berbeda,
       dan nilai berfrekuensi satu tetap mendapat barisnya sendiri — tidak ada
       kelompok 'lainnya' di mana pun. Khususnya `purpose_end_use` TIDAK dilipat
       menjadi dua ember 'dibuang'/'dimanfaatkan': nilai nyatanya belum pernah
       terlihat di basis data mana pun.

    6. BARIS 'TIDAK TERCATAT' ADALAH KELOMPOK SEPERTI YANG LAIN, dan posisinya
       ditentukan TONASENYA — tidak dipaku di baris terakhir. Rekap diurutkan
       total netto DESC → jumlah muatan DESC → nilai ASC (preseden kode:
       WeighbridgeReportService::groupedBreakdownOf), sehingga sekelompok muatan
       tanpa lokasi tercatat tetapi bertonase besar MUNCUL DI ATAS — dan itu
       benar, karena 300 MT yang tidak diketahui tujuannya adalah justru baris
       yang paling perlu dibaca.

    7. TIGA KETIADAAN DINYATAKAN UTUH DI LAYAR, dirender langsung dari payload
       (`_note_no_hourly`, `_note_shift_not_published`, `_note_absences`) dan
       tidak diringkas: sebaran per jam TIDAK DAPAT dibuat (baris detail hanya
       membawa `event_date`, tanpa satu pun kolom waktu — jadi ini bukan fitur
       yang ditunda), kolom `shift` tidak ditampilkan sama sekali meski tetap
       ada di ekspor, dan tidak ada blok downtime maupun penandaan nilai di luar
       batas. Blok yang hilang tanpa penjelasan selalu dibaca sebagai blok yang
       belum dibuat.

    8. `undated_load_count` DICETAK BERDAMPINGAN DENGAN TOTALNYA beserta
       kalimat yang menyatakan hubungan keduanya. Mock tidak menampilkannya;
       TECH-SPEC yang otoritatif. Ia satu-satunya sebab penjumlahan rekap
       harian boleh lebih kecil daripada jumlah muatan, dan selisih tanpa
       penjelasan tidak dapat dibedakan dari cacat.

    SATUAN BERATNYA MT (metrik ton), apa adanya dari kolomnya tanpa konversi —
    label (MT) yang sama dipakai form input (form-solid-waste-disposal), layar
    Detail (detail-solid-waste-disposal) dan Data Browser untuk kolom yang sama.

    Bacaan saja: tidak ada satu pun tombol/field di layar ini yang mengubah data
    stasiun.
--}}
@php
    // Angka dengan jumlah desimal tetap. null SELALU menjadi '—', TIDAK PERNAH
    // 0 — nol berarti 'terukur dan hasilnya nol', sedangkan '—' berarti 'tidak
    // pernah terukur'. Aturan ini berlaku di seluruh halaman: kartu, tabel,
    // maupun kaki tabel. 0,00 MT pada kartu berat adalah cacat yang uji layar
    // ini mencarinya.
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

    $loads = $summary['loads'] ?? null;
    $compliance = $summary['compliance'] ?? null;
    $daily = $summary['daily'] ?? [];
    $dailyTotal = $summary['daily_total'] ?? null;
    $completeness = $summary['completeness'] ?? null;
    $textEntries = $summary['text_entries'] ?? ['findings' => [], 'remarks' => []];
    $draftLoadCount = $summary['draft_load_count'] ?? 0;
    $undatedLoadCount = $summary['undated_load_count'] ?? 0;

    // KETIGA REKAP KATEGORIS LAINNYA — markup tabelnya ditulis SEKALI lalu
    // di-loop, bukan disalin tiga kali: tiga tabel hasil salin-tempel adalah
    // tempat paling mudah sebuah kolom tertinggal pada salah satunya saja.
    // Rekap `purpose_end_use` TIDAK ada di daftar ini karena ia harus dirender
    // DI DALAM kotak angka pokok (lihat docblock poin 2), bukan sebagai kartu
    // tersendiri.
    $rekapLain = [
        [
            'key' => 'by_waste_type',
            'testid' => 'by-waste-type',
            'judul' => 'Rekap per Jenis Limbah Padat',
            'kolom' => 'solid_waste_type',
            'label' => 'Jenis limbah padat',
            'kosong' => 'jenis limbah padat',
        ],
        [
            'key' => 'by_source_station',
            'testid' => 'by-source-station',
            'judul' => 'Rekap per Stasiun Asal',
            'kolom' => 'source_station',
            'label' => 'Stasiun asal',
            'kosong' => 'stasiun asal',
        ],
        [
            'key' => 'by_disposal_site',
            'testid' => 'by-disposal-site',
            'judul' => 'Rekap per Lokasi Pembuangan/Pemanfaatan',
            'kolom' => 'disposal_utilization_site',
            'label' => 'Lokasi pembuangan/pemanfaatan',
            'kosong' => 'lokasi pembuangan/pemanfaatan',
        ],
    ];

    // ---------------------------------------------------------------
    // Geometri grafik garis (kosakata md-lc*, diperkenalkan screen-132).
    // SATU SERI: total netto per tanggal — angka yang sama dengan kolom Total
    // netto pada rekap harian, bukan rata-rata dan bukan jumlah muatan.
    // ---------------------------------------------------------------
    $lcJumlah = count($daily);

    $lcX = function (int $i) use ($lcJumlah) {
        if ($lcJumlah <= 1) {
            return 409.0;
        }

        return round(72 + 674 * $i / ($lcJumlah - 1), 1);
    };

    $beratHarian = [];

    foreach ($daily as $row) {
        if (($row['net_weight_total'] ?? null) !== null) {
            $beratHarian[] = (float) $row['net_weight_total'];
        }
    }

    $adaGrafikTren = $beratHarian !== [];
    // SUMBU DIMULAI DARI NOL, sengaja: yang dibaca adalah besaran berat, dan
    // sumbu terpotong akan melebih-lebihkan naik-turunnya. Langkah sumbu
    // 'angka bulat' lewat App\Support\ChartAxis (temuan audit 2026-10-04 #6).
    $sumbuTren = \App\Support\ChartAxis::nice(0.0, $adaGrafikTren ? max(max($beratHarian), 1.0) : 1.0);
    $trenHi = $sumbuTren['hi'];

    $lcY = fn ($value) => round(260 - 216 * ((float) $value) / $trenHi, 1);

    // Titik grafik. DUA keadaan yang SENGAJA dibedakan: tanggal yang punya
    // muatan tetapi tak satu pun nettonya terisi DILEWATI — tidak digambar pada
    // nol, karena beratnya tidak diketahui dan nol akan mengarang. Tanggal tanpa
    // muatan sama sekali tidak punya entri daily[] sejak dari service, jadi ia
    // tidak punya titik dan tidak punya baris.
    $titikTren = [];

    foreach ($daily as $i => $row) {
        if (($row['net_weight_total'] ?? null) === null) {
            continue;
        }

        $titikTren[] = [$lcX($i), $lcY($row['net_weight_total'])];
    }

    // Periode masih berjalan / belum mulai. DIHITUNG DARI $countedUntil yang
    // diserahkan komponen, bukan dari now() di dalam blade: satu-satunya tempat
    // halaman ini menyentuh waktu sekarang ada di komponennya, sehingga
    // keterangan 'dihitung sampai ...' dapat diuji tanpa menyentuh DOM.
    $periodeBerjalan = $selectedPeriod !== null && $countedUntil < $selectedPeriod['end_date'];
    $periodeBelumMulai = $completeness !== null && (int) $completeness['days_counted'] === 0;
@endphp

<div class="md" data-testid="laporan-solid-waste-disposal">

    {{-- ============ 1. Hero: periode yang sedang dibaca ============ --}}
    <section class="md-hero" data-testid="report-hero">
        <div class="md-hero__main">
            <p class="md-hero__eyebrow">Laporan Periode &middot; Stasiun Solid Waste Disposal</p>
            <h1 class="md-hero__title">{{ $selectedPeriod['name'] ?? 'Laporan Solid Waste Disposal' }}</h1>
            <p class="md-hero__subtitle" data-testid="hero-subtitle">
                @if ($selectedPeriod)
                    {{ $summary['business_unit']['name'] ?? $businessUnitName }}
                    @if ($selectedProductionLine) &middot; {{ $selectedProductionLine['name'] }} @endif
                    &middot; {{ $tgl($selectedPeriod['start_date']) }} &ndash; {{ $tgl($selectedPeriod['end_date']) }}
                @else
                    Muatan limbah padat yang keluar sepanjang satu Periode Pelaporan pada satu Production Line &mdash; satu baris detail adalah satu muatan keluar
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
        Hanya periode yang mencakup Solid Waste Disposal yang ditampilkan, dan periode tertutup tetap dapat dibaca serta diekspor. Angka disaring menurut Production Line yang melekat pada record itu sendiri, dan keanggotaan periode mengikuti tanggal record induknya. Ekspor mengunduh rincian satu baris per muatan dengan kolom konteks diulang di setiap baris, memuat seluruh kolom detail termasuk shift, nomor gate pass, nomor segel dan id operator.
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
                menampilkan daftar Production Line, daftar Periode Pelaporan, dan laporan Solid Waste
                Disposal-nya.
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
                Laporan Solid Waste Disposal menghasilkan angka gabungan per line, dan mencampur
                beberapa production line membuat angkanya tidak bisa ditindaklanjuti siapa pun. Pilih
                satu production line pada pemilih di atas untuk menampilkan laporannya. Selama belum
                dipilih, layar ini tidak menampilkan satu angka pun &mdash; termasuk tidak menampilkan
                angka seluruh mill sebagai penggantinya.
            </p>
        </div>
    @elseif ($periods === [])
        {{-- Empty state (d): mill belum punya Periode Pelaporan yang mencakup
             Solid Waste Disposal. Yang kosong di sini adalah PEMILIHNYA —
             dibedakan dari 'periode tanpa data' di bawah. Bukan 404. --}}
        <div class="md-empty" data-testid="no-period-hint">
            <span class="md-empty__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
            </span>
            <p class="md-empty__title">Belum ada Periode Pelaporan</p>
            <p class="md-empty__text">
                Mill ini belum memiliki Periode Pelaporan yang mencakup stasiun Solid Waste Disposal.
                Hubungi Admin agar membuatnya terlebih dahulu di layar Kelola Periode Pelaporan, lalu
                laporan periode akan tampil di sini.
            </p>
        </div>
    @elseif ($summary !== null)

        {{-- ============ 3. Peringatan pokok ============ --}}
        <div class="md-explain" data-testid="per-transaction-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
            <span>
                Laporan ini meringkas <b>transaksi</b>, bukan pembacaan berkala:
                <b>satu baris detail adalah satu muatan keluar</b> &mdash; satu kendaraan limbah padat
                yang meninggalkan pabrik &mdash; bukan satu pembacaan per slot waktu. Di stasiun ini
                <b>tidak ada grid slot waktu</b>, tidak ada pembacaan per slot, dan karenanya tidak ada
                kartu minimum/rata-rata/maksimum per slot. Satu hal lagi yang mengikat seluruh layar
                ini: <b>tidak ada satu angka &lsquo;limbah keluar&rsquo; yang berdiri sendiri</b> &mdash;
                angka total selalu dirender di dalam satu kotak bersama rekap tujuan penggunaan akhir,
                karena perbedaan antara limbah yang dibuang dan limbah yang dimanfaatkan kembali adalah
                justru informasi yang paling dicari, dan satu angka gabungan menghapusnya.
                <small>
                    Berat bersih <b>diturunkan server</b> sebagai bruto dikurangi tara; ia tidak pernah
                    diisi manusia, dan laporan ini membacanya apa adanya tanpa menghitungnya ulang.
                </small>
            </span>
        </div>

        {{-- ============ 4 + 5. SATU KOTAK: angka pokok + rekap tujuan penggunaan akhir ============
             Keduanya DI DALAM SATU container dengan sengaja (docblock poin 2):
             tidak ada angka total yang boleh terbaca tanpa rinciannya di
             sampingnya. --}}
        <section class="md-card" data-testid="loads-card">
            <header class="md-card__head">
                <h3>Limbah Keluar Periode Ini</h3>
                <span class="md-card__hint">angka total dan rekap tujuan penggunaan akhir sengaja berada di <b>satu kotak</b> &mdash; keduanya tidak dapat dibaca terpisah</span>
            </header>

            <div class="md-kpis md-kpis--4">
                <article class="md-kpi" data-testid="kpi-load-count">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Jumlah Muatan Keluar</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 17h-4V5h12v12h-3"/><path d="M10 9H6L3 12v5h2"/><circle cx="7.5" cy="17.5" r="2"/><circle cx="17.5" cy="17.5" r="2"/></svg></span>
                    </div>
                    <p class="md-kpi__value">{{ $cacah($loads['load_count']) }} <span>muatan</span></p>
                    <p class="md-kpi__meta">sepanjang {{ $tgl($summary['period']['start_date']) }} &ndash; {{ $tgl($summary['period']['end_date']) }}</p>
                    <p class="md-kpi__foot">{{ $cacah($draftLoadCount) }} di antaranya masih <b>draft</b> dan ikut terhitung</p>
                    <p class="md-kpi__foot"><small>Satu muatan = satu baris detail, bukan satu record. Angka ini menghitung seluruh muatan, termasuk yang penimbangannya belum selesai.</small></p>
                </article>

                <article class="md-kpi" data-testid="kartu-total-netto">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Total Berat Bersih</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18"/><path d="M5 7h14"/><path d="M5 7l-3 7h6z"/><path d="M19 7l-3 7h6z"/><path d="M8 21h8"/></svg></span>
                    </div>
                    <p class="md-kpi__value" data-testid="net-weight-total">{{ $nilai($loads['net_weight_total'], 1) }} <span>MT</span></p>
                    <p class="md-kpi__meta">dari <b>{{ $cacah($loads['net_weight_load_count']) }} muatan</b> yang nettonya benar-benar terisi</p>
                    <p class="md-kpi__foot">{{ $cacah($loads['missing_net_weight_load_count']) }} muatan <b>tidak ikut dijumlahkan</b> karena nettonya belum ada</p>
                    <p class="md-kpi__foot"><small>Penyebutnya dicetak berdampingan dengan totalnya supaya pembaca tahu total ini berdiri di atas berapa muatan. Ketika tidak ada satu pun muatan bertimbang, angka di atas dicetak sebagai tanda pisah dan <b>tidak pernah sebagai nol</b>: nol berarti tidak ada beban terangkut, sedangkan tanda pisah berarti tidak diketahui.</small></p>
                </article>

                {{-- PENYEBUT RATA-RATA DICETAK DI DALAM KARTU INI, bukan di
                     tempat lain di halaman: rata-rata dari 6 muatan tidak boleh
                     terlihat sama meyakinkan dengan rata-rata dari 600, dan
                     angka penyebutnya harus dapat disasarkan ke kartunya. --}}
                <article class="md-kpi" data-testid="kartu-rata-netto">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Rata-rata Netto per Muatan</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 18h16"/><path d="M4 14l5-5 4 4 7-7"/></svg></span>
                    </div>
                    <p class="md-kpi__value" data-testid="net-weight-avg">{{ $nilai($loads['net_weight_avg'], 2) }} <span>MT</span></p>
                    <p class="md-kpi__meta">penyebutnya <b data-testid="net-weight-load-count">{{ $cacah($loads['net_weight_load_count']) }}</b> muatan, bukan {{ $cacah($loads['load_count']) }}</p>
                    <p class="md-kpi__foot">{{ $nilai($loads['net_weight_total'], 1) }} MT &divide; {{ $cacah($loads['net_weight_load_count']) }} muatan</p>
                    <p class="md-kpi__foot"><small>Membaginya dengan seluruh muatan periode akan menghasilkan rata-rata yang turun secara palsu hanya karena ada penimbangan yang belum selesai. Muatan yang nettonya kosong <b>tidak menurunkan rata-rata, ia hilang darinya</b>.</small></p>
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
                    <p class="md-kpi__value" data-testid="missing-net-weight-load-count">{{ $cacah($loads['missing_net_weight_load_count']) }} <span>muatan</span></p>
                    <p class="md-kpi__meta">netto kosong karena <b>bruto atau tara belum terisi</b> &mdash; <b>penimbangan belum selesai</b>, bukan nol</p>
                    <p class="md-kpi__foot">ikut jumlah muatan &mdash; <b>tidak</b> ikut total maupun rata-rata netto</p>
                    <p class="md-kpi__foot"><small>Berat bersih adalah kolom <b>turunan server</b> (bruto dikurangi tara), jadi kosongnya bukan &lsquo;lupa diisi&rsquo; melainkan kendaraan yang sudah ditimbang kotor tetapi belum kembali ditimbang kosong, atau sebaliknya. Memasukkannya sebagai nol akan menurunkan total dan rata-rata dengan muatan yang beratnya belum diketahui.</small></p>
                </article>
            </div>

            {{-- undated_load_count DICETAK HANYA KETIKA > 0, berdampingan dengan
                 totalnya beserta kalimat yang menyatakan hubungan keduanya: ia
                 satu-satunya sebab penjumlahan rekap harian boleh lebih kecil
                 daripada jumlah muatan. Tanpa kalimat itu, selisihnya akan selalu
                 dicurigai sebagai bug — dan kecurigaan itu wajar.

                 MENGAPA BERSYARAT, BUKAN SELALU. `event_date` adalah kolom NOT NULL
                 (migrasi 2026_08_31_000003 menuliskan `$table->date('event_date');`
                 tanpa `->nullable()`), jadi muatan tak bertanggal DITOLAK di tingkat
                 skema dan angka ini selalu 0 dalam praktik. Mencetak "0 di antaranya
                 tidak punya tanggal kejadian" pada setiap laporan berarti mengarang
                 kekhawatiran atas keadaan yang dilarang database — dan pembaca yang
                 melihatnya akan mencari masalah yang tidak ada. Ketika angkanya 0,
                 penjumlahan rekap harian memang SAMA DENGAN jumlah muatan, sehingga
                 tidak ada selisih yang perlu dijelaskan.

                 Penghitungnya tetap dipertahankan di payload dan cabang agregasinya
                 tetap diuji, supaya invariannya tetap benar bila kolom itu suatu saat
                 dilonggarkan. --}}
            @if ($undatedLoadCount > 0)
            <div class="md-explain" data-testid="undated-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
                <span>
                    Dari <b>{{ $cacah($loads['load_count']) }} muatan</b> periode ini,
                    <b data-testid="undated-load-count">{{ $cacah($undatedLoadCount) }}</b> di antaranya
                    <b>tidak punya tanggal kejadian</b> sehingga tidak dapat ditempatkan pada hari mana
                    pun. Muatan seperti itu <b>tetap ikut</b> di jumlah muatan, di total serta rata-rata
                    netto, dan di keempat rekap kategoris &mdash; tetapi <b>tidak ikut</b> di rekap
                    harian. Itulah satu-satunya sebab penjumlahan rekap harian
                    ({{ $cacah($dailyTotal['load_count']) }} muatan) boleh lebih kecil daripada jumlah
                    muatan periode, dan selisihnya tepat sebesar angka itu.
                </span>
            </div>
            @endif

            <p class="md-card__hint" data-testid="by-purpose-end-use-sub">
                Rekap per Tujuan Penggunaan Akhir (<code>purpose_end_use</code>) &mdash; rincian wajib dari angka di atas
            </p>

            <div class="md-recap">
                <table class="md-table" data-testid="by-purpose-end-use-table">
                    <thead>
                        <tr>
                            <th scope="col">Tujuan penggunaan akhir</th>
                            <th scope="col">Muatan</th>
                            <th scope="col">Total netto (MT)</th>
                            <th scope="col">Muatan netto terisi</th>
                            <th scope="col">Rata-rata/muatan (MT)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($summary['by_purpose_end_use'] as $row)
                            {{-- Baris value NULL TIDAK PERNAH DIBUANG, dan
                                 posisinya ditentukan tonasenya — bukan dipaku di
                                 baris terakhir. Membuangnya membuat kolom Muatan
                                 berhenti menjumlah ke jumlah muatan periode. --}}
                            <tr data-testid="{{ $row['value'] === null ? 'by-purpose-end-use-row-null' : 'by-purpose-end-use-row' }}">
                                <td @class(['is-muted' => $row['value'] === null])>
                                    @if ($row['value'] === null)
                                        <b>tidak tercatat</b> <small>(kolom tujuan penggunaan akhir kosong pada muatan itu)</small>
                                    @else
                                        {{ $row['value'] }}
                                    @endif
                                </td>
                                <td>{{ $cacah($row['load_count']) }}</td>
                                <td @class(['is-muted' => $row['net_weight_total'] === null])>{{ $nilai($row['net_weight_total'], 1) }}</td>
                                <td class="is-muted">{{ $cacah($row['net_weight_load_count']) }}</td>
                                <td @class(['is-muted' => $row['net_weight_avg'] === null])>{{ $nilai($row['net_weight_avg'], 2) }}</td>
                            </tr>
                        @empty
                            <tr data-testid="by-purpose-end-use-empty">
                                <td colspan="5" class="is-muted">
                                    Tidak ada satu muatan pun pada periode {{ $summary['period']['name'] }}
                                    di line {{ $summary['production_line']['name'] }}, jadi rekap ini belum punya baris.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr data-testid="by-purpose-end-use-row-total">
                            <td>Total periode</td>
                            <td>{{ $cacah($loads['load_count']) }}</td>
                            <td>{{ $nilai($loads['net_weight_total'], 1) }}</td>
                            <td>{{ $cacah($loads['net_weight_load_count']) }}</td>
                            <td>{{ $nilai($loads['net_weight_avg'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="md-explain" data-testid="by-purpose-end-use-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg>
                <span>
                    Rekap ini diterbitkan <b>per nilai apa adanya</b> &mdash; <b>bukan</b> sebagai dua
                    ember &lsquo;dibuang&rsquo; dan &lsquo;dimanfaatkan&rsquo;.
                    <code>purpose_end_use</code> adalah kolom teks tanpa himpunan nilai tertutup dan
                    tanpa tabel master, jadi menebak pemetaan dari isi kolom ke salah satu ember berarti
                    <b>menanam aturan yang terlihat benar dan diam-diam salah</b>; pemisahan itu
                    dibiarkan terbaca dari rekapnya sendiri. Dua nilai yang berbeda hanya pada
                    besar-kecil huruf <b>tetap ditampilkan sebagai dua baris</b>: laporan tidak
                    menormalkan diam-diam, karena normalisasi yang tidak terlihat justru menyembunyikan
                    data master yang perlu dirapikan. Kolom Muatan menjumlah tepat ke
                    {{ $cacah($loads['load_count']) }} muatan, termasuk baris <b>tidak tercatat</b> yang
                    berdiri sendiri.
                    <small>
                        Urutannya dari <b>total netto terbesar</b>, lalu jumlah muatan, lalu nilainya
                        &mdash; sehingga sekelompok muatan tanpa tujuan tercatat tetapi bertonase besar
                        muncul <b>di atas</b>, bukan terkubur di baris terakhir. Nilai berfrekuensi satu
                        tetap mendapat barisnya sendiri; tidak ada kelompok &lsquo;lainnya&rsquo; di mana
                        pun pada layar ini.
                    </small>
                </span>
            </div>
        </section>

        {{-- ============ 6. Tiga rekap kategoris lainnya ============
             Markup tabelnya ditulis SEKALI lalu di-loop. --}}
        @foreach ($rekapLain as $rekap)
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
                                <th scope="col">Muatan</th>
                                <th scope="col">Total netto (MT)</th>
                                <th scope="col">Muatan netto terisi</th>
                                <th scope="col">Rata-rata/muatan (MT)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($entri as $row)
                                <tr data-testid="{{ $row['value'] === null ? $rekap['testid'].'-row-null' : $rekap['testid'].'-row' }}">
                                    <td @class(['is-muted' => $row['value'] === null])>
                                        @if ($row['value'] === null)
                                            <b>tidak tercatat</b> <small>(kolom {{ $rekap['kosong'] }} kosong pada muatan itu)</small>
                                        @else
                                            {{ $row['value'] }}
                                        @endif
                                    </td>
                                    <td>{{ $cacah($row['load_count']) }}</td>
                                    <td @class(['is-muted' => $row['net_weight_total'] === null])>{{ $nilai($row['net_weight_total'], 1) }}</td>
                                    <td class="is-muted">{{ $cacah($row['net_weight_load_count']) }}</td>
                                    <td @class(['is-muted' => $row['net_weight_avg'] === null])>{{ $nilai($row['net_weight_avg'], 2) }}</td>
                                </tr>
                            @empty
                                <tr data-testid="{{ $rekap['testid'] }}-empty">
                                    <td colspan="5" class="is-muted">
                                        Tidak ada satu muatan pun pada periode {{ $summary['period']['name'] }}
                                        di line {{ $summary['production_line']['name'] }}, jadi rekap ini belum punya baris.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr data-testid="{{ $rekap['testid'] }}-row-total">
                                <td>Total periode</td>
                                <td>{{ $cacah($loads['load_count']) }}</td>
                                <td>{{ $nilai($loads['net_weight_total'], 1) }}</td>
                                <td>{{ $cacah($loads['net_weight_load_count']) }}</td>
                                <td>{{ $nilai($loads['net_weight_avg'], 2) }}</td>
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
                        dibuang dari jumlah, sehingga kolom Muatan menjumlah tepat ke
                        {{ $cacah($loads['load_count']) }} muatan.
                        <small>
                            Diurutkan dari <b>total netto terbesar</b>, lalu jumlah muatan, lalu
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

        {{-- ============ 7. Jejak kepatuhan ============
             BLOK TERSENDIRI, sengaja TIDAK di dalam kotak angka muatan: ini
             dokumen angkut yang tidak terbit, bukan ukuran produksi. --}}
        <section class="md-card" data-testid="compliance-card">
            <header class="md-card__head">
                <h3>Jejak Kepatuhan Dokumen Angkut</h3>
                <span class="md-card__hint">penghitung <b>kehadiran</b>, bukan ukuran &mdash; tidak pernah dirata-rata, diurutkan, maupun digrafikkan</span>
            </header>
            <div class="md-kpis md-kpis--2">
                <article class="md-kpi" data-testid="kpi-missing-gate-pass">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Muatan Tanpa Nomor Gate Pass</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9V6a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v3a2.5 2.5 0 0 0 0 5v3a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-3a2.5 2.5 0 0 0 0-5z"/><path d="M9 8v8"/></svg></span>
                    </div>
                    <p class="md-kpi__value">{{ $cacah($compliance['missing_gate_pass_count']) }} <span>dari {{ $cacah($loads['load_count']) }} muatan</span></p>
                    <p class="md-kpi__meta">{{ $cacah($loads['load_count'] - $compliance['missing_gate_pass_count']) }} muatan punya nomor gate pass</p>
                    <p class="md-kpi__foot">nol pun <b>tetap dicetak</b> &mdash; nol itulah yang memberi tahu pemeriksaannya berjalan</p>
                </article>
                <article class="md-kpi" data-testid="kpi-missing-seal">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Muatan Tanpa Nomor Segel Keamanan</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="9" r="5"/><path d="M9.5 13.5 8 21l4-2 4 2-1.5-7.5"/></svg></span>
                    </div>
                    <p class="md-kpi__value">{{ $cacah($compliance['missing_seal_count']) }} <span>dari {{ $cacah($loads['load_count']) }} muatan</span></p>
                    <p class="md-kpi__meta">{{ $cacah($loads['load_count'] - $compliance['missing_seal_count']) }} muatan punya nomor segel</p>
                    <p class="md-kpi__foot">nol pun <b>tetap dicetak</b> &mdash; nol itulah yang memberi tahu pemeriksaannya berjalan</p>
                </article>
            </div>
            {{-- Prosa milik payload, dirender apa adanya. --}}
            <div class="md-explain" data-testid="compliance-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                <span>{{ $compliance['_note'] }}</span>
            </div>
        </section>

        {{-- ============ 8. PERNYATAAN: sebaran per jam tidak dapat dibuat ============
             Dirender UTUH dari payload, tidak diringkas: prosa ini ada justru
             untuk mencegah seseorang menambahkannya kemudian dengan menebak jam
             dari created_at — yang berarti menebak dari waktu input, bukan waktu
             kejadian. Kelasnya .md-explain, BUKAN .md-threshold. --}}
        <div class="md-explain" data-testid="no-hourly-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            <span>{{ $summary['_note_no_hourly'] }}</span>
        </div>

        {{-- ============ 9. PERNYATAAN: kolom shift tidak ditampilkan ============ --}}
        <div class="md-explain" data-testid="shift-not-published-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M4.9 4.9l14.2 14.2"/></svg>
            <span>{{ $summary['_note_shift_not_published'] }}</span>
        </div>

        {{-- ============ 10. Rekap harian ============
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
                    {{ $cacah(count($daily)) }} baris &middot; satu baris per <b>hari bermuatan</b>,
                    bukan per hari kalender &middot; klik untuk membuka atau menutup
                </small>
            </summary>
            <div class="md-recap">
                <table class="md-table" data-testid="daily-table">
                    <thead>
                        <tr>
                            <th scope="col">Tanggal</th>
                            <th scope="col">Muatan</th>
                            <th scope="col">Total netto (MT)</th>
                            <th scope="col">Muatan netto terisi</th>
                            <th scope="col">Rata-rata/muatan (MT)</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Tanggal tanpa satu muatan pun TIDAK mendapat baris:
                             baris nol akan terbaca sebagai 'terukur nol',
                             padahal hari tanpa pengangkutan adalah keadaan
                             normal di stasiun ini. --}}
                        @forelse ($daily as $row)
                            <tr data-testid="daily-row-{{ $row['date'] }}">
                                <td>{{ $tgl($row['date']) }}</td>
                                <td>{{ $cacah($row['load_count']) }}</td>
                                <td @class(['is-muted' => $row['net_weight_total'] === null])>{{ $nilai($row['net_weight_total'], 1) }}</td>
                                <td class="is-muted">{{ $cacah($row['net_weight_load_count']) }}</td>
                                <td @class(['is-muted' => $row['net_weight_avg'] === null])>{{ $nilai($row['net_weight_avg'], 2) }}</td>
                            </tr>
                        @empty
                            <tr data-testid="daily-empty">
                                <td colspan="5" class="is-muted">
                                    Tidak ada satu muatan bertanggal pun pada periode
                                    {{ $summary['period']['name'] }} di line
                                    {{ $summary['production_line']['name'] }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr data-testid="daily-row-total">
                            <td>Total harian</td>
                            <td>{{ $cacah($dailyTotal['load_count']) }}</td>
                            <td>{{ $nilai($dailyTotal['net_weight_total'], 1) }}</td>
                            <td>{{ $cacah($dailyTotal['net_weight_load_count']) }}</td>
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
            <div class="md-explain" data-testid="daily-recap-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                <span>{{ $dailyTotal['_note'] }}</span>
            </div>
        </details>

        {{-- ============ 11. Tren harian berat bersih ============ --}}
        <section class="md-card" data-testid="daily-trend">
            <header class="md-card__head">
                <h3>Tren Harian Berat Bersih</h3>
                <span class="md-card__hint">satu garis &middot; MT per tanggal &middot; sumbu tegak dimulai dari nol</span>
            </header>
            @if ($adaGrafikTren)
                <div class="md-lc" data-testid="daily-trend-chart">
                    <svg class="md-lc__svg" viewBox="0 0 760 300" role="img"
                         aria-label="Tren harian berat bersih limbah padat keluar dalam MT sepanjang {{ count($daily) }} tanggal bermuatan">
                        @foreach ($sumbuTren['ticks'] as $tickNilai)
                            @php $tickY = round(260 - 216 * $tickNilai / $trenHi, 1); @endphp
                            <line class="md-lc__grid" x1="72" y1="{{ $tickY }}" x2="746" y2="{{ $tickY }}"/>
                            <text class="md-lc__ytick" x="64" y="{{ $tickY + 4 }}" text-anchor="end">{{ $nilai($tickNilai, $sumbuTren['decimals']) }}</text>
                        @endforeach
                        <line class="md-lc__axis" x1="72" y1="260" x2="746" y2="260"/>
                        @foreach ($daily as $i => $row)
                            <text class="md-lc__xtick" x="{{ $lcX($i) }}" y="280" text-anchor="middle">{{ $tglAngka($row['date']) }}</text>
                        @endforeach
                        @if (count($titikTren) > 1)
                            <polyline class="md-lc__line md-lc__line--s1"
                                      points="{{ collect($titikTren)->map(fn ($p) => $p[0].','.$p[1])->implode(' ') }}"/>
                        @endif
                        @foreach ($titikTren as $p)
                            <circle class="md-lc__dot md-lc__dot--s1" cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="3"/>
                        @endforeach
                    </svg>
                </div>
                {{-- Petunjuk gulir — tampil HANYA bila kartu lebih sempit
                     daripada grafiknya (container query di report-styles). --}}
                <p class="md-scrollhint md-scrollhint--lc">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M15 8l4 4-4 4M9 8l-4 4 4 4"/></svg>
                    Geser mendatar untuk melihat seluruh tanggal.
                </p>
                <ul class="md-legend" data-testid="daily-trend-legend">
                    <li class="md-legend__item"><span class="md-legend__swatch md-legend__swatch--s1"></span>
                        Total berat bersih per tanggal &mdash; total periode {{ $nilai($loads['net_weight_total'], 1) }} MT
                        dari {{ $cacah($loads['net_weight_load_count']) }} muatan netto terisi</li>
                    <li class="md-legend__item"><small>Sumbu tegak dalam MT, <b>dimulai dari nol</b>. Hanya tanggal yang punya muatan yang digambar. Tanggal yang punya muatan tetapi tak satu pun nettonya terisi sengaja <b>dilewati</b>, bukan digambar pada nol &mdash; beratnya tidak diketahui, dan nol akan mengarang.</small></li>
                </ul>
                <div class="md-explain" data-testid="daily-trend-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
                    <span>
                        Yang digambar adalah <b>total netto per tanggal</b>, yaitu angka yang sama dengan
                        kolom Total netto pada rekap harian &mdash; bukan rata-rata, dan bukan jumlah
                        muatan. Muatan yang penimbangannya belum selesai tidak ikut menaikkan satu titik
                        pun, karena nettonya memang belum ada; itulah sebabnya kolom <b>Muatan netto
                        terisi</b> ikut dicetak pada setiap baris rekap harian.
                        <small>
                            Sumbu tegak dimulai dari nol supaya tinggi tiap titik sebanding dengan
                            beratnya; sumbu terpotong akan melebih-lebihkan naik-turunnya. Tidak ada satu
                            titik pun yang ditandai di luar batas &mdash; lihat pernyataan di bagian
                            terakhir layar ini.
                        </small>
                    </span>
                </div>
            @else
                <p class="md-filters__hint" data-testid="daily-trend-unavailable">
                    Tidak ada satu pun muatan yang nettonya terisi pada periode dan line ini, jadi
                    trennya tidak digambar &mdash; grafik kosong akan terbaca sebagai garis datar yang
                    terukur.
                </p>
            @endif
        </section>

        {{-- ============ 12a. Rekap Temuan — bagian TERPISAH ============
             findings menjawab 'apa yang DITEMUKAN'. Dua rekap ini tidak pernah
             disatukan: satu muatan yang mengisi kedua kolom muncul di KEDUA
             daftar, dan itu benar — menyatukannya justru akan menampilkan muatan
             itu dua kali di dalam satu daftar yang sama. --}}
        <section class="md-card" data-testid="findings-card">
            <header class="md-card__head">
                <h3>Rekap Temuan</h3>
                <span class="md-card__hint">{{ $cacah(count($textEntries['findings'])) }} muatan mengisi kolom <code>findings</code> sepanjang periode &middot; diterbitkan sebagai teks</span>
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
                    <code>findings</code> kosong pada seluruh muatan. Bagian ini tetap ditampilkan
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
                    prosa sebagai enum. Hanya muatan yang kolomnya <b>tidak kosong</b> yang muncul di
                    sini.
                </span>
            </div>
        </section>

        {{-- ============ 12b. Rekap Catatan — bagian TERPISAH ============ --}}
        <section class="md-card" data-testid="remarks-card">
            <header class="md-card__head">
                <h3>Rekap Catatan</h3>
                <span class="md-card__hint">{{ $cacah(count($textEntries['remarks'])) }} muatan mengisi kolom <code>remarks</code> sepanjang periode &middot; diterbitkan sebagai teks</span>
            </header>
            @if ($textEntries['remarks'] !== [])
                <div class="md-notes">
                    @foreach ($textEntries['remarks'] as $entri)
                        <div class="md-notes__item" data-testid="remarks-entry">
                            <span class="md-pill">{{ $tgl($entri['date']) }}</span>
                            <p>{{ $entri['value'] }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="md-filters__hint" data-testid="remarks-empty">
                    Tidak ada satu catatan pun yang dicatat pada periode dan line ini &mdash; kolom
                    <code>remarks</code> kosong pada seluruh muatan. Bagian ini tetap ditampilkan
                    dengan keterangan ini, bukan dihilangkan dari layar.
                </p>
            @endif
            <div class="md-explain" data-testid="remarks-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg>
                <span>
                    Bagian ini menjawab <b>apa yang dicatat petugas</b> &mdash; pertanyaan yang berbeda
                    dari Rekap Temuan, yang menjawab <b>apa yang ditemukan</b>. Karena itu keduanya
                    <b>dua bagian terpisah</b> dan tidak pernah disatukan. Satu muatan yang mengisi
                    kedua kolom sekaligus <b>muncul pada kedua rekap</b>; itu benar dan <b>bukan
                    penghitungan ganda</b> &mdash; menyatukan kedua rekap justru akan menampilkan muatan
                    itu dua kali di dalam satu daftar yang sama.
                </span>
            </div>
        </section>

        {{-- ============ 13. Kelengkapan pencatatan ============
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
                        <span>Hari dengan minimal satu muatan keluar</span>
                        <span class="md-budget__nums">
                            <strong data-testid="days-with-load">{{ $cacah($completeness['days_with_load']) }}</strong>
                            dari <span data-testid="days-counted">{{ $cacah($completeness['days_counted']) }}</span>
                            hari yang sudah lewat
                        </span>
                    </div>
                    {{-- percent NULL (periode belum mulai) → '—', BUKAN 0,0%:
                         nol persen berarti ada hari yang diharapkan dan tak satu
                         pun terisi, sedangkan '—' berarti belum ada hari yang
                         diharapkan sama sekali. Nilainya datang dari payload;
                         blade tidak menghitungnya ulang. --}}
                    <span class="md-budget__pct" data-testid="days-with-load-percent">{{ $completeness['percent'] === null ? '—' : $nilai($completeness['percent'], 1).'%' }}</span>
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
                    punya minimal satu muatan keluar. Rentang periode <b>inklusif di kedua ujung</b>,
                    jadi hari pertama dan hari terakhir ikut dihitung. Hari tanpa satu muatan pun
                    <b>bukan hal yang sama</b> dengan hari bermuatan nol MT: hari semacam itu tidak
                    mendapat baris pada rekap harian dan tidak mendapat titik pada grafik trennya.
                    <small>
                        Keanggotaan periode ditentukan oleh <b>tanggal record induk</b>, bukan tanggal
                        kejadian tiap muatan dan bukan waktu baris dibuat &mdash; keduanya dapat berbeda,
                        dan konsekuensinya rekap harian dapat memuat tanggal di luar rentang periode. Itu
                        dibiarkan apa adanya: menyembunyikannya akan membuat total tidak cocok dengan
                        rincian hariannya.
                    </small>
                </span>
            </div>
        </section>

        {{-- ============ 14. Muatan draft ============ --}}
        <section class="md-card" data-testid="draft-card">
            <header class="md-card__head">
                <h3>Muatan Berstatus Draft</h3>
                <span class="md-card__hint">ikut terhitung di seluruh angka di layar ini</span>
            </header>
            <div class="md-kpis md-kpis--3">
                <article class="md-kpi" data-testid="kpi-draft-load-count">
                    <div class="md-kpi__top">
                        <span class="md-kpi__label">Muatan Draft</span>
                        <span class="md-kpi__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3v5h5"/><path d="M19 9v10a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7z"/><path d="M9 13h6M9 17h4"/></svg></span>
                    </div>
                    <p class="md-kpi__value">{{ $cacah($draftLoadCount) }} <span>dari {{ $cacah($loads['load_count']) }} muatan</span></p>
                    <p class="md-kpi__meta">status <b>Draft</b> pada record induknya</p>
                    <p class="md-kpi__foot"><b>ikut terhitung</b> di jumlah muatan, total netto, rata-rata, keempat rekap kategoris, jejak kepatuhan, rekap harian, dan tren</p>
                </article>
            </div>
            <div class="md-explain" data-testid="draft-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg>
                <span>
                    Muatan berstatus draft <b>tetap ikut terhitung</b> dalam seluruh angka di layar ini,
                    dan jumlahnya dinyatakan di sini supaya pembaca tahu <b>sebagian angkanya belum
                    final</b>. Aturan ini mengikuti seluruh laporan stasiun yang sudah ada, yang tak satu
                    pun menyaring status record: laporan yang berhitung dengan aturan berbeda antar
                    stasiun lebih menyesatkan daripada laporan yang memasukkan data belum selesai secara
                    terbuka. Bila <b>seluruh</b> muatan periode berstatus draft, angka tetap dihitung
                    penuh dan jumlah draftnya dinyatakan. Pembaca diberi tahu, bukan dilindungi.
                </span>
            </div>
        </section>

        {{-- ============ 15. PERNYATAAN: tanpa downtime, tanpa penandaan di luar batas ============
             Dirender UTUH dari payload. Kelasnya .md-explain, BUKAN
             .md-threshold: ketiadaan penandaan diasersi MENURUT NAMA KELAS pada
             HTML ter-render, sehingga kelas bernama 'threshold' pada kotak yang
             menyatakan tidak ada ambang akan memerahkan asersi itu justru karena
             namanya. --}}
        <div class="md-explain" data-testid="absences-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            <span>{{ $summary['_note_absences'] }}</span>
        </div>
    @endif
    </div>
</div>
