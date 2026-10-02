<?php

/**
 * DashboardHomeTest (Feature/Livewire) — screen-025--dashboard-web.
 *
 * The dashboard now renders only the daily mill report (dummy figures); the
 * filterable Weighbridge/Grading/Cages Track KPI block was removed 2026-09-17.
 */

use App\Enums\UserRole;
use App\Livewire\Dashboard\DashboardHome;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->role(UserRole::Supervisor)->create();
});

it('renders the daily mill report with every section', function () {
    Livewire::actingAs($this->user)
        ->test(DashboardHome::class)
        ->assertSeeHtml('data-testid="daily-mill-report"')
        ->assertSee('Dashboard Operasional Mill')
        ->assertSee('Penerimaan FFB vs Budget')
        ->assertSee('Stok FFB')
        ->assertSee('Milling per Line')
        ->assertSee('Milling Hours per Line')
        ->assertSee('Kualitas & Stok Tangki')
        ->assertSee('Energi & Air')
        ->assertSee('Oil Extraction Rate')
        ->assertSee('Tabel lengkap laporan harian');
});

it('labels the figures as dummy data', function () {
    Livewire::actingAs($this->user)
        ->test(DashboardHome::class)
        ->assertSee('Data dummy');
});

it('no longer shows the station input summary block', function () {
    Livewire::actingAs($this->user)
        ->test(DashboardHome::class)
        ->assertDontSee('Ringkasan Input Stasiun')
        ->assertDontSeeHtml('data-testid="dash-card-weighbridge"')
        ->assertDontSeeHtml('id="date_from"');
});

/*
 * Barometer kartu KPI. Sejak 2026-09-29 setiap kartu wajib membawa DUA hal:
 * bar progres terhadap acuan tetap, dan badge deviasi terhadap pembanding waktu.
 * Sebelumnya hanya 'FFB Diterima' yang punya bar dan lima sisanya hanya punya
 * badge, sehingga kartu-kartu itu tidak bisa dibandingkan satu sama lain.
 */
it('gives every KPI card both a progress bar and a deviation badge', function () {
    $html = Livewire::actingAs($this->user)->test(DashboardHome::class)->html();

    $start = strpos($html, '<div class="md-kpis">');
    expect($start)->not->toBeFalse();
    $block = substr($html, $start, strpos($html, '<div class="md-row', $start) - $start);

    $cards = substr_count($block, '<article class="md-kpi">');
    expect($cards)->toBe(6);
    expect(substr_count($block, '<div class="md-bar">'))->toBe($cards);
    expect(substr_count($block, 'class="md-trend md-trend--'))->toBe($cards);
});

it('keeps each KPI progress bar consistent with its own label', function () {
    $html = Livewire::actingAs($this->user)->test(DashboardHome::class)->html();

    $start = strpos($html, '<div class="md-kpis">');
    $block = substr($html, $start, strpos($html, '<div class="md-row', $start) - $start);

    // Lebar bar (style="width: N%") harus sama dengan persentase yang ditulis
    // pada baris label tepat di bawahnya — bar dan angka tidak boleh berbeda.
    // Dibatasi pada blok KPI: bagian lain halaman juga memakai .md-bar.
    preg_match_all(
        '/<div class="md-bar"><span style="width: ([\d.]+)%"><\/span><\/div>\s*<p class="md-kpi__foot">([\d.]+)%/',
        $block,
        $m,
    );

    expect($m[1])->toHaveCount(6);
    expect($m[1])->toBe($m[2]);
});

/*
 * Istilah down-time. Dikoreksi user 2026-09-30: CDT = Commercial Down Time
 * (bukan "Crop"), EDT = Emergency Down Time (bukan "Equipment"), SDT =
 * Scheduled Down Time. Dua dari tiga salah sejak blok ini dibuat. Dikunci di
 * sini karena salahnya tidak terlihat dari kode — hanya orang mill yang tahu.
 */
it('spells out the down-time abbreviations the way the mill does', function () {
    $page = Livewire::actingAs($this->user)->test(DashboardHome::class);

    $page->assertSee('CDT (Commercial Down Time)')
        ->assertSee('SDT (Scheduled Down Time)')
        ->assertSee('EDT (Emergency Down Time)');

    $page->assertDontSee('Crop Down Time')
        ->assertDontSee('Equipment Down Time');
});

/*
 * Available Milling Hours menggantikan EE pada blok Distribusi Jam per Line
 * 2026-09-30). Dua hal dikunci: nilainya DIHITUNG dari jam di baris yang sama
 * (dulu 'ee' ditanam sebagai konstanta terpisah dan bisa melenceng diam-diam),
 * dan EE tidak lagi muncul di blok itu.
 */
