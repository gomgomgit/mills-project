<?php

/**
 * DataBrowserSterilizerTest (Feature/Livewire) —
 * screen-124--data-browser-sterilizer-web /
 * usecase-124--data-browser-sterilizer-web.
 *
 * Component tests for App\Livewire\Data\DataBrowserSterilizer. Mirrors
 * DataBrowserCpoDispatchTest.php's structure/conventions exactly.
 */

use App\Enums\UserRole;
use App\Livewire\Data\DataBrowserSterilizer;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\SterilizerDetail;
use App\Models\SterilizerRecord;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->sterilizer()->create();
    // FIXTURE DIPERBAIKI 2026-09-28. Sebelumnya tanpa forBusinessUnit():
    // default UserFactory adalah `'business_unit_id' => BusinessUnit::factory()`,
    // jadi Supervisor ini lahir di MILL LAIN — dan test tetap hijau justru
    // karena layar ini belum punya cakupan mill. Fixture-nya yang salah,
    // bukan asersinya.
    $this->user = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();

    // Mill kedua + stasiunnya: pembanding untuk keempat test cakupan mill
    // di bagian bawah berkas ini.
    $this->otherBusinessUnit = BusinessUnit::factory()->create();
    $this->otherStation = Station::factory()->forBusinessUnit($this->otherBusinessUnit)->sterilizer()->create();
});

// Scenario: "Telusuri & Ekspor Data Sterilizer — success"
it('success: shows filtered rows and export links after setting filter properties', function () {
    $recordWithDetails = SterilizerRecord::factory()->forStation($this->station)->onDate('2026-08-05')->create();
    SterilizerDetail::factory()->forRecord($recordWithDetails)->count(3)->create();

    SterilizerRecord::factory()->forStation($this->station)->onDate('2026-08-06')->create();

    // Outside the filter range — must not appear.
    SterilizerRecord::factory()->forStation($this->station)->onDate('2026-09-01')->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserSterilizer::class)
        ->set('date_from', '2026-08-01')
        ->set('date_to', '2026-08-15')
        ->set('business_unit_id', $this->businessUnit->id)
        ->assertSet('errorMessage', null)
        ->assertViewHas('records', function ($records) use ($recordWithDetails) {
            $byId = collect($records)->keyBy('id');

            return count($records) === 2
                && $byId[$recordWithDetails->id]['cycle_count'] === 3;
        })
        ->assertViewHas('meta', fn ($meta) => $meta['total'] === 2)
        ->assertViewHas('exportCsvUrl', fn ($url) => str_contains($url, 'format=csv')
            && str_contains($url, 'business_unit_id='.$this->businessUnit->id))
        ->assertViewHas('exportExcelUrl', fn ($url) => str_contains($url, 'format=excel'))
        ->assertSee('Ekspor CSV')
        ->assertSee('Ekspor Excel');
});

// Scenario: "Telusuri & Ekspor Data Sterilizer — Tidak Ada Data Sesuai Filter"
it('Tidak Ada Data Sesuai Filter: shows the empty state when no records match the filter', function () {
    SterilizerRecord::factory()->forStation($this->station)->onDate('2026-08-05')->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserSterilizer::class)
        ->set('date_from', '2020-01-01')
        ->set('date_to', '2020-01-02')
        ->assertSet('errorMessage', null)
        ->assertViewHas('records', fn ($records) => count($records) === 0)
        ->assertViewHas('meta', fn ($meta) => $meta['total'] === 0)
        ->assertSee('Tidak ada data');
});

// Scenario: "Telusuri & Ekspor Data Sterilizer — Rentang Tanggal Tidak Valid"
it('Rentang Tanggal Tidak Valid: shows a validation error and does not apply the filter', function () {
    SterilizerRecord::factory()->forStation($this->station)->onDate('2026-08-05')->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserSterilizer::class)
        ->set('date_from', '2026-08-20')
        ->set('date_to', '2026-08-10')
        ->assertSet('errorMessage', 'Rentang tanggal tidak valid: tanggal awal harus sebelum atau sama dengan tanggal akhir.')
        ->assertViewHas('records', fn ($records) => count($records) === 0)
        ->assertSee('Rentang tanggal tidak valid');
});

