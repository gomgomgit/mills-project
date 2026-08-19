<?php

/**
 * LoginFormTest (Feature/Livewire) — screen-001--login-web / usecase-001--login-web.
 *
 * Component tests for App\Livewire\Auth\LoginForm, one per test_scenarios'
 * component_test step. Uses Livewire::test() (shipped by livewire/livewire
 * itself — no separate pest-plugin-livewire dependency required).
 *
 * Note on the "loading state shown" assertion (scenario 1): Livewire's
 * wire:loading directive is a client-side (Alpine/DOM) behavior that isn't
 * directly observable through a server-side ->call() round trip. The
 * pragmatic equivalent asserted here is that the rendered markup actually
 * wires up a loading indicator for the `login` action (wire:loading /
 * wire:target="login"), which is what makes the loading state possible.
 */

use App\Enums\UserRole;
use App\Livewire\Auth\LoginForm;
use App\Models\BusinessUnit;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
});

// Scenario: "Login Web — berhasil"
it('shows a loading affordance and redirects to the role dashboard on success', function () {
    $user = User::factory()
        ->password('Passw0rd!')
        ->role(UserRole::Supervisor)
        ->forBusinessUnit($this->businessUnit)
        ->create();

    $component = Livewire::test(LoginForm::class);

    // The login action wires up a loading indicator in the markup.
    $component->assertSeeHtml('wire:target="login"');

    $component
        ->set('username', $user->username)
        ->set('password', 'Passw0rd!')
        ->set('business_unit_id', $this->businessUnit->id)
        ->call('login')
        ->assertRedirect('/supervisor/dashboard');

    $component->assertSet('errorMessage', null);
});

// Scenario: "Login Web — Kredensial Salah"
it('shows an invalid-credentials error, clears the password, and stays editable', function () {
    $user = User::factory()
        ->password('Passw0rd!')
        ->forBusinessUnit($this->businessUnit)
        ->create();

    Livewire::test(LoginForm::class)
        ->set('username', $user->username)
        ->set('password', 'WrongPass1!')
        ->set('business_unit_id', $this->businessUnit->id)
        ->call('login')
        ->assertNoRedirect()
        ->assertSet('errorMessage', 'Username atau password salah.')
        ->assertSet('password', '');
});

// Scenario: "Login Web — Akun Dinonaktifkan"
it('shows an account-inactive error', function () {
    $user = User::factory()
        ->password('Passw0rd!')
        ->forBusinessUnit($this->businessUnit)
        ->inactive()
        ->create();

    Livewire::test(LoginForm::class)
        ->set('username', $user->username)
        ->set('password', 'Passw0rd!')
        ->set('business_unit_id', $this->businessUnit->id)
        ->call('login')
        ->assertNoRedirect()
        ->assertSet('errorMessage', 'Akun tidak aktif, hubungi Admin.')
        ->assertSet('password', '');
});

// Scenario: "Login Web — Business Area Tidak Sesuai"
it('shows a business-area-mismatch error', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();

    $user = User::factory()
        ->password('Passw0rd!')
        ->forBusinessUnit($this->businessUnit)
        ->create();

    Livewire::test(LoginForm::class)
        ->set('username', $user->username)
        ->set('password', 'Passw0rd!')
        ->set('business_unit_id', $otherBusinessUnit->id)
        ->call('login')
        ->assertNoRedirect()
        ->assertSet('errorMessage', 'Business area yang dipilih tidak sesuai dengan akses Anda.')
        ->assertSet('password', '');
});

// Scenario: "Login Web — Format Password Tidak Valid"
it('shows a password-format validation error and blocks submission', function () {
    $user = User::factory()
        ->forBusinessUnit($this->businessUnit)
        ->create();

    Livewire::test(LoginForm::class)
        ->set('username', $user->username)
        ->set('password', 'abc')
        ->set('business_unit_id', $this->businessUnit->id)
        ->call('login')
        ->assertNoRedirect()
        ->assertHasErrors(['password'])
        ->assertSet('errorMessage', null);
});
