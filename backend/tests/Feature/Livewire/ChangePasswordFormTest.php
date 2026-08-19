<?php

/**
 * ChangePasswordFormTest (Feature/Livewire) — screen-003--ganti-password-web
 * / usecase-003--ganti-password-web.
 *
 * Component tests for App\Livewire\Settings\ChangePasswordForm, one per
 * test_scenarios' component_test step. Uses Livewire::actingAs($user)->
 * test() (mirrors tests/Feature/Livewire/LoginFormTest.php's use of
 * Livewire::test(), plus actingAs() since this component requires an
 * authenticated user — auth()->user() is used directly inside save()).
 *
 * Scenarios 3 and 4 (invalid new_password format / mismatched
 * confirmation) are caught by the component's own client-side rules()
 * ('min:6'+'regex' for new_password, 'same:new_password' for
 * new_password_confirmation) before AuthService::changePassword() is ever
 * called — so they surface as Livewire validation errors
 * (assertHasErrors()), not as errorMessage. The corresponding
 * OldPasswordIncorrectException / PasswordConfirmationMismatchException
 * catch branches inside save() (server-side defense in depth, exercised
 * directly by AuthServiceChangePasswordTest.php) are what set errorMessage
 * for scenario 2 below.
 */

use App\Enums\UserRole;
use App\Livewire\Settings\ChangePasswordForm;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()
        ->role(UserRole::Supervisor)
        ->password('OldPass123!')
        ->create();
});

// Scenario: "Ganti Password Web — success"
it('shows a success message and resets the form on a valid change', function () {
    Livewire::actingAs($this->user)
        ->test(ChangePasswordForm::class)
        ->set('old_password', 'OldPass123!')
        ->set('new_password', 'NewPass456!')
        ->set('new_password_confirmation', 'NewPass456!')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('successMessage', 'Password berhasil diubah.')
        ->assertSet('errorMessage', null)
        ->assertSet('old_password', '')
        ->assertSet('new_password', '')
        ->assertSet('new_password_confirmation', '');

    expect(Hash::check('NewPass456!', $this->user->fresh()->password_hash))->toBeTrue();
});

// Scenario: "Ganti Password Web — Password Lama Salah"
it('shows an old-password-incorrect error and leaves the password unchanged', function () {
    Livewire::actingAs($this->user)
        ->test(ChangePasswordForm::class)
        ->set('old_password', 'WrongOldPass1!')
        ->set('new_password', 'NewPass456!')
        ->set('new_password_confirmation', 'NewPass456!')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('errorMessage', 'Password lama salah.')
        ->assertSet('successMessage', null)
        ->assertSet('old_password', '');

    expect(Hash::check('OldPass123!', $this->user->fresh()->password_hash))->toBeTrue();
});

// Scenario: "Ganti Password Web — Password Baru Tidak Memenuhi Format"
it('shows a new_password format validation error and blocks submission', function () {
    Livewire::actingAs($this->user)
        ->test(ChangePasswordForm::class)
        ->set('old_password', 'OldPass123!')
        ->set('new_password', 'abc')
        ->set('new_password_confirmation', 'abc')
        ->call('save')
        ->assertHasErrors(['new_password'])
        ->assertSet('successMessage', null)
        ->assertSet('errorMessage', null);

    expect(Hash::check('OldPass123!', $this->user->fresh()->password_hash))->toBeTrue();
});

// Scenario: "Ganti Password Web — Konfirmasi Tidak Cocok"
it('shows a confirmation-mismatch validation error and blocks submission', function () {
    Livewire::actingAs($this->user)
        ->test(ChangePasswordForm::class)
        ->set('old_password', 'OldPass123!')
        ->set('new_password', 'NewPass456!')
        ->set('new_password_confirmation', 'Different789!')
        ->call('save')
        ->assertHasErrors(['new_password_confirmation'])
        ->assertSet('successMessage', null)
        ->assertSet('errorMessage', null);

    expect(Hash::check('OldPass123!', $this->user->fresh()->password_hash))->toBeTrue();
});
