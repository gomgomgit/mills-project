<?php

/**
 * KelolaStationAuditTest — temuan audit 2026-10-04 #7, #10, #12, #15 di
 * Kelola Station.
 */

use App\Enums\UserRole;
use App\Livewire\MasterData\KelolaStation;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->bu = BusinessUnit::factory()->create(['name' => 'Mill Audit']);
    $this->lineA = ProductionLine::factory()->forBusinessUnit($this->bu)->create(['name' => 'Line A']);
    $this->lineB = ProductionLine::factory()->forBusinessUnit($this->bu)->create(['name' => 'Line B']);
});

it('#7 pilihan Type berisi seluruh tipe station (bukan 4), berlabel rapi', function () {
    Livewire::actingAs($this->admin)->test(KelolaStation::class)
        ->assertViewHas('typeOptions', function (array $options) {
            $labels = array_column($options, 'label');

            return count($options) >= 18
                && in_array('Boiler Room', $labels, true)
                && in_array('CPO Dispatch', $labels, true)
                && in_array('Other', $labels, true);
        });
});

it('#7 edit station Boiler Room menampilkan Type terpilih yang ada di pilihan', function () {
    $station = Station::factory()->forProductionLine($this->lineA)->boilerRoom()->create();

    Livewire::actingAs($this->admin)->test(KelolaStation::class)
        ->call('openEditForm', $station->id)
        ->assertSet('type', 'boiler-room')
        ->assertViewHas('typeOptions', fn (array $options) => in_array('boiler-room', array_column($options, 'value'), true));
});

it('#7 menolak tipe kembar dalam satu Production Line, tapi boleh di line lain dan boleh untuk Other', function () {
    Station::factory()->forProductionLine($this->lineA)->weighbridge()->create(['name' => 'WB A']);

    $fill = fn ($component, string $line, string $type, string $name, bool $active = true) => $component
        ->call('openCreateForm')
        ->set('business_unit_id', $this->bu->id)
        ->set('production_line_id', $line)
        ->set('type', $type)
        ->set('is_active', $active)
        ->set('form.name', $name);

    $component = Livewire::actingAs($this->admin)->test(KelolaStation::class);

    $fill($component, $this->lineA->id, 'weighbridge', 'WB A kedua')
        ->call('save')
        ->assertHasErrors('type')
        ->assertSee('sudah memiliki station bertipe Weighbridge');
    expect(Station::where('production_line_id', $this->lineA->id)->where('type', 'weighbridge')->count())->toBe(1);

    $fill($component, $this->lineB->id, 'weighbridge', 'WB B')->call('save')->assertHasNoErrors();

    Station::factory()->forProductionLine($this->lineA)->other()->create(['name' => 'Lain 1']);
    $fill($component, $this->lineA->id, 'other', 'Lain 2', false)->call('save')->assertHasNoErrors();
});

it('#7 API juga menolak tipe kembar dalam satu line', function () {
    Station::factory()->forProductionLine($this->lineA)->weighbridge()->create();

    $this->actingAs($this->admin)->postJson('/api/stations', [
        'business_unit_id' => $this->bu->id,
        'production_line_id' => $this->lineA->id,
        'name' => 'WB lagi',
        'type' => 'weighbridge',
        'is_active' => true,
    ])->assertStatus(422)->assertJsonValidationErrors('type');
});

it('#7 station duplikat lama tetap bisa diedit namanya (aturan hanya saat line/tipe berubah)', function () {
    Station::factory()->forProductionLine($this->lineA)->weighbridge()->create(['name' => 'WB 1']);
    $legacy = Station::factory()->forProductionLine($this->lineA)->weighbridge()->create(['name' => 'WB 2']);

    Livewire::actingAs($this->admin)->test(KelolaStation::class)
        ->call('openEditForm', $legacy->id)
        ->set('form.name', 'WB 2 diganti')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('successMessage', 'Station berhasil diperbarui.');

    expect($legacy->fresh()->name)->toBe('WB 2 diganti');
});

it('#10 daftar menampilkan kolom Production Line, dan bisa difilter per line', function () {
    Station::factory()->forProductionLine($this->lineA)->weighbridge()->create(['name' => 'Weighbridge']);
    Station::factory()->forProductionLine($this->lineB)->weighbridge()->create(['name' => 'Weighbridge']);

    $component = Livewire::actingAs($this->admin)->test(KelolaStation::class)
        ->assertViewHas('stations', fn ($rows) => collect($rows)->pluck('production_line_name')->sort()->values()->all() === ['Line A', 'Line B'])
        ->assertSeeHtml('<th>Production Line</th>');

    $component->set('filterProductionLineId', $this->lineB->id)
        ->assertViewHas('stations', fn ($rows) => count($rows) === 1 && $rows[0]['production_line_name'] === 'Line B');
});

it('#15 empty state saat filter tidak cocok berbeda dari "Belum ada Station"', function () {
    $emptyBu = BusinessUnit::factory()->create();
    Station::factory()->forProductionLine($this->lineA)->weighbridge()->create();

    Livewire::actingAs($this->admin)->test(KelolaStation::class)
        ->set('filterBusinessUnitId', $emptyBu->id)
        ->assertSee('Tidak ada Station yang cocok dengan filter')
        ->assertDontSee('Belum ada Station');
});

it('#15 label tipe dari master, bukan ucfirst(slug)', function () {
    Station::factory()->forProductionLine($this->lineA)->cpoDispatch()->create();

    Livewire::actingAs($this->admin)->test(KelolaStation::class)
        ->assertSee('CPO Dispatch')
        ->assertDontSee('Cpo dispatch');
});

it('#12 pesan sukses setelah tambah dan hapus', function () {
    $component = Livewire::actingAs($this->admin)->test(KelolaStation::class)
        ->call('openCreateForm')
        ->set('business_unit_id', $this->bu->id)
        ->set('production_line_id', $this->lineA->id)
        ->set('type', 'grading')
        ->set('form.name', 'Grading A')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('successMessage', 'Station berhasil ditambahkan.');

    $id = Station::where('name', 'Grading A')->value('id');
    $component->call('askDelete', $id)->call('confirmDelete')
        ->assertSet('successMessage', 'Station berhasil dihapus.')
        ->assertSee('Station berhasil dihapus.');
});
