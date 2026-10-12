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
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->bu = BusinessUnit::factory()->create(['name' => 'Mill Setting Audit']);
});

// Ketiga uji "#13" DIHAPUS 2026-10-12 bersama kolom app_name: subjeknya
// (nama aplikasi kosong ditolak, pesannya berbahasa Indonesia, API menolak
// 422) tidak ada lagi karena kolomnya tidak ada lagi.
//
// Satu jaminan dari temuan #13 TIDAK ikut hilang — pesan validasi layar ini
// harus berbahasa Indonesia, bukan pesan bawaan Laravel yang berbahasa
// Inggris. Jaminan itu dipindahkan ke kolom unggahan yang masih ada.
it('#13 pesan validasi layar ini berbahasa Indonesia, bukan bawaan Laravel', function () {
    // File teks bernama .png: ditolak App\Rules\RealImage.
    $palsu = UploadedFile::fake()->createWithContent('home.png', 'ini teks biasa, bukan gambar');

    Livewire::actingAs($this->admin)->test(MillsSetting::class)
        ->set('selectedBusinessUnitId', $this->bu->id)
        ->set('home_page_image', $palsu)
        ->call('save')
        ->assertHasErrors('home_page_image')
        ->assertSet('successMessage', null)
        ->assertDontSee('must be a file of type')
        ->assertDontSee('must not be greater than');
});

it('#13 lanjutan: app_name/logo yang masih dikirim pemanggil lama diabaikan, bukan 422', function () {
    $this->actingAs($this->admin)
        ->patchJson("/api/mill-settings/{$this->bu->id}", ['app_name' => '', 'logo' => 'apa pun'])
        ->assertOk()
        ->assertJsonMissingPath('app_name')
        ->assertJsonMissingPath('logo');

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
