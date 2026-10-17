<?php

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\ProcessWaterRecord;
use App\Models\Shift;
use App\Models\Station;
use App\Models\User;
use App\Support\Concerns\StampsShift;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Illuminate\Support\Facades\DB;

/**
 * PENANDAAN `shift_id` PADA RECORD STASIUN (2026-10-16).
 *
 * Sampai putaran ini jadwal Shift tidak punya satu pun pembaca. Berkas ini
 * membuktikan penghubungnya benar-benar bekerja — dan ia ada justru karena
 * 9166 test lain lulus TANPA membuktikan apa pun tentangnya: kolom yang
 * selalu null lolos di setiap asersi yang sudah ada, karena tak satu pun
 * menanyakannya.
 *
 * ── PEMBAGIANNYA: SATU STASIUN MENDALAM, DELAPAN BELAS MENYELURUH ───────
 *
 * Perilakunya diuji mendalam pada SATU stasiun (Process Water), karena ke-18
 * `create()` memanggil satu trait yang sama — menjalankan perilaku yang sama
 * 18 kali tidak menambah satu pun kelas kesalahan baru, hanya menambah menit.
 *
 * Yang TIDAK boleh diuji hanya pada satu stasiun adalah PENYAMBUNGANNYA:
 * sebuah service yang lupa memakai trait-nya, atau tabel yang lupa diberi
 * kolomnya, tidak akan terlihat dari stasiun mana pun selain dirinya sendiri.
 * Karena itu bagian itu diasersi atas KE-18-nya sekaligus, lewat refleksi dan
 * skema — bukan atas daftar yang ditulis tangan dan akan tertinggal.
 */
beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->processWater()->create();
    $this->lineId = $this->station->production_line_id;
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();

    openPeriodFor($this->businessUnit->id, 'process-water');
});

function shiftStampPayload(array $overrides = []): array
{
    return array_merge([
        'process_water_id' => 'PW-SHIFT-001',
        'date' => '2026-08-31',
        'details' => [['time_slot' => '07:00', 'raw_water_flow_m3h' => 45.5]],
    ], $overrides);
}

/** Ke-18 tabel record — daftar yang sama dengan migrasi 2026_10_16_000002. */
function shiftStampRecordTables(): array
{
    return [
        'boiler_room_records', 'cages_track_records', 'clarification_records',
        'cpo_dispatch_records', 'depricarping_records', 'effluent_plant_records',
        'engine_room_records', 'grading_records', 'kernel_dispatch_records',
        'kernel_plant_records', 'pressing_records', 'process_quality_control_records',
        'process_water_records', 'solid_waste_disposal_records', 'sterilizer_records',
        'storage_tank_records', 'threshing_records', 'weighbridge_records',
    ];
}

// ── PENYAMBUNGAN: KE-18, BUKAN SATU ────────────────────────────────────

it('ke-18 tabel record punya kolom shift_id', function () {
    foreach (shiftStampRecordTables() as $table) {
        expect(Schema::hasColumn($table, 'shift_id'))->toBeTrue("$table tidak punya kolom shift_id");
    }

    expect(shiftStampRecordTables())->toHaveCount(18);
});

/**
 * Sebuah service yang lupa memakai trait-nya akan menyimpan record TANPA
 * shift selamanya, diam-diam, dan hanya stasiun itu yang terpengaruh — jadi
 * seluruh test stasiun lain tetap hijau. Refleksi, bukan daftar yang ditulis
 * tangan: daftar akan tertinggal ketika stasiun ke-19 lahir, sementara glob
 * di bawah ikut tumbuh dengan sendirinya.
 */
it('ke-18 record service memakai trait StampsShift', function () {
    $services = glob(app_path('Services/*RecordService.php'));

    expect($services)->toHaveCount(18);

    foreach ($services as $path) {
        $class = 'App\\Services\\'.basename($path, '.php');

        expect(class_exists($class))->toBeTrue("$class tidak ditemukan");

        // assertContains, BUKAN expect()->toContain($needle, $message):
        // toContain() menerima BEBERAPA NEEDLE, jadi argumen kedua yang
        // dimaksudkan sebagai pesan justru menjadi needle kedua — dan
        // asersinya memerah menuntut array memuat kalimat pesannya sendiri.
        // Jebakan ini sudah tercatat di proyek ini dan tetap terjadi lagi di
        // sini; dibiarkan tertulis supaya pembaca berikutnya mengenalinya.
        $this->assertContains(
            StampsShift::class,
            class_uses_recursive($class),
            basename($path).' tidak memakai StampsShift'
        );
    }
});

