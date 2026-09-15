<?php

/**
 * LoginMobileTest (Feature/Api) — screen-002--login-mobile / usecase-002--login-mobile.
 *
 * Integration tests for POST /api/login when the request body includes
 * `device_name` (App\Http\Controllers\Api\AuthController's mobile branch),
 * one per test_scenarios' api_test step. Exercises the real route ->
 * controller -> AuthService -> Eloquent -> Sanctum chain against the sqlite
 * in-memory testing DB (RefreshDatabase, bound in tests/Pest.php for the
 * Feature suite) — same route as screen-001's LoginWebTest.php
 * (POST /api/login is shared; the presence of `device_name` selects this
 * screen's branch), so fixtures/conventions mirror that file.
 *
 * Response shape note: same as LoginWebTest.php — shared_decisions
 * .error_format is `{ "message": ..., "errors": {...} }`, no machine
 * -readable error_code field in the body, so these tests assert HTTP status
 * + message text (and, for 422, the `errors` bag) rather than an
 * error_code field. See known_issues.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
});

// Scenario: "Login Mobile — berhasil"
it('returns 200 with user, business_unit and token on valid mobile login', function () {
    $user = User::factory()
        ->password('Passw0rd!')
        ->forBusinessUnit($this->businessUnit)
        ->create(['username' => 'operator01']);

    $response = $this->postJson('/api/login', [
        'username' => $user->username,
        'password' => 'Passw0rd!',
        'business_unit_id' => $this->businessUnit->id,
        'device_name' => 'Samsung A54 - Operator',
    ]);

    $response->assertOk();
    $response->assertJson([
        'user' => [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'role' => $user->role->value,
        ],
        'business_unit' => [
            'id' => $this->businessUnit->id,
            'name' => $this->businessUnit->name,
        ],
    ]);
    $response->assertJsonMissing(['redirect_to']);
    expect($response->json('token'))->toBeString()->not->toBeEmpty();
});

// business_unit_id is no longer collected client-side (mobile/src/
// components/LoginForm.vue) — an operator/supervisor/mill_management
// account already has one assigned, so AuthService::login() auto-derives
// it when the request omits the field entirely.
it('auto-derives business_unit_id from the account when the field is omitted', function () {
    $user = User::factory()
        ->password('Passw0rd!')
        ->forBusinessUnit($this->businessUnit)
        ->create(['username' => 'operator01']);

    $response = $this->postJson('/api/login', [
        'username' => $user->username,
        'password' => 'Passw0rd!',
        'device_name' => 'Samsung A54 - Operator',
    ]);

    $response->assertOk();
    $response->assertJson([
        'business_unit' => [
            'id' => $this->businessUnit->id,
            'name' => $this->businessUnit->name,
        ],
    ]);
});

it('returns 403 when business_unit_id is omitted and the account has none assigned', function () {
    $user = User::factory()
        ->password('Passw0rd!')
        ->create(['username' => 'orphanuser', 'business_unit_id' => null]);

    $response = $this->postJson('/api/login', [
        'username' => $user->username,
        'password' => 'Passw0rd!',
        'device_name' => 'Samsung A54 - Operator',
    ]);

    $response->assertStatus(403);
});

// Scenario: "Login Mobile — Kredensial Salah"
it('returns 401 when the password is wrong', function () {
    $user = User::factory()
        ->password('Passw0rd!')
        ->forBusinessUnit($this->businessUnit)
        ->create();

    $response = $this->postJson('/api/login', [
        'username' => $user->username,
        'password' => 'WrongPass1!',
        'business_unit_id' => $this->businessUnit->id,
        'device_name' => 'Samsung A54 - Operator',
    ]);

    $response->assertStatus(401);
    $response->assertJson([
        'message' => 'Username atau password salah.',
    ]);
});

// Scenario: "Login Mobile — Akun Dinonaktifkan"
it('returns 403 when the account is inactive', function () {
    $user = User::factory()
        ->password('Passw0rd!')
        ->forBusinessUnit($this->businessUnit)
        ->inactive()
        ->create();

    $response = $this->postJson('/api/login', [
        'username' => $user->username,
        'password' => 'Passw0rd!',
        'business_unit_id' => $this->businessUnit->id,
        'device_name' => 'Samsung A54 - Operator',
    ]);

    $response->assertStatus(403);
    $response->assertJson([
        'message' => 'Akun tidak aktif, hubungi Admin.',
    ]);
});

// Scenario: "Login Mobile — Password Tidak Memenuhi Format Minimum"
it('returns 422 with a validation error when the password format is invalid', function () {
    $user = User::factory()
        ->forBusinessUnit($this->businessUnit)
        ->create();

    $response = $this->postJson('/api/login', [
        'username' => $user->username,
        'password' => 'abc',
        'business_unit_id' => $this->businessUnit->id,
        'device_name' => 'Samsung A54 - Operator',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password']);
});

// Scenario: "Login Mobile — Business Area Tidak Sesuai Penugasan"
it('returns 403 when business_unit_id does not match the user', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();

    $user = User::factory()
        ->password('Passw0rd!')
        ->forBusinessUnit($this->businessUnit)
        ->create();

    $response = $this->postJson('/api/login', [
        'username' => $user->username,
        'password' => 'Passw0rd!',
        'business_unit_id' => $otherBusinessUnit->id,
        'device_name' => 'Samsung A54 - Operator',
    ]);

    $response->assertStatus(403);
    $response->assertJson([
        'message' => 'Business area yang dipilih tidak sesuai dengan akses Anda.',
    ]);
});

// Product decision 2026-09-14 — Mill Management explicitly joins Station
// Operator and Supervisor as a mobile role.
//
// This used to work only by ACCIDENT: AuthService never gated the mobile
// branch by role at all, so every role could obtain a token while the
// actor-index documented mobile as Operator + Supervisor only. This test
// makes the allowance deliberate, so a future "lock mobile down to its
// documented roles" change has to confront Mill Management on purpose
// rather than silently locking them out.
it('issues a mobile token for every role that is meant to use the app', function (UserRole $role) {
    $user = User::factory()
        ->role($role)
        ->password('Passw0rd!')
        ->forBusinessUnit($this->businessUnit)
        ->create();

    $response = $this->postJson('/api/login', [
        'username' => $user->username,
        'password' => 'Passw0rd!',
        'device_name' => 'Samsung A54',
    ]);

    $response->assertOk();
    $response->assertJsonPath('user.role', $role->value);
    expect($response->json('token'))->toBeString()->not->toBeEmpty();
})->with([
    'operator' => UserRole::Operator,
    'supervisor' => UserRole::Supervisor,
    'mill management' => UserRole::MillManagement,
]);

// Admin is the one role with no business unit of its own — it still logs in
// (unrestricted across mills by design, see AuthService), just with no
// business_unit payload. Kept separate from the dataset above because the
// factory setup differs, not because the outcome does.
it('issues a mobile token for an Admin, with no business unit attached', function () {
    $user = User::factory()->role(UserRole::Admin)->password('Passw0rd!')->create(['business_unit_id' => null]);

    $response = $this->postJson('/api/login', [
        'username' => $user->username,
        'password' => 'Passw0rd!',
        'device_name' => 'Samsung A54',
    ]);

    $response->assertOk();
    $response->assertJsonPath('user.role', UserRole::Admin->value);
    $response->assertJsonPath('business_unit', null);
});
