<?php

/**
 * AuthServiceChangePasswordTest — screen-003--ganti-password-web /
 * usecase-003--ganti-password-web.
 *
 * Unit tests for App\Services\AuthService::changePassword(), covering the
 * unit_test_cases derived from this screen's business_logic (steps 1-6).
 * Kept as a separate file from tests/Unit/Services/AuthServiceTest.php
 * (screen-001's login()) so screen-001/002's existing test cases are never
 * touched by this screen's work — mirrors the pattern established by
 * AuthServiceMobileLoginTest.php.
 *
 * Same pragmatic deviation from test_strategy.unit_test.mock_policy ("mock
 * all I/O") as AuthServiceTest.php / AuthServiceMobileLoginTest.php:
 * changePassword() persists via `$user->save()` (Eloquent) rather than
 * through an injectable repository abstraction (none exists in this
 * codebase). This suite instead binds Tests\TestCase + RefreshDatabase
 * (sqlite in-memory, per phpunit.xml) and seeds a minimal fixture user via
 * the model factory — fast/isolated in practice (in-memory DB, no network
 * I/O), while exercising the real Hash::check()/Hash::make() + Eloquent
 * save() calls, which is the behavior actually worth covering here.
 */

use App\Enums\UserRole;
use App\Exceptions\OldPasswordIncorrectException;
use App\Exceptions\PasswordConfirmationMismatchException;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->authService = new AuthService();
    $this->user = User::factory()
        ->role(UserRole::Supervisor)
        ->password('OldPass123!')
        ->create();
});

// unit_test_case 1: returns 422 VALIDATION_ERROR when a required field is
// missing/empty.
it('throws a 422 ValidationException when a required field is missing', function () {
    // old_password missing
    expect(fn () => $this->authService->changePassword($this->user, '', 'NewPass456!', 'NewPass456!'))
        ->toThrow(ValidationException::class);

    // new_password missing
    expect(fn () => $this->authService->changePassword($this->user, 'OldPass123!', '', ''))
        ->toThrow(ValidationException::class);

    // new_password_confirmation missing
    expect(fn () => $this->authService->changePassword($this->user, 'OldPass123!', 'NewPass456!', ''))
        ->toThrow(ValidationException::class);
});

// unit_test_case 2: returns 422 VALIDATION_ERROR when new_password format
// is invalid (< 6 chars, missing alphanumeric, or missing symbol) —
// checked before the confirmation-match / old-password checks.
it('throws a 422 ValidationException when the new_password format is invalid', function (string $badPassword) {
    expect(fn () => $this->authService->changePassword($this->user, 'OldPass123!', $badPassword, $badPassword))
        ->toThrow(ValidationException::class);
})->with([
    'too short' => ['Ab1!'],
    'no symbol' => ['abcdef1'],
    'no alphanumeric' => ['!!!!!!'],
]);

// unit_test_case 3: returns 422 PASSWORD_CONFIRMATION_MISMATCH when
// new_password_confirmation != new_password (both individually valid
// format).
it('throws PasswordConfirmationMismatchException (422) when the confirmation does not match', function () {
    expect(fn () => $this->authService->changePassword($this->user, 'OldPass123!', 'NewPass456!', 'Different789!'))
        ->toThrow(PasswordConfirmationMismatchException::class);
});

// unit_test_case 4: returns 422 OLD_PASSWORD_INCORRECT when old_password
// does not match the user's current password hash.
it('throws OldPasswordIncorrectException (422) when the old password is wrong', function () {
    expect(fn () => $this->authService->changePassword($this->user, 'WrongOldPass1!', 'NewPass456!', 'NewPass456!'))
        ->toThrow(OldPasswordIncorrectException::class);

    // No mutation should have occurred as a side effect of the failed
    // attempt.
    expect(Hash::check('OldPass123!', $this->user->fresh()->password_hash))->toBeTrue();
});

// unit_test_case 5: success — old_password matches, new_password format
// valid, confirmation matches -> user.password_hash updated with hash of
// new_password, method returns void (caller builds the 200 response).
it('updates the user password hash when all conditions pass', function () {
    $result = $this->authService->changePassword($this->user, 'OldPass123!', 'NewPass456!', 'NewPass456!');

    expect($result)->toBeNull();

    $fresh = $this->user->fresh();
    expect(Hash::check('NewPass456!', $fresh->password_hash))->toBeTrue();
    expect(Hash::check('OldPass123!', $fresh->password_hash))->toBeFalse();
});
