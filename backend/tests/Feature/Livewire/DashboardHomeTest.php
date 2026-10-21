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

    // Total downtime = CDT + SDT + EDT. Line 1: 7.00 + 1.50 + 3.00 = 11.50 ; Line 2: 12.00.
    // Milling dan Available sengaja TIDAK ada di label — keduanya sudah jadi segmen
    // hijau dan abu-abu pada batang tepat di bawahnya.
    expect($block)->toContain('Total downtime 11.50 jam');
    expect($block)->toContain('Total downtime 12.00 jam');

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
it('keeps milling, idle and total downtime summing to 24', function () {
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
    preg_match_all('/title="Milling: [\d.]+ jam">([\d.]+)</', $block, $milling);
    preg_match_all('/title="Available: [\d.]+ jam">([\d.]+)</', $block, $idle);

    expect($mdh[1])->toHaveCount(2);
    expect($milling[1])->toBe(['11.0', '10.5']);
    expect($idle[1])->toBe(['1.5', '1.5']);
    foreach ($mdh[1] as $i => $v) {
        expect((float) $v + (float) $milling[1][$i] + (float) $idle[1][$i])->toBe(24.0);
    }

    // Hijau = Milling, abu-abu = Available (keputusan user 2026-10-05); segmen hijau
    // tidak lagi berlabel Available Milling Hours.
    expect($block)->not->toContain('title="Available Milling Hours:');
    expect($block)->toContain('<span>Milling</span>');
    expect($block)->toContain('<span>Available</span>');
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

    // 'Milling Hours' juga substring 'Available Milling Hours' — jangkar di <td>.
    $milling = $row('>Milling Hours<');

    expect($available)->toHaveCount(3);
    foreach ($available as $i => $total) {
        expect($mdh[$i] + $amh[$i])->toBe($total);
        expect($milling[$i])->toBeLessThanOrEqual($amh[$i]);
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

    expect($m[1])->toHaveCount(10);          // 5 segmen (Milling, Available, CDT, SDT, EDT) x 2 line
    foreach ($m[1] as $i => $width) {
        // 1 jam dari 24 = 4.1667%; apa pun di atas itu wajib membawa angka.
        if ((float) $width >= 4.1667) {
            expect(trim($m[2][$i]))->not->toBe('', "segmen {$width}% kosong");
        }
    }

    // SDT 1.50 jam pada kedua line — inilah yang dulu hilang — plus Available 1.50 jam.
    expect(array_count_values($m[2])['1.5'] ?? 0)->toBe(4);
});

/*
 * Lebar batang memakai Available milik line itu, bukan angka 24 yang ditulis
 * langsung di template. Kelima segmen harus menutup 100% persis.
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

    foreach (array_chunk($m[1], 5) as $line) {
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
 *   - meta Milling Hours  → "Line 1 11.00 · Line 2 10.50"
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
        'Line 1 11.00 jam · Line 2 10.50 jam',  // meta kartu Milling Hours
    ] as $needle) {
        expect($text)->toContain($needle);
    }
});

/*
 * Setiap blok menyebut periodenya sendiri dengan kosakata yang sama seperti
 * tabel: Today / MTD / YTD (keputusan user 2026-09-30). Sebelumnya campur aduk —
 * "Hari ini", "Posisi hari ini", "Today", dan enam kartu KPI tanpa penanda
 * apa pun, sehingga pembaca harus menebak angka mana yang hari ini.
 */
it('states the period on every card block', function () {
    $html = Livewire::actingAs($this->user)->test(DashboardHome::class)->html();
    $cards = substr($html, 0, strpos($html, 'Tabel lengkap'));
    $text = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($cards)));

    // Enam kartu KPI, masing-masing membawa penanda periodenya.
    expect(substr_count($cards, 'class="md-kpi__period">Today<'))->toBe(6);

    foreach ([
        'MTD · terhadap budget bulan berjalan',
        'Today · posisi stok',
        'Today · 24 jam tersedia',
        'Today · terhadap batas mutu',
        'Today · DCR vs teoritis',
    ] as $hint) {
        expect($text)->toContain($hint);
    }

    // Kosakata lama tidak boleh kembali.
    expect($text)->not->toContain('Hari ini');
    expect($text)->not->toContain('Posisi hari ini');
    expect($text)->not->toContain('Nilai hari ini');
});

/*
 * Tren pada keenam kartu KPI semuanya membandingkan nilai Today dengan
 * rata-rata harian MTD (keputusan user 2026-10-05). Sebelumnya campur aduk:
 * FFB Diolah & Stok CPO "vs kemarin", CPO & Kernel "poin OER/KER vs MTD".
 */
