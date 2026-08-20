<?php

/**
 * AuthServiceMobileLoginTest — screen-002--login-mobile / usecase-002--login-mobile.
 *
 * Unit tests for App\Services\AuthService::login() when called with a
 * non-null $deviceName (the mobile/Sanctum-token branch), covering the 6
 * unit_test_cases derived from this screen's business_logic. Kept as a
 * separate file from tests/Unit/Services/AuthServiceTest.php (screen-001's
 * web/session branch) rather than appended into it, so screen-001's
 * existing test cases are never touched by this screen's work.
 *
 * Same pragmatic deviation from test_strategy.unit_test.mock_policy
 * ("mock all I/O") as AuthServiceTest.php: AuthService looks up the user via
 * `User::where(...)->first()` (Eloquent facade/static call), which is
 * impractical to mock in Pest without an injectable repository abstraction
 * (none exists in this codebase). This suite instead binds Tests\TestCase +
 * RefreshDatabase (sqlite in-memory, per phpunit.xml) and seeds minimal
 * fixtures via model factories — fast/isolated in practice (in-memory DB,
 * no network I/O), while exercising the real Eloquent query and the real
 * Sanctum token issuance (personal_access_tokens table), which is the
 * behavior actually worth covering here.
 */

use App\Enums\UserRole;
use App\Exceptions\AccountInactiveException;
use App\Exceptions\BusinessAreaMismatchException;
use App\Exceptions\InvalidCredentialsException;
use App\Models\BusinessUnit;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->authService = new AuthService();
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->deviceName = 'Samsung A54 - Operator';
});

// Mobile login (device_name) branch — unit_test_case 1:
// returns 422 VALIDATION_ERROR when request body is invalid (missing/empty
// required field(s)), no user lookup should occur.
it('throws a 422 ValidationException when a required field is missing (mobile branch)', function () {
    // username missing
    expect(fn () => $this->authService->login('', 'Passw0rd!', $this->businessUnit->id, $this->deviceName))
        ->toThrow(ValidationException::class);

    // password missing
    expect(fn () => $this->authService->login('someuser', '', $this->businessUnit->id, $this->deviceName))
        ->toThrow(ValidationException::class);

    // business_unit_id is intentionally NOT required for mobile — an
    // operator/supervisor already has one assigned, auto-derived instead
    // (see AuthServiceTest.php's auto-derive tests, same logic path).

    // device_name explicitly empty (still selects the mobile branch, but
    // fails its own required-field rule).
    expect(fn () => $this->authService->login('someuser', 'Passw0rd!', $this->businessUnit->id, ''))
        ->toThrow(ValidationException::class);

    // No user should have been created/looked up as a side effect of any
    // of the above — nothing to assert via a spy (no repository
    // abstraction), so this is documented rather than asserted directly;
    // the InvalidCredentialsException path (unit_test_case 3) is what
    // proves the lookup+compare step actually runs when validation passes.
});

// Mobile login (device_name) branch — unit_test_case 2:
// returns 422 VALIDATION_ERROR when password format invalid (< 6 chars or
// wrong format), before credential lookup.
it('throws a 422 ValidationException when the password format is invalid (mobile branch)', function (string $badPassword) {
    expect(fn () => $this->authService->login('someuser', $badPassword, $this->businessUnit->id, $this->deviceName))
        ->toThrow(ValidationException::class);
})->with([
    'too short' => ['Ab1!'],
    'no symbol' => ['abcdef1'],
    'no alphanumeric' => ['!!!!!!'],
]);

// Mobile login (device_name) branch — unit_test_case 3:
// returns 401 INVALID_CREDENTIALS when user not found OR password mismatch.
it('throws InvalidCredentialsException (401) when the username is not found (mobile branch)', function () {
    expect(fn () => $this->authService->login('does-not-exist', 'Passw0rd!', $this->businessUnit->id, $this->deviceName))
        ->toThrow(InvalidCredentialsException::class);
});

it('throws InvalidCredentialsException (401) when the password does not match (mobile branch)', function () {
    $user = User::factory()
        ->password('CorrectPass1!')
        ->forBusinessUnit($this->businessUnit)
        ->create();

    expect(fn () => $this->authService->login($user->username, 'WrongPass1!', $this->businessUnit->id, $this->deviceName))
        ->toThrow(InvalidCredentialsException::class);
});

// Mobile login (device_name) branch — unit_test_case 4:
// returns 403 ACCOUNT_INACTIVE when user.is_active=false (password matches).
it('throws AccountInactiveException (403) when the account is inactive (mobile branch)', function () {
    $user = User::factory()
        ->password('Passw0rd!')
        ->forBusinessUnit($this->businessUnit)
        ->inactive()
        ->create();

    expect(fn () => $this->authService->login($user->username, 'Passw0rd!', $this->businessUnit->id, $this->deviceName))
        ->toThrow(AccountInactiveException::class);
});

// Mobile login (device_name) branch — unit_test_case 5:
// returns 403 BUSINESS_AREA_MISMATCH when business_unit_id doesn't match
// user assignment.
it('throws BusinessAreaMismatchException (403) when business_unit_id does not match the user (mobile branch)', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();

    $user = User::factory()
        ->password('Passw0rd!')
        ->forBusinessUnit($this->businessUnit)
        ->create();

    expect(fn () => $this->authService->login($user->username, 'Passw0rd!', $otherBusinessUnit->id, $this->deviceName))
        ->toThrow(BusinessAreaMismatchException::class);
});

// Mobile login (device_name) branch — unit_test_case 6:
// success — creates Sanctum token named device_name, returns
// {user, business_unit, token}, and does NOT establish a web session
// (distinguishes this branch from screen-001's).
it('returns success payload with a Sanctum token and does not establish a session', function () {
    $user = User::factory()
        ->password('Passw0rd!')
        ->role(UserRole::Operator)
        ->forBusinessUnit($this->businessUnit)
        ->create();

    $result = $this->authService->login($user->username, 'Passw0rd!', $this->businessUnit->id, $this->deviceName);

    expect($result)->toMatchArray([
        'user' => [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'role' => 'operator',
            'business_unit_id' => $this->businessUnit->id,
        ],
        'business_unit' => [
            'id' => $this->businessUnit->id,
            'name' => $this->businessUnit->name,
        ],
    ]);

    expect($result['token'])->toBeString()->not->toBeEmpty();
    expect($result)->not->toHaveKey('redirect_to');

    // The token was actually persisted (Sanctum) and named after
    // $deviceName.
    expect(PersonalAccessToken::query()->where('tokenable_id', $user->id)->count())->toBe(1);
    expect(PersonalAccessToken::query()->where('tokenable_id', $user->id)->first()->name)->toBe($this->deviceName);

    // No web session established for the mobile branch.
    expect(Auth::check())->toBeFalse();
});
