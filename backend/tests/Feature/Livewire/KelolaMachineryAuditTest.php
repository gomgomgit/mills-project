<?php

/**
 * KelolaMachineryAuditTest — temuan audit 2026-10-04 #9, #12, #15 di
 * Kelola Mesin.
 */

use App\Enums\UserRole;
use App\Livewire\MasterData\KelolaMachinery;
use App\Models\BusinessUnit;
use App\Models\Machinery;
use App\Models\MachineryGroup;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->bu = BusinessUnit::factory()->create(['name' => 'Mill Mesin']);
    $this->line = ProductionLine::factory()->forBusinessUnit($this->bu)->create(['name' => 'Line 1']);
    $this->station = Station::factory()->forProductionLine($this->line)->weighbridge()->create(['name' => 'Weighbridge']);
    $this->group = MachineryGroup::factory()->forStation($this->station)->withGroupCode('MG-AUD-1')->create(['description' => 'Pompa utama']);
});

it('#9a "+ Mesin" di baris grup membuka form dengan grup itu terpilih', function () {
    Livewire::actingAs($this->admin)->test(KelolaMachinery::class)
        ->assertSeeHtml("wire:click=\"openCreateForm('{$this->group->id}')\"")
        ->call('openCreateForm', $this->group->id)
        ->assertSet('machinery_group_id', $this->group->id)
        ->assertSet('selectedStationName', 'Weighbridge')
        ->assertSet('selectedProductionLineName', 'Line 1');
});

it('#9b error baris Asuransi / Pajak & Pembelian dirender di bawah kolomnya', function () {
    Livewire::actingAs($this->admin)->test(KelolaMachinery::class)
        ->call('openCreateForm', $this->group->id)
        ->set('form.equipment_code', 'EQ-AUD-1')
        ->set('form.name', 'Mesin Audit')
        ->set('insurances.0.premium', 'bukan angka')
        ->set('taxPurchases.0.contact_email', 'bukan-email')
        ->call('save')
        ->assertHasErrors('insurances.0.premium')
        ->assertSet('showForm', true)
        ->assertSee('Data belum tersimpan')
        ->assertSeeHtml('kc-form-field__input--error')
        ->tap(function ($component) {
            // Pesan error-nya benar-benar tercetak di HTML (bukan hanya ada
            // di error bag tanpa @error yang merendernya — bug aslinya).
            $message = $component->errors()->first('insurances.0.premium');
            expect($message)->not->toBe('');
            $component->assertSee($message);
        })
        // Asuransi divalidasi lebih dulu oleh service; setelah dibetulkan,
        // error Pajak & Pembelian juga muncul di kolomnya.
        ->set('insurances.0.premium', '1000')
        ->call('save')
        ->assertHasErrors('taxPurchases.0.contact_email')
        ->assertSet('showForm', true);

    expect(Machinery::where('equipment_code', 'EQ-AUD-1')->exists())->toBeFalse();
});

it('#9c pemilih Station berlabel "Mill — Line — Station", pemilih grup berlabel deskripsi + station, daftar grup punya kolom Business Unit', function () {
    Livewire::actingAs($this->admin)->test(KelolaMachinery::class)
        ->assertViewHas('stationOptions', fn ($options) => collect($options)->contains('label', 'Mill Mesin — Line 1 — Weighbridge'))
        ->assertViewHas('machineryGroupOptions', fn ($options) => collect($options)->contains('label', 'MG-AUD-1 — Pompa utama (Weighbridge · Line 1)'))
        ->assertSeeHtml('<th>Business Unit</th>')
        ->assertViewHas('groupRows', fn ($rows) => $rows[0]['business_unit_name'] === 'Mill Mesin');
});

it('#12 pesan error hapus lama hilang setelah aksi berikutnya berhasil, dan ada pesan sukses', function () {
    Machinery::factory()->forFullMachineryGroup($this->group)->create();
    $empty = MachineryGroup::factory()->forStation($this->station)->withGroupCode('MG-AUD-KOSONG')->create();

    Livewire::actingAs($this->admin)->test(KelolaMachinery::class)
        ->call('askDeleteGroup', $this->group->id)
        ->call('confirmDeleteGroup')
        ->assertSet('deleteGroupErrorMessage', fn ($m) => ! empty($m))
        ->call('askDeleteGroup', $empty->id)
        ->call('confirmDeleteGroup')
        ->assertSet('deleteGroupErrorMessage', null)
        ->assertSet('successMessage', 'Machinery Group berhasil dihapus.');
});

it('#15 wadah "Tanpa grup" tidak tampil saat pencarian tidak mencocokkan mesin tanpa grup', function () {
    Machinery::factory()->create(['machinery_group_id' => null, 'station_id' => $this->station->id, 'production_line_id' => $this->line->id, 'equipment_code' => 'EQ-LEPAS', 'name' => 'Lepas']);

    Livewire::actingAs($this->admin)->test(KelolaMachinery::class)
        ->assertSeeHtml('data-testid="ungrouped-bucket"')
        ->set('search', 'MG-AUD')
        ->assertDontSeeHtml('data-testid="ungrouped-bucket"');
});

it('#15 Station / Production Line di form Mesin dan Grup tampil sebagai teks, bukan input disabled', function () {
    $html = Livewire::actingAs($this->admin)->test(KelolaMachinery::class)
        ->call('openCreateForm', $this->group->id)
        ->html();

    expect($html)->not->toMatch('/<input[^>]*id="(station_display|production_line_display)"/')
        ->toContain('id="station_display"');

    $groupHtml = Livewire::actingAs($this->admin)->test(KelolaMachinery::class)
        ->call('openEditGroupForm', $this->group->id)
        ->html();
    expect($groupHtml)->not->toMatch('/<input[^>]*id="group_production_line_display"/');
});