// Scenario: "Telusuri & Ekspor Data Sterilizer — Ekspor Gagal"
it('Ekspor Gagal: export links are always built from the current filters (no client-side size guard)', function () {
    Livewire::actingAs($this->user)
        ->test(DataBrowserSterilizer::class)
        ->set('date_from', '2026-01-01')
        ->set('date_to', '2026-12-31')
        ->assertViewHas('exportCsvUrl', fn ($url) => str_contains($url, '/api/sterilizer-records/export')
            && str_contains($url, 'date_from=2026-01-01')
            && str_contains($url, 'date_to=2026-12-31'));
});

// Scenario: "Telusuri & Ekspor Data Sterilizer — Klik Baris Membuka Detail"
it('Klik Baris Membuka Detail: rows render with a clickable-row link to the real detail route', function () {
    $record = SterilizerRecord::factory()->forStation($this->station)->onDate('2026-08-05')->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserSterilizer::class)
        ->assertSeeHtml("onclick=\"window.location.href='".route('data.sterilizer.detail', ['id' => $record->id])."'\"");
});

// ─── CAKUPAN MILL (2026-09-28) ──────────────────────────────────────────────
// Sampai hari ini layar ini TIDAK punya cakupan mill sama sekali:
// $business_unit_id default '' dan buildFilteredQuery() memperlakukan nilai
// kosong sebagai "tanpa filter", sehingga Supervisor mill mana pun bisa
// melihat — dan mengekspor — record seluruh mill.
//
// Asersinya memeriksa ISI (id record mana yang muncul), bukan jumlah baris:
// kebocoran yang mengembalikan data mill lain juga menghasilkan "ada baris",
// jadi menghitung baris saja tidak membuktikan apa pun.

it('cakupan mill: Supervisor Mill A tidak melihat record Mill B', function () {
    $mine = SterilizerRecord::factory()->forStation($this->station)->create();
    $theirs = SterilizerRecord::factory()->forStation($this->otherStation)->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserSterilizer::class)
        ->assertViewHas('records', function ($records) use ($mine, $theirs) {
            $ids = collect($records)->pluck('id')->all();

            return in_array($mine->id, $ids, true) && ! in_array($theirs->id, $ids, true);
        });
});

it('cakupan mill: memaksa business_unit_id Mill B lewat properti Livewire tidak mengubah apa pun', function () {
    $mine = SterilizerRecord::factory()->forStation($this->station)->create();
    $theirs = SterilizerRecord::factory()->forStation($this->otherStation)->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserSterilizer::class)
        ->set('business_unit_id', $this->otherBusinessUnit->id)
        // Properti dipaku kembali ke mill aktor — <select> dan tautan ekspor
        // menampilkan kenyataan, bukan pilihan yang sudah dibuang diam-diam.
        ->assertSet('business_unit_id', $this->businessUnit->id)
        ->assertViewHas('records', function ($records) use ($mine, $theirs) {
            $ids = collect($records)->pluck('id')->all();

            return in_array($mine->id, $ids, true) && ! in_array($theirs->id, $ids, true);
        })
        // Dropdown mill TERSARING, bukan label statis: hanya mill aktor yang
        // ada di dalamnya, jadi mill lain tidak bisa dipilih sejak awal.
        ->assertViewHas('businessUnits', fn ($units) => $units->pluck('id')->all() === [$this->businessUnit->id])
        // Tautan ekspor ikut memakai mill aktor, bukan mill yang disuntikkan.
        ->assertViewHas('exportCsvUrl', fn ($url) => str_contains($url, 'business_unit_id='.$this->businessUnit->id)
            && ! str_contains($url, 'business_unit_id='.$this->otherBusinessUnit->id));
});

it('cakupan mill: Admin tetap melihat semua mill', function () {
    $a = SterilizerRecord::factory()->forStation($this->station)->create();
    $b = SterilizerRecord::factory()->forStation($this->otherStation)->create();

    // `business_unit_id` kolom Admin sengaja diabaikan — Admin dinilai dari
    // PERAN, konsisten dengan ScopesToActorMill::actorReadMillId().
    $admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();

    Livewire::actingAs($admin)
        ->test(DataBrowserSterilizer::class)
        ->assertViewHas('records', function ($records) use ($a, $b) {
            $ids = collect($records)->pluck('id')->all();

            return in_array($a->id, $ids, true) && in_array($b->id, $ids, true);
        })
        ->assertViewHas('businessUnits', fn ($units) => $units->count() >= 2);
});