it('shows available milling hours per line, computed from that line own hours', function () {
    $html = Livewire::actingAs($this->user)->test(DashboardHome::class)->html();

    // 'Milling Hours per Line' sejak commit 8d97485 (2026-10-02); teks lamanya
    // 'Distribusi Jam per Line' sudah tidak ada di halaman. Tanpa penjagaan di
    // bawah, strpos() mengembalikan false, substr() membaca dari AWAL halaman,
    // dan dua dari empat test ini tetap hijau atas wilayah yang salah.
    $start = strpos($html, 'Milling Hours per Line');
    expect($start)->not->toBeFalse();
    $block = substr($html, $start, strpos($html, 'Kualitas', $start) - $start);

    // Total downtime = CDT + SDT + EDT. Line 1: 8.50 + 1.50 + 3.00 = 13.00 ; Line 2: 13.50.
    // Available Milling Hours sengaja TIDAK ada di label — ia sudah jadi segmen
    // hijau pada batang tepat di bawahnya.
    expect($block)->toContain('Total downtime 13.00 jam');
    expect($block)->toContain('Total downtime 13.50 jam');

    // Singkatan MDH dan AMH tidak dipakai sama sekali: keduanya tidak lazim di
    // mill, tidak seperti CDT/SDT/EDT yang memang istilah baku.
    expect($block)->not->toContain('MDH');
    expect($block)->not->toContain('AMH');
    expect($block)->not->toContain('EE ');
});

/*
 * Available Milling Hours + total downtime = 24 menurut definisinya. Dikunci karena keduanya dihitung dari
 * closure yang berbeda: kalau salah satunya diubah tanpa yang lain, jumlahnya
 * berhenti 24 dan tidak ada lagi yang menangkapnya.
 */
it('keeps available milling hours and total downtime summing to 24', function () {
    $html = Livewire::actingAs($this->user)->test(DashboardHome::class)->html();

    // 'Milling Hours per Line' sejak commit 8d97485 (2026-10-02); teks lamanya
    // 'Distribusi Jam per Line' sudah tidak ada di halaman. Tanpa penjagaan di
    // bawah, strpos() mengembalikan false, substr() membaca dari AWAL halaman,
    // dan dua dari empat test ini tetap hijau atas wilayah yang salah.
    $start = strpos($html, 'Milling Hours per Line');
    expect($start)->not->toBeFalse();
    $block = substr($html, $start, strpos($html, 'Kualitas', $start) - $start);

    // Total downtime dari label, Available Milling Hours dari segmen hijau — dua
    // tempat berbeda pada layar yang harus tetap saling menutup.
    preg_match_all('/Total downtime ([\d.]+) jam/', $block, $mdh);
    // Dicocokkan lewat title-nya, bukan posisi: Livewire menyisipkan penanda
    // <!--[if BLOCK]--> di antara .md-stack dan span pertamanya.
    preg_match_all('/title="Available Milling Hours: [\d.]+ jam">([\d.]+)</', $block, $amh);

    expect($mdh[1])->toHaveCount(2);
    expect($amh[1])->toHaveCount(2);
    foreach ($mdh[1] as $i => $v) {
        expect((float) $v + (float) $amh[1][$i])->toBe(24.0);
    }
});

/*
 * Panel Reliability. Dulu bernama 'Today Reliability' dengan kolom EE (%) dan
 * BE (%) — judulnya menyebut 'Today' padahal kolomnya MTD/YTD, dan ketiga
 * angkanya ternyata metrik yang sama pada tiga periode, bukan dua metrik.
 * Diganti Available Milling Hours/total downtime per periode (user 2026-09-30).
 */
it('reports reliability as available milling hours and total downtime, not EE and BE', function () {
    $page = Livewire::actingAs($this->user)->test(DashboardHome::class);

    $page->assertSee('Reliability per Line')
        ->assertDontSee('Today Reliability')
        ->assertDontSee('EE (%)')
        ->assertDontSee('BE (%)');
});

/*
 * Bukti sumber tunggal: total downtime + Available Milling Hours harus sama dengan Available tiap line
 * dan setiap periode di panel 'Hours per Line'. Sebelum 2026-09-30 angka-angka
 * ini ditulis tangan di tiga tempat terpisah dan bisa berselisih diam-diam.
 */
