<?php

/**
 * LogoutTest (Feature) — web session logout.
 *
 * Not tied to a screen tech-spec (no screen-XXX/usecase-XXX entry) —
 * infrastructure route registered outside the ASDLC-managed block in
 * routes/web.php, same footing as /health. Added 2026-08-24: the shared
 * shell (components/layouts/app.blade.php) had no logout affordance at
 * all — every authenticated web screen showed the user's name in the
 * header with no way to sign out short of clearing cookies manually.
 *
 * Exercises the real route -> 'auth' middleware -> closure handler chain
 * (POST /logout, Auth::guard('web')->logout() + session invalidate +
 * regenerateToken(), redirect to Login) directly, same approach as
 * ProductionProcessActivityTest.php for a Blade/closure-only route.
 */

use App\Enums\UserRole;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->role(UserRole::Supervisor)->create();
});

it('logs the user out and redirects to Login', function () {
    $response = $this->actingAs($this->user, 'web')->post('/logout');

    $response->assertRedirect('/login');
    $this->assertGuest('web');
});

it('invalidates the session so a subsequent authenticated request is rejected', function () {
    $this->actingAs($this->user, 'web')->post('/logout');

    $this->get('/dashboard')->assertRedirect('/login');
});

it('renders a Logout button in the shared shell for an authenticated user', function () {
    $response = $this->actingAs($this->user, 'web')->get('/dashboard');

    $response->assertOk();
    $response->assertSee('data-testid="logout-button"', false);
    $response->assertSee('Logout');
});

it('redirects an unauthenticated POST /logout to Login rather than erroring', function () {
    $this->post('/logout')->assertRedirect('/login');
});