/**
 * Memakai trait-nya belum berarti MEMANGGILNYA. Dibaca dari sumber, karena
 * itulah satu-satunya cara memeriksa ke-18 jalur create() tanpa menyusun
 * fixture lengkap untuk ke-18 stasiun — dan sebuah panggilan yang hilang
 * tidak akan terlihat dari mana pun selain stasiunnya sendiri.
 */
it('ke-18 record service memanggil stampShiftId() di dalam create()', function () {
    foreach (glob(app_path('Services/*RecordService.php')) as $path) {
        $source = file_get_contents($path);

        $this->assertStringContainsString(
            "\$attributes['shift_id'] = \$this->stampShiftId(\$data, \$station->production_line_id);",
            $source,
            basename($path).' tidak menyetempel shift_id'
        );
    }
});

// ── PERILAKU: SATU STASIUN, MENDALAM ───────────────────────────────────

it('jalur web: menurunkan shift dari JAM SIMPAN di server ketika klien tidak mengirim apa pun', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineId)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineId)->malam()->create();

    // Jam server dibekukan — tanpa ini test ini lulus atau gagal menurut jam
    // dinding mesin yang menjalankannya.
    $this->travelTo(Carbon::parse('2026-08-31 09:00:00'));

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/process-water-records', shiftStampPayload([
        'production_line_id' => $this->lineId,
    ]));

    $response->assertCreated();

    $record = ProcessWaterRecord::where('process_water_id', 'PW-SHIFT-001')->firstOrFail();

    expect($record->shift_id)->toBe($pagi->id);

    $this->travelBack();
});

it('jalur web: shift yang diturunkan mengikuti jam, bukan tanggal record', function () {
    Shift::factory()->forProductionLine($this->lineId)->pagi()->create();
    $malam = Shift::factory()->forProductionLine($this->lineId)->malam()->create();

    // KONSEKUENSI YANG DIPILIH SADAR (pemilik produk 2026-10-16): form web
    // punya Tanggal yang boleh DIMUNDURKAN, dan shift-nya tetap diturunkan
    // dari jam SIMPAN. Record bertanggal 31 Agustus yang disimpan pukul 02:00
    // karenanya tertandai shift MALAM — shift saat input, bukan shift saat
    // kejadian. Diasersi supaya batas ini terbaca sebagai keputusan, bukan
    // ditemukan ulang sebagai kejutan.
    $this->travelTo(Carbon::parse('2026-09-15 02:00:00'));

    $this->actingAs($this->supervisor, 'web')->postJson('/api/process-water-records', shiftStampPayload([
        'production_line_id' => $this->lineId,
        'process_water_id' => 'PW-SHIFT-MUNDUR',
        'date' => '2026-08-31',
    ]))->assertCreated();

    $record = ProcessWaterRecord::where('process_water_id', 'PW-SHIFT-MUNDUR')->firstOrFail();

    expect($record->shift_id)->toBe($malam->id);
    expect($record->date->toDateString())->toBe('2026-08-31');

    $this->travelBack();
});

it('jalur mobile: memakai shift_id kiriman perangkat apa adanya', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineId)->pagi()->create();
    $malam = Shift::factory()->forProductionLine($this->lineId)->malam()->create();

    // Jam server SENGAJA jatuh di shift Pagi, sementara perangkat mengirim
    // Malam — persis keadaan nyatanya: operator menyimpan pukul 02:00 lalu
    // menyinkronkan pukul 09:00. Yang menang harus stempel perangkat; bila
    // server yang menurunkan, nilainya akan menjadi Pagi dan test ini merah.
    $this->travelTo(Carbon::parse('2026-08-31 09:00:00'));

    $this->actingAs($this->supervisor, 'web')->postJson('/api/process-water-records', shiftStampPayload([
        'production_line_id' => $this->lineId,
        'shift_id' => $malam->id,
    ]))->assertCreated();

    $record = ProcessWaterRecord::where('process_water_id', 'PW-SHIFT-001')->firstOrFail();

    expect($record->shift_id)->toBe($malam->id);
    expect($record->shift_id)->not->toBe($pagi->id);

    $this->travelBack();
});

it('jalur mobile: menolak 422 shift milik line lain dan TIDAK menulis record apa pun', function () {
    $stationLain = Station::factory()->forBusinessUnit($this->businessUnit)->processWater()->create();
    $shiftLineLain = Shift::factory()->forProductionLine($stationLain->production_line_id)->pagi()->create();

    expect($stationLain->production_line_id)->not->toBe($this->lineId);

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/process-water-records', shiftStampPayload([
        'production_line_id' => $this->lineId,
        'shift_id' => $shiftLineLain->id,
    ]));

    $response->assertStatus(422);

    // Penolakannya terjadi SEBELUM satu baris pun ditulis — bukan record
    // tersimpan tanpa shift, dan bukan record tersimpan dengan shift asing.
    expect(ProcessWaterRecord::where('process_water_id', 'PW-SHIFT-001')->exists())->toBeFalse();
});

