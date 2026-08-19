<?php

/**
 * ChangePasswordMobileTest (Feature/Api) — screen-004--ganti-password-mobile
 * / usecase-004--ganti-password-mobile.
 *
 * Integration tests for PATCH /api/me/password
 * (App\Http\Controllers\Api\AuthController::changePassword()), one per
 * test_scenarios' api_test step — same route as screen-003's
 * ChangePasswordWebTest.php (see routes/api.php's merge-decision comment:
 * the route now runs 'auth:web,sanctum' + 'role:admin,supervisor,
 * mill_management,operator' so it serves both the web-session requester
 * (screen-003) and this screen's Sanctum-token mobile requester). This
 * file exercises only the Sanctum-token branch; fixtures/conventions
 * mirror ChangePasswordWebTest.php with the guard swapped.
 *
 * Sanctum auth in tests: no existing Feature test in this codebase
 * authenticates a request as a Sanctum-token user (LoginMobileTest.php
 * tests the token-issuing endpoint itself, unauthenticated). This suite
 * establishes that convention via Laravel\Sanctum\Sanctum::actingAs($user,
 * ['*']) — Sanctum's own test helper, which resolves $request->user() for
 * the 'sanctum' guard exactly as a real `Authorization: Bearer <token>`
 * request would, without needing to mint/parse a real token string.
 *
 * Response shape note (mirrors ChangePasswordWebTest.php /
 * LoginMobileTest.php): shared_decisions.error_format is
 * `{ "message": ..., "errors": {...} }` — no machine-readable error_code
 * field in the body, so these tests assert HTTP status + message text
 * (and, for 422 validation, the `errors` bag) rather than an error_code
 * field.
 *
 * Does NOT re-test unit_test_cases already covered by
 * tests/Unit/Services/AuthServiceChangePasswordTest.php (unchanged, reused
 * as-is by this screen) — only the 4 test_scenarios' api_test steps below,
 * plus route-level auth-guard coverage for the Sanctum branch.
 */

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()
        ->role(UserRole::Operator)
        ->password('OldPass123!')
        ->create();
});

// Scenario: "Ganti Password Mobile — berhasil"
it('returns 200 and persists the new password hash on a valid change (Sanctum)', function () {
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->patchJson('/api/me/password', [
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

// Scenario: "Ganti Password Mobile — berhasil" (supervisor also allowed,
// per the merged role list — same self-service action, no
// mobile-vs-web-only role split for this endpoint).
it('returns 200 for a supervisor authenticated via Sanctum too', function () {
    $supervisor = User::factory()
        ->role(UserRole::Supervisor)
        ->password('OldPass123!')
        ->create();

    Sanctum::actingAs($supervisor, ['*']);

    $response = $this->patchJson('/api/me/password', [
        'old_password' => 'OldPass123!',
        'new_password' => 'NewPass456!',
        'new_password_confirmation' => 'NewPass456!',
    ]);

    $response->assertOk();
    $response->assertJson([
        'message' => 'Password berhasil diubah.',
    ]);
});

// Scenario: "Ganti Password Mobile — Password Lama Salah"
it('returns 422 OLD_PASSWORD_INCORRECT when the old password is wrong (Sanctum)', function () {
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->patchJson('/api/me/password', [
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

// Scenario: "Ganti Password Mobile — Password Baru Tidak Memenuhi Format"
it('returns 422 VALIDATION_ERROR when the new password format is invalid (Sanctum)', function () {
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->patchJson('/api/me/password', [
        'old_password' => 'OldPass123!',
        'new_password' => 'abc',
        'new_password_confirmation' => 'abc',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['new_password']);

    // Password unchanged.
    expect(Hash::check('OldPass123!', $this->user->fresh()->password_hash))->toBeTrue();
});

// Scenario: "Ganti Password Mobile — Konfirmasi Tidak Cocok"
it('returns 422 PASSWORD_CONFIRMATION_MISMATCH when the confirmation does not match (Sanctum)', function () {
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->patchJson('/api/me/password', [
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
// a request with no Sanctum token and no web session must not reach
// AuthService at all.
it('returns 401 when there is no authenticated Sanctum token or session', function () {
    $response = $this->patchJson('/api/me/password', [
        'old_password' => 'OldPass123!',
        'new_password' => 'NewPass456!',
        'new_password_confirmation' => 'NewPass456!',
    ]);

    $response->assertStatus(401);
});
