<?php

/**
 * KelolaUserRoleAuditTest — temuan audit 2026-10-04 #3, #11, #12 di Kelola
 * User & Role. Kunci: user yang dibuat di layar ini HARUS bisa login —
 * jadi bukti #3 memakai AuthService::login() sungguhan, bukan sekadar
 * memeriksa pesan validasi.
 */

use App\Enums\UserRole;
use App\Livewire\UserManagement\KelolaUserRole;
use App\Models\BusinessUnit;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->bu = BusinessUnit::factory()->create();
});

function fillNewUser($component, string $username, string $password, BusinessUnit $bu)
{
    return $component->call('openCreateForm')
        ->set('form.username', $username)
        ->set('form.name', 'User Baru')
        ->set('form.role', 'supervisor')
        ->set('form.business_unit_id', $bu->id)
        ->set('form.password', $password);
}

it('#3 menolak password tanpa simbol saat membuat user (aturan sama dengan login)', function () {
    fillNewUser(Livewire::actingAs($this->admin)->test(KelolaUserRole::class), 'spv-lemah', 'abcdef', $this->bu)
        ->call('save')
        ->assertHasErrors('form.password')
        ->assertSee('kombinasi huruf/angka serta simbol');

    expect(User::where('username', 'spv-lemah')->exists())->toBeFalse();
});

it('#3 user yang dibuat di Kelola User benar-benar bisa login web', function () {
    fillNewUser(Livewire::actingAs($this->admin)->test(KelolaUserRole::class), 'spv-kuat', 'Rahasia1!', $this->bu)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('successMessage', 'User berhasil ditambahkan.');

    auth()->guard('web')->logout();
    $result = app(AuthService::class)->login('spv-kuat', 'Rahasia1!');
    expect($result['redirect_to'])->toBe('/dashboard');
});

it('#3 Edit User punya Reset Password opsional: kosong = tidak berubah, terisi = diganti & divalidasi', function () {
    $user = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->bu)->create([
        'password_hash' => Hash::make('Lama123!'),
    ]);

    $component = Livewire::actingAs($this->admin)->test(KelolaUserRole::class)
        ->call('openEditForm', $user->id)
        ->assertSee('Reset Password')
        ->set('form.name', 'Nama Baru')
        ->call('save')
        ->assertHasNoErrors();
    expect(Hash::check('Lama123!', $user->fresh()->password_hash))->toBeTrue();

    $component->call('openEditForm', $user->id)
        ->set('form.password', 'abcdef')
        ->call('save')
        ->assertHasErrors('form.password');
    expect(Hash::check('Lama123!', $user->fresh()->password_hash))->toBeTrue();

    $component->set('form.password', 'Baru456!')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('successMessage', 'User berhasil diperbarui dan password telah direset.');
    expect(Hash::check('Baru456!', $user->fresh()->password_hash))->toBeTrue();
});

it('#11 keunikan username tidak peka huruf besar/kecil, dan spasi ditolak', function () {
    User::factory()->role(UserRole::Operator)->forBusinessUnit($this->bu)->create(['username' => 'operator01']);

    fillNewUser(Livewire::actingAs($this->admin)->test(KelolaUserRole::class), 'OPERATOR01', 'Rahasia1!', $this->bu)
        ->call('save')
        ->assertHasErrors('form.username')
        ->assertSee('Username sudah digunakan.');

    fillNewUser(Livewire::actingAs($this->admin)->test(KelolaUserRole::class), 'user dua', 'Rahasia1!', $this->bu)
        ->call('save')
        ->assertHasErrors('form.username')
        ->assertSee('Username tidak boleh mengandung spasi.');
});

it('#11 API create juga menolak username beda huruf saja', function () {
    User::factory()->role(UserRole::Operator)->forBusinessUnit($this->bu)->create(['username' => 'operator01']);

    $this->actingAs($this->admin)->postJson('/api/users', [
        'username' => 'Operator01',
        'name' => 'X',
        'role' => 'operator',
        'business_unit_id' => $this->bu->id,
        'password' => 'Rahasia1!',
    ])->assertStatus(422)->assertJsonValidationErrors('username');
});

it('#12/#15 Nonaktifkan meminta konfirmasi, label peran rapi, Username di Edit tampil sebagai teks', function () {
    $mm = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->bu)->create();

    $html = Livewire::actingAs($this->admin)->test(KelolaUserRole::class)->html();
    expect($html)->toContain('wire:confirm="Nonaktifkan user')
        ->toContain('Mill Management')
        ->not->toContain('Mill management');

    $edit = Livewire::actingAs($this->admin)->test(KelolaUserRole::class)->call('openEditForm', $mm->id)->html();
    expect($edit)->toContain('data-testid="username-static"')
        ->not->toMatch('/<input[^>]*id="username"[^>]*disabled/');
});