it('compares every KPI trend against the MTD daily average', function () {
    $html = Livewire::actingAs($this->user)->test(DashboardHome::class)->html();
    preg_match_all('/md-kpi__foot--trend">(.*?)<\/p>/s', $html, $m);
    $trends = array_map(fn ($t) => trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($t)))), $m[1]);

    expect($trends)->toHaveCount(6);
    foreach ($trends as $trend) {
        expect($trend)->toContain('vs rata-rata harian MTD (');
        expect($trend)->not->toContain('kemarin');
        expect($trend)->not->toContain('poin');
    }
    expect($trends)->toContain('+0.1% vs rata-rata harian MTD (599.36 MT)');
    expect($trends)->toContain('-0.4% vs rata-rata harian MTD (128.90 MT)');
    expect($trends)->toContain('+0.3% vs rata-rata harian MTD (31.12 MT)');
    expect($trends)->toContain('+0.9% vs rata-rata harian MTD (2,396.80 MT)');
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

    // Penerimaan FFB kini DITURUNKAN dari neraca stok (lihat test neraca di
    // bawah), jadi ketiga persentase ini berubah ketika stok atau FFB diolah
    // berubah — yang dikunci di sini tetap bentuknya: satu desimal, ketiganya.
    expect($m[1])->toBe(['49.1', '50.8', '49.6']);
    foreach ($m[1] as $pct) {
        expect($pct)->toMatch('/^\d+\.\d$/');
    }
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

/*
 * ────────────────────────────────────────────────────────────────────────────
 * Koherensi angka dummy (audit 2026-10-20).
 *
 * Angka di dashboard ini dummy, tetapi tidak saling bebas: pembaca laporan
 * mill memeriksa relasinya — Crushed / jam = Throughput, stok kemarin +
 * produksi − kirim = stok hari ini, penerimaan − olahan = perubahan stok. Audit
 * 2026-10-20 menemukan sembilan relasi yang sudah melenceng tanpa terlihat dari
 * kode, karena angka yang sama ditulis ulang di ringkasan kartu DAN di tabel.
 *
 * Blok test di bawah mengunci relasinya, bukan angkanya: ia MENGHITUNG dari
 * yang dirender, jadi ia tetap berlaku ketika angka dummy-nya diganti.
 * ────────────────────────────────────────────────────────────────────────────
 */

/** Baris satu panel tabel, ter-key label (dengan dan tanpa nama grup). */
function dmrRows(string $html, string $title): array
{
    // Jangkar pada markup penutup judul panel, bukan teks telanjang: judul
    // grafik <h3>Milling Hours per Line</h3> memuat 'Hours per Line'.
    $start = strpos($html, '>'.$title.'</h4>');
    expect($start)->not->toBeFalse("panel '{$title}' tidak ada di halaman");
    $table = substr($html, $start, strpos($html, '</table>', $start) - $start);

    $rows = [];
    $group = '';
    foreach (array_slice(preg_split('/<tr/', $table), 1) as $chunk) {
        // Potongannya mulai SETELAH '<tr', jadi atributnya masih ikut sebagai
        // teks biasa: tanpa dipotong di '>' pertama, nama grup terbaca
        // 'class="dmr-table__group">Line 1'.
        $gt = strpos($chunk, '>');
        $inner = substr($chunk, $gt + 1);
        if (str_contains(substr($chunk, 0, $gt), 'dmr-table__group')) {
            $group = trim(html_entity_decode(strip_tags($inner)));

            continue;
        }
        preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $inner, $m);
        if ($m[1] === []) {
            continue;
        }
        $cells = array_map(fn ($c) => trim(html_entity_decode(strip_tags($c))), $m[1]);
        $label = array_shift($cells);
        $rows[$group === '' ? $label : $group.' / '.$label] = $cells;
        $rows[$label] = $cells;
    }

    return $rows;
}

/** Satu sel angka; '-' dan sel kosong jadi null. */
function dmrNum(array $rows, string $label, int $col): ?float
{
    expect($rows)->toHaveKey($label);
    $cell = $rows[$label][$col] ?? '';

    return $cell === '-' || $cell === '' ? null : (float) str_replace(',', '', $cell);
}

/*
 * Throughput dan Avg Cage Weight adalah PEMBAGIAN dari dua kolom lain, bukan
 * angka tersendiri. Dulu ditulis tangan: throughput MTD kedua line meleset
 * 0.18 MT/jam dari Crushed / Milling Hours — selisih yang hanya terlihat kalau
 * pembacanya benar-benar membagi.
 */