it('menyimpan tanpa shift ketika saatnya jatuh di celah jadwal, bukan menolak', function () {
    Shift::factory()->forProductionLine($this->lineId)->pagi()->create();

    // 20:00 — di luar Pagi 07:00–15:00, dan line ini tidak punya shift lain.
    $this->travelTo(Carbon::parse('2026-08-31 20:00:00'));

    $this->actingAs($this->supervisor, 'web')->postJson('/api/process-water-records', shiftStampPayload([
        'production_line_id' => $this->lineId,
    ]))->assertCreated();

    $record = ProcessWaterRecord::where('process_water_id', 'PW-SHIFT-001')->firstOrFail();

    // DATA LAPANGAN MENANG ATAS KELENGKAPAN MASTER. Menolak di sini akan
    // menghukum operator atas jadwal yang hanya Admin dapat perbaiki.
    expect($record->shift_id)->toBeNull();

    $this->travelBack();
});

it('menyimpan tanpa shift ketika line belum punya jadwal sama sekali', function () {
    $this->actingAs($this->supervisor, 'web')->postJson('/api/process-water-records', shiftStampPayload([
        'production_line_id' => $this->lineId,
    ]))->assertCreated();

    expect(ProcessWaterRecord::where('process_water_id', 'PW-SHIFT-001')->firstOrFail()->shift_id)->toBeNull();
    expect(Shift::count())->toBe(0);
});

/**
 * `shift_id` adalah STEMPEL SEJARAH, bukan atribut yang ikut disunting.
 * Record shift malam yang disunting keesokan paginya akan berpindah menjadi
 * record shift pagi bila ia dihitung ulang — dan perpindahan itu tidak akan
 * terlihat oleh siapa pun. Sama dengan `production_line_id`, yang juga
 * snapshot saat penulisan.
 */
it('update() TIDAK menghitung ulang shift record yang sudah tersimpan', function () {
    $malam = Shift::factory()->forProductionLine($this->lineId)->malam()->create();
    $pagi = Shift::factory()->forProductionLine($this->lineId)->pagi()->create();

    $this->travelTo(Carbon::parse('2026-08-31 02:00:00'));

    $created = $this->actingAs($this->supervisor, 'web')->postJson('/api/process-water-records', shiftStampPayload([
        'production_line_id' => $this->lineId,
    ]))->assertCreated()->json();

    $record = ProcessWaterRecord::whereKey($created['id'])->firstOrFail();
    expect($record->shift_id)->toBe($malam->id);

    // Disunting pukul 09:00 — jam yang dimiliki shift PAGI.
    $this->travelTo(Carbon::parse('2026-09-01 09:00:00'));

    $this->actingAs($this->supervisor, 'web')->patchJson('/api/process-water-records/'.$created['id'], [
        'production_line_id' => $this->lineId,
        'process_water_id' => 'PW-SHIFT-001',
        'date' => '2026-08-31',
        'note' => 'disunting',
        'details' => [['time_slot' => '07:00', 'raw_water_flow_m3h' => 50.0]],
    ])->assertOk();

    $record->refresh();

    expect($record->note)->toBe('disunting');
    expect($record->shift_id)->toBe($malam->id);
    expect($record->shift_id)->not->toBe($pagi->id);

    $this->travelBack();
});

// ═══════════════════════════════════════════════════════════════════════
// TAMPILAN — shift yang tersimpan harus TERLIHAT (2026-10-17)
//
// Sampai di sini `shift_id` tertulis dan terbukti tersimpan, tetapi tidak
// muncul di satu layar pun: ia hanya dapat dibaca dari basis data. Bagian
// ini membuktikan ia sampai ke mata manusia — dan bahwa "tanpa shift"
// dinyatakan dengan KATA, bukan dengan tanda hubung yang menyamakannya
// dengan data yang hilang.
// ═══════════════════════════════════════════════════════════════════════

it('layar Detail menampilkan nama shift beserta rentang jamnya', function () {
    $malam = Shift::factory()->forProductionLine($this->lineId)->malam()->create();

    $created = $this->actingAs($this->supervisor, 'web')->postJson('/api/process-water-records', shiftStampPayload([
        'production_line_id' => $this->lineId,
        'shift_id' => $malam->id,
    ]))->assertCreated()->json();

    Livewire::actingAs($this->supervisor)
        ->test(\App\Livewire\Data\DetailProcessWater::class, ['id' => $created['id']])
        ->assertSee('Shift Kerja')
        ->assertSee('Malam')
        // '+1' BUKAN HIASAN: tanpa itu '23:00 — 07:00' terbaca sebagai salah
        // input, dan pembacaan keliru itulah yang membuat aturannya ada.
        ->assertSee('23:00 — 07:00 +1')
        ->assertDontSee('Tanpa shift');
});

