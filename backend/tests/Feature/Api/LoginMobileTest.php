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
