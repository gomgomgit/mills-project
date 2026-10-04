<?php

/**
 * MillsSettingAuditTest — temuan audit 2026-10-04 #10, #13, #15 di Mills
 * Setting.
 */

use App\Enums\UserRole;
use App\Livewire\Settings\MillsSetting;
use App\Models\BusinessUnit;
use App\Models\MillSetting;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->bu = BusinessUnit::factory()->create(['name' => 'Mill Setting Audit']);
});

it('#13 nama aplikasi kosong ditolak (spec: tidak pernah kosong), tidak ada pesan "berhasil"', function () {
    Livewire::actingAs($this->admin)->test(MillsSetting::class)
        ->set('selectedBusinessUnitId', $this->bu->id)
        ->set('app_name', '')
        ->call('save')
        ->assertHasErrors('app_name')
        ->assertSee('Nama aplikasi wajib diisi.')
        ->assertSet('successMessage', null);

    expect(MillSetting::where('business_unit_id', $this->bu->id)->value('app_name'))->not->toBe('');
});

it('#13 pesan validasi berbahasa Indonesia (bukan "The app name field must not be greater than 255 characters.")', function () {
    Livewire::actingAs($this->admin)->test(MillsSetting::class)
        ->set('selectedBusinessUnitId', $this->bu->id)
        ->set('app_name', str_repeat('a', 256))
        ->call('save')
        ->assertSee('Nama aplikasi maksimal 255 karakter.')
        ->assertDontSee('must not be greater than');
});

it('#13 API: app_name dikirim kosong ditolak 422, tidak dikirim = tidak berubah', function () {
    $this->actingAs($this->admin)
        ->patchJson("/api/mill-settings/{$this->bu->id}", ['app_name' => ''])
        ->assertStatus(422)->assertJsonValidationErrors('app_name');

    $this->actingAs($this->admin)
        ->patchJson("/api/mill-settings/{$this->bu->id}", ['immediate_sync_enabled' => true])
        ->assertOk();
});

it('#10 daftar icon station menampilkan Production Line, dan #15 pemilih icon memakai x-searchable-select', function () {
    $lineA = ProductionLine::factory()->forBusinessUnit($this->bu)->create(['name' => 'Line A']);
    $lineB = ProductionLine::factory()->forBusinessUnit($this->bu)->create(['name' => 'Line B']);
    $a = Station::factory()->forProductionLine($lineA)->weighbridge()->create(['name' => 'Weighbridge']);
    Station::factory()->forProductionLine($lineB)->weighbridge()->create(['name' => 'Weighbridge']);

    $component = Livewire::actingAs($this->admin)->test(MillsSetting::class)
        ->set('selectedBusinessUnitId', $this->bu->id)
        ->assertSeeHtml('<th>Production Line</th>')
        ->assertSee('Line A')->assertSee('Line B')
        ->assertSeeHtml('role="combobox"')
        ->assertDontSeeHtml('wire:change="setStationIcon');

    $component->set("stationIcons.{$a->id}", 'truck')
        ->assertSet('successMessage', 'Icon station berhasil disimpan.');
    expect($a->fresh()->icon)->toBe('truck');
});

it('#15 pemilih Mill untuk Admin adalah x-searchable-select, bukan <select>', function () {
    Livewire::actingAs($this->admin)->test(MillsSetting::class)
        ->assertSeeHtml('id="selectedBusinessUnitId"')
        ->assertDontSeeHtml('<select id="selectedBusinessUnitId"');
});
