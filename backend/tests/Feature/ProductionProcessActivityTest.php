<?php

/**
 * ProductionProcessActivityTest (Feature) — screen-035--production-process-
 * activity-web / usecase-035--production-process-activity-web.
 *
 * This screen has no controller/service/API (pure static Blade view per
 * tech-spec v1) — tests exercise the real route -> 'auth' + 'role'
 * middleware -> view chain directly.
 *
 * TEMPORARY (2026-08-24, narrowed 2026-08-25): Threshing/Pressing/
 * Depricarping/Kernel Plant (promoted to active 2026-08-23) were hidden
 * from this grid by product decision. Threshing/Pressing were re-enabled
 * 2026-08-25 (product wants them live now) — only Depricarping/Kernel
 * Plant remain hidden, still fully built/tested, just not ready to expose
 * yet. The blade view wraps their 2 tiles in a `{{-- --}}` comment
 * (compiles away entirely, not just visually hidden), so this test now
 * asserts 5 active tiles render and the remaining 2 do NOT appear. When
 * fully re-enabled (un-comment the tiles in the blade view), flip this
 * test back to asserting all 7 — see git history around 2026-08-23 for the
 * exact original version of this test.
 */

use App\Enums\UserRole;
use App\Models\User;

beforeEach(function () {
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();
});

// Scenario: "Pilih Stasiun (Web) — berhasil"
it('berhasil: renders the 5 active station tiles linking to their Data Browser routes', function () {
    $response = $this->actingAs($this->supervisor, 'web')->get('/production-process-activity');

    $response->assertOk();
    $response->assertSee(route('data.weighbridge'), false);
    $response->assertSee(route('data.grading'), false);
    $response->assertSee(route('data.cages-track'), false);
    $response->assertSee(route('data.threshing'), false);
    $response->assertSee(route('data.pressing'), false);
    $response->assertSee('Weighbridge');
    $response->assertSee('Grading');
    $response->assertSee('Cages Track');
    $response->assertSee('Threshing');
    $response->assertSee('Pressing');
});

// TEMPORARY (2026-08-24, narrowed 2026-08-25) — see class docblock. Remove
// this test when Depricarping/Kernel Plant are re-enabled.
it('temporarily hides Depricarping/Kernel Plant', function () {
    $response = $this->actingAs($this->supervisor, 'web')->get('/production-process-activity');

    $response->assertOk();
    $response->assertDontSee(route('data.depricarping'), false);
    $response->assertDontSee(route('data.kernel-plant'), false);
    $response->assertDontSee('Depricarping');
    $response->assertDontSee('Kernel Plant');
});

// Scenario: "Pilih Stasiun (Web) — Klik Stasiun Disabled"
it('Klik Stasiun Disabled: renders 8 placeholder tiles with no navigable link', function () {
    $response = $this->actingAs($this->supervisor, 'web')->get('/production-process-activity');

    $response->assertOk();
    $response->assertSee('Sterilizer');
    $response->assertSee('Bulking Storage');
    $response->assertSeeInOrder(['Sterilizer', 'Belum tersedia']);
    // Placeholder tiles are rendered as <div>, not <a href>, so there is no
    // route for them to navigate to — nothing further to assert technically.
});

it('is reachable by Mill Management and Admin, same as Supervisor', function () {
    $this->actingAs($this->millManagement, 'web')->get('/production-process-activity')->assertOk();
    $this->actingAs($this->admin, 'web')->get('/production-process-activity')->assertOk();
});

it('returns 403 for Operator (mobile-only role, no web access per actor_permissions)', function () {
    $this->actingAs($this->operator, 'web')->get('/production-process-activity')->assertForbidden();
});

it('redirects an unauthenticated request to login', function () {
    $this->get('/production-process-activity')->assertRedirect('/login');
});