it('derives the hours table so downtime and milling hours close back to Available', function () {
    $html = Livewire::actingAs($this->user)->test(DashboardHome::class)->html();

    // Markup penutup judul panel, bukan teks telanjang: judul grafik
    // <h3>Milling Hours per Line</h3> MEMUAT substring 'Hours per Line', jadi
    // jangkar lama menangkap grafik itu alih-alih panel tabelnya. Judul panel
    // dirender <h4 class="dmr-panel__title">.
    $start = strpos($html, '>Hours per Line</h4>');
    expect($start)->not->toBeFalse();
    $table = substr($html, $start, strpos($html, '</table>', $start) - $start);

    $row = function (string $label) use ($table): array {
        $at = strpos($table, $label);
        expect($at)->not->toBeFalse();
        preg_match_all('/<td[^>]*>([\d,.]+)<\/td>/', substr($table, $at, 600), $m);

        return array_map(fn ($v) => (float) str_replace(',', '', $v), array_slice($m[1], 0, 3));
    };

    // Label muncul sekali per line; strpos mengambil kemunculan pertama (Line 1).
    $available = $row('Available');
    $mdh = $row('Total Downtime');
    $amh = $row('Available Milling Hours');

    expect($available)->toHaveCount(3);
    foreach ($available as $i => $total) {
        expect($mdh[$i] + $amh[$i])->toBe($total);
    }
});

/*
 * Setiap segmen batang jam harus mencetak angkanya. Sebelum 2026-09-30 ambangnya
 * 2 jam, sehingga SDT (1.50 jam) selalu kosong di layar padahal segmennya cukup
 * lebar untuk memuat angka itu.
 */
it('prints a figure on every hour segment wide enough to hold one', function () {
    $html = Livewire::actingAs($this->user)->test(DashboardHome::class)->html();

    // 'Milling Hours per Line' sejak commit 8d97485 (2026-10-02); teks lamanya
    // 'Distribusi Jam per Line' sudah tidak ada di halaman. Tanpa penjagaan di
    // bawah, strpos() mengembalikan false, substr() membaca dari AWAL halaman,
    // dan dua dari empat test ini tetap hijau atas wilayah yang salah.
    $start = strpos($html, 'Milling Hours per Line');
    expect($start)->not->toBeFalse();
    $block = substr($html, $start, strpos($html, 'Kualitas', $start) - $start);

    preg_match_all('/<span style="width: ([\d.]+)%[^>]*>([^<]*)<\/span>/', $block, $m);

    expect($m[1])->toHaveCount(8);           // 4 segmen x 2 line
    foreach ($m[1] as $i => $width) {
        // 1 jam dari 24 = 4.1667%; apa pun di atas itu wajib membawa angka.
        if ((float) $width >= 4.1667) {
            expect(trim($m[2][$i]))->not->toBe('', "segmen {$width}% kosong");
        }
    }

    // SDT 1.50 jam pada kedua line — inilah yang dulu hilang.
    expect(array_count_values($m[2])['1.5'] ?? 0)->toBe(2);
});

/*
 * Lebar batang memakai Available milik line itu, bukan angka 24 yang ditulis
 * langsung di template. Keempat segmen harus menutup 100% persis.
 */
it('sizes each bar against that line own available hours', function () {
    $html = Livewire::actingAs($this->user)->test(DashboardHome::class)->html();

    // 'Milling Hours per Line' sejak commit 8d97485 (2026-10-02); teks lamanya
    // 'Distribusi Jam per Line' sudah tidak ada di halaman. Tanpa penjagaan di
    // bawah, strpos() mengembalikan false, substr() membaca dari AWAL halaman,
    // dan dua dari empat test ini tetap hijau atas wilayah yang salah.
    $start = strpos($html, 'Milling Hours per Line');
    expect($start)->not->toBeFalse();
    $block = substr($html, $start, strpos($html, 'Kualitas', $start) - $start);

    preg_match_all('/<span style="width: ([\d.]+)%/', $block, $m);

    foreach (array_chunk($m[1], 4) as $line) {
        expect(round(array_sum(array_map('floatval', $line)), 4))->toBe(100.0);
    }
});

/*
 * Setiap baris pada blok Stok FFB membawa satuannya sendiri (keputusan user
 * 2026-09-30). Sebelumnya hanya angka telanjang; satuan hanya ada sekali di
 * tengah donat, sehingga tiap baris legenda tidak dapat dibaca berdiri sendiri.
 */
