<?php

/**
 * LoginWebTest (Feature/Api) — screen-001--login-web / usecase-001--login-web.
 *
 * Integration tests for POST /api/login (App\Http\Controllers\Api\AuthController),
 * one per test_scenarios' api_test step. Exercises the real route -> controller
 * -> AuthService -> Eloquent chain against the sqlite in-memory testing DB
 * (RefreshDatabase, bound in tests/Pest.php for the Feature suite).
 *
 * Response shape note: shared_decisions.error_format is
 * `{ "message": ..., "errors": {...} }` — ApiExceptionHandler does not put a
 * machine-readable error_code in the response body (App\Exceptions\ErrorCodes
 * is documented as "not transmitted in the response body itself"). So these
 * tests assert HTTP status + the exception's message text (and, for 422, the
 * `errors` bag) rather than an error_code field. See known_issues.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
});

// Scenario: "Login Web — berhasil"
it('returns 200 with user, business_unit and redirect_to on valid login', function () {
    $user = User::factory()
        ->password('Passw0rd!')
        ->role(UserRole::Supervisor)
        ->forBusinessUnit($this->businessUnit)
        ->create();

    $response = $this->postJson('/api/login', [
        'username' => $user->username,
        'password' => 'Passw0rd!',
        'business_unit_id' => $this->businessUnit->id,
    ]);

    $response->assertOk();
    $response->assertJson([
        'user' => [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'role' => 'supervisor',
        ],
        'business_unit' => [
            'id' => $this->businessUnit->id,
            'name' => $this->businessUnit->name,
        ],
        'redirect_to' => '/dashboard',
    ]);
});

// Scenario: "Login Web — berhasil tanpa mengirim business_unit_id"
// (2026-08-20: the "Business Area" picker was removed from the web login
// form — the client no longer sends this field at all; it's auto-derived
// server-side from the account, mirroring the earlier mobile removal.)
it('auto-derives business_unit_id from the account when the field is omitted', function () {
    $user = User::factory()
        ->password('Passw0rd!')
        ->role(UserRole::Supervisor)
        ->forBusinessUnit($this->businessUnit)
        ->create();

    $response = $this->postJson('/api/login', [
        'username' => $user->username,
        'password' => 'Passw0rd!',
    ]);

    $response->assertOk();
    $response->assertJson([
        'business_unit' => [
            'id' => $this->businessUnit->id,
            'name' => $this->businessUnit->name,
        ],
        'redirect_to' => '/dashboard',
    ]);
});

// Scenario: "Login Web — Admin tanpa business unit berhasil login"
// Admin has no business_unit_id assigned (unrestricted across mills, by
// design) — this is expected, not an error; 'business_unit' is null in the
// response rather than the request being rejected.
it('lets an Admin (no business unit assigned) log in with a null business_unit in the response', function () {
    $admin = User::factory()
        ->password('Passw0rd!')
        ->role(UserRole::Admin)
        ->create(['business_unit_id' => null]);

    $response = $this->postJson('/api/login', [
        'username' => $admin->username,
        'password' => 'Passw0rd!',
    ]);

    $response->assertOk();
    $response->assertJson([
        'user' => ['role' => 'admin', 'business_unit_id' => null],
        'business_unit' => null,
        'redirect_to' => '/dashboard',
    ]);
});

// Scenario: "Login Web — Kredensial Salah"
it('returns 401 when the password is wrong', function () {
    $user = User::factory()
        ->password('Passw0rd!')
        ->forBusinessUnit($this->businessUnit)
        ->create();

    $response = $this->postJson('/api/login', [
        'username' => $user->username,
        'password' => 'WrongPass1!',
        'business_unit_id' => $this->businessUnit->id,
    ]);

    $response->assertStatus(401);
    $response->assertJson([
        'message' => 'Username atau password salah.',
    ]);
});

// Scenario: "Login Web — Akun Dinonaktifkan"
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
    ]);

    $response->assertStatus(403);
    $response->assertJson([
        'message' => 'Akun tidak aktif, hubungi Admin.',
    ]);
});

// Scenario: "Login Web — Business Area Tidak Sesuai"
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
    ]);

    $response->assertStatus(403);
    $response->assertJson([
        'message' => 'Business area yang dipilih tidak sesuai dengan akses Anda.',
    ]);
});

// Scenario: "Login Web — Format Password Tidak Valid"
it('returns 422 with a validation error when the password format is invalid', function () {
    $user = User::factory()
        ->forBusinessUnit($this->businessUnit)
        ->create();

    $response = $this->postJson('/api/login', [
        'username' => $user->username,
        'password' => 'abc',
        'business_unit_id' => $this->businessUnit->id,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password']);
});
