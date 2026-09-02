<?php

/**
 * ProductionProcessActivityTest (Feature) — screen-035--production-process-
 * activity-web / usecase-035--production-process-activity-web.
 *
 * This screen has no controller/service/API (pure static Blade view per
 * tech-spec v1) — tests exercise the real route -> 'auth' + 'role'
 * middleware -> view chain directly.
 *
 * Threshing/Pressing/Depricarping/Kernel Plant (promoted to active
 * 2026-08-23) were temporarily hidden from this grid by product decision,
 * then re-enabled in stages: Threshing/Pressing on 2026-08-25, Depricarping/
 * Kernel Plant on 2026-08-28. All 7 active station tiles now render.
 *
 * 2026-08-31 — 6 more former placeholders promoted to active (Clarification,
 * Boiler Room, Effluent Plant, Engine Room, Process Water, Storage Tank),
 * plus 4 brand-new active tiles with no Data Browser screen yet at the time
 * (Solid Waste Disposal, Kernel Dispatch, CPO Dispatch, Process Quality
 * Control) — 17 active tiles total. 9 of these initially had no registered
 * route, so they rendered as active-styled tiles pointing to
 * `javascript:void(0)` rather than a real `route()` href. Solid Waste
 * Disposal's Data Browser screen (screen-091), Process Water's Data Browser
 * screen (screen-092), Kernel Dispatch's Data Browser screen (screen-093),
 * CPO Dispatch's Data Browser screen (screen-094), and Effluent Plant's Data
 * Browser screen (screen-095) have since been implemented and are now
 * routed like the original 7. Storage Tank's Data Browser screen
 * (screen-096) has since also been implemented and routed. Engine Room's
 * Data Browser screen (screen-097) has since also been implemented and
 * routed. Boiler Room's Data Browser screen (screen-098) has since also
 * been implemented and routed. Clarification's Data Browser screen
 * (screen-099) has since also been implemented and routed. Process Quality
 * Control's Data Browser screen (screen-100) has since also been
 * implemented and routed — all 17 active tiles are now routed to a real
 * Data Browser screen; the "still-unrouted" scenario this test used to
 * cover no longer applies to any tile.
 *
 * 2026-09-01 — 'Loading Ramp' placeholder removed entirely: it turned out
 * to be a duplicate name for the already-active Cages Track station, not
 * a distinct station. Only Sterilizer remained placeholder at that point.
 *
 * 2026-09-01 (final promotion) — Sterilizer promoted to a fully active
 * tile, routed to its own Data Browser screen (screen-124). This was the
 * LAST remaining placeholder — 18 active tiles total, 0 placeholders. The
 * "Klik Stasiun Disabled" scenario this test used to cover no longer
 * applies to any tile — repurposed below into an assertion that no
 * placeholder/disabled tile renders at all.
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
it('berhasil: renders the 10 visible active station tiles linking to their Data Browser routes, all routed', function () {
    $response = $this->actingAs($this->supervisor, 'web')->get('/production-process-activity');

    $response->assertOk();
    $response->assertSee(route('data.weighbridge'), false);
    $response->assertSee(route('data.grading'), false);
    $response->assertSee(route('data.cages-track'), false);
    $response->assertSee(route('data.threshing'), false);
    $response->assertSee(route('data.pressing'), false);
    $response->assertSee(route('data.depricarping'), false);
    $response->assertSee(route('data.kernel-plant'), false);
    $response->assertSee(route('data.boiler-room'), false);
    $response->assertSee(route('data.clarification'), false);
    $response->assertSee(route('data.sterilizer'), false);
    $response->assertSee('Weighbridge');
    $response->assertSee('Grading');
    $response->assertSee('Cages Track');
    $response->assertSee('Threshing');
    $response->assertSee('Pressing');
    $response->assertSee('Depricarping');
    $response->assertSee('Kernel Plant');
    $response->assertSee('Boiler Room');
    $response->assertSee('Clarification');
    $response->assertSee('Sterilizer');
    // No tile links to the javascript:void(0) placeholder any more — every
    // visible tile has a real route() href (see the assertions above).
    $response->assertDontSee('javascript:void(0)', false);
});

// Regression: a 2026-09-01 edit embedded literal '{{-- --}}' characters
// inside a Blade comment's own prose ("Remove the surrounding {{-- --}}
// comment markers..."), which terminated that comment early — Blade
// comments don't nest — and leaked the rest of the comment body as literal
// page text ("comment markers to re-enable a given tile. --}}"). Fixed by
// rewording the prose to not contain literal comment-delimiter characters.
it('never leaks raw Blade comment delimiters or comment prose into the rendered page', function () {
    $response = $this->actingAs($this->supervisor, 'web')->get('/production-process-activity');

    $response->assertOk();
    $response->assertDontSee('{{--', false);
    $response->assertDontSee('--}}', false);
    $response->assertDontSee('comment markers');
});

// Scenario: 2026-09-01 (hide) — 8 stations temporarily hidden from this
// grid per product decision. They remain fully active/functional
// (own Data Browser screens still resolve if visited directly, covered by
// their own screen tests) — this only asserts they don't render HERE.
it('does not render the 8 temporarily-hidden station tiles on this grid', function () {
    $response = $this->actingAs($this->supervisor, 'web')->get('/production-process-activity');

    $response->assertOk();
    $response->assertDontSee('Engine Room');
    $response->assertDontSee('Storage Tank');
    $response->assertDontSee('Effluent Plant');
    $response->assertDontSee('CPO Dispatch');
    $response->assertDontSee('Kernel Dispatch');
    $response->assertDontSee('Process Water');
    $response->assertDontSee('Solid Waste Disposal');
    $response->assertDontSee('Process Quality Control');
});

// Scenario: "Pilih Stasiun (Web) — Klik Stasiun Disabled" — repurposed
// 2026-09-01: 0 placeholders remain (Sterilizer was the last one promoted),
// so there is nothing left to click-disabled. This now asserts the
// negative: no placeholder/disabled tile renders at all.
it('renders 0 placeholder tiles — no disabled station tile remains anywhere on the grid', function () {
    $response = $this->actingAs($this->supervisor, 'web')->get('/production-process-activity');

    $response->assertOk();
    $response->assertDontSee('Belum tersedia');
    $response->assertDontSee('Loading Ramp');
    $response->assertDontSeeHtml('class="station-tile disabled"');
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
