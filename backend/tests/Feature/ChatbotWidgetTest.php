<?php

/**
 * ChatbotWidgetTest (Feature) — resources/views/components/
 * chatbot-widget.blade.php ("Mills AI").
 *
 * Not tied to a screen tech-spec — mounted globally in the shared shell
 * (components/layouts/app.blade.php), same footing as FloatingClock.vue
 * on mobile. Covers only what's server-rendered: the widget markup itself
 * renders on an authenticated page, and config/millsai.php's values reach
 * the browser via the inline Alpine @js() config. The actual chat
 * behavior (auth token flow, send/receive, markdown render, history,
 * reset) is client-side JS against an external Aivena API — not
 * exercised here (no PHP HTTP boundary to test against; would need a
 * browser/JS test, out of scope for this Feature suite).
 *
 * Uses FAKE config values (not the real MILLS_AI_* from .env) — this only
 * proves the config('millsai.*') -> @js() wiring works, so the real
 * project_id/email/password never need to appear in this file (tracked in
 * git, unlike .env).
 */

use App\Enums\UserRole;
use App\Models\User;

beforeEach(function () {
    config([
        'millsai.base_url' => 'https://fake-chatbot-api.test/api/v1',
        'millsai.project_id' => 'fake-project-id-0000',
        'millsai.auth_email' => 'fake@example.test',
        'millsai.auth_password' => 'fake-password',
    ]);

    $this->user = User::factory()->role(UserRole::Supervisor)->create();
});

it('renders the Mills AI widget bubble on an authenticated page', function () {
    $response = $this->actingAs($this->user, 'web')->get('/dashboard');

    $response->assertOk();
    $response->assertSee('data-testid="chatbot-widget-bubble"', false);
    $response->assertSee('Mills AI');
});

it('embeds the configured project_id and base_url into the inline widget config', function () {
    $response = $this->actingAs($this->user, 'web')->get('/dashboard');

    // @js() renders as JSON.parse('...') with slashes/quotes escaped for a
    // JS string literal — assert on substrings that survive that escaping
    // rather than the exact escaped form (fragile/implementation-specific).
    $response->assertSee('fake-project-id-0000');
    $response->assertSee('fake-chatbot-api.test');
});

it('does not render the widget on the unauthenticated Login page', function () {
    $response = $this->get('/login');

    $response->assertOk();
    $response->assertDontSee('data-testid="chatbot-widget-bubble"', false);
});
