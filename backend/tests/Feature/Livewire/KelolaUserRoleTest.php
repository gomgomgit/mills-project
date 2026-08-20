<?php

/**
 * KelolaUserRoleTest (Feature/Livewire) — screen-032--kelola-user-role /
 * usecase-032--kelola-user-role.
 *
 * Component tests for App\Livewire\UserManagement\KelolaUserRole, one per
 * test_scenarios' component_test step. Uses Livewire::actingAs($user)->test()
 * mirroring tests/Feature/Livewire/KelolaBusinessUnitTest.php.
 *
 * The "akses ditolak" scenario exercises the real HTTP route (route-level
 * 'auth' + 'role:admin' guard) rather than Livewire::test(), same
 * reasoning as KelolaBusinessUnitTest.php's equivalent scenario.
 */

use App\Enums\UserRole;
use App\Livewire\UserManagement\KelolaUserRole;
use App\Models\BusinessUnit;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->businessUnit = BusinessUnit::factory()->create();
});

// Scenario "Kelola User & Role — Tambah User berhasil"
it('berhasil: fills the form and creates a user that appears in the list', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaUserRole::class)
        ->call('openCreateForm')
        ->assertSet('showForm', true)
        ->set('form.username', 'supervisor02')
        ->set('form.name', 'Andi Wijaya')
        ->set('form.role', 'supervisor')
        ->set('form.business_unit_id', $this->businessUnit->id)
        ->set('form.password', 'Passw0rd!')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false)
        ->assertViewHas('users', fn ($users) => collect($users)->contains(
            fn ($u) => $u['username'] === 'supervisor02' && $u['role'] === 'supervisor'
        ));
});

// Scenario "Kelola User & Role — Edit User berhasil"
it('berhasil: edits an existing user', function () {
    $user = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaUserRole::class)
        ->call('openEditForm', $user->id)
        ->assertSet('showForm', true)
        ->set('form.name', 'Nama Baru')
        ->set('form.role', 'mill_management')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false);

    expect($user->fresh()->name)->toBe('Nama Baru');
});

// Scenario "Kelola User & Role — Username Sudah Dipakai"
it('menampilkan error validasi saat username sudah dipakai', function () {
    User::factory()->create(['username' => 'existinguser']);

    Livewire::actingAs($this->admin)
        ->test(KelolaUserRole::class)
        ->call('openCreateForm')
        ->set('form.username', 'existinguser')
        ->set('form.name', 'User Lain')
        ->set('form.role', 'supervisor')
        ->set('form.business_unit_id', $this->businessUnit->id)
        ->set('form.password', 'Passw0rd!')
        ->call('save')
        ->assertHasErrors('form.username');
});

// Scenario "Kelola User & Role — Business Unit Wajib Dipilih"
it('menampilkan error validasi saat role bukan Admin tapi Business Unit kosong', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaUserRole::class)
        ->call('openCreateForm')
        ->set('form.username', 'operator05')
        ->set('form.name', 'Operator Baru')
        ->set('form.role', 'operator')
        ->set('form.password', 'Passw0rd!')
        ->call('save')
        ->assertHasErrors('form.business_unit_id');
});

// Scenario "Kelola User & Role — Admin Menonaktifkan Akun Sendiri Ditolak"
it('menolak Admin menonaktifkan akun miliknya sendiri', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaUserRole::class)
        ->call('toggleStatus', $this->admin->id, false)
        ->assertSet('statusErrorMessage', fn ($message) => ! empty($message));

    expect($this->admin->fresh()->is_active)->toBeTrue();
});

// Scenario "Kelola User & Role — Nonaktifkan/Aktifkan User Lain berhasil"
it('berhasil menonaktifkan user lain', function () {
    $user = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaUserRole::class)
        ->call('toggleStatus', $user->id, false)
        ->assertSet('statusErrorMessage', null);

    expect($user->fresh()->is_active)->toBeFalse();
});

// Scenario "Kelola User & Role — Akses ditolak untuk non-Admin"
it('menolak akses ke layar untuk non-Admin', function () {
    $supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();

    $response = $this->actingAs($supervisor, 'web')->get('/users');

    $response->assertForbidden();
});
