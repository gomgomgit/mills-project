<?php

/**
 * AuthServiceTest — screen-001--login-web / usecase-001--login-web.
 *
 * Unit tests for App\Services\AuthService::login(), covering all 7
 * unit_test_cases derived from the screen's business_logic (steps 1-7).
 *
 * Pragmatic deviation from test_strategy.unit_test.mock_policy ("mock all
 * I/O"): AuthService looks up the user via `User::where(...)->first()`
 * (Eloquent, a facade/static call) rather than through an injectable
 * repository abstraction — none exists in this codebase. Mocking Eloquent
 * statics in Pest is impractical, so this suite instead binds the plain
 * Pest "Unit" test file to Tests\TestCase + RefreshDatabase (sqlite
 * in-memory, per phpunit.xml) and seeds minimal fixtures via model
 * factories. This keeps the test fast/isolated in practice (in-memory DB,
 * no network I/O) while exercising the real Eloquent query, which is the
 * behavior actually worth covering here. See known_issues in the
 * test-writer-agent report for the full rationale.
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
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->authService = new AuthService();
    $this->businessUnit = BusinessUnit::factory()->create();
});

it('throws a 422 ValidationException when a required field is missing', function () {
    // username missing
    expect(fn () => $this->authService->login('', 'Passw0rd!', $this->businessUnit->id))
        ->toThrow(ValidationException::class);

    // password missing
    expect(fn () => $this->authService->login('someuser', '', $this->businessUnit->id))
        ->toThrow(ValidationException::class);

    // business_unit_id is intentionally NOT required — see the
    // auto-derive tests below (an operator/supervisor/mill_management
    // account already has one assigned, so the caller no longer has to
    // send it).
});

it('throws a 422 ValidationException when the password format is invalid', function (string $badPassword) {
    expect(fn () => $this->authService->login('someuser', $badPassword, $this->businessUnit->id))
        ->toThrow(ValidationException::class);
})->with([
    'too short' => ['Ab1!'],
    'no symbol' => ['abcdef1'],
    'no alphanumeric' => ['!!!!!!'],
]);

it('throws InvalidCredentialsException (401) when the username is not found', function () {
    expect(fn () => $this->authService->login('does-not-exist', 'Passw0rd!', $this->businessUnit->id))
        ->toThrow(InvalidCredentialsException::class);
});

it('throws InvalidCredentialsException (401) when the password does not match', function () {
    $user = User::factory()
        ->password('CorrectPass1!')
        ->forBusinessUnit($this->businessUnit)
        ->create();

    expect(fn () => $this->authService->login($user->username, 'WrongPass1!', $this->businessUnit->id))
        ->toThrow(InvalidCredentialsException::class);
});

it('throws AccountInactiveException (403) when the account is inactive', function () {
    $user = User::factory()
        ->password('Passw0rd!')
        ->forBusinessUnit($this->businessUnit)
        ->inactive()
        ->create();

    expect(fn () => $this->authService->login($user->username, 'Passw0rd!', $this->businessUnit->id))
        ->toThrow(AccountInactiveException::class);
});

it('throws BusinessAreaMismatchException (403) when business_unit_id does not match the user', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();

    $user = User::factory()
        ->password('Passw0rd!')
        ->forBusinessUnit($this->businessUnit)
        ->create();

    expect(fn () => $this->authService->login($user->username, 'Passw0rd!', $otherBusinessUnit->id))
        ->toThrow(BusinessAreaMismatchException::class);
});

it('auto-derives business_unit_id from the account when it is omitted (null)', function () {
    $user = User::factory()
        ->password('Passw0rd!')
        ->forBusinessUnit($this->businessUnit)
        ->create();

    $result = $this->authService->login($user->username, 'Passw0rd!', null);

    expect($result['business_unit']['id'])->toBe($this->businessUnit->id);
});

it('throws BusinessAreaMismatchException (403) when business_unit_id is omitted and the account has none assigned', function () {
    $user = User::factory()
        ->password('Passw0rd!')
        ->create(['business_unit_id' => null]);

    expect(fn () => $this->authService->login($user->username, 'Passw0rd!', null))
        ->toThrow(BusinessAreaMismatchException::class);
});

it('returns success payload and establishes a session when all conditions pass', function () {
    $user = User::factory()
        ->password('Passw0rd!')
        ->role(UserRole::Supervisor)
        ->forBusinessUnit($this->businessUnit)
        ->create();

    $result = $this->authService->login($user->username, 'Passw0rd!', $this->businessUnit->id);

    expect($result)->toMatchArray([
        'user' => [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'role' => 'supervisor',
            'business_unit_id' => $this->businessUnit->id,
        ],
        'business_unit' => [
            'id' => $this->businessUnit->id,
            'name' => $this->businessUnit->name,
        ],
        'redirect_to' => '/dashboard',
    ]);

    expect(Auth::check())->toBeTrue();
    expect(Auth::id())->toBe($user->id);
});