it('cakupan mill: aktor terikat mill tanpa business_unit_id gagal-tertutup dengan pesan', function () {
    SterilizerRecord::factory()->forStation($this->station)->create();
    SterilizerRecord::factory()->forStation($this->otherStation)->create();

    $millless = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    Livewire::actingAs($millless)
        ->test(DataBrowserSterilizer::class)
        // Pesan yang bisa ditindaklanjuti, bukan daftar kosong tanpa sebab —
        // dan bukan pula pelebaran diam-diam ke semua mill.
        ->assertSet('errorMessage', 'Akun Anda belum terhubung ke mill. Hubungi Admin.')
        ->assertViewHas('records', fn ($records) => $records === []);
});

// ─── PRODUCTION LINE (2026-09-28) ───────────────────────────────────────────
// Production line adalah KONTEKS YANG DIPILIH, bukan ikatan akun: tidak ada
// `users.production_line_id` dan tidak boleh ada. Karena itu filternya
// default ke "Semua Line" — daftar ini daftar BARIS, bukan angka gabungan
// seperti laporan periode, jadi melihat semuanya memang berguna ASAL setiap
// baris menunjukkan line-nya. Itulah tugas kolom Production Line.
//
// Setiap asersi cakupan di bawah memeriksa ISI DUA ARAH: record yang
// seharusnya ADA memang ada, dan yang seharusnya TIDAK ADA memang tidak.
// Sisi "ADA" bukan hiasan — SQLite (driver test) memperlakukan WHERE pada
// kolom yang tidak ada sebagai string literal dan mengembalikan 0 baris TANPA
// error, sementara PostgreSQL (dev/produksi) melempar. Filter yang menyaring
// habis karena salah kolom akan tetap hijau kalau kita hanya mengasersi
// ketiadaan.

it('production line: "Semua Line" menampilkan record dari beberapa line di mill aktor', function () {
    $lineA = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'LINE-ALPHA']);
    $lineB = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'LINE-BETA']);
    $recordA = SterilizerRecord::factory()->forStation(Station::factory()->forProductionLine($lineA)->sterilizer()->create())->create();
    $recordB = SterilizerRecord::factory()->forStation(Station::factory()->forProductionLine($lineB)->sterilizer()->create())->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserSterilizer::class)
        ->assertSet('production_line_id', '')
        ->assertViewHas('records', function ($records) use ($recordA, $recordB) {
            $byId = collect($records)->keyBy('id');

            return $byId->has($recordA->id)
                && $byId->has($recordB->id)
                && $byId[$recordA->id]['production_line_name'] === 'LINE-ALPHA'
                && $byId[$recordB->id]['production_line_name'] === 'LINE-BETA';
        })
        // Kolomnya benar-benar terender per baris, bukan cuma ada di payload.
        ->assertSeeHtml('<td>LINE-ALPHA</td>')
        ->assertSeeHtml('<td>LINE-BETA</td>')
        // Pilihan filter hanya line DI DALAM mill yang sedang berlaku bagi
        // aktor: keduanya ada, dan tidak ada satu pun opsi dari mill lain.
        ->assertViewHas('productionLines', function ($lines) use ($lineA, $lineB) {
            $ids = collect($lines)->pluck('id');
            $inMill = ProductionLine::where('business_unit_id', $lineA->business_unit_id)->pluck('id');

            return $ids->contains($lineA->id)
                && $ids->contains($lineB->id)
                && $ids->diff($inMill)->isEmpty();
        });
});

it('production line: memilih satu line menyempitkan daftar ke line itu', function () {
    $lineA = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'LINE-ALPHA']);
    $lineB = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'LINE-BETA']);
    $recordA = SterilizerRecord::factory()->forStation(Station::factory()->forProductionLine($lineA)->sterilizer()->create())->create();
    $recordB = SterilizerRecord::factory()->forStation(Station::factory()->forProductionLine($lineB)->sterilizer()->create())->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserSterilizer::class)
        ->set('production_line_id', $lineA->id)
        ->assertSet('production_line_id', $lineA->id)
        ->assertViewHas('records', function ($records) use ($recordA, $recordB) {
            $ids = collect($records)->pluck('id')->all();

            return in_array($recordA->id, $ids, true) && ! in_array($recordB->id, $ids, true);
        })
        // Filter ikut ke tautan ekspor, bukan hanya ke tampilan.
        ->assertViewHas('exportCsvUrl', fn ($url) => str_contains($url, 'production_line_id='.$lineA->id))
        ->assertViewHas('exportExcelUrl', fn ($url) => str_contains($url, 'production_line_id='.$lineA->id));
});

