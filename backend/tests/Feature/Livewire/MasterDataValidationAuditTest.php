<?php

/**
 * MasterDataValidationAuditTest — temuan audit 2026-10-04 #11 (keunikan
 * tidak peka huruf besar/kecil, format email/website) dan #12 (pesan
 * sukses) di Kelola Corporate / Company / Production Line.
 */

use App\Enums\UserRole;
use App\Livewire\MasterData\KelolaCompany;
use App\Livewire\MasterData\KelolaCorporate;
use App\Livewire\MasterData\KelolaProductionLine;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Corporate;
use App\Models\ProductionLine;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('#11 kode corporate "corp-a" bentrok dengan "CORP-A"', function () {
    Corporate::factory()->create(['corporate_code' => 'CORP-A', 'name' => 'Corp A']);

    Livewire::actingAs($this->admin)->test(KelolaCorporate::class)
        ->call('openCreateForm')
        ->set('form.corporate_code', 'corp-a')
        ->set('form.name', 'Corp Lain')
        ->call('save')
        ->assertHasErrors('form.corporate_code')
        ->assertSee('Kode corporate sudah digunakan.');

    $this->actingAs($this->admin)->postJson('/api/corporates', ['corporate_code' => 'Corp-A', 'name' => 'X'])
        ->assertStatus(422)->assertJsonValidationErrors('corporate_code');
});

it('#11 email & website corporate divalidasi formatnya', function () {
    Livewire::actingAs($this->admin)->test(KelolaCorporate::class)
        ->call('openCreateForm')
        ->set('form.corporate_code', 'CORP-FMT')
        ->set('form.name', 'Corp Format')
        ->set('form.email', 'bukan email')
        ->set('form.website', 'bukan website')
        ->call('save')
        ->assertHasErrors(['form.email', 'form.website'])
        ->assertSee('Format email tidak valid.')
        ->set('form.email', 'info@contoh.co.id')
        ->set('form.website', 'www.contoh.co.id')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('successMessage', 'Corporate berhasil ditambahkan.');
});

it('#11 kode company tidak peka huruf besar/kecil', function () {
    $corporate = Corporate::factory()->create();
    Company::factory()->create(['corporate_id' => $corporate->id, 'company_code' => 'COMP-X']);

    Livewire::actingAs($this->admin)->test(KelolaCompany::class)
        ->call('openCreateForm')
        ->set('corporate_id', $corporate->id)
        ->set('form.company_code', 'comp-x')
        ->set('form.name', 'PT Lain')
        ->call('save')
        ->assertHasErrors('form.company_code');
});

it('#11 kode production line tidak peka huruf besar/kecil; #12 pesan sukses edit', function () {
    $bu = BusinessUnit::factory()->create();
    ProductionLine::factory()->forBusinessUnit($bu)->withCode('PL-01')->create();
    $other = ProductionLine::factory()->forBusinessUnit($bu)->create();

    Livewire::actingAs($this->admin)->test(KelolaProductionLine::class)
        ->call('openEditForm', $other->id)
        ->set('form.code', 'pl-01')
        ->call('save')
        ->assertHasErrors('form.code')
        ->set('form.code', 'PL-02')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('successMessage', 'Production Line berhasil diperbarui.');
});