it('derives throughput and average cage weight from the columns beside them', function () {
    $html = Livewire::actingAs($this->user)->test(DashboardHome::class)->html();
    $milling = dmrRows($html, 'Milling Summary');
    $hours = dmrRows($html, 'Hours per Line');

    foreach (['Line 1', 'Line 2'] as $line) {
        foreach ([0 => 'Today', 1 => 'MTD', 2 => 'YTD'] as $col => $period) {
            $crushed = dmrNum($milling, "{$line} / FFB Crushed (MT)", $col);
            $cages = dmrNum($milling, "{$line} / Cages Tipped", $col);
            $millingHours = dmrNum($hours, "{$line} / Milling Hours", $col);

            expect(dmrNum($milling, "{$line} / Avg Cage Weight (MT)", $col))
                ->toBe(round($crushed / $cages, 2), "Avg Cage Weight {$line} {$period}");
            expect(dmrNum($milling, "{$line} / Throughput (MT/hr)", $col))
                ->toBe(round($crushed / $millingHours, 2), "Throughput {$line} {$period}");
        }
    }
});

/*
 * Neraca stok produk. Kolom Today / Yesterday / Adjustment harus menutup
 * terhadap produksi, pembelian, pengiriman, dan pemakaian sebagai bahan bakar:
 *
 *     kemarin + diproduksi + dibeli − dikirim − bahan bakar + penyesuaian = hari ini
 *
 * Dua baris dulu tidak menutup: penyesuaian Kernel −2.50 MT tampil di kolomnya
 * tapi tidak ikut terhitung, dan pembelian shell 12.50 MT hilang sama sekali.
 */
it('closes the product stock balance on every row', function () {
    $html = Livewire::actingAs($this->user)->test(DashboardHome::class)->html();
    $inventory = dmrRows($html, 'Inventory Level (MT)');
    $production = dmrRows($html, 'Production');
    $arrival = dmrRows($html, 'Product Arrival');
    $dispatch = dmrRows($html, 'Product Dispatched');

    foreach (['CPO', 'Kernel', 'EB', 'Acid Oil', 'Fibre', 'Shell', 'Solid'] as $product) {
        $produced = isset($production[$product]) ? dmrNum($production, $product, 0) : 0.0;
        $purchased = isset($arrival[$product.' (MT)']) ? dmrNum($arrival, $product.' (MT)', 0) : 0.0;
        $fuel = dmrNum($dispatch, $product.' (MT)', 0) ?? 0.0;      // kolom 'As Fuel'
        $dispatched = dmrNum($dispatch, $product.' (MT)', 1) ?? 0.0; // kolom 'Today'

        $closing = dmrNum($inventory, $product, 0)
            - dmrNum($inventory, $product, 1)   // Yesterday
            - dmrNum($inventory, $product, 2);  // Adjustment

        expect(round($closing, 2))
            ->toBe(round($produced + $purchased - $dispatched - $fuel, 2), "neraca stok {$product}");
    }
});

/*
 * Neraca FFB. Penerimaan dulu ditulis tangan dan kebetulan PERSIS sama dengan
 * FFB diolah di ketiga periode, padahal stok FFB tersedia turun 26.10 MT hari
 * itu — artinya dua kartu teratas memajang angka identik yang mustahil.
 */
it('moves the FFB stock by exactly what arrived minus what was crushed', function () {
    $html = Livewire::actingAs($this->user)->test(DashboardHome::class)->html();
    $stock = dmrRows($html, 'FFB Stock (MT)');
    $arrival = dmrNum(dmrRows($html, 'Product Arrival'), 'Total FFB (MT)', 0);
    $crushed = dmrNum(dmrRows($html, 'Milling Summary'), 'Total FFB Crushed (MT)', 0);

    $today = dmrNum($stock, 'Total Available FFB', 0);
    $yesterday = dmrNum($stock, 'Total Available FFB', 1);

    expect(round($today - $yesterday, 2))->toBe(round($arrival - $crushed, 2));

    // Dan keduanya TIDAK boleh sama: stok yang bergerak menuntut selisih.
    expect($arrival)->not->toBe($crushed);
});

/*
 * Kolom 'Days' panel Reliability, Available = 24 jam x hari, dan tanggal di
 * hero harus bercerita hal yang sama. Dulu kolomnya berbunyi 15 / 15 sementara
 * Available YTD 3,096 jam setara 129 hari, pada laporan yang tanggalnya diambil
 * dari now()->subDay() — tiga versi hari yang berbeda di satu halaman.
 */