it('production line: kolom dibaca dari kolom record — stasiun yang dipindah tidak menulis ulang sejarah', function () {
    // Konsumen pertama jaminan 2026_09_28_000041. Sebelum kolom itu ada, line
    // sebuah record hanya bisa DITURUNKAN lewat station->production_line_id,
    // sehingga memindah satu stasiun ke line lain diam-diam menulis ulang
    // seluruh sejarah stasiun itu. Di sini bedanya bukan kosmetik: kedua nilai
    // berbeda, dan yang benar adalah line tempat data itu benar-benar
    // dihasilkan.
    $lineA = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'LINE-ALPHA']);
    $lineB = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'LINE-BETA']);
    $station = Station::factory()->forProductionLine($lineA)->sterilizer()->create();
    $record = SterilizerRecord::factory()->forStation($station)->create();

    expect($record->fresh()->production_line_id)->toBe($lineA->id);

    // Stasiun dipindah ke line lain SETELAH record ditulis.
    $station->update(['production_line_id' => $lineB->id]);
    expect($station->fresh()->production_line_id)->toBe($lineB->id);
    expect($record->fresh()->production_line_id)->toBe($lineA->id);

    Livewire::actingAs($this->user)
        ->test(DataBrowserSterilizer::class)
        ->assertViewHas('records', fn ($records) => collect($records)->firstWhere('id', $record->id)['production_line_name'] === 'LINE-ALPHA')
        ->assertSeeHtml('<td>LINE-ALPHA</td>')
        ->assertDontSeeHtml('<td>LINE-BETA</td>');

    // Penyaringannya pun ikut kolom record, bukan konfigurasi stasiun hari ini.
    Livewire::actingAs($this->user)
        ->test(DataBrowserSterilizer::class)
        ->set('production_line_id', $lineA->id)
        ->assertViewHas('records', fn ($records) => collect($records)->pluck('id')->contains($record->id));

    Livewire::actingAs($this->user)
        ->test(DataBrowserSterilizer::class)
        ->set('production_line_id', $lineB->id)
        ->assertViewHas('records', fn ($records) => ! collect($records)->pluck('id')->contains($record->id));
});

it('production line: line milik mill lain lewat properti Livewire diabaikan', function () {
    $lineA = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'LINE-ALPHA']);
    $recordA = SterilizerRecord::factory()->forStation(Station::factory()->forProductionLine($lineA)->sterilizer()->create())->create();

    $foreignLine = ProductionLine::factory()->forBusinessUnit($this->otherBusinessUnit)->create(['name' => 'LINE-ASING']);
    $foreignRecord = SterilizerRecord::factory()->forStation(Station::factory()->forProductionLine($foreignLine)->sterilizer()->create())->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserSterilizer::class)
        ->set('production_line_id', $foreignLine->id)
        // Jatuh DIAM-DIAM ke "semua line di dalam mill aktor" — bukan error,
        // persis perlakuan yang sudah berlaku untuk `business_unit_id`.
        ->assertSet('production_line_id', '')
        ->assertSet('errorMessage', null)
        ->assertViewHas('records', function ($records) use ($recordA, $foreignRecord) {
            $ids = collect($records)->pluck('id')->all();

            return in_array($recordA->id, $ids, true) && ! in_array($foreignRecord->id, $ids, true);
        })
        ->assertViewHas('productionLines', fn ($lines) => ! collect($lines)->pluck('id')->contains($foreignLine->id))
        ->assertDontSee('LINE-ASING');
});

it('production line: Admin mengganti mill => pilihan line ikut direset', function () {
    $lineA = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'LINE-ALPHA']);
    $admin = User::factory()->role(UserRole::Admin)->create();

    Livewire::actingAs($admin)
        ->test(DataBrowserSterilizer::class)
        ->set('business_unit_id', $this->businessUnit->id)
        ->set('production_line_id', $lineA->id)
        ->assertSet('production_line_id', $lineA->id)
        // Line mill lama tidak valid di mill baru: dibiarkan, ia hanya akan
        // menghasilkan daftar kosong tanpa sebab yang terlihat.
        ->set('business_unit_id', $this->otherBusinessUnit->id)
        ->assertSet('production_line_id', '');
});