it('layar Detail menulis "Tanpa shift" dengan KATA ketika record tidak bershift', function () {
    $created = $this->actingAs($this->supervisor, 'web')->postJson('/api/process-water-records', shiftStampPayload([
        'production_line_id' => $this->lineId,
    ]))->assertCreated()->json();

    expect(ProcessWaterRecord::whereKey($created['id'])->firstOrFail()->shift_id)->toBeNull();

    Livewire::actingAs($this->supervisor)
        ->test(\App\Livewire\Data\DetailProcessWater::class, ['id' => $created['id']])
        ->assertSee('Shift Kerja')
        ->assertSee('Tanpa shift');
});

/**
 * LABELNYA "Shift Kerja", BUKAN "Shift" — dan khusus di layar ini
 * pembedaan itu menanggung beban nyata: grid Process Water Detail sudah
 * punya kolom bernama "Shift" yang artinya lain sama sekali (teks bebas
 * per slot waktu yang diketik operator). Dua hal berbeda dengan nama sama
 * di satu layar adalah cara tercepat membuat orang salah baca.
 */
it('label header berbeda dari kolom Shift per slot yang sudah ada di grid', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineId)->pagi()->create();

    $created = $this->actingAs($this->supervisor, 'web')->postJson('/api/process-water-records', shiftStampPayload([
        'production_line_id' => $this->lineId,
        'shift_id' => $pagi->id,
        'details' => [['time_slot' => '07:00', 'raw_water_flow_m3h' => 45.5, 'shift' => 'A']],
    ]))->assertCreated()->json();

    $html = Livewire::actingAs($this->supervisor)
        ->test(\App\Livewire\Data\DetailProcessWater::class, ['id' => $created['id']])
        ->html();

    // Keduanya ada di halaman yang sama, dan namanya TIDAK sama.
    expect($html)->toContain('Shift Kerja');
    expect($html)->toContain('<th>Shift</th>');

    // Nilai header disasar lewat ELEMENNYA, bukan dipindai dari seluruh
    // halaman: 'Pagi' pendek dan dapat muncul di mana saja.
    $matched = preg_match('/data-testid="detail-shift"[^>]*>(.*?)<\/span>/s', $html, $m);

    expect($matched)->toBe(1);
    $this->assertStringContainsString('Pagi', $m[1], 'field Shift Kerja tidak memuat nama shift-nya');
});

it('Data Browser menampilkan kolom Shift Kerja, dan "Tanpa shift" untuk yang tidak bershift', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineId)->pagi()->create();

    $this->actingAs($this->supervisor, 'web')->postJson('/api/process-water-records', shiftStampPayload([
        'production_line_id' => $this->lineId,
        'shift_id' => $pagi->id,
        'process_water_id' => 'PW-BERSHIFT',
    ]))->assertCreated();

    $this->actingAs($this->supervisor, 'web')->postJson('/api/process-water-records', shiftStampPayload([
        'production_line_id' => $this->lineId,
        'process_water_id' => 'PW-TANPA-SHIFT',
    ]))->assertCreated();

    $html = Livewire::actingAs($this->supervisor)
        ->test(\App\Livewire\Data\DataBrowserProcessWater::class)
        ->html();

    expect($html)->toContain('<th>Shift Kerja</th>');
    expect($html)->toContain('Pagi');
    expect($html)->toContain('Tanpa shift');
});

/**
 * N+1 ADALAH YANG PALING MUDAH TERLEWAT di sini: `$record->shift?->name`
 * pada daftar berpaginasi menembak satu kueri per baris, dan hasilnya
 * BENAR — jadi tidak ada asersi tampilan yang akan memerahkannya. Yang
 * membedakan hanyalah menghitung kueri.
 */
it('daftar memuat shift sekali untuk seluruh halaman, bukan satu kueri per baris', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineId)->pagi()->create();

    foreach (range(1, 5) as $i) {
        $this->actingAs($this->supervisor, 'web')->postJson('/api/process-water-records', shiftStampPayload([
            'production_line_id' => $this->lineId,
            'shift_id' => $pagi->id,
            'process_water_id' => 'PW-N1-'.$i,
        ]))->assertCreated();
    }

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        if (str_contains($query->sql, '"shifts"') || str_contains($query->sql, '`shifts`')) {
            $queries[] = $query->sql;
        }
    });

    Livewire::actingAs($this->supervisor)
        ->test(\App\Livewire\Data\DataBrowserProcessWater::class)
        ->html();

    // Lima baris, SATU kueri shift. Tanpa eager load angkanya akan lima.
    expect(count($queries))->toBeLessThanOrEqual(1);
});