it('keeps the report date, the Days column and the available hours on one calendar', function () {
    $html = Livewire::actingAs($this->user)->test(DashboardHome::class)->html();

    $start = strpos($html, 'md-chip md-chip--date');
    expect($start)->not->toBeFalse();
    $chip = html_entity_decode(strip_tags(substr($html, $start, strpos($html, '</span>', $start) - $start)));
    expect(preg_match('/(\d{1,2}) (\p{L}+) (\d{4})/u', $chip, $d))->toBe(1, "tanggal tidak terbaca dari '{$chip}'");

    // Nama bulan dibaca lewat locale yang sama dengan yang dipakai merender,
    // bukan dengan mengulang rumus tanggalnya di sini.
    $months = [];
    foreach (range(1, 12) as $m) {
        $months[\Carbon\Carbon::create((int) $d[3], $m, 1)->translatedFormat('F')] = $m;
    }
    expect($months)->toHaveKey($d[2]);
    $reportDate = \Carbon\Carbon::create((int) $d[3], $months[$d[2]], (int) $d[1]);

    $reliability = dmrRows($html, 'Reliability per Line');
    $hours = dmrRows($html, 'Hours per Line');

    foreach (['Line 1', 'Line 2'] as $line) {
        expect(dmrNum($reliability, $line, 6))->toBe((float) $reportDate->day, "Days MTD {$line}");
        expect(dmrNum($reliability, $line, 7))->toBe((float) $reportDate->daysInMonth, "Days Total {$line}");
        expect(dmrNum($hours, "{$line} / Available", 0))->toBe(24.0);
        expect(dmrNum($hours, "{$line} / Available", 1))->toBe(24.0 * $reportDate->day);
        expect(dmrNum($hours, "{$line} / Available", 2))->toBe(24.0 * $reportDate->dayOfYear);
    }

    // Garis pro-rata budget memakai pasangan hari yang sama.
    expect($html)->toContain('Pro-rata hari ke-'.$reportDate->day);
    expect($html)->toContain('(hari ke-'.$reportDate->day.' dari '.$reportDate->daysInMonth.')');
});

/*
 * Kapasitas tangki CPO disebut di dua tempat: penyebut bar kartu 'Stok CPO' dan
 * gauge tiap tangki di blok Kualitas & Stok Tangki. Dulu kartu memakai 3,000 MT
 * sementara blok di bawahnya menggambar 4 x 1,000 MT.
 */
it('quotes the same CPO tank capacity on the KPI card as on the tank gauges', function () {
    $html = Livewire::actingAs($this->user)->test(DashboardHome::class)->html();
    $text = html_entity_decode(strip_tags($html));

    expect(preg_match('/dari kapasitas (\d+) tangki \(([\d,]+) MT\)/', $text, $card))->toBe(1);
    preg_match_all('/md-tank__stock">[\d,.]+ <small>\/ ([\d,]+) MT/', $html, $gauges);

    // Gauge CPO = sebanyak yang disebut kartu, diambil dari urutan blok tangki.
    $cpoGauges = array_slice(array_map(fn ($v) => (float) str_replace(',', '', $v), $gauges[1]), 0, (int) $card[1]);

    expect(count($cpoGauges))->toBe((int) $card[1]);
    expect(array_sum($cpoGauges))->toBe((float) str_replace(',', '', $card[2]));

    // Dan stok yang dipajang kartu = jumlah isi keempat tangki itu.
    $tanks = dmrRows($html, 'Product Quality');
    $sum = 0.0;
    foreach (range(1, (int) $card[1]) as $i) {
        $sum += dmrNum($tanks, "CPO Tank {$i} / FFA (%)", 3);
    }
    expect($sum)->toBe(dmrNum(dmrRows($html, 'Inventory Level (MT)'), 'CPO', 0));
});

/*
 * Badge deviasi tiap kartu KPI harus mengikuti nilainya sendiri dan pembanding
 * yang ia sebutkan. Dulu kartu Milling Hours menyebut pembanding 21.58 jam
 * (benarnya 21.45) dan karenanya menunjuk TURUN 0.4% padahal naik 0.2% — arah
 * panah yang salah adalah satu-satunya hal yang dibaca orang dari badge itu.
 */
it('points every KPI deviation badge the way its own figures point', function () {
    $html = Livewire::actingAs($this->user)->test(DashboardHome::class)->html();

    $start = strpos($html, '<div class="md-kpis">');
    expect($start)->not->toBeFalse();
    $block = substr($html, $start, strpos($html, '<div class="md-row', $start) - $start);

    preg_match_all('/<article class="md-kpi">(.*?)<\/article>/s', $block, $cards);
    expect($cards[1])->toHaveCount(6);

    foreach ($cards[1] as $card) {
        preg_match('/md-kpi__label">(.*?)</s', $card, $label);
        preg_match('/md-kpi__value">([\d,.]+)/s', $card, $value);
        preg_match('/md-trend md-trend--(\w+)">([+-][\d.]+)%/s', $card, $badge);
        preg_match('/vs rata-rata harian MTD \(([\d,.]+)/s', $card, $base);

        $name = trim($label[1]);
        $today = (float) str_replace(',', '', $value[1]);
        $against = (float) str_replace(',', '', $base[1]);
        $deviation = ($today / $against - 1) * 100;

        expect((float) $badge[2])->toBe(round($deviation, 1), "besar deviasi kartu {$name}");
        expect($badge[1])->toBe($deviation >= 0 ? 'up' : 'down', "arah deviasi kartu {$name}");
    }
});
