<?php

/**
 * ChangePasswordWebTest (Feature/Api) — screen-003--ganti-password-web /
 * usecase-003--ganti-password-web.
 *
 * Integration tests for PATCH /api/me/password
 * (App\Http\Controllers\Api\AuthController::changePassword()), one per
 * test_scenarios' api_test step. Exercises the real route -> 'auth:web' +
 * 'role' middleware -> controller -> AuthService -> Eloquent chain against
 * the sqlite in-memory testing DB (RefreshDatabase, bound in tests/Pest.php
 * for the Feature suite).
 *
 * Session auth: authenticated via $this->actingAs($user, 'web') (matches
 * config/auth.php's 'web' session guard, the same guard AuthController::
 * changePassword() reads via $request->user('web')) — Laravel's
 * actingAs() resolves the guard's user directly for the test request, so
 * it exercises the real 'auth:web' middleware without needing a real
 * session cookie round trip.
 *
 * Response shape note (mirrors LoginWebTest.php): shared_decisions.error_
 * format is `{ "message": ..., "errors": {...} }` — ApiExceptionHandler
 * does not put a machine-readable error_code in the response body, so
 * these tests assert HTTP status + message text (and, for 422 validation,
 * the `errors` bag) rather than an error_code field.
 */

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->user = User::factory()
        ->role(UserRole::Supervisor)
        ->password('OldPass123!')
        ->create();
});

// Scenario: "Ganti Password Web — success"
it('returns 200 and persists the new password hash on a valid change', function () {
    $response = $this->actingAs($this->user, 'web')->patchJson('/api/me/password', [
        'old_password' => 'OldPass123!',
        'new_password' => 'NewPass456!',
        'new_password_confirmation' => 'NewPass456!',
    ]);

    $response->assertOk();
    $response->assertJson([
        'message' => 'Password berhasil diubah.',
    ]);

    $fresh = $this->user->fresh();
    expect(Hash::check('NewPass456!', $fresh->password_hash))->toBeTrue();
    expect(Hash::check('OldPass123!', $fresh->password_hash))->toBeFalse();
});

// Scenario: "Ganti Password Web — Password Lama Salah"
it('returns 422 OLD_PASSWORD_INCORRECT when the old password is wrong', function () {
    $response = $this->actingAs($this->user, 'web')->patchJson('/api/me/password', [
        'old_password' => 'WrongOldPass1!',
        'new_password' => 'NewPass456!',
        'new_password_confirmation' => 'NewPass456!',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'message' => 'Password lama salah.',
    ]);

    // Password unchanged.
    expect(Hash::check('OldPass123!', $this->user->fresh()->password_hash))->toBeTrue();
});

// Scenario: "Ganti Password Web — Password Baru Tidak Memenuhi Format"
it('returns 422 VALIDATION_ERROR when the new password format is invalid', function () {
    $response = $this->actingAs($this->user, 'web')->patchJson('/api/me/password', [
        'old_password' => 'OldPass123!',
        'new_password' => 'abc',
        'new_password_confirmation' => 'abc',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['new_password']);

    // Password unchanged.
    expect(Hash::check('OldPass123!', $this->user->fresh()->password_hash))->toBeTrue();
});

// Scenario: "Ganti Password Web — Konfirmasi Tidak Cocok"
it('returns 422 PASSWORD_CONFIRMATION_MISMATCH when the confirmation does not match', function () {
    $response = $this->actingAs($this->user, 'web')->patchJson('/api/me/password', [
        'old_password' => 'OldPass123!',
        'new_password' => 'NewPass456!',
        'new_password_confirmation' => 'Different789!',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'message' => 'Konfirmasi password tidak cocok dengan password baru.',
    ]);

    // Password unchanged.
    expect(Hash::check('OldPass123!', $this->user->fresh()->password_hash))->toBeTrue();
});

// Auth-guard coverage (route-level, in addition to the 4 scenarios above):
// unauthenticated requests must not reach AuthService at all.
it('returns 401 when there is no authenticated session', function () {
    $response = $this->patchJson('/api/me/password', [
        'old_password' => 'OldPass123!',
        'new_password' => 'NewPass456!',
        'new_password_confirmation' => 'NewPass456!',
    ]);

    $response->assertStatus(401);
});

// Actor-permission coverage: as of screen-004--ganti-password-mobile's
// implementation, this route's role list was DELIBERATELY merged to
// 'admin,supervisor,mill_management,operator' (see routes/api.php's "MERGE
// DECISION" comment) — self-service password change (requester IS the
// target user) is allowed for ANY authenticated role, so an operator is no
// longer rejected with 403 here. This test previously asserted the OLD
// (pre-screen-004) role restriction (403 for operator); it is updated,
// one-time, by test-writer-agent during screen-004's initial test-writing
// pass to match that documented decision — see this screen's known_issues
// for the rationale. All other tests in this file are unchanged.
it('returns 200 when the authenticated user is an operator (role list merged for screen-004)', function () {
    $operator = User::factory()
        ->role(UserRole::Operator)
        ->password('OldPass123!')
        ->create();

    $response = $this->actingAs($operator, 'web')->patchJson('/api/me/password', [
        'old_password' => 'OldPass123!',
        'new_password' => 'NewPass456!',
        'new_password_confirmation' => 'NewPass456!',
    ]);

    $response->assertOk();
    $response->assertJson([
        'message' => 'Password berhasil diubah.',
    ]);
});