it('puts a unit on every FFB stock figure', function () {
    $html = Livewire::actingAs($this->user)->test(DashboardHome::class)->html();

    $start = strpos($html, 'Stok FFB');
    $block = substr($html, $start, strpos($html, '</article>', $start) - $start);

    preg_match_all('/<b>([\d,.]+) <small>([^<]+)<\/small><\/b>/', $block, $m);

    expect($m[1])->toHaveCount(5);                      // 5 pos stok
    expect(array_unique($m[2]))->toBe(['MT']);          // semuanya MT

    // Tidak boleh ada angka legenda yang tertinggal tanpa satuan.
    expect(preg_match('/<b>[\d,.]+<\/b>/', $block))->toBe(0);
});

/*
 * Audit satuan (keputusan user 2026-09-30: setiap data pada dashboard membawa
 * satuannya). Empat tempat sebelumnya menampilkan angka telanjang:
 *   - Cages Tipped   → cacahan tanpa satuan
 *   - ambang mutu    → "FFA 3.42% ≤ 5.00", ambangnya kehilangan %
 *   - selisih OER    → "-0.70", satuannya poin persentase
 *   - meta Jam Olah  → "Line 1 11.00 · Line 2 10.50"
 */
it('carries a unit on every figure in the card blocks', function () {
    $html = Livewire::actingAs($this->user)->test(DashboardHome::class)->html();

    // Diperiksa pada TEKS hasil render, bukan markup: satuan sering dibungkus
    // <small> sehingga "64 lori" tidak pernah muncul sebagai string utuh di HTML.
    $text = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html)));

    foreach ([
        '64 lori',                              // Milling per Line — cacahan lori
        '58 lori',
        '≤ 5.00%',                              // ambang FFA
        '≤ 0.20%',                              // ambang moisture
        '≤ 6.10%',                              // ambang admixture
        '-0.70 poin',                           // selisih OER: poin persentase
        'Line 1 11.00 jam · Line 2 10.50 jam',  // meta kartu Jam Olah
    ] as $needle) {
        expect($text)->toContain($needle);
    }
});

/*
 * Setiap blok menyebut periodenya sendiri dengan kosakata yang sama seperti
 * tabel: Tdy / MTD / YTD (keputusan user 2026-09-30). Sebelumnya campur aduk —
 * "Hari ini", "Posisi hari ini", "Today", dan enam kartu KPI tanpa penanda
 * apa pun, sehingga pembaca harus menebak angka mana yang hari ini.
 */
it('states the period on every card block', function () {
    $html = Livewire::actingAs($this->user)->test(DashboardHome::class)->html();
    $cards = substr($html, 0, strpos($html, 'Tabel lengkap'));
    $text = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($cards)));

    // Enam kartu KPI, masing-masing membawa penanda periodenya.
    expect(substr_count($cards, 'class="md-kpi__period">Tdy<'))->toBe(6);

    foreach ([
        'MTD · terhadap budget bulan berjalan',
        'Tdy · posisi stok',
        'Tdy · 24 jam tersedia',
        'Tdy · terhadap batas mutu',
        'Tdy · DCR vs teoritis',
    ] as $hint) {
        expect($text)->toContain($hint);
    }

    // Kosakata lama tidak boleh kembali.
    expect($text)->not->toContain('Hari ini');
    expect($text)->not->toContain('Posisi hari ini');
    expect($text)->not->toContain('Nilai hari ini');
});

/*
 * Ketiga persentase pada blok Penerimaan FFB vs Budget memakai jumlah desimal
 * yang sama. Dulu round(51.02, 1) dicetak PHP sebagai "51" — satu baris
 * kehilangan desimalnya sementara dua tetangganya tidak.
 */
it('prints the budget percentages with the same number of decimals', function () {
    $html = Livewire::actingAs($this->user)->test(DashboardHome::class)->html();

    $start = strpos($html, 'Penerimaan FFB vs Budget');
    $block = substr($html, $start, strpos($html, '</article>', $start) - $start);

    preg_match_all('/md-budget__pct">([\d.]+)%/', $block, $m);

    expect($m[1])->toBe(['49.5', '51.0', '49.9']);
});

/*
 * Pos stok lori disebut "Sterilized" / "Unsterilized" saja (keputusan user
 * 2026-09-30), di legenda Stok FFB maupun di tabel FFB Stock (MT) — dua tempat
 * yang dulu memakai istilah berbeda untuk angka yang sama.
 */
it('names the cage stock rows without the word Cages', function () {
    $page = Livewire::actingAs($this->user)->test(DashboardHome::class);

    $page->assertSee('Sterilized')
        ->assertSee('Unsterilized')
        ->assertDontSee('Sterilized Cages')
        ->assertDontSee('Unsterilized Cages');
});
